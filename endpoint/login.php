<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/auth.php");
include("../conn/schema.php");
header('Content-Type: application/json');

require_post_method();
ensure_user_profile_columns($conn);

$username = post_str('username');
$password = post_str('password', false);

$errors = [];
if ($err = validate_login_username($username)) {
    $errors['username'] = $err;
}
if ($err = validate_login_password($password)) {
    $errors['password'] = $err;
}
if (!empty($errors)) {
    validation_fail($errors, 'Please enter a valid username and password.');
}

try {
    $stmt = $conn->prepare("SELECT * FROM tbl_user WHERE username = :username");
    $stmt->bindParam(":username", $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['tbl_user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $token = issue_user_token($conn, (int) $user['tbl_user_id']);

        echo json_encode([
            "success" => true,
            "message" => "Login successful.",
            "token" => $token,
            "user" => [
                "id" => (int) $user['tbl_user_id'],
                "name" => $user['name'],
                "username" => $user['username'],
                "role" => $user['role'],
                "phone" => $user['phone'] ?? '',
            ],
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Invalid username or password.",
            "errors" => [
                "username" => "Invalid username or password.",
                "password" => "Invalid username or password."
            ]
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to sign in right now. Please try again."]);
}
?>
