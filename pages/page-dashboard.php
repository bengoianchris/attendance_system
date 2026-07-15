<?php
include(__DIR__ . '/../conn/conn.php');

// Total Students
$stmtStudents = $conn->prepare("SELECT COUNT(*) as total FROM tbl_student");
$stmtStudents->execute();
$totalStudents = $stmtStudents->fetch()['total'];

// Today's Attendance
$today = date("Y-m-d");
$stmtToday = $conn->prepare("SELECT COUNT(*) as total FROM tbl_attendance WHERE DATE(time_in) = :today");
$stmtToday->bindParam(":today", $today, PDO::PARAM_STR);
$stmtToday->execute();
$todayAttendance = $stmtToday->fetch()['total'];

// Total Records
$stmtRecords = $conn->prepare("SELECT COUNT(*) as total FROM tbl_attendance");
$stmtRecords->execute();
$totalRecords = $stmtRecords->fetch()['total'];

// Attendance Rate
$attendanceRate = $totalStudents > 0 ? round(($todayAttendance / $totalStudents) * 100) : 0;

// Recent 10 attendance records
$stmtRecent = $conn->prepare("SELECT a.tbl_attendance_id, s.student_name, s.course_section, a.time_in 
    FROM tbl_attendance a 
    LEFT JOIN tbl_student s ON s.tbl_student_id = a.tbl_student_id 
    ORDER BY a.time_in DESC LIMIT 10");
$stmtRecent->execute();
$recentRecords = $stmtRecent->fetchAll();
?>

<!-- Page Header -->
<div class="page-header">
    <h1>Dashboard</h1>
    <p>Welcome back! Here's an overview of today's attendance.</p>
</div>

<!-- Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-primary fade-in-up">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <div class="stat-card-value" data-counter="<?= $totalStudents ?>"><?= $totalStudents ?></div>
            <div class="stat-card-label">Total Students</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-success fade-in-up">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <i class="bi bi-clipboard-check-fill"></i>
                </div>
            </div>
            <div class="stat-card-value" data-counter="<?= $todayAttendance ?>"><?= $todayAttendance ?></div>
            <div class="stat-card-label">Today's Attendance</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-warning fade-in-up">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
            </div>
            <div class="stat-card-value"><?= $attendanceRate ?>%</div>
            <div class="stat-card-label">Attendance Rate</div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6">
        <div class="stat-card stat-info fade-in-up">
            <div class="stat-card-header">
                <div class="stat-card-icon">
                    <i class="bi bi-journal-text"></i>
                </div>
            </div>
            <div class="stat-card-value" data-counter="<?= $totalRecords ?>"><?= $totalRecords ?></div>
            <div class="stat-card-label">Total Records</div>
        </div>
    </div>
</div>

<!-- Recent Attendance + Quick Actions -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <i class="bi bi-clock-history"></i>
                    Recent Attendance
                </div>
                <a href="#attendance" class="btn-secondary-custom" style="font-size: 0.75rem; padding: 6px 14px;">
                    View All <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="dash-card-body no-padding">
                <div class="table-wrapper">
                    <table class="dash-table" id="recentAttendanceTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Course & Section</th>
                                <th>Time In</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentRecords) > 0): ?>
                                <?php foreach ($recentRecords as $row): ?>
                                    <tr>
                                        <td><?= $row["tbl_attendance_id"] ?></td>
                                        <td style="font-weight: 600; color: var(--gray-800);"><?= htmlspecialchars($row["student_name"]) ?></td>
                                        <td><?= htmlspecialchars($row["course_section"]) ?></td>
                                        <td><?= date('M d, Y h:i A', strtotime($row["time_in"])) ?></td>
                                        <td><span class="status-badge present">Present</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center" style="padding: 40px; color: var(--gray-400);">
                                        No attendance records today
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title">
                    <i class="bi bi-lightning-fill"></i>
                    Quick Actions
                </div>
            </div>
            <div class="dash-card-body">
                <div class="d-flex flex-column gap-3">
                    <a href="#attendance" class="quick-action-card">
                        <div class="quick-action-icon" style="background: var(--success-light); color: var(--success);">
                            <i class="bi bi-qr-code-scan"></i>
                        </div>
                        <div class="quick-action-text">
                            <h6>Scan QR Code</h6>
                            <p>Record student attendance via QR</p>
                        </div>
                    </a>

                    <a href="#students" class="quick-action-card">
                        <div class="quick-action-icon" style="background: var(--primary-light); color: var(--primary);">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <div class="quick-action-text">
                            <h6>Manage Students</h6>
                            <p>Add, edit, or remove students</p>
                        </div>
                    </a>

                    <a href="#attendance" class="quick-action-card">
                        <div class="quick-action-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </div>
                        <div class="quick-action-text">
                            <h6>View Records</h6>
                            <p>Browse all attendance records</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
