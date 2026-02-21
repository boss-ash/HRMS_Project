<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$base = getBasePath();
$flash_error = $_SESSION['flash_error'] ?? '';
if (isset($_SESSION['flash_error'])) {
    unset($_SESSION['flash_error']);
}

// Stats for dashboard
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM employees"))['c'];
$active = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM employees WHERE status = 'active'"))['c'];
$departments = mysqli_query($conn, "SELECT department, COUNT(*) AS c FROM employees WHERE department IS NOT NULL AND department != '' GROUP BY department ORDER BY c DESC LIMIT 5");

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?php if ($flash_error): ?>
    <div class="mb-6 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800"><?= isAdmin() ? 'Admin' : 'Staff' ?> Dashboard</h1>
        <p class="text-slate-500 mt-1">Overview of your workforce</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Total Employees</p>
                    <p class="text-3xl font-bold text-slate-800 mt-1"><?= (int) $total ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-primary-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <a href="<?= htmlspecialchars($base) ?>employees.php" class="mt-4 inline-flex text-sm font-medium text-primary-600 hover:text-primary-700">View all →</a>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Active</p>
                    <p class="text-3xl font-bold text-emerald-600 mt-1"><?= (int) $active ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-4 text-sm text-slate-500">Currently active employees</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 md:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Inactive / On leave</p>
                    <p class="text-3xl font-bold text-amber-600 mt-1"><?= (int) ($total - $active) ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="mt-4 text-sm text-slate-500">Not active in system</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-semibold text-slate-800">Employees by Department</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (mysqli_num_rows($departments)): while ($row = mysqli_fetch_assoc($departments)): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm text-slate-800"><?= htmlspecialchars($row['department']) ?></td>
                        <td class="px-6 py-4 text-sm font-medium text-slate-600"><?= (int) $row['c'] ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="2" class="px-6 py-8 text-center text-slate-500">No department data yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
