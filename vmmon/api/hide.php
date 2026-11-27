<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
$view=($_POST['view']??'')==='compact'?'compact':'normal'; $ids=json_decode((string)($_POST['ids']??'[]'),true); if(!is_array($ids)) $ids=[];
$ids=array_values(array_unique(array_filter(array_map('strval',$ids),fn($x)=>preg_match('/^\\d+(_\\d+)?$/',$x)===1)));
try{ db()->prepare('INSERT INTO user_prefs(user_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([(int)$_SESSION['u']['id'],'hide:'.(lay_n($_POST['lay']??1)>1?lay_n($_POST['lay']).':':'').$view,json_encode($ids)]); echo '{"ok":true}'; }
catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'user_prefs saknas (sql/3.07.sql?)']); }
