<?php require 'config.php'; require 'lib.php'; need_login(); track_user();
$v=in_array($_GET['v']??'',['svc','srv','evt','log'])?$_GET['v']:'alerts'; if($v==='log'||(($_POST['a']??'')==='map')){ require __DIR__.'/history_log.php'; exit; }
function fmt_dur($s){ if($s===null) return '–'; $s=(int)$s; if($s<60) return $s.' s'; if($s<3600) return floor($s/60).' min'; if($s<86400) return floor($s/3600).' h '.floor($s%3600/60).' min'; return floor($s/86400).' '.t('d','d').' '.floor($s%86400/3600).' h'; }
if($v==='evt'){ // Alla warning/critical-händelser med orsak till att notis (inte) skickats
 $fs=($_GET['sev']??'')==='critical'?'critical':(($_GET['sev']??'')==='warning'?'warning':''); $fn=in_array($_GET['nt']??'',['0','1'],true)?$_GET['nt']:''; $fq=trim((string)($_GET['q']??'')); $fd=max(1,min(365,(int)($_GET['d']??7))); $LIMe=500;
 $w=['started>=NOW()-INTERVAL '.$fd.' DAY']; $p=[]; if($fs){ $w[]='sev=?'; $p[]=$fs; } if($fn!==''){ $w[]='notified=?'; $p[]=(int)$fn; } if($fq!==''){ $w[]='(server_name LIKE ? OR kind LIKE ? OR detail LIKE ? OR reason LIKE ?)'; for($i=0;$i<4;$i++) $p[]='%'.$fq.'%'; }
 $wh=' WHERE '.implode(' AND ',$w); $rows=[]; $tot=0; $err='';
 try{ $c=db()->prepare('SELECT COUNT(*) FROM alert_events'.$wh); $c->execute($p); $tot=(int)$c->fetchColumn(); $q=db()->prepare('SELECT *,TIMESTAMPDIFF(SECOND,started,NOW()) age FROM alert_events'.$wh.' ORDER BY id DESC LIMIT '.$LIMe); $q->execute($p); $rows=$q->fetchAll(); }catch(Throwable $e){ $err=t('Tabellen alert_events saknas – kör sql/3.08.sql','Table alert_events is missing – run sql/3.08.sql'); }
 $KL=metric_labels();
 top(t('Historik','History')); ?>
<h2><?= t('Historik','History') ?></h2>
<div class="rngn"><?php foreach(['alerts'=>t('Larm','Alerts'),'svc'=>t('Tjänster','Services'),'srv'=>t('Server-checks','Server checks'),'evt'=>t('Alla events','All events'),'log'=>'Trap'] as $k=>$n) echo '<a class="btn sm'.($v===$k?' on':'').'" href="history.php?v='.$k.'">'.h($n).'</a>'; ?><a class="btn sm" href="history_export.php"><?= t('Export','Export') ?></a></div>
<form method="get" class="card hf"><input type="hidden" name="v" value="evt">
 <label class="hl"><span><?= t('Period (dagar)','Period (days)') ?></span><input type="number" name="d" min="1" max="365" value="<?= $fd ?>"></label>
 <label class="hl"><span><?= t('Allvarlighet','Severity') ?></span><select name="sev"><option value=""><?= t('Alla','All') ?></option><option value="critical"<?= $fs==='critical'?' selected':'' ?>>Critical</option><option value="warning"<?= $fs==='warning'?' selected':'' ?>>Warning</option></select></label>
 <label class="hl"><span><?= t('Notis','Notification') ?></span><select name="nt"><option value=""><?= t('Alla','All') ?></option><option value="1"<?= $fn==='1'?' selected':'' ?>><?= t('Skickad','Sent') ?></option><option value="0"<?= $fn==='0'?' selected':'' ?>><?= t('Ej skickad','Not sent') ?></option></select></label>
 <label class="hl"><span><?= t('Sök','Search') ?></span><input name="q" value="<?= h($fq) ?>"></label>
 <div class="hbt"><button class="btn" type="submit"><?= t('Filtrera','Filter') ?></button><a class="btn sm gh" href="history.php?v=evt"><?= t('Återställ filter','Reset filters') ?></a></div></form>
<p class="mut"><?= $err?h($err):($tot>$LIMe?sprintf(t('Visar de senaste %d av %d events.','Showing the latest %d of %d events.'),$LIMe,$tot):sprintf(t('%d events.','%d events.'),$tot)) ?> <?= t('Alla warning/critical-händelser loggas oavsett hur länge de varat, med orsak till att notis skickats eller inte.','All warning/critical events are logged however short, with the reason a notification was or was not sent.') ?></p>
<div class="tw"><table class="card"><tr><th><?= t('Start','Start') ?></th><th><?= t('Slut','End') ?></th><th><?= t('Varaktighet','Duration') ?></th><th>Server</th><th><?= t('Typ','Type') ?></th><th><?= t('Nivå','Level') ?></th><th><?= t('Detaljer','Details') ?></th><th><?= t('Notis / orsak','Notification / reason') ?></th></tr>
<?php foreach($rows as $r): $cr=$r['sev']==='critical'; ?>
<tr><td><?= h($r['started']) ?></td><td><?= $r['ended']?h($r['ended']):'<span class="badt">'.t('pågår','ongoing').'</span>' ?></td><td><?= fmt_dur($r['ended']?$r['dur']:$r['age']) ?></td><td><?= h($r['server_name']) ?></td><td><?= h($KL[$r['kind']]??$r['kind']) ?></td><td class="<?= $cr?'badt':'warnt' ?>"><?= $cr?'Critical':'Warning' ?></td><td class="det"><?= h($r['detail']) ?></td><td class="det"><span class="<?= $r['notified']?'okt':'mut' ?>"><?= $r['notified']?'✔ ':'✖ ' ?></span><?= h($r['reason']) ?></td></tr>
<?php endforeach; if(!$rows&&!$err) echo '<tr><td colspan="8" class="mut">'.t('Inga events i valt tidsspann.','No events in the selected period.').'</td></tr>'; ?></table></div>
<?php if(function_exists('bottom')) bottom(); exit; }
// ---- Filter -------------------------------------------------------------
function arr($k){ $a=$_GET[$k]??[]; if(!is_array($a)) $a=[$a]; $o=[]; foreach($a as $x){ $x=(string)$x; if($x!==''&&strlen($x)<=200) $o[]=$x; } return array_slice(array_values(array_unique($o)),0,200); }
function dt($k){ $s=trim((string)($_GET[$k]??'')); if(!preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})$/',$s,$m)) return ''; return strtotime($m[1].' '.$m[2].':00')?$m[1].'T'.$m[2]:''; }
$from=dt('from'); $to=dt('to'); $fSrv=arr('srv'); $fRule=arr('rule'); $fEv=arr('ev'); $res=in_array($_GET['res']??'',['1h','1d','1w'])?$_GET['res']:'auto';
if(!isset($_GET['from'])&&!isset($_GET['to'])){ $from=date('Y-m-d\TH:i',time()-7*86400); $to=date('Y-m-d\TH:i'); } // standard: senaste 7 dagarna (töm Från/Till och filtrera för att se allt)
$tbl=$v==='alerts'?'alert_log':'check_events'; $ruleCol=$v==='alerts'?'metric':'label'; $evCol=$v==='alerts'?'event':'status';
$evs=$v==='alerts'?['alarm'=>t('Larm','Alert'),'repeat'=>t('Påminnelse','Reminder'),'ok'=>t('Återställt','Recovered')]:['down'=>t('Nere','Down'),'up'=>t('Uppe igen','Back up')];
$evCls=['alarm'=>'badt','repeat'=>'badt','ok'=>'okt','down'=>'badt','up'=>'okt'];
$fEv=array_values(array_intersect($fEv,array_keys($evs)));
$w=[]; $p=[];
if($v!=='alerts'){ $w[]='svc=?'; $p[]=$v==='svc'?1:0; }
if($from){ $w[]='ts>=?'; $p[]=str_replace('T',' ',$from).':00'; }
if($to){ $w[]='ts<=?'; $p[]=str_replace('T',' ',$to).':59'; }
$in=function($col,$vals) use(&$w,&$p){ if($vals){ $w[]=$col.' IN ('.implode(',',array_fill(0,count($vals),'?')).')'; foreach($vals as $x) $p[]=$x; } };
if($v!=='svc'&&$fSrv){ $ids=[]; $nms=[]; foreach($fSrv as $x){ if(preg_match('/^\d+$/',$x)) $ids[]=(int)$x; elseif(strpos($x,'n:')===0) $nms[]=substr($x,2); }
 // Id-baserat; äldre rader utan id matchas på det namn enheten hade
 $c=[]; if($ids){ $c[]='server_id IN ('.implode(',',$ids).')'; $q0=db()->prepare('SELECT name,is_services FROM servers WHERE id IN ('.implode(',',$ids).')'); $q0->execute(); foreach($q0 as $sx){ $nn=$sx['is_services']?['Tjänster','Services']:[$sx['name']]; foreach($nn as $n1){ $c[]='(server_id IS NULL AND server_name=?)'; $p[]=$n1; } } }
 foreach($nms as $n1){ $c[]='server_name=?'; $p[]=$n1; } if($c) $w[]='('.implode(' OR ',$c).')'; }
