<?php require 'config.php'; require 'lib.php';
$t=(string)($_GET['t']??''); $ok=false; $msg=t('Länken är ogiltig eller har gått ut.','The link is invalid or has expired.');
if(preg_match('/^[a-f0-9]{64}$/',$t)){ $q=db()->prepare('SELECT * FROM users WHERE email_token=? AND email_exp>NOW() AND pending_email IS NOT NULL'); $q->execute([hash('sha256',$t)]); $u=$q->fetch();
 if($u){ $old=(string)$u['email']; db()->prepare('UPDATE users SET email=pending_email,pending_email=NULL,email_token=NULL,email_exp=NULL WHERE id=?')->execute([$u['id']]); $ok=true; $msg=t('Din nya e-postadress är bekräftad: ','Your new email address is confirmed: ').$u['pending_email'];
  applog('info','auth','Email address confirmed: '.$u['username']);
  if($old!==''&&filter_var($old,FILTER_VALIDATE_EMAIL)) send_mail($old,t('E-postadressen för ditt VM Monitor-konto ändrades','The email address of your VM Monitor account was changed'),t("Hej {$u['username']},\n\nE-postadressen för ditt konto har ändrats till {$u['pending_email']}. Var det inte du, kontakta en administratör.","Hi {$u['username']},\n\nThe email address of your account was changed to {$u['pending_email']}. If this was not you, contact an administrator.")); } }
top(t('Bekräfta e-post','Confirm email')); ?>
<div class="card login"><h2><?= t('Bekräfta e-postadress','Confirm email address') ?></h2><p class="<?= $ok?'okt':'err' ?>"><?= h($msg) ?></p><p class="mut"><a href="<?= !empty($_SESSION['u'])?'profile.php':'login.php' ?>"><?= !empty($_SESSION['u'])?t('Till din profil','To your profile'):t('Till inloggningen','To the login page') ?></a></p></div>
<?php bottom();
