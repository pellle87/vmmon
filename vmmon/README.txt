INSTALLATION (PHP 8+, tillägg: curl, openssl, pdo_mysql, snmp (för SNMP-checks))
1. Ta bort gamla databasen (DROP DATABASE vmmon;) och kör: mysql -u root -p < sql/schema.sql  + DB-användare med rättigheter på vmmon.*
2. Redigera config.php (DB-uppgifter). Lägg filerna i webbroten, kör över HTTPS.
3. Skapa admin-kontot (e-post krävs för glömt lösenord). Gå till Inställningar och fyll i SMTP + intervall.
4. Lägg till servrar (namn, IP, Linux/Windows) och checks. Skapa universella larmregler under Larmregler.
Ingen cron: dashboarden anropar api/poll.php var 15:e sekund – pollning och larm körs bara medan någon har dashboarden öppen.

UPPGRADERA FRÅN FÖREGÅENDE VERSION: kör sql/migrate_v3.sql (lägger till kolumnen servers.info).
EXAKT LINUX-VERSION (valfritt): skapa /usr/local/bin/osrelease.sh (chmod +x) med innehållet:
  #!/bin/sh
  . /etc/os-release; echo "$PRETTY_NAME"
lägg i snmpd.conf:  extend osrelease /usr/local/bin/osrelease.sh
(om snmpd använder en begränsad view måste .1.3.6.1.4.1.8072 vara tillåten), starta om snmpd.
Utan detta härleds Ubuntu/Debian-version från kernelversionen.
UPPGRADERA FRÅN v3: kör sql/migrate_v4.sql (target blir TEXT så många dolda diskar ryms).
UPPGRADERA FRÅN v4: kör sql/migrate_v5.sql (tabell för belastningshistorik). Graferna fylls på från och med uppgraderingen.
UPPGRADERA FRÅN v5: kör sql/migrate_v6.sql (kolumn för dolda service checks).
UPPGRADERA FRÅN v6: kör sql/migrate_v7.sql (service checks-kort, underhållsläge, nätverkstabeller och nya larmtyper).
Nätverksdata hämtas av SNMP-checken (IF-MIB); hastigheter räknas ut från räknare, så graferna börjar visa data efter ett par minuter.
DB-UPPGIFTER: lägg dem i config.local.php (se config.local.php.example) – då kan config.php ersättas vid uppdateringar utan att du skriver om dem.
UPPGRADERA FRÅN v7: kör sql/migrate_v8.sql (användares senaste inloggning/aktivitet + historik för check-statusbyten).
UPPGRADERA FRÅN v8: kör sql/migrate_v9.sql (kolumn för ignorerade serverkort).
UPPGRADERA FRÅN v9: kör sql/migrate_v10.sql (aktivera/inaktivera, Telegram/larmkanaler, SNMP v3). Larmmottagare flyttas till Inställningar > Larmkontakter.
UPPGRADERA FRÅN v10: kör sql/migrate_v11.sql (webhook som larmkanal). Lösenord/tokens lagras nu krypterade; nyckeln ligger i secret.php som skapas automatiskt – ta backup på den (utan den måste lösenorden skrivas in igen).
UPPGRADERA FRÅN v11: kör sql/migrate_v12.sql (inaktivera användare, nytt målsystem för larmregler).
UPPGRADERA FRÅN v12: kör sql/migrate_v13.sql (kommentar på servrar).
HYPERVISORS: VMware ESXi identifieras via SNMP (sysObjectID/sysDescr) och VM-listan hämtas ur VMWARE-VMINFO-MIB (aktivera SNMP på ESXi: esxcli system snmp set --communities <community> --enable true). Proxmox VE identifieras via kernelnamnet (-pve); där finns ingen officiell MIB, så körande VM:ar härleds ur processlistan (net-snmp-vyn måste släppa igenom .1.3.6.1.2.1.25.4).
UPPGRADERA FRÅN v13: kör sql/migrate_v14.sql (Hosts: Proxmox/VMware/Hyper-V + nya larmtyper).
HOSTS: Proxmox via API-token (Datacenter > Permissions > API Tokens, roll PVEAuditor, avmarkera 'Privilege Separation' eller ge token samma roll). VMware ESXi via SNMP. Hyper-V via Zabbix-agent (nycklarna wmi.getall måste vara tillåtna). Hosts är rader i tabellen servers (hv_type) så borttagning/cascade, underhållsläge, ignore och kortordning fungerar som för servrar.
UPPGRADERA FRÅN v14: kör sql/migrate_v15.sql (dolda VM:ar/storage på host-kort).

BAKGRUNDSPROCESS (rekommenderas – annars körs kontroller och larm bara när någon har dashboarden öppen):
 Linux: kopiera vmmon-worker.service till /etc/systemd/system/, justera sökvägar/User, sedan:
   systemctl daemon-reload && systemctl enable --now vmmon-worker
 Windows: nssm install VMMonitor php.exe C:\sökväg\worker.php   (eller Schemalagd aktivitet vid start)
 Utan tjänst: cron/Schemaläggare varje minut:  php /sökväg/worker.php --for=55
 PHP för kommandoraden (php-cli) måste ha tilläggen pdo_mysql, curl, openssl och snmp (kontrollera med: php -m).
 Når worker.php är igång hoppar dashboarden över egen pollning. Dör den tar webbläsaren över automatiskt.

LOGGAR: journalctl -u vmmon-worker -f   (eller: systemctl status vmmon-worker). Du ska se 'startar', 'kördes: N kontroller…' vid första cykeln och 'lever: …' var 10:e minut. Prova för hand: sudo -u www-data php worker.php --once

UPPGRADERA FRÅN v15: kör sql/migrate_v16.sql (Nätverk: switchar/routrar/brandväggar, dölj kort på dash, nya larmtyper).
NÄTVERK: meny Nätverk. Lägg till Cisco, Meraki, HP ProCurve (t.ex. 2530), Aruba AOS-CX, Huawei, UniFi, OPNsense, pfSense eller annan SNMP-enhet och välj roll (Switch/Router/Brandvägg). Ping + SNMP; interface, trafik, fel, CPU, minne, temperatur, fläktar och nätdelar (där enheten stöder det). Intervall under Inställningar. Larmtyper: enhet svarar inte på SNMP, temperatur, fläkt/nätdel – samt CPU/minne/interface-regler som för servrar.
Knappen Dölj från dash finns nu på Servrar, Virtualisering, Tjänster och Nätverk.

UPPGRADERA FRÅN v16: kör sql/migrate_v17.sql (larmtyp 'nätverksenhet svarar inte på ping' + SMS som larmkanal).
ZABBIX-AGENT: ~60 färdiga kontroller i grupper (System, Minne, Disk och filer, Processer och tjänster, Nätverk, Windows, Applikationer) samt egen item-key. Valfritt villkor för OK, t.ex. <90, >=1, =active, ~text. Agenten måste tillåta monitorns IP i Server= (passiva kontroller, port 10050). Använd Testa nu för att se agentens exakta felmeddelande. system.run stöds inte avsiktligt.
SMS: Inställningar > Notifieringar > SMS (REST-API). Mallar för 46elks och Twilio. Välj SMS som kanal i larmregeln.

DASHBOARD: sortering (Egen layout / Kritikalitet) väljs på dashboarden och sparas i webbläsaren. Ljudsignal vid ny kritisk status kan stängas av under Inställningar > Allmänt.

UPPGRADERA FRÅN v17: kör sql/migrate_v18.sql (tabell för applikationsloggen: Inställningar > Loggar).
Rutan med Pausa larm / Inaktivera / Dölj från dash är nu gemensam för server-, host-, nätverks- och tjänstesidor. Dolda kort övervakas och larmar som vanligt.

Ingen ny migrering för denna version (kräver v18). Panelen Tjänster och kontroller (checks_lib.php) är nu gemensam för server-, host- och nätverkssidor. Loggvyn har källor Inloggning/SNMP/TCP/URL/Zabbix/Proxmox/VMware+Hyper-V/Worker/Larm/Konfiguration och egen lagringstid (Inställningar > Allmänt > Spara loggar). Worker-loggen läser även systemd-journalen om webbserver-användaren ingår i gruppen systemd-journal.

UPPGRADERA FRÅN v18: kör sql/migrate_v19.sql (larmtyp: tjänst/TCP-svarstid över X ms).

Uppgradering (svarstid + värdkontroller):
- Ingen migrering. Ersätt checks_lib.php, settings.php, index.php, assets/app.js, assets/style.css.
- Ny slider "Svarstid" (ms) under Inställningar > Allmänt: normal/varning/kritisk. Färgar ms-märket på tjänstekort och påverkar kortets status.
- Proxmox API / VMware SNMP / Hyper-V-agent och nätverksenheters SNMP visas nu som egen rad i "Tjänster och kontroller".

Uppgradering (hv_ping): ingen migrering. Ersätt lib.php, alerts.php, host.php, netdev.php. Larmregel "Host svarar inte på ping" tillagd, "Uppdatera nu"-knappen borttagen (Testa nu finns i tabellen).

TCP-svarstid mäter nu bara uppkopplingen, inte väntan på bannern (SMTP-hälsning kunde ge ~2000 ms och göra kortet kritiskt). Ersätt lib.php.

Dashboard: tjänstchips färgas gula/röda vid långsam svarstid och kortet visar en rad med orsaken. Ersätt assets/app.js och assets/style.css.

UPPGRADERA FRÅN v19: kör sql/migrate_v20.sql (TCP/URL-kontroller: välj Upp/ner och/eller Svarstid (ms) per kontroll; befintliga får upp/ner på, svarstid av). Ersätt checks_lib.php, services.php, lib.php, api/data.php, assets/app.js, assets/style.css, sql/schema.sql.
Svarstid styrs av slidern under Inställningar > Allmänt och påverkar bara kontroller där Svarstid är ibockad.

Loggar: sammanfattning per källa (TCP, URL, Zabbix, SNMP, Proxmox/VMware/Hyper-V) var 10:e minut, samt loggrad när svarstiden byter nivå (normal/varning/kritisk) för kontroller med Svarstid ibockad. Larmtypen svarstid gäller bara kontroller med Svarstid ibockad. Ersätt lib.php och logs.php.

