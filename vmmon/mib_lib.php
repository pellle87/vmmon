<?php // MIB-filer: läser in tillverkares MIB (SMIv1/SMIv2), så att OID:er får namn och heltalsvärden får text (t.ex. 2 = down).
// Uppladdade filer tolkas till definitioner (tabellen mib_files, JSON). Efter varje ändring löses alla namn upp till numeriska OID:er och sparas i mib_names.
function mib_roots(){ return ['ccitt'=>'0','iso'=>'1','joint-iso-ccitt'=>'2','org'=>'1.3','dod'=>'1.3.6','internet'=>'1.3.6.1','directory'=>'1.3.6.1.1','mgmt'=>'1.3.6.1.2','mib-2'=>'1.3.6.1.2.1','transmission'=>'1.3.6.1.2.1.10','experimental'=>'1.3.6.1.3','private'=>'1.3.6.1.4','enterprises'=>'1.3.6.1.4.1','security'=>'1.3.6.1.5','snmpV2'=>'1.3.6.1.6','snmpDomains'=>'1.3.6.1.6.1','snmpProxys'=>'1.3.6.1.6.2','snmpModules'=>'1.3.6.1.6.3','snmpMIB'=>'1.3.6.1.6.3.1','snmpMIBObjects'=>'1.3.6.1.6.3.1.1','snmpTraps'=>'1.3.6.1.6.3.1.1.5','system'=>'1.3.6.1.2.1.1','interfaces'=>'1.3.6.1.2.1.2','ifMIB'=>'1.3.6.1.2.1.31','ifMIBObjects'=>'1.3.6.1.2.1.31.1','entityMIB'=>'1.3.6.1.2.1.47']; }

// Tolkar en MIB-fil. Returnerar ['module'=>…,'defs'=>[[name,kind,parent,suffix,enums],…],'imports'=>[modulnamn]]
function mib_parse($txt,$fname=''){
 $txt=str_replace("\r",'',$txt); $txt=preg_replace('/"[^"]*"/s','""',$txt); // beskrivningstexter bort (kan innehålla allt möjligt)
 $txt=preg_replace('/--[^\n]*/','',$txt);
 $hdr=preg_match('/^\s*([A-Za-z][A-Za-z0-9-]*)\s+DEFINITIONS\s*(?:[A-Z]+\s+TAGS\s*)?::=\s*BEGIN/m',$txt,$m); $mod=$hdr?$m[1]:preg_replace('/\.[^.]*$/','',basename($fname));
 $imp=[]; if(preg_match_all('/\bFROM\s+([A-Za-z][A-Za-z0-9-]*)/',$txt,$mm)) $imp=array_values(array_unique($mm[1]));
 $kinds='OBJECT IDENTIFIER|OBJECT-TYPE|NOTIFICATION-TYPE|MODULE-IDENTITY|OBJECT-IDENTITY|TRAP-TYPE|NOTIFICATION-GROUP|OBJECT-GROUP|MODULE-COMPLIANCE|AGENT-CAPABILITIES';
 preg_match_all('/^[ \t]*([a-z][A-Za-z0-9-]*)[ \t]+('.$kinds.')\b|^[ \t]*([A-Z][A-Za-z0-9-]*)[ \t]*::=[ \t]*(TEXTUAL-CONVENTION)\b|^[ \t]*([A-Z][A-Za-z0-9-]*)[ \t]*::=[ \t]*(INTEGER|Integer32|BITS)[ \t]*\{/m',$txt,$H,PREG_OFFSET_CAPTURE|PREG_SET_ORDER);
 $defs=[]; $tc=[]; $n=count($H);
 for($i=0;$i<$n;$i++){ $h=$H[$i]; $st=$h[0][1]; $en=$i+1<$n?$H[$i+1][0][1]:strlen($txt); $body=substr($txt,$st,$en-$st);
  if(!empty($h[4][0])){ $e=mib_enums($body); if($e) $tc[$h[3][0]]=$e; continue; }
  if(!empty($h[6][0])&&empty($h[1][0])){ $e=mib_enums($body); if($e) $tc[$h[5][0]]=$e; continue; }
  $name=$h[1][0]; $kind=$h[2][0]; $parent=null; $suffix=''; if(in_array($kind,['NOTIFICATION-GROUP','OBJECT-GROUP','MODULE-COMPLIANCE','AGENT-CAPABILITIES'],true)) continue; // grupper/efterlevnad har inget eget värde att visa
  if($kind==='TRAP-TYPE'){ if(preg_match('/\bENTERPRISE\s+([A-Za-z][A-Za-z0-9-]*)/',$body,$x)&&preg_match('/::=\s*(\d+)/',$body,$y)){ $parent=$x[1]; $suffix='.0.'.$y[1]; } }
  elseif(preg_match_all('/::=\s*\{([^}]*)\}/',$body,$x)){ $a=mib_assign(end($x[1])); if($a){ $parent=$a[0]; $suffix=$a[1]; } }
  if($parent===null) continue;
  $en=null; if($kind==='OBJECT-TYPE'||$kind==='NOTIFICATION-TYPE'){ if(preg_match('/\bSYNTAX\s+(?:INTEGER|Integer32|BITS)?\s*\{([^}]*)\}/s',$body,$x)){ $en=mib_enums('{'.$x[1].'}'); } elseif(preg_match('/\bSYNTAX\s+([A-Z][A-Za-z0-9-]*)/',$body,$x)) $en='tc:'.$x[1]; }
  $defs[]=[$name,$kind,$parent,$suffix,$en]; }
 // textuella konventioner: byt tc:Namn mot uppräkningen (om den finns i samma fil – annars lämnas den till uppslagningen över alla filer)
 foreach($defs as &$d) if(is_string($d[4])&&strpos($d[4],'tc:')===0){ $k=substr($d[4],3); $d[4]=$tc[$k]??$d[4]; } unset($d);
 return ['module'=>$mod,'defs'=>$defs,'imports'=>$imp,'tc'=>$tc,'hdr'=>(bool)$hdr]; }
