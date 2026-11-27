<?php // Historik → Brandvägg, sammanställning: dygnssummor ur tabellen log_fw_daily (fylls av mottagaren i realtid, oberoende av hur länge råloggen sparas).
require_once __DIR__.'/config.php'; require_once __DIR__.'/lib.php';
if(!function_exists('top')||!isset($_SESSION['u'])){ http_response_code(403); exit; }
$dv=function($k,$d){ $s=trim((string)($_GET[$k]??'')); return preg_match('/^\d{4}-\d{2}-\d{2}$/',$s)&&strtotime($s)!==false?$s:$d; };
$from=$dv('from',date('Y-m-d',time()-6*86400)); $to=$dv('to',date('Y-m-d')); if($from>$to){ $x=$from; $from=$to; $to=$x; }
$fd=trim((string)($_GET['dev']??'')); $fa=in_array($_GET['act']??'block',['block','pass','all'],true)?($_GET['act']??'block'):'block';
$w=['day>=?','day<=?']; $p=[$from,$to]; if($fd==='u') $w[]='server_id=0'; elseif(ctype_digit($fd)&&$fd!==''){ $w[]='server_id=?'; $p[]=(int)$fd; }
$wh=' WHERE '.implode(' AND ',$w); $wa=$wh; $pa=$p; if($fa!=='all'){ $wa.=' AND act=?'; $pa[]=$fa; }
$err=''; $days=[]; $tops=[]; $devs=[]; $any=false;
try{ $devs=db()->query('SELECT id,name FROM servers WHERE is_services=0 ORDER BY name')->fetchAll();
 $q=db()->prepare('SELECT day,act,SUM(cnt) c FROM log_fw_daily'.$wh.' GROUP BY day,act ORDER BY day DESC'); $q->execute($p); foreach($q->fetchAll() as $r){ $days[$r['day']][$r['act']]=(int)$r['c']; }
 foreach(['src'=>'src','dst'=>'dst','dport'=>"CONCAT(proto,'/',dport)",'rule'=>'rule'] as $k=>$col){ $g=$k==='dport'?'proto,dport':$k; $q=db()->prepare("SELECT $col AS v, SUM(cnt) c FROM log_fw_daily$wa AND ".($k==='rule'?"rule<>''":($k==='dport'?'dport>0':"$k<>''"))." GROUP BY $g ORDER BY c DESC LIMIT 15"); $q->execute($pa); $tops[$k]=$q->fetchAll(); }
 $any=(bool)db()->query('SELECT 1 FROM log_fw_daily LIMIT 1')->fetchColumn();
}catch(Throwable $e){ $err=t('Tabellen log_fw_daily saknas – kör den senaste SQL-filen under fliken Uppdatera.','The table log_fw_daily is missing – run the latest SQL file on the Update tab.'); }
function ls_link($k,$v,$from,$to,$fd,$fa){ $q=['from'=>$from.'T00:00','to'=>$to.'T23:59']; if($fd!=='') $q['dev']=$fd; if($fa!=='all') $q['act']=$fa;
 if($k==='dport'){ [$pr,$po]=explode('/',$v,2)+['','']; if($pr!=='') $q['proto']=$pr; $q['dport']=$po; } else $q[$k]=$v; return 'syslog.php?'.http_build_query($q); }
