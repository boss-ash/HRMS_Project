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

$payslips = [];
$res = @mysqli_query($conn, "SELECT * FROM payslips WHERE employee_id = $employeeId ORDER BY pay_period_end DESC LIMIT 24");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $payslips[] = $row;
}

$pageTitle = 'My Payslips';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Staff</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-50">My Payslips</h1>
            <p class="text-sm text-slate-400 mt-1">View your salary history and pay details.</p>
        </div>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700/70">
                            <th class="px-5 py-3">Period</th>
                            <th class="px-5 py-3">Gross pay</th>
                            <th class="px-5 py-3">Deductions</th>
                            <th class="px-5 py-3">Net pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payslips)): ?>
                        <tr><td colspan="4" class="px-5 py-8 text-slate-500">No payslips on file yet. HR will add them when available.</td></tr>
                        <?php else: ?>
                        <?php foreach ($payslips as $p): ?>
                        <tr class="border-b border-slate-800/80 hover:bg-slate-800/50">
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($p['pay_period_start']) ?> – <?= htmlspecialchars($p['pay_period_end']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= number_format($p['gross_pay'], 2) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= number_format($p['deductions'], 2) ?></td>
                            <td class="px-5 py-3 font-medium text-emerald-200"><?= number_format($p['net_pay'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
</body>
</html>
