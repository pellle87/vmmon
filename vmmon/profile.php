<?php require 'config.php'; require 'lib.php'; need_login(); track_user(); csrf_check();
$uid=(int)$_SESSION['u']['id']; $q=db()->prepare('SELECT * FROM users WHERE id=?'); $q->execute([$uid]); $me=$q->fetch(); if(!$me){ header('Location: logout.php'); exit; }
$tot=!empty($me['totp_enabled'])&&!empty($me['totp_secret']);
function pwok($me){ return password_verify((string)($_POST['cur']??''),$me['password_hash']); }
if($_SERVER['REQUEST_METHOD']==='POST'){ $a=$_POST['a']??''; $f='';
 if($a==='pw'){ $n=(string)($_POST['n1']??'');
  if(!pwok($me)) $f=t('Nuvarande lösenord är fel.','The current password is wrong.');
  elseif(strlen($n)<8) $f=t('Det nya lösenordet måste vara minst 8 tecken.','The new password must be at least 8 characters.');
  elseif($n!==(string)($_POST['n2']??'')) $f=t('De nya lösenorden är inte lika.','The new passwords do not match.');
  else { db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($n,PASSWORD_DEFAULT),$uid]); td_clear($uid); applog('info','auth','Password changed by the user: '.$me['username']); $f=t('Lösenordet ändrades.','Password changed.'); } }
 elseif($a==='totp_start'&&!$tot){ $_SESSION['totp_pending']=b32enc(random_bytes(20)); }
 elseif($a==='totp_cancel'){ unset($_SESSION['totp_pending']); }
 elseif($a==='totp_confirm'&&!$tot&&!empty($_SESSION['totp_pending'])){ $sec=$_SESSION['totp_pending']; $st=totp_match($sec,$_POST['code']??'');
  if($st===false) $f=t('Fel kod – försök igen med den aktuella koden från appen.','Wrong code – try again with the current code from the app.');
  else { db()->prepare('UPDATE users SET totp_secret=?,totp_enabled=1,totp_last=? WHERE id=?')->execute([secret_enc($sec),$st,$uid]); $_SESSION['rc_show']=recovery_new($uid); unset($_SESSION['totp_pending']); applog('info','auth','Two-step verification enabled: '.$me['username']); $f=t('Tvåstegsverifiering är aktiverad. Spara dina återställningskoder nedan.','Two-step verification is enabled. Save your recovery codes below.'); } }
 elseif($a==='totp_regen'&&$tot){ if(!pwok($me)) $f=t('Nuvarande lösenord är fel.','The current password is wrong.'); else { $_SESSION['rc_show']=recovery_new($uid); applog('info','auth','New recovery codes created: '.$me['username']); $f=t('Nya återställningskoder skapades. De gamla gäller inte längre.','New recovery codes created. The old ones no longer work.'); } }
 elseif($a==='totp_disable'&&$tot){ if(!pwok($me)) $f=t('Nuvarande lösenord är fel.','The current password is wrong.'); else { db()->prepare('UPDATE users SET totp_secret=NULL,totp_enabled=0,totp_recovery=NULL,totp_last=NULL WHERE id=?')->execute([$uid]); td_clear($uid); applog('warning','auth','Two-step verification disabled: '.$me['username']); $f=t('Tvåstegsverifiering är avaktiverad.','Two-step verification is disabled.'); } }
 elseif($a==='theme'){ $id=(string)($_POST['theme']??''); $id=($id!==''&&isset(themes_list()[$id]))?$id:null; try{ db()->prepare('UPDATE users SET theme=? WHERE id=?')->execute([$id,$uid]); $_SESSION['u']['theme']=$id; }catch(Throwable $e){ $_SESSION['flash']=t('Kör sql/migrate_v40.sql för att kunna spara tema.','Run sql/migrate_v40.sql to be able to save the theme.'); } }
 elseif($a==='sound'){ $v=(string)($_POST['sound']??''); $v=($v==='off'||isset(snd_types()[$v]))?$v:null; try{ db()->prepare('UPDATE users SET sound=? WHERE id=?')->execute([$v,$uid]); $_SESSION['u']['sound']=$v; }catch(Throwable $e){ $_SESSION['flash']=t('Kör sql/migrate_v40.sql för att kunna spara ljud.','Run sql/migrate_v40.sql to be able to save the sound.'); } }
 elseif($a==='lang'){ $l=($_POST['lang']??'')==='sv'?'sv':'en'; db()->prepare('UPDATE users SET lang=? WHERE id=?')->execute([$l,$uid]); $_SESSION['lang']=$l; $_SESSION['u']['lang']=$l; setcookie('lang',$l,time()+31536000,'/'); }
 elseif($a==='td_clear'){ td_clear($uid); $f=t('Alla ihågkomna enheter glömdes.','All remembered devices were forgotten.'); }
 elseif($a==='email_cancel'){ db()->prepare('UPDATE users SET pending_email=NULL,email_token=NULL,email_exp=NULL WHERE id=?')->execute([$uid]); }
 elseif($a==='email_change'||$a==='email_resend'){ $ne=$a==='email_resend'?(string)$me['pending_email']:trim((string)($_POST['email']??''));
  if($a==='email_change'&&!pwok($me)) $f=t('Nuvarande lösenord är fel.','The current password is wrong.');
  elseif(!filter_var($ne,FILTER_VALIDATE_EMAIL)||strlen($ne)>120) $f=t('Ange en giltig e-postadress.','Enter a valid email address.');
  elseif(strcasecmp($ne,(string)$me['email'])===0) $f=t('Det är redan din e-postadress.','That is already your email address.');
  else { $tok=bin2hex(random_bytes(32)); db()->prepare('UPDATE users SET pending_email=?,email_token=?,email_exp=NOW()+INTERVAL 1 DAY WHERE id=?')->execute([$ne,hash('sha256',$tok),$uid]);
   $link=base_url().'confirm_email.php?t='.$tok;
   $err=send_mail($ne,t('Bekräfta din nya e-postadress för VM Monitor','Confirm your new email address for VM Monitor'),t("Hej {$me['username']},\n\nDu har bett om att byta e-postadress för ditt konto i VM Monitor. Bekräfta den nya adressen genom att öppna länken nedan (gäller i 24 timmar):\n\n$link\n\nOm det inte var du kan du ignorera det här mejlet.","Hi {$me['username']},\n\nYou asked to change the email address of your VM Monitor account. Confirm the new address by opening the link below (valid for 24 hours):\n\n$link\n\nIf this was not you, you can ignore this email."));
   if($err===null){ applog('info','auth','Confirmation mail sent for email change: '.$me['username']); $f=t('Ett bekräftelsemejl har skickats till ','A confirmation email has been sent to ').$ne.t('. Klicka på länken i mejlet för att slutföra bytet.','. Click the link in the email to complete the change.'); }
   else { db()->prepare('UPDATE users SET pending_email=NULL,email_token=NULL,email_exp=NULL WHERE id=?')->execute([$uid]); $f=t('Mejlet kunde inte skickas: ','The email could not be sent: ').$err; } } }
 elseif($a==='lay_copy'){ require_once __DIR__.'/layout_lib.php'; $su=(int)($_POST['src_user']??0); $vw=($_POST['view']??'')==='compact'?'compact':'normal'; $sl=lay_n($_POST['src_lay']??1); $dl=lay_n($_POST['dst_lay']??1);
  $q=db()->prepare('SELECT username FROM users WHERE id=?'); $q->execute([$su]); $sn=$q->fetchColumn();
  if($sn===false) $f=t('Användaren finns inte.','The user does not exist.');
  elseif($su===$uid&&$sl===$dl) $f=t('Källan och målet är samma layout.','The source and the target are the same layout.');
  else{ try{ $ex=lay_export($su,$sl,$vw); if(!lay_has_content($ex)) $f=sprintf(t('%s har ingen sparad %s-layout %d (den använder standardordningen) – inget kopierades.','%s has no saved %s layout %d (it uses the default order) – nothing was copied.'),$sn,$vw,$sl);
    else{ lay_import($uid,$dl,$vw,$ex); applog('info','config',"Dashboard layout copied: $sn $vw $sl -> {$me['username']} $dl"); $f=sprintf(t('Kopierade %s-layout %d från %s till din layout %d. Ladda om dashboarden.','Copied %s layout %d from %s to your layout %d. Reload the dashboard.'),$vw,$sl,$sn,$dl); } }
   catch(Throwable $e){ $f=t('Kopieringen misslyckades: ','The copy failed: ').$e->getMessage(); } } }
 elseif($a==='pk_del'){ db()->prepare('DELETE FROM user_passkeys WHERE id=? AND user_id=?')->execute([(int)($_POST['id']??0),$uid]); applog('info','auth','Passkey removed: '.$me['username']); $f=t('Passkey togs bort.','Passkey removed.'); }
 unset($_SESSION['mfa_t']);
 if($f!=='') $_SESSION['flash']=$f; header('Location: profile.php'); exit; }
