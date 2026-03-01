<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/totp.php';
require_once __DIR__ . '/includes/activity_log.php';

$base = getBaseUrl();
if (isLoggedIn()) {
    header('Location: ' . $base . 'dashboard.php');
    exit;
}
if (empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: ' . $base . 'login.php');
    exit;
}

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
            header('Location: ' . $base . 'dashboard.php');
            exit;
        }
        $error = 'Invalid or expired code. Please try again.';
    }
}

$pageTitle = 'Verify Code';
require_once __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen bg-slate-950 text-slate-50 relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 opacity-60">
        <div class="absolute -top-32 -left-32 h-80 w-80 rounded-full bg-primary-500 blur-3xl mix-blend-screen animate-orb-slow"></div>
        <div class="absolute top-1/2 -right-24 h-80 w-80 rounded-full bg-emerald-500 blur-3xl mix-blend-screen animate-orb-medium"></div>
        <div class="absolute bottom-0 left-1/2 h-40 w-[28rem] -translate-x-1/2 bg-gradient-to-r from-slate-900 via-primary-700/60 to-slate-900 blur-3xl opacity-70 animate-orb-soft"></div>
    </div>
    <div class="relative z-10 flex items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">
            <div class="rounded-3xl border border-slate-700/60 bg-slate-950/80 shadow-[0_24px_90px_rgba(15,23,42,0.9)] backdrop-blur-xl overflow-hidden">
                <div class="px-8 pt-8 pb-6">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-14 h-14 rounded-full bg-primary-500/15 border border-primary-400/50 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-primary-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                        <h1 class="text-xl font-semibold text-slate-50">Two-factor authentication</h1>
                        <p class="text-slate-400 mt-1 text-sm">Enter the 6‑digit code from your authenticator app to continue.</p>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <?php if ($error): ?>
                        <div class="rounded-2xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-xs">
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label for="code" class="block text-xs font-medium tracking-wide text-slate-300 mb-1.5">Verification code</label>
                            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required
                                placeholder="000000"
                                class="w-full rounded-xl border border-slate-700/80 bg-slate-900/70 px-4 py-3 text-center text-xl tracking-[0.4em] text-slate-50 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40 outline-none">
                        </div>
                        <button type="submit" class="w-full py-2.5 px-4 rounded-2xl bg-gradient-to-r from-primary-500 via-primary-400 to-emerald-400 text-sm font-semibold text-slate-950 shadow-lg shadow-primary-900/50 hover:from-primary-400 hover:via-primary-300 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-slate-950">
                            Verify and sign in
                        </button>
                    </form>
                    <p class="mt-4 text-center">
                        <a href="login.php" class="text-xs text-slate-400 hover:text-primary-200">← Back to login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
