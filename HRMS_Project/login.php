<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/activity_log.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Cleanup old rate-limit rows (older than 1 day) to prevent table bloat
cleanupOldLoginAttempts($conn, 86400);

$error = '';
$username = '';
$cooldownEnd = null;
$attemptsRemaining = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate()) {
        $error = 'Invalid request. Please try again.';
    } else {
    $username = sanitize_string($_POST['username'] ?? '', 50);
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        // Laging i-verify muna ang password — kung tama, login agad (hindi na block ng rate limit)
        $stmt = mysqli_prepare($conn, "SELECT id, username, password, full_name, role, employee_id, totp_secret FROM users WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            clearLoginAttempts($conn);
            session_regenerate_id(true);
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            $_SESSION['pending_2fa_username'] = $user['username'];
            $_SESSION['pending_2fa_full_name'] = $user['full_name'];
            $_SESSION['pending_2fa_role'] = ($user['role'] === 'admin') ? 'admin' : 'staff';
            $_SESSION['pending_2fa_employee_id'] = $user['employee_id'] ? (int) $user['employee_id'] : null;
            if (!empty($user['totp_secret'])) {
                header('Location: verify_2fa.php');
            } else {
                header('Location: setup_2fa.php');
            }
            exit;
        }
        // Mali ang password: i-record ang failed attempt, log, saka i-check kung over limit na
        recordFailedLogin($conn);
        log_activity($conn, null, 'login_failed', 'Username: ' . $username);
        if (!isLoginAllowed($conn)) {
            $error = 'rate_limit';
            $cooldownEnd = getCooldownEndTimestamp($conn);
            if ($cooldownEnd === null) $cooldownEnd = time() + LOGIN_RATE_LIMIT_WINDOW;
        } else {
            $error = 'invalid_credentials';
            $attemptsRemaining = LOGIN_RATE_LIMIT_MAX_ATTEMPTS - getFailedAttemptCount($conn);
        }
    }
    }
}

// Pag refresh, kung naka-lock pa rin (cooldown), ipakita pa rin ang countdown — hindi magre-reset
if ($error === '' && $_SERVER['REQUEST_METHOD'] !== 'POST' && !isLoginAllowed($conn)) {
    $error = 'rate_limit';
    $cooldownEnd = getCooldownEndTimestamp($conn);
    if ($cooldownEnd === null) $cooldownEnd = time() + LOGIN_RATE_LIMIT_WINDOW;
}

$timeoutMessage = isset($_GET['timeout']) && $_GET['timeout'] === '1';
$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-100 via-slate-50 to-primary-50 px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200/60 overflow-hidden">
            <div class="px-8 pt-10 pb-2">
                <?php if ($timeoutMessage): ?>
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
                    You were logged out due to inactivity. Please sign in again.
                </div>
                <?php endif; ?>
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-bold text-slate-800">HRMS</h1>
                    <p class="text-slate-500 mt-1">Human Resource Management System</p>
                </div>
                <form method="POST" class="space-y-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <?php if ($error): ?>
                    <div class="rounded-xl bg-red-50/80 border border-red-200 text-red-800 px-4 py-4 text-sm shadow-sm" id="error-box">
                        <?php if ($error === 'rate_limit'): ?>
                        <div class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-200 text-red-700">!</span>
                            <div>
                                <p class="font-semibold">Too many failed attempts</p>
                                <?php if ($cooldownEnd): ?>
                                <p class="mt-1 text-red-700">Try again in <strong id="cooldown-seconds" data-end="<?= (int) $cooldownEnd ?>" class="text-red-800">--</strong> seconds.</p>
                                <?php else: ?>
                                <p class="mt-1 text-red-700">Please try again in a few seconds.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php elseif ($error === 'invalid_credentials'): ?>
                        <div class="flex items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-800">!</span>
                            <div class="min-w-0">
                                <p class="font-semibold">Invalid username or password.</p>
                                <?php if ($attemptsRemaining !== null && $attemptsRemaining >= 0): ?>
                                <p class="mt-2 flex items-center gap-2 text-amber-800">
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">
                                        <?= $attemptsRemaining === 0 ? 'No attempts left' : ($attemptsRemaining === 1 ? '1 attempt left' : $attemptsRemaining . ' attempts left') ?>
                                    </span>
                                    <span class="text-red-600">before 20s lockout</span>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="font-medium"><?= htmlspecialchars($error) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                        <input type="text" id="username" name="username" required
                            value="<?= htmlspecialchars($username) ?>"
                            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                class="w-full rounded-lg border border-slate-300 px-4 py-2.5 pr-11 text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition"
                                autocomplete="current-password">
                            <button type="button" id="toggle-password" class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500/30" title="Show password" aria-label="Show password">
                                <svg id="icon-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <svg id="icon-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878a4.5 4.5 0 106.262 6.262M4.031 11.117A10.047 10.047 0 002 12c1.274 4.057 5.065 7 9.542 7 1.18 0 2.32-.257 3.374-.72M3 3l18 18"/></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" id="btn-submit" class="w-full py-3 px-4 rounded-lg bg-primary-600 text-white font-semibold hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        Sign in
                    </button>
                </form>
            </div>
            <p class="text-center text-slate-400 text-xs pb-6">Default: admin / password</p>
        </div>
    </div>
</div>
<script>
(function() {
    var pw = document.getElementById('password');
    var btn = document.getElementById('toggle-password');
    var iconEye = document.getElementById('icon-eye');
    var iconEyeOff = document.getElementById('icon-eye-off');
    if (pw && btn) {
        btn.addEventListener('click', function() {
            var isHidden = pw.type === 'password';
            pw.type = isHidden ? 'text' : 'password';
            if (iconEye) iconEye.classList.toggle('hidden', isHidden);
            if (iconEyeOff) iconEyeOff.classList.toggle('hidden', !isHidden);
            btn.setAttribute('title', isHidden ? 'Hide password' : 'Show password');
            btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });
        pw.addEventListener('blur', function() {
            pw.type = 'password';
            if (iconEye) iconEye.classList.remove('hidden');
            if (iconEyeOff) iconEyeOff.classList.add('hidden');
            btn.setAttribute('title', 'Show password');
            btn.setAttribute('aria-label', 'Show password');
        });
    }
})();
(function() {
    var el = document.getElementById('cooldown-seconds');
    if (!el) return;
    var end = parseInt(el.getAttribute('data-end'), 10) * 1000;
    var btn = document.getElementById('btn-submit');
    var maxSeconds = 20;
    function tick() {
        var now = Date.now();
        var left = Math.max(0, Math.ceil((end - now) / 1000));
        if (left > maxSeconds) left = maxSeconds;
        el.textContent = left;
        if (left > 0) {
            if (btn) { btn.disabled = true; }
            setTimeout(tick, 1000);
        } else {
            if (btn) { btn.disabled = false; }
            el.textContent = '0';
            var box = document.getElementById('error-box');
            if (box) box.innerHTML = '<p class="text-emerald-700 font-medium">You can try again now.</p>';
        }
    }
    tick();
})();
</script>
</body>
</html>
