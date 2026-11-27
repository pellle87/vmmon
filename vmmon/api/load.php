<?php require '../config.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$sid=(int)($_GET['server']??0); $r=$_GET['range']??'24h'; $now=time(); $pdo=db();
$secs=['24h'=>86400,'7d'=>604800,'30d'=>2592000][$r]??null;
if($secs===null){ $q=$pdo->prepare('SELECT UNIX_TIMESTAMP(MIN(bk)) FROM load_hist WHERE server_id=?'); $q->execute([$sid]); $m=$q->fetchColumn(); $secs=$m?max(3600,$now-(int)$m):86400; }
$size=max(300,(int)ceil($secs/300/300)*300);
$q=$pdo->prepare("SELECT FLOOR(UNIX_TIMESTAMP(bk)/$size)*$size AS t, SUM(cpu_sum)/NULLIF(SUM(cpu_n),0) AS cpu, MAX(CASE WHEN cpu_n>0 THEN cpu_max END) AS cpu_max, SUM(mem_sum)/NULLIF(SUM(mem_n),0) AS mem, MAX(CASE WHEN mem_n>0 THEN mem_max END) AS mem_max FROM load_hist WHERE server_id=? AND bk>=FROM_UNIXTIME(?) GROUP BY t ORDER BY t");
$q->execute([$sid,$now-$secs]); $out=[]; $f=fn($v)=>$v===null?null:round((float)$v,1);
foreach($q as $p) $out[]=['t'=>(int)$p['t'],'cpu'=>$f($p['cpu']),'cpu_max'=>$f($p['cpu_max']),'mem'=>$f($p['mem']),'mem_max'=>$f($p['mem_max'])];
echo json_encode(['points'=>$out,'size'=>$size,'range'=>$r]);
