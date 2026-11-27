<?php
// Ikonbibliotek för tjänstekontroller: std (följer med), mdi + si (hämtas från Inställningar), up (egna SVG i databasen).
function icon_root(){ return __DIR__.'/assets/chkicons'; }
function icon_spec_ok($s){ return $s===''||preg_match('/^(?:(?:std|mdi|si):[a-z0-9._-]{1,70}|up:\d{1,9})$/',$s)===1; }
function icon_url($s){ if($s==='') return ''; [$k,$n]=explode(':',$s,2); if($k==='up') return 'icon.php?id='.(int)$n;
 if($k==='std') return is_file(icon_root().'/std/'.$n.'.svg')?'assets/chkicons/std/'.$n.'.svg':''; if(!in_array($k,['mdi','si'],true)) return '';
 return is_file(icon_root()."/lib/$k/$n.svg")?"assets/chkicons/lib/$k/$n.svg":''; }
// Standardikon per kontrolltyp
function icon_default($src,$kind=''){ if($src==='tcp'&&$kind==='ping') return 'std:ping'; $m=['tcp'=>'std:tcp','url'=>'std:url','snmp'=>'std:snmp','zabbix'=>'std:zabbix','docker'=>'std:docker','ha'=>'si:homeassistant','pbs'=>'si:proxmox','hv'=>'std:api','nw'=>'std:snmp'];
 if($src==='zabbix'&&strpos($kind,'docker_containers')===0) return 'std:docker'; return $m[$src]??'std:heartbeat'; }
function icon_for($src,$kind,$spec){ $u=icon_url((string)$spec); if($u==='') $u=icon_url(icon_default($src,$kind)); if($u==='') $u=icon_url(['ha'=>'std:home','pbs'=>'std:pbs'][$src]??'std:heartbeat'); return $u; }
function icon_pick($c){ foreach($c as $x) if(($u=icon_url($x))!=='') return $u; return ''; }
function icon_list_std(){ $o=[]; foreach(glob(icon_root().'/std/*.svg')?:[] as $f) $o[]=basename($f,'.svg'); sort($o); return $o; }
function icon_lib_index($fresh=false){ static $ix=null; if($ix!==null&&!$fresh) return $ix; $f=icon_root().'/lib/index.json'; $ix=is_file($f)?(json_decode((string)file_get_contents($f),true)?:[]):[]; return $ix; }
function icon_sanitize_svg($s){ $s=trim((string)$s); if(strlen($s)>150000||stripos($s,'<svg')===false) return null;
 $s=preg_replace('/<\?xml.*?\?>|<!DOCTYPE.*?(\]>|>)|<!--.*?-->|<!ENTITY[^>]*>/is','',$s);
 $s=preg_replace('/<(script|foreignObject|iframe|object|embed|audio|video|image)\b.*?(<\/\1>|\/>)/is','',$s);
 $s=preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$s);
 $s=preg_replace('/\s(?:xlink:)?href\s*=\s*("(?!#)[^"]*"|\'(?!#)[^\']*\')/i','',$s);
 $s=preg_replace('/javascript:/i','',$s);
 if(!preg_match('/<svg\b[^>]*\sviewBox\s*=/i',$s)&&preg_match('/<svg\b[^>]*\swidth\s*=\s*"([\d.]+)(?:px)?"[^>]*\sheight\s*=\s*"([\d.]+)(?:px)?"/i',$s,$m)) $s=preg_replace('/<svg\b/i','<svg viewBox="0 0 '.$m[1].' '.$m[2].'"',$s,1);
 if(!preg_match('/<svg\b[^>]*\sxmlns\s*=/i',$s)) $s=preg_replace('/<svg\b/i','<svg xmlns="http://www.w3.org/2000/svg"',$s,1);
 return $s; }
