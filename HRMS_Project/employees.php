<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/ensure_archive.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/activity_log.php';
requireLogin();
requireAdmin();

$base = getBasePath();
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? '';
if (isset($_SESSION['flash_message'])) { unset($_SESSION['flash_message'], $_SESSION['flash_type']); }

// Archive (soft delete): store for 30 days, then auto-deleted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive' && isset($_POST['id']) && csrf_validate()) {
    $id = (int) $_POST['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE employees SET archived_at = NOW() WHERE id = ? AND (archived_at IS NULL OR archived_at = '')");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
            log_activity($conn, $_SESSION['user_id'], 'archive_employee', 'Employee ID ' . $id);
            $message = 'Employee archived. They can be restored from Archive within 30 days.';
            $messageType = 'success';
        }
        mysqli_stmt_close($stmt);
    }
}

// Restore from archive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore' && isset($_POST['id']) && csrf_validate()) {
    $id = (int) $_POST['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE employees SET archived_at = NULL WHERE id = ? AND archived_at IS NOT NULL");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
            log_activity($conn, $_SESSION['user_id'], 'restore_employee', 'Employee ID ' . $id);
            $message = 'Employee restored to active list.';
            $messageType = 'success';
        }
        mysqli_stmt_close($stmt);
    }
}

// Purge archived older than 30 days (only when viewing archive list)
$viewArchive = isset($_GET['view']) && $_GET['view'] === 'archived';
$ARCHIVE_DAYS = 30;
if ($viewArchive) {
    $purgeSql = "DELETE FROM employees WHERE archived_at IS NOT NULL AND archived_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $purgeStmt = mysqli_prepare($conn, $purgeSql);
    mysqli_stmt_bind_param($purgeStmt, 'i', $ARCHIVE_DAYS);
    mysqli_stmt_execute($purgeStmt);
    $purged = mysqli_affected_rows($conn);
    mysqli_stmt_close($purgeStmt);
    if ($purged > 0) {
        log_activity($conn, $_SESSION['user_id'], 'purge_archived', $purged . ' employee(s) older than ' . $ARCHIVE_DAYS . ' days');
    }
}

