<?php // Bakgrundsprocess: kör kontroller, hosts och larm oberoende av om någon har dashboarden öppen.
// Som tjänst (systemd/NSSM):  php worker.php        Via schemaläggare (varje minut):  php worker.php --for=55
// Test för hand:  php worker.php --once      Loggar varje cykel som gjorde något:  php worker.php -v
if(PHP_SAPI!=='cli') exit('Endast CLI');
chdir(__DIR__); $GLOBALS['vm_worker']=1; require __DIR__.'/config.php'; require __DIR__.'/lib.php'; $_SESSION=[]; app_tz_sync(); mib_seed_once();
function wlog($m){ fwrite(STDOUT,date('c').' worker: '.$m."\n"); fflush(STDOUT); $lv=(strpos($m,'ERROR')===0||strpos($m,'3 errors')===0)?'error':((strpos($m,'WARNING')===0)?'warning':((strpos($m,'alive')===0||strpos($m,'ran')===0)?'debug':'info')); applog($lv,'worker',$m); }
$end=time()+3600; foreach($argv as $a) if(preg_match('/^--for=(\d+)$/',$a,$m)) $end=time()+(int)$m[1];
$codeSig=function(){ $m=0; foreach(glob(__DIR__.'/*.php') as $f) $m=max($m,(int)@filemtime($f)); return $m; }; $sig0=$codeSig(); $changed=false;
$once=in_array('--once',$argv,true); $child=in_array('--poller',$argv,true); $verbose=$once||in_array('-v',$argv,true)||in_array('--verbose',$argv,true); $fail=0; $stop=false; $tot=['cycles'=>0,'checks'=>0,'hosts'=>0]; $lastLog=0; $first=true;
// Antal pollare: Inställningar → "Pollare" (auto, 1–16). Auto väljer efter antal kontroller och enheter. 1 = allt i en process.
function worker_count(){ $v=setting('worker_n','auto'); if(is_numeric($v)) return max(1,min(16,(int)$v)); $pdo=db(); $ck=(int)$pdo->query('SELECT COUNT(*) FROM server_checks WHERE enabled=1')->fetchColumn(); $dv=(int)$pdo->query('SELECT COUNT(*) FROM servers WHERE enabled=1 AND (hv_type IS NOT NULL OR nw_type IS NOT NULL)')->fetchColumn(); return max(1,min(8,(int)ceil($ck/300+$dv/15))); }
function spawn_poller(){ $d=[0=>['pipe','r'],1=>STDOUT,2=>STDERR]; $p=@proc_open([PHP_BINARY,__FILE__,'--poller'],$d,$pipes,__DIR__); if(!is_resource($p)) return null; return ['p'=>$p,'in'=>$pipes[0]]; }
function kill_poller($k){ @fclose($k['in']); @proc_terminate($k['p'],9); @proc_close($k['p']); }
if(function_exists('pcntl_async_signals')){ pcntl_async_signals(true); foreach([SIGTERM,SIGINT] as $sg) pcntl_signal($sg,function() use(&$stop){ $stop=true; }); }
// ---- Pollare (startas av supervisorn): kör förfallna kontroller/hosts/nätverksenheter, parallellt med övriga pollare.
if($child){ wlog('poller started (pid '.getmypid().')'); if(stripos(PHP_OS,'WIN')!==0) @stream_set_blocking(STDIN,false); $e=0;
 do{ try{ setting('',null,true); app_tz_sync(); guest_mem_map(true); $r=poll_due(); $e=0; }catch(Throwable $ex){ wlog('ERROR: poller: '.$ex->getMessage()); if(++$e>=3){ wlog('poller: 3 errors in a row – exiting'); exit(1); } $r=null; }
  if($codeSig()!==$sig0) break; if(stripos(PHP_OS,'WIN')!==0){ @fread(STDIN,1); if(@feof(STDIN)) break; } if(!$r||(!$r['checks']&&!$r['hosts'])) sleep(1); }while(!$stop&&time()<$end);
 exit(0); }
// ---- Supervisor
wlog('starting (PHP '.PHP_VERSION.', pid '.getmypid().')');
foreach(['pdo_mysql','curl','openssl','snmp'] as $x) if(!extension_loaded($x)) wlog("WARNING: PHP extension $x is missing in the command-line PHP (php -m)");
if(!fping_bin()) wlog('info: fping is not installed – pings run one at a time (apt install fping runs them in bulk)');
$kids=[]; $want=1; $lastN=0; $lastPost=0; $lastSpawn=0; $multi=false;
do{ try{ setting('',null,true); app_tz_sync(); set_setting('worker_beat',time());
   if($once){ guest_mem_map(true); $r=poll_all(); $tot['cycles']++; $tot['checks']+=$r['checks']; $tot['hosts']+=$r['hosts']; wlog("ran: {$r['checks']} checks, {$r['hosts']} hosts in {$r['ms']} ms"); break; }
   if(time()-$lastN>=30||!$lastN){ $lastN=time(); $want=worker_count(); if($want!==($multi?count($kids):1)) wlog('pollers: '.$want.($want>1?' (parallel)':' (single process)')); $multi=$want>1; set_setting('worker_info',json_encode(['n'=>$want,'ts'=>time()])); }
   if($multi){
    foreach($kids as $i=>$k){ $st=@proc_get_status($k['p']); if(!$st||!$st['running']){ kill_poller($k); unset($kids[$i]); } }
    while(count($kids)>$want){ kill_poller(array_pop($kids)); }
    if(count($kids)<$want&&time()-$lastSpawn>=2){ $k=spawn_poller(); $lastSpawn=time(); if($k) $kids[]=$k; else wlog('ERROR: could not start a poller process (proc_open)'); }
    if(time()-$lastPost>=5){ $lastPost=time(); guest_mem_map(true); poll_post(); $tot['cycles']++; }
   } else {
    if($kids){ foreach($kids as $k) kill_poller($k); $kids=[]; }
    guest_mem_map(true); $r=poll_all(); $tot['cycles']++; $tot['checks']+=$r['checks']; $tot['hosts']+=$r['hosts'];
    if($r['checks']||$r['hosts']) applog('debug','worker',"cycle: {$r['checks']} checks, {$r['hosts']} hosts/network, {$r['ms']} ms"); if($first||($verbose&&($r['checks']||$r['hosts']))) wlog("ran: {$r['checks']} checks, {$r['hosts']} hosts in {$r['ms']} ms".($first?' (first cycle)':'')); $first=false; }
   set_setting('worker_beat',time()); $fail=0; }
 catch(Throwable $e){ wlog('ERROR: '.$e->getMessage()); if(++$fail>=3){ wlog('3 errors in a row – exiting (the service will restart)'); foreach($kids as $k) kill_poller($k); exit(1); } }
 if(time()-$lastLog>=600){ wlog("alive: {$tot['cycles']} cycles, {$tot['checks']} checks and {$tot['hosts']} host polls since start".($multi?', '.count($kids).' pollers':'')); $lastLog=time(); }
 if($once) break; if($codeSig()!==$sig0){ $changed=true; break; } for($i=0;$i<5&&!$stop;$i++){ $pn=0; try{ $pn=ping_retry_pass(); }catch(Throwable $e){ wlog('ERROR: ping retry: '.$e->getMessage()); } if($pn===0) sleep(1); } }while(!$stop&&time()<$end);
foreach($kids as $k) kill_poller($k);
wlog($changed?'code files changed – restarting to load the new version':($stop?'stopping (signal)':($once?'done (--once)':'hour is up – restarting')));