function mib_enums($s){ $o=[]; if(preg_match('/\{(.*)\}/s',$s,$m)&&preg_match_all('/([A-Za-z][A-Za-z0-9-]*)\s*\(\s*(-?\d+)\s*\)/',$m[1],$x,PREG_SET_ORDER)) foreach($x as $e) $o[$e[2]]=$e[1]; return $o?:null; }
// "{ parent 5 }" / "{ iso org(3) dod(6) 1 }" → [föräldernamn eller 'oid:1.3.6', '.5']
function mib_assign($s){ $t=preg_split('/\s+/',trim($s)); if(!$t||$t[0]==='') return null; $nums=[]; $p=null; $i=0;
 $f=$t[0]; if(preg_match('/^([A-Za-z][A-Za-z0-9-]*)\((\d+)\)$/',$f,$m)){ $nums[]=$m[2]; } elseif(ctype_digit($f)){ $nums[]=$f; } else $p=$f;
 for($i=1;$i<count($t);$i++){ $x=$t[$i]; if(preg_match('/^(?:[A-Za-z][A-Za-z0-9-]*)?\((\d+)\)$/',$x,$m)) $nums[]=$m[1]; elseif(ctype_digit($x)) $nums[]=$x; else return null; }
 if($p===null){ if(!$nums) return null; return ['oid:'.implode('.',$nums),'']; } return [$p,$nums?'.'.implode('.',$nums):'']; }

// Löser upp alla definitioner i alla filer till OID och skriver om mib_names. Returnerar [antal, ej upplösta namn[]]
function mib_rebuild(){ $pdo=db(); $files=$pdo->query('SELECT id,module,defs FROM mib_files ORDER BY id')->fetchAll(); $R=mib_roots(); $all=[]; $tc=[];
 foreach($files as $f){ $j=json_decode((string)$f['defs'],true); if(!is_array($j)) continue; foreach($j['tc']??[] as $k=>$e) $tc[$k]=$e; foreach($j['defs']??[] as $d){ $d[]=$f['module']; $all[]=$d; } }
 $oid=[]; foreach($R as $k=>$v) $oid[$k]=$v; $out=[]; $pend=$all; $guard=0;
 do{ $left=[]; $prog=false; foreach($pend as $d){ [$name,$kind,$parent,$suffix,$en,$module]=$d; $base=strpos($parent,'oid:')===0?substr($parent,4):($oid[$parent]??null);
   if($base===null){ $left[]=$d; continue; } $o=$base.$suffix; $oid[$name]=$o; $prog=true;
   if(is_string($en)&&strpos($en,'tc:')===0) $en=$tc[substr($en,3)]??null;
   $out[$o]=[$o,mb_substr($name,0,120),mb_substr($module,0,100),$kind==='NOTIFICATION-TYPE'||$kind==='TRAP-TYPE'?'n':($kind==='OBJECT-TYPE'?'o':'i'),is_array($en)?json_encode($en):null]; }
  $pend=$left; }while($prog&&$pend&&++$guard<200);
 $pdo->beginTransaction(); try{ $pdo->exec('DELETE FROM mib_names'); $rows=array_values($out); foreach(array_chunk($rows,300) as $ch){ $ph=implode(',',array_fill(0,count($ch),'(?,?,?,?,?)')); $p=[]; foreach($ch as $r) foreach($r as $x) $p[]=$x; $pdo->prepare('INSERT INTO mib_names(oid,name,module,kind,enums) VALUES '.$ph)->execute($p); } $pdo->commit(); }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
 set_setting('mib_ver',(string)time()); $un=[]; foreach($pend as $d) $un[$d[2]]=1; return [count($out),array_slice(array_keys($un),0,15),count($pend)]; }

