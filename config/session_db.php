<?php
/**
 * MediCare HMS — Database-backed session handler.
 *
 * Used when SESSION_DRIVER=db (required for serverless / multi-instance
 * deployments where PHP file sessions don't persist between requests).
 * Works with SQLite, MySQL, and PostgreSQL via PDO.
 */

declare(strict_types=1);

class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $db;
    private string $table = 'app_sessions';

    /** Accepts an optional PDO; defaults to the application singleton. */
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? getDB();
    }

    public function open(string $path, string $name): bool
    {
        return true; // connection is managed by the singleton
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->db->prepare("SELECT data FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row === false ? '' : (string)$row[0];
    }

    public function write(string $id, string $data): bool
    {
        try {
            $this->db->beginTransaction();
            $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$id]);
            $this->db->prepare("INSERT INTO {$this->table} (id, data, last_accessed) VALUES (?, ?, ?)")
                ->execute([$id, $data, time()]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('DB session write failed: ' . $e->getMessage());
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$id]);
            return true;
        } catch (Throwable $e) {
            error_log('DB session destroy failed: ' . $e->getMessage());
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE last_accessed < ?");
            $stmt->execute([time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('DB session gc failed: ' . $e->getMessage());
            return false;
        }
    }
}
