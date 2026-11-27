<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
$pdo=db(); $uid=(int)$_SESSION['u']['id']; $a=$_POST['a']??''; $id=(int)($_POST['id']??0);
function clean_graphs($raw){ $g=json_decode((string)$raw,true); if(!is_array($g)) return null; $o=[];
 foreach(array_slice($g,0,2) as $x){ if(!is_array($x)) continue; $k=$x['k']??''; if(!in_array($k,['cpu','mem','disk','ms','net'],true)) continue;
  $o[]=['k'=>$k,'sid'=>(int)($x['sid']??0),'d'=>mb_substr((string)($x['d']??''),0,190),'c'=>(int)($x['c']??0),'i'=>(int)($x['i']??0),'n'=>mb_substr((string)($x['n']??''),0,120),'r'=>in_array($x['r']??'',['24h','7d','30d','all'],true)?$x['r']:'24h']; }
 return $o?:null; }
try{
 if($a==='save'){ $w=(int)($_POST['w']??1)===2?2:1; $g=clean_graphs($_POST['graphs']??''); $v=$_POST['v']??''; $v=in_array($v,['normal','compact','both'],true)?$v:'';
  if(!$g){ http_response_code(400); echo '{"ok":false}'; exit; }
  $js=json_encode($g,JSON_UNESCAPED_UNICODE);
  if($id){ $pdo->prepare('UPDATE dash_graphs SET w=?,graphs=? WHERE id=? AND user_id=?')->execute([$w,$js,$id,$uid]);
   if($v!==''){ try{ $pdo->prepare('UPDATE dash_graphs SET v=? WHERE id=? AND user_id=?')->execute([$v,$id,$uid]); }catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/migrate_v27.sql saknas?']); exit; } } }
  else { if($v===''){ $v='both'; }
   try{ $pdo->prepare('INSERT INTO dash_graphs(user_id,w,v,graphs,lay) VALUES(?,?,?,?,?)')->execute([$uid,$w,$v,$js,lay_n($_POST['lay']??1)]); }
   catch(Throwable $e){ try{ $pdo->prepare('INSERT INTO dash_graphs(user_id,w,v,graphs) VALUES(?,?,?,?)')->execute([$uid,$w,$v,$js]); }catch(Throwable $e2){ $pdo->prepare('INSERT INTO dash_graphs(user_id,w,graphs) VALUES(?,?,?)')->execute([$uid,$w,$js]); } }
   $id=(int)$pdo->lastInsertId(); }
  if($id&&isset($_POST['cw'],$_POST['ch'])){ try{ $pdo->prepare('UPDATE dash_graphs SET cw=?,ch=? WHERE id=? AND user_id=?')->execute([max(1,min(4,(int)$_POST['cw'])),max(1,min(3,(int)$_POST['ch'])),$id,$uid]); }catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/3.03.sql saknas?']); exit; } }
  echo json_encode(['ok'=>true,'id'=>$id]); exit; }
 if($a==='del'){ $pdo->prepare('DELETE FROM dash_graphs WHERE id=? AND user_id=?')->execute([$id,$uid]);
  $q=$pdo->prepare('SELECT card_order FROM users WHERE id=?'); $q->execute([$uid]); $o=array_filter(explode(',',(string)$q->fetchColumn()),fn($x)=>$x!=='g'.$id);
  $pdo->prepare('UPDATE users SET card_order=? WHERE id=?')->execute([implode(',',$o),$uid]);
  try{ $q=$pdo->prepare('SELECT card_order_compact FROM users WHERE id=?'); $q->execute([$uid]); $o=array_filter(explode(',',(string)$q->fetchColumn()),fn($x)=>$x!==''&&$x!=='g'.$id); $pdo->prepare('UPDATE users SET card_order_compact=? WHERE id=?')->execute([implode(',',$o),$uid]); }catch(Throwable $e){}
  echo '{"ok":true}'; exit; }
 http_response_code(400); echo '{"ok":false}';
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/migrate_v25.sql saknas?']); }
