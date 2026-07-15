<?php
include(__DIR__ . '/../conn/conn.php');

$stmt = $conn->prepare("SELECT * FROM tbl_student ORDER BY tbl_student_id DESC");
$stmt->execute();
$students = $stmt->fetchAll();
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>Student Masterlist</h1>
        <p>Manage registered students and their QR codes.</p>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addStudentModal" onclick="DashboardApp.resetAddStudentModal()">
        <i class="bi bi-person-plus-fill"></i>
        Add Student
    </button>
</div>

<!-- Students Table -->
<div class="dash-card">
    <div class="dash-card-header">
        <div class="dash-card-title">
            <i class="bi bi-people-fill"></i>
            All Students
        </div>
        <span style="font-size: 0.78rem; color: var(--gray-500);">
            <?= count($students) ?> student<?= count($students) !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="dash-card-body no-padding">
        <div class="table-wrapper">
            <table class="dash-table" id="studentTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Course & Section</th>
                        <th>Parent Name</th>
                        <th>Parent Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $row): ?>
                        <tr>
                            <td id="studentID-<?= $row['tbl_student_id'] ?>"><?= $row["tbl_student_id"] ?></td>
                            <td id="studentName-<?= $row['tbl_student_id'] ?>" style="font-weight: 600; color: var(--gray-800);"><?= htmlspecialchars($row["student_name"]) ?></td>
                            <td id="studentCourse-<?= $row['tbl_student_id'] ?>"><?= htmlspecialchars($row["course_section"]) ?></td>
                            <td id="studentParentName-<?= $row['tbl_student_id'] ?>"><?= htmlspecialchars($row["parent_name"] ?? '') ?></td>
                            <td id="studentParentPhone-<?= $row['tbl_student_id'] ?>"><?= htmlspecialchars($row["parent_phone"] ?? '') ?></td>
                            <td>
                                <div class="action-group">
                                    <button class="btn-action btn-action-view" title="Student ID Card" data-bs-toggle="modal" data-bs-target="#studentCardModal<?= $row['tbl_student_id'] ?>">
                                        <i class="bi bi-person-vcard"></i>
                                    </button>
                                    <button class="btn-action btn-action-edit" title="Edit Student" onclick="DashboardApp.openUpdateModal(<?= $row['tbl_student_id'] ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-action btn-action-delete" title="Delete Student" onclick="DashboardApp.deleteStudent(<?= $row['tbl_student_id'] ?>)">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <?php
                            $sid = (int) $row['tbl_student_id'];
                            $sName = htmlspecialchars($row['student_name']);
                            $sCourse = htmlspecialchars($row['course_section']);
                            $sParent = htmlspecialchars($row['parent_name'] ?? '');
                            $sPhone = htmlspecialchars($row['parent_phone'] ?? '');
                            $sCode = htmlspecialchars($row['generated_code']);
                            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' . urlencode($row['generated_code']);
                            $nameParts = preg_split('/\s+/', trim($row['student_name']));
                            $initials = strtoupper(substr($nameParts[0] ?? 'S', 0, 1) . substr(end($nameParts) ?: '', 0, 1));
                            $studentNo = 'STU-' . str_pad((string) $sid, 5, '0', STR_PAD_LEFT);
                        ?>

                        <!-- Student ID Card Modal -->
                        <div class="modal fade" id="studentCardModal<?= $sid ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content student-card-modal">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-person-vcard me-2" style="color: var(--primary);"></i>Student ID Card</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body student-card-modal-body">
                                        <div class="student-id-card" id="student-id-card-<?= $sid ?>">
                                            <aside class="id-card-rail">
                                                <div class="id-card-rail-top">
                                                    <div class="id-card-rail-mark"><i class="bi bi-qr-code-scan"></i></div>
                                                    <div class="id-card-rail-title">QR Attend</div>
                                                </div>
                                                <div class="id-card-rail-mid">
                                                    <span>STUDENT</span>
                                                    <span>IDENTITY</span>
                                                    <span>CARD</span>
                                                </div>
                                                <div class="id-card-rail-bottom">ATTENDANCE SYSTEM</div>
                                            </aside>

                                            <div class="id-card-main">
                                                <div class="id-card-topbar">
                                                    <div>
                                                        <div class="id-card-org">Campus Attendance Authority</div>
                                                        <div class="id-card-doc">Official Student Identification Card</div>
                                                    </div>
                                                    <div class="id-card-chip" aria-hidden="true">
                                                        <span></span><span></span><span></span><span></span>
                                                    </div>
                                                </div>

                                                <div class="id-card-content">
                                                    <div class="id-card-identity">
                                                        <div class="id-card-photo">
                                                            <div class="id-card-avatar"><?= htmlspecialchars($initials) ?></div>
                                                        </div>
                                                        <div class="id-card-details">
                                                            <div class="id-card-kicker">Registered Student</div>
                                                            <h3 class="id-card-name"><?= $sName ?></h3>
                                                            <div class="id-card-divider"></div>
                                                            <dl class="id-card-grid">
                                                                <div>
                                                                    <dt>Student No.</dt>
                                                                    <dd><?= $studentNo ?></dd>
                                                                </div>
                                                                <div>
                                                                    <dt>Course & Section</dt>
                                                                    <dd><?= $sCourse ?></dd>
                                                                </div>
                                                                <?php if ($sParent !== ''): ?>
                                                                <div>
                                                                    <dt>Guardian</dt>
                                                                    <dd><?= $sParent ?></dd>
                                                                </div>
                                                                <?php endif; ?>
                                                                <?php if ($sPhone !== ''): ?>
                                                                <div>
                                                                    <dt>Emergency Contact</dt>
                                                                    <dd><?= $sPhone ?></dd>
                                                                </div>
                                                                <?php endif; ?>
                                                            </dl>
                                                        </div>
                                                    </div>

                                                    <div class="id-card-scan">
                                                        <div class="id-card-qr-wrap">
                                                            <img src="<?= $qrUrl ?>" alt="Student QR Code" class="id-card-qr-img" width="160" height="160">
                                                        </div>
                                                        <div class="id-card-scan-label">Scan to record attendance</div>
                                                        <div class="id-card-scan-code"><?= $sCode ?></div>
                                                    </div>
                                                </div>

                                                <div class="id-card-bottom">
                                                    <div class="id-card-bottom-left">
                                                        <strong>Property of QR Attend</strong>
                                                        <span>If found, return to the administration office.</span>
                                                    </div>
                                                    <div class="id-card-bottom-right">
                                                        <span class="id-card-sig-label">Authorized</span>
                                                        <span class="id-card-sig">QR Attend Registry</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn-primary-custom" onclick="DashboardApp.printStudentCard(<?= $sid ?>)">
                                            <i class="bi bi-printer"></i>
                                            Print Card
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2" style="color: var(--primary);"></i>Add New Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label-custom" for="studentName">Full Name</label>
                    <input type="text" class="form-control-custom" id="studentName" placeholder="Enter student name" maxlength="100">
                    <div class="field-error" data-error-for="studentName"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="studentCourse">Course & Section</label>
                    <input type="text" class="form-control-custom" id="studentCourse" placeholder="e.g., BSIS 4B" maxlength="100">
                    <div class="field-error" data-error-for="studentCourse"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="parentName">Parent Name <span class="optional">(optional)</span></label>
                    <input type="text" class="form-control-custom" id="parentName" placeholder="Enter parent/guardian name" maxlength="100">
                    <div class="field-error" data-error-for="parentName"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="parentPhone">Parent Phone <span class="optional">(optional)</span></label>
                    <input type="text" class="form-control-custom" id="parentPhone" placeholder="e.g., +1 555 123 4567" maxlength="20">
                    <div class="field-error" data-error-for="parentPhone"></div>
                    <div class="form-hint">7–15 digits; spaces and + - ( ) allowed.</div>
                </div>
                <input type="hidden" id="generatedCode">
                <div class="field-error" data-error-for="generatedCode"></div>

                <button type="button" class="btn-secondary-custom w-100 qr-generator" onclick="DashboardApp.generateQrCode()">
                    <i class="bi bi-qr-code"></i>
                    Generate QR Code
                </button>

                <div class="qr-con text-center mt-3" style="display: none;">
                    <p style="font-size: 0.82rem; color: var(--gray-600);">Save this QR code for the student.</p>
                    <img class="mb-3" src="" id="qrImg" alt="QR Code" style="border-radius: 2.5px; border: 1px solid var(--gray-200);">
                </div>

                <div class="modal-close mt-3" style="display: none;">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn-secondary-custom flex-fill" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn-primary-custom flex-fill" onclick="DashboardApp.addStudent()">
                            <i class="bi bi-check2-circle"></i>
                            Add Student
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Student Modal -->
<div class="modal fade" id="updateStudentModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2" style="color: var(--info);"></i>Update Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="updateStudentId">
                <div class="mb-3">
                    <label class="form-label-custom" for="updateStudentName">Full Name</label>
                    <input type="text" class="form-control-custom" id="updateStudentName" maxlength="100">
                    <div class="field-error" data-error-for="updateStudentName"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="updateStudentCourse">Course & Section</label>
                    <input type="text" class="form-control-custom" id="updateStudentCourse" maxlength="100">
                    <div class="field-error" data-error-for="updateStudentCourse"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="updateParentName">Parent Name <span class="optional">(optional)</span></label>
                    <input type="text" class="form-control-custom" id="updateParentName" maxlength="100">
                    <div class="field-error" data-error-for="updateParentName"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="updateParentPhone">Parent Phone <span class="optional">(optional)</span></label>
                    <input type="text" class="form-control-custom" id="updateParentPhone" maxlength="20">
                    <div class="field-error" data-error-for="updateParentPhone"></div>
                    <div class="form-hint">7–15 digits; spaces and + - ( ) allowed.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="DashboardApp.updateStudent()">
                    <i class="bi bi-check2-circle"></i>
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
