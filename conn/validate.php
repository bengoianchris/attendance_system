<?php
/**
 * Shared validation helpers for QR Attend.
 * Returns field => message maps; empty array means valid.
 * Auth rules are read from conn/config.php via cfg().
 */

require_once __DIR__ . '/config.php';

function validation_fail(array $errors, string $fallback = 'Please fix the highlighted fields.') {
    $message = !empty($errors) ? reset($errors) : $fallback;
    echo json_encode([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ]);
    exit;
}

function require_post_method() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method. Please use POST.']);
        exit;
    }
}

function post_str(string $key, bool $trim = true): string {
    if (!isset($_POST[$key])) {
        return '';
    }
    $value = (string) $_POST[$key];
    return $trim ? trim($value) : $value;
}

function validate_required(string $value, string $label): ?string {
    if ($value === '') {
        return "$label is required.";
    }
    return null;
}

function validate_length(string $value, string $label, int $min, int $max): ?string {
    $len = mb_strlen($value);
    if ($len < $min) {
        return "$label must be at least $min characters.";
    }
    if ($len > $max) {
        return "$label must not exceed $max characters.";
    }
    return null;
}

function validate_person_name(string $value, string $label, bool $required = true): ?string {
    if ($value === '') {
        return $required ? "$label is required." : null;
    }
    if ($err = validate_length($value, $label, 2, 100)) {
        return $err;
    }
    if (!preg_match("/^[\p{L}\s.'\-]+$/u", $value)) {
        return "$label may only contain letters, spaces, periods, hyphens, and apostrophes.";
    }
    return null;
}

function validate_course_section(string $value): ?string {
    if ($err = validate_required($value, 'Course & section')) {
        return $err;
    }
    if ($err = validate_length($value, 'Course & section', 2, 100)) {
        return $err;
    }
    if (!preg_match('/^[A-Za-z0-9\s.\-\/]+$/', $value)) {
        return 'Course & section may only contain letters, numbers, spaces, periods, hyphens, and slashes.';
    }
    return null;
}

function validate_phone(string $value, string $label = 'Parent phone', bool $required = false): ?string {
    if ($value === '') {
        return $required ? "$label is required." : null;
    }
    if ($err = validate_length($value, $label, 7, 20)) {
        return $err;
    }
    if (!preg_match('/^[0-9+\-\s().]+$/', $value)) {
        return "$label may only contain digits, spaces, and + - ( ).";
    }
    $digits = preg_replace('/\D+/', '', $value);
    if (strlen($digits) < 7 || strlen($digits) > 15) {
        return "$label must contain between 7 and 15 digits.";
    }
    return null;
}

function validate_otp(string $value): ?string {
    if ($value === '') {
        return 'Verification code is required.';
    }
    if (!preg_match('/^\d{4,8}$/', $value)) {
        return 'Enter a valid verification code.';
    }
    return null;
}

function validate_username(string $value): ?string {
    if ($err = validate_required($value, 'Username')) {
        return $err;
    }
    $min = (int) cfg('auth.username.min_length', 3);
    $max = (int) cfg('auth.username.max_length', 50);
    if ($err = validate_length($value, 'Username', $min, $max)) {
        return $err;
    }
    $pattern = (string) cfg('auth.username.pattern', '/^[A-Za-z0-9._-]+$/');
    if ($pattern !== '' && !preg_match($pattern, $value)) {
        return (string) cfg(
            'auth.username.pattern_message',
            'Username may only contain letters, numbers, periods, underscores, and hyphens.'
        );
    }
    return null;
}