$where=$w?' WHERE '.implode(' AND ',$w):'';
// värden till flervalslistorna
$lst=function($col) use($tbl,$v){ $q=db()->prepare('SELECT DISTINCT '.$col.' FROM '.$tbl.($v==='alerts'?'':' WHERE svc=?').' ORDER BY '.$col.' LIMIT 500'); $q->execute($v==='alerts'?[]:[$v==='svc'?1:0]); return array_values(array_filter($q->fetchAll(PDO::FETCH_COLUMN),function($x){ return $x!==null&&$x!==''; })); };
// Enheter: alla servrar, virtuella hosts och nätverksenheter, oavsett om de haft händelser. Värdet är server-id och namnet är detsamma som på dashboardkortet.
// Namn som bara finns i äldre loggrader (borttagna enheter) läggs sist.
$optSrv=[]; $srvLbl=[]; $titleOf=[];
foreach(db()->query('SELECT s.*,(SELECT COUNT(*) FROM server_checks c WHERE c.server_id=s.id AND c.last_check IS NOT NULL) AS chk FROM servers s') as $x){ $titleOf[(int)$x['id']]=srv_title($x); $x['_t']=$titleOf[(int)$x['id']]; $allSrv[]=$x; }
// äldre rader saknar id: visa kortnamnet om det sparade namnet entydigt pekar på en enhet
$nmCount=[]; foreach($allSrv as $x) if(empty($x['is_services'])) $nmCount[$x['name']]=($nmCount[$x['name']]??0)+1;
$nameTitle=[]; foreach($allSrv as $x){ if(!empty($x['is_services'])){ $nameTitle['Tjänster']=$nameTitle['Services']=$x['_t']; } elseif($nmCount[$x['name']]===1) $nameTitle[$x['name']]=$x['_t']; }
$shown=function($r) use($titleOf,$nameTitle){ if(!empty($r['server_id'])&&isset($titleOf[(int)$r['server_id']])) return $titleOf[(int)$r['server_id']]; return $nameTitle[$r['server_name']]??$r['server_name']; };
if($v!=='svc'){
 $g=[t('Servrar','Servers')=>[],t('Virtuella hosts','Virtual hosts')=>[],t('Nätverksenheter','Network devices')=>[],t('Tjänster','Services')=>[]]; $kS=array_keys($g); $knownN=[];
 usort($allSrv,function($a,$b){ return strnatcasecmp($a['_t'],$b['_t']); });
 foreach($allSrv as $x){ $id=(string)(int)$x['id'];
  if(!empty($x['is_services'])){ if($v==='alerts'){ $g[$kS[3]][]=$id; $srvLbl[$id]=$x['_t']; } $knownN['Tjänster']=$knownN['Services']=1; continue; }
  $k=!empty($x['hv_type'])?1:(!empty($x['nw_type'])?2:0); $g[$kS[$k]][]=$id; $srvLbl[$id]=$x['_t']; $knownN[$x['name']]=1; }
 // äldre rader utan känt id och med okänt namn
 $old=[]; $q0=db()->query('SELECT DISTINCT server_name FROM '.$tbl.' WHERE server_id IS NULL'.($v==='alerts'?'':' AND svc=0').' ORDER BY server_name LIMIT 200');
 foreach($q0->fetchAll(PDO::FETCH_COLUMN) as $x) if($x!==null&&$x!==''&&!isset($knownN[$x])){ $old[]='n:'.$x; $srvLbl['n:'.$x]=$x; }
 if($old) $g[t('Borttagna / okända','Removed / unknown')]=$old;
 $optSrv=array_filter($g);
}
// Regler (larm) respektive checks: alla möjliga, inte bara de som utlösts
$optRule=[]; $ruleLbl=null;
if($v==='alerts'){
 $ML=metric_labels();
 $GR=[t('Resurser','Resources')=>['cpu','mem','swap','disk'],t('Hårdvara','Hardware')=>['hw_temp','hw_fan'],t('Tjänster','Services')=>['check','check_ms'],t('Servrar','Servers')=>['offline','smart','smart_temp','src_snmp','src_zabbix','zbx','src_pbs'],t('Nätverk','Network')=>['net_err','net_drop','net_crc','net_down','net_util','nw_ping','nw_down','nw_temp','nw_psu','nw_fan','nw_hw'],t('Virtualisering','Virtualization')=>['hv_ping','hv_down','hv_cpu','hv_mem','hv_storage','hv_vm_down','hv_vm_cpu','hv_vm_mem'],t('Lagringsenheter','Storage devices')=>['st_ping','st_down','st_temp','st_psu','st_fan','st_hw','st_health'],'BMC'=>['bmc_ping','bmc_down','bmc_temp','bmc_psu','bmc_fan','bmc_hw','bmc_health'],t('VM Monitor själv','VM Monitor itself')=>['sys_worker','sys_zbx','sys_recv']];
 $ruleLbl=$ML; $optRule=$GR; $used=[]; foreach($GR as $a) foreach($a as $k) $used[$k]=1; $oth=[]; foreach($lst('metric') as $x) if(!isset($used[$x])) $oth[]=$x; if($oth) $optRule[t('Övrigt','Other')]=$oth;
} else {
 $lb=[]; $q=db()->prepare('SELECT DISTINCT c.label FROM server_checks c JOIN servers s ON s.id=c.server_id WHERE s.is_services=? AND c.label IS NOT NULL AND c.label<>\'\''); $q->execute([$v==='svc'?1:0]); foreach($q->fetchAll(PDO::FETCH_COLUMN) as $x) $lb[$x]=1;
 foreach($lst('label') as $x) $lb[$x]=1; $optRule=array_keys($lb); natcasesort($optRule); $optRule=array_values($optRule);
}
$cnt=db()->prepare('SELECT COUNT(*) FROM '.$tbl.$where); $cnt->execute($p); $total=(int)$cnt->fetchColumn(); $LIM=500;
// ---- Graf: antal händelser per tidsintervall -----------------------------
$gq=db()->prepare('SELECT ts,'.$evCol.' e FROM '.$tbl.$where.' ORDER BY ts LIMIT 200000'); $gq->execute($p); $grows=$gq->fetchAll();
$chart=null;
if($grows||$from){
 $t0=$from?strtotime(str_replace('T',' ',$from).':00'):strtotime($grows[0]['ts']); $t1=$to?strtotime(str_replace('T',' ',$to).':59'):time(); if($t1<=$t0) $t1=$t0+60;
 $span=$t1-$t0; $steps=[600,1800,3600,10800,21600,43200,86400,604800]; $step=null;
 if($res!=='auto'){ $step=['1h'=>3600,'1d'=>86400,'1w'=>604800][$res]; if($span/$step>400) $step=null; }
 if(!$step){ foreach($steps as $s){ if($span/$s<=100){ $step=$s; break; } } if(!$step) $step=604800; }
 $bs=function($t) use($step){ if($step===604800) return strtotime('monday this week',strtotime(date('Y-m-d',$t))); if($step===86400) return strtotime(date('Y-m-d',$t)); $o=(int)date('Z',$t); return (int)(floor(($t+$o)/$step)*$step-$o); };
 $nx=function($b) use($step){ return $step===604800?strtotime('+7 days',$b):($step===86400?strtotime('+1 day',$b):$b+$step); };
 // Nyckel per intervall. Under ett dygn räknas i lokal tid så att sommar-/vintertidsbyte inte förskjuter staplarna.
 $kf=function($t) use($step,$bs){ return $step<86400?(int)floor(($t+(int)date('Z',$t))/$step):$bs($t); };
 $B=[]; $idx=[];
 if($step<86400){ for($n=$kf($t0),$ne=$kf($t1);$n<=$ne;$n++){ $z=$n*$step; $st=$z-(int)date('Z',$z); $st=$z-(int)date('Z',$st); $idx[$n]=count($B); $B[]=$st; } }
 else { $b=$bs($t0); $guard=0; while($b<=$t1&&$guard++<2000){ $idx[$b]=count($B); $B[]=$b; $b=$nx($b); } }
 $keys=array_keys($evs); $S=[]; foreach($keys as $k) $S[$k]=array_fill(0,count($B),0);
 foreach($grows as $r){ $i=$idx[$kf(strtotime($r['ts']))]??null; if($i!==null&&isset($S[$r['e']])) $S[$r['e']][$i]++; }
 $lab=[]; $full=[]; $f=[]; $tt=[]; $prevD='';
 foreach($B as $i=>$b){ $d=date('m-d',$b); $e=$nx($b);
  if($step<86400){ $lab[]=($d!==$prevD?$d.' ':'').date('H:i',$b); $full[]=date('Y-m-d H:i',$b).' – '.date('H:i',$e); } else { $lab[]=$d; $full[]=$step===604800?date('Y-m-d',$b).' – '.date('Y-m-d',$e-1):date('Y-m-d',$b); }
  $prevD=$d; $f[]=date('Y-m-d\TH:i',$b); $tt[]=date('Y-m-d\TH:i',$e-60); }
 $colors=['alarm'=>'var(--bad)','repeat'=>'var(--warn)','ok'=>'var(--ok)','down'=>'var(--bad)','up'=>'var(--ok)']; $se=[];
 foreach($keys as $k) $se[]=['k'=>$k,'n'=>$evs[$k],'c'=>$colors[$k],'v'=>$S[$k],'t'=>array_sum($S[$k])];
 $chart=['t0'=>date('Y-m-d H:i',$t0),'t1'=>date('Y-m-d H:i',min($t1,time())),'lab'=>$lab,'full'=>$full,'from'=>$f,'to'=>$tt,'s'=>$se,'cap'=>count($grows)>=200000];
}
$base=['v'=>$v]+($fSrv?['srv'=>$fSrv]:[])+($fRule?['rule'=>$fRule]:[])+($fEv?['ev'=>$fEv]:[])+($res!=='auto'?['res'=>$res]:[]);
$qs=function($x) { return 'history.php?'.http_build_query($x); };
$preset=function($h) use($base,$qs){ return $qs($base+['from'=>date('Y-m-d\TH:i',time()-$h*3600)]); };
$active=$from||$to||$fSrv||$fRule||$fEv;
$ms=function($name,$label,$opts,$sel,$lblMap=null){ if(!$opts) return ''; $o='<details class="ms"><summary><span class="mslab">'.h($label).'</span><span class="msn" data-all="'.h(t('Alla','All')).'" data-sel="'.h(t('valda','selected')).'"></span></summary><div class="msl">';
 $one=function($x) use($name,$sel,$lblMap){ $lbl=$lblMap?($lblMap[$x]??$x):$x; return '<label><input type="checkbox" name="'.h($name).'[]" value="'.h($x).'"'.(in_array((string)$x,$sel,true)?' checked':'').'> <span>'.h($lbl).'</span></label>'; };
 if(is_array(reset($opts))){ foreach($opts as $gl=>$xs){ $o.='<div class="msg">'.h($gl).'</div>'; foreach($xs as $x) $o.=$one($x); } } else foreach($opts as $x) $o.=$one($x);
 return $o.'</div></details>'; };
