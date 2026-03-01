<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/ensure_archive.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$base = getBasePath();
$flash_error = $_SESSION['flash_error'] ?? '';
$flash_success = $_SESSION['flash_success'] ?? '';
if (isset($_SESSION['flash_error'])) unset($_SESSION['flash_error']);
if (isset($_SESSION['flash_success'])) unset($_SESSION['flash_success']);

// Stats for dashboard (exclude archived)
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM employees WHERE (archived_at IS NULL OR archived_at = '')"))['c'];
$active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM employees WHERE (archived_at IS NULL OR archived_at = '') AND status = 'active'"))['c'];
$departments = mysqli_query($conn, "SELECT department, COUNT(*) AS c FROM employees WHERE (archived_at IS NULL OR archived_at = '') AND department IS NOT NULL AND department != '' GROUP BY department ORDER BY c DESC LIMIT 5");

$userName = $_SESSION['user_name'] ?? 'User';
$hour = (int) date('H');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50 overflow-hidden">
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 lg:py-10 dashboard-perspective">
        <div class="pointer-events-none absolute -top-10 -left-16 h-64 w-64 rounded-full bg-primary-500/50 blur-3xl animate-orb-slow"></div>
        <div class="pointer-events-none absolute top-16 -right-10 h-60 w-60 rounded-full bg-emerald-500/50 blur-3xl animate-orb-medium"></div>
        <div class="pointer-events-none absolute bottom-0 left-1/2 h-48 w-[28rem] -translate-x-1/2 bg-gradient-to-r from-slate-900 via-primary-700/50 to-slate-900 blur-3xl opacity-80 animate-orb-soft"></div>
        <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[32rem] h-[32rem] rounded-full bg-slate-800/30 blur-3xl"></div>

        <div class="relative z-10">
            <?php if ($flash_error): ?>
            <div class="mb-6 rounded-2xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm flex items-start gap-2">
                <span class="mt-0.5 h-1.5 w-1.5 rounded-full bg-red-300 animate-pulse"></span>
                <p><?= htmlspecialchars($flash_error) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($flash_success): ?>
            <div class="mb-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm flex items-start gap-2">
                <span class="mt-0.5 h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                <p><?= htmlspecialchars($flash_success) ?></p>
            </div>
            <?php endif; ?>

            <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between float-in">
                <div>
                    <p class="text-xs text-slate-400 mb-1">
                        <?= htmlspecialchars($greeting) ?>, <span class="text-slate-200"><?= htmlspecialchars($userName) ?></span>
                    </p>
                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-500 mb-1">
                        <?= isAdmin() ? 'Admin' : 'Staff' ?> workspace
                    </p>
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">
                        People overview
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Snapshot of employees, activity and departments in your Horyzon workspace.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="<?= htmlspecialchars($base) ?>employees.php"
                       class="inline-flex items-center gap-2 rounded-full border border-slate-700/70 bg-slate-900/70 px-3 py-1.5 text-xs font-medium text-slate-200 hover:border-primary-400/80 hover:text-primary-100 hover:bg-slate-900/90 hover:scale-105 transition-all duration-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Employees directory
                    </a>
                    <?php if (isAdmin()): ?>
                    <a href="<?= htmlspecialchars($base) ?>employees.php?add=1"
                       class="inline-flex items-center gap-1.5 rounded-full bg-primary-500/90 px-3 py-1.5 text-xs font-semibold text-slate-950 shadow-lg shadow-primary-900/50 hover:bg-primary-400 hover:scale-105 hover:shadow-primary-800/60 transition-all duration-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add employee
                    </a>
                    <?php endif; ?>
                </div>
            </header>

            <section class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] mb-8">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <article class="card-3d card-3d-diagonal relative overflow-hidden rounded-2xl border border-slate-700/60 bg-slate-900/80 backdrop-blur-sm p-5 float-in float-in-1">
                        <div class="card-3d-edge"></div>
                        <div class="absolute inset-y-0 right-0 w-28 bg-gradient-to-t from-primary-500/25 via-primary-500/5 to-transparent pointer-events-none"></div>
                        <div class="relative flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-slate-400">Total employees</p>
                            </div>
                            <div class="orb-3d orb-3d-primary">
                                <span class="orb-3d-number" aria-label="Total: <?= (int) $total ?>"><?= (int) $total ?></span>
                            </div>
                        </div>
                        <p class="mt-3 text-[11px] text-slate-400">
                            All employees currently registered in the system.
                        </p>
                    </article>

                    <article class="card-3d card-3d-diagonal relative overflow-hidden rounded-2xl border border-emerald-600/50 bg-emerald-950/50 backdrop-blur-sm p-5 float-in float-in-2">
                        <div class="card-3d-edge"></div>
                        <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-t from-emerald-500/40 via-emerald-500/5 to-transparent pointer-events-none"></div>
                        <div class="relative flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-emerald-200/90">Active</p>
                            </div>
                            <div class="orb-3d orb-3d-emerald">
                                <span class="orb-3d-number" aria-label="Active: <?= (int) $active ?>"><?= (int) $active ?></span>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-[11px] text-emerald-100/90">
                            <p>Currently active employees.</p>
                            <?php $rate = $total > 0 ? round(($active / $total) * 100) : 0; ?>
                            <span class="inline-flex items-center gap-1 font-medium">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                <?= $rate ?>% active
                            </span>
                        </div>
                    </article>

                    <article class="card-3d card-3d-diagonal relative overflow-hidden rounded-2xl border border-amber-600/50 bg-amber-950/50 backdrop-blur-sm p-5 float-in float-in-3">
                        <div class="card-3d-edge"></div>
                        <div class="absolute inset-y-0 right-0 w-24 bg-gradient-to-t from-amber-500/40 via-amber-500/5 to-transparent pointer-events-none"></div>
                        <div class="relative flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium text-amber-200/90">Inactive / On leave</p>
                            </div>
                            <div class="orb-3d orb-3d-amber">
                                <span class="orb-3d-number" aria-label="Inactive: <?= (int) ($total - $active) ?>"><?= (int) ($total - $active) ?></span>
                            </div>
                        </div>
                        <p class="mt-3 text-[11px] text-amber-100/90">
                            Includes resigned, on leave, or temporarily inactive employees.
                        </p>
                    </article>
                </div>

                <aside class="panel-3d rounded-2xl border border-slate-700/60 bg-slate-900/80 backdrop-blur-sm p-5 flex flex-col justify-between gap-4 float-in float-in-4">
                    <div>
                        <p class="text-xs font-medium text-slate-400">Quick actions</p>
                        <p class="mt-1 text-sm text-slate-300">
                            Jump into the most common HR tasks.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <a href="<?= htmlspecialchars($base) ?>employees.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-primary-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">View employees</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Search, filter and review profiles</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <?php if (isAdmin()): ?>
                        <a href="<?= htmlspecialchars($base) ?>employees.php?add=1"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-primary-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">New hire</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Create a new employee record</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>admin_leave.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-emerald-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">Leave requests</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Approve or reject staff leave</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>admin_payslips.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-primary-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">Payslips</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Add and view payslips</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>admin_attendance.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-amber-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">Attendance</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Add and view records</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>admin_announcements.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-primary-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">Announcements</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Create company updates</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>admin_password_requests.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-amber-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">Password reset requests</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Staff forgot password → set new password</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars($base) ?>staff_leave.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-emerald-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">My Leave</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Apply leave, view balance & history</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>staff_payslips.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-primary-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">My Payslips</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">View salary history</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>staff_attendance.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-amber-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">My Attendance</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Time in & out records</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <a href="<?= htmlspecialchars($base) ?>staff_announcements.php"
                           class="flex items-center justify-between rounded-xl border border-slate-700/70 bg-slate-800/50 px-3 py-2.5 text-slate-200 hover:border-primary-400/80 hover:bg-slate-800/80 hover:translate-x-0.5 transition-all duration-300">
                            <span>
                                <span class="block font-medium">Announcements</span>
                                <span class="mt-0.5 block text-[11px] text-slate-400">Company news & updates</span>
                            </span>
                            <span class="ml-2 text-slate-500">&rarr;</span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">
                        Data shown in real time from the Horyzon database.
                    </p>
                </aside>
            </section>

            <section class="panel-3d rounded-2xl border border-slate-700/60 bg-slate-900/80 backdrop-blur-sm overflow-hidden float-in float-in-5">
                <div class="px-6 py-4 border-b border-slate-800/80 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-100">Employees by department</h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">Top departments based on headcount.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-800/80 px-2.5 py-1 text-[10px] font-medium text-slate-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary-400"></span>
                        Org structure
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800/80 text-sm">
                        <thead class="bg-slate-900/80">
                            <tr>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Department</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Count</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em] hidden sm:table-cell">Load</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            <?php if (mysqli_num_rows($departments)): ?>
                                <?php while ($row = mysqli_fetch_assoc($departments)):
                                    $count = (int) $row['c'];
                                    $percent = $total > 0 ? max(6, round(($count / $total) * 100)) : 0;
                                ?>
                                <tr class="hover:bg-slate-900/70 transition">
                                    <td class="px-6 py-3 text-[13px] text-slate-100 whitespace-nowrap">
                                        <?= htmlspecialchars($row['department']) ?>
                                    </td>
                                    <td class="px-6 py-3 text-[13px] font-medium text-slate-50">
                                        <?= $count ?>
                                    </td>
                                    <td class="px-6 py-3 text-[11px] text-slate-300 hidden sm:table-cell">
                                        <div class="flex items-center gap-2">
                                            <div class="relative h-1.5 w-full max-w-[160px] overflow-hidden rounded-full bg-slate-800/90">
                                                <div class="h-full rounded-full bg-gradient-to-r from-primary-400 via-primary-300 to-emerald-300" style="width: <?= $percent ?>%"></div>
                                            </div>
                                            <span class="tabular-nums text-slate-400"><?= $percent ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-sm text-slate-400">
                                        No department data yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
