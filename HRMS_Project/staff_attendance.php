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

$month = isset($_GET['month']) ? preg_replace('/[^0-9\-]/', '', $_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

$records = [];
$res = @mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $employeeId AND work_date >= '$start' AND work_date <= '$end' ORDER BY work_date DESC");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) $records[] = $row;
}

$pageTitle = 'My Attendance';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Staff</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-50">My Attendance</h1>
                <p class="text-sm text-slate-400 mt-1">View your time in and time out records.</p>
            </div>
            <form method="get" class="flex items-center gap-2">
                <label for="month" class="text-xs text-slate-400">Month</label>
                <input type="month" id="month" name="month" value="<?= htmlspecialchars($month) ?>" class="rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                <button type="submit" class="rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-200 hover:bg-slate-700/80">View</button>
            </form>
        </div>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-slate-700/70">
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Time in</th>
                            <th class="px-5 py-3">Time out</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr><td colspan="4" class="px-5 py-8 text-slate-500">No attendance records for this month yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($records as $r): ?>
                        <tr class="border-b border-slate-800/80 hover:bg-slate-800/50">
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($r['work_date']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= $r['time_in'] ? htmlspecialchars(substr($r['time_in'], 0, 5)) : '—' ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= $r['time_out'] ? htmlspecialchars(substr($r['time_out'], 0, 5)) : '—' ?></td>
                            <td class="px-5 py-3"><span class="inline-flex px-2 py-0.5 rounded text-xs bg-slate-700/70 text-slate-200"><?= htmlspecialchars($r['status'] ?? 'present') ?></span></td>
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
