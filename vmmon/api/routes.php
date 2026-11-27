<?php require '../config.php'; require '../lib.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$id=(int)($_GET['id']??0); $q=db()->prepare('SELECT * FROM servers WHERE id=? AND nw_type IS NOT NULL'); $q->execute([$id]); $s=$q->fetch();
if(!$s||in_array((string)$s['nw_role'],['nas','san','bmc'],true)){ echo json_encode(['ok'=>false,'error'=>t('Enheten hittades inte','Device not found')]); exit; }
if(!function_exists('snmp2_real_walk')){ echo json_encode(['ok'=>false,'error'=>t('PHP SNMP-tillägget saknas','PHP SNMP extension missing')]); exit; }
$cn=json_decode((string)$s['nw_conn'],true)?:[]; if(($cn['snmp']??'')==='none'){ echo json_encode(['ok'=>false,'error'=>t('SNMP är avstängt för enheten','SNMP is disabled for this device')]); exit; }
@set_time_limit(60); $g=snmp_cfg($cn['snmp']??'default'); snmp_set_valueretrieval(SNMP_VALUE_PLAIN); snmp_set_quick_print(true);
$nm=[]; $nq=db()->prepare('SELECT if_index,name,descr FROM net_if WHERE server_id=?'); $nq->execute([$id]); foreach($nq as $r) $nm[(int)$r['if_index']]=$r['name']?:$r['descr'];
try{ [$rows,$tr]=route_table($s['host'],$g,$nm); }catch(Throwable $e){ echo json_encode(['ok'=>false,'error'=>substr($e->getMessage(),0,200)]); exit; }
if(!$rows){ echo json_encode(['ok'=>false,'error'=>t('Enheten returnerade ingen routingtabell (stöder den IP-FORWARD-MIB, och tillåter communityn den?)','The device returned no routing table (does it support IP-FORWARD-MIB, and does the community allow it?)')]); exit; }
echo json_encode(['ok'=>true,'routes'=>$rows,'n'=>count($rows),'truncated'=>$tr],JSON_INVALID_UTF8_SUBSTITUTE);
