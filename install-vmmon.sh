#!/usr/bin/env bash
# ============================================================================
#  VM Monitor (vmmon) – automatiskt installationsskript
#  Stöd: Debian 11/12/13 och Ubuntu 22.04/24.04 (apt). Kör som root.
#
#  Gör:  installerar beroenden, konfigurerar MariaDB/MySQL, PHP-FPM och
#        Apache eller Nginx (prestanda + säkerhet), laddar ner och packar upp
#        vmmon.zip, skapar databas/användare, config.local.php, secret.php,
#        systemd-tjänsten vmmon-worker, HTTPS (Let's Encrypt/självsignerat).
#
#  Användning:
#     sudo bash install-vmmon.sh              (frågar efter allt)
#     sudo bash install-vmmon.sh --yes        (oövervakat, använder miljövariabler/standardvärden)
#
#  Miljövariabler (hoppar över motsvarande fråga):
#     VMMON_ZIP        URL eller sökväg till vmmon.zip
#     VMMON_DOMAIN     domän/IP som sidan nås på
#     VMMON_WEB        apache | nginx
#     VMMON_DB         mariadb | mysql
#     VMMON_DIR        installationskatalog (standard /var/www/vmmon)
#     VMMON_DB_ROOT_PASS, VMMON_DB_NAME, VMMON_DB_USER, VMMON_DB_PASS
#     VMMON_TLS        none | letsencrypt | selfsigned
#     VMMON_EMAIL      e-post för Let's Encrypt
#     VMMON_PROXIES    IP-adresser till omvänd proxy (komma-separerade), valfritt
#     VMMON_TZ         tidszon, t.ex. Europe/Stockholm
#     VMMON_PHP        PHP-version som installeras från Ondřej Surýs paketkälla (standard 8.5),
#                      eller 'distro' för att använda distributionens egen PHP
#     VMMON_ZABBIX     yes | no   installera tjänsten vmmon-zabbix för Zabbix-agenter i active-läge
#                      (standard no; frågas alltid om utom med --yes)
#     VMMON_ZABBIX_FW  yes | no   öppna port 10051/tcp i ufw för den (standard no)
# ============================================================================
set -Eeuo pipefail

VMMON_PHP=${VMMON_PHP:-8.5}
YES=0
for a in "$@"; do case "$a" in -y|--yes) YES=1 ;; -h|--help) sed -n '2,31p' "$0"; exit 0 ;; esac; done

# ---------- utskrift ----------
if [[ -t 1 ]]; then B=$'\e[1m'; G=$'\e[32m'; Y=$'\e[33m'; R=$'\e[31m'; C=$'\e[36m'; N=$'\e[0m'; else B=;G=;Y=;R=;C=;N=; fi
info(){ printf '%s\n' "${C}➜${N} $*"; }
ok(){   printf '%s\n' "${G}✔${N} $*"; }
warn(){ printf '%s\n' "${Y}!${N} $*" >&2; }
die(){  printf '%s\n' "${R}✖ $*${N}" >&2; exit 1; }
hdr(){  printf '\n%s\n' "${B}== $* ==${N}"; }
trap 'die "Installationen avbröts på rad $LINENO (kommando: $BASH_COMMAND)"' ERR

TMPD=""; MYCNF=""
cleanup(){ [[ -n $MYCNF && -f $MYCNF ]] && rm -f "$MYCNF"; [[ -n $TMPD && -d $TMPD ]] && rm -rf "$TMPD"; return 0; }
trap cleanup EXIT