function validate_password(string $value, bool $required = true): ?string {
    if ($value === '') {
        return $required ? 'Password is required.' : null;
    }

    $min = (int) cfg('auth.password.min_length', 8);
    $max = (int) cfg('auth.password.max_length', 72);
    $requireLetter = (bool) cfg('auth.password.require_letter', true);
    $requireNumber = (bool) cfg('auth.password.require_number', true);
    $requireSpecial = (bool) cfg('auth.password.require_special', false);

    if (strlen($value) < $min) {
        return "Password must be at least {$min} characters.";
    }
    if (strlen($value) > $max) {
        return "Password must not exceed {$max} characters.";
    }

    $hasLetter = (bool) preg_match('/[A-Za-z]/', $value);
    $hasNumber = (bool) preg_match('/[0-9]/', $value);
    $hasSpecial = (bool) preg_match('/[^A-Za-z0-9]/', $value);

    if ($requireLetter && $requireNumber && (!$hasLetter || !$hasNumber)) {
        return 'Password must include at least one letter and one number.';
    }
    if ($requireLetter && !$hasLetter) {
        return 'Password must include at least one letter.';
    }
    if ($requireNumber && !$hasNumber) {
        return 'Password must include at least one number.';
    }
    if ($requireSpecial && !$hasSpecial) {
        return 'Password must include at least one special character.';
    }

    return null;
}

function validate_role(string $value): ?string {
    if ($err = validate_required($value, 'Role')) {
        return $err;
    }
    $roles = cfg('auth.roles', ['admin', 'staff']);
    if (!is_array($roles) || !in_array($value, $roles, true)) {
        $label = is_array($roles) ? implode(' or ', array_map('ucfirst', $roles)) : 'Admin or Staff';
        return "Role must be either {$label}.";
    }
    return null;
}

function validate_qr_code(string $value): ?string {
    if ($err = validate_required($value, 'QR code')) {
        return $err;
    }
    if ($err = validate_length($value, 'QR code', 8, 64)) {
        return $err;
    }
    if (!preg_match('/^[A-Za-z0-9]+$/', $value)) {
        return 'QR code is invalid. It must be alphanumeric.';
    }
    return null;
}

function validate_positive_id($value, string $label): ?string {
    if ($value === '' || $value === null) {
        return "$label is required.";
    }
    if (!ctype_digit((string) $value) || (int) $value < 1) {
        return "$label is invalid.";
    }
    return null;
}

function validate_login_username(string $value): ?string {
    if ($err = validate_required($value, 'Username')) {
        return $err;
    }
    $min = (int) cfg('auth.username.min_length', 3);
    $max = (int) cfg('auth.username.max_length', 50);
    if ($err = validate_length($value, 'Username', $min, $max)) {
        return $err;
    }
    return null;
}

function validate_login_password(string $value): ?string {
    if ($value === '') {
        return 'Password is required.';
    }
    $max = (int) cfg('auth.password.max_length', 72);
    if (strlen($value) > $max) {
        return 'Password is too long.';
    }
    return null;
}

function validate_student_payload(array $data, bool $requireCode = true): array {
    $errors = [];

    if ($err = validate_person_name($data['student_name'] ?? '', 'Student name')) {
        $errors['student_name'] = $err;
    }
    if ($err = validate_course_section($data['course_section'] ?? '')) {
        $errors['course_section'] = $err;
    }
    if ($err = validate_person_name($data['parent_name'] ?? '', 'Parent name', false)) {
        $errors['parent_name'] = $err;
    }
    if ($err = validate_phone($data['parent_phone'] ?? '', 'Parent phone', false)) {
        $errors['parent_phone'] = $err;
    }
    if ($requireCode) {
        if ($err = validate_qr_code($data['generated_code'] ?? '')) {
            $errors['generated_code'] = $err;
        }
    }

    return $errors;
}

function validate_user_payload(array $data, bool $requirePassword = true): array {
    $errors = [];

    if ($err = validate_person_name($data['name'] ?? '', 'Full name')) {
        $errors['name'] = $err;
    }
    if ($err = validate_username($data['username'] ?? '')) {
        $errors['username'] = $err;
    }
    if ($err = validate_password($data['password'] ?? '', $requirePassword)) {
        $errors['password'] = $err;
    }
    if ($err = validate_role($data['role'] ?? '')) {
        $errors['role'] = $err;
    }

    return $errors;
}
?>
