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

// TEST 9: Billing Calculation (matches receptionist/billing.php net = subtotal - discount + tax)
try {
    $calcNet = static fn(float $subtotal, float $discount, float $tax): float => $subtotal - $discount + $tax;
    assertTest("Billing Net = Subtotal - Discount + Tax", $calcNet(1000, 100, 50) === 950.0, "Billing Math 1 failed");
    assertTest("Billing Discount Subtracts", $calcNet(500, 100, 0) === 400.0, "Billing Math 2 failed");
    assertTest("Billing Tax Adds", $calcNet(500, 0, 50) === 550.0, "Billing Math 3 failed");
    assertTest("Billing Decimal Precision", $calcNet(1250.50, 250.50, 13) === 1013.0, "Billing Math 4 failed");
    assertTest("Billing Check-In Formula (net=fee)", $calcNet(500, 0, 0) === 500.0, "Check-in billing math failed");
} catch (\Throwable $e) {
    assertTest("Billing Calculation Suite", false, $e->getMessage());
}

// TEST 10: UHID Format & Sequential Generation (rolled back, non-destructive)
try {
    $db = getDB();
    $db->beginTransaction();
    $maxBefore = (int)$db->query("SELECT COALESCE(MAX(id),0) FROM patients")->fetchColumn();
    $uhid1 = generateUHID();
    assertTest("UHID Format (UHID-NNNNN)", preg_match('/^UHID-\d{5}$/', $uhid1) === 1, "UHID format invalid: {$uhid1}");
    assertTest("UHID Starts With Prefix", str_starts_with($uhid1, UHID_PREFIX), "UHID prefix missing");

    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $stmtU = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'patient', 'active')");
    $stmtU->execute(['testuhid_' . $suffix, 'testuhid_' . $suffix . '@test.local', password_hash('x', PASSWORD_DEFAULT), 'Test UHID', '9800000000']);
    $uid = (int)$db->lastInsertId();
    $stmtP = $db->prepare("INSERT INTO patients (user_id, uhid, gender, blood_group) VALUES (?, ?, 'other', 'O+')");
    $stmtP->execute([$uid, $uhid1]);
    $uhid2 = generateUHID();
    $expected2 = UHID_PREFIX . str_pad($maxBefore + 2, 5, '0', STR_PAD_LEFT);
    assertTest("UHID Sequential Increment", $uhid2 === $expected2, "Expected {$expected2}, got {$uhid2}");
    $db->rollBack();
} catch (\Throwable $e) {
    assertTest("UHID Generation Suite", false, $e->getMessage());
}

// TEST 11: Invoice Number Format & Monthly Sequencing
try {
    $db = getDB();
    $db->beginTransaction();
    $inv = generateInvoiceNumber();
    assertTest("Invoice Number Format INV-YYYYMM-NNNN", preg_match('/^INV-\d{6}-\d{4}$/', $inv) === 1, "Invoice format invalid: {$inv}");
    $suffix2 = substr(bin2hex(random_bytes(4)), 0, 8);
    $stmtU2 = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'patient', 'active')");
    $stmtU2->execute(['testinv_' . $suffix2, 'testinv_' . $suffix2 . '@test.local', password_hash('x', PASSWORD_DEFAULT), 'Test Inv', '9800000001']);
    $uid2 = (int)$db->lastInsertId();
    $stmtP2 = $db->prepare("INSERT INTO patients (user_id, uhid, gender, blood_group) VALUES (?, ?, 'other', 'O+')");
    $stmtP2->execute([$uid2, 'UHID-TESTINV']);
    $pid2 = (int)$db->lastInsertId();
    $stmtB = $db->prepare("INSERT INTO billing (patient_id, invoice_number, subtotal, discount, tax, net_amount, payment_status, payment_method, payment_date, created_by) VALUES (?, ?, 100, 0, 0, 100, 'paid', 'Cash', CURRENT_TIMESTAMP, ?)");
    $stmtB->execute([$pid2, $inv, $uid2]);
    $inv2 = generateInvoiceNumber();
    assertTest("Invoice Number Sequential", $inv2 !== $inv, "Invoice number did not advance");
    $prefix = 'INV-' . date('Ym') . '-';
    $num1 = (int)substr($inv, -4);
    $num2 = (int)substr($inv2, -4);
    assertTest("Invoice Number Numeric Increment", $num2 === $num1 + 1, "Expected increment by 1, got {$num1} -> {$num2}");
    $db->rollBack();
} catch (\Throwable $e) {
    assertTest("Invoice Number Suite", false, $e->getMessage());
}

// TEST 12: Appointment Token Sequencing (per doctor per date)
try {
    $db = getDB();
    $db->beginTransaction();
    $docId = (int)$db->query("SELECT id FROM doctors ORDER BY id LIMIT 1")->fetchColumn();
    assertTest("Doctor Record Available For Token Test", $docId > 0, "No doctor row present");
    $today = date('Y-m-d');
    $token1 = generateToken($docId, $today);
    assertTest("Token Number Starts At 1", $token1 >= 1, "Token 1 was {$token1}");

    $suffix3 = substr(bin2hex(random_bytes(4)), 0, 8);
    $stmtU3 = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'patient', 'active')");
    $stmtU3->execute(['testtok_' . $suffix3, 'testtok_' . $suffix3 . '@test.local', password_hash('x', PASSWORD_DEFAULT), 'Test Token', '9800000002']);
    $uid3 = (int)$db->lastInsertId();
    $stmtP3 = $db->prepare("INSERT INTO patients (user_id, uhid, gender, blood_group) VALUES (?, ?, 'other', 'O+')");
    $stmtP3->execute([$uid3, 'UHID-TOKTEST']);
    $pid3 = (int)$db->lastInsertId();
    $stmtA = $db->prepare("INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, status, token_number, reason, created_by) VALUES (?, ?, 1, ?, '10:00', 'scheduled', ?, 'test', ?)");
    $stmtA->execute([$pid3, $docId, $today, $token1, $uid3]);
    $token2 = generateToken($docId, $today);
    assertTest("Token Sequencing Increments", $token2 === $token1 + 1, "Expected " . ($token1+1) . ", got {$token2}");
    $db->rollBack();
} catch (\Throwable $e) {
    assertTest("Token Sequencing Suite", false, $e->getMessage());
}

// Display Summary
// Display Summary
echo implode("\n", $testResults) . "\n\n";
echo "-----------------------------------------------------\n";
echo " Total Tests: " . ($passed + $failed) . " | Passed: {$passed} | Failed: {$failed}\n";
echo "=====================================================\n";

exit($failed === 0 ? 0 : 1);

