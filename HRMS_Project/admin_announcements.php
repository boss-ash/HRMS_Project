<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';
requireLogin();
requireAdmin();

$base = getBasePath();
$flash_error = '';
$flash_success = '';

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create' && csrf_validate()) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $published = isset($_POST['is_published']) ? 1 : 0;
    if ($title === '') {
        $flash_error = 'Title is required.';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, content, is_published, created_by) VALUES (?, ?, ?, ?)");
        $uid = (int) $_SESSION['user_id'];
        mysqli_stmt_bind_param($stmt, 'ssii', $title, $content, $published, $uid);
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, $uid, 'create_announcement', 'Announcement: ' . substr($title, 0, 50));
            $flash_success = 'Announcement created.';
        } else {
            $flash_error = 'Failed to create.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Toggle publish
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && $_POST['action'] === 'toggle' && csrf_validate()) {
    $id = (int) $_POST['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_published = NOT is_published WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
            $flash_success = 'Announcement updated.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id']) && $_POST['action'] === 'delete' && csrf_validate()) {
    $id = (int) $_POST['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM announcements WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
            log_activity($conn, $_SESSION['user_id'], 'delete_announcement', 'ID ' . $id);
            $flash_success = 'Announcement deleted.';
        }
        mysqli_stmt_close($stmt);
    }
}

$list = [];
$res = @mysqli_query($conn, "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 50");
if ($res) { while ($row = mysqli_fetch_assoc($res)) $list[] = $row; }

$pageTitle = 'Announcements';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Admin</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-50">Announcements</h1>
            <p class="text-sm text-slate-400 mt-1">Create and manage company announcements. Staff see published ones on Announcements page.</p>
        </div>

        <?php if ($flash_error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5 mb-6">
            <h2 class="text-sm font-medium text-slate-300 mb-4">New announcement</h2>
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <div>
                    <label for="title" class="block text-xs font-medium text-slate-400 mb-1">Title</label>
                    <input type="text" id="title" name="title" required maxlength="200" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm" placeholder="Announcement title">
                </div>
                <div>
                    <label for="content" class="block text-xs font-medium text-slate-400 mb-1">Content</label>
                    <textarea id="content" name="content" rows="4" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm" placeholder="Message to staff"></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_published" name="is_published" value="1" checked class="rounded border-slate-600 bg-slate-800 text-primary-500">
                    <label for="is_published" class="text-sm text-slate-300">Published (visible to staff)</label>
                </div>
                <button type="submit" class="rounded-lg bg-primary-500 hover:bg-primary-400 text-slate-900 font-semibold px-4 py-2 text-sm">Create</button>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 overflow-hidden">
            <h2 class="text-sm font-medium text-slate-300 px-5 py-4 border-b border-slate-700/70">All announcements</h2>
            <div class="overflow-auto max-h-[calc(100vh-28rem)]">
                <table class="w-full text-sm">
                    <thead class="bg-slate-900/80 sticky top-0 z-10">
                        <tr class="text-left text-slate-400">
                            <th class="px-5 py-3">Title</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Created</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list)): ?>
                        <tr><td colspan="4" class="px-5 py-8 text-slate-500">No announcements yet.</td></tr>
                        <?php else: foreach ($list as $a): ?>
                        <tr class="border-t border-slate-800/80 hover:bg-slate-800/50">
                            <td class="px-5 py-3 text-slate-200"><?= htmlspecialchars($a['title']) ?></td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded text-xs <?= $a['is_published'] ? 'bg-emerald-500/20 text-emerald-200' : 'bg-slate-600/80 text-slate-400' ?>"><?= $a['is_published'] ? 'Published' : 'Draft' ?></span>
                            </td>
                            <td class="px-5 py-3 text-slate-400"><?= htmlspecialchars($a['created_at']) ?></td>
                            <td class="px-5 py-3 flex gap-1">
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="rounded px-2 py-1 text-xs bg-slate-600 hover:bg-slate-500 text-slate-200"><?= $a['is_published'] ? 'Unpublish' : 'Publish' ?></button>
                                </form>
                                <form method="post" class="inline" onsubmit="return confirm('Delete this announcement?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                    <button type="submit" class="rounded px-2 py-1 text-xs bg-red-500/80 hover:bg-red-500 text-slate-100">Delete</button>
                                </form>
                            </td>
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
