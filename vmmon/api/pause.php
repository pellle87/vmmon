<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
if(!is_admin()){ http_response_code(403); exit('{}'); }
$id=(int)($_POST['id']??0); $m=(int)($_POST['mins']??-1); $pdo=db();
$q=$pdo->prepare('SELECT name FROM servers WHERE id=?'); $q->execute([$id]); $nm=$q->fetchColumn(); if($nm===false){ exit('{}'); }
if($m<0){ $pdo->prepare('UPDATE servers SET maint_until=NULL WHERE id=?')->execute([$id]); applog('info','config',"$nm: alerts resumed"); }
elseif($m>0){ $m=min($m,525600); $pdo->prepare('UPDATE servers SET maint_until=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE id=?')->execute([$m,$id]); applog('info','config',"$nm: alerts paused $m min"); }
else { $pdo->prepare("UPDATE servers SET maint_until='9999-12-31 23:59:59' WHERE id=?")->execute([$id]); applog('info','config',"$nm: alerts paused indefinitely"); }
echo '{"ok":1}';
