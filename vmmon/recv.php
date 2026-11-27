<?php // Mottagare för SNMP-traps (UDP, standard 162) och syslog (UDP, standard 514). Kör som tjänst (vmmon-recv): php recv.php
// Händelserna sparas i tabellen log_events och visas under Historik → Trap / syslog. Larmregler kan utlösas av dem.
if(PHP_SAPI!=='cli') exit('Endast CLI');
chdir(__DIR__); require __DIR__.'/config.php'; require __DIR__.'/lib.php'; require __DIR__.'/recv_lib.php'; $_SESSION=[]; app_tz_sync();
function rlog($m){ fwrite(STDOUT,date('c').' recv: '.$m."\n"); fflush(STDOUT); }
$stop=false; if(function_exists('pcntl_async_signals')){ pcntl_async_signals(true); foreach([SIGTERM,SIGINT] as $sg) pcntl_signal($sg,function() use(&$stop){ $stop=true; }); }
$codeSig=function(){ $m=0; foreach(glob(__DIR__.'/*.php') as $f) $m=max($m,(int)@filemtime($f)); return $m; }; $sig0=$codeSig();
rlog('starting (PHP '.PHP_VERSION.', pid '.getmypid().')');
foreach(['pdo_mysql','openssl','sockets'] as $x) if(!extension_loaded($x)&&$x!=='sockets') rlog("WARNING: PHP extension $x is missing in the command-line PHP (php -m)");
$S=['trap'=>['s'=>null,'port'=>0,'err'=>''],'syslog'=>['s'=>null,'port'=>0,'err'=>''],'tcp'=>['s'=>null,'port'=>0,'err'=>'']]; $TC=[]; $cfg=[]; $lastCfg=0; $lastBeat=0; $lastMap=0; $lastPrune=0; $lastFlush=0; $nextBind=['trap'=>0,'syslog'=>0,'tcp'=>0];
$buf=[]; $agg=[]; $lastAgg=0; $cnt=['trap'=>0,'syslog'=>0,'drop'=>0,'ignored'=>0,'bad'=>0]; $rate=[]; $warned=[]; $MAP=['ip'=>[],'name'=>[],'man'=>[]]; $dns=[]; $bfDone=false; $bfLast=null; $bfTot=0;
$warn=function($m) use(&$warned){ $k=md5(preg_replace('/\d+\.\d+\.\d+\.\d+/','',$m)); if(($warned[$k]??0)>time()-600) return; $warned[$k]=time(); applog('warning','recv',$m); rlog('WARNING: '.$m); };
function recv_cfg(){ $v3=json_decode(secret('trap_v3'),true); $cm=array_values(array_filter(array_map('trim',explode(',',(string)setting('trap_comm','')))));
 $ig=array_values(array_filter(array_map('trim',preg_split('/\R/',(string)setting('log_ignore',''))))); $mn=json_decode((string)setting('log_map','{}'),true)?:[];
 return ['trap_on'=>setting('trap_on','1')==='1','trap_port'=>max(1,min(65535,(int)setting('trap_port',162))),'sys_on'=>setting('syslog_on','1')==='1','sys_port'=>max(1,min(65535,(int)setting('syslog_port',514))),'tcp_on'=>setting('syslog_tcp_on','0')==='1','tcp_port'=>max(1,min(65535,(int)setting('syslog_tcp_port',514))),
  'comm'=>$cm,'v3'=>is_array($v3)?$v3:[],'unknown'=>setting('log_unknown','1')==='1','maxsev'=>max(0,min(7,(int)setting('log_maxsev',7))),'ignore'=>$ig,'keep'=>max(1,min(3650,(int)setting('log_keep',30))),'rate'=>max(0,(int)setting('log_rate',6000)),'keepraw'=>setting('log_keepraw','0')==='1','nopass'=>setting('log_nopass','0')==='1','sumkeep'=>max(1,min(3650,(int)setting('log_sum_keep',730))),'man'=>$mn]; }
