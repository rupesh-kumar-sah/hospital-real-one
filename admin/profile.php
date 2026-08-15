<?php
/**
 * Hospital Management System — User Profile Page
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireLogin();

$pageTitle = 'My Profile';
$breadcrumbs = [['label' => 'Profile']];

$currentUser = getCurrentUser();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
    $stmt->execute([$fullName, $phone, $email, getUserId()]);

    if (!empty($password)) {
        $stmtP = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmtP->execute([password_hash($password, PASSWORD_DEFAULT), getUserId()]);
    }

    $_SESSION['full_name'] = $fullName;
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $phone;

    setFlash('success', 'Profile updated successfully.');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <p class="page-subtitle">Manage your personal details and account credentials</p>
    </div>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= sanitize($currentUser['full_name']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($currentUser['email']) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control" value="<?= sanitize($currentUser['phone']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?= ROLE_LABELS[$currentUser['role']] ?? ucfirst($currentUser['role']) ?>" readonly style="background: var(--gray-100);">
            </div>
            <div class="form-group">
                <label class="form-label">New Password (leave blank to keep current)</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
