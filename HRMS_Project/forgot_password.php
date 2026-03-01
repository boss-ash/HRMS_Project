<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/activity_log.php';

if (isLoggedIn()) {
    header('Location: ' . getBasePath() . 'dashboard.php');
    exit;
}

$base = getBasePath();
$message = '';
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailRaw = trim($_POST['email'] ?? '');
    $email = sanitize_email($emailRaw);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address (e.g. yourname@gmail.com). Names and numbers only are not allowed.';
    } else {
        $emailLower = mb_strtolower($email);
        $stmt = mysqli_prepare($conn, "SELECT id FROM employees WHERE LOWER(TRIM(email)) = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $emailLower);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $employee = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$employee) {
            $message = 'No record found for this email.';
            $isSuccess = false;
        } else {
            $empId = (int) $employee['id'];
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE employee_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $empId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$user) {
                $message = 'This email is in our records but no login account is linked. Please contact your administrator to get login access.';
                $isSuccess = false;
            } else {
                $userId = (int) $user['id'];
                $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'password_reset_requests'");
                if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
                    $message = 'Password reset is not configured. Please contact your administrator.';
                    $isSuccess = false;
                } else {
                    $stmt = mysqli_prepare($conn, "INSERT INTO password_reset_requests (user_id, status) VALUES (?, 'pending')");
                    mysqli_stmt_bind_param($stmt, 'i', $userId);
                    if (mysqli_stmt_execute($stmt)) {
                        log_activity($conn, $userId, 'password_reset_requested', null);
                        $isSuccess = true;
                        $message = 'Your password reset request has been sent to the administrator. You will be notified when your new password is set.';
                    } else {
                        $message = 'Request could not be submitted. Please try again.';
                        $isSuccess = false;
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }
}

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen bg-slate-950 text-slate-50 relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 opacity-60">
        <div class="absolute -top-32 -left-32 h-80 w-80 rounded-full bg-primary-500 blur-3xl mix-blend-screen"></div>
        <div class="absolute top-1/2 -right-24 h-80 w-80 rounded-full bg-emerald-500 blur-3xl mix-blend-screen"></div>
    </div>

    <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">
            <a href="<?= htmlspecialchars($base) ?>login.php" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-primary-200 mb-6">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to sign in
            </a>

            <div class="rounded-3xl border border-slate-700/60 bg-slate-950/80 shadow-xl backdrop-blur-xl px-7 py-7">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Password reset</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-50">Forgot password?</h1>
                <p class="mt-2 text-sm text-slate-400">Enter your registered email address only. Your request will be sent to the administrator, who will set a new password for you.</p>

                <?php if ($message !== ''): ?>
                <div class="mt-4 rounded-xl px-4 py-3 text-sm <?= $isSuccess ? 'bg-emerald-500/10 border border-emerald-500/40 text-emerald-100' : 'bg-red-500/10 border border-red-500/40 text-red-100' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
                <?php endif; ?>

                <?php if ($message === '' || !$isSuccess): ?>
                <form method="post" class="mt-5 space-y-4" id="forgot-form">
                    <div>
                        <label for="email" class="block text-xs font-medium text-slate-300 mb-1">Email address</label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                               placeholder="e.g. yourname@gmail.com"
                               class="w-full rounded-xl border border-slate-700/80 bg-slate-900/60 px-4 py-2.5 text-sm text-slate-50 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                        <p class="mt-1 text-[11px] text-slate-500">Email only — no names or numbers.</p>
                    </div>
                    <button type="submit" class="w-full rounded-xl bg-primary-500 hover:bg-primary-400 text-slate-900 font-semibold px-4 py-2.5 text-sm">
                        Request password reset
                    </button>
                </form>
                <?php else: ?>
                <p class="mt-4">
                    <a href="<?= htmlspecialchars($base) ?>login.php" class="text-sm font-medium text-primary-300 hover:text-primary-200">Return to sign in</a>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
