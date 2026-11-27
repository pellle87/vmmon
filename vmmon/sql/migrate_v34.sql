-- v34: Proxmox Backup Server (PBS API-kontroller)
ALTER TABLE servers ADD COLUMN pbs_conn TEXT NULL;
ALTER TABLE server_checks MODIFY source ENUM('zabbix','snmp','tcp','url','pbs') NOT NULL;
