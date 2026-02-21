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
    'add_employee' => 'Add employee',
    'edit_employee' => 'Edit employee',
    'delete_employee' => 'Delete employee',
];
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Activity Logs</h1>
        <p class="text-slate-500 mt-1">Login, logout, and key actions — <span class="font-medium text-primary-600">Admin only</span></p>
    </div>

    <div class="mb-6 flex flex-wrap gap-3 items-center">
        <form method="GET" class="flex flex-wrap gap-2 items-center">
            <label class="text-sm text-slate-600">Action:</label>
            <select name="action" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-800 focus:ring-2 focus:ring-primary-500">
                <option value="">All</option>
                <?php foreach ($actionLabels as $val => $label): ?>
                <option value="<?= htmlspecialchars($val) ?>" <?= $filterAction === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-200">Filter</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if ($logs && mysqli_num_rows($logs)): while ($row = mysqli_fetch_assoc($logs)): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm text-slate-600 whitespace-nowrap"><?= htmlspecialchars(date('M j, Y H:i:s', strtotime($row['created_at']))) ?></td>
                        <td class="px-6 py-4">
                            <?php
                            $action = $row['action'];
                            $label = $actionLabels[$action] ?? $action;
                            $class = $action === 'login' ? 'bg-emerald-100 text-emerald-800' : ($action === 'logout' ? 'bg-slate-100 text-slate-800' : ($action === 'login_failed' ? 'bg-red-100 text-red-800' : 'bg-primary-100 text-primary-800'));
                            ?>
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium <?= $class ?>"><?= htmlspecialchars($label) ?></span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-800"><?= $row['user_id'] ? htmlspecialchars($row['full_name'] . ' (' . $row['username'] . ')') : '—' ?></td>
                        <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($row['details'] ?? '') ?>"><?= htmlspecialchars($row['details'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-sm text-slate-500"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No activity logs yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
