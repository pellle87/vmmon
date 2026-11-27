<?php require '../config.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$sid=(int)($_GET['server']??0); $ifx=(int)($_GET['ifx']??0); $r=$_GET['range']??'24h'; $now=time(); $pdo=db();
$secs=['24h'=>86400,'7d'=>604800,'30d'=>2592000][$r]??null;
if($secs===null){ $q=$pdo->prepare('SELECT UNIX_TIMESTAMP(MIN(bk)) FROM net_hist WHERE server_id=? AND if_index=?'); $q->execute([$sid,$ifx]); $m=$q->fetchColumn(); $secs=$m?max(3600,$now-(int)$m):86400; }
$size=max(300,(int)ceil($secs/300/300)*300);
$q=$pdo->prepare("SELECT FLOOR(UNIX_TIMESTAMP(bk)/$size)*$size AS t, SUM(in_sum)/SUM(n) AS inb, MAX(in_max) AS inb_max, SUM(out_sum)/SUM(n) AS outb, MAX(out_max) AS outb_max, SUM(err_sum)/SUM(n) AS err, MAX(err_max) AS err_max, SUM(disc_sum)/SUM(n) AS disc, MAX(disc_max) AS disc_max FROM net_hist WHERE server_id=? AND if_index=? AND bk>=FROM_UNIXTIME(?) GROUP BY t ORDER BY t");
$q->execute([$sid,$ifx,$now-$secs]); $out=[]; $f=fn($v,$d=0)=>$v===null?null:round((float)$v,$d);
foreach($q as $p) $out[]=['t'=>(int)$p['t'],'inb'=>$f($p['inb']),'inb_max'=>$f($p['inb_max']),'outb'=>$f($p['outb']),'outb_max'=>$f($p['outb_max']),'err'=>$f($p['err'],2),'err_max'=>$f($p['err_max'],2),'disc'=>$f($p['disc'],2),'disc_max'=>$f($p['disc_max'],2)];
echo json_encode(['points'=>$out,'size'=>$size,'range'=>$r]);
