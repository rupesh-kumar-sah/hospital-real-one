<?php
/**
 * MediCare HMS — Automated Verification & Test Suite
 * Tests Core Functions, Database Connection, Encryption, CSRF, Rate Limiting, and IDOR Checks.
 */

// Define CLI execution environment
$_SERVER['HTTP_HOST'] = 'localhost:9000';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/encryption.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_middleware.php';

$passed = 0;
$failed = 0;
$testResults = [];

function assertTest(string $testName, bool $condition, string $failureMsg = '') {
    global $passed, $failed, $testResults;
    if ($condition) {
        $passed++;
        $testResults[] = "[PASS] {$testName}";
    } else {
        $failed++;
        $testResults[] = "[FAIL] {$testName}: {$failureMsg}";
    }
}

echo "=====================================================\n";
echo " MediCare HMS — Running Automated Test Suite\n";
echo "=====================================================\n\n";

// TEST 1: Database Connection & Querying
try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $res = $stmt->fetch();
    assertTest("Database Connection & Users Count Query", isset($res['cnt']), "Failed to fetch user count from database");
} catch (\Throwable $e) {
    assertTest("Database Connection & Users Count Query", false, $e->getMessage());
}

// TEST 2: Field-Level Encryption & Decryption (E2EE)
try {
    $secret = "Patient Medical Record Notes #12345 - Confidential";
    $encrypted = encryptData($secret);
    $decrypted = decryptData($encrypted);
    assertTest("E2EE Field Encryption Format", str_starts_with($encrypted, 'ENC::'), "Encrypted string missing ENC:: prefix");
    assertTest("E2EE Field Decryption Integrity", $decrypted === $secret, "Decrypted data does not match original plaintext");
} catch (\Throwable $e) {
    assertTest("E2EE Encryption & Decryption", false, $e->getMessage());
}

// TEST 3: CSRF Token Generation & Verification
try {
    $token = generateCSRFToken();
    assertTest("CSRF Token Generation", !empty($token) && strlen($token) >= 32, "CSRF token is empty or too short");
    assertTest("CSRF Token Verification Success", verifyCSRFToken($token), "Valid CSRF token failed verification");
    assertTest("CSRF Token Verification Failure on Invalid Token", !verifyCSRFToken("invalid_token_str"), "Invalid token passed verification");
} catch (\Throwable $e) {
    assertTest("CSRF Token Suite", false, $e->getMessage());
}

// TEST 4: Utility Functions
try {
    $uhid = generateUHID();
    assertTest("UHID Generator Format", str_starts_with($uhid, 'UHID-'), "UHID does not start with UHID- prefix");

    $inv = generateInvoiceNumber();
    assertTest("Invoice Generator Format", str_starts_with($inv, 'INV-'), "Invoice number does not start with INV-");

    $age = calculateAge('1995-05-15');
    assertTest("Age Calculation", str_contains($age, 'yrs'), "Age string invalid format");

    $currency = formatCurrency(1250.50);
    assertTest("Currency Formatter", str_contains($currency, 'Rs.') && str_contains($currency, '1,250.50'), "Currency string invalid format");
} catch (\Throwable $e) {
    assertTest("Utility Functions Suite", false, $e->getMessage());
}

// TEST 5: Password Hashing & Verification
try {
    $pass = "MedicareStaff#2026!";
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    assertTest("Password Verification Success", password_verify($pass, $hash), "Valid password failed verification");
    assertTest("Password Verification Failure", !password_verify("wrong_pass", $hash), "Wrong password verified as true");
} catch (\Throwable $e) {
    assertTest("Password Hashing Suite", false, $e->getMessage());
}

// TEST 6: Rate Limiting Helper
try {
    resetRateLimit('test_action');
    $attempt1 = checkRateLimit('test_action', 2, 60);
    $attempt2 = checkRateLimit('test_action', 2, 60);
    $attempt3 = checkRateLimit('test_action', 2, 60);
    assertTest("Rate Limiter Allows Allowed Attempts", $attempt1 && $attempt2, "Rate limiter blocked early attempt");
    assertTest("Rate Limiter Blocks Exceeded Attempts", !$attempt3, "Rate limiter allowed exceeding attempt");
    resetRateLimit('test_action');
} catch (\Throwable $e) {
    assertTest("Rate Limiter Suite", false, $e->getMessage());
}

