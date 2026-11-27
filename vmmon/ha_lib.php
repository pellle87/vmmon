<?php
// Home Assistant REST API (Bearer-token). Hämtar config, states, services, events och felloggen.
function ha_opts(){ return ['sum'=>t('Statistik (automationer, lampor, brytare)','Statistics (automations, lights, switches)'),'upd'=>t('Uppdateringar som finns tillgängliga','Available updates'),'un'=>t('Otillgängliga entiteter','Unavailable entities'),'att'=>t('Lågt batteri och problemsensorer','Low battery and problem sensors'),'per'=>t('Personer och plats','People and location'),'svc'=>t('Tjänster och händelser','Services and events'),'log'=>t('Felloggen','Error log'),'ent'=>t('Alla entiteter (sökbar tabell)','All entities (searchable table)')]; }
function ha_conn($p){ $p=(string)$p; if($p!==''&&$p[0]==='{'){ $j=json_decode($p,true); if(is_array($j)){ $o=(array)($j['o']??[]); if(empty($j['v'])&&in_array('att',$o,true)) $o[]='un'; return ['tok'=>(string)($j['t']??''),'o'=>array_values(array_intersect(array_keys(ha_opts()),$o))]; } } return ['tok'=>$p,'o'=>array_keys(ha_opts())]; }
function ha_pack($tok,$o){ return json_encode(['v'=>2,'t'=>$tok,'o'=>array_values(array_intersect(array_keys(ha_opts()),(array)$o))]); }
function ha_get($base,$path,$tok,$verify,$to=15,$raw=false){ $c=curl_init($base.$path); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>1,CURLOPT_TIMEOUT=>$to,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok,'Content-Type: application/json'],CURLOPT_SSL_VERIFYPEER=>$verify?1:0,CURLOPT_SSL_VERIFYHOST=>$verify?2:0,CURLOPT_FOLLOWLOCATION=>false]);
 $r=curl_exec($c); $code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE); $e=curl_error($c); curl_close($c); if($r===false) return [null,$e?:'no response'];
 if($code===401) return [null,'HTTP 401 ('.t('ogiltig token','invalid token').')']; if($code<200||$code>=300) return [null,"HTTP $code"]; if($raw) return [$r,null];
 $j=json_decode($r,true); return $j===null?[null,t('Oväntat svar','Unexpected reply')]:[$j,null]; }