function recv_bind($proto,$port){ $e=0; $es=''; if($proto==='tcp'){ $s=@stream_socket_server("tcp://[::]:$port",$e,$es); if(!$s) $s=@stream_socket_server("tcp://0.0.0.0:$port",$e,$es); return [$s,$es,null]; } $s=@stream_socket_server("udp://[::]:$port",$e,$es,STREAM_SERVER_BIND); if(!$s){ $s=@stream_socket_server("udp://0.0.0.0:$port",$e,$es,STREAM_SERVER_BIND); }
 $sk=null; if($s&&function_exists('socket_import_stream')){ $sk=@socket_import_stream($s); if($sk){ @socket_set_option($sk,SOL_SOCKET,SO_RCVBUF,16*1024*1024); } } return [$s,$es,$sk]; }
function peer_ip($peer){ $ip=preg_replace('/^\[?(.*?)\]?:\d+$/','$1',(string)$peer); if(stripos($ip,'::ffff:')===0) $ip=substr($ip,7); return $ip; }
// IP/värdnamn -> enhet. Värdnamn slås upp högst var 10:e minut.
function recv_map(&$dns){ $ip=[]; $nm=[]; foreach(db()->query('SELECT id,name,host,info FROM servers WHERE is_services=0')->fetchAll() as $s){ $h=trim((string)$s['host']); if($h!==''){ if(filter_var($h,FILTER_VALIDATE_IP)) $ip[$h]=(int)$s['id']; else { $c=$dns[$h]??null; if(!$c||$c[0]<time()-600){ $r=@gethostbyname($h); $dns[$h]=$c=[time(),$r!==$h?$r:'']; } if($c[1]!=='') $ip[$c[1]]=(int)$s['id']; $nm[strtolower($h)]=(int)$s['id']; } }
  $nm[strtolower((string)$s['name'])]=(int)$s['id']; $in=json_decode((string)$s['info'],true); if(!empty($in['hostname'])){ $hn=strtolower((string)$in['hostname']); $nm[$hn]=(int)$s['id']; $nm[explode('.',$hn)[0]]=(int)$s['id']; } } return ['ip'=>$ip,'name'=>$nm]; }
$flush=function() use(&$buf){ if(!$buf) return; $rows=$buf; $buf=[]; try{ foreach(array_chunk($rows,100) as $ch){ $ph=[]; $p=[]; foreach($ch as $r){ $ph[]='(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'; $f=$r['fw']??[]; array_push($p,$r['ts'],$r['sid'],$r['ip'],$r['kind'],$r['sev'],$r['fac'],$r['host'],$r['prog'],$r['oid'],$r['msg'],$f['act']??null,$f['dir']??null,$f['proto']??null,$f['src']??null,$f['dst']??null,$f['sport']??null,$f['dport']??null,$f['in']??null,$f['out']??null,$f['rule']??null); }
   db()->prepare('INSERT INTO log_events(ts,server_id,src_ip,kind,sev,facility,host,prog,oid,msg,fw_act,fw_dir,fw_proto,fw_src,fw_dst,fw_sport,fw_dport,fw_in,fw_out,fw_rule) VALUES '.implode(',',$ph))->execute($p); } }catch(Throwable $e){ rlog('ERROR: '.$e->getMessage()); applog('error','recv','Could not save events: '.$e->getMessage().' (run the latest SQL file on the Update tab)'); } };
$aggFlush=function() use(&$agg,$warn){ if(!$agg) return; $rows=$agg; $agg=[]; try{ foreach(array_chunk($rows,200) as $ch){ $ph=[]; $p=[]; foreach($ch as $a){ $ph[]='(?,?,?,?,?,?,?,?,?,?)'; array_push($p,...$a); }
  db()->prepare('INSERT INTO log_fw_daily(day,server_id,act,dir,proto,src,dst,dport,rule,cnt) VALUES '.implode(',',$ph).' ON DUPLICATE KEY UPDATE cnt=cnt+VALUES(cnt)')->execute($p); } }catch(Throwable $e){ $warn('Could not save the daily firewall summary: '.$e->getMessage().' (run the latest SQL file on the Update tab)'); if(count($agg)<50000) foreach($rows as $k=>$a){ if(isset($agg[$k])) $agg[$k][9]+=$a[9]; else $agg[$k]=$a; } } };
