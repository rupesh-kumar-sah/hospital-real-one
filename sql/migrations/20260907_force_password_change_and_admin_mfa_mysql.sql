-- MySQL 8 migration for existing installations.
ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN mfa_secret TEXT NULL;
ALTER TABLE users ADD COLUMN mfa_backup_codes TEXT NULL;
ALTER TABLE users ADD COLUMN mfa_enrolled_at DATETIME NULL;
