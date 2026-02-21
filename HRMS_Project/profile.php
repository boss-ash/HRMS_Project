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
<main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">My Profile</h1>
        <p class="text-slate-500 mt-1">View your employee information (read-only)</p>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h2 class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></h2>
            <p class="text-sm text-slate-500"><?= htmlspecialchars($emp['employee_code']) ?></p>
        </div>
        <dl class="px-6 py-4 divide-y divide-slate-200">
            <div class="py-3 flex justify-between gap-4">
                <dt class="text-sm font-medium text-slate-500">Email</dt>
                <dd class="text-sm text-slate-800"><?= htmlspecialchars($emp['email']) ?></dd>
            </div>
            <div class="py-3 flex justify-between gap-4">
                <dt class="text-sm font-medium text-slate-500">Phone</dt>
                <dd class="text-sm text-slate-800"><?= htmlspecialchars($emp['phone'] ?? '—') ?></dd>
            </div>
            <div class="py-3 flex justify-between gap-4">
                <dt class="text-sm font-medium text-slate-500">Department</dt>
                <dd class="text-sm text-slate-800"><?= htmlspecialchars($emp['department'] ?? '—') ?></dd>
            </div>
            <div class="py-3 flex justify-between gap-4">
                <dt class="text-sm font-medium text-slate-500">Position</dt>
                <dd class="text-sm text-slate-800"><?= htmlspecialchars($emp['position'] ?? '—') ?></dd>
            </div>
            <div class="py-3 flex justify-between gap-4">
                <dt class="text-sm font-medium text-slate-500">Hire Date</dt>
                <dd class="text-sm text-slate-800"><?= htmlspecialchars($emp['hire_date'] ?? '—') ?></dd>
            </div>
            <div class="py-3 flex justify-between gap-4">
                <dt class="text-sm font-medium text-slate-500">Status</dt>
                <dd><span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium <?= $emp['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : ($emp['status'] === 'on_leave' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') ?>"><?= htmlspecialchars($emp['status']) ?></span></dd>
            </div>
        </dl>
    </div>
</main>
</body>
</html>
