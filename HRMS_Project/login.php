<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/activity_log.php';
require_once __DIR__ . '/includes/webauthn_helper.php';
require_once __DIR__ . '/includes/google_oauth_helper.php';

$base = getBaseUrl();
if (isLoggedIn()) {
    header('Location: ' . $base . 'dashboard.php');
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
                header('Location: ' . $base . 'verify_2fa.php');
            } else {
                header('Location: ' . $base . 'setup_2fa.php');
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
<div class="min-h-screen bg-slate-950 text-slate-50 relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 opacity-60">
        <div class="absolute -top-32 -left-32 h-80 w-80 rounded-full bg-primary-500 blur-3xl mix-blend-screen animate-orb-slow"></div>
        <div class="absolute top-1/2 -right-24 h-80 w-80 rounded-full bg-emerald-500 blur-3xl mix-blend-screen animate-orb-medium"></div>
        <div class="absolute bottom-0 left-1/2 h-40 w-[28rem] -translate-x-1/2 bg-gradient-to-r from-slate-900 via-primary-700/60 to-slate-900 blur-3xl opacity-70 animate-orb-soft"></div>
    </div>

    <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-8">
        <div class="grid w-full max-w-5xl gap-10 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] items-center">
            <section class="hidden md:block">
                <div class="inline-flex items-center gap-2 rounded-full border border-primary-500/30 bg-primary-500/10 px-3 py-1 text-xs font-medium text-primary-200 mb-5">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Horyzon • HR workspace
                </div>
                <h1 class="text-3xl lg:text-4xl font-semibold tracking-tight text-slate-50">
                    Horyzon
                    <span class="block text-primary-300 mt-1">Human Resource Management</span>
                </h1>
                <p class="mt-4 text-sm text-slate-300/90 max-w-md">
                    Centralizes employee records, departments, and positions with secure 2FA sign‑in, audit‑ready activity logs,
                    and real‑time visibility into who is in your organization.
                </p>
                <dl class="mt-6 grid grid-cols-2 gap-4 max-w-md text-xs text-slate-300/90">
                    <div class="rounded-xl border border-slate-800 bg-slate-900/40 px-4 py-3">
                        <dt class="text-slate-400">Security</dt>
                        <dd class="mt-1 font-semibold text-slate-50">2FA sign-in • Audit trail</dd>
                    </div>
                    <div class="rounded-xl border border-slate-800 bg-slate-900/40 px-4 py-3">
                        <dt class="text-slate-400">Visibility</dt>
                        <dd class="mt-1 font-semibold text-slate-50">Live employee overview</dd>
                    </div>
                </dl>
            </section>

            <section class="relative">
                <div class="absolute -inset-0.5 rounded-3xl bg-gradient-to-br from-primary-400/40 via-slate-50/5 to-emerald-400/40 opacity-70 blur-lg"></div>
                <div class="relative rounded-3xl border border-slate-700/60 bg-slate-950/80 shadow-[0_24px_90px_rgba(15,23,42,0.9)] backdrop-blur-xl">
                    <div class="px-7 pt-7 pb-3 border-b border-slate-800/80 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Sign in</p>
                            <p class="mt-1 text-sm font-medium text-slate-100">Horyzon Control Panel</p>
                        </div>
                        <span class="inline-flex items-center justify-center rounded-full bg-primary-500/15 border border-primary-400/40 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-primary-200">
                            Horyzon
                        </span>
                    </div>

                    <div class="px-7 pt-4 pb-7 space-y-5">
                        <?php if ($timeoutMessage): ?>
                        <div class="rounded-2xl bg-amber-500/10 border border-amber-500/40 text-amber-100 px-4 py-3 text-xs flex items-start gap-2">
                            <span class="mt-0.5 h-1.5 w-1.5 rounded-full bg-amber-300 animate-pulse"></span>
                            <p>You were logged out due to inactivity. Please sign in again.</p>
                        </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-5">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                            <?php if ($error): ?>
                            <div class="rounded-2xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-4 text-xs shadow-sm" id="error-box">
                                <?php if ($error === 'rate_limit'): ?>
                                <div class="flex items-start gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-red-500/30 text-xs font-semibold text-red-50">!</span>
                                    <div class="space-y-1">
                                        <p class="text-[13px] font-semibold tracking-tight">Too many failed attempts</p>
                                        <?php if ($cooldownEnd): ?>
                                        <p class="text-[13px] text-red-100/90">
                                            Try again in
                                            <strong id="cooldown-seconds"
                                                data-end="<?= (int) $cooldownEnd ?>"
                                                class="text-red-50 font-semibold">--</strong>
                                            seconds.
                                        </p>
                                        <?php else: ?>
                                        <p class="text-[13px] text-red-100/90">Please try again in a few seconds.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php elseif ($error === 'invalid_credentials'): ?>
                                <div class="flex items-start gap-3">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/25 text-xs font-semibold text-amber-50">!</span>
                                    <div class="min-w-0 space-y-1">
                                        <p class="text-[13px] font-semibold tracking-tight">Invalid username or password.</p>
                                        <?php if ($attemptsRemaining !== null && $attemptsRemaining >= 0): ?>
                                        <p class="mt-1 flex items-center gap-2 text-amber-100/90">
                                            <span class="inline-flex items-center rounded-full bg-amber-500/15 px-2.5 py-0.5 text-[11px] font-medium text-amber-50 border border-amber-500/40">
                                                <?= $attemptsRemaining === 0 ? 'No attempts left' : ($attemptsRemaining === 1 ? '1 attempt left' : $attemptsRemaining . ' attempts left') ?>
                                            </span>
                                            <span class="text-[11px] text-red-200">before temporary lockout</span>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php else: ?>
                                <p class="text-[13px] font-medium"><?= htmlspecialchars($error) ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="space-y-1.5">
                                <label for="username" class="block text-xs font-medium tracking-wide text-slate-300">Username</label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        id="username"
                                        name="username"
                                        required
                                        value="<?= htmlspecialchars($username) ?>"
                                        class="peer w-full rounded-xl border border-slate-700/80 bg-slate-900/60 px-4 py-2.5 text-sm text-slate-50 placeholder-slate-500 outline-none ring-0 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40 transition">
                                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[10px] uppercase tracking-[0.18em] text-slate-500 peer-focus:text-primary-300">
                                        USER
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label for="password" class="block text-xs font-medium tracking-wide text-slate-300">Password</label>
                                <div class="relative">
                                    <input
                                        type="password"
                                        id="password"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        class="peer w-full rounded-xl border border-slate-700/80 bg-slate-900/60 px-4 py-2.5 pr-11 text-sm text-slate-50 placeholder-slate-500 outline-none ring-0 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40 transition">
                                    <button
                                        type="button"
                                        id="toggle-password"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-400 hover:text-slate-100 hover:bg-slate-800/80 focus:outline-none focus:ring-2 focus:ring-primary-500/50"
                                        title="Show password"
                                        aria-label="Show password">
                                        <svg id="icon-eye" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <svg id="icon-eye-off" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878a4.5 4.5 0 106.262 6.262M4.031 11.117A10.047 10.047 0 002 12c1.274 4.057 5.065 7 9.542 7 1.18 0 2.32-.257 3.374-.72M3 3l18 18"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <button
                                type="submit"
                                id="btn-submit"
                                class="relative mt-1 w-full overflow-hidden rounded-2xl bg-gradient-to-r from-primary-500 via-primary-400 to-emerald-400 px-4 py-2.5 text-sm font-semibold tracking-wide text-slate-950 shadow-lg shadow-primary-900/50 transition hover:from-primary-400 hover:via-primary-300 hover:to-emerald-300 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:cursor-not-allowed disabled:opacity-60">
                                <span class="relative z-10 flex items-center justify-center gap-2">
                                    <span>Sign in</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5-5 5M6 12h12"/>
                                    </svg>
                                </span>
                            </button>

                            <?php if (passkey_available()): ?>
                            <div class="relative flex items-center gap-3 pt-1">
                                <span class="flex-1 border-t border-slate-700/80"></span>
                                <span class="text-[11px] uppercase tracking-wider text-slate-500">or</span>
                                <span class="flex-1 border-t border-slate-700/80"></span>
                            </div>
                            <button
                                type="button"
                                id="btn-passkey"
                                class="w-full rounded-2xl border border-slate-600 bg-slate-800/60 px-4 py-2.5 text-sm font-medium text-slate-200 hover:border-slate-500 hover:bg-slate-700/60 focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:ring-offset-2 focus:ring-offset-slate-950 flex items-center justify-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                                <span>Sign in with passkey</span>
                            </button>
                            <p id="passkey-login-msg" class="text-xs text-slate-400 text-center hidden"></p>
                            <?php endif; ?>

                            <?php if (google_oauth_enabled()): ?>
                            <div class="relative flex items-center gap-3 pt-1">
                                <span class="flex-1 border-t border-slate-700/80"></span>
                                <span class="text-[11px] uppercase tracking-wider text-slate-500">or</span>
                                <span class="flex-1 border-t border-slate-700/80"></span>
                            </div>
                            <a href="<?= htmlspecialchars(getBasePath() . 'google_login.php') ?>" class="w-full inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-600 bg-slate-800/60 px-4 py-2.5 text-sm font-medium text-slate-200 hover:border-slate-500 hover:bg-slate-700/60 focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:ring-offset-2 focus:ring-offset-slate-950">
                                <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                Sign in with Google
                            </a>
                            <?php endif; ?>

                            <p class="pt-2 text-center">
                                <a href="<?= htmlspecialchars(getBasePath() . 'forgot_password.php') ?>" class="text-xs font-medium text-primary-300 hover:text-primary-200">Forgot password?</a>
                                <span class="text-slate-600 mx-1">·</span>
                                <span class="text-[10px] text-slate-500">Request reset → Admin will set new password</span>
                            </p>

                            <p class="pt-1 text-[10px] text-slate-500 flex items-center gap-1">
                                <span class="h-1 w-1 rounded-full bg-emerald-400"></span>
                                Enforced with rate limiting &amp; 2FA
                            </p>
                        </form>
                    </div>
                </div>
            </section>
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
<?php if (passkey_available()): ?>
(function() {
    var btn = document.getElementById('btn-passkey');
    var msg = document.getElementById('passkey-login-msg');
    if (!btn) return;
    var base = '<?= addslashes(getBasePath()) ?>';
    var api = base + 'passkey_api.php';
    function base64urlToBuffer(str) {
        str = (str || '').replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        var bin = atob(str);
        var buf = new ArrayBuffer(bin.length);
        var view = new Uint8Array(buf);
        for (var i = 0; i < bin.length; i++) view[i] = bin.charCodeAt(i);
        return buf;
    }
    function bufferToBase64url(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    function showMsg(text, err) {
        if (!msg) return;
        msg.textContent = text;
        msg.classList.remove('hidden');
        msg.className = 'text-xs text-center ' + (err ? 'text-red-300' : 'text-slate-400');
    }
    btn.addEventListener('click', function() {
        btn.disabled = true;
        if (msg) msg.classList.add('hidden');
        fetch(api + '?action=login_options', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok || !data.options) {
                    showMsg(data.error || 'Could not get options', true);
                    btn.disabled = false;
                    return Promise.reject(new Error(data.error || 'No options'));
                }
                var options = data.options;
                if (options.publicKey) {
                    options.publicKey.challenge = base64urlToBuffer(options.publicKey.challenge);
                    if (options.publicKey.allowCredentials && options.publicKey.allowCredentials.length) {
                        options.publicKey.allowCredentials.forEach(function(c) {
                            c.id = base64urlToBuffer(c.id);
                        });
                    }
                }
                return navigator.credentials.get(options);
            })
            .then(function(cred) {
                if (!cred) {
                    showMsg('Passkey sign-in was cancelled or not available.', true);
                    btn.disabled = false;
                    return;
                }
                var payload = {
                    action: 'login_verify',
                    id: cred.id,
                    rawId: bufferToBase64url(cred.rawId),
                    response: {
                        clientDataJSON: bufferToBase64url(cred.response.clientDataJSON),
                        authenticatorData: bufferToBase64url(cred.response.authenticatorData),
                        signature: bufferToBase64url(cred.response.signature),
                        userHandle: cred.response.userHandle ? bufferToBase64url(cred.response.userHandle) : null
                    }
                };
                return fetch(api, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload)
                }).then(function(r) { return r.json(); });
            })
            .then(function(data) {
                if (data && data.ok && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showMsg((data && data.error) || 'Sign-in failed', true);
                    btn.disabled = false;
                }
            })
            .catch(function(err) {
                var txt = err.message || 'Something went wrong';
                if (txt.indexOf('timed out') !== -1 || txt.indexOf('not allowed') !== -1) {
                    txt = 'Passkey timed out or was cancelled. Try again, or add a passkey first in Login methods.';
                }
                showMsg(txt, true);
                btn.disabled = false;
            });
    });
})();
<?php endif; ?>
</script>
</body>
</html>
