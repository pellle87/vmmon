<?php require '../config.php'; require '../lib.php'; need_login(); if(!is_admin()){ http_response_code(403); exit('403'); }
header('Content-Type: text/plain; charset=utf-8');
$q=db()->prepare('SELECT host,nw_conn FROM servers WHERE id=? AND nw_type IS NOT NULL'); $q->execute([(int)($_GET['id']??0)]); $s=$q->fetch(); if(!$s) exit('Not found');
$oid=trim((string)($_GET['oid']??'1.3.6.1.2.1.99.1.1.1')); if(!preg_match('/^\.?\d+(\.\d+)*$/',$oid)) exit('Bad OID');
$cn=json_decode((string)$s['nw_conn'],true)?:[]; $g=snmp_cfg($cn['snmp']??'default'); if(isset($_GET['c'])&&$_GET['c']!==''&&preg_match('/^[\w.\-@!]{1,64}$/',$_GET['c'])) $g=['v'=>2,'c'=>$_GET['c']];
if(!function_exists('snmp2_real_walk')) exit('PHP SNMP missing');
snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true);
$sd=@snmp_gt($s['host'],$g,'1.3.6.1.2.1.1.1.0'); echo '# sysDescr: '.($sd===false?'NO RESPONSE (SNMP av, fel community/v3, eller brandvägg/ACL blockerar monitor-IP)':$sd)."\n"; if($sd===false) exit;
$r=snmp_rw_safe($s['host'],$g,ltrim($oid,'.')); if(!$r) exit('Empty/no response from walk (tried SNMPv2c bulk with small repetitions and SNMPv1 getnext)');
echo "# {$s['host']} walk $oid (".count($r)." rows, max 3000)\n"; $n=0; foreach($r as $k=>$v){ if(++$n>3000){ echo "... truncated\n"; break; } echo $k.' = '.str_replace(["\r","\n"],' ',(string)$v)."\n"; }
