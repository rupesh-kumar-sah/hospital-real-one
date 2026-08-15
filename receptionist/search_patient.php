<?php
/**
 * Hospital Management System — Receptionist: Search Patient Directory
 */

require_once __DIR__ . '/../includes/auth_middleware.php';
requireRole(['receptionist', 'admin', 'doctor', 'nurse']);

$pageTitle = 'Search Patient Directory';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/receptionist/dashboard.php'], ['label' => 'Search Patient']];

$db = getDB();
$search = trim($_GET['q'] ?? '');

$patients = [];
if ($search) {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name, u.email, u.phone 
        FROM patients p 
        JOIN users u ON p.user_id = u.id 
        WHERE p.uhid LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?
        ORDER BY u.full_name
    ");
    $term = "%{$search}%";
    $stmt->execute([$term, $term, $term, $term]);
    $patients = $stmt->fetchAll();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Search Patient Directory</h1>
        <p class="page-subtitle">Find patients by UHID, Name, Phone or Email</p>
    </div>
</div>

<div class="card mb-24">
    <div class="card-body">
        <form method="GET" class="d-flex gap-12">
            <div class="search-box flex-1" style="max-width: none;">
                <i class="fas fa-search"></i>
                <input type="text" name="q" class="form-control" placeholder="Type patient name, UHID (e.g. UHID-00001) or phone number..." value="<?= sanitize($search) ?>" autofocus required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</div>

<?php if ($search): ?>
<div class="card">
    <div class="card-header">
        <h3>Search Results for "<?= sanitize($search) ?>" (<?= count($patients) ?> found)</h3>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>UHID</th>
                    <th>Patient Name</th>
                    <th>Gender / DOB</th>
                    <th>Contact</th>
                    <th>Emergency Contact</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                <tr><td colspan="6"><div class="empty-state"><p>No patient record matching search criteria</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($patients as $pt): ?>
                <tr>
                    <td><code class="text-primary font-bold"><?= sanitize($pt['uhid']) ?></code></td>
                    <td><strong><?= sanitize($pt['full_name']) ?></strong></td>
                    <td><?= calculateAge($pt['date_of_birth']) ?> (<?= ucfirst($pt['gender'] ?: 'N/A') ?>)</td>
                    <td><?= sanitize($pt['phone'] ?: 'N/A') ?><br><span class="text-xs text-muted"><?= sanitize($pt['email']) ?></span></td>
                    <td><?= sanitize($pt['emergency_contact_name'] ?: '-') ?> (<?= sanitize($pt['emergency_contact_phone'] ?: '-') ?>)</td>
                    <td>
                        <a href="/receptionist/appointments.php?patient_id=<?= $pt['id'] ?>" class="btn btn-sm btn-primary">Book Appt</a>
                        <a href="/doctor/patient_history.php?patient_id=<?= $pt['id'] ?>" class="btn btn-sm btn-secondary">History</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
