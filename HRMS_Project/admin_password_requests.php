<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';
require_once __DIR__ . '/includes/password_policy.php';
requireLogin();
requireAdmin();

$base = getBasePath();
$flash_error = '';
$flash_success = '';

$tableExists = false;
$tr = @mysqli_query($conn, "SHOW TABLES LIKE 'password_reset_requests'");
if ($tr && mysqli_num_rows($tr) > 0) $tableExists = true;

// Set new password (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_password' && isset($_POST['request_id'], $_POST['new_password']) && csrf_validate()) {
    $requestId = (int) $_POST['request_id'];
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirm) {
        $flash_error = 'Passwords do not match.';
    } else {
        $errs = validate_password_policy($newPassword);
        if (!empty($errs)) {
            $flash_error = implode(' ', $errs);
        } elseif ($requestId > 0 && $tableExists) {
            $stmt = mysqli_prepare($conn, "SELECT pr.id, pr.user_id, u.username FROM password_reset_requests pr JOIN users u ON u.id = pr.user_id WHERE pr.id = ? AND pr.status = 'pending' LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $requestId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $req = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($req) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $uid = (int) $req['user_id'];
                $adminId = (int) $_SESSION['user_id'];
                $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'si', $hash, $uid);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    $stmt = mysqli_prepare($conn, "UPDATE password_reset_requests SET status = 'completed', completed_at = NOW(), completed_by = ? WHERE user_id = ? AND status = 'pending'");
                    mysqli_stmt_bind_param($stmt, 'ii', $adminId, $uid);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    log_activity($conn, $adminId, 'password_reset_completed', 'User: ' . $req['username']);
                    $flash_success = 'New password set for ' . htmlspecialchars($req['username']) . '. Inform the staff to sign in with the new password.';
                } else {
                    mysqli_stmt_close($stmt);
                    $flash_error = 'Failed to update password.';
                }
            } else {
                $flash_error = 'Request not found or already processed.';
            }
        } else {
            $flash_error = 'Invalid request.';
        }
    }
}

// Cancel request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id']) && $_POST['action'] === 'cancel' && csrf_validate()) {
    $requestId = (int) $_POST['request_id'];
    if ($requestId > 0 && $tableExists) {
        $stmt = mysqli_prepare($conn, "UPDATE password_reset_requests SET status = 'cancelled', completed_at = NOW(), completed_by = ? WHERE id = ? AND status = 'pending'");
        $adminId = (int) $_SESSION['user_id'];
        mysqli_stmt_bind_param($stmt, 'ii', $adminId, $requestId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (mysqli_affected_rows($conn) > 0) $flash_success = 'Request cancelled.';
    }
}

$pending = [];
$completed = [];
if ($tableExists) {
    $res = mysqli_query($conn, "SELECT pr.*, u.username, u.full_name, u.role FROM password_reset_requests pr JOIN users u ON u.id = pr.user_id ORDER BY pr.requested_at DESC LIMIT 100");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            if ($row['status'] === 'pending') $pending[] = $row;
            else $completed[] = $row;
        }
    }
}

