<?php
/**
 * Auth helpers — supports PHP session (web) and API token (Flutter mobile).
 * Token size, roles, and related settings live in conn/config.php.
 */

require_once __DIR__ . '/config.php';

function get_bearer_token(): string
{
    $header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'authorization') {
                $header = $value;
                break;
            }
        }
    }

    if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
        return trim($matches[1]);
    }

    if (cfg('auth.allow_token_in_post', true) && isset($_POST['api_token'])) {
        return trim((string) $_POST['api_token']);
    }

    return '';
}

function generate_api_token(): string
{
    $bytes = (int) cfg('auth.api_token_bytes', 32);
    if ($bytes < 16) {
        $bytes = 16;
    }
    if ($bytes > 64) {
        $bytes = 64;
    }
    return bin2hex(random_bytes($bytes));
}

function ensure_api_token_column(PDO $conn): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $conn->query("SELECT api_token FROM tbl_user LIMIT 1");
    } catch (PDOException $e) {
        $conn->exec("ALTER TABLE tbl_user ADD COLUMN api_token VARCHAR(128) NULL DEFAULT NULL");
        try {
            $conn->exec("CREATE UNIQUE INDEX idx_user_api_token ON tbl_user (api_token)");
        } catch (PDOException $ignored) {
            // Index may already exist on retry.
        }
    }
}

function issue_user_token(PDO $conn, int $userId): string
{
    ensure_api_token_column($conn);
    $token = generate_api_token();
    $stmt = $conn->prepare("UPDATE tbl_user SET api_token = :token WHERE tbl_user_id = :id");
    $stmt->execute([':token' => $token, ':id' => $userId]);
    return $token;
}

function clear_user_token(PDO $conn, int $userId): void
{
    ensure_api_token_column($conn);
    $stmt = $conn->prepare("UPDATE tbl_user SET api_token = NULL WHERE tbl_user_id = :id");
    $stmt->execute([':id' => $userId]);
}

function auth_json_exit(string $message, string $error = 'unauthenticated', int $status = 401): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $error,
        'message' => $message,
    ]);
    exit;
}

/**
 * Requires a logged-in user via session or API token.
 * Returns associative user row (at least tbl_user_id, name, username, role).
 */
function require_authenticated_user(PDO $conn): array
{
    apply_cors_headers();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (isset($_SESSION['user_id'])) {
        return [
            'tbl_user_id' => (int) $_SESSION['user_id'],
            'name' => $_SESSION['name'] ?? '',
            'username' => $_SESSION['username'] ?? '',
            'role' => $_SESSION['role'] ?? 'staff',
        ];
    }

    ensure_api_token_column($conn);
    $token = get_bearer_token();
    if ($token === '') {
        auth_json_exit('Please log in to continue.');
    }

    $stmt = $conn->prepare("SELECT tbl_user_id, name, username, role FROM tbl_user WHERE api_token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        auth_json_exit('Session expired. Please log in again.');
    }

    $_SESSION['user_id'] = $user['tbl_user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    return $user;
}

/**
 * Requires an authenticated user with the configured admin role.
 */
function require_admin_user(PDO $conn): array
{
    $user = require_authenticated_user($conn);
    $adminRole = (string) cfg('auth.admin_role', 'admin');
    if (($user['role'] ?? '') !== $adminRole) {
        auth_json_exit('Access denied. Admins only.', 'forbidden', 403);
    }
    return $user;
}
