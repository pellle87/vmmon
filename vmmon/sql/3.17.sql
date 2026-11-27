-- VM Monitor 1.75 – samlad migrering. Innehåller ALLA ändringar sedan 1.31 och kan köras flera gånger:
-- tabeller skapas med IF NOT EXISTS, kolumner läggs bara till om de saknas och standardgrupperna skapas bara
-- när tabellen device_groups skapas för första gången. Därför behövs bara den senaste N.NN.sql.
-- (Nya versioner: kopiera den här filen, lägg till de nya ändringarna på samma återkörbara sätt och döp om till nya versionen.)
-- Gäller även nyinstallation av en äldre databas från 1.31 och uppåt. Helt nya installationer använder schema.sql.
CREATE TABLE IF NOT EXISTS user_prefs(user_id INT NOT NULL, k VARCHAR(60) NOT NULL, v MEDIUMTEXT NULL, PRIMARY KEY(user_id,k));
CREATE TABLE IF NOT EXISTS dash_heads(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, w TINYINT NOT NULL DEFAULT 2, color VARCHAR(12) NOT NULL DEFAULT 'acc', text VARCHAR(120) NOT NULL DEFAULT 'Headline', FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE);
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE dash_heads ADD COLUMN tcol VARCHAR(12) NOT NULL DEFAULT ''auto''','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='dash_heads' AND column_name='tcol');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE dash_graphs ADD COLUMN cw TINYINT NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='dash_graphs' AND column_name='cw');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE dash_graphs ADD COLUMN ch TINYINT NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='dash_graphs' AND column_name='ch');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
CREATE TABLE IF NOT EXISTS alert_events(id BIGINT AUTO_INCREMENT PRIMARY KEY, server_id INT NULL, server_name VARCHAR(80), kind VARCHAR(20) NOT NULL, sev ENUM('warning','critical') NOT NULL, detail VARCHAR(500), started DATETIME NOT NULL, ended DATETIME NULL, dur INT NULL, notified TINYINT NOT NULL DEFAULT 0, reason VARCHAR(255), INDEX(started), INDEX(ended), INDEX(server_id,kind));
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE dash_graphs ADD COLUMN lay TINYINT NOT NULL DEFAULT 1','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='dash_graphs' AND column_name='lay');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE dash_heads ADD COLUMN lay TINYINT NOT NULL DEFAULT 1','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='dash_heads' AND column_name='lay');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN oidc_sub VARCHAR(255) NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='oidc_sub');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN pw_token CHAR(64) NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='pw_token');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE users ADD COLUMN pw_exp DATETIME NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='pw_exp');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @dg_new=(SELECT IF(COUNT(*)=0,1,0) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='device_groups');
CREATE TABLE IF NOT EXISTS device_groups(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, sort INT NOT NULL DEFAULT 0);
CREATE TABLE IF NOT EXISTS device_group_members(group_id INT NOT NULL, server_id INT NOT NULL, PRIMARY KEY(group_id,server_id), INDEX(server_id), FOREIGN KEY(group_id) REFERENCES device_groups(id) ON DELETE CASCADE, FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE);
INSERT INTO device_groups(name,sort) SELECT 'Production servers',1 FROM DUAL WHERE @dg_new=1;
INSERT INTO device_groups(name,sort) SELECT 'Lab servers',2 FROM DUAL WHERE @dg_new=1;
INSERT INTO device_groups(name,sort) SELECT 'Networking',3 FROM DUAL WHERE @dg_new=1;
INSERT INTO device_groups(name,sort) SELECT 'Services',4 FROM DUAL WHERE @dg_new=1;
INSERT IGNORE INTO device_group_members(group_id,server_id) SELECT (SELECT id FROM device_groups WHERE name='Networking' ORDER BY id LIMIT 1),id FROM servers WHERE @dg_new=1 AND nw_type IS NOT NULL AND COALESCE(nw_role,'') NOT IN ('nas','san','bmc');
INSERT IGNORE INTO device_group_members(group_id,server_id) SELECT (SELECT id FROM device_groups WHERE name='Services' ORDER BY id LIMIT 1),id FROM servers WHERE @dg_new=1 AND is_services=1;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE servers ADD COLUMN icon VARCHAR(80) NOT NULL DEFAULT ''''','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='servers' AND column_name='icon');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE device_groups ADD COLUMN icon VARCHAR(80) NOT NULL DEFAULT ''''','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='device_groups' AND column_name='icon');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE server_checks ADD COLUMN sort INT NOT NULL DEFAULT 0','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='server_checks' AND column_name='sort');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- v1.78: regler per enhetstyp (storage/BMC) – befintliga nw_-regler kopieras så att lagring och BMC fortsätter larma
ALTER TABLE alert_rules MODIFY metric ENUM('cpu','mem','disk','check','offline','net_err','net_drop','net_crc','sys_worker','sys_zbx','sys_recv','swap','hw_temp','hw_fan','net_down','net_util','hv_ping','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem','nw_down','nw_temp','nw_hw','nw_ping','check_ms','src_snmp','src_zabbix','src_pbs','smart','nw_health','zbx','ha_state','ha_supported','ha_updates','ha_unavail','st_ping','st_down','st_temp','st_psu','st_fan','st_hw','st_health','bmc_ping','bmc_down','bmc_temp','bmc_psu','bmc_fan','bmc_hw','bmc_health','nw_psu','nw_fan','smart_temp','log_event','users') NOT NULL;
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'st_ping',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_ping' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='st_ping' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'st_down',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_down' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='st_down' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'st_temp',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_temp' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='st_temp' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'st_psu',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_psu' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='st_psu' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'st_fan',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_fan' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='st_fan' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'st_hw',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_hw' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='st_hw' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'bmc_ping',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_ping' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='bmc_ping' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'bmc_down',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_down' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='bmc_down' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'bmc_temp',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_temp' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='bmc_temp' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'bmc_psu',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_psu' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='bmc_psu' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'bmc_fan',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_fan' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='bmc_fan' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'bmc_hw',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_hw' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='bmc_hw' AND q.targets<=>r.targets AND q.threshold<=>r.threshold AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
INSERT INTO alert_rules(metric,server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled) SELECT 'bmc_health',server_id,iface,kinds,s_247,s_days,s_from,s_to,threshold,minutes,repeat_min,emails,notify_email,notify_tg,notify_wh,notify_sms,notify_signal,targets,enabled FROM alert_rules r WHERE r.metric='nw_health' AND NOT EXISTS(SELECT 1 FROM alert_rules q WHERE q.metric='bmc_health' AND q.targets<=>r.targets AND q.minutes=r.minutes AND q.s_days=r.s_days AND q.notify_email=r.notify_email AND q.notify_tg=r.notify_tg);
UPDATE alert_rules SET metric='st_health' WHERE metric='nw_health';

-- v1.81: API-token
CREATE TABLE IF NOT EXISTS api_tokens(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, prefix VARCHAR(12) NOT NULL, token_hash CHAR(64) NOT NULL, enabled TINYINT NOT NULL DEFAULT 1, created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, last_used DATETIME NULL, last_ip VARCHAR(45) NULL, UNIQUE KEY(token_hash));

-- v1.84: ordning på värdraderna (SNMP/API/TrueNAS API) bland kontrollerna
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='servers' AND COLUMN_NAME='host_sort');
SET @q=IF(@c=0,'ALTER TABLE servers ADD COLUMN host_sort INT NOT NULL DEFAULT 0','SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @c=(SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='servers' AND COLUMN_NAME='tn_sort');
SET @q=IF(@c=0,'ALTER TABLE servers ADD COLUMN tn_sort INT NOT NULL DEFAULT 0','SELECT 1'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE net_if ADD COLUMN in_crc DOUBLE NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='net_if' AND column_name='in_crc');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE net_if ADD COLUMN crc_pm DOUBLE NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='net_if' AND column_name='crc_pm');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- 1.99: Zabbix-agent i active-läge
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE servers ADD COLUMN zbx_active TINYINT NOT NULL DEFAULT 0','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='servers' AND column_name='zbx_active');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE servers ADD COLUMN zbx_host VARCHAR(120) NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='servers' AND column_name='zbx_host');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
CREATE TABLE IF NOT EXISTS zbx_active(server_id INT NOT NULL, kh CHAR(32) NOT NULL, k VARCHAR(500) NOT NULL, val MEDIUMTEXT NULL, ts INT NULL, req INT NOT NULL DEFAULT 0, PRIMARY KEY(server_id,kh), FOREIGN KEY(server_id) REFERENCES servers(id) ON DELETE CASCADE) ENGINE=InnoDB;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE server_checks ADD COLUMN fail_n INT NOT NULL DEFAULT 0','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='server_checks' AND column_name='fail_n');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- 2.50: SNMP traps and syslog (log_events) and the alert rule type log_event
CREATE TABLE IF NOT EXISTS log_events(id BIGINT AUTO_INCREMENT, ts DATETIME NOT NULL, server_id INT NULL, src_ip VARCHAR(45) NOT NULL, kind ENUM('trap','syslog') NOT NULL, sev TINYINT NOT NULL DEFAULT 6, facility TINYINT NULL, host VARCHAR(120) NULL, prog VARCHAR(80) NULL, oid VARCHAR(190) NULL, msg TEXT NOT NULL, fw_act VARCHAR(10) NULL, fw_dir VARCHAR(4) NULL, fw_proto VARCHAR(12) NULL, fw_src VARCHAR(45) NULL, fw_dst VARCHAR(45) NULL, fw_sport INT NULL, fw_dport INT NULL, fw_in VARCHAR(40) NULL, fw_out VARCHAR(40) NULL, fw_rule VARCHAR(40) NULL, PRIMARY KEY(id,ts), INDEX(server_id,ts), INDEX(fw_src), INDEX(fw_dst)) ENGINE=InnoDB PARTITION BY RANGE COLUMNS(ts) (PARTITION p0 VALUES LESS THAN ('2000-01-01 00:00:00'), PARTITION pmax VALUES LESS THAN (MAXVALUE));
ALTER TABLE alert_rules MODIFY metric ENUM('cpu','mem','disk','check','offline','net_err','net_drop','net_crc','sys_worker','sys_zbx','sys_recv','swap','hw_temp','hw_fan','net_down','net_util','hv_ping','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem','nw_down','nw_temp','nw_hw','nw_ping','check_ms','src_snmp','src_zabbix','src_pbs','smart','nw_health','zbx','ha_state','ha_supported','ha_updates','ha_unavail','st_ping','st_down','st_temp','st_psu','st_fan','st_hw','st_health','bmc_ping','bmc_down','bmc_temp','bmc_psu','bmc_fan','bmc_hw','bmc_health','nw_psu','nw_fan','smart_temp','log_event','users') NOT NULL;
-- 2.51: structured firewall fields in log_events (pfSense/OPNsense filterlog, netfilter, Meraki, MikroTik)
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE log_events ADD COLUMN fw_act VARCHAR(10) NULL, ADD COLUMN fw_dir VARCHAR(4) NULL, ADD COLUMN fw_proto VARCHAR(12) NULL, ADD COLUMN fw_src VARCHAR(45) NULL, ADD COLUMN fw_dst VARCHAR(45) NULL, ADD COLUMN fw_sport INT NULL, ADD COLUMN fw_dport INT NULL, ADD COLUMN fw_in VARCHAR(40) NULL, ADD COLUMN fw_out VARCHAR(40) NULL, ADD COLUMN fw_rule VARCHAR(40) NULL, ADD INDEX(fw_src), ADD INDEX(fw_dst)','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='log_events' AND column_name='fw_act');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
-- 2.52: fewer indexes and one partition per day (retention by dropping whole days). Only done when the table is not partitioned yet.
SET @s=(SELECT IF(COUNT(*)>0,'ALTER TABLE log_events DROP INDEX ts','DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='log_events' AND index_name='ts');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)>0,'ALTER TABLE log_events DROP INDEX kind','DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='log_events' AND index_name='kind');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)>0,'ALTER TABLE log_events DROP INDEX src_ip','DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='log_events' AND index_name='src_ip');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @p=(SELECT COUNT(*) FROM information_schema.partitions WHERE table_schema=DATABASE() AND table_name='log_events' AND partition_name IS NOT NULL);
SET @s=IF(@p=0,CONCAT('ALTER TABLE log_events DROP PRIMARY KEY, ADD PRIMARY KEY(id,ts) PARTITION BY RANGE COLUMNS(ts) (PARTITION p0 VALUES LESS THAN (''',DATE_FORMAT(CURDATE(),'%Y-%m-%d'),' 00:00:00''), PARTITION pmax VALUES LESS THAN (MAXVALUE))'),'DO 0');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
-- Dygnssammanställning av brandväggstrafik (v2.56)
CREATE TABLE IF NOT EXISTS log_fw_daily(day DATE NOT NULL, server_id INT NOT NULL DEFAULT 0, act VARCHAR(10) NOT NULL, dir VARCHAR(4) NOT NULL DEFAULT '', proto VARCHAR(12) NOT NULL DEFAULT '', src VARCHAR(45) NOT NULL DEFAULT '', dst VARCHAR(45) NOT NULL DEFAULT '', dport INT NOT NULL DEFAULT 0, rule VARCHAR(40) NOT NULL DEFAULT '', cnt BIGINT NOT NULL DEFAULT 0, PRIMARY KEY(day,server_id,act,dir,proto,src,dst,dport,rule), INDEX(server_id,day)) ENGINE=InnoDB;
-- Grupper för larmregler (v2.62)
CREATE TABLE IF NOT EXISTS alert_rule_groups(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, sort_order INT NOT NULL DEFAULT 0) ENGINE=InnoDB;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE alert_rules ADD COLUMN group_id INT NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='alert_rules' AND column_name='group_id');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE alert_rules ADD COLUMN name VARCHAR(100) NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='alert_rules' AND column_name='name');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;

