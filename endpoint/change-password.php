<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/auth.php");
header('Content-Type: application/json');

$authUser = require_authenticated_user($conn);
require_post_method();

$currentPassword = post_str('current_password', false);
$newPassword = post_str('new_password', false);
$confirmPassword = post_str('confirm_password', false);

$errors = [];
if ($currentPassword === '') {
    $errors['current_password'] = 'Current password is required.';
}
if ($err = validate_password($newPassword, true)) {
    $errors['new_password'] = $err;
}
if ($confirmPassword === '') {
    $errors['confirm_password'] = 'Please confirm your new password.';
} elseif ($newPassword !== $confirmPassword) {
    $errors['confirm_password'] = 'New password and confirmation do not match.';
}
if (!empty($errors)) {
    validation_fail($errors);
}

try {
    $stmt = $conn->prepare("SELECT password FROM tbl_user WHERE tbl_user_id = :id LIMIT 1");
    $stmt->execute([':id' => $authUser['tbl_user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !password_verify($currentPassword, $row['password'])) {
        validation_fail(['current_password' => 'Current password is incorrect.']);
    }

    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE tbl_user SET password = :password WHERE tbl_user_id = :id");
    $update->execute([':password' => $hashed, ':id' => $authUser['tbl_user_id']]);

    echo json_encode(["success" => true, "message" => "Password changed successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to change password. Please try again."]);
}
?>
