<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/webauthn_helper.php';
require_once __DIR__ . '/includes/google_oauth_helper.php';
requireLogin();

$base = getBasePath();
$userId = (int) $_SESSION['user_id'];
$passkeyAvailable = passkey_available();
$emailSaved = '';
$userEmail = '';

if (google_oauth_enabled()) {
    google_oauth_ensure_columns($conn);
    $res = mysqli_query($conn, "SELECT email FROM users WHERE id = $userId LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) $userEmail = $row['email'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_email']) && csrf_validate()) {
        $email = trim($_POST['email'] ?? '');
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $stmt = mysqli_prepare($conn, "UPDATE users SET email = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $email, $userId);
        if (mysqli_stmt_execute($stmt)) $emailSaved = $email ? 'Email saved. You can now use Sign in with Google with this address.' : 'Email cleared.';
        mysqli_stmt_close($stmt);
        $userEmail = $email;
    }
}

$passkeyList = [];
$existingCount = 0;
if ($passkeyAvailable) {
    $passkeyList = passkey_list_credentials($conn, $userId);
    $existingCount = count($passkeyList);
}

$pageTitle = 'Login Methods';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950/95 text-slate-50">
    <div class="relative mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
        <div class="pointer-events-none absolute -top-10 -left-16 h-56 w-56 rounded-full bg-primary-500/40 blur-3xl"></div>
        <div class="pointer-events-none absolute top-16 -right-10 h-52 w-52 rounded-full bg-emerald-500/40 blur-3xl"></div>

        <div class="relative z-10">
            <div class="mb-6">
                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Security</p>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">Login Methods</h1>
                <p class="text-sm text-slate-400 mt-1">Manage how you sign in to Horyzon. Add a passkey to use fingerprint, face ID, or device lock.</p>
            </div>

            <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 shadow-[0_18px_60px_rgba(15,23,42,0.8)] overflow-hidden">
                <div class="p-6">
                    <?php if (!$passkeyAvailable): ?>
                    <p class="text-sm text-slate-400">Passkey sign-in is not available on this server.</p>
                    <?php else: ?>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 rounded-xl border border-slate-700/70 bg-slate-800/50 p-5">
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-slate-600 bg-slate-800/80 text-slate-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"/></svg>
                            </span>
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-600 bg-slate-800/80 -ml-6 mt-4 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-slate-100">Tired of passwords?</p>
                            <p class="text-sm text-slate-400 mt-0.5">Use your device (Windows Hello, fingerprint, face ID, or screen lock) — no USB key required.</p>
                        </div>
                        <div class="shrink-0">
                            <button type="button" id="add-passkey-btn" class="rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-100 px-4 py-2.5 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                                Add Passkey
                            </button>
                        </div>
                    </div>
                    <p id="passkey-message" class="mt-3 text-sm hidden"></p>
                    <?php if ($existingCount > 0): ?>
                    <div class="mt-4">
                        <p class="text-xs font-medium text-slate-300 mb-2">Your passkeys</p>
                        <ul id="passkey-list" class="space-y-2">
                            <?php foreach ($passkeyList as $pk): ?>
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-slate-700/60 bg-slate-800/40 px-3 py-2 text-sm" data-passkey-id="<?= (int)$pk['id'] ?>">
                                <span class="text-slate-300"><?= !empty($pk['name']) ? htmlspecialchars($pk['name']) : ('Passkey added ' . date('M j, Y', strtotime($pk['created_at']))) ?></span>
                                <button type="button" class="remove-passkey rounded-lg border border-red-500/50 bg-red-500/10 px-3 py-1.5 text-xs font-medium text-red-200 hover:bg-red-500/20 focus:outline-none focus:ring-2 focus:ring-red-500/50" data-id="<?= (int)$pk['id'] ?>">Remove</button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mt-2 text-xs text-slate-500">Removing a passkey only deletes it from this site. You may still need to remove it from your device or Google Password Manager.</p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <div class="mt-6 pt-6 border-t border-slate-700/70">
                        <p class="font-medium text-slate-100 mb-1">Sign in with Google (Gmail)</p>
                        <p class="text-sm text-slate-400 mb-3">Use your Google account to sign in. Set your email below so we can link your account, or ask admin to add it.</p>
                        <?php if (google_oauth_enabled()): ?>
                        <?php if ($emailSaved): ?>
                        <p class="text-sm text-emerald-300 mb-2"><?= htmlspecialchars($emailSaved) ?></p>
                        <?php endif; ?>
                        <form method="post" class="flex flex-wrap items-end gap-3 mb-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="save_email" value="1">
                            <div class="min-w-[200px]">
                                <label for="email" class="block text-xs text-slate-400 mb-1">Email (for Google sign-in)</label>
                                <input type="email" id="email" name="email" value="<?= htmlspecialchars($userEmail) ?>" placeholder="you@gmail.com" class="w-full rounded-lg border border-slate-600 bg-slate-800/60 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                            </div>
                            <button type="submit" class="rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-100 px-3 py-2 text-sm font-medium">Save</button>
                        </form>
                        <a href="<?= htmlspecialchars($base) ?>google_login.php" class="inline-flex items-center gap-2 rounded-xl border border-slate-600 bg-slate-800/50 px-4 py-2.5 text-sm font-medium text-slate-200 hover:border-slate-500 hover:bg-slate-700/60">
                            <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                            Sign in with Google
                        </a>
                        <?php else: ?>
                        <p class="text-xs text-slate-500">To enable: copy <code class="text-slate-400">config/google_oauth.sample.php</code> to <code class="text-slate-400">config/google_oauth.php</code> and add your Google Client ID from <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" class="text-primary-300 hover:underline">Google Cloud Console</a>.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="mt-6">
                <a href="<?= htmlspecialchars($base) ?>change_password.php" class="text-sm text-slate-400 hover:text-primary-300">Change password</a>
            </div>
        </div>
    </div>
</main>
<?php if ($passkeyAvailable): ?>
<script>
(function() {
    var base = '<?= addslashes($base) ?>';
    var api = base + 'passkey_api.php';
    var btn = document.getElementById('add-passkey-btn');
    var msg = document.getElementById('passkey-message');

    function showMsg(text, isError) {
        msg.textContent = text;
        msg.classList.remove('hidden', 'text-red-300', 'text-emerald-300');
        msg.classList.add(isError ? 'text-red-300' : 'text-emerald-300');
    }

    function bufferToBase64url(buf) {
        var bytes = new Uint8Array(buf);
        var bin = '';
        for (var i = 0; i < bytes.length; i++) bin += String.fromCharCode(bytes[i]);
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    btn.addEventListener('click', function() {
        btn.disabled = true;
        msg.classList.add('hidden');
        fetch(api + '?action=register_options', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok || !data.options) {
                    showMsg(data.error || 'Could not get options', true);
                    btn.disabled = false;
                    return;
                }
                var options = data.options;
                if (options.publicKey) {
                    options.publicKey.challenge = base64urlToBuffer(options.publicKey.challenge);
                    if (options.publicKey.user && options.publicKey.user.id) {
                        options.publicKey.user.id = base64urlToBuffer(options.publicKey.user.id);
                    }
                    if (options.publicKey.excludeCredentials) {
                        options.publicKey.excludeCredentials.forEach(function(c) {
                            c.id = base64urlToBuffer(c.id);
                        });
                    }
                }
                return navigator.credentials.create(options);
            })
            .then(function(cred) {
                if (!cred) {
                    showMsg('Passkey creation was cancelled or not supported.', true);
                    btn.disabled = false;
                    return;
                }
                var payload = {
                    id: cred.id,
                    rawId: bufferToBase64url(cred.rawId),
                    response: {
                        clientDataJSON: bufferToBase64url(cred.response.clientDataJSON),
                        attestationObject: bufferToBase64url(cred.response.attestationObject)
                    }
                };
                return fetch(api, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ action: 'register', ...payload })
                }).then(function(r) { return r.json(); });
            })
            .then(function(data) {
                if (data && data.ok) {
                    showMsg('Passkey added successfully. You can now sign in with it.', false);
                    setTimeout(function() { window.location.reload(); }, 1500);
                } else {
                    showMsg((data && data.error) || 'Registration failed', true);
                    btn.disabled = false;
                }
            })
            .catch(function(err) {
                showMsg(err.message || 'Something went wrong', true);
                btn.disabled = false;
            });
    });

    function base64urlToBuffer(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        var bin = atob(str);
        var buf = new ArrayBuffer(bin.length);
        var view = new Uint8Array(buf);
        for (var i = 0; i < bin.length; i++) view[i] = bin.charCodeAt(i);
        return buf;
    }

    document.getElementById('passkey-list') && document.getElementById('passkey-list').addEventListener('click', function(e) {
        var btn = e.target && e.target.classList && e.target.classList.contains('remove-passkey') ? e.target : null;
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        if (!id) return;
        btn.disabled = true;
        fetch(api + '?action=remove_passkey&id=' + encodeURIComponent(id), { method: 'POST', credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.ok) {
                    var li = btn.closest('li');
                    if (li) li.remove();
                    var list = document.getElementById('passkey-list');
                    if (list && list.children.length === 0) window.location.reload();
                } else {
                    if (msg) { msg.textContent = (data && data.error) || 'Could not remove passkey'; msg.classList.remove('hidden'); msg.classList.add('text-red-300'); }
                    btn.disabled = false;
                }
            })
            .catch(function() {
                if (msg) { msg.textContent = 'Could not remove passkey'; msg.classList.remove('hidden'); msg.classList.add('text-red-300'); }
                btn.disabled = false;
            });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