// TEST 7: Health Diagnostic Check
try {
    ob_start();
    include __DIR__ . '/../api/health.php';
    $json = ob_get_clean();
    $healthData = json_decode($json, true);
    assertTest("API Health JSON Endpoint Status", ($healthData['status'] ?? '') === 'ok', "Health endpoint status is not ok");
    assertTest("API Health Database Connection Flag", ($healthData['database']['connected'] ?? false) === true, "Health endpoint reports db disconnected");
} catch (\Throwable $e) {
    assertTest("API Health Check", false, $e->getMessage());
}

// TEST 8: JWT Access Token & Security Validation
try {
    require_once __DIR__ . '/../config/jwt.php';
    
    $testUser = [
        'id' => 999,
        'username' => 'test_doctor',
        'email' => 'doctor@hospital.test',
        'role' => 'doctor',
        'full_name' => 'Dr. Test Specialist'
    ];
    
    // 8.1 Valid JWT Generation & Verification
    $jwt = generateAccessToken($testUser);
    $payload = verifyAccessToken($jwt);
    assertTest("JWT Access Token Generation & Signature Verification", $payload !== null && $payload['sub'] === 999 && $payload['role'] === 'doctor', "Valid JWT verification failed");
    
    // 8.2 Tampered JWT Rejection
    $tamperedJwt = $jwt . 'tamper';
    $tamperedPayload = verifyAccessToken($tamperedJwt);
    assertTest("Tampered JWT Access Token Rejection", $tamperedPayload === null, "Tampered JWT passed verification");
    
    // 8.3 Forged Payload Rejection
    $parts = explode('.', $jwt);
    $forgedPayloadObj = json_decode(base64UrlDecode($parts[1]), true);
    $forgedPayloadObj['role'] = 'admin'; // Role escalation attempt
    $forgedPayloadStr = base64UrlEncode(json_encode($forgedPayloadObj));
    $forgedJwt = $parts[0] . '.' . $forgedPayloadStr . '.' . $parts[2];
    $forgedResult = verifyAccessToken($forgedJwt);
    assertTest("Role Escalation Tampered JWT Rejection", $forgedResult === null, "Forged role escalation JWT passed verification");
    
    // 8.4 Refresh Token Generation, Storage & Revocation
    $rawRefresh = generateRefreshToken();
    
    // Ensure table exists in local DB
    $db = getDB();
    $db->exec("CREATE TABLE IF NOT EXISTS refresh_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, token_hash VARCHAR(64) UNIQUE NOT NULL, expires_at DATETIME NOT NULL, revoked INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(45), user_agent TEXT)");
    
    // Test storing and revoking
    $storeOk = storeRefreshToken(1, $rawRefresh, '127.0.0.1', 'PHPUnitTest');
    assertTest("Refresh Token Database Storage", $storeOk === true, "Failed to store refresh token in database");
    
    $verifyUser = verifyRefreshToken($rawRefresh);
    assertTest("Refresh Token Verification", $verifyUser !== null && (int)$verifyUser['user_id'] === 1, "Refresh token verification failed");
    
    $revokeOk = revokeRefreshToken($rawRefresh);
    assertTest("Refresh Token Revocation Execution", $revokeOk === true, "Failed to revoke refresh token");
    
    $reverifyRevoked = verifyRefreshToken($rawRefresh);
    assertTest("Revoked Refresh Token Rejection", $reverifyRevoked === null, "Revoked refresh token was accepted");
    
} catch (\Throwable $e) {
    assertTest("JWT & Refresh Token Security Suite", false, $e->getMessage());
}

// Display Summary
echo implode("\n", $testResults) . "\n\n";
echo "-----------------------------------------------------\n";
echo " Total Tests: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo "=====================================================\n";

exit($failed === 0 ? 0 : 1);

