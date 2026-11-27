-- v30: tvåstegsverifiering (TOTP)
ALTER TABLE users ADD COLUMN totp_secret TEXT NULL, ADD COLUMN totp_enabled TINYINT NOT NULL DEFAULT 0, ADD COLUMN totp_recovery TEXT NULL, ADD COLUMN totp_last BIGINT NULL;
