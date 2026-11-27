<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
if(!is_admin()){ http_response_code(403); exit('{"ok":false}'); }
$t=($_POST['k']??'')==='group'?'group':'server'; $id=(int)($_POST['id']??0); $ic=trim((string)($_POST['icon']??''));
if(!$id||!icon_spec_ok($ic)){ http_response_code(400); exit('{"ok":false}'); }
try{ db()->prepare($t==='group'?'UPDATE device_groups SET icon=? WHERE id=?':'UPDATE servers SET icon=? WHERE id=?')->execute([$ic,$id]); echo json_encode(['ok'=>true,'u'=>icon_url($ic)]); }
catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/3.10.sql saknas?']); }
