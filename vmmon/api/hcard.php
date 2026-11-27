<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
$pdo=db(); $uid=(int)$_SESSION['u']['id']; $a=$_POST['a']??''; $id=(int)($_POST['id']??0);
try{
 if($a==='save'){ $w=max(1,min(8,(int)($_POST['w']??2))); $c=(string)($_POST['color']??'acc');
  if(!preg_match('/^(acc|ok|warn|bad|off|btn|card|#[0-9a-fA-F]{6})$/',$c)) $c='acc';
  $tc=(string)($_POST['tcol']??'auto'); if(!preg_match('/^(auto|#[0-9a-fA-F]{6})$/',$tc)) $tc='auto';
  $tx=trim(mb_substr((string)($_POST['text']??''),0,120)); if($tx==='') $tx='Headline';
  try{ if($id) $pdo->prepare('UPDATE dash_heads SET w=?,color=?,text=?,tcol=? WHERE id=? AND user_id=?')->execute([$w,$c,$tx,$tc,$id,$uid]);
   else { try{ $pdo->prepare('INSERT INTO dash_heads(user_id,w,color,text,tcol,lay) VALUES(?,?,?,?,?,?)')->execute([$uid,$w,$c,$tx,$tc,lay_n($_POST['lay']??1)]); }catch(Throwable $e0){ $pdo->prepare('INSERT INTO dash_heads(user_id,w,color,text,tcol) VALUES(?,?,?,?,?)')->execute([$uid,$w,$c,$tx,$tc]); } $id=(int)$pdo->lastInsertId(); } }
  catch(Throwable $e){ if($id) $pdo->prepare('UPDATE dash_heads SET w=?,color=?,text=? WHERE id=? AND user_id=?')->execute([$w,$c,$tx,$id,$uid]);
   else { $pdo->prepare('INSERT INTO dash_heads(user_id,w,color,text) VALUES(?,?,?,?)')->execute([$uid,$w,$c,$tx]); $id=(int)$pdo->lastInsertId(); } }
  echo json_encode(['ok'=>true,'id'=>$id]); exit; }
 if($a==='del'){ $pdo->prepare('DELETE FROM dash_heads WHERE id=? AND user_id=?')->execute([$id,$uid]);
  foreach(['card_order','card_order_compact'] as $col){ try{ $q=$pdo->prepare("SELECT $col FROM users WHERE id=?"); $q->execute([$uid]); $o=array_filter(explode(',',(string)$q->fetchColumn()),fn($x)=>$x!==''&&$x!=='h'.$id); $pdo->prepare("UPDATE users SET $col=? WHERE id=?")->execute([implode(',',$o),$uid]); }catch(Throwable $e){} }
  echo '{"ok":true}'; exit; }
 http_response_code(400); echo '{"ok":false}';
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/3.05.sql saknas?']); }
