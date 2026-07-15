<?php
require_once __DIR__ . '/../conn/session.php';
include("../conn/conn.php");
include("../conn/validate.php");
include("../conn/auth.php");
include("../conn/sms.php");
header('Content-Type: application/json');

require_authenticated_user($conn);
require_post_method();

$qrCode = post_str('qr_code');

if ($err = validate_qr_code($qrCode)) {
    validation_fail(['qr_code' => $err]);
}

try {
    $selectStmt = $conn->prepare("SELECT tbl_student_id, student_name, course_section, parent_name, parent_phone FROM tbl_student WHERE generated_code = :generated_code");
    $selectStmt->bindParam(":generated_code", $qrCode, PDO::PARAM_STR);
    $selectStmt->execute();
    $result = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        validation_fail(['qr_code' => 'No student found for this QR code.']);
    }

    $studentID = $result["tbl_student_id"];
    $studentName = $result["student_name"];
    $courseSection = $result["course_section"];
    $today = date("Y-m-d");

    $dupStmt = $conn->prepare("SELECT 1 FROM tbl_attendance WHERE tbl_student_id = :sid AND DATE(time_in) = :today");
    $dupStmt->execute([':sid' => $studentID, ':today' => $today]);
    if ($dupStmt->fetch()) {
        validation_fail(['qr_code' => "$studentName already has an attendance record for today."]);
    }

    $timeIn = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("INSERT INTO tbl_attendance (tbl_student_id, time_in) VALUES (:tbl_student_id, :time_in)");
    $stmt->bindParam(":tbl_student_id", $studentID, PDO::PARAM_INT);
    $stmt->bindParam(":time_in", $timeIn, PDO::PARAM_STR);
    $stmt->execute();

    $smsResult = notify_parent_attendance($result, $timeIn);
    $smsStatus = [
        'sent' => !empty($smsResult['success']),
        'skipped' => !empty($smsResult['skipped']),
        'message' => $smsResult['message'] ?? '',
    ];

    $message = "Attendance recorded for $studentName.";
    if ($smsStatus['sent']) {
        $message .= ' Parent notified by SMS.';
    } elseif (!$smsStatus['skipped']) {
        $message .= ' (Parent SMS could not be sent.)';
    }

    echo json_encode([
        "success" => true,
        "message" => $message,
        "student_name" => $studentName,
        "course_section" => $courseSection,
        "time_in" => $timeIn,
        "qr_code" => $qrCode,
        "sms" => $smsStatus,
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Unable to record attendance. Please try again."]);
}
?>
