<?php require '../config.php'; require '../lib.php'; need_login(); track_user(); csrf_check(); header('Content-Type: application/json');
$ids=implode(',',array_filter(explode(',',$_POST['order']??''),fn($x)=>preg_match('/^(g\d+|h\d+|\d+(_\d+)?)$/',$x)===1));
$view=($_POST['view']??'')==='compact'?'compact':'normal'; $lay=lay_n($_POST['lay']??1); $col=$view==='compact'?'card_order_compact':'card_order';
try{ if($lay>1) db()->prepare('INSERT INTO user_prefs(user_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([(int)$_SESSION['u']['id'],'ord:'.$lay.':'.$view,$ids]); else db()->prepare("UPDATE users SET $col=? WHERE id=?")->execute([$ids,$_SESSION['u']['id']]); }
catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/migrate_v26.sql saknas?']); exit; }
// rutnätspositioner (valfritt): {"cols":5,"p":{"12":[1,1],"g3":[2,1]}}
if(isset($_POST['pos'])){ try{ $pk='pos:'.$lay.':'.$view; $in=json_decode((string)$_POST['pos'],true); $clean=null;
  if(is_array($in)&&isset($in['cols'],$in['p'])&&is_array($in['p'])){ $p=[]; foreach($in['p'] as $k=>$v){ if(preg_match('/^(g\d+|h\d+|\d+(_\d+)?)$/',(string)$k)&&is_array($v)&&count($v)===2) $p[(string)$k]=[max(1,min(60,(int)$v[0])),max(1,min(1000,(int)$v[1]))]; } $clean=['cols'=>max(1,min(60,(int)$in['cols'])),'p'=>$p]; if(($in['u']??0)==2) $clean['u']=2; }
  if($lay>1){ if($clean) db()->prepare('INSERT INTO user_prefs(user_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([(int)$_SESSION['u']['id'],$pk,json_encode($clean)]); else db()->prepare('DELETE FROM user_prefs WHERE user_id=? AND k=?')->execute([(int)$_SESSION['u']['id'],$pk]); echo '{"ok":true}'; exit; }
  $q=db()->prepare('SELECT card_pos FROM users WHERE id=?'); $q->execute([$_SESSION['u']['id']]); $all=json_decode((string)$q->fetchColumn(),true); if(!is_array($all)) $all=[];
  if($clean) $all[$view]=$clean; else unset($all[$view]);
  db()->prepare('UPDATE users SET card_pos=? WHERE id=?')->execute([json_encode($all),$_SESSION['u']['id']]);
 }catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>'sql/migrate_v37.sql saknas?']); exit; } }
echo '{"ok":true}';