$pageTitle = 'Password Reset Requests';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Admin</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-50">Password reset requests</h1>
            <p class="text-sm text-slate-400 mt-1">Staff who forgot their password submit requests here. Set a new password and inform them.</p>
        </div>

        <?php if ($flash_error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm"><?= $flash_success ?></div>
        <?php endif; ?>

        <?php if (!$tableExists): ?>
        <div class="rounded-2xl border border-amber-500/40 bg-amber-500/10 text-amber-100 px-5 py-4 text-sm">Run database migration: <code class="bg-slate-900/80 px-1.5 py-0.5 rounded">database/migrate_password_reset_requests.sql</code></div>
        <?php else: ?>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden mb-6">
            <h2 class="text-sm font-medium text-slate-300 px-5 py-4 border-b border-slate-700/70">Pending requests</h2>
            <p class="text-xs text-slate-500 px-5 pb-2"><?= get_password_policy_description() ?></p>
            <?php if (empty($pending)): ?>
            <div class="px-5 py-8 text-slate-500 text-sm">No pending requests.</div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/80">
                        <tr class="text-left text-slate-400">
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3">Requested</th>
                            <th class="px-5 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        <?php foreach ($pending as $r): ?>
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-5 py-3">
                                <span class="font-medium text-slate-100"><?= htmlspecialchars($r['full_name']) ?></span>
                                <span class="text-slate-500">(<?= htmlspecialchars($r['username']) ?>)</span>
                                <span class="ml-1.5 inline-flex px-2 py-0.5 rounded text-[10px] <?= $r['role'] === 'admin' ? 'bg-amber-500/20 text-amber-200' : 'bg-slate-600/80 text-slate-300' ?>"><?= htmlspecialchars($r['role']) ?></span>
                            </td>
                            <td class="px-5 py-3 text-slate-400"><?= htmlspecialchars($r['requested_at']) ?></td>
                            <td class="px-5 py-3">
                                <button type="button" onclick="document.getElementById('modal-<?= (int)$r['id'] ?>').classList.remove('hidden')" class="rounded px-3 py-1.5 text-xs font-medium bg-primary-500/90 text-slate-900 hover:bg-primary-400">Set new password</button>
                                <form method="post" class="inline-block ml-1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="rounded px-3 py-1.5 text-xs font-medium bg-slate-600 hover:bg-slate-500 text-slate-200">Cancel</button>
                                </form>
                            </td>
                        </tr>
                        <!-- Modal for set password -->
                        <tr id="modal-<?= (int)$r['id'] ?>" class="hidden bg-slate-800/80">
                            <td colspan="3" class="px-5 py-4">
                                <div class="rounded-xl border border-slate-600 bg-slate-900/90 p-4 max-w-md">
                                    <p class="text-xs font-medium text-slate-300 mb-3">Set new password for <strong class="text-slate-100"><?= htmlspecialchars($r['username']) ?></strong></p>
                                    <form method="post" class="space-y-3">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="set_password">
                                        <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">New password</label>
                                            <input type="password" name="new_password" required minlength="8" class="w-full rounded-lg border border-slate-600 bg-slate-800 text-slate-100 px-3 py-2 text-sm" placeholder="New password">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Confirm password</label>
                                            <input type="password" name="confirm_password" required minlength="8" class="w-full rounded-lg border border-slate-600 bg-slate-800 text-slate-100 px-3 py-2 text-sm" placeholder="Confirm">
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" class="rounded-lg bg-primary-500 hover:bg-primary-400 text-slate-900 font-semibold px-3 py-2 text-sm">Set password</button>
                                            <button type="button" onclick="document.getElementById('modal-<?= (int)$r['id'] ?>').classList.add('hidden')" class="rounded-lg border border-slate-600 px-3 py-2 text-sm text-slate-300 hover:bg-slate-700">Close</button>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <h2 class="text-sm font-medium text-slate-300 px-5 py-4 border-b border-slate-700/70">Recent (completed / cancelled)</h2>
            <?php if (empty($completed)): ?>
            <div class="px-5 py-6 text-slate-500 text-sm">None yet.</div>
            <?php else: ?>
            <div class="overflow-auto max-h-64">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/80 sticky top-0">
                        <tr class="text-left text-slate-400">
                            <th class="px-5 py-3">User</th>
                            <th class="px-5 py-3">Requested</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/80">
                        <?php foreach (array_slice($completed, 0, 20) as $r): ?>
                        <tr>
                            <td class="px-5 py-2 text-slate-200"><?= htmlspecialchars($r['username']) ?></td>
                            <td class="px-5 py-2 text-slate-400"><?= htmlspecialchars($r['requested_at']) ?></td>
                            <td class="px-5 py-2"><span class="inline-flex px-2 py-0.5 rounded text-xs <?= $r['status'] === 'completed' ? 'bg-emerald-500/20 text-emerald-200' : 'bg-slate-600/80 text-slate-400' ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <?php endif; ?>
    </div>
</main>
</body>
</html>
