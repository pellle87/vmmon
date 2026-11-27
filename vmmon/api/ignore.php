<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
if(!is_admin()){ http_response_code(403); exit('{}'); }
db()->prepare('UPDATE servers SET ignored=1-ignored WHERE id=?')->execute([(int)($_POST['id']??0)]); echo '{"ok":1}';
