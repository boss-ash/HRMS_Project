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

// Add attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add' && csrf_validate()) {
    $employee_id = (int) ($_POST['employee_id'] ?? 0);
    $work_date = trim($_POST['work_date'] ?? '');
    $time_in = trim($_POST['time_in'] ?? '');
    $time_out = trim($_POST['time_out'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['present', 'absent', 'leave', 'half_day']) ? $_POST['status'] : 'present';

    if ($employee_id <= 0 || $work_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $work_date)) {
        $flash_error = 'Select an employee and enter a valid date.';
    } else {
        $time_in_sql = ($time_in !== '' && preg_match('/^\d{1,2}:\d{2}$/', $time_in)) ? $time_in . ':00' : null;
        $time_out_sql = ($time_out !== '' && preg_match('/^\d{1,2}:\d{2}$/', $time_out)) ? $time_out . ':00' : null;
        $stmt = mysqli_prepare($conn, "INSERT INTO attendance (employee_id, work_date, time_in, time_out, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE time_in = COALESCE(VALUES(time_in), time_in), time_out = COALESCE(VALUES(time_out), time_out), status = VALUES(status)");
        mysqli_stmt_bind_param($stmt, 'issss', $employee_id, $work_date, $time_in_sql, $time_out_sql, $status);
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, $_SESSION['user_id'], 'add_attendance', 'Employee ID ' . $employee_id . ' date ' . $work_date);
            $flash_success = 'Attendance record saved.';
        } else {
            $flash_error = 'Failed to save.';
        }
        mysqli_stmt_close($stmt);
    }
}

$employees = [];
$res = mysqli_query($conn, "SELECT id, employee_code, first_name, last_name FROM employees WHERE (archived_at IS NULL OR archived_at = '') ORDER BY first_name, last_name");
if ($res) { while ($row = mysqli_fetch_assoc($res)) $employees[] = $row; }

$month = isset($_GET['month']) ? preg_replace('/[^0-9\-]/', '', $_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));

$records = [];
$res = @mysqli_query($conn, "SELECT a.*, e.first_name, e.last_name, e.employee_code FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.work_date >= '$start' AND a.work_date <= '$end' ORDER BY a.work_date DESC, e.first_name, e.last_name LIMIT 300");
if ($res) { while ($row = mysqli_fetch_assoc($res)) $records[] = $row; }

$pageTitle = 'Attendance';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-50">Attendance</h1>
                <p class="text-sm text-slate-400 mt-1">Add and view attendance records.</p>
            </div>
            <form method="get" class="flex items-center gap-2">
                <label class="text-xs text-slate-400">Month</label>
                <input type="month" name="month" value="<?= htmlspecialchars($month) ?>" class="rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                <button type="submit" class="rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2 text-sm">View</button>
            </form>
        </div>

        <?php if ($flash_error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5 mb-6">
            <h2 class="text-sm font-medium text-slate-300 mb-4">Add / update attendance</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="employee_id" class="block text-xs font-medium text-slate-400 mb-1">Employee</label>
                        <select id="employee_id" name="employee_id" required class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                            <option value="">Select</option>
                            <?php foreach ($employees as $e): ?>
                            <option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="work_date" class="block text-xs font-medium text-slate-400 mb-1">Date</label>
                        <input type="date" id="work_date" name="work_date" required class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="time_in" class="block text-xs font-medium text-slate-400 mb-1">Time in</label>
                        <input type="time" id="time_in" name="time_in" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="time_out" class="block text-xs font-medium text-slate-400 mb-1">Time out</label>
                        <input type="time" id="time_out" name="time_out" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select id="status" name="status" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                            <option value="present">Present</option>
                            <option value="half_day">Half day</option>
                            <option value="absent">Absent</option>
                            <option value="leave">Leave</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="rounded-lg bg-primary-500 hover:bg-primary-400 text-slate-900 font-semibold px-4 py-2 text-sm">Save</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <h2 class="text-sm font-medium text-slate-300 px-5 py-4 border-b border-slate-700/70">Records (<?= $month ?>)</h2>
            <div class="overflow-auto max-h-[calc(100vh-28rem)]">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/80 sticky top-0 z-10">
                        <tr class="text-left text-slate-400">
                            <th class="px-5 py-3">Employee</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Time in</th>
                            <th class="px-5 py-3">Time out</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr><td colspan="5" class="px-5 py-8 text-slate-500">No records for this month.</td></tr>
                        <?php else: foreach ($records as $r): ?>
                        <tr class="border-t border-slate-800/80 hover:bg-slate-800/50">
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?> <span class="text-slate-500">(<?= htmlspecialchars($r['employee_code']) ?>)</span></td>
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($r['work_date']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= $r['time_in'] ? htmlspecialchars(substr($r['time_in'], 0, 5)) : '—' ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= $r['time_out'] ? htmlspecialchars(substr($r['time_out'], 0, 5)) : '—' ?></td>
                            <td class="px-5 py-3"><span class="inline-flex px-2 py-0.5 rounded text-xs bg-slate-700/70 text-slate-200"><?= htmlspecialchars($r['status'] ?? 'present') ?></span></td>
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
