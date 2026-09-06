-- PostgreSQL migration for existing installations.
ALTER TABLE users ADD COLUMN must_change_password BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE users ADD COLUMN mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE users ADD COLUMN mfa_secret TEXT NULL;
ALTER TABLE users ADD COLUMN mfa_backup_codes TEXT NULL;
ALTER TABLE users ADD COLUMN mfa_enrolled_at TIMESTAMP NULL;
