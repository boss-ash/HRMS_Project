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
            header('Location: ' . $base . 'dashboard.php');
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
$qrUrl = totp_get_qr_url($secret, $username, 'Horyzon');

$pageTitle = 'Set up 2FA';
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
                            <svg class="w-7 h-7 text-primary-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <h1 class="text-xl font-semibold text-slate-50">Set up two‑factor authentication</h1>
                        <p class="text-slate-400 mt-1 text-sm">
                            Scan the QR code with your authenticator app, then confirm with a 6‑digit code.
                        </p>
                    </div>

                    <div class="flex flex-col items-center mb-6">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($qrUrl) ?>" alt="QR Code" class="rounded-xl border border-slate-700/80 w-48 h-48 bg-slate-900/80">
                        <p class="mt-3 text-[11px] text-slate-400 max-w-xs text-center">
                            Or enter this key manually:
                            <code class="bg-slate-900/80 border border-slate-700/80 px-1.5 py-0.5 rounded text-[10px] break-all text-slate-100"><?= htmlspecialchars($secret) ?></code>
                        </p>
                    </div>

                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <?php if ($error): ?>
                        <div class="rounded-2xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-xs">
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        <div>
                            <label for="code" class="block text-xs font-medium tracking-wide text-slate-300 mb-1.5">Enter 6‑digit code</label>
                            <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" required
                                placeholder="000000"
                                class="w-full rounded-xl border border-slate-700/80 bg-slate-900/70 px-4 py-3 text-center text-xl tracking-[0.4em] text-slate-50 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40 outline-none">
                        </div>
                        <button type="submit" class="w-full py-2.5 px-4 rounded-2xl bg-gradient-to-r from-primary-500 via-primary-400 to-emerald-400 text-sm font-semibold text-slate-950 shadow-lg shadow-primary-900/50 hover:from-primary-400 hover:via-primary-300 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-slate-950">
                            Verify and finish
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
