<?php
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/auth.php");
header('Content-Type: application/json');

require_admin_user($conn);
require_post_method();

$userId = post_str('tbl_user_id');
$name = post_str('name');
$username = post_str('username');
$password = post_str('password', false);
$role = post_str('role');

$errors = [];
if ($err = validate_positive_id($userId, 'User ID')) {
    $errors['tbl_user_id'] = $err;
}

$fieldErrors = validate_user_payload([
    'name' => $name,
    'username' => $username,
    'password' => $password,
    'role' => $role
], false);

$errors = array_merge($errors, $fieldErrors);

if (!empty($errors)) {
    validation_fail($errors);
}

try {
    $exists = $conn->prepare("SELECT 1 FROM tbl_user WHERE tbl_user_id = :id");
    $exists->execute([':id' => $userId]);
    if (!$exists->fetch()) {
        validation_fail(['tbl_user_id' => 'User not found.']);
    }

    $checkStmt = $conn->prepare("SELECT 1 FROM tbl_user WHERE username = :username AND tbl_user_id != :id");
    $checkStmt->execute([':username' => $username, ':id' => $userId]);
    if ($checkStmt->fetch()) {
        validation_fail(['username' => "Username '$username' is already taken by another user."]);
    }

    $sql = "UPDATE tbl_user SET name = :name, username = :username, role = :role";
    $params = [
        ':name' => $name,
        ':username' => $username,
        ':role' => $role,
        ':id' => $userId
    ];

    if ($password !== '') {
        $sql .= ", password = :password";
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE tbl_user_id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($_SESSION['user_id'] == $userId) {
        $_SESSION['name'] = $name;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
    }

    echo json_encode(["success" => true, "message" => "User '$name' updated successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to update user. Please try again."]);
}
?>
