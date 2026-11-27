<?php // Zabbix-agent i active-läge. Agenten ansluter till VM Monitor (port 10051, ServerActive=) i stället för att VM Monitor ansluter till agenten (port 10050).
// Det här är bara protokolldelen och lagringen av värden. Lyssnaren som tar emot anslutningarna är zbx-active.php, och zbx() i lib.php läser värdena härifrån för servrar i active-läge.
// Allt SQL här är medvetet enkelt (inga MySQL-specifika funktioner) så att samma kod går att testa mot SQLite.

function zbxa_on(){ return setting('zbx_active_on','0')==='1'; }
function zbxa_delay(){ return max(10,(int)setting('zbx_active_delay',60)); }
// host (adress) -> server-id för servrar i active-läge. Cachas i 30 s så att varje zbx()-anrop inte behöver en fråga.
function zbxa_map($reset=false){ static $c=null,$ts=0; if($reset||$c===null||time()-$ts>30){ $c=[]; $ts=time(); try{ foreach(db()->query('SELECT id,host FROM servers WHERE zbx_active=1') as $r) $c[strtolower(trim((string)$r['host']))]=(int)$r['id']; }catch(Throwable $e){} } return $c; }
function zbxa_waiting(){ return t('Zabbix active: väntar på första värdet från agenten (agenten hämtar sin lista var 2:a minut)','Zabbix active: waiting for the first value from the agent (the agent refreshes its list every 2 minutes)'); }
// Läser ett värde som agenten skickat. Anropet registrerar också nyckeln, så att agenten får den i sin lista vid nästa hämtning.
function zbxa_get($sid,$key){ $pdo=db(); $kh=md5($key); $q=$pdo->prepare('SELECT val,ts,req FROM zbx_active WHERE server_id=? AND kh=?'); $q->execute([$sid,$kh]); $r=$q->fetch();
 if(!$r){ $pdo->prepare('INSERT INTO zbx_active(server_id,kh,k,val,ts,req) VALUES(?,?,?,NULL,NULL,?)')->execute([$sid,$kh,substr($key,0,500),time()]); lasterr(zbxa_waiting()); return null; }
 if((int)$r['req']<time()-3600) $pdo->prepare('UPDATE zbx_active SET req=? WHERE server_id=? AND kh=?')->execute([time(),$sid,$kh]);
 if($r['ts']===null){ lasterr(zbxa_waiting()); return null; }
 $age=time()-(int)$r['ts']; if($age>max(300,3*zbxa_delay())){ lasterr(t('Zabbix active: inget nytt värde från agenten på ','Zabbix active: no new value from the agent for ').round($age/60).' min'); return null; }
 $v=(string)$r['val']; if(strpos($v,'ZBX_NOTSUPPORTED')===0){ lasterr(t('agenten: ','agent: ').substr(str_replace("\0",' ',$v),0,150)); return null; }
 return trim($v); }

// Senast mottagna värde utan att registrera något (för visning). Returnerar null om inget finns eller om värdet är för gammalt.
function zbxa_val($sid,$key){ try{ $q=db()->prepare('SELECT val,ts FROM zbx_active WHERE server_id=? AND kh=?'); $q->execute([$sid,md5($key)]); $r=$q->fetch(); }catch(Throwable $e){ return null; } if(!$r||$r['ts']===null||time()-(int)$r['ts']>max(300,3*zbxa_delay())||strpos((string)$r['val'],'ZBX_NOTSUPPORTED')===0) return null; return trim((string)$r['val']); }
function zbxa_seen($sid,$req,$info=''){ try{ set_setting('zbx_seen_'.(int)$sid,json_encode(['ts'=>time(),'req'=>$req,'info'=>mb_substr((string)$info,0,200)])); }catch(Throwable $e){} }