// Create or Update (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create', 'update'], true) && csrf_validate()) {
    $employee_code = sanitize_code($_POST['employee_code'] ?? '', 20);
    $first_name = sanitize_string($_POST['first_name'] ?? '', 50);
    $last_name = sanitize_string($_POST['last_name'] ?? '', 50);
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_string($_POST['phone'] ?? '', 20);
    $department = sanitize_string($_POST['department'] ?? '', 50);
    $position = sanitize_string($_POST['position'] ?? '', 50);
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
    $salary = isset($_POST['salary']) && $_POST['salary'] !== '' ? (float) $_POST['salary'] : null;
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'on_leave'], true) ? $_POST['status'] : 'active';

    if ($hire_date !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
        $hire_date = null;
    }

    $errors = [];
    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (!validate_email($email)) $errors[] = 'Invalid email format.';
    if (empty($employee_code)) $errors[] = 'Employee code is required.';

    if (empty($errors)) {
        if ($_POST['action'] === 'create') {
            $stmt = mysqli_prepare($conn, "SELECT id FROM employees WHERE employee_code = ? OR email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'ss', $employee_code, $email);
            mysqli_stmt_execute($stmt);
            $check = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
            if (mysqli_num_rows($check)) {
                $errors[] = 'Employee code or email already exists.';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO employees (employee_code, first_name, last_name, email, phone, department, position, hire_date, salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'ssssssssds', $employee_code, $first_name, $last_name, $email, $phone, $department, $position, $hire_date, $salary, $status);
                if (mysqli_stmt_execute($stmt)) {
                    log_activity($conn, $_SESSION['user_id'], 'add_employee', $employee_code . ' - ' . $first_name . ' ' . $last_name);
                    $_SESSION['flash_message'] = 'Employee added successfully.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ' . $base . 'employees.php');
                    exit;
                } else {
                    $errors[] = 'Failed to add employee.';
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($conn, "SELECT id FROM employees WHERE id != ? AND (employee_code = ? OR email = ?) LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'iss', $id, $employee_code, $email);
            mysqli_stmt_execute($stmt);
            $check = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
            if (mysqli_num_rows($check)) {
                $errors[] = 'Employee code or email already in use by another employee.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE employees SET employee_code=?, first_name=?, last_name=?, email=?, phone=?, department=?, position=?, hire_date=?, salary=?, status=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssssssssdsi', $employee_code, $first_name, $last_name, $email, $phone, $department, $position, $hire_date, $salary, $status, $id);
                if (mysqli_stmt_execute($stmt)) {
                    log_activity($conn, $_SESSION['user_id'], 'edit_employee', 'Employee ID ' . $id . ' - ' . $employee_code);
                    $_SESSION['flash_message'] = 'Employee updated successfully.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ' . $base . 'employees.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update employee.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
    if (!empty($errors)) {
        $message = implode(' ', $errors);
        $messageType = 'error';
    }
}

if ($viewArchive) {
    $employees = mysqli_query($conn, "SELECT * FROM employees WHERE archived_at IS NOT NULL ORDER BY archived_at DESC");
} else {
    $employees = mysqli_query($conn, "SELECT * FROM employees WHERE archived_at IS NULL ORDER BY created_at DESC");
}
$pageTitle = $viewArchive ? 'Archived Employees' : 'Employees';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="min-h-[calc(100vh-4rem)] bg-slate-950/95 text-slate-50">
    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
        <div class="pointer-events-none absolute -top-10 -left-16 h-56 w-56 rounded-full bg-primary-500/40 blur-3xl"></div>
        <div class="pointer-events-none absolute top-16 -right-10 h-52 w-52 rounded-full bg-emerald-500/40 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 left-1/2 h-40 w-[26rem] -translate-x-1/2 bg-gradient-to-r from-slate-900 via-primary-700/40 to-slate-900 blur-3xl opacity-70"></div>

        <div class="relative z-10">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <p class="text-[10px] uppercase tracking-[0.24em] text-slate-400 mb-1">Admin workspace</p>
                    <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-slate-50"><?= $viewArchive ? 'Archived employees' : 'Employee directory' ?></h1>
                    <p class="text-sm text-slate-400 mt-1"><?= $viewArchive ? 'Restore within 30 days or records are permanently removed.' : 'Add, edit, and manage employees stored in Horyzon.' ?></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($viewArchive): ?>
                    <a href="<?= htmlspecialchars($base) ?>employees.php" class="inline-flex items-center rounded-2xl border border-slate-700/70 bg-slate-900/70 px-4 py-2.5 text-sm font-medium text-slate-200 hover:border-primary-400/80 hover:text-primary-100">← Back to directory</a>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars($base) ?>employees.php?view=archived" class="inline-flex items-center rounded-2xl border border-amber-600/50 bg-amber-950/30 px-4 py-2.5 text-sm font-medium text-amber-100 hover:border-amber-500/60">Archived (30-day store)</a>
                    <button
                    type="button"
                    onclick="openModal()"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary-500/90 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-md shadow-primary-900/40 hover:bg-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-slate-950">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
                    </svg>
                    Add employee
                </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 rounded-2xl px-4 py-3 text-sm <?= $messageType === 'success'
                ? 'bg-emerald-500/10 border border-emerald-500/40 text-emerald-100'
                : 'bg-red-500/10 border border-red-500/40 text-red-100' ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <div class="rounded-2xl border border-slate-700/70 bg-slate-900/80 shadow-[0_18px_60px_rgba(15,23,42,0.8)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-800/80 text-sm">
                        <thead class="bg-slate-900/80">
                            <tr>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Code</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Name</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Email</th>
                                <?php if ($viewArchive): ?>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Archived on</th>
                                <?php else: ?>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Department</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Position</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Status</th>
                                <?php endif; ?>
                                <th class="px-6 py-3 text-right text-[11px] font-medium text-slate-400 uppercase tracking-[0.16em]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            <?php if (mysqli_num_rows($employees)): while ($emp = mysqli_fetch_assoc($employees)): ?>
                            <tr class="hover:bg-slate-900/60 transition">
                                <td class="px-6 py-3 text-[13px] font-medium text-slate-50 whitespace-nowrap">
                                    <?= htmlspecialchars($emp['employee_code']) ?>
                                </td>
                                <td class="px-6 py-3 text-[13px] text-slate-100 whitespace-nowrap">
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </td>
                                <td class="px-6 py-3 text-[13px] text-slate-300">
                                    <?= htmlspecialchars($emp['email']) ?>
                                </td>
                                <?php if ($viewArchive): ?>
                                <td class="px-6 py-3 text-[13px] text-slate-400 whitespace-nowrap">
                                    <?= !empty($emp['archived_at']) ? htmlspecialchars(date('M j, Y H:i', strtotime($emp['archived_at']))) : '—' ?>
                                </td>
                                <td class="px-6 py-3 text-right text-[13px] whitespace-nowrap">
                                    <form method="POST" class="inline" onsubmit="return confirm('Restore this employee to the active directory?');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
                                        <button type="submit" class="text-emerald-300 hover:text-emerald-200 font-medium">Restore</button>
                                    </form>
                                </td>
                                <?php else: ?>
                                <td class="px-6 py-3 text-[13px] text-slate-300 whitespace-nowrap">
                                    <?= htmlspecialchars($emp['department'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-3 text-[13px] text-slate-300 whitespace-nowrap">
                                    <?= htmlspecialchars($emp['position'] ?? '—') ?>
                                </td>
                                <td class="px-6 py-3">
                                    <?php
                                    $statusClass = $emp['status'] === 'active'
                                        ? 'bg-emerald-500/15 text-emerald-100 border border-emerald-400/50'
                                        : ($emp['status'] === 'on_leave'
                                            ? 'bg-amber-500/15 text-amber-100 border border-amber-400/50'
                                            : 'bg-slate-700/60 text-slate-100 border border-slate-500/60');
                                    ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-medium <?= $statusClass ?>">
                                        <?= htmlspecialchars($emp['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-right text-[13px] whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="js-edit-employee text-primary-200 hover:text-primary-100 font-medium"
                                        data-employee="<?= htmlspecialchars(json_encode([
                                            'id' => (int)$emp['id'],
                                            'employee_code' => $emp['employee_code'],
                                            'first_name' => $emp['first_name'],
                                            'last_name' => $emp['last_name'],
                                            'email' => $emp['email'],
                                            'phone' => $emp['phone'] ?? '',
                                            'department' => $emp['department'] ?? '',
                                            'position' => $emp['position'] ?? '',
                                            'hire_date' => $emp['hire_date'] ?? '',
                                            'salary' => $emp['salary'] !== null ? (float)$emp['salary'] : '',
                                            'status' => $emp['status']
                                        ]), ENT_QUOTES, 'UTF-8') ?>">
                                        Edit
                                    </button>
                                    <form
                                        method="POST"
                                        class="inline ml-3"
                                        onsubmit="return confirm('Archive this employee? They can be restored from Archive within 30 days.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
                                        <button type="submit" class="text-amber-300 hover:text-amber-200 font-medium">
                                            Archive
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="<?= $viewArchive ? '5' : '7' ?>" class="px-6 py-12 text-center text-sm text-slate-400">
                                    <?= $viewArchive ? 'No archived employees.' : 'No employees yet. Use “Add employee” to create one.' ?>
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

<!-- Modal: Add/Edit Employee (system dark theme) -->
<div id="employeeModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="relative rounded-2xl border border-slate-700/80 bg-slate-900 shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-700/80">
                <h2 id="modalTitle" class="text-lg font-semibold text-slate-50">Add Employee</h2>
                <p class="text-xs text-slate-400 mt-0.5">Fill in the details below. Required fields are marked with *.</p>
            </div>
            <form id="employeeForm" method="POST" action="<?= htmlspecialchars($base) ?>employees.php" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="employee_code" class="block text-xs font-medium text-slate-400 mb-1">Employee Code *</label>
                        <input type="text" id="employee_code" name="employee_code" required maxlength="20" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                    </div>
                    <div class="col-span-2 grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-xs font-medium text-slate-400 mb-1">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required maxlength="50" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                        </div>
                        <div>
                            <label for="last_name" class="block text-xs font-medium text-slate-400 mb-1">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required maxlength="50" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label for="email" class="block text-xs font-medium text-slate-400 mb-1">Email *</label>
                        <input type="email" id="email" name="email" required maxlength="100" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-medium text-slate-400 mb-1">Phone</label>
                        <input type="text" id="phone" name="phone" maxlength="20" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                    </div>
                    <div>
                        <label for="department" class="block text-xs font-medium text-slate-400 mb-1">Department</label>
                        <input type="text" id="department" name="department" maxlength="50" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                    </div>
                    <div>
                        <label for="position" class="block text-xs font-medium text-slate-400 mb-1">Position</label>
                        <input type="text" id="position" name="position" maxlength="50" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                    </div>
                    <div>
                        <label for="hire_date" class="block text-xs font-medium text-slate-400 mb-1">Hire Date</label>
                        <input type="date" id="hire_date" name="hire_date" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                    </div>
                    <div>
                        <label for="salary" class="block text-xs font-medium text-slate-400 mb-1">Salary</label>
                        <input type="number" id="salary" name="salary" step="0.01" min="0" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                    </div>
                    <div class="col-span-2">
                        <label for="status" class="block text-xs font-medium text-slate-400 mb-1">Status</label>
                        <select id="status" name="status" class="w-full rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-2 text-sm text-slate-100 focus:border-primary-400 focus:ring-2 focus:ring-primary-500/40">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                </div>
            </form>
            <div class="px-6 py-4 border-t border-slate-700/80 flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-lg border border-slate-600 text-slate-300 font-medium hover:bg-slate-800 hover:text-slate-100">Cancel</button>
                <button type="submit" form="employeeForm" id="employeeFormSubmit" class="px-4 py-2 rounded-lg bg-primary-500 text-slate-900 font-semibold hover:bg-primary-400 focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-60 disabled:pointer-events-none">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.js-edit-employee').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var data = this.getAttribute('data-employee');
        if (!data) return;
        try {
            var emp = JSON.parse(data);
            document.getElementById('modalTitle').textContent = 'Edit Employee';
            document.getElementById('formAction').value = 'update';
            document.getElementById('formId').value = emp.id || '';
            document.getElementById('employee_code').value = emp.employee_code || '';
            document.getElementById('first_name').value = emp.first_name || '';
            document.getElementById('last_name').value = emp.last_name || '';
            document.getElementById('email').value = emp.email || '';
            document.getElementById('phone').value = emp.phone || '';
            document.getElementById('department').value = emp.department || '';
            document.getElementById('position').value = emp.position || '';
            document.getElementById('hire_date').value = emp.hire_date || '';
            document.getElementById('salary').value = emp.salary !== undefined && emp.salary !== '' ? emp.salary : '';
            document.getElementById('status').value = emp.status || 'active';
            document.getElementById('employeeModal').classList.remove('hidden');
        } catch (e) {}
    });
});
function openModal() {
    document.getElementById('modalTitle').textContent = 'Add Employee';
    document.getElementById('formAction').value = 'create';
    document.getElementById('formId').value = '';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeModal').classList.remove('hidden');
}
function closeModal() {
    document.getElementById('employeeModal').classList.add('hidden');
}
if (window.location.search.indexOf('add=1') !== -1) {
    document.addEventListener('DOMContentLoaded', function() { openModal(); });
}
var form = document.getElementById('employeeForm');
if (form) {
    form.addEventListener('submit', function() {
        var btn = document.getElementById('employeeFormSubmit');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    });
}
</script>
</body>
</html>
