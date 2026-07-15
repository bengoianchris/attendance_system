<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/schema.php");
include("../conn/sms.php");
header('Content-Type: application/json');

require_post_method();
ensure_user_profile_columns($conn);

$username = post_str('username');
if ($err = validate_login_username($username)) {
    validation_fail(['username' => $err]);
}

$config = app_config();
$ttl = (int) ($config['password_reset']['otp_ttl_minutes'] ?? 15);
$otpLen = (int) ($config['password_reset']['otp_length'] ?? 6);
$otpLen = max(4, min(8, $otpLen));

try {
    $stmt = $conn->prepare("SELECT tbl_user_id, name, phone FROM tbl_user WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Generic response to avoid username enumeration, but still fail clearly when no phone
    if (!$user) {
        echo json_encode([
            "success" => true,
            "message" => "If this account exists and has a phone number, a reset code has been sent.",
            "sent" => false,
        ]);
        exit;
    }

    $phone = trim((string) ($user['phone'] ?? ''));
    if ($phone === '') {
        echo json_encode([
            "success" => false,
            "message" => "No phone number is linked to this account. Ask an admin to add your phone in Users/Profile, then try again.",
            "errors" => ["username" => "No phone on file for password reset."],
        ]);
        exit;
    }

    $otp = '';
    for ($i = 0; $i < $otpLen; $i++) {
        $otp .= (string) random_int(0, 9);
    }

    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = (new DateTimeImmutable("+{$ttl} minutes"))->format('Y-m-d H:i:s');

    $update = $conn->prepare("UPDATE tbl_user SET reset_otp_hash = :hash, reset_otp_expires = :expires WHERE tbl_user_id = :id");
    $update->execute([
        ':hash' => $hash,
        ':expires' => $expires,
        ':id' => $user['tbl_user_id'],
    ]);

    $appName = $config['app_name'] ?? 'QR Attend';
    $smsMessage = "{$appName} password reset code: {$otp}. Valid for {$ttl} minutes. Do not share this code.";
    $sms = send_egosms($phone, $smsMessage);

    if (empty($sms['success'])) {
        echo json_encode([
            "success" => false,
            "message" => $sms['message'] ?? 'Unable to send reset code by SMS.',
        ]);
        exit;
    }

    $masked = normalize_ug_phone($phone);
    if (strlen($masked) > 4) {
        $masked = str_repeat('*', max(0, strlen($masked) - 4)) . substr($masked, -4);
    }

    echo json_encode([
        "success" => true,
        "message" => "A reset code was sent by SMS to {$masked}.",
        "sent" => true,
        "expires_in_minutes" => $ttl,
        "username" => $username,
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to start password reset. Please try again."]);
}
?>
