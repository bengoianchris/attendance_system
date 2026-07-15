<?php
/**
 * Seed demo students + attendance for pagination/filter testing.
 * Run: php seed-demo-data.php
 */
require __DIR__ . '/conn/conn.php';

if (!$conn) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$firstNames = [
    'Ava', 'Noah', 'Mia', 'Liam', 'Sophia', 'Ethan', 'Olivia', 'Lucas', 'Emma', 'Mason',
    'Isabella', 'Logan', 'Amelia', 'James', 'Harper', 'Benjamin', 'Evelyn', 'Henry', 'Chloe', 'Alexander',
    'Ella', 'Daniel', 'Grace', 'Matthew', 'Lily', 'Samuel', 'Zoe', 'David', 'Nora', 'Joseph',
    'Hannah', 'Carter', 'Aria', 'Owen', 'Scarlett', 'Wyatt', 'Layla', 'Jack', 'Penelope', 'Gabriel'
];

$lastNames = [
    'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
    'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin'
];

$courses = [
    'BSIS 1A', 'BSIS 1B', 'BSIS 2A', 'BSIS 2B', 'BSIS 3A', 'BSIS 3B', 'BSIS 4A', 'BSIS 4B',
    'BSCS 2A', 'BSCS 3B', 'BSIT 1A', 'BSIT 2B'
];

$parentFirst = ['Maria', 'John', 'Ana', 'Robert', 'Elena', 'Carlos', 'Grace', 'Peter', 'Helen', 'Mark'];

function randomCode(int $length = 10): string {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function randomPhone(): string {
    return '09' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
}

try {
    $conn->beginTransaction();

    // Avoid re-seeding the same demo batch repeatedly
    $check = $conn->query("SELECT COUNT(*) FROM tbl_student WHERE generated_code LIKE 'SEED%'")->fetchColumn();
    if ((int) $check > 0) {
        echo "Demo seed data already exists ({$check} seeded students). Clearing previous SEED* batch...\n";
        $conn->exec("DELETE FROM tbl_attendance WHERE tbl_student_id IN (SELECT tbl_student_id FROM tbl_student WHERE generated_code LIKE 'SEED%')");
        $conn->exec("DELETE FROM tbl_student WHERE generated_code LIKE 'SEED%'");
    }

    $insertStudent = $conn->prepare(
        "INSERT INTO tbl_student (student_name, course_section, parent_name, parent_phone, generated_code)
         VALUES (:name, :course, :parent_name, :parent_phone, :code)"
    );

    $studentIds = [];
    $targetStudents = 48;

    for ($i = 1; $i <= $targetStudents; $i++) {
        $name = $firstNames[($i - 1) % count($firstNames)] . ' ' . $lastNames[($i - 1) % count($lastNames)];
        // slight uniqueness for repeated combos
        if ($i > count($firstNames)) {
            $name .= ' ' . chr(64 + ($i % 26 ?: 26));
        }

        $course = $courses[($i - 1) % count($courses)];
        $parent = $parentFirst[($i - 1) % count($parentFirst)] . ' ' . $lastNames[($i * 3) % count($lastNames)];
        $phone = randomPhone();
        $code = 'SEED' . str_pad((string) $i, 6, '0', STR_PAD_LEFT);

        $insertStudent->execute([
            ':name' => $name,
            ':course' => $course,
            ':parent_name' => $parent,
            ':parent_phone' => $phone,
            ':code' => $code,
        ]);

        $studentIds[] = (int) $conn->lastInsertId();
    }

    $insertAttendance = $conn->prepare(
        "INSERT INTO tbl_attendance (tbl_student_id, time_in) VALUES (:sid, :time_in)"
    );

    // Spread attendance across the last 14 days so date filters work
    $attendanceCount = 0;
    foreach ($studentIds as $index => $sid) {
        $daysPresent = 3 + ($index % 5); // 3–7 days each
        for ($d = 0; $d < $daysPresent; $d++) {
            $dayOffset = ($index + $d * 2) % 14; // within last 2 weeks
            $hour = 7 + (($index + $d) % 3);     // 07–09
            $minute = ($index * 7 + $d * 11) % 60;
            $second = ($index + $d) % 60;

            $dt = new DateTime('now');
            $dt->setTime($hour, $minute, $second);
            $dt->modify("-{$dayOffset} days");

            $insertAttendance->execute([
                ':sid' => $sid,
                ':time_in' => $dt->format('Y-m-d H:i:s'),
            ]);
            $attendanceCount++;
        }
    }

    $conn->commit();

    $totalStudents = (int) $conn->query('SELECT COUNT(*) FROM tbl_student')->fetchColumn();
    $totalAttendance = (int) $conn->query('SELECT COUNT(*) FROM tbl_attendance')->fetchColumn();

    echo "Seed complete.\n";
    echo "- Inserted students: {$targetStudents}\n";
    echo "- Inserted attendance rows: {$attendanceCount}\n";
    echo "- Total students now: {$totalStudents}\n";
    echo "- Total attendance now: {$totalAttendance}\n";
    echo "QR codes use prefix SEED000001 … SEED000048\n";
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}
