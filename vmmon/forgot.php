<?php require 'config.php'; require 'lib.php'; $done=false;
if(pw_login_off()){ $_SESSION['flash']=t('Lösenordsinloggning är avstängd – använd passkey eller SSO.','Password login is disabled – use a passkey or SSO.'); header('Location: login.php'); exit; }
if($_SERVER['REQUEST_METHOD']==='POST'){ csrf_check(); $q=trim($_POST['q']??'');
 $s=db()->prepare("SELECT * FROM users WHERE (username=? OR email=?) AND email<>'' AND disabled=0 LIMIT 1"); $s->execute([$q,$q]); $u=$s->fetch();
 if($u&&(empty($u['pw_exp'])||strtotime($u['pw_exp'])<time()+3300)){ $tok=bin2hex(random_bytes(32)); $lnk=base_url().'reset.php?t='.$tok;
  $err=send_mail($u['email'],t('Återställ lösenord för VM Monitor','Reset your VM Monitor password'),t("Hej {$u['username']},\n\nKlicka på länken för att välja ett nytt lösenord (giltig i 1 timme):\n$lnk\n\nOm du inte har begärt detta kan du ignorera mejlet – ditt lösenord ändras inte.","Hi {$u['username']},\n\nClick the link to choose a new password (valid for 1 hour):\n$lnk\n\nIf you did not request this you can ignore the email – your password is not changed."));
  if($err===null) db()->prepare('UPDATE users SET pw_token=?,pw_exp=NOW()+INTERVAL 1 HOUR WHERE id=?')->execute([hash('sha256',$tok),$u['id']]); }
 $done=true; }
top(t('Glömt lösenord','Forgot password')); ?>
<form method="post" class="card login"><h2><?= t('Glömt lösenord','Forgot password') ?></h2>
<?php if($done) echo '<p class="okt">'.t('Om kontot finns och har en e-postadress har en återställningslänk skickats.','If the account exists and has an email address, a reset link has been sent.').'</p>'; echo csrf_field(); ?>
<input name="q" placeholder="<?= t('Användarnamn eller e-post','Username or email') ?>" required autofocus><button class="btn"><?= t('Skicka återställningslänk','Send reset link') ?></button>
<p class="mut"><a href="login.php"><?= t('Tillbaka till inloggning','Back to login') ?></a></p></form>
<?php bottom();
