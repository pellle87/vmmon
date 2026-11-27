-- v40: teman per användare, larmljud (globalt + per användare), larmschema (dagar/tider/24-7) och larmtyp "Zabbix-kontroll"
ALTER TABLE users ADD COLUMN theme VARCHAR(60) NULL, ADD COLUMN sound VARCHAR(20) NULL;
ALTER TABLE alert_rules MODIFY metric ENUM('cpu','mem','disk','check','offline','net_err','net_drop','net_down','net_util','hv_ping','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem','nw_down','nw_temp','nw_hw','nw_ping','check_ms','src_snmp','src_zabbix','src_pbs','smart','nw_health','zbx') NOT NULL;
ALTER TABLE alert_rules ADD COLUMN kinds TEXT NULL, ADD COLUMN s_247 TINYINT NOT NULL DEFAULT 1, ADD COLUMN s_days VARCHAR(7) NOT NULL DEFAULT '1234567', ADD COLUMN s_from TIME NOT NULL DEFAULT '00:00:00', ADD COLUMN s_to TIME NOT NULL DEFAULT '23:59:00';
