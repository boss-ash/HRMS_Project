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

// Approve or reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && csrf_validate()) {
    $id = (int) $_POST['id'];
    $action = $_POST['action'];
    if ($id > 0 && in_array($action, ['approve', 'reject'], true)) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = mysqli_prepare($conn, "UPDATE leave_requests SET status = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND status = 'pending'");
        $uid = (int) $_SESSION['user_id'];
        mysqli_stmt_bind_param($stmt, 'sii', $status, $uid, $id);
        if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
            log_activity($conn, $uid, $action === 'approve' ? 'approve_leave' : 'reject_leave', 'Leave request ID ' . $id);
            $flash_success = 'Leave request ' . $status . '.';
        } else {
            $flash_error = 'Request not found or already processed.';
        }
        mysqli_stmt_close($stmt);
    }
}

$filterStatus = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : '';
$where = $filterStatus !== '' ? "WHERE lr.status = '" . mysqli_real_escape_string($conn, $filterStatus) . "'" : '';
$sql = "SELECT lr.*, e.first_name, e.last_name, e.employee_code
        FROM leave_requests lr
        JOIN employees e ON e.id = lr.employee_id
        $where
        ORDER BY lr.created_at DESC
        LIMIT 200";
$logs = @mysqli_query($conn, $sql);

$pageTitle = 'Leave Management';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-50">Leave Management</h1>
                <p class="text-sm text-slate-400 mt-1">Approve or reject staff leave requests.</p>
            </div>
            <form method="get" class="flex items-center gap-2">
                <select name="status" class="rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm">
                    <option value="">All status</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit" class="rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2 text-sm">Filter</button>
            </form>
        </div>

        <?php if ($flash_error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <div class="overflow-auto max-h-[calc(100vh-16rem)]">
                <table class="min-w-full divide-y divide-slate-800/80 text-sm">
                    <thead class="bg-slate-900/80 sticky top-0 z-10">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-medium text-slate-400 uppercase">Employee</th>
                            <th class="px-5 py-3 text-left text-[11px] font-medium text-slate-400 uppercase">Type</th>
                            <th class="px-5 py-3 text-left text-[11px] font-medium text-slate-400 uppercase">Start – End</th>
                            <th class="px-5 py-3 text-left text-[11px] font-medium text-slate-400 uppercase">Days</th>
                            <th class="px-5 py-3 text-left text-[11px] font-medium text-slate-400 uppercase">Status</th>
                            <th class="px-5 py-3 text-left text-[11px] font-medium text-slate-400 uppercase">Applied</th>
                            <th class="px-5 py-3 text-left text-[11px] font-medium text-slate-400 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        <?php if ($logs && mysqli_num_rows($logs)): while ($row = mysqli_fetch_assoc($logs)): ?>
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?> <span class="text-slate-500">(<?= htmlspecialchars($row['employee_code']) ?>)</span></td>
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($row['leave_type']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($row['start_date']) ?> – <?= htmlspecialchars($row['end_date']) ?></td>
                            <td class="px-5 py-3 text-slate-200"><?= number_format($row['total_days'], 1) ?></td>
                            <td class="px-5 py-3">
                                <?php
                                $st = $row['status'];
                                $cls = $st === 'approved' ? 'bg-emerald-500/20 text-emerald-200' : ($st === 'rejected' ? 'bg-red-500/20 text-red-200' : 'bg-amber-500/20 text-amber-200');
                                ?>
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium <?= $cls ?>"><?= htmlspecialchars($st) ?></span>
                            </td>
                            <td class="px-5 py-3 text-slate-400"><?= htmlspecialchars($row['created_at']) ?></td>
                            <td class="px-5 py-3">
                                <?php if ($row['status'] === 'pending'): ?>
                                <form method="post" class="inline-flex gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <button type="submit" name="action" value="approve" class="rounded px-2 py-1 text-xs font-medium bg-emerald-500/80 text-slate-900 hover:bg-emerald-400">Approve</button>
                                    <button type="submit" name="action" value="reject" class="rounded px-2 py-1 text-xs font-medium bg-red-500/80 text-slate-900 hover:bg-red-400">Reject</button>
                                </form>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="7" class="px-5 py-8 text-center text-slate-500">No leave requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
