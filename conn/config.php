<?php
/**
 * =============================================================================
 * QR Attend — SINGLE configuration file
 * =============================================================================
 * Manage ALL API keys, database credentials, and authentication rules here.
 *
 * Priority for secrets:
 *   1) Environment variables / optional `.env` in the project root
 *   2) The fallback values in this file
 *
 * Copy `.env.example` → `.env` for local secrets without editing this file.
 * =============================================================================
 */

if (!function_exists('qr_attend_load_dotenv')) {
    /**
     * Lightweight .env loader (KEY=VALUE). Does not override existing env vars.
     */
    function qr_attend_load_dotenv(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            // Strip optional quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $exists = getenv($name);
            if ($exists !== false && $exists !== '') {
                continue;
            }

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    function env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        $lower = strtolower($value);
        if (in_array($lower, ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($lower, ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        return $value;
    }

    /**
     * Cached application config (the array defined below).
     */
    function app_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        qr_attend_load_dotenv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

        $config = [
            /* ---------- App ---------- */
            'app_name' => env('APP_NAME', 'QR Attend'),
            'app_url' => env('APP_URL', ''), // optional public base URL

            /* ---------- Database ---------- */
            'database' => [
                'host' => env('DB_HOST', 'localhost'),
                'name' => env('DB_NAME', 'attendance'),
                'user' => env('DB_USER', 'root'),
                'pass' => env('DB_PASS', ''),
                'charset' => env('DB_CHARSET', 'utf8mb4'),
            ],

            /* ---------- EgoSMS API ---------- */
            // Dashboard: https://www.egosms.co
            'egosms' => [
                'enabled' => env('EGOSMS_ENABLED', true),
                'username' => env('EGOSMS_USERNAME', 'YOUR_EGOSMS_USERNAME'),
                'password' => env('EGOSMS_PASSWORD', 'YOUR_EGOSMS_PASSWORD'),
                'sender' => env('EGOSMS_SENDER', 'QRAttend'),
                // Live:  https://www.egosms.co/api/v1/plain/
                // Sandbox: http://sandbox.egosms.co/api/v1/plain/
                'endpoint' => env('EGOSMS_ENDPOINT', 'https://www.egosms.co/api/v1/plain/'),
                'priority' => (string) env('EGOSMS_PRIORITY', '0'),
                'timeout_seconds' => (int) env('EGOSMS_TIMEOUT', 20),
            ],

            /* ---------- SMS behaviour ---------- */
            'sms' => [
                'notify_parent_on_attendance' => env('SMS_NOTIFY_PARENT_ON_ATTENDANCE', true),
                'attendance_template' => env(
                    'SMS_ATTENDANCE_TEMPLATE',
                    '{student} has been marked present at {time} ({course}). - {app}'
                ),
                'max_length' => (int) env('SMS_MAX_LENGTH', 160),
            ],

            /* ---------- Authentication & API access ---------- */
            'auth' => [
                // Roles allowed in the system (first-class admin role below)
                'roles' => ['admin', 'staff'],
                'admin_role' => 'admin',

                // Mobile / API bearer tokens (stored on tbl_user.api_token)
                'api_token_bytes' => (int) env('AUTH_API_TOKEN_BYTES', 32),
                'allow_token_in_post' => env('AUTH_ALLOW_TOKEN_IN_POST', true),

                // Username rules (web + API)
                'username' => [
                    'min_length' => (int) env('AUTH_USERNAME_MIN', 3),
                    'max_length' => (int) env('AUTH_USERNAME_MAX', 50),
                    // Letters, numbers, . _ -
                    'pattern' => '/^[A-Za-z0-9._-]+$/',
                    'pattern_message' => 'Username may only contain letters, numbers, periods, underscores, and hyphens.',
                ],

                // Password rules (create / change / reset)
                'password' => [
                    'min_length' => (int) env('AUTH_PASSWORD_MIN', 8),
                    'max_length' => (int) env('AUTH_PASSWORD_MAX', 72),
                    'require_letter' => env('AUTH_PASSWORD_REQUIRE_LETTER', true),
                    'require_number' => env('AUTH_PASSWORD_REQUIRE_NUMBER', true),
                    'require_special' => env('AUTH_PASSWORD_REQUIRE_SPECIAL', false),
                ],

                // Session (web dashboard)
                'session' => [
                    'name' => env('AUTH_SESSION_NAME', 'QRATTENDSESSID'),
                ],
            ],

            /* ---------- Password reset (OTP via SMS) ---------- */
            'password_reset' => [
                'otp_ttl_minutes' => (int) env('PASSWORD_RESET_OTP_TTL', 15),
                'otp_length' => (int) env('PASSWORD_RESET_OTP_LENGTH', 6),
            ],

            /* ---------- CORS (optional; for browser clients hitting APIs) ---------- */
            'cors' => [
                'enabled' => env('CORS_ENABLED', false),
                'allowed_origins' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
                ))),
                'allowed_methods' => 'GET, POST, OPTIONS',
                'allowed_headers' => 'Content-Type, Authorization',
            ],
        ];

        // Apply custom session name as early as config is first loaded.
        $sessionName = (string) ($config['auth']['session']['name'] ?? '');
        if ($sessionName !== '' && session_status() === PHP_SESSION_NONE) {
            session_name($sessionName);
        }

        return $config;
    }

    /**
     * Dot-path getter, e.g. cfg('egosms.username') or cfg('auth.password.min_length', 8).
     */
    function cfg(string $path, $default = null)
    {
        $parts = explode('.', $path);
        $value = app_config();
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /**
     * Public subset of auth rules for the web UI (no secrets).
     */
    function auth_public_config(): array
    {
        $auth = cfg('auth', []);
        return [
            'roles' => $auth['roles'] ?? ['admin', 'staff'],
            'admin_role' => $auth['admin_role'] ?? 'admin',
            'username' => $auth['username'] ?? [],
            'password' => $auth['password'] ?? [],
            'password_reset' => [
                'otp_length' => (int) cfg('password_reset.otp_length', 6),
                'otp_ttl_minutes' => (int) cfg('password_reset.otp_ttl_minutes', 15),
            ],
        ];
    }

    /**
     * Apply CORS headers when enabled in config.
     */
    function apply_cors_headers(): void
    {
        if (!cfg('cors.enabled', false)) {
            return;
        }

        $origins = cfg('cors.allowed_origins', ['*']);
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allow = '*';

        if (in_array('*', $origins, true)) {
            $allow = $requestOrigin !== '' ? $requestOrigin : '*';
        } elseif ($requestOrigin !== '' && in_array($requestOrigin, $origins, true)) {
            $allow = $requestOrigin;
        } elseif (!empty($origins[0])) {
            $allow = $origins[0];
        }

        header('Access-Control-Allow-Origin: ' . $allow);
        header('Access-Control-Allow-Methods: ' . (string) cfg('cors.allowed_methods', 'GET, POST, OPTIONS'));
        header('Access-Control-Allow-Headers: ' . (string) cfg('cors.allowed_headers', 'Content-Type, Authorization'));
        header('Access-Control-Allow-Credentials: true');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

// Ensure helpers are registered when this file is required for its return value.
app_config();

return app_config();
