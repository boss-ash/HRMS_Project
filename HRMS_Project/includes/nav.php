<?php if (isLoggedIn()): ?>
<nav class="bg-white border-b border-slate-200 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-8">
                <a href="<?= htmlspecialchars($base) ?>dashboard.php" class="text-xl font-semibold text-primary-600">HRMS</a>
                <div class="hidden sm:flex gap-4">
                    <a href="<?= htmlspecialchars($base) ?>dashboard.php" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">Dashboard</a>
                    <?php if (isAdmin()): ?>
                    <a href="<?= htmlspecialchars($base) ?>employees.php" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md <?= (basename($_SERVER['PHP_SELF']) === 'employees.php') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">Employees</a>
                    <a href="<?= htmlspecialchars($base) ?>activity_logs.php" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md <?= (basename($_SERVER['PHP_SELF']) === 'activity_logs.php') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">Activity Logs</a>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars($base) ?>profile.php" class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md <?= (basename($_SERVER['PHP_SELF']) === 'profile.php') ? 'bg-primary-50 text-primary-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">My Profile</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div id="session-timer-wrap" class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5" title="Session idle timeout — will logout when it reaches 0">
                    <span class="text-slate-500 text-xs hidden sm:inline">Session:</span>
                    <span id="session-timer" class="text-sm font-medium tabular-nums text-slate-700" data-expires-at="<?= (int)(($_SESSION['last_activity'] ?? time()) + SESSION_IDLE_TIMEOUT) ?>">--:--</span>
                </div>
                <span class="text-sm text-slate-600"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                <span class="text-xs text-slate-400">(<?= htmlspecialchars(getRole()) ?>)</span>
                <a href="<?= htmlspecialchars($base) ?>logout.php" class="text-sm font-medium text-slate-600 hover:text-primary-600">Logout</a>
            </div>
        </div>
    </div>
</nav>
<script>
(function() {
    var wrap = document.getElementById('session-timer-wrap');
    var el = document.getElementById('session-timer');
    if (!el || !el.getAttribute('data-expires-at')) return;
    var base = '<?= addslashes($base) ?>';
    var heartbeatUrl = base + 'heartbeat.php';
    var expiresAt = parseInt(el.getAttribute('data-expires-at'), 10) * 1000;
    function formatLeft(ms) {
        var s = Math.max(0, Math.ceil(ms / 1000));
        var m = Math.floor(s / 60);
        s = s % 60;
        return m + ':' + (s < 10 ? '0' : '') + s;
    }
    function update() {
        var now = Date.now();
        var left = expiresAt - now;
        el.textContent = formatLeft(left);
        if (left <= 60000) wrap.classList.add('bg-amber-100'); else wrap.classList.remove('bg-amber-100');
        if (left <= 0) {
            window.location.href = base + 'logout.php?timeout=1';
            return;
        }
        setTimeout(update, 1000);
    }
    function heartbeat() {
        fetch(heartbeatUrl, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok && d.expires_at) {
                    expiresAt = d.expires_at * 1000;
                }
            })
            .catch(function() {});
    }
    var heartbeatThrottle = 0;
    function onActivity() {
        var now = Date.now();
        if (now - heartbeatThrottle < 60000) return;
        heartbeatThrottle = now;
        heartbeat();
    }
    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(ev) {
        document.addEventListener(ev, onActivity);
    });
    update();
})();
</script>
<?php endif; ?>
