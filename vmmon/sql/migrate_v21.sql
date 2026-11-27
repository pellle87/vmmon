-- Signal som notifieringstyp på larmregler
ALTER TABLE alert_rules ADD COLUMN notify_signal TINYINT NOT NULL DEFAULT 0;
