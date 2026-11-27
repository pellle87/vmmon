<?php // Syslog: egen huvudsida (loggrader med filter, och Brandvägg-sammanställning under v=sum).
require 'config.php'; require 'lib.php'; need_login(); track_user(); csrf_check();
$LOGMODE='syslog'; require __DIR__.(($_GET['v']??'')==='sum'?'/history_logsum.php':'/history_log.php'); exit;
