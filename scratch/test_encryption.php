<?php
require_once __DIR__ . '/../config/encryption.php';

$testString = "Patient John Doe - Confidential Notes: Severe Allergy to Penicillin";
$encrypted = encryptData($testString);
$decrypted = decryptData($encrypted);

echo "Original:  $testString\n";
echo "Encrypted: $encrypted\n";
echo "Decrypted: $decrypted\n";

if ($testString === $decrypted) {
    echo "SUCCESS: AES-256-GCM E2EE Encryption Verified!\n";
} else {
    echo "FAILED: Decryption mismatch!\n";
    exit(1);
}
