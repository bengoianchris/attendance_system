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

$attendanceId = post_str('attendance_id');
if ($err = validate_positive_id($attendanceId, 'Attendance ID')) {
    validation_fail(['attendance_id' => $err]);
}

try {
    $exists = $conn->prepare("SELECT 1 FROM tbl_attendance WHERE tbl_attendance_id = :id");
    $exists->execute([':id' => $attendanceId]);
    if (!$exists->fetch()) {
        validation_fail(['attendance_id' => 'Attendance record not found.']);
    }

    $stmt = $conn->prepare("DELETE FROM tbl_attendance WHERE tbl_attendance_id = :id");
    $stmt->bindParam(":id", $attendanceId, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(["success" => true, "message" => "Attendance record deleted successfully."]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to delete attendance record. Please try again."]);
}
?>