// ---- Protokoll
function zbxp_frame($arr){ $j=json_encode($arr,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); return "ZBXD\x01".pack('V',strlen($j)).pack('V',0).$j; }
// Tolkar en ram ur bufferten. Returnerar ['need'] (mer data behövs), ['bad'] eller ['ok',$data,$antalByteMedHeader].
function zbxp_parse($buf){ if(strlen($buf)<5) return ['need']; if(strncmp($buf,'ZBXD',4)!==0) return ['bad']; $fl=ord($buf[4]); if(!($fl&1)) return ['bad']; $large=($fl&4)>0; $hl=$large?21:13; if(strlen($buf)<$hl) return ['need'];
 if($large){ $len=unpack('P',substr($buf,5,8))[1]; $ul=unpack('P',substr($buf,13,8))[1]; } else { $len=unpack('V',substr($buf,5,4))[1]; $ul=unpack('V',substr($buf,9,4))[1]; }
 if($len<0||$len>16777216||$ul<0||$ul>67108864) return ['bad']; if(strlen($buf)<$hl+$len) return ['need']; $d=substr($buf,$hl,$len);
 if($fl&2){ $d=function_exists('gzuncompress')?@gzuncompress($d,$ul?:0):false; if($d===false) return ['bad']; }
 $j=json_decode($d,true); if(!is_array($j)) return ['bad']; return ['ok',$j,$hl+$len]; }
// Servern en agent hör ihop med: Hostname= i agentens konfiguration mot "Agentens värdnamn" på servern (tomt = serverns namn), eller serverns adress.
function zbxa_find($host){ $h=strtolower(trim((string)$host)); if($h==='') return 0; $q=db()->prepare("SELECT id FROM servers WHERE zbx_active=1 AND (LOWER(zbx_host)=? OR (COALESCE(zbx_host,'')='' AND LOWER(name)=?) OR LOWER(host)=?) ORDER BY id LIMIT 1"); $q->execute([$h,$h,$h]); $id=$q->fetchColumn(); return $id?(int)$id:0; }
function zbxa_ip_ok($sid,$ip){ $q=db()->prepare('SELECT host FROM servers WHERE id=?'); $q->execute([$sid]); $h=trim((string)$q->fetchColumn()); if($h==='') return false; if($h===$ip) return true; if(filter_var($h,FILTER_VALIDATE_IP)) return false; $r=@gethostbynamel($h); return is_array($r)&&in_array($ip,$r,true); }
// Nycklar som en servers kontroller behöver. Läggs in direkt så att agenten får dem första gången den hämtar sin lista, utan att vänta på en pollning.
function zbxa_seed($sid){ $pdo=db(); $it=zbx_items(); $keys=['agent.ping','system.uptime','system.swap.size[,total]','system.swap.size[,pfree]'];
 foreach($pdo->query("SELECT kind,target FROM server_checks WHERE server_id=".(int)$sid." AND source='zabbix' AND enabled=1") as $c){ $k=$c['kind']; $d=$it[$k]??null; if(!$d) continue;
  if($k==='cpu') array_push($keys,'system.cpu.util','system.cpu.num'); elseif($k==='mem') array_push($keys,'vm.memory.size[pavailable]','vm.memory.size[total]'); elseif($k==='disk') $keys[]='vfs.fs.discovery'; elseif($d[2]!=='') $keys[]=zbx_key($d[2],(string)$c['target']); }
 foreach(array_unique($keys) as $key){ $kh=md5($key); $q=$pdo->prepare('SELECT 1 FROM zbx_active WHERE server_id=? AND kh=?'); $q->execute([$sid,$kh]); if(!$q->fetchColumn()) $pdo->prepare('INSERT INTO zbx_active(server_id,kh,k,val,ts,req) VALUES(?,?,?,NULL,NULL,?)')->execute([$sid,$kh,substr($key,0,500),time()]); } }
