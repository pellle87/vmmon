#!/usr/bin/env bash
# Builds Vmmon_<version>.zip (folder inside: vmmon) from ./vmmon. Version is read from vmmon/config.php.
set -euo pipefail; cd "$(dirname "$0")"
V=$(grep -o "VMMON_VERSION','[0-9.]*" vmmon/config.php | grep -o "[0-9.]*$")
rm -f "Vmmon_$V.zip"; zip -qr "Vmmon_$V.zip" vmmon -x '*.log' -x 'vmmon/update_tmp/*' -x 'vmmon/config.local.php' -x 'vmmon/backups/*.zip'
echo "Vmmon_$V.zip"