top(t('Historik','History')); ?>
<h2><?= t('Historik','History') ?></h2>
<div class="rngn"><?php foreach(['alerts'=>t('Larm','Alerts'),'svc'=>t('Tjänster','Services'),'srv'=>t('Server-checks','Server checks'),'evt'=>t('Alla events','All events'),'log'=>'Trap'] as $k=>$n) echo '<a class="btn sm'.($v===$k?' on':'').'" href="history.php?v='.$k.'">'.h($n).'</a>'; ?><a class="btn sm" href="history_export.php"><?= t('Export','Export') ?></a></div>
<form method="get" class="card hf" id="hf"><input type="hidden" name="v" value="<?= h($v) ?>">
 <label class="hl"><span><?= t('Från','From') ?></span><input type="datetime-local" name="from" value="<?= h($from) ?>"></label>
 <label class="hl"><span><?= t('Till','To') ?></span><input type="datetime-local" name="to" value="<?= h($to) ?>"></label>
 <?= $ms('srv',t('Enhet','Unit'),$optSrv,$fSrv,$srvLbl) ?>
 <?= $ms('rule',$v==='alerts'?t('Regel','Rule'):t('Check','Check'),$optRule,$fRule,$ruleLbl) ?>
 <?= $ms('ev',t('Händelse','Event'),array_keys($evs),$fEv,$evs) ?>
 <label class="hl"><span><?= t('Graf: intervall','Graph: interval') ?></span><select name="res"><?php foreach(['auto'=>t('Auto','Auto'),'1h'=>t('Timme','Hour'),'1d'=>t('Dag','Day'),'1w'=>t('Vecka','Week')] as $k=>$n) echo '<option value="'.$k.'"'.($res===$k?' selected':'').'>'.h($n).'</option>'; ?></select></label>
 <div class="hbt"><button class="btn" type="submit"><?= t('Filtrera','Filter') ?></button><a class="btn sm gh" href="history.php?v=<?= h($v) ?>"><?= t('Återställ filter','Reset filters') ?></a></div>
 <div class="hq"><span class="mut"><?= t('Snabbval','Quick') ?>:</span><a class="btn sm gh" href="<?= h($preset(24)) ?>">24 h</a><a class="btn sm gh" href="<?= h($preset(168)) ?>">7 <?= t('dagar','days') ?></a><a class="btn sm gh" href="<?= h($preset(720)) ?>">30 <?= t('dagar','days') ?></a><a class="btn sm gh" href="<?= h($preset(2160)) ?>">90 <?= t('dagar','days') ?></a></div>
