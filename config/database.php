<?php
/**
 * Hospital Management System — Database Configuration
 * Supports MySQL (Production / Laptop Remote Tunnel) and SQLite3 (Local fallback).
 */

require_once __DIR__ . '/encryption.php';

// Helper function to load .env file if available
if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void {
        if (!file_exists($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) continue;
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Auto-load .env from root directory if present
loadEnv(__DIR__ . '/../.env');

define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite');
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'medicare_hms');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PATH', getenv('DB_PATH') ?: __DIR__ . '/../data/hms.db');

define('SCHEMA_PATH', __DIR__ . '/../sql/schema.sql');
define('SEED_PATH', __DIR__ . '/../sql/seed_data.sql');
define('MYSQL_SCHEMA_PATH', __DIR__ . '/../sql/mysql_schema.sql');

/**
 * Get PDO database connection (singleton pattern)
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $driver = strtolower(DB_DRIVER);
        
        if ($driver === 'mysql') {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME
                );
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                    PDO::ATTR_TIMEOUT => 5
                ];
                
                if (getenv('DB_SSL') === 'true') {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = getenv('DB_SSL_CA') ?: true;
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
                
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                return $pdo;
            } catch (PDOException $e) {
                error_log('MySQL Connection failed: ' . $e->getMessage() . '. Falling back to SQLite.');
                // Proceed to SQLite fallback below
            }
        }
        
        // SQLite Driver / Fallback Mode
        try {
            $dbDir = dirname(DB_PATH);
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0755, true);
            }
            
            $isNew = !file_exists(DB_PATH);
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            
            if ($isNew) {
                initializeDatabase($pdo);
            }
        } catch (PDOException $e) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['error' => 'Database Connection Failed: ' . $e->getMessage()]);
                exit;
            }
            die('Database Connection Error: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Initialize new database with schema and seed data
 */
function initializeDatabase(PDO $pdo): void {
    if (file_exists(SCHEMA_PATH)) {
        $schema = file_get_contents(SCHEMA_PATH);
        $pdo->exec($schema);
    }
    
    if (file_exists(SEED_PATH)) {
        $seed = file_get_contents(SEED_PATH);
        $pdo->exec($seed);
    }
}
