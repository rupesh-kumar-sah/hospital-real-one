<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/ip_allowlist.php';

function deviceCookieName(): string
{
    return 'hms_admin_device';
}

function deviceCookieValue(): string
{
    return (string)($_COOKIE[deviceCookieName()] ?? '');
}

function deviceFingerprint(string $secret): string
{
    return hash('sha256', $secret);
}

function findAdminDevice(int $userId, string $secret): ?array
{
    if ($userId < 1 || $secret === '') {
        return null;
    }
    $stmt = getDB()->prepare(
        'SELECT * FROM admin_devices WHERE user_id = ? AND token_hash = ? AND revoked_at IS NULL AND expires_at > CURRENT_TIMESTAMP LIMIT 1'
    );
    $stmt->execute([$userId, deviceFingerprint($secret)]);
    return $stmt->fetch() ?: null;
}

function issueAdminDevice(int $userId): void
{
    $secret = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO admin_devices (user_id, token_hash, label, ip_address, user_agent, last_used_at, expires_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)'
    );
    $expiresAt = date('Y-m-d H:i:s', time() + 180 * 86400);
    $stmt->execute([
        $userId,
        deviceFingerprint($secret),
        trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device')),
        requestClientIp(),
        trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
        $expiresAt
    ]);
    setcookie(deviceCookieName(), $secret, [
        'expires' => time() + 180 * 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

function touchAdminDevice(int $deviceId): void
{
    $stmt = getDB()->prepare('UPDATE admin_devices SET last_used_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$deviceId]);
}

function forgetAdminDeviceCookie(): void
{
    setcookie(deviceCookieName(), '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}
