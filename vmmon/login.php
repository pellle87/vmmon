<?php require 'config.php'; require 'lib.php';
$first=!db()->query('SELECT COUNT(*) FROM users')->fetchColumn(); $err='';
$p2=$_SESSION['p2']??null; if($p2&&time()-(int)$p2['t']>300){ unset($_SESSION['p2']); $p2=null; }
if($_SERVER['REQUEST_METHOD']==='POST'){ csrf_check();
 if($first){ if(strlen($_POST['p'])>=8&&filter_var($_POST['e'],FILTER_VALIDATE_EMAIL)){ db()->prepare("INSERT INTO users(username,email,password_hash,role,lang) VALUES(?,?,?,'admin',?)")->execute([$_POST['u'],$_POST['e'],password_hash($_POST['p'],PASSWORD_DEFAULT),'en']); header('Location: login.php');exit;}
  $err=t('Ange en giltig e-post och ett lösenord på minst 8 tecken.','Enter a valid email and a password of at least 8 characters.'); }
 elseif(isset($_POST['cancel'])){ unset($_SESSION['p2']); $p2=null; }
 elseif($p2&&isset($_POST['code'])){ $u=login_user_by_id($p2['id']);
  if(!$u||!empty($u['disabled'])){ unset($_SESSION['p2']); $p2=null; $err=t('Fel användarnamn eller lösenord.','Wrong username or password.'); }
  elseif(!empty($u['locked'])){ unset($_SESSION['p2']); $p2=null; $err=login_locked_msg((int)$u['lmin']); }
  else { $c=trim((string)$_POST['code']);
   if(totp_check($u,$c)){ $rd=(int)setting('totp_remember_days',30); if($rd>0&&!empty($_POST['remember'])) td_new($u['id'],$rd); login_done($u,' (2FA)'); }
   $left=recovery_use($u,$c); if($left!==false){ $_SESSION['flash']=t('Du loggade in med en återställningskod ('.$left.' kvar). Du kan inaktivera eller ställa in tvåstegsverifiering på nytt under din profil.','You signed in with a recovery code ('.$left.' left). You can disable or set up two-step verification again under your profile.'); login_done($u,' ('.t('återställningskod','recovery code').')'); }
   applog('warning','auth','Failed 2FA code for '.$u['username'].' ('.client_ip().')');
   $lkm=login_failed($u); if($lkm){ unset($_SESSION['p2']); $p2=null; $err=login_locked_msg($lkm); } else $err=t('Fel kod.','Wrong code.'); } }
 elseif(pw_login_off()){ $err=t('Lösenordsinloggning är avstängd – använd passkey eller SSO.','Password login is disabled – use a passkey or SSO.'); }
 else { $s=null; try{ $s=db()->prepare('SELECT *,IFNULL(locked_until>NOW(),0) AS locked,TIMESTAMPDIFF(MINUTE,NOW(),locked_until)+1 AS lmin FROM users WHERE username=?'); $s->execute([$_POST['u']]); }catch(Throwable $e){ $s=db()->prepare('SELECT * FROM users WHERE username=?'); $s->execute([$_POST['u']]); } $u=$s->fetch();
  if($u&&!empty($u['locked'])){ applog('warning','auth','Login blocked (account locked): '.$u['username'].' ('.client_ip().')'); $err=login_locked_msg((int)$u['lmin']); }
  else { if($u&&password_verify($_POST['p'],$u['password_hash'])&&empty($u['disabled'])){
    if(!empty($u['totp_enabled'])&&!empty($u['totp_secret'])&&!td_valid($u['id'])){ $p2=['id'=>(int)$u['id'],'t'=>time()]; $_SESSION['p2']=$p2; }
    else login_done($u); }
   if(!$p2){
    applog('warning','auth','Failed login for '.substr((string)($_POST['u']??''),0,50).' ('.client_ip().')');
    $lkm=$u?login_failed($u):0;
    $err=$lkm?login_locked_msg($lkm):(($u&&!empty($u['disabled'])&&password_verify($_POST['p'],$u['password_hash']))?t('Kontot är inaktiverat.','The account is disabled.'):t('Fel användarnamn eller lösenord.','Wrong username or password.')); } } } }
