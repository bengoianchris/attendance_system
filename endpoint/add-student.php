<?php
require_once __DIR__ . '/../conn/session.php';
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "error" => "unauthenticated", "message" => "Please log in to continue."]);
    exit;
}

include("../conn/conn.php");
include("../conn/validate.php");
header('Content-Type: application/json');

require_post_method();

$studentName = post_str('student_name');
$studentCourse = post_str('course_section');
$parentName = post_str('parent_name');
$parentPhone = post_str('parent_phone');
$generatedCode = post_str('generated_code');

$errors = validate_student_payload([
    'student_name' => $studentName,
    'course_section' => $studentCourse,
    'parent_name' => $parentName,
    'parent_phone' => $parentPhone,
    'generated_code' => $generatedCode
], true);

if (!empty($errors)) {
    validation_fail($errors);
}

try {
    $dupStmt = $conn->prepare("SELECT 1 FROM tbl_student WHERE generated_code = :generated_code");
    $dupStmt->execute([':generated_code' => $generatedCode]);
    if ($dupStmt->fetch()) {
        validation_fail(['generated_code' => 'This QR code is already assigned to another student. Generate a new one.']);
    }

    $stmt = $conn->prepare("INSERT INTO tbl_student (student_name, course_section, parent_name, parent_phone, generated_code) VALUES (:student_name, :course_section, :parent_name, :parent_phone, :generated_code)");
    $stmt->bindParam(":student_name", $studentName, PDO::PARAM_STR);
    $stmt->bindParam(":course_section", $studentCourse, PDO::PARAM_STR);
    $stmt->bindParam(":parent_name", $parentName, PDO::PARAM_STR);
    $stmt->bindParam(":parent_phone", $parentPhone, PDO::PARAM_STR);
    $stmt->bindParam(":generated_code", $generatedCode, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Student '$studentName' added successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to add student. Please try again."]);
}
?>
