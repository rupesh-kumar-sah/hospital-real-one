<?php
/**
 * REST API — POST /api/v1/auth/logout
 * Revokes refresh token in database and clears HttpOnly cookie.
 */

require_once __DIR__ . '/../../../includes/api_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method Not Allowed. POST required.', 405);
}

$refreshToken = $_COOKIE['hms_refresh_token'] ?? '';
if (empty($refreshToken)) {
    $body = getJsonBody();
    $refreshToken = $body['refresh_token'] ?? '';
}

if (!empty($refreshToken)) {
    revokeRefreshToken($refreshToken);
}

clearRefreshTokenCookie();

jsonSuccess(null, 'Logged out successfully');