IKONER: dashboardkorten har en ikon före namnet (assets/icons/, se README.txt där). Inga migreringar. Ersätt assets/app.js, assets/style.css och mappen assets/icons.

Dashboard: översta rutan visar Servrar/Nätverk/Virtuella/Tjänster var för sig, och Inaktiva och Ignorerade visas alltid (även 0). Ersätt assets/app.js och assets/style.css.

MOBILMENY + SIGNAL: toppmenyn blir en hamburgermeny på skärmar under 900 px. Ny notifieringstyp Signal (via egen signal-cli-rest-api, se Inställningar > Notifieringar). Kör sql/migrate_v21.sql. Ersätt config.php, lib.php, alerts.php, settings.php, sql/schema.sql och assets/style.css.

ZABBIX AGENT2: vmmon skickar nu förfrågan med Zabbix-protokollets header (ZBXD) – krävs av Agent2, annars i/o timeout i agentloggen. Ersätt lib.php. Inga migreringar.

DASHBOARD: sortering heter nu Egen / Kritikalitet. Ny vy Normal / Kompakt (10 kort per rad på bred skärm: namn, IP/värdnamn, OS/modell och status). Valen sparas per användare i databasen. Kör sql/migrate_v22.sql. Ersätt index.php, api/pref.php (ny), assets/app.js, assets/style.css, config.php och sql/schema.sql. Rättat: i mobilmenyn dolde klassen .nm kortens mätarnamn på smala skärmar – bytt till .nvm.

DASHBOARD: Sortering och Vy är nu ikonknappar (flytta / varning, stora / små rutor) med hjälptext vid hovring. Ersätt assets/app.js och assets/style.css. Inga migreringar.

FIX: sortering/vy-knapparna reagerar direkt igen (dashboarden hoppade över omritning medan musen låg över knapparna). Ljud: försöker aktiveras direkt vid sidladdning och vid fler typer av klick/tryck. Ersätt assets/app.js.

Dashboard kompakt vy: samma avstånd under statusrutorna som i normal vy. Ersätt assets/style.css.

Dashboard: sortering/vy faller tillbaka på webbläsaren om kolumnerna dash_sort/dash_view saknas (sql/migrate_v22.sql ej körd). Ersätt index.php och assets/app.js.

IKONER: dashboarden listar assets/icons automatiskt och begär bara filer som finns (inga 404 i konsolen). Egen ikon = lägg filen i assets/icons med rätt namn. Favicon tillagd. Ersätt index.php, config.php, assets/app.js och assets/icons/README.txt.

DASHBOARD: i kompakt vy visas normalt kort som förhandsvisning när man hovrar (efter 0,25 s, bredvid kortet). Ersätt assets/app.js och assets/style.css.

DASHBOARD: förhandsvisningen i kompakt vy växer nu ut från kortet (överlappar det) i stället för att ligga bredvid; webbläsarens egen tooltip på kompakta kort borttagen. Ersätt assets/app.js.

SERVICES: klicka på en rad på Services-sidan för att visa svarstid (ms) över tid (24h/7 dagar/30 dagar/Allt). Historik sparas från och med nu (5-minutersfack, samma lagringstid som övrig historik). Kör sql/migrate_v23.sql. Ersätt services.php, lib.php, sql/schema.sql, assets/style.css och api/svc_ms.php (ny).

SERVICES: raderna går nu hela vägen ut (knappcellen var flex), tooltip på raderna borttagen. Ersätt services.php och assets/style.css.

TABELLER: radlinjerna går nu hela vägen ut på alla sidor med knappkolumn (Servers, Network, Virtualization, Alert rules, Users, Services, checkpaneler). Ersätt bara assets/style.css.

SERVERS: nya kolumner CPU och Minne med staplar (samma trösklar som dashboarden). KONTROLLER: rader i Services and checks (server-, host-, nätverks- och Services-sidorna) färgas grön/gul/röd efter status. Ersätt lib.php, servers.php, checks_lib.php, services.php och assets/style.css. Inga migreringar.

TABELLER: hela raden är klickbar på Servers/Virtualization/Network (Ctrl-klick öppnar i ny flik). Diskar (server) och Storage (host) färgas grönt/gult/rött efter fyllnadsgrad och diskgränserna i Inställningar. Listan med dolda diskar visas inte längre i SNMP-raden. Ersätt lib.php, checks_lib.php, server.php, host.php, servers.php, hosts.php, network.php, config.php och assets/style.css.

HOST/NÄTVERKSENHET: API-/SNMP-raden i Services and checks har nu Inaktivera, Dölj från dash och Ta bort (påverkar hela enheten). Ersätt checks_lib.php, hosts.php och network.php.

LISTOR: namnen på Servers/Virtualization/Network är fet text (hela raden är klickbar). Servers har ny kolumn Tjänster (antal aktiva kontroller per server). Ersätt servers.php, hosts.php, network.php och assets/style.css.

GRAF: klicka på en TCP/URL-kontroll i Services and checks (server, host, nätverksenhet) för svarstidsgraf, som på Services-sidan. Delad kod i assets/msgraph.js. Servers: Tjänster-kolumnen centrerad. Kräver sql/migrate_v23.sql. Ersätt checks_lib.php, services.php, servers.php, assets/msgraph.js (ny) och assets/style.css.

DASHBOARD: grafer vid musöver även för disk (diskanvändning 24 h). Inställningar > Allmänt: varje graf (CPU, Minne, Disk) kan stängas av. Historik börjar sparas efter sql/migrate_v24.sql. Ersätt lib.php, index.php, settings.php, sql/schema.sql, assets/app.js och api/disk.php (ny).

DASHBOARD: Redigera-läge. Knappen Edit under ljudtexten öppnar redigeringsläge: dra korten, Add graph (guide: typ > enhet > graf, 1 eller 2 grafer per kort, 1 eller 2 kort bred), Spara/Avbryt. Utanför redigeringsläge går korten inte att flytta. Fungerar i både normal och kompakt vy. Layouten (inkl. grafkort) sparas per användare. Kör sql/migrate_v25.sql. Ersätt index.php (oförändrad), api/data.php, api/order.php, api/gcard.php (ny), api/graph_opts.php (ny), assets/app.js, assets/style.css och sql/schema.sql.

DASHBOARD: egen kortordning för Normal och Kompakt vy, sparas per användare. Kompakt använder normal ordning tills du sparat en egen. Vybyte är låst i redigeringsläget. Kör sql/migrate_v26.sql. Ersätt api/data.php, api/order.php, api/gcard.php, assets/app.js, assets/style.css och sql/schema.sql.

Uppgradering (kompakt vy, grafkort 2 kort höga):
  Ersätt assets/style.css och assets/app.js. Ingen migrering.

Uppgradering (grafer: tidsperiod + per vy):
  1. Kör sql/migrate_v27.sql (dash_graphs.v).
  2. Ersätt api/data.php, api/gcard.php, assets/app.js, assets/style.css, sql/schema.sql.

Uppgradering (grafkort per vy, avbryt ångrar borttagning): ersätt assets/app.js. Ingen ny migrering (kräver v27).

Uppgradering (kortare hjälptexter, Proxmox-kort, rubriker): ersätt assets/app.js, host.php, netdev.php.

Uppgradering (texter borttagna, "Edit dash", språkväljare): ersätt network.php, hosts.php, services.php, config.php, assets/app.js, assets/style.css.

Historik: filter och graf (ingen migrering krävs)
  Ersätt history.php och assets/style.css. På Historik finns filter för tid (från/till + snabbval 24 h/7/30/90 dagar),
  servrar (flera), regel/check (flera) och händelse (flera), samt "Återställ filter". Ovanför tabellen visas en graf med antal
  händelser per intervall (auto/timme/dag/vecka) för valt tidsspann; klicka på en stapel för att filtrera på just det intervallet.

Uppgradering: historik knyts till server-id (sql/migrate_v28.sql)
  Kör sql/migrate_v28.sql en gång i MySQL. Den lägger till server_id i alert_log och check_events och fyller i id för befintliga rader
  där namnet entydigt matchar en server. Ersätt lib.php, api/data.php, history.php, sql/schema.sql och assets/style.css.
  Dashboardkortets namn räknas nu av funktionen srv_title() i lib.php (samma som förut: värdnamn från SNMP/Zabbix, annars IP, annars ditt namn),
  och Historik visar samma namn i listan och tabellen. Ett omdöpt eller omkonfigurerat namn tappar inte längre historiken.

Sortering i tabeller (ingen migrering)
  Ny fil assets/tsort.js (laddas via bottom() i config.php). Klicka på en kolumnrubrik för att sortera stigande, klicka igen för fallande
  (pil visas). Naturlig sortering: 1, 2, 10; IP-adresser och interface-namn som Gi1/0/10 sorteras rätt. Tomma värden hamnar sist.
  Interface-listan på server.php och netdev.php sorteras nu naturligt som standard.
  Ersätt config.php, server.php, netdev.php, assets/style.css och lägg till assets/tsort.js.

Proxmox: värdnamn och generell info + sortering efter storlek (ingen migrering)
  Ersätt lib.php, host.php och assets/tsort.js. Efter nästa pollning av Proxmox-hosten (eller knappen "Uppdatera") visas värdnamn (nodnamn),
  kluster, noder med IP, kernel, CPU-modell/sockets/kärnor/trådar, belastning, swap, systemdisk och uptime på host.php.
  Kräver samma token som förut (PVEAuditor med Propagate på /). Saknas rättighet för någon av de nya anropen visas bara fälten som inte gick att hämta.
  En host utan eget namn får nu nodens värdnamn som kortnamn i stället för IP-adressen (när det är en enda nod).
  Tabellsortering: värden med enhet (KB/MB/GB/TB, Mbit/s, "2 h 5 min", "3d 4h 5m") sorteras nu efter faktiskt värde.

