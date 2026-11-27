USE vmmon;
ALTER TABLE servers ADD COLUMN hv_type VARCHAR(10) NULL, ADD COLUMN hv_conn TEXT NULL, ADD COLUMN hv_data MEDIUMTEXT NULL, ADD COLUMN hv_poll DATETIME NULL, ADD COLUMN hv_status ENUM('up','down') NULL, ADD COLUMN hv_error VARCHAR(255) NULL;
ALTER TABLE alert_rules MODIFY metric ENUM('cpu','mem','disk','check','offline','net_err','net_drop','net_down','net_util','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem') NOT NULL;