function ha_local($iso){ if(!$iso) return ''; try{ $d=new DateTime($iso); $d->setTimezone(new DateTimeZone(date_default_timezone_get())); return $d->format('Y-m-d H:i:s'); }catch(Throwable $e){ return ''; } }
function ha_summarize($cfg,$states,$svc,$ev,$log,$op=null){ $op=$op??array_keys(ha_opts()); $o=['ver'=>(string)($cfg['version']??''),'state'=>(string)($cfg['state']??''),'loc'=>(string)($cfg['location_name']??''),'tz'=>(string)($cfg['time_zone']??''),'url_i'=>(string)($cfg['internal_url']??''),'url_e'=>(string)($cfg['external_url']??''),'comp'=>count($cfg['components']??[]),'safe'=>!empty($cfg['safe_mode'])||!empty($cfg['recovery_mode']),'unit'=>(string)($cfg['unit_system']['temperature']??'')];
 $dom=[]; $ent=[]; $un=[]; $unk=0; $bat=[]; $upd=[]; $prob=[]; $per=[]; $auto=['t'=>0,'on'=>0]; $lt=['t'=>0,'on'=>0]; $sw=['t'=>0,'on'=>0]; $mp=0;
 $pc=['problem','safety','smoke','gas','moisture','tamper','carbon_monoxide','co','heat','cold','lock','vibration','battery'];
 foreach(is_array($states)?$states:[] as $x){ if(!is_array($x)||empty($x['entity_id'])) continue; $id=(string)$x['entity_id']; $d=strstr($id,'.',true)?:$id; $st=(string)($x['state']??''); $at=is_array($x['attributes']??null)?$x['attributes']:[]; $nm=(string)($at['friendly_name']??''); $dom[$d]=($dom[$d]??0)+1;
  $ent[]=['i'=>$id,'s'=>mb_substr($st,0,60),'n'=>mb_substr($nm,0,60),'u'=>mb_substr((string)($at['unit_of_measurement']??''),0,12),'c'=>ha_local($x['last_changed']??'')];
  if($st==='unavailable') $un[]=['i'=>$id,'n'=>$nm?:$id]; elseif($st==='unknown'&&!in_array($d,['button','scene','event','input_button','tag','conversation','stt','tts','ai_task'],true)) $unk++;
  $dc=(string)($at['device_class']??'');
  if($d==='sensor'&&$dc==='battery'&&is_numeric($st)&&(float)$st<=20) $bat[]=['i'=>$id,'n'=>$nm?:$id,'v'=>(float)$st];
  if($d==='binary_sensor'&&$dc==='battery'&&$st==='on') $bat[]=['i'=>$id,'n'=>$nm?:$id,'v'=>null];
  if($d==='binary_sensor'&&$st==='on'&&in_array($dc,['problem','safety','smoke','gas','moisture','tamper','carbon_monoxide','co'],true)) $prob[]=['i'=>$id,'n'=>$nm?:$id,'k'=>$dc];
  if($d==='update'&&$st==='on') $upd[]=['i'=>$id,'n'=>$nm?:$id,'a'=>(string)($at['installed_version']??''),'b'=>(string)($at['latest_version']??'')];
  if($d==='person') $per[]=['n'=>$nm?:$id,'s'=>$st];
  if($d==='automation'){ $auto['t']++; if($st==='on') $auto['on']++; } if($d==='light'){ $lt['t']++; if($st==='on') $lt['on']++; } if($d==='switch'){ $sw['t']++; if($st==='on') $sw['on']++; } if($d==='media_player'&&in_array($st,['playing','on'],true)) $mp++; }
 usort($ent,fn($a,$b)=>strcmp($a['i'],$b['i'])); ksort($dom); usort($bat,fn($a,$b)=>($a['v']??0)<=>($b['v']??0));
 $on=fn($k)=>in_array($k,$op,true); $o['opt']=$op; $o['ent_total']=count($ent); $o['un_n']=count($un);
 $o+=['dom'=>$on('ent')?$dom:[],'ents'=>$on('ent')?array_slice($ent,0,2000):[],'un'=>$on('un')?array_slice($un,0,100):[],'bat'=>$on('att')?array_slice($bat,0,50):[],'prob'=>$on('att')?array_slice($prob,0,50):[],'upd'=>$on('upd')?array_slice($upd,0,50):[],'upd_n'=>count($upd),'per'=>$on('per')?array_slice($per,0,30):[],'auto'=>$auto,'light'=>$lt,'sw'=>$sw,'mp'=>$mp,'unk'=>$unk];
 $sn=0; $sd=[]; foreach(is_array($svc)?$svc:[] as $x){ $n=count((array)($x['services']??[])); $sn+=$n; if(!empty($x['domain'])) $sd[(string)$x['domain']]=$n; } $o['svc_dom']=count($sd); $o['svc_n']=$sn;
 $o['ev_n']=is_array($ev)?count($ev):0; $o['ev_l']=0; foreach(is_array($ev)?$ev:[] as $x) $o['ev_l']+=(int)($x['listener_count']??0);
 $ll=[]; $we=0; $ww=0; if(!$on('log')) $log=null; if(is_string($log)&&$log!==''){ foreach(preg_split('/\r?\n/',$log) as $l){ if(preg_match('/^\S+ \S+ (ERROR|CRITICAL)\b/',$l)) $we++; elseif(preg_match('/^\S+ \S+ WARNING\b/',$l)) $ww++; else continue; $ll[]=mb_substr($l,0,300); } }
 $o['log']=array_slice($ll,-40); $o['log_e']=$we; $o['log_w']=$ww; return $o; }
function ha_check_run($c,$h){ $cn=['port'=>8123,'scheme'=>'http']; if(preg_match('/^(?:(https?)[:\/]*)?(\d+)$/i',trim((string)$c['target']),$m)){ $cn['port']=(int)$m[2]; if($m[1]) $cn['scheme']=strtolower($m[1]); }
 $cc=ha_conn($c['param']); $op=$cc['o']; $tok=secret_dec($cc['tok']); if($tok==='') return [false,t('Token saknas','Token missing')]; $base=$cn['scheme'].'://'.$h.':'.$cn['port']; $v=false;
 [$cfg,$e]=ha_get($base,'/api/config',$tok,$v,10); if($e!==null) return [false,"Home Assistant $h:{$cn['port']} – $e"]; if(!is_array($cfg)) return [false,t('Oväntat svar från /api/config','Unexpected reply from /api/config')];
 $st=$sv=$ev=$lg=null; if(array_intersect(['sum','upd','un','att','per','ent'],$op)) [$st]=ha_get($base,'/api/states',$tok,$v,25); if(in_array('svc',$op,true)){ [$sv]=ha_get($base,'/api/services',$tok,$v,20); [$ev]=ha_get($base,'/api/events',$tok,$v,10); } if(in_array('log',$op,true)) [$lg]=ha_get($base,'/api/error_log',$tok,$v,15,true);
 $d=ha_summarize($cfg,$st,$sv,$ev,$lg,$op); $hs=(bool)array_intersect(['sum','upd','un','att','per','ent'],$op); $ok=strtoupper($d['state'])==='RUNNING'||$d['state']==='';
 $dt=($d['ver']?'v'.$d['ver'].' · ':'').($hs?$d['ent_total'].' '.t('entiteter','entities'):$d['comp'].' '.t('komponenter','components')).(in_array('un',$op,true)&&$d['un_n']?' · '.$d['un_n'].' '.t('otillgängliga','unavailable'):'').(!empty($d['upd_n'])?' · '.$d['upd_n'].' '.t('uppdateringar','updates'):'').(!$ok?' · '.$d['state']:'');
 return [$ok,$dt,null,['ha'=>$d]]; }
