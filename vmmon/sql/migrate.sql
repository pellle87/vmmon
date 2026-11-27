-- Uppgradering från förra versionen (behåller användare, servrar och kontroller)
USE vmmon;
ALTER TABLE users ADD lang CHAR(2) DEFAULT 'sv';
ALTER TABLE servers ADD os ENUM('Linux','Windows') DEFAULT 'Linux', ADD os_info VARCHAR(200) NULL, ADD sort_order INT DEFAULT 0;
ALTER TABLE server_checks ADD ignore_disks VARCHAR(255) DEFAULT '';
DELETE FROM server_checks WHERE source='snmp'; -- SNMP-kontroller läggs om (nu en kontroll som hämtar allt)
DROP TABLE IF EXISTS metrics; DROP TABLE IF EXISTS alert_rules;
CREATE TABLE check_log(id BIGINT AUTO_INCREMENT PRIMARY KEY, check_id INT NOT NULL, ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status TINYINT NOT NULL, value FLOAT NULL, INDEX(check_id,ts), FOREIGN KEY(check_id) REFERENCES server_checks(id) ON DELETE CASCADE);
CREATE TABLE alert_rules(id INT AUTO_INCREMENT PRIMARY KEY, metric ENUM('cpu','mem','disk','check','offline') NOT NULL, threshold FLOAT DEFAULT 85, minutes INT DEFAULT 0, repeat_min INT DEFAULT 0, emails VARCHAR(500) NOT NULL);
CREATE TABLE alert_state(rule_id INT NOT NULL, skey VARCHAR(190) NOT NULL, server_id INT NOT NULL, since DATETIME DEFAULT CURRENT_TIMESTAMP, last_sent DATETIME NULL, info VARCHAR(255), PRIMARY KEY(rule_id,skey), FOREIGN KEY(rule_id) REFERENCES alert_rules(id) ON DELETE CASCADE, FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE);
CREATE TABLE alert_log(id BIGINT AUTO_INCREMENT PRIMARY KEY, ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, server_id INT NULL, server_name VARCHAR(80), metric VARCHAR(20), state ENUM('alarm','repeat','ok'), info VARCHAR(255), mailed TINYINT DEFAULT 0, mail_error VARCHAR(255), INDEX(ts));
CREATE TABLE settings(k VARCHAR(50) PRIMARY KEY, v TEXT);
