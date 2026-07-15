<?php
/**
 * EgoSMS helper + phone utilities.
 * Credentials & toggles live in conn/config.php (or .env).
 */

require_once __DIR__ . '/config.php';

function normalize_ug_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null || $digits === '') {
        return '';
    }

    // 07XXXXXXXX / 7XXXXXXXX → 2567XXXXXXXX
    if (preg_match('/^0(7\d{8})$/', $digits, $m)) {
        return '256' . $m[1];
    }
    if (preg_match('/^(7\d{8})$/', $digits, $m)) {
        return '256' . $m[1];
    }
    // Already international without +
    if (preg_match('/^2567\d{8}$/', $digits)) {
        return $digits;
    }
    // Generic: keep digits if long enough
    if (strlen($digits) >= 10 && strlen($digits) <= 15) {
        return $digits;
    }
    return '';
}

function format_sms_message(string $template, array $vars): string {
    $message = $template;
    foreach ($vars as $key => $value) {
        $message = str_replace('{' . $key . '}', (string) $value, $message);
    }
    $maxLen = (int) cfg('sms.max_length', 160);
    if ($maxLen > 0 && mb_strlen($message) > $maxLen) {
        $message = mb_substr($message, 0, max(1, $maxLen - 3)) . '...';
    }
    return $message;
}

/**
 * Send SMS via EgoSMS plain HTTP API.
 * @return array{success:bool,message:string,raw?:string}
 */
function send_egosms(string $number, string $message): array {
    $config = app_config();
    $sms = $config['egosms'] ?? [];

    if (empty($sms['enabled'])) {
        return ['success' => false, 'message' => 'SMS is disabled in config.'];
    }

    $username = trim((string) ($sms['username'] ?? ''));
    $password = (string) ($sms['password'] ?? '');
    $sender = trim((string) ($sms['sender'] ?? 'QRAttend'));
    $endpoint = rtrim((string) ($sms['endpoint'] ?? 'https://www.egosms.co/api/v1/plain/'), '/') . '/';
    $timeout = (int) ($sms['timeout_seconds'] ?? 20);

    if ($username === '' || $username === 'YOUR_EGOSMS_USERNAME' || $password === '' || $password === 'YOUR_EGOSMS_PASSWORD') {
        return ['success' => false, 'message' => 'EgoSMS credentials are not configured. Set them in conn/config.php or .env.'];
    }

    $normalized = normalize_ug_phone($number);
    if ($normalized === '') {
        return ['success' => false, 'message' => 'Invalid phone number.'];
    }

    $query = http_build_query([
        'username' => $username,
        'password' => $password,
        'number' => $normalized,
        'message' => $message,
        'sender' => $sender,
        'priority' => (string) ($sms['priority'] ?? '0'),
    ]);

    $url = $endpoint . '?' . $query;

    $raw = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = (string) curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === '' && $err !== '') {
            return ['success' => false, 'message' => 'SMS request failed: ' . $err];
        }
    } else {
        $context = stream_context_create([
            'http' => ['timeout' => $timeout, 'ignore_errors' => true],
        ]);
        $raw = (string) @file_get_contents($url, false, $context);
        if ($raw === '') {
            return ['success' => false, 'message' => 'SMS request failed (empty response).'];
        }
    }

    $rawTrim = trim($raw);
    $ok = stripos($rawTrim, 'MsgID') !== false
        || stripos($rawTrim, 'OK') === 0
        || stripos($rawTrim, 'Success') !== false;

    // EgoSMS often returns MsgID=... on success, or error text
    if (!$ok && preg_match('/^\d+$/', $rawTrim)) {
        $ok = true; // some accounts return numeric message id only
    }

    return [
        'success' => $ok,
        'message' => $ok ? 'SMS sent successfully.' : ('SMS failed: ' . $rawTrim),
        'raw' => $rawTrim,
    ];
}

function notify_parent_attendance(array $student, string $timeIn): array {
    $config = app_config();
    if (empty($config['sms']['notify_parent_on_attendance'])) {
        return ['success' => false, 'message' => 'Parent SMS notifications are disabled.', 'skipped' => true];
    }

    $phone = trim((string) ($student['parent_phone'] ?? ''));
    if ($phone === '') {
        return ['success' => false, 'message' => 'No parent phone on file.', 'skipped' => true];
    }

    $template = (string) ($config['sms']['attendance_template'] ?? '{student} marked present at {time}.');
    $message = format_sms_message($template, [
        'student' => $student['student_name'] ?? 'Student',
        'course' => $student['course_section'] ?? '',
        'time' => date('h:i A', strtotime($timeIn)),
        'date' => date('M d, Y', strtotime($timeIn)),
        'app' => $config['app_name'] ?? 'QR Attend',
        'parent' => $student['parent_name'] ?? 'Parent',
    ]);

    return send_egosms($phone, $message);
}
?>
