<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/auth.php");
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    try {
        clear_user_token($conn, (int) $_SESSION['user_id']);
    } catch (PDOException $e) {
        // Continue logout even if token clear fails.
    }
} else {
    $token = get_bearer_token();
    if ($token !== '') {
        try {
            ensure_api_token_column($conn);
            $stmt = $conn->prepare("UPDATE tbl_user SET api_token = NULL WHERE api_token = :token");
            $stmt->execute([':token' => $token]);
        } catch (PDOException $e) {
            // Continue logout.
        }
    }
}

$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

echo json_encode(["success" => true, "message" => "Logged out successfully."]);
?>
