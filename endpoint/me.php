<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/auth.php");
include("../conn/schema.php");
header('Content-Type: application/json');

$user = require_authenticated_user($conn);
ensure_user_profile_columns($conn);

$stmt = $conn->prepare("SELECT tbl_user_id, name, username, role, phone, created_at FROM tbl_user WHERE tbl_user_id = :id LIMIT 1");
$stmt->execute([':id' => $user['tbl_user_id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(["success" => false, "error" => "unauthenticated", "message" => "User not found."]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Authenticated.",
    "user" => [
        "id" => (int) $row['tbl_user_id'],
        "name" => $row['name'],
        "username" => $row['username'],
        "role" => $row['role'],
        "phone" => $row['phone'] ?? '',
        "created_at" => $row['created_at'] ?? null,
    ],
]);
?>
