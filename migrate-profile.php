<?php
require __DIR__ . '/conn/conn.php';
require __DIR__ . '/conn/schema.php';
ensure_user_profile_columns($conn);
echo "Profile columns ready.\n";
foreach ($conn->query('SHOW COLUMNS FROM tbl_user') as $row) {
    echo $row['Field'] . "\n";
}
