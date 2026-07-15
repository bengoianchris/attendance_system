<?php
require_once __DIR__ . '/../conn/session.php';
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "error" => "unauthenticated", "message" => "Please log in to continue."]);
    exit;
}

include("../conn/conn.php");

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$course = isset($_GET['course']) ? trim($_GET['course']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$sql = "SELECT a.tbl_attendance_id, s.student_name, s.course_section, a.time_in
    FROM tbl_attendance a
    LEFT JOIN tbl_student s ON s.tbl_student_id = a.tbl_student_id
    WHERE 1=1";
$params = [];

if ($dateFrom !== '') {
    $sql .= " AND DATE(a.time_in) >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND DATE(a.time_in) <= :date_to";
    $params[':date_to'] = $dateTo;
}
if ($course !== '') {
    $sql .= " AND s.course_section = :course";
    $params[':course'] = $course;
}
if ($search !== '') {
    $sql .= " AND s.student_name LIKE :search";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY a.time_in DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=UTF-8');
    http_response_code(500);
    echo 'Unable to export attendance records.';
    exit;
}

$filename = 'attendance_export_' . date('Y-m-d_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Attendance</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
echo '<body>';
echo '<table border="1">';
echo '<thead><tr>';
echo '<th>ID</th>';
echo '<th>Student Name</th>';
echo '<th>Course &amp; Section</th>';
echo '<th>Time In</th>';
echo '<th>Date</th>';
echo '</tr></thead><tbody>';

foreach ($records as $row) {
    $timeIn = !empty($row['time_in']) ? strtotime($row['time_in']) : false;
    $timeLabel = $timeIn ? date('M d, Y h:i A', $timeIn) : '';
    $dateLabel = $timeIn ? date('Y-m-d', $timeIn) : '';

    echo '<tr>';
    echo '<td>' . htmlspecialchars((string) $row['tbl_attendance_id']) . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['student_name'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['course_section'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars($timeLabel) . '</td>';
    echo '<td>' . htmlspecialchars($dateLabel) . '</td>';
    echo '</tr>';
}

if (count($records) === 0) {
    echo '<tr><td colspan="5">No attendance records found for the selected filters.</td></tr>';
}

echo '</tbody></table>';
echo '</body></html>';
exit;
?>