$pwoff=!$first&&pw_login_off(); $sso=!$first&&oidc_on();
top(t('Logga in','Log in')); ?>
<?php if($p2): ?>
<form method="post" class="card login"><h2><?= t('Tvåstegsverifiering','Two-step verification') ?></h2>
<?php if($err) echo '<p class="err">'.h($err).'</p>'; flash(); echo csrf_field(); ?>
<p class="mut"><?= t('Ange den 6-siffriga koden från din autentiseringsapp, eller en av dina återställningskoder.','Enter the 6-digit code from your authenticator app, or one of your recovery codes.') ?></p>
<input name="code" placeholder="<?= t('Kod eller återställningskod','Code or recovery code') ?>" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" required autofocus>
<?php $rd=(int)setting('totp_remember_days',30); if($rd>0): ?><label class="chk"><input type="checkbox" name="remember" value="1"> <?= t('Kom ihåg mig i '.$rd.' dagar','Remember me for '.$rd.' days') ?></label><?php endif; ?>
<button class="btn"><?= t('Verifiera','Verify') ?></button>
<p class="mut"><button class="btn sm" name="cancel" value="1" formnovalidate><?= t('Avbryt','Cancel') ?></button></p></form>
<?php else: ?>
<form method="post" class="card login"><h2><?= $first?t('Skapa första administratören','Create the first administrator'):t('Logga in','Log in') ?></h2>
<?php if($err) echo '<p class="err">'.h($err).'</p>'; flash(); echo csrf_field(); ?>
<?php if(!$pwoff): ?><input name="u" placeholder="<?= t('Användarnamn','Username') ?>" required autofocus><?php endif; ?>
<?php if($first) echo '<input name="e" type="email" required placeholder="'.t('E-post (för glömt lösenord)','Email (for password reset)').'">'; ?>
<?php if(!$pwoff): ?><input name="p" type="password" placeholder="<?= t('Lösenord','Password') ?>" required>
<button class="btn"><?= $first?t('Skapa konto','Create account'):t('Logga in','Log in') ?></button><?php else: ?><p class="mut"><?= t('Lösenordsinloggning är avstängd.','Password login is disabled.') ?></p><?php endif; ?>
<?php if($sso): ?><a class="btn<?= $pwoff?'':' sm' ?>" href="oauth.php?a=login"><?= h(setting('oidc_name','OAuth Login')) ?></a><?php endif; ?>
<?php if(!$first): ?><button type="button" class="btn sm" id="pkb" hidden><?= t('Logga in med passkey','Sign in with a passkey') ?></button><p class="err" id="pke" hidden></p><?php endif; ?>
<p class="mut"><?php if(!$first&&!$pwoff) echo '<a href="forgot.php">'.t('Glömt lösenord?','Forgot password?').'</a> · '; ?><a href="lang.php?l=sv">Svenska</a> · <a href="lang.php?l=en">English</a></p></form>
<?php endif; ?>
<?php if(!$first&&!$p2): ?><script src="assets/passkey.js"></script><script>(function(){var b=document.getElementById('pkb');if(!b||!pkSupported())return;b.hidden=false;b.onclick=function(){var e=document.getElementById('pke');e.hidden=true;b.disabled=true;pkLogin().then(function(r){location.href=r.url||'index.php'},function(x){b.disabled=false;e.textContent=(x&&x.name==='NotAllowedError')?'<?= h(t('Avbrutet eller ingen passkey hittades.','Cancelled or no passkey found.')) ?>':(x&&x.message)||'Error';e.hidden=false})}})()</script><?php endif; bottom();
