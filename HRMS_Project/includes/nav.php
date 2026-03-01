<?php if (isLoggedIn()): ?>
<nav class="bg-slate-950/95 border-b border-slate-800/80 backdrop-blur-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-8">
                <a href="<?= htmlspecialchars($base) ?>dashboard.php" class="text-xl font-semibold tracking-tight text-slate-50">
                    Horyzon
                </a>
                <div class="hidden sm:flex gap-3">
                    <a href="<?= htmlspecialchars($base) ?>dashboard.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">Dashboard</a>
                    <?php if (isAdmin()): ?>
                    <a href="<?= htmlspecialchars($base) ?>employees.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'employees.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">Employees</a>
                    <a href="<?= htmlspecialchars($base) ?>activity_logs.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'activity_logs.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">Activity Logs</a>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars($base) ?>profile.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'profile.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">My Profile</a>
                    <a href="<?= htmlspecialchars($base) ?>staff_leave.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'staff_leave.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">Leave</a>
                    <a href="<?= htmlspecialchars($base) ?>staff_payslips.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'staff_payslips.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">Payslips</a>
                    <a href="<?= htmlspecialchars($base) ?>staff_attendance.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'staff_attendance.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">Attendance</a>
                    <a href="<?= htmlspecialchars($base) ?>staff_announcements.php" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border <?= (basename($_SERVER['PHP_SELF']) === 'staff_announcements.php') ? 'border-primary-400/80 bg-primary-500/20 text-primary-100' : 'border-transparent text-slate-300 hover:border-slate-600 hover:bg-slate-900/80 hover:text-slate-50' ?>">Announcements</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
                <div id="session-timer-wrap" class="flex items-center gap-2 rounded-full bg-slate-900/80 border border-slate-700/80 px-3 py-1.5" title="Session idle timeout — will logout when it reaches 0">
                    <span class="text-slate-400 text-[11px] hidden sm:inline">Session</span>
                    <span id="session-timer" class="text-xs font-semibold tabular-nums text-slate-100" data-expires-at="<?= (int)(($_SESSION['last_activity'] ?? time()) + SESSION_IDLE_TIMEOUT) ?>">--:--</span>
                </div>
                <div class="hidden sm:block w-px h-6 bg-slate-700/80" aria-hidden="true"></div>
                <button type="button" id="hrms-theme-toggle" class="rounded-lg p-2 text-slate-400 hover:bg-slate-800/80 hover:text-slate-100 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500/50" title="Toggle dark/light" aria-label="Toggle theme">
                    <span id="hrms-theme-icon-dark" class="hidden"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg></span>
                    <span id="hrms-theme-icon-light" class="hidden"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></span>
                </button>
                <div class="hidden sm:block w-px h-6 bg-slate-700/80" aria-hidden="true"></div>
                <div class="flex flex-col items-end">
                    <span class="text-sm font-medium text-slate-50 leading-tight"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                    <span class="text-[10px] uppercase tracking-wider font-medium text-slate-400 mt-0.5"><?= htmlspecialchars(getRole()) ?></span>
                </div>
                <div class="hidden sm:block w-px h-6 bg-slate-700/80" aria-hidden="true"></div>
                <div class="flex items-center gap-1 sm:gap-2">
                    <a href="<?= htmlspecialchars($base) ?>login_methods.php" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors <?= (basename($_SERVER['PHP_SELF']) === 'login_methods.php') ? 'bg-primary-500/20 text-primary-200 border border-primary-500/40' : 'text-slate-300 hover:bg-slate-800/80 hover:text-slate-100 border border-transparent' ?>">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Login methods
                    </a>
                    <a href="<?= htmlspecialchars($base) ?>change_password.php" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors <?= (basename($_SERVER['PHP_SELF']) === 'change_password.php') ? 'bg-primary-500/20 text-primary-200 border border-primary-500/40' : 'text-slate-300 hover:bg-slate-800/80 hover:text-slate-100 border border-transparent' ?>">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        Password
                    </a>
                    <a href="<?= htmlspecialchars($base) ?>logout.php" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-300 hover:bg-red-500/10 hover:text-red-200 hover:border-red-500/30 border border-transparent transition-colors">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Logout
                    </a>
                </div>
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
        if (left <= 60000) {
            wrap.classList.add('session-warning');
        } else {
            wrap.classList.remove('session-warning');
        }
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
(function(){
    var html = document.documentElement;
    var btn = document.getElementById('hrms-theme-toggle');
    var iconDark = document.getElementById('hrms-theme-icon-dark');
    var iconLight = document.getElementById('hrms-theme-icon-light');
    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('hrms-theme', theme);
        if (iconDark) iconDark.classList.toggle('hidden', theme !== 'dark');
        if (iconLight) iconLight.classList.toggle('hidden', theme !== 'light');
    }
    function toggleTheme() {
        var t = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        applyTheme(t);
    }
    if (btn) btn.addEventListener('click', toggleTheme);
    var current = html.getAttribute('data-theme') || 'dark';
    if (iconDark) iconDark.classList.toggle('hidden', current !== 'dark');
    if (iconLight) iconLight.classList.toggle('hidden', current !== 'light');
})();
</script>
<?php endif; ?>
