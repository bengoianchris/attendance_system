<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/auth.php");
include("../conn/schema.php");
header('Content-Type: application/json');

$authUser = require_authenticated_user($conn);
require_post_method();
ensure_user_profile_columns($conn);

$name = post_str('name');
$username = post_str('username');
$phone = post_str('phone');

$errors = [];
if ($err = validate_person_name($name, 'Full name')) {
    $errors['name'] = $err;
}
if ($err = validate_username($username)) {
    $errors['username'] = $err;
}
if ($err = validate_phone($phone, 'Phone', false)) {
    $errors['phone'] = $err;
}
if (!empty($errors)) {
    validation_fail($errors);
}

try {
    $checkStmt = $conn->prepare("SELECT 1 FROM tbl_user WHERE username = :username AND tbl_user_id != :id");
    $checkStmt->execute([':username' => $username, ':id' => $authUser['tbl_user_id']]);
    if ($checkStmt->fetch()) {
        validation_fail(['username' => "Username '$username' is already taken."]);
    }

    $stmt = $conn->prepare("UPDATE tbl_user SET name = :name, username = :username, phone = :phone WHERE tbl_user_id = :id");
    $stmt->execute([
        ':name' => $name,
        ':username' => $username,
        ':phone' => $phone !== '' ? $phone : null,
        ':id' => $authUser['tbl_user_id'],
    ]);

    $_SESSION['name'] = $name;
    $_SESSION['username'] = $username;

    echo json_encode([
        "success" => true,
        "message" => "Profile updated successfully.",
        "user" => [
            "id" => (int) $authUser['tbl_user_id'],
            "name" => $name,
            "username" => $username,
            "role" => $authUser['role'],
            "phone" => $phone,
        ],
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to update profile. Please try again."]);
}
?>
