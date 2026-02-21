<?php
define('HRMS_LOADED', true);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/activity_log.php';
requireLogin();
requireAdmin();

$base = getBasePath();
$message = '';
$messageType = '';

// Delete (Admin only - enforced by requireAdmin above)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id']) && csrf_validate()) {
    $id = (int) $_POST['id'];
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM employees WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) {
            log_activity($conn, $_SESSION['user_id'], 'delete_employee', 'Employee ID ' . $id);
            $message = 'Employee deleted successfully.';
            $messageType = 'success';
        }
        mysqli_stmt_close($stmt);
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
                    $message = 'Employee added successfully.';
                    $messageType = 'success';
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
                    $message = 'Employee updated successfully.';
                    $messageType = 'success';
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

$employees = mysqli_query($conn, "SELECT * FROM employees ORDER BY created_at DESC");
$pageTitle = 'Employees';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Employee Management</h1>
            <p class="text-slate-500 mt-1">Add, edit, and manage employees (Admin only)</p>
        </div>
        <button type="button" onclick="openModal()" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white font-medium hover:bg-primary-700 focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Add Employee
        </button>
    </div>

    <?php if ($message): ?>
    <div class="mb-6 rounded-lg px-4 py-3 <?= $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Department</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Position</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (mysqli_num_rows($employees)): while ($emp = mysqli_fetch_assoc($employees)): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm font-medium text-slate-800"><?= htmlspecialchars($emp['employee_code']) ?></td>
                        <td class="px-6 py-4 text-sm text-slate-800"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                        <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($emp['email']) ?></td>
                        <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($emp['department'] ?? '—') ?></td>
                        <td class="px-6 py-4 text-sm text-slate-600"><?= htmlspecialchars($emp['position'] ?? '—') ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium <?= $emp['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : ($emp['status'] === 'on_leave' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-800') ?>"><?= htmlspecialchars($emp['status']) ?></span>
                        </td>
                        <td class="px-6 py-4 text-right text-sm">
                            <button type="button" class="js-edit-employee text-primary-600 hover:text-primary-700 font-medium"
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
                                ]), ENT_QUOTES, 'UTF-8') ?>">Edit</button>
                            <form method="POST" class="inline ml-2" onsubmit="return confirm('Delete this employee?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
                                <button type="submit" class="text-red-600 hover:text-red-700 font-medium">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">No employees yet. Click "Add Employee" to create one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal: Add/Edit Employee -->
<div id="employeeModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/50 transition-opacity" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-slate-200">
                <h2 id="modalTitle" class="text-lg font-semibold text-slate-800">Add Employee</h2>
            </div>
            <form id="employeeForm" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="employee_code" class="block text-sm font-medium text-slate-700 mb-1">Employee Code *</label>
                        <input type="text" id="employee_code" name="employee_code" required maxlength="20" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="col-span-2 grid grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required maxlength="50" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required maxlength="50" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                        <input type="email" id="email" name="email" required maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                        <input type="text" id="phone" name="phone" maxlength="20" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="department" class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                        <input type="text" id="department" name="department" maxlength="50" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="position" class="block text-sm font-medium text-slate-700 mb-1">Position</label>
                        <input type="text" id="position" name="position" maxlength="50" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="hire_date" class="block text-sm font-medium text-slate-700 mb-1">Hire Date</label>
                        <input type="date" id="hire_date" name="hire_date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label for="salary" class="block text-sm font-medium text-slate-700 mb-1">Salary</label>
                        <input type="number" id="salary" name="salary" step="0.01" min="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="col-span-2">
                        <label for="status" class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-800 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                </div>
            </form>
            <div class="px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 font-medium hover:bg-slate-50">Cancel</button>
                <button type="submit" form="employeeForm" class="px-4 py-2 rounded-lg bg-primary-600 text-white font-medium hover:bg-primary-700">Save</button>
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
</script>
</body>
</html>
