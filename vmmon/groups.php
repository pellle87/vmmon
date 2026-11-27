<?php require 'config.php'; require 'lib.php'; need_login(); track_user(); csrf_check();
$pdo=db(); $adm=is_admin(); $edit=(int)($_GET['edit']??0);
if($_SERVER['REQUEST_METHOD']==='POST'&&!$adm){ http_response_code(403); exit(t('Du har bara läsbehörighet.','You have read-only access.')); }
$ids=fn($k)=>array_values(array_unique(array_filter(array_map('intval',(array)($_POST[$k]??[])))));
if($_SERVER['REQUEST_METHOD']==='POST'){ $a=$_POST['a']??''; $gid=(int)($_POST['gid']??0); $nm=trim(mb_substr((string)($_POST['name']??''),0,80));
 try{
  if($a==='create'){ if($nm===''){ $_SESSION['flash']=t('Ange ett gruppnamn.','Enter a group name.'); header('Location: groups.php'); exit; }
   $mx=(int)$pdo->query('SELECT COALESCE(MAX(sort),0) FROM device_groups')->fetchColumn(); $pdo->prepare('INSERT INTO device_groups(name,sort) VALUES(?,?)')->execute([$nm,$mx+1]); header('Location: groups.php?edit='.$pdo->lastInsertId()); exit; }
  if($a==='save'&&$gid){ if($nm!=='') $pdo->prepare('UPDATE device_groups SET name=? WHERE id=?')->execute([$nm,$gid]); $pdo->prepare('DELETE FROM device_group_members WHERE group_id=?')->execute([$gid]);
   $ins=$pdo->prepare('INSERT IGNORE INTO device_group_members(group_id,server_id) VALUES(?,?)'); $ex=array_flip($pdo->query('SELECT id FROM servers')->fetchAll(PDO::FETCH_COLUMN)); foreach($ids('dev') as $d) if(isset($ex[$d])) $ins->execute([$gid,$d]); $_SESSION['flash']=t('Gruppen sparades.','Group saved.'); }
  if($a==='del'&&$gid){ $pdo->prepare('DELETE FROM device_group_members WHERE group_id=?')->execute([$gid]); $pdo->prepare('DELETE FROM device_groups WHERE id=?')->execute([$gid]);
   // ta bort gruppen ur larmreglernas målval; en regel som bara riktade sig mot gruppen stängs av så att den inte plötsligt gäller alla
   foreach($pdo->query('SELECT id,targets FROM alert_rules WHERE targets IS NOT NULL')->fetchAll() as $r){ $j=json_decode((string)$r['targets'],true); if(!is_array($j)) continue; $n=array_values(array_filter($j,fn($x)=>!(isset($x['g'])&&(int)$x['g']===$gid))); if(count($n)===count($j)) continue;
    if($n) $pdo->prepare('UPDATE alert_rules SET targets=? WHERE id=?')->execute([json_encode($n),$r['id']]); else $pdo->prepare('UPDATE alert_rules SET targets=NULL,enabled=0 WHERE id=?')->execute([$r['id']]); }
   $_SESSION['flash']=t('Gruppen togs bort. Larmregler som bara gällde den gruppen har inaktiverats.','Group deleted. Alert rules that only applied to that group were disabled.'); }
  if($a==='bulk'){ $g=$ids('groups'); $d=$ids('dev'); $mode=($_POST['mode']??'')==='remove'?'remove':'add';
   if($g&&$d){ $ex=array_flip($pdo->query('SELECT id FROM device_groups')->fetchAll(PDO::FETCH_COLUMN)); $ins=$pdo->prepare('INSERT IGNORE INTO device_group_members(group_id,server_id) VALUES(?,?)'); $del=$pdo->prepare('DELETE FROM device_group_members WHERE group_id=? AND server_id=?');
    foreach($g as $x){ if(!isset($ex[$x])) continue; foreach($d as $y) ($mode==='add'?$ins:$del)->execute([$x,$y]); } $_SESSION['flash']=$mode==='add'?t('Enheterna lades till i de valda grupperna.','Devices added to the selected groups.'):t('Enheterna togs bort från de valda grupperna.','Devices removed from the selected groups.'); }
   else $_SESSION['flash']=t('Välj minst en grupp och en enhet.','Select at least one group and one device.'); }
 }catch(Throwable $e){ $_SESSION['flash']=t('Kunde inte spara (har sql/3.17.sql körts?).','Could not save (has sql/3.17.sql been run?).'); }
 header('Location: groups.php'); exit; }
