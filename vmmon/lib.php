<?php // Kontroller (Zabbix, SNMP, TCP, URL), larm, SMTP. Pollas via api/poll.php.
function tcp_defs(){ return ['ssh'=>['SSH',22],'ftp'=>['FTP',21],'smtp'=>['SMTP',25],'smtps'=>['SMTPS',465],'submission'=>['SMTP submission',587],'dns'=>['DNS (TCP)',53],'http'=>['HTTP',80],'https'=>['HTTPS',443],'pop3'=>['POP3',110],'pop3s'=>['POP3S',995],'imap'=>['IMAP',143],'imaps'=>['IMAPS',993],'mysql'=>['MySQL / MariaDB',3306],'mssql'=>['Microsoft SQL Server',1433],'postgres'=>['PostgreSQL',5432],'rdp'=>['RDP',3389],'smb'=>['SMB',445],'winrm'=>['WinRM',5985],'ldap'=>['LDAP',389],'ldaps'=>['LDAPS',636],'redis'=>['Redis',6379],'mongodb'=>['MongoDB',27017],'docker'=>['Docker API',2375]]; }
// [etikett, målfältets placeholder, extra, målfält obligatoriskt]
function catalog($sa=false){
 $tcp=['ping'=>['Ping (ICMP)','','',false]];
 foreach(tcp_defs() as $k=>[$n,$p]) $tcp[$k]=["$n ($p)",'','',false];
 if($sa) $tcp['custom']=[t('Egen port','Custom port'),'','',false];
 $ign=t('Dölj diskar på dashboard, t.ex. /,/boot (valfritt)','Hide disks on dashboard, e.g. /,/boot (optional)');
 return [
 'zabbix'=>zbx_catalog($ign),
 'snmp'=>['all'=>['SNMP','','',false]],
 'pbs'=>['all'=>['PBS API','','',false]],
 'docker'=>['containers'=>[t('Containrar (alla måste köra)','Containers (all must run)'),'','',false]],
 'ha'=>['api'=>['Home Assistant API','','',false]],
 'tcp'=>$tcp,
 'url'=>['http'=>[t('Svarar (HTTP 200–399)','Responds (HTTP 200–399)'),'https://exempel.se','',true],'status200'=>[t('Svarar exakt HTTP 200','Responds with exactly HTTP 200'),'https://exempel.se','',true],'contains'=>[t('Sidan innehåller text','Page contains text'),'https://exempel.se','',true],'ssl'=>[t('SSL-certifikat (dagar kvar, gräns i Inställningar)','SSL certificate (days left, limit in Settings)'),'https://exempel.se','',true]]];
}
function zbx_items(){ static $c=null; if($c!==null) return $c;
 $G=['ag'=>t('Agent','Agent'),'sys'=>t('System','System'),'mem'=>t('Minne','Memory'),'disk'=>t('Disk och filer','Disks and files'),'proc'=>t('Processer och tjänster','Processes and services'),'net'=>t('Nätverk','Network'),'win'=>'Windows','app'=>t('Applikationer (Agent 2)','Applications (Agent 2)'),'cus'=>t('Egen','Custom')];
 $i=function($g,$sv,$en,$key,$hint='',$req=false,$mode='info') use($G){ return [$G[$g],t($sv,$en),$key,$hint,$req,$mode]; };
 $ign=t('Dölj diskar på dashboard, t.ex. /,/boot (valfritt)','Hide disks on dashboard, e.g. /,/boot (optional)');
 return $c=[
 'ping'=>$i('ag','Agent svarar (agent.ping)','Agent responds (agent.ping)','agent.ping','',false,'eq1'),
 'agent_version'=>$i('ag','Agentversion','Agent version','agent.version'),'agent_hostname'=>$i('ag','Agentens värdnamn','Agent hostname','agent.hostname'),
 'cpu'=>$i('sys','CPU-användning','CPU usage',''),'cpu_num'=>$i('sys','Antal CPU-kärnor','Number of CPU cores','system.cpu.num'),
 'cpu_load1'=>$i('sys','CPU-load 1 min (Linux)','CPU load 1 min (Linux)','system.cpu.load[all,avg1]'),'cpu_load5'=>$i('sys','CPU-load 5 min (Linux)','CPU load 5 min (Linux)','system.cpu.load[all,avg5]'),'cpu_load15'=>$i('sys','CPU-load 15 min (Linux)','CPU load 15 min (Linux)','system.cpu.load[all,avg15]'),
 'cpu_iowait'=>$i('sys','CPU iowait % (Linux)','CPU iowait % (Linux)','system.cpu.util[,iowait]'),'cpu_model'=>$i('sys','CPU-modell','CPU model','system.hw.cpu[all,model]'),
 'hostname'=>$i('sys','Värdnamn','Hostname','system.hostname'),'uname'=>$i('sys','Systeminfo (uname)','System info (uname)','system.uname'),'os'=>$i('sys','OS-version','OS version','system.sw.os'),'arch'=>$i('sys','Arkitektur','Architecture','system.sw.arch'),
 'uptime'=>$i('sys','Uptime','Uptime','system.uptime'),'users_num'=>$i('sys','Inloggade användare','Logged-in users','system.users.num'),'time'=>$i('sys','Lokal tid på servern','Server local time','system.localtime'),
 'proc_total'=>$i('proc','Antal processer','Number of processes','proc.num[]'),'proc_run'=>$i('proc','Antal körande processer','Number of running processes','proc.num[,,run]'),
 'mem'=>$i('mem','Minnesanvändning','Memory usage',''),'mem_total'=>$i('mem','Totalt minne (byte)','Total memory (bytes)','vm.memory.size[total]'),'mem_avail'=>$i('mem','Tillgängligt minne (byte)','Available memory (bytes)','vm.memory.size[available]'),'mem_pavail'=>$i('mem','Tillgängligt minne %','Available memory %','vm.memory.size[pavailable]'),
 'swap_pfree'=>$i('mem','Ledig swap/växlingsfil %','Free swap / page file %','system.swap.size[,pfree]'),'swap_total'=>$i('mem','Total swap (byte)','Total swap (bytes)','system.swap.size[,total]'),
 'disk'=>$i('disk','Diskanvändning (alla diskar)','Disk usage (all disks)',''),
 'fs_pused'=>$i('disk','Använt % på en disk/sökväg','Used % on one disk/path','vfs.fs.size[{t}]'.'' ,t('Disk/sökväg, t.ex. / eller C:','Disk/path, e.g. / or C:'),true),
 'fs_free'=>$i('disk','Ledigt utrymme (byte) på en disk','Free space (bytes) on one disk','vfs.fs.size[{t},free]',t('Disk/sökväg, t.ex. / eller C:','Disk/path, e.g. / or C:'),true),
 'fs_total'=>$i('disk','Total storlek (byte) på en disk','Total size (bytes) on one disk','vfs.fs.size[{t},total]',t('Disk/sökväg, t.ex. / eller C:','Disk/path, e.g. / or C:'),true),
 'inode_pfree'=>$i('disk','Lediga inoder % (Linux)','Free inodes % (Linux)','vfs.fs.inode[{t},pfree]',t('Sökväg, t.ex. /','Path, e.g. /'),true),
 'file_exists'=>$i('disk','Fil/katalog finns','File/directory exists','vfs.file.exists[{t}]',t('Sökväg, t.ex. /var/run/app.pid','Path, e.g. /var/run/app.pid'),true,'eq1'),
 'file_size'=>$i('disk','Filstorlek (byte)','File size (bytes)','vfs.file.size[{t}]',t('Sökväg','Path'),true),'file_time'=>$i('disk','Fil senast ändrad (unix-tid)','File last modified (unix time)','vfs.file.time[{t}]',t('Sökväg','Path'),true),
 'file_contents'=>$i('disk','Filens innehåll','File contents','vfs.file.contents[{t}]',t('Sökväg','Path'),true),
 'service'=>$i('proc','Windows-tjänst körs','Windows service running','service.info[{t},state]',t('Tjänstnamn, t.ex. MSSQLSERVER','Service name, e.g. MSSQLSERVER'),true,'eq0'),
 'systemd'=>$i('proc','Systemd-enhet aktiv (Linux, Agent 2)','Systemd unit active (Linux, Agent 2)','systemd.unit.info[{t},ActiveState]',t('Enhet, t.ex. apache2','Unit, e.g. apache2'),true,'active'),
 'process'=>$i('proc','Process körs','Process running','proc.num[{t}]',t('Processnamn, t.ex. mysqld','Process name, e.g. mysqld'),true,'gt0'),
 'proc_cpu'=>$i('proc','Process CPU %','Process CPU %','proc.cpu.util[{t}]',t('Processnamn','Process name'),true),'proc_mem'=>$i('proc','Process minne (byte)','Process memory (bytes)','proc.mem[{t}]',t('Processnamn','Process name'),true),
 'net_in'=>$i('net','Trafik in (byte/s) på nätkort','Traffic in (bytes/s) on NIC','net.if.in[{t}]',t('Nätkort, t.ex. eth0','NIC, e.g. eth0'),true),'net_out'=>$i('net','Trafik ut (byte/s) på nätkort','Traffic out (bytes/s) on NIC','net.if.out[{t}]',t('Nätkort, t.ex. eth0','NIC, e.g. eth0'),true),
 'net_in_err'=>$i('net','Mottagningsfel på nätkort','Receive errors on NIC','net.if.in[{t},errors]',t('Nätkort','NIC'),true),'net_out_err'=>$i('net','Sändningsfel på nätkort','Send errors on NIC','net.if.out[{t},errors]',t('Nätkort','NIC'),true),
 'net_tcp_service'=>$i('net','Nätverkstjänst svarar (från agenten)','Network service responds (from agent)','net.tcp.service[{t}]',t('t.ex. ssh, http eller http,,8080','e.g. ssh, http or http,,8080'),true,'eq1'),
 'net_tcp_perf'=>$i('net','Nätverkstjänst svarstid (s)','Network service response time (s)','net.tcp.service.perf[{t}]',t('t.ex. http','e.g. http'),true),
 'net_tcp_listen'=>$i('net','TCP-port lyssnar lokalt','TCP port listening locally','net.tcp.listen[{t}]',t('Port, t.ex. 3306','Port, e.g. 3306'),true,'eq1'),
 'net_tcp_port'=>$i('net','TCP-port nåbar från servern (localhost)','TCP port reachable from the server (localhost)','net.tcp.port[,{t}]',t('Port, t.ex. 443','Port, e.g. 443'),true,'eq1'),
 'net_udp_listen'=>$i('net','UDP-port lyssnar lokalt','UDP port listening locally','net.udp.listen[{t}]',t('Port, t.ex. 161','Port, e.g. 161'),true,'eq1'),
 'net_dns'=>$i('net','DNS-uppslag fungerar (från servern)','DNS lookup works (from the server)','net.dns[,{t},A]',t('Domän, t.ex. exempel.se','Domain, e.g. example.com'),true,'eq1'),
 'web_page'=>$i('net','Hämta webbsida (från servern)','Fetch web page (from the server)','web.page.get[{t}]',t('Värd, t.ex. localhost','Host, e.g. localhost'),true),
 'perf'=>$i('win','Windows Performance Counter','Windows performance counter','perf_counter_en[{t}]',t('t.ex. \\Processor(_Total)\\% Processor Time','e.g. \\Processor(_Total)\\% Processor Time'),true),
 'cpu_freq'=>$i('sys','CPU-frekvens, aktuell (Hz, Linux)','CPU frequency, current (Hz, Linux)','system.hw.cpu[all,curfreq]'),'cpu_maxfreq'=>$i('sys','CPU-frekvens, max (Hz, Linux)','CPU frequency, max (Hz, Linux)','system.hw.cpu[all,maxfreq]'),
 'win_cpu_mhz'=>$i('win','CPU-klockfrekvens aktuell (MHz)','CPU clock speed, current (MHz)','wmi.get[root\cimv2,"SELECT CurrentClockSpeed FROM Win32_Processor"]'),'win_cpu_maxmhz'=>$i('win','CPU-klockfrekvens max (MHz)','CPU clock speed, max (MHz)','wmi.get[root\cimv2,"SELECT MaxClockSpeed FROM Win32_Processor"]'),'win_mem_speed'=>$i('win','Minneshastighet (MHz)','Memory speed (MHz)','wmi.get[root\cimv2,"SELECT Speed FROM Win32_PhysicalMemory"]'),'win_mem_cfg'=>$i('win','Minneshastighet, konfigurerad (MHz)','Memory speed, configured (MHz)','wmi.get[root\cimv2,"SELECT ConfiguredClockSpeed FROM Win32_PhysicalMemory"]'),
 'wmi'=>$i('win','WMI-fråga','WMI query','wmi.get[{t}]',t('t.ex. root\\cimv2,select Status from Win32_DiskDrive','e.g. root\\cimv2,select Status from Win32_DiskDrive'),true),
 'registry'=>$i('win','Registervärde','Registry value','registry.data[{t}]',t('t.ex. HKLM\\SOFTWARE\\Foo,Bar','e.g. HKLM\\SOFTWARE\\Foo,Bar'),true),
 'docker_ping'=>$i('app','Docker svarar','Docker responds','docker.ping','',false,'eq1'),
 'docker_containers'=>$i('app','Docker-containrar (agent 2; alla måste köra)','Docker containers (agent 2; all must run)','docker.containers','',false,'info'),
 'mysql_ping'=>$i('app','MySQL/MariaDB svarar','MySQL/MariaDB responds','mysql.ping[{t}]',t('Anslutning, t.ex. tcp://localhost:3306,användare,lösen (valfritt om macros finns)','Connection, e.g. tcp://localhost:3306,user,password (optional if macros exist)'),false,'eq1'),
 'pgsql_ping'=>$i('app','PostgreSQL svarar','PostgreSQL responds','pgsql.ping[{t}]',t('Anslutning (valfritt)','Connection (optional)'),false,'eq1'),
 'redis_ping'=>$i('app','Redis svarar','Redis responds','redis.ping[{t}]',t('Anslutning, t.ex. tcp://localhost:6379 (valfritt)','Connection, e.g. tcp://localhost:6379 (optional)'),false,'eq1'),
 'mongodb_ping'=>$i('app','MongoDB svarar','MongoDB responds','mongodb.ping[{t}]',t('Anslutning (valfritt)','Connection (optional)'),false,'eq1'),
 'memcached_ping'=>$i('app','Memcached svarar','Memcached responds','memcached.ping[{t}]',t('Anslutning (valfritt)','Connection (optional)'),false,'eq1'),
 'smart'=>$i('app','SMART-diskhälsa (Agent 2 + smartctl)','SMART disk health (Agent 2 + smartctl)','smart.disk.discovery','',false,'info'),
 'key'=>$i('cus','Egen item-key (valfri nyckel från agenten)','Custom item key (any agent key)','{t}',t('t.ex. net.tcp.listen[3306] – vilken nyckel agenten stöder','e.g. net.tcp.listen[3306] – any key the agent supports'),true,'nz')]; }
function zbx_catalog($ign){ $o=[]; foreach(zbx_items() as $k=>$v) $o[$k]=[$v[1],$k==='disk'?$ign:$v[3],'',$v[4],$v[0]]; return $o; }
function zbx_key($tpl,$t){ $t=trim($t); if(strpos($tpl,'{t}')===false) return $tpl; if($t===''&&$tpl!=='{t}') return preg_replace('/\[[^\]]*\{t\}[^\]]*\]/','',$tpl); if($tpl==='vfs.fs.size[{t}]') return "vfs.fs.size[$t,pused]"; return str_replace('{t}',$t,$tpl); }
function zbx_cond($r,$cond){ if(!preg_match('/^(>=|<=|!=|!~|>|<|=|~)?\s*(.*)$/s',trim($cond),$m)) return true; $op=$m[1]?:'='; $v=trim($m[2]); $r=trim($r);
 if($op==='~') return stripos($r,$v)!==false; if($op==='!~') return stripos($r,$v)===false;
 if(is_numeric($r)&&is_numeric($v)){ $a=(float)$r; $b=(float)$v; return match($op){'>'=>$a>$b,'<'=>$a<$b,'>='=>$a>=$b,'<='=>$a<=$b,'!='=>$a!=$b,default=>$a==$b}; }
 return $op==='!='?strcasecmp($r,$v)!==0:strcasecmp($r,$v)===0; }
function zbx_run($c,$h){ $k=$c['kind']; $it=zbx_items()[$k]??null; if(!$it||$it[2]==='') return [false,t('okänd Zabbix-kontroll','unknown Zabbix check')];
 $key=zbx_key($it[2],$c['target']); $r=zbx($h,10050,$key); if($r===null) return [false,lasterr()?:t('ingen respons','no response')]; $cond=trim((string)$c['param']);
 $ok=$cond!==''?zbx_cond($r,$cond):match($it[5]){'eq1'=>$r==='1','gt0'=>(float)$r>0,'eq0'=>$r==='0','active'=>$r==='active','nz'=>$r!=='0',default=>true};
 $v=$k==='uptime'&&is_numeric($r)?floor($r/86400).'d '.floor($r%86400/3600).'h '.floor($r%3600/60).'m':substr(str_replace(["\r","\n"],' ',$r),0,80);
 if(!$ok) return [false,t('Fel (svar: ','Error (reply: ').$v.')'];
 return [true,($it[5]==='info'||$cond!==''||$k==='key')?$v:'OK']; }
// ---- Applikationslogg (Inställningar > Loggar)
function applog($level,$src,$msg){ try{ if($level==='debug'&&setting('log_debug','0')!=='1') return; $u=PHP_SAPI!=='cli'?($_SESSION['u']['username']??null):null;
 db()->prepare('INSERT INTO app_log(level,src,usr,msg) VALUES(?,?,?,?)')->execute([in_array($level,['debug','info','warning','error'],true)?$level:'info',substr($src,0,20),$u,mb_substr((string)$msg,0,600)]); }catch(Throwable $e){} }
// Gemensam ruta överst på server-, host-, nätverks- och tjänstesidor: pausa larm, inaktivera, dölj/visa kort på dash
function server_box_post($id){ $a=$_POST['a']??''; if(!in_array($a,['maint_start','maint_end','toggle_server','toggle_dash'],true)) return false; $pdo=db(); $id=(int)$id;
 $q=$pdo->prepare('SELECT name FROM servers WHERE id=?'); $q->execute([$id]); $nm=(string)$q->fetchColumn();
 if($a==='maint_start'){ $m=(int)($_POST['mins']??0); if($m>0) $pdo->prepare('UPDATE servers SET maint_until=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE id=?')->execute([$m,$id]); else $pdo->prepare("UPDATE servers SET maint_until='9999-12-31 23:59:59' WHERE id=?")->execute([$id]); applog('info','config',"$nm: ".'alerts paused'.' '.($m>0?"$m min":'indefinitely')); }
 if($a==='maint_end'){ $pdo->prepare('UPDATE servers SET maint_until=NULL WHERE id=?')->execute([$id]); applog('info','config',"$nm: ".'alerts resumed'); }
 if($a==='toggle_server'){ $pdo->prepare('UPDATE servers SET enabled=1-enabled,hv_poll=NULL,nw_poll=NULL WHERE id=?')->execute([$id]); $pdo->prepare('UPDATE server_checks SET last_check=NULL WHERE server_id=? AND (SELECT enabled FROM servers WHERE id=?)=1')->execute([$id,$id]); applog('info','config',"$nm: ".'enabled/disabled'); }
 if($a==='toggle_dash'){ $pdo->prepare('UPDATE servers SET dash_hidden=1-dash_hidden WHERE id=?')->execute([$id]); applog('info','config',"$nm: ".'card hidden/shown on dash'); }
 return true; }
function server_box($srv){ $id=(int)$srv['id']; $mn=db()->prepare('SELECT maint_until,(maint_until IS NOT NULL AND maint_until>NOW()) a,enabled,dash_hidden FROM servers WHERE id=?'); $mn->execute([$id]); $mn=$mn->fetch(); $en=(int)$mn['enabled']===1; $dh=(int)$mn['dash_hidden']===1;
 $pt=h(t('Pausa larm (t.ex. vid patchning)','Pause alerts (e.g. during patching)'));
 if(!is_admin()){ if($mn['a']) echo '<p class="card badt">🔧 '.t('Larm är pausade','Alerts are paused').'</p>'; return; }
 echo '<div class="card inline"><form method="post" class="inline">'.csrf_field();
 if($mn['a']) echo '<input type="hidden" name="a" value="maint_end"><span class="badt help" title="'.$pt.'">🔧 '.t('Larm pausade till ','Alerts paused until ').h(substr($mn['maint_until'],0,4)==='9999'?t('vidare','further notice'):$mn['maint_until']).'</span><button class="btn sm go">'.t('Återuppta larm','Resume alerts').'</button>';
 else { echo '<input type="hidden" name="a" value="maint_start"><span class="mut help" title="'.$pt.'">'.t('Pausa larm','Pause alerts').'</span><select name="mins">'; foreach([30=>'30 min',60=>'1 h',120=>'2 h',240=>'4 h',480=>'8 h',1440=>'24 h',0=>t('Tills vidare','Indefinitely')] as $k=>$n) echo '<option value="'.$k.'">'.$n.'</option>'; echo '</select><button class="btn sm warn">'.t('Pausa','Pause').'</button>'; }
 echo '</form><form method="post" class="inline">'.csrf_field().'<input type="hidden" name="a" value="toggle_server"><button class="btn sm '.($en?'warn':'go').'" title="'.h($en?t('Inaktivera – inga kontroller körs och inga larm skickas','Disable – no checks run and no alerts are sent'):t('Aktivera – kontroller körs igen','Enable – checks run again')).'">'.($en?t('Inaktivera','Disable'):t('Aktivera','Enable')).'</button></form>'
  .'<form method="post" class="inline">'.csrf_field().'<input type="hidden" name="a" value="toggle_dash"><button class="btn sm '.($dh?'go':'danger').'" title="'.h(t('Döljer bara kortet från dashboarden – övervakning och larm fortsätter som vanligt','Only hides the card from the dashboard – monitoring and alerts continue as usual')).'">'.($dh?t('Visa på dash','Show on dash'):t('Dölj från dash','Hide on dash')).'</button></form></div>'; }
require_once __DIR__.'/icons_lib.php';
require_once __DIR__.'/ha_lib.php';
require_once __DIR__.'/mib_lib.php';
require_once __DIR__.'/checks_lib.php';
require_once __DIR__.'/zbx_active_lib.php';
function journal_tail($unit,$n=200){ $unit=preg_replace('/[^\w.@-]/','',$unit); if(!function_exists('exec')||in_array('exec',array_map('trim',explode(',',(string)ini_get('disable_functions'))),true)) return [false,t('PHP exec() är avstängd (disable_functions).','PHP exec() is disabled (disable_functions).')];
 $o=[]; $rc=0; @exec('journalctl -u '.escapeshellarg($unit).' -n '.(int)$n.' --no-pager -o short-iso 2>&1',$o,$rc); $t=trim(implode("\n",$o)); if($rc!==0||$t==='') return [false,$t!==''?$t:'journalctl: '.t('inget resultat (finns systemd/journalctl?)','no result (is systemd/journalctl available?)')]; return [true,$t]; }
function lasterr($s=null){ static $e=''; if($s!==null)$e=$s; return $e; }
function zbx($h,$p,$key){ if(($zs=(zbxa_map()[strtolower(trim((string)$h))]??0))) return zbxa_get($zs,$key);
 $f=@fsockopen($h,(int)$p,$en,$es,3); if(!$f){ lasterr("Zabbix-agent $h:$p ".t('onåbar','unreachable').': '.($es?:(error_get_last()['message']??'?'))." (errno $en)"); return null; }
 stream_set_timeout($f,3); fwrite($f,"ZBXD\x01".pack('P',strlen($key)).$key); $r=stream_get_contents($f); fclose($f);
 if(strncmp($r,"ZBXD\x01",5)===0) $r=substr($r,13); $r=trim($r);
 if($r===''){ lasterr(t('tomt svar – tillåter agenten monitor-serverns IP i Server= ?','empty response – does the agent allow the monitor IP in Server= ?')); return null; }
 if(strpos($r,'ZBX_NOTSUPPORTED')===0){ lasterr(t('agenten: ','agent: ').substr(str_replace("\0",' ',$r),0,150)); return null; }
 return $r; }
// ---- SNMP-konfiguration: v2c (community) eller v3. param: ''/'default' = standard-community, 'v3default' = standard v3, '{json}' = egna v3-uppgifter, annars egen community
// ---- Hemligheter (lösenord/token) lagras krypterade (AES-256-GCM). Nyckeln ligger i secret.php (skapas automatiskt) eller SECRET_KEY i config.local.php
function secret_key(){ static $k=null; if($k!==null) return $k; if(defined('SECRET_KEY')&&SECRET_KEY!=='') return $k=hash('sha256',SECRET_KEY,true);
 $f=__DIR__.'/secret.php'; if(!is_file($f)){ @file_put_contents($f,"<?php return '".bin2hex(random_bytes(32))."';\n"); @chmod($f,0600); } $v=is_file($f)?@include $f:''; return $k=is_string($v)&&$v!==''?hash('sha256',$v,true):false; }
function secret_enc($v){ $v=(string)$v; if($v===''||strpos($v,'enc1:')===0||strpos($v,'plain1:')===0) return $v; $k=secret_key(); if(!$k||!function_exists('openssl_encrypt')) return 'plain1:'.base64_encode($v);
 $iv=random_bytes(12); $c=openssl_encrypt($v,'aes-256-gcm',$k,OPENSSL_RAW_DATA,$iv,$tag); return $c===false?'plain1:'.base64_encode($v):'enc1:'.base64_encode($iv.$tag.$c); }
function secret_dec($v){ $v=(string)$v; if(strpos($v,'enc1:')===0){ $k=secret_key(); $b=base64_decode(substr($v,5)); if(!$k||strlen($b)<29) return ''; $r=openssl_decrypt(substr($b,28),'aes-256-gcm',$k,OPENSSL_RAW_DATA,substr($b,0,12),substr($b,12,16)); return $r===false?'':$r; }
 if(strpos($v,'plain1:')===0) return (string)base64_decode(substr($v,7)); return $v; }
function secret($k){ return secret_dec(setting($k,'')); }
function set_secret($k,$v){ set_setting($k,secret_enc($v)); }
// Tidszon: webbsidorna använder PHP:s tidszon från webbserverns php.ini, men kommandoradens PHP (mottagaren) har ofta en annan (UTC). Webben sparar sin tidszon i inställningen app_tz och
// tjänster som körs i terminalen (recv.php) använder den, så att tidsstämplarna blir samma som i gränssnittet.
function app_tz_sync(){ try{ $pf=(string)setting('tz_pref',''); $ok=$pf!==''&&in_array($pf,timezone_identifiers_list(),true);
 if($ok){ if($pf!==date_default_timezone_get()) date_default_timezone_set($pf); if(PHP_SAPI!=='cli'&&(string)setting('app_tz','')!==$pf) set_setting('app_tz',$pf); }
 elseif(PHP_SAPI==='cli'){ $z=(string)setting('app_tz',''); if($z!==''&&$z!==date_default_timezone_get()&&in_array($z,timezone_identifiers_list(),true)) date_default_timezone_set($z); }
 else { $z=date_default_timezone_get(); if((string)setting('app_tz','')!==$z) set_setting('app_tz',$z); } db_tz(); }catch(Throwable $e){} }
// Enheter som mottagaren faktiskt tagit emot loggar från (per typ). Underhålls av mottagaren; fylls första gången från senaste dygnet. 0 = okänd avsändare.
function log_devs_all(){ $j=json_decode((string)setting('log_devs',''),true); if(is_array($j)) return $j; $j=['trap'=>[],'syslog'=>[]];
 try{ foreach(db()->query("SELECT kind,COALESCE(server_id,0) sid FROM log_events WHERE ts>='".date('Y-m-d H:i:s',time()-86400)."' GROUP BY kind,COALESCE(server_id,0)")->fetchAll() as $r) $j[$r['kind']][]=(int)$r['sid']; set_setting('log_devs',json_encode($j)); }catch(Throwable $e){} return $j; }
function log_dev_note($kind,$sid){ static $k=null; if($k===null) $k=log_devs_all(); $sid=(int)$sid; if(in_array($sid,$k[$kind]??[],true)) return; $k[$kind][]=$sid; try{ set_setting('log_devs',json_encode($k)); }catch(Throwable $e){} }
function settings_tabs($v){ echo '<div class="rngn">'; foreach(['general'=>[t('Allmänt','General'),'settings.php?v=general'],'security'=>[t('Säkerhet','Security'),'settings.php?v=security'],'snmp'=>['SNMP','settings.php?v=snmp'],'notifications'=>[t('Notifieringar','Notifications'),'settings.php?v=notifications'],'groups'=>[t('Enhetsgrupper','Device groups'),'groups.php'],'icons'=>[t('Ikoner','Icons'),'settings.php?v=icons'],'zabbix'=>['Zabbix','settings.php?v=zabbix'],'traps'=>[t('Trap / syslog','Trap / syslog'),'settings.php?v=traps'],'mibs'=>['MIB','settings.php?v=mibs'],'api'=>['API','settings.php?v=api'],'users'=>[t('Användare','Users'),'users.php'],'logs'=>[t('Loggar','Logs'),'logs.php'],'update'=>[t('Uppdatera','Update'),'update.php']] as $k=>[$n,$u]) echo '<a class="btn sm'.($v===$k?' on':'').'" href="'.$u.'">'.h($n).'</a>'; echo '</div>'; }
function notify_cfg(){ return ['email'=>trim((string)setting('smtp_host',''))!=='','tg'=>trim((string)secret('tg_token'))!==''&&trim((string)setting('tg_chat',''))!=='','wh'=>trim((string)setting('wh_url',''))!=='','sms'=>trim((string)setting('sms_url',''))!==''&&trim((string)setting('sms_to',''))!=='','signal'=>trim((string)setting('signal_url',''))!==''&&trim((string)setting('signal_from',''))!==''&&trim((string)setting('signal_to',''))!==''];}
function wh_send($payload,$text){ $url=trim(setting('wh_url','')); if($url==='') return t('Webhook är inte konfigurerad (Inställningar)','Webhook is not configured (Settings)');
 $fmt=setting('wh_format','json'); $body=$fmt==='slack'?['text'=>$text]:($fmt==='discord'?['content'=>substr($text,0,1900)]:$payload);
 $hd=['Content-Type: application/json']; $hn=trim(setting('wh_header','')); $hv=secret('wh_secret'); if($hn!==''&&$hv!=='') $hd[]="$hn: $hv";
 $c=curl_init($url); curl_setopt_array($c,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>$hd,CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>10]);
 $r=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); $e=curl_error($c); curl_close($c);
 return($code>=200&&$code<300)?null:($e?:"HTTP $code ".substr(trim((string)$r),0,100)); }
function snmp3_default(){ return ['v'=>3,'u'=>setting('snmp3_user',''),'lvl'=>setting('snmp3_level','')?:'authPriv','ap'=>setting('snmp3_auth_proto','')?:'SHA','apw'=>secret('snmp3_auth_pass'),'pp'=>setting('snmp3_priv_proto','')?:'AES','ppw'=>secret('snmp3_priv_pass')]; }
function snmp_cfg($param){ $p=trim((string)$param); if($p===''||$p==='default') return ['v'=>2,'c'=>setting('snmp_community','public')?:'public']; if($p==='v3default') return snmp3_default();
 if($p[0]==='{'){ $j=json_decode($p,true); if(is_array($j)){ foreach(['apw','ppw'] as $q) if(isset($j[$q])) $j[$q]=secret_dec($j[$q]); if((int)($j['v']??3)===2){ $o=['v'=>2,'c'=>(string)($j['c']??'')?:(setting('snmp_community','public')?:'public')]; if((int)($j['port']??0)>0) $o['port']=(int)$j['port']; return $o; } return array_merge(snmp3_default(),array_filter($j,fn($x)=>$x!==''&&$x!==null),['v'=>3]); } } return ['v'=>2,'c'=>$p]; }
// Adress med eventuell egen SNMP-port (t.ex. Meraki snmp.meraki.com:16100). PHP:s SNMP-funktioner tar host:port.
// Timeout/omförsök: enheter som redan är nere får 1 s och inga omförsök så att en pollningsrunda inte fastnar på dem.
function snmp_to(){ return empty($GLOBALS['snmp_fast'])?2000000:1000000; }
function snmp_rt(){ return empty($GLOBALS['snmp_fast'])?1:0; }
function snmp_hp($h,$g){ $p=(int)($g['port']??0); if($p<=0||$p>65535||$p===161) return $h; if(strpos($h,':')!==false&&$h[0]!=='[') return '['.$h.']:'.$p; return $h.':'.$p; }
function snmp3_args($g){ $l=in_array($g['lvl']??'',['noAuthNoPriv','authNoPriv','authPriv'],true)?$g['lvl']:'authPriv'; $g['ap']=$g['ap']?:'SHA'; $g['pp']=$g['pp']?:'AES'; $a=$l!=='noAuthNoPriv'; $pr=$l==='authPriv'; return [$g['u'],$l,$a?$g['ap']:'',$a?$g['apw']:'',$pr?$g['pp']:'',$pr?(($g['ppw']??'')!==''?$g['ppw']:$g['apw']):'']; }
function snmp_gt($h,$g,$o){ if($g['v']===3){ [$u,$l,$ap,$apw,$pp,$ppw]=snmp3_args($g); return snmp3_get(snmp_hp($h,$g),$u,$l,$ap,$apw,$pp,$ppw,$o,snmp_to(),snmp_rt()); } return snmp2_get(snmp_hp($h,$g),$g['c'],$o,snmp_to(),snmp_rt()); }
function snmp_wk($h,$g,$o){ if($g['v']===3){ [$u,$l,$ap,$apw,$pp,$ppw]=snmp3_args($g); return snmp3_walk(snmp_hp($h,$g),$u,$l,$ap,$apw,$pp,$ppw,$o,snmp_to(),snmp_rt()); } return snmp2_walk(snmp_hp($h,$g),$g['c'],$o,snmp_to(),snmp_rt()); }
function snmp_rw($h,$g,$o){ if($g['v']===3){ [$u,$l,$ap,$apw,$pp,$ppw]=snmp3_args($g); return snmp3_real_walk(snmp_hp($h,$g),$u,$l,$ap,$apw,$pp,$ppw,$o,snmp_to(),snmp_rt()); } return snmp2_real_walk(snmp_hp($h,$g),$g['c'],$o,snmp_to(),snmp_rt()); }
function snmp_param_parse($p){ $o=['ver'=>'2','comm'=>'','port'=>'','u'=>'','lvl'=>'','ap'=>'','apw'=>'','pp'=>'','ppw'=>'']; $p=trim((string)$p); if($p===''||$p==='default') return $o; if($p==='none'){ $o['ver']='none'; return $o; } if($p==='v3default'){ $o['ver']='3'; return $o; }
 if($p[0]==='{'){ $j=json_decode($p,true)?:[]; $o['ver']=(int)($j['v']??3)===2?'2':'3'; if($o['ver']==='2') $o['comm']=(string)($j['c']??''); $o['port']=(int)($j['port']??0)>0?(string)(int)$j['port']:''; foreach(['u','lvl','ap','apw','pp','ppw'] as $k) $o[$k]=(string)($j[$k]??''); return $o; } $o['comm']=$p; return $o; }
function snmp_param_build($ver,$post,$old=''){ if($ver==='none') return 'none'; if($ver==='3'){ $o=snmp_param_parse($old); $j=[];
  foreach(['u'=>'s3_user','lvl'=>'s3_lvl','ap'=>'s3_ap','apw'=>'s3_apw','pp'=>'s3_pp','ppw'=>'s3_ppw'] as $k=>$f){ $v=trim((string)($post[$f]??'')); if($v===''&&in_array($k,['apw','ppw'])&&$o['ver']==='3') $v=$o[$k]; if($v!==''){ if(in_array($k,['apw','ppw'])) $v=secret_enc($v); $j[$k]=$v; } }
  $pt=(int)preg_replace('/\D/','',(string)($post['s_port']??'')); if($pt>0&&$pt<=65535) $j['port']=$pt;
  return $j?json_encode($j):'v3default'; }
 $c=trim((string)($post['s_comm']??'')); $pt=(int)preg_replace('/\D/','',(string)($post['s_port']??'')); if($pt>0&&$pt<=65535) return json_encode(['v'=>2,'c'=>$c,'port'=>$pt]); return $c===''?'default':$c; }
// SNMPv3-fält som visas när versionen är 3 (och communityfältet .s2f när den är 2). Selecten ska ha class="sver".
function snmp_v3_fields($sp){ $o=''; $pw=fn($k,$ph)=>'<input name="'.$k.'" type="password" size="14" autocomplete="new-password" placeholder="'.h($ph).'">';
 $o.='<span class="s3f" style="display:none"><input name="s3_user" size="18" value="'.h($sp['u']??'').'" placeholder="'.h(t('v3-användare (tomt = default)','v3 user (empty = default)')).'"><select name="s3_lvl"><option value="">level: default</option>'; foreach(['noAuthNoPriv','authNoPriv','authPriv'] as $x) $o.='<option'.(($sp['lvl']??'')===$x?' selected':'').'>'.$x.'</option>'; $o.='</select><select name="s3_ap"><option value="">auth: default</option>'; foreach(['MD5','SHA','SHA224','SHA256','SHA384','SHA512'] as $x) $o.='<option'.(($sp['ap']??'')===$x?' selected':'').'>'.$x.'</option>';
 $o.='</select>'.$pw('s3_apw',!empty($sp['apw'])?t('auth-lösenord (oförändrat om tomt)','auth password (unchanged if empty)'):'auth password').'<select name="s3_pp"><option value="">priv: default</option>'; foreach(['DES','AES','AES192','AES256'] as $x) $o.='<option'.(($sp['pp']??'')===$x?' selected':'').'>'.$x.'</option>'; $o.='</select>'.$pw('s3_ppw',!empty($sp['ppw'])?t('priv-lösenord (oförändrat om tomt)','priv password (unchanged if empty)'):t('priv-lösenord (tomt = samma som auth, t.ex. UniFi)','priv password (empty = same as auth, e.g. UniFi)')).'</span>';
 static $js=false; if(!$js){ $js=true; $o.='<script>(function(){function f(s){var fm=s.form||document,v=s.value;[].forEach.call(fm.querySelectorAll(".s2f"),function(e){var on=v==="2";e.style.display=on?"":"none";e.disabled=!on});[].forEach.call(fm.querySelectorAll(".s3f"),function(e){var on=v==="3";e.style.display=on?"contents":"none";[].forEach.call(e.querySelectorAll("input,select"),function(i){i.disabled=!on})})}[].forEach.call(document.querySelectorAll("select.sver"),function(s){s.addEventListener("change",function(){f(s)});f(s)})})();</script>'; }
 return $o; }
function snmp_walk($h,$c,$oid){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); error_clear_last(); $r=@snmp_wk($h,$c,$oid);
 if($r===false||$r===null){ lasterr("SNMP $h (OID $oid): ".(error_get_last()['message']??t('ingen respons – kontrollera community, brandvägg och att SNMP tillåter monitor-IP','no response – check community, firewall and that SNMP allows the monitor IP'))); return []; } return array_values($r); }
function short_disk($n){ $n=trim($n); return preg_match('/^([A-Za-z]:)\\\\/',$n,$m)?$m[1]:$n; }
function sget($h,$c,$o){ $r=@snmp_gt($h,$c,$o); if($r===false||$r===null) return null; $r=trim((string)$r,"\" \r\n\t"); return($r===''||stripos($r,'No Such')===0)?null:$r; }
function ticks($v){ $v=(string)$v; if(preg_match('/\((\d+)\)/',$v,$m)) return (int)($m[1]/100); if(preg_match('/^(\d+):(\d+):(\d+):(\d+)/',$v,$m)) return $m[1]*86400+$m[2]*3600+$m[3]*60+(int)$m[4]; return is_numeric($v)?(int)($v/100):null; }
// [OS-namn, kernel/build, källa]
function os_guess($d,$oid=''){
 if(preg_match('/^VMware (ESXi?)\s+([\d.]+)/i',$d,$m)) return ["VMware $m[1] $m[2]",preg_match('/build-(\d+)/',$d,$b)?"build $b[1]":'','descr'];
 if(preg_match('/Windows.*Build (\d+)/s',$d,$m)){ $b=(int)$m[1]; $cl=preg_match('/311\.1\.1\.3\.1\.(\d)/',(string)$oid,$q)&&$q[1]==='1';
  $map=$cl?[[26200,'Windows 11 25H2'],[26100,'Windows 11 24H2'],[22631,'Windows 11 23H2'],[22621,'Windows 11 22H2'],[22000,'Windows 11 21H2'],[10240,'Windows 10'],[9600,'Windows 8.1'],[9200,'Windows 8'],[7601,'Windows 7']]
   :[[26100,'Windows Server 2025'],[25398,'Windows Server 23H2'],[22000,'Windows 11'],[20348,'Windows Server 2022'],[19041,'Windows 10'],[18362,'Windows 10'],[17763,'Windows Server 2019'],[14393,'Windows Server 2016'],[10240,'Windows 10'],[9600,'Windows Server 2012 R2'],[9200,'Windows Server 2012'],[7601,'Windows Server 2008 R2']];
  $n='Windows'; foreach($map as [$min,$nm]) if($b>=$min){ $n=$nm; break; }
  return [$n,"build $b",'build']; }
 $ub=['4.4'=>'16.04','4.15'=>'18.04','5.4'=>'20.04','5.15'=>'22.04','6.8'=>'24.04','6.11'=>'24.10','6.14'=>'25.04','6.17'=>'25.10']; $db=['4.9'=>'9','4.19'=>'10','5.10'=>'11','6.1'=>'12','6.12'=>'13'];
 if(preg_match('/^Linux\s+\S+\s+((\d+\.\d+)\S*)/',$d,$m)){ $kv=$m[1]; $mm=$m[2];
  if(stripos($kv,'-pve')!==false) return ['Proxmox VE',$kv,'descr'];
  if(stripos($d,'ubuntu')!==false) return ['Ubuntu'.(isset($ub[$mm])?' '.$ub[$mm]:''),$kv,'inferred'];
  if(stripos($d,'debian')!==false) return ['Debian'.(isset($db[$mm])?' '.$db[$mm]:''),$kv,'inferred'];
  if(preg_match('/\.el(\d+)/',$kv,$e)) return ['RHEL / Rocky / Alma '.$e[1],$kv,'inferred'];
  if(preg_match('/\.fc(\d+)/',$kv,$e)) return ['Fedora '.$e[1],$kv,'inferred'];
  return ['Linux',$kv,'descr']; }
 if(preg_match('/^(.+?)\s*Hardware:/s',$d,$m)){ $o=rtrim(trim(preg_replace('/\s*\([^)]*\)\.?\s*$/','',trim($m[1]))),'. '); if($o!=='') return [$o,'','descr']; }
 return [substr(trim(preg_replace('/\s+/',' ',$d)),0,60),'','descr']; }
function filter_disks($disks,$ignore){ $ig=array_filter(array_map(fn($x)=>strtolower(trim($x)),explode(',',(string)$ignore))); return array_values(array_filter($disks,fn($d)=>!in_array(strtolower($d['n']),$ig,true))); }
function snmp_table($h,$c,$oid){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); $r=@snmp_rw($h,$c,$oid); $o=[]; if(is_array($r)) foreach($r as $k=>$v){ $p=strrpos($k,'.'); if($p!==false) $o[(int)substr($k,$p+1)]=$v; } return $o; }
function ifnum($v){ $v=(string)$v; if(preg_match('/\((\d+)\)/',$v,$m)) return (int)$m[1]; if(is_numeric($v)) return (int)$v; $l=strtolower($v); foreach(['up'=>1,'down'=>2,'testing'=>3,'unknown'=>4,'dormant'=>5,'notpresent'=>6,'lowerlayerdown'=>7,'softwareloopback'=>24,'ethernetcsmacd'=>6] as $n=>$i) if(strpos($l,$n)===0) return $i; return 0; }
function fmt_mac($v){ $v=(string)$v; if(strlen($v)===6&&preg_match('/[^\x20-\x7e]/',$v)) return implode(':',str_split(bin2hex($v),2)); return trim($v); }
function win_if_noise($n,$d){ $s=$n.' '.$d; return (bool)preg_match('/LightWeight Filter|QoS Packet Scheduler|Kernel Debugger|Teredo|6to4 Adapter|IP-HTTPS|isatap|Wan Miniport|Npcap Packet Driver|Local Area Connection\*/i',$s); }
function snmp_if_ips($h,$c){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); $m=[];
 $r=@snmp_rw($h,$c,'1.3.6.1.2.1.4.20.1.2'); if(is_array($r)) foreach($r as $k=>$v){ if(preg_match('/(\d+\.\d+\.\d+\.\d+)$/',(string)$k,$x)&&strpos($x[1],'127.')!==0&&strpos($x[1],'169.254.')!==0){ $ix=(int)preg_replace('/\D/','',(string)$v); if($ix>0) $m[$ix][]=$x[1]; } }
 $r=@snmp_rw($h,$c,'1.3.6.1.2.1.4.34.1.3'); if(is_array($r)) foreach($r as $k=>$v){ $b=null; $k=(string)$k;
  if(preg_match('/4\.34\.1\.3\.2\.16\.((?:\d+\.){15}\d+)$/',$k,$x)) $b=pack('C*',...array_map('intval',explode('.',$x[1])));
  elseif(preg_match('/ipv6\.\"?((?:[0-9a-fA-F]{1,2}:){15}[0-9a-fA-F]{1,2})\"?$/',$k,$x)) $b=hex2bin(implode('',array_map(fn($p)=>str_pad($p,2,'0',STR_PAD_LEFT),explode(':',$x[1]))));
  if($b===null||strlen($b)!==16) continue; $ip=@inet_ntop($b); if(!$ip||$ip==='::1'||stripos($ip,'fe80:')===0) continue; $ix=(int)preg_replace('/\D/','',(string)$v); if($ix>0) $m[$ix][]=$ip; }
 foreach($m as $i=>$l) $m[$i]=array_values(array_unique($l)); return $m; }
// Döljer logiska gränssnitt på nätverksenheter: nedtagna LAG, interna slot-nummer (UniFi/FASTPATH 3/1–3/26 när riktiga portar är 0/x) och VAP/VLAN-subinterface på accesspunkter
function nw_if_filter($ifs,$role){ if(!in_array($role,['switch','ap','router'],true)) return $ifs; $slot0=false; foreach($ifs as $i) if(preg_match('#^0/\d+$#',(string)$i['name'])){ $slot0=true; break; }
 return array_values(array_filter($ifs,function($i) use($role,$slot0){ $n=(string)$i['name']; $lag=(int)$i['type']===161;
  if($lag&&(int)$i['oper']!==1) return false;
  if($slot0&&preg_match('#^[1-9]\d*/\d+$#',$n)&&!($lag&&(int)$i['oper']===1)) return false;
  if($role==='ap'&&preg_match('/^(wifi\d+ap\d+|.+\.\d+$|erspan|gre|gretap|ip6tnl|ip6gre|sit\d|ifb|imq|teql|mon\.|bond\d|ath\d|wlan\d)/i',$n)) return false;
  return true; })); }
function snmp_ifaces($h,$c,$max=32,$skip=[],$role=''){
 $nm=snmp_table($h,$c,'1.3.6.1.2.1.31.1.1.1.1'); $ds=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.2'); if(!$ds&&!$nm) return [];
 $al=snmp_table($h,$c,'1.3.6.1.2.1.31.1.1.1.18'); $ty=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.3'); $hs=snmp_table($h,$c,'1.3.6.1.2.1.31.1.1.1.15'); $sp=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.5'); $mc=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.6');
 $ad=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.7'); $op=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.8');
 $hi=snmp_table($h,$c,'1.3.6.1.2.1.31.1.1.1.6'); $hc=(bool)$hi; $ho=$hc?snmp_table($h,$c,'1.3.6.1.2.1.31.1.1.1.10'):[];
 $ii=$hc?$hi:snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.10'); $oo=$hc?$ho:snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.16');
 $ie=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.14'); $oe=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.20'); $idc=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.13'); $odc=snmp_table($h,$c,'1.3.6.1.2.1.2.2.1.19'); $crc=snmp_table($h,$c,'1.3.6.1.2.1.10.7.2.1.3');
 $ipm=snmp_if_ips($h,$c);
 $out=[];
 foreach(($ds?:$nm) as $i=>$unused){ $name=trim((string)($nm[$i]??''))?:trim((string)($ds[$i]??'')); $descr=trim((string)($ds[$i]??'')); $type=ifnum($ty[$i]??0);
  if($type===24||in_array($type,$skip,true)||($skip&&preg_match('/^(Null|Vl\d|Vlan|Loopback|Lo\d|Stack|Sup-eth|Tunnel|CPU|oob)/i',$name))||preg_match('/^(lo|virbr|cali|flannel|cni|tunl|kube|dummy|tap\d+i\d+|fwbr\d+|fwln\d+|fwpr\d+)/i',$name)) continue;
  if(win_if_noise($name,$descr)&&!(preg_match('/^Local Area Connection\*/i',$name.' '.$descr)&&!empty($ipm[$i]))) continue;
  $speed=isset($hs[$i])&&(int)$hs[$i]>0?(int)$hs[$i]:(isset($sp[$i])?(int)((float)$sp[$i]/1e6):0);
  $out[]=['idx'=>$i,'name'=>$name,'descr'=>$descr,'alias'=>trim((string)($al[$i]??'')),'type'=>$type,'speed'=>$speed,'mac'=>fmt_mac($mc[$i]??''),'ips'=>implode(', ',$ipm[$i]??[]),'admin'=>ifnum($ad[$i]??0),'oper'=>ifnum($op[$i]??0),
   'in'=>(float)($ii[$i]??0),'out'=>(float)($oo[$i]??0),'ierr'=>(float)($ie[$i]??0),'oerr'=>(float)($oe[$i]??0),'idisc'=>(float)($idc[$i]??0),'odisc'=>(float)($odc[$i]??0),'crc'=>isset($crc[$i])?(float)$crc[$i]:null,'hc'=>$hc];
  if(count($out)>=$max) break; }
 return nw_if_filter($out,$role); }
function snmp_custom($h,$c,$sid=null,$explicit=false){ $out=[]; foreach(json_decode(setting('snmp_oids','[]'),true)?:[] as $o){ if(($explicit||!empty($o['servers']))&&($sid===null||empty($o['servers'])||!in_array((int)$sid,array_map('intval',$o['servers']),true))) continue; $out[]=['name'=>$o['name']??'','oid'=>$o['oid'],'value'=>($v=sget($h,$c,$o['oid']))===null?null:substr($v,0,200)]; } return $out; }
// Hypervisor-info via SNMP: VMware ESXi (VMWARE-SYSTEM/VMINFO-MIB) och Proxmox VE (VM:ar härleds ur processlistan, ingen officiell MIB)
function snmp_hv($h,$c,$sd,$oid){ $sd=(string)$sd; $oid=(string)$oid;
 if(stripos($sd,'VMware ESX')!==false||strpos($oid,'.6876.')!==false||strpos($oid,'6876.4')!==false){
  $prod=trim(sget($h,$c,'1.3.6.1.4.1.6876.1.1.0').' '.sget($h,$c,'1.3.6.1.4.1.6876.1.2.0')); $bld=sget($h,$c,'1.3.6.1.4.1.6876.1.4.0');
  $nm=snmp_table($h,$c,'1.3.6.1.4.1.6876.2.1.1.2'); $go=snmp_table($h,$c,'1.3.6.1.4.1.6876.2.1.1.4'); $mm=snmp_table($h,$c,'1.3.6.1.4.1.6876.2.1.1.5'); $st=snmp_table($h,$c,'1.3.6.1.4.1.6876.2.1.1.6'); $cp=snmp_table($h,$c,'1.3.6.1.4.1.6876.2.1.1.9'); $vms=[];
  foreach($nm as $i=>$n){ $vms[]=['name'=>trim((string)$n),'state'=>strtolower(trim((string)($st[$i]??''))),'cpus'=>(int)($cp[$i]??0),'mem'=>(int)($mm[$i]??0),'os'=>trim((string)($go[$i]??''))]; if(count($vms)>=300) break; }
  return ['type'=>'vmware','product'=>trim($prod.($bld?" build $bld":'')),'vms'=>$vms]; }
 if(stripos($sd,'-pve')!==false){ $pn=snmp_table($h,$c,'1.3.6.1.2.1.25.4.2.1.2'); $vms=[];
  if(snmp_is_pbs($pn)) return ['type'=>'pbs','product'=>'Proxmox Backup Server','vms'=>[]];
  foreach($pn as $i=>$n){ if(!preg_match('/^(kvm|qemu-system)/',trim((string)$n))) continue; $p=(string)sget($h,$c,'1.3.6.1.2.1.25.4.2.1.5.'.$i);
   $vms[]=['name'=>preg_match('/-name\s+(?:guest=)?([^,\s]+)/',$p,$m)?$m[1]:'pid '.$i,'id'=>preg_match('/-id\s+(\d+)/',$p,$m2)?$m2[1]:'','state'=>'running','cpus'=>preg_match('/-smp\s+(\d+)/',$p,$m3)?(int)$m3[1]:0]; if(count($vms)>=300) break; }
  return ['type'=>'proxmox','product'=>'Proxmox VE','vms'=>$vms]; }
 return null; }
function snmp_is_pbs($pn){ $pbs=false; $pve=false; foreach($pn as $n){ $n=trim((string)$n); if(strpos($n,'proxmox-backup')===0) $pbs=true; if(preg_match('/^(pveproxy|pvedaemon|pvestatd|pve-)/',$n)) $pve=true; } return $pbs&&!$pve; }
// Är servern en virtuell maskin? Via MAC-prefix (VMware, Hyper-V, KVM/Proxmox m.fl.) och hårdvarubeskrivningar från SNMP (hrDeviceDescr, CPU-modell)
function virt_detect($dd,$model,$ifs,$sd){ $oui=['00:50:56'=>'VMware','00:0c:29'=>'VMware','00:05:69'=>'VMware','00:1c:14'=>'VMware','00:15:5d'=>'Hyper-V','52:54:00'=>'KVM/QEMU','bc:24:11'=>'KVM/QEMU','00:16:3e'=>'Xen','08:00:27'=>'VirtualBox','00:1c:42'=>'Parallels'];
 foreach($ifs as $i){ $n=(string)($i['name']??$i['descr']??''); if(preg_match('/^(lo|virbr|docker|br-|veth|vnet|vmnet|vboxnet|tap|tun|cni|flannel|cali|vmbr|vEthernet)/i',$n)||preg_match('/vEthernet/i',(string)($i['descr']??''))) continue; $m=strtolower(substr((string)($i['mac']??''),0,8)); if(isset($oui[$m])) return ['t'=>$oui[$m],'src'=>'mac']; }
 $txt=implode(' | ',array_merge([(string)$model,(string)$sd],array_map('strval',$dd)));
 foreach(['VMware'=>'/vmware|vmxnet/i','Hyper-V'=>'/Microsoft Hyper-V Network Adapter/i','KVM/QEMU'=>'/qemu|\bkvm\b|virtio|bochs/i','VirtualBox'=>'/virtualbox|\bvbox/i','Xen'=>'/\bxen\b|xenserver/i','Parallels'=>'/parallels/i'] as $t=>$re) if(preg_match($re,$txt)) return ['t'=>$t,'src'=>'hw'];
 return null; }
function vm_name_map(){ static $m=null; if($m!==null) return $m; $m=[]; $L=['proxmox'=>'Proxmox VE','vmware'=>'VMware ESXi','hyperv'=>'Hyper-V']; foreach(db()->query('SELECT name,hv_type,hv_data FROM servers WHERE hv_type IS NOT NULL')->fetchAll() as $h){ foreach((json_decode((string)$h['hv_data'],true)['vms']??[]) as $v){ $k=strtolower(trim((string)($v['n']??''))); if($k!==''&&!isset($m[$k])) $m[$k]=['h'=>$h['name'],'t'=>$L[$h['hv_type']]??$h['hv_type']]; } } return $m; }
// Virtuell maskin? Träff mot en VM i en övervakad virtualiseringshost (namn/värdnamn) eller SNMP-detektering. Switchar, hostar och tjänster hoppas över.
function srv_virt($s,$inf){ if(!empty($s['is_services'])||!empty($s['hv_type'])||!empty($s['nw_type'])) return null; $v=$inf['virt']??null; $m=vm_name_map(); $hn=strtolower(explode('.',trim((string)($inf['hostname']??'')))[0]);
 foreach(array_filter([strtolower(trim((string)$s['name'])),$hn]) as $k) if(isset($m[$k])) return ['t'=>$v['t']??null,'h'=>$m[$k]['h'],'hv'=>$m[$k]['t']]; return $v?['t'=>$v['t'],'h'=>null,'hv'=>null]:null; }
// ---- SMART-hälsa (SNMP: Synology-MIB eller snmpd extend "smart"; Zabbix: Agent 2 smart.disk.*)
function smart_row($x){ $n=(string)($x['n']??'?'); $hl=$x['health']??null; $re=isset($x['realloc'])&&is_numeric($x['realloc'])?(int)$x['realloc']:null; $pe=isset($x['pend'])&&is_numeric($x['pend'])?(int)$x['pend']:null; $tp=isset($x['temp'])&&is_numeric($x['temp'])?(int)$x['temp']:null;
 $st='unk'; if($hl==='fail') $st='fail'; elseif($hl==='ok') $st='ok'; if($st!=='fail'&&(($re??0)>0||($pe??0)>0||!empty($x['warn'])||($tp!==null&&$tp>=60))) $st='warn'; if($st==='unk'&&$hl===null&&($re!==null||$pe!==null)) $st='ok';
 return ['n'=>$n,'model'=>trim((string)($x['model']??'')),'serial'=>trim((string)($x['serial']??'')),'st'=>$st,'temp'=>$tp,'poh'=>isset($x['poh'])&&is_numeric($x['poh'])?((int)$x['poh']>0xFFFFFFFF?((int)$x['poh']&0xFFFFFFFF):(int)$x['poh']):null,'realloc'=>$re,'pend'=>$pe,'src'=>(string)($x['src']??'')]; }
function smart_snmp($h,$c){ $o=[];
 $id=snmp_walk($h,$c,'1.3.6.1.4.1.6574.2.1.1.2'); // Synology DiskStation
 if($id){ $md=snmp_walk($h,$c,'1.3.6.1.4.1.6574.2.1.1.3'); $stt=snmp_walk($h,$c,'1.3.6.1.4.1.6574.2.1.1.5'); $tp=snmp_walk($h,$c,'1.3.6.1.4.1.6574.2.1.1.6'); $hs=snmp_walk($h,$c,'1.3.6.1.4.1.6574.2.1.1.13'); $bd=snmp_walk($h,$c,'1.3.6.1.4.1.6574.2.1.1.9');
  foreach($id as $i=>$n){ $s=(int)($stt[$i]??1); $hv=(int)($hs[$i]??0); $o[]=smart_row(['n'=>trim((string)$n),'model'=>$md[$i]??'','health'=>($s>=4||$hv>=3)?'fail':'ok','warn'=>$hv===2,'temp'=>$tp[$i]??null,'realloc'=>$bd[$i]??null,'src'=>'synology']); } }
 $ln=snmp_walk($h,$c,'1.3.6.1.4.1.8072.1.3.2.4.1.2.5.115.109.97.114.116'); // NET-SNMP-EXTEND-MIB: extend smart <skript>
 foreach($ln as $l){ $p=array_map('trim',explode('|',(string)$l)); if(count($p)<4||$p[0]==='') continue; $hv=strtoupper($p[3]); $o[]=smart_row(['n'=>$p[0],'model'=>$p[1],'serial'=>$p[2],'health'=>strpos($hv,'PASS')===0||$hv==='OK'?'ok':(strpos($hv,'FAIL')===0?'fail':null),'temp'=>$p[4]??null,'poh'=>$p[5]??null,'realloc'=>$p[6]??null,'pend'=>$p[7]??null,'src'=>'extend']); }
 return $o; }
function smart_find($a,$re){ if(!is_array($a)) return null; if(isset($a['name'])&&is_string($a['name'])&&preg_match($re,$a['name'])) return $a['raw_value']??($a['raw']['value']??($a['value']??null));
 foreach($a as $k=>$v){ if(is_array($v)){ $r=smart_find($v,$re); if($r!==null) return $r; } elseif(is_string($k)&&preg_match($re,$k)) return $v; } return null; }
function zbx_smart($h,$p=10050){ $r=zbx($h,$p,'smart.disk.discovery'); if($r===null) return [false,lasterr()?:t('ingen respons','no response')];
 if(stripos($r,'ZBX_NOTSUPPORTED')!==false||stripos($r,'not supported')!==false) return [false,t('SMART kräver Zabbix Agent 2 med smartctl installerat','SMART requires Zabbix Agent 2 with smartctl installed')];
 $l=json_decode($r,true); if(!is_array($l)) return [false,t('oväntat svar från agenten','unexpected reply from the agent').': '.substr($r,0,80)]; $o=[];
 foreach($l as $x){ $n=$x['{#NAME}']??($x['name']??null); if($n===null) continue; $g=zbx($h,$p,'smart.disk.get['.$n.']'); $j=json_decode((string)$g,true); if(!is_array($j)){ $o[]=smart_row(['n'=>$n,'src'=>'zabbix']); continue; }
  $ss=$j['smart_status']??($j['health']??null); $ok=is_array($ss)?($ss['passed']??null):(is_bool($ss)?$ss:(is_string($ss)?(stripos($ss,'PASS')===0||strtolower($ss)==='ok'):null));
  $o[]=smart_row(['n'=>$j['disk_name']??$n,'model'=>$j['model_name']??($j['model']??''),'serial'=>$j['serial_number']??'','health'=>$ok===null?null:($ok?'ok':'fail'),'temp'=>$j['temperature']??smart_find($j,'/^temperature/i'),'poh'=>$j['power_on_time']??smart_find($j,'/power_on/i'),'realloc'=>smart_find($j,'/realloc/i'),'pend'=>smart_find($j,'/pending/i'),'src'=>'zabbix']); }
 $b=count(array_filter($o,fn($x)=>$x['st']==='fail')); $w=count(array_filter($o,fn($x)=>$x['st']==='warn'));
 return [true,count($o).t(' diskar',' disks').($b?" · $b ".t('felar','failing'):'').($w?" · $w ".t('varnar','warning'):''),null,['smart'=>$o]]; }
// Proxmox VE / PBS: /disks/list + /disks/smart (samma format). $get($devpath,$name) -> smart-svar eller null
function smart_api($list,$get,$t0=null){ $o=[]; $t0=$t0??time(); foreach(array_slice(is_array($list)?$list:[],0,24) as $x){ $dev=(string)($x['devpath']??''); $nm=(string)($x['name']??basename($dev)); if($nm==='') continue; $hl=strtolower((string)($x['health']??$x['status']??''));
  $r=['n'=>$nm,'model'=>$x['model']??'','serial'=>$x['serial']??'','health'=>($hl==='passed'||$hl==='ok')?'ok':($hl==='failed'?'fail':null),'src'=>'api'];
  $wo=$x['wearout']??null; if(is_numeric($wo)&&(float)$wo<10) $r['warn']=true;
  if(time()-$t0<25){ $sj=$get($dev,$nm); $sd=is_array($sj)?($sj['data']??[]):[]; $h2=strtolower((string)($sd['health']??$sd['status']??'')); if($r['health']===null&&($h2==='passed'||$h2==='ok')) $r['health']='ok'; if($h2==='failed') $r['health']='fail';
   foreach($sd['attributes']??[] as $a){ $id=(int)($a['id']??0); $raw=(int)preg_replace('/[^0-9].*$/','',(string)($a['raw']??$a['value']??'0')); if($id===5) $r['realloc']=$raw; elseif($id===197) $r['pend']=$raw; elseif($id===9) $r['poh']=$raw; elseif(($id===194||$id===190)&&!isset($r['temp'])) $r['temp']=$raw; } }
  $o[]=smart_row($r); } return $o; }
function srv_smart($info,$rows){ $l=is_array($info)?($info['smart']??[]):[]; foreach($rows as $c){ if($c['source']==='zabbix'&&$c['kind']==='smart'&&$c['last_data']){ $d=json_decode($c['last_data'],true); foreach($d['smart']??[] as $x) $l[]=$x; } elseif($c['source']==='pbs'&&$c['last_data']){ $d=json_decode($c['last_data'],true); foreach($d['pbs']['smart']??[] as $x) $l[]=$x; } } return array_values($l); }
function ha_snmp($h,$c){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); $r=@snmp_rw($h,$c,'1.3.6.1.4.1.8072.1.3.2.4.1.2'); $o=[]; if(!is_array($r)) return null;
 foreach($r as $k=>$v){ $n=null; $ln=1;
  if(preg_match('/nsExtendOutLine\.\"?([^\"]+?)\"?\.(\d+)$/',$k,$m)){ $n=$m[1]; $ln=(int)$m[2]; }
  elseif(preg_match('/8072\.1\.3\.2\.4\.1\.2\.(\d+(?:\.\d+)+)$/',$k,$m)){ $p=array_map('intval',explode('.',$m[1])); $len=array_shift($p); $ln=array_pop($p); if(count($p)===$len) $n=implode('',array_map('chr',$p)); }
  if($n===null||strpos($n,'hass_')!==0||$ln!==1) continue; $v=trim(preg_replace('/^STRING:\s*/','',trim((string)$v)),"\" \r\n\t"); if($v!=='') $o[substr($n,5)]=$v; }
 return $o?:null; }
// Sensorer via SNMP för servrar och hostar: temperaturer och fläktvarvtal. Standard-MIB:ar först (LM-SENSORS i net-snmp, ENTITY-SENSOR), därefter
// HPE (CPQHLTH) och Dell OpenManage. Enheter som inte exponerar något ger tomma listor. Namnet avgör om en temperatur är moderkort, CPU eller disk (visas i listan).
function hw_temp_role($n){ $n=strtolower((string)$n); if(preg_match('/cpu|core|package|proc|socket/',$n)) return 'cpu'; if(preg_match('/mother|mainboard|\bmb\b|system ?board|chassis|ambient|inlet|exhaust|outlet|\bsys|board|pch|vrm/',$n)) return 'board'; if(preg_match('/disk|hdd|nvme|ssd|drive|sata|sas/',$n)) return 'disk'; return ''; }
function snmp_sensors($h,$g){ $o=['temps'=>[],'fans'=>[]]; $nz=fn($v)=>is_numeric($v);
 $nm=snmp_table($h,$g,'1.3.6.1.4.1.2021.13.16.2.1.2'); foreach(snmp_table($h,$g,'1.3.6.1.4.1.2021.13.16.2.1.3') as $i=>$x){ if(!$nz($x)) continue; $v=(float)$x/1000; if($v>-40&&$v<150&&abs($v)>0.01) $o['temps'][]=['n'=>trim((string)($nm[$i]??''))?:'Temp '.$i,'v'=>round($v,1)]; }
 $nm=snmp_table($h,$g,'1.3.6.1.4.1.2021.13.16.3.1.2'); foreach(snmp_table($h,$g,'1.3.6.1.4.1.2021.13.16.3.1.3') as $i=>$x) if($nz($x)) $o['fans'][]=['n'=>trim((string)($nm[$i]??''))?:'Fan '.$i,'rpm'=>(int)$x,'ok'=>(int)$x>0];
 if(!$o['temps']||!$o['fans']){ $ty=snmp_table($h,$g,'1.3.6.1.2.1.99.1.1.1.1'); if($ty){ $sc=snmp_table($h,$g,'1.3.6.1.2.1.99.1.1.1.2'); $pc=snmp_table($h,$g,'1.3.6.1.2.1.99.1.1.1.3'); $va=snmp_table($h,$g,'1.3.6.1.2.1.99.1.1.1.4'); $en=snmp_table($h,$g,'1.3.6.1.2.1.47.1.1.1.1.7'); $ed=snmp_table($h,$g,'1.3.6.1.2.1.47.1.1.1.1.2');
  foreach($ty as $i=>$x){ if(!isset($va[$i])||!$nz($va[$i])) continue; $real=(float)$va[$i]*pow(10,((int)($sc[$i]??9)-9)*3)/pow(10,(int)($pc[$i]??0)); $n=trim((string)($en[$i]??''))?:(trim((string)($ed[$i]??''))?:'#'.$i); $x=(int)$x;
   if($x===8&&!$o['temps']&&$real>0&&$real<150) $o['temps'][]=['n'=>$n,'v'=>round($real,1)]; elseif($x===10&&!$o['fans']) $o['fans'][]=['n'=>$n,'rpm'=>(int)$real,'ok'=>$real>0]; } } }
 if(!$o['temps']){ $lc=bmc_tbl($h,$g,'1.3.6.1.4.1.232.6.2.6.8.1.3'); $loc=[1=>'Other',3=>'System',4=>'System board',5=>'I/O board',6=>'CPU',7=>'Memory',8=>'Storage',10=>'Power supply',11=>'Ambient',12=>'Chassis']; foreach(bmc_tbl($h,$g,'1.3.6.1.4.1.232.6.2.6.8.1.4') as $i=>$x) if($nz($x)&&(float)$x>0&&(float)$x<150) $o['temps'][]=['n'=>($loc[(int)($lc[$i]??0)]??'Sensor').' '.$i,'v'=>(float)$x]; }
 if(!$o['temps']){ $nm=bmc_tbl($h,$g,'1.3.6.1.4.1.674.10892.1.700.20.1.8'); foreach(bmc_tbl($h,$g,'1.3.6.1.4.1.674.10892.1.700.20.1.6') as $i=>$x) if($nz($x)&&(float)$x>0&&(float)$x/10<150) $o['temps'][]=['n'=>trim((string)($nm[$i]??''))?:'Temp '.$i,'v'=>round((float)$x/10,1)]; }
 if(!$o['fans']){ $nm=bmc_tbl($h,$g,'1.3.6.1.4.1.674.10892.1.700.12.1.8'); $st=bmc_tbl($h,$g,'1.3.6.1.4.1.674.10892.1.700.12.1.5'); foreach(bmc_tbl($h,$g,'1.3.6.1.4.1.674.10892.1.700.12.1.6') as $i=>$x) if($nz($x)) $o['fans'][]=['n'=>trim((string)($nm[$i]??''))?:'Fan '.$i,'rpm'=>(int)$x,'ok'=>isset($st[$i])?(int)$st[$i]===3:(int)$x>0]; }
 $o['temps']=array_slice($o['temps'],0,24); $o['fans']=array_slice($o['fans'],0,24); return $o; }
// Sensorer sparas högst var femte minut per enhet (de ändras långsamt och varje SNMP-svep belastar enheten)
function snmp_hw($h,$g,$old=null,$ttl=300){ if(is_array($old)&&!empty($old['ts'])&&time()-(int)$old['ts']<$ttl&&isset($old['temps'])) return $old; $o=snmp_sensors($h,$g); $o['ts']=time(); return $o; }
function snmp_all($h,$c,$sid=null){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); error_clear_last();
 $sd=@snmp_gt($h,$c,'1.3.6.1.2.1.1.1.0');
 if($sd===false||$sd===null){ lasterr("SNMP $h: ".(error_get_last()['message']??t('ingen respons – kontrollera community, brandvägg och att SNMP tillåter monitor-IP','no response – check community, firewall and that SNMP allows the monitor IP'))); return null; }
 $cpu=array_map('floatval',snmp_walk($h,$c,'1.3.6.1.2.1.25.3.3.1.2'));
 $d=snmp_walk($h,$c,'1.3.6.1.2.1.25.2.3.1.3');$ty=snmp_walk($h,$c,'1.3.6.1.2.1.25.2.3.1.2');$u=snmp_walk($h,$c,'1.3.6.1.2.1.25.2.3.1.4');$sz=snmp_walk($h,$c,'1.3.6.1.2.1.25.2.3.1.5');$us=snmp_walk($h,$c,'1.3.6.1.2.1.25.2.3.1.6');
 $tot=0;$usd=0;$cache=0;$disks=[];$swp=null;
 foreach($d as $i=>$name){ $un=(float)($u[$i]??1); $tt=(float)($sz[$i]??0)*$un; $x=(float)($us[$i]??0)*$un; $type=$ty[$i]??'';
  if(preg_match('/Memory buffers|Cached memory/i',$name)) $cache+=$x;
  elseif(preg_match('/hrStorageVirtualMemory|25\.2\.1\.3$/',$type)){ if($tt>0&&preg_match('/swap|page ?file/i',$name)) $swp=['t'=>$tt,'u'=>$x]; }
  elseif(preg_match('/hrStorageRam|25\.2\.1\.2$/',$type)){ if($tt>$tot){$tot=$tt;$usd=$x;} }
  elseif(preg_match('/hrStorageFixedDisk|25\.2\.1\.4$/',$type)&&$tt>0&&!preg_match('#^/(run|dev|sys|proc|snap)#',$name)) $disks[]=['n'=>short_disk($name),'full'=>trim($name),'t'=>$tt,'f'=>$tt-$x]; }
 $oid=sget($h,$c,'1.3.6.1.2.1.1.2.0'); [$os,$kv,$src]=os_guess((string)$sd,(string)$oid);
 $ext=sget($h,$c,'1.3.6.1.4.1.8072.1.3.2.3.1.1.9.111.115.114.101.108.101.97.115.101'); if($ext){ $os=$ext; $src='extend'; }
 $model=null; $dt=snmp_walk($h,$c,'1.3.6.1.2.1.25.3.2.1.2'); $dd=snmp_walk($h,$c,'1.3.6.1.2.1.25.3.2.1.3');
 foreach($dt as $i=>$t2) if(preg_match('/hrDeviceProcessor|25\.3\.1\.3$/',$t2)){ $model=preg_replace('/^(GenuineIntel|AuthenticAMD):\s*/','',trim($dd[$i]??'')); break; }
 $ips=array_slice(array_values(array_filter(snmp_walk($h,$c,'1.3.6.1.2.1.4.20.1.1'),fn($x)=>strpos($x,'127.')!==0&&strpos($x,'169.254.')!==0)),0,8);
 $mu=$tot?max(0,$usd-$cache):null;
 if(!$swp){ $st1=nw_num(sget($h,$c,'1.3.6.1.4.1.2021.4.3.0')); $sa=nw_num(sget($h,$c,'1.3.6.1.4.1.2021.4.4.0')); if($st1>0&&$sa!==null) $swp=['t'=>$st1*1024,'u'=>max(0,$st1-$sa)*1024]; }
 $oldhw=null; if($sid){ try{ $q=db()->prepare('SELECT info FROM servers WHERE id=?'); $q->execute([$sid]); $oldhw=(json_decode((string)$q->fetchColumn(),true)?:[])['hw']??null; }catch(Throwable $e){} } $hw=snmp_hw($h,$c,$oldhw); $hw['swap']=$swp;
 $ifs=snmp_ifaces($h,$c,96); $cu=snmp_custom($h,$c,$sid); $ses=stripos((string)$sd,'windows')!==false?array_slice(array_values(array_unique(array_filter(array_map(fn($x)=>trim((string)$x,"\" \r\n\t"),snmp_table($h,$c,'1.3.6.1.4.1.77.1.2.25.1.1'))))),0,50):[]; // Windows: LAN Manager svUserName (inloggade användare) $hv=snmp_hv($h,$c,$sd,$oid); if(($hv['type']??'')==='pbs'){ $os='Proxmox Backup Server'; $src='descr'; }
 return ['ifs'=>$ifs,'cpu'=>$cpu?round(array_sum($cpu)/count($cpu),1):null,'cores'=>count($cpu)?:null,'mem'=>$tot?round($mu/$tot*100,1):null,'mem_t'=>$tot?:null,'mem_u'=>$mu,'disks'=>$disks,'os'=>$os,
  'info'=>['hw'=>$hw,'ha'=>preg_match('/home\s*assistant/i',(string)$sd)?ha_snmp($h,$c):null,'smart'=>smart_snmp($h,$c),'virt'=>virt_detect($dd,$model,$ifs,$sd),'hv'=>$hv,'custom'=>$cu,'oid'=>$oid,'hostname'=>sget($h,$c,'1.3.6.1.2.1.1.5.0'),'os'=>$os,'os_src'=>$src,'kernel'=>$kv,'cpu_model'=>$model,'cores'=>count($cpu)?:null,'mem_total'=>$tot?:null,
   'uptime'=>ticks(sget($h,$c,'1.3.6.1.2.1.25.1.1.0')??sget($h,$c,'1.3.6.1.2.1.1.3.0')),'ips'=>$ips,'procs'=>sget($h,$c,'1.3.6.1.2.1.25.1.6.0'),'users'=>sget($h,$c,'1.3.6.1.2.1.25.1.5.0'),'sessions'=>$ses,
   'location'=>sget($h,$c,'1.3.6.1.2.1.1.6.0'),'contact'=>sget($h,$c,'1.3.6.1.2.1.1.4.0'),'descr'=>trim((string)$sd)]]; }
function zbx_metric($h,$p,$k){
 if($k==='cpu'){ $r=zbx($h,$p,'system.cpu.util'); if($r===null) return null; $n=zbx($h,$p,'system.cpu.num'); return [round((float)$r,1),$n===null?[]:['cores'=>(int)$n]]; }
 if($k==='mem'){ $r=zbx($h,$p,'vm.memory.size[pavailable]'); if($r===null) return null; $tt=zbx($h,$p,'vm.memory.size[total]'); $pc=round(100-(float)$r,1); return [$pc,$tt===null?[]:['t'=>(float)$tt,'u'=>(float)$tt*$pc/100]]; }
 $j=json_decode(zbx($h,$p,'vfs.fs.discovery')?:'[]',true)?:[]; $j=$j['data']??$j; $disks=[];
 foreach($j as $f){ $n=$f['{#FSNAME}']??''; if($n===''||in_array($f['{#FSTYPE}']??'',['tmpfs','devtmpfs','squashfs','overlay','proc','sysfs','cgroup','cgroup2','iso9660'])) continue;
  $tt=(float)zbx($h,$p,"vfs.fs.size[$n,total]"); $fr=(float)zbx($h,$p,"vfs.fs.size[$n,free]"); if($tt>0) $disks[]=['n'=>$n,'full'=>$n,'t'=>$tt,'f'=>$fr]; }
 return $disks?[null,$disks]:null; }
function fping_bin(){ static $b=null; if($b===null){ $b=''; if(stripos(PHP_OS,'WIN')!==0&&function_exists('exec')) foreach(['/usr/bin/fping','/usr/sbin/fping','/usr/local/bin/fping','/usr/local/sbin/fping','/bin/fping'] as $p) if(@is_executable($p)){ $b=$p; break; } } return $b; }
// Pingar många värdar på en gång (fping, om det finns). Returnerar [check-id => [ok,detalj]]. Saknas fping eller får en värd inget tolkbart svar pingas den med vanlig ping.
function ping_many($cks,$fast=false){ $res=[]; $hosts=[]; foreach($cks as $c) $hosts[(string)$c['host']]=1; $hosts=array_keys($hosts); $rtt=[]; $bin=fping_bin();
 if($bin&&$hosts){ foreach(array_chunk($hosts,250) as $ch){ $out=[]; @exec(escapeshellarg($bin).' -C 1 -q -t '.($fast?1000:2000).' -i 2 '.implode(' ',array_map('escapeshellarg',$ch)).' 2>&1',$out); foreach($out as $l){ if(preg_match('/^(\S+)\s*:\s*(\d+(?:\.\d+)?|-)\s*$/',trim($l),$m)) $rtt[$m[1]]=$m[2]==='-'?false:(float)$m[2]; } } }
 $cache=[]; foreach($cks as $c){ $h=(string)$c['host']; if(!isset($cache[$h])){ if(array_key_exists($h,$rtt)) $cache[$h]=$rtt[$h]===false?[false,t('inget svar på ping','no reply to ping')]:[true,($rtt[$h]<10?round($rtt[$h],1):round($rtt[$h])).' ms']; else $cache[$h]=ping_host($h); } $res[$c['id']]=$cache[$h]; }
 return $res; }
function ping_host($h){ if(!function_exists('exec')) return [false,t('PHP exec() är avstängd (disable_functions) – ping kan inte köras','PHP exec() is disabled (disable_functions) – ping cannot run')]; $w=stripos(PHP_OS,'WIN')===0; $t0=microtime(true);
 $fast=!empty($GLOBALS['ping_fast']); @exec(($w?($fast?'ping -n 1 -w 1000 ':'ping -n 1 -w 2000 '):($fast?'ping -c 1 -W 1 ':'ping -c 1 -W 2 ')).escapeshellarg($h).' 2>&1',$o,$rc);
 if($rc===0) return [true,round((microtime(true)-$t0)*1000).' ms'];
 return [false,$rc===127?t('kommandot ping finns inte på monitor-servern','the ping command is missing on the monitor server'):t('inget svar på ping (rc=','no reply to ping (rc=').$rc.') '.substr(trim(implode(' ',$o)),0,120)]; }
// Returnerar [ok, detalj, värde|null, data|null]
function run_check($c){ lasterr(''); $k=$c['kind'];$t=trim($c['target']);$h=$c['host'];
 if($c['source']==='snmp'){ if(!function_exists('snmp2_walk')) return [false,t('PHP SNMP-tillägget saknas (installera php-snmp och starta om webbservern)','PHP SNMP extension missing (install php-snmp and restart the web server)')];
  $m=snmp_all($h,snmp_cfg($c['param']),$c['server_id']??null); if(!$m) return [false,lasterr()?:'SNMP'];
  $d=[]; if($m['cpu']!==null)$d[]='CPU '.$m['cpu'].'%'; if($m['mem']!==null)$d[]=t('Minne ','Mem ').$m['mem'].'%'; $d[]=count($m['disks']).t(' diskar',' disks'); $d[]=$m['os'];
  return [true,implode(' · ',$d),null,$m]; }
 if(($c['source']==='docker')||($c['source']==='zabbix'&&strpos($k,'docker_containers')===0)) return dk_check_run($c,$h);
 if($c['source']==='zabbix'&&$k==='smart') return zbx_smart($h,10050);
 if($c['source']==='zabbix'&&in_array($k,['cpu','mem','disk'])){ $m=zbx_metric($h,10050,$k); if($m===null) return [false,lasterr()?:t('ingen respons','no response'),null,null];
  if($k==='disk') return [true,count($m[1]).t(' diskar',' disks'),null,$m[1]];
  return [true,($k==='cpu'?'CPU ':t('Minne ','Mem ')).$m[0].'%',$m[0],$m[1]]; }
 switch($c['source']){
 case 'pbs': return pbs_run($c,$h);
  case 'ha': return ha_check_run($c,$h);
 case 'zabbix':
  return zbx_run($c,$h);
 case 'tcp': if($k==='ping') return ping_host($h);
  $port=(int)($c['param']?:(tcp_defs()[$k][1]??0)); if(!$port) return [false,t('okänd tjänst','unknown service')];
  $tls=in_array($k,['smtps','https','pop3s','imaps','ldaps']); $t0=microtime(true);
  error_clear_last(); $f=@stream_socket_client(($tls?'ssl://':'tcp://')."$h:$port",$en,$es,3,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
  if(!$f){ $ip=gethostbyname($h); $why=$es?:(error_get_last()['message']??'?');
   $hint=in_array($en,[111,10061])?t('avvisas aktivt: tjänsten lyssnar bara lokalt (t.ex. 127.0.0.1) eller målets brandvägg avvisar monitor-IP','actively refused: the service only listens locally (e.g. 127.0.0.1) or the target firewall rejects the monitor IP'):(in_array($en,[110,10060])?t('timeout: troligen brandvägg som släpper paketen','timeout: probably a firewall dropping packets'):(in_array($en,[113,101,10051,10065])?t('ingen route till målet','no route to host'):''));
   return [false,"port $port @ $ip: $why (errno $en)".($hint?" – $hint":'')]; }
  $ms=round((microtime(true)-$t0)*1000); // svarstid = uppkoppling (inkl. TLS), inte väntan på bannern (t.ex. Postfix postscreen fördröjer hälsningen ~2 s)
  $b=''; if(in_array($k,['ssh','ftp','smtp','pop3','imap'])){ stream_set_timeout($f,2); $b=' – '.substr(trim((string)fgets($f,200)),0,50); } fclose($f);
  return [true,$ms.' ms'.$b];
 case 'url':
  if($k==='ssl'){ $u=parse_url($t); $hh=$u['host']??$t; $pp=$u['port']??443;
   $f=@stream_socket_client("ssl://$hh:$pp",$en,$es,5,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['capture_peer_cert'=>true,'verify_peer'=>false,'verify_peer_name'=>false]]));
   if(!$f) return [false,t('TLS-anslutning misslyckades: ','TLS connection failed: ').$es]; $x=openssl_x509_parse(stream_context_get_params($f)['options']['ssl']['peer_certificate']); $dd=(int)floor(($x['validTo_time_t']-time())/86400); return [$dd>=max(1,(int)setting('thr_cert_c',14)),t("giltigt $dd dagar till","valid for $dd more days")]; }
  $ch=curl_init($t); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>1,CURLOPT_SSL_VERIFYPEER=>0,CURLOPT_SSL_VERIFYHOST=>0]);
  $b=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); $ms=round(curl_getinfo($ch,CURLINFO_TOTAL_TIME)*1000); $er=curl_error($ch); curl_close($ch);
  $ok=match($k){'status200'=>$code===200,'contains'=>$code>=200&&$code<400&&stripos((string)$b,$c['param'])!==false,default=>$code>=200&&$code<400};
  return [$ok,"HTTP $code, $ms ms".($er?" – $er":'')]; }
 return [false,'?']; }
function exec_check($c){ $GLOBALS['force_lang']=setting('sys_lang','sv'); try{ return run_check($c); }catch(Throwable $e){ applog('error',$c['source']??'check','PHP: '.substr($e->getMessage(),0,200)); return [false,'PHP: '.substr($e->getMessage(),0,200)]; } finally{ unset($GLOBALS['force_lang']); } }
// Loggar statusbyten (nere/uppe) för alla checks – visas under Historik
function ms_of_detail($d){ return preg_match('/^(?:HTTP \d+, )?(\d+(?:\.\d+)?)\s*ms/',(string)$d,$m)?(float)$m[1]:null; }
function ms_class($v){ if($v===null) return 0; $n=(int)setting('thr_ms_n',200); $w=max((int)setting('thr_ms_w',500),$n+1); return $v>$w?2:($v>$n?1:0); }
function log_ms_change($c,$r){ if(empty($c['mon_ms'])||!$r[0]) return; $o=ms_class(ms_of_detail($c['last_detail']??'')); $v=ms_of_detail($r[1]); $n=ms_class($v); if($o===$n) return;
 $q=db()->prepare('SELECT name,is_services FROM servers WHERE id=?'); $q->execute([$c['server_id']]); $s=$q->fetch()?:['name'=>'?','is_services'=>0];
 applog($n===2?'error':($n===1?'warning':'info'),$c['source']??'check',($s['is_services']?'Services':$s['name']).' / '.$c['label'].': '.'response time '.round($v).' ms – '.($n===2?'critical':($n===1?'warning':'back to normal'))); }
// Sammanfattning per källa i loggen (max var 10:e minut) så att URL-, TCP-, Zabbix-, SNMP- och hostloggarna visar att övervakningen lever även när inget ändras
function log_summary(){ try{ log_summary_run(); }catch(Throwable $e){} }
function log_summary_run(){ if(time()-(int)setting('log_sum_ts',0)<600) return; set_setting('log_sum_ts',time()); setting('',null,true); $pdo=db(); $G=[];
 foreach($pdo->query("SELECT c.source,c.label,c.last_status,s.name,s.is_services FROM server_checks c JOIN servers s ON s.id=c.server_id WHERE c.enabled=1 AND s.enabled=1")->fetchAll() as $r){ $g=&$G[$r['source']]; $g['n']=($g['n']??0)+1; if($r['last_status']==='down'){ $g['d'][]=($r['is_services']?'':$r['name'].' / ').$r['label']; } elseif($r['last_status']==='up') $g['u']=($g['u']??0)+1; else $g['p']=($g['p']??0)+1; unset($g); }
 foreach($G as $src=>$g){ $d=$g['d']??[]; applog($d?'warning':'info',$src,'Summary: '.$g['n'].' '.'checks'.', '.($g['u']??0).' '.'up'.', '.count($d).' '.'down'.(($g['p']??0)?', '.$g['p'].' '.'pending':'').($d?' ('.implode('; ',array_slice($d,0,6)).(count($d)>6?'…':'').')':'')); }
 foreach($pdo->query("SELECT name,hv_type,hv_status,hv_error,nw_type,nw_status,nw_error FROM servers WHERE enabled=1 AND (hv_type IS NOT NULL OR nw_type IS NOT NULL)")->fetchAll() as $r){ $nw=!empty($r['nw_type']); $st=$nw?$r['nw_status']:$r['hv_status']; $src=$nw?'snmp':($r['hv_type']?:'host');
  applog($st==='down'?'warning':'info',$src,'Summary: '.$r['name'].' – '.($st==='up'?'up':($st==='down'?'not responding: '.($nw?$r['nw_error']:$r['hv_error']):'pending'))); } }
function log_check_event($c,$r){ log_ms_change($c,$r); $old=$c['last_status']??null; $new=$r[0]?'up':'down'; if($old===$new||($old===null&&$new==='up')) return; $pdo=db();
 $s=$pdo->prepare('SELECT name,is_services FROM servers WHERE id=?'); $s->execute([$c['server_id']]); $s=$s->fetch()?:['name'=>'?','is_services'=>0];
 $dur=null; if($new==='up'){ $q=$pdo->prepare('SELECT TIMESTAMPDIFF(SECOND,ts,NOW()) FROM check_events WHERE check_id=? ORDER BY id DESC LIMIT 1'); $q->execute([$c['id']]); $v=$q->fetchColumn(); $dur=$v===false||$v===null?null:(int)$v; }
 $pdo->prepare('INSERT INTO check_events(check_id,server_id,server_name,svc,label,target,status,detail,dur) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$c['id'],$s['id'],$s['is_services']?t('Tjänster','Services'):$s['name'],(int)$s['is_services'],$c['label'],$s['is_services']?substr((string)$c['target'],0,250):'',$new,substr((string)$r[1],0,250),$dur]);
 applog($new==='down'?'warning':'info',$c['source']??'check',($s['is_services']?'Services':$s['name']).' / '.$c['label'].': '.($new==='down'?'down':'back up').($r[1]?' – '.substr((string)$r[1],0,200):'')); }
// Håller reda på när inloggade användare senast var aktiva (max 1 uppdatering/min per session)
// ---- Teman: alla *.css i themes/ och themes/custom/ indexeras (standardtema = sidans ursprungliga färger, ingen fil)
function themes_list(){ static $L=null; if($L!==null) return $L; $L=[]; $ord=['light','monokai','monokai-dark','monokai-dimmed','dark','dark-modern','nord'];
 foreach([[__DIR__.'/themes',1],[__DIR__.'/themes/custom',0]] as [$dir,$bi]){ foreach(glob($dir.'/*.css')?:[] as $f){ $id=strtolower(basename($f,'.css')); if(!preg_match('/^[a-z0-9_-]{1,40}$/',$id)||$id==='default'||isset($L[$id])) continue;
   $hd=(string)@file_get_contents($f,false,null,0,600); $nm=preg_match('/name\s*:\s*([^\r\n*]+)/i',$hd,$m)?trim($m[1]):$id; $L[$id]=['name'=>$nm,'file'=>'themes/'.($bi?'':'custom/').basename($f),'builtin'=>$bi,'v'=>(int)@filemtime($f)]; } }
 uksort($L,function($a,$b) use($L,$ord){ $ia=array_search($a,$ord,true); $ib=array_search($b,$ord,true); if($ia!==false&&$ib!==false) return $ia<=>$ib; if($ia!==false) return -1; if($ib!==false) return 1; return strcasecmp($L[$a]['name'],$L[$b]['name']); });
 return $L; }
function theme_href(){ $id=(string)($_SESSION['u']['theme']??''); $l=themes_list(); if($id===''||!isset($l[$id])) return ''; return $l[$id]['file'].'?v='.$l[$id]['v']; }
// Länk till användarens tema + liten kontroll var 15:e sekund så att öppna sidor byter tema när det ändras på en annan dator.
function theme_link(){ $hr=theme_href(); $o=$hr!==''?'<link id="vmtheme" rel="stylesheet" href="'.h($hr).'">':''; if(empty($_SESSION['u'])) return $o;
 return $o.'<script>(function(){var cur='.json_encode($hr).';setInterval(function(){if(document.hidden||!window.fetch)return;fetch("theme.php",{credentials:"same-origin",cache:"no-store"}).then(function(r){return r.ok?r.json():null}).then(function(j){if(!j||typeof j.href!=="string"||j.href===cur)return;cur=j.href;var e=document.getElementById("vmtheme");if(!cur){if(e)e.remove();return}if(!e){e=document.createElement("link");e.id="vmtheme";e.rel="stylesheet";document.head.appendChild(e)}e.href=cur}).catch(function(){})},15000)})();</script>'; }
// ---- Larmljud: globalt val (setting sound_type) och valfri överstyrning per användare (users.sound: tomt = följ globalt, 'off' = av, annars ljud-id)
function snd_types(){ return ['classic'=>t('Klassisk (tre pip)','Classic (three beeps)'),'chime'=>t('Klockspel','Chime'),'bell'=>t('Klocka','Bell'),'doorbell'=>t('Dörrklocka','Doorbell'),'soft'=>t('Mjuk','Soft'),'pulse'=>t('Puls','Pulse'),'digital'=>t('Digital','Digital'),'alarm'=>t('Larm','Alarm'),'siren'=>t('Siren','Siren'),'sonar'=>t('Sonar','Sonar')]; }
function snd_effective(){ $ty=snd_types(); $g=setting('sound_type','classic'); if(!isset($ty[$g])) $g='classic'; $u=(string)($_SESSION['u']['sound']??'');
 if($u==='off') return [false,$g]; if(isset($ty[$u])) return [true,$u]; return [setting('sound_alert','1')!=='0',$g]; }
function track_user(){ if(empty($_SESSION['u'])) return; $id=(int)$_SESSION['u']['id']; $u=null;
 try{ $q=db()->prepare('SELECT role,disabled,theme,sound FROM users WHERE id=?'); $q->execute([$id]); $u=$q->fetch(); }catch(Throwable $e){ try{ $q=db()->prepare('SELECT role,disabled FROM users WHERE id=?'); $q->execute([$id]); $u=$q->fetch(); }catch(Throwable $e2){ $u=null; } }
 if($u===false||($u&&!empty($u['disabled']))){ $_SESSION=[]; session_destroy(); if(strpos($_SERVER['SCRIPT_NAME'],'/api/')!==false){ http_response_code(401); exit('{}'); } header('Location: login.php'); exit; }
 if($u){ $_SESSION['u']['role']=$u['role']; if(array_key_exists('theme',$u)){ $_SESSION['u']['theme']=$u['theme']; $_SESSION['u']['sound']=$u['sound']; } }
 if(time()-($_SESSION['seen']??0)<60) return; $_SESSION['seen']=time(); try{ db()->prepare('UPDATE users SET last_seen=NOW(),logged_in=1 WHERE id=?')->execute([$id]); }catch(Throwable $e){} }
function ping_fail_n(){ return min(60,max(1,(int)setting('ping_fail_n',3))); }
// Ping: en enhet blir röd först efter X missade pingar i rad (inställning). Tills dess ligger föregående status kvar och pingen upprepas varje sekund (ping_retry_pass).
function ping_hold($c,&$r){ if(($c['source']??'')!=='tcp'||($c['kind']??'')!=='ping') return false; $pdo=db(); $q=$pdo->prepare('SELECT fail_n,last_status FROM server_checks WHERE id=?'); $q->execute([$c['id']]); $o=$q->fetch(); if(!$o) return false;
 if($r[0]){ if((int)$o['fail_n']>0) $pdo->prepare('UPDATE server_checks SET fail_n=0 WHERE id=?')->execute([$c['id']]); return false; }
 if($o['last_status']==='down') return false; $n=(int)$o['fail_n']+1; $X=ping_fail_n();
 if($n<$X){ $pdo->prepare('UPDATE server_checks SET fail_n=?,last_check=NOW() WHERE id=?')->execute([$n,$c['id']]); return true; }
 $pdo->prepare('UPDATE server_checks SET fail_n=? WHERE id=?')->execute([$X,$c['id']]); $r[1]=t('inget svar på ','no reply to ').$X.t(' pingar i rad',' pings in a row').' – '.$r[1]; return false; }
// Körs av workern varje sekund: upprepar ping för enheter som just missat en ping. Returnerar antal pingar som kördes.
function ping_retry_pass(){ $pdo=db(); $X=ping_fail_n(); $dn=false;
 $rows=$pdo->query("SELECT c.*,s.host AS host FROM server_checks c JOIN servers s ON s.id=c.server_id WHERE c.enabled=1 AND s.enabled=1 AND c.source='tcp' AND c.kind='ping' AND c.fail_n>0 AND c.fail_n<$X AND (c.last_status IS NULL OR c.last_status<>'down') AND TIMESTAMPDIFF(SECOND,c.last_check,NOW())>=1")->fetchAll(); if(!$rows) return 0;
 $GLOBALS['ping_fast']=1; try{ wbeat(); $rs=ping_many($rows,true); foreach($rows as $ck){ store_result($ck,$rs[$ck['id']]); $st=$pdo->prepare('SELECT last_status FROM server_checks WHERE id=?'); $st->execute([$ck['id']]); if($st->fetchColumn()==='down') $dn=true; } } finally{ unset($GLOBALS['ping_fast']); }
 if($dn){ evaluate_rules(); try{ track_events(); }catch(Throwable $e){ applog('error','events',$e->getMessage()); } }
 return count($rows); }
function store_result($c,$r){ $pdo=db(); if(ping_hold($c,$r)) return; applog('debug',$c['source']??'check',($c['label']??'').' @ '.($c['host']??'').': '.($r[0]?'up':'down').' – '.substr((string)($r[1]??''),0,200)); try{ log_check_event($c,$r); }catch(Throwable $e){} $v=$r[2]??null; $d=$r[3]??null; $inf=null;
 $ifs=null; if(is_array($d)&&isset($d['ifs'])){ $ifs=$d['ifs']; unset($d['ifs']); }
 if(is_array($d)&&isset($d['info'])){ $inf=$d['info']; unset($d['info']); }
 $pdo->prepare('UPDATE server_checks SET last_status=?,last_detail=?,last_value=?,last_data=?,last_check=NOW() WHERE id=?')->execute([$r[0]?'up':'down',substr((string)$r[1],0,250),$v,$d===null?null:json_encode($d,JSON_INVALID_UTF8_SUBSTITUTE|JSON_PARTIAL_OUTPUT_ON_ERROR),$c['id']]);
 if($inf!==null) $pdo->prepare('UPDATE servers SET os_info=?,os_full=?,info=? WHERE id=?')->execute([$inf['os']??null,$inf['descr']??null,json_encode($inf),$c['server_id']]);
 if($ifs!==null&&$r[0]) record_net($c['server_id'],$ifs);
 $cp=null;$mp=null; if(in_array($c['source'],['snmp','pbs'],true)&&is_array($d)){ $cp=$d['cpu']??null; $mp=$d['mem']??null; } elseif($c['source']==='zabbix'&&$v!==null){ if($c['kind']==='cpu')$cp=$v; if($c['kind']==='mem')$mp=$v; }
 if($r[0]&&($cp!==null||$mp!==null)) record_load($c['server_id'],$cp,$mp);
 if($r[0]){ $dd=null; if(in_array($c['source'],['snmp','pbs'],true)&&is_array($d)) $dd=$d['disks']??null; elseif($c['source']==='zabbix'&&($c['kind']??'')==='disk'&&is_array($d)) $dd=$d; if(is_array($dd)) record_disks($c['server_id'],filter_disks($dd,$c['target']??'')); }
 $pdo->prepare("INSERT INTO check_hist(check_id,hr,up,total) VALUES(?,DATE_FORMAT(NOW(),'%Y-%m-%d %H:00:00'),?,1) ON DUPLICATE KEY UPDATE up=up+VALUES(up),total=total+1")->execute([$c['id'],$r[0]?1:0]);
 $ms=$r[0]?ms_of_detail($r[1]):null; if($ms!==null){ try{ $pdo->prepare('INSERT INTO check_ms_hist(check_id,bk,n,ms_sum,ms_max) VALUES(?,FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP()/300)*300),1,?,?) ON DUPLICATE KEY UPDATE n=n+1,ms_sum=ms_sum+VALUES(ms_sum),ms_max=GREATEST(ms_max,VALUES(ms_max))')->execute([$c['id'],$ms,$ms]); }catch(Throwable $e){} } }
// Nätverksräknare -> hastigheter (bit/s, fel och tappade per minut) + historik i 5-minutersfack
function record_net($sid,$ifs){ $pdo=db(); $now=time(); $seen=[];
 try{ $rq=$pdo->prepare('SELECT nw_role,nw_data FROM servers WHERE id=?'); $rq->execute([$sid]); if($rw=$rq->fetch()){ if($rw['nw_role']) $ifs=nw_if_filter($ifs,strpos((string)$rw['nw_data'],'"wifi"')!==false?'ap':$rw['nw_role']); } }catch(Throwable $e){}
 $sel=$pdo->prepare('SELECT * FROM net_if WHERE server_id=? AND if_index=?');
 static $hip=null,$hcr=null; if($hip===null){ try{ $hip=(bool)$pdo->query("SHOW COLUMNS FROM net_if LIKE 'ips'")->fetch(); }catch(Throwable $e){ $hip=false; } } if($hcr===null){ try{ $hcr=(bool)$pdo->query("SHOW COLUMNS FROM net_if LIKE 'crc_pm'")->fetch(); }catch(Throwable $e){ $hcr=false; } }
 $cols='server_id,if_index,name,descr,alias,type,speed_mbps,mac,admin,oper,in_oct,out_oct,in_err,out_err,in_disc,out_disc,last_ts,rate_in,rate_out,err_pm,disc_pm'; $upd='name=VALUES(name),descr=VALUES(descr),alias=VALUES(alias),type=VALUES(type),speed_mbps=VALUES(speed_mbps),mac=VALUES(mac),admin=VALUES(admin),oper=VALUES(oper),in_oct=VALUES(in_oct),out_oct=VALUES(out_oct),in_err=VALUES(in_err),out_err=VALUES(out_err),in_disc=VALUES(in_disc),out_disc=VALUES(out_disc),last_ts=VALUES(last_ts),rate_in=VALUES(rate_in),rate_out=VALUES(rate_out),err_pm=VALUES(err_pm),disc_pm=VALUES(disc_pm)';
 $nx=($hip?1:0)+($hcr?2:0); $up=$pdo->prepare('INSERT INTO net_if('.$cols.($hip?',ips':'').($hcr?',in_crc,crc_pm':'').') VALUES('.implode(',',array_fill(0,21+$nx,'?')).') ON DUPLICATE KEY UPDATE '.$upd.($hip?',ips=VALUES(ips)':'').($hcr?',in_crc=VALUES(in_crc),crc_pm=VALUES(crc_pm)':''));
 $hs=$pdo->prepare('INSERT INTO net_hist(server_id,if_index,bk,n,in_sum,in_max,out_sum,out_max,err_sum,err_max,disc_sum,disc_max) VALUES(?,?,FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP()/300)*300),1,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE n=n+1,in_sum=in_sum+VALUES(in_sum),in_max=GREATEST(in_max,VALUES(in_max)),out_sum=out_sum+VALUES(out_sum),out_max=GREATEST(out_max,VALUES(out_max)),err_sum=err_sum+VALUES(err_sum),err_max=GREATEST(err_max,VALUES(err_max)),disc_sum=disc_sum+VALUES(disc_sum),disc_max=GREATEST(disc_max,VALUES(disc_max))');
 foreach($ifs as $i){ $seen[]=(int)$i['idx']; $sel->execute([$sid,$i['idx']]); $o=$sel->fetch(); $ri=$ro=$er=$dp=$cr=null;
  if($o&&$o['last_ts']){ $dt=$now-(int)$o['last_ts']; if($dt<5) continue;
   if($dt<=7200){ $dl=function($n,$p) use($i){ $x=(float)$n-(float)$p; if($x<0){ if($i['hc']) return null; $x+=4294967296; } return $x; };
    $a=$dl($i['in'],$o['in_oct']); $b=$dl($i['out'],$o['out_oct']); $c1=$dl($i['ierr'],$o['in_err']); $c2=$dl($i['oerr'],$o['out_err']); $e1=$dl($i['idisc'],$o['in_disc']); $e2=$dl($i['odisc'],$o['out_disc']);
    if($a!==null) $ri=$a*8/$dt; if($b!==null) $ro=$b*8/$dt; if($c1!==null&&$c2!==null) $er=($c1+$c2)*60/$dt; if($e1!==null&&$e2!==null) $dp=($e1+$e2)*60/$dt; if($hcr&&isset($i['crc'])&&$i['crc']!==null&&$o['in_crc']!==null){ $c3=$dl($i['crc'],$o['in_crc']); if($c3!==null) $cr=$c3*60/$dt; }
    $cap=(int)$i['speed']>0?(float)$i['speed']*1e6*1.25:1e12; if($ri!==null&&$ri>$cap) $ri=null; if($ro!==null&&$ro>$cap) $ro=null; } }
  if(!empty($i['nostat'])) $ri=$ro=$er=$dp=$cr=null;
  $up->execute(array_merge([$sid,$i['idx'],substr($i['name'],0,100),substr($i['descr'],0,255),substr($i['alias'],0,255),$i['type'],$i['speed'],substr($i['mac'],0,32),$i['admin'],$i['oper'],$i['in'],$i['out'],$i['ierr'],$i['oerr'],$i['idisc'],$i['odisc'],$now,$ri,$ro,$er,$dp],$hip?[substr((string)($i['ips']??''),0,255)]:[],$hcr?[$i['crc']??null,$cr]:[]));
  if(empty($i['nostat'])&&($ri!==null||$ro!==null||$er!==null||$dp!==null)) $hs->execute([$sid,$i['idx'],$ri??0,$ri??0,$ro??0,$ro??0,$er??0,$er??0,$dp??0,$dp??0]); }
 if($seen) $pdo->exec('DELETE FROM net_if WHERE server_id='.(int)$sid.' AND if_index NOT IN ('.implode(',',$seen).')'); }
// Diskanvändning i 5-minutersfack (snitt + max, procent använt)
function record_disks($sid,$disks){ try{ $st=db()->prepare('INSERT INTO disk_hist(server_id,dk,bk,n,used_sum,used_max) VALUES(?,?,FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP()/300)*300),1,?,?) ON DUPLICATE KEY UPDATE n=n+1,used_sum=used_sum+VALUES(used_sum),used_max=GREATEST(used_max,VALUES(used_max))'); foreach($disks as $k){ if(empty($k['t'])||$k['t']<=0||!isset($k['f'])) continue; $p=max(0,min(100,100-$k['f']/$k['t']*100)); $st->execute([$sid,mb_substr((string)$k['n'],0,190),$p,$p]); } }catch(Throwable $e){} }
// Belastningshistorik i 5-minutersfack (snitt + max)
function record_load($sid,$cpu,$mem){ db()->prepare("INSERT INTO load_hist(server_id,bk,cpu_sum,cpu_n,cpu_max,mem_sum,mem_n,mem_max) VALUES(?,FROM_UNIXTIME(FLOOR(UNIX_TIMESTAMP()/300)*300),?,?,?,?,?,?) ON DUPLICATE KEY UPDATE cpu_sum=cpu_sum+VALUES(cpu_sum),cpu_n=cpu_n+VALUES(cpu_n),cpu_max=GREATEST(IFNULL(cpu_max,0),IFNULL(VALUES(cpu_max),0)),mem_sum=mem_sum+VALUES(mem_sum),mem_n=mem_n+VALUES(mem_n),mem_max=GREATEST(IFNULL(mem_max,0),IFNULL(VALUES(mem_max),0))")->execute([$sid,$cpu??0,$cpu===null?0:1,$cpu,$mem??0,$mem===null?0:1,$mem]); }
// Dolda diskar (checkens target) filtreras bort här – gäller dashboard och larm
// Fullaste disk som stapel: $disks = [['n'=>namn,'t'=>total,'f'=>ledigt],…]
function disk_bar_cell($disks){ $bp=null; $bn=''; foreach($disks as $x){ if(($x['t']??0)>0){ $pp=($x['t']-$x['f'])/$x['t']*100; if($bp===null||$pp>$bp){ $bp=$pp; $bn=(string)($x['full']??'')!==''?$x['full']:$x['n']; } } } return bar_cell($bp,'disk',$bn); }
function bar_cell($p,$k,$ti=''){ if($p===null||$p==='') return '<td class="bc mut">–</td>'; $p=max(0,min(100,(float)$p)); $n=(int)setting("thr_{$k}_n",75); $w=max((int)setting("thr_{$k}_w",85),$n+1); $r=(int)round($p); $c=$r>$w?'bad':($r>$n?'warn':'ok');
 return '<td class="bc"'.($ti!==''?' title="'.h($ti).'"':'').'><div class="brow"><div class="bar"><i class="'.$c.'" style="width:'.round($p,1).'%"></i></div><b>'.round($p).'%</b></div>'.($ti!==''?'<small class="mut dn">'.h($ti).'</small>':'').'</td>'; }
function use_cls($p,$hidden=false){ if($hidden) return 'dim'; $n=(int)setting('thr_disk_n',75); $w=max((int)setting('thr_disk_w',85),$n+1); $r=(int)round($p); return $r>$w?'rs-bad':($r>$n?'rs-warn':'rs-ok'); }
function chk_row_cls($c){ if(($c['enabled']??1)==0||!empty($c['hidden'])) return 'dim'; $s=$c['last_status']??null; if(!$s) return ''; if($s==='down') return (array_key_exists('mon_up',$c)&&(int)$c['mon_up']===0)?'':'rs-bad';
 if(!empty($c['mon_ms'])){ $m=ms_class(ms_of_detail($c['last_detail']??'')); if($m===2) return 'rs-bad'; if($m===1) return 'rs-warn'; } return 'rs-ok'; }
function srv_metrics($cs){ $r=['cpu'=>null,'cores'=>null,'mem'=>null,'mem_t'=>null,'mem_u'=>null,'disks'=>[]];
 foreach($cs as $c){ $d=$c['last_data']?json_decode($c['last_data'],true):null; if(!is_array($d)) $d=[];
  if(in_array($c['source'],['snmp','pbs'],true)){ $r['cpu']??=$d['cpu']??null; $r['cores']??=$d['cores']??null; $r['mem']??=$d['mem']??null; $r['mem_t']??=$d['mem_t']??null; $r['mem_u']??=$d['mem_u']??null; $r['disks']=array_merge($r['disks'],filter_disks($d['disks']??[],$c['target'])); }
  elseif($c['source']==='zabbix'){ $k=$c['kind'];
   if($k==='cpu'&&$c['last_value']!==null){ $r['cpu']??=(float)$c['last_value']; $r['cores']??=$d['cores']??null; }
   if($k==='mem'&&$c['last_value']!==null){ $r['mem']??=(float)$c['last_value']; $r['mem_t']??=$d['t']??null; $r['mem_u']??=$d['u']??null; }
   if($k==='disk') $r['disks']=array_merge($r['disks'],filter_disks($d,$c['target'])); } }
 return $r; }
function smtp_send($to,$subject,$body){ $host=setting('smtp_host'); $port=(int)setting('smtp_port',25); $sec=setting('smtp_sec','none'); $user=setting('smtp_user'); $pass=secret('smtp_pass'); $from=setting('smtp_from');
 if(!$host||!$from) return t('SMTP är inte konfigurerat (Inställningar)','SMTP is not configured (Settings)');
 $f=@stream_socket_client(($sec==='ssl'?'ssl://':'tcp://')."$host:$port",$en,$es,10,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]));
 if(!$f) return "SMTP $host:$port: $es ($en)"; stream_set_timeout($f,10);
 $rd=function() use($f){ $r=''; while(($l=fgets($f,515))!==false){ $r.=$l; if(strlen($l)<4||$l[3]===' ') break; } return $r; };
 $cmd=function($c,$ok) use($f,$rd){ if($c!==null) fwrite($f,$c."\r\n"); $r=$rd(); return preg_match('/^('.$ok.')/',$r)?null:('SMTP: '.trim($r)); };
 if($e=$cmd(null,'220')) return $e; if($e=$cmd('EHLO monitor','250')) return $e;
 if($sec==='tls'){ if($e=$cmd('STARTTLS','220')) return $e; if(!stream_socket_enable_crypto($f,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) return 'SMTP: STARTTLS failed'; if($e=$cmd('EHLO monitor','250')) return $e; }
 if($user!==''){ if($e=$cmd('AUTH LOGIN','334')) return $e; if($e=$cmd(base64_encode($user),'334')) return $e; if($e=$cmd(base64_encode($pass),'235')) return $e; }
 if($e=$cmd("MAIL FROM:<$from>",'250')) return $e;
 foreach($to as $a) if($e=$cmd("RCPT TO:<$a>",'25[01]')) return $e;
 if($e=$cmd('DATA','354')) return $e;
 $hdr="From: ".setting('smtp_from_name','VM Monitor')." <$from>\r\nTo: ".implode(', ',$to)."\r\nSubject: =?UTF-8?B?".base64_encode($subject)."?=\r\nDate: ".date('r')."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body));
 fwrite($f,$hdr."\r\n.\r\n"); if($e=$cmd(null,'250')) return $e; $cmd('QUIT','221'); fclose($f); return null; }
function send_mail($emails,$subject,$body){ $to=array_values(array_filter(preg_split('/[\s,;]+/',(string)$emails,-1,PREG_SPLIT_NO_EMPTY),fn($x)=>filter_var($x,FILTER_VALIDATE_EMAIL)));
 return $to?smtp_send($to,$subject,$body):t('Inga giltiga mottagare','No valid recipients'); }
// ---- Larm ----
// Mål för en regel: NULL = allt. targets = [{"s":serverId},{"c":checkId},{"i":"serverId:ifIndex"}]
// Automatisk kolumnhantering (visa/dölj, bredd, ordning) för alla tabeller på detaljsidor: lägger data-tc på varje jämn tabell i den buffrade sidan
function tc_auto_start($page){ $GLOBALS['tc_auto_page']=$page; ob_start('tc_auto_cb'); }
function tc_auto_cb($html){ $pg=$GLOBALS['tc_auto_page']??'x'; $used=[];
 return preg_replace_callback('~<table class="card([^"]*)"([^>]*)>(.*?)</table>~s',function($m) use($pg,&$used){
  if(strpos($m[2],'data-tc')!==false) return $m[0];
  if(!preg_match_all('~<tr[^>]*>(.*?)</tr>~s',$m[3],$tr)||count($tr[1])<2) return $m[0]; $n=null;
  foreach($tr[1] as $r){ if(stripos($r,'colspan')!==false) return $m[0]; $c=preg_match_all('~<t[dh][\s>]~',$r); if($n===null) $n=$c; elseif($n!==$c) return $m[0]; }
  if($n<2) return $m[0]; preg_match('~<th[^>]*>(.*?)</th>~s',$tr[1][0],$f); $slug=strtolower(preg_replace('~[^a-z0-9]+~i','_',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',trim(strip_tags($f[1]??'')))?:'')); $slug=trim($slug,'_')?:'t'; $key='dt_'.$pg.'_'.substr($slug,0,20).'_'.$n; $b=$key; $i=1; while(isset($used[$key])) $key=$b.'_'.(++$i); $used[$key]=1;
  return '<table class="card'.$m[1].'"'.tc_attr($key).$m[2].'>'.$m[3].'</table>'; },$html); }
// Inloggning: lösenord av/på, passkey-krav och OAuth/OpenID Connect (SSO)
function pw_login_off(){ return setting('pw_login_off','0')==='1'&&!(defined('VMMON_FORCE_PASSWORD_LOGIN')&&VMMON_FORCE_PASSWORD_LOGIN); }
function oidc_on(){ return setting('oidc_on','0')==='1'&&trim(setting('oidc_issuer',''))!==''&&trim(setting('oidc_client_id',''))!==''&&secret('oidc_secret')!==''; }
function pw_off_check($actor){ $pdo=db(); try{ $q=$pdo->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id=?'); $q->execute([(int)$actor]); if(!(int)$q->fetchColumn()) return t('Du måste själv ha en passkey för att kunna stänga av lösenordsinloggningen (lägg till en under Min profil).','You must have a passkey yourself to disable password login (add one under My profile).');
  $n=(int)$pdo->query("SELECT COUNT(*) FROM users u WHERE u.role='admin' AND IFNULL(u.disabled,0)=0 AND u.last_seen>=NOW()-INTERVAL 1 DAY AND EXISTS(SELECT 1 FROM user_passkeys p WHERE p.user_id=u.id)")->fetchColumn();
  if(!$n) return t('Minst en administratör med passkey måste ha varit inloggad/aktiv under det senaste dygnet.','At least one administrator with a passkey must have been signed in/active during the last 24 hours.'); }catch(Throwable $e){ return t('Kunde inte kontrollera passkeys (har sql/3.09.sql körts?).','Could not check passkeys (has sql/3.09.sql been run?).'); } return ''; }
function oidc_http($url,$post=null,$hdr=[]){ $c=curl_init($url); $o=[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>10,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json'],$hdr)]; if($post!==null){ $o[CURLOPT_POST]=1; $o[CURLOPT_POSTFIELDS]=http_build_query($post); } curl_setopt_array($c,$o); $r=curl_exec($c); $code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); $e=curl_error($c); curl_close($c); return [$code,(string)$r,$e]; }
function oidc_url_ok($u){ $p=parse_url($u); if(!$p||empty($p['host'])||!in_array($p['scheme']??'',['https','http'],true)) return false; if($p['scheme']==='https') return true; $h=$p['host']; return $h==='localhost'||strpos($h,'.')===false||substr($h,-6)==='.local'||(filter_var($h,FILTER_VALIDATE_IP)&&!filter_var($h,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)); }
// Hämtar (och cachar i 1 h) providerns discovery-dokument. Returnerar [array|null, felmeddelande]
function oidc_disc($force=false){ $iss=rtrim(trim(setting('oidc_issuer','')),'/'); if($iss===''||!oidc_url_ok($iss)) return [null,t('Ogiltig Issuer-URL (https krävs, http tillåts bara för lokala adresser).','Invalid issuer URL (https required, http is only allowed for local addresses).')];
 $c=json_decode((string)setting('oidc_disc',''),true); if(!$force&&is_array($c)&&($c['iss']??'')===$iss&&time()-(int)($c['t']??0)<3600) return [$c['d'],''];
 [$code,$b,$e]=oidc_http($iss.'/.well-known/openid-configuration'); $d=json_decode($b,true); if($code!==200||!is_array($d)) return [null,t('Kunde inte hämta discovery-dokumentet från ','Could not fetch the discovery document from ').$iss.'/.well-known/openid-configuration ('.($e?:'HTTP '.$code).')'];
 foreach(['issuer','authorization_endpoint','token_endpoint'] as $k) if(empty($d[$k])) return [null,t('Discovery-dokumentet saknar ','The discovery document is missing ').$k];
 if(rtrim($d['issuer'],'/')!==$iss) return [null,t('Issuer i discovery-dokumentet ('.$d['issuer'].') stämmer inte med den inskrivna URL:en.','The issuer in the discovery document ('.$d['issuer'].') does not match the entered URL.')];
 set_setting('oidc_disc',json_encode(['iss'=>$iss,'t'=>time(),'d'=>$d])); return [$d,'']; }
function oidc_check(){ if(trim(setting('oidc_client_id',''))===''||secret('oidc_secret')==='') return t('Client ID och Client secret krävs.','Client ID and client secret are required.'); [$d,$e]=oidc_disc(true); return $d?'':$e; }
// Enhetsgrupper (device_groups / device_group_members)
function dg_all(){ static $c=null; if($c!==null) return $c; try{ return $c=db()->query('SELECT * FROM device_groups ORDER BY sort,name')->fetchAll(); }catch(Throwable $e){ return []; } }
function dg_map($reset=false){ static $m=null; if($reset) $m=null; if($m!==null) return $m; $m=[]; try{ foreach(db()->query('SELECT m.server_id,g.name FROM device_group_members m JOIN device_groups g ON g.id=m.group_id ORDER BY g.sort,g.name') as $r) $m[(int)$r['server_id']][]=$r['name']; }catch(Throwable $e){} return $m; }
function dg_members($gids){ $gids=array_values(array_filter(array_map('intval',(array)$gids))); if(!$gids) return []; try{ return array_map('intval',db()->query('SELECT DISTINCT server_id FROM device_group_members WHERE group_id IN ('.implode(',',$gids).')')->fetchAll(PDO::FETCH_COLUMN)); }catch(Throwable $e){ return []; } }
function dg_ths(){ $o=''; $i=0; foreach(dg_all() as $g){ $i++; $o.='<th data-k="g'.(int)$g['id'].'" class="tx" data-l="'.h(t('Grupp: ','Group: ').$g['name']).'" title="'.h($g['name']).'">'.h($g['name']).'</th>'; } return $o; }
function dg_tds($sid){ $o=''; $in=array_flip(dg_ids_for((int)$sid)); foreach(dg_all() as $g){ $m=isset($in[(int)$g['id']]); $o.='<td data-sort="'.h($m?$g['name']:'').'">'.($m?h($g['name']):'–').'</td>'; } return $o; }
function dg_ids_for($sid){ static $m=null; if($m===null){ $m=[]; try{ foreach(db()->query('SELECT server_id,group_id FROM device_group_members') as $r) $m[(int)$r['server_id']][]=(int)$r['group_id']; }catch(Throwable $e){} } return $m[$sid]??[]; }
function dg_td($sid){ $n=dg_map()[(int)$sid]??[]; return tc_td($n?implode(', ',$n):null,implode(', ',$n)); }
function rule_targets($r,$expand=true){ $j=json_decode((string)($r['targets']??''),true); $o=['all'=>false,'s'=>[],'c'=>[],'i'=>[],'v'=>[],'g'=>[]];
 if(is_array($j)) foreach($j as $x){ if(isset($x['s'])) $o['s'][]=(int)$x['s']; if(isset($x['c'])) $o['c'][]=(int)$x['c']; if(isset($x['i'])) $o['i'][]=(string)$x['i']; if(isset($x['v'])) $o['v'][]=(string)$x['v']; if(isset($x['g'])) $o['g'][]=(int)$x['g']; }
 if(!$o['s']&&!$o['c']&&!$o['i']&&!$o['v']&&!$o['g']){ if(!empty($r['server_id'])) $o['s'][]=(int)$r['server_id']; else $o['all']=true; }
 if($expand&&$o['g']){ $o['s']=array_values(array_unique(array_merge($o['s'],dg_members($o['g'])))); } return $o; }
// Larmschema: 24/7 eller valda dagar (ISO 1=mån … 7=sön) och tidsintervall (kan gå över midnatt; dag = den dag intervallet startade)
function rule_in_schedule($r,$now=null){ if(!array_key_exists('s_247',$r)||!empty($r['s_247'])) return true; $now=$now??time(); $days=(string)($r['s_days']??'1234567'); if($days==='') return false;
 $f=substr((string)($r['s_from']??'00:00:00'),0,5); $to=substr((string)($r['s_to']??'23:59:00'),0,5); $t=date('H:i',$now); $d=(int)date('N',$now);
 if($f===$to) return strpos($days,(string)$d)!==false;
 if($f<$to) return $t>=$f&&$t<$to&&strpos($days,(string)$d)!==false;
 if($t>=$f) return strpos($days,(string)$d)!==false; if($t<$to){ $p=$d===1?7:$d-1; return strpos($days,(string)$p)!==false; } return false; }
function rule_applies($tg,$r,$s,$cs){ if($tg['all']) return true; $sid=(int)$s['id']; if(in_array($sid,$tg['s'],true)) return true;
 if(in_array($r['metric'],['check','check_ms'],true)){ foreach($cs as $c) if(in_array((int)$c['id'],$tg['c'],true)) return true; return false; }
 if(strpos($r['metric'],'net_')===0){ foreach($tg['i'] as $x) if(strpos($x,$sid.':')===0) return true; }
 if(strpos($r['metric'],'hv_vm_')===0){ foreach($tg['v'] as $x) if(strpos($x,$sid.':')===0) return true; } return false; }
// Minnesanvändning "inifrån" gästen (SNMP/Zabbix) för servrar vars namn eller hostname matchar en VM – Proxmox räknar cache som använt minne
function guest_mem_map($reset=false){ static $m=null; if($reset) $m=null; if($m!==null) return $m; $m=[]; $pdo=db(); $cs=$pdo->prepare('SELECT * FROM server_checks WHERE server_id=?');
 foreach($pdo->query('SELECT * FROM servers WHERE is_services=0 AND hv_type IS NULL AND enabled=1')->fetchAll() as $g){ $cs->execute([$g['id']]); $mt=srv_metrics($cs->fetchAll()); if($mt['mem']===null) continue;
  $inf=json_decode((string)$g['info'],true)?:[]; $hn=strtolower(explode('.',trim((string)($inf['hostname']??'')))[0]); foreach(array_filter([strtolower(trim($g['name'])),$hn]) as $k) $m[$k]=$mt['mem']; } return $m; }
function vm_in($tg,$sid,$k){ if(!$tg||$tg['all']||in_array((int)$sid,$tg['s'],true)) return true; return in_array($sid.':'.$k,$tg['v'],true); }
function chk_in($tg,$sid,$c){ if(!$tg||$tg['all']) return true; return in_array((int)$sid,$tg['s'],true)||in_array((int)$c['id'],$tg['c'],true); }
function iface_ok($r,$tg,$sid,$i){ if($tg&&!$tg['all']&&!in_array((int)$sid,$tg['s'],true)) return in_array($sid.':'.$i['if_index'],$tg['i'],true); return iface_match($r,$i); }
function iface_match($r,$i){ $l=array_filter(array_map('strtolower',array_map('trim',explode(',',(string)$r['iface'])))); if(!$l) return true; foreach([$i['name'],$i['descr'],$i['alias']] as $x) if(in_array(strtolower((string)$x),$l,true)) return true; return false; }
// Klartext för varje regeltyp (används i larmregler, historik och notiser)
function metric_labels(){ static $m=null; if($m===null) $m=['cpu'=>t('CPU-användning över','CPU usage above'),'mem'=>t('Minnesanvändning över','Memory usage above'),'disk'=>t('Diskanvändning (någon disk) över','Disk usage (any disk) above'),'check'=>t('Tjänst nere','Service down'),'check_ms'=>t('Tjänst/TCP-svarstid över (ms)','Service/TCP response time above (ms)'),'offline'=>t('Server svarar inte (ping)','Server not responding (ping)'),'smart'=>t('Diskhälsa (SMART) varnar eller felar','Disk health (SMART) warning or failing'),'nw_health'=>t('Hårdvara degraderad eller felar','Hardware degraded or failing'),'src_snmp'=>t('SNMP svarar inte','SNMP not responding'),'src_zabbix'=>t('Zabbix-agent svarar inte','Zabbix agent not responding'),'src_pbs'=>t('PBS API svarar inte','PBS API not responding'),'ha_updates'=>t('Home Assistant: uppdatering tillgänglig','Home Assistant: update available'),'ha_unavail'=>t('Home Assistant: otillgängliga entiteter över (antal)','Home Assistant: unavailable entities above (count)'),'ha_state'=>t('Home Assistant: status är inte running','Home Assistant: state is not running'),'ha_supported'=>t('Home Assistant: supported är inte true','Home Assistant: supported is not true'),'zbx'=>t('Zabbix-kontroll fallerar (välj typer)','Zabbix check failing (choose types)'),
 'log_event'=>t('Trap/syslog-händelse matchar','Trap/syslog event matches'),
 'users'=>t('Inloggade användare (antal, minst)','Logged-on users (count, at least)'),
 'sys_worker'=>t('Bakgrundstjänsten (worker) har stoppat','Background service (worker) has stopped'),'sys_zbx'=>t('Zabbix-lyssnaren (active-läge) har stoppat','Zabbix listener (active mode) has stopped'),'sys_recv'=>t('Trap/syslog-mottagaren har stoppat','Trap/syslog receiver has stopped'),'net_err'=>t('Nätverksfel per minut över','Network errors per minute above'),'net_drop'=>t('Tappade paket per minut över','Dropped packets per minute above'),'swap'=>t('Swap-användning över','Swap usage above'),'hw_temp'=>t('Temperatur (server/host, valfri sensor) över','Temperature (server/host, any sensor) above'),'hw_fan'=>t('Fläkthastighet (server/host) under (RPM)','Fan speed (server/host) below (RPM)'),'net_crc'=>t('CRC-fel per minut över','CRC errors per minute above'),'net_down'=>t('Interface nere','Interface down'),'net_util'=>t('Interface-belastning över','Interface utilization above'),
 'hv_ping'=>t('Host svarar inte (ping)','Host not responding (ping)'),'hv_down'=>t('Host svarar inte (API/anslutning)','Host not responding (API/connection)'),'hv_cpu'=>t('Host CPU över','Host CPU above'),'hv_mem'=>t('Host minne över','Host memory above'),'hv_storage'=>t('Storage-användning över','Storage usage above'),'hv_vm_down'=>t('VM körs inte','VM not running'),'hv_vm_cpu'=>t('VM CPU över','VM CPU above'),'hv_vm_mem'=>t('VM minne över','VM memory above'),
 'nw_ping'=>t('Nätverksenhet svarar inte (ping)','Network device not responding (ping)'),'nw_down'=>t('Nätverksenhet svarar inte (SNMP)','Network device not responding (SNMP)'),'nw_temp'=>t('Temperatur över','Temperature above'),'nw_hw'=>t('Fläkt eller nätdel felar','Fan or power supply fault'),'nw_psu'=>t('Nätdel (PSU) fel','Power supply (PSU) failure'),'nw_fan'=>t('Fläkt fel','Fan failure'), 'st_ping'=>t('Lagringsenhet svarar inte (ping)','Storage device not responding (ping)'),'st_down'=>t('Lagringsenhet svarar inte (SNMP)','Storage device not responding (SNMP)'),'st_temp'=>t('Lagring: temperatur över','Storage: temperature above'),'st_psu'=>t('Lagring: nätdel (PSU) fel','Storage: power supply (PSU) failure'),'st_fan'=>t('Lagring: fläkt fel','Storage: fan failure'),'st_hw'=>t('Lagring: fläkt eller nätdel felar','Storage: fan or power supply fault'),'st_health'=>t('Lagring: RAID/pool/volym degraderad eller felar','Storage: RAID/pool/volume degraded or failing'),
 'bmc_ping'=>t('BMC svarar inte (ping)','BMC not responding (ping)'),'bmc_down'=>t('BMC svarar inte (SNMP)','BMC not responding (SNMP)'),'bmc_temp'=>t('BMC: temperatur över','BMC: temperature above'),'bmc_psu'=>t('BMC: nätdel (PSU) fel','BMC: power supply (PSU) failure'),'bmc_fan'=>t('BMC: fläkt fel','BMC: fan failure'),'bmc_hw'=>t('BMC: fläkt eller nätdel felar','BMC: fan or power supply fault'),'bmc_health'=>t('BMC: hårdvara degraderad eller felar','BMC: hardware degraded or failing'),
 'smart_temp'=>t('Disktemperatur över','Disk temperature above')]; return $m; }
function metric_label($k){ return metric_labels()[$k]??$k; }
// Enhetstyp per regel: en regel gäller bara enheter av rätt typ (server, host, nätverk, lagring, BMC)
function dev_cat($s){ if(!empty($s['is_services'])) return 'svc'; if(!empty($s['hv_type'])) return 'hv'; if(!empty($s['nw_type'])){ $r=(string)($s['nw_role']??''); return $r==='bmc'?'bmc':(in_array($r,['nas','san'],true)?'st':'nw'); } return 'srv'; }
function metric_cat($m){ static $c=null; if($c===null){ $c=['offline'=>['srv'],'users'=>['srv'],'cpu'=>['srv','hv','nw','st'],'mem'=>['srv','hv','nw','st'],'disk'=>['srv','hv','st','nw'],'smart'=>['srv','hv','st'],'smart_temp'=>['srv','hv','st'],'src_snmp'=>['srv','hv','nw','st','bmc'],'src_zabbix'=>['srv','hv'],'zbx'=>['srv','hv'],'src_pbs'=>['srv','hv'],'ha_state'=>['srv'],'ha_supported'=>['srv'],'ha_updates'=>['srv'],'ha_unavail'=>['srv'],'nw_health'=>['nw','st','bmc'],'net_err'=>['srv','hv','nw','st','bmc'],'net_drop'=>['srv','hv','nw','st','bmc'],'net_down'=>['srv','hv','nw','st','bmc'],'net_crc'=>['srv','hv','nw','st','bmc'],'swap'=>['srv','hv'],'hw_temp'=>['srv','hv'],'hw_fan'=>['srv','hv'],'net_util'=>['srv','hv','nw','st','bmc']]; } if(isset($c[$m])) return $c[$m]; if(preg_match('/^(hv|nw|st|bmc)_/',$m,$x)) return [$x[1]]; return null; }
// Swap som text för serversidans infotabell: från SNMP (hrStorage) eller, för servrar i Zabbix active-läge, från agentens värden.
function zbx_swap_txt($srv,$inf){ $G=1073741824; $sw=$inf['hw']['swap']??null; if($sw&&($sw['t']??0)>0){ $p=round($sw['u']/$sw['t']*100); return round($sw['u']/$G,1).' / '.round($sw['t']/$G,1).' GB ('.$p.'%)'; }
 if(!empty($srv['zbx_active'])){ $t=zbxa_val((int)$srv['id'],'system.swap.size[,total]'); $pf=zbxa_val((int)$srv['id'],'system.swap.size[,pfree]'); if($t!==null&&is_numeric($t)&&$t>0){ $u=is_numeric($pf)?round((100-(float)$pf)/100*$t/$G,1):null; return ($u!==null?$u.' / ':'').round($t/$G,1).' GB'.($u!==null?' ('.round(100-(float)$pf).'%)':''); } } return null; }
function hw_of($srv){ $o=['temps'=>[],'fans'=>[],'swaps'=>[]]; if(!empty($srv['hv_type'])){ $d=json_decode((string)($srv['hv_data']??''),true)?:[]; $hw=$d['hw']??[]; $o['temps']=$hw['temps']??[]; $o['fans']=$hw['fans']??[]; if(!empty($hw['swap'])) $o['swaps'][]=['n'=>'Swap']+$hw['swap']; foreach($d['nodes']??[] as $n) if(!empty($n['swap_t'])) $o['swaps'][]=['n'=>'Swap '.$n['n'],'t'=>$n['swap_t'],'u'=>$n['swap']??0]; }
 else { $hw=(json_decode((string)($srv['info']??''),true)?:[])['hw']??[]; $o['temps']=$hw['temps']??[]; $o['fans']=$hw['fans']??[]; if(!empty($hw['swap'])) $o['swaps'][]=['n'=>'Swap']+$hw['swap']; } return $o; }
function hw_panel($srv){ $hw=hw_of($srv); if(!$hw['temps']&&!$hw['fans']&&!$hw['swaps']) return; $RL=['cpu'=>'CPU','board'=>t('Moderkort/system','Board/system'),'disk'=>t('Disk','Disk')];
 echo '<h3>'.t('Hårdvara','Hardware').'</h3>';
 if($hw['swaps']){ echo '<dl class="card info">'; foreach($hw['swaps'] as $x){ $p=$x['t']>0?round($x['u']/$x['t']*100):0; echo '<dt>'.h($x['n']).'</dt><dd>'.h(round($x['u']/1073741824,1).' / '.gb($x['t']).' ('.$p.'%)').'</dd>'; } echo '</dl>'; }
 if($hw['temps']){ echo '<div class="sc5"><table class="card"><tr><th>'.t('Temperatursensor','Temperature sensor').'</th><th>'.t('Typ','Type').'</th><th>'.t('Temperatur','Temperature').'</th></tr>'; foreach($hw['temps'] as $x){ $r=hw_temp_role($x['n']); echo '<tr><td>'.h($x['n']).'</td><td>'.h($RL[$r]??'–').'</td><td data-sort="'.(float)$x['v'].'">'.h(round($x['v'],1)).' °C</td></tr>'; } echo '</table></div>'; }
 if($hw['fans']){ echo '<div class="sc5"><table class="card"><tr><th>'.t('Fläkt','Fan').'</th><th>RPM</th><th>Status</th></tr>'; foreach($hw['fans'] as $x){ $ok=!empty($x['ok']); echo '<tr><td>'.h($x['n']).'</td><td data-sort="'.(int)($x['rpm']??0).'">'.(isset($x['rpm'])?number_format((int)$x['rpm'],0,'',' '):'–').'</td><td class="'.($ok?'okt':'badt').'">'.($ok?'OK':t('Fel/stoppad','Fault/stopped')).'</td></tr>'; } echo '</table></div>'; } }
function rule_state($r,$cs,$ifs,$tg=null,$sid=0,$srv=null){ $mt=srv_metrics($cs); if(!empty($srv['nw_type'])){ $nd=json_decode((string)$srv['nw_data'],true)?:[]; $mt['cpu']=$nd['cpu']??null; $mt['mem']=$nd['mem']??null; $mt['disks']=nw_has_disks($srv['nw_role']??'')?array_values(array_filter($nd['disks']??[],fn($x)=>!in_array('v:'.$x['n'],nw_hid($srv),true))):[]; } if(!empty($srv['hv_type'])){ $hd=json_decode((string)($srv['hv_data']??''),true)?:[]; if($mt['cpu']===null&&isset($hd['cpu'])) $mt['cpu']=round($hd['cpu'],1); if($mt['mem']===null&&isset($hd['mem'])) $mt['mem']=round($hd['mem'],1); $hhh=json_decode((string)($srv['hv_hidden']??''),true)?:[]; foreach($hd['storage']??[] as $x){ if(in_array($x['n'],$hhh['st']??[],true)||empty($x['total'])) continue; $mt['disks'][]=['n'=>$x['n'],'t'=>$x['total'],'f'=>$x['total']-$x['used']]; } } $cpu=$mt['cpu']; $mem=$mt['mem']; $disks=$mt['disks']; $m=$r['metric']; if(preg_match('/^(?:st|bmc)_(ping|down|temp|psu|fan|hw|health)$/',$m,$am)) $m='nw_'.$am[1]; $th=(float)$r['threshold']; if($m==='log_event') return log_rule_state($r,(int)$sid);
 if(strpos($m,'hv_')===0){ $d=json_decode((string)($srv['hv_data']??''),true)?:[]; $pc=fn($a,$b)=>$b>0?$a/$b*100:null; $GM=guest_mem_map(); $hh=json_decode((string)($srv['hv_hidden']??''),true)?:[];
  if($m==='hv_ping'){ foreach($cs as $c) if($c['kind']==='ping'&&$c['last_status']==='down'&&($c['mon_up']??1)) return [true,t('Hosten svarar inte på ping','Host does not reply to ping')]; return [false,'']; }
  if($m==='hv_down') return [($srv['hv_status']??'')==='down',t('Hosten svarar inte: ','Host not responding: ').($srv['hv_error']??'')];
  if($m==='hv_cpu') return [isset($d['cpu'])&&$d['cpu']>=$th,'CPU '.round($d['cpu']??0,1).'%'];
  if($m==='hv_mem') return [isset($d['mem'])&&$d['mem']>=$th,t('Minne ','Memory ').round($d['mem']??0,1).'%'];
  $o=[]; if($m==='hv_storage'){ foreach($d['storage']??[] as $x){ if(in_array($x['n'],$hh['st']??[],true)) continue; $u=$pc($x['used'],$x['total']); if($u!==null&&$u>=$th) $o[]=$x['n'].' '.round($u,1).'%'; } return [(bool)$o,implode(', ',$o)]; }
  foreach($d['vms']??[] as $v){ if(in_array($v['k'],$hh['vms']??[],true)||!vm_in($tg,$sid,$v['k'])) continue; $run=in_array($v['state']??'',['running','poweredon'],true);
   if($m==='hv_vm_down'&&!$run) $o[]=$v['n'].' ('.($v['state']?:'?').')';
   if($m==='hv_vm_cpu'&&$run&&isset($v['cpu'])&&$v['cpu']>=$th) $o[]=$v['n'].' '.round($v['cpu'],1).'%';
   if($m==='hv_vm_mem'&&$run){ $u=$GM[strtolower($v['n'])]??$pc($v['mem']??0,$v['mem_t']??0); if($u!==null&&$u>=$th) $o[]=$v['n'].' '.round($u,1).'%'; } }
  return [(bool)$o,implode('; ',$o)]; }
 if(strpos($m,'nw_')===0){ $d=json_decode((string)($srv['nw_data']??''),true)?:[];
  if($m==='nw_ping'){ foreach($cs as $c) if($c['kind']==='ping'&&$c['last_status']==='down'&&($c['mon_up']??1)) return [true,t('Enheten svarar inte på ping','Device does not reply to ping')]; return [false,'']; }
  if($m==='nw_down') return [($srv['nw_status']??'')==='down',t('SNMP svarar inte: ','SNMP not responding: ').($srv['nw_error']??'')];
  if($m==='nw_health'){ $o=[]; $hz=nw_hid($srv); foreach($d['health']??[] as $x) if($x['st']!=='ok'&&!in_array('h:'.$x['n'],$hz,true)) $o[]=$x['n'].' ('.$x['txt'].')'; return [(bool)$o,(nw_is_st($srv['nw_role']??'')?t('Lagring: ','Storage: '):t('Enheter: ','Devices: ')).implode('; ',$o)]; }
  if($m==='nw_temp'){ $o=[]; foreach($d['temps']??[] as $x) if($x['v']>=$th) $o[]=$x['n'].' '.round($x['v']).'°C'; if(!$o&&isset($d['temp'])&&$d['temp']>=$th) $o[]=round($d['temp']).'°C'; return [(bool)$o,t('Temperatur: ','Temperature: ').implode(', ',$o)]; }
  $o=[]; $hz=nw_hid($srv); $lst=$m==='nw_psu'?($d['psu']??[]):($m==='nw_fan'?($d['fans']??[]):array_merge($d['fans']??[],$d['psu']??[])); foreach($lst as $x) if(empty($x['ok'])&&!in_array($x['n'],$hz,true)) $o[]=$x['n']; return [(bool)$o,($m==='nw_psu'?t('Nätdelsfel: ','PSU failure: '):($m==='nw_fan'?t('Fläktfel: ','Fan failure: '):t('Maskinvarufel (fläkt/nätdel): ','Hardware fault (fan/PSU): '))).implode(', ',$o)]; }
 if(strpos($m,'net_')===0){ $o=[]; foreach($ifs as $i){ if(!iface_ok($r,$tg,$sid,$i)) continue; $n=$i['name'];
   if($m==='net_err'&&$i['err_pm']!==null&&(float)$i['err_pm']>=$th) $o[]="$n ".round($i['err_pm'],1).t(' fel/min',' errors/min');
   if($m==='net_drop'&&$i['disc_pm']!==null&&(float)$i['disc_pm']>=$th) $o[]="$n ".round($i['disc_pm'],1).t(' tappade/min',' dropped/min');
   if($m==='net_crc'&&isset($i['crc_pm'])&&$i['crc_pm']!==null&&(float)$i['crc_pm']>=$th) $o[]="$n ".round($i['crc_pm'],1).t(' CRC-fel/min',' CRC errors/min');
   if($m==='net_down'&&(int)$i['admin']===1&&(int)$i['oper']!==1) $o[]="$n ".t('är nere','is down');
   if($m==='net_util'&&(int)$i['speed_mbps']>0){ $u=max((float)$i['rate_in'],(float)$i['rate_out'])/((float)$i['speed_mbps']*1e6)*100; if($u>=$th) $o[]="$n ".round($u,1).'%'; } }
  return [(bool)$o,implode('; ',$o)]; }
 if($m==='users'){ $inf=json_decode((string)($srv['info']??''),true)?:[]; $nm=$inf['sessions']??[]; $n=isset($inf['users'])&&is_numeric($inf['users'])?(int)$inf['users']:($nm?count($nm):null); if($n===null) return [false,'']; return [$n>=max(1,(int)$th),t('Inloggade användare: ','Logged-on users: ').$n.($nm?' ('.implode(', ',array_slice($nm,0,10)).')':'')]; }
 if(in_array($m,['swap','hw_temp','hw_fan'],true)){ $hw=hw_of($srv); $o=[];
  if($m==='swap'){ foreach($hw['swaps'] as $x) if($x['t']>0){ $u=$x['u']/$x['t']*100; if($u>=$th) $o[]=$x['n'].' '.round($u,1).'%'; } return [(bool)$o,implode(', ',$o)]; }
  if($m==='hw_temp'){ foreach($hw['temps'] as $x) if((float)$x['v']>=$th) $o[]=$x['n'].' '.round($x['v']).'°C'; return [(bool)$o,t('Temperatur: ','Temperature: ').implode(', ',$o)]; }
  foreach($hw['fans'] as $x) if(isset($x['rpm'])&&(float)$x['rpm']<$th) $o[]=$x['n'].' '.(int)$x['rpm'].' RPM'; return [(bool)$o,t('Låg fläkthastighet: ','Low fan speed: ').implode(', ',$o)]; }
 if($m==='ha_unavail'){ $n=0; $nm=[]; foreach($cs as $c){ if($c['source']!=='ha'||!$c['last_data']||(isset($c['enabled'])&&!$c['enabled'])) continue; $d=json_decode($c['last_data'],true)['ha']??null; if(!is_array($d)||!isset($d['un_n'])) continue; $n+=(int)$d['un_n']; foreach($d['un']??[] as $u) $nm[]=$u['n']; } return [$n>=max(1,$th),t('Home Assistant: ','Home Assistant: ').$n.' '.t('otillgängliga entiteter','unavailable entities').($nm?' ('.implode(', ',array_slice($nm,0,10)).(count($nm)>10?'…':'').')':'')]; }
 if($m==='ha_updates'){ $o=[]; foreach($cs as $c){ if($c['source']!=='ha'||!$c['last_data']||(isset($c['enabled'])&&!$c['enabled'])) continue; $d=json_decode($c['last_data'],true)['ha']??null; if(!is_array($d)||empty($d['upd_n'])) continue; foreach($d['upd'] as $u) $o[]=$u['n'].($u['a']!==''||$u['b']!==''?' '.$u['a'].' → '.$u['b']:''); if(!$d['upd']) $o[]=$d['upd_n'].' '.t('uppdateringar','updates'); } return [(bool)$o,t('Home Assistant: uppdatering tillgänglig: ','Home Assistant: update available: ').implode('; ',$o)]; }
 if($m==='ha_state'||$m==='ha_supported'){ $ha=(json_decode((string)($srv['info']??''),true)?:[])['ha']??null; if(!is_array($ha)) return [false,'']; if($m==='ha_state'){ $v=(string)($ha['state']??''); return [$v!==''&&strtolower($v)!=='running',t('Home Assistant: status ','Home Assistant: state ').$v]; } $v=(string)($ha['supported']??''); return [$v!==''&&strtolower($v)!=='true',t('Home Assistant: stöds inte (supported = ','Home Assistant: unsupported (supported = ').$v.')']; }
 if($m==='cpu') return [$cpu!==null&&$cpu>=$th,"CPU $cpu%"];
 if($m==='mem') return [$mem!==null&&$mem>=$th,t('Minne ','Memory ').$mem.'%'];
 if($m==='disk'){ $o=[]; foreach($disks as $d) if($d['t']>0){ $u=100-$d['f']/$d['t']*100; if($u>=$th) $o[]=$d['n'].' '.round($u,1).'%'; } return [(bool)$o,implode(', ',$o)]; }
 if($m==='smart_temp'){ $sm=srv_smart(json_decode((string)($srv['info']??''),true)?:[],$cs); foreach((json_decode((string)($srv['hv_data']??''),true)['smart']??[]) as $x) $sm[]=$x; foreach((json_decode((string)($srv['nw_data']??''),true)['smart']??[]) as $x) if(!in_array('s:'.$x['n'],nw_hid($srv),true)) $sm[]=$x; $o=[]; foreach($sm as $x) if(isset($x['temp'])&&$x['temp']!==null&&(float)$x['temp']>=$th) $o[]=$x['n'].' '.round((float)$x['temp']).'°C'; return [(bool)$o,t('Disktemperatur: ','Disk temperature: ').implode('; ',$o)]; }
 if($m==='smart'){ $sm=srv_smart(json_decode((string)($srv['info']??''),true)?:[],$cs); foreach((json_decode((string)($srv['hv_data']??''),true)['smart']??[]) as $x) $sm[]=$x; foreach((json_decode((string)($srv['nw_data']??''),true)['smart']??[]) as $x) if(!in_array('s:'.$x['n'],nw_hid($srv),true)) $sm[]=$x; $o=[]; foreach($sm as $x) if($x['st']==='fail') $o[]=$x['n'].' '.($x['model']?'('.$x['model'].') ':'').t('SMART-fel','SMART failing'); elseif($x['st']==='warn') $o[]=$x['n'].' '.t('varnar','warning').($x['realloc']?' ('.$x['realloc'].' realloc)':'').($x['pend']?' ('.$x['pend'].' pending)':'').($x['temp']!==null&&$x['temp']>=60?' '.$x['temp'].'°C':''); return [(bool)$o,t('Diskhälsa: ','Disk health: ').implode('; ',$o)]; }
 if(in_array($m,['src_snmp','src_zabbix','src_pbs'],true)){ $sr=substr($m,4); $o=[]; foreach($cs as $c) if($c['source']===$sr&&$c['last_status']==='down') $o[]=$c['label'].' ('.$c['last_detail'].')'; return [(bool)$o,['snmp'=>'SNMP','zabbix'=>'Zabbix-agent','pbs'=>'PBS API'][$sr].t(' svarar inte: ',' not responding: ').implode('; ',$o)]; }
 if($m==='zbx'){ $kl=array_filter(array_map('trim',explode(',',(string)($r['kinds']??'')))); $o=[]; foreach($cs as $c) if($c['source']==='zabbix'&&$c['last_status']==='down'&&($c['mon_up']??1)&&(!$kl||in_array($c['kind'],$kl,true))) $o[]=$c['label'].' ('.$c['last_detail'].')'; return [(bool)$o,t('Zabbix-kontroll: ','Zabbix check: ').implode('; ',$o)]; }
 if($m==='check_ms'){ $o=[]; foreach($cs as $c) if($c['last_status']==='up'&&!empty($c['mon_ms'])&&chk_in($tg,$sid,$c)&&preg_match('/^(?:HTTP \d+, )?(\d+(?:\.\d+)?)\s*ms/',(string)$c['last_detail'],$mm)&&(float)$mm[1]>=$th) $o[]=$c['label'].' '.$mm[1].' ms'; return [(bool)$o,t('Svarstid över gränsen: ','Response time over limit: ').implode('; ',$o)]; }
 if($m==='check'){ $o=[]; foreach($cs as $c) if($c['last_status']==='down'&&($c['mon_up']??1)&&chk_in($tg,$sid,$c)&&!(($c['kind']??'')==='ping'&&empty($srv['is_services']))) $o[]=$c['label'].' ('.$c['last_detail'].')'; return [(bool)$o,t('Nere: ','Down: ').implode('; ',$o)]; }
 $off=false; foreach($cs as $c) if($c['source']==='tcp'&&$c['kind']==='ping'&&$c['last_status']==='down'&&($c['mon_up']??1)) $off=true; return [$off,t('Servern svarar inte på ping','Server does not reply to ping')]; }
function sms_send($text){ $url=trim(setting('sms_url','')); $to=preg_split('/[\s,;]+/',setting('sms_to',''),-1,PREG_SPLIT_NO_EMPTY);
 if($url===''||!$to) return t('SMS är inte konfigurerat (Inställningar)','SMS is not configured (Settings)'); $get=setting('sms_method','POST')==='GET'; $json=setting('sms_format','form')==='json'; $tpl=setting('sms_body',''); $from=setting('sms_from',''); $text=mb_substr($text,0,setting('sms_max','300')?(int)setting('sms_max','300'):300); $err=[];
 $enc=function($v,$mode){ return $mode==='url'?rawurlencode($v):($mode==='json'?substr(json_encode($v,JSON_UNESCAPED_UNICODE),1,-1):$v); };
 foreach($to as $n){ $hd=[]; $b=null; $mode=$json?'json':'url'; $rep=fn($s,$m)=>str_replace(['{to}','{from}','{message}','{text}'],[$enc($n,$m),$enc($from,$m),$enc($text,$m),$enc($text,$m)],$s);
  $u=$get?$rep($url,'url'):$url; if(!$get){ $b=$rep($tpl,$mode); $hd[]='Content-Type: '.($json?'application/json':'application/x-www-form-urlencoded'); }
  $hn=trim(setting('sms_header','')); $hv=secret('sms_secret'); if($hn!==''&&$hv!=='') $hd[]="$hn: $hv";
  $c=curl_init($u); $o=[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$hd]; if(!$get){ $o[CURLOPT_POST]=1; $o[CURLOPT_POSTFIELDS]=$b; } $us=trim(setting('sms_user','')); if($us!=='') $o[CURLOPT_USERPWD]=$us.':'.secret('sms_pass');
  curl_setopt_array($c,$o); $r=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); $e=curl_error($c); curl_close($c);
  if(!($code>=200&&$code<300)) $err[]="$n: ".($e?:"HTTP $code ".substr(trim((string)$r),0,120)); }
 return $err?implode('; ',$err):null; }
function tg_send($text){ $tok=trim(secret('tg_token')); $chats=preg_split('/[\s,;]+/',setting('tg_chat',''),-1,PREG_SPLIT_NO_EMPTY);
 if($tok===''||!$chats) return t('Telegram är inte konfigurerat (Inställningar)','Telegram is not configured (Settings)'); $err=[];
 foreach($chats as $ch){ $f=['chat_id'=>$ch,'text'=>substr($text,0,4000),'disable_web_page_preview'=>1]; $th=trim(setting('tg_thread','')); if($th!=='') $f['message_thread_id']=$th;
  $c=curl_init("https://api.telegram.org/bot$tok/sendMessage"); curl_setopt_array($c,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>$f,CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>10]); $r=curl_exec($c); $e=curl_error($c); curl_close($c);
  $j=json_decode((string)$r,true); if(!$j||empty($j['ok'])) $err[]="$ch: ".($e?:($j['description']??'?')); }
 return $err?implode('; ',$err):null; }
function signal_send($text){ $url=rtrim(trim(setting('signal_url','')),'/'); $from=trim(setting('signal_from','')); $to=preg_split('/[\s,;]+/',setting('signal_to',''),-1,PREG_SPLIT_NO_EMPTY);
 if($url===''||$from===''||!$to) return t('Signal är inte konfigurerat (Inställningar)','Signal is not configured (Settings)');
 $hd=['Content-Type: application/json']; $hn=trim(setting('signal_header','')); $hv=secret('signal_secret'); if($hn!==''&&$hv!=='') $hd[]="$hn: $hv";
 $c=curl_init($url.'/v2/send'); curl_setopt_array($c,[CURLOPT_POST=>1,CURLOPT_POSTFIELDS=>json_encode(['message'=>mb_substr($text,0,1800),'number'=>$from,'recipients'=>array_values($to)],JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>$hd,CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15]);
 $r=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); $e=curl_error($c); curl_close($c);
 if($code>=200&&$code<300) return null; $j=json_decode((string)$r,true); return $e?:("HTTP $code ".(is_array($j)&&!empty($j['error'])?$j['error']:substr(trim((string)$r),0,150))); }
// Namnet som visas på dashboardkortet: värdnamn (SNMP/Zabbix) om det finns, annars IP/host när servern kontrollerats, annars namnet man gett. Hosts och nätverksenheter: namn, annars host.
function b32enc($s){ $a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $b=''; foreach(str_split($s) as $c) $b.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT); $o=''; foreach(str_split($b,5) as $x) $o.=$a[bindec(str_pad($x,5,'0'))]; return $o; }
function b32dec($s){ $a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s=strtoupper(preg_replace('/[^A-Za-z2-7]/','',$s)); $b=''; foreach(str_split($s) as $c) $b.=str_pad(decbin(strpos($a,$c)),5,'0',STR_PAD_LEFT); $o=''; foreach(str_split($b,8) as $x) if(strlen($x)===8) $o.=chr(bindec($x)); return $o; }
function totp_at($secret,$step){ $h=hash_hmac('sha1',pack('J',$step),b32dec($secret),true); $o=ord($h[19])&15; $v=(unpack('N',substr($h,$o,4))[1]&0x7fffffff)%1000000; return str_pad((string)$v,6,'0',STR_PAD_LEFT); }
function totp_match($secret,$code,$after=0){ $code=preg_replace('/\s/','',(string)$code); if(!preg_match('/^\d{6}$/',$code)||$secret==='') return false; $now=intdiv(time(),30); for($d=-1;$d<=1;$d++){ $st=$now+$d; if($st<=$after) continue; if(hash_equals(totp_at($secret,$st),$code)) return $st; } return false; }
function totp_check($u,$code){ $st=totp_match(secret_dec((string)($u['totp_secret']??'')),$code,(int)($u['totp_last']??0)); if($st===false) return false; try{ db()->prepare('UPDATE users SET totp_last=? WHERE id=?')->execute([$st,$u['id']]); }catch(Throwable $e){} return true; }
function recovery_norm($c){ return strtolower(preg_replace('/[^A-Za-z0-9]/','',(string)$c)); }
function recovery_new($uid){ $al='abcdefghjkmnpqrstuvwxyz23456789'; $codes=[]; $hs=[]; for($i=0;$i<8;$i++){ $c=''; for($j=0;$j<10;$j++) $c.=$al[random_int(0,strlen($al)-1)]; $codes[]=substr($c,0,5).'-'.substr($c,5); $hs[]=hash('sha256',$c); } db()->prepare('UPDATE users SET totp_recovery=? WHERE id=?')->execute([json_encode($hs),$uid]); return $codes; }
function recovery_use($u,$code){ $n=recovery_norm($code); if(strlen($n)!==10||empty($u['totp_recovery'])) return false; $l=json_decode((string)$u['totp_recovery'],true)?:[]; $h=hash('sha256',$n); foreach($l as $i=>$x) if(hash_equals((string)$x,$h)){ unset($l[$i]); $l=array_values($l); try{ db()->prepare('UPDATE users SET totp_recovery=? WHERE id=?')->execute([json_encode($l),$u['id']]); }catch(Throwable $e){} return count($l); } return false; }
function login_user_by_id($id){ try{ $s=db()->prepare('SELECT *,IFNULL(locked_until>NOW(),0) AS locked,TIMESTAMPDIFF(MINUTE,NOW(),locked_until)+1 AS lmin FROM users WHERE id=?'); $s->execute([$id]); }catch(Throwable $e){ $s=db()->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$id]); } return $s->fetch(); }
function login_locked_msg($min){ return t('Kontot är låst efter för många misslyckade försök. Försök igen om ca ','The account is locked after too many failed attempts. Try again in about ').$min.t(' min eller be en administratör låsa upp det.',' min or ask an administrator to unlock it.'); }
function login_done($u,$via='',$redir=true){ session_regenerate_id(true); unset($_SESSION['p2'],$_SESSION['sso']); unset($u['totp_secret'],$u['totp_recovery'],$u['email_token']); $_SESSION['login_t']=time(); $_SESSION['u']=$u; $_SESSION['lang']=$u['lang']; try{ db()->prepare('UPDATE users SET last_login=NOW(),last_seen=NOW(),logged_in=1 WHERE id=?')->execute([$u['id']]); db()->prepare('UPDATE users SET locked_until=NULL WHERE id=?')->execute([$u['id']]); db()->prepare('DELETE FROM login_fail WHERE user_id=?')->execute([$u['id']]); }catch(Throwable $e){} applog('info','auth','Login: '.$u['username'].$via.' ('.client_ip().')'); if($redir){ header('Location: index.php'); exit; } }

function b64u($s){ return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
function b64u_dec($s){ return base64_decode(strtr((string)$s,'-_','+/')); }
function cbor_dec($d,&$o){ $b=ord($d[$o++]); $mt=$b>>5; $ai=$b&31;
 if($ai<24) $v=$ai; elseif($ai===24){ $v=ord($d[$o++]); } elseif($ai===25){ $v=unpack('n',substr($d,$o,2))[1]; $o+=2; } elseif($ai===26){ $v=unpack('N',substr($d,$o,4))[1]; $o+=4; } elseif($ai===27){ $v=unpack('J',substr($d,$o,8))[1]; $o+=8; } else throw new Exception('cbor');
 switch($mt){ case 0: return $v; case 1: return -1-$v; case 2: case 3: $r=substr($d,$o,$v); $o+=$v; return $r;
  case 4: $r=[]; for($i=0;$i<$v;$i++) $r[]=cbor_dec($d,$o); return $r;
  case 5: $r=[]; for($i=0;$i<$v;$i++){ $k=cbor_dec($d,$o); $r[$k]=cbor_dec($d,$o); } return $r;
  case 6: return cbor_dec($d,$o); default: return [20=>false,21=>true,22=>null][$v]??null; } }
function der_len($n){ return $n<128?chr($n):($n<256?"\x81".chr($n):"\x82".pack('n',$n)); }
function der_t($t,$v){ return chr($t).der_len(strlen($v)).$v; }
function der_int($b){ $b=ltrim($b,"\0"); if($b===''||ord($b[0])>127) $b="\0".$b; return der_t(2,$b); }
function cose_pem($k){ $kty=$k[1]??0; $alg=$k[3]??0;
 if($kty===2&&$alg===-7&&($k[-1]??0)===1&&strlen($k[-2]??'')===32&&strlen($k[-3]??'')===32){ $der=hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200')."\x04".$k[-2].$k[-3]; }
 elseif($kty===3&&$alg===-257&&!empty($k[-1])&&!empty($k[-2])){ $rsa=der_t(0x30,der_int($k[-1]).der_int($k[-2])); $der=der_t(0x30,der_t(0x30,hex2bin('06092a864886f70d0101010500')).der_t(3,"\0".$rsa)); }
 else return null;
 return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n"; }
function wa_rp(){ return strtolower(preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''))); }
function wa_client($cdj,$type){ $c=json_decode($cdj,true); $w=$_SESSION['wa']??null; unset($_SESSION['wa']);
 if(!is_array($c)||!$w||time()-$w['t']>300||$w['k']!==$type) return 'challenge expired';
 if(($c['type']??'')!==$type) return 'type'; if(!hash_equals($w['c'],(string)($c['challenge']??''))) return 'challenge';
 $h=strtolower((string)parse_url((string)($c['origin']??''),PHP_URL_HOST)); $sc=(string)parse_url((string)($c['origin']??''),PHP_URL_SCHEME);
 if($h===''||$h!==wa_rp()||($sc!=='https'&&$h!=='localhost')) return 'origin'; return ''; }
function td_valid($uid){ $c=(string)($_COOKIE['vm_td']??''); if(!preg_match('/^(\d+)\.([a-f0-9]{64})$/',$c,$m)) return false; try{ $q=db()->prepare('SELECT token_hash FROM trusted_devices WHERE id=? AND user_id=? AND expires>NOW()'); $q->execute([$m[1],$uid]); $h=$q->fetchColumn(); }catch(Throwable $e){ return false; } return $h&&hash_equals($h,hash('sha256',$m[2])); }
function td_new($uid,$days){ try{ $tok=bin2hex(random_bytes(32)); $pdo=db(); if(rand(1,20)===1) $pdo->query('DELETE FROM trusted_devices WHERE expires<NOW()'); $pdo->prepare('INSERT INTO trusted_devices(user_id,token_hash,expires) VALUES(?,?,NOW()+INTERVAL '.(int)$days.' DAY)')->execute([$uid,hash('sha256',$tok)]); setcookie('vm_td',$pdo->lastInsertId().'.'.$tok,['expires'=>time()+$days*86400,'path'=>'/','secure'=>is_https(),'httponly'=>true,'samesite'=>'Lax']); }catch(Throwable $e){} }
function td_clear($uid){ try{ db()->prepare('DELETE FROM trusted_devices WHERE user_id=?')->execute([$uid]); }catch(Throwable $e){} }
function login_failed($u){ try{ $pdo=db(); $max=max(1,(int)setting('lock_attempts',5)); $win=max(1,(int)setting('lock_window',5)); $lk=max(1,(int)setting('lock_minutes',15));
 $pdo->prepare('INSERT INTO login_fail(user_id,ip) VALUES(?,?)')->execute([$u['id'],substr((string)(client_ip()),0,45)]);
 $q=$pdo->prepare('SELECT COUNT(*) FROM login_fail WHERE user_id=? AND ts>NOW()-INTERVAL '.$win.' MINUTE'); $q->execute([$u['id']]);
 if((int)$q->fetchColumn()>=$max){ $pdo->prepare('UPDATE users SET locked_until=NOW()+INTERVAL '.$lk.' MINUTE WHERE id=?')->execute([$u['id']]); $pdo->prepare('DELETE FROM login_fail WHERE user_id=?')->execute([$u['id']]); applog('warning','auth',('Account locked for '.$lk.' min after too many failed logins: ').$u['username']); return $lk; }
 }catch(Throwable $e){} return 0; }
function srv_title($s,$checked=false){ if(!empty($s['is_services'])) return t('Tjänster','Services');
 $hn=''; $cl=function($v){ $v=trim((string)$v); if($v!==''&&!preg_match('/^\d+\.\d+\.\d+\.\d+$/',$v)) $v=explode('.',$v)[0]; return $v; };
 if(!empty($s['nw_type'])){ $d=json_decode((string)($s['nw_data']??''),true)?:[]; $hn=$cl($d['hostname']??''); }
 elseif(!empty($s['hv_type'])){ $d=json_decode((string)($s['hv_data']??''),true)?:[]; $nodes=$d['nodes']??[];
  if(count($nodes)===1) $hn=$cl($nodes[0]['n']??''); else foreach($nodes as $n){ if(($n['ip']??'')!==''&&$n['ip']===$s['host']){ $hn=$cl($n['n']??''); break; } }
  if($hn===''){ $inf=json_decode((string)($s['info']??''),true)?:[]; $hn=$cl($inf['hostname']??''); } }
 else { $inf=json_decode((string)($s['info']??''),true)?:[]; $hn=$cl($inf['hostname']??''); }
 if($hn!=='') return $hn;
 if(setting('title_own_name','0')==='1'&&trim((string)$s['name'])!=='') return $s['name'];
 return $s['host']; }
function api_uptime($s,$lbl=null){ $lbl=$lbl?:(!empty($s['nw_type'])?'SNMP':strtoupper((string)$s['hv_type']).' API'); $o=['–','–','–','–'];
 try{ $q=db()->prepare("SELECT UNIX_TIMESTAMP(ts) t,status FROM check_events WHERE server_id=? AND check_id IS NULL AND svc=0 AND label=? ORDER BY id"); $q->execute([$s['id'],$lbl]); $ev=$q->fetchAll(); }catch(Throwable $e){ return $o; }
 $now=time(); $iv=[]; $dn=null; foreach($ev as $e){ if($e['status']==='down'){ if($dn===null) $dn=(int)$e['t']; } elseif($dn!==null){ $iv[]=[$dn,(int)$e['t']]; $dn=null; } } if($dn!==null) $iv[]=[$dn,$now];
 $first=$ev?max(0,(int)$ev[0]['t']-3600):null;
 foreach([86400,604800,2592000,0] as $i=>$w){ $from=$w?$now-$w:($first??$now-86400); if($w&&$first!==null) $from=max($from,min($first,$from)); $span=$now-$from; if($span<=0) continue; $dd=0; foreach($iv as $x){ $a=max($x[0],$from); $b=min($x[1],$now); if($b>$a) $dd+=$b-$a; } $o[$i]=number_format(max(0,100-$dd/$span*100),2).'%'; }
 if(!$ev) $o=['100.00%','100.00%','100.00%','100.00%'];
 return $o; }
function fmt_dur_s($sec){ $sec=max(0,(int)$sec); if($sec<60) return $sec.' s'; if($sec<3600) return floor($sec/60).' min'.($sec%60?' '.($sec%60).' s':''); if($sec<86400) return floor($sec/3600).' h '.floor($sec%3600/60).' min'; return floor($sec/86400).' '.t('d','d').' '.floor($sec%86400/3600).' h '.floor($sec%3600/60).' min'; }
function alert_fire($r,$s,$ev,$info,$cx=[]){ if(!empty($s['is_services'])) $s['name']=t('Tjänster','Services'); $m=$r['metric']; $tag=['alarm'=>t('LARM','ALERT'),'repeat'=>t('FORTSÄTTER','STILL ACTIVE'),'ok'=>'OK'][$ev];
 $since=$cx['since']??null; $age=isset($cx['age'])?(int)$cx['age']:null; $now=date('Y-m-d H:i:s'); $prev='';
 if($ev==='ok'){ try{ $q=db()->prepare("SELECT message FROM alert_log WHERE server_id=? AND metric=? AND event='alarm' ORDER BY id DESC LIMIT 1"); $q->execute([(int)($s['id']??0),$m]); $prev=(string)$q->fetchColumn(); }catch(Throwable $e){} }
 $tm=''; if($ev==='ok'){ if($since) $tm=t('Problemet startade: ','Problem started: ').$since."\n".t('Återställt: ','Recovered: ').$now."\n".t('Tid med problem: ','Time with problem: ').fmt_dur_s($age)."\n"; }
 else { if($since) $tm=t('Problemet startade: ','Problem started: ').$since."\n".t('Pågått: ','Ongoing for: ').fmt_dur_s($age)."\n"; }
 $cn=trim((string)($r['name']??'')); $ml=$cn!==''?$cn:metric_label($m); $thr=in_array($m,['users','cpu','mem','disk','swap','hw_temp','hw_fan','check_ms','ha_unavail','smart_temp','net_err','net_drop','net_crc','net_util','hv_cpu','hv_mem','hv_storage','hv_vm_cpu','hv_vm_mem','nw_temp','st_temp','bmc_temp'],true)?' ('.rtrim(rtrim(number_format((float)$r['threshold'],2,'.',''),'0'),'.').')':''; if($cn!=='') $thr=''; $rl=t('Regel','Rule').' #'.(int)($r['id']??0).': '.$ml.$thr;
 $body=$ev==='ok'?t("Larmet är återställt för {$s['name']} ($ml).","Alert cleared for {$s['name']} ($ml).")."\n".t('Regel','Rule').': '.$ml."\n".$tm.($prev!==''?t('Det som larmade: ','What alerted: ').$prev:''):"Server: {$s['name']} ({$s['host']})\n".$rl."\n".$tm.$info;
 $sfx=($ev==='ok'&&$since)?' ('.t('nere/problem i ','problem for ').fmt_dur_s($age).')':'';
 $subj="[$tag] {$s['name']}: $ml$sfx"; $res=[]; applog($ev==='ok'?'info':'warning','alert',"[$tag] {$s['name']}: $ml".($info!==''?' – '.str_replace("\n",' ',substr($info,0,300)):''));
 if(!empty($r['notify_email'])){ $e=send_mail(setting('alarm_contacts',''),$subj,$body); $res[]='Email: '.($e?:'OK'); }
 if(!empty($r['notify_tg'])){ $e=tg_send(($ev==='ok'?'✅ ':($ev==='repeat'?'⚠️ ':'🔴 ')).$subj."\n".$body); $res[]='Telegram: '.($e?:'OK'); }
 if(!empty($r['notify_wh'])){ $e=wh_send(['event'=>$ev,'subject'=>$subj,'server'=>$s['name'],'host'=>$s['host'],'rule'=>$m,'rule_name'=>$ml,'threshold'=>(float)$r['threshold'],'message'=>$ev==='ok'?$prev:$info,'since'=>$since,'duration_sec'=>$age,'time'=>date('c')],$subj."\n".$body); $res[]='Webhook: '.($e?:'OK'); }
 if(!empty($r['notify_signal'])){ $e=signal_send(($ev==='ok'?'OK ':'').$subj."\n".($ev==='ok'?($since?t('Startade ','Started ').$since:''):$tm.$info)); $res[]='Signal: '.($e?:'OK'); }
 if(!empty($r['notify_sms'])){ $e=sms_send(($ev==='ok'?'OK ':'').$subj.($ev==='ok'?'':' – '.str_replace("\n",' ',$tm.$info))); $res[]='SMS: '.($e?:'OK'); }
 if(!$res) $res[]=t('Ingen kanal vald','No channel selected');
 foreach($res as $x) if(!preg_match('/: OK$/',$x)) applog('error','notify',"{$s['name']}: $x"); if(!array_filter($res,fn($x)=>!preg_match('/: OK$/',$x))) applog('info','notify',"{$s['name']}: ".implode(', ',array_map(fn($x)=>preg_replace('/: OK$/','',$x),$res)));
 db()->prepare('INSERT INTO alert_log(server_id,server_name,metric,event,message,mail_result) VALUES(?,?,?,?,?,?)')->execute([(int)($s['id']??0)?:null,$s['name'],$m,$ev,substr(($cn!==''?"[$cn] ":'').$info,0,1000),substr(implode('; ',$res),0,250)]); }
// Total överblick: alla warning/critical-händelser loggas oavsett längd, med orsak till att ingen notis skickats (alert_events)
function track_events(){ $pdo=db(); $ths=function($k,$d){ $n=(int)setting("thr_{$k}_n",$d); return [$n+1,max((int)setting("thr_{$k}_w",85),$n+1)+1]; };
 [$cw,$cc]=$ths('cpu',75); [$mw,$mc]=$ths('mem',75); [$dw,$dc]=$ths('disk',75); $msn=(int)setting('thr_ms_n',200); $msw=max((int)setting('thr_ms_w',500),$msn+1);
 // [metric, regelmetric, varning-tröskel, critical-tröskel] (null = bara critical / bara varning)
 $DEF=[['check','check',null,0],['check_ms','check_ms',$msn,$msw],['cpu','cpu',$cw,$cc],['mem','mem',$mw,$mc],['disk','disk',$dw,$dc],['offline','offline',null,0],['smart','smart',null,0],
  ['hv_down','hv_down',null,0],['hv_ping','hv_ping',null,0],['hv_cpu','hv_cpu',$cw,$cc],['hv_mem','hv_mem',$mw,$mc],['hv_storage','hv_storage',$dw,$dc],
  ['nw_down','nw_down',null,0],['nw_ping','nw_ping',null,0],['nw_psu','nw_psu',null,0],['nw_fan','nw_fan',null,0],['nw_hw','nw_hw',null,0],['nw_temp','nw_temp',65,75],
  ['st_down','st_down',null,0],['st_ping','st_ping',null,0],['st_health','st_health',null,0],['st_psu','st_psu',null,0],['st_fan','st_fan',null,0],['st_hw','st_hw',null,0],['st_temp','st_temp',65,75],
  ['bmc_down','bmc_down',null,0],['bmc_ping','bmc_ping',null,0],['bmc_health','bmc_health',null,0],['bmc_psu','bmc_psu',null,0],['bmc_fan','bmc_fan',null,0],['bmc_hw','bmc_hw',null,0],['bmc_temp','bmc_temp',65,75]];
 $gp=setting('alerts_paused_until',''); $paused=$gp!==''&&(bool)$pdo->query('SELECT '.$pdo->quote($gp).'>NOW()')->fetchColumn();
 $rules=$pdo->query('SELECT * FROM alert_rules WHERE enabled=1')->fetchAll(); $TG=[]; $chk=$pdo->prepare('SELECT * FROM server_checks WHERE server_id=? AND enabled=1'); $nif=$pdo->prepare('SELECT * FROM net_if WHERE server_id=?');
 $open=[]; foreach($pdo->query('SELECT *,TIMESTAMPDIFF(SECOND,started,NOW()) age FROM alert_events WHERE ended IS NULL')->fetchAll() as $o) $open[$o['server_id'].'|'.$o['kind']]=$o;
 $sq=$pdo->prepare('SELECT *,TIMESTAMPDIFF(SECOND,since,NOW()) age FROM alert_state WHERE rule_id=? AND server_id=?'); $aq=$pdo->prepare("SELECT COUNT(*) FROM alert_log WHERE server_id=? AND metric=? AND event='alarm' AND ts>=?");
 $seen=[];
 foreach($pdo->query('SELECT *,(maint_until IS NOT NULL AND maint_until>NOW()) AS maint FROM servers WHERE enabled=1')->fetchAll() as $s){ $sid=(int)$s['id']; $chk->execute([$sid]); $cs=$chk->fetchAll(); $nif->execute([$sid]); $ifs=$nif->fetchAll();
  foreach($DEF as [$m,$rm,$tw,$tc]){
   if(!empty($s['is_services'])&&!in_array($m,['check','check_ms'],true)) continue; if(($mc=metric_cat($m))!==null&&!in_array(dev_cat($s),$mc,true)) continue;
   $sev=null; $det='';
   [$bc,$ic]=rule_state(['metric'=>$rm,'threshold'=>$tc,'iface'=>'','kinds'=>''],$cs,$ifs,null,$sid,$s); if($bc){ $sev='critical'; $det=$ic; }
   elseif($tw!==null){ [$bw,$iw]=rule_state(['metric'=>$rm,'threshold'=>$tw,'iface'=>'','kinds'=>''],$cs,$ifs,null,$sid,$s); if($bw){ $sev='warning'; $det=$iw; } }
   $key=$sid.'|'.$m; $ev=$open[$key]??null; if(!$sev){ continue; } $seen[$key]=1;
   // orsak till notis / ingen notis
   $mine=array_values(array_filter($rules,function($r) use($rm,$m,&$TG,$s,$cs){ if($r['metric']!==$rm) return false; $tg=$TG[$r['id']]??=rule_targets($r); return rule_applies($tg,$r,$s,$cs); }));
   $age=$ev?(int)$ev['age']:0; $notified=0; $reason='';
   if($s['maint']) $reason=t('Underhållsläge (larm pausade för enheten)','Maintenance mode (alerts paused for the unit)');
   elseif($paused) $reason=t('Alla larm pausade','All alerts paused');
   elseif(!$mine) $reason=t('Ingen matchande regel','No matching rule');
   else { $inS=array_values(array_filter($mine,'rule_in_schedule')); if(!$inS) $reason=t('Utanför regelns schema','Outside the rule schedule');
    else { $hit=null; $wait=null; $notH=false; foreach($inS as $r){ $tg=$TG[$r['id']]; [$b]=rule_state($r,$cs,$ifs,$tg,$sid,$s); if(!$b) continue; $notH=true; $aq->execute([$sid,$r['metric'],$ev?$ev['started']:date('Y-m-d H:i:s')]); if($ev&&(int)$aq->fetchColumn()>0){ $hit=$r; break; } $wait=$wait&&$wait['minutes']<=$r['minutes']?$wait:$r; }
     if($hit){ $notified=1; $reason=sprintf(t('Notis skickad (regel #%d, krävde %d min)','Notification sent (rule #%d, required %d min)'),$hit['id'],$hit['minutes']); }
     elseif($wait) $reason=sprintf(t('Väntar: regel #%d kräver %d min (hittills %s)','Waiting: rule #%d requires %d min (so far %s)'),$wait['id'],$wait['minutes'],fmt_dur_s($age));
     elseif(!$notH) $reason=t('Regelns tröskel nås inte','Rule threshold not reached'); } }
   if(!$ev){ $pdo->prepare('INSERT INTO alert_events(server_id,server_name,kind,sev,detail,started,notified,reason) VALUES(?,?,?,?,?,NOW(),?,?)')->execute([$sid,substr(!empty($s['is_services'])?t('Tjänster','Services'):$s['name'],0,80),$m,$sev,substr($det,0,500),$notified,substr($reason,0,255)]); }
   elseif($ev['sev']!==$sev){ // eskalering/nedtrappning = ny händelse
    $pdo->prepare('UPDATE alert_events SET ended=NOW(),dur=TIMESTAMPDIFF(SECOND,started,NOW()) WHERE id=?')->execute([$ev['id']]);
    $pdo->prepare('INSERT INTO alert_events(server_id,server_name,kind,sev,detail,started,notified,reason) VALUES(?,?,?,?,?,NOW(),?,?)')->execute([$sid,$ev['server_name'],$m,$sev,substr($det,0,500),$notified,substr($reason,0,255)]); }
   else $pdo->prepare('UPDATE alert_events SET detail=?,notified=?,reason=? WHERE id=?')->execute([substr($det,0,500),$notified,substr($reason,0,255),$ev['id']]); }
 }
 // stängda: problemet är borta
 foreach($open as $key=>$o){ if(isset($seen[$key])) continue; $dur=(int)$o['age']; $reason=(string)$o['reason'];
  if(!$o['notified']){ if(preg_match('/#(\d+) kräver (\d+) min|#(\d+) requires (\d+) min/',$reason,$mm)){ $rq=(int)($mm[2]?:$mm[4]); $reason=sprintf(t('Critical-tiden för kort (varade %s, regel #%d kräver %d min)','Critical time too short (lasted %s, rule #%d requires %d min)'),fmt_dur_s($dur),(int)($mm[1]?:$mm[3]),$rq); } }
  $pdo->prepare('UPDATE alert_events SET ended=NOW(),dur=?,reason=? WHERE id=?')->execute([$dur,substr($reason,0,255),$o['id']]); } }
// Systemregler (worker och Zabbix-lyssnare): gäller VM Monitor själv, inte någon enhet. Tillstånd sparas i settings (alert_state kräver en server).
// Körs av workern (via evaluate_rules), av Zabbix-lyssnaren och av webbläsarens poll, så att en död worker kan upptäckas av någon annan.
function eval_system_rules(){ $pdo=db(); try{ $rules=$pdo->query("SELECT * FROM alert_rules WHERE enabled=1 AND metric LIKE 'sys\\_%'")->fetchAll(); }catch(Throwable $e){ return; } if(!$rules) return;
 if(!$pdo->query("SELECT GET_LOCK('vmmon_sysrules',0)")->fetchColumn()) return;
 try{ $gp=setting('alerts_paused_until',''); $paused=$gp!==''&&(bool)$pdo->query('SELECT '.$pdo->quote($gp).'>NOW()')->fetchColumn();
  $host=parse_url(base_url(),PHP_URL_HOST)?:'localhost'; $S=['id'=>0,'name'=>'VM Monitor','host'=>$host,'is_services'=>0]; $now=time();
  foreach($rules as $r){ $key='sysrule_'.(int)$r['id']; $st=json_decode((string)setting($key,''),true)?:['since'=>null,'firing'=>0,'last'=>0];
   $bad=false; $info='';
   if($r['metric']==='sys_worker'){ $b=(int)setting('worker_beat',0); $age=$b>0?$now-$b:null; $bad=$age===null||$age>=60; $info=$age===null?t('Workern har aldrig startat.','The worker has never started.'):t('Workern sågs senast för ','The worker was last seen ').fmt_dur_s($age).t(' sedan.',' ago.'); }
   elseif($r['metric']==='sys_zbx'&&setting('zbx_active_on','0')==='1'){ $b=(int)setting('zbx_active_beat',0); $age=$b>0?$now-$b:null; $bad=$age===null||$age>=60; $info=$age===null?t('Lyssnaren har aldrig startat (sudo systemctl enable --now vmmon-zabbix).','The listener has never started (sudo systemctl enable --now vmmon-zabbix).'):t('Lyssnaren sågs senast för ','The listener was last seen ').fmt_dur_s($age).t(' sedan.',' ago.'); }
   elseif($r['metric']==='sys_recv'&&(setting('trap_on','1')==='1'||setting('syslog_on','1')==='1'||setting('syslog_tcp_on','0')==='1')){ $b=(int)setting('recv_beat',0); $age=$b>0?$now-$b:null; $bad=$age===null||$age>=60; $info=$age===null?t('Mottagaren har aldrig startat (sudo systemctl enable --now vmmon-recv).','The receiver has never started (sudo systemctl enable --now vmmon-recv).'):t('Mottagaren sågs senast för ','The receiver was last seen ').fmt_dur_s($age).t(' sedan (sudo systemctl status vmmon-recv).',' ago (sudo systemctl status vmmon-recv).'); }
   if($paused||!rule_in_schedule($r)){ if(!$st['firing']&&$st['since']!==null){ $st['since']=null; set_setting($key,json_encode($st)); } continue; }
   $ch=false;
   if($bad){ if($st['since']===null){ $st['since']=$now; $ch=true; } $age2=$now-(int)$st['since'];
    if(!$st['firing']&&$age2>=(int)$r['minutes']*60){ alert_fire($r,$S,'alarm',$info,['since'=>date('Y-m-d H:i:s',(int)$st['since']),'age'=>$age2]); $st['firing']=1; $st['last']=$now; $ch=true; }
    elseif($st['firing']&&(int)$r['repeat_min']>0&&$now-(int)$st['last']>=(int)$r['repeat_min']*60){ alert_fire($r,$S,'repeat',$info,['since'=>date('Y-m-d H:i:s',(int)$st['since']),'age'=>$age2]); $st['last']=$now; $ch=true; }
   } elseif($st['since']!==null||$st['firing']){ if($st['firing']) alert_fire($r,$S,'ok','',$st['since']!==null?['since'=>date('Y-m-d H:i:s',(int)$st['since']),'age'=>$now-(int)$st['since']]:[]); $st=['since'=>null,'firing'=>0,'last'=>0]; $ch=true; }
   if($ch) set_setting($key,json_encode($st)); }
 }catch(Throwable $e){ applog('error','alert',$e->getMessage()); }
 $pdo->query("SELECT RELEASE_LOCK('vmmon_sysrules')"); }
// Larmregel "trap/syslog-händelse matchar": larmar när minst N matchande händelser kommit in för enheten under de senaste X minuterna,
// och återställs när inga matchande händelser finns kvar i tidsfönstret. Inställningar (JSON i kolumnen kinds): src (any|trap|syslog), sev (0–7, "minst så allvarlig"), pat (text eller /regex/), win (minuter).
function log_rule_cfg($r){ $j=json_decode((string)($r['kinds']??''),true); if(!is_array($j)) $j=[]; $src=in_array($j['src']??'',['trap','syslog'],true)?$j['src']:'any'; return ['src'=>$src,'sev'=>max(0,min(7,(int)($j['sev']??7))),'pat'=>trim((string)($j['pat']??'')),'win'=>max(1,min(1440,(int)($j['win']??10))),'fa'=>trim((string)($j['fa']??'')),'fp'=>trim((string)($j['fp']??'')),'fs'=>trim((string)($j['fs']??'')),'fd'=>trim((string)($j['fd']??'')),'fdp'=>trim((string)($j['fdp']??'')),'grp'=>in_array($j['grp']??'',['src','dst'],true)?$j['grp']:'','cnt'=>in_array($j['cnt']??'',['dport','dst','src'],true)?$j['cnt']:'ev']; }
function log_rule_state($r,$sid){ require_once __DIR__.'/recv_lib.php'; $c=log_rule_cfg($r); $n=max(1,(int)$r['threshold']); if(!$sid) return [false,'']; $w=['server_id=?','ts>=?','sev<=?',"(prog IS NULL OR prog<>'vmmon')"]; $p=[$sid,date('Y-m-d H:i:s',time()-$c['win']*60),$c['sev']]; if($c['src']!=='any'){ $w[]='kind=?'; $p[]=$c['src']; } foreach(log_fw_where(['act'=>$c['fa'],'proto'=>$c['fp'],'src'=>$c['fs'],'dst'=>$c['fd'],'dport'=>$c['fdp']],$p) as $x) $w[]=$x;
 if($c['grp']!==''){ $col=$c['grp']==='src'?'fw_src':'fw_dst'; $ex=['ev'=>'COUNT(*)','dport'=>'COUNT(DISTINCT fw_dport)','dst'=>'COUNT(DISTINCT fw_dst)','src'=>'COUNT(DISTINCT fw_src)'][$c['cnt']]; $w[]="$col IS NOT NULL";
  try{ $q=db()->prepare("SELECT $col g,COUNT(*) ev,COUNT(DISTINCT fw_dport) dp,COUNT(DISTINCT fw_dst) dd,COUNT(DISTINCT fw_src) ds,$ex m FROM log_events WHERE ".implode(' AND ',$w)." GROUP BY $col HAVING $ex>=".(int)$n." ORDER BY m DESC LIMIT 5"); $q->execute($p); $g=$q->fetchAll(); }catch(Throwable $e){ return [false,'']; }
  if(!$g) return [false,'']; $what=['ev'=>t('händelser','events'),'dport'=>t('olika målportar','distinct destination ports'),'dst'=>t('olika mål-IP','distinct destination IPs'),'src'=>t('olika käll-IP','distinct source IPs')][$c['cnt']];
  $o=t('Samma ','Same ').($c['grp']==='src'?t('käll-IP','source IP'):t('mål-IP','destination IP')).': '.t('minst','at least').' '.$n.' '.$what.' '.t('senaste','in the last').' '.$c['win']." min:\n"; foreach($g as $x) $o.=$x['g'].': '.(int)$x['m'].' '.$what.($c['cnt']==='ev'?'':' ('.(int)$x['ev'].' '.t('händelser','events').')')."\n"; return [true,rtrim($o)]; }
 try{ $q=db()->prepare('SELECT ts,kind,sev,host,prog,msg FROM log_events WHERE '.implode(' AND ',$w).' ORDER BY id DESC LIMIT 300'); $q->execute($p); $rows=$q->fetchAll(); }catch(Throwable $e){ return [false,'']; }
 $hit=[]; foreach($rows as $x){ if(log_match($c['pat'],$x['msg'].' '.($x['host']??'').' '.($x['prog']??''))) $hit[]=$x; }
 if(count($hit)<$n) return [false,'']; $o=count($hit).' '.t('händelser senaste','events in the last').' '.$c['win'].' min'.($c['pat']!==''?' ('.$c['pat'].')':'').":\n"; foreach(array_slice($hit,0,5) as $x) $o.='['.$x['ts'].' '.($x['kind']==='trap'?'trap':'syslog').'] '.($x['prog']?$x['prog'].': ':'').mb_substr($x['msg'],0,220)."\n"; return [true,rtrim($o)]; }
function evaluate_rules(){ $pdo=db(); eval_system_rules();
 $gp=setting('alerts_paused_until',''); if($gp!==''&&(bool)$pdo->query('SELECT '.$pdo->quote($gp).'>NOW()')->fetchColumn()){ $pdo->query('UPDATE alert_state SET since=NULL WHERE firing=0'); return; }
 $rules=$pdo->query('SELECT * FROM alert_rules WHERE enabled=1')->fetchAll(); if(!$rules) return;
 $TG=[]; $needNet=false; foreach($rules as $r) if(strpos($r['metric'],'net_')===0) $needNet=true;
 $chk=$pdo->prepare('SELECT * FROM server_checks WHERE server_id=? AND enabled=1'); $nif=$pdo->prepare('SELECT * FROM net_if WHERE server_id=?');
 $q=$pdo->prepare('SELECT *,TIMESTAMPDIFF(SECOND,since,NOW()) age,TIMESTAMPDIFF(SECOND,last_sent,NOW()) sage FROM alert_state WHERE rule_id=? AND server_id=?');
 foreach($pdo->query('SELECT *,(maint_until IS NOT NULL AND maint_until>NOW()) AS maint FROM servers WHERE enabled=1')->fetchAll() as $s){
  if($s['maint']){ $pdo->prepare('UPDATE alert_state SET since=NULL WHERE server_id=? AND firing=0')->execute([$s['id']]); continue; }
  $chk->execute([$s['id']]); $cs=$chk->fetchAll(); $ifs=[]; if($needNet){ $nif->execute([$s['id']]); $ifs=$nif->fetchAll(); }
  foreach($rules as $r){ if(strpos($r['metric'],'sys_')===0) continue; if(!rule_in_schedule($r)){ $pdo->prepare('UPDATE alert_state SET since=NULL WHERE rule_id=? AND server_id=? AND firing=0')->execute([$r['id'],$s['id']]); continue; } $tg=$TG[$r['id']]??=rule_targets($r); if(!rule_applies($tg,$r,$s,$cs)) continue; if($s['is_services']&&!in_array($r['metric'],['check','check_ms'],true)) continue; if(($mc=metric_cat($r['metric']))!==null&&!in_array(dev_cat($s),$mc,true)) continue;
   [$bad,$info]=rule_state($r,$cs,$ifs,$tg,$s['id'],$s); $k=[$r['id'],$s['id']];
   $q->execute($k); $st=$q->fetch(); if(!$st){ $pdo->prepare('INSERT INTO alert_state(rule_id,server_id) VALUES(?,?)')->execute($k); $st=['since'=>null,'firing'=>0,'age'=>null,'sage'=>null]; }
   if($bad){
    if($st['since']===null){ $pdo->prepare('UPDATE alert_state SET since=NOW() WHERE rule_id=? AND server_id=?')->execute($k); $st['age']=0; }
    if(!$st['firing']&&$st['age']>=($r['metric']==='log_event'?0:$r['minutes'])*60){ alert_fire($r,$s,'alarm',$info,['since'=>date('Y-m-d H:i:s',time()-(int)$st['age']),'age'=>(int)$st['age']]); $pdo->prepare('UPDATE alert_state SET firing=1,last_sent=NOW() WHERE rule_id=? AND server_id=?')->execute($k); }
    elseif($st['firing']&&$r['repeat_min']>0&&$st['sage']!==null&&$st['sage']>=$r['repeat_min']*60){ alert_fire($r,$s,'repeat',$info,['since'=>date('Y-m-d H:i:s',time()-(int)$st['age']),'age'=>(int)$st['age']]); $pdo->prepare('UPDATE alert_state SET last_sent=NOW() WHERE rule_id=? AND server_id=?')->execute($k); }
   } elseif($st['since']!==null||$st['firing']){ if($st['firing']) alert_fire($r,$s,'ok','',$st['since']!==null?['since'=>date('Y-m-d H:i:s',time()-(int)$st['age']),'age'=>(int)$st['age']]:[]); $pdo->prepare('UPDATE alert_state SET since=NULL,firing=0 WHERE rule_id=? AND server_id=?')->execute($k); } } } }

// ===== Docker-kontroller (Docker Engine API eller Zabbix agent 2) =====
function dk_norm($list){ $o=[]; if(!is_array($list)) return $o; if(isset($list['data'])&&is_array($list['data'])) $list=$list['data'];
 foreach($list as $c){ if(!is_array($c)) continue; $g=fn($k)=>$c[$k]??$c[lcfirst($k)]??null; $nm=$g('Names'); $n=is_array($nm)?(string)($nm[0]??''):(string)($nm??$g('Name')??''); $n=ltrim($n,'/'); if($n==='') $n=substr((string)$g('Id'),0,12);
  $state=strtolower((string)$g('State')); $status=(string)$g('Status'); $health=''; if(preg_match('/\((healthy|unhealthy|health: starting)\)/i',$status,$m)) $health=strtolower($m[1])==='health: starting'?'starting':strtolower($m[1]); $ex=preg_match('/Exited \((-?\d+)\)/',$status,$m)?(int)$m[1]:null;
  $pt=[]; $pl=$g('Ports'); if(is_array($pl)) foreach($pl as $pp){ if(!is_array($pp)) continue; $pv=(int)($pp['PrivatePort']??$pp['privatePort']??0); $pb=(int)($pp['PublicPort']??$pp['publicPort']??0); $ty=(string)($pp['Type']??$pp['type']??'tcp'); if(!$pv) continue; $x=($pb?$pb.'→':'').$pv.'/'.$ty; $pt[$x]=$pb?0:1; } ksort($pt,SORT_NATURAL); $pt=array_slice(array_keys($pt),0,12);
  $o[]=['id'=>substr((string)$g('Id'),0,12),'n'=>$n,'img'=>(string)$g('Image'),'state'=>$state,'status'=>$status,'health'=>$health,'exit'=>$ex,'ports'=>$pt]; }
 return $o; }
function dk_fetch($h,$cn){ $info=[];
 if($cn['mode']==='zabbix'){ $p=(int)($cn['port']??10050)?:10050; error_clear_last(); $raw=zbx($h,$p,'docker.containers'); if($raw===null) return ['ok'=>false,'err'=>lasterr()?:t('ingen respons från Zabbix agent (finns Docker-pluginet i agent 2?)','no response from Zabbix agent (is the Docker plugin in agent 2 available?)')];
  $list=json_decode($raw,true); if(!is_array($list)) return ['ok'=>false,'err'=>t('Oväntat svar från docker.containers: ','Unexpected docker.containers reply: ').substr($raw,0,120)];
  $ij=json_decode((string)zbx($h,$p,'docker.info'),true); if(is_array($ij)) $info=$ij; $cl=dk_norm($list); }
 else { $sc=($cn['scheme']??'http')==='https'?'https':'http'; $p=(int)($cn['port']??2375)?:2375; $base="$sc://$h:$p"; $v=!empty($cn['verify']);
  [$lj,$le]=http_json($base.'/containers/json?all=1',[],$v,8); if($le!==null) return ['ok'=>false,'err'=>"Docker API $h:$p – $le"]; $cl=dk_norm($lj);
  [$ij]=http_json($base.'/info',[],$v,6); if(is_array($ij)) $info=$ij; }
 return ['ok'=>true,'ver'=>(string)($info['ServerVersion']??$info['server_version']??''),'images'=>$info['Images']??null,'containers'=>array_slice($cl,0,200)]; }
// Alla containrar måste köra (och inte vara ohälsosamma) utom de som är dolda/ignorerade.
function dk_eval($cl,$ign){ $bad=[]; $run=0;$stop=0;$pau=0;
 foreach($cl as $c){ $st=$c['state']; if($st==='running') $run++; elseif($st==='paused') $pau++; else $stop++; if(in_array($c['n'],$ign,true)) continue; $why=null;
  if($st!=='running') $why=$st?:'?'; elseif(($c['health']??'')==='unhealthy') $why='unhealthy';
  if($why!==null) $bad[]=$c['n'].' ('.$why.')'; }
 return ['run'=>$run,'stop'=>$stop,'paused'=>$pau,'bad'=>$bad]; }
function dk_ign($c){ return array_values(array_filter(array_map('trim',explode(',',(string)($c['param']??''))))); }
function dk_check_run($c,$h){
 if($c['source']==='zabbix') $cn=['mode'=>'zabbix','port'=>10050];
 else { $cn=['mode'=>'api','port'=>2375,'scheme'=>'http']; if(preg_match('/^(?:(https?)[:\/]*)?(\d+)$/i',trim((string)$c['target']),$m)){ $cn['port']=(int)$m[2]; if($m[1]) $cn['scheme']=strtolower($m[1]); } }
 $ign=dk_ign($c); $r=dk_fetch($h,$cn); if(!$r['ok']) return [false,$r['err']];
 $e=dk_eval($r['containers'],$ign); $dt=$e['run'].' '.t('körs','running').' · '.$e['stop'].' '.t('stoppade','stopped').($e['paused']?' · '.$e['paused'].' '.t('pausade','paused'):'');
 return [!$e['bad'],$dt.($e['bad']?' – '.implode(', ',array_slice($e['bad'],0,6)).(count($e['bad'])>6?'…':''):''),null,['docker'=>['ver'=>$r['ver'],'images'=>$r['images'],'run'=>$e['run'],'stop'=>$e['stop'],'paused'=>$e['paused'],'containers'=>$r['containers']]]]; }
// Puls från bakgrundsprocessen. Anropas även mellan enskilda kontroller så att en lång pollningsrunda (t.ex. många SNMP-timeouts) inte ser ut som att processen stoppat.
function wbeat(){ static $l=0; if(empty($GLOBALS['vm_worker'])||time()-$l<5) return; $l=time(); try{ set_setting('worker_beat',time()); }catch(Throwable $e){} }
// ---- Pollning. Varje objekt (kontroll, host, nätverksenhet) låses med GET_LOCK medan det pollas, så flera pollare (worker.php startar dem automatiskt) kan köra samtidigt utan att ta samma objekt.
function poll_claim($n){ $q=db()->prepare('SELECT GET_LOCK(?,0)'); $q->execute([$n]); return (bool)$q->fetchColumn(); }
function poll_release($n){ try{ $q=db()->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$n]); }catch(Throwable $e){} }
// Nedade objekt (utom ping/TCP) pollas glesare: 3 × intervallet, högst 300 s men aldrig under intervallet.
function poll_backoff($iv,$down){ return $down?max($iv,min($iv*3,300)):$iv; }
function nw_due($ns,$in){ $iv=(int)((json_decode((string)$ns['nw_conn'],true)?:[])['iv']??0); $iv=$iv>=10?$iv:$in; $iv=poll_backoff($iv,$ns['nw_status']==='down'); return $ns['nw_poll']===null||(int)$ns['nw_age']>=$iv; }
function hv_due($hs,$ih){ $iv=poll_backoff($ih,($hs['hv_status']??'')==='down'); return $hs['hv_poll']===null||(int)$hs['hv_age']>=$iv; }
function poll_due(){ $pdo=db(); set_time_limit(180); $t0=microtime(true); $nc=0; $nh=0; $slow=[]; $GLOBALS['force_lang']=setting('sys_lang','sv');
 $a=max(10,(int)setting('int_tcp',30));$b=max(10,(int)setting('int_url',60));$c=max(10,(int)setting('int_snmp',60));$d=max(10,(int)setting('int_zabbix',60));
 $base="COALESCE(c.interval_sec,CASE c.source WHEN 'tcp' THEN $a WHEN 'url' THEN $b WHEN 'snmp' THEN $c ELSE $d END)";
 $due="c.last_check IS NULL OR TIMESTAMPDIFF(SECOND,c.last_check,NOW())>=IF(c.last_status='down' AND c.source<>'tcp',GREATEST($base,LEAST($base*3,300)),$base)";
 $sel="SELECT c.*,IF(s.is_services=1,c.target,s.host) AS host FROM server_checks c JOIN servers s ON s.id=c.server_id WHERE c.enabled=1 AND s.enabled=1 AND ($due)";
 $cks=$pdo->query($sel)->fetchAll(); shuffle($cks); $pings=[]; $held=[];
 $re=$pdo->prepare($sel.' AND c.id=?');
 foreach($cks as $ck){ $ln='vm_ck_'.$ck['id']; if(!poll_claim($ln)) continue; $re->execute([$ck['id']]); $fr=$re->fetch(); if(!$fr){ poll_release($ln); continue; }
  if($fr['source']==='tcp'&&$fr['kind']==='ping'){ $pings[]=$fr; $held[]=$ln; continue; }
  wbeat(); $t1=microtime(true); if($fr['source']==='snmp'&&$fr['last_status']==='down') $GLOBALS['snmp_fast']=1; try{ store_result($fr,exec_check($fr)); }finally{ unset($GLOBALS['snmp_fast']); poll_release($ln); } $nc++; $ms=round((microtime(true)-$t1)*1000); if($ms>=1500) $slow[]=($fr['label']?:$fr['kind']).' @ '.$fr['host'].' '.$ms.' ms'; }
 if($pings){ try{ wbeat(); $rs=ping_many($pings); foreach($pings as $pk){ store_result($pk,$rs[$pk['id']]); $nc++; } }finally{ foreach($held as $ln) poll_release($ln); } }
 $ih=max(10,(int)setting('int_hosts',60)); $hsel="SELECT *,TIMESTAMPDIFF(SECOND,hv_poll,NOW()) AS hv_age FROM servers WHERE hv_type IS NOT NULL AND enabled=1";
 $hq=$pdo->prepare($hsel.' AND id=?'); $hl=$pdo->query($hsel)->fetchAll(); shuffle($hl);
 foreach($hl as $hs){ if(!hv_due($hs,$ih)) continue; $ln='vm_hv_'.$hs['id']; if(!poll_claim($ln)) continue; try{ $hq->execute([$hs['id']]); $fr=$hq->fetch(); if(!$fr||!hv_due($fr,$ih)) continue; wbeat(); $t1=microtime(true); if(($fr['hv_status']??'')==='down') $GLOBALS['snmp_fast']=1; try{ poll_host($fr); }finally{ unset($GLOBALS['snmp_fast']); } $nh++; $ms=round((microtime(true)-$t1)*1000); if($ms>=1500) $slow[]=$fr['name'].' '.$ms.' ms'; }finally{ poll_release($ln); } }
 $in=max(10,(int)setting('int_snmp',60)); $nsel="SELECT *,TIMESTAMPDIFF(SECOND,nw_poll,NOW()) AS nw_age FROM servers WHERE nw_type IS NOT NULL AND enabled=1";
 $nq=$pdo->prepare($nsel.' AND id=?'); $nl=$pdo->query($nsel)->fetchAll(); shuffle($nl);
 foreach($nl as $ns){ if(!nw_due($ns,$in)) continue; $ln='vm_nw_'.$ns['id']; if(!poll_claim($ln)) continue; try{ $nq->execute([$ns['id']]); $fr=$nq->fetch(); if(!$fr||!nw_due($fr,$in)) continue; wbeat(); $t1=microtime(true); if($fr['nw_status']==='down') $GLOBALS['snmp_fast']=1; try{ poll_network($fr); }finally{ unset($GLOBALS['snmp_fast']); } $nh++; $ms=round((microtime(true)-$t1)*1000); if($ms>=1500) $slow[]=$fr['name'].' '.$ms.' ms'; }finally{ poll_release($ln); } }
 $ms=round((microtime(true)-$t0)*1000); if($nc||$nh) applog('debug','poll',"$nc checks, $nh hosts/network – $ms ms".($slow?' – '.t('långsammast: ','slowest: ').implode('; ',array_slice($slow,0,5)):''));
 unset($GLOBALS['force_lang']); return ['checks'=>$nc,'hosts'=>$nh,'ms'=>$ms,'slow'=>$slow]; }
// Larm, händelser och städning. Körs av en process i taget (lås), vanligen worker-supervisorn.
function poll_post(){ $pdo=db(); if(!poll_claim('vmmon_post')) return; try{ $GLOBALS['force_lang']=setting('sys_lang','sv'); evaluate_rules(); try{ track_events(); }catch(Throwable $e){ applog('error','events',$e->getMessage()); } log_summary();
 if(rand(1,50)===1){ $n=max(1,(int)setting('retention_days',30)); $ln=max(1,(int)setting('log_retention_days',90)); $pdo->query("DELETE FROM check_hist WHERE hr<NOW()-INTERVAL $n DAY"); $pdo->query("DELETE FROM alert_log WHERE ts<NOW()-INTERVAL $n DAY"); $pdo->query("DELETE FROM load_hist WHERE bk<NOW()-INTERVAL $n DAY"); $pdo->query("DELETE FROM net_hist WHERE bk<NOW()-INTERVAL $n DAY");
 try{ $pdo->query("DELETE FROM disk_hist WHERE bk<NOW()-INTERVAL $n DAY"); }catch(Throwable $e){} try{ $pdo->query("DELETE FROM check_ms_hist WHERE bk<NOW()-INTERVAL $n DAY"); }catch(Throwable $e){} try{ $pdo->query("DELETE FROM check_events WHERE ts<NOW()-INTERVAL $n DAY"); $pdo->query("DELETE FROM alert_events WHERE started<NOW()-INTERVAL $n DAY AND ended IS NOT NULL"); $pdo->query("DELETE FROM app_log WHERE ts<NOW()-INTERVAL $ln DAY"); }catch(Throwable $e){} try{ $pdo->query("DELETE FROM login_fail WHERE ts<NOW()-INTERVAL $n DAY"); $pdo->query("DELETE FROM trusted_devices WHERE expires<NOW()"); $pdo->query("UPDATE users SET pending_email=NULL,email_token=NULL,email_exp=NULL WHERE email_exp<NOW()"); }catch(Throwable $e){} }
 }finally{ unset($GLOBALS['force_lang']); poll_release('vmmon_post'); } }
// Allt i en process (webbfallback när ingen worker kör, och worker.php --once).
function poll_all(){ $r=poll_due(); poll_post(); return $r; }
// ---- delade UI-hjälpare
// Per-användar-inställningar (t.ex. kolumner i tabeller). Tabellen user_prefs skapas av sql/3.09.sql.
function lay_n($x=null){ $n=(int)($x??user_pref('lay')); return $n>=1&&$n<=3?$n:1; }
function user_pref($k){ try{ $q=db()->prepare('SELECT v FROM user_prefs WHERE user_id=? AND k=?'); $q->execute([(int)($_SESSION['u']['id']??0),$k]); $v=$q->fetchColumn(); return $v===false?'':(string)$v; }catch(Throwable $e){ return ''; } }
function tc_attr($key){ return ' data-tc="'.h($key).'" data-t="'.h(csrf()).'" data-cfg="'.h(user_pref('cols:'.$key)).'"'; }
function tc_th($k,$label,$x=true,$cls=''){ $c=trim(($x?'tx ':'').$cls); return '<th data-k="'.$k.'"'.($c!==''?' class="'.$c.'"':'').'>'.h($label).'</th>'; }
function tc_upt($s){ $s=(int)$s; if($s<=0) return '–'; $d=floor($s/86400); return ($d?$d.'d ':'').floor($s%86400/3600).'h '.floor($s%3600/60).'m'; }
function tc_ifs_by_server(){ $o=[]; try{ foreach(db()->query('SELECT server_id,if_index,name,descr,oper,admin,rate_in,rate_out,err_pm,disc_pm FROM net_if') as $r) $o[$r['server_id']][]=$r; }catch(Throwable $e){} return $o; }
// Mest trafikerade gränssnitt (in+ut) som cell
function tc_topif($ifs){ $b=null; $bv=-1; foreach($ifs as $i){ if((int)$i['oper']!==1) continue; $v=(float)$i['rate_in']+(float)$i['rate_out']; if($v>$bv){ $bv=$v; $b=$i; } } if(!$b) return '<td class="mut">–</td>'; return '<td data-sort="'.(int)$bv.'">'.h(if_key($b)).' <small class="mut">'.h(fmt_bps($bv)).'</small></td>'; }
function tc_td($v,$sort=null){ $v=($v===null||$v==='')?'–':$v; return '<td'.($sort!==null?' data-sort="'.h($sort).'"':'').'>'.h($v).'</td>'; }
function if_key($nf){ return (string)(($nf['name']??'')!==''?$nf['name']:($nf['descr']??'')); }
function nw_hid($srv){ $l=json_decode((string)($srv['nw_hidden']??''),true); return is_array($l)?$l:[]; }
function nw_post($id){ if(($_POST['a']??'')!=='toggle_sensor') return; $k=(string)($_POST['key']??''); if($k==='') return; $q=db()->prepare('SELECT nw_hidden FROM servers WHERE id=?'); $q->execute([$id]); $l=json_decode((string)$q->fetchColumn(),true); if(!is_array($l)) $l=[]; $i=array_search($k,$l,true); if($i===false) $l[]=$k; else array_splice($l,$i,1); db()->prepare('UPDATE servers SET nw_hidden=? WHERE id=?')->execute([json_encode(array_values($l)),$id]); }
function nw_btn($id,$srv,$key){ if(!is_admin()) return ''; $hd=in_array($key,nw_hid($srv),true); return fbtn('toggle_sensor',$id,$hd?t('Visa på dash','Show on dash'):t('Dölj från dash','Hide on dash'),$hd?'':'danger','','<input type="hidden" name="key" value="'.h($key).'">'); }
function nw_vis($d,$srv,$k){ $h=nw_hid($srv); return array_values(array_filter($d[$k]??[],fn($x)=>!in_array($x['n'],$h,true))); }
function if_shown($srv){ $l=json_decode((string)($srv['if_show']??''),true); return is_array($l)?$l:[]; }
function if_post($id){ if(($_POST['a']??'')!=='toggle_if') return; $k=(string)($_POST['key']??''); if($k==='') return; $q=db()->prepare('SELECT if_show FROM servers WHERE id=?'); $q->execute([$id]); $l=json_decode((string)$q->fetchColumn(),true); if(!is_array($l)) $l=[]; $i=array_search($k,$l,true); if($i===false) $l[]=$k; else array_splice($l,$i,1); db()->prepare('UPDATE servers SET if_show=? WHERE id=?')->execute([json_encode(array_values($l)),$id]); }
function if_btn($id,$srv,$key){ if(!is_admin()) return ''; $on=in_array($key,if_shown($srv),true); return fbtn('toggle_if',$id,$on?t('Dölj från dash','Hide on dash'):t('Visa på dash','Show on dash'),$on?'danger':'','','<input type="hidden" name="key" value="'.h($key).'">'); }
function fbtn($a,$cid,$txt,$cls='',$conf='',$extra=''){return '<form method="post"'.($conf?' onsubmit="return confirm(\''.$conf.'\')"':'').'>'.csrf_field().'<input type="hidden" name="a" value="'.$a.'"><input type="hidden" name="cid" value="'.$cid.'">'.$extra.'<button class="btn sm '.$cls.'">'.$txt.'</button></form>';}
function ksel($name,$tgt,$opts,$cur=''){ $o=''; $gr=null; foreach($opts as $k=>$v){ if(isset($v[4])&&$v[4]!==$gr){ if($gr!==null) $o.='</optgroup>'; $gr=$v[4]; $o.='<optgroup label="'.h($gr).'">'; } $o.='<option value="'.h($k).'" data-t="'.h($v[1]).'"'.($k===$cur?' selected':'').'>'.h($v[0]).'</option>'; } if($gr!==null) $o.='</optgroup>'; return '<select name="'.$name.'" class="ks" data-for="'.$tgt.'">'.$o.'</select>'; }
function ev($ed,$src,$f,$def=''){ return $ed&&$ed['source']===$src?h($ed[$f]):$def; }
function pct($u,$t){ return $t>0?number_format($u/$t*100,2).'%':'–'; }
function chk_type($c,$cat){ $k=$c['kind']; if($c['source']==='tcp'&&$k==='custom') return 'TCP '.$c['param']; if($c['source']==='zabbix'&&in_array($k,['net_tcp_listen','net_tcp_port','net_tcp_service','net_tcp_perf'],true)&&(string)$c['target']!=='') return 'Zabbix TCP '.$c['target']; return $cat[$c['source']][$k][0]??$k; }
function chk_short($c,$cat){ $k=$c['kind']; $src=$c['source'];
 if($src==='tcp'){ if($k==='ping') return 'ICMP'; if($k==='custom') return 'TCP'; $d=tcp_defs(); if(isset($d[$k])) return preg_replace('/\s*\(.*\)\s*$/','',$d[$k][0]); }
 if($src==='url'){ if($k==='ssl') return 'Cert'; if($k==='contains') return t('Text','Text'); return preg_match('~^https://~i',(string)$c['target'])?'HTTPS':'HTTP'; }
 return trim(preg_replace('/\s*\([^)]*\)\s*$/','',chk_type($c,$cat))); }
function fmt_iv($s){ $s=(int)$s; if($s>=3600&&$s%3600===0) return ($s/3600).' h'; if($s>=60&&$s%60===0) return ($s/60).' min'; return $s.' s'; }
function iv_post($n='iv'){ $v=str_replace(',','.',trim((string)($_POST[$n]??''))); if($v===''||!is_numeric($v)) return null; $m=['s'=>1,'m'=>60,'h'=>3600][$_POST[$n.'_u']??'s']??1; return min(604800,max(10,(int)round((float)$v*$m))); }
function iv_field($sec,$fn='iv'){ $u='m'; $v=''; if($sec){ $sec=(int)$sec; if($sec%3600===0){ $u='h'; $v=$sec/3600; } elseif($sec%60===0){ $u='m'; $v=$sec/60; } else { $u='s'; $v=$sec; } }
 $o=''; foreach(['s'=>t('sekunder','seconds'),'m'=>t('minuter','minutes'),'h'=>t('timmar','hours')] as $k=>$nn) $o.='<option value="'.$k.'"'.($k===$u?' selected':'').'>'.h($nn).'</option>';
 return '<span class="ivf"><input name="'.$fn.'" type="text" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" size="8" value="'.h((string)$v).'" placeholder="'.h(t('Intervall (standard)','Interval (default)')).'" title="'.h(t('Tomt = standardintervallet. Minst 10 sekunder, högst 7 dagar.','Empty = default interval. Minimum 10 seconds, maximum 7 days.')).'"><select name="'.$fn.'_u">'.$o.'</select></span>'; }
function fmt_bps($v){ if($v===null) return '–'; $v=(float)$v; return $v>=1e9?round($v/1e9,2).' Gb/s':($v>=1e6?round($v/1e6,1).' Mb/s':($v>=1e3?round($v/1e3).' kb/s':round($v).' b/s')); }
function services_server_id(){ $pdo=db(); $id=$pdo->query('SELECT id FROM servers WHERE is_services=1 LIMIT 1')->fetchColumn(); if(!$id){ $pdo->exec("INSERT INTO servers(name,host,is_services) VALUES('Service checks','',1)"); $id=$pdo->lastInsertId(); } return (int)$id; }
// ---- Städning: ta bort borttagna servrar/checks ur larmreglernas mål (regler som blir utan mål tas bort, annars skulle de börja gälla "alla")
function purge_targets($sids=[],$cids=[]){ $pdo=db();
 foreach($pdo->query('SELECT id,targets FROM alert_rules WHERE targets IS NOT NULL')->fetchAll() as $r){ $j=json_decode($r['targets'],true); if(!is_array($j)) continue; $n=[];
  foreach($j as $x){ if(isset($x['s'])&&in_array((int)$x['s'],$sids,true)) continue; if(isset($x['c'])&&in_array((int)$x['c'],$cids,true)) continue;
   foreach(['i','v'] as $k) if(isset($x[$k])&&in_array((int)explode(':',(string)$x[$k])[0],$sids,true)) continue 2; $n[]=$x; }
  if(count($n)===count($j)) continue; if(!$n) $pdo->prepare('DELETE FROM alert_rules WHERE id=?')->execute([$r['id']]); else $pdo->prepare('UPDATE alert_rules SET targets=? WHERE id=?')->execute([json_encode($n),$r['id']]); } }
// ---- Hosts (Proxmox via API, VMware via SNMP, Hyper-V via Zabbix-agent/WMI)
function http_json($url,$headers,$verify,$to=12){ $c=curl_init($url); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>$to,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>$verify?1:0,CURLOPT_SSL_VERIFYHOST=>$verify?2:0]);
 $r=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); $e=curl_error($c); curl_close($c); if($r===false) return [null,$e?:'no response']; $j=json_decode($r,true);
 if($code<200||$code>=300) return [null,"HTTP $code".($code===401?' (token/behörighet)':'').(is_array($j)&&!empty($j['errors'])?' '.json_encode($j['errors'],JSON_UNESCAPED_UNICODE):'')]; return [$j,null]; }
function pve_get($s,$cn,$path,$to=12){ return http_json('https://'.$s['host'].':'.(((int)($cn['port']??8006))?:8006).'/api2/json'.$path,['Authorization: PVEAPIToken='.($cn['token']??'').'='.secret_dec($cn['secret']??'')],!empty($cn['verify']),$to); }
function pbs_conn($sid){ $q=db()->prepare('SELECT pbs_conn FROM servers WHERE id=?'); $q->execute([(int)$sid]); return json_decode((string)$q->fetchColumn(),true)?:[]; }
function pbs_save_conn($sid){ $o=pbs_conn($sid); $c=$o; $tk=trim($_POST['pbs_token']??''); if($tk!=='') $c['token']=$tk; $sec=$_POST['pbs_secret']??''; if($sec!==''&&$sec!=='********') $c['secret']=secret_enc($sec);
 $pt=(int)preg_replace('/\D/','',$_POST['pbs_port']??''); $c['port']=$pt?:($o['port']??8007); $c['verify']=!empty($_POST['pbs_verify'])?1:0; db()->prepare('UPDATE servers SET pbs_conn=? WHERE id=?')->execute([json_encode($c),(int)$sid]); }
function pbs_get($h,$cn,$path,$to=12){ return http_json('https://'.$h.':'.(((int)($cn['port']??8007))?:8007).'/api2/json'.$path,['Authorization: PBSAPIToken='.($cn['token']??'').':'.secret_dec($cn['secret']??'')],!empty($cn['verify']),$to); }
// Proxmox Backup Server via API-token (roll Audit på / för användare och token): hämtar allt i ett anrop – CPU, minne, datastores, jobb och senaste körning
function pbs_run($c,$h){ $cn=pbs_conn($c['server_id']); $hrs=(int)$c['param']; $hrs=$hrs>0?$hrs:48;
 if(empty($cn['token'])||empty($cn['secret'])) return [false,t('PBS API-token saknas – fyll i token och secret i kontrollen','PBS API token missing – enter token and secret in the check')];
 [$j,$e]=pbs_get($h,$cn,'/nodes/localhost/status'); if(!$j) return [false,$e]; $d=$j['data']??[];
 $cpu=isset($d['cpu'])?round((float)$d['cpu']*100,1):null; $mt=(float)($d['memory']['total']??0); $mu=(float)($d['memory']['used']??0); $mem=$mt>0?round($mu/$mt*100,1):null;
 [$u]=pbs_get($h,$cn,'/status/datastore-usage'); $disks=[]; $st=[]; foreach($u['data']??[] as $x){ if(empty($x['store'])) continue; $st[]=(string)$x['store']; $tt=(float)($x['total']??0); if($tt>0) $disks[]=['n'=>(string)$x['store'],'full'=>(string)$x['store'],'t'=>$tt,'f'=>(float)($x['avail']??0)]; }
 [$v]=pbs_get($h,$cn,'/version',6); $ver=$v['data']['version']??'';
 // jobb: senaste körningen per typ och datastore (7 dygn bakåt)
 [$tj]=pbs_get($h,$cn,'/nodes/localhost/tasks?limit=500&since='.(time()-7*86400)); $want=['backup','prune','prunejob','verificationjob','verify','garbage_collection','syncjob','sync']; $ord=array_flip($want); $last=[]; $fail24=0;
 foreach($tj['data']??[] as $x){ $ty=(string)($x['worker_type']??''); if(!isset($ord[$ty])) continue; $sto=explode(':',(string)($x['worker_id']??''))[0]; $k=$ty.'|'.$sto; $s0=$x['starttime']??0; $stt=$x['status']??null;
  $cl=empty($x['endtime'])&&$stt===null?'run':(($stt==='OK')?'ok':(strpos((string)$stt,'WARNINGS')===0?'warn':'fail'));
  if($cl==='fail'&&$s0>time()-86400) $fail24++;
  if(!isset($last[$k])||$s0>$last[$k]['s']) $last[$k]=['t'=>$ty,'id'=>$sto,'s'=>(int)$s0,'e'=>(int)($x['endtime']??$s0),'c'=>$cl,'m'=>$cl==='fail'?substr((string)$stt,0,80):'']; }
 uasort($last,fn($a,$b)=>[$ord[$a['t']],$a['id']]<=>[$ord[$b['t']],$b['id']]); $jobs=array_slice(array_values($last),0,10);
 // senaste backup per datastore
 $stores=[]; $old=[]; foreach($st as $s1){ [$g]=pbs_get($h,$cn,'/admin/datastore/'.rawurlencode($s1).'/groups'); if(!$g) continue; $lb=0; foreach($g['data']??[] as $x) $lb=max($lb,(int)($x['last-backup']??0)); $stores[]=['n'=>$s1,'e'=>$lb]; if(!$lb||(time()-$lb)/3600>$hrs) $old[]=$s1; }
 [$dl,$de2]=pbs_get($h,$cn,'/nodes/localhost/disks/list',10); $sme=$dl?(empty($dl['data'])?t('PBS returnerade inga diskar','PBS returned no disks'):''):(string)$de2; $sm=smart_api($dl['data']??[],fn($dv,$nm)=>pbs_get($h,$cn,'/nodes/localhost/disks/smart?disk='.rawurlencode($nm),6)[0]);
 $bad=[]; foreach($jobs as $x) if($x['c']==='fail') $bad[]=$x['t'].' '.$x['id']; $ok=!$bad&&!$old;
 $dt=[]; if($cpu!==null)$dt[]='CPU '.$cpu.'%'; if($mem!==null)$dt[]=t('Minne ','Mem ').$mem.'%'; $dt[]=count($st).' datastore'.(count($st)===1?'':'s'); if($ver)$dt[]='PBS '.$ver;
 if($bad) $dt[]=t('senaste körning misslyckades: ','latest run failed: ').implode(', ',array_slice($bad,0,3)); if($old) $dt[]=t("ingen backup senaste $hrs h: ","no backup in $hrs h: ").implode(', ',$old);
 return [$ok,implode(' · ',$dt),null,['cpu'=>$cpu,'cores'=>null,'mem'=>$mem,'mem_t'=>$mt?:null,'mem_u'=>$mu,'disks'=>$disks,'pbs'=>['smart'=>$sm,'smart_err'=>$sme,'ver'=>$ver,'jobs'=>$jobs,'stores'=>$stores,'fail24'=>$fail24,'hrs'=>$hrs]]]; }
function poll_proxmox($s,$cn){ [$j,$e]=pve_get($s,$cn,'/cluster/resources'); if(!$j) return ['ok'=>false,'error'=>$e]; [$v]=pve_get($s,$cn,'/version'); $nodes=[]; $vms=[]; $st=[];
 foreach($j['data']??[] as $x){ $ty=$x['type']??'';
  if($ty==='node') $nodes[]=['n'=>$x['node']??($x['id']??'?'),'cpu'=>round(($x['cpu']??0)*100,1),'mem'=>(float)($x['mem']??0),'mem_t'=>(float)($x['maxmem']??0),'up'=>(int)($x['uptime']??0),'on'=>($x['status']??'')==='online'];
  elseif($ty==='qemu'||$ty==='lxc'){ if(!empty($x['template'])) continue; $vms[]=['k'=>(string)($x['vmid']??$x['id']??''),'n'=>$x['name']??('vm'.($x['vmid']??'')),'id'=>(string)($x['vmid']??''),'node'=>$x['node']??'','kind'=>$ty==='lxc'?'ct':'vm','state'=>$x['status']??'','cpu'=>round(($x['cpu']??0)*100,1),'mem'=>(float)($x['mem']??0),'mem_t'=>(float)($x['maxmem']??0),'disk'=>(float)($x['disk']??0),'disk_t'=>(float)($x['maxdisk']??0),'up'=>(int)($x['uptime']??0),'netin'=>(float)($x['netin']??0),'netout'=>(float)($x['netout']??0)]; }
  elseif($ty==='storage'){ $key=!empty($x['shared'])?($x['storage']??''):(($x['node']??'').'/'.($x['storage']??'')); $st[$key]=['n'=>$x['storage']??'','node'=>$x['node']??'','used'=>(float)($x['disk']??0),'total'=>(float)($x['maxdisk']??0),'type'=>$x['plugintype']??'','ok'=>($x['status']??'')==='available']; } }
 // Generell info per nod (värdnamn = nodnamn): CPU-modell, kernel, load, swap, root-disk, version, IP. Fel här stoppar inte pollningen.
 $cl=null; [$cj]=pve_get($s,$cn,'/cluster/status',6); $ips=[];
 foreach($cj['data']??[] as $x){ if(($x['type']??'')==='cluster') $cl=['name'=>(string)($x['name']??''),'quorate'=>isset($x['quorate'])?(int)$x['quorate']:null]; elseif(($x['type']??'')==='node'&&!empty($x['ip'])) $ips[$x['name']??'']=(string)$x['ip']; }
 $tn=time(); foreach($nodes as &$nd){ if(isset($ips[$nd['n']])) $nd['ip']=$ips[$nd['n']]; if(!$nd['on']||time()-$tn>20) continue;
  [$nj]=pve_get($s,$cn,'/nodes/'.rawurlencode($nd['n']).'/status',6); $q=$nj['data']??null; if(!is_array($q)) continue;
  $ci=$q['cpuinfo']??[]; if(!empty($ci['model'])) $nd['model']=preg_replace('/\s+/',' ',trim((string)$ci['model'])); if(!empty($ci['sockets'])) $nd['sockets']=(int)$ci['sockets']; if(!empty($ci['cores'])) $nd['cores']=(int)$ci['cores']; if(!empty($ci['cpus'])) $nd['threads']=(int)$ci['cpus'];
  if(!empty($q['kversion'])&&preg_match('/^(\S+\s+\S+)/',(string)$q['kversion'],$mk)) $nd['kernel']=$mk[1];
  if(!empty($q['loadavg'])&&is_array($q['loadavg'])) $nd['load']=array_map('floatval',array_slice($q['loadavg'],0,3));
  if(isset($q['swap']['total'])&&(float)$q['swap']['total']>0){ $nd['swap']=(float)($q['swap']['used']??0); $nd['swap_t']=(float)$q['swap']['total']; }
  if(isset($q['rootfs']['total'])&&(float)$q['rootfs']['total']>0){ $nd['root']=(float)($q['rootfs']['used']??0); $nd['root_t']=(float)$q['rootfs']['total']; }
  if(!empty($q['pveversion'])&&preg_match('#/([^/]+)#',(string)$q['pveversion'],$mv)) $nd['pve']=$mv[1]; }
 unset($nd);
 // Nätverksgränssnitt per nod (/nodes/{n}/network) + nodens trafik (rrddata) + VM-nätkort (config net0..N). Fel här stoppar inte pollningen.
 $ifs=[]; $ifs_err=[]; $t1=time(); foreach($nodes as &$nd){ if(!$nd['on']||time()-$t1>20) continue; $np='/nodes/'.rawurlencode($nd['n']);
  [$wj,$werr]=pve_get($s,$cn,$np.'/network',6); if($werr) $ifs_err[$nd['n']]=$werr; foreach($wj['data']??[] as $x){ if(!is_array($x)||empty($x['iface'])) continue; $ad=(string)($x['cidr']??''); if($ad===''&&!empty($x['address'])) $ad=(string)$x['address'].(!empty($x['netmask'])?'/'.$x['netmask']:''); $ifs[]=['k'=>$nd['n'].'/'.$x['iface'],'n'=>(string)$x['iface'],'node'=>$nd['n'],'type'=>(string)($x['type']??''),'up'=>!empty($x['active']),'addr'=>$ad,'gw'=>(string)($x['gateway']??''),'ports'=>trim((string)($x['bridge_ports']??$x['slaves']??$x['ovs_ports']??'')),'mtu'=>(string)($x['mtu']??''),'vlan'=>(string)($x['vlan-id']??($x['bridge_vlan_aware']??0?('VLAN-aware'.(!empty($x['bridge_vids'])?' '.$x['bridge_vids']:'')):'')),'note'=>(string)($x['comments']??'')]; }
  [$rj]=pve_get($s,$cn,$np.'/rrddata?timeframe=hour&cf=AVERAGE',6); $rr=is_array($rj['data']??null)?array_reverse($rj['data']):[]; foreach($rr as $pt){ if(isset($pt['netin'])||isset($pt['netout'])){ $nd['bin']=round((float)($pt['netin']??0)*8); $nd['bout']=round((float)($pt['netout']??0)*8); break; } } }
 unset($nd);
 // SDN (Proxmox VE 7+/8): zoner, vnets och subnät. Kräver SDN.Audit. Fel stoppar inte pollningen.
 $sdn=['zones'=>[],'vnets'=>[]]; try{ [$zj]=pve_get($s,$cn,'/cluster/sdn/zones',6); [$vj]=pve_get($s,$cn,'/cluster/sdn/vnets',6); $zst=[]; foreach($nodes as $nd2){ if(empty($nd2['on'])) continue; [$zs]=pve_get($s,$cn,'/nodes/'.rawurlencode($nd2['n']).'/sdn/zones',5); foreach($zs['data']??[] as $z) if(!empty($z['zone'])) $zst[$z['zone']][$nd2['n']]=(string)($z['status']??''); }
  foreach($zj['data']??[] as $z){ if(empty($z['zone'])) continue; $stt=$zst[$z['zone']]??[]; $sdn['zones'][]=['n'=>(string)$z['zone'],'type'=>(string)($z['type']??''),'status'=>$stt?(count(array_filter($stt,fn($v)=>$v!=='available'))?'error':'available'):'','nodes'=>(string)($z['nodes']??''),'bridge'=>(string)($z['bridge']??''),'tag'=>(string)($z['tag']??''),'ipam'=>(string)($z['ipam']??'')]; }
  $tc=time(); foreach($vj['data']??[] as $v){ if(empty($v['vnet'])) continue; $sn=[]; if(time()-$tc<10){ [$sj]=pve_get($s,$cn,'/cluster/sdn/vnets/'.rawurlencode($v['vnet']).'/subnets',4); foreach($sj['data']??[] as $x) $sn[]=trim((string)($x['cidr']??$x['subnet']??'').(!empty($x['gateway'])?' gw '.$x['gateway']:'')); }
   $sdn['vnets'][]=['n'=>(string)$v['vnet'],'zone'=>(string)($v['zone']??''),'tag'=>(string)($v['tag']??''),'alias'=>(string)($v['alias']??''),'vlanaware'=>!empty($v['vlanaware']),'subnets'=>$sn]; } }catch(Throwable $e){}
 $t2=time(); foreach($vms as &$vm){ if(time()-$t2>25) break; if(!$vm['node']) continue; $cj=pve_get($s,$cn,'/nodes/'.rawurlencode($vm['node']).'/'.($vm['kind']==='ct'?'lxc':'qemu').'/'.rawurlencode($vm['id']).'/config',5)[0]; $cf=$cj['data']??null; if(!is_array($cf)) continue; $nics=[];
  foreach($cf as $kk=>$vv){ if(!preg_match('/^net\d+$/',(string)$kk)||!is_string($vv)) continue; $o=['n'=>$kk]; foreach(explode(',',$vv) as $part){ $kv=explode('=',$part,2); if(count($kv)<2) continue; [$a1,$b1]=$kv; if(in_array($a1,['virtio','e1000','e1000e','rtl8139','vmxnet3','i82551','i82557b','i82559er','ne2k_pci','ne2k_isa','pcnet','tulip'],true)){ $o['model']=$a1; $o['mac']=$b1; } elseif($a1==='hwaddr') $o['mac']=$b1; elseif($a1==='bridge') $o['bridge']=$b1; elseif($a1==='tag') $o['vlan']=$b1; elseif($a1==='ip') $o['ip']=$b1; elseif($a1==='name') $o['ifn']=$b1; elseif($a1==='rate') $o['rate']=$b1; elseif($a1==='firewall') $o['fw']=$b1; elseif($a1==='link_down') $o['down']=$b1; }
   $nics[]=$o; } if($nics) $vm['nics']=$nics; }
 unset($vm);
 // SMART per nod (kräver Sys.Audit på /nodes). Fel stoppar inte pollningen.
 $smt=[]; $t3=time(); foreach($nodes as $nd){ if(!$nd['on']||time()-$t3>25) continue; $np='/nodes/'.rawurlencode($nd['n']); [$dj]=pve_get($s,$cn,$np.'/disks/list',8); if(!$dj) continue; foreach(smart_api($dj['data']??[],fn($dv,$nm)=>pve_get($s,$cn,$np.'/disks/smart?disk='.rawurlencode($dv),6)[0],$t3) as $r){ $r['nd']=$nd['n']; if(count($nodes)>1) $r['n']=$nd['n'].': '.$r['n']; $smt[]=$r; } }
 $ag=null; if(!empty($cn['agent'])){ $ag=['ok'=>0,'fail'=>0,'err'=>'']; $t0=time();
  foreach($vms as &$vm){ if($vm['kind']!=='vm'||$vm['state']!=='running') continue; if(time()-$t0>25) break;
   [$fj,$fe]=pve_get($s,$cn,'/nodes/'.rawurlencode($vm['node']).'/qemu/'.rawurlencode($vm['id']).'/agent/get-fsinfo',6);
   if(!$fj){ $ag['fail']++; if($ag['err']==='') $ag['err']=(string)$fe; continue; } $u=0; $t=0; $seen=[];
   foreach($fj['data']['result']??$fj['data']??[] as $fs){ if(!is_array($fs)||in_array($fs['type']??'',['tmpfs','devtmpfs','squashfs','overlay','iso9660'],true)) continue; $tb=(float)($fs['total-bytes']??0); $key=(string)($fs['name']??$fs['mountpoint']??''); if($tb<=0||isset($seen[$key])) continue; $seen[$key]=1; $u+=(float)($fs['used-bytes']??0); $t+=$tb; }
   if($t>0){ $vm['disk']=$u; $vm['disk_t']=$t; $ag['ok']++; } else $ag['fail']++; }
  unset($vm); }
 if(!$vms&&!$st&&array_sum(array_column($nodes,'mem_t'))==0) return ['ok'=>false,'error'=>t('Token ser inga resurser – ge själva token (inte bara användaren) rollen PVEAuditor på path / med Propagate, eller avmarkera Privilege Separation','Token sees no resources – give the token itself (not just the user) the PVEAuditor role on path / with Propagate, or uncheck Privilege Separation')];
 $on=array_filter($nodes,fn($n)=>$n['on']); $mt=array_sum(array_column($on,'mem_t')); $mu=array_sum(array_column($on,'mem'));
 return ['ok'=>true,'data'=>['type'=>'proxmox','version'=>$v['data']['version']??'','cluster'=>$cl,'agent'=>$ag,'smart'=>$smt,'nodes'=>$nodes,'ifs'=>$ifs,'ifs_err'=>$ifs_err,'sdn'=>$sdn,'vms'=>$vms,'storage'=>array_values($st),'cpu'=>$on?round(array_sum(array_column($on,'cpu'))/count($on),1):null,'mem'=>$mt>0?round($mu/$mt*100,1):null,'mem_t'=>$mt,'mem_u'=>$mu]]; }
function poll_vmware($s,$cn){ if(!function_exists('snmp2_walk')) return ['ok'=>false,'error'=>t('PHP SNMP-tillägget saknas','PHP SNMP extension missing')];
 $m=snmp_all($s['host'],snmp_cfg($cn['snmp']??'default'),$s['id']??null); if(!$m) return ['ok'=>false,'error'=>lasterr()?:'SNMP']; $hv=$m['info']['hv']??null; $vms=[];
 foreach($hv['vms']??[] as $v){ $stt=$v['state']??''; $vms[]=['k'=>$v['name'],'n'=>$v['name'],'id'=>'','node'=>'','kind'=>'vm','state'=>$stt==='poweredon'?'running':($stt==='poweredoff'?'stopped':($stt==='suspended'?'paused':$stt)),'cpu'=>null,'cpus'=>$v['cpus']??0,'mem'=>null,'mem_t'=>(float)($v['mem']??0)*1048576,'os'=>$v['os']??''];
  }
 $st=array_map(fn($d)=>['n'=>$d['n'],'node'=>'','used'=>$d['t']-$d['f'],'total'=>$d['t'],'type'=>'','ok'=>true],$m['disks']);
 return ['ok'=>true,'ifs'=>$m['ifs']??[],'data'=>['hw'=>$m['info']['hw']??null,'custom'=>$m['info']['custom']??[],'type'=>'vmware','version'=>$hv['product']??$m['os'],'nodes'=>[['n'=>$m['info']['hostname']??$s['host'],'cpu'=>$m['cpu'],'mem'=>$m['mem_u'],'mem_t'=>$m['mem_t'],'up'=>$m['info']['uptime']??0,'on'=>true]],'vms'=>$vms,'storage'=>$st,'cpu'=>$m['cpu'],'mem'=>$m['mem'],'mem_t'=>$m['mem_t'],'mem_u'=>$m['mem_u']]]; }
function poll_hyperv($s,$cn){ $p=(((int)($cn['port']??10050))?:10050); $h=$s['host']; $cpu=zbx_metric($h,$p,'cpu'); if(!$cpu) return ['ok'=>false,'error'=>lasterr()?:'Zabbix-agent']; $mem=zbx_metric($h,$p,'mem'); $dk=zbx_metric($h,$p,'disk'); $vms=[];
 $ma=[2=>'running',3=>'stopped',32768=>'paused',32769=>'saved',32770=>'starting',32774=>'stopping'];
 $r=json_decode((string)zbx($h,$p,'wmi.getall[root\virtualization\v2,"SELECT ElementName,EnabledState FROM Msvm_ComputerSystem WHERE Caption=\'Virtual Machine\'"]'),true)?:[];
 $pr=json_decode((string)zbx($h,$p,'wmi.getall[root\cimv2,"SELECT Name,PercentTotalRunTime FROM Win32_PerfFormattedData_HvStats_HyperVHypervisorVirtualProcessor"]'),true)?:[]; $cp=[];
 foreach($pr as $x){ $n=(string)($x['Name']??''); if(strpos($n,':')===false||stripos($n,'Hv LP')!==false||stripos($n,'_Total')!==false) continue; $vm=substr($n,0,strrpos($n,':Hv VP')?:strlen($n)); $cp[$vm][]=(float)($x['PercentTotalRunTime']??0); }
 foreach($r as $x){ $n=(string)($x['ElementName']??''); $vms[]=['k'=>$n,'n'=>$n,'id'=>'','node'=>'','kind'=>'vm','state'=>$ma[(int)($x['EnabledState']??0)]??'other','cpu'=>isset($cp[$n])?round(array_sum($cp[$n])/count($cp[$n]),1):null,'mem'=>null,'mem_t'=>0]; }
 $st=array_map(fn($d)=>['n'=>$d['n'],'node'=>'','used'=>$d['t']-$d['f'],'total'=>$d['t'],'type'=>'','ok'=>true],$dk[1]??[]);
 return ['ok'=>true,'data'=>['type'=>'hyperv','version'=>'Hyper-V','nodes'=>[['n'=>$h,'cpu'=>$cpu[0],'mem'=>$mem[1]['u']??0,'mem_t'=>$mem[1]['t']??0,'up'=>0,'on'=>true]],'vms'=>$vms,'storage'=>$st,'cpu'=>$cpu[0],'mem'=>$mem[0]??null,'mem_t'=>$mem[1]['t']??null,'mem_u'=>$mem[1]['u']??null]]; }
// Interface för virtualiseringshostar (så att de går att välja i larmreglerna "Interface"): VMware via SNMP (samma som övrig data),
// Proxmox och Hyper-V via SNMP med standard-community om hosten svarar (snabb prob, och ett misslyckande kommer ihåg i 15 min så att
// hostar utan SNMP inte fördröjer varje pollning). Proxmox utan SNMP: länkstatus från API:t (inga trafikräknare).
function hv_snmp_g($s){ static $c=[]; $sid=(int)($s['id']??0); if(isset($c[$sid])) return $c[$sid]?:null; $g=false;
 if(function_exists('snmp2_get')&&$sid){ $k='hvsnmp_'.$sid; $f=(int)setting($k,0); if(!($f>0&&time()-$f<900)){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); $gg=snmp_cfg('default');
   $ok=$gg['v']===3?(@snmp_gt($s['host'],$gg,'1.3.6.1.2.1.1.1.0')!==false):(@snmp2_get($s['host'],$gg['c'],'1.3.6.1.2.1.1.1.0',400000,0)!==false);
   if($ok){ $g=$gg; if($f) set_setting($k,0); } else set_setting($k,time()); } }
 $c[$sid]=$g; return $g?:null; }
function hv_ifs($s,$d){ $ty=$s['hv_type'];
 if($g=hv_snmp_g($s)){ $x=snmp_ifaces($s['host'],$g,96); if($x) return $x; }
 if($ty==='proxmox'&&!empty($d['ifs'])){ $o=[]; $multi=count($d['nodes']??[])>1; foreach($d['ifs'] as $x){ if(in_array($x['type']??'',['loopback'],true)) continue; $nm=($multi?$x['node'].'/':'').$x['n'];
   $o[]=['idx'=>(crc32($x['node'].'/'.$x['n'])&0x7fffffff),'name'=>substr($nm,0,100),'descr'=>'','alias'=>(string)($x['note']??''),'type'=>6,'speed'=>0,'mac'=>'','ips'=>(string)($x['addr']??''),'admin'=>1,'oper'=>!empty($x['up'])?1:2,'in'=>0,'out'=>0,'ierr'=>0,'oerr'=>0,'idisc'=>0,'odisc'=>0,'crc'=>null,'hc'=>true,'nostat'=>true]; } return $o; }
 return null; }
function poll_host($s){ $pdo=db(); $cn=json_decode((string)$s['hv_conn'],true)?:[]; $own=!isset($GLOBALS['force_lang']); if($own) $GLOBALS['force_lang']=setting('sys_lang','sv'); lasterr('');
 try{ $r=$s['hv_type']==='proxmox'?poll_proxmox($s,$cn):($s['hv_type']==='vmware'?poll_vmware($s,$cn):poll_hyperv($s,$cn));
  if(!empty($r['ok'])&&in_array($s['hv_type'],['proxmox','hyperv'],true)&&function_exists('snmp2_get')){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); $cuv=snmp_custom($s['host'],snmp_cfg('default'),$s['id'],true); if($cuv) $r['data']['custom']=$cuv; if($hg=hv_snmp_g($s)){ $oh=(json_decode((string)$s['hv_data'],true)?:[])['hw']??null; $r['data']['hw']=snmp_hw($s['host'],$hg,$oh); } } }catch(Throwable $e){ $r=['ok'=>false,'error'=>substr($e->getMessage(),0,200)]; }
 $new=$r['ok']?'up':'down'; $old=$s['hv_status'];
 $pdo->prepare('UPDATE servers SET hv_poll=NOW(),hv_status=?,hv_error=?'.($r['ok']?',hv_data=?':'').' WHERE id=?')->execute(array_merge([$new,$r['ok']?null:substr((string)$r['error'],0,250)],$r['ok']?[json_encode($r['data'])]:[],[$s['id']]));
 if($r['ok']){ record_load($s['id'],$r['data']['cpu']??null,$r['data']['mem']??null); try{ $hi=$s['hv_type']==='vmware'?($r['ifs']??[]):hv_ifs($s,$r['data']); if($hi) record_net($s['id'],$hi); }catch(Throwable $e){ applog('debug','snmp',$s['name'].': interface – '.$e->getMessage()); } }
 applog('debug',$s['hv_type'],$s['name'].': '.($r['ok']?'OK – '.count($r['data']['vms']??[]).' VM':($r['error']??'')));
 if($old!==$new&&!($old===null&&$new==='up')){ applog($new==='down'?'warning':'info',$s['hv_type']?:'host',$s['name'].' ('.strtoupper($s['hv_type']).'): '.($new==='down'?'not responding – '.($r['error']??''):'responding again')); try{ $pdo->prepare("INSERT INTO check_events(check_id,server_id,server_name,svc,label,target,status,detail) VALUES(NULL,?,?,0,?,'',?,?)")->execute([$s['id'],$s['name'],strtoupper($s['hv_type']).' API',$new,substr((string)($r['error']??''),0,250)]); }catch(Throwable $e){} }
 if($own) unset($GLOBALS['force_lang']); return $r; }

// ---- Nätverksenheter (switchar/routrar/brandväggar) via SNMP: Cisco, Meraki, HP ProCurve, Aruba AOS-CX, Huawei, UniFi, OPNsense, pfSense
function gb($b){ return $b>0?round($b/1073741824,1).' GB':'–'; }
function st_model($m){ $m=trim((string)$m); if(preg_match('/(?:TrueNAS|FreeNAS)[\w.\-]*\w/i',$m,$x)) return $x[0]; $m=preg_split('/\.\s+Hardware:|,\s|\s\(/',$m)[0]; return mb_substr($m,0,60); }
function st_types(){ return ['synology'=>'Synology DSM','qnap'=>'QNAP','truenas'=>'TrueNAS / FreeNAS','netapp'=>'NetApp','dell'=>'Dell (PowerEdge / PowerVault MD, iDRAC / OpenManage)','hpe'=>'HPE (ProLiant / StoreEasy / 3PAR / Nimble / MSA)','ibm'=>'IBM (Storwize / FlashSystem / DS)','hitachi'=>'Hitachi Vantara','pure'=>'Pure Storage','oceanstor'=>'Huawei OceanStor','lenovo'=>'Lenovo ThinkSystem DE / DM','generic'=>t('Annan (standard-SNMP)','Other (standard SNMP)')]; }
function st_roles(){ return ['nas'=>'NAS','san'=>'SAN']; }
function nw_has_disks($role){ return nw_is_st($role)||in_array((string)$role,['firewall','cloudkey'],true); }
// Eget avläsningsintervall (sekunder) per nätverks-/lagrings-/BMC-enhet. Sparas i nw_conn.iv; tomt = standardintervallet under Inställningar.
function nw_conn_iv($c){ $v=(int)preg_replace('/\D/','',(string)($_POST['nw_iv']??'')); if($v>=10) $c['iv']=min($v,86400); else unset($c['iv']); return $c; }
function nw_iv_input($ec){ return '<input name="nw_iv" type="number" min="10" max="86400" style="width:120px" value="'.h($ec['iv']??'').'" placeholder="'.h(t('Intervall (sek)','Interval (sec)')).'" title="'.h(t('Hur ofta enheten avläses via SNMP, i sekunder (minst 10). Tomt = standardintervallet under Inställningar.','How often the device is read over SNMP, in seconds (minimum 10). Empty = the default interval under Settings.')).'"> '; }
// Kommer ihåg senaste valen (typ, roll, SNMP-version, port, intervall, community, v3-användare/nivå/protokoll – inte lösenord) när man lägger till flera enheter i rad. Sparas i webbläsaren.
function nw_remember_js($key){ return '<script>(function(){var K="vmmon_add_'.h($key).'",t=document.getElementById("ntype");if(!t||!t.form)return;var f=t.form,N=["type","role","s_ver","s_port","nw_iv","s_comm","s3_user","s3_lvl","s3_ap","s3_pp"];function el(n){return f.elements[n]}
function ev(e){if(e)e.dispatchEvent(new Event("change",{bubbles:true}))}
try{var d=JSON.parse(localStorage.getItem(K)||"null");if(d){["type","role","s_ver"].forEach(function(n){var e=el(n);if(e&&d[n]!=null&&e.tagName==="SELECT"&&[].some.call(e.options,function(o){return o.value===d[n]})){e.value=d[n];ev(e)}});["s_port","nw_iv","s_comm","s3_user","s3_lvl","s3_ap","s3_pp"].forEach(function(n){var e=el(n);if(e&&d[n]!=null&&d[n]!=="")e.value=d[n]})}}catch(x){}
f.addEventListener("submit",function(){try{var o={};N.forEach(function(n){var e=el(n);if(e)o[n]=e.value});localStorage.setItem(K,JSON.stringify(o))}catch(x){}})})();</script>'; }
function nw_is_st($role){ return in_array((string)$role,['nas','san','bmc'],true); }
function bmc_types(){ return ['idrac'=>'Dell iDRAC','ilo'=>'HPE iLO','ipmi'=>t('IPMI / annan BMC (Supermicro, Lenovo XCC m.fl.)','IPMI / other BMC (Supermicro, Lenovo XCC etc.)')]; }
function bmc_pv($v){ return ['idrac'=>'dell','ilo'=>'hpe'][$v]??$v; }
function nw_page($role){ return $role==='bmc'?'bmc.php':(in_array((string)$role,['nas','san'],true)?'storage.php':'network.php'); }
function nw_types($all=false){ $n=['cisco'=>'Cisco (IOS / IOS-XE / NX-OS / ASA)','meraki'=>'Cisco Meraki','hp'=>'HP ProCurve / Aruba (2530 m.fl.)','aosc'=>'HPE Aruba AOS-CX','huawei'=>'Huawei','unifi'=>'Ubiquiti UniFi','opnsense'=>'OPNsense','pfsense'=>'pfSense','generic'=>t('Annan (standard-SNMP)','Other (standard SNMP)')]; if($all){ unset($n['generic']); $n=array_merge($n,st_types(),bmc_types()); } return $n; }
function nw_roles($all=false){ $r=['switch'=>'Switch','router'=>'Router','firewall'=>t('Brandvägg','Firewall'),'ap'=>t('Accesspunkt','Access point'),'cloudkey'=>'Cloud Key']; return $all?array_merge($r,st_roles(),['bmc'=>'BMC']):$r; }
// Meraki Cloud Controller (MERAKI-CLOUD-CONTROLLER-MIB, enterprise 29671): alla enheter i organisationen (switchar, accesspunkter, brandväggar) via en enda SNMP-adress.
function meraki_utf8($s){ $s=trim((string)$s,"\" \r\n\t"); if(preg_match('/^([0-9A-Fa-f]{2} )+[0-9A-Fa-f]{2}$/',$s)) $s=(string)hex2bin(str_replace(' ','',$s)); return mb_convert_encoding($s,'UTF-8','UTF-8'); }
function meraki_dt($v){ $v=trim((string)$v,"\" \r\n\t"); $b=preg_match('/^([0-9A-Fa-f]{2} )+[0-9A-Fa-f]{2}$/',$v)?(string)hex2bin(str_replace(' ','',$v)):$v; if(strlen($b)<7) return ''; $y=unpack('n',$b)[1]; if($y<2000||$y>2100) return ''; return sprintf('%04d-%02d-%02d %02d:%02d',$y,ord($b[2]),ord($b[3]),ord($b[4]),ord($b[5])); }
function meraki_collect($h,$g){ $B='1.3.6.1.4.1.29671.1.1.'; $nm=snmp_walk_suffix($h,$g,$B.'4.1.2',2000); if(!$nm) return null;
 $c=function($n) use($h,$g,$B){ return snmp_walk_suffix($h,$g,$B.'4.1.'.$n,2000); }; $st=$c(3); $ct=$c(4); $cl=$c(5); $ip=$c(7); $sn=$c(8); $pc=$c(9); $ds=$c(10); $nt=$c(11); $devs=[];
 foreach($nm as $k=>$name){ $p=explode('.',$k); if(count($p)!==6) continue; $mac=implode(':',array_map(fn($x)=>sprintf('%02x',(int)$x),$p)); $sv=(string)($st[$k]??''); $on=stripos($sv,'offline')!==false?0:(preg_match('/(\d+)/',$sv,$m)?((int)$m[1]?1:0):0);
  $devs[]=['mac'=>$mac,'n'=>meraki_utf8($name),'on'=>$on,'cl'=>isset($cl[$k])&&is_numeric($cl[$k])?(int)$cl[$k]:null,'ip'=>trim((string)($ip[$k]??''),"\" "),'sn'=>meraki_utf8($sn[$k]??''),'pc'=>meraki_utf8($pc[$k]??''),'ds'=>meraki_utf8($ds[$k]??''),'net'=>meraki_utf8($nt[$k]??''),'ct'=>meraki_dt($ct[$k]??'')]; if(count($devs)>=500) break; }
 usort($devs,fn($a,$b)=>($a['on']<=>$b['on'])?:strcasecmp($a['net'],$b['net'])?:strcasecmp($a['n'],$b['n'])); return ['org'=>meraki_utf8(sget($h,$g,$B.'1.0')),'devs'=>$devs]; }
function nw_disks($h,$g){ $d=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.3'); $ty=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.2'); $u=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.4'); $sz=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.5'); $us=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.6'); $o=[];
 foreach($d as $i=>$name){ $un=(float)($u[$i]??1); $tt=(float)($sz[$i]??0)*$un; $x=(float)($us[$i]??0)*$un; if(preg_match('/hrStorageFixedDisk|25\.2\.1\.4$|hrStorageNetworkDisk|25\.2\.1\.10$/',(string)($ty[$i]??''))&&$tt>0&&!preg_match('#^/(run|dev|sys|proc|snap|boot)|/\.|@|^/mnt/\.#',$name)) $o[]=['n'=>short_disk($name),'full'=>trim($name),'t'=>$tt,'f'=>$tt-$x]; } return array_slice($o,0,24); }
function nw_max($t,$lo=0,$hi=100){ $m=null; foreach($t as $v){ $f=(float)$v; if($f>=$lo&&$f<=$hi&&($m===null||$f>$m)) $m=$f; } return $m; }
function nw_num($v){ return($v!==null&&is_numeric($v))?(float)$v:null; }
function nw_ucd($h,$g){ $idle=nw_num(sget($h,$g,'1.3.6.1.4.1.2021.11.11.0')); $tt=nw_num(sget($h,$g,'1.3.6.1.4.1.2021.4.5.0')); $av=nw_num(sget($h,$g,'1.3.6.1.4.1.2021.4.6.0')); $bf=nw_num(sget($h,$g,'1.3.6.1.4.1.2021.4.14.0')); $ca=nw_num(sget($h,$g,'1.3.6.1.4.1.2021.4.15.0')); $fr=$av; if($av!==null) $fr=$av+max(0,(float)$bf)+max(0,(float)$ca); if($fr!==null&&$tt) $fr=min($fr,$tt); return [$idle!==null?round(100-$idle,1):null,($tt&&$fr!==null)?round((1-$fr/$tt)*100,1):null]; }
function nw_hrmem($h,$g){ $ty=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.2'); $u=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.4'); $sz=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.5'); $us=snmp_walk($h,$g,'1.3.6.1.2.1.25.2.3.1.6'); $b=null;
 foreach($ty as $i=>$x){ if(!preg_match('/hrStorageRam|25\.2\.1\.2$/',(string)$x)) continue; $tt=(float)($sz[$i]??0)*(float)($u[$i]??1); if($tt>0&&($b===null||$tt>$b[0])) $b=[$tt,(float)($us[$i]??0)*(float)($u[$i]??1)]; } return $b?round($b[1]/$b[0]*100,1):null; }
// ---- Lagringsenheter: RAID/pool/volym/diskstatus per tillverkare (SNMP). Rad: ['n'=>namn,'st'=>ok|warn|fail,'txt'=>status,'k'=>raid|pool|volume|disk|system]
function st_row($n,$st,$txt,$k){ return ['n'=>(string)$n,'st'=>$st,'txt'=>(string)$txt,'k'=>$k]; }
function st_cols($h,$g,$oid){ $r=@snmp_rw($h,$g,$oid); $o=[]; if(is_array($r)) foreach($r as $k=>$v){ $p=explode('.',(string)$k); $row=array_pop($p); $col=array_pop($p); $o[$col][$row]=trim((string)$v,"\" \r\n"); } return $o; }
function st_map($m,$v,$k,$name){ $x=$m[(int)$v]??['warn',t('status ','status ').$v]; return st_row($name,$x[0],$x[1],$k); }
function tn_req($h,$cn,$path,$post=null){ $c=curl_init('https://'.$h.':'.(((int)($cn['port']??443))?:443).'/api/v2.0'.$path); $hd=['Authorization: Bearer '.secret_dec($cn['key']??''),'Accept: application/json']; $o=[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_SSL_VERIFYPEER=>!empty($cn['verify'])?1:0,CURLOPT_SSL_VERIFYHOST=>!empty($cn['verify'])?2:0];
 if($post!==null){ $o[CURLOPT_POST]=1; $o[CURLOPT_POSTFIELDS]=json_encode($post); $hd[]='Content-Type: application/json'; } $o[CURLOPT_HTTPHEADER]=$hd; curl_setopt_array($c,$o);
 $r=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); $e=curl_error($c); curl_close($c); if($r===false) return [null,$e?:'no response']; $j=json_decode($r,true);
 if($code<200||$code>=300) return [null,"HTTP $code".($code===401?' ('.t('ogiltig API-nyckel','invalid API key').')':'').(is_array($j)&&!empty($j['message'])?' '.substr((string)$j['message'],0,120):'')]; return [$j,null]; }
function tn_save_conn($id){ $q=db()->prepare('SELECT nw_conn FROM servers WHERE id=?'); $q->execute([(int)$id]); $cn=json_decode((string)$q->fetchColumn(),true)?:[]; $t=$cn['tn']??[]; $k=trim($_POST['tn_key']??''); if($k!==''&&$k!=='********') $t['key']=secret_enc($k); $pt=(int)preg_replace('/\D/','',$_POST['tn_port']??''); $t['port']=$pt?:443; $t['verify']=!empty($_POST['tn_verify'])?1:0; if(empty($t['key'])) return false; $cn['tn']=$t; db()->prepare('UPDATE servers SET nw_conn=?,nw_poll=NULL WHERE id=?')->execute([json_encode($cn),(int)$id]); return true; }
function tn_del_conn($id){ $q=db()->prepare('SELECT nw_conn FROM servers WHERE id=?'); $q->execute([(int)$id]); $cn=json_decode((string)$q->fetchColumn(),true)?:[]; unset($cn['tn']); db()->prepare('UPDATE servers SET nw_conn=?,nw_poll=NULL WHERE id=?')->execute([json_encode($cn),(int)$id]); }
function tn_on($cn){ return !empty($cn['tn']['key'])&&empty($cn['tn']['off']); }
// Loggar tillståndsbyten för TrueNAS API (ger uptime-statistik via check_events, label 'TrueNAS API')
function tn_event($s,$state,$err=''){ try{ $q=db()->prepare("SELECT status FROM check_events WHERE server_id=? AND check_id IS NULL AND svc=0 AND label='TrueNAS API' ORDER BY id DESC LIMIT 1"); $q->execute([$s['id']]); $old=$q->fetchColumn(); if($old===false){ if($state==='up') return; } elseif($old===$state) return;
  applog($state==='down'?'warning':'info','snmp',$s['name'].' (TrueNAS API): '.($state==='down'?'not responding – '.$err:'responding again'));
  db()->prepare("INSERT INTO check_events(check_id,server_id,server_name,svc,label,target,status,detail) VALUES(NULL,?,?,0,'TrueNAS API','',?,?)")->execute([$s['id'],$s['name'],$state,substr((string)$err,0,250)]); }catch(Throwable $e){} }
// TrueNAS (CORE och SCALE) via REST API v2.0 med API-nyckel: pooler, diskar, temperatur, SMART-testresultat och aktiva larm
function tn_probe($h,$cn){ $c=$cn['tn']??[]; $o=['ok'=>false,'error'=>null,'health'=>[],'smart'=>[],'info'=>[],'pools'=>[]];
 [$si,$e]=tn_req($h,$c,'/system/info'); if(!is_array($si)){ $o['error']=$e; return $o; } $o['ok']=true; $o['info']=['version'=>(string)($si['version']??''),'hostname'=>(string)($si['hostname']??''),'uptime'=>isset($si['uptime_seconds'])?(int)$si['uptime_seconds']:null];
 [$pl,$e]=tn_req($h,$c,'/pool'); if(is_array($pl)) foreach($pl as $p){ $s=strtoupper((string)($p['status']??'')); $hl=$p['healthy']??null; $st=$s==='ONLINE'?($hl===false?'warn':'ok'):'fail'; $tx=$s?:'?'; if($s==='ONLINE'&&$hl===false) $tx.=' ('.t('ej frisk','not healthy').')'; if(!empty($p['scan']['errors'])) $tx.=' · scrub '.(int)$p['scan']['errors'].' '.t('fel','errors');
  $o['health'][]=st_row($p['name']??'pool',$st,$tx,'pool'); if(!empty($p['size'])) $o['pools'][]=['n'=>(string)($p['name']??'pool'),'full'=>(string)($p['name']??'pool'),'t'=>(float)$p['size'],'f'=>(float)($p['free']??max(0,(float)$p['size']-(float)($p['allocated']??0)))]; }
 elseif($e) $o['health'][]=st_row('Pools','warn',$e,'pool');
 [$dk,$e]=tn_req($h,$c,'/disk'); $names=[]; $dd=[]; if(is_array($dk)) foreach($dk as $d){ $n=(string)($d['name']??''); if($n===''||!empty($d['expiretime'])) continue; $names[]=$n; $dd[$n]=$d; }
 $tp=[]; if($names){ [$tt,$e]=tn_req($h,$c,'/disk/temperatures',['names'=>$names]); if(is_array($tt)) $tp=$tt; }
 $res=[]; [$sr,$e]=tn_req($h,$c,'/smart/test/results'); if(is_array($sr)) foreach($sr as $r){ $dn=(string)($r['disk']??''); $best=null; foreach(($r['tests']??[]) as $t1){ if($best===null||(int)($t1['num']??999)<(int)($best['num']??999)) $best=$t1; } if($dn!==''&&$best) $res[$dn]=strtoupper((string)($best['status']??'')); }
 $al=[]; [$aj,$e]=tn_req($h,$c,'/alert/list'); if(is_array($aj)) foreach($aj as $a){ if(!empty($a['dismissed'])) continue; $lv=strtoupper((string)($a['level']??'')); $st=in_array($lv,['ERROR','CRITICAL','ALERT','EMERGENCY'],true)?'fail':($lv==='WARNING'?'warn':null); if(!$st) continue; $tx=trim(html_entity_decode(strip_tags((string)($a['formatted']??$a['klass']??''))));
  $al[]=['st'=>$st,'tx'=>$tx,'k'=>(string)($a['klass']??'Alert')]; }
 $at=[]; $mode=null; $o['attr']=0; foreach(array_slice($names,0,24) as $n){ $r1=null; foreach($mode===null?['s','l']:[$mode] as $m){ [$r1,$e]=tn_req($h,$c,'/disk/smart_attributes',$m==='s'?$n:[$n]); if(is_array($r1)){ $mode=$m; break; } } if(!is_array($r1)){ if($mode===null) break; continue; }
  $v=[]; foreach($r1 as $a){ if(!is_array($a)) continue; $id=(int)($a['id']??0); $nm=strtolower((string)($a['name']??'')); $rw=$a['raw']??($a['value_raw']??($a['rawvalue']??null)); if(is_array($rw)) $rw=$rw['value']??($rw['string']??null); if($rw===null||!preg_match('/-?\d+/',(string)$rw,$mm)) continue; $x=(int)$mm[0];
   if($id===5||preg_match('/^reallocat(ed|e)_(sector|nand|block|blk)/',$nm)) $v['realloc']=$x; elseif($id===9||$nm==='power_on_hours') $v['poh']=$x; elseif($id===197||$nm==='current_pending_sector') $v['pend']=$x; elseif(($id===198||$nm==='offline_uncorrectable')&&!isset($v['pend'])) $v['pend']=$x; } if($v){ $at[$n]=$v; $o['attr']++; } }
 foreach($names as $n){ $d=$dd[$n]; $hv=null; $wn=false; $s=$res[$n]??''; if($s==='SUCCESS') $hv='ok'; elseif($s!==''&&strpos($s,'FAIL')!==false) $hv='fail';
  foreach($al as $a) if(preg_match('/\b'.preg_quote($n,'/').'\b/',$a['tx'])){ if($a['st']==='fail') $hv='fail'; else $wn=true; }
  $o['smart'][]=smart_row(['n'=>$n,'model'=>$d['model']??'','serial'=>$d['serial']??'','temp'=>$tp[$n]??null,'health'=>$hv,'warn'=>$wn,'src'=>'truenas-api']+($at[$n]??[])); }
 $seen=[]; foreach($al as $a){ $k=$a['k'].$a['tx']; if(isset($seen[$k])) continue; $seen[$k]=1; if(count($seen)>8) break; $o['health'][]=st_row($a['k'],$a['st'],mb_substr($a['tx'],0,140),'alert'); }
 return $o; } function st_probe($h,$g,$v,$cn=[]){ $o=['health'=>[],'temps'=>[],'fans'=>[],'psu'=>[],'cpu'=>null];
 if($v==='synology'){ $nm=snmp_walk($h,$g,'1.3.6.1.4.1.6574.3.1.1.2'); $stt=snmp_walk($h,$g,'1.3.6.1.4.1.6574.3.1.1.3');
  $M=[1=>['ok','Normal'],2=>['warn','Repairing'],3=>['warn','Migrating'],4=>['warn','Expanding'],5=>['warn','Deleting'],6=>['warn','Creating'],7=>['warn','Syncing'],8=>['ok','Parity check'],9=>['warn','Assembling'],10=>['warn','Canceling'],11=>['fail','Degraded'],12=>['fail','Crashed'],13=>['ok','Data scrub']];
  foreach($nm as $i=>$n) $o['health'][]=st_map($M,$stt[$i]??0,'raid',trim((string)$n)); }
 elseif($v==='qnap'){ $d=st_cols($h,$g,'1.3.6.1.4.1.24681.1.2.11.1'); foreach(($d[2]??[]) as $i=>$n){ $s=(string)($d[4][$i]??''); if($s==='-5') continue; $sm=strtoupper((string)($d[7][$i]??'')); $st=($s==='0'||$s==='')?'ok':'fail'; $tx=$st==='ok'?'Ready':'status '.$s; if($st==='ok'&&$sm!==''&&strpos($sm,'GOOD')===false&&strpos($sm,'NORMAL')===false){ $st='warn'; $tx='SMART '.$sm; } $o['health'][]=st_row($n,$st,$tx,'disk'); }
  $vl=st_cols($h,$g,'1.3.6.1.4.1.24681.1.2.17.1'); foreach(($vl[2]??[]) as $i=>$n){ $s=(string)($vl[6][$i]??''); $o['health'][]=st_row($n,(stripos($s,'ready')!==false||$s==='')?'ok':'warn',$s?:'Ready','volume'); }
  $t=sget($h,$g,'1.3.6.1.4.1.24681.1.2.5.0'); if($t!==null&&preg_match('/(\d+)/',$t,$m)) $o['temps'][]=['n'=>t('System','System'),'v'=>(float)$m[1]]; }
 elseif($v==='netapp'){ $gs=(int)sget($h,$g,'1.3.6.1.4.1.789.1.2.2.4.0'); if($gs){ $msg=sget($h,$g,'1.3.6.1.4.1.789.1.2.2.25.0'); $o['health'][]=st_row(t('Systemstatus','System status'),$gs===3?'ok':($gs===4?'warn':($gs>=5?'fail':'warn')),$msg?:['','','','OK','Non-critical','Critical','Non-recoverable'][$gs]??'','system'); }
  $fd=sget($h,$g,'1.3.6.1.4.1.789.1.6.4.7.0'); if($fd!==null) $o['health'][]=st_row(t('Diskar','Disks'),(int)$fd>0?'fail':'ok',(int)$fd>0?"$fd ".t('felande','failed'):'OK','disk');
  $ff=sget($h,$g,'1.3.6.1.4.1.789.1.2.4.2.0'); if($ff!==null) $o['fans'][]=['n'=>t('Fläktar','Fans'),'ok'=>(int)$ff===0]; $fp=sget($h,$g,'1.3.6.1.4.1.789.1.2.4.4.0'); if($fp!==null) $o['psu'][]=['n'=>t('Nätdelar','Power supplies'),'ok'=>(int)$fp===0];
  $c=sget($h,$g,'1.3.6.1.4.1.789.1.2.1.3.0'); if($c!==null&&is_numeric($c)) $o['cpu']=(float)$c; }
 elseif($v==='dell'){ $gs=(int)(sget($h,$g,'1.3.6.1.4.1.674.10892.5.2.1.0')?:sget($h,$g,'1.3.6.1.4.1.674.10892.1.200.10.1.2.1')); if($gs) $o['health'][]=st_row(t('Systemstatus','System status'),$gs===3?'ok':($gs===4?'warn':($gs>=5?'fail':'warn')),[3=>'OK',4=>'Non-critical',5=>'Critical',6=>'Non-recoverable'][$gs]??'status '.$gs,'system');
  $vn=snmp_walk($h,$g,'1.3.6.1.4.1.674.10892.5.5.1.20.140.1.1.2'); $vs=snmp_walk($h,$g,'1.3.6.1.4.1.674.10892.5.5.1.20.140.1.1.4'); $M=[1=>['warn','Unknown'],2=>['ok','Online'],3=>['fail','Failed'],4=>['fail','Degraded']]; foreach($vn as $i=>$n) $o['health'][]=st_map($M,$vs[$i]??0,'raid',trim((string)$n));
  $pn=snmp_walk($h,$g,'1.3.6.1.4.1.674.10892.5.5.1.20.130.4.1.2'); $ps=snmp_walk($h,$g,'1.3.6.1.4.1.674.10892.5.5.1.20.130.4.1.4'); $M=[1=>['warn','Unknown'],2=>['ok','Ready'],3=>['ok','Online'],4=>['warn','Foreign'],5=>['warn','Offline'],6=>['warn','Blocked'],7=>['fail','Failed'],8=>['ok','Non-RAID'],9=>['warn','Removed']]; foreach($pn as $i=>$n) $o['health'][]=st_map($M,$ps[$i]??0,'disk',trim((string)$n)); }
 elseif($v==='hpe'){ $c=(int)sget($h,$g,'1.3.6.1.4.1.232.6.1.3.0'); if($c) $o['health'][]=st_row(t('Systemstatus','System status'),$c===2?'ok':($c===3?'warn':($c===4?'fail':'warn')),[2=>'OK',3=>'Degraded',4=>'Failed'][$c]??'status '.$c,'system');
  $M=[2=>['ok','OK'],3=>['warn','Degraded'],4=>['fail','Failed']]; $ld=snmp_walk($h,$g,'1.3.6.1.4.1.232.3.2.3.1.1.11'); foreach($ld as $i=>$s) $o['health'][]=st_map($M,$s,'raid','Logical drive '.($i+1));
  $pd=snmp_walk($h,$g,'1.3.6.1.4.1.232.3.2.5.1.1.37'); foreach($pd as $i=>$s) $o['health'][]=st_map($M,$s,'disk','Physical drive '.($i+1)); }
 elseif($v==='truenas'&&tn_on($cn)&&($tj=tn_probe($h,$cn))&&$tj['ok']){ $o['health']=$tj['health']; $o['smart']=$tj['smart']; $o['pools']=$tj['pools']; $o['tn']=$tj['info']; $o['attr']=$tj['attr']??0; }
 elseif($v==='truenas'){ if(tn_on($cn)&&!empty($tj)) $o['health'][]=st_row('TrueNAS API','fail',(string)$tj['error'],'api'); foreach(['1.3.6.1.4.1.50536.1.1.1','1.3.6.1.4.1.25359.1.1.1'] as $base){ $cols=st_cols($h,$g,$base); if(!$cols) continue; $hc=null; foreach($cols as $ci=>$rows) if($rows&&count(array_filter($rows,fn($x)=>preg_match('/^(ONLINE|DEGRADED|FAULTED|OFFLINE|UNAVAIL|REMOVED)$/i',$x)))===count($rows)){ $hc=$ci; break; }
   if($hc===null) continue; $nc=null; foreach($cols as $ci=>$rows) if($ci!==$hc&&$rows&&count(array_filter($rows,fn($x)=>!is_numeric($x)&&$x!==''))===count($rows)){ $nc=$ci; break; }
   foreach($cols[$hc] as $i=>$s){ $u=strtoupper($s); $o['health'][]=st_row($nc!==null?($cols[$nc][$i]??"pool $i"):"pool $i",$u==='ONLINE'?'ok':($u==='DEGRADED'?'fail':'fail'),$s,'pool'); } break; } }
 if($v==='truenas'&&empty($o['smart'])){ $dn=snmp_walk($h,$g,'1.3.6.1.4.1.50536.3.1.2'); $dt=snmp_walk($h,$g,'1.3.6.1.4.1.50536.3.1.3'); foreach($dn as $i=>$n){ $tv=isset($dt[$i])&&is_numeric($dt[$i])?round((float)$dt[$i]/1000):null; $o['smart'][]=smart_row(['n'=>trim((string)$n),'temp'=>$tv,'src'=>'truenas']); } }
 return $o; }
function st_summary($l){ if(!$l) return null; $o=['tot'=>count($l),'ok'=>0,'warn'=>0,'fail'=>0,'rows'=>[]]; foreach($l as $x){ $o[$x['st']]++; } $o['rows']=array_slice(array_map(fn($x)=>['n'=>$x['n'],'s'=>$x['st'],'t'=>$x['txt'],'k'=>$x['k']],$l),0,12); return $o; }
function snmp_rw_safe($h,$g,$oid){ if(($g['v']??2)!==2||!class_exists('SNMP')){ $r=@snmp_rw($h,$g,$oid); return is_array($r)?$r:[]; }
 foreach([[SNMP::VERSION_2c,4],[SNMP::VERSION_1,0]] as [$ver,$mr]){ try{ $s=new SNMP($ver,snmp_hp($h,$g),$g['c'],4000000,1); $s->oid_output_format=SNMP_OID_OUTPUT_NUMERIC; $s->valueretrieval=SNMP_VALUE_PLAIN; $s->quick_print=true; $w=$mr?@$s->walk($oid,false,$mr):@$s->walk($oid); $s->close(); if(is_array($w)&&$w) return $w; }catch(Throwable $e){} }
 return []; }
function bmc_tbl($h,$g,$base){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); $r=snmp_rw_safe($h,$g,$base); $o=[]; if(!is_array($r)) return $o; $n=count(explode('.',$base)); foreach($r as $k=>$v){ $p=explode('.',ltrim((string)$k,'.')); if($p[0]==='iso') $p[0]='1'; $o[implode('.',array_slice($p,$n))]=is_string($v)?trim($v,"\" \r\n"):$v; } return $o; }
// BMC (iDRAC / iLO / IPMI-baserade som Huawei iBMC, Supermicro, Lenovo XCC): hälsa, temperaturer, fläktar, nätdelar, effekt (W) och spänningar
function bmc_probe($h,$g,$v,$cn=[]){ $o=['health'=>[],'temps'=>[],'fans'=>[],'psu'=>[],'pwr'=>[],'volts'=>[],'watts'=>null,'cpu'=>null,'mem'=>null]; $pv=bmc_pv($v);
 if(in_array($pv,['dell','hpe'],true)){ $pr=st_probe($h,$g,$pv,$cn); $o['health']=$pr['health']??[]; foreach(['temps','fans','psu'] as $k) if(!empty($pr[$k])) $o[$k]=$pr[$k]; }
 $okd=fn($x)=>(int)$x===3; $sv=fn($x)=>(int)$x===3?'ok':((int)$x===4?'warn':((int)$x>=5?'fail':'warn'));
 if($pv==='dell'){ $B='1.3.6.1.4.1.674.10892.5.4.';
  $nm=bmc_tbl($h,$g,$B.'700.20.1.8'); $rd=bmc_tbl($h,$g,$B.'700.20.1.6'); if($rd){ $o['temps']=[]; foreach($rd as $i=>$x) if(is_numeric($x)&&(float)$x!==0.0) $o['temps'][]=['n'=>($nm[$i]??'')?:'Temp '.$i,'v'=>round((float)$x/10,1)]; }
  $nm=bmc_tbl($h,$g,$B.'700.12.1.8'); $st=bmc_tbl($h,$g,$B.'700.12.1.5'); $rd=bmc_tbl($h,$g,$B.'700.12.1.6'); if($st){ $o['fans']=[]; foreach($st as $i=>$x) $o['fans'][]=['n'=>($nm[$i]??'')?:'Fan '.$i,'ok'=>$okd($x),'rpm'=>isset($rd[$i])&&is_numeric($rd[$i])?(int)$rd[$i]:null]; }
  $nm=bmc_tbl($h,$g,$B.'600.12.1.8'); $st=bmc_tbl($h,$g,$B.'600.12.1.5'); $wt=bmc_tbl($h,$g,$B.'600.12.1.6'); if($st){ $o['psu']=[]; foreach($st as $i=>$x) $o['psu'][]=['n'=>($nm[$i]??'')?:'PSU '.$i,'ok'=>$okd($x),'w'=>isset($wt[$i])&&is_numeric($wt[$i])&&(float)$wt[$i]>0?(float)$wt[$i]:null]; }
  $nm=bmc_tbl($h,$g,$B.'600.30.1.8'); foreach(bmc_tbl($h,$g,$B.'600.30.1.6') as $i=>$x) if(is_numeric($x)&&preg_match('/pwr|power|consumption|watt/i',(string)($nm[$i]??''))&&(float)$x>0) $o['pwr'][]=['n'=>$nm[$i],'v'=>(float)$x];
  $nm=bmc_tbl($h,$g,$B.'600.20.1.8'); foreach(bmc_tbl($h,$g,$B.'600.20.1.6') as $i=>$x) if(is_numeric($x)&&(float)$x>0&&isset($nm[$i])) $o['volts'][]=['n'=>$nm[$i],'v'=>round((float)$x/1000,3)]; }
 if($pv==='hpe'){ $loc=[1=>'Other',2=>'Unknown',3=>'System',4=>'System board',5=>'I/O board',6=>'CPU',7=>'Memory',8=>'Storage',9=>'Removable media',10=>'Power supply',11=>'Ambient',12=>'Chassis',13=>'Bridge card'];
  $lc=bmc_tbl($h,$g,'1.3.6.1.4.1.232.6.2.6.8.1.3'); $rd=bmc_tbl($h,$g,'1.3.6.1.4.1.232.6.2.6.8.1.4'); if($rd){ $o['temps']=[]; foreach($rd as $i=>$x) if(is_numeric($x)&&(float)$x>0&&(float)$x<200) $o['temps'][]=['n'=>($loc[(int)($lc[$i]??0)]??'Sensor').' '.$i,'v'=>(float)$x]; } }

 // Huawei iBMC (HUAWEI-SERVER-IBMC-MIB, 1.3.6.1.4.1.2011.2.235.1.1)
 if($pv!=='dell'&&$pv!=='hpe'&&(sget($h,$g,'1.3.6.1.4.1.2011.2.235.1.1.26.1.0')!==null||sget($h,$g,'1.3.6.1.4.1.2011.2.235.1.1.1.1.0')!==null)){ $H='1.3.6.1.4.1.2011.2.235.1.1.'; $hs=fn($x)=>(int)$x===1?'ok':((int)$x===2?'warn':((int)$x>=3&&(int)$x<=4?'fail':null)); $stt=[1=>'OK',2=>'Minor',3=>'Major',4=>'Critical'];
  foreach([['1.0',t('System','System'),'system'],['15.1.0','CPU','cpu'],['16.1.0',t('Minne','Memory'),'mem'],['18.1.0',t('Diskar','Drives'),'disk'],['24.1.0','PCIe','system'],['26.1.0',t('Temperatur','Temperature'),'system'],['6.1.0',t('Nätdelar','Power supplies'),'psu'],['8.3.0',t('Fläktar','Fans'),'fan']] as [$so,$nmx,$kx]){ $x=sget($h,$g,$H.($so==='1.0'?'1.1.0':$so)); if($x===null||!is_numeric($x)) continue; $st=$hs($x); if($st===null) continue; $o['health'][]=st_row($nmx,$st,$stt[(int)$x]??'status '.$x,$kx); }
  $nm=bmc_tbl($h,$g,$H.'26.50.1.2'); $o['temps']=[]; foreach(bmc_tbl($h,$g,$H.'26.50.1.3') as $i=>$x) if(is_numeric($x)&&(float)$x>0&&(float)$x/10<200) $o['temps'][]=['n'=>($nm[$i]??'')?:'Temp '.$i,'v'=>round((float)$x/10,1)];
  $lc=bmc_tbl($h,$g,$H.'8.50.1.7')+bmc_tbl($h,$g,$H.'8.50.1.5'); $sp=bmc_tbl($h,$g,$H.'8.50.1.2'); $sr=bmc_tbl($h,$g,$H.'8.50.1.8'); $fs=bmc_tbl($h,$g,$H.'8.50.1.4'); $o['fans']=[]; $fp=bmc_tbl($h,$g,$H.'8.50.1.3'); foreach($lc+$sp+$sr as $i=>$_){ if(isset($fp[$i])&&is_numeric($fp[$i])&&(int)$fp[$i]!==2) continue; if(isset($fs[$i])&&is_numeric($fs[$i])){ $st=$hs($fs[$i]); if($st===null) continue; $ok=$st==='ok'; } else { $ok=(float)($sp[$i]??$sr[$i]??0)>0; if(!$ok&&!isset($sp[$i])&&!isset($sr[$i])) continue; } $o['fans'][]=['n'=>($lc[$i]??'')?:'Fan '.$i,'ok'=>$ok,'rpm'=>isset($sp[$i])&&is_numeric($sp[$i])&&(float)$sp[$i]>0?(int)$sp[$i]:null,'pct'=>isset($sr[$i])&&is_numeric($sr[$i])?(int)$sr[$i]:null]; }
  $lc=bmc_tbl($h,$g,$H.'6.50.1.13')+bmc_tbl($h,$g,$H.'6.50.1.11'); $pp=bmc_tbl($h,$g,$H.'6.50.1.9'); $ps=bmc_tbl($h,$g,$H.'6.50.1.7'); $ip=bmc_tbl($h,$g,$H.'6.50.1.8'); $rt=bmc_tbl($h,$g,$H.'6.50.1.6'); $o['psu']=[]; foreach($lc+$ps+$ip as $i=>$_){ if(isset($pp[$i])&&is_numeric($pp[$i])&&(int)$pp[$i]!==2) continue; if(isset($ps[$i])&&is_numeric($ps[$i])){ $st=$hs($ps[$i]); if($st===null) continue; $ok=$st==='ok'; } else $ok=true; $w=isset($ip[$i])&&is_numeric($ip[$i])&&(float)$ip[$i]>0?(float)$ip[$i]:null; $o['psu'][]=['n'=>($lc[$i]??'')?:'PSU '.$i,'ok'=>$ok,'w'=>$w,'max'=>isset($rt[$i])&&is_numeric($rt[$i])?(int)$rt[$i]:null]; }
  $pw=sget($h,$g,$H.'1.13.0'); if(is_numeric($pw)&&(float)$pw>0){ $o['pwr'][]=['n'=>t('Systemeffekt','System power'),'v'=>(float)$pw]; $o['watts']=(float)$pw; }
  foreach([['20.3.0',t('Medeleffekt','Average power')],['20.1.0',t('Toppeffekt','Peak power')]] as [$so,$nmx]){ $x=sget($h,$g,$H.$so); if($x!==null&&preg_match('/(\d+(?:[.,]\d+)?)/',$x,$mm)&&(float)$mm[1]>0) $o['pwr'][]=['n'=>$nmx,'v'=>(float)str_replace(',','.',$mm[1])]; }
  if($o['watts']===null){ $s=array_sum(array_map(fn($z)=>$z['w']??0,$o['psu'])); if($s>0) $o['watts']=round($s,1); }
  $c=sget($h,$g,$H.'1.23.0'); if(is_numeric($c)) $o['cpu']=(float)$c; $m=sget($h,$g,$H.'1.25.0'); if(is_numeric($m)) $o['mem']=(float)$m;
  $ed=bmc_tbl($h,$g,$H.'1.50.1.5'); $es=bmc_tbl($h,$g,$H.'1.50.1.4'); $en=bmc_tbl($h,$g,$H.'1.50.1.2'); $k=0; foreach($ed as $i=>$x){ if(++$k>8) break; $o['health'][]=st_row(($en[$i]??'')?:'Alarm',((int)($es[$i]??0))>=3?'fail':'warn',mb_substr((string)$x,0,140),'alert'); } }
 // standard ENTITY-SENSOR-MIB (RFC 3433) – fyller på det som saknas (Huawei iBMC, Supermicro, Lenovo XCC m.fl. som exponerar den)
 $ty=bmc_tbl($h,$g,'1.3.6.1.2.1.99.1.1.1.1'); if($ty){ $sc=bmc_tbl($h,$g,'1.3.6.1.2.1.99.1.1.1.2'); $pc=bmc_tbl($h,$g,'1.3.6.1.2.1.99.1.1.1.3'); $va=bmc_tbl($h,$g,'1.3.6.1.2.1.99.1.1.1.4'); $os=bmc_tbl($h,$g,'1.3.6.1.2.1.99.1.1.1.5'); $en=bmc_tbl($h,$g,'1.3.6.1.2.1.47.1.1.1.1.7'); $ed=bmc_tbl($h,$g,'1.3.6.1.2.1.47.1.1.1.1.2');
  $have=fn($arr,$n)=>(bool)array_filter($arr,fn($z)=>$z['n']===$n);
  foreach($ty as $i=>$x){ if(!isset($va[$i])||!is_numeric($va[$i])) continue; $real=(float)$va[$i]*pow(10,((int)($sc[$i]??9)-9)*3)/pow(10,(int)($pc[$i]??0)); $n=trim((string)($en[$i]??''))?:(trim((string)($ed[$i]??''))?:'#'.$i); $ok=(int)($os[$i]??1)===1; $x=(int)$x;
   if($x===8&&$real>0&&$real<200&&!$have($o['temps'],$n)) $o['temps'][]=['n'=>$n,'v'=>round($real,1)];
   elseif($x===6&&$real>=0&&!$have($o['pwr'],$n)&&!preg_match('/^(PS|PSU)\d*\s*(Out|Output)/i',$n)) $o['pwr'][]=['n'=>$n,'v'=>round($real,1)];
   elseif(($x===3||$x===4)&&!$have($o['volts'],$n)) $o['volts'][]=['n'=>$n,'v'=>round($real,3)];
   elseif($x===10&&!$have($o['fans'],$n)) $o['fans'][]=['n'=>$n,'ok'=>$ok&&$real>0,'rpm'=>(int)$real]; } }
 if($o['watts']===null&&$o['pwr']){ $best=null; foreach($o['pwr'] as $pw) if(preg_match('/system|total|consumption|chassis|input/i',$pw['n'])){ $best=$pw['v']; break; } $o['watts']=$best!==null?$best:round(array_sum(array_column(array_filter($o['pwr'],fn($z)=>!preg_match('/average|peak|medel|topp/i',$z['n'])),'v')),1); }
 elseif($o['watts']===null&&$o['psu']){ $s=array_sum(array_map(fn($z)=>$z['w']??0,$o['psu'])); if($s>0) $o['watts']=round($s,1); }
 return $o; }
// Routingtabell via SNMP (hämtas live när man klickar, sparas inte): IP-FORWARD-MIB ipCidrRouteTable, annars gamla ipRouteTable.
function snmp_walk_suffix($h,$g,$oid,$max=2500){ if(($g['v']??2)===2&&class_exists('SNMP')){ try{ $s=new SNMP(SNMP::VERSION_2c,snmp_hp($h,$g),$g['c'],5000000,1); $s->oid_output_format=SNMP_OID_OUTPUT_NUMERIC; $s->valueretrieval=SNMP_VALUE_PLAIN; $s->quick_print=true; $w=@$s->walk($oid,true,25); $s->close(); if(is_array($w)){ $o=[]; foreach($w as $k=>$v){ $o[ltrim((string)$k,'.')]=is_string($v)?trim($v,"\" \r\n"):$v; if(count($o)>=$max) break; } return $o; } }catch(Throwable $e){} }
 return bmc_tbl($h,$g,$oid); }
function route_table($h,$g,$names=[],$max=2000){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true);
 $TY=[1=>'other',2=>'reject',3=>'local',4=>'remote']; $PR=[1=>'other',2=>'local',3=>'static',4=>'icmp',5=>'egp',6=>'ggp',7=>'hello',8=>'rip',9=>'is-is',10=>'es-is',11=>'igrp',12=>'bbn-spf',13=>'ospf',14=>'bgp',15=>'idpr',16=>'eigrp'];
 $pl=function($m){ $b=0; foreach(explode('.',$m) as $x) $b+=substr_count(decbin((int)$x),'1'); return $b; }; $rows=[]; $tr=false; $B='1.3.6.1.2.1.4.24.4.1.';
 $ifx=snmp_walk_suffix($h,$g,$B.'5',$max+1);
 if($ifx){ $ty=snmp_walk_suffix($h,$g,$B.'6',$max+1); $pr=snmp_walk_suffix($h,$g,$B.'7',$max+1); $me=snmp_walk_suffix($h,$g,$B.'11',$max+1);
  foreach($ifx as $k=>$ix){ $p=explode('.',$k); if(count($p)<13) continue; if(count($rows)>=$max){ $tr=true; break; } $ix=(int)$ix;
   $rows[]=['dst'=>implode('.',array_slice($p,0,4)),'len'=>$pl(implode('.',array_slice($p,4,4))),'gw'=>implode('.',array_slice($p,9,4)),'if'=>$ix>0?($names[$ix]??('#'.$ix)):'','type'=>$TY[(int)($ty[$k]??0)]??'','proto'=>$PR[(int)($pr[$k]??0)]??'','metric'=>isset($me[$k])&&is_numeric($me[$k])&&(int)$me[$k]>=0?(int)$me[$k]:null]; } }
 else { $B='1.3.6.1.2.1.4.21.1.'; $ifx=snmp_walk_suffix($h,$g,$B.'2',$max+1);
  if($ifx){ $nh=snmp_walk_suffix($h,$g,$B.'7',$max+1); $mk=snmp_walk_suffix($h,$g,$B.'11',$max+1); $ty=snmp_walk_suffix($h,$g,$B.'8',$max+1); $pr=snmp_walk_suffix($h,$g,$B.'9',$max+1); $me=snmp_walk_suffix($h,$g,$B.'3',$max+1);
   foreach($ifx as $k=>$ix){ if(count($rows)>=$max){ $tr=true; break; } $ix=(int)$ix; $rows[]=['dst'=>$k,'len'=>$pl((string)($mk[$k]??'0.0.0.0')),'gw'=>(string)($nh[$k]??''),'if'=>$ix>0?($names[$ix]??('#'.$ix)):'','type'=>$TY[(int)($ty[$k]??0)]??'','proto'=>$PR[(int)($pr[$k]??0)]??'','metric'=>isset($me[$k])&&is_numeric($me[$k])&&(int)$me[$k]>=0?(int)$me[$k]:null]; } } }
 usort($rows,function($a,$b){ return ip2long($a['dst'])<=>ip2long($b['dst'])?:($a['len']<=>$b['len']); }); return [$rows,$tr]; }
function nw_collect($s,$cn){ if(!function_exists('snmp2_walk')) return ['ok'=>false,'error'=>t('PHP SNMP-tillägget saknas','PHP SNMP extension missing')];
 $h=$s['host']; $g=snmp_cfg($cn['snmp']??'default'); $v=$s['nw_type']; $role=$s['nw_role']?:'switch'; $skip=in_array($role,['switch','ap'],true)?[53,135,136,131]:[];
 snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); error_clear_last(); $sd=@snmp_gt($h,$g,'1.3.6.1.2.1.1.1.0');
 if($sd===false||$sd===null) return ['ok'=>false,'error'=>"SNMP $h: ".(error_get_last()['message']??t('ingen respons – kontrollera community/SNMPv3, brandvägg och att SNMP tillåter monitor-IP','no response – check community/SNMPv3, firewall and that SNMP allows the monitor IP'))];
 $sd=trim((string)$sd,"\" \r\n"); $cpu=null; $mem=null; $temps=[]; $fans=[]; $psu=[];
 $prev=json_decode((string)($s['nw_data']??''),true); if(!is_array($prev)) $prev=[]; $slowIv=max(60,(int)setting('int_slow',900)); $slowDue=empty($prev['slow_ts'])||!isset($prev['model'])||preg_match('/^Meraki\s+(Switch|AP|Access Point|Security Appliance)$|Cloud Managed/i',(string)$prev['model'])||time()-(int)$prev['slow_ts']>=$slowIv-5; $slowTs=$slowDue?time():(int)$prev['slow_ts']; // sällan ändrade data (modell, serienummer, plats, diskar på brandvägg/Cloud Key) läses bara vart int_slow:e sekund
 if($v==='cisco'){ foreach(['7','8','6'] as $o){ $cpu=nw_max(snmp_table($h,$g,'1.3.6.1.4.1.9.9.109.1.1.1.1.'.$o)); if($cpu!==null) break; }
  $u=array_sum(array_map('floatval',snmp_table($h,$g,'1.3.6.1.4.1.9.9.48.1.1.1.5'))); $f=array_sum(array_map('floatval',snmp_table($h,$g,'1.3.6.1.4.1.9.9.48.1.1.1.6'))); if($u+$f>0) $mem=round($u/($u+$f)*100,1);
  $nm=snmp_table($h,$g,'1.3.6.1.4.1.9.9.13.1.3.1.2'); foreach(snmp_table($h,$g,'1.3.6.1.4.1.9.9.13.1.3.1.3') as $i=>$x) if((float)$x>0&&(float)$x<150) $temps[]=['n'=>trim((string)($nm[$i]??'#'.$i)),'v'=>(float)$x];
  $nm=snmp_table($h,$g,'1.3.6.1.4.1.9.9.13.1.4.1.2'); foreach(snmp_table($h,$g,'1.3.6.1.4.1.9.9.13.1.4.1.3') as $i=>$x) if((int)$x!==5) $fans[]=['n'=>trim((string)($nm[$i]??'Fan '.$i)),'ok'=>(int)$x===1];
  $nm=snmp_table($h,$g,'1.3.6.1.4.1.9.9.13.1.5.1.2'); foreach(snmp_table($h,$g,'1.3.6.1.4.1.9.9.13.1.5.1.3') as $i=>$x) if((int)$x!==5) $psu[]=['n'=>trim((string)($nm[$i]??'PSU '.$i)),'ok'=>(int)$x===1]; }
 elseif($v==='hp'){ $cpu=nw_num(sget($h,$g,'1.3.6.1.4.1.11.2.14.11.5.1.9.6.1.0')); $tt=nw_num(sget($h,$g,'1.3.6.1.4.1.11.2.14.11.5.1.1.2.1.1.1.5.1')); $al=nw_num(sget($h,$g,'1.3.6.1.4.1.11.2.14.11.5.1.1.2.1.1.1.7.1')); if($tt&&$al!==null) $mem=round($al/$tt*100,1);
  $ds=snmp_table($h,$g,'1.3.6.1.4.1.11.2.14.11.1.2.6.1.7'); foreach(snmp_table($h,$g,'1.3.6.1.4.1.11.2.14.11.1.2.6.1.4') as $i=>$x){ if((int)$x===5||(int)$x===1) continue; $n=trim((string)($ds[$i]??'#'.$i)); $ok=(int)$x===4; if(preg_match('/fan/i',$n)) $fans[]=['n'=>$n,'ok'=>$ok]; elseif(preg_match('/power|psu|supply/i',$n)) $psu[]=['n'=>$n,'ok'=>$ok]; } }
 elseif($v==='huawei'){ $cpu=nw_max(snmp_table($h,$g,'1.3.6.1.4.1.2011.5.25.31.1.1.1.1.5')); $mem=nw_max(snmp_table($h,$g,'1.3.6.1.4.1.2011.5.25.31.1.1.1.1.7')); $nm=snmp_table($h,$g,'1.3.6.1.2.1.47.1.1.1.1.7'); foreach(snmp_table($h,$g,'1.3.6.1.4.1.2011.5.25.31.1.1.1.1.11') as $i=>$x) if((float)$x>0&&(float)$x<150) $temps[]=['n'=>trim((string)($nm[$i]??'#'.$i)),'v'=>(float)$x]; }
 if($v==='synology'){ $tp=nw_num(sget($h,$g,'1.3.6.1.4.1.6574.1.2.0')); if($tp!==null&&$tp>0) $temps[]=['n'=>t('System','System'),'v'=>$tp]; foreach(['1.3.6.1.4.1.6574.1.4.1.0'=>t('Systemfläkt','System fan'),'1.3.6.1.4.1.6574.1.4.2.0'=>t('CPU-fläkt','CPU fan')] as $o=>$n){ $x=nw_num(sget($h,$g,$o)); if($x!==null) $fans[]=['n'=>$n,'ok'=>(int)$x===1]; } $x=nw_num(sget($h,$g,'1.3.6.1.4.1.6574.1.3.0')); if($x!==null) $psu[]=['n'=>t('Nätdel','Power supply'),'ok'=>(int)$x===1]; }
 if($cpu===null){ $hc=array_map('floatval',snmp_walk($h,$g,'1.3.6.1.2.1.25.3.3.1.2')); if($hc) $cpu=round(array_sum($hc)/count($hc),1); }
 if($cpu===null||$mem===null){ [$uc,$um]=nw_ucd($h,$g); $cpu??=$uc; $mem??=$um; } if($mem===null) $mem=nw_hrmem($h,$g);
 if(!$temps){ $ty=snmp_table($h,$g,'1.3.6.1.2.1.99.1.1.1.1'); if($ty){ $va=snmp_table($h,$g,'1.3.6.1.2.1.99.1.1.1.4'); $nm=snmp_table($h,$g,'1.3.6.1.2.1.47.1.1.1.1.7'); foreach($ty as $i=>$x) if((int)$x===8&&isset($va[$i])&&(float)$va[$i]>0&&(float)$va[$i]<150) $temps[]=['n'=>trim((string)($nm[$i]??'#'.$i)),'v'=>(float)$va[$i]]; } }
 if(!$temps&&$v==='cisco'){ $ty=snmp_table($h,$g,'1.3.6.1.4.1.9.9.91.1.1.1.1.1'); if($ty){ $va=snmp_table($h,$g,'1.3.6.1.4.1.9.9.91.1.1.1.1.4'); $nm=snmp_table($h,$g,'1.3.6.1.2.1.47.1.1.1.1.7'); foreach($ty as $i=>$x) if((int)$x===8&&isset($va[$i])&&(float)$va[$i]>0&&(float)$va[$i]<150) $temps[]=['n'=>trim((string)($nm[$i]??'#'.$i)),'v'=>(float)$va[$i]]; } }
 if(!$temps||!$fans){ $sx=snmp_sensors($h,$g); if(!$temps) $temps=$sx['temps']; if(!$fans) foreach($sx['fans'] as $fx) $fans[]=['n'=>$fx['n'],'ok'=>$fx['ok'],'rpm'=>$fx['rpm']]; }
 $temp=$temps?max(array_column($temps,'v')):null; if(count($temps)>12) $temps=array_slice($temps,0,12);
 if(!$slowDue){ $model=$prev['model']; $idn=['hostname'=>(string)($prev['hostname']??''),'serial'=>(string)($prev['serial']??''),'location'=>(string)($prev['location']??''),'contact'=>(string)($prev['contact']??'')]; } else {
 $model=sget($h,$g,'1.3.6.1.2.1.47.1.1.1.1.13.1')?:sget($h,$g,'1.3.6.1.2.1.47.1.1.1.1.13.1001')?:sget($h,$g,'1.3.6.1.2.1.47.1.1.1.1.2.1')?:substr(trim(preg_split('/[,\r\n]/',$sd)[0]),0,70);
 if(preg_match('/^FreeBSD\s+\S+\s+(\d[\w.\-]*)/',$sd,$fm)) $model='FreeBSD '.$fm[1];
 if($v==='truenas'&&preg_match('/(?:TrueNAS|FreeNAS)[\w.\-]*\w/i',$sd,$tm)) $model=$tm[0];
 if($v==='synology'){ $sm=sget($h,$g,'1.3.6.1.4.1.6574.1.5.1.0'); if($sm) $model='Synology '.trim((string)$sm,"\" "); }
 if($v==='meraki'||stripos($sd,'meraki')!==false||stripos((string)$model,'meraki')===0){ $mm=''; $mre='/\b((?:MS|MR|MX|MG|MV|MT|CW|CS|Z)\d[\w\-]*)/i';
  if(preg_match('/Meraki\s+((?:MS|MR|MX|MG|MV|MT|CW|CS|Z)\d[\w\-]*)/i',$sd,$mx)) $mm=$mx[1];
  if($mm===''){ foreach((array)@snmp_walk($h,$g,'1.3.6.1.2.1.47.1.1.1.1.13') as $ev){ $ev=trim((string)$ev,"\" "); if($ev!==''&&preg_match($mre,$ev,$mx)){ $mm=$mx[1]; break; } } }
  if($mm===''){ try{ $ip0=preg_replace('/:\d+$/','',(string)$h); foreach(db()->query("SELECT nw_data FROM servers WHERE nw_data LIKE '%\"meraki\"%'") as $o0){ $j0=json_decode((string)$o0['nw_data'],true); foreach((array)($j0['meraki']['devs']??[]) as $d0){ if(($d0['ip']??'')===$ip0&&($d0['pc']??'')!==''){ $mm=(string)$d0['pc']; break 2; } } } }catch(Throwable $e){} }
  if($mm!=='') $model='Meraki '.$mm; else $model=trim(preg_replace('/\s+Cloud Managed.*$/i','',(string)$model)); }
 $idn=['hostname'=>(string)sget($h,$g,'1.3.6.1.2.1.1.5.0'),'serial'=>(string)sget($h,$g,'1.3.6.1.2.1.47.1.1.1.1.11.1'),'location'=>(string)sget($h,$g,'1.3.6.1.2.1.1.6.0'),'contact'=>(string)sget($h,$g,'1.3.6.1.2.1.1.4.0')]; }
 if(!$slowDue){ $hn0=trim((string)sget($h,$g,'1.3.6.1.2.1.1.5.0'),"\" \r\n"); if($hn0!=='') $idn['hostname']=$hn0; } // namnet följer enheten vid varje pollning, inte bara i den långsamma rundan
 $stx=[]; if($role==='bmc'){ $pb=bmc_probe($h,$g,$v,$cn); if($pb['cpu']!==null) $cpu=$pb['cpu']; if($pb['mem']!==null) $mem=$pb['mem']; $temps=$pb['temps']; $fans=$pb['fans']; $psu=$pb['psu']; $temp=$temps?max(array_column($temps,'v')):$temp; $stx=['health'=>$pb['health'],'pwr'=>$pb['pwr'],'volts'=>$pb['volts'],'watts'=>$pb['watts']];} elseif(in_array($role,['firewall','cloudkey'],true)){ $stx=['disks'=>($slowDue||!isset($prev['disks']))?nw_disks($h,$g):$prev['disks']]; } elseif(nw_is_st($role)){ $pr=st_probe($h,$g,$v,$cn); if($pr['cpu']!==null&&$cpu===null) $cpu=$pr['cpu']; foreach(['temps','fans','psu'] as $kk) if($pr[$kk]) $$kk=array_merge($$kk,$pr[$kk]); $temp=$temps?max(array_column($temps,'v')):$temp; $stx=['disks'=>!empty($pr['pools'])?$pr['pools']:nw_disks($h,$g),'smart'=>(function($a,$b){ $have=array_column($a,'n'); foreach($b as $x) if(!in_array($x['n'],$have,true)) $a[]=$x; return $a; })(smart_snmp($h,$g),$pr['smart']??[]),'health'=>$pr['health']]; }
 if($v==='meraki'||stripos($sd,'meraki')!==false){ $mk=meraki_collect($h,$g); if($mk){ $stx['meraki']=$mk; $stx['health']=[]; foreach($mk['devs'] as $x) if(!$x['on']) $stx['health'][]=st_row($x['n'],'fail',t('offline','offline').($x['ct']?' ('.t('senast sedd ','last seen ').$x['ct'].')':''),'device'); } }
 $wifi=($role==='ap'||($v==='unifi'&&$role!=='cloudkey'))?nw_wifi($h,$g,$v):null; $ifr=($role==='ap'||$wifi)?'ap':$role; $skip=in_array($ifr,['switch','ap'],true)?[53,135,136,131]:[]; $ifs=snmp_ifaces($h,$g,128,$skip,$ifr); $poe=(!nw_is_st($role)&&$role!=='firewall')?nw_poe($h,$g,$ifs,$v):null;
 return ['ok'=>true,'ifs'=>$ifs,'data'=>['vendor'=>$v,'role'=>$role,'model'=>substr((string)$model,0,80),'descr'=>substr($sd,0,300),'hostname'=>$idn['hostname'],'serial'=>$idn['serial'],'uptime'=>ticks(sget($h,$g,'1.3.6.1.2.1.25.1.1.0')??sget($h,$g,'1.3.6.1.2.1.1.3.0')),'location'=>$idn['location'],'contact'=>$idn['contact'],'slow_ts'=>$slowTs,'cpu'=>$cpu,'mem'=>$mem,'temp'=>$temp,'temps'=>$temps,'fans'=>$fans,'psu'=>$psu]+($wifi?['wifi'=>$wifi]:[])+($poe?['poe'=>$poe]:[])+$stx]; }
// Wi-Fi-data från accesspunkt: UniFi (UBNT-UniFi-MIB, 41112.1.6) – klienter per SSID och radiokanaler. Okända/saknade värden hoppas över.
function nw_wifi($h,$g,$v){ $o=['clients'=>null,'ssids'=>[],'radios'=>[]];
 $nm=snmp_table($h,$g,'1.3.6.1.4.1.41112.1.6.1.2.1.6'); $nn=snmp_table($h,$g,'1.3.6.1.4.1.41112.1.6.1.2.1.8'); $rd=snmp_table($h,$g,'1.3.6.1.4.1.41112.1.6.1.2.1.9'); $ch=snmp_table($h,$g,'1.3.6.1.4.1.41112.1.6.1.2.1.4');
 foreach($nn as $i=>$n){ if(!is_numeric($n)) continue; $o['ssids'][]=['n'=>trim((string)($nm[$i]??'')),'c'=>(int)$n,'r'=>trim((string)($rd[$i]??'')),'ch'=>isset($ch[$i])&&is_numeric($ch[$i])?(int)$ch[$i]:null]; }
 if($o['ssids']) $o['clients']=array_sum(array_column($o['ssids'],'c'));
 $rn=snmp_table($h,$g,'1.3.6.1.4.1.41112.1.6.1.1.1.2'); $rt=snmp_table($h,$g,'1.3.6.1.4.1.41112.1.6.1.1.1.3'); $cu=snmp_table($h,$g,'1.3.6.1.4.1.41112.1.6.1.1.1.6');
 foreach($rn as $i=>$n){ $band=['ng'=>'2.4 GHz','na'=>'5 GHz','6e'=>'6 GHz'][strtolower(trim((string)($rt[$i]??'')))]??trim((string)($rt[$i]??'')); $u=isset($cu[$i])&&is_numeric($cu[$i])?(float)$cu[$i]:null; $o['radios'][]=['n'=>trim((string)$n),'b'=>$band,'cu'=>$u!==null&&$u>=0&&$u<=100?$u:null]; }
 return ($o['ssids']||$o['radios'])?$o:null; }
// PoE via standard POWER-ETHERNET-MIB (pethPsePortDetectionStatus / pethMainPse). Portindex tolkas som ifIndex när det går, annars i ordning bland ethernetportarna.
function nw_poe($h,$g,$ifs,$v=''){ $st=snmp_table($h,$g,'1.3.6.1.2.1.105.1.1.1.6'); if(!$st) return null; $ad=snmp_table($h,$g,'1.3.6.1.2.1.105.1.1.1.3'); $idx=array_map(fn($i)=>(int)$i['idx'],$ifs); $phys=[]; foreach($ifs as $i) if((int)$i['type']===6) $phys[]=(int)$i['idx']; sort($phys);
 $keys=array_keys($st); sort($keys); $direct=!array_diff($keys,$idx); $ports=[];
 foreach($keys as $k=>$pi){ $ix=$direct?$pi:($phys[$k]??null); if($ix===null) continue; $ports[$ix]=['s'=>ifnum($st[$pi]),'a'=>isset($ad[$pi])?(ifnum($ad[$pi])===1?1:0):null]; }
 $mw=[]; if($v==='hp') $mw=snmp_table($h,$g,'1.3.6.1.4.1.11.2.14.11.1.9.1.1.1.8'); elseif($v==='cisco') $mw=snmp_table($h,$g,'1.3.6.1.4.1.9.9.402.1.2.1.7'); // verklig effekt per port i mW (HP-ICF-POE-MIB, CISCO-POWER-ETHERNET-EXT-MIB)
 if($mw) foreach($keys as $k=>$pi){ $ix=$direct?$pi:($phys[$k]??null); if($ix!==null&&isset($ports[$ix])&&isset($mw[$pi])&&is_numeric($mw[$pi])&&(float)$mw[$pi]>=0) $ports[$ix]['w']=round((float)$mw[$pi]/1000,1); }
 if(!$ports) return null; $bud=array_map('floatval',snmp_table($h,$g,'1.3.6.1.2.1.105.1.3.1.1.2')); $use=array_map('floatval',snmp_table($h,$g,'1.3.6.1.2.1.105.1.3.1.1.4'));
 $pw=array_sum(array_map(fn($x)=>$x['w']??0,$ports)); return ['ports'=>$ports,'bud'=>$bud?array_sum($bud):null,'use'=>$use?array_sum($use):($mw?round($pw,1):null)]; }
function nw_collect_api($s,$cn){ $pr=tn_probe($s['host'],$cn); if(empty($pr['ok'])) return ['ok'=>false,'error'=>'TrueNAS API: '.($pr['error']?:t('inget svar','no response'))]; $tj=$pr['info'];
 $tm=[]; foreach($pr['smart'] as $x) if($x['temp']!==null) $tm[]=['n'=>$x['n'],'v'=>(float)$x['temp']]; $temp=$tm?max(array_column($tm,'v')):null;
 return ['ok'=>true,'ifs'=>[],'data'=>['vendor'=>'truenas','role'=>$s['nw_role']?:'nas','model'=>substr((string)$tj['version'],0,80),'descr'=>'','hostname'=>(string)$tj['hostname'],'serial'=>'','uptime'=>$tj['uptime'],'location'=>'','contact'=>'','cpu'=>null,'mem'=>null,'temp'=>$temp,'temps'=>[],'fans'=>[],'psu'=>[],'disks'=>$pr['pools'],'smart'=>$pr['smart'],'health'=>$pr['health']]]; }
function poll_network($s){ $pdo=db(); $cn=json_decode((string)$s['nw_conn'],true)?:[]; if(($cn['snmp']??'')==='none'&&$s['nw_type']==='truenas'&&tn_on($cn)){ $own=!isset($GLOBALS['force_lang']); if($own) $GLOBALS['force_lang']=setting('sys_lang','sv'); try{ $r=nw_collect_api($s,$cn); }catch(Throwable $e){ $r=['ok'=>false,'error'=>substr($e->getMessage(),0,200)]; } $new=$r['ok']?'up':'down'; $old=$s['nw_status'];
  $pdo->prepare('UPDATE servers SET nw_poll=NOW(),nw_status=?,nw_error=?'.($r['ok']?',nw_data=?':'').' WHERE id=?')->execute(array_merge([$new,$r['ok']?null:substr((string)$r['error'],0,250)],$r['ok']?[json_encode($r['data'])]:[],[$s['id']]));
  tn_event($s,$new,$r['error']??'');
  if($own) unset($GLOBALS['force_lang']); return $r; }
 if(($cn['snmp']??'')==='none'){ $pdo->prepare('UPDATE servers SET nw_poll=NOW(),nw_status=NULL,nw_error=NULL WHERE id=?')->execute([$s['id']]); return ['ok'=>false,'error'=>t('SNMP är avstängt för enheten (bara ping)','SNMP is disabled for this device (ping only)'),'ifs'=>[]]; } $own=!isset($GLOBALS['force_lang']); if($own) $GLOBALS['force_lang']=setting('sys_lang','sv'); lasterr('');
 $tp0=microtime(true); try{ $r=nw_collect($s,$cn); if(!empty($r['ok'])) $r['data']['poll_ms']=round((microtime(true)-$tp0)*1000); if(!empty($r['ok'])&&function_exists('snmp2_get')){ snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true); $r['data']['custom']=snmp_custom($s['host'],snmp_cfg($cn['snmp']??'default'),$s['id']); } }catch(Throwable $e){ $r=['ok'=>false,'error'=>substr($e->getMessage(),0,200)]; }
 $new=$r['ok']?'up':'down'; $old=$s['nw_status'];
 $pdo->prepare('UPDATE servers SET nw_poll=NOW(),nw_status=?,nw_error=?'.($r['ok']?',nw_data=?':'').' WHERE id=?')->execute(array_merge([$new,$r['ok']?null:substr((string)$r['error'],0,250)],$r['ok']?[json_encode($r['data'])]:[],[$s['id']]));
 applog('debug','snmp',$s['name'].' (SNMP): '.($r['ok']?'OK – '.count($r['ifs']).' interface':($r['error']??'')));
 if($r['ok']&&tn_on($cn)){ $ae=''; foreach($r['data']['health']??[] as $x) if(($x['k']??'')==='api') $ae=$x['txt']; tn_event($s,$ae!==''?'down':'up',$ae); }
 if($r['ok']){ if(!empty($r['ifs'])) record_net($s['id'],$r['ifs']); record_load($s['id'],$r['data']['cpu'],$r['data']['mem']); }
 if($old!==$new&&!($old===null&&$new==='up')){ applog($new==='down'?'warning':'info','snmp',$s['name'].' (SNMP): '.($new==='down'?'not responding – '.($r['error']??''):'responding again')); try{ $pdo->prepare("INSERT INTO check_events(check_id,server_id,server_name,svc,label,target,status,detail) VALUES(NULL,?,?,0,'SNMP','',?,?)")->execute([$s['id'],$s['name'],$new,substr((string)($r['error']??''),0,250)]); }catch(Throwable $e){} }
 if($own) unset($GLOBALS['force_lang']); return $r; }
app_tz_sync();
// ---- Uppdateringskoll mot GitHub (fast repo, VMMON_REPO). gh_http används av update.php och av workern.
function gh_http($url,$file=null){ $c=curl_init($url); $fh=$file?fopen($file,'wb'):null; $o=[CURLOPT_FOLLOWLOCATION=>1,CURLOPT_MAXREDIRS=>5,CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_USERAGENT=>'vmmon/'.VMMON_VERSION,CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS]; if($fh) $o[CURLOPT_FILE]=$fh; else $o[CURLOPT_RETURNTRANSFER]=1; curl_setopt_array($c,$o); $r=curl_exec($c); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); $er=curl_error($c); curl_close($c); if($fh) fclose($fh); return [$code,$r,$er]; }
function gh_latest(&$err){ $err=''; [$code,$body,$er]=gh_http('https://api.github.com/repos/'.VMMON_REPO.'/releases/latest'); $j=$code===200?json_decode((string)$body,true):null;
 if(!is_array($j)||empty($j['tag_name'])){ $err=t('Kunde inte hämta senaste release från GitHub: ','Could not fetch the latest release from GitHub: ').($er?:"HTTP $code"); return null; }
 $tag=ltrim((string)$j['tag_name'],'vV'); if(!preg_match('/^\d+(\.\d+)*$/',$tag)){ $err=t('Releasens tagg är inte ett versionsnummer: ','The release tag is not a version number: ').$j['tag_name']; return null; }
 $url=''; foreach((array)($j['assets']??[]) as $as){ if(preg_match('~^[Vv]mmon[_-].*\.zip$~',(string)($as['name']??''))&&!empty($as['browser_download_url'])){ $url=$as['browser_download_url']; break; } } if($url==='') $url=(string)($j['zipball_url']??'');
 return ['tag'=>$tag,'url'=>strpos($url,'https://')===0?$url:'']; }
// Kollar GitHub högst var 12:e timme (2 gånger per dygn); vid fel nytt försök efter 1 timme. Larmar en gång per ny version.
function upd_auto_check($force=false){ $now=time(); $last=(int)setting('upd_chk_ts','0'); $okLast=setting('upd_chk_err','')==='';
 if(!$force&&$now-$last<($okLast?43200:3600)) return null; set_setting('upd_chk_ts',$now);
 $r=gh_latest($er); if(!$r){ set_setting('upd_chk_err',$er); return null; } set_setting('upd_chk_err',''); set_setting('upd_latest',$r['tag']);
 if(version_compare($r['tag'],VMMON_VERSION,'>')){ if(setting('upd_alert','0')==='1'&&setting('upd_notified','')!==$r['tag']){ upd_notify($r['tag']); set_setting('upd_notified',$r['tag']); } }
 return $r; }
function upd_notify($tag){ $subj=sprintf(t('[UPPDATERING] VM Monitor %s finns','[UPDATE] VM Monitor %s is available'),$tag); $body=sprintf(t("En ny version av VM Monitor finns: %s (installerad: %s).\nInstallera den under Inställningar → Uppdatera.","A new version of VM Monitor is available: %s (installed: %s).\nInstall it under Settings → Update."),$tag,VMMON_VERSION); $res=[];
 if(setting('upd_n_email','0')==='1'){ $e=send_mail(setting('alarm_contacts',''),$subj,$body); $res[]='Email: '.($e?:'OK'); }
 if(setting('upd_n_tg','0')==='1'){ $e=tg_send('🔔 '.$subj."\n".$body); $res[]='Telegram: '.($e?:'OK'); }
 if(setting('upd_n_wh','0')==='1'){ $e=wh_send(['event'=>'update_available','subject'=>$subj,'version'=>$tag,'installed'=>VMMON_VERSION,'time'=>date('c')],$subj."\n".$body); $res[]='Webhook: '.($e?:'OK'); }
 if(setting('upd_n_signal','0')==='1'){ $e=signal_send($subj."\n".$body); $res[]='Signal: '.($e?:'OK'); }
 if(setting('upd_n_sms','0')==='1'){ $e=sms_send($subj); $res[]='SMS: '.($e?:'OK'); }
 $bad=array_filter($res,fn($x)=>!preg_match('/: OK$/',$x)); applog($bad?'error':'info','notify','update '.$tag.': '.($res?implode(', ',$res):'no channel selected')); }