</form>
<div class="card hg"><div class="hgh"><b><?= t('Antal händelser','Number of events') ?></b><span class="mut"><?= $chart?h($chart['t0']).' → '.h($to?$chart['t1']:date('Y-m-d H:i')):'' ?></span><span class="hleg" id="hleg"></span></div>
<?php if($chart&&$total): ?><div id="hchart" class="hchart"></div><p class="mut hhint"><?= t('Klicka på en stapel för att filtrera på just det intervallet.','Click a bar to filter on that interval.') ?><?= $chart['cap']?' '.t('(graf begränsad till de första 200 000 händelserna)','(graph limited to the first 200,000 events)'):'' ?></p>
<?php else: ?><p class="mut" style="margin:14px 0 4px"><?= t('Inga händelser i valt tidsspann.','No events in the selected time range.') ?></p><?php endif; ?></div>
<p class="mut"><?= $total>$LIM?sprintf(t('Visar de senaste %d av %d händelser.','Showing the latest %d of %d events.'),$LIM,$total):sprintf(t('%d händelser.','%d events.'),$total) ?><?= $active?'':' '.t('(inget filter aktivt)','(no filter active)') ?></p>
<?php if($v==='alerts'){ $ipKind=[]; $ipLinks=function($msg,$ts) use(&$ipKind){ $out=''; $pos=0; if(!preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/',$msg,$mm,PREG_OFFSET_CAPTURE)) return h($msg); $t=strtotime($ts)?:time();
 foreach($mm[0] as [$ip,$off]){ $out.=h(substr($msg,$pos,$off-$pos)); $pos=$off+strlen($ip); if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){ $out.=h($ip); continue; }
  $k=$ipKind[$ip]??null; if($k===null){ $k='syslog'; $fw=false; try{ $q=db()->prepare('SELECT kind FROM log_events WHERE fw_src=? AND ts>=? ORDER BY id DESC LIMIT 1'); $q->execute([$ip,date('Y-m-d H:i:s',$t-86400)]); $x=$q->fetchColumn(); $fw=(bool)$x; if(!$x){ $q=db()->prepare('SELECT kind FROM log_events WHERE src_ip=? AND ts BETWEEN ? AND ? ORDER BY id DESC LIMIT 1'); $q->execute([$ip,date('Y-m-d H:i:s',$t-86400),date('Y-m-d H:i:s',$t+60)]); $x=$q->fetchColumn(); } if($x) $k=$x; }catch(Throwable $e){} $ipKind[$ip]=$k.($fw?'+fw':''); } $k=$ipKind[$ip]; $fwl=substr($k,-3)==='+fw'; $k=$fwl?'syslog':$k;
  $qs=[$fwl?'src':'q'=>$ip,'from'=>date('Y-m-d\TH:i',$t-86400),'to'=>date('Y-m-d\TH:i',$t+3600)]; $url=$k==='trap'?'history.php?v=log&'.http_build_query($qs):'syslog.php?'.http_build_query($qs);
  $out.='<a href="'.h($url).'" title="'.h($k==='trap'?t('Visa traps från denna IP','Show traps from this IP'):t('Visa syslog från denna IP','Show syslog from this IP')).'">'.h($ip).'</a>'; }
 return $out.h(substr($msg,$pos)); }; } ?>
