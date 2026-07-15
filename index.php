<?php
require_once __DIR__ . '/conn/session.php';
$isLoggedIn = isset($_SESSION['user_id']);
$appAuthConfig = auth_public_config();

if ($isLoggedIn) {
    // Generate user initials
    $nameParts = explode(' ', $_SESSION['name']);
    $initials = '';
    foreach ($nameParts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="QR Code Attendance Management System — Track student attendance with QR scanning technology">
    <title>QR Attendance — Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="./assets/img/logo.svg">
    <link rel="apple-touch-icon" href="./assets/img/logo.png">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <!-- Dashboard CSS -->
    <link href="./assets/css/dashboard.css" rel="stylesheet">
    <script>
        window.APP_AUTH = <?= json_encode($appAuthConfig, JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body>

    <?php if (!$isLoggedIn): ?>
        <!-- Login Screen -->
        <div class="login-screen">
            <div class="login-card">
                <div class="login-logo">
                    <img src="./assets/img/logo.svg" alt="QR Attend" width="48" height="48">
                </div>
                <h4 class="text-center fw-bold mb-1" style="color: var(--gray-900); letter-spacing: -0.5px;">QR Attend</h4>
                <p class="text-center text-muted mb-4" style="font-size: 0.82rem;">Sign in to manage the system</p>
                
                <div id="login-error" class="alert alert-danger p-2 mb-3" style="display: none; font-size: 0.8rem; border-radius: 3px;"></div>
                <div id="login-success" class="alert alert-success p-2 mb-3" style="display: none; font-size: 0.8rem; border-radius: 3px;"></div>
                
                <form id="loginForm" novalidate>
                    <div class="mb-3">
                        <label class="form-label-custom" for="loginUsername">Username</label>
                        <input type="text" class="form-control-custom" id="loginUsername" placeholder="Enter username" maxlength="50" autocomplete="username">
                        <div class="field-error" id="loginUsername-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="loginPassword">Password</label>
                        <input type="password" class="form-control-custom" id="loginPassword" placeholder="Enter password" maxlength="72" autocomplete="current-password">
                        <div class="field-error" id="loginPassword-error"></div>
                    </div>

                    <div class="d-flex justify-content-end mb-3">
                        <a href="#" id="forgotPasswordLink" style="font-size: 0.78rem; color: var(--primary); text-decoration: none; font-weight: 600;">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="btn-primary-custom w-100 justify-content-center py-2" style="font-size: 0.85rem;">
                        Sign In
                        <i class="bi bi-box-arrow-in-right"></i>
                    </button>
                </form>

                <form id="forgotPasswordForm" novalidate style="display: none;">
                    <p class="text-muted mb-3" style="font-size: 0.8rem;">Enter your username. We'll SMS a reset code to the phone on your profile.</p>
                    <div class="mb-3">
                        <label class="form-label-custom" for="forgotUsername">Username</label>
                        <input type="text" class="form-control-custom" id="forgotUsername" maxlength="50" autocomplete="username">
                        <div class="field-error" id="forgotUsername-error"></div>
                    </div>
                    <button type="submit" class="btn-primary-custom w-100 justify-content-center py-2 mb-2" style="font-size: 0.85rem;">
                        Send Reset Code
                    </button>
                    <button type="button" class="btn-secondary-custom w-100 justify-content-center" id="backToLoginBtn">Back to Sign In</button>
                </form>

                <form id="resetPasswordForm" novalidate style="display: none;">
                    <p class="text-muted mb-3" style="font-size: 0.8rem;">Enter the SMS code and choose a new password.</p>
                    <input type="hidden" id="resetUsername">
                    <div class="mb-3">
                        <label class="form-label-custom" for="resetOtp">Reset Code</label>
                        <input type="text" class="form-control-custom" id="resetOtp" maxlength="8" inputmode="numeric" autocomplete="one-time-code">
                        <div class="field-error" id="resetOtp-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="resetPassword">New Password</label>
                        <input type="password" class="form-control-custom" id="resetPassword" maxlength="72" autocomplete="new-password">
                        <div class="field-error" id="resetPassword-error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom" for="resetConfirmPassword">Confirm Password</label>
                        <input type="password" class="form-control-custom" id="resetConfirmPassword" maxlength="72" autocomplete="new-password">
                        <div class="field-error" id="resetConfirmPassword-error"></div>
                    </div>
                    <button type="submit" class="btn-primary-custom w-100 justify-content-center py-2 mb-2" style="font-size: 0.85rem;">
                        Reset Password
                    </button>
                    <button type="button" class="btn-secondary-custom w-100 justify-content-center" id="backToForgotBtn">Resend Code</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Sidebar Backdrop (mobile) -->
        <div id="sidebar-backdrop" class="sidebar-backdrop"></div>

        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <a href="#dashboard" class="sidebar-brand">
                    <div class="sidebar-brand-icon">
                        <img src="./assets/img/logo.svg" alt="" width="36" height="36">
                    </div>
                    <span class="sidebar-brand-text">QR Attend</span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-label">Main Menu</div>

                <a href="#dashboard" class="sidebar-link active" data-page="dashboard" data-title="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span class="link-text">Dashboard</span>
                </a>

                <a href="#attendance" class="sidebar-link" data-page="attendance" data-title="Attendance">
                    <i class="bi bi-clipboard-check-fill"></i>
                    <span class="link-text">Attendance</span>
                </a>

                <a href="#students" class="sidebar-link" data-page="students" data-title="Students">
                    <i class="bi bi-people-fill"></i>
                    <span class="link-text">Students</span>
                </a>

                <a href="#profile" class="sidebar-link" data-page="profile" data-title="My Profile">
                    <i class="bi bi-person-circle"></i>
                    <span class="link-text">My Profile</span>
                </a>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="#users" class="sidebar-link" data-page="users" data-title="Users">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span class="link-text">Users</span>
                </a>
                <?php endif; ?>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['name']) ?></div>
                        <div class="sidebar-user-role"><?= ucfirst(htmlspecialchars($_SESSION['role'])) ?></div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div id="main-wrapper" class="main-wrapper">

            <!-- Top Navbar -->
            <header class="top-navbar">
                <div class="top-navbar-left">
                    <button id="sidebar-toggle" class="sidebar-toggle" title="Toggle Sidebar">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 id="page-title" class="page-title">Dashboard</h1>
                </div>

                <div class="top-navbar-right">
                    <button class="navbar-icon-btn" title="Fullscreen" onclick="document.documentElement.requestFullscreen()">
                        <i class="bi bi-arrows-fullscreen"></i>
                    </button>
                    <div class="dropdown">
                        <button class="navbar-user-btn" data-bs-toggle="dropdown">
                            <div class="navbar-avatar"><?= htmlspecialchars($initials) ?></div>
                            <span class="navbar-user-name"><?= htmlspecialchars($_SESSION['name']) ?></span>
                            <i class="bi bi-chevron-down" style="font-size: 0.65rem; color: var(--gray-400);"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 3.5px; border: 1px solid var(--gray-200); box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
                            <li><span class="dropdown-item-text text-muted" style="font-size: 0.75rem;">Logged in as <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#profile"><i class="bi bi-person-circle me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="DashboardApp.logout(event)"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Main Content (AJAX loaded) -->
            <main id="main-content" class="content-area">
                <!-- Page content loads here via AJAX -->
                <div class="d-flex justify-content-center align-items-center" style="height: 60vh;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </main>
        </div>
    <?php endif; ?>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <!-- Instascan (QR Scanner) -->
    <script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
    <!-- Dashboard JS -->
    <script src="./assets/js/dashboard.js"></script>

</body>
</html>