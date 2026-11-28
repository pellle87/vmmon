<?php require 'config.php'; require 'lib.php'; need_login(); track_user(); csrf_check(); if($_SERVER['REQUEST_METHOD']==='POST'&&!is_admin()){ http_response_code(403); exit(t('Du har bara läsbehörighet.','You have read-only access.')); }
$adm=is_admin(); $ok=true; $TL=[]; try{ db()->query('SELECT 1 FROM alert_templates LIMIT 1'); tpl_seed(); }catch(Throwable $e){ $ok=false; }
$cut=function($k,$n){ return mb_substr(trim(str_replace("\r",'',(string)($_POST[$k]??''))),0,$n); };
if($ok&&$_SERVER['REQUEST_METHOD']==='POST'){ $a=$_POST['a']??''; $id=(int)($_POST['id']??0); $pdo=db();
 if($a==='save'){ $nm=$cut('name',100); if($nm===''){ $_SESSION['flash']=t('Ange ett namn på mallen.','Enter a name for the template.'); header('Location: alert_templates.php'.($id?'?edit='.$id:'?new=1')); exit; }
  $v=[$nm,$cut('subject',255),$cut('body',8000),$cut('sms',500)?:null,$cut('ok_subject',255)?:null,$cut('ok_body',8000)?:null];
  if($id){ $pdo->prepare('UPDATE alert_templates SET name=?,subject=?,body=?,sms=?,ok_subject=?,ok_body=? WHERE id=?')->execute(array_merge($v,[$id])); }
  else { $pdo->prepare('INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) VALUES(?,?,?,?,?,?)')->execute($v); }
  $_SESSION['flash']=t('Mallen sparades.','Template saved.'); header('Location: alert_templates.php'); exit; }
 if($a==='copy'&&$id){ $pdo->prepare("INSERT INTO alert_templates(name,subject,body,sms,ok_subject,ok_body) SELECT CONCAT(LEFT(name,90),' (copy)'),subject,body,sms,ok_subject,ok_body FROM alert_templates WHERE id=?")->execute([$id]); header('Location: alert_templates.php'); exit; }
 if($a==='del'&&$id){ $pdo->prepare('UPDATE alert_rules SET template_id=NULL WHERE template_id=?')->execute([$id]); $pdo->prepare('DELETE FROM alert_templates WHERE id=?')->execute([$id]); $_SESSION['flash']=t('Mallen togs bort. Regler som använde den använder nu standardtexten.','Template deleted. Rules that used it now use the default text.'); header('Location: alert_templates.php'); exit; }
 if($a==='pubu'){ $u=trim((string)($_POST['public_url']??'')); if($u===''||preg_match('~^https?://[^\s]+$~i',$u)) set_setting('public_url',rtrim($u,'/')); header('Location: alert_templates.php'); exit; }
 if($a==='test'){ $vv=tpl_sample((string)($_POST['name']??''),(string)($_POST['spk']??'')); $sub=trim(tpl_render($cut('subject',255),$vv)); $bd=tpl_render($cut('body',8000),$vv); $sm=trim($cut('sms',500)); $smt=$sm!==''?trim(tpl_render($sm,$vv)):mb_substr($sub.($bd!==''?' – '.str_replace("\n",' ',$bd):''),0,300); $sub='[TEST] '.$sub; $res=[];
  if(!empty($_POST['n_email'])){ $e=send_mail(setting('alarm_contacts',''),$sub,$bd); $res[]='Email: '.($e?:'OK'); }
  if(!empty($_POST['n_tg'])){ $e=tg_send('🧪 '.$sub."\n".$bd); $res[]='Telegram: '.($e?:'OK'); }
  if(!empty($_POST['n_wh'])){ $e=wh_send(['event'=>'test','subject'=>$sub,'message'=>$bd,'time'=>date('c')],$sub."\n".$bd); $res[]='Webhook: '.($e?:'OK'); }
  if(!empty($_POST['n_signal'])){ $e=signal_send($sub."\n".$bd); $res[]='Signal: '.($e?:'OK'); }
  if(!empty($_POST['n_sms'])){ $e=sms_send('[TEST] '.$smt); $res[]='SMS: '.($e?:'OK'); }
  $_SESSION['flash']=$res?t('Testmeddelande (med exempeldata): ','Test message (with sample data): ').implode(', ',$res):t('Välj minst en kanal för testet.','Pick at least one channel for the test.');
  $_SESSION['tpl_draft']=['id'=>$id,'name'=>$cut('name',100),'subject'=>$cut('subject',255),'body'=>$cut('body',8000),'sms'=>$cut('sms',500),'ok_subject'=>$cut('ok_subject',255),'ok_body'=>$cut('ok_body',8000)]; header('Location: alert_templates.php?'.($id?'edit='.$id:'new=1')); exit; }
}
$ed=null; $isNew=!empty($_GET['new']); $eid=(int)($_GET['edit']??0);
if($ok&&$eid){ $q=db()->prepare('SELECT * FROM alert_templates WHERE id=?'); $q->execute([$eid]); $ed=$q->fetch()?:null; }
$dr=$_SESSION['tpl_draft']??null; unset($_SESSION['tpl_draft']); if($dr&&(int)$dr['id']===($ed?(int)$ed['id']:0)&&($ed||$isNew)) $ed=array_merge($ed?:[],$dr);
$form=$ok&&$adm&&($ed||$isNew);
top(t('Larmmallar','Alert templates')); flash(); ?>
<h2><?= t('Larm','Alert') ?></h2>
<?php alert_tabs('templates'); ?>
<?php if(!$ok): ?><p class="card badt"><?= t('Tabellen alert_templates saknas – kör den senaste SQL-filen (Uppdatera-fliken) först.','The alert_templates table is missing – run the latest SQL file (Update tab) first.') ?></p><?php bottom(); exit; endif; ?>
<?php if($form): $E=$ed?:['id'=>0,'name'=>'','subject'=>'[%status] %hostname: %rule','body'=>"Server: %hostname (%ip)\nRule #%ruleid: %rule\nProblem started: %started\nOngoing for: %duration\n%info",'sms'=>'','ok_subject'=>'','ok_body'=>'']; ?>
<form method="post" class="card" id="tf"><?= csrf_field() ?><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= (int)$E['id'] ?>">
<h3 style="margin-top:0"><?= $E['id']?t('Ändra larmmall','Edit alert template'):t('Ny larmmall','New alert template') ?></h3>
<p class="mut" style="margin:0 0 10px"><?= t('Skriv texten som notisen ska ha. Klicka på en variabel för att sätta in den där markören står. Variabler som är tomma för just det larmet försvinner, och en rad där alla variabler är tomma tas bort.','Write the text of the notification. Click a variable to insert it where the cursor is. Variables that are empty for a given alert disappear, and a line where every variable is empty is removed.') ?></p>
<label class="f" style="max-width:360px"><?= t('Namn på mallen','Template name') ?><input name="name" value="<?= h($E['name']) ?>" maxlength="100" required style="width:100%"></label>
<div class="tplgrid">
<div>
<label class="f"><?= t('Rubrik (e-postämne, första raden i Telegram)','Subject (e-mail subject, first line in Telegram)') ?><input name="subject" class="tf" value="<?= h($E['subject']) ?>" maxlength="255" style="width:100%"></label>
<label class="f"><?= t('Text','Text') ?><textarea name="body" class="tf" rows="10" maxlength="8000" style="width:100%;font-family:monospace"><?= h((string)$E['body']) ?></textarea></label>
<label class="f"><?= t('Kort text för SMS (valfri, tom = rubrik + text på en rad)','Short text for SMS (optional, empty = subject + text on one line)') ?><input name="sms" class="tf" value="<?= h((string)$E['sms']) ?>" maxlength="500" style="width:100%"></label>
<details<?= trim((string)$E['ok_subject'].(string)$E['ok_body'])!==''?' open':'' ?> style="margin-top:8px"><summary><?= t('Egen text när larmet är återställt (valfritt)','Own text when the alert is cleared (optional)') ?></summary>
<p class="mut" style="margin:6px 0"><?= t('Tomt = den vanliga automatiska OK-texten skickas.','Empty = the normal automatic OK text is sent.') ?></p>
<label class="f"><?= t('Rubrik','Subject') ?><input name="ok_subject" class="tf" value="<?= h((string)$E['ok_subject']) ?>" maxlength="255" style="width:100%"></label>
<label class="f"><?= t('Text','Text') ?><textarea name="ok_body" class="tf" rows="6" maxlength="8000" style="width:100%;font-family:monospace"><?= h((string)$E['ok_body']) ?></textarea></label></details>
</div>
<div>
<div class="mut" style="margin-bottom:6px"><b><?= t('Emojis','Emojis') ?></b> <small><?= t('(klicka för att sätta in – du kan också klistra in egna)','(click to insert – you can also paste your own)') ?></small></div><div class="tplvars"><?php foreach(['🔴','🟠','🟡','🟢','✅','⚠️','❗','🚨','🔥','🔔','📈','📉','🌡️','💾','🖥️','🌐','🔌','📡','🛡️','🔒','📨','⏱️','🧩','🏠','⚙️','🔧','👤'] as $em) echo '<button type="button" class="btn sm tv" data-e="'.h($em).'">'.$em.'</button> '; ?></div>
<div class="mut" style="margin-bottom:6px"><b><?= t('Variabler','Variables') ?></b></div>
<?php foreach(tpl_catalog() as [$gn,$vs]): ?><div class="tplvars"><span class="mut"><?= h($gn) ?></span> <?php foreach($vs as $k=>$d) echo '<button type="button" class="btn sm tv" data-v="'.h($k).'" title="'.h($d).'">%'.h($k).'</button> '; ?></div><?php endforeach; ?>
<div class="mut" style="margin:12px 0 4px"><b><?= t('Förhandsvisning (exempeldata)','Preview (sample data)') ?></b> <select name="spk" id="spk" style="margin-left:8px"><option value=""><?= t('Automatiskt efter mallens namn','Automatic from the template name') ?></option><?php foreach(tpl_profiles() as $pk=>$pv): ?><option value="<?= h($pk) ?>"<?= (($_POST['spk']??'')===$pk)?' selected':'' ?>><?= h(t($pv[0],$pv[1])) ?></option><?php endforeach; ?></select></div>
<div class="card" style="margin:0;background:var(--bg,transparent)"><div id="pvs" style="font-weight:600"></div><pre id="pvb" style="white-space:pre-wrap;margin:6px 0 0;font-family:inherit"></pre></div>
<div class="mut" style="margin:8px 0 2px"><?= t('SMS:','SMS:') ?></div><div class="card" style="margin:0"><pre id="pvm" style="white-space:pre-wrap;margin:0;font-family:inherit"></pre></div>
</div></div>
<div class="inline" style="margin-top:12px"><button class="btn go"><?= t('Spara','Save') ?></button><a class="btn" href="alert_templates.php"><?= t('Avbryt','Cancel') ?></a>
<span class="mut" style="margin-left:14px"><?= t('Skicka test till:','Send a test to:') ?></span><?php $NC=notify_cfg(); foreach(['email'=>'E-mail','tg'=>'Telegram','wh'=>'Webhook','signal'=>'Signal','sms'=>'SMS'] as $k=>$lb): $on=!empty($NC[$k]); ?><label class="chk"><input type="checkbox" name="n_<?= $k ?>" value="1"<?= $on?'':' disabled' ?>> <?= h($lb) ?></label><?php endforeach; ?><button class="btn" type="submit" name="a" value="test" formnovalidate><?= t('Skicka test','Send test') ?></button></div>
</form>
<script>(function(){<?php $SA=[]; foreach(array_keys(tpl_profiles()) as $pk) $SA[$pk]=tpl_sample('',$pk); ?>var SA=<?= json_encode($SA,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG) ?>,PM=<?= json_encode(tpl_pick_map()) ?>,S=SA.resource,f=document.getElementById('tf'),last=f.elements.body;
function pick(){var v=f.elements.spk.value;if(!v){var n=String(f.elements.name.value).toLowerCase();v='resource';for(var i=0;i<PM.length;i++)if(n.indexOf(PM[i][0])>=0){v=PM[i][1];break}}S=SA[v]||SA.resource}
f.elements.spk.addEventListener('change',function(){up()});f.elements.name.addEventListener('input',function(){up()});
function R(tx){return String(tx).split(/\r?\n/).map(function(ln){var had=false,all=true;var r=ln.replace(/%(?:\{([a-z]+)\}|([a-z]+))/gi,function(m,b,p){var n=(b||p).toLowerCase(),rest='';if(!(n in S)&&!b){for(var i=n.length-1;i>=2;i--){var q=n.slice(0,i);if(q in S){rest=p.slice(i);n=q;break}}}if(!(n in S))return m;had=true;var v=String(S[n]);if(v!=='')all=false;return v+rest});return had&&all?null:r}).filter(function(x){return x!==null}).join('\n').replace(/\s+$/,'')}
function up(){pick();var s=R(f.elements.subject.value).trim(),b=R(f.elements.body.value),m=f.elements.sms.value.trim();document.getElementById('pvs').textContent=s;document.getElementById('pvb').textContent=b;document.getElementById('pvm').textContent=m?R(m).trim():(s+(b?' – '+b.replace(/\n/g,' '):'')).slice(0,300)}
f.querySelectorAll('.tf').forEach(function(e){e.addEventListener('focus',function(){last=e});e.addEventListener('input',up)});
f.querySelectorAll('.tv').forEach(function(b){b.addEventListener('mousedown',function(e){e.preventDefault()});b.addEventListener('click',function(){var e=last,t=b.dataset.e||('%'+b.dataset.v),a=e.selectionStart==null?e.value.length:e.selectionStart,z=e.selectionEnd==null?a:e.selectionEnd;e.value=e.value.slice(0,a)+t+e.value.slice(z);e.focus();e.selectionStart=e.selectionEnd=a+t.length;up()})});up()})();</script>
<?php else: ?>
<?php $T=db()->query('SELECT t.*,(SELECT COUNT(*) FROM alert_rules r WHERE r.template_id=t.id) used FROM alert_templates t ORDER BY t.name')->fetchAll(); ?>
<p class="mut"><?= t('En larmmall bestämmer hur notisen ser ut. Koppla en mall till en larmregel när du skapar eller ändrar regeln.','An alert template decides how the notification looks. Attach a template to an alert rule when you create or edit the rule.') ?></p>
<?php if($adm): ?><p><a class="btn go" href="alert_templates.php?new=1"><?= t('Ny larmmall','New alert template') ?></a></p><?php endif; ?>
<div class="tw"><table class="card"><tr><th><?= t('Namn','Name') ?></th><th><?= t('Rubrik','Subject') ?></th><th><?= t('Används av','Used by') ?></th><th></th></tr>
<?php foreach($T as $x): ?><tr><td><b><?= h($x['name']) ?></b></td><td><code><?= h($x['subject']) ?></code></td><td><?= (int)$x['used'] ?> <?= t('regler','rules') ?></td><td class="inline"><?php if($adm): ?><a class="btn sm" href="alert_templates.php?edit=<?= (int)$x['id'] ?>"><?= t('Ändra','Edit') ?></a><form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="copy"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn sm"><?= t('Kopiera','Copy') ?></button></form><form method="post" onsubmit="return confirm('<?= h(t('Ta bort mallen? Regler som använder den får standardtexten.','Delete the template? Rules using it get the default text.')) ?>')"><?= csrf_field() ?><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><button class="btn sm danger"><?= t('Ta bort','Delete') ?></button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php if($adm): ?><form method="post" class="card inline"><?= csrf_field() ?><input type="hidden" name="a" value="pubu"><label class="f"><?= t('Publik adress till VM Monitor (för %link)','Public URL of VM Monitor (for %link)') ?><input name="public_url" value="<?= h(setting('public_url','')) ?>" placeholder="https://monitor.example.com" size="34"></label><button class="btn"><?= t('Spara','Save') ?></button></form><?php endif; ?>
<?php endif; ?>
<style>.tplgrid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:18px;margin-top:8px}@media(max-width:900px){.tplgrid{grid-template-columns:minmax(0,1fr)}}.tplvars{margin:0 0 6px;line-height:2}.tplvars .tv{font-family:monospace}</style>
<?php bottom();