<?php if($v==='alerts'): $ev=['alarm'=>[t('Larm','Alert'),'badt'],'repeat'=>[t('Påminnelse','Reminder'),'badt'],'ok'=>[t('Återställt','Recovered'),'okt']]; ?>
<div class="tw"><table class="card"<?= tc_attr('h_alerts') ?>><tr><th data-k="ts"><?= t('Tid','Time') ?></th><th data-k="srv">Server</th><th data-k="rule"><?= t('Regel','Rule') ?></th><th data-k="ev"><?= t('Händelse','Event') ?></th><th data-k="msg"><?= t('Detaljer','Details') ?></th><th data-k="dl"><?= t('Leverans','Delivery') ?></th><?= tc_th('date',t('Datum','Date')) ?><?= tc_th('dsum',t('Leverans (sammanfattning)','Delivery (summary)')) ?></tr>
<?php $q=db()->prepare('SELECT * FROM alert_log'.$where.' ORDER BY id DESC LIMIT '.$LIM); $q->execute($p); foreach($q as $r): ?>
<tr><td><?= h($r['ts']) ?></td><td><?= h($shown($r)) ?></td><td><?= h(metric_label($r['metric'])) ?></td><td class="<?= $ev[$r['event']][1] ?>"><?= $ev[$r['event']][0] ?></td><td class="det"><?= $r['metric']==='log_event'?$ipLinks((string)$r['message'],(string)$r['ts']):h($r['message']) ?></td><td class="det"><?php foreach(preg_split('/; (?=(?:Email|Telegram|Webhook|Signal|SMS): )/',(string)$r['mail_result']) as $i=>$pt) echo ($i?'<br>':'').'<span class="'.(preg_match('/^(?:\w+: )?OK$/',$pt)?'okt':'badt').'">'.h($pt).'</span>'; ?></td><?php $pts=preg_split('/; (?=(?:Email|Telegram|Webhook|Signal|SMS): )/',(string)$r['mail_result']); $okn=count(array_filter($pts,fn($x)=>preg_match('/^(?:\w+: )?OK$/',$x))); echo tc_td(substr((string)$r['ts'],0,10)),((string)$r['mail_result']===''?'<td class="mut">–</td>':'<td class="'.($okn===count($pts)?'okt':'badt').'">'.$okn.' / '.count($pts).' OK</td>'); ?></tr>
<?php endforeach; ?></table></div>
<?php else: $svc=$v==='svc'?1:0; $q=db()->prepare('SELECT * FROM check_events'.$where.' ORDER BY id DESC LIMIT '.$LIM); $q->execute($p); ?>
<div class="tw"><table class="card"<?= tc_attr($svc?'h_svc':'h_srv') ?>><tr><th data-k="ts"><?= t('Tid','Time') ?></th><?php if(!$svc) echo '<th data-k="srv">Server</th>'; ?><th data-k="chk"><?= t('Check','Check') ?></th><?php if($svc) echo '<th data-k="target">'.t('Mål','Target').'</th>'; ?><th data-k="ev"><?= t('Händelse','Event') ?></th><th data-k="msg"><?= t('Detaljer','Details') ?></th><th data-k="dur"><?= t('Var nere i','Was down for') ?></th><?= tc_th('date',t('Datum','Date')) ?><?php if(!$svc) echo tc_th('target',t('Mål','Target')); ?></tr>
<?php foreach($q as $r): $up=$r['status']==='up'; ?>
<tr><td><?= h($r['ts']) ?></td><?php if(!$svc) echo '<td>'.h($shown($r)).'</td>'; ?><td><?= h($r['label']) ?></td><?php if($svc) echo '<td class="det">'.h($r['target']).'</td>'; ?><td class="<?= $up?'okt':'badt' ?>"><?= $up?t('Uppe igen','Back up'):t('Nere','Down') ?></td><td class="det"><?= h($r['detail']) ?></td><td><?= $up?fmt_dur($r['dur']):'' ?></td><?= tc_td(substr((string)$r['ts'],0,10)) ?><?php if(!$svc) echo tc_td($r['target']); ?></tr>
<?php endforeach; ?></table></div>
<?php endif; ?>
<script>
(function(){
 // flervalslistor: visa "Alla" eller "N valda"
 var ms=document.querySelectorAll('details.ms');
 function upd(d){var n=d.querySelectorAll('input:checked').length,e=d.querySelector('.msn');e.textContent=n?n+' '+e.dataset.sel:e.dataset.all;d.classList.toggle('on',n>0)}
 ms.forEach(function(d){upd(d);d.addEventListener('change',function(){upd(d)})});
 document.addEventListener('click',function(e){ms.forEach(function(d){if(!d.contains(e.target))d.open=false})});
 document.addEventListener('keydown',function(e){if(e.key==='Escape')ms.forEach(function(d){d.open=false})});
 var C=<?= json_encode($chart&&$total?$chart:null,JSON_UNESCAPED_UNICODE) ?>; if(!C)return;
 var host=document.getElementById('hchart'),leg=document.getElementById('hleg'),NS='http://www.w3.org/2000/svg',tip=document.createElement('div');tip.className='htip';tip.hidden=true;host.appendChild(tip);
 leg.innerHTML=C.s.map(function(s){return '<span><i style="background:'+s.c+'"></i>'+s.n+' <b>'+s.t+'</b></span>'}).join('');
 function el(n,a,p){var e=document.createElementNS(NS,n);for(var k in a)e.setAttribute(k,a[k]);if(p)p.appendChild(e);return e}
 function nice(m){var s=Math.max(1,Math.ceil(m/4)),e=Math.pow(10,Math.floor(Math.log10(s))),f=s/e,n=f<=1?1:f<=2?2:f<=5?5:10;return n*e*4}
 function draw(){
  var old=host.querySelector('svg');if(old)old.remove();
  var W=Math.max(300,host.clientWidth),H=Math.max(180,host.clientHeight),L=40,R=8,T=8,Bm=34,n=C.lab.length,pw=W-L-R,ph=H-T-Bm;
  var tot=C.lab.map(function(_,i){return C.s.reduce(function(a,s){return a+s.v[i]},0)}),mx=Math.max.apply(null,tot),top=nice(Math.max(mx,1)),step=top/4;
  var svg=el('svg',{width:W,height:H,viewBox:'0 0 '+W+' '+H,role:'img','aria-label':'Events'});host.insertBefore(svg,tip);
  for(var g=0;g<=4;g++){var y=T+ph-ph*g/4;el('line',{x1:L,x2:W-R,y1:y,y2:y,stroke:'var(--bd)','stroke-width':1},svg);var tx=el('text',{x:L-6,y:y+4,'text-anchor':'end',fill:'var(--mut)','font-size':11},svg);tx.textContent=step*g}
  var bw=pw/n,gap=Math.min(2,bw*.2),every=Math.max(1,Math.ceil(70/bw));
  for(var i=0;i<n;i++){
   var x=L+i*bw,yb=T+ph,grp=el('g',{'class':'hb'},svg);
   el('rect',{x:x,y:T,width:bw,height:ph,fill:'transparent'},grp);
   C.s.forEach(function(s){var v=s.v[i];if(!v)return;var h=ph*v/top;yb-=h;el('rect',{x:x+gap/2,y:yb,width:Math.max(1,bw-gap),height:h,fill:s.c,rx:Math.min(2,bw/4)},grp)});
   if(i%every===0){var cx=x+bw/2,ed=cx>W-R-34,lb=el('text',{x:ed?W-R:cx,y:H-Bm+16,'text-anchor':ed?'end':'middle',fill:'var(--mut)','font-size':11},svg);lb.textContent=C.lab[i]}
   (function(i,grp){
    grp.addEventListener('mousemove',function(ev){var r=host.getBoundingClientRect();tip.hidden=false;
     tip.innerHTML='<b>'+C.full[i]+'</b><br>'+C.s.map(function(s){return '<span><i style="background:'+s.c+'"></i>'+s.n+': '+s.v[i]+'</span>'}).join('<br>');
     var tx=ev.clientX-r.left+14;if(tx+tip.offsetWidth>r.width)tx=ev.clientX-r.left-tip.offsetWidth-14;tip.style.left=Math.max(0,tx)+'px';tip.style.top=Math.max(0,ev.clientY-r.top-10)+'px'});
    grp.addEventListener('mouseleave',function(){tip.hidden=true});
    grp.addEventListener('click',function(){var f=document.getElementById('hf');f.elements.from.value=C.from[i];f.elements.to.value=C.to[i];f.submit()});
   })(i,grp);
  }
 }
 draw();var rt;addEventListener('resize',function(){clearTimeout(rt);rt=setTimeout(draw,120)});
})();
</script>
<?php bottom();
