<?php require '../config.php'; require '../lib.php'; need_login(); session_write_close(); header('Content-Type: application/json');
$q=strtolower(trim((string)($_GET['q']??''))); $set=$_GET['set']??'all'; $lim=160; $o=[]; $more=false;
$add=function($spec,$name) use(&$o,$lim,&$more){ if(count($o)>=$lim){ $more=true; return; } $u=icon_url($spec); if($u!=='') $o[]=['s'=>$spec,'n'=>$name,'u'=>$u]; };
$match=fn($n)=>$q===''||strpos($n,$q)!==false;
if(in_array($set,['all','std'],true)) foreach(icon_list_std() as $n) if($match($n)) $add("std:$n",$n);
if(in_array($set,['all','up'],true)) foreach(icon_uploaded() as $r) if($match(strtolower($r['name']))) $add('up:'.$r['id'],$r['name']);
$ix=icon_lib_index(); foreach(['mdi','si'] as $k) if(in_array($set,['all',$k],true)) foreach($ix[$k]??[] as $n) if($match($n)&&($q!==''||$set!=='all')) $add("$k:$n",$n);
echo json_encode(['items'=>$o,'more'=>$more,'lib'=>!empty($ix)],JSON_UNESCAPED_UNICODE);
