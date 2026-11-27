<?php require '../config.php'; require '../lib.php'; need_login(); csrf_check(); header('Content-Type: application/json');
$k=$_POST['k']??''; $v=(string)($_POST['v']??'');
if(!preg_match('/^cols:(servers|hosts|network|storage|bmc|services|h_alerts|h_svc|h_srv|alert_rules|dt_[a-z0-9_]{1,50})$/',$k)||strlen($v)>6000){ http_response_code(400); echo '{"ok":false}'; exit; }
try{ $uid=(int)$_SESSION['u']['id'];
 if($v===''){ db()->prepare('DELETE FROM user_prefs WHERE user_id=? AND k=?')->execute([$uid,$k]); }
 else { $j=json_decode($v,true); if(!is_array($j)){ http_response_code(400); echo '{"ok":false}'; exit; }
  $c=['o'=>array_values(array_filter(array_map('strval',(array)($j['o']??[])),fn($x)=>preg_match('/^[a-z0-9_]{1,20}$/i',$x))),'v'=>array_values(array_filter(array_map('strval',(array)($j['v']??[])),fn($x)=>preg_match('/^[a-z0-9_]{1,20}$/i',$x))),'w'=>[]];
  foreach((array)($j['w']??[]) as $a=>$b) if(preg_match('/^[a-z0-9_]{1,20}$/i',(string)$a)&&is_numeric($b)) $c['w'][$a]=max(30,min(1200,(int)$b));
  db()->prepare('INSERT INTO user_prefs(user_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([$uid,$k,json_encode($c)]); }
 echo '{"ok":true}'; }
catch(Throwable $e){ http_response_code(500); echo '{"ok":false,"error":"sql/3.15.sql saknas?"}'; }
