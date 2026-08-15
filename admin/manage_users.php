<?php
/**
 * Hospital Management System — Admin: Manage Users
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole('admin');

$pageTitle = 'Manage Users';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/admin/dashboard.php'], ['label' => 'Manage Users']];

$db = getDB();
$error = '';
$success = '';

// Handle Create / Toggle / Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($email) || empty($fullName) || empty($role) || empty($password)) {
            setFlash('error', 'Please fill in all required fields.');
        } else {
            // Check existing
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                setFlash('error', 'Username or Email already exists.');
            } else {
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([
                    $username,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    $fullName,
                    $phone,
                    $role
                ]);
                $newUserId = $db->lastInsertId();

                // If doctor or nurse or patient, add record
                if ($role === 'doctor') {
                    $deptId = (int)($_POST['department_id'] ?? 0);
                    $spec = trim($_POST['specialization'] ?? 'General');
                    $fee = (float)($_POST['consultation_fee'] ?? 500);
                    $stmtDoc = $db->prepare("INSERT INTO doctors (user_id, department_id, specialization, consultation_fee) VALUES (?, ?, ?, ?)");
                    $stmtDoc->execute([$newUserId, $deptId ?: null, $spec, $fee]);
                } elseif ($role === 'patient') {
                    $uhid = generateUHID();
                    $stmtPat = $db->prepare("INSERT INTO patients (user_id, uhid) VALUES (?, ?)");
                    $stmtPat->execute([$newUserId, $uhid]);
                } elseif ($role === 'nurse') {
                    $stmtNurse = $db->prepare("INSERT INTO nurses (user_id) VALUES (?)");
                    $stmtNurse->execute([$newUserId]);
                }

                logAudit('create', 'users', $newUserId, "Created user {$username} with role {$role}");
                setFlash('success', "User {$fullName} created successfully.");
                header('Location: /admin/manage_users.php');
                exit;
            }
        }
    } elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        logAudit('update', 'users', $userId, "Changed status of user #{$userId} to {$newStatus}");
        setFlash('success', "User status updated to {$newStatus}.");
        header('Location: /admin/manage_users.php');
        exit;
    } elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === getUserId()) {
            setFlash('error', 'You cannot delete your own logged-in account!');
        } else {
            $db->beginTransaction();
            try {
                // Delete user (cascades to doctors/patients/nurses tables)
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $db->commit();
                logAudit('delete', 'users', $userId, "Permanently deleted user #{$userId}");
                setFlash('success', "User deleted successfully.");
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', "Could not delete user: " . $e->getMessage());
            }
        }
        header('Location: /admin/manage_users.php');
        exit;
    }
}

// Fetch users with filters
$roleFilter = $_GET['role'] ?? '';
$search = trim($_GET['search'] ?? '');

$query = "SELECT u.* FROM users u WHERE 1=1";
$params = [];

if ($roleFilter) {
    $query .= " AND u.role = ?";
    $params[] = $roleFilter;
}
if ($search) {
    $query .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}
$query .= " ORDER BY u.created_at DESC";

$page = max(1, (int)($_GET['page'] ?? 1));
$pagination = paginate($query, $params, $page, 15);
$users = $pagination['data'];

$departments = $db->query("SELECT * FROM departments WHERE status = 'active'")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>User Management</h1>
        <p class="page-subtitle">View, add, activate, deactivate, or delete system user accounts</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addUserModal')">
        <i class="fas fa-user-plus"></i> Add New User
    </button>
</div>

<!-- Filters -->
<div class="card mb-24">
    <div class="card-body">
        <form method="GET" class="form-row align-center">
            <div class="search-box flex-1">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Search by name, username, email or phone..." value="<?= sanitize($search) ?>">
            </div>
            <div style="width: 200px;">
                <select name="role" class="form-control" onchange="this.form.submit()">
                    <option value="">All Roles</option>
                    <?php foreach (ROLE_LABELS as $rKey => $rLabel): ?>
                    <option value="<?= $rKey ?>" <?= $roleFilter === $rKey ? 'selected' : '' ?>><?= $rLabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
            <?php if ($search || $roleFilter): ?>
            <a href="/admin/manage_users.php" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Email / Phone</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>No users found</h3>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="info-card">
                            <div class="avatar avatar-sm" style="background: <?= ROLE_COLORS[$u['role']] ?? 'var(--primary)' ?>;">
                                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                            </div>
                            <div class="info-details">
                                <h4><?= sanitize($u['full_name']) ?></h4>
                                <p class="text-xs">@<?= sanitize($u['username']) ?></p>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge" style="--badge-color: <?= ROLE_COLORS[$u['role']] ?? '#6b7280' ?>;">
                            <i class="fas <?= ROLE_ICONS[$u['role']] ?? 'fa-user' ?>"></i>
                            <?= ROLE_LABELS[$u['role']] ?? ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <div><?= sanitize($u['email']) ?></div>
                        <div class="text-xs text-muted"><?= sanitize($u['phone'] ?: 'No phone') ?></div>
                    </td>
                    <td>
                        <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= ucfirst($u['status']) ?>
                        </span>
                    </td>
                    <td><?= formatDate($u['created_at']) ?></td>
                    <td>
                        <div class="btn-group">
                            <!-- Toggle Activate / Deactivate -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="status" value="<?= $u['status'] ?>">
                                <button type="submit" class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>" data-tooltip="<?= $u['status'] === 'active' ? 'Deactivate User' : 'Activate User' ?>">
                                    <i class="fas <?= $u['status'] === 'active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                    <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>

                            <!-- Delete User -->
                            <?php if ($u['id'] !== getUserId()): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently DELETE user <?= sanitize($u['full_name']) ?>? This action cannot be undone.');">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger" data-tooltip="Permanently Delete User">
                                    <i class="fas fa-trash-can"></i> Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <?= renderPagination($pagination, '/admin/manage_users.php') ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus text-primary"></i> Create New User Account</h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">×</button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Dr. Jane Doe">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role <span class="required">*</span></label>
                        <select name="role" id="userRoleSelect" class="form-control" required onchange="toggleDoctorFields(this.value)">
                            <option value="">Select Role</option>
                            <?php foreach (ROLE_LABELS as $rKey => $rLabel): ?>
                            <option value="<?= $rKey ?>"><?= $rLabel ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Username <span class="required">*</span></label>
                        <input type="text" name="username" class="form-control" required placeholder="janedoe">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="jane@hospital.com">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control" placeholder="98XXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-control" required placeholder="••••••••">
                    </div>
                </div>

                <!-- Doctor specific fields -->
                <div id="doctorFields" style="display: none; border-top: 1px dashed var(--gray-200); padding-top: 12px; margin-top: 8px;">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= sanitize($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" class="form-control" placeholder="Cardiologist">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Consultation Fee (Rs.)</label>
                        <input type="number" name="consultation_fee" class="form-control" value="500" step="50">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDoctorFields(role) {
    document.getElementById('doctorFields').style.display = role === 'doctor' ? 'block' : 'none';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
