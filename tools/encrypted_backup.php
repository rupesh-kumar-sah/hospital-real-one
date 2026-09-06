<?php
/**
 * MediCare HMS encrypted SQLite backup utility.
 *
 * Usage:
 *   php tools/encrypted_backup.php backup
 *   php tools/encrypted_backup.php restore path\backup.mchms output\restore.db
 *
 * Required environment variable:
 *   BACKUP_ENCRYPTION_KEY - a random 64-character hexadecimal value
 */

declare(strict_types=1);

const BACKUP_MAGIC = "MCHMS-BACKUP-1\0";
const BACKUP_CIPHER = 'aes-256-gcm';

function loadDotEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (getenv($name) === false) {
            putenv("{$name}={$value}");
        }
    }
}

function fail(string $message): never
{
    fwrite(STDERR, "Backup failed: {$message}\n");
    exit(1);
}

function encryptionKey(): string
{
    $value = getenv('BACKUP_ENCRYPTION_KEY') ?: '';
    if (!preg_match('/^[a-fA-F0-9]{64}$/', $value)) {
        fail('BACKUP_ENCRYPTION_KEY must be exactly 64 hexadecimal characters.');
    }
    return hex2bin($value);
}

function databasePath(): string
{
    $configured = getenv('DB_PATH') ?: 'E:\\HM DATA\\hms.db';
    if (!is_file($configured)) {
        fail("SQLite database was not found at {$configured}.");
    }
    return $configured;
}

function backupDirectory(): string
{
    $directory = getenv('BACKUP_DIRECTORY') ?: 'E:\\HM DATA\\cloud-backups';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        fail("Unable to create backup directory {$directory}.");
    }
    return rtrim($directory, "\\/");
}

function createSnapshot(string $databasePath, string $snapshotPath): void
{
    $source = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $source->exec('PRAGMA busy_timeout = 10000');
    $source->exec('PRAGMA wal_checkpoint(TRUNCATE)');
    $quoted = $source->quote($snapshotPath);
    $source->exec("VACUUM INTO {$quoted}");
    unset($source);
}

function encryptFile(string $inputPath, string $outputPath, string $key): void
{
    $plaintext = file_get_contents($inputPath);
    if ($plaintext === false) {
        fail("Unable to read temporary snapshot {$inputPath}.");
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plaintext,
        BACKUP_CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        BACKUP_MAGIC
    );
    if ($ciphertext === false || strlen($tag) !== 16) {
        fail('OpenSSL AES-256-GCM encryption failed.');
    }

    $payload = BACKUP_MAGIC . $iv . $tag . $ciphertext;
    if (file_put_contents($outputPath, $payload, LOCK_EX) === false) {
        fail("Unable to write encrypted backup {$outputPath}.");
    }
}

function decryptFile(string $inputPath, string $outputPath, string $key): void
{
    $payload = file_get_contents($inputPath);
    $magicLength = strlen(BACKUP_MAGIC);
    if ($payload === false || strlen($payload) <= $magicLength + 28) {
        fail('Backup file is empty or incomplete.');
    }
    if (substr($payload, 0, $magicLength) !== BACKUP_MAGIC) {
        fail('Backup format is not recognized.');
    }

    $iv = substr($payload, $magicLength, 12);
    $tag = substr($payload, $magicLength + 12, 16);
    $ciphertext = substr($payload, $magicLength + 28);
    $plaintext = openssl_decrypt(
        $ciphertext,
        BACKUP_CIPHER,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        BACKUP_MAGIC
    );
    if ($plaintext === false) {
        fail('Backup authentication failed. Check the encryption key or file integrity.');
    }
    if (file_put_contents($outputPath, $plaintext, LOCK_EX) === false) {
        fail("Unable to write restored database {$outputPath}.");
    }
}

function verifyDatabase(string $path): void
{
    $db = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $result = $db->query('PRAGMA integrity_check')->fetchColumn();
    if ($result !== 'ok') {
        fail("SQLite integrity check failed: {$result}");
    }
}

loadDotEnv(__DIR__ . '/../.env');
$command = $argv[1] ?? 'backup';
$key = encryptionKey();

try {
    if ($command === 'backup') {
        $directory = backupDirectory();
        $timestamp = gmdate('Ymd-His');
        $snapshot = $directory . DIRECTORY_SEPARATOR . ".snapshot-{$timestamp}.db";
        $output = $directory . DIRECTORY_SEPARATOR . "hms-{$timestamp}.mchms";

        try {
            createSnapshot(databasePath(), $snapshot);
            verifyDatabase($snapshot);
            encryptFile($snapshot, $output, $key);
        } finally {
            if (is_file($snapshot)) {
                unlink($snapshot);
            }
        }

        $hash = hash_file('sha256', $output);
        if ($hash === false) {
            fail('Unable to hash the encrypted backup.');
        }
        if (file_put_contents($output . '.sha256', "{$hash}  " . basename($output) . PHP_EOL, LOCK_EX) === false) {
            fail('Unable to write the backup checksum manifest.');
        }
        echo "Encrypted backup created: " . basename($output) . PHP_EOL;
        echo "SHA-256 manifest created: " . basename($output) . ".sha256" . PHP_EOL;
        exit(0);
    }

    if ($command === 'restore') {
        $input = $argv[2] ?? '';
        $output = $argv[3] ?? '';
        if ($input === '' || $output === '') {
            fail('Restore requires an encrypted backup path and an output database path.');
        }
        if (is_file($output)) {
            fail("Refusing to overwrite existing database {$output}.");
        }
        decryptFile($input, $output, $key);
        verifyDatabase($output);
        echo "Backup restored and verified: {$output}" . PHP_EOL;
        exit(0);
    }

    fail('Use "backup" or "restore".');
} catch (Throwable $exception) {
    fail($exception->getMessage());
}