-- MIB-filer (v2.73)
CREATE TABLE IF NOT EXISTS mib_files(id INT AUTO_INCREMENT PRIMARY KEY, module VARCHAR(100) NOT NULL, fname VARCHAR(200) NOT NULL, n INT NOT NULL DEFAULT 0, defs MEDIUMTEXT NOT NULL, uploaded DATETIME NOT NULL, KEY(module)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS mib_names(oid VARCHAR(255) NOT NULL PRIMARY KEY, name VARCHAR(120) NOT NULL, module VARCHAR(100) NOT NULL, kind CHAR(1) NOT NULL DEFAULT 'o', enums TEXT NULL, KEY(name)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS alert_templates(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, subject VARCHAR(255) NOT NULL DEFAULT '', body MEDIUMTEXT NULL, sms TEXT NULL, ok_subject VARCHAR(255) NULL, ok_body MEDIUMTEXT NULL, created TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE alert_rules ADD COLUMN template_id INT NULL','DO 0') FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='alert_rules' AND column_name='template_id');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Standard','%emoji [%status] %hostname: %rule','Server: %hostname (%ip)\nRule #%ruleid: %rule\nProblem started: %started\nOngoing for: %duration\n%info','[%status] %hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Standard');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Short / SMS','%emoji [%status] %hostname: %rule','%infoline','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Short / SMS');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Device down (ping / SNMP / host)','%emoji [%status] %hostname: %rule','🖥️ Device: %hostname (%ip)\nType: %type\nProblem started: %started\nOngoing for: %duration\n%info','%hostname (%ip) is down. %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Device down (ping / SNMP / host)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Resource usage (CPU, memory, disk, swap)','%emoji [%status] %hostname: %rule','📈 %hostname (%ip)\nRule #%ruleid: %rule\nValue: %value (limit %threshold)\nProblem started: %started\nOngoing for: %duration\n%info','%hostname: %rule %value (limit %threshold)','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Resource usage (CPU, memory, disk, swap)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Logged-on users','%emoji [%status] %hostname: %rule','👤 %hostname (%ip)\n%info\nLimit: %threshold\nProblem started: %started\nOngoing for: %duration','%hostname: %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Logged-on users');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Service check (TCP / URL / certificate)','%emoji [%status] %hostname: %rule','🌐 %hostname (%ip)\nRule #%ruleid: %rule\n%info\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Service check (TCP / URL / certificate)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Zabbix agent check','%emoji [%status] %hostname: %rule','📡 %hostname (%ip)\nRule #%ruleid: %rule\n%info\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Zabbix agent check');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Hardware and sensors (temperature, fan, PSU, disk health)','%emoji [%status] %hostname: %rule','🌡️ %hostname (%ip)\nRule #%ruleid: %rule\n%info\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Hardware and sensors (temperature, fan, PSU, disk health)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Network interface (link, errors, drops, utilisation)','%emoji [%status] %hostname: %rule','🔌 %hostname (%ip)\nRule #%ruleid: %rule\n%info\nLimit: %threshold\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Network interface (link, errors, drops, utilisation)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Virtualization (hosts and VMs)','%emoji [%status] %hostname: %rule','🧩 %hostname (%ip)\nRule #%ruleid: %rule\n%info\nLimit: %threshold\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Virtualization (hosts and VMs)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Storage (NAS / SAN)','%emoji [%status] %hostname: %rule','💾 %hostname (%ip)\nRule #%ruleid: %rule\n%info\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Storage (NAS / SAN)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'BMC / IPMI','%emoji [%status] %hostname: %rule','🔧 %hostname (%ip)\nRule #%ruleid: %rule\n%info\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='BMC / IPMI');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Home Assistant','%emoji [%status] %hostname: %rule','🏠 %hostname (%ip)\nRule #%ruleid: %rule\n%info\nProblem started: %started\nOngoing for: %duration','%hostname: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Home Assistant');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'VM Monitor system (worker, listeners)','%emoji [%status] %hostname: %rule','⚙️ %hostname\nRule #%ruleid: %rule\n%info\nProblem started: %started\nOngoing for: %duration','VM Monitor: %rule %infoline','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='VM Monitor system (worker, listeners)');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Trap / syslog event','%emoji [%status] %hostname: %rule','📨 %hostname (%ip)\nRule #%ruleid: %rule\nProblem started: %started\nEvents: %events in %window min\nSource: %prog\nLatest: %lastmsg','%hostname: %rule %events events. %lastmsg','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Trap / syslog event');
INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT 'Brute force (source IP)','%emoji [%status] %hostname: %rule','🛡️ Server: %hostname (%ip)\nRule #%ruleid: %rule\nProblem started: %started\nOngoing for: %ongoingfor s\nWorst source: %srcip (%events events in %window min)\nAll sources:\n%srclist','%hostname: %srcip %events events','%emoji [%status] %hostname: %rule (%duration)','Alert cleared for %hostname (%rule).\nProblem started: %started\nRecovered: %now\nTime with problem: %duration' FROM DUAL WHERE NOT EXISTS(SELECT 1 FROM settings WHERE k='tpl_seeded2') AND NOT EXISTS(SELECT 1 FROM alert_templates WHERE name='Brute force (source IP)');
INSERT IGNORE INTO settings(k,v) VALUES('tpl_seeded2','1');
SET @s=(SELECT IF(COUNT(*)=0,'ALTER TABLE log_events ADD INDEX idx_kind_ts(kind,ts)','DO 0') FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='log_events' AND index_name='idx_kind_ts');
PREPARE st FROM @s;
EXECUTE st;
DEALLOCATE PREPARE st;
