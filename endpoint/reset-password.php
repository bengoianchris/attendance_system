<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/schema.php");
header('Content-Type: application/json');

require_post_method();
ensure_user_profile_columns($conn);

$username = post_str('username');
$otp = post_str('otp');
$password = post_str('password', false);
$confirm = post_str('confirm_password', false);

$errors = [];
if ($err = validate_login_username($username)) {
    $errors['username'] = $err;
}
if ($err = validate_otp($otp)) {
    $errors['otp'] = $err;
}
if ($err = validate_password($password, true)) {
    $errors['password'] = $err;
}
if ($confirm === '') {
    $errors['confirm_password'] = 'Please confirm your new password.';
} elseif ($password !== $confirm) {
    $errors['confirm_password'] = 'Password and confirmation do not match.';
}
if (!empty($errors)) {
    validation_fail($errors);
}

try {
    $stmt = $conn->prepare("SELECT tbl_user_id, reset_otp_hash, reset_otp_expires FROM tbl_user WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['reset_otp_hash']) || empty($user['reset_otp_expires'])) {
        validation_fail(['otp' => 'Invalid or expired reset code. Request a new one.']);
    }

    $expires = strtotime($user['reset_otp_expires']);
    if ($expires === false || $expires < time()) {
        validation_fail(['otp' => 'This reset code has expired. Request a new one.']);
    }

    if (!password_verify($otp, $user['reset_otp_hash'])) {
        validation_fail(['otp' => 'Invalid reset code.']);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE tbl_user SET password = :password, reset_otp_hash = NULL, reset_otp_expires = NULL, api_token = NULL WHERE tbl_user_id = :id");
    // api_token column may not exist yet on very old DBs — fall back
    try {
        $update->execute([':password' => $hashed, ':id' => $user['tbl_user_id']]);
    } catch (PDOException $e) {
        $update = $conn->prepare("UPDATE tbl_user SET password = :password, reset_otp_hash = NULL, reset_otp_expires = NULL WHERE tbl_user_id = :id");
        $update->execute([':password' => $hashed, ':id' => $user['tbl_user_id']]);
    }

    echo json_encode([
        "success" => true,
        "message" => "Password reset successful. You can now sign in with your new password.",
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to reset password. Please try again."]);
}
?>
