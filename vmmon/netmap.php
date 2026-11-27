<?php require 'config.php'; require 'lib.php'; need_login(); track_user(); csrf_check();
$pdo=db(); $adm=is_admin();
function nm_norm($n){ $n=strtolower(trim((string)$n,"\" \r\n")); if($n===''||filter_var($n,FILTER_VALIDATE_IP)) return $n; return explode('.',$n)[0]; }
function nm_jget($k){ $j=json_decode((string)setting($k,''),true); return is_array($j)?$j:[]; }
if($_SERVER['REQUEST_METHOD']==='POST'){
 $a=$_POST['a']??'';
 if($a==='opt'){ $o=json_decode((string)($_POST['o']??''),true); $c=[]; foreach(['nmports','nmgrid','nmunk','nmiso'] as $k) if(is_array($o)&&isset($o[$k])) $c[$k]=$o[$k]?1:0; try{ $pdo->prepare('INSERT INTO user_prefs(user_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([(int)$_SESSION['u']['id'],'nmopt',json_encode($c)]); echo '{"ok":true}'; }catch(Throwable $e){ http_response_code(500); echo '{"ok":false}'; } exit; }
 if(!$adm){ http_response_code(403); exit('{}'); }
 if($a==='pos'){ $in=json_decode((string)($_POST['pos']??''),true); $pos=nm_jget('netmap_pos'); if(is_array($in)) foreach($in as $k=>$v){ if(is_array($v)&&count($v)===2&&preg_match('/^[0-9a-z:\-_.]{1,80}$/i',(string)$k)) $pos[(string)$k]=[round((float)$v[0]),round((float)$v[1])]; } if(count($pos)>1500) $pos=array_slice($pos,-1500,null,true); set_setting('netmap_pos',json_encode($pos)); header('Content-Type: application/json'); exit('{"ok":1}'); }
 if($a==='resetpos'){ set_setting('netmap_pos',''); header('Location: netmap.php'); exit; }
 if($a==='addlink'){ $A=(int)($_POST['la']??0); $B=(int)($_POST['lb']??0); if($A>0&&$B>0&&$A!==$B){ $L=nm_jget('netmap_links'); $L[]=['id'=>bin2hex(random_bytes(4)),'a'=>$A,'ap'=>mb_substr(trim((string)($_POST['lap']??'')),0,60),'b'=>$B,'bp'=>mb_substr(trim((string)($_POST['lbp']??'')),0,60),'l'=>mb_substr(trim((string)($_POST['ll']??'')),0,80)]; set_setting('netmap_links',json_encode(array_slice($L,-500))); $_SESSION['flash']=t('Kopplingen lades till.','Link added.'); } header('Location: netmap.php'); exit; }
 if($a==='dellink'){ $L=array_values(array_filter(nm_jget('netmap_links'),fn($x)=>($x['id']??'')!==($_POST['id']??''))); set_setting('netmap_links',json_encode($L)); $_SESSION['flash']=t('Kopplingen togs bort.','Link removed.'); header('Location: netmap.php'); exit; }
 http_response_code(400); exit('{}');
}
// ---- bygg graf ----
$rows=$pdo->query("SELECT id,name,host,os_info,info,hv_data,nw_type,nw_role,hv_type,nw_data,nw_status,hv_status,enabled,dash_hidden,(maint_until IS NOT NULL AND maint_until>NOW()) maint FROM servers WHERE is_services=0 ORDER BY name")->fetchAll();
$png=[]; try{ foreach($pdo->query("SELECT server_id,MAX(last_status='down') d FROM server_checks WHERE kind='ping' AND enabled=1 AND mon_up=1 GROUP BY server_id") as $r) $png[(int)$r['server_id']]=(int)$r['d']; }catch(Throwable $e){}
$byName=[]; $byIp=[]; $byMac=[]; $nodes=[]; $nbrs=[]; $fdbs=[]; $vmhost=[]; $arpm=[];
foreach($rows as $r){ $id=(int)$r['id']; $nd=json_decode((string)$r['nw_data'],true)?:[]; $inf=json_decode((string)$r['info'],true)?:[];
 $role=$r['nw_type']?(string)($r['nw_role']?:'switch'):($r['hv_type']?'host':'server');
 $st='ok'; if(!(int)$r['enabled']) $st='dis'; elseif($r['nw_type']&&$r['nw_status']==='down') $st='bad'; elseif($r['hv_type']&&$r['hv_status']==='down') $st='bad'; elseif(!empty($png[$id])) $st='bad';
 $url=($r['nw_type']?'netdev.php?id=':($r['hv_type']?'host.php?id=':'server.php?id=')).$id;
 $nodes[$id]=['id'=>(string)$id,'n'=>$r['name'],'k'=>$role,'st'=>$st,'u'=>$url,'ip'=>filter_var($r['host'],FILTER_VALIDATE_IP)?$r['host']:''];
 $byName[nm_norm($r['name'])]=$id; if(!filter_var($r['host'],FILTER_VALIDATE_IP)) $byName[nm_norm($r['host'])]=$id; else $byIp[$r['host']]=$id;
 foreach([$nd['hostname']??'',$inf['hostname']??''] as $hn) if($hn!=='') $byName[nm_norm($hn)]=$id;
 foreach((array)($nd['nbr']??[]) as $x) $nbrs[$id][]=$x;
 foreach((array)($nd['fdb']??[]) as $x) $fdbs[$id][]=$x;
 foreach((array)($nd['arp']??[]) as $ai=>$am) if(!isset($arpm[$ai])) $arpm[$ai]=$am;
 if($r['hv_type']) foreach((array)(json_decode((string)$r['hv_data'],true)['vms']??[]) as $vv){ $vk=nm_norm($vv['n']??''); if($vk!==''&&!isset($vmhost[$vk])) $vmhost[$vk]=$id; } }
$mc=[]; try{ foreach($pdo->query("SELECT server_id,LOWER(mac) mac FROM net_if WHERE mac<>'' AND mac<>'00:00:00:00:00:00'") as $r){ $mc[$r['mac']][(int)$r['server_id']]=1; } }catch(Throwable $e){} foreach($mc as $m=>$s) if(count($s)===1) $byMac[$m]=array_key_first($s);
$links=[]; $unk=[];
foreach($nbrs as $a=>$list) foreach($list as $x){ $b=null; if(!empty($x['rm'])) $b=$byMac[strtolower($x['rm'])]??null; if($b===null&&!empty($x['ri'])) $b=$byIp[$x['ri']]??null; if($b===null&&($x['rn']??'')!=='') $b=$byName[nm_norm($x['rn'])]??null;
 if($b===$a) continue; if($b===null){ $key='u:'.(($x['rn']??'')!==''?nm_norm($x['rn']):($x['rm']?:$x['ri'])); if($key==='u:') continue; if(!isset($nodes[$key])) $nodes[$key]=['id'=>$key,'n'=>($x['rn']!==''?$x['rn']:($x['rm']?:$x['ri'])),'k'=>'unknown','st'=>'unk','u'=>'','ip'=>$x['ri']??'']; $bk=$key; } else $bk=(string)$b;
 $ak=(string)$a; $ap=(string)$x['lp']; $bp=(string)($x['rp']??''); if(strcmp($ak,$bk)>0){ [$ak,$bk]=[$bk,$ak]; [$ap,$bp]=[$bp,$ap]; }
 $dup=false; foreach($links as &$L){ if($L['a']===$ak&&$L['b']===$bk&&((strcasecmp($L['ap'],$ap)===0&&$ap!=='')||(strcasecmp($L['bp'],$bp)===0&&$bp!==''))){ if($L['ap']==='') $L['ap']=$ap; if($L['bp']==='') $L['bp']=$bp; $dup=true; break; } } unset($L);
 if(!$dup) $links[]=['a'=>$ak,'b'=>$bk,'ap'=>$ap,'bp'=>$bp,'m'=>0,'p'=>strtoupper((string)$x['pr'])]; }

// Fysiska maskiner utan LLDP: switchens MAC-tabell visar vilken port enhetens MAC lärts på (portar med LLDP-granne eller över 40 MAC hoppas över = uppkopplingar mellan switchar)
$pn=[]; try{ foreach($pdo->query("SELECT server_id,if_index,name FROM net_if") as $r) $pn[(int)$r['server_id']][(int)$r['if_index']]=(string)$r['name']; }catch(Throwable $e){}
$isVm=[]; foreach($nodes as $id=>$nd){ if(is_int($id)&&$nd['k']==='server'&&isset($vmhost[nm_norm($nd['n'])])&&$vmhost[nm_norm($nd['n'])]!==$id) $isVm[$id]=1; }
$hasMac=array_flip(array_values($byMac)); foreach($nodes as $id=>$nd){ if(!is_int($id)||isset($hasMac[$id])||in_array($nd['k'],['switch','router','ap'],true)||$nd['ip']===''||empty($arpm[$nd['ip']])) continue; if(!isset($byMac[$arpm[$nd['ip']]])) $byMac[$arpm[$nd['ip']]]=$id; }
foreach($fdbs as $sw=>$list){ $lldpPorts=[]; foreach($nbrs[$sw]??[] as $x) if(!empty($x['li'])) $lldpPorts[(int)$x['li']]=1; $seen=[];
 foreach($list as $x){ $t=$byMac[$x['m']]??null; if($t===null||$t===$sw||isset($lldpPorts[(int)$x['i']])||(int)$x['c']>40||isset($isVm[$t])) continue; if(in_array($nodes[$t]['k'],['switch','router','ap'],true)) continue; $key=$sw.'-'.$x['i'].'-'.$t; if(isset($seen[$key])) continue; $seen[$key]=1;
  $dupe=false; foreach($links as $L) if(!$L['m']&&(($L['a']===(string)$sw&&$L['b']===(string)$t)||($L['a']===(string)$t&&$L['b']===(string)$sw))){ $dupe=true; break; } if($dupe) continue;
  $links[]=['a'=>(string)$sw,'b'=>(string)$t,'ap'=>$pn[$sw][(int)$x['i']]??('if '.$x['i']),'bp'=>'','m'=>0,'p'=>'MAC']; } }
// Virtuella maskiner: kopplas till sin hypervisor (via namn)
foreach($nodes as $id=>$nd){ if(!is_int($id)||$nd['k']!=='server') continue; $hid=$vmhost[nm_norm($nd['n'])]??null; if($hid&&$hid!==$id&&isset($nodes[$hid])) $links[]=['a'=>(string)$hid,'b'=>(string)$id,'ap'=>'','bp'=>'','m'=>0,'p'=>'VM']; }
$man=nm_jget('netmap_links'); foreach($man as $L){ if(!isset($nodes[(string)$L['a']])||!isset($nodes[(string)$L['b']])) continue; $links[]=['a'=>(string)$L['a'],'b'=>(string)$L['b'],'ap'=>(string)($L['ap']??''),'bp'=>(string)($L['bp']??''),'m'=>1,'p'=>'','id'=>$L['id'],'l'=>(string)($L['l']??'')]; }
$data=['nodes'=>array_values($nodes),'links'=>$links,'pos'=>(object)nm_jget('netmap_pos')];
top(t('Nätverkskarta','Network map'));
$tot=0; $ndev=0; foreach($links as $L) if(!$L['m']) $tot++; foreach($nbrs as $l) $ndev++;
?>
<h2><?= t('Nätverkskarta','Network map') ?></h2>
<div class="nmbar">
<label class="chk"><input type="checkbox" id="nmports"> <?= t('Visa portnamn','Show port names') ?></label>
<label class="chk"><input type="checkbox" id="nmgrid" checked> <?= t('Visa rutnät','Show grid') ?></label>
<label class="chk"><input type="checkbox" id="nmunk" checked> <?= t('Visa okända grannar','Show unknown neighbours') ?></label>
<label class="chk"><input type="checkbox" id="nmiso" checked> <?= t('Visa enheter utan koppling','Show unlinked devices') ?></label>
<input type="search" id="nmq" placeholder="<?= h(t('Sök enhet…','Find device…')) ?>">
<button type="button" class="btn sm" id="nmfit"><?= t('Anpassa vy','Fit view') ?></button>
<?php if($adm): ?><form method="post" class="inline" onsubmit="return confirm('<?= h(t('Återställ alla placeringar?','Reset all positions?')) ?>')"><?= csrf_field() ?><input type="hidden" name="a" value="resetpos"><button class="btn sm"><?= t('Ordna om automatiskt','Re-arrange automatically') ?></button></form><?php endif; ?>
</div>
<div class="nmwrap"><svg id="nm" xmlns="http://www.w3.org/2000/svg"></svg><div id="nmtip" class="nmtip" hidden></div></div>
<?php if($adm): ?>
<h3><?= t('Egna kopplingar','Manual links') ?></h3>
<form method="post" class="card inline" style="align-items:flex-end;gap:10px"><?= csrf_field() ?><input type="hidden" name="a" value="addlink">
<?php $opt=''; foreach($rows as $r) $opt.='<option value="'.(int)$r['id'].'">'.h($r['name']).'</option>'; ?>
<label class="f"><?= t('Enhet A','Device A') ?><select name="la"><?= $opt ?></select></label>
<label class="f"><?= t('Port A','Port A') ?><input name="lap" maxlength="60" style="width:110px"></label>
<label class="f"><?= t('Enhet B','Device B') ?><select name="lb"><?= $opt ?></select></label>
<label class="f"><?= t('Port B','Port B') ?><input name="lbp" maxlength="60" style="width:110px"></label>
<label class="f"><?= t('Beskrivning (valfri)','Label (optional)') ?><input name="ll" maxlength="80" style="width:180px"></label>
<button class="btn go"><?= t('Lägg till','Add') ?></button></form>
<?php if($man): ?><div class="tw"><table><tr><th><?= t('Från','From') ?></th><th><?= t('Till','To') ?></th><th><?= t('Beskrivning','Label') ?></th><th></th></tr>
<?php foreach($man as $L): $na=$nodes[(string)$L['a']]['n']??('#'.$L['a']); $nb=$nodes[(string)$L['b']]['n']??('#'.$L['b']); ?>
<tr><td><?= h($na) ?><?= ($L['ap']??'')!==''?' <span class="mut">('.h($L['ap']).')</span>':'' ?></td><td><?= h($nb) ?><?= ($L['bp']??'')!==''?' <span class="mut">('.h($L['bp']).')</span>':'' ?></td><td><?= h($L['l']??'') ?></td>
<td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="a" value="dellink"><input type="hidden" name="id" value="<?= h($L['id']) ?>"><button class="btn sm danger"><?= t('Ta bort','Remove') ?></button></form></td></tr>
<?php endforeach; ?></table></div><?php endif; ?>
<?php endif; ?>
<script>window.NM=<?= json_encode($data,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) ?>;window.NMADM=<?= $adm?1:0 ?>;window.NMT=<?= json_encode(csrf()) ?>;window.NMO=<?= json_encode((object)(json_decode(user_pref('nmopt'),true)?:[])) ?>;window.NMX={is:<?= json_encode(t('Nät','Network')) ?>,iu:<?= json_encode(t('Utan koppling','Unlinked')) ?>,ln:<?= json_encode(t('Port','Port')) ?>,m:<?= json_encode(t('Egen koppling','Manual link')) ?>};</script>
<script src="assets/netmap.js"></script>
<?php bottom();