function icon_uploaded(){ try{ return db()->query('SELECT id,name FROM icons ORDER BY name,id')->fetchAll(); }catch(Throwable $e){ return []; } }
// Hämtar Material Design Icons (@mdi/svg) och Simple Icons (simple-icons) från npm-registret.
function icon_fetch_lib(){ if(!function_exists('curl_init')) return [false,'PHP curl saknas']; @set_time_limit(600); @ini_set('memory_limit','512M'); $root=icon_root().'/lib'; $res=[]; $ix=[];
 foreach(['mdi'=>['@mdi/svg','svg/'],'si'=>['simple-icons','icons/']] as $k=>[$pkg,$sub]){
  $dir="$root/$k"; if(!is_dir($dir)&&!@mkdir($dir,0775,true)) return [false,t('Kan inte skriva i ','Cannot write to ').$dir];
  if(!is_writable($dir)) return [false,t('Mappen är inte skrivbar: ','Folder is not writable: ').$dir];
  [$meta,$e]=http_json('https://registry.npmjs.org/'.$pkg.'/latest',[],true,30); $tb=is_array($meta)?($meta['dist']['tarball']??''):''; if($tb==='') return [false,"$pkg: ".($e?:t('inget svar från npm','no reply from npm'))];
  $tmp=sys_get_temp_dir().'/vmmon_'.$k.'_'.getmypid().'.tgz'; $fp=fopen($tmp,'w'); $ch=curl_init($tb); curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_TIMEOUT=>300,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_USERAGENT=>'VM Monitor']); $ok=curl_exec($ch); $er=curl_error($ch); curl_close($ch); fclose($fp);
  if(!$ok){ @unlink($tmp); return [false,"$pkg: ".$er]; }
  try{ $ph=new PharData($tmp); $tar=substr($tmp,0,-4).'.tar'; @unlink($tar); $ph->decompress(); $t2=new PharData($tar); $n=0; $names=[];
   foreach(new RecursiveIteratorIterator($t2) as $fi){ $p=str_replace('\\','/',$fi->getPathname()); $pos=strpos($p,'/package/'.$sub); if($pos===false) continue; $rel=substr($p,$pos+strlen('/package/'.$sub)); if(!preg_match('/^[a-z0-9._-]+\.svg$/i',$rel)) continue; $c=file_get_contents($fi->getPathname()); if($c===false||stripos($c,'<svg')===false) continue; $nm=strtolower(substr($rel,0,-4)); if(@file_put_contents("$dir/$nm.svg",$c)===false){ @unlink($tar); @unlink($tmp); return [false,t('Kan inte skriva filer i ','Cannot write files in ').$dir.t(' – webbserverns användare behöver skrivrättighet (ägare eller grupp), t.ex. chown/chgrp till www-data och 775.',' – the web server user needs write permission (owner or group), e.g. chown/chgrp to www-data and 775.')]; } $names[]=$nm; $n++; }
   @unlink($tar); @unlink($tmp); sort($names); $ix[$k]=$names; $res[]="$k: $n"; }
  catch(Throwable $x){ @unlink($tmp); return [false,"$pkg: ".$x->getMessage()]; } }
 if(@file_put_contents($root.'/index.json',json_encode($ix))===false) return [false,t('Kan inte skriva ','Cannot write ').$root.'/index.json']; return [true,implode(', ',$res)]; }
