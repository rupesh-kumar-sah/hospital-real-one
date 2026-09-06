<?php
/**
 * REST API — /api/v1/patient/profile
 * GET: Returns full demographics and health notes
 * PUT: Updates editable demographics
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

$auth = requireApiAuth('patient');
$userId = (int)$auth['sub'];
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT u.id as user_id, u.username, u.email, u.full_name, u.phone, u.avatar,
                   p.id as patient_id, p.uhid, p.date_of_birth, p.gender, p.blood_group, p.marital_status,
                   p.address, p.city, p.state, p.zip_code, p.emergency_contact_name, p.emergency_contact_phone,
                   p.emergency_contact_relation, p.insurance_provider, p.insurance_number, p.allergies, p.chronic_conditions
            FROM users u
            JOIN patients p ON u.id = p.user_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
        
        if (!$profile) {
            jsonError('Profile not found', 404);
        }
        
        jsonSuccess($profile, 'Profile retrieved');
    } catch (\Throwable $e) {
        jsonError('Failed to fetch profile: ' . $e->getMessage(), 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    
    $fullName = trim($body['full_name'] ?? '');
    $phone = trim($body['phone'] ?? '');
    $address = trim($body['address'] ?? '');
    $city = trim($body['city'] ?? '');
    $state = trim($body['state'] ?? '');
    $emergencyName = trim($body['emergency_contact_name'] ?? '');
    $emergencyPhone = trim($body['emergency_contact_phone'] ?? '');
    
    try {
        $db->beginTransaction();
        
        if (!empty($fullName) || !empty($phone)) {
            $stmtU = $db->prepare("UPDATE users SET full_name = COALESCE(NULLIF(?, ''), full_name), phone = COALESCE(NULLIF(?, ''), phone), updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtU->execute([$fullName, $phone, $userId]);
        }
        
        $stmtP = $db->prepare("
            UPDATE patients SET 
                address = ?, city = ?, state = ?, 
                emergency_contact_name = ?, emergency_contact_phone = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ?
        ");
        $stmtP->execute([$address, $city, $state, $emergencyName, $emergencyPhone, $userId]);
        
        $db->commit();
        jsonSuccess(null, 'Profile updated successfully');
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        jsonError('Failed to update profile: ' . $e->getMessage(), 500);
    }
}

jsonError('Method Not Allowed', 405);
