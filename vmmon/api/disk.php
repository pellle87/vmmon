<?php require '../config.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$sid=(int)($_GET['server']??0); $dk=(string)($_GET['disk']??''); $r=$_GET['range']??'24h'; $now=time(); $pdo=db();
$secs=['24h'=>86400,'7d'=>604800,'30d'=>2592000][$r]??86400;
try{
$size=max(300,(int)ceil($secs/300/300)*300);
$q=$pdo->prepare("SELECT FLOOR(UNIX_TIMESTAMP(bk)/$size)*$size AS t, SUM(used_sum)/NULLIF(SUM(n),0) AS used, MAX(used_max) AS used_max FROM disk_hist WHERE server_id=? AND dk=? AND bk>=FROM_UNIXTIME(?) GROUP BY t ORDER BY t");
$q->execute([$sid,$dk,$now-$secs]); $out=[];
foreach($q as $p) $out[]=['t'=>(int)$p['t'],'used'=>round((float)$p['used'],1),'used_max'=>round((float)$p['used_max'],1)];
echo json_encode(['points'=>$out,'size'=>$size,'range'=>$r]);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['points'=>[],'error'=>'sql/migrate_v24.sql saknas?']); }