$pend=$_SESSION['totp_pending']??''; $codes=$_SESSION['rc_show']??null; unset($_SESSION['rc_show']);
try{ $pq=db()->prepare('SELECT * FROM user_passkeys WHERE user_id=? ORDER BY id'); $pq->execute([$uid]); $pks=$pq->fetchAll(); }catch(Throwable $e){ $pks=[]; }
$enf=setting('enforce_2fa','0')==='1'&&!$tot&&!$pks;
$left=$tot?count(json_decode((string)$me['totp_recovery'],true)?:[]):0;
top(t('Min profil','My profile')); flash(); ?>
<h2><?= t('Min profil','My profile') ?> – <?= h($me['username']) ?></h2>
<?php if($enf): ?><p class="card badt"><?= t('Administratören kräver tvåstegsverifiering. Ställ in TOTP eller lägg till en passkey nedan för att fortsätta använda systemet.','The administrator requires two-step verification. Set up TOTP or add a passkey below to keep using the system.') ?></p><?php endif; ?>
<h3><?= t('Webbplatsinställningar','Site settings') ?></h3>
<div class="card pg" id="pg-site">
<section class="ps"><h4 class="sh"><?= t('Språk','Language') ?></h4>
<form method="post" class="pf"><?= csrf_field() ?><input type="hidden" name="a" value="lang">
<label class="f"><?= t('Språk i gränssnittet','Interface language') ?><select name="lang" onchange="this.form.submit()"><option value="en"<?= ($me['lang']??'en')==='en'?' selected':'' ?>>English</option><option value="sv"<?= ($me['lang']??'en')==='sv'?' selected':'' ?>>Svenska</option></select></label></form>
</section>
<section class="ps"><h4 class="sh"><?= t('Tema','Theme') ?></h4>
<form method="post" class="pf"><?= csrf_field() ?><input type="hidden" name="a" value="theme">
<label class="f"><?= t('Färgtema (sparas för ditt konto)','Colour theme (saved for your account)') ?><select name="theme" onchange="this.form.submit()"><option value=""<?= empty($_SESSION['u']['theme'])?' selected':'' ?>><?= t('Standard','Default') ?></option><?php foreach(themes_list() as $tid=>$tm) echo '<option value="'.h($tid).'"'.(($_SESSION['u']['theme']??'')===$tid?' selected':'').'>'.h($tm['name']).($tm['builtin']?'':' ★').'</option>'; ?></select></label></form>
</section>
<section class="ps"><h4 class="sh"><?= t('Larmljud','Alert sound') ?></h4>
<form method="post" class="pf"><?= csrf_field() ?><input type="hidden" name="a" value="sound"><?php $gs=snd_types(); $gcur=setting('sound_type','classic'); $us=(string)($_SESSION['u']['sound']??''); ?>
<label class="f"><?= t('Ljud när något blir kritisk på dashboarden','Sound when something becomes critical on the dashboard') ?><select name="sound" id="pfsnd"><option value=""<?= $us===''?' selected':'' ?>><?= t('Följ systeminställningen','Follow system setting') ?> (<?= h(setting('sound_alert','1')==='0'?t('av','off'):($gs[$gcur]??$gcur)) ?>)</option><option value="off"<?= $us==='off'?' selected':'' ?>><?= t('Av','Off') ?></option><?php foreach($gs as $k=>$n) echo '<option value="'.h($k).'"'.($us===$k?' selected':'').'>'.h($n).'</option>'; ?></select></label>
<div class="inline"><button class="btn">💾 <?= t('Spara','Save') ?></button><button type="button" class="btn" id="pfplay">🔔 <?= t('Spela upp','Preview') ?></button></div></form>
<script src="assets/sounds.js"></script><script>(function(){var s=document.getElementById('pfsnd'),b=document.getElementById('pfplay'),g=<?= json_encode($gcur) ?>;b.onclick=function(){var v=s.value;if(v==='off')return;VMSND.preview(v===''?g:v)}})();</script>
</section>
<section class="ps ps-wide"><h4 class="sh"><?= t('Kopiera dashboard-layout','Copy dashboard layout') ?></h4>
<form method="post" class="pf" onsubmit="return confirm(this.dataset.c)" data-c="<?= h(t('Din valda layout i den vyn ersätts helt. Fortsätta?','Your chosen layout in that view is replaced completely. Continue?')) ?>"><?= csrf_field() ?><input type="hidden" name="a" value="lay_copy">
<?php $lus=db()->query('SELECT id,username FROM users ORDER BY username')->fetchAll(); $lo=function($n){ $o=''; for($i=1;$i<=3;$i++) $o.='<option value="'.$i.'">'.h(t('Layout','Layout')).' '.$i.'</option>'; return $o; }; ?>
<label class="f"><?= t('Från användare','From user') ?><select name="src_user"><?php foreach($lus as $u): ?><option value="<?= (int)$u['id'] ?>"<?= (int)$u['id']===$uid?' selected':'' ?>><?= h($u['username']) ?><?= (int)$u['id']===$uid?' ('.h(t('jag','me')).')':'' ?></option><?php endforeach; ?></select></label>
<label class="f"><?= t('Vy','View') ?><select name="view"><option value="compact">Compact</option><option value="normal">Normal</option></select></label>
<label class="f"><?= t('Deras layout','Their layout') ?><select name="src_lay"><?= $lo(0) ?></select></label>
<label class="f"><?= t('Till min layout','To my layout') ?><select name="dst_lay"><?= $lo(0) ?></select></label>
<button class="btn"><?= t('Kopiera','Copy') ?></button></form></section></div>
<h3><?= t('Konto och säkerhet','Account & security') ?></h3>
<div class="card pg" id="pg-acc">
<section class="ps"><h4 class="sh"><?= t('E-postadress','Email address') ?></h4>
<form method="post" class="pf"><?= csrf_field() ?><input type="hidden" name="a" value="email_change">
<p class="mut"><?= t('Nuvarande adress: ','Current address: ') ?><b><?= h($me['email']?:'–') ?></b></p>
<?php if(!empty($me['pending_email'])&&strtotime((string)$me['email_exp'])>time()): ?><p class="warnt"><?= t('Väntar på bekräftelse: ','Waiting for confirmation: ') ?><b><?= h($me['pending_email']) ?></b> <?= t('– klicka på länken i mejlet.','– click the link in the email.') ?></p>
<div class="inline"><button class="btn sm" name="a" value="email_resend" formnovalidate><?= t('Skicka igen','Resend') ?></button><button class="btn sm danger" name="a" value="email_cancel" formnovalidate><?= t('Avbryt byte','Cancel change') ?></button></div><?php endif; ?>
<label class="f"><?= t('Ny e-postadress','New email address') ?><input type="email" name="email" required maxlength="120" autocomplete="email"></label>
<label class="f"><?= t('Nuvarande lösenord','Current password') ?><input type="password" name="cur" required autocomplete="current-password"></label>
<div><button class="btn"><?= t('Skicka bekräftelsemejl','Send confirmation email') ?></button></div></form>
</section>
<section class="ps"><h4 class="sh"><?= t('Byt lösenord','Change password') ?></h4>
<form method="post" class="pf"><?= csrf_field() ?><input type="hidden" name="a" value="pw">
<label class="f"><?= t('Nuvarande lösenord','Current password') ?><input type="password" name="cur" required autocomplete="current-password"></label>
<label class="f"><?= t('Nytt lösenord (minst 8 tecken)','New password (at least 8 characters)') ?><input type="password" name="n1" required minlength="8" autocomplete="new-password"></label>
<label class="f"><?= t('Upprepa nytt lösenord','Repeat new password') ?><input type="password" name="n2" required minlength="8" autocomplete="new-password"></label>
<div><button class="btn"><?= t('Byt lösenord','Change password') ?></button></div></form>
</section>
<section class="ps"><h4 class="sh"><?= t('Tvåstegsverifiering (TOTP)','Two-step verification (TOTP)') ?></h4>
<?php if($codes): ?>
<div class="pf"><b><?= t('Dina återställningskoder','Your recovery codes') ?></b>
<p class="mut"><?= t('Spara dem på ett säkert ställe – de visas bara en gång. Varje kod fungerar en gång och kan användas för att logga in om du tappar bort din enhet. Därefter kan du avaktivera tvåstegsverifiering här.','Store them somewhere safe – they are shown only once. Each code works once and can be used to sign in if you lose your device. You can then disable two-step verification here.') ?></p>
<pre id="rc" class="rc"><?= h(implode("\n",$codes)) ?></pre>
<div><button type="button" class="btn sm" onclick="var t=document.getElementById('rc').textContent,b=this;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent='✔'},function(){var r=document.createRange();r.selectNodeContents(document.getElementById('rc'));var s=getSelection();s.removeAllRanges();s.addRange(r)})"><?= t('Kopiera','Copy') ?></button></div></div>
<?php endif; ?>
<?php if($tot): ?>
<div class="pf"><p class="okt">● <?= t('Aktiverad','Enabled') ?> · <?= t('Återställningskoder kvar: ','Recovery codes left: ') ?><b><?= $left ?></b></p>
<form method="post" class="pf"><?= csrf_field() ?><label class="f"><?= t('Nuvarande lösenord','Current password') ?><input type="password" name="cur" required autocomplete="current-password"></label>
<div class="inline"><button class="btn" name="a" value="totp_regen"><?= t('Skapa nya återställningskoder','Create new recovery codes') ?></button><button class="btn" name="a" value="td_clear" formnovalidate title="<?= h(t('Kräv TOTP-kod igen på alla enheter','Require the TOTP code again on all devices')) ?>"><?= t('Glöm ihågkomna enheter','Forget remembered devices') ?></button><button class="btn danger" name="a" value="totp_disable" onclick="return confirm('<?= h(t('Avaktivera tvåstegsverifiering?','Disable two-step verification?')) ?>')"><?= t('Avaktivera','Disable') ?></button></div></form></div>
<?php elseif($pend): $uri='otpauth://totp/'.rawurlencode('VM Monitor').':'.rawurlencode($me['username']).'?secret='.$pend.'&issuer='.rawurlencode('VM Monitor').'&algorithm=SHA1&digits=6&period=30'; ?>
<div class="pf"><p><?= t('1. Skanna QR-koden med din autentiseringsapp (t.ex. Google Authenticator, Microsoft Authenticator eller Aegis).','1. Scan the QR code with your authenticator app (e.g. Google Authenticator, Microsoft Authenticator or Aegis).') ?></p>
<div id="qr" class="qr"></div><p class="mut"><?= t('Går det inte att skanna? Ange nyckeln manuellt:','Cannot scan? Enter the key manually:') ?> <code><?= h(trim(chunk_split($pend,4,' '))) ?></code></p>
<form method="post" class="pf"><?= csrf_field() ?><input type="hidden" name="a" value="totp_confirm"><label class="f"><?= t('2. Ange den 6-siffriga koden från appen','2. Enter the 6-digit code from the app') ?><input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required></label>
<div class="inline"><button class="btn"><?= t('Aktivera','Enable') ?></button><button class="btn sm" name="a" value="totp_cancel" formnovalidate><?= t('Avbryt','Cancel') ?></button></div></form></div>
<script src="assets/qr.js"></script><script>document.getElementById('qr').innerHTML=qrSvg(<?= json_encode($uri) ?>,220)</script>
<?php else: ?>
<div class="pf"><p class="mut"><?= t('Skydda ditt konto med en engångskod från en autentiseringsapp vid inloggning. Du får återställningskoder som fungerar om du tappar bort din enhet.','Protect your account with a one-time code from an authenticator app when signing in. You get recovery codes that work if you lose your device.') ?></p>
<form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="totp_start"><button class="btn"><?= t('Ställ in tvåstegsverifiering','Set up two-step verification') ?></button></form></div>
<?php endif; ?>
</section>
<section class="ps"><h4 class="sh">Passkeys</h4>
<div class="pf"><p class="mut"><?= t('Logga in utan lösenord med fingeravtryck, ansiktsigenkänning, enhetens PIN eller en säkerhetsnyckel. En passkey räknas som tvåstegsverifiering och kräver HTTPS och ett domännamn.','Sign in without a password using a fingerprint, face recognition, the device PIN or a security key. A passkey counts as two-step verification and requires HTTPS and a domain name.') ?></p>
<?php if($pks): ?><table style="width:100%"><tr><th><?= t('Namn','Name') ?></th><th><?= t('Tillagd','Added') ?></th><th><?= t('Senast använd','Last used') ?></th><th></th></tr>
<?php foreach($pks as $k): ?><tr><td><?= h($k['name']) ?></td><td><?= h($k['created']) ?></td><td><?= $k['last_used']?h($k['last_used']):t('aldrig','never') ?></td><td><form method="post" onsubmit="return confirm('<?= h(t('Ta bort passkey?','Remove passkey?')) ?>')"><?= csrf_field() ?><input type="hidden" name="a" value="pk_del"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn danger sm"><?= t('Ta bort','Remove') ?></button></form></td></tr><?php endforeach; ?></table><?php endif; ?>
<div id="pkadd" hidden><label class="f"><?= t('Namn på passkey (t.ex. din telefon)','Passkey name (e.g. your phone)') ?><input id="pkn" maxlength="60" placeholder="Passkey"></label>
<div class="inline"><button type="button" class="btn" id="pkgo"><?= t('Lägg till passkey','Add a passkey') ?></button></div><p class="err" id="pke" hidden></p></div>
<p class="mut" id="pkno" hidden><?= t('Din webbläsare eller anslutning stöder inte passkeys (kräver HTTPS).','Your browser or connection does not support passkeys (HTTPS required).') ?></p></div>
</section></div>
<script src="assets/passkey.js"></script><script>(function(){if(!pkSupported()){document.getElementById('pkno').hidden=false;return}document.getElementById('pkadd').hidden=false;var b=document.getElementById('pkgo'),e=document.getElementById('pke');b.onclick=function(){e.hidden=true;b.disabled=true;pkRegister(document.getElementById('pkn').value).then(function(){location.reload()},function(x){b.disabled=false;e.textContent=(x&&x.name==='NotAllowedError')?'<?= h(t('Avbrutet.','Cancelled.')) ?>':(x&&x.message)||'Error';e.hidden=false})}})()</script>
<?php bottom();
