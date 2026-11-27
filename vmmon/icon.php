<?php require 'config.php'; require 'lib.php'; need_login(); session_write_close();
$q=db()->prepare('SELECT svg FROM icons WHERE id=?'); $q->execute([(int)($_GET['id']??0)]); $s=$q->fetchColumn();
if($s===false){ http_response_code(404); exit; }
header('Content-Type: image/svg+xml'); header('Cache-Control: private, max-age=86400'); header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox"); header('X-Content-Type-Options: nosniff'); echo $s;
