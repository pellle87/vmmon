-- v41: IP-adresser per nätverksgränssnitt (SNMP)
ALTER TABLE net_if ADD COLUMN ips VARCHAR(255) NULL;
