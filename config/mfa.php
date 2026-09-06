<?php
/**
 * Dependency-free RFC 6238 TOTP helpers.
 */

require_once __DIR__ . '/encryption.php';

function base32Encode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $len = strlen($data); $i < $len; $i++) {
        $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    for ($i = 0, $len = strlen($bits); $i < $len; $i += 5) {
        $chunk = substr($bits, $i, 5);
        $chunk = str_pad($chunk, 5, '0');
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function base32Decode(string $encoded): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded));
    $bits = '';
    for ($i = 0, $len = strlen($encoded); $i < $len; $i++) {
        $value = strpos($alphabet, $encoded[$i]);
        if ($value === false) {
            continue;
        }
        $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }
    $result = '';
    for ($i = 0, $len = strlen($bits) - 7; $i < $len; $i += 8) {
        $result .= chr(bindec(substr($bits, $i, 8)));
    }
    return $result;
}

function generateTotpSecret(): string {
    return base32Encode(random_bytes(20));
}

function totpCode(string $secret, ?int $timestamp = null): string {
    $timestamp = $timestamp ?? time();
    $counter = intdiv($timestamp, 30);
    $counterBytes = pack('N*', 0, $counter);
    $hash = hash_hmac('sha1', $counterBytes, base32Decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $binary = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode(string $secret, string $code, int $window = 1): bool {
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || $secret === '') {
        return false;
    }
    $time = time();
    for ($offset = -$window; $offset <= $window; $offset++) {
        if (hash_equals(totpCode($secret, $time + ($offset * 30)), $code)) {
            return true;
        }
    }
    return false;
}

function totpUri(string $secret, string $account, string $issuer = 'MediCare HMS'): string {
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

function generateBackupCodes(int $count = 8): array {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(5)));
    }
    return $codes;
}

function hashBackupCodes(array $codes): string {
    return json_encode(array_map(static fn(string $code): string => password_hash($code, PASSWORD_DEFAULT), $codes), JSON_THROW_ON_ERROR);
}

function consumeBackupCode(string $storedHashes, string $code): ?string {
    $hashes = json_decode($storedHashes, true);
    if (!is_array($hashes)) {
        return null;
    }
    foreach ($hashes as $index => $hash) {
        if (is_string($hash) && password_verify(strtoupper(trim($code)), $hash)) {
            unset($hashes[$index]);
            return json_encode(array_values($hashes), JSON_THROW_ON_ERROR);
        }
    }
    return null;
}