// Märkesikon för serverkortets rubrik (kräver hämtade ikonbibliotek; annars '' och de vanliga ikonerna används).
function brand_icon($os,$osinfo,$inf,$hvtype,$nwv,$hint=''){ $o=strtolower(trim((string)$osinfo.' '.$hint)); $c=[]; $has=fn($a)=>(bool)array_filter((array)$a,fn($x)=>stripos($o,$x)!==false);
 // nyckelord i OS-/modelltext -> Simple Icons-namn (första träffen vinner)
 $kw=['home assistant'=>'homeassistant','proxmox'=>'proxmox','truenas'=>'truenas','synology'=>'synology','qnap'=>'qnap','unraid'=>'unraid','openmediavault'=>'openmediavault','mikrotik'=>'mikrotik','routeros'=>'mikrotik','fortinet'=>'fortinet','fortigate'=>'fortinet','juniper'=>'junipernetworks','junos'=>'junipernetworks','netgear'=>'netgear','tp-link'=>'tplink','tplink'=>'tplink','ubiquiti'=>'ubiquiti','unifi'=>'ubiquiti','edgeos'=>'ubiquiti','opnsense'=>'opnsense','pfsense'=>'pfsense','openwrt'=>'openwrt','vyos'=>'vyos','sophos'=>'sophos','palo alto'=>'paloaltonetworks','watchguard'=>'watchguard','sonicwall'=>'sonicwall','checkpoint'=>'checkpoint','arista'=>'arista','dell'=>'dell','supermicro'=>'supermicro','lenovo'=>'lenovo','asustor'=>'asustor','raspberry'=>'raspberrypi','vmware'=>'vmware','esxi'=>'vmware','freebsd'=>'freebsd','openbsd'=>'openbsd','netbsd'=>'netbsd','macos'=>'apple','darwin'=>'apple','android'=>'android'];
 if(!empty($inf['ha'])) $c=['si:homeassistant'];
 elseif(($inf['hv']['type']??'')==='pbs'||stripos($o,'proxmox backup')!==false) $c=['si:proxmox'];
 elseif($hvtype) $c=['proxmox'=>['si:proxmox'],'vmware'=>['si:vmware','mdi:vmware'],'hyperv'=>['mdi:microsoft-windows']][$hvtype]??[];
 else {
  if($nwv){ $m=['unifi'=>'ubiquiti','cisco'=>'cisco','meraki'=>'cisco','hp'=>'hp','aosc'=>'hewlettpackardenterprise','huawei'=>'huawei','opnsense'=>'opnsense','pfsense'=>'pfsense','truenas'=>'truenas','idrac'=>'dell','ilo'=>'hewlettpackardenterprise']; if(isset($m[$nwv])){ $c[]='si:'.$m[$nwv]; if($nwv==='hp'){ $c[]='si:hewlettpackardenterprise'; } if($nwv==='aosc') $c[]='si:arubanetworks'; } }
  if(!$c) foreach($kw as $k=>$n) if(stripos($o,$k)!==false){ $c[]='si:'.$n; break; }
  if(!$c){ if($os==='windows'||stripos($o,'windows')!==false) $c=['mdi:microsoft-windows','si:windows'];
   else { $al=['red'=>'redhat','rocky'=>'rockylinux','alma'=>'almalinux','arch'=>'archlinux','alpine'=>'alpinelinux','opensuse'=>'opensuse','suse'=>'suse','mint'=>'linuxmint','raspbian'=>'raspberrypi','centos'=>'centos','oracle'=>'oracle','amazon'=>'amazonlinux','kali'=>'kalilinux','manjaro'=>'manjaro','gentoo'=>'gentoo','nixos'=>'nixos','pop'=>'popos','zorin'=>'zorin','elementary'=>'elementary','void'=>'voidlinux','slackware'=>'slackware','fedora'=>'fedora','ubuntu'=>'ubuntu','debian'=>'debian','truenas'=>'truenas']; $d=preg_match('/^[a-z]+/',strtolower((string)$osinfo),$mm)?$mm[0]:''; if($d!=='') $c[]='si:'.($al[$d]??$d); $c[]='si:linux'; } } }
 foreach($c as $x) if(($u=icon_url($x))!=='') return $u; return ''; }

/* ---- enhetsikoner (v1.76) ---- */
function has_col($t,$c){ static $m=[]; $k="$t.$c"; if(!isset($m[$k])){ try{ $m[$k]=(bool)db()->query('SHOW COLUMNS FROM `'.preg_replace('/\W/','',$t).'` LIKE '.db()->quote($c))->fetch(); }catch(Throwable $e){ $m[$k]=false; } } return $m[$k]; }
function chk_ord(){ return has_col('server_checks','sort')?'sort,id':'id'; }
// Ikon för en enhet: egen (servers.icon) först, annars märkesikon från OS/modell. Returnerar [url|'' , egen?]
function dev_icon($s,$inf=[],$model=''){
 $sp=(string)($s['icon']??''); if($sp!==''&&($u=icon_url($sp))!=='') return [$u,true];
 if(!empty($s['is_services'])) return ['',false];
 $u=brand_icon($s['os']??'',$s['os_info']??'',(array)$inf,$s['hv_type']??'',$s['nw_type']??'',(string)$model);
 if($u===''&&($s['nw_role']??'')==='ap') $u=icon_pick(['mdi:access-point']);
 return [$u,false]; }
