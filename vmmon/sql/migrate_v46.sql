-- v46: Home Assistant API som kontrolltyp (source 'ha') och större last_data (alla entiteter)
ALTER TABLE server_checks MODIFY source ENUM('zabbix','snmp','tcp','url','pbs','docker','ha') NOT NULL;
ALTER TABLE server_checks MODIFY last_data MEDIUMTEXT NULL;
