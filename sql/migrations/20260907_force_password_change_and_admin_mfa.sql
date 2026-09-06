-- Security migration for existing SQLite databases.
-- Run once against the active database. MySQL/PostgreSQL equivalents are
-- included below as comments because their ALTER TABLE syntax differs.

ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN mfa_enabled INTEGER NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN mfa_secret TEXT DEFAULT NULL;
CREATE TABLE IF NOT EXISTS admin_devices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
ALTER TABLE users ADD COLUMN mfa_backup_codes TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN mfa_enrolled_at DATETIME DEFAULT NULL;

-- MySQL 8 (run these instead of the SQLite statements above):
-- ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE users ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE users ADD COLUMN mfa_secret TEXT NULL;
-- ALTER TABLE users ADD COLUMN mfa_backup_codes TEXT NULL;
-- ALTER TABLE users ADD COLUMN mfa_enrolled_at DATETIME NULL;

-- PostgreSQL 12+ (run these instead of the SQLite statements above):
-- ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT FALSE;
-- ALTER TABLE users ADD COLUMN mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE;
-- ALTER TABLE users ADD COLUMN mfa_secret TEXT NULL;
-- ALTER TABLE users ADD COLUMN mfa_backup_codes TEXT NULL;
-- ALTER TABLE users ADD COLUMN mfa_enrolled_at TIMESTAMP NULL;