function zbxa_items($sid){ $q=db()->prepare('SELECT k,kh FROM zbx_active WHERE server_id=? AND req>=? ORDER BY k'); $q->execute([$sid,time()-86400]); $o=[]; foreach($q as $r) $o[]=['key'=>$r['k'],'key_orig'=>$r['k'],'itemid'=>(int)hexdec(substr($r['kh'],0,7)),'delay'=>(string)zbxa_delay(),'lastlogsize'=>0,'mtime'=>0]; return $o; }
// Hanterar en begäran. $auth är lyssnarens minne över vilka värdar som har identifierat sig (värdnamn => [ip, tid]). Returnerar [svar, händelsetext|null].
function zbxa_handle($req,$ip,&$auth){ $r=(string)($req['request']??'');
 if($r==='active checks'){ $host=trim((string)($req['host']??'')); $sid=zbxa_find($host); if(!$sid) return [['response'=>'failed','info'=>"host [$host] not found"],"unknown host '$host' from $ip (enable active mode and set the agent hostname on the server)"];
  $tok=trim((string)secret('zbx_active_token')); if($tok!==''){ if(!hash_equals($tok,(string)($req['host_metadata']??''))) return [['response'=>'failed','info'=>'host metadata does not match'],"wrong HostMetadata from $host ($ip)"]; }
  elseif(!zbxa_ip_ok($sid,$ip)) return [['response'=>'failed','info'=>'host not allowed from this address'],"$host connected from $ip, which is not the server's address (set a token under Settings > Zabbix to allow other addresses)"];
  $first=!isset($auth[strtolower($host)]); $auth[strtolower($host)]=['ip'=>$ip,'ts'=>time(),'sid'=>$sid]; zbxa_seed($sid);
  zbxa_seen($sid,'active checks',count(zbxa_items($sid)).' keys in list'); $it=zbxa_items($sid); return [['response'=>'success','config_revision'=>(int)hexdec(substr(md5(json_encode($it)),0,7)),'data'=>$it],$first?"agent $host connected from $ip":null]; }
 if($r==='active check heartbeat') return [['response'=>'success'],null];
 if($r==='agent data'||$r==='sender data'){ $items=is_array($req['data']??null)?$req['data']:[]; $ok=0; $fail=0; $now=time(); $pdo=db(); $bad=null;
  $top=strtolower(trim((string)($req['host']??''))); $imap=[];
  foreach($items as $x){ if(!is_array($x)){ $fail++; continue; }
   // Zabbix 6.4+ skickar värdnamnet en gång för hela paketet och identifierar värdet med itemid; äldre agenter har host och key på varje värde
   $host=strtolower(trim((string)($x['host']??''))); if($host==='') $host=$top;
   if($host===''){ $same=array_keys(array_filter($auth,function($q) use($ip){ return $q['ip']===$ip; })); if(count($same)===1) $host=$same[0]; }
   $a=$auth[$host]??null;
   if(!$a||$a['ip']!==$ip||$now-$a['ts']>86400){ $fail++; $bad=$bad??"data from $host ($ip) refused – the agent has not identified itself with active checks yet"; continue; }
   $key=(string)($x['key']??''); $kh=$key!==''?md5($key):'';
   if($kh===''&&isset($x['itemid'])){ if(!isset($imap[$a['sid']])){ $imap[$a['sid']]=[]; $q=$pdo->prepare('SELECT kh FROM zbx_active WHERE server_id=?'); $q->execute([$a['sid']]); foreach($q as $r0) $imap[$a['sid']][(int)hexdec(substr($r0['kh'],0,7))]=$r0['kh']; } $kh=$imap[$a['sid']][(int)$x['itemid']]??''; }
   if($kh===''){ $fail++; continue; } $v=$x['value']??''; $v=is_scalar($v)?(string)$v:json_encode($v); if(isset($x['state'])&&(int)$x['state']===1) $v='ZBX_NOTSUPPORTED: '.$v; $v=substr($v,0,1000000);
   $u=$pdo->prepare('UPDATE zbx_active SET val=?,ts=? WHERE server_id=? AND kh=?'); $u->execute([$v,$now,$a['sid'],$kh]); if($u->rowCount()>0) $ok++; else { $e=$pdo->prepare('SELECT 1 FROM zbx_active WHERE server_id=? AND kh=?'); $e->execute([$a['sid'],$kh]); if($e->fetchColumn()) $ok++; else $fail++; } }
  foreach($auth as $ah=>$av) if($av['ip']===$ip&&isset($av['sid'])) zbxa_seen($av['sid'],'agent data','processed '.$ok.', failed '.$fail); return [['response'=>'success','info'=>'processed: '.$ok.'; failed: '.$fail.'; total: '.count($items).'; seconds spent: 0.000100'],$bad]; }
 return [['response'=>'failed','info'=>'unsupported request'],null]; }