// Lägger till en fil (text). Returnerar [modul, antal definitioner]
// Moduler utan egna objekt (t.ex. rena typdefinitioner) sparas också, så att importer från dem räknas som uppfyllda.
function mib_add($txt,$fname){ $r=mib_parse($txt,$fname); if(!$r['hdr']&&!$r['defs']&&!$r['tc']) return null; $pdo=db(); $pdo->prepare('DELETE FROM mib_files WHERE module=?')->execute([$r['module']]);
 $pdo->prepare('INSERT INTO mib_files(module,fname,n,defs,uploaded) VALUES(?,?,?,?,?)')->execute([mb_substr($r['module'],0,100),mb_substr(basename($fname),0,200),count($r['defs']),json_encode(['defs'=>$r['defs'],'tc'=>$r['tc'],'imports'=>$r['imports']]),date('Y-m-d H:i:s')]); return [$r['module'],count($r['defs'])]; }

// Uppslagning: närmaste känd förälder till OID:en slås upp i mib_names (primärnyckel), med cache i minnet.
// (Hela tabellen är >100 000 rader; den laddas inte in. Mottagaren är en långlevande process: cachen töms när mib_ver ändras.)
// Grova rotnoder (iso, internet, enterprises …) ger bara "internet.99.99" – då visas hellre den numeriska OID:en.
function mib_find($oid){ static $c=[],$v=null; $nv=(string)setting('mib_ver','0'); if($v!==$nv){ $c=[]; $v=$nv; } if($nv==='0') return null; $o=trim((string)$oid,'.'); if($o===''||!preg_match('/^\d+(\.\d+)*$/',$o)) return null; if(array_key_exists($o,$c)) return $c[$o];
 $pre=[]; $p=$o; for($i=0;$i<40&&$p!=='';$i++){ $pre[]=$p; $q=strrpos($p,'.'); if($q===false) break; $p=substr($p,0,$q); }
 $r=null; try{ $st=db()->prepare('SELECT oid,name,enums FROM mib_names WHERE oid IN ('.implode(',',array_fill(0,count($pre),'?')).')'); $st->execute($pre); $best=null; foreach($st->fetchAll() as $x) if($best===null||strlen($x['oid'])>strlen($best['oid'])) $best=$x; if($best&&($best['oid']===$o||substr_count($best['oid'],'.')>=6)) $r=[$best['name'].substr($o,strlen($best['oid'])),$best['enums']]; }catch(Throwable $e){}
 if(count($c)>5000) $c=[]; return $c[$o]=$r; }
// OID → namn (+ ev. instansdel), eller null
function mib_name($oid){ $r=mib_find($oid); return $r?$r[0]:null; }
function mib_enum_text($oid,$v){ if(!preg_match('/^-?\d+$/',(string)$v)) return null; $r=mib_find($oid); if(!$r||!$r[1]) return null; $e=json_decode($r[1],true); return is_array($e)&&isset($e[(string)$v])?$e[(string)$v].' ('.$v.')':null; }

// Medföljande MIB-filer (mibs/seed.jsonl.gz: färdigtolkade definitioner, en modul per rad). Läses in en gång vid första körningen och via knappen på MIB-sidan.
// Redan uppladdade moduler skrivs inte över. Returnerar antal tillagda moduler, eller null om filen saknas.
function mib_seed(){ @ini_set('memory_limit','768M'); @set_time_limit(300); $f=__DIR__.'/mibs/seed.jsonl.gz'; if(!is_file($f)||!in_array('compress.zlib',stream_get_wrappers(),true)) return null; $pdo=db(); $have=[]; foreach($pdo->query('SELECT module FROM mib_files') as $r) $have[$r['module']]=1;
 $h=@fopen('compress.zlib://'.$f,'rb'); if(!$h) return null; $now=date('Y-m-d H:i:s'); $n=0; $st=$pdo->prepare('INSERT INTO mib_files(module,fname,n,defs,uploaded) VALUES(?,?,?,?,?)'); $pdo->beginTransaction();
 try{ while(($l=fgets($h))!==false){ $j=json_decode($l,true); if(!is_array($j)||isset($have[$j['m']])) continue; $st->execute([mb_substr($j['m'],0,100),mb_substr($j['f'],0,200),count($j['d']),json_encode(['defs'=>$j['d'],'tc'=>$j['t'],'imports'=>$j['i']]),$now]); $n++; } $pdo->commit(); }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); fclose($h); throw $e; } fclose($h);
 if($n) mib_rebuild(); set_setting('mib_seeded','1'); return $n; }
// Första körningen: läs in de medföljande MIB:arna (anropas av bakgrundsprocessen). Fel ignoreras (t.ex. tabellerna saknas ännu).
function mib_seed_once(){ try{ if(setting('mib_seeded','')==='1') return; set_setting('mib_seeded','1'); try{ mib_seed(); }catch(Throwable $e){ set_setting('mib_seeded',''); } }catch(Throwable $e){} }
