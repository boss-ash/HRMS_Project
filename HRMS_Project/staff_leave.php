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

$leaveTableExists = false;
$tr = @mysqli_query($conn, "SHOW TABLES LIKE 'leave_requests'");
if ($tr && mysqli_num_rows($tr) > 0) $leaveTableExists = true;
if (!$leaveTableExists) {
    $requests = [];
    $balance = 15;
    $used = 0;
    $year = (int) date('Y');
    $flash_error = 'Leave module is not set up. Ask admin to run database/migrate_staff_modules.sql.';
    $flash_success = '';
} else {
$ANNUAL_LEAVE_DAYS = 15; // days per year
$year = (int) date('Y');

// Apply leave (POST)
$flash_error = '';
$flash_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply' && !empty($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $start = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $end = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $leave_type = in_array($_POST['leave_type'] ?? '', ['annual', 'sick', 'unpaid']) ? $_POST['leave_type'] : 'annual';

    if ($start === '' || $end === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        $flash_error = 'Please enter valid start and end dates.';
    } elseif (strtotime($end) < strtotime($start)) {
        $flash_error = 'End date must be on or after start date.';
    } else {
        $startTs = strtotime($start);
        $endTs = strtotime($end);
        $days = 0;
        for ($d = $startTs; $d <= $endTs; $d += 86400) {
            $dw = (int) date('w', $d);
            if ($dw != 0 && $dw != 6) $days++; // exclude weekend
        }
        if ($days <= 0) {
            $flash_error = 'No working days in the selected range.';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, total_days, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, 'isssds', $employeeId, $leave_type, $start, $end, $days, $reason);
            if (mysqli_stmt_execute($stmt)) {
                $flash_success = 'Leave request submitted for ' . $days . ' day(s). Pending approval.';
            } else {
                $flash_error = 'Failed to submit request.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Balance: annual entitlement - approved annual leave this year
$balanceRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total_days), 0) AS used FROM leave_requests WHERE employee_id = $employeeId AND status = 'approved' AND leave_type = 'annual' AND YEAR(start_date) = $year"));
$used = (float) ($balanceRow['used'] ?? 0);
$balance = max(0, $ANNUAL_LEAVE_DAYS - $used);

// My leave requests
$requests = [];
$res = mysqli_query($conn, "SELECT * FROM leave_requests WHERE employee_id = $employeeId ORDER BY created_at DESC LIMIT 50");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $requests[] = $row;
}
}

$pageTitle = 'My Leave';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Staff</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-50">My Leave</h1>
            <p class="text-sm text-slate-400 mt-1">Apply for leave and view your balance and history.</p>
        </div>

        <?php if ($flash_error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5 mb-6">
            <h2 class="text-sm font-medium text-slate-400 mb-2">Annual leave balance (<?= $year ?>)</h2>
            <p class="text-3xl font-semibold text-emerald-100"><span class="tabular-nums"><?= number_format($balance, 1) ?></span> days left</p>
            <p class="text-xs text-slate-500 mt-1"><?= number_format($used, 1) ?> days used this year. Entitlement: <?= $ANNUAL_LEAVE_DAYS ?> days/year.</p>
        </section>

        <?php if ($leaveTableExists): ?>
        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5 mb-6">
            <h2 class="text-sm font-medium text-slate-300 mb-4">Apply for leave</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="apply">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="leave_type" class="block text-xs font-medium text-slate-400 mb-1">Type</label>
                        <select id="leave_type" name="leave_type" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                            <option value="annual">Annual</option>
                            <option value="sick">Sick</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                    <div></div>
                    <div>
                        <label for="start_date" class="block text-xs font-medium text-slate-400 mb-1">Start date</label>
                        <input type="date" id="start_date" name="start_date" required class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="end_date" class="block text-xs font-medium text-slate-400 mb-1">End date</label>
                        <input type="date" id="end_date" name="end_date" required class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label for="reason" class="block text-xs font-medium text-slate-400 mb-1">Reason (optional)</label>
                    <textarea id="reason" name="reason" rows="2" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm" placeholder="Brief reason for leave"></textarea>
                </div>
                <button type="submit" class="rounded-lg bg-primary-500 hover:bg-primary-400 text-slate-900 font-semibold px-4 py-2 text-sm">Submit request</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <h2 class="text-sm font-medium text-slate-300 px-5 py-4 border-b border-slate-700/70">Leave history</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700/70">
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Start</th>
                            <th class="px-5 py-3">End</th>
                            <th class="px-5 py-3">Days</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Date applied</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                        <tr><td colspan="6" class="px-5 py-6 text-slate-500">No leave requests yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                        <tr class="border-b border-slate-800/80 hover:bg-slate-800/50">
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($r['leave_type']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($r['start_date']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($r['end_date']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= number_format($r['total_days'], 1) ?></td>
                            <td class="px-5 py-3">
                                <?php
                                $st = $r['status'];
                                $cls = $st === 'approved' ? 'bg-emerald-500/20 text-emerald-200' : ($st === 'rejected' ? 'bg-red-500/20 text-red-200' : 'bg-amber-500/20 text-amber-200');
                                ?>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
                            </td>
                            <td class="px-5 py-3 text-slate-400"><?= htmlspecialchars($r['created_at']) ?></td>
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
