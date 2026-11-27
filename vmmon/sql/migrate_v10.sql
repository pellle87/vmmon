USE vmmon;
ALTER TABLE servers ADD COLUMN enabled TINYINT NOT NULL DEFAULT 1;
ALTER TABLE server_checks ADD COLUMN enabled TINYINT NOT NULL DEFAULT 1, MODIFY param VARCHAR(1000) NOT NULL DEFAULT '';
ALTER TABLE alert_rules MODIFY emails TEXT NULL, ADD COLUMN notify_email TINYINT NOT NULL DEFAULT 1, ADD COLUMN notify_tg TINYINT NOT NULL DEFAULT 0;
-- befintliga regelmottagare blir larmkontakter (Inställningar)
INSERT IGNORE INTO settings(k,v) SELECT 'alarm_contacts', GROUP_CONCAT(emails SEPARATOR ', ') FROM alert_rules HAVING COUNT(*)>0;
