<?php // Lyssnare för Zabbix-agenter i active-läge. Kör som tjänst (vmmon-zabbix): php zbx-active.php
// Agenten (ServerActive=) hämtar sin lista med "active checks" och skickar sedan värden med "agent data". Protokoll och lagring finns i zbx_active_lib.php.
if(PHP_SAPI!=='cli') exit('Endast CLI');
chdir(__DIR__); require __DIR__.'/config.php'; require __DIR__.'/lib.php'; $_SESSION=[]; app_tz_sync();
function zlog($m){ fwrite(STDOUT,date('c').' zabbix: '.$m."\n"); fflush(STDOUT); }
$stop=false; if(function_exists('pcntl_async_signals')){ pcntl_async_signals(true); foreach([SIGTERM,SIGINT] as $sg) pcntl_signal($sg,function() use(&$stop){ $stop=true; }); }
$codeSig=function(){ $m=0; foreach(glob(__DIR__.'/*.php') as $f) $m=max($m,(int)@filemtime($f)); return $m; }; $sig0=$codeSig();
zlog('starting (PHP '.PHP_VERSION.', pid '.getmypid().')');
$srv=null; $port=0; $clients=[]; $auth=[]; $lastCfg=0; $lastBeat=0; $lastPrune=0; $lastSys=0; $warned=[]; $on=false; $listenErr=''; $nextBind=0;
$warn=function($m) use(&$warned){ $k=md5(preg_replace('/\d+\.\d+\.\d+\.\d+/','',$m)); if(($warned[$k]??0)>time()-600) return; $warned[$k]=time(); applog('warning','zabbix',$m); zlog('WARNING: '.$m); };
$close=function($id) use(&$clients){ if(isset($clients[$id])){ @fclose($clients[$id]['s']); unset($clients[$id]); } };
while(!$stop){
 try{
  if(time()-$lastCfg>=10){ $lastCfg=time(); setting('',null,true); app_tz_sync(); $on=setting('zbx_active_on','0')==='1'; $np=(int)setting('zbx_active_port',10051);
   if(($srv&&(!$on||$np!==$port))){ foreach(array_keys($clients) as $id) $close($id); @fclose($srv); $srv=null; zlog('listener closed'); }
   if($on&&!$srv&&time()>=$nextBind){ $port=$np; $e=0; $es='';
    $srv=@stream_socket_server("tcp://[::]:$port",$e,$es); if(!$srv) $srv=@stream_socket_server("tcp://0.0.0.0:$port",$e,$es);
    if($srv){ stream_set_blocking($srv,false); zlog("listening on port $port"); applog('info','zabbix',"Active listener started on port $port"); $listenErr=''; }
    else { $nextBind=time()+30; if($listenErr!==$es){ $listenErr=$es; applog('error','zabbix',"Cannot listen on port $port: $es"); zlog("ERROR: cannot listen on port $port: $es"); } } } }
  if(time()-$lastBeat>=10){ $lastBeat=time(); if($srv) set_setting('zbx_active_beat',time()); }
  if(time()-$lastSys>=30){ $lastSys=time(); try{ eval_system_rules(); }catch(Throwable $e){} }
  if(time()-$lastPrune>=86400){ $lastPrune=time(); try{ db()->prepare('DELETE FROM zbx_active WHERE req<?')->execute([time()-3*86400]); }catch(Throwable $e){} }
  if(!$srv){ sleep(1); if($codeSig()!==$sig0) break; continue; }
  $rd=[$srv]; foreach($clients as $c) $rd[]=$c['s']; $w=null; $x=null;
  if(@stream_select($rd,$w,$x,1)>0){ foreach($rd as $s){
    if($s===$srv){ $c=@stream_socket_accept($srv,0,$peer); if($c){ if(count($clients)>=200){ @fclose($c); continue; } stream_set_blocking($c,false); $ip=preg_replace('/^\[?(.*?)\]?:\d+$/','$1',(string)$peer); if(strpos($ip,'::ffff:')===0) $ip=substr($ip,7); $clients[(int)$c]=['s'=>$c,'ip'=>$ip,'buf'=>'','t'=>time()]; } continue; }
    $id=(int)$s; $d=@fread($s,65536);
    if($d===''||$d===false){ if(feof($s)) $close($id); continue; }
    $clients[$id]['buf'].=$d; if(strlen($clients[$id]['buf'])>20000000){ $close($id); continue; }
    $p=zbxp_parse($clients[$id]['buf']); if($p[0]==='need') continue; if($p[0]==='bad'){ $warn('unreadable data from '.$clients[$id]['ip'].' (not the Zabbix protocol, or an encrypted/unsupported connection): '.bin2hex(substr($clients[$id]['buf'],0,24))); $close($id); continue; }
    [$resp,$ev]=zbxa_handle($p[1],$clients[$id]['ip'],$auth); applog('debug','zabbix',$clients[$id]['ip'].' '.($p[1]['request']??'?').' host='.($p[1]['host']??($p[1]['data'][0]['host']??'-')).' -> '.($resp['response']??'?').(isset($resp['info'])?' ('.$resp['info'].')':'').(isset($resp['data'])?' items='.count($resp['data']):'')); if($ev){ if(strpos($ev,'agent ')===0){ applog('info','zabbix',$ev); zlog($ev); } else $warn($ev); }
    @stream_set_blocking($s,true); stream_set_timeout($s,3); @fwrite($s,zbxp_frame($resp)); $close($id); } }
  foreach($clients as $id=>$c) if(time()-$c['t']>15) $close($id);
  if($codeSig()!==$sig0){ zlog('code files changed – restarting to load the new version'); break; }
 }catch(Throwable $e){ zlog('ERROR: '.$e->getMessage()); applog('error','zabbix',$e->getMessage()); sleep(2); try{ db(); }catch(Throwable $e2){ sleep(5); } }
}
zlog('stopping');