// Reservikon (inbyggd, samma urval som dashboarden) när ingen märkesikon finns
function dev_icon_fallback($s){ $c=[]; if(!empty($s['is_services'])) $c[]='svc'; elseif(!empty($s['hv_type'])) $c[]='hv-'.$s['hv_type']; elseif(!empty($s['nw_type'])){ $v=$s['nw_type']; $r=$s['nw_role']??''; array_push($c,"nw-$v-$r","nw-$v","nw-$r"); } else { $d=preg_match('/^[a-z]+/',strtolower((string)($s['os_info']??'')),$m)?$m[0]:''; if($d!=='') $c[]='os-'.$d; $c[]='os-'.(($s['os']??'')==='windows'?'windows':'linux'); } $c[]='server';
 foreach($c as $x) if(is_file(__DIR__."/assets/icons/$x.svg")) return "assets/icons/$x.svg"; return ''; }
function dev_icon_html($s,$inf=[],$model=''){ [$u,$cu]=dev_icon($s,$inf,$model); $o='<span class="dic" data-t="server" data-tok="'.h(csrf()).'" data-id="'.(int)$s['id'].'" data-custom="'.($cu?1:0).'">';
 if($u!=='') $o.='<i class="ti ico bi" style="-webkit-mask-image:url('.h($u).');mask-image:url('.h($u).')"></i>'; elseif(($f=dev_icon_fallback($s))!=='') $o.='<img class="ti" src="'.h($f).'" alt="">';
 if(is_admin()) $o.='<button type="button" class="pen" title="'.h(t('Välj egen ikon','Choose your own icon')).'" aria-label="'.h(t('Välj egen ikon','Choose your own icon')).'">✎</button>';
 return $o.'</span>'.dic_script(); }
function dic_script(){ static $d=false; if($d||!is_admin()) return ''; $d=true; return '<script src="assets/devicon.js"></script>'; }
// Standardikon för enhetsgrupper
function group_icon_default(){ return 'std:group'; }
function group_icon_url($g){ $u=icon_url((string)($g['icon']??'')); return $u!==''?$u:icon_url(group_icon_default()); }
function group_icon_html($g){ $u=group_icon_url($g); $o='<span class="dic" data-t="group" data-tok="'.h(csrf()).'" data-id="'.(int)$g['id'].'" data-custom="'.(((string)($g['icon']??''))!==''?1:0).'">'; if($u!=='') $o.='<i class="ti ico bi" style="-webkit-mask-image:url('.h($u).');mask-image:url('.h($u).')"></i>';
 if(is_admin()) $o.='<button type="button" class="pen" title="'.h(t('Välj ikon','Choose icon')).'" aria-label="'.h(t('Välj ikon','Choose icon')).'">✎</button>'; return $o.'</span>'.dic_script(); }
// Greppet som används för att dra en kontrollrad (ändra ordning) – bara för administratörer
function chk_grip(){ return is_admin()?'<span class="grip" title="'.h(t('Dra för att ändra ordning','Drag to reorder')).'" aria-hidden="true">⋮⋮</span>':''; }

// Ordning på kontrollrader inkl. "värd"-raderna (SNMP/API = 'h', TrueNAS API = 'tn'): sort, sedan id. Värdraderna ligger först tills man drar dem.
function chk_merge($l){ $i=0; foreach($l as &$x){ $x['_i']=$i++; } unset($x); usort($l,function($a,$b){ $c=($a['so'][0]??0)<=>($b['so'][0]??0); if($c) return $c; $c=($a['so'][1]??0)<=>($b['so'][1]??0); return $c?:$a['_i']<=>$b['_i']; }); foreach($l as &$x) unset($x['so'],$x['_i']); return $l; }
function chk_sort_rows($html,$cs,$srv){ $sm=[]; foreach($cs as $c) $sm[(string)$c['id']]=[(int)($c['sort']??0),(int)$c['id']]; $sm['h']=[(int)($srv['host_sort']??0),-2]; $sm['tn']=[(int)($srv['tn_sort']??0),-1];
 $parts=preg_split('/(?=<tr[ >])/',$html); $rows=[]; $head=array_shift($parts); foreach($parts as $i=>$p){ $k=preg_match('/data-cid="([^"]+)"/',$p,$m)?$m[1]:null; $rows[]=['h'=>$p,'k'=>$sm[$k]??[0,0],'i'=>$i]; }
 usort($rows,function($a,$b){ $c=$a['k'][0]<=>$b['k'][0]; if($c) return $c; $c=$a['k'][1]<=>$b['k'][1]; return $c?:$a['i']<=>$b['i']; }); return $head.implode('',array_column($rows,'h')); }
