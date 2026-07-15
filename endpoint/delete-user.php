<?php
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/auth.php");
header('Content-Type: application/json');

$authUser = require_admin_user($conn);
require_post_method();

$userId = post_str('tbl_user_id');
if ($err = validate_positive_id($userId, 'User ID')) {
    validation_fail(['tbl_user_id' => $err]);
}

if ((int) $userId === (int) $authUser['tbl_user_id']) {
    validation_fail(['tbl_user_id' => 'You cannot delete your own account.']);
}

try {
    $nameStmt = $conn->prepare("SELECT name FROM tbl_user WHERE tbl_user_id = :id");
    $nameStmt->execute([':id' => $userId]);
    $user = $nameStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        validation_fail(['tbl_user_id' => 'User not found.']);
    }

    $name = $user['name'];

    $stmt = $conn->prepare("DELETE FROM tbl_user WHERE tbl_user_id = :id");
    $stmt->bindParam(":id", $userId, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "User '$name' deleted successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to delete user. Please try again."]);
}
