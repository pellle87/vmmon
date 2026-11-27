<?php require 'config.php'; require 'lib.php';
if(pw_login_off()){ header('Location: login.php'); exit; }
$tk=(string)($_GET['t']??$_POST['t2']??''); $u=null; $msg=''; $ok=false;
if(preg_match('/^[a-f0-9]{64}$/',$tk)){ try{ $q=db()->prepare('SELECT * FROM users WHERE pw_token=? AND pw_exp>NOW() AND disabled=0'); $q->execute([hash('sha256',$tk)]); $u=$q->fetch(); }catch(Throwable $e){ $u=null; } }
if($u&&$_SERVER['REQUEST_METHOD']==='POST'){ csrf_check(); $p1=(string)($_POST['p1']??''); $p2=(string)($_POST['p2']??'');
 if(strlen($p1)<8) $msg=t('Lösenordet måste vara minst 8 tecken.','The password must be at least 8 characters.'); elseif($p1!==$p2) $msg=t('Lösenorden är olika.','The passwords do not match.');
 else{ db()->prepare('UPDATE users SET password_hash=?,pw_token=NULL,pw_exp=NULL WHERE id=?')->execute([password_hash($p1,PASSWORD_DEFAULT),$u['id']]); try{ db()->prepare('UPDATE users SET locked_until=NULL WHERE id=?')->execute([$u['id']]); db()->prepare('DELETE FROM login_fail WHERE user_id=?')->execute([$u['id']]); }catch(Throwable $e){} applog('info','auth','Password reset via email link: '.$u['username'].' ('.client_ip().')'); $_SESSION['flash']=t('Lösenordet är ändrat – logga in.','Password changed – please sign in.'); header('Location: login.php'); exit; } }
top(t('Nytt lösenord','New password')); ?>
<form method="post" class="card login"><h2><?= t('Nytt lösenord','New password') ?></h2>
<?php if(!$u): ?><p class="badt"><?= t('Länken är ogiltig eller har gått ut.','The link is invalid or has expired.') ?></p><p><a href="forgot.php"><?= t('Begär en ny länk','Request a new link') ?></a></p>
<?php else: if($msg) echo '<p class="badt">'.h($msg).'</p>'; echo csrf_field(); ?><input type="hidden" name="t2" value="<?= h($tk) ?>">
<input type="password" name="p1" placeholder="<?= t('Nytt lösenord (minst 8 tecken)','New password (min. 8 characters)') ?>" autocomplete="new-password" required autofocus><input type="password" name="p2" placeholder="<?= t('Upprepa lösenordet','Repeat password') ?>" autocomplete="new-password" required><button class="btn"><?= t('Spara lösenord','Save password') ?></button><?php endif; ?>
</form>
<?php bottom(); ?>
