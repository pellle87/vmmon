USE vmmon;
ALTER TABLE servers ADD COLUMN nw_type VARCHAR(12) NULL, ADD COLUMN nw_role VARCHAR(10) NULL, ADD COLUMN nw_conn TEXT NULL, ADD COLUMN nw_data MEDIUMTEXT NULL, ADD COLUMN nw_poll DATETIME NULL, ADD COLUMN nw_status ENUM('up','down') NULL, ADD COLUMN nw_error VARCHAR(255) NULL, ADD COLUMN dash_hidden TINYINT NOT NULL DEFAULT 0;
ALTER TABLE alert_rules MODIFY metric ENUM('cpu','mem','disk','check','offline','net_err','net_drop','net_down','net_util','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem','nw_down','nw_temp','nw_hw') NOT NULL;
