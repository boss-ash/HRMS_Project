<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sanitize.php';
requireLogin();

$base = getBasePath();
$employeeId = getEmployeeId();
if ($employeeId === null) {
    $_SESSION['flash_error'] = 'No employee profile linked to your account.';
    header('Location: ' . $base . 'dashboard.php');
    exit;
}
requireOwnProfile($employeeId);

$stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, phone FROM employees WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $employeeId);
mysqli_stmt_execute($stmt);
$emp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$emp) {
    $_SESSION['flash_error'] = 'Employee record not found.';
    header('Location: ' . $base . 'dashboard.php');
    exit;
}

$flash_error = '';
$flash_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_validate()) {
    $phone = sanitize_string($_POST['phone'] ?? '', 20);
    $stmt = mysqli_prepare($conn, "UPDATE employees SET phone = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $phone, $employeeId);
    if (mysqli_stmt_execute($stmt)) {
        $flash_success = 'Profile updated.';
        $emp['phone'] = $phone;
    } else {
        $flash_error = 'Update failed.';
    }
    mysqli_stmt_close($stmt);
}

$pageTitle = 'Edit My Profile';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-50">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <a href="<?= htmlspecialchars($base) ?>profile.php" class="text-xs text-slate-400 hover:text-primary-200">← Back to My Profile</a>
            <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mt-2 mb-1">Staff</p>
            <h1 class="text-2xl font-semibold tracking-tight text-slate-50">Edit My Profile</h1>
            <p class="text-sm text-slate-400 mt-1">Update your contact information. Only phone can be edited by staff.</p>
        </div>

        <?php if ($flash_error): ?>
        <div class="mb-4 rounded-xl bg-red-500/10 border border-red-500/40 text-red-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>
        <?php if ($flash_success): ?>
        <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-500/40 text-emerald-100 px-4 py-3 text-sm"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>

        <section class="rounded-2xl border border-slate-700/70 bg-slate-900/80 p-5">
            <form method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div>
                    <label for="phone" class="block text-xs font-medium text-slate-400 mb-1">Phone</label>
                    <input type="text" id="phone" name="phone" maxlength="20" value="<?= htmlspecialchars($emp['phone'] ?? '') ?>"
                           class="w-full rounded-lg border border-slate-600 bg-slate-800/80 text-slate-100 px-3 py-2 text-sm" placeholder="e.g. 555-0101">
                </div>
                <button type="submit" class="rounded-lg bg-primary-500 hover:bg-primary-400 text-slate-900 font-semibold px-4 py-2 text-sm">Save changes</button>
            </form>
        </section>
    </div>
</main>
</body>
</html>
