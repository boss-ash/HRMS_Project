<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$base = getBasePath();
$employeeId = getEmployeeId();

if ($employeeId === null) {
    $_SESSION['flash_error'] = 'No employee profile linked to your account.';
    header('Location: ' . $base . 'dashboard.php');
    exit;
}

requireOwnProfile($employeeId);

$stmt = mysqli_prepare($conn, "SELECT * FROM employees WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $employeeId);
mysqli_stmt_execute($stmt);
$emp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$emp) {
    $_SESSION['flash_error'] = 'Employee record not found.';
    header('Location: ' . $base . 'dashboard.php');
    exit;
}

$pageTitle = 'My Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950/95 text-slate-50">
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
        <div class="pointer-events-none absolute -top-10 -left-20 h-52 w-52 rounded-full bg-primary-500/40 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 -right-10 h-52 w-52 rounded-full bg-emerald-500/40 blur-3xl opacity-60"></div>

        <div class="relative z-10">
            <div class="mb-6">
                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">My profile</p>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">Account details</h1>
                <p class="text-sm text-slate-400 mt-1">Read‑only view of your employee record in Horyzon.</p>
            </div>

            <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 shadow-[0_18px_60px_rgba(15,23,42,0.8)] overflow-hidden">
                <header class="px-6 py-5 border-b border-slate-800/80 flex items-center justify-between gap-4 flex-wrap">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-50"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h2>
                        <p class="text-xs text-slate-400 mt-1">Employee code: <span class="font-mono text-slate-200"><?= htmlspecialchars($emp['employee_code']) ?></span></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="<?= htmlspecialchars($base) ?>profile_edit.php" class="inline-flex items-center px-3 py-1.5 rounded-full border border-primary-400/60 bg-primary-500/15 text-primary-100 text-xs font-medium hover:bg-primary-500/25">Edit profile</a>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-800/80 border border-slate-600/80 text-[11px] text-slate-200">
                            Linked account
                        </span>
                    </div>
                </header>

                <dl class="px-6 py-4 divide-y divide-slate-800/80 text-sm">
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-4">
                        <dt class="text-slate-400">Email</dt>
                        <dd class="text-slate-100"><?= htmlspecialchars($emp['email']) ?></dd>
                    </div>
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-4">
                        <dt class="text-slate-400">Phone</dt>
                        <dd class="text-slate-100"><?= htmlspecialchars($emp['phone'] ?? '—') ?></dd>
                    </div>
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-4">
                        <dt class="text-slate-400">Department</dt>
                        <dd class="text-slate-100"><?= htmlspecialchars($emp['department'] ?? '—') ?></dd>
                    </div>
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-4">
                        <dt class="text-slate-400">Position</dt>
                        <dd class="text-slate-100"><?= htmlspecialchars($emp['position'] ?? '—') ?></dd>
                    </div>
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-4">
                        <dt class="text-slate-400">Hire date</dt>
                        <dd class="text-slate-100"><?= htmlspecialchars($emp['hire_date'] ?? '—') ?></dd>
                    </div>
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-1.5 sm:gap-4">
                        <dt class="text-slate-400">Status</dt>
                        <dd>
                            <?php
                            $statusClass = $emp['status'] === 'active'
                                ? 'bg-emerald-500/15 text-emerald-100 border border-emerald-400/50'
                                : ($emp['status'] === 'on_leave'
                                    ? 'bg-amber-500/15 text-amber-100 border border-amber-400/50'
                                    : 'bg-slate-700/70 text-slate-100 border border-slate-500/70');
                            ?>
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-medium <?= $statusClass ?>">
                                <?= htmlspecialchars($emp['status']) ?>
                            </span>
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</main>
</body>
</html>
