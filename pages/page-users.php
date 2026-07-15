<?php
require_once __DIR__ . '/../conn/session.php';
$adminRole = (string) cfg('auth.admin_role', 'admin');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== $adminRole) {
    echo '<div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h5>Access Denied</h5>
        <p>You do not have the required permissions to view this page.</p>
    </div>';
    exit;
}

include(__DIR__ . '/../conn/conn.php');

$stmt = $conn->prepare("SELECT tbl_user_id, name, username, role, created_at FROM tbl_user ORDER BY tbl_user_id DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Page Header -->
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1>User Management</h1>
        <p>Manage administrator and staff accounts.</p>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addUserModal" onclick="DashboardApp.resetAddUserModal()">
        <i class="bi bi-person-plus-fill"></i>
        Add User
    </button>
</div>

<!-- Users Table -->
<div class="dash-card">
    <div class="dash-card-header">
        <div class="dash-card-title">
            <i class="bi bi-people-fill"></i>
            All Users
        </div>
        <span style="font-size: 0.78rem; color: var(--gray-500);">
            <?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="dash-card-body no-padding">
        <div class="table-wrapper">
            <table class="dash-table" id="userTable" style="width: 100%;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td id="userID-<?= $row['tbl_user_id'] ?>"><?= $row["tbl_user_id"] ?></td>
                            <td id="userName-<?= $row['tbl_user_id'] ?>" style="font-weight: 600; color: var(--gray-800);"><?= htmlspecialchars($row["name"]) ?></td>
                            <td id="userUsername-<?= $row['tbl_user_id'] ?>"><?= htmlspecialchars($row["username"]) ?></td>
                            <td>
                                <span class="badge <?= $row['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>" style="font-size: 0.7rem; border-radius: 3px;">
                                    <?= strtoupper($row['role']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y h:i A', strtotime($row["created_at"])) ?></td>
                            <td>
                                <div class="action-group">
                                    <button class="btn-action btn-action-edit" title="Edit User" onclick="DashboardApp.openUpdateUserModal(<?= $row['tbl_user_id'] ?>, '<?= htmlspecialchars($row['role']) ?>')">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($row['tbl_user_id'] != $_SESSION['user_id']): ?>
                                        <button class="btn-action btn-action-delete" title="Delete User" onclick="DashboardApp.deleteUser(<?= $row['tbl_user_id'] ?>)">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-action btn-action-delete" title="Delete User (Self)" disabled style="opacity: 0.3; cursor: not-allowed;">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2" style="color: var(--primary);"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label-custom" for="newUserName">Full Name</label>
                    <input type="text" class="form-control-custom" id="newUserName" placeholder="Enter full name" maxlength="100">
                    <div class="field-error" data-error-for="newUserName"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="newUserUsername">Username</label>
                    <input type="text" class="form-control-custom" id="newUserUsername" placeholder="Enter username" maxlength="50">
                    <div class="field-error" data-error-for="newUserUsername"></div>
                    <div class="form-hint">3–50 characters; letters, numbers, . _ - only.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="newUserPassword">Password</label>
                    <input type="password" class="form-control-custom" id="newUserPassword" placeholder="Enter password" maxlength="72">
                    <div class="field-error" data-error-for="newUserPassword"></div>
                    <div class="form-hint">At least 8 characters, with one letter and one number.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="newUserRole">Role</label>
                    <select class="form-control-custom" id="newUserRole">
                        <option value="staff" selected>Staff (View & Log Attendance)</option>
                        <option value="admin">Admin (Full Control)</option>
                    </select>
                    <div class="field-error" data-error-for="newUserRole"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="DashboardApp.addUser()">
                    <i class="bi bi-check2-circle"></i>
                    Add User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Update User Modal -->
<div class="modal fade" id="updateUserModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2" style="color: var(--info);"></i>Update User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="updateUserId">
                <div class="mb-3">
                    <label class="form-label-custom" for="updateUserName">Full Name</label>
                    <input type="text" class="form-control-custom" id="updateUserName" maxlength="100">
                    <div class="field-error" data-error-for="updateUserName"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="updateUserUsername">Username</label>
                    <input type="text" class="form-control-custom" id="updateUserUsername" maxlength="50">
                    <div class="field-error" data-error-for="updateUserUsername"></div>
                    <div class="form-hint">3–50 characters; letters, numbers, . _ - only.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="updateUserPassword">New Password <span class="optional">(leave empty to keep current)</span></label>
                    <input type="password" class="form-control-custom" id="updateUserPassword" placeholder="Enter new password" maxlength="72">
                    <div class="field-error" data-error-for="updateUserPassword"></div>
                    <div class="form-hint">If set: at least 8 characters, with one letter and one number.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom" for="updateUserRole">Role</label>
                    <select class="form-control-custom" id="updateUserRole">
                        <option value="staff">Staff (View & Log Attendance)</option>
                        <option value="admin">Admin (Full Control)</option>
                    </select>
                    <div class="field-error" data-error-for="updateUserRole"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-custom" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-primary-custom" onclick="DashboardApp.updateUser()">
                    <i class="bi bi-check2-circle"></i>
                    Update
                </button>
            </div>
        </div>
    </div>
</div>
