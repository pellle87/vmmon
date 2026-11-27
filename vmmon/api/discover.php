<?php require '../config.php'; require '../lib.php'; require '../discover_lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
if($_SERVER['REQUEST_METHOD']!=='POST'||!is_admin()){ http_response_code(403); exit('{"ok":false,"error":"forbidden"}'); }
$a=(string)($_POST['a']??''); $comm=function(){ $c=trim((string)($_POST['comm']??'')); if($c==='********'){ foreach(disc_rows() as $r) if(($r['id']??'')===(string)($_POST['id']??'')) return disc_comm($r); } return $c!==''?$c:(setting('snmp_community','public')?:'public'); };
session_write_close(); @set_time_limit(120);
try{
 if($a==='expand'){ $n=disc_norm_net($_POST['net']??''); if(!$n) exit(json_encode(['ok'=>false,'error'=>t('Ogiltigt nät. Använd formatet 10.10.10.0/24 (minst /16).','Invalid network. Use the format 10.10.10.0/24 (at least /16).')])); $ips=disc_ips($n); echo json_encode(['ok'=>true,'net'=>$n,'ips'=>$ips]); exit; }
 if($a==='scan'){ if(!function_exists('snmp2_get')) exit(json_encode(['ok'=>false,'error'=>t('PHP SNMP-tillägget saknas.','The PHP SNMP extension is missing.')])); $c=$comm(); $ips=array_slice(array_values(array_filter((array)($_POST['ips']??[]),fn($x)=>filter_var($x,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))),0,32);
  $n0=count($ips); if(!empty($_POST['ping'])) $ips=disc_ping_many($ips); $have=disc_known(); $found=[]; foreach($ips as $ip){ $r=disc_scan_ip($ip,$c); if($r){ $r['exists']=isset($have[$ip]); $found[]=$r; } } echo json_encode(['ok'=>true,'found'=>$found,'alive'=>count($ips)]); exit; }
 if($a==='add'){ $items=json_decode((string)($_POST['items']??'[]'),true); if(!is_array($items)) $items=[]; $c=$comm(); $ok=0; $err=[]; foreach(array_slice($items,0,100) as $it){ $e=disc_add((array)$it,$c); if($e===null) $ok++; else $err[]=($it['ip']??'?').': '.$e; } echo json_encode(['ok'=>true,'added'=>$ok,'errors'=>$err]); exit; }
 echo json_encode(['ok'=>false,'error'=>'unknown action']);
}catch(Throwable $e){ applog('error','config','Autodiscover error: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'server error']); }
