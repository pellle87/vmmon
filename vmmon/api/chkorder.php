<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
if(!is_admin()){ http_response_code(403); exit('{"ok":false}'); }
$sid=(int)($_POST['sid']??0); $ids=json_decode((string)($_POST['ids']??'[]'),true); if(!is_array($ids)) $ids=[]; $ids=array_values(array_unique(array_filter(array_map(fn($x)=>in_array($x,['h','tn'],true)?$x:(int)$x,$ids))));
if(!$sid||!$ids){ http_response_code(400); exit('{"ok":false}'); }
try{ $u=db()->prepare('UPDATE server_checks SET sort=? WHERE id=? AND server_id=?'); foreach($ids as $i=>$c){ if($c==='h'||$c==='tn') db()->prepare('UPDATE servers SET '.($c==='h'?'host_sort':'tn_sort').'=? WHERE id=?')->execute([$i+1,$sid]); else $u->execute([$i+1,$c,$sid]); } echo '{"ok":true}'; }
catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/3.14.sql saknas?']); }
