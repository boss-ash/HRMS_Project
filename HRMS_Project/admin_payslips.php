<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/ensure_archive.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';
requireLogin();
requireAdmin();

$base = getBasePath();
$flash_error = '';
$flash_success = '';

// Add payslip
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add' && csrf_validate()) {
    $employee_id = (int) ($_POST['employee_id'] ?? 0);
    $period_start = trim($_POST['pay_period_start'] ?? '');
    $period_end = trim($_POST['pay_period_end'] ?? '');
    $gross = (float) ($_POST['gross_pay'] ?? 0);
    $deductions = (float) ($_POST['deductions'] ?? 0);
    $net = (float) ($_POST['net_pay'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if ($employee_id <= 0 || $period_start === '' || $period_end === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_end)) {
        $flash_error = 'Select an employee and valid period dates.';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO payslips (employee_id, pay_period_start, pay_period_end, gross_pay, deductions, net_pay, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issddds', $employee_id, $period_start, $period_end, $gross, $deductions, $net, $notes);
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, $_SESSION['user_id'], 'add_payslip', 'Employee ID ' . $employee_id . ' period ' . $period_start);
            $flash_success = 'Payslip added.';
        } else {
            $flash_error = 'Failed to add payslip.';
        }
        mysqli_stmt_close($stmt);
    }
}

$employees = [];
$res = mysqli_query($conn, "SELECT id, employee_code, first_name, last_name FROM employees WHERE (archived_at IS NULL OR archived_at = '') ORDER BY first_name, last_name");
if ($res) { while ($row = mysqli_fetch_assoc($res)) $employees[] = $row; }

$payslips = [];
$res = @mysqli_query($conn, "SELECT p.*, e.first_name, e.last_name, e.employee_code FROM payslips p JOIN employees e ON e.id = p.employee_id ORDER BY p.pay_period_end DESC LIMIT 100");
if ($res) { while ($row = mysqli_fetch_assoc($res)) $payslips[] = $row; }

$pageTitle = 'Payslips';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Admin</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-50">Payslips</h1>
            <p class="text-sm text-slate-400 mt-1">Add and view employee payslips.</p>
        </div>

        <?php if ($flash_error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5 mb-6">
            <h2 class="text-sm font-medium text-slate-300 mb-4">Add payslip</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="employee_id" class="block text-xs font-medium text-slate-400 mb-1">Employee</label>
                        <select id="employee_id" name="employee_id" required class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                            <option value="">Select employee</option>
                            <?php foreach ($employees as $e): ?>
                            <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' (' . $e['employee_code'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div></div>
                    <div>
                        <label for="pay_period_start" class="block text-xs font-medium text-slate-400 mb-1">Period start</label>
                        <input type="date" id="pay_period_start" name="pay_period_start" required class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="pay_period_end" class="block text-xs font-medium text-slate-400 mb-1">Period end</label>
                        <input type="date" id="pay_period_end" name="pay_period_end" required class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="gross_pay" class="block text-xs font-medium text-slate-400 mb-1">Gross pay</label>
                        <input type="number" id="gross_pay" name="gross_pay" step="0.01" min="0" value="0" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="deductions" class="block text-xs font-medium text-slate-400 mb-1">Deductions</label>
                        <input type="number" id="deductions" name="deductions" step="0.01" min="0" value="0" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="net_pay" class="block text-xs font-medium text-slate-400 mb-1">Net pay</label>
                        <input type="number" id="net_pay" name="net_pay" step="0.01" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="notes" class="block text-xs font-medium text-slate-400 mb-1">Notes (optional)</label>
                        <input type="text" id="notes" name="notes" maxlength="255" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="rounded-lg bg-primary-500 hover:bg-primary-400 text-slate-900 font-semibold px-4 py-2 text-sm">Add payslip</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <h2 class="text-sm font-medium text-slate-300 px-5 py-4 border-b border-slate-700/70">Recent payslips</h2>
            <div class="overflow-auto max-h-[calc(100vh-28rem)]">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/80 sticky top-0 z-10">
                        <tr class="text-left text-slate-400">
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Period</th>
                            <th class="px-5 py-3">Gross</th>
                            <th class="px-5 py-3">Deductions</th>
                            <th class="px-5 py-3">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payslips)): ?>
                        <tr><td colspan="5" class="px-5 py-8 text-slate-500">No payslips yet.</td></tr>
                        <?php else: foreach ($payslips as $p): ?>
                        <tr class="border-t border-slate-800/80 hover:bg-slate-800/50">
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($p['pay_period_start']) ?> – <?= htmlspecialchars($p['pay_period_end']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= number_format($p['gross_pay'], 2) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= number_format($p['deductions'], 2) ?></td>
                            <td class="px-5 py-3 font-medium text-emerald-200"><?= number_format($p['net_pay'], 2) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</main>
</body>
</html>
