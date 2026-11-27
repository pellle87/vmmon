<?php require '../config.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$cid=(int)($_GET['id']??0); $r=$_GET['range']??'24h'; $now=time(); $pdo=db();
$secs=['24h'=>86400,'7d'=>604800,'30d'=>2592000][$r]??null;
try{
if($secs===null){ $q=$pdo->prepare('SELECT UNIX_TIMESTAMP(MIN(bk)) FROM check_ms_hist WHERE check_id=?'); $q->execute([$cid]); $m=$q->fetchColumn(); $secs=$m?max(3600,$now-(int)$m):86400; }
$size=max(300,(int)ceil($secs/300/300)*300);
$q=$pdo->prepare("SELECT FLOOR(UNIX_TIMESTAMP(bk)/$size)*$size AS t, SUM(ms_sum)/NULLIF(SUM(n),0) AS ms, MAX(ms_max) AS ms_max FROM check_ms_hist WHERE check_id=? AND bk>=FROM_UNIXTIME(?) GROUP BY t ORDER BY t");
$q->execute([$cid,$now-$secs]); $out=[];
foreach($q as $p) $out[]=['t'=>(int)$p['t'],'ms'=>round((float)$p['ms'],1),'ms_max'=>round((float)$p['ms_max'],1)];
echo json_encode(['points'=>$out,'size'=>$size,'range'=>$r]);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['points'=>[],'error'=>'sql/migrate_v23.sql saknas?']); }
