USE vmmon;
ALTER TABLE users ADD COLUMN disabled TINYINT NOT NULL DEFAULT 0;
ALTER TABLE alert_rules ADD COLUMN targets TEXT NULL;
UPDATE alert_rules SET targets=CONCAT('[{"s":',server_id,'}]') WHERE server_id IS NOT NULL AND targets IS NULL;
