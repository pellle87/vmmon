-- v36: nya larmtyper (SNMP/Zabbix/PBS svarar inte) och dolda sensorer
ALTER TABLE alert_rules MODIFY metric ENUM('cpu','mem','disk','check','offline','net_err','net_drop','net_down','net_util','hv_ping','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem','nw_down','nw_temp','nw_hw','nw_ping','check_ms','src_snmp','src_zabbix','src_pbs') NOT NULL;

-- dolda sensorer (fläkt/nätdel) och dolda noder
ALTER TABLE servers ADD COLUMN nw_hidden TEXT NULL;
