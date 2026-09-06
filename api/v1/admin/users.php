<?php
/**
 * REST API — /api/v1/admin/users
 * GET: Lists users with role/status filters
 * POST: Creates new staff account
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('admin');
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['q'] ?? '');
    
    $where = [];
    $params = [];
    
    if (!empty($role)) {
        $where[] = "role = ?";
        $params[] = $role;
    }
    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    if (!empty($search)) {
        $where[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
    }
    
    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    try {
        $stmt = $db->prepare("SELECT id, username, email, full_name, phone, role, status, last_login, created_at FROM users {$whereSql} ORDER BY id DESC LIMIT 100");
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        jsonSuccess($users, 'Users list retrieved');
    } catch (\Throwable $e) {
        jsonServerError('Failed to fetch users', $e);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    
    $fullName = trim($body['full_name'] ?? '');
    $username = trim($body['username'] ?? '');
    $email = trim($body['email'] ?? '');
    $phone = trim($body['phone'] ?? '');
    $role = trim($body['role'] ?? 'patient');
    $password = $body['password'] ?? '';
    $status = $body['status'] ?? 'active';
    
    if (empty($fullName) || empty($username) || empty($email) || empty($password)) {
        jsonError('Full name, username, email, and password are required.', 422);
    }
    if (!array_key_exists($role, ROLE_LABELS)) {
        jsonError('Invalid user role.', 422);
    }
    if (!in_array($status, ['active', 'inactive'], true)) {
        jsonError('Invalid user status.', 422);
    }
    if (($passwordError = passwordStrengthError($password)) !== null) {
        jsonError($passwordError, 422);
    }
    
    try {
        $check = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $check->execute([$username, $email]);
        if ($check->fetch()) {
            jsonError('A user with this username or email already exists.', 409);
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, phone, role, status, must_change_password, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$username, $email, $passwordHash, $fullName, $phone, $role, $status]);
        $newUserId = (int)$db->lastInsertId();
        
        // If role is doctor, create default doctor profile
        if ($role === 'doctor') {
            $deptId = !empty($body['department_id']) ? (int)$body['department_id'] : null;
            $fee = (float)($body['consultation_fee'] ?? 500.00);
            $spec = trim($body['specialization'] ?? 'General Physician');
            
            $stmtDoc = $db->prepare("INSERT INTO doctors (user_id, department_id, specialization, consultation_fee, status, created_at) VALUES (?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)");
            $stmtDoc->execute([$newUserId, $deptId, $spec, $fee]);
        }
        
        jsonSuccess(['id' => $newUserId, 'username' => $username, 'role' => $role], 'User created successfully', 201);
    } catch (\Throwable $e) {
        jsonServerError('Failed to create user', $e);
    }
}

jsonError('Method Not Allowed', 405);
