<?php require 'config.php'; require 'lib.php'; need_login(); track_user(); session_write_close();
header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode(['href'=>theme_href()]);