// ---- data
$G=dg_all(); $MB=[]; try{ foreach($pdo->query('SELECT * FROM device_group_members') as $r) $MB[(int)$r['group_id']][(int)$r['server_id']]=1; }catch(Throwable $e){}
$DEV=[]; $cat=[t('Servrar','Servers')=>[],t('Virtualisering','Virtualization')=>[],t('Nätverk','Network')=>[],t('Lagring','Storage')=>[],'BMC'=>[],t('Tjänster','Services')=>[]]; $ck=array_keys($cat);
foreach($pdo->query('SELECT * FROM servers ORDER BY name')->fetchAll() as $s){ $s['_t']=srv_title($s); $DEV[(int)$s['id']]=$s;
 $k=!empty($s['is_services'])?5:(!empty($s['hv_type'])?1:(!empty($s['nw_type'])?($s['nw_role']==='bmc'?4:(nw_is_st($s['nw_role'])?3:2)):0)); $cat[$ck[$k]][]=$s; }
foreach($cat as &$l) usort($l,fn($a,$b)=>strnatcasecmp($a['_t'],$b['_t'])); unset($l);
function dev_list($cat,$name,$sel=[]){ $o='<div class="dgdev">'; foreach($cat as $gl=>$l){ if(!$l) continue; $o.='<div class="dgh"><b>'.h($gl).'</b> <a href="#" class="dgall" data-on="1">'.t('alla','all').'</a> · <a href="#" class="dgall" data-on="0">'.t('ingen','none').'</a></div><div class="dgl">';
  foreach($l as $s) $o.='<label class="pl"><input type="checkbox" name="'.$name.'[]" value="'.(int)$s['id'].'"'.(isset($sel[(int)$s['id']])?' checked':'').'> '.h($s['_t']).(empty($s['is_services'])&&$s['host']&&$s['host']!==$s['_t']?' <small>'.h($s['host']).'</small>':'').'</label>'; $o.='</div>'; } return $o.'</div>'; }
top(t('Enhetsgrupper','Device groups')); flash(); ?>
<?php if($adm): ?><h2><?= t('Inställningar','Settings') ?></h2>
<?php settings_tabs('groups'); ?><?php else: ?><h2><?= t('Enhetsgrupper','Device groups') ?></h2><?php endif; ?>
<?php if($adm): ?><h3><?= t('Enhetsgrupper','Device groups') ?></h3><?php endif; ?>
<p class="mut"><?= t('Gruppera servrar, hosts, nätverks- och lagringsenheter och tjänster. Grupperna kan visas som kolumn på listsidorna (Kolumner) och användas som mål för larmregler.','Group servers, hosts, network and storage devices and services. Groups can be shown as a column on the list pages (Columns) and used as the target of alert rules.') ?></p>
<?php if($adm): ?><form method="post" class="card inline"><?= csrf_field() ?><input type="hidden" name="a" value="create"><input name="name" maxlength="80" placeholder="<?= h(t('Ny grupp, t.ex. Kontoret','New group, e.g. Office')) ?>" style="min-width:240px"><button class="btn"><?= t('Skapa grupp','Create group') ?></button></form><?php endif; ?>
<?php if(!$G): ?><p class="mut"><?= t('Inga grupper ännu.','No groups yet.') ?></p><?php endif; ?>
<?php foreach($G as $g): $gid=(int)$g['id']; $mem=$MB[$gid]??[]; $isEd=$adm&&$edit===$gid; ?>
<div class="card dgc" id="g<?= $gid ?>">
<?php if($isEd): ?>
 <form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="save"><input type="hidden" name="gid" value="<?= $gid ?>">
 <div class="inline" style="margin-bottom:8px"><label class="f"><?= t('Gruppnamn','Group name') ?><input name="name" maxlength="80" value="<?= h($g['name']) ?>" style="min-width:260px"></label></div>
 <div class="mut" style="margin-bottom:4px"><?= t('Välj en eller flera enheter som ska ingå i gruppen:','Select one or more devices to include in the group:') ?></div>
 <?= dev_list($cat,'dev',$mem) ?>
 <div class="inline" style="margin-top:10px"><button class="btn go"><?= t('Spara','Save') ?></button><a class="btn" href="groups.php"><?= t('Avbryt','Cancel') ?></a></div></form>
 <form method="post" onsubmit="return confirm('<?= h(t('Ta bort gruppen? Enheterna påverkas inte.','Delete the group? The devices are not affected.')) ?>')" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="a" value="del"><input type="hidden" name="gid" value="<?= $gid ?>"><button class="btn danger sm"><?= t('Ta bort grupp','Delete group') ?></button></form>
