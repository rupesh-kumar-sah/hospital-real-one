<?php
/**
 * Lightweight in-request cache for static reference data
 * (departments, pricing catalogs, lab catalog, payment methods).
 *
 * Data like department lists and pricing catalogs changes rarely and is read
 * on nearly every page. This helper caches results for $ttl seconds using
 * APCu when available, falling back to a static per-request array.
 */
function refcache_get(string $key, int $ttl, callable $producer)
{
    static $local = []; // per-request fallback cache

    // 1. APCu shared-memory cache (fastest, survives across requests)
    if (function_exists('apcu_enabled') && apcu_enabled()) {
        $hit = apcu_fetch('refcache_' . $key, $ok);
        if ($ok) return $hit;
        $val = $producer();
        apcu_store('refcache_' . $key, $val, $ttl);
        return $val;
    }

    // 2. Per-request static cache (still eliminates duplicate queries on one page)
    if (array_key_exists($key, $local)) return $local[$key];
    $val = $producer();
    $local[$key] = $val;
    return $val;
}

/** All active departments (cached 5 min). */
function cached_departments(): array
{
    return refcache_get('departments', 300, function () {
        $stmt = getDB()->prepare("SELECT * FROM departments WHERE status = 'active' ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    });
}

/** Active service pricing catalog grouped by category (cached 5 min). */
function cached_service_pricing(): array
{
    return refcache_get('service_pricing', 300, function () {
        $stmt = getDB()->prepare("SELECT * FROM service_pricing WHERE status = 'active' ORDER BY category ASC, service_name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    });
}

/** Active lab test catalog (cached 5 min). */
function cached_lab_catalog(): array
{
    return refcache_get('lab_catalog', 300, function () {
        $stmt = getDB()->prepare("SELECT * FROM lab_test_catalog WHERE status = 'active' ORDER BY category ASC, test_name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    });
}

/** Active payment methods (cached 5 min). */
function cached_payment_methods(): array
{
    return refcache_get('payment_methods', 300, function () {
        $stmt = getDB()->prepare("SELECT * FROM payment_methods WHERE status = 'active' ORDER BY id ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    });
}
