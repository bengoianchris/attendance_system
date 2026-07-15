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

$studentId = post_str('tbl_student_id');
$studentName = post_str('student_name');
$studentCourse = post_str('course_section');
$parentName = post_str('parent_name');
$parentPhone = post_str('parent_phone');

$errors = [];
if ($err = validate_positive_id($studentId, 'Student ID')) {
    $errors['tbl_student_id'] = $err;
}

$fieldErrors = validate_student_payload([
    'student_name' => $studentName,
    'course_section' => $studentCourse,
    'parent_name' => $parentName,
    'parent_phone' => $parentPhone
], false);

$errors = array_merge($errors, $fieldErrors);

if (!empty($errors)) {
    validation_fail($errors);
}

try {
    $exists = $conn->prepare("SELECT 1 FROM tbl_student WHERE tbl_student_id = :id");
    $exists->execute([':id' => $studentId]);
    if (!$exists->fetch()) {
        validation_fail(['tbl_student_id' => 'Student not found.']);
    }

    $stmt = $conn->prepare("UPDATE tbl_student SET student_name = :student_name, course_section = :course_section, parent_name = :parent_name, parent_phone = :parent_phone WHERE tbl_student_id = :tbl_student_id");
    $stmt->bindParam(":tbl_student_id", $studentId, PDO::PARAM_INT);
    $stmt->bindParam(":student_name", $studentName, PDO::PARAM_STR);
    $stmt->bindParam(":course_section", $studentCourse, PDO::PARAM_STR);
    $stmt->bindParam(":parent_name", $parentName, PDO::PARAM_STR);
    $stmt->bindParam(":parent_phone", $parentPhone, PDO::PARAM_STR);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Student '$studentName' updated successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to update student. Please try again."]);
}
?>
