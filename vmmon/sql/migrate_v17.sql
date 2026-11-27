USE vmmon;
ALTER TABLE alert_rules MODIFY metric ENUM('cpu','mem','disk','check','offline','net_err','net_drop','net_down','net_util','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem','nw_down','nw_temp','nw_hw','nw_ping') NOT NULL;
ALTER TABLE alert_rules ADD COLUMN notify_sms TINYINT NOT NULL DEFAULT 0;
