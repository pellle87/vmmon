<?php require '../config.php'; require '../lib.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$b=(int)setting('worker_beat',0); $z=(int)setting('zbx_active_beat',0); echo json_encode(['age'=>$b>0?max(0,time()-$b):null,'z_on'=>setting('zbx_active_on','0')==='1','z_age'=>$z>0?max(0,time()-$z):null]);
