<?php require 'config.php'; $l=($_GET['l']??'')==='en'?'en':'sv'; $_SESSION['lang']=$l; setcookie('lang',$l,time()+31536000,'/');
if(!empty($_SESSION['u'])) db()->prepare('UPDATE users SET lang=? WHERE id=?')->execute([$l,$_SESSION['u']['id']]);
$r=$_SERVER['HTTP_REFERER']??''; header('Location: '.((parse_url($r,PHP_URL_HOST)===($_SERVER['HTTP_HOST']??'')&&$r)?$r:'index.php'));