<?php else: ?>
 <div class="inline"><h3 style="margin:0"><?= group_icon_html($g) ?><?= h($g['name']) ?></h3><span class="mut"><?= sprintf(t('%d enheter','%d devices'),count($mem)) ?></span><?php if($adm): ?><a class="btn sm" href="groups.php?edit=<?= $gid ?>#g<?= $gid ?>"><?= t('Ändra grupp','Edit group') ?></a><?php endif; ?></div>
 <div class="dgchips"><?php if(!$mem) echo '<span class="mut">'.t('Tom grupp','Empty group').'</span>'; else foreach($mem as $sid=>$_) if(isset($DEV[$sid])) echo '<a class="chip" href="'.(!empty($DEV[$sid]['is_services'])?'services.php':(!empty($DEV[$sid]['hv_type'])?'host.php?id='.$sid:(!empty($DEV[$sid]['nw_type'])?'netdev.php?id='.$sid:'server.php?id='.$sid))).'">'.h($DEV[$sid]['_t']).'</a>'; ?></div>
<?php endif; ?></div>
<?php endforeach; ?>
<?php if($adm&&$G&&$DEV): ?><details class="card"><summary><b><?= t('Flytta flera enheter samtidigt','Bulk add/remove devices') ?></b></summary>
 <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="a" value="bulk">
 <div class="mut" style="margin-bottom:4px">1. <?= t('Välj enheter','Select devices') ?></div><?= dev_list($cat,'dev') ?>
 <div class="mut" style="margin:10px 0 4px">2. <?= t('Välj en eller flera grupper','Select one or more groups') ?></div><div class="dgl"><?php foreach($G as $g) echo '<label class="pl"><input type="checkbox" name="groups[]" value="'.(int)$g['id'].'"> <i class="ti ico bi" style="width:16px;height:16px;margin-right:4px;-webkit-mask-image:url('.h(group_icon_url($g)).');mask-image:url('.h(group_icon_url($g)).')"></i>'.h($g['name']).'</label>'; ?></div>
 <div class="inline" style="margin-top:10px"><button class="btn go" name="mode" value="add"><?= t('Lägg till i valda grupper','Add to selected groups') ?></button><button class="btn warn" name="mode" value="remove"><?= t('Ta bort från valda grupper','Remove from selected groups') ?></button></div></form></details><?php endif; ?>
<style>.dgc{margin-bottom:12px}.dgchips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.chip{background:var(--btn);color:var(--tx);border-radius:999px;padding:3px 10px;font-size:13px;text-decoration:none}.chip:hover{background:var(--acc);color:var(--onacc)}
.dgdev .dgh{margin:8px 0 3px}.dgl{display:flex;flex-wrap:wrap;gap:4px 16px}.dgl .pl{min-width:190px}.dgall{font-size:12px}</style>
<script>document.addEventListener('click',function(e){var a=e.target.closest('.dgall');if(!a)return;e.preventDefault();var on=a.dataset.on==='1';a.closest('.dgh').nextElementSibling.querySelectorAll('input[type=checkbox]').forEach(function(c){c.checked=on})});</script>
<?php bottom();
