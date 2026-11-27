-- v43: Docker som kontrolltyp (Docker API / Zabbix agent 2) under Tjänster och kontroller
ALTER TABLE server_checks MODIFY source ENUM('zabbix','snmp','tcp','url','pbs','docker') NOT NULL;
