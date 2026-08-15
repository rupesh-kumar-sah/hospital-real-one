<?php
/**
 * Hospital Management System — Admin: Departments & Wards Management
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$pageTitle = 'Manage Departments';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Departments']];

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_department') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($name) {
            $stmt = $db->prepare("INSERT INTO departments (name, description, status) VALUES (?, ?, 'active')");
            $stmt->execute([$name, $description]);
            logAudit('create', 'departments', $db->lastInsertId(), "Created department {$name}");
            setFlash('success', "Department '{$name}' created.");
            header('Location: /admin/manage_departments.php');
            exit;
        }
    } elseif ($action === 'delete_department') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$deptId]);
        logAudit('delete', 'departments', $deptId, "Deleted department #{$deptId}");
        setFlash('success', "Department deleted successfully.");
        header('Location: /admin/manage_departments.php');
        exit;
    } elseif ($action === 'toggle_status') {
        $deptId = (int)($_POST['department_id'] ?? 0);
        $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $db->prepare("UPDATE departments SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $deptId]);
        logAudit('update', 'departments', $deptId, "Updated department status to {$newStatus}");
        setFlash('success', "Department status updated to {$newStatus}.");
        header('Location: /admin/manage_departments.php');
        exit;
    }
}

$departments = $db->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM doctors WHERE department_id = d.id) as doctor_count,
           (SELECT COUNT(*) FROM appointments WHERE department_id = d.id) as appt_count
    FROM departments d
    ORDER BY d.name
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Hospital Departments</h1>
        <p class="page-subtitle">Manage, activate, deactivate, or delete medical departments</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addDeptModal')">
        <i class="fas fa-plus"></i> Add Department
    </button>
</div>

<div class="grid-3">
    <?php foreach ($departments as $dept): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-building text-primary"></i> <?= sanitize($dept['name']) ?></h3>
            <span class="badge <?= $dept['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($dept['status']) ?></span>
        </div>
        <div class="card-body">
            <p class="text-sm text-muted mb-16"><?= sanitize($dept['description'] ?: 'No description provided.') ?></p>
            <div class="d-flex justify-between text-sm font-semibold border-top pt-12 mb-12">
                <span><i class="fas fa-user-doctor text-primary"></i> <?= $dept['doctor_count'] ?> Doctors</span>
                <span><i class="fas fa-calendar-check text-success"></i> <?= $dept['appt_count'] ?> Appointments</span>
            </div>
            <div class="d-flex gap-8 justify-end">
                <form method="POST" inline style="display:inline;">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                    <input type="hidden" name="status" value="<?= $dept['status'] ?>">
                    <button type="submit" class="btn btn-sm <?= $dept['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>">
                        <?= $dept['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>

                <form method="POST" inline style="display:inline;" onsubmit="return confirm('Delete department <?= sanitize($dept['name']) ?>?');">
                    <input type="hidden" name="action" value="delete_department">
                    <input type="hidden" name="department_id" value="<?= $dept['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash-can"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="modal-overlay" id="addDeptModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Department</h3>
            <button class="modal-close" onclick="closeModal('addDeptModal')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="add_department">
                <div class="form-group">
                    <label class="form-label">Department Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Neurology">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" placeholder="Brief overview of department services..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addDeptModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Department</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