v29: Titel = hostname (annars IP, eller eget namn om inställningen "Visa eget namn..." är på) på dashboardkort och sidrubriker. Uptime-procent för Proxmox/SNMP-API-raden beräknas från händelsehistoriken. Ingen migrering.
v30: CPU/minnes-graferna visar nu även max-linje för 24h (tidigare bara >25h).
v31: Kontolåsning. Kör sql/migrate_v29.sql (users.locked_until + tabellen login_fail). Inställningar: Settings > General > Användarinställningar.
v32: Låsmeddelande direkt vid låsning. Betrodda proxyservrar (Settings > General > Användarinställningar) gör att riktig besökar-IP loggas bakom Nginx Proxy Manager.
v33: Settings > General: ny ruta Allmänna inställningar överst, (i)-knappar med förklaringar istället för hjälptexter.
v34: General settings: fälten under varandra med underrubrikerna History och User settings.
v35: General settings innehåller nu intervall och gränsvärden. Separata Spara-knappar för General settings och Dashboard settings.
v36: Profilsida (klicka på användarnamnet i menyn): byt lösenord, TOTP med QR-kod och återställningskoder. Kör sql/migrate_v30.sql.
v37: Passkeys (WebAuthn) på profilsidan och på inloggningen, samt "Kräv tvåstegsverifiering" under Settings > General > User settings. Kör sql/migrate_v31.sql. Passkeys kräver HTTPS och domännamn.
v38: Kom ihåg enhet vid TOTP, sessionslängd och byte av e-post med bekräftelselänk. Kör sql/migrate_v32.sql.
v39: Settings: Account lockout i egen underrubrik på en rad, kortare etikett för Virtualization.
v40: Borttagna knappar: Services and checks (servers), View/Test now (virtualization, network). Raderna är klickbara.
v41: Rensning av utgångna inloggningsförsök, ihågkomna enheter och e-postbekräftelser ingår nu i den automatiska städningen.
v42: Misslyckade inloggningsförsök sparas lika länge som Keep history.
v43: Loggar alltid på engelska. Språkval flyttat till profilsidan; engelska är standard (sql/migrate_v33.sql är valfri).
v44: Hostkort visar OS (t.ex. Proxmox VE 9.2.26) och smalare etikettkolumn. Proxmox Backup Server känns igen via SNMP (visas som PBS, inte som Proxmox VE utan VM:ar) och får källan PBS API under Services and checks: CPU/minne/datastores, misslyckade jobb och ålder på senaste backup. Kör sql/migrate_v34.sql.
v45: PBS API är nu en enda kontroll som hämtar allt: CPU, minne, datastores, jobb (backup, prune, verify, GC, sync) med status på senaste körning och ålder på senaste backup. Visas som en backupruta på dashboardkortet. Ta bort de gamla PBS-kontrollerna (Failed tasks / Latest backup) manuellt. Ingen migrering.
v46: Servervyn för PBS har en tabell "Backup jobs" där jobb och senaste backup per datastore kan döljas/visas på dashboarden. Ingen migrering.
v47: Dölj/Visa, Aktivera/Inaktivera och Test now i tabeller uppdaterar bara den raden utan omladdning (scrollposition och sidan står still), och statusmeddelanden visas vid muspekaren. Övriga knappar laddar om sidan men behåller scrollpositionen. Workern startar om sig själv när PHP-filerna ändras (annars körs gammal kod i upp till en timme efter uppgradering – kör annars: systemctl restart vmmon-worker).
v48: Samma avstånd (16 px) mellan kortrader på dashboarden som mellan korten i sidled, även under sammanfattningsraden.
v49: Virtuella maskiner känns igen (MAC-prefix och hårdvarubeskrivningar via SNMP, samt namnträff mot VM:ar i övervakade Proxmox/VMware/Hyper-V-hostar) och får en extra VM-ikon bredvid OS-ikonen på dashboarden samt raden "Virtual machine" på serversidan. Switchar, hostar och tjänster hoppas över. Ingen migrering; datat fylls i vid nästa SNMP-kontroll.
v50: Settings > SNMP > Custom OIDs: välj med en dropdown vilka servrar varje OID ska kontrolleras mot (inga valda = alla). OID:n hämtas bara på de valda. Ingen migrering.
v51: Egna OID:er kan även väljas per virtualiseringshost och nätverksenhet (listan är grupperad). Värdena visas på host- och nätverkssidorna. Proxmox/Hyper-V-hostar kontrolleras bara om de väljs uttryckligen (standard-community). Ingen migrering.
v52: Normalvyn på dashboarden: alla kortrader är lika höga som grafkortet (mäts automatiskt, minst 360 px). Rubrik (ikon, namn, IP/OS, status) och chips ligger alltid kvar, mellandelen scrollar om innehållet är längre. Linjen ovanför backupjobb-rutan på PBS-kortet är borttagen.
v53: Grafkort med en graf (1 eller 2 kolumner bred) fyller hela kortet i normalvyn i stället för att se tomma ut.
v54: Grafkort för tjänster visar bara "Response <tjänst>" utan prefixet "Services".
v55: Proxmox hämtar nu nätverksgränssnitt via API (noder, nodtrafik, VM-nätkort) och visar dem på host-sidan. Alla interface kan visas/döljas på dashboardkorten för servrar, nätverksenheter och virtuella hosts (standard: dolda, kräver sql/migrate_v35.sql). Agent-port-fältet för Hyper-V är bredare.
v56: Proxmox med flera noder får ett kort per nod på dashen (döljs per nod på host-sidan). Scrollen i korten bevaras vid auto-uppdatering. Nya larmtyper: SNMP/Zabbix-agent/PBS API svarar inte. Fläkt-/nätdelssensorer kan döljas på nätverksenhetens sida (påverkar kort och larm). Kräver sql/migrate_v36.sql.
v57: Sammanfattningsraden på dashen har en ny ruta efter den första som visar om bakgrundstjänsten (systemctl) körs och när den senast sågs (api/worker.php). Rutorna är mindre och texterna centrerade.
v58: Kortare ljudtext (Click page) och borttagen redigeringstext i summeringsraden.
v59: I redigeringsläget flyter Lägg till graf/Spara/Avbryt i en panel längst ned på sidan.
v60: Edit dash-knappen finns kvar i redigeringsläget (orange, 'Redigeringsläge', inaktiv).
v61: Fri rutnätslayout – i redigeringsläget visas ett rutnät med tomma celler; dra kort till en tom cell (luckor tillåtna) eller släpp på ett kort av samma storlek för att byta plats. Positionerna sparas per användare och vy (kräver sql/migrate_v37.sql) och gäller när sorteringen är 'Egen ordning' och kolumnantalet är samma som när layouten sparades.
v62: Fix – 2 breda grafkort behåller sin bredd (och höjd i kompakt vy) i redigeringsläget i rutnätslayouten.
v63: SMART-diskhälsa. Via SNMP: Synology-NAS direkt (Synology-MIB) och Linux/TrueNAS via snmpd-extend med extras/vmmon-smart.sh (se kommentarerna i skriptet). Via Zabbix: ny kontroll "SMART-diskhälsa" (kräver Agent 2 + smartctl). Visas på serversidan (tabell), på dashkortet (summering, problemdiskar listas) och som larmtyp "Diskhälsa (SMART) varnar eller felar". Kräver sql/migrate_v38.sql.
v64: SMART-diskhälsa även via Proxmox VE API (per nod, /disks/list + /disks/smart, kräver Sys.Audit på /nodes) och PBS API (kräver Sys.Audit på /system/disks). Visas på host-/serversidan, på korten (per nod) och i larmtypen Diskhälsa.
v65: PBS: visar orsaken på serversidan när SMART/diskar inte kan hämtas (t.ex. 403 = saknar Sys.Audit på /system/disks).
v66: Ny sida Storage (storage.php) för NAS och SAN: samma upplägg som Network – ping läggs till automatiskt, SNMP v2c/v3 eller 'Ingen SNMP (bara ping)'. Dashkort med egna ikoner (nw-nas.svg / nw-san.svg, samt nw-<tillverkare>-<roll>.svg), volymer/kapacitet, SMART (SNMP), fläkt/nätdel/temperatur. Synology-MIB stöds (temp, fläktar, nätdel, modell). Enheten får detaljsida (netdev.php) med volymer och SMART. Ingen ny migrering.
v67: Lagring: RAID/pool/volym/disk-status via SNMP per tillverkare – Synology (RAID), QNAP (diskar/volymer), NetApp (systemstatus, felande diskar, fläktar, nätdelar, CPU), Dell (iDRAC/OpenManage: system, virtuella/fysiska diskar), HPE (ProLiant/StoreEasy: system, logiska/fysiska diskar), TrueNAS (zpool). Nya tillverkare i listan: IBM, Hitachi, Pure Storage, Huawei OceanStor, Lenovo (standard-SNMP). Visas på dashkortet, detaljsidan, Storage-listan och som larmtyp 'Lagring: RAID/pool/volym'. Kräver sql/migrate_v39.sql.
v68: Storage-enheter: volymer, lagringsstatus (RAID/pool) och SMART-diskar kan döljas/visas på dashkortet från enhetens sida.
v69: Storage: ingen NAS/SAN-väljare (alla är NAS), kortare modelltext (TrueNAS-13.0-U6.8 / TrueNAS-SCALE-24.10.1).
v70: TrueNAS diskTemperaturer (50536.3.1), 'hälsa okänd' när status saknas, NAS-rollen/Typ-raden borttagen på storage-kort
v71: TrueNAS API (CORE+SCALE): valfri API-nyckel/port/cert-verifiering på Lagring-sidan. Hämtar pool-status (ONLINE/DEGRADED), pool-kapacitet, diskar med modell/serienr/temperatur, SMART-testresultat och aktiva TrueNAS-larm (/api/v2.0). Fungerar även med 'Ingen SNMP'. Pool/larm-fel ger status i 'Hälsa' och kan larmas via nw_health. Fixade även Ändra-länken på Lagring (pekade på network.php).
v72: 'TrueNAS API' som val i Tjänster och kontroller på TrueNAS-enheter (nyckel/port/cert), egen rad med Ändra/Ta bort.
v73: Testa nu på TrueNAS API-raden.
v74: TrueNAS API försöker även läsa SMART-attribut per disk (disk/smart_attributes): drifttimmar, omallokerade, pending. Testa nu visar hur många diskar som gav värden.
v75: Seagate Power_On_Hours-raw (timmar i låga 32 bitar, resten millisekunder) maskas till rätt värde.
v76: SMART-rutan på dashen visar bara sammanfattning (X diskar, N varning), inte enskilda diskrader. TrueNAS API: fler attributnamn för omallokerade/pending (SSD).
v77: TrueNAS API: uptime-statistik (24h/7d/30d/totalt via tillståndsbyten) och Inaktivera/Aktivera-knapp (inaktiverat API → SNMP-fallback eller bara ping).
v78: Summeringsrutan Servers/Network/Storage/Virtual/Services ryms på en rad.
v79: KÖR sql/migrate_v40.sql. (1) Teman: Min profil > Tema (Standard = ursprungliga färger, nya användare får Standard; Light, Monokai, Monokai Dark, Monokai Dimmed, Dark, Dark Modern, Nord). Egna teman: lägg en .css i themes/custom/ (mall och variabler i themes/README.txt) – de indexeras automatiskt. (2) Larmregler: schema med dagar (mån–sön), tidsintervall (kan gå över midnatt) och 24/7-ruta som gråar ut dag/tid. Utanför schemat skickas inga nya larm. (3) Ny larmtyp "Zabbix-kontroll fallerar" där man väljer vilka av Zabbix-agentens kontroller som ska larma. (4) (i)-ikon vid Svarstid (ms) på Tjänster/kontroller. (5) 10 larmljud: globalt val under Inställningar > Allmänt (Dashboardinställningar) och eget val/av per användare under Min profil.
v80: SNMPv3-fält (användare, nivå, auth/priv) visas nu vid val av SNMP v3 när man lägger till/ändrar Server, Virtualisering (ESXi), Nätverk och Lagring. Monokai/Monokai Dimmed: varning är nu orange (tydlig mot grönt), kritisk rosa/röd, accent turkos. Ingen ny migrering.
v81: Monokai omgjort efter Sublime Text Monokai (bakgrund #272822, grön/orange/rosa/turkos/lila). Nytt tema Monokai Dark (mörkare variant). Monokai Dimmed lite ljusare bakgrund. Ingen ny migrering.
v1.00: New version numbering (VMMON_VERSION in config.php, raised by 0.01 per change: 1.01, 1.02 …). Profile: Site settings (language, theme, alert sound) and Account and security (email, password, TOTP, passkeys) in separate boxes with subheadings. A ? button in the menu (between user and log out) shows the name + version. The text about custom themes was removed from the profile. No new migration.
v1.01: Profile: the subgroups in Site settings and Account and security are now side by side (columns) instead of stacked. No new migration.
v1.02: Profile: the drop-downs (language, theme, alert sound) now have natural width instead of the full column width. No new migration.
v1.03: (1) Hide/show boxes (toasts) – a new box closes the previous one so they do not overlap. (2) Home Assistant via SNMP: if the system description contains 'Home Assistant', NET-SNMP-EXTEND-MIB::nsExtendOutLine (hass_*: state, supported, docker, HAOS, supervisor, core version) is fetched and shown on the server page under the details. Requires the add-on's extend lines. No new migration; the details are updated at the next SNMP poll.
v1.04: RUN sql/migrate_v41.sql (net_if.ips). The interface tables (server, switch/network, storage) now show an IP column with all IPv4/IPv6 addresses (not loopback/link-local) that SNMP reports per interface. Without the migration everything works as before but without the IP column; the addresses are filled in at the next SNMP poll.
v1.05: Windows interfaces: filter layers (WFP/QoS/Fortinet NDIS LightWeight Filter), Kernel Debugger, Teredo/6to4/IP-HTTPS/isatap, Wan Miniport and Wi-Fi Direct (Local Area Connection*) are hidden. Real adapters, VPN adapters and bridges (vEthernet etc.) are shown. No new migration; old rows disappear at the next SNMP poll.
v1.06: Proxmox: new SDN section (zones with status per node, vnets with VLAN/VNI, alias, VLAN-aware and subnets) – requires SDN.Audit for the API token. The VLAN column now also shows VLAN-aware bridges (with bridge_vids). If network interfaces cannot be fetched the error is shown (the token needs Sys.Audit on /nodes). The SNMP interface list hides Proxmox per-VM plumbing (tapNNNi0, fwbr/fwln/fwpr) so bridges (vmbr*) and VLAN interfaces fit. No new migration.
v1.07: Docker interfaces (docker0, br-*, veth*) are shown again in the interface list for servers (SNMP); the maximum number of interfaces was raised to 96. No new migration.
v1.08: RUN sql/migrate_v42.sql. Docker monitoring per server (server page > Docker): fetched via Zabbix agent 2 (docker.containers/docker.info/docker.container_stats) or the Docker Engine API (recommended via a read-only socket proxy). Shows version, number running/stopped/paused, and a table with container, image, state, status (healthy/unhealthy), CPU and memory; problem containers on top; individual containers can be hidden (excluded from alerts). Two new alert types: 'Docker: container crashed/restarting/unhealthy' and 'Docker: any container is stopped'. Interval: the setting int_docker (default 60 s).
v1.09: RUN sql/migrate_v43.sql (sql/migrate_v42.sql is no longer needed). Docker is now a check type under Services and checks: choose 'Docker API' (port or https:port, ignore list) or Zabbix agent > 'Docker containers (agent 2)'. The check turns red when a container has crashed/is restarting/is unhealthy (or the 'all must run' variant when any is stopped) and shows 'N running · M stopped' – so regular alert rules (Service down) work. The server page shows a container table (image, state, status, CPU, memory) from the check. The separate Docker section and the dk_* alert types were removed. The Type column shows only 'SNMP' / 'PBS API' / 'TrueNAS API' / 'Proxmox API' instead of for example 'snmp · SNMP (CPU, memory, disk, OS)'.
v1.10: The Docker check was simplified: a single type (all containers must run; unhealthy ones count as failures) – no ignore box in the form; in the container table on the server page you hide a container with 'Hide on dash' (then it does not count as a failure). CPU/memory per container was removed (too slow). No new migration beyond v43.
v1.11: The Docker check no longer affects the card's status or the error rows on the dashboard – only the pill (for example 'dck API') turns red (alert rules apply as before). A Docker icon (assets/icons/docker.svg) next to the server name and the row 'Containers N / M running' on the card (excluded containers are not counted). The buttons in the container table are now called 'Exclude from dash' / 'Include on dash'. No new migration.
v1.12: (1) Hovering over 'N / M running' (Docker) on the card shows a list of the containers that run and those that do not. (2) Two new settings under Settings > Dashboard settings: 'Docker: show containers on hover' and 'Compact mode: show the whole card on hover' (both on by default). No new migration.
v1.13: Docker hover: no scrolling, the list flows in two columns and shows up to 50 containers (the rest as '+N more'); containers that are not running are on top. Compact view: the Docker logo is now at the bottom right of the box (not next to the name). No new migration.
v1.14: History: delivery is colored per channel (Telegram: OK green even if email fails and is red). New alert rules 'Home Assistant: state is not running' and 'supported is not true' (only shown when a Home Assistant exists). New migration sql/migrate_v44.sql.
v1.15: The Home Assistant alert rule only shows servers identified as Home Assistant. New setting 'Use 24-hour clock' (Settings > General > Dashboard settings): from/to in the alert rules' schedule are shown as 00–23.
v1.16: 'Use 24-hour clock' moved to Settings > General settings. Off = all dates/times (logs, history, lists, the alert rules' schedule) are shown with AM/PM. The default is on (24h).
v1.17: Fix: the AM/PM conversion doubled AM on times with seconds (10:14:25 AM:25 AM). New setting 'Date format' (General settings): YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY or MM/DD/YYYY – applies to all dates in logs, history and lists.
v1.18: The Docker table on the server page: rows are colored like service checks (green = running/healthy, yellow = starting/paused/restarting, red = stopped/unhealthy/error) and a new Ports column (published ports such as 8080→80/tcp, only exposed such as 443/tcp). Ports are filled in at the next check. The text under the 24h box was removed.
v1.19: Icons for service checks. Every check can get an icon (picker with search, tabs All/Default/MDI/Simple Icons/Own) that replaces the name pill on the dashboard and is colored by status; the name is shown on hover. Without a choice a default icon per check type is used. New tab Settings > Icons: fetch Material Design Icons + Simple Icons (from npm, needs internet and write permission in assets/chkicons/lib/) and upload your own SVGs. 30 own default icons are included. New migration sql/migrate_v45.sql (server_checks.icon + the icons table).
v1.20: Fix: the icons on the dashboard's checks were shown as empty boxes (a relative URL in a CSS variable was resolved against style.css). The mask image is now set directly in the style attribute.
v1.21: Hover on a check icon: only the type is shown (not the check's name), SNMP shows 'SNMP v2c' or 'SNMP v3', Docker shows 'Docker API – Running containers'. The text '(CPU, memory, disk, OS)' was removed. When the icon libraries have been fetched, the server cards' header icon gets a brand icon: Proxmox/PBS = Proxmox, Linux = the distribution's logo or the penguin, Home Assistant, Windows, TrueNAS, OPNsense/pfSense and more. Without fetched libraries the regular icons are used.
v1.22: Home Assistant API as a check type under Services and checks (port or https:port + a long-lived access token, stored encrypted). Fetches /api/config, /api/states, /api/services, /api/events and /api/error_log. The server page shows version, status, location, number of entities, automations/lights/switches, updates, unavailable entities, low battery, active problem sensors, persons, the error log and a searchable table with all entities. Icon: Home Assistant (Simple Icons) or std:home. New migration sql/migrate_v46.sql (source 'ha', last_data MEDIUMTEXT).
v1.23: The Home Assistant check has checkboxes for what to fetch: statistics, updates, needs attention, persons, services and events, the error log and all entities. Basic info (version, status, location, time zone, number of components, URLs) is always fetched. Only what is selected is fetched and shown. Older HA checks keep everything. Fix: the default icon for Home Assistant is now shown in the icon picker.
v1.24: Alert rule 'Home Assistant: update available' (shown when a Home Assistant exists; requires the Updates option to be ticked on the check). 'Unavailable entities' is now its own checkbox on the Home Assistant check, and 'Low battery and problem sensors' another. Older checks that had 'needs attention' get both. New migration sql/migrate_v47.sql.
v1.25: Intervals on checks: no up/down arrow, instead a field with a unit choice seconds/minutes/hours (10 s – 7 days, decimals such as 1.5 h are fine). Applies to both server checks and standalone services. The list shows 'N h' for whole hours.
v1.26: The default intervals under Settings > General use the same fields as the checks: no up/down arrow, unit choice seconds/minutes/hours, 10 s – 7 days.
v1.27: Up/down arrows removed on all number fields and the number fields on Settings > General are narrower. The Proxmox icon on Proxmox API and PBS API checks (when the libraries are fetched). More brand icons on the server cards (MikroTik, Fortinet, Juniper, Netgear, TP-Link, Synology, QNAP, Dell, Supermicro, FreeBSD, OpenWrt, more Linux distributions and others, via OS/model text). Hover: TCP custom shows 'TCP <port>', Zabbix TCP checks show 'Zabbix TCP <port>', and SNMP checks on network devices show SNMP v2c/v3 (previously only 'SNMP'). New alert rule 'Home Assistant: unavailable entities above (count)'. New migration sql/migrate_v48.sql.
v1.28: Access point as a role in Network (own card on the dashboard with clients, SSIDs and channel utilization via the UniFi MIB, a Wi-Fi table on the device page); LAG interfaces that are down and VAP/VLAN sub-interfaces on an AP are hidden so an 8-port switch shows 8 ports
v1.29: Port filtering is done in record_net and the slot rule hides internal 3/x when 0/x exists (a UniFi switch shows only real ports); UniFi devices with Wi-Fi data (switch/AP combos such as the U6-IW) get SSID/radio info and the AP filter regardless of role; PoE per port and total power (POWER-ETHERNET-MIB) on the device page and the dashboard card; SSIDs are grouped by name
v1.30: PoE power in watts per port (HP/Aruba via HP-ICF-POE-MIB, Cisco via CISCO-POWER-ETHERNET-EXT-MIB) and total power as a sum if the device does not report it
v1.31: Columns in the tables on Servers, Network, Storage and Services can be moved (drag the header), resized (drag the edge, double-click resets the column) and shown/hidden with the Columns button, which also has extra columns (for example uptime, kernel/build, busiest interface, PoE, Wi-Fi clients, temperature, response time). Saved per user (requires sql/1.31.sql); the current columns and order are the default. Graph cards on the dashboard no longer have a status border
v1.32: The Columns button is now in the last cell of the table header, the column edges have a visible grip mark, and the action buttons (Edit, Hide on dash, Disable, Delete and others) are collected in a Manage/Modify drop-down menu at the far right so the tables do not get horizontal scroll
v1.33: The tables are always full width without horizontal scroll (the columns shrink the more are shown, text is cut with … and the full text is shown on hover), column width is changed against the neighboring column, the Manage menu has neutral buttons and Delete always asks for confirmation
v1.34: Services: the Type column shows only the check type (for example HTTPS (443), Ping (ICMP)) and a new column Response time warning (Yes/No); the same column handling (move, width, show/hide, saved per user) on History (Alerts, Services, Server checks) and Alert rules; tables without an action column have the column button ☰ in the last header
v1.35: Hosts (virtualization hosts) have the same column handling as the other tables, with extra columns for version, nodes, stopped VMs, CPU, memory, fullest storage, storage volumes, uptime and comment
v1.36: The Columns ▾ button is in the same place and looks the same on all tables (also History, which gets an empty last column for it); CPU and memory on Hosts are shown as bars like on Servers
v1.37: Alert rules: the rule name without a threshold, the Threshold column visible by default. Compact dashboard: headline cards in edit mode (width 1–5 cards, color from the theme or own, own text, half card height; the grid now has half row heights, saved positions are converted automatically). Run sql/1.37.sql.
v1.38: Headline cards: width = number of cards, if they are dragged in between rows the cards below move down, the text is vertically centered, a color wheel for own background + selectable text color (run sql/1.38.sql). IP/Hostname and Community as placeholders on hosts/network/storage. Services: port and interval are filled in automatically when you choose a service.
v1.39: Headline text centered horizontally; the button is called Add headline.
v1.40: The icon counter is updated after a download. The dashboard is not updated automatically in edit mode. Graph cards in compact view can be made 1–4 cards wide and 1–3 cards high, the graphs adapt (run sql/1.40.sql).
v1.41: All SQL files are now in the sql/ folder (schema.sql + migrations). New migrations are named after the version they upgrade to, for example sql/1.41.sql (migrate_v49/50/51/52 were renamed to 1.31/1.37/1.38/1.40.sql). UniFi logo on access points. Cards can be hidden per user and per view/layout with the [−] button in edit mode (Hide from dash was removed from Modify; previously hidden cards are transferred automatically the first time). The graph cards' size buttons got color and an error message. The Services card preview in compact view was fixed. No new migration.
v1.42: The [−] button is red; hidden cards disappear from the grid in edit mode and end up in the Hidden cards menu next to Add graph/Add headline – click a card there to put it back on the dashboard.
v1.43: New BMC page (after Storage) for iLO / iDRAC / IPMI: added as a server (ping automatically, SNMP optional or off), health/temperature/fans/power supplies via SNMP for iDRAC and iLO, own card and icon on the dashboard, own counter and column choices. Alert rules: only configured notification channels can be selected. The [−] button on the dashboard cards is now a small red round −. No new migration.
v1.44: The headline cards work again (a field name collision in the hide function). Cards can now be dragged half a step (one row) at a time, also over their own old position; the card follows where you grabbed it.
v1.45: BMC: common SNMP collection for iDRAC, iLO and IPMI-based BMCs (Huawei iBMC, Supermicro, Lenovo XCC and others): power supplies (status + W), power draw, temperatures, fans (rpm) and voltages. iDRAC via IDRAC-MIB, iLO via CPQHLTH-MIB (temperatures + system status) and all via the standard ENTITY-SENSOR-MIB. New column Power on the BMC page, power on the dashboard card, and an SNMP walk link on the device page (troubleshooting).
v1.46: snmpwalk.php tests sysDescr first and accepts &c=community for testing.
v1.47: The SNMP walk for BMCs now uses small bulk requests (and SNMPv1 getnext as a fallback) – some BMCs (for example Huawei iBMC) do not answer large GETBULK. Applies to both the collection and snmpwalk.php.
v1.48: Huawei iBMC (HUAWEI-SERVER-IBMC-MIB): system status, CPU/memory/disk/PCIe/temperature/power supply/fan status, temperatures, fans (rpm/%), power supplies (status + input power), system power, average/peak power, CPU/memory usage and active alarms. Status values 1=OK, 2=Minor, 3=Major, 4=Critical (an assumption – the MIB dump does not show the enumerations).
v1.49: Huawei iBMC: power supplies and fans get their real names (PS1, PS2 …) from the device name instead of the location ('chassis'); empty power supply slots are hidden (presence ≠ 2).
v1.50: Huawei iBMC: temperatures are in tenths of degrees (240 = 24.0 °C) – now correct; Inlet/CPU1/CPU2 are shown. Fans: Fan1 Front … with speed, empty slots (presence ≠ 2) are hidden.
v1.51: New alert rules: 'Power supply (PSU) failure' and 'Fan failure' (separate, for network/storage/BMC), 'Disk temperature above' (SMART temperature on servers, hosts and storage). 'Temperature above' applies to all devices that report temperature (network, storage, BMC – highest sensor, the alert text lists all above the limit). 'Hardware: RAID/pool/volume/BMC' was renamed from 'Storage'. No new migration.
v1.52: Alert messages show when the problem started and how long it has lasted; the OK message shows how long it lasted and what alerted (also in the webhook: since/duration_sec). New history tab 'All events': all warning/critical events are logged regardless of length with the reason a notification was or was not sent (no matching rule, threshold not reached, critical time too short, schedule, maintenance, paused alerts). 3 layouts per user – choose with the 'Layout' button in edit mode, the last active layout is saved. Run sql/1.52.sql.
v1.53: New export page in History (history_export.php): choose a period (a day–1 year, all, or own from/to) and content (summary/statistics, alert log, all events with notification reason, service and server check events) and download as Excel (.xlsx) or CSV. No SQL change.
v1.54: The export page: a Preview button (shows the number of rows and the first 15 rows per sheet before exporting); the buttons ended up wrong because of a CSS collision with the dashboard's .hb – new class .hbt (also fixes the Filter/Reset buttons in History).
v1.55: Device groups: a new page/menu (after Services) where you create, rename, delete and edit groups (several devices per group, one device in several groups, bulk move). Default groups Production servers, Lab servers, Networking, Services. The 'Groups' column (via Columns) on Servers, Virtualization, Network, Storage, BMC and Services. Alert rules can target device groups. Run sql/1.55.sql.
v1.56: Server, host and device pages: a row 'Device groups' in the info card; all tables on the pages (interfaces, checks, disks, VMs and others) get the same column handling as the list pages – show/hide, resize and move columns (saved per user). Tables with merged cells/uneven rows are left unchanged. No SQL.
v1.57: Column handling: the action column (for example Show/Hide on dash) gets a width to fit the buttons so they do not end up outside the table. The zip file is called Vmmon_<version>.zip from now on (the folder in the zip is called vmmon).
v1.58: The action column (Show/Hide on dash and others) is right-aligned like the Modify button.
v1.59: Built-in updater (Settings > Update VM Monitor, update.php): upload a new Vmmon zip, the version is checked (must be newer), the current version is backed up (the 3 latest are kept, can be downloaded/restored), the files are replaced (icons and config.local.php are not touched) and SQL migrations (N.NN.sql between the old and new version) are run. Requires PHP zip and write permission for the web server user. Restart vmmon-worker afterwards. 'Hide from dash' is now called 'Hide on dash'. The zip contains only schema.sql and the 5 latest migrations. No SQL.
v1.60: The updater: no manual restart is needed – the background process already restarts itself when the PHP files change. The result page now shows the background process's heartbeat live (running/restarting/no heartbeat). No SQL.
v1.61: The updater: only requires writable FOLDERS (775/2775, files are replaced via rename), shows which user PHP runs as, owner/group/permissions and which folders are not writable, plus chgrp/chmod commands. The backups/ folder (with .htaccess) is now included in the zip. No SQL.
v1.62: The updater is the tab 'Update' among the Settings tabs (the button was removed from the top), shorter text, the commands on their own lines. SQL: one single, re-runnable sql/1.62.sql that contains all changes since 1.31 (tables IF NOT EXISTS, columns are only added if missing, the default groups are only created the first time) – the zip contains only it + schema.sql, the updater only runs the latest. Run sql/1.62.sql (safe to run even if you already ran the earlier ones).
v1.63: Sign-in with user name/password can be turned off (requires an admin with a passkey active in the last day, confirmation on save, emergency override VMMON_FORCE_PASSWORD_LOGIN). SSO via OAuth/OpenID Connect (Pocket ID and others) under User settings. Run sql/1.63.sql.
v1.64: New settings page Security (User settings, SSO, Account lockout moved there). The SSO button's default name is 'OAuth Login'.
v1.65: Optional role mapping for SSO (claim + value gives admin, optional demotion).
v1.66: Security review: forgotten password via a one-time link (reset.php) instead of a new password directly, secure session cookies and security headers, XSS hardening in the dashboard JS, .htaccess in sql/ and extras/, Account lockout above SSO. Run sql/1.66.sql.
v1.67: jQuery 3.6.1 is local in assets/ (no external CDN).
v1.68: Password managers (Bitwarden and others) ignore the fields on the settings pages.
v1.69: The icon download checks that files are really written (gives an error message instead of a false OK); the texts on the icon page were simplified.
v1.70: assets/chkicons/lib/index.json is included in the zip (empty on a new install); the updater never overwrites assets/chkicons/lib/ (downloaded icon libraries are preserved).
v1.71: Edit mode on the dashboard shows 5 empty rows below the last card in compact view and 2 in normal view.
v1.72: The graph card's size buttons (Width/Height/Remove) stay at the top in edit mode and are not scrolled away after a drag.
v1.73: A column per device group (Group 1–N, sortable, hidden by default under Columns) on servers/virtualization/network/storage/BMC/services; the button is just called 'Create group'.
v1.74: 'Create group' in the default button color; group columns are called 'Group: name' without a number.
v1.75: Double-clicking a column edge fits the width to the widest content (like Excel); the group columns' header is just the group name.
v1.76: An icon for every device is shown in the header of the device page (server, virtualization, network, storage, BMC, services) with a pencil to choose an own icon (also used on the dashboard); device groups can get their own icon (default: the grid icon 'group'); the checks on the device page and Services can be dragged (⋮⋮) to change the order, which controls the order in the Services card on the dashboard (without an own order the status-first order applies as before). Run sql/1.77.sql.
v1.77: The rule 'Server not responding (ping)' (offline) no longer applies to virtualization hosts and network/storage/BMC devices (they have their own rules: Host/Network device not responding), so a network device that loses ping gives only one notification instead of two.
v1.78: Alert rules per device type: Server/Network/Virtualization/Storage/BMC rules only apply to the respective type of device, new st_*/bmc_* rules, 'Storage devices' and network rules under Network, alert history shows rule names in plain text.
v1.79: Alert rules: the target picker only shows devices the rule can apply to (CPU/memory: servers+network+storage, disk: servers+hosts+storage, offline: servers and so on), interface alerts for all device types with a choice of device and then interface, disk alerts for storage and hosts, 'Service down' does not count the device's own ping check (covered by the ping rules).
v1.80: Alert rules: CPU/memory also apply to virtualization hosts, the target choices are sorted by device type (with headings) and name.
v1.81: Read API (api/v1.php): token authentication (Settings > API), endpoints for devices, checks, interfaces, active alerts, rules, alert history, time series (cpu/memory/disk/network/response time) and Prometheus format (r=metrics) for Grafana.
v1.82: Settings: password managers (Bitwarden and others) now ignore all fields already in the HTML; the API token list no longer dims the buttons for disabled tokens.
v1.83: Settings: the field 'Max failed attempts' was renamed (the word login triggered Bitwarden), shorter API text.
v1.84: The host rows (SNMP/API/TrueNAS API) can now be dragged among the checks on the network, storage, BMC and host pages, and the order is followed on the dashboard.
v1.85: Alert rules: the device groups are now inside the 'Select…' list (at the bottom) instead of a separate box, the explanatory text was removed (it is in the docs).
v1.86: Alert rules: Device groups is a separate choice beside All and Select… (shows the group list when selected).
v1.87: Alert rules: the Select…/Device groups box is no longer full width but adapts to its content.
v1.88: Alert rules: the device groups are listed in two columns.
v1.89: Settings > SNMP: Autodiscover (network + community, a manual scan that pings first in batches of at most 8 and then tries SNMP, found devices are added as server/network/storage/BMC). Fix: Custom OIDs (Add/remove did not work since v1.82 because the password manager filter damaged the page's script), the device picker looks normal, the plus sign is gone.
v1.90: Custom OIDs: the device picker is a regular drop-down 'Select device(s)'. Autodiscover also recognizes devices added by host name (resolves the name to an IP) so they are never added twice.
v1.91: Settings > SNMP: new layout in one box with subheadings (SNMP v2c, SNMP v3, Custom OIDs, Autodiscover). v2c and v3 each have their own Save button, Custom OIDs and Autodiscover are saved automatically when you change, add or remove a row or click elsewhere.
v1.92: Autodiscover was outside the SNMP box (an extra </div>) – fixed. Descriptive texts under Custom OIDs and Autodiscover were removed.
v1.93: Custom OID and Autodiscover: an input row with an Add button, added rows are listed below with ✕ (Autodiscover also Scan); the ping choice applies to all rows.
v1.94: Autodiscover: a Cancel button during scanning and in the result list; better automatic type detection (firewall/switch/AP also via device name).
v1.95: Fix: the Autodiscover result list was not shown in v1.94 (the Cancel button was wired before the list existed).
v1.96: The interfaces of virtualization hosts (VMware via SNMP, Proxmox/Hyper-V via SNMP if the host answers, otherwise Proxmox link status from the API) can now be selected in the alert rules under Interface. New rule CRC errors per minute (Etherlike-MIB dot3StatsFCSErrors) and a CRC/min column in the interface tables. New SQL: net_if.in_crc/crc_pm.
v1.97: Hardware for servers and hosts via SNMP: temperatures and fan speeds (LM-SENSORS, ENTITY-SENSOR, HPE, Dell OpenManage), swap (total/used, HOST-RESOURCES or UCD) – shown on the device page. New alert rules: Swap usage above, Temperature (server/host) above, Fan speed below (RPM). Network devices get the same sensors as a fallback. New Zabbix items: CPU frequency (Linux) and CPU clock speed and memory speed via WMI (Windows).
v1.98: Routing table on the network device page (switch/router/firewall): the button Fetch routing table reads IP-FORWARD-MIB live via SNMP (ipCidrRouteTable, otherwise ipRouteTable), with a filter and a summary (number of routes, default route). Not stored. No new SQL.
v1.99: Zabbix agent in active mode (the agent connects to VM Monitor). A new listener zbx-active.php as the service vmmon-zabbix (turned on under Settings > Zabbix, port 10051, optional token via HostMetadata). The mode and the agent's host name are chosen per server under Servers > edit. All Zabbix checks then read the values the agent sent. SQL: zbx_active, servers.zbx_active/zbx_host. Existing installations: sudo cp vmmon-zabbix.service /etc/systemd/system/ && sudo systemctl enable --now vmmon-zabbix
v2.00: "Test now", "Disable" and similar buttons on a check broke the row layout (the Manage menu disappeared and the buttons ended up outside the table). The row is now put back into the column layout after the ajax update.
v2.01: The Zabbix tab in Settings has a new layout (narrower fields, the Create token button separated from Save).
v2.02: Zabbix active: replies include config_revision (required by Zabbix 7.0 agents), support for the active check heartbeat, a debug log per request and a warning for unreadable connections.
v2.03: Zabbix active: servers in active mode always ask for agent.ping, uptime and swap (otherwise there were no keys to send if the server's checks do not use Zabbix). The Zabbix tab shows the last contact and the number of keys. Swap is shown in the server page's info table (SNMP or the agent's values); 0 GB used is shown as 0 instead of –.
v2.04: Swap is again shown only in the Hardware panel (not in the info table); 0 GB used is shown as 0.
v2.05: Zabbix active: understands the data format of Zabbix 6.4+/7.0 agents (host name once per packet, values identified by itemid instead of key). Previously all data was rejected.
v2.06: The background service was shown as Stopped during long polling rounds (the heartbeat was only set after a whole round). The heartbeat is now set between every check/host/network device and at the start of every round.
v2.07: The status box on the dashboard now shows Worker and (if active mode is on) Zabbix listener, each with its status and 'Last seen'.
v2.08: The explanation of active mode is now in an (i) button next to the checkbox on the Zabbix tab.
v2.09: Dashboard status boxes: Worker and Zabbix listener are separate boxes (name in green/red, 'Last seen' below). The (i) button on the Zabbix tab is outside the checkbox label and can be clicked.
v2.10: Worker and Zabbix listener are in the same status box on the dashboard. The click handler of the (i) buttons was only loaded on the General tab in Settings, so (i) on the Zabbix tab did nothing; it is now loaded on all tabs.
v2.11: Worker and Zabbix listener are stacked in the status box.
v2.12: The Worker/Zabbix listener status box is lower: 'Last seen' is shown as a tooltip on hover instead of as text.
v2.13: New alert rules 'Background service (worker) has stopped' and 'Zabbix listener has stopped' (group VM Monitor itself; 'for how long' sets how long the service must have been gone). Checked by the worker, the Zabbix listener and open browsers. SQL: ENUM extended with sys_worker, sys_zbx. BMC devices were also shown under Network (network.php); they are now shown only under BMC.
v2.14: The routing table gave 'Request failed' (api/routes.php was missing lib.php). Fixed.
v2.15: Firewalls now show disks/volumes (SNMP hrStorage) under Volumes and can get disk usage alerts.
v2.16: The top row of the dashboard no longer wraps to two lines on smaller computer screens.
v2.17: Graph cards in compact view: the wizard now shows Width 1–4 and Height 1–3 as in edit mode, and creates the card with those choices.
v2.18: New graph cards in compact view get the default width 2 and height 2.
v2.19: The colors of the graph lines now follow the theme (accent/ok/warning/error).
v2.20: The CSRF error now says whether the session cookie is missing or the page has expired. The install script no longer sets session.cookie_secure in the PHP pool (sign-in did not work via http://localhost).
v2.21: SNMPv3 with an empty security level crashed ('must not be empty'). It now falls back to authPriv/SHA/AES.
v2.22: Own SNMP port per device/check (v2c and v3), for example Meraki snmp.meraki.com port 16100. The Port field is next to the SNMP version.
v2.23: Meraki: fetches all devices in the organization (MERAKI-CLOUD-CONTROLLER-MIB) – a table with status/clients/IP/serial number/last seen on the device page, a summary on the card and an alert (Hardware degraded or failing) when a Meraki device goes offline.
v2.24: New device role 'Cloud Key' (UniFi Cloud Key/controller) under Network: CPU, memory, disks/volumes, temperature and interfaces via SNMP.
v2.25: SNMPv3: an empty privacy password uses the auth password (UniFi has only one password).
v2.26: Own polling interval (seconds) per network/storage/BMC device in the form for adding/editing a device.
v2.27: The forms for adding network, storage and BMC devices remember the last choices (type, role, SNMP version, port, interval, community, v3 user/level/protocols).
v2.28: The device page shows the device's own polling interval (if set) instead of the default interval.
v2.29: The SNMP interval under Settings now applies to all SNMP (servers, network, storage, BMC). The 'Network (switches/routers)' setting has been removed.
v2.30: Update page: backups now also contain the database (database.sql), all backups are kept, a 'Back up now' button, 'Delete' per backup and an optional database restore on Restore.
v2.31: The backup list now shows all backups (bug: only the latest was visible). The database is always restored when the backup contains it (the '+ database' box was removed).
v2.32: Memory via SNMP (UCD) counts buffers and cache as free, so Linux devices such as the UniFi Cloud Key do not show too high a memory value.
v2.33: Ping turns red only after X missed pings in a row (Settings, default 3). After a missed ping the device is pinged again every second by the background process. New column server_checks.fail_n (see the SQL file).
v2.34: The Fullest disk column is shown as a bar (like CPU/memory) on servers.php and is now also available on storage.php (Columns menu).
v2.35: CPU and memory as bars on storage.php and network.php too.
v2.36: The column Senast is now called Senaste kontroll / Last check.
v2.37: The Update page prints the steps while update, backup and restore are running (streamed output) and then goes to the result page.
v2.38: Removed an information text on the update page before installation.
v2.39: Date/time filters (history, export) are shown in 24-hour format when 24 h is enabled (AM/PM followed the browser). Removed the icon text on the update page.
v2.40: The date/time filter uses the chosen date format (Settings), a calendar button and a row without line breaks.
v2.41: History opens with To = now and From = 7 days earlier.
v2.42: Scaling for hundreds of devices: worker.php starts N pollers automatically (Settings → Pollers, auto/1–16) with a lock per object; bulk ping with fping if available; SNMP/URL/host objects that are down are polled less often (3×, max 300 s) and with a 1 s timeout; rarely changing SNMP data (model, serial number, location, disks on firewall/Cloud Key) is read every 15 minutes (Settings); poll time is logged and slow devices are listed; the dashboard data is fetched with two queries instead of one per device.
v2.43: Default intervals: the SNMP field is just called SNMP and has an (i) explanation, SNMP: rarely changing data has an (i) explaining which data is meant.
v2.44: The changelog shown on the Update page is now in English (all earlier entries translated; new entries are written in English).
v2.45: Fix: the default interval fields (TCP/ping, URL, SNMP, SNMP: rarely changing data, Zabbix agent) were missing on the Settings page since v2.43; only Virtualization was shown.
v2.46: The dashboard Services card now shows only the short check type (HTTPS, HTTP, ICMP, Cert, SSH, …) without port numbers.
v2.47: The Add/Edit check form on the Services page now shows only the fields that apply: search text only for "contains text", no response-time option for SSL certificate checks, no port field for ping.
v2.48: Fix: the (i) info button next to Response (ms) on the Services page (and the check forms) now opens its popup like the ones in Settings; the popup script is shared on all pages.
v2.49: New dashboard settings "Hide OS icon in compact view" and "Hide VM icon in compact view".
v2.50: New: SNMP trap (v1, v2c, v3 with SHA/MD5 and AES/DES) and syslog (RFC 3164/5424, UniFi, Meraki, switches, routers, firewalls) receiver as the service vmmon-recv (UDP 162 and 514 by default, ports configurable under Settings > Trap / syslog). Events are stored in the new table log_events (run the SQL file on the Update tab) and shown under History > Trap / syslog with filters (period, type, severity, device, text), a manual link from an unknown sender IP to a device, and automatic refresh. New alert rule type "Trap/syslog event matches" (source, severity, text or /regex/, count within a time window). Settings: SNMPv3 trap users, accepted communities, ignore filters, retention, flood protection. After updating, install the service once: sudo cp vmmon-recv.service /etc/systemd/system/ && sudo systemctl daemon-reload && sudo systemctl enable --now vmmon-recv (the install script does it for new installations).
v2.51: Fix: the Alert rules page was empty since v2.50 (a missing include broke the rule form). New: firewall log filtering under History > Trap / syslog. Firewall lines are parsed into action (pass/block), direction, protocol, source and destination IP and port, in/out interface and rule number for pfSense/OPNsense (filterlog), Linux netfilter/UniFi, Meraki flows and MikroTik, shown as a readable line with clickable values, with filters for each field (several values, ! to exclude, wildcards, CIDR, port ranges). Older events are converted automatically when the receiver starts. The alert rule Trap/syslog event matches can use the same firewall filters. New setting to keep only blocked traffic. Run the SQL file on the Update tab (adds columns to log_events).
v2.52: Trap / syslog page: automatic refresh now only reloads the event list (not the whole page), so text typed in the filters is kept. The protocol filter is now a dropdown (TCP, UDP, ICMP, …).
v2.53: Trap / syslog for large volumes. log_events is now partitioned per day (run the SQL file on the Update tab; it rebuilds the table once) so retention (1-3650 days, setting Keep events for) removes whole days instantly instead of deleting rows; fewer indexes; parsed firewall lines store only the fields (original line optional) which roughly halves the size. The receiver creates the daily partitions itself and uses a 16 MB receive buffer. Flood limit default is now 6000 per sender per minute, 0 = no limit, and dropped lines are shown as a warning. The event list counts up to 10 000, defaults to the last 24 hours and pages with Newer/Older. New Storage box under Settings > Trap / syslog (rows, size, estimate for the retention).
v2.54: Fix: the SQL migration of v2.53 failed on MariaDB (syntax error before PARTITION BY). Corrected; it can be run again safely.
v2.55: Syslog over TCP in the receiver (Settings → Trap / syslog): optional TCP listener (default off, default port 514), newline and octet-counting framing (RFC 6587), up to 500 connections, idle connections closed after 30 min. Same parsing, filtering, rate limit and storage as UDP. No TLS. No SQL changes.
v2.56: Daily firewall summary. The receiver counts pass/block per day, device, source, destination, protocol/port and rule in the new table log_fw_daily (counted even when 'do not store pass' is on, and independent of raw-log retention). New page History → Firewall summary with per-day totals and top sources/destinations/ports/rules, linked to the raw log. New setting 'Keep daily summary (days)' (default 730). SQL: sql/3.05.sql adds log_fw_daily. Counting starts when the update is installed.
v2.57: Trap/syslog history: new filters 'Rows' (firewall traffic only / other log lines such as DHCP and system) and 'Program'; the program name in each non-firewall row is clickable to show only that program. The summary page text no longer lists vendors. No SQL changes.
v2.58: More firewall log formats parsed into the firewall fields, filters and daily summary: Cisco ASA/FTD (messages 302013/302015, 106023, 106100, 106001/106006/106015), Palo Alto (CSV TRAFFIC/THREAT), Fortinet FortiGate and Sophos XG/UTM (key=value), and more Ubiquiti variants (UniFi/EdgeRouter iptables prefixes, rule names such as WAN_IN-3000-D). Written from the vendors' documented formats, not tested against real devices. No SQL changes.
v2.59: Trap/syslog history: the Source column now always shows the sender IP (plus the clickable program name), instead of switching between IP and host name depending on whether the row was stored compactly; the firewall filter heading is now just 'Syslog' without the vendor list. No SQL changes.
v2.60: Alert rules: new system rule 'Trap/syslog receiver has stopped' (checks the vmmon-recv heartbeat), and the rule type 'Trap/syslog event matches' can now count per sender: group by same source IP or same destination IP, and count events, distinct destination ports (port scan), distinct destination IPs or distinct source IPs (e.g. many blocks / port scan from one IP within X minutes). Ready-made templates (many blocks from one IP, port scan, brute force SSH/RDP, link down, failed logins) fill in the fields. Grouping works on parsed firewall lines. No SQL changes.
v2.61: Fix: saving the alert rule 'Trap/syslog receiver has stopped' failed (500) because the metric column is an ENUM; sys_recv is added to it by sql/3.05.sql (run the update so the SQL file is executed). No other changes.
v2.62: Alert rules can be organised in groups: 'New group', drag a rule by its handle onto another group's header (saved immediately), collapse/expand groups (remembered per browser), rename/delete/reorder groups, and choose the group in the rule form (also works on touch screens). Clicking a column header sorts the rules within each group. Rules without a group are shown under 'Ungrouped'. SQL: sql/3.05.sql adds the table alert_rule_groups and alert_rules.group_id. The column sorting script (tsort.js) now treats rows with class grph as group separators.
v2.63: Alert rule groups: no more browser popups (prompt/confirm) – new group, rename and delete use inline fields in the page. Menu: new main page 'Syslog' after Services (log lines with firewall filters, and the firewall summary as a second tab); History → 'Trap' now shows only SNMP traps. 'Device groups' moved from the main menu to Settings (tab after Notifications). No SQL changes.
v2.64: Custom dialog boxes replace the browser's confirm()/alert() popups everywhere (delete device group, delete rule/user/server/backup, clear log, dashboard card removal, error messages…). New assets/dlg.js, loaded on every page; it works with both inline onsubmit/onclick confirms and script calls. Esc/click outside cancels. No SQL changes.
v2.65: Fix: trap/syslog events were stamped one hour too early. The receiver runs in command-line PHP, whose time zone is UTC unless php.ini for the CLI says otherwise, while the web pages use the web server's PHP time zone. The web UI now saves its time zone (setting app_tz) and the receiver uses it. Events stored before this update keep their old (UTC) time stamps. No SQL changes.
v2.66: The worker (and its poller processes) and the Zabbix active listener now use the web UI's time zone too (setting app_tz, see v2.65). Before, they ran in the command-line PHP time zone (often UTC), so alert schedules (rule_in_schedule), 'since' times in alert messages and some event time stamps were evaluated an hour off compared with the web pages. No SQL changes.
v2.67: The database connection now sets its session time zone (SET time_zone) to the PHP/web time zone, so NOW(), CURRENT_TIMESTAMP and PHP's date() agree in every process (web, worker, receiver, Zabbix listener). Before, MariaDB used the system time zone (UTC on many servers) while PHP wrote local time into some columns, which skewed durations and showed some times an hour off. Re-applied automatically at daylight-saving changes. One-time effect right after the update: DATETIME values stored earlier with NOW() are still in the old time zone, so for a short while durations of already open alerts/events can be one hour too long and some 'last seen' ages look an hour old until the next poll. No SQL changes.

v2.68: New setting Settings > General > Time zone (tz_pref). Web, worker, receiver and the database session all use it, so times no longer depend on php.ini (often UTC). Shows PHP and database time for checking, plus a button to use the browser time zone. app_tz_sync() also runs when lib.php loads.

v2.69: Time zone field moved below the 24h clock option in Settings > General; removed the clock line and browser button.

v2.70: Syslog parser understands switch/router formats: Cisco IOS (sequence number, origin, *timestamp, %FAC-SEV-MNEM), HP/Aruba (ProCurve, AOS-CX), Huawei (%%01MOD/SEV/NAME(l)[n]), Dell, Juniper, MikroTik topics (system,info,account), timestamps with year/ms/time zone, "YYYY-MM-DD HH:MM:SS". Program column shows the message code/topic, host is picked up when present.

v2.71: Syslog/Trap device filter lists only devices the receiver has received events from (setting log_devs, kept by the receiver, first filled from the last 24 h); "Unknown sender" only if such events exist. Trap values: 4-byte octet strings shown with IPv4, 8/11-byte ones with DateAndTime when plausible.

v2.72: HP/Aruba trap names (HP-SWITCH-BASIC-CONFIG-MIB): hpSwitchRunningConfigChange, hpSwitchStartupConfigChange and their varbinds (user, source IP, event time, sequence); address type and throttled values shown as text.

v2.73: MIB upload (Settings > MIB): .mib/.my/.txt or .zip; parses OBJECT-TYPE/NOTIFICATION-TYPE/TRAP-TYPE/OID nodes and INTEGER enums (also via TEXTUAL-CONVENTION), resolves across files, lists missing imported MIBs, lookup by name/OID. Trap messages are decoded at display time (header + one line per varbind, names from built-in table and MIBs, hex IPs/dates, enum text), so old events become readable too. New tables mib_files, mib_names (run sql/3.05.sql).

v2.74: MIB parser skips OBJECT-GROUP/NOTIFICATION-GROUP/MODULE-COMPLIANCE/AGENT-CAPABILITIES (no own value; also fixes block boundaries). Tested on 436 vendor MIB files (LibreNMS MIB collection): parse 0.7 s, ~34k names.

v2.75: MIB modules that only define types/nodes (no objects, e.g. IPV6-TC) are stored too, so imports from them count as satisfied; RFC-1213 alias ignored in the missing list.

v2.76: RFC1315-MIB (obsolete Frame Relay MIB, not available anywhere) is ignored in the missing-MIB list.

v2.77: MIB page: lookup box moved above the file list; the file list is collapsible.

v2.78: Log alert rule templates: configuration change (HP/Cisco traps and syslog), successful login, Windows event IDs (4625 failed logon, 4728/4732/4756 group change, 4720 user created, 1102 audit log cleared, 7036 service stopped; needs the Windows event log forwarded as syslog).

v2.79: Windows sessions: SNMP polling also reads logged-on user names (LAN Manager svUserName) on Windows hosts; shown on the server page; new alert rule "Logged-on users (count, at least)" (metric users, added to the alert_rules ENUM in sql/3.05.sql); RDP logon template for log rules.

v2.80: Alert rule form: group dropdown narrower with space above the buttons.

v2.81: Bundled MIB set (mibs/seed.jsonl.gz, 1468 modules / ~160 000 names: standard, Cisco, HP/Aruba/Comware, Huawei, Fortinet, Palo Alto, Sophos, UniFi/Ubiquiti, MikroTik, Meraki, Juniper, Dell, Windows, NAS/UPS and more). Loaded once by the worker on first run (setting mib_seeded) and via the button on Settings > MIB; modules you uploaded yourself are not overwritten. MIB lookups now query mib_names per OID (cached) instead of loading the whole table.
v2.82: Alert rules can now have an optional custom name (new column alert_rules.name, run sql/3.05.sql). The name is shown in the rules list, in notification subjects/messages, alert history and webhook rule_name; empty = automatic name as before.
v2.82 fix: the old metric ENUM ALTER inside sql/3.05.sql lacked log_event/users and failed on databases that already had such rules (Data truncated); both ALTERs now use the full list. The file is safe to re-run.
v2.83: Re-release of 2.82 with the fixed sql/3.05.sql (version bumped so the updater accepts it on an install that already shows 2.82).
v2.84: History → Trap tab now has the Export button. In History → Alerts, IP addresses in log-rule alert messages are links that open the syslog or trap list filtered on that IP (kind looked up from the stored events, syslog by default).
v2.85: IP links in History → Alerts use the From IP (src) filter for firewall lines, so the syslog list actually shows the matches (the free-text search does not cover firewall fields).
v2.86: db() now checks the connection every 5 s and reconnects, so the long-running services (recv, worker, Zabbix listener) recover on their own after a MariaDB restart instead of keeping a dead connection (which made the heartbeat stop and the settings page claim the receiver was not running).
v2.87: The worker, Zabbix listener and trap/syslog receiver status lines in Settings refresh themselves every 10 s (previously only at page load, so an open page kept showing green after a service stopped).
v2.88: setting() keeps the last successfully read settings when the database cannot be read, instead of falling back to defaults for everything. Before, a database hiccup made the Zabbix/trap listeners think they were switched off and close their ports.
v2.89: Open pages pick up a changed theme on their own (checks theme.php every 15 s while the tab is visible), so a theme change made on one computer shows on a dashboard left open on another without F5.
v2.90: Dashboard services card no longer prints the full URL for URL checks (the type HTTPS/HTTP/Cert is shown; the full address is the tooltip).
v2.91: Meraki devices show 'Meraki <model>' (e.g. Meraki CW9162I) instead of the full sysDescr; switches look up the model from sysDescr, the ENTITY-MIB or the Meraki cloud device list when SNMP only says 'Meraki Switch'. The device name (sysName) is refreshed on every poll instead of every slow round (15 min). Dashboard headlines can be up to 8 cards wide (capped to the number of columns on smaller screens).
v2.92: Edit forms for devices (network, storage, BMC, hosts, servers) no longer stretch every input to the height of the comment box (align-items:flex-end).
v2.93: Meraki devices with a cached generic/old model ('Meraki Switch', '… Cloud Managed …') are re-read on the next poll instead of waiting for the 15-minute slow round, so the model fix from 2.91 shows up immediately after an update.
v2.94: Certificate checks in the dashboard services card show the days left (e.g. '52 d') in the same pill style as response times; yellow under 30 days, red under 14.
v2.95: Certificate thresholds in Settings → General (Critical below / Warning below, in days left, defaults 14 and 30). They colour the days-left pill on the dashboard and the SSL check itself goes down below the critical limit (was fixed at 14 days).
v2.96: Update page: 'Check GitHub for an update' – enter owner/repo, the latest release zip (Vmmon_*.zip) is downloaded and shown for confirmation, then installed with the normal backup + SQL migration flow.
v2.97: Dashboard: critical cards are shown at the top of their column under the headline (display only – the saved layout is untouched) and go back when healthy. Compact cards get a small (!) button to ignore/stop ignoring warning/critical.
v2.98: Critical cards under a headline that is 2+ columns wide take the first slots under it in reading order (row by row from the left); the card that sat there swaps places with the critical one. 1-column headlines work as before.
v2.99: My profile → Copy dashboard layout: copy another user's layout (normal→normal or compact→compact only; layout 1–3 to any of your own layouts, headlines, graph cards, positions, order and hidden cards included). Available to read-only users too.
v3.00: My profile: the Copy dashboard layout section is full width below the other site settings with its fields in one row.
v3.01: Removed the explanatory text above the Copy dashboard layout form.
v3.02: The GitHub update field on the Update page is pre-filled with pellle87/vmmon.
v3.03: The Update page no longer asks for a GitHub repository – the Update button always checks the official repository (pellle87/vmmon). The background worker checks GitHub twice a day and the Update page can send an alert (e-mail, Telegram, webhook, Signal, SMS) once when a new version is available.
v3.04: Update page: deleting a backup now says "Backup deleted" instead of "Restore complete" and no longer shows the restart note.
v3.05: Fixed a syntax error in update.php introduced in 3.04.
