<?php require 'config.php'; require 'lib.php'; need_login(); track_user();
function fmt_dur($s){ if($s===null||$s==='') return '–'; $s=(int)$s; if($s<60) return $s.' s'; if($s<3600) return floor($s/60).' min'; if($s<86400) return floor($s/3600).' h '.floor($s%3600/60).' min'; return floor($s/86400).' d '.floor($s%86400/3600).' h '.floor($s%3600/60).' min'; }
function xl_col($i){ $s=''; $i++; while($i>0){ $m=($i-1)%26; $s=chr(65+$m).$s; $i=intdiv($i-$m-1,26); } return $s; }
function xl_esc($v){ return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$v),ENT_XML1|ENT_QUOTES,'UTF-8'); }
// Minimal .xlsx (inline strings, rubrik i fetstil, frusen översta rad, autofilter). $sheets: [namn=>[header[],rows[][]]]
function xlsx_build($sheets,$path){ $z=new ZipArchive; if($z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) return false; $n=count($sheets);
 $ct='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
 for($i=1;$i<=$n;$i++) $ct.='<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'; $z->addFromString('[Content_Types].xml',$ct.'</Types>');
 $z->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
 $wb='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'; $rl='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'; $i=0;
 foreach($sheets as $name=>[$hd,$rows]){ $i++; $wb.='<sheet name="'.xl_esc(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/',' ',$name),0,31)).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>'; $rl.='<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
  $x='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
  foreach($hd as $c=>$_){ $mx=mb_strlen((string)$hd[$c]); foreach(array_slice($rows,0,200) as $r) $mx=max($mx,mb_strlen((string)($r[$c]??''))); $x.='<col min="'.($c+1).'" max="'.($c+1).'" width="'.min(70,max(10,$mx+2)).'" customWidth="1"/>'; }
  $x.='</cols><sheetData><row r="1">'; foreach($hd as $c=>$v) $x.='<c r="'.xl_col($c).'1" t="inlineStr" s="1"><is><t>'.xl_esc($v).'</t></is></c>'; $x.='</row>'; $ri=1;
  foreach($rows as $r){ $ri++; $x.='<row r="'.$ri.'">'; foreach($r as $c=>$v){ if($v===null||$v==='') continue; $ref=xl_col($c).$ri; if(is_int($v)||is_float($v)) $x.='<c r="'.$ref.'"><v>'.$v.'</v></c>'; else $x.='<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.xl_esc($v).'</t></is></c>'; } $x.='</row>'; }
  $x.='</sheetData>'.($hd?'<autoFilter ref="A1:'.xl_col(count($hd)-1).max(1,$ri).'"/>':'').'</worksheet>'; $z->addFromString('xl/worksheets/sheet'.$i.'.xml',$x); }
 $z->addFromString('xl/workbook.xml',$wb.'</sheets></workbook>'); $z->addFromString('xl/_rels/workbook.xml.rels',$rl.'</Relationships>');
 $z->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
 return $z->close(); }
function csv_build($hd,$rows){ $o=fopen('php://temp','w+'); fwrite($o,"\xEF\xBB\xBF"); fputcsv($o,$hd,';'); foreach($rows as $r) fputcsv($o,array_map(fn($v)=>is_string($v)?str_replace(["\r","\n"],' ',$v):$v,$r),';'); rewind($o); return stream_get_contents($o); }

$pdo=db(); $LIM=200000;
function exp_build($pdo,$LIM,&$rangeTxt){ $pr=$_POST['p']??'30'; $from=trim((string)($_POST['from']??'')); $to=trim((string)($_POST['to']??'')); $sel=array_flip((array)($_POST['sets']??[]));
 $f=null; $t=null; $ok=fn($x)=>preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/',$x)?str_replace('T',' ',$x).':00':null;
 if($ok($from)) $f=$ok($from); if($ok($to)) $t=substr($ok($to),0,16).':59';
 if(!$f&&!$t&&$pr!=='all'){ $d=in_array($pr,['1','7','30','90','180','365'],true)?(int)$pr:30; $f=date('Y-m-d H:i:s',time()-$d*86400); }
 $cond=function($col) use($f,$t,&$p){ $w=[]; $p=[]; if($f){ $w[]="$col>=?"; $p[]=$f; } if($t){ $w[]="$col<=?"; $p[]=$t; } return $w?' WHERE '.implode(' AND ',$w):''; };
 $evn=['alarm'=>t('Larm','Alert'),'repeat'=>t('Påminnelse','Reminder'),'ok'=>t('Återställt','Recovered')]; $sheets=[]; $rangeTxt=($f?:t('början','beginning')).' – '.($t?:t('nu','now'));
 $q=function($sql,$params) use($pdo){ try{ $s=$pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); }catch(Throwable $e){ return null; } };
 if(isset($sel['sum'])){
  $w=$cond('ts'); $a=$q("SELECT server_name,metric,SUM(event='alarm') al,SUM(event='repeat') rp,SUM(event='ok') ok_,MIN(ts) fi,MAX(ts) la FROM alert_log$w GROUP BY server_name,metric ORDER BY al DESC,server_name",$p)?:[]; $rows=[]; foreach($a as $r) $rows[]=[$r['server_name'],$r['metric'],(int)$r['al'],(int)$r['rp'],(int)$r['ok_'],$r['fi'],$r['la']];
  $sheets[t('Sammanfattning larm','Alert summary')]=[[t('Enhet','Unit'),t('Regel','Rule'),t('Larm','Alerts'),t('Påminnelser','Reminders'),t('Återställningar','Recoveries'),t('Första','First'),t('Senaste','Last')],$rows];
  $w=$cond('started'); $e=$q("SELECT server_name,kind,sev,COUNT(*) n,SUM(ended IS NULL) open_,SUM(COALESCE(dur,TIMESTAMPDIFF(SECOND,started,NOW()))) tot,MAX(COALESCE(dur,TIMESTAMPDIFF(SECOND,started,NOW()))) mx,SUM(notified) nt FROM alert_events$w GROUP BY server_name,kind,sev ORDER BY tot DESC",$p); $rows=[];
  foreach($e??[] as $r){ $n=(int)$r['n']; $rows[]=[$r['server_name'],$r['kind'],$r['sev'],$n,(int)$r['open_'],(int)$r['tot'],fmt_dur($r['tot']),$n?(int)round($r['tot']/$n):0,(int)$r['mx'],fmt_dur($r['mx']),(int)$r['nt'],$n-(int)$r['nt']]; }
  $sheets[t('Sammanfattning events','Event summary')]=[[t('Enhet','Unit'),t('Typ','Type'),t('Nivå','Level'),t('Antal','Count'),t('Pågående','Ongoing'),t('Total tid (s)','Total time (s)'),t('Total tid','Total time'),t('Snitt (s)','Average (s)'),t('Längsta (s)','Longest (s)'),t('Längsta','Longest'),t('Notis skickad','Notified'),t('Ingen notis','Not notified')],$rows]; }
 if(isset($sel['log'])){ $w=$cond('ts'); $a=$q("SELECT * FROM alert_log$w ORDER BY id DESC LIMIT $LIM",$p)?:[]; $rows=[]; foreach($a as $r) $rows[]=[$r['ts'],$r['server_name'],$r['metric'],$evn[$r['event']]??$r['event'],$r['message'],$r['mail_result']];
  $sheets[t('Larm-logg','Alert log')]=[[t('Tid','Time'),t('Enhet','Unit'),t('Regel','Rule'),t('Händelse','Event'),t('Detaljer','Details'),t('Leverans','Delivery')],$rows]; }
 if(isset($sel['evt'])){ $w=$cond('started'); $a=$q("SELECT *,TIMESTAMPDIFF(SECOND,started,NOW()) age FROM alert_events$w ORDER BY id DESC LIMIT $LIM",$p)?:[]; $rows=[]; foreach($a as $r){ $d=$r['ended']?(int)$r['dur']:(int)$r['age']; $rows[]=[$r['started'],$r['ended']?:t('pågår','ongoing'),$d,fmt_dur($d),$r['server_name'],$r['kind'],$r['sev'],$r['detail'],$r['notified']?t('Ja','Yes'):t('Nej','No'),$r['reason']]; }
  $sheets[t('Alla events','All events')]=[[t('Start','Start'),t('Slut','End'),t('Varaktighet (s)','Duration (s)'),t('Varaktighet','Duration'),t('Enhet','Unit'),t('Typ','Type'),t('Nivå','Level'),t('Detaljer','Details'),t('Notis skickad','Notified'),t('Notis / orsak','Notification / reason')],$rows]; }
 foreach(['svc'=>[1,t('Tjänst-events','Service events')],'srv'=>[0,t('Server-check-events','Server check events')]] as $k=>[$sv,$nm]) if(isset($sel[$k])){ $w=$cond('ts'); $w.=($w?' AND':' WHERE').' svc='.$sv; $a=$q("SELECT * FROM check_events$w ORDER BY id DESC LIMIT $LIM",$p)?:[]; $rows=[];
  foreach($a as $r) $rows[]=[$r['ts'],$r['server_name'],$r['label'],$r['target'],$r['status']==='up'?t('Uppe igen','Back up'):t('Nere','Down'),$r['detail'],$r['dur']!==null?(int)$r['dur']:null,$r['dur']!==null?fmt_dur($r['dur']):''];
  $sheets[$nm]=[[t('Tid','Time'),t('Enhet','Unit'),'Check',t('Mål','Target'),t('Händelse','Event'),t('Detaljer','Details'),t('Nere i (s)','Down for (s)'),t('Nere i','Down for')],$rows]; }
 if(!$sheets){ $sheets[t('Tomt','Empty')]=[[t('Inget valt','Nothing selected')],[]]; }
 return $sheets; }
$prev=null; $act=$_POST['a']??'export';
if($_SERVER['REQUEST_METHOD']==='POST'){ csrf_check(); set_time_limit(300); $fmt=($_POST['fmt']??'xlsx')==='csv'?'csv':'xlsx'; $sheets=exp_build($pdo,$LIM,$rangeTxt);
 if($act==='preview'){ $prev=$sheets; } else {
 $base='vmmon-export-'.date('Ymd-His'); $info=[t('Period: ','Period: ').$rangeTxt];
 if($fmt==='xlsx'&&class_exists('ZipArchive')){ $tmp=tempnam(sys_get_temp_dir(),'vmx'); if(xlsx_build($sheets,$tmp)){ header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="'.$base.'.xlsx"'); header('Content-Length: '.filesize($tmp)); readfile($tmp); unlink($tmp); exit; } @unlink($tmp); }
 // CSV-fallback (Excel öppnar semikolonseparerad UTF-8): flera ark = en zip om möjligt, annars första arket
 if(count($sheets)>1&&class_exists('ZipArchive')){ $tmp=tempnam(sys_get_temp_dir(),'vmc'); $z=new ZipArchive; $z->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE); $i=0; foreach($sheets as $nm=>[$hd,$rows]){ $i++; $z->addFromString($i.'-'.preg_replace('/[^A-Za-z0-9åäöÅÄÖ_-]+/u','_',$nm).'.csv',csv_build($hd,$rows)); } $z->close(); header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.$base.'-csv.zip"'); readfile($tmp); unlink($tmp); exit; }
 [$hd,$rows]=reset($sheets); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'.$base.'.csv"'); echo csv_build($hd,$rows); exit;  }
}
top(t('Historik – export','History – export')); ?>
<h2><?= t('Historik','History') ?></h2>
<div class="rngn"><?php foreach(['alerts'=>t('Larm','Alerts'),'svc'=>t('Tjänster','Services'),'srv'=>t('Server-checks','Server checks'),'evt'=>t('Alla events','All events')] as $k=>$n) echo '<a class="btn sm" href="history.php?v='.$k.'">'.h($n).'</a>'; ?><a class="btn sm on" href="history_export.php"><?= t('Export','Export') ?></a></div>
<form method="post" class="card hf" style="display:block;max-width:640px"><?php $P=fn($k,$d='')=>$_POST[$k]??$d; $sets=(array)($_POST['sets']??($_SERVER['REQUEST_METHOD']==='POST'?[]:['sum','log','evt','svc','srv'])); ?>
 <input type="hidden" name="t" value="<?= h(csrf()) ?>">
 <p class="mut"><?= t('Exportera all statistik om larm, recover och events till Excel. Välj hur långt tillbaka och vilka delar som ska med.','Export all statistics on alerts, recoveries and events to Excel. Choose how far back and what to include.') ?></p>
 <label class="hl"><span><?= t('Hur långt tillbaka','How far back') ?></span><select name="p"><?php foreach(['1'=>t('Senaste dygnet','Last 24 hours'),'7'=>t('7 dagar','7 days'),'30'=>t('30 dagar','30 days'),'90'=>t('90 dagar','90 days'),'180'=>t('180 dagar','180 days'),'365'=>t('1 år','1 year'),'all'=>t('Allt som finns sparat','Everything stored')] as $k=>$n) echo '<option value="'.$k.'"'.($k===$P('p','30')?' selected':'').'>'.h($n).'</option>'; ?></select></label>
 <p class="mut" style="margin:10px 0 4px"><?= t('…eller eget tidsspann (fyll i Från/Till så gäller det istället):','…or a custom range (if filled in, it is used instead):') ?></p>
 <div style="display:flex;gap:10px;flex-wrap:wrap"><label class="hl"><span><?= t('Från','From') ?></span><input type="datetime-local" name="from" value="<?= h($P('from')) ?>"></label><label class="hl"><span><?= t('Till','To') ?></span><input type="datetime-local" name="to" value="<?= h($P('to')) ?>"></label></div>
 <p class="mut" style="margin:14px 0 4px"><?= t('Innehåll','Contents') ?></p>
 <div style="display:grid;gap:6px">
 <label><input type="checkbox" name="sets[]" value="sum"<?= in_array('sum',$sets,true)?' checked':'' ?>> <?= t('Sammanfattning/statistik (antal larm, återställningar, total och längsta tid per enhet)','Summary/statistics (alerts, recoveries, total and longest time per unit)') ?></label>
 <label><input type="checkbox" name="sets[]" value="log"<?= in_array('log',$sets,true)?' checked':'' ?>> <?= t('Larm-logg (larm, påminnelser, återställt, leverans)','Alert log (alerts, reminders, recovered, delivery)') ?></label>
 <label><input type="checkbox" name="sets[]" value="evt"<?= in_array('evt',$sets,true)?' checked':'' ?>> <?= t('Alla warning/critical-events med orsak till notis','All warning/critical events with notification reason') ?></label>
 <label><input type="checkbox" name="sets[]" value="svc"<?= in_array('svc',$sets,true)?' checked':'' ?>> <?= t('Tjänst-events (nere/uppe)','Service events (down/up)') ?></label>
 <label><input type="checkbox" name="sets[]" value="srv"<?= in_array('srv',$sets,true)?' checked':'' ?>> <?= t('Server-check-events','Server check events') ?></label></div>
 <label class="hl" style="margin-top:12px"><span><?= t('Format','Format') ?></span><select name="fmt"><option value="xlsx">Excel (.xlsx)</option><option value="csv"<?= $P('fmt')==='csv'?' selected':'' ?>>CSV</option></select></label>
 <div class="hbt" style="margin-top:14px"><button class="btn" type="submit" name="a" value="preview"><?= t('Förhandsgranska','Preview') ?></button><button class="btn go" type="submit" name="a" value="export"><?= t('Exportera','Export') ?></button></div>
</form>
<?php if($prev!==null): ?>
<h3 style="margin:18px 0 6px"><?= t('Förhandsgranskning','Preview') ?></h3><p class="mut"><?= t('Så här blir innehållet i filen (visar de 15 första raderna per blad).','This is what the file will contain (first 15 rows per sheet shown).') ?></p>
<?php foreach($prev as $nm=>[$hd,$rows]): $n=count($rows); ?>
<div class="card" style="margin-bottom:12px"><b><?= h($nm) ?></b> <span class="mut">– <?= sprintf(t('%d rader','%d rows'),$n) ?><?= $n>=$LIM?' '.t('(begränsat)','(capped)'):'' ?></span>
<div class="tw" style="margin-top:8px"><table class="card"><tr><?php foreach($hd as $x) echo '<th>'.h($x).'</th>'; ?></tr>
<?php foreach(array_slice($rows,0,15) as $r){ echo '<tr>'; foreach($hd as $i=>$_) echo '<td class="det">'.h(mb_strimwidth((string)($r[$i]??''),0,120,'…')).'</td>'; echo '</tr>'; } if(!$rows) echo '<tr><td class="mut" colspan="'.count($hd).'">'.t('Inga rader i valt spann.','No rows in the selected range.').'</td></tr>'; ?></table></div></div>
<?php endforeach; endif; ?>
<?php bottom();
