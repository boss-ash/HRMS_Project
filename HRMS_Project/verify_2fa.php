<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/activity_log.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
if (empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: login.php');
    exit;
}

$base = getBasePath();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $userId = (int) $_SESSION['pending_2fa_user_id'];
        $stmt = mysqli_prepare($conn, "SELECT totp_secret FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($row && !empty($row['totp_secret']) && totp_verify($row['totp_secret'], $code)) {
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $_SESSION['pending_2fa_full_name'];
            $_SESSION['username'] = $_SESSION['pending_2fa_username'];
            $_SESSION['user_role'] = $_SESSION['pending_2fa_role'];
            $_SESSION['employee_id'] = $_SESSION['pending_2fa_employee_id'] ?? null;
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_full_name'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_employee_id']);
            log_activity($conn, $userId, 'login');
            header('Location: dashboard.php');
            exit;
        }
        $error = 'Invalid or expired code. Please try again.';
    }
}

$pageTitle = 'Verify Code';
require_once __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-100 via-slate-50 to-primary-50 px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200/60 overflow-hidden">
            <div class="px-8 pt-10 pb-6">
                <div class="text-center mb-6">
                    <div class="mx-auto w-14 h-14 rounded-full bg-primary-100 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h1 class="text-xl font-bold text-slate-800">Two-Factor Authentication</h1>
                    <p class="text-slate-500 mt-1">Enter the 6-digit code from your authenticator app</p>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <?php if ($error): ?>
                    <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <div>
                        <label for="code" class="block text-sm font-medium text-slate-700 mb-1">Verification code</label>
                        <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required
                            placeholder="000000"
                            class="w-full rounded-lg border border-slate-300 px-4 py-3 text-center text-xl tracking-[0.4em] text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <button type="submit" class="w-full py-3 px-4 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition">
                        Verify and sign in
                    </button>
                </form>
                <p class="mt-4 text-center">
                    <a href="login.php" class="text-sm text-slate-500 hover:text-primary-600">← Back to login</a>
                </p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
