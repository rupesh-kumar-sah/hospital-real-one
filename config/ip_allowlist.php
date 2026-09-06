<?php

declare(strict_types=1);

function ipAllowlistValues(string $listName): array
{
    $environmentName = match ($listName) {
        'admin' => 'ADMIN_ALLOWED_IPS',
        'staff' => 'STAFF_ALLOWED_IPS',
        default => throw new InvalidArgumentException('Unknown IP allowlist.')
    };
    $raw = getenv($environmentName);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function trustedProxyAddresses(): array
{
    $raw = getenv('TRUSTED_PROXY_IPS') ?: '';
    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

function ipMatchesRule(string $ip, string $rule): bool
{
    if (filter_var($rule, FILTER_VALIDATE_IP) !== false) {
        return hash_equals($rule, $ip);
    }
    if (str_contains($rule, '/')) {
        [$network, $prefix] = explode('/', $rule, 2);
        $networkBinary = @inet_pton($network);
        $ipBinary = @inet_pton($ip);
        $prefix = (int)$prefix;
        if ($networkBinary === false || $ipBinary === false || strlen($networkBinary) !== strlen($ipBinary)) {
            return false;
        }
        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;
        if ($bytes > 0 && substr($networkBinary, 0, $bytes) !== substr($ipBinary, 0, $bytes)) {
            return false;
        }
        return $bits === 0 || (ord($networkBinary[$bytes]) & (0xFF << (8 - $bits))) ===
            (ord($ipBinary[$bytes]) & (0xFF << (8 - $bits)));
    }
    return false;
}

function requestClientIp(): string
{
    $remoteAddress = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    foreach (trustedProxyAddresses() as $proxyRule) {
        if (ipMatchesRule($remoteAddress, $proxyRule)) {
            $forwarded = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            $candidate = trim(explode(',', $forwarded)[0] ?? '');
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }
    }
    return $remoteAddress;
}

function checkIPAllowlist(string $listName): void
{
    $rules = ipAllowlistValues($listName);
    if ($rules === []) {
        if (getenv('APP_ENV') === 'production') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not Found';
            exit;
        }
        return;
    }
    $clientIp = requestClientIp();
    foreach ($rules as $rule) {
        if (ipMatchesRule($clientIp, $rule)) {
            return;
        }
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
    exit;
}
