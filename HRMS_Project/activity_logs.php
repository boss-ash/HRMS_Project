<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
requireAdmin(); // Admin only — staff are redirected to dashboard with "Access denied"

$base = getBasePath();

$filterAction = isset($_GET['action']) ? trim($_GET['action']) : '';
$filterUser = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

$where = [];
$params = [];
$types = '';
if ($filterAction !== '') {
    $where[] = 'a.action = ?';
    $params[] = $filterAction;
    $types .= 's';
}
if ($filterUser > 0) {
    $where[] = 'a.user_id = ?';
    $params[] = $filterUser;
    $types .= 'i';
}
$sqlWhere = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT a.id, a.user_id, a.action, a.details, a.ip_address, a.user_agent, a.created_at, u.username, u.full_name
        FROM activity_logs a
        LEFT JOIN users u ON u.id = a.user_id
        $sqlWhere
        ORDER BY a.created_at DESC
        LIMIT 500";
if ($params) {
    $stmt = mysqli_prepare($conn, $sql);
    $bindRefs = [$types];
    foreach ($params as $k => $v) $bindRefs[] = &$params[$k];
    call_user_func_array([$stmt, 'bind_param'], $bindRefs);
    mysqli_stmt_execute($stmt);
    $logs = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
} else {
    $logs = mysqli_query($conn, $sql);
}

$pageTitle = 'Activity Logs';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$actionLabels = [
    'login' => 'Login',
    'logout' => 'Logout',
    'login_failed' => 'Login failed',
    'change_password' => 'Change password',
    'add_employee' => 'Add employee',
    'edit_employee' => 'Edit employee',
    'archive_employee' => 'Archive employee',
    'restore_employee' => 'Restore employee',
    'purge_archived' => 'Purge archived (30+ days)',
    'delete_employee' => 'Delete employee',
    'approve_leave' => 'Approve leave',
    'reject_leave' => 'Reject leave',
    'add_payslip' => 'Add payslip',
    'add_attendance' => 'Add attendance',
    'create_announcement' => 'Create announcement',
    'delete_announcement' => 'Delete announcement',
    'password_reset_requested' => 'Password reset requested',
    'password_reset_completed' => 'Password reset (admin set new)',
];
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950/95 text-slate-50">
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
        <div class="pointer-events-none absolute -top-10 -left-16 h-56 w-56 rounded-full bg-primary-500/40 blur-3xl"></div>
        <div class="pointer-events-none absolute top-16 -right-10 h-52 w-52 rounded-full bg-emerald-500/40 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 left-1/2 h-40 w-[26rem] -translate-x-1/2 bg-gradient-to-r from-slate-900 via-primary-700/40 to-slate-900 blur-3xl opacity-70"></div>

        <div class="relative z-10">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Audit trail • Admin</p>
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50">Activity logs</h1>
                    <p class="text-sm text-slate-400 mt-1">
                        Sign-ins, sign-outs, and critical changes recorded by Horyzon.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <form method="GET" class="flex flex-wrap items-center gap-2 bg-slate-900/60 border border-slate-700/80 rounded-2xl px-3 py-2 text-xs">
                        <label class="text-slate-300">Action</label>
                        <select
                            name="action"
                            class="rounded-xl border border-slate-700/80 bg-slate-900/80 px-2.5 py-1 text-xs text-slate-100 focus:outline-none focus:ring-2 focus:ring-primary-500/40">
                            <option value="">All</option>
                            <?php foreach ($actionLabels as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $filterAction === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button
                            type="submit"
                            class="rounded-xl bg-primary-500/90 px-3 py-1 text-xs font-semibold text-slate-950 hover:bg-primary-400 transition">
                            Filter
                        </button>
                    </form>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden shadow-[0_18px_60px_rgba(15,23,42,0.8)]">
                <div class="overflow-auto max-h-[calc(100vh-16rem)]">
                    <table class="min-w-full divide-y divide-slate-800/80 text-sm">
                        <thead class="bg-slate-900/80 sticky top-0 z-10 shadow-[0_1px_0_0_rgba(148,163,184,0.1)]">
                            <tr>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Time</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Action</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">User</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Details</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            <?php if ($logs && mysqli_num_rows($logs)): while ($row = mysqli_fetch_assoc($logs)): ?>
                            <tr class="hover:bg-slate-900/60 transition">
                                <td class="px-6 py-3 text-[13px] text-slate-300 whitespace-nowrap">
                                    <?= htmlspecialchars(date('M j, Y H:i:s', strtotime($row['created_at']))) ?>
                                </td>
                                <td class="px-6 py-3">
                                    <?php
                                    $action = $row['action'];
                                    $label = $actionLabels[$action] ?? $action;
                                    $class = $action === 'login'
                                        ? 'bg-emerald-500/15 text-emerald-100 border border-emerald-400/50'
                                        : ($action === 'logout'
                                            ? 'bg-slate-700/70 text-slate-100 border border-slate-500/70'
                                            : ($action === 'login_failed'
                                                ? 'bg-red-500/15 text-red-100 border border-red-400/60'
                                                : 'bg-primary-500/15 text-primary-100 border border-primary-400/60'));
                                    ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-medium <?= $class ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-[13px] text-slate-100 whitespace-nowrap">
                                    <?= $row['user_id']
                                        ? htmlspecialchars($row['full_name'] . ' (' . $row['username'] . ')')
                                        : '—' ?>
                                </td>
                                <td class="px-6 py-3 text-[13px] text-slate-300 max-w-xs truncate" title="<?= htmlspecialchars($row['details'] ?? '') ?>">
                                    <?= htmlspecialchars($row['details'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-3 text-[13px] text-slate-400 whitespace-nowrap">
                                    <?= htmlspecialchars($row['ip_address'] ?? '—') ?>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400">
                                    No activity logs yet.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