$proc=function($kind,$d,$ip,$s,$peer) use(&$cfg,&$cnt,&$rate,&$buf,&$warned,&$MAP,&$agg,$warn,$flush,$aggFlush){ $t=time();
    $rk=$ip.'|'.intdiv($t,60); $rate[$rk]=($rate[$rk]??0)+1; if($cfg['rate']>0&&$rate[$rk]>$cfg['rate']){ $cnt['drop']++; if($rate[$rk]===$cfg['rate']+1) $warn("Too many messages from $ip (more than {$cfg['rate']} per minute) – the rest are dropped. Raise the limit or filter on the sender (Settings → Trap / syslog)."); return; }
    if($kind==='trap'){ $r=snmp_parse($d,['comm'=>$cfg['comm'],'v3'=>$cfg['v3']]);
     if(empty($r['ok'])){ if(!empty($r['ignore'])) return; $cnt['bad']++; $bad=$r['bad']??''; if($bad!==''||($r['err']??'')!=='not SNMP'){ $key='bad|'.$ip.'|'.$bad.'|'.($r['user']??''); if(($warned[$key]??0)<$t-300){ $warned[$key]=$t; $msg=($r['ver']??'SNMP').' trap from '.$ip.' was not accepted: '.$r['err'].(!empty($r['user'])?' (user/community: '.$r['user'].')':''); $sid=$MAP['man'][$ip]??($cfg['man'][$ip]??($MAP['ip'][$ip]??null));
       $buf[]=['ts'=>date('Y-m-d H:i:s',$t),'sid'=>$sid,'ip'=>$ip,'kind'=>'trap','sev'=>4,'fac'=>null,'host'=>null,'prog'=>'vmmon','oid'=>null,'msg'=>$msg]; applog('warning','recv',$msg); } } else { $warn("Packet on the trap port from $ip is not SNMP"); } return; }
     if(!empty($r['ack'])) @stream_socket_sendto($s,$r['ack'],0,$peer);
     if($r['sev']>$cfg['maxsev']){ $cnt['ignored']++; return; } $r['ipx']=$r['agent']; $cnt['trap']++; }
    else { $r=syslog_parse($d); if($r['sev']>$cfg['maxsev']){ $cnt['ignored']++; return; } $cnt['syslog']++; $r['ipx']=null; }
    $r['fw']=$kind==='syslog'?fw_parse($r['prog']??'',$r['msg']):null; $nop=$r['fw']&&$cfg['nopass']&&($r['fw']['act']??'')==='pass';
    $skip=false; $full=$r['msg'].' '.($r['host']??'').' '.($r['prog']??''); foreach($cfg['ignore'] as $pt) if(log_match($pt,$full)){ $skip=true; break; } if($skip){ $cnt['ignored']++; return; }
    $sid=$cfg['man'][$ip]??($MAP['ip'][$ip]??null); if($sid===null&&!empty($r['ipx'])) $sid=$cfg['man'][$r['ipx']]??($MAP['ip'][$r['ipx']]??null);
    if($sid===null&&!empty($r['host'])){ $hn=strtolower($r['host']); $sid=$MAP['name'][$hn]??$MAP['name'][explode('.',$hn)[0]]??null; }
    if($sid===null&&!$cfg['unknown']){ $cnt['ignored']++; return; }
    if($r['fw']){ $f=$r['fw']; $ak=date('Y-m-d',$t).'|'.(int)$sid.'|'.($f['act']??'').'|'.($f['dir']??'').'|'.($f['proto']??'').'|'.($f['src']??'').'|'.($f['dst']??'').'|'.(int)($f['dport']??0).'|'.($f['rule']??'');
     if(isset($agg[$ak])) $agg[$ak][9]++; elseif(count($agg)<50000) $agg[$ak]=[date('Y-m-d',$t),(int)$sid,mb_substr((string)($f['act']??''),0,10),mb_substr((string)($f['dir']??''),0,4),mb_substr((string)($f['proto']??''),0,12),mb_substr((string)($f['src']??''),0,45),mb_substr((string)($f['dst']??''),0,45),(int)($f['dport']??0),mb_substr((string)($f['rule']??''),0,40),1]; else $cnt['bad']++; }
    if(!empty($nop)){ $cnt['ignored']++; return; }
    if($r['fw']&&!$cfg['keepraw']){ $r['msg']=''; $r['host']=null; $r['prog']=null; } // tolkad brandväggsrad: fälten räcker, originalraden sparas inte (sparar ungefär hälften av utrymmet)
    log_dev_note($r['kind'],$sid);
    $buf[]=['ts'=>date('Y-m-d H:i:s',$t),'sid'=>$sid,'ip'=>$ip,'kind'=>$r['kind'],'sev'=>(int)$r['sev'],'fac'=>$r['fac']??null,'host'=>$r['host'],'prog'=>$r['prog'],'oid'=>$r['oid']?:null,'msg'=>$r['msg'],'fw'=>$r['fw']]; if(count($buf)>=200) $flush(); };
