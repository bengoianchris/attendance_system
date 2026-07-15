<?php
/**
 * Ensure profile / password-reset columns exist on tbl_user.
 */
function ensure_user_profile_columns(PDO $conn): void {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'phone' => "ALTER TABLE tbl_user ADD COLUMN phone VARCHAR(30) NULL DEFAULT NULL AFTER role",
        'reset_otp_hash' => "ALTER TABLE tbl_user ADD COLUMN reset_otp_hash VARCHAR(255) NULL DEFAULT NULL",
        'reset_otp_expires' => "ALTER TABLE tbl_user ADD COLUMN reset_otp_expires DATETIME NULL DEFAULT NULL",
    ];

    foreach ($columns as $name => $ddl) {
        try {
            $conn->query("SELECT {$name} FROM tbl_user LIMIT 1");
        } catch (PDOException $e) {
            try {
                $conn->exec($ddl);
            } catch (PDOException $ignored) {
                // Column may have been added concurrently.
            }
        }
    }
}
?>
