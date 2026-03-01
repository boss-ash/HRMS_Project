<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password_policy.php';
require_once __DIR__ . '/includes/activity_log.php';
requireLogin();

$base = getBasePath();
$userId = (int) $_SESSION['user_id'];
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
    $current = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($newPass) || empty($confirm)) {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row || !password_verify($current, $row['password'])) {
            $message = 'Current password is incorrect.';
            $messageType = 'error';
        } elseif ($newPass !== $confirm) {
            $message = 'New password and confirmation do not match.';
            $messageType = 'error';
        } else {
            $policyErrors = validate_password_policy($newPass);
            if (!empty($policyErrors)) {
                $message = 'New password does not meet the policy: ' . implode(' ', $policyErrors);
                $messageType = 'error';
            } else {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $hash, $userId);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    log_activity($conn, $userId, 'change_password', 'Password updated');
                    $_SESSION['flash_success'] = 'Your password has been changed successfully.';
                    header('Location: ' . $base . 'dashboard.php');
                    exit;
                }
                mysqli_stmt_close($stmt);
                $message = 'Unable to update password. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

$pageTitle = 'Change Password';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
$policyText = get_password_policy_description();
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950/95 text-slate-50">
    <div class="relative mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
        <div class="pointer-events-none absolute -top-10 -left-16 h-56 w-56 rounded-full bg-primary-500/40 blur-3xl"></div>
        <div class="pointer-events-none absolute top-16 -right-10 h-52 w-52 rounded-full bg-emerald-500/40 blur-3xl"></div>

        <div class="relative z-10">
            <div class="mb-6">
                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Security</p>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">Change password</h1>
                <p class="text-sm text-slate-400 mt-1">Update your Horyzon login password. New password must follow the policy below.</p>
            </div>

            <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 shadow-[0_18px_60px_rgba(15,23,42,0.8)] overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-800/80">
                    <h2 class="text-sm font-semibold text-slate-100">Password policy</h2>
                    <p class="text-[11px] text-slate-400 mt-1"><?= htmlspecialchars($policyText) ?></p>
                </div>

                <form method="POST" class="p-6 space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                    <?php if ($message): ?>
                    <div class="rounded-2xl px-4 py-3 text-sm <?= $messageType === 'error' ? 'bg-red-500/10 border border-red-500/40 text-red-100' : 'bg-emerald-500/10 border border-emerald-500/40 text-emerald-100' ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                    <?php endif; ?>

                    <div class="space-y-1.5">
                        <label for="current_password" class="block text-xs font-medium tracking-wide text-slate-300">Current password</label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                                class="w-full rounded-xl border border-slate-700/80 bg-slate-900/60 px-4 py-2.5 text-sm text-slate-50 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40 outline-none">
                            <button type="button" aria-label="Show current password" class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-100 hover:bg-slate-800/80 js-toggle" data-target="current_password">
                                <svg class="w-4 h-4 js-icon-eye" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg class="w-4 h-4 hidden js-icon-eye-off" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878a4.5 4.5 0 106.262 6.262M4.031 11.117A10.047 10.047 0 002 12c1.274 4.057 5.065 7 9.542 7 1.18 0 2.32-.257 3.374-.72M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="new_password" class="block text-xs font-medium tracking-wide text-slate-300">New password</label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password" required autocomplete="new-password"
                                class="w-full rounded-xl border border-slate-700/80 bg-slate-900/60 px-4 py-2.5 text-sm text-slate-50 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40 outline-none">
                            <button type="button" aria-label="Show new password" class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-100 hover:bg-slate-800/80 js-toggle" data-target="new_password">
                                <svg class="w-4 h-4 js-icon-eye" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg class="w-4 h-4 hidden js-icon-eye-off" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878a4.5 4.5 0 106.262 6.262M4.031 11.117A10.047 10.047 0 002 12c1.274 4.057 5.065 7 9.542 7 1.18 0 2.32-.257 3.374-.72M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="confirm_password" class="block text-xs font-medium tracking-wide text-slate-300">Confirm new password</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password"
                                class="w-full rounded-xl border border-slate-700/80 bg-slate-900/60 px-4 py-2.5 text-sm text-slate-50 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40 outline-none">
                            <button type="button" aria-label="Show confirm password" class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-100 hover:bg-slate-800/80 js-toggle" data-target="confirm_password">
                                <svg class="w-4 h-4 js-icon-eye" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg class="w-4 h-4 hidden js-icon-eye-off" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878a4.5 4.5 0 106.262 6.262M4.031 11.117A10.047 10.047 0 002 12c1.274 4.057 5.065 7 9.542 7 1.18 0 2.32-.257 3.374-.72M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <button type="submit" class="rounded-2xl bg-primary-500/90 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-md shadow-primary-900/40 hover:bg-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-slate-950">
                            Update password
                        </button>
                        <a href="<?= htmlspecialchars($base) ?>dashboard.php" class="inline-flex items-center rounded-2xl border border-slate-700/70 bg-slate-900/70 px-4 py-2.5 text-sm font-medium text-slate-200 hover:border-slate-600 hover:bg-slate-800/80">
                            Cancel
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</main>
<script>
document.querySelectorAll('.js-toggle').forEach(function(btn) {
    var id = btn.getAttribute('data-target');
    var input = document.getElementById(id);
    if (!input || !btn) return;
    var eye = btn.querySelector('.js-icon-eye');
    var eyeOff = btn.querySelector('.js-icon-eye-off');
    btn.addEventListener('click', function() {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        if (eye) eye.classList.toggle('hidden', show);
        if (eyeOff) eyeOff.classList.toggle('hidden', !show);
    });
});
</script>
</body>
</html>
