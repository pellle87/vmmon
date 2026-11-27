<?php require '../config.php'; require '../lib.php'; need_login(); csrf_check();
$k=$_POST['k']??''; $v=$_POST['v']??''; $ok=['sort'=>['layout','crit'],'view'=>['normal','compact']];
header('Content-Type: application/json');
if($k==='lay'&&in_array($v,['1','2','3'],true)){ try{ db()->prepare('INSERT INTO user_prefs(user_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([(int)$_SESSION['u']['id'],'lay',$v]); echo '{"ok":true}'; }catch(Throwable $e){ http_response_code(500); echo '{"ok":false}'; } exit; }
if(!isset($ok[$k])||!in_array($v,$ok[$k],true)){ http_response_code(400); echo '{"ok":false}'; exit; }
try{ db()->prepare('UPDATE users SET dash_'.$k.'=? WHERE id=?')->execute([$v,$_SESSION['u']['id']]); echo '{"ok":true}'; }
catch(Throwable $e){ http_response_code(500); echo '{"ok":false,"error":"sql/migrate_v22.sql saknas?"}'; }
