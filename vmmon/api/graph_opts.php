<?php require '../config.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$sid=(int)($_GET['server']??0); $out=[];
try{ $q=db()->prepare('SELECT if_index,name,alias FROM net_if WHERE server_id=? ORDER BY if_index LIMIT 200'); $q->execute([$sid]);
 foreach($q as $r) $out[]=['i'=>(int)$r['if_index'],'n'=>(string)($r['name']?:('if'.$r['if_index'])),'a'=>(string)($r['alias']??'')]; }catch(Throwable $e){}
echo json_encode(['ifs'=>$out]);
