<?php
require_once __DIR__ . '/config.php';

$db = cfg('database', []);
$servername = (string) ($db['host'] ?? 'localhost');
$username = (string) ($db['user'] ?? 'root');
$password = (string) ($db['pass'] ?? '');
$dbname = (string) ($db['name'] ?? 'attendance');
$charset = (string) ($db['charset'] ?? 'utf8mb4');

$sessionName = (string) cfg('auth.session.name', '');
if ($sessionName !== '' && session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
}

try {
    $dsn = "mysql:host={$servername};dbname={$dbname};charset={$charset}";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Check credentials in conn/config.php or .env.',
    ]);
    exit;
}
