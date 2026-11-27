<?php
// Kopiera en dashboard-layout (en vy: normal eller compact, layout 1–3) från en användare till en annan layout hos den inloggade.
// Lagring: layout 1 ligger i users.card_order(_compact)/card_pos, layout 2–3 i user_prefs ('ord:L:vy', 'pos:L:vy'); dolda kort i 'hide:[L:]vy';
// rubrikkort i dash_heads och grafkort i dash_graphs (båda har kolumnen lay). Kort-id:n g<id>/h<id> byts mot nya id:n hos mottagaren.
function lay_pref_get($uid,$k){ $q=db()->prepare('SELECT v FROM user_prefs WHERE user_id=? AND k=?'); $q->execute([$uid,$k]); $v=$q->fetchColumn(); return $v===false?null:(string)$v; }
function lay_pref_set($uid,$k,$v){ if($v===null||$v===''){ db()->prepare('DELETE FROM user_prefs WHERE user_id=? AND k=?')->execute([$uid,$k]); return; } db()->prepare('INSERT INTO user_prefs(user_id,k,v) VALUES(?,?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute([$uid,$k,$v]); }
function lay_export($uid,$lay,$view){ $o=['ord'=>'','pos'=>null,'hide'=>null,'heads'=>[],'graphs'=>[]];
 if($lay>1){ $o['ord']=(string)lay_pref_get($uid,'ord:'.$lay.':'.$view); $o['pos']=lay_pref_get($uid,'pos:'.$lay.':'.$view); $o['hide']=lay_pref_get($uid,'hide:'.$lay.':'.$view); }
 else { $col=$view==='compact'?'card_order_compact':'card_order'; $q=db()->prepare("SELECT $col AS o,card_pos FROM users WHERE id=?"); $q->execute([$uid]); $r=$q->fetch(); $o['ord']=(string)($r['o']??''); $all=json_decode((string)($r['card_pos']??''),true); $o['pos']=is_array($all)&&isset($all[$view])?json_encode($all[$view]):null; $o['hide']=lay_pref_get($uid,'hide:'.$view); }
 if($view==='compact'){ $q=db()->prepare('SELECT id,w,color,text,tcol FROM dash_heads WHERE user_id=? AND lay=? ORDER BY id'); $q->execute([$uid,$lay]); $o['heads']=$q->fetchAll(); }
 $q=db()->prepare("SELECT id,w,v,cw,ch,graphs FROM dash_graphs WHERE user_id=? AND lay=? AND v IN (?,'both') ORDER BY id"); $q->execute([$uid,$lay,$view]); $o['graphs']=$q->fetchAll(); return $o; }
function lay_has_content($o){ return $o['ord']!==''||$o['pos']!==null||$o['hide']!==null||$o['heads']||$o['graphs']; }
// Skriver källans layout över mottagarens layout $lay i samma vy. Allt eller inget (transaktion där databasen klarar det).
function lay_import($uid,$lay,$view,$o){ $pdo=db(); $map=[]; $own=!$pdo->inTransaction(); if($own) $pdo->beginTransaction();
 try{
  if($view==='compact') $pdo->prepare('DELETE FROM dash_heads WHERE user_id=? AND lay=?')->execute([$uid,$lay]);
  $other=$view==='compact'?'normal':'compact';
  $pdo->prepare("UPDATE dash_graphs SET v=? WHERE user_id=? AND lay=? AND v='both'")->execute([$other,$uid,$lay]);
  $pdo->prepare('DELETE FROM dash_graphs WHERE user_id=? AND lay=? AND v=?')->execute([$uid,$lay,$view]);
  foreach($o['heads'] as $h){ $pdo->prepare('INSERT INTO dash_heads(user_id,w,color,text,tcol,lay) VALUES(?,?,?,?,?,?)')->execute([$uid,$h['w'],$h['color'],$h['text'],$h['tcol'],$lay]); $map['h'.$h['id']]='h'.$pdo->lastInsertId(); }
  foreach($o['graphs'] as $g){ $pdo->prepare('INSERT INTO dash_graphs(user_id,w,v,cw,ch,graphs,lay) VALUES(?,?,?,?,?,?,?)')->execute([$uid,$g['w'],$view,$g['cw'],$g['ch'],$g['graphs'],$lay]); $map['g'.$g['id']]='g'.$pdo->lastInsertId(); }
  $rm=function($k) use($map){ return preg_match('/^[gh]\d+$/',$k)?($map[$k]??null):$k; };
  $ord=implode(',',array_values(array_filter(array_map($rm,array_filter(explode(',',$o['ord']),'strlen')),fn($x)=>$x!==null)));
  $pos=null; if($o['pos']!==null){ $p=json_decode($o['pos'],true); if(is_array($p)&&isset($p['p'])&&is_array($p['p'])){ $np=[]; foreach($p['p'] as $k=>$v){ $nk=$rm((string)$k); if($nk!==null) $np[$nk]=$v; } $p['p']=$np; $pos=$p; } }
  if($lay>1){ lay_pref_set($uid,'ord:'.$lay.':'.$view,$ord); lay_pref_set($uid,'pos:'.$lay.':'.$view,$pos?json_encode($pos):null); lay_pref_set($uid,'hide:'.$lay.':'.$view,$o['hide']); }
  else { $col=$view==='compact'?'card_order_compact':'card_order'; $q=$pdo->prepare('SELECT card_pos FROM users WHERE id=?'); $q->execute([$uid]); $all=json_decode((string)$q->fetchColumn(),true); if(!is_array($all)) $all=[]; if($pos) $all[$view]=$pos; else unset($all[$view]);
   $pdo->prepare("UPDATE users SET $col=?,card_pos=? WHERE id=?")->execute([$ord,json_encode($all),$uid]); lay_pref_set($uid,'hide:'.$view,$o['hide']); }
  if($own) $pdo->commit();
 }catch(Throwable $e){ if($own&&$pdo->inTransaction()) $pdo->rollBack(); throw $e; } }
