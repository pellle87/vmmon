Ikoner före namnet på dashboardkorten.
Filerna här är enkla neutrala symboler. Vill du ha egna loggor (t.ex. Windows, Linux, Proxmox, ESXi) lägger du dem här som SVG med samma filnamn, så ersätter de symbolerna. Använd loggor du har rätt att använda, hämta dem från respektive tillverkares varumärkessida.

Namn som letas upp, i den här ordningen (första som finns används):
  Servrar:     nas (TrueNAS/Synology m.fl.), os-<distribution> (t.ex. os-ubuntu, os-debian), os-windows eller os-linux, server
  Virtualisering: hv-proxmox, hv-vmware, hv-hyperv, server
  Nätverk:     nw-<tillverkare>-<roll> (t.ex. nw-cisco-switch), nw-<tillverkare> (cisco, meraki, hp, aosc, huawei, unifi, opnsense, pfsense), nw-switch / nw-router / nw-firewall, server
  Tjänster:    svc
Ladda om sidan med Ctrl+F5 efter att du bytt en fil.

Obs: dashboarden listar mappen automatiskt. Filer som inte finns begärs aldrig, så inga 404-fel i webbläsarkonsolen. Lägg bara dit en fil med rätt namn (t.ex. os-ubuntu.svg, nw-hp-switch.svg, nw-opnsense.svg) så används den efter nästa sidladdning.

vm.svg visas som en extra ikon bredvid ordinarie ikon när servern är en virtuell maskin (hittas via MAC-prefix/SNMP eller namn i en övervakad virtualiseringshost).
Lagring (Storage): nw-nas.svg och nw-san.svg används som standard. Egna ikoner: nw-<tillverkare>-nas.svg, t.ex. nw-synology-nas.svg, nw-truenas-nas.svg, nw-qnap-nas.svg, nw-netapp-san.svg.
