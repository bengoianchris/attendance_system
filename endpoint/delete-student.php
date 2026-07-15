<?php
require_once __DIR__ . '/../conn/session.php';
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "error" => "unauthenticated", "message" => "Please log in to continue."]);
    exit;
}

include('../conn/conn.php');
include('../conn/validate.php');
header('Content-Type: application/json');

require_post_method();

$studentId = post_str('student_id');
if ($err = validate_positive_id($studentId, 'Student ID')) {
    validation_fail(['student_id' => $err]);
}

try {
    $exists = $conn->prepare("SELECT student_name FROM tbl_student WHERE tbl_student_id = :id");
    $exists->execute([':id' => $studentId]);
    $student = $exists->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        validation_fail(['student_id' => 'Student not found.']);
    }

    $stmtAtt = $conn->prepare("DELETE FROM tbl_attendance WHERE tbl_student_id = :id");
    $stmtAtt->bindParam(":id", $studentId, PDO::PARAM_INT);
    $stmtAtt->execute();

    $stmt = $conn->prepare("DELETE FROM tbl_student WHERE tbl_student_id = :id");
    $stmt->bindParam(":id", $studentId, PDO::PARAM_INT);
    $stmt->execute();

    $name = $student['student_name'];
    echo json_encode(["success" => true, "message" => "Student '$name' deleted successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to delete student. Please try again."]);
}
?>
