<?php
/**
 * Hospital Management System — Global Error & Exception Handling
 *
 * Guarantees that raw PHP stack traces, SQL errors, and file paths are never
 * displayed to end users. All errors are logged server-side and converted into
 * a generic, user-safe response (JSON for the API, HTML for pages).
 */

if (!function_exists('hms_log_error')) {
    function hms_log_error(string $message): void
    {
        error_log('[MediCare HMS] ' . $message);
    }

    function hms_is_api_request(): bool
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        return str_starts_with($uri, '/api/');
    }

    function hms_error_page(string $status = '500 Internal Server Error'): void
    {
        if (!headers_sent()) {
            http_response_code((int)substr($status, 0, 3));
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Service Unavailable</title>'
            . '<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f8fafc;color:#0f172a;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
            . '.box{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:32px;max-width:480px;text-align:center}'
            . 'h1{font-size:1.25rem;margin:0 0 8px} p{color:#475569;font-size:0.9rem;margin:0}</style></head>'
            . '<body><div class="box"><h1>Something went wrong</h1>'
            . '<p>We could not complete your request. Please try again. If the problem persists, contact the administrator.</p>'
            . '</div></body></html>';
    }

    function hms_exception_handler(Throwable $e): void
    {
        hms_log_error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in '
            . ($e->getFile() ?? '?') . ':' . ($e->getLine() ?? '?'));
        if (hms_is_api_request() && !headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error.',
                'timestamp' => date('c')
            ]);
            exit;
        }
        hms_error_page();
        exit;
    }

    // Convert PHP warnings/notices/deprecations into logged messages (never displayed).
    function hms_error_handler(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false; // Respect @-suppression.
        }
        hms_log_error(sprintf('PHP %d: %s in %s:%d', $severity, $message, $file, $line));
        return true;
    }

    set_exception_handler('hms_exception_handler');
    set_error_handler('hms_error_handler');
}

// Never render raw stack traces to browsers.
if (getenv('APP_ENV') === 'production' || (getenv('APP_ENV') ?: '') !== 'development') {
    @ini_set('display_errors', '0');
} else {
    @ini_set('display_errors', '1');
}
@ini_set('log_errors', '1');
@ini_set('log_errors_max_len', '0');