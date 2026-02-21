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
$userId = (int) $_SESSION['pending_2fa_user_id'];
$username = $_SESSION['pending_2fa_username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $code = trim($_POST['code'] ?? '');
        $secret = $_SESSION['pending_2fa_secret'] ?? '';
        if (empty($secret)) {
            $error = 'Session expired. Please log in again.';
        } elseif (totp_verify($secret, $code)) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET totp_secret = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $secret, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $_SESSION['pending_2fa_full_name'];
            $_SESSION['username'] = $_SESSION['pending_2fa_username'];
            $_SESSION['user_role'] = $_SESSION['pending_2fa_role'];
            $_SESSION['employee_id'] = $_SESSION['pending_2fa_employee_id'] ?? null;
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_username'], $_SESSION['pending_2fa_full_name'], $_SESSION['pending_2fa_role'], $_SESSION['pending_2fa_employee_id'], $_SESSION['pending_2fa_secret']);
            log_activity($conn, $userId, 'login');
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid code. Please enter the current code from your app.';
        }
    }
} else {
    if (empty($_SESSION['pending_2fa_secret'])) {
        $_SESSION['pending_2fa_secret'] = totp_generate_secret(20);
    }
}

$secret = $_SESSION['pending_2fa_secret'] ?? '';
$qrUrl = totp_get_qr_url($secret, $username, 'HRMS');

$pageTitle = 'Set up 2FA';
require_once __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-100 via-slate-50 to-primary-50 px-4 py-8">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200/60 overflow-hidden">
            <div class="px-8 pt-10 pb-6">
                <div class="text-center mb-6">
                    <div class="mx-auto w-14 h-14 rounded-full bg-primary-100 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <h1 class="text-xl font-bold text-slate-800">Set up Google Authenticator</h1>
                    <p class="text-slate-500 mt-1">Scan the QR code with your authenticator app, then enter the 6-digit code below.</p>
                </div>

                <div class="flex flex-col items-center mb-6">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($qrUrl) ?>" alt="QR Code" class="rounded-xl border border-slate-200 w-48 h-48">
                    <p class="mt-3 text-xs text-slate-500">Or enter this key manually: <code class="bg-slate-100 px-1 rounded break-all"><?= htmlspecialchars($secret) ?></code></p>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <?php if ($error): ?>
                    <div class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <div>
                        <label for="code" class="block text-sm font-medium text-slate-700 mb-1">Enter 6-digit code</label>
                        <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required
                            placeholder="000000"
                            class="w-full rounded-lg border border-slate-300 px-4 py-3 text-center text-xl tracking-[0.4em] text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <button type="submit" class="w-full py-3 px-4 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition">
                        Verify and finish
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
