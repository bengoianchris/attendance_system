<?php
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/auth.php");
header('Content-Type: application/json');

require_admin_user($conn);
require_post_method();

$name = post_str('name');
$username = post_str('username');
$password = post_str('password', false);
$role = post_str('role');

$errors = validate_user_payload([
    'name' => $name,
    'username' => $username,
    'password' => $password,
    'role' => $role
], true);

if (!empty($errors)) {
    validation_fail($errors);
}

try {
    $checkStmt = $conn->prepare("SELECT 1 FROM tbl_user WHERE username = :username");
    $checkStmt->execute([':username' => $username]);
    if ($checkStmt->fetch()) {
        validation_fail(['username' => "Username '$username' is already taken."]);
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO tbl_user (name, username, password, role) VALUES (:name, :username, :password, :role)");
    $stmt->bindParam(":name", $name, PDO::PARAM_STR);
    $stmt->bindParam(":username", $username, PDO::PARAM_STR);
    $stmt->bindParam(":password", $hashedPassword, PDO::PARAM_STR);
    $stmt->bindParam(":role", $role, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "User '$name' added successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to add user. Please try again."]);
}
?>