top('Syslog'); flash(); ?>
<h2>Syslog</h2>
<div class="rngn"><a class="btn sm" href="syslog.php"><?= t('Loggrader','Log lines') ?></a><a class="btn sm on" href="syslog.php?v=sum"><?= t('Brandvägg, sammanställning','Firewall summary') ?></a></div>
<p class="mut"><?= t('Dygnssummor av brandväggstrafik (pass och block) som mottagaren räknar upp löpande. Räknas även när "spara inte pass" är på, och finns kvar länge efter att råloggen rensats. Siffrorna börjar räknas från den dag uppdateringen installerades. Klicka på ett värde för att se råloggraderna.','Daily totals of firewall traffic (pass and block) counted continuously by the receiver. Pass traffic is counted even when "do not store pass" is on, and the totals remain long after the raw log is cleaned. Counting starts the day the update was installed. Click a value to see the raw log lines.') ?></p>
<?php if($err): ?><p class="card badt"><?= h($err) ?></p><?php else: ?>
<form method="get" class="card hf"><input type="hidden" name="v" value="sum">
 <label class="hl"><span><?= t('Från','From') ?></span><input type="date" name="from" value="<?= h($from) ?>"></label>
 <label class="hl"><span><?= t('Till','To') ?></span><input type="date" name="to" value="<?= h($to) ?>"></label>
 <label class="hl"><span><?= t('Enhet','Device') ?></span><select name="dev"><option value=""><?= t('Alla','All') ?></option><option value="u"<?= $fd==='u'?' selected':'' ?>><?= t('Okänd avsändare','Unknown sender') ?></option><?php foreach($devs as $d) echo '<option value="'.(int)$d['id'].'"'.($fd===(string)$d['id']?' selected':'').'>'.h($d['name']).'</option>'; ?></select></label>
 <label class="hl"><span><?= t('Topplistor för','Top lists for') ?></span><select name="act"><option value="block"<?= $fa==='block'?' selected':'' ?>>Block</option><option value="pass"<?= $fa==='pass'?' selected':'' ?>>Pass</option><option value="all"<?= $fa==='all'?' selected':'' ?>><?= t('Båda','Both') ?></option></select></label>
 <button class="btn sm"><?= t('Visa','Show') ?></button></form>
<?php if(!$days): ?><p class="card mut"><?= $any?t('Ingen trafik i valt tidsspann.','No traffic in the selected period.'):t('Ingen sammanställning ännu. Den fylls när mottagaren tar emot brandväggsloggar.','No summary yet. It fills as the receiver gets firewall logs.') ?></p><?php else: $tp=0; $tb=0; foreach($days as $d) { $tp+=$d['pass']??0; $tb+=$d['block']??0; } ?>
<div class="tw"><table class="card"><tr><th><?= t('Dag','Day') ?></th><th style="text-align:right">Pass</th><th style="text-align:right">Block</th></tr>
<?php foreach($days as $dy=>$d): ?><tr><td><a href="<?= h('syslog.php?'.http_build_query(array_filter(['from'=>$dy.'T00:00','to'=>$dy.'T23:59','dev'=>$fd]))) ?>"><?= h($dy) ?></a></td><td style="text-align:right"><?= number_format($d['pass']??0,0,',',' ') ?></td><td style="text-align:right"><?= number_format($d['block']??0,0,',',' ') ?></td></tr><?php endforeach; ?>
<tr><th><?= t('Summa','Total') ?></th><th style="text-align:right"><?= number_format($tp,0,',',' ') ?></th><th style="text-align:right"><?= number_format($tb,0,',',' ') ?></th></tr></table></div>
<?php foreach(['src'=>t('Vanligaste källor (från IP)','Top sources (from IP)'),'dst'=>t('Vanligaste mål (till IP)','Top destinations (to IP)'),'dport'=>t('Vanligaste målportar','Top destination ports'),'rule'=>t('Vanligaste regler','Top rules')] as $k=>$ttl): if(empty($tops[$k])) continue; ?>
<h3><?= h($ttl) ?></h3><div class="tw"><table class="card"><tr><th><?= h($ttl) ?></th><th style="text-align:right"><?= t('Antal','Count') ?></th></tr>
<?php foreach($tops[$k] as $r): ?><tr><td><a href="<?= h(ls_link($k,$r['v'],$from,$to,$fd,$fa)) ?>"><?= h($r['v']) ?></a></td><td style="text-align:right"><?= number_format((int)$r['c'],0,',',' ') ?></td></tr><?php endforeach; ?></table></div>
<?php endforeach; endif; endif; ?>
<?php if(function_exists('bottom')) bottom(); exit;
