#!/bin/bash
# vmmon-smart.sh – SMART-hälsa till VM Monitor via SNMP (snmpd "extend")
# Skriver en rad per disk:  enhet|modell|serienummer|hälsa|temp|drifttimmar|reallocated|pending
#
# Installera (Debian/Ubuntu):
#   apt install smartmontools snmpd
#   cp vmmon-smart.sh /usr/local/bin/ && chmod +x /usr/local/bin/vmmon-smart.sh
#   /etc/snmp/snmpd.conf:      extend smart /usr/bin/sudo /usr/local/bin/vmmon-smart.sh
#   /etc/sudoers.d/vmmon-smart: Debian-snmp ALL=(root) NOPASSWD: /usr/local/bin/vmmon-smart.sh
#   systemctl restart snmpd
# (smartctl kräver root. Kör snmpd som root eller använd sudo enligt ovan.)
SC=$(command -v smartctl) || exit 0
for d in $($SC --scan | awk '{print $1}'); do
  o=$($SC -i -H -A "$d" 2>/dev/null)
  model=$(echo "$o" | awk -F: '/^(Device Model|Model Number|Product|Model Family)/{sub(/^ +/,"",$2);print $2;exit}')
  serial=$(echo "$o" | awk -F: '/^Serial [Nn]umber/{sub(/^ +/,"",$2);print $2;exit}')
  health=$(echo "$o" | awk -F: '/overall-health|SMART Health Status/{sub(/^ +/,"",$2);print $2;exit}')
  temp=$(echo "$o" | awk '/^(194|190) /{print $10;exit} /^Temperature:/{print $2;exit} /^Current Drive Temperature:/{print $4;exit}')
  poh=$(echo "$o" | awk '/^  *9 Power_On_Hours|^9 /{print $10;exit} /^Power On Hours:/{gsub(",","",$4);print $4;exit}')
  real=$(echo "$o" | awk '/^  *5 Reallocated/{print $10;exit}')
  pend=$(echo "$o" | awk '/^197 /{print $10;exit}')
  echo "$(basename "$d")|${model//|/ }|${serial//|/ }|${health//|/ }|${temp}|${poh}|${real}|${pend}"
done
