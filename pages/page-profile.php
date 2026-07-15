<?php
require_once __DIR__ . '/../conn/session.php';
if (!isset($_SESSION['user_id'])) {
    echo '<div class="empty-state">
        <div class="empty-state-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h5>Please sign in</h5>
        <p>You need to be logged in to view your profile.</p>
    </div>';
    exit;
}

include(__DIR__ . '/../conn/conn.php');
include(__DIR__ . '/../conn/schema.php');
ensure_user_profile_columns($conn);

$stmt = $conn->prepare("SELECT tbl_user_id, name, username, role, phone, created_at FROM tbl_user WHERE tbl_user_id = :id LIMIT 1");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<div class="empty-state"><h5>User not found</h5></div>';
    exit;
}
?>

<div class="page-header">
    <h1>My Profile</h1>
    <p>Update your account details and password.</p>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title"><i class="bi bi-person-badge"></i> Account Details</div>
            </div>
            <div class="dash-card-body">
                <form id="profileForm" onsubmit="return DashboardApp.saveProfile(event)">
                    <div class="mb-3">
                        <label class="form-label-custom" for="profileName">Full Name</label>
                        <input type="text" class="form-control-custom" id="profileName" maxlength="100" value="<?= htmlspecialchars($user['name']) ?>">
                        <div class="field-error" data-error-for="profileName"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="profileUsername">Username</label>
                        <input type="text" class="form-control-custom" id="profileUsername" maxlength="50" value="<?= htmlspecialchars($user['username']) ?>">
                        <div class="field-error" data-error-for="profileUsername"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="profilePhone">Phone <span class="optional">(for password reset SMS)</span></label>
                        <input type="text" class="form-control-custom" id="profilePhone" maxlength="20" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g., 0700123456">
                        <div class="field-error" data-error-for="profilePhone"></div>
                        <div class="form-hint">Used by EgoSMS for password reset codes.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Role</label>
                        <input type="text" class="form-control-custom" value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" readonly>
                    </div>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-check2-circle"></i>
                        Save Profile
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="dash-card">
            <div class="dash-card-header">
                <div class="dash-card-title"><i class="bi bi-key"></i> Change Password</div>
            </div>
            <div class="dash-card-body">
                <form id="changePasswordForm" onsubmit="return DashboardApp.changePassword(event)">
                    <div class="mb-3">
                        <label class="form-label-custom" for="currentPassword">Current Password</label>
                        <input type="password" class="form-control-custom" id="currentPassword" maxlength="72" autocomplete="current-password">
                        <div class="field-error" data-error-for="currentPassword"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="newPassword">New Password</label>
                        <input type="password" class="form-control-custom" id="newPassword" maxlength="72" autocomplete="new-password">
                        <div class="field-error" data-error-for="newPassword"></div>
                        <div class="form-hint">At least 8 characters, with one letter and one number.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="confirmNewPassword">Confirm New Password</label>
                        <input type="password" class="form-control-custom" id="confirmNewPassword" maxlength="72" autocomplete="new-password">
                        <div class="field-error" data-error-for="confirmNewPassword"></div>
                    </div>
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-shield-lock"></i>
                        Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
