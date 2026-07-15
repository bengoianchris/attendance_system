<?php
include(__DIR__ . '/../conn/conn.php');

$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$course = isset($_GET['course']) ? trim($_GET['course']) : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Validate date formats (YYYY-MM-DD)
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$coursesStmt = $conn->prepare("SELECT DISTINCT course_section FROM tbl_student WHERE course_section IS NOT NULL AND course_section <> '' ORDER BY course_section ASC");
$coursesStmt->execute();
$courses = $coursesStmt->fetchAll(PDO::FETCH_COLUMN);

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

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$queryParams = array_filter([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'course' => $course,
    'q' => $search,
], static function ($value) {
    return $value !== '' && $value !== null;
});
$exportQuery = http_build_query($queryParams);
$hasFilters = !empty($queryParams);
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Attendance Records</h1>
        <p>Scan QR codes, filter records, and export attendance data.</p>
    </div>
    <a class="btn-primary-custom" href="./endpoint/export-attendance.php<?= $exportQuery !== '' ? '?' . htmlspecialchars($exportQuery) : '' ?>" id="exportAttendanceBtn">
        <i class="bi bi-file-earmark-excel"></i>
        Export Excel
    </a>
</div>

<div class="row g-4">
    <!-- QR Scanner Panel -->
    <div hidden class="col-lg-4">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <i class="bi bi-qr-code-scan"></i>
                    QR Scanner
                </div>
            </div>
            <div class="dash-card-body">
                <div class="scanner-con">
                    <div class="scanner-panel">
                        <h5><i class="bi bi-camera-video me-2"></i>Scan QR Code</h5>
                        <p style="color: var(--gray-400); font-size: 0.8rem; margin-bottom: 16px;">Position the QR code in front of your camera</p>
                        <video id="interactive" class="viewport" width="100%"></video>
                    </div>
                </div>

                <div class="qr-detected-container" style="display: none;">
                    <div class="scanner-detected">
                        <div style="font-size: 2.5rem; margin-bottom: 8px;">
                            <i class="bi bi-check-circle-fill" style="color: var(--success);"></i>
                        </div>
                        <h4>QR Code Detected!</h4>
                        <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 20px;">Student QR code has been successfully scanned.</p>
                        <input type="hidden" id="detected-qr-code">
                        <button type="button" class="btn-primary-custom w-100" onclick="DashboardApp.submitAttendance()">
                            <i class="bi bi-check2-circle"></i>
                            Submit Attendance
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Records Table -->
    <div class="col-lg-12">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <i class="bi bi-table"></i>
                    Attendance Records
                </div>
                <span style="font-size: 0.78rem; color: var(--gray-500);" id="attendanceRecordCount">
                    <?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?><?= $hasFilters ? ' (filtered)' : '' ?>
                </span>
            </div>

            <div class="attendance-filters">
                <form id="attendanceFilterForm" class="attendance-filter-form" onsubmit="return DashboardApp.applyAttendanceFilters(event)">
                    <div class="attendance-filter-grid">
                        <div class="filter-field">
                            <label class="form-label-custom" for="filterDateFrom">From Date</label>
                            <input type="date" class="form-control-custom" id="filterDateFrom" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                        </div>
                        <div class="filter-field">
                            <label class="form-label-custom" for="filterDateTo">To Date</label>
                            <input type="date" class="form-control-custom" id="filterDateTo" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                        <div class="filter-field">
                            <label class="form-label-custom" for="filterCourse">Course & Section</label>
                            <select class="form-control-custom" id="filterCourse" name="course">
                                <option value="">All courses</option>
                                <?php foreach ($courses as $courseOption): ?>
                                    <option value="<?= htmlspecialchars($courseOption) ?>" <?= $course === $courseOption ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($courseOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label class="form-label-custom" for="filterSearch">Student Name</label>
                            <input type="text" class="form-control-custom" id="filterSearch" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search student..." maxlength="100">
                        </div>
                    </div>
                    <div class="attendance-filter-actions">
                        <button type="button" class="btn-secondary-custom" onclick="DashboardApp.filterAttendanceToday()">
                            <i class="bi bi-calendar-day"></i>
                            Today
                        </button>
                        <button type="submit" class="btn-primary-custom">
                            <i class="bi bi-funnel"></i>
                            Apply Filters
                        </button>
                        <button type="button" class="btn-secondary-custom" onclick="DashboardApp.clearAttendanceFilters()">
                            <i class="bi bi-x-circle"></i>
                            Clear
                        </button>
                    </div>
                </form>
            </div>

            <div class="dash-card-body no-padding">
                <div class="table-wrapper">
                    <table class="dash-table" id="attendanceTable" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Course & Section</th>
                                <th>Time In</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($records) > 0): ?>
                                <?php foreach ($records as $row): ?>
                                    <tr>
                                        <td><?= $row["tbl_attendance_id"] ?></td>
                                        <td style="font-weight: 600; color: var(--gray-800);"><?= htmlspecialchars($row["student_name"] ?? '') ?></td>
                                        <td><?= htmlspecialchars($row["course_section"] ?? '') ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($row["time_in"])) ?></td>
                                        <td>
                                            <div class="action-group">
                                                <button class="btn-action btn-action-delete" title="Delete" onclick="DashboardApp.deleteAttendance(<?= (int) $row['tbl_attendance_id'] ?>)">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