# ---------- hjälpfunktioner ----------
ask(){ # ask VAR "fråga" "standard"
  local var=$1 prompt=$2 def=${3:-} ans=""
  [[ -n ${!var:-} ]] && return 0
  if (( YES )); then printf -v "$var" '%s' "$def"; return 0; fi
  read -r -p "  $prompt${def:+ [$def]}: " ans || true
  printf -v "$var" '%s' "${ans:-$def}"
}
choose(){ # choose VAR "fråga" standard val1 val2 ...
  local var=$1 prompt=$2 def=$3; shift 3
  local opts=("$@") ans=""
  if [[ -n ${!var:-} ]]; then return 0; fi
  if (( YES )); then printf -v "$var" '%s' "$def"; return 0; fi
  while :; do
    read -r -p "  $prompt (${opts[*]}) [$def]: " ans || true; ans=${ans:-$def}
    for o in "${opts[@]}"; do [[ $ans == "$o" ]] && { printf -v "$var" '%s' "$ans"; return 0; }; done
    warn "Välj ett av: ${opts[*]}"
  done
}
genpw(){ head -c 96 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | cut -c1-"${1:-24}"; }
ask_secret(){ # ask_secret VAR "fråga"   (tomt = slumpa)
  local var=$1 prompt=$2 a="" b=""
  [[ -n ${!var:-} ]] && return 0
  if (( YES )); then printf -v "$var" '%s' "$(genpw)"; return 0; fi
  while :; do
    read -r -s -p "  $prompt (tomt = slumpa ett starkt lösenord): " a || true; echo
    if [[ -z $a ]]; then printf -v "$var" '%s' "$(genpw)"; GENERATED+=("$var"); return 0; fi
    read -r -s -p "  Upprepa lösenordet: " b || true; echo
    [[ $a == "$b" ]] || { warn "Lösenorden matchar inte."; continue; }
    (( ${#a} >= 8 )) || { warn "Minst 8 tecken."; continue; }
    printf -v "$var" '%s' "$a"; return 0
  done
}
GENERATED=()
sql_esc(){ local s=$1; s=${s//\\/\\\\}; s=${s//\'/\\\'}; printf '%s' "$s"; }
php_esc(){ local s=$1; s=${s//\\/\\\\}; s=${s//\'/\\\'}; printf '%s' "$s"; }
ram_mb(){ awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo; }
clamp(){ local v=$1 lo=$2 hi=$3; (( v<lo )) && v=$lo; (( v>hi )) && v=$hi; printf '%d' "$v"; }
pkg_installed(){ dpkg -s "$1" >/dev/null 2>&1; }
pkg_available(){ apt-cache policy "$1" 2>/dev/null | awk '/Candidate:/ {print $2}' | grep -vq '(none)'; }
apt_i(){ DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends "$@" >/dev/null; }

# ---------- förkontroller ----------
preflight(){
  [[ $EUID -eq 0 ]] || die "Kör skriptet som root (sudo bash $0)."
  [[ -r /etc/os-release ]] || die "Hittar inte /etc/os-release."
  # shellcheck disable=SC1091
  . /etc/os-release
  case "${ID:-}${ID_LIKE:-}" in *debian*|*ubuntu*) ;; *) die "Bara Debian/Ubuntu (apt) stöds av detta skript. Din dist: ${PRETTY_NAME:-okänd}." ;; esac
  command -v apt-get >/dev/null || die "apt-get saknas."
  OSNAME=${PRETTY_NAME:-$ID}
  ok "System: $OSNAME, RAM: $(ram_mb) MB"
}

# ---------- frågor ----------
gather(){
  hdr "Inställningar"
  local defdom; defdom=$(hostname -f 2>/dev/null || hostname)
  ask VMMON_DOMAIN "Domän eller IP-adress som VM Monitor nås på" "$defdom"
  ask VMMON_ZIP "Nedladdningslänk (URL) eller sökväg till vmmon.zip" ""
  [[ -n $VMMON_ZIP ]] || die "Ingen källa angiven för vmmon.zip."
  choose VMMON_WEB "Webbserver" apache apache nginx
  local defdb=mariadb; pkg_installed mysql-server && defdb=mysql
  choose VMMON_DB "Databasserver" "$defdb" mariadb mysql
  ask VMMON_DIR "Installationskatalog" "/var/www/vmmon"
  ask VMMON_DB_NAME "Databasnamn" "vmmon"
  ask VMMON_DB_USER "Databasanvändare för vmmon" "vmmon"
  ask_secret VMMON_DB_PASS "Lösenord för databasanvändaren '$VMMON_DB_USER'"
  ask_secret VMMON_DB_ROOT_PASS "Lösenord för databasens root-användare (nytt, eller befintligt om det redan är satt)"
  choose VMMON_TLS "HTTPS" none none letsencrypt selfsigned
  if [[ $VMMON_TLS == letsencrypt ]]; then
    ask VMMON_EMAIL "E-postadress för Let's Encrypt (förnyelsevarningar)" ""
    [[ -n $VMMON_EMAIL ]] || die "E-post krävs för Let's Encrypt."
    [[ $VMMON_DOMAIN =~ ^[0-9.]+$ ]] && die "Let's Encrypt kräver ett domännamn, inte en IP-adress."
  fi
  ask VMMON_PROXIES "IP-adress(er) till omvänd proxy framför servern, t.ex. Nginx Proxy Manager (komma-separerade, tomt = ingen)" ""
  local deftz; deftz=$(timedatectl show -p Timezone --value 2>/dev/null || echo UTC)
  ask VMMON_TZ "Tidszon för PHP" "$deftz"
  # Zabbix active-läge: frågas alltid (utan --yes), standard är nej. Finns tjänsten redan (uppgradering) behålls den utan fråga.
  if [[ -z ${VMMON_ZABBIX:-} && -f /etc/systemd/system/vmmon-zabbix.service ]]; then VMMON_ZABBIX=yes; fi
  if [[ -z ${VMMON_ZABBIX:-} ]] && ! (( YES )); then
    echo
    echo "  Zabbix-agenter i active-läge: agenten ansluter då till VM Monitor (port 10051) i stället för tvärtom."
    echo "  Det kräver en extra tjänst, vmmon-zabbix. Den lyssnar inte på något förrän du slår på funktionen"
    echo "  under Inställningar → Zabbix, och den kan även larma om bakgrundstjänsten (worker) stannar."
  fi
  choose VMMON_ZABBIX "Installera tjänsten vmmon-zabbix (Zabbix active-läge)?" no no yes
  if [[ $VMMON_ZABBIX == yes ]] && command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q '^Status: active'; then
    choose VMMON_ZABBIX_FW "ufw är aktiv. Öppna port 10051/tcp för Zabbix-agenterna?" no no yes
  fi
  VMMON_ZABBIX_FW=${VMMON_ZABBIX_FW:-no}
  [[ $VMMON_WEB == apache || $VMMON_WEB == nginx ]] || die "VMMON_WEB måste vara apache eller nginx."
  [[ $VMMON_DB == mariadb || $VMMON_DB == mysql ]] || die "VMMON_DB måste vara mariadb eller mysql."
  [[ $VMMON_TLS == none || $VMMON_TLS == letsencrypt || $VMMON_TLS == selfsigned ]] || die "VMMON_TLS måste vara none, letsencrypt eller selfsigned."
  [[ $VMMON_ZABBIX == yes || $VMMON_ZABBIX == no ]] || die "VMMON_ZABBIX måste vara yes eller no."
  [[ $VMMON_ZABBIX_FW == yes || $VMMON_ZABBIX_FW == no ]] || die "VMMON_ZABBIX_FW måste vara yes eller no."
  [[ $VMMON_DIR == /* && $VMMON_DIR != "/" ]] || die "Ogiltig installationskatalog."
  [[ $VMMON_DB_NAME =~ ^[A-Za-z0-9_]+$ ]] || die "Databasnamnet får bara innehålla A–Z, 0–9 och _."
  [[ $VMMON_DB_USER =~ ^[A-Za-z0-9_]+$ ]] || die "Databasanvändaren får bara innehålla A–Z, 0–9 och _."
  [[ $VMMON_DOMAIN =~ ^[A-Za-z0-9.:_-]+$ ]] || die "Ogiltig domän/adress."
  if ! (( YES )); then
    echo; echo "  Sammanfattning:"
    echo "    Adress:      $VMMON_DOMAIN ($VMMON_TLS)"
    echo "    Webbserver:  $VMMON_WEB      Databas: $VMMON_DB ($VMMON_DB_NAME / $VMMON_DB_USER)"
    echo "    Katalog:     $VMMON_DIR"
    echo "    Källa:       $VMMON_ZIP"
    echo "    Zabbix active-tjänst (vmmon-zabbix): $VMMON_ZABBIX"
    read -r -p "  Fortsätta? [J/n]: " go || true
    [[ ${go:-J} =~ ^[JjYy]$ ]] || die "Avbrutet av användaren."
  fi
}

# ---------- PHP-paketkälla (Ondřej Surý) ----------
# Debian och Ubuntu ligger ofta efter med PHP, så standard är att lägga till Ondřej Surýs paketkälla och installera PHP 8.5.
add_php_repo(){
  [[ $VMMON_PHP == distro ]] && return 0
  [[ $VMMON_PHP =~ ^8\.[0-9]+$ ]] || die "VMMON_PHP måste vara en version som 8.5, eller 'distro'."
  hdr "PHP $VMMON_PHP (paketkälla från Ondřej Surý)"
  apt_i ca-certificates curl lsb-release gnupg
  if [[ ${ID:-} == ubuntu ]]; then
    apt_i software-properties-common
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || die "Kunde inte lägga till ppa:ondrej/php (ingen internetåtkomst till launchpad.net?). Sätt VMMON_PHP=distro för att använda distributionens PHP."
  else
    local kd; kd=$(mktemp -d)
    curl -fsSL --retry 3 --connect-timeout 15 -o "$kd/k.deb" https://packages.sury.org/debsuryorg-archive-keyring.deb || die "Kunde inte hämta nyckeln från packages.sury.org. Sätt VMMON_PHP=distro för att använda distributionens PHP."
    dpkg -i "$kd/k.deb" >/dev/null; rm -rf "$kd"
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
  fi
  DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null
  pkg_available "php$VMMON_PHP-cli" || die "php$VMMON_PHP-cli finns inte i paketkällan för din distribution. Välj en annan version i VMMON_PHP eller sätt VMMON_PHP=distro."
  ok "Paketkälla tillagd"
}

# ---------- paket ----------
install_packages(){
  hdr "Installerar paket"
  info "Uppdaterar paketlistor…"
  DEBIAN_FRONTEND=noninteractive apt-get update -qq >/dev/null
  add_php_repo
  local pk=(ca-certificates curl unzip openssl iputils-ping fping snmp)
  if [[ $VMMON_PHP == distro ]]; then pk+=(php-cli php-fpm php-mysql php-curl php-snmp php-mbstring php-xml php-zip)
  else local v=$VMMON_PHP; pk+=("php$v-cli" "php$v-fpm" "php$v-mysql" "php$v-curl" "php$v-snmp" "php$v-mbstring" "php$v-xml" "php$v-zip"); fi
  [[ $VMMON_DB == mariadb ]] && pk+=(mariadb-server mariadb-client) || pk+=(mysql-server mysql-client)
  if [[ $VMMON_DB == mysql ]] && ! pkg_available mysql-server; then die "mysql-server finns inte i din dists paketkälla (Debian har bara MariaDB). Välj mariadb."; fi
  [[ $VMMON_WEB == apache ]] && pk+=(apache2) || pk+=(nginx)
  [[ $VMMON_TLS == letsencrypt ]] && { [[ $VMMON_WEB == apache ]] && pk+=(certbot python3-certbot-apache) || pk+=(certbot python3-certbot-nginx); }
  info "Installerar: ${pk[*]}"
  apt_i "${pk[@]}"
  if [[ $VMMON_PHP != distro ]]; then update-alternatives --set php "/usr/bin/php$VMMON_PHP" >/dev/null 2>&1 || true; fi
  PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
  php -r 'exit(PHP_MAJOR_VERSION>=8?0:1);' || die "PHP $PHPV är för gammalt – VM Monitor kräver PHP 8+. Låt skriptet lägga till Ondřej Surýs paketkälla (ta bort VMMON_PHP=distro)."
  for m in pdo_mysql curl openssl snmp mbstring; do php -m | grep -qix "$m" || die "PHP-tillägget $m saknas."; done
  ok "PHP $PHPV med alla nödvändiga tillägg"
  WEBUSER=www-data
  getent passwd "$WEBUSER" >/dev/null || die "Användaren $WEBUSER saknas."
}

# ---------- databas ----------
db_service(){ if systemctl list-unit-files 2>/dev/null | grep -q '^mariadb.service'; then echo mariadb; else echo mysql; fi; }
dbx(){ if [[ -n $MYCNF ]]; then mysql --defaults-extra-file="$MYCNF" "$@"; else mysql -uroot "$@"; fi; }

configure_db(){
  hdr "Databas ($VMMON_DB)"
  local mem svc bp mc; mem=$(ram_mb); svc=$(db_service)
  bp=$(clamp $(( mem / 4 )) 128 4096)
  mc=$(clamp $(( mem / 8 )) 60 300)
  install -d /etc/mysql/conf.d
  cat > /etc/mysql/conf.d/99-vmmon.cnf <<EOF
# Skapad av install-vmmon.sh – prestanda och säkerhet för VM Monitor
[mysqld]
bind-address                   = 127.0.0.1
skip-name-resolve
character-set-server           = utf8mb4
collation-server               = utf8mb4_unicode_ci
innodb_buffer_pool_size        = ${bp}M
# Hela övervakningen skriver ofta; 2 = fsync en gång per sekund i stället för per transaktion
# (vid strömavbrott kan max ca 1 s data gå förlorad, databasen förblir konsistent)
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table          = 1
max_connections                = ${mc}
local_infile                   = 0
EOF
  systemctl enable --now "$svc" >/dev/null 2>&1 || true
  systemctl restart "$svc"
  local i; for i in $(seq 1 30); do mysqladmin -uroot status >/dev/null 2>&1 && break; mysqladmin --defaults-extra-file=/dev/null ping >/dev/null 2>&1 && break; sleep 1; done

  # Root-lösenord: försök via socket (nyinstallerad server), annars med angivet lösenord
  MYCNF=$(mktemp); chmod 600 "$MYCNF"
  local cp=${VMMON_DB_ROOT_PASS//\\/\\\\}; cp=${cp//\"/\\\"}
  printf '[client]\nuser=root\npassword="%s"\n' "$cp" > "$MYCNF"
  local rp; rp=$(sql_esc "$VMMON_DB_ROOT_PASS")
  if mysql -uroot -e 'SELECT 1' >/dev/null 2>&1; then
    info "Sätter root-lösenord och härdar databasen…"
    if mysql --version | grep -qi mariadb; then
      mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA unix_socket OR mysql_native_password USING PASSWORD('$rp');"
    else
      mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '$rp';"
    fi
  elif mysql --defaults-extra-file="$MYCNF" -e 'SELECT 1' >/dev/null 2>&1; then
    info "Databasens root-lösenord stämde – hoppar över ändring."
  else
    die "Kan inte logga in som databas-root (varken via socket eller med angivet lösenord)."
  fi
  dbx -e "DROP USER IF EXISTS ''@'localhost'; DROP USER IF EXISTS ''@'$(hostname)'; DROP DATABASE IF EXISTS test; FLUSH PRIVILEGES;" 2>/dev/null || true

  local un pw; un=$(sql_esc "$VMMON_DB_USER"); pw=$(sql_esc "$VMMON_DB_PASS")
  dbx <<SQL
CREATE DATABASE IF NOT EXISTS \`$VMMON_DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$un'@'localhost' IDENTIFIED BY '$pw';
ALTER USER '$un'@'localhost' IDENTIFIED BY '$pw';
GRANT ALL PRIVILEGES ON \`$VMMON_DB_NAME\`.* TO '$un'@'localhost';
FLUSH PRIVILEGES;
SQL
  ok "Databas '$VMMON_DB_NAME' och användare '$VMMON_DB_USER' klara (buffer pool ${bp} MB, max ${mc} anslutningar)"
}

import_schema(){
  local have; have=$(dbx -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$VMMON_DB_NAME' AND table_name='users'")
  if [[ $have -ge 1 ]]; then
    warn "Databasen har redan tabeller – sql/schema.sql importeras inte. Vid uppgradering: kör den senaste sql/N.NN.sql mot databasen (se README.txt)."
    return 0
  fi
  [[ -f $VMMON_DIR/sql/schema.sql ]] || die "sql/schema.sql saknas i $VMMON_DIR."
  head -2 "$VMMON_DIR/sql/schema.sql" | grep -qi 'CREATE DATABASE' || die "Oväntat format på sql/schema.sql (rad 1 ska vara CREATE DATABASE)."
  tail -n +3 "$VMMON_DIR/sql/schema.sql" | dbx "$VMMON_DB_NAME"
  ok "Databasschemat importerat"
}

# ---------- ladda ner och packa upp ----------
fetch_app(){
  hdr "Hämtar VM Monitor"
  TMPD=$(mktemp -d)
  local zip="$TMPD/vmmon.zip"
  if [[ $VMMON_ZIP =~ ^https?:// ]]; then
    info "Laddar ner $VMMON_ZIP"
    curl -fL --retry 3 --connect-timeout 15 -o "$zip" "$VMMON_ZIP" || die "Nedladdningen misslyckades."
  else
    [[ -f $VMMON_ZIP ]] || die "Filen $VMMON_ZIP finns inte."
    cp "$VMMON_ZIP" "$zip"
  fi
  unzip -tq "$zip" >/dev/null || die "Filen är ingen giltig zip (kontrollera länken/licensnyckeln)."
  unzip -q "$zip" -d "$TMPD/x"
  local root="$TMPD/x"
  [[ -f $root/lib.php ]] || { local sub; sub=$(find "$root" -maxdepth 2 -name lib.php -printf '%h\n' | head -1); [[ -n $sub ]] && root=$sub; }
  [[ -f $root/lib.php && -f $root/sql/schema.sql ]] || die "Zip-filen ser inte ut som VM Monitor (lib.php/sql/schema.sql saknas)."
  SRC=$root

  UPGRADE=0
  if [[ -f $VMMON_DIR/lib.php ]]; then
    UPGRADE=1
    local bk="/root/vmmon-backup-$(date +%Y%m%d-%H%M%S).tar.gz"
    info "Befintlig installation hittad – tar backup: $bk"
    tar -czf "$bk" -C "$(dirname "$VMMON_DIR")" "$(basename "$VMMON_DIR")"
  fi
  install -d "$VMMON_DIR"
  # Behåll lokal konfiguration och nyckel vid uppgradering
  cp -a "$SRC"/. "$VMMON_DIR"/
  ok "Filer lagda i $VMMON_DIR"
}

write_config(){
  hdr "Konfiguration"
  local f="$VMMON_DIR/config.local.php"
  if (( UPGRADE )) && [[ -f $f ]]; then
    info "Behåller befintlig config.local.php"
  else
    cat > "$f" <<EOF
<?php // Skapad av install-vmmon.sh
const DB_DSN='mysql:host=localhost;dbname=$(php_esc "$VMMON_DB_NAME");charset=utf8mb4';
const DB_USER='$(php_esc "$VMMON_DB_USER")';
const DB_PASS='$(php_esc "$VMMON_DB_PASS")';
EOF
  fi
  local sf="$VMMON_DIR/secret.php"
  if [[ ! -f $sf ]]; then
    printf "<?php return '%s';\n" "$(head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')" > "$sf"
    ok "Skapade secret.php (krypteringsnyckel – TA BACKUP på den!)"
  fi
  # Rättigheter: koden ägs av root och är bara läsbar för webbanvändaren
  chown -R root:"$WEBUSER" "$VMMON_DIR"
  find "$VMMON_DIR" -type d -exec chmod 750 {} +
  find "$VMMON_DIR" -type f -exec chmod 640 {} +
  chmod 600 "$f" "$sf"; chown "$WEBUSER":"$WEBUSER" "$f" "$sf"
  find "$VMMON_DIR/extras" -name '*.sh' -exec chmod 750 {} + 2>/dev/null || true
  # Mappar som appen själv skriver i: ikonbiblioteket (Inställningar → Ikoner, nedladdning) och backups
  install -d "$VMMON_DIR/assets/chkicons/lib" "$VMMON_DIR/backups"
  chown -R "$WEBUSER":"$WEBUSER" "$VMMON_DIR/assets/chkicons" "$VMMON_DIR/backups"
  find "$VMMON_DIR/assets/chkicons" "$VMMON_DIR/backups" -type d -exec chmod 750 {} +
  find "$VMMON_DIR/assets/chkicons" "$VMMON_DIR/backups" -type f -exec chmod 640 {} +
  ok "Filrättigheter satta (config.local.php/secret.php: endast $WEBUSER)"
}

# ---------- PHP ----------
configure_php(){
  hdr "PHP-FPM $PHPV"
  local mem; mem=$(ram_mb)
  local maxc; maxc=$(clamp $(( mem * 40 / 100 / 40 )) 5 60)
  local secure=0; [[ $VMMON_TLS != none ]] && secure=1
  cat > "/etc/php/$PHPV/mods-available/vmmon.ini" <<EOF
; Skapad av install-vmmon.sh
expose_php = Off
date.timezone = ${VMMON_TZ}
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 4000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 10
realpath_cache_size = 4096K
realpath_cache_ttl = 600
EOF
  phpenmod -v "$PHPV" vmmon
  # Egen pool så att övriga sajter på servern inte påverkas
  local pool="/etc/php/$PHPV/fpm/pool.d/vmmon.conf"
  cat > "$pool" <<EOF
; Skapad av install-vmmon.sh
[vmmon]
user = $WEBUSER
group = $WEBUSER
listen = /run/php/vmmon.sock
listen.owner = $WEBUSER
listen.group = $WEBUSER
listen.mode = 0660
pm = dynamic
pm.max_children = $maxc
pm.start_servers = $(clamp $(( maxc / 4 )) 2 10)
pm.min_spare_servers = 2
pm.max_spare_servers = $(clamp $(( maxc / 2 )) 4 20)
pm.max_requests = 500
request_terminate_timeout = 120s
catch_workers_output = yes
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 60
php_admin_value[upload_max_filesize] = 8M
php_admin_value[post_max_size] = 10M
php_admin_flag[display_errors] = off
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php${PHPV}-fpm-vmmon.log
php_admin_flag[allow_url_include] = off
php_admin_flag[session.use_strict_mode] = on
php_admin_flag[session.cookie_httponly] = on
php_admin_value[session.cookie_samesite] = Lax
; session.cookie_secure sätts inte här: appen väljer själv per anrop (https → secure), annars går det inte att logga in via http://localhost på servern själv
; exec() behövs för ping; övriga skalfunktioner stängs av för webben (worker/CLI påverkas inte)
php_admin_value[disable_functions] = passthru,shell_exec,system,proc_open,popen,pcntl_exec,show_source,highlight_file
EOF
  php-fpm"$PHPV" -t >/dev/null 2>&1 || die "PHP-FPM-konfigurationen är ogiltig (php-fpm$PHPV -t)."
  systemctl enable --now "php${PHPV}-fpm" >/dev/null 2>&1 || true
  systemctl restart "php${PHPV}-fpm"
  # Loggsidan läser journalctl via PHP
  usermod -aG systemd-journal "$WEBUSER" 2>/dev/null || true
  ok "PHP-FPM-pool 'vmmon' (max $maxc processer), opcache, härdning"
}

# ---------- webbserver ----------
DENY_RE='^/(config|config\.local|secret|lib|checks_lib|lang|worker|recv|recv_lib|zbx-active|zbx_active_lib)\.php$'
write_apache(){
  hdr "Apache"
  a2dismod -q mpm_prefork "php${PHPV}" 2>/dev/null || true
  a2enmod -q mpm_event proxy proxy_fcgi setenvif headers rewrite deflate expires >/dev/null 2>&1 || true
  [[ $VMMON_TLS != none ]] && a2enmod -q ssl http2 >/dev/null 2>&1 || true
  cat > /etc/apache2/conf-available/vmmon-security.conf <<'EOF'
ServerTokens Prod
ServerSignature Off
TraceEnable Off
EOF
  a2enconf -q vmmon-security >/dev/null 2>&1 || true
  local vh=/etc/apache2/sites-available/vmmon.conf
  local common
  common=$(cat <<EOF
  ServerName $VMMON_DOMAIN
  DocumentRoot $VMMON_DIR
  DirectoryIndex index.php
  <Directory $VMMON_DIR>
    Options -Indexes -MultiViews +FollowSymLinks
    AllowOverride None
    Require all granted
  </Directory>
  <FilesMatch "\.php\$">
    SetHandler "proxy:unix:/run/php/vmmon.sock|fcgi://localhost"
  </FilesMatch>
  # Filer som aldrig ska nås utifrån
  <LocationMatch "^/(config|config\.local|secret|lib|checks_lib|lang|worker|recv|recv_lib|zbx-active|zbx_active_lib)\.php\$">
    Require all denied
  </LocationMatch>
  <FilesMatch "\.(sql|txt|sh|service|example|md|log|bak)\$">
    Require all denied
  </FilesMatch>
  <LocationMatch "^/extras/">
    Require all denied
  </LocationMatch>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "same-origin"
  <Location "/assets/">
    Header set Cache-Control "public, max-age=300"
  </Location>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
  LimitRequestBody 10485760
  ErrorLog \${APACHE_LOG_DIR}/vmmon-error.log
  CustomLog \${APACHE_LOG_DIR}/vmmon-access.log combined
EOF
)
  if [[ $VMMON_TLS == selfsigned ]]; then
    make_selfsigned
    cat > "$vh" <<EOF
<VirtualHost *:80>
  ServerName $VMMON_DOMAIN
  RewriteEngine On
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>
<VirtualHost *:443>
$common
  Protocols h2 http/1.1
  SSLEngine on
  SSLCertificateFile /etc/ssl/vmmon/vmmon.crt
  SSLCertificateKeyFile /etc/ssl/vmmon/vmmon.key
  SSLProtocol -all +TLSv1.2 +TLSv1.3
</VirtualHost>
EOF
  else
    cat > "$vh" <<EOF
<VirtualHost *:80>
$common
</VirtualHost>
EOF
  fi
  [[ $WEB_FRESH == 1 ]] && a2dissite -q 000-default >/dev/null 2>&1 || true
  a2ensite -q vmmon >/dev/null
  apache2ctl configtest >/dev/null 2>&1 || { apache2ctl configtest; die "Apache-konfigurationen är ogiltig."; }
  systemctl enable --now apache2 >/dev/null 2>&1 || true
  systemctl reload apache2 || systemctl restart apache2
  ok "Apache klar (event MPM + PHP-FPM)"
}

write_nginx(){
  hdr "Nginx"
  local snippet='
  root '"$VMMON_DIR"';
  index index.php;
  client_max_body_size 10m;
  server_tokens off;
  add_header X-Content-Type-Options "nosniff" always;
  add_header X-Frame-Options "SAMEORIGIN" always;
  add_header Referrer-Policy "same-origin" always;
  location ~* '"$DENY_RE"' { deny all; return 404; }
  location ~* \.(sql|txt|sh|service|example|md|log|bak)$ { deny all; return 404; }
  location ^~ /extras/ { deny all; return 404; }
  location ^~ /assets/ { expires 5m; add_header Cache-Control "public"; try_files $uri =404; }
  location / { try_files $uri $uri/ =404; }
  location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/vmmon.sock;
    fastcgi_read_timeout 90s;
  }'
  local vh=/etc/nginx/sites-available/vmmon
  # gzip (läggs en gång i egen conf)
  cat > /etc/nginx/conf.d/vmmon-gzip.conf <<'EOF'
gzip on;
gzip_comp_level 5;
gzip_min_length 512;
gzip_types text/css application/javascript application/json image/svg+xml text/plain;
EOF
  if [[ $VMMON_TLS == selfsigned ]]; then
    make_selfsigned
    cat > "$vh" <<EOF
server {
  listen 80;
  listen [::]:80;
  server_name $VMMON_DOMAIN;
  return 301 https://\$host\$request_uri;
}
server {
  listen 443 ssl;
  listen [::]:443 ssl;
  http2 on;
  server_name $VMMON_DOMAIN;
  ssl_certificate /etc/ssl/vmmon/vmmon.crt;
  ssl_certificate_key /etc/ssl/vmmon/vmmon.key;
  ssl_protocols TLSv1.2 TLSv1.3;
  ssl_session_cache shared:SSL:10m;
$snippet
}
EOF
  else
    cat > "$vh" <<EOF
server {
  listen 80;
  listen [::]:80;
  server_name $VMMON_DOMAIN;
$snippet
}
EOF
  fi
  ln -sf "$vh" /etc/nginx/sites-enabled/vmmon
  [[ $WEB_FRESH == 1 ]] && rm -f /etc/nginx/sites-enabled/default
  # "http2 on;" kräver nginx >= 1.25.1; äldre versioner använder "listen 443 ssl http2"
  if [[ $VMMON_TLS == selfsigned ]] && ! nginx -t >/dev/null 2>&1; then
    sed -i '/^  http2 on;$/d; s/listen 443 ssl;/listen 443 ssl http2;/; s/listen \[::\]:443 ssl;/listen [::]:443 ssl http2;/' "$vh"
  fi
  nginx -t >/dev/null 2>&1 || { nginx -t; die "Nginx-konfigurationen är ogiltig."; }
  systemctl enable --now nginx >/dev/null 2>&1 || true
  systemctl reload nginx || systemctl restart nginx
  ok "Nginx klar (PHP-FPM via socket)"
}

make_selfsigned(){
  install -d -m 755 /etc/ssl/vmmon
  if [[ ! -f /etc/ssl/vmmon/vmmon.crt ]]; then
    local san="DNS:$VMMON_DOMAIN"; [[ $VMMON_DOMAIN =~ ^[0-9.]+$ ]] && san="IP:$VMMON_DOMAIN"
    openssl req -x509 -nodes -newkey rsa:3072 -days 825 -keyout /etc/ssl/vmmon/vmmon.key -out /etc/ssl/vmmon/vmmon.crt \
      -subj "/CN=$VMMON_DOMAIN" -addext "subjectAltName=$san" >/dev/null 2>&1
    chmod 600 /etc/ssl/vmmon/vmmon.key
    ok "Självsignerat certifikat skapat (giltigt 825 dagar)"
  fi
}

letsencrypt(){
  [[ $VMMON_TLS == letsencrypt ]] || return 0
  hdr "Let's Encrypt"
  local plug=--nginx; [[ $VMMON_WEB == apache ]] && plug=--apache
  if certbot $plug -d "$VMMON_DOMAIN" --non-interactive --agree-tos -m "$VMMON_EMAIL" --redirect --no-eff-email >/tmp/certbot-vmmon.log 2>&1; then
    ok "Certifikat utfärdat och förnyelse schemalagd (certbot.timer)"
    # HSTS först när certifikatet fungerar
    if [[ $VMMON_WEB == apache ]]; then
      local lf=/etc/apache2/sites-available/vmmon-le-ssl.conf
      [[ -f $lf ]] && sed -i '/<\/VirtualHost>/i\  Header always set Strict-Transport-Security "max-age=15552000"' "$lf" && systemctl reload apache2 || true
    else
      sed -i '0,/server_tokens off;/s//server_tokens off;\n  add_header Strict-Transport-Security "max-age=15552000" always;/' /etc/nginx/sites-available/vmmon && nginx -t >/dev/null 2>&1 && systemctl reload nginx || true
    fi
  else
    warn "Let's Encrypt misslyckades (se /tmp/certbot-vmmon.log). Vanliga orsaker: DNS pekar inte hit, port 80 är blockerad. Sajten körs över HTTP tills vidare – kör senare: certbot $plug -d $VMMON_DOMAIN"
    VMMON_TLS=none
  fi
}

# ---------- tjänst ----------
install_service(){
  hdr "Bakgrundstjänst (vmmon-worker)"
  cat > /etc/systemd/system/vmmon-worker.service <<EOF
[Unit]
Description=VM Monitor worker (kontroller, hosts och larm)
After=network-online.target mysql.service mariadb.service
Wants=network-online.target

[Service]
User=$WEBUSER
WorkingDirectory=$VMMON_DIR
ExecStart=$(command -v php) $VMMON_DIR/worker.php
Restart=always
RestartSec=5
PrivateTmp=yes
ProtectHome=yes

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable vmmon-worker >/dev/null 2>&1
  systemctl restart vmmon-worker
  sleep 3
  if systemctl is-active --quiet vmmon-worker; then ok "Tjänsten vmmon-worker körs$(systemctl is-enabled --quiet vmmon-worker && echo ' och startar automatiskt vid omstart')"; else warn "vmmon-worker startade inte – se: journalctl -u vmmon-worker -n 50"; fi
  # fping pingar många enheter parallellt (worker faller tillbaka på vanlig ping om det inte fungerar för tjänstens användare)
  if command -v fping >/dev/null; then
    if su -s /bin/sh "$WEBUSER" -c 'fping -C 1 -q -t 500 127.0.0.1' >/dev/null 2>&1; then ok "fping fungerar för $WEBUSER (parallell ping)"; else warn "fping är installerat men fungerar inte för $WEBUSER – vmmon använder vanlig ping (kontrollera: getcap \$(command -v fping))"; fi
  else warn "fping saknas – vmmon använder vanlig ping en i taget"; fi
  # Lyssnare för Zabbix-agenter i active-läge (bara om du valt det). Den öppnar ingen port förrän den slås på under Inställningar > Zabbix.
  # Stor mottagarbuffert för UDP så att syslog/traps inte tappas vid skurar (mottagaren ber om 16 MB)
  printf '# Skapad av install-vmmon.sh – stor UDP-buffert för vmmon-recv\nnet.core.rmem_max = 16777216\n' > /etc/sysctl.d/99-vmmon.conf
  sysctl -q -p /etc/sysctl.d/99-vmmon.conf >/dev/null 2>&1 || warn "Kunde inte sätta net.core.rmem_max (gäller efter omstart)"
  # Mottagare för SNMP-traps och syslog. Lyssnar på UDP 162 och 514 (kan ändras under Inställningar > Trap / syslog).
  cat > /etc/systemd/system/vmmon-recv.service <<EOF
[Unit]
Description=VM Monitor mottagare for SNMP-traps och syslog
After=network-online.target mysql.service mariadb.service
Wants=network-online.target

[Service]
User=$WEBUSER
WorkingDirectory=$VMMON_DIR
ExecStart=$(command -v php) $VMMON_DIR/recv.php
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
Restart=always
RestartSec=5
PrivateTmp=yes
ProtectHome=yes

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable vmmon-recv >/dev/null 2>&1
  systemctl restart vmmon-recv
  sleep 2
  if systemctl is-active --quiet vmmon-recv; then ok "Tjänsten vmmon-recv körs$(systemctl is-enabled --quiet vmmon-recv && echo ' och startar automatiskt vid omstart') (SNMP-traps UDP 162, syslog UDP 514)"; else warn "vmmon-recv startade inte – se: journalctl -u vmmon-recv -n 50"; fi
  if [[ $VMMON_ZABBIX == yes ]]; then
  cat > /etc/systemd/system/vmmon-zabbix.service <<EOF
[Unit]
Description=VM Monitor Zabbix active-lyssnare
After=network-online.target mysql.service mariadb.service
Wants=network-online.target

[Service]
User=$WEBUSER
WorkingDirectory=$VMMON_DIR
ExecStart=$(command -v php) $VMMON_DIR/zbx-active.php
Restart=always
RestartSec=5
PrivateTmp=yes
ProtectHome=yes

[Install]
WantedBy=multi-user.target
EOF
  systemctl daemon-reload
  systemctl enable vmmon-zabbix >/dev/null 2>&1
  systemctl restart vmmon-zabbix
  if systemctl is-active --quiet vmmon-zabbix; then ok "Tjänsten vmmon-zabbix körs$(systemctl is-enabled --quiet vmmon-zabbix && echo ' och startar automatiskt vid omstart') (lyssnar först när active-läge slås på i Inställningar > Zabbix)"; else warn "vmmon-zabbix startade inte – se: journalctl -u vmmon-zabbix -n 50"; fi
  fi
}

firewall(){
  if command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q '^Status: active'; then
    ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null
    ok "ufw: port 80 och 443 öppnade"
    if [[ $VMMON_ZABBIX == yes && $VMMON_ZABBIX_FW == yes ]]; then ufw allow 10051/tcp >/dev/null; ok "ufw: port 10051 öppnad för Zabbix-agenter"; fi
  fi
}

app_settings(){
  if [[ -n $VMMON_PROXIES ]]; then
    local px; px=$(sql_esc "$VMMON_PROXIES")
    dbx "$VMMON_DB_NAME" -e "INSERT INTO settings(k,v) VALUES('trusted_proxies','$px') ON DUPLICATE KEY UPDATE v=VALUES(v)" \
      && ok "Betrodda proxyadresser satta: $VMMON_PROXIES"
  fi
}

verify(){
  hdr "Verifiering"
  if runuser -u "$WEBUSER" -- php -r "require '$VMMON_DIR/config.local.php'; new PDO(DB_DSN,DB_USER,DB_PASS); echo 'ok';" >/dev/null 2>&1; then ok "PHP når databasen som $WEBUSER"; else warn "PHP kunde inte ansluta till databasen – kontrollera config.local.php"; fi
  local scheme=http; [[ $VMMON_TLS != none ]] && scheme=https
  local code; code=$(curl -ksS -o /dev/null -w '%{http_code}' -H "Host: $VMMON_DOMAIN" "$scheme://127.0.0.1/login.php" 2>/dev/null || echo 000)
  if [[ $code == 200 || $code == 302 ]]; then ok "Webbservern svarar ($code)"; else warn "Oväntat svar från webbservern: $code (kontrollera loggarna)"; fi
  code=$(curl -ksS -o /dev/null -w '%{http_code}' -H "Host: $VMMON_DOMAIN" "$scheme://127.0.0.1/sql/schema.sql" 2>/dev/null || echo 000)
  [[ $code == 403 || $code == 404 ]] && ok "Känsliga filer är blockerade (sql/schema.sql → $code)" || warn "sql/schema.sql kan nås utifrån ($code) – kontrollera webbserverns konfiguration!"
}

summary(){
  local scheme=http; [[ $VMMON_TLS != none ]] && scheme=https
  local f=/root/vmmon-install.txt
  umask 077
  cat > "$f" <<EOF
VM Monitor – installationsuppgifter ($(date '+%F %T'))
Adress:               $scheme://$VMMON_DOMAIN/
Katalog:              $VMMON_DIR
Webbserver:           $VMMON_WEB      Databas: $VMMON_DB
DB root-lösenord:     $VMMON_DB_ROOT_PASS
DB-användare:         $VMMON_DB_USER
DB-lösenord:          $VMMON_DB_PASS
DB-namn:              $VMMON_DB_NAME
Krypteringsnyckel:    $VMMON_DIR/secret.php   (TA BACKUP – utan den går sparade lösenord/tokens inte att läsa)
Tjänst:               systemctl status vmmon-worker   |   journalctl -u vmmon-worker -f
Trap/syslog:          systemctl status vmmon-recv     |   journalctl -u vmmon-recv -f   (öppna UDP 162 och 514 i brandväggen från enheterna)
$( [[ $VMMON_ZABBIX == yes ]] && echo "Zabbix active:        systemctl status vmmon-zabbix   |   journalctl -u vmmon-zabbix -f   (slå på under Inställningar > Zabbix)" )
EOF
  hdr "Klart!"
  echo "  Öppna:  ${B}$scheme://$VMMON_DOMAIN/${N}  och skapa admin-kontot."
  echo "  Uppgifterna är sparade i ${B}$f${N} (bara root kan läsa)."
  echo "  Ta backup på ${B}$VMMON_DIR/secret.php${N} och databasen (mysqldump)."
  if [[ $VMMON_ZABBIX == yes ]]; then echo "  Zabbix active-läge: slå på ${B}Inställningar → Zabbix${N} och välj Active på servern (Servrar → redigera). Öppna port 10051 i brandväggen om du inte gjorde det här."; fi
  echo "  Nästa steg i webbgränssnittet: Inställningar → fyll i SMTP och intervall, lägg till servrar."
  [[ $VMMON_TLS == selfsigned ]] && echo "  ${Y}Självsignerat certifikat: webbläsaren varnar tills du godkänner det. Passkeys kräver giltigt certifikat.${N}"
  [[ $VMMON_TLS == none ]] && echo "  ${Y}HTTP utan kryptering. Kör bakom HTTPS-proxy (t.ex. Nginx Proxy Manager) och ange proxyns IP (VMMON_PROXIES) – passkeys och inloggning bör köras över HTTPS.${N}"
  (( UPGRADE )) && echo "  ${Y}Uppgradering: kör den senaste filen i sql/ (sql/N.NN.sql, se README.txt) mot databasen.${N}"
  return 0
}

main(){
  preflight
  gather
  WEB_FRESH=0
  if [[ $VMMON_WEB == apache ]]; then pkg_installed apache2 || WEB_FRESH=1; else pkg_installed nginx || WEB_FRESH=1; fi
  # Port 80 upptagen av annan webbserver?
  local other=nginx; [[ $VMMON_WEB == nginx ]] && other=apache2
  if systemctl is-active --quiet "$other" 2>/dev/null; then die "$other körs redan på servern och skulle krocka på port 80/443. Välj samma webbserver eller stoppa $other."; fi
  install_packages
  fetch_app
  write_config
  configure_db
  import_schema
  app_settings
  configure_php
  if [[ $VMMON_WEB == apache ]]; then write_apache; else write_nginx; fi
  letsencrypt
  install_service
  firewall
  verify
  summary
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then main "$@"; fi