while(!$stop){
 try{
  if(time()-$lastCfg>=10){ $lastCfg=time(); setting('',null,true); app_tz_sync(); $cfg=recv_cfg();
   foreach(['trap'=>[$cfg['trap_on'],$cfg['trap_port']],'syslog'=>[$cfg['sys_on'],$cfg['sys_port']],'tcp'=>[$cfg['tcp_on'],$cfg['tcp_port']]] as $k=>[$on,$np]){ $x=&$S[$k]; $pr=$k==='tcp'?'TCP':'UDP';
    if($x['s']&&(!$on||$np!==$x['port'])){ @fclose($x['s']); $x['s']=null; $x['sk']=null; if($k==='tcp'){ foreach($TC as $c) @fclose($c['s']); $TC=[]; } rlog("$k socket closed"); }
    if($on&&!$x['s']&&time()>=$nextBind[$k]){ [$sock,$es,$sk]=recv_bind($k,$np); if($sock){ stream_set_blocking($sock,false); $x['s']=$sock; $x['sk']=$sk; $x['port']=$np; $x['err']=''; rlog("$k listening on $pr port $np"); applog('info','recv',"Listening for $k on $pr port $np"); }
     else { $nextBind[$k]=time()+30; $x['port']=$np; $msg="Cannot listen on $pr port $np for $k: $es"; if($np<1024) $msg.=' (ports below 1024 need root or CAP_NET_BIND_SERVICE – see the vmmon-recv service file, or choose a higher port)'; if($x['err']!==$msg){ $x['err']=$msg; applog('error','recv',$msg); rlog('ERROR: '.$msg); } } }
    if(!$on){ $x['err']=''; $x['port']=0; } unset($x); } }
  if(!$bfDone){ try{ if($bfLast===null) $bfLast=(int)setting('log_fw_bf_id','0'); $q=db()->prepare("SELECT id,prog,msg FROM log_events WHERE id>? AND kind='syslog' AND fw_act IS NULL AND fw_proto IS NULL AND (prog LIKE 'filterlog%' OR prog='flows' OR msg LIKE '%SRC=%' OR msg LIKE '%src=%' OR msg LIKE '%proto %') ORDER BY id LIMIT 300"); $q->execute([$bfLast]); $rows=$q->fetchAll();
    if(!$rows){ $bfDone=true; if($bfTot) rlog("firewall fields added to $bfTot older events"); } else { foreach($rows as $x){ $bfLast=(int)$x['id']; $f=fw_parse($x['prog'],$x['msg']); if($f){ db()->prepare('UPDATE log_events SET fw_act=?,fw_dir=?,fw_proto=?,fw_src=?,fw_dst=?,fw_sport=?,fw_dport=?,fw_in=?,fw_out=?,fw_rule=? WHERE id=?')->execute([$f['act'],$f['dir'],$f['proto'],$f['src'],$f['dst'],$f['sport'],$f['dport'],$f['in'],$f['out'],$f['rule'],$x['id']]); $bfTot++; } } set_setting('log_fw_bf_id',$bfLast); }
   }catch(Throwable $e){ $bfDone=true; $warn('Could not add firewall fields to older events: '.$e->getMessage().' (run the latest SQL file on the Update tab)'); } }
  if(time()-$lastMap>=60){ $lastMap=time(); $m=recv_map($dns); $MAP['ip']=$m['ip']; $MAP['name']=$m['name']; }
  if(time()-$lastBeat>=10){ $lastBeat=time(); set_setting('recv_beat',time()); set_setting('recv_info',json_encode(['trap'=>$S['trap']['s']?$S['trap']['port']:0,'syslog'=>$S['syslog']['s']?$S['syslog']['port']:0,'tcp'=>$S['tcp']['s']?$S['tcp']['port']:0,'tcpc'=>count($TC),'err'=>trim($S['trap']['err'].' '.$S['syslog']['err'].' '.$S['tcp']['err']),'cnt'=>$cnt,'pid'=>getmypid(),'since'=>$GLOBALS['t0']??($GLOBALS['t0']=time())])); }
  if(time()-$lastPrune>=600||$lastPrune===0){ $lastPrune=time(); $rate=[];
   try{ for($i=0;$i<30;$i++){ $st=db()->prepare('DELETE FROM log_fw_daily WHERE day<? LIMIT 20000'); $st->execute([date('Y-m-d',time()-$cfg['sumkeep']*86400)]); if($st->rowCount()<20000) break; } }catch(Throwable $e){}
   try{ $pm=log_part_maintain($cfg['keep']); if($pm===null){ // ej partitionerad: radera i omgångar
     for($i=0;$i<30;$i++){ $st=db()->prepare('DELETE FROM log_events WHERE ts<? LIMIT 20000'); $st->execute([date('Y-m-d H:i:s',time()-$cfg['keep']*86400)]); if($st->rowCount()<20000) break; } }
    elseif($pm!==''){ rlog('partitions: '.$pm); applog('info','recv','Log partitions: '.$pm); } }catch(Throwable $e){ $warn('Log retention/partition maintenance failed: '.$e->getMessage()); } }
  $rd=[]; foreach($S as $x) if($x['s']) $rd[]=$x['s']; foreach($TC as $c) $rd[]=$c['s']; if(!$rd){ sleep(1); if($codeSig()!==$sig0) break; continue; }
  $w=null; $ex=null; $n=@stream_select($rd,$w,$ex,1);
  if($n>0) foreach($rd as $s){
   if($S['tcp']['s']===$s){ for($i=0;$i<50;$i++){ $c=@stream_socket_accept($s,0,$pn); if(!$c) break; $cip=peer_ip($pn);
      if(count($TC)>=500){ @fclose($c); $warn("Too many open syslog TCP connections (500) – refused one from $cip"); continue; }
      stream_set_blocking($c,false); $TC[(int)$c]=['s'=>$c,'ip'=>$cip,'b'=>'','last'=>time()]; } continue; }
   if(isset($TC[(int)$s])){ $id=(int)$s; $d=@fread($s,65536);
     if($d===false||($d===''&&feof($s))){ foreach(syslog_tcp_frames($TC[$id]['b'],$bad,true) as $m) $proc('syslog',$m,$TC[$id]['ip'],null,null); @fclose($s); unset($TC[$id]); continue; }
     if($d==='') continue; $TC[$id]['b'].=$d; $TC[$id]['last']=time();
     foreach(syslog_tcp_frames($TC[$id]['b'],$bad) as $m) $proc('syslog',$m,$TC[$id]['ip'],null,null);
     if($bad){ $warn('Invalid syslog TCP stream from '.$TC[$id]['ip'].' – connection closed'); $cnt['bad']++; @fclose($s); unset($TC[$id]); }
     continue; }
   $kind=($S['trap']['s']===$s)?'trap':'syslog'; $k=0;
   while($k++<500){ $d=@stream_socket_recvfrom($s,65535,0,$peer); if($d===false||$d==='') break; $proc($kind,$d,peer_ip($peer),$s,$peer); } }
  foreach($TC as $id=>$c) if($c['last']<time()-1800){ @fclose($c['s']); unset($TC[$id]); }
  if($buf&&(time()-$lastFlush>=1||count($buf)>=100)){ $lastFlush=time(); $flush(); }
  if($agg&&time()-$lastAgg>=10){ $lastAgg=time(); $aggFlush(); }
  if($codeSig()!==$sig0){ rlog('code files changed – restarting to load the new version'); break; }
 }catch(Throwable $e){ rlog('ERROR: '.$e->getMessage()); applog('error','recv',$e->getMessage()); sleep(2); try{ db(); }catch(Throwable $e2){ sleep(5); } }
}
$flush(); $aggFlush(); foreach($S as $x) if($x['s']) @fclose($x['s']); rlog('stopping');
