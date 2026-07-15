<?php
/**
 * SMS test utility — CLI or browser (admin session).
 *
 * CLI:
 *   php test-sms.php 0700123456
 *   php test-sms.php 256700123456 "Custom test message"
 *
 * Browser (while logged in as admin):
 *   /attendance/test-sms.php?phone=0700123456
 */
declare(strict_types=1);

require_once __DIR__ . '/conn/config.php';
require_once __DIR__ . '/conn/sms.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/conn/session.php';
    require_once __DIR__ . '/conn/conn.php';
    require_once __DIR__ . '/conn/auth.php';
    header('Content-Type: text/plain; charset=utf-8');
    try {
        require_admin_user($conn);
    } catch (Throwable $e) {
        http_response_code(403);
        echo "Admin login required to run SMS test in the browser.\n";
        exit(1);
    }
}

$phone = $isCli ? ($argv[1] ?? '') : (string) ($_GET['phone'] ?? $_POST['phone'] ?? '');
$message = $isCli
    ? ($argv[2] ?? '')
    : (string) ($_GET['message'] ?? $_POST['message'] ?? '');

$phone = trim($phone);
$message = trim($message);

function print_line(string $text): void
{
    echo $text . PHP_EOL;
}

print_line('=== QR Attend · EgoSMS test ===');
print_line('Enabled:  ' . (cfg('egosms.enabled') ? 'yes' : 'no'));
print_line('Username: ' . (string) cfg('egosms.username'));
print_line('Sender:   ' . (string) cfg('egosms.sender'));
print_line('Endpoint: ' . (string) cfg('egosms.endpoint'));
$pw = (string) cfg('egosms.password');
print_line('Password: ' . ($pw !== '' && $pw !== 'YOUR_EGOSMS_PASSWORD' ? '(set)' : '(MISSING)'));
print_line('');

if ($phone === '') {
    print_line('Usage:');
    print_line('  php test-sms.php <phone> ["optional message"]');
    print_line('  Example: php test-sms.php 0700123456');
    print_line('');
    print_line('No phone provided — dry-run only (credentials check).');
    print_line('Provide a Uganda number (07XXXXXXXX or 2567XXXXXXXX) to send a real SMS.');
    exit(0);
}

$normalized = normalize_ug_phone($phone);
print_line('Input phone:      ' . $phone);
print_line('Normalized phone: ' . ($normalized !== '' ? $normalized : '(invalid)'));

if ($normalized === '') {
    print_line('ERROR: Invalid phone number.');
    exit(1);
}

if ($message === '') {
    $app = (string) cfg('app_name', 'QR Attend');
    $message = "{$app} SMS test OK at " . date('Y-m-d H:i:s') . '. If you received this, EgoSMS is working.';
}

print_line('Message: ' . $message);
print_line('Sending...');

$result = send_egosms($phone, $message);

print_line('');
print_line('Success: ' . (!empty($result['success']) ? 'YES' : 'NO'));
print_line('Message: ' . ($result['message'] ?? ''));
print_line('Raw API: ' . ($result['raw'] ?? '(empty)'));

if (empty($result['success'])) {
    print_line('');
    print_line('Troubleshooting tips:');
    print_line('  1) Confirm API username/password in EgoSMS dashboard → API settings');
    print_line('  2) Ensure account has SMS credit/balance');
    print_line('  3) Sender ID must be approved (try EGOSMS_SENDER=Egosms or InfoSMS in .env)');
    print_line('  4) Number format should be 2567XXXXXXXX');
    exit(1);
}

print_line('');
print_line('Check the phone for the SMS.');
exit(0);
