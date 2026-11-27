<?php // Avkodning av SNMP-traps (v1, v2c, v3) och syslog (RFC 3164/5424, Meraki). Används av recv.php (tjänsten vmmon-recv).
// Funktionssamling för mottagaren; partitionsfunktionerna längst ner använder databasen.

// ---- BER / ASN.1 -------------------------------------------------------------------------------
// Läser ett TLV från $s vid offset $o. Returnerar [tag, innehåll, innehållets startposition] och flyttar $o, eller null vid fel.
function ber_tlv($s,&$o){ $n=strlen($s); if($o+2>$n) return null; $tag=ord($s[$o]); $l=ord($s[$o+1]); $p=$o+2;
 if($l&0x80){ $k=$l&0x7f; if($k<1||$k>4||$p+$k>$n) return null; $l=0; for($i=0;$i<$k;$i++) $l=$l*256+ord($s[$p+$i]); $p+=$k; }
 if($p+$l>$n) return null; $c=substr($s,$p,$l); $o=$p+$l; return [$tag,$c,$p]; }
function ber_items($c){ $o=0; $a=[]; $n=strlen($c); while($o<$n){ $t=ber_tlv($c,$o); if(!$t) return null; $a[]=$t; } return $a; }
function ber_int($c){ $n=strlen($c); if($n===0) return 0; $v=0; for($i=0;$i<$n;$i++) $v=$v*256+ord($c[$i]); if(ord($c[0])&0x80&&$n<8) $v-=pow(2,8*$n); return (int)$v; }
function ber_uint($c){ $v=0; for($i=0,$n=strlen($c);$i<$n;$i++) $v=$v*256+ord($c[$i]); return $v>=9.2e18?sprintf('%.0f',$v):(string)(int)$v; }
function ber_oid($c){ if($c==='') return ''; $a=[]; $b0=ord($c[0]); $a[]=intdiv($b0,40); $a[]=$b0%40; $v=0; for($i=1,$n=strlen($c);$i<$n;$i++){ $b=ord($c[$i]); $v=$v*128+($b&0x7f); if(!($b&0x80)){ $a[]=$v; $v=0; } } return implode('.',$a); }
function ber_len($l){ if($l<128) return chr($l); $s=''; while($l>0){ $s=chr($l&255).$s; $l>>=8; } return chr(0x80|strlen($s)).$s; }
function ber_enc($tag,$c){ return chr($tag).ber_len(strlen($c)).$c; }
function ber_enc_int($v){ $s=''; $v=(int)$v; do{ $s=chr($v&255).$s; $v>>=8; }while($v>0); if(ord($s[0])&0x80) $s="\0".$s; return ber_enc(2,$s); }

// ---- Namn och värden -----------------------------------------------------------------------------
function snmp_names(){ static $n=null; if($n===null) $n=[
 '1.3.6.1.2.1.1.1'=>'sysDescr','1.3.6.1.2.1.1.3'=>'sysUpTime','1.3.6.1.2.1.1.5'=>'sysName','1.3.6.1.2.1.1.6'=>'sysLocation','1.3.6.1.6.3.1.1.4.1'=>'snmpTrapOID','1.3.6.1.6.3.1.1.4.3'=>'snmpTrapEnterprise',
 '1.3.6.1.2.1.2.2.1.1'=>'ifIndex','1.3.6.1.2.1.2.2.1.2'=>'ifDescr','1.3.6.1.2.1.2.2.1.3'=>'ifType','1.3.6.1.2.1.2.2.1.7'=>'ifAdminStatus','1.3.6.1.2.1.2.2.1.8'=>'ifOperStatus','1.3.6.1.2.1.31.1.1.1.1'=>'ifName','1.3.6.1.2.1.31.1.1.1.18'=>'ifAlias',
 '1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.0.1'=>'hpSwitchStartupConfigSource','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.0.2'=>'hpSwitchStartupConfigSourceIPAddrType','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.0.3'=>'hpSwitchStartupConfigSourceIPAddr','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.0.4'=>'hpSwitchStartupConfigSourceUsername','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.0.5'=>'hpSwitchStartupConfigThrottled','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.9'=>'hpSwitchStartupConfigSeqNum','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.3'=>'hpSwitchRunningCfgChgLatestDateAndTime','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.4'=>'hpSwitchRunningCfgChgCount','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.5'=>'hpSwitchRunningCfgChgEntriesBumped','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.6.1.2'=>'hpSwitchRunningCfgChgEventId','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.6.1.3'=>'hpSwitchRunningCfgChgEventMethod','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.6.1.4'=>'hpSwitchRunningCfgChgEventSourceIPAddrType','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.6.1.5'=>'hpSwitchRunningCfgChgEventSourceIPAddr','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.6.1.6'=>'hpSwitchRunningCfgChgEventUsername','1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.12.6.1.7'=>'hpSwitchRunningCfgChgEventDateAndTime']; return $n; }
function snmp_traps(){ static $n=null; if($n===null) $n=[ // OID => [namn, syslog-nivå]
 '1.3.6.1.6.3.1.1.5.1'=>['coldStart',5],'1.3.6.1.6.3.1.1.5.2'=>['warmStart',5],'1.3.6.1.6.3.1.1.5.3'=>['linkDown',4],'1.3.6.1.6.3.1.1.5.4'=>['linkUp',6],'1.3.6.1.6.3.1.1.5.5'=>['authenticationFailure',4],'1.3.6.1.6.3.1.1.5.6'=>['egpNeighborLoss',4],
 '1.3.6.1.2.1.15.0.1'=>['bgpEstablished',6],'1.3.6.1.2.1.15.0.2'=>['bgpBackwardTransition',4],'1.3.6.1.2.1.47.2.0.1'=>['entConfigChange',5],'1.3.6.1.2.1.14.16.2.1'=>['ospfIfStateChange',5],'1.3.6.1.2.1.14.16.2.2'=>['ospfNbrStateChange',5],
 '1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.0.6'=>['hpSwitchStartupConfigChange',5],'1.3.6.1.4.1.11.2.14.11.5.1.7.1.29.1.0.7'=>['hpSwitchRunningConfigChange',5]]; return $n; }
function oid_name($oid){ $N=snmp_names(); if(isset($N[$oid])) return $oid==='1.3.6.1.2.1.1.3'?'sysUpTime':$N[$oid]; if(function_exists('mib_name')&&($mn=mib_name($oid))!==null) return $mn; foreach($N as $p=>$nm){ if(strpos($oid,$p.'.')===0) return $nm.substr($oid,strlen($p)); } return $oid; }
function oid_label($oid){ foreach([['1.3.6.1.2.1.1.3.0','sysUpTime.0'],['1.3.6.1.6.3.1.1.4.1.0','snmpTrapOID.0'],['1.3.6.1.6.3.1.1.4.3.0','snmpTrapEnterprise.0']] as [$o,$n]) if($oid===$o) return $n; return oid_name($oid); }
function fmt_ticks($t){ $t=(int)$t; $s=intdiv($t,100); return sprintf('%dd %02d:%02d:%02d',intdiv($s,86400),intdiv($s%86400,3600),intdiv($s%3600,60),$s%60); }
function snmp_text($c){ if($c==='') return ''; if(preg_match('/^[\x09\x0a\x0d\x20-\x7e]*$/',$c)||(preg_match('//u',$c)&&!preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$c))) return $c;
 if(strlen($c)===6) return implode(':',str_split(bin2hex($c),2)); return '0x'.bin2hex($c); }
function snmp_val($tag,$c){ switch($tag){ case 2: return (string)ber_int($c); case 4: return snmp_text($c); case 5: return ''; case 6: return ber_oid($c);
 case 0x40: return strlen($c)===4?inet_ntop($c):'0x'.bin2hex($c); case 0x41: case 0x42: case 0x46: return ber_uint($c); case 0x43: return fmt_ticks(ber_uint($c)); case 0x44: return '0x'.bin2hex($c);
 case 0x80: return 'noSuchObject'; case 0x81: return 'noSuchInstance'; case 0x82: return 'endOfMibView'; } return '0x'.bin2hex($c); }
function trap_value_nice($name,$v,$oid=null){ if($oid!==null&&function_exists('mib_enum_text')&&($mt=mib_enum_text($oid,$v))!==null) return $mt; if(preg_match('/AddrType$/',$name)&&(int)$v===1&&!preg_match('/\D/',(string)$v)) return 'ipv4 (1)'; if(preg_match('/ConfigThrottled$/',$name)&&in_array((string)$v,['1','2'],true)) return $v==='1'?'true (1)':'false (2)';
  if(preg_match('/^(ifOperStatus|ifAdminStatus)/',$name)){ $m=[1=>'up',2=>'down',3=>'testing',4=>'unknown',5=>'dormant',6=>'notPresent',7=>'lowerLayerDown']; return ($m[(int)$v]??$v).' ('.$v.')'; } if(is_string($v)&&preg_match('/^0x([0-9a-f]+)$/',$v,$m)){ $b=hex2bin($m[1]); $n=strlen($b); // oformaterade oktettsträngar: gissa IPv4 (4 byte) och DateAndTime (8/11 byte)
  if($n===4) return $v.' ('.implode('.',array_map('ord',str_split($b))).')';
  if($n===8||$n===11){ $y=ord($b[0])*256+ord($b[1]); $mo=ord($b[2]); $d=ord($b[3]); if($y>=2000&&$y<=2100&&$mo>=1&&$mo<=12&&$d>=1&&$d<=31) return $v.sprintf(' (%04d-%02d-%02d %02d:%02d:%02d)',$y,$mo,$d,ord($b[4]),ord($b[5]),ord($b[6])); } }
 return $v; }

// Trap-text för visning: rubrik + en rad per varbind. OID:er slås upp i inbyggda namn och uppladdade MIB vid visning, så även äldre rader blir läsbara.
function trap_msg_html($msg){ $msg=(string)$msg; if(!preg_match('/^(\S+?)(?: \(([\d.]+)\))?: (.*)$/s',$msg,$m)) return h($msg);
 $to=$m[2]!==''?$m[2]:(preg_match('/^\d+(\.\d+)+$/',$m[1])?$m[1]:''); $T=snmp_traps(); $nm=$m[1]; if($to!==''&&preg_match('/^\d+(\.\d+)+$/',$nm)) $nm=$T[$to][0]??oid_name($to);
 $o='<b>'.h($nm).'</b>'.($to!==''&&$nm!==$to?' <small class="mut">'.h($to).'</small>':'');
 foreach(preg_split('/, (?=(?:\d+(?:\.\d+)+|[A-Za-z][\w.-]*)=)/',$m[3]) as $vb){ $p=explode('=',$vb,2); if(count($p)<2){ $o.='<div>'.h($vb).'</div>'; continue; } [$k,$v]=$p; $oid=null; $kn=$k;
  if(preg_match('/^\d+(\.\d+)+$/',$k)){ $oid=$k; $kn=oid_name($k); } if(preg_match('/^-?\d+$|^0x[0-9a-f]+$/',$v)) $v=trap_value_nice($kn,$v,$oid);
  $o.='<div class="tv"><span class="mut">'.h($kn).'</span> '.h($v).'</div>'; }
 return $o; }

// Gör ett trap-resultat av en lista [[oid,värde],…]
function trap_build($ver,$vbs,$extra=[]){ $oid=$extra['oid']??''; $ent=''; $rest=[];
 foreach($vbs as [$o,$v]){ if($o==='1.3.6.1.6.3.1.1.4.1.0'){ $oid=$v; continue; } if($o==='1.3.6.1.2.1.1.3.0') continue; if($o==='1.3.6.1.6.3.1.1.4.3.0'){ $ent=$v; continue; } $rest[]=[$o,$v]; }
 $T=snmp_traps(); $nm=$T[$oid][0]??''; $sev=$T[$oid][1]??5; if($nm==='') $nm=$oid!==''?oid_name($oid):'trap';
 $p=[]; foreach($rest as [$o,$v]){ $n=oid_label($o); $p[]=$n.'='.trap_value_nice($n,$v,$o); }
 $msg=$nm.($oid!==''&&$nm!==$oid?' ('.$oid.')':'').($p?': '.implode(', ',$p):''); if($ent!==''&&$oid==='') $msg.=' enterprise='.$ent;
 return ['kind'=>'trap','ver'=>$ver,'oid'=>$oid,'name'=>$nm,'sev'=>$sev,'msg'=>mb_substr($msg,0,3900),'agent'=>$extra['agent']??null,'comm'=>$extra['comm']??'','host'=>null,'prog'=>'SNMP '.$ver,'ack'=>$extra['ack']??null]; }

// Varbind-lista ur en SEQUENCE OF SEQUENCE
function vb_list($c){ $o=[]; $it=ber_items($c); if($it===null) return null; foreach($it as $x){ if($x[0]!==0x30) return null; $p=ber_items($x[1]); if(!$p||count($p)<2||$p[0][0]!==6) return null; $o[]=[ber_oid($p[0][1]),snmp_val($p[1][0],$p[1][1])]; } return $o; }

// ---- SNMP v1/v2c/v3 --------------------------------------------------------------------------------
// $cfg: ['comm'=>[tillåtna community, tom = alla], 'v3'=>[['u'=>,'ap'=>sha1|md5|sha256…,'aw'=>,'pp'=>aes|des|'','pw'=>],…]]
// Returnerar ['ok'=>true,…trap…] eller ['ok'=>false,'err'=>text,'ver'=>…,'user'=>…]
function snmp_parse($d,$cfg){ $o=0; $top=ber_tlv($d,$o); if(!$top||$top[0]!==0x30) return ['ok'=>false,'err'=>'not SNMP']; $it=ber_items($top[1]); if(!$it||$it[0][0]!==2) return ['ok'=>false,'err'=>'not SNMP'];
 $ver=ber_int($it[0][1]);
 if($ver===0||$ver===1){ if(count($it)<3||$it[1][0]!==4) return ['ok'=>false,'err'=>'bad message']; $comm=$it[1][1]; $v=$ver===0?'v1':'v2c'; $pdu=$it[2];
  if(!empty($cfg['comm'])&&!in_array($comm,$cfg['comm'],true)) return ['ok'=>false,'err'=>'community not accepted','ver'=>$v,'user'=>$comm,'bad'=>'comm'];
  $r=snmp_pdu($pdu,$v,$comm); if(!$r['ok']) return $r;
  if($pdu[0]===0xA6) $r['ack']=ber_enc(0x30,ber_enc_int($ver).ber_enc(4,$comm).ber_enc(0xA2,$pdu[1])); return $r; }
 if($ver===3) return snmp_v3($d,$it,$cfg,$top[2]);
 return ['ok'=>false,'err'=>'unsupported SNMP version '.$ver]; }
function snmp_pdu($pdu,$v,$comm){ $tag=$pdu[0];
 if($tag===0xA4){ $p=ber_items($pdu[1]); if(!$p||count($p)<6) return ['ok'=>false,'err'=>'bad v1 trap','ver'=>$v]; $ent=ber_oid($p[0][1]); $ag=strlen($p[1][1])===4?inet_ntop($p[1][1]):null; $gen=ber_int($p[2][1]); $spec=ber_int($p[3][1]); $vb=vb_list($p[5][1]); if($vb===null) return ['ok'=>false,'err'=>'bad varbinds','ver'=>$v];
  $std=[0=>'1.3.6.1.6.3.1.1.5.1',1=>'1.3.6.1.6.3.1.1.5.2',2=>'1.3.6.1.6.3.1.1.5.3',3=>'1.3.6.1.6.3.1.1.5.4',4=>'1.3.6.1.6.3.1.1.5.5',5=>'1.3.6.1.6.3.1.1.5.6']; $oid=$gen<6?$std[$gen]:$ent.'.0.'.$spec;
  $r=trap_build($v,$vb,['oid'=>$oid,'agent'=>$ag,'comm'=>$comm]); if($gen>=6){ $r['sev']=5; } $r['ok']=true; return $r; }
 if($tag===0xA7||$tag===0xA6){ $p=ber_items($pdu[1]); if(!$p||count($p)<4) return ['ok'=>false,'err'=>'bad trap PDU','ver'=>$v]; $vb=vb_list($p[3][1]); if($vb===null) return ['ok'=>false,'err'=>'bad varbinds','ver'=>$v];
  $r=trap_build($v,$vb,['comm'=>$comm]); if($tag===0xA6) $r['prog']='SNMP '.$v.' inform'; $r['ok']=true; return $r; }
 return ['ok'=>false,'err'=>'ignored PDU type 0x'.dechex($tag),'ver'=>$v,'ignore'=>true]; }

function usm_algo($n){ $n=strtolower((string)$n); return ['md5'=>['md5',12],'sha'=>['sha1',12],'sha1'=>['sha1',12],'sha224'=>['sha224',16],'sha256'=>['sha256',24],'sha384'=>['sha384',32],'sha512'=>['sha512',48]][$n]??null; }
// RFC 3414: lösenord -> Ku (1 MiB) -> lokaliserad nyckel för motorn
function usm_key($pw,$eng,$algo){ static $c=[]; $k=$algo.'|'.bin2hex($eng).'|'.md5($pw); if(isset($c[$k])) return $c[$k]; $l=strlen($pw); if($l<1) return ''; $buf=substr(str_repeat($pw,intdiv(1048576,$l)+1),0,1048576); $ku=hash($algo,$buf,true); return $c[$k]=hash($algo,$ku.$eng.$ku,true); }
function snmp_v3($d,$it,$cfg,$base=0){ if(count($it)<4) return ['ok'=>false,'err'=>'bad v3 message','ver'=>'v3'];
 $g=ber_items($it[1][1]); if(!$g||count($g)<4) return ['ok'=>false,'err'=>'bad v3 header','ver'=>'v3']; $flags=ord($g[2][1]??"\0"); $auth=($flags&1)!==0; $priv=($flags&2)!==0;
 if($it[2][0]!==4) return ['ok'=>false,'err'=>'bad v3 security','ver'=>'v3']; $sp=$it[2][1]; $spStart=$base+$it[2][2]; $so=0; $sq=ber_tlv($sp,$so); $s=$sq?ber_items($sq[1]):null; if(!$s||count($s)<6) return ['ok'=>false,'err'=>'bad v3 security parameters','ver'=>'v3'];
 $eng=$s[0][1]; $boots=ber_int($s[1][1]); $etime=ber_int($s[2][1]); $user=$s[3][1]; $ap=$s[4][1]; $pp=$s[5][1]; $E=['ok'=>false,'ver'=>'v3','user'=>$user,'eng'=>bin2hex($eng)];
 $U=null; foreach($cfg['v3']??[] as $x) if(($x['u']??'')===$user){ $U=$x; break; }
 if(!$U) return $E+['err'=>'unknown SNMPv3 user','bad'=>'user'];
 $aa=usm_algo($U['ap']??''); $kauth=null;
 if($auth){ if(!$aa||(string)($U['aw']??'')==='') return $E+['err'=>'SNMPv3 user has no authentication password in Settings','bad'=>'user'];
  $kauth=usm_key($U['aw'],$eng,$aa[0]); $pos=strpos($sp,$ap); if($ap===''||strlen($ap)!==$aa[1]||$pos===false) return $E+['err'=>'SNMPv3 authentication parameters have the wrong length (check the authentication protocol)','bad'=>'auth'];
  $z=substr_replace($d,str_repeat("\0",strlen($ap)),$spStart+$pos,strlen($ap)); $mac=substr(hash_hmac($aa[0],$z,$kauth,true),0,$aa[1]); if(!hash_equals($mac,$ap)) return $E+['err'=>'SNMPv3 authentication failed (wrong password or protocol)','bad'=>'auth']; }
 $md=$it[3]; if($md[0]===0x30){ $sc=$md; }
 else if($md[0]===4){ if(!$priv) return $E+['err'=>'bad v3 data'];
  $pa=strtolower((string)($U['pp']??'')); if(!$aa||(string)($U['pw']??'')==='') return $E+['err'=>'SNMPv3 user has no privacy password in Settings','bad'=>'user']; $kp=usm_key($U['pw'],$eng,$aa[0]);
  if($pa==='aes'){ $iv=pack('N',$boots).pack('N',$etime).$pp; $pl=@openssl_decrypt($md[1],'aes-128-cfb',substr($kp,0,16),OPENSSL_RAW_DATA,$iv); }
  elseif($pa==='des'){ if(strlen($pp)!==8) return $E+['err'=>'bad DES salt']; $iv=substr($kp,8,8)^$pp; $pl=@openssl_decrypt($md[1],'des-cbc',substr($kp,0,8),OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING,$iv); }
  else return $E+['err'=>'SNMPv3 user has no privacy protocol in Settings','bad'=>'user'];
  if($pl===false||$pl==='') return $E+['err'=>'SNMPv3 decryption failed (wrong privacy password or protocol)','bad'=>'priv']; $o=0; $sc=ber_tlv($pl,$o); if(!$sc||$sc[0]!==0x30) return $E+['err'=>'SNMPv3 decryption failed (wrong privacy password or protocol)','bad'=>'priv']; }
 else return $E+['err'=>'bad v3 data'];
 if($priv&&$md[0]!==4) return $E+['err'=>'SNMPv3 message is not encrypted but privacy is expected'];
 $sp2=ber_items($sc[1]); if(!$sp2||count($sp2)<3) return $E+['err'=>'bad v3 scoped PDU']; $pdu=$sp2[2];
 $r=snmp_pdu($pdu,'v3',$user); if(!$r['ok']) return $r+$E; $r['user']=$user; return $r; }

// ---- Syslog ---------------------------------------------------------------------------------------------
// RFC 3164, RFC 5424 och Merakis variant ("<PRI>1 <unixtid> <enhet> <typ> <text>"). Returnerar ['kind'=>'syslog', sev, fac, host, prog, msg].
// Tidsstämpel i flera varianter: RFC 3164 (även med år, millisekunder, tidszon, *-prefix), ISO 8601 och "ÅÅÅÅ-MM-DD HH:MM:SS".
// Switchar/routrar (Cisco, HP/Aruba, Huawei, Dell, Juniper, MikroTik) lägger även löpnummer, värdnamn och egna meddelandeformat.
function syslog_ts($d,$noHostOnly=false){
 $TS='[*.]?(?:[A-Z][a-z]{2}\s+\d{1,2}(?:\s+\d{4})?\s+\d{2}:\d{2}:\d{2}(?:[.,]\d+)?(?:\s?[+-]\d{2}:?\d{2})?|\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:[.,]\d+)?(?:Z|[+-]\d{2}:?\d{2})?)(?:\s+(?:CEST|CET|UTC|GMT|EEST|EET|WEST|WET|BST|MSK|[PMCE][SD]T))?';
 if(!preg_match('/^'.$TS.':?\s+(.*)$/s',$d,$m)) return null; $x=$m[1]; $host=null;
 if(!$noHostOnly||!preg_match('/^%/',$x)){ // värdnamn: första ordet, om det inte är ett meddelande
  if(preg_match('/^(\S+)\s+(.*)$/s',$x,$q)&&!preg_match('/[:,]$|^%|,/',$q[1])&&!preg_match('/^\[?\d{4,}\]?:?$/',$q[1])&&!preg_match('/^[^\s]+:/',$q[1])){ $host=$q[1]; $x=$q[2]; } }
 if($host!==null){ if(preg_match('/^:\s*(.*)$/s',$x,$q)) $x=$q[1];
  if(preg_match('/^\d{1,12}:?\s+(.*)$/s',$x,$q)&&preg_match('/^(?:%|[\w.-]+:|'.$TS.')/',$q[1])) $x=$q[1]; // löpnummer före meddelandet (Dell/HP/Cisco)
  if(preg_match('/^'.$TS.':?\s+(.*)$/s',$x,$q)) $x=$q[1]; } // andra tidsstämpeln (Cisco)
 [$prog,$msg]=syslog_prog($x); return [$host,$prog,$msg]; }
// Programnamn/ämne ur meddelandet: vanligt "prog[pid]: text", Cisco %FAC-SEV-MNEM:, Huawei %%01MOD/SEV/NAME(l)[n]: och MikroTik-ämnen (system,info,account).
function syslog_prog($msg){ $msg=ltrim((string)$msg);
 if(preg_match('/^%%\d+([A-Z0-9_]+)\/\d\/([A-Z0-9_]+)(?:\([a-z]\))?(?:\[\d+\])?:\s*(.*)$/s',$msg,$q)) return [$q[1].'/'.$q[2],$q[3]];
 if(preg_match('/^([a-z][a-z0-9_-]*(?:,[a-z0-9_-]+)+):?\s+(.*)$/s',$msg,$q)&&preg_match('/(^|,)(info|warning|error|critical|debug)(,|$)/',$q[1])) return [$q[1],$q[2]];
 if(preg_match('/^([^\s:\[]{1,48})(?:\[(\d+)\])?:\s*(.*)$/s',$msg,$q)) return [$q[1].($q[2]!==''?'['.$q[2].']':''),$q[3]];
 return [null,$msg]; }

function syslog_parse($d){ $d=rtrim(str_replace("\0",'',$d),"\r\n "); $sev=6; $fac=null; $pri=false;
 if(preg_match('/^<(\d{1,3})>(.*)$/s',$d,$m)){ $pv=(int)$m[1]; if($pv<=191){ $sev=$pv&7; $fac=$pv>>3; $d=$m[2]; $pri=true; } }
 $host=null; $prog=null; $msg=$d;
 if(preg_match('/^1 (\S+) (\S+) (\S+) (.*)$/s',$d,$m)){ // RFC 5424 (eller Meraki)
  $host=$m[2]==='-'?null:$m[2]; $prog=$m[3]==='-'?null:$m[3]; $rest=$m[4];
  if(!preg_match('/^\d+(\.\d+)?$/',$m[1])&&preg_match('/^(\S+) (\S+) (-|(?:\[[^\]]*\])+) ?(.*)$/s',$rest,$q)){ if($q[1]!=='-') $prog=($prog?$prog:'').'['.$q[1].']'; $msg=$q[4]; if($q[3]!=='-'&&$q[4]==='') $msg=$q[3]; } else $msg=$rest; }
 elseif($v=syslog_ts($d)){ [$host,$prog,$msg]=$v; }
 elseif(preg_match('/^(?:(\w[\w.-]*):\s+)?(\d{1,12}):\s+(.*)$/s',$d,$m)&&($v=syslog_ts($m[3],true))){ [$h2,$prog,$msg]=$v; $host=$h2??($m[1]!==''?$m[1]:null); } // Cisco: [origin: ]löpnummer: tidsstämpel: %MEDDELANDE
 else{ [$prog,$msg]=syslog_prog($d); }
 $msg=trim($msg); if(!preg_match('//u',$msg)) $msg=mb_convert_encoding($msg,'UTF-8','ISO-8859-1');
 return ['kind'=>'syslog','sev'=>$sev,'fac'=>$fac,'host'=>$host!==null?mb_substr($host,0,120):null,'prog'=>$prog!==null?mb_substr($prog,0,80):null,'oid'=>null,'msg'=>mb_substr($msg,0,3900),'pri'=>$pri]; }

function sev_names(){ return [0=>'Emergency',1=>'Alert',2=>'Critical',3=>'Error',4=>'Warning',5=>'Notice',6=>'Info',7=>'Debug']; }
// Mönster: vanlig text (skiftlägesokänslig delsträng) eller /regex/flaggor
function log_match($pat,$text){ $pat=(string)$pat; if($pat==='') return true; if(preg_match('~^/(.+)/([imsu]*)$~s',$pat,$m)){ $r=@preg_match('~'.str_replace('~','\~',$m[1]).'~'.$m[2],$text); return $r===1; } return stripos($text,$pat)!==false; }

// ---- Brandväggsloggar: strukturerade fält ur syslog-raden -----------------------------------------------------------------------
// Känner igen pfSense/OPNsense (filterlog, CSV), Linux netfilter/iptables (SRC= DST= …, även UniFi), Meraki-flöden och MikroTik.
// Returnerar ['act'=>pass|block|reject|…,'dir'=>in|out|fwd,'in'=>ingress-interface,'out'=>egress-interface,'proto'=>tcp|udp|icmp|…,'src','dst','sport','dport','rule'] eller null.
function fw_norm_act($a){ $a=strtolower(trim((string)$a)); if($a==='') return null; if(in_array($a,['pass','accept','allow','permit','allowed'],true)) return 'pass'; if(in_array($a,['block','drop','deny','denied','blocked'],true)) return 'block'; return substr($a,0,10); }
function fw_parse($prog,$msg){ $prog=(string)$prog; $msg=(string)$msg;
 if(stripos($prog,'filterlog')===0){ $f=explode(',',$msg); if(count($f)<18) return null; $ver=$f[8]??''; $r=['rule'=>$f[0]!==''?$f[0].($f[1]!==''?'.'.$f[1]:''):null,'act'=>fw_norm_act($f[6]),'dir'=>strtolower($f[7])==='out'?'out':(strtolower($f[7])==='in'?'in':null),'sport'=>null,'dport'=>null];
  if($ver==='4'){ $proto=strtolower($f[16]??''); $src=$f[18]??''; $dst=$f[19]??''; $po=20; } elseif($ver==='6'){ $proto=strtolower($f[12]??''); $src=$f[15]??''; $dst=$f[16]??''; $po=17; } else return null;
  if(!filter_var($src,FILTER_VALIDATE_IP)||!filter_var($dst,FILTER_VALIDATE_IP)) return null; $r['proto']=$proto?:null; $r['src']=$src; $r['dst']=$dst;
  if(in_array($proto,['tcp','udp'],true)){ $sp=$f[$po]??''; $dp=$f[$po+1]??''; if(ctype_digit($sp)) $r['sport']=(int)$sp; if(ctype_digit($dp)) $r['dport']=(int)$dp; }
  $ifc=$f[4]??''; $r['in']=$r['dir']==='in'?$ifc:null; $r['out']=$r['dir']==='out'?$ifc:null; return $r; }
 if(preg_match('/\bSRC=([0-9a-fA-F:.]+)\s+DST=([0-9a-fA-F:.]+)/',$msg,$m)){ $r=['src'=>$m[1],'dst'=>$m[2],'proto'=>null,'sport'=>null,'dport'=>null,'act'=>null,'dir'=>null,'in'=>null,'out'=>null,'rule'=>null];
  if(preg_match('/\bPROTO=(\w+)/',$msg,$x)) $r['proto']=strtolower($x[1]); if(preg_match('/\bSPT=(\d+)/',$msg,$x)) $r['sport']=(int)$x[1]; if(preg_match('/\bDPT=(\d+)/',$msg,$x)) $r['dport']=(int)$x[1];
  $in=preg_match('/\bIN=(\S*)/',$msg,$x)?$x[1]:''; $out=preg_match('/\bOUT=(\S*)/',$msg,$x)?$x[1]:''; $r['in']=$in!==''?$in:null; $r['out']=$out!==''?$out:null; $r['dir']=($in!==''&&$out!=='')?'fwd':($in!==''?'in':($out!==''?'out':null));
  if(preg_match('/\[([A-Za-z][A-Za-z0-9_.:-]*)\]/',$msg,$x)){ $r['rule']=mb_substr($x[1],0,40); if(preg_match('/-([ADR])$/',$x[1],$y)) $r['act']=['A'=>'pass','D'=>'block','R'=>'reject'][$y[1]]; elseif(preg_match('/-(ACCEPT|ALLOW|DROP|DENY|REJECT|BLOCK)$/i',$x[1],$y)) $r['act']=fw_norm_act($y[1]); }
  if($r['act']===null&&preg_match('/\b(DROP|BLOCK|REJECT|DENY|ACCEPT|ALLOW|PASS)/i',$msg,$x)) $r['act']=fw_norm_act($x[1]); return $r; }
 if(preg_match('/\bsrc=([0-9a-fA-F:.]+)\s+dst=([0-9a-fA-F:.]+)/',$msg,$m)){ $r=['src'=>$m[1],'dst'=>$m[2],'proto'=>null,'sport'=>null,'dport'=>null,'act'=>null,'dir'=>null,'in'=>null,'out'=>null,'rule'=>null]; // Meraki-flöden
  if(preg_match('/\bprotocol=(\w+)/',$msg,$x)) $r['proto']=strtolower($x[1]); if(preg_match('/\bsport=(\d+)/',$msg,$x)) $r['sport']=(int)$x[1]; if(preg_match('/\bdport=(\d+)/',$msg,$x)) $r['dport']=(int)$x[1];
  if(preg_match('/\b(allow|deny)\b/i',$prog.' '.$msg,$x)) $r['act']=fw_norm_act($x[1]); if(preg_match('/pattern:\s*(.+)$/',$msg,$x)) $r['rule']=mb_substr(trim($x[1]),0,40); return ($r['proto']!==null||$r['dport']!==null||$r['act']!==null)?$r:null; }
 if(preg_match('/proto (\w+)(?: \([^)]*\))?, ([0-9.]+):(\d+)->([0-9.]+):(\d+)/',$msg,$m)){ $r=['src'=>$m[2],'dst'=>$m[4],'proto'=>strtolower($m[1]),'sport'=>(int)$m[3],'dport'=>(int)$m[5],'act'=>null,'dir'=>null,'in'=>null,'out'=>null,'rule'=>null]; // MikroTik
  if(preg_match('/\bin:(\S+)/',$msg,$x)&&$x[1]!=='(unknown') $r['in']=rtrim($x[1],',')?:null; if(preg_match('/\bout:(\S+)/',$msg,$x)&&$x[1]!=='(unknown') $r['out']=rtrim($x[1],',')?:null; return $r; }
 return fw_parse_more($prog,$msg); }

// Fler tillverkare: Cisco ASA/FTD (%ASA-x-nnnnnn), Palo Alto (CSV TRAFFIC/THREAT), Fortinet FortiGate och Sophos (key=value). Skrivna efter tillverkarnas dokumenterade loggformat.
function fw_ip_ok($x){ return filter_var($x,FILTER_VALIDATE_IP)!==false; }
function fw_proto_name($p){ $p=strtolower(trim((string)$p)); static $m=['1'=>'icmp','2'=>'igmp','6'=>'tcp','17'=>'udp','47'=>'gre','50'=>'esp','51'=>'ah','58'=>'icmp6','89'=>'ospf','112'=>'vrrp','132'=>'sctp']; return $m[$p]??($p!==''?$p:null); }
function fw_blank(){ return ['src'=>null,'dst'=>null,'proto'=>null,'sport'=>null,'dport'=>null,'act'=>null,'dir'=>null,'in'=>null,'out'=>null,'rule'=>null]; }
function fw_parse_more($prog,$msg){ $all=trim($prog.' '.$msg);
 // ---- Cisco ASA / FTD ----
 if(preg_match('/%(?:ASA|FTD|PIX)-\d-(\d{6})\b/',$all,$k)){ $id=$k[1]; $r=fw_blank(); $ip='[0-9a-fA-F:.]+'; $ifc='[^\s:\/]+';
  if(($id==='302013'||$id==='302015')&&preg_match('/Built (inbound|outbound) (\w+) connection \d+ for ('.$ifc.'):('.$ip.')\/(\d+) \([^)]*\) to ('.$ifc.'):('.$ip.')\/(\d+)/',$all,$m)){ $r['proto']=strtolower($m[2]); $r['act']='pass';
   if($m[1]==='inbound'){ $r['src']=$m[4]; $r['sport']=(int)$m[5]; $r['in']=$m[3]; $r['dst']=$m[7]; $r['dport']=(int)$m[8]; $r['out']=$m[6]; $r['dir']='in'; }
   else { $r['src']=$m[7]; $r['sport']=(int)$m[8]; $r['in']=$m[6]; $r['dst']=$m[4]; $r['dport']=(int)$m[5]; $r['out']=$m[3]; $r['dir']='out'; } return $r; }
  if($id==='106023'&&preg_match('/Deny (\w+) src ('.$ifc.'):('.$ip.')(?:\/(\d+))? dst ('.$ifc.'):('.$ip.')(?:\/(\d+))?.*?by access-group "?([^"\s]+)/',$all,$m)){ $r['act']='block'; $r['proto']=strtolower($m[1]); $r['in']=$m[2]; $r['src']=$m[3]; $r['sport']=$m[4]!==''?(int)$m[4]:null; $r['out']=$m[5]; $r['dst']=$m[6]; $r['dport']=($m[7]??'')!==''?(int)$m[7]:null; $r['rule']=mb_substr($m[8],0,40); $r['dir']='fwd'; return $r; }
  if($id==='106100'&&preg_match('/access-list (\S+) (permitted|denied|est-allowed) (\w+) ('.$ifc.')\/('.$ip.')\((\d+)\) -> ('.$ifc.')\/('.$ip.')\((\d+)\)/',$all,$m)){ $r['rule']=mb_substr($m[1],0,40); $r['act']=$m[2]==='denied'?'block':'pass'; $r['proto']=strtolower($m[3]); $r['in']=$m[4]; $r['src']=$m[5]; $r['sport']=(int)$m[6]; $r['out']=$m[7]; $r['dst']=$m[8]; $r['dport']=(int)$m[9]; $r['dir']='fwd'; return $r; }
  if(in_array($id,['106001','106006','106015','106010'],true)&&preg_match('/(?:Deny |denied )?(?:inbound |outbound )?(?:(\w+) )?(?:\(no connection\) )?(?:connection denied )?from ('.$ip.')\/(\d+) to ('.$ip.')\/(\d+)/i',$all,$m)){ $r['act']='block'; $r['proto']=$m[1]!==''&&!ctype_digit($m[1])&&strtolower($m[1])!=='connection'?strtolower($m[1]):(preg_match('/\b(TCP|UDP|ICMP)\b/i',$all,$x)?strtolower($x[1]):null); $r['src']=$m[2]; $r['sport']=(int)$m[3]; $r['dst']=$m[4]; $r['dport']=(int)$m[5]; if(preg_match('/on interface (\S+)/',$all,$x)) $r['in']=$x[1]; $r['dir']='in'; return $r; }
  return null; }
 // ---- Palo Alto (CSV) ----
 if(strpos($msg,'TRAFFIC')!==false||strpos($msg,'THREAT')!==false){ $f=str_getcsv($msg,',','"','');
  $t=null; foreach([3,4,2,5] as $i) if(isset($f[$i])&&($f[$i]==='TRAFFIC'||$f[$i]==='THREAT')){ $t=$i; break; }
  if($t!==null&&isset($f[$t+27])&&($f[$t+1]??'')!=='start'){ $r=fw_blank(); $r['src']=$f[$t+4]; $r['dst']=$f[$t+5]; if(!fw_ip_ok($r['src'])||!fw_ip_ok($r['dst'])) return null;
   $r['rule']=($f[$t+8]??'')!==''?mb_substr($f[$t+8],0,40):null; $r['in']=($f[$t+15]??'')!==''?$f[$t+15]:null; $r['out']=($f[$t+16]??'')!==''?$f[$t+16]:null; $r['dir']='fwd';
   $sp=$f[$t+21]??''; $dp=$f[$t+22]??''; if(ctype_digit($sp)) $r['sport']=(int)$sp; if(ctype_digit($dp)) $r['dport']=(int)$dp; $r['proto']=fw_proto_name($f[$t+26]??'');
   $a=strtolower($f[$t+27]??''); $r['act']=$a==='allow'||$a==='alert'?'pass':(strpos($a,'reset')===0?'reject':(fw_norm_act($a)?:null)); if($a==='drop'||$a==='deny'||strpos($a,'block')===0) $r['act']='block'; return $r; } }
 // ---- key=value: Fortinet FortiGate, Sophos XG/SFOS och UTM ----
 if((strpos($msg,'srcip=')!==false||strpos($msg,'src_ip=')!==false)&&preg_match_all('/\b([A-Za-z_][\w.]*)=(?:"((?:[^"\\\\]|\\\\.)*)"|(\S*))/',$msg,$mm,PREG_SET_ORDER)){ $k=[]; foreach($mm as $x){ $k[strtolower($x[1])]=count($x)>3?$x[3]:$x[2]; }
  $g=function(...$n) use($k){ foreach($n as $x) if(isset($k[$x])&&$k[$x]!==''&&$k[$x]!=='-') return $k[$x]; return null; };
  $s=$g('srcip','src_ip'); $d=$g('dstip','dst_ip'); if($s!==null&&$d!==null&&fw_ip_ok($s)&&fw_ip_ok($d)){ $r=fw_blank(); $r['src']=$s; $r['dst']=$d; $r['proto']=fw_proto_name($g('proto','protocol'));
   $sp=$g('srcport','src_port'); $dp=$g('dstport','dst_port'); if($sp!==null&&ctype_digit($sp)) $r['sport']=(int)$sp; if($dp!==null&&ctype_digit($dp)) $r['dport']=(int)$dp;
   $a=strtolower((string)$g('action','status','log_subtype')); $r['act']=in_array($a,['accept','allow','allowed','pass','close','timeout','start','ip-conn','client-rst','server-rst','passthrough','detected'],true)?'pass':(in_array($a,['deny','denied','drop','dropped','block','blocked','reject'],true)?'block':(fw_norm_act($a)?:null));
   $r['in']=$g('srcintf','in_interface','initf','srcinf'); $r['out']=$g('dstintf','out_interface','outitf','dstinf'); $r['dir']=$r['in']!==null&&$r['out']!==null?'fwd':($r['in']!==null?'in':($r['out']!==null?'out':null));
   $ru=$g('policyid','fw_rule_id','fwrule','ruleid'); $r['rule']=$ru!==null?mb_substr($ru,0,40):null; return $r; } }
 return null; }

// SQL-villkor för brandväggsfilter. $f: act, dir, proto, src, dst, sport, dport, in, out, rule (text; "!" först = invertera; flera värden med komma;
// IP: exakt, 10.1.* eller CIDR 10.1.0.0/16; port: 80,443,1024-2000). Används av händelsesidan och larmregler. Parametrar läggs i $p.
function log_fw_where($f,&$p,$a=''){ $w=[]; $c=function($n) use($a){ return $a.$n; };
 $txt=function($col,$v,$ci=true) use(&$w,&$p,$c){ $v=trim((string)$v); if($v==='') return; $neg=false; if($v[0]==='!'){ $neg=true; $v=ltrim(substr($v,1)); } $vs=array_values(array_filter(array_map('trim',explode(',',$v)),'strlen')); if(!$vs) return; $ors=[]; foreach($vs as $x){ if(strpos($x,'*')!==false||strpos($x,'%')!==false){ $ors[]=$c($col).' LIKE ?'; $p[]=str_replace('*','%',$x); } else { $ors[]=$c($col).'=?'; $p[]=$x; } } $s='('.implode(' OR ',$ors).')'; $w[]=$neg?'('.$c($col).' IS NULL OR NOT '.$s.')':$s; };
 $ip=function($col,$v) use(&$w,&$p,$c){ $v=trim((string)$v); if($v==='') return; $neg=false; if($v[0]==='!'){ $neg=true; $v=ltrim(substr($v,1)); } $vs=array_values(array_filter(array_map('trim',explode(',',$v)),'strlen')); $ors=[];
  foreach($vs as $x){ if(preg_match('~^(\d+\.\d+\.\d+\.\d+)/(\d{1,2})$~',$x,$m)&&(int)$m[2]<=32&&filter_var($m[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){ $bits=(int)$m[2]; $lo=ip2long($m[1])&(($bits===0?0:(~0<<(32-$bits)))&0xFFFFFFFF); $hi=$lo|((~(($bits===0?0:(~0<<(32-$bits)))))&0xFFFFFFFF); $ors[]='('.$c($col).' NOT LIKE \'%:%\' AND INET_ATON('.$c($col).') BETWEEN ? AND ?)'; $p[]=$lo&0xFFFFFFFF; $p[]=$hi&0xFFFFFFFF; }
   elseif(strpos($x,'*')!==false){ $ors[]=$c($col).' LIKE ?'; $p[]=str_replace('*','%',$x); } else { $ors[]=$c($col).'=?'; $p[]=$x; } }
  if(!$ors) return; $s='('.implode(' OR ',$ors).')'; $w[]=$neg?'('.$c($col).' IS NULL OR NOT '.$s.')':$s; };
 $port=function($col,$v) use(&$w,&$p,$c){ $v=trim((string)$v); if($v==='') return; $neg=false; if($v[0]==='!'){ $neg=true; $v=ltrim(substr($v,1)); } $ors=[]; foreach(array_filter(array_map('trim',explode(',',$v)),'strlen') as $x){ if(preg_match('/^(\d+)-(\d+)$/',$x,$m)){ $ors[]=$c($col).' BETWEEN ? AND ?'; $p[]=(int)$m[1]; $p[]=(int)$m[2]; } elseif(ctype_digit($x)){ $ors[]=$c($col).'=?'; $p[]=(int)$x; } } if(!$ors) return; $s='('.implode(' OR ',$ors).')'; $w[]=$neg?'('.$c($col).' IS NULL OR NOT '.$s.')':$s; };
 $txt('fw_act',$f['act']??''); $txt('fw_dir',$f['dir']??''); $txt('fw_proto',$f['proto']??''); $ip('fw_src',$f['src']??''); $ip('fw_dst',$f['dst']??''); $port('fw_sport',$f['sport']??''); $port('fw_dport',$f['dport']??''); $txt('fw_in',$f['in']??''); $txt('fw_out',$f['out']??''); $txt('fw_rule',$f['rule']??'');
 return $w; }

// ---- Partitioner (en per dygn) och retention ---------------------------------------------------------------------------------------
// log_events partitioneras per dygn (PARTITION BY RANGE COLUMNS(ts)). Då tas ett helt dygn bort med DROP PARTITION, vilket går direkt även med hundratals miljoner rader.
function log_part_info(){ try{ $r=db()->query("SELECT PARTITION_NAME n,PARTITION_DESCRIPTION d FROM information_schema.PARTITIONS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='log_events' AND PARTITION_NAME IS NOT NULL ORDER BY PARTITION_ORDINAL_POSITION")->fetchAll(); }catch(Throwable $e){ return null; }
 if(!$r) return null; $o=['parts'=>[],'max'=>false]; foreach($r as $x){ $d=trim((string)$x['d'],"'\" "); if(strtoupper($d)==='MAXVALUE'){ $o['max']=true; continue; } $o['parts'][]=[(string)$x['n'],$d]; } return $o; }
// Skapar partitioner för idag och 7 dagar framåt, tar bort dygn äldre än $keep dagar. Returnerar en kort text om något gjordes, annars ''. Kastar vid fel.
function log_part_maintain($keep){ $pi=log_part_info(); if(!$pi||!$pi['max']) return null; $pdo=db(); $out=[]; $today=strtotime(date('Y-m-d')); $last=$pi['parts']?strtotime(end($pi['parts'])[1]):0; $new=[];
 for($i=0;$i<=7;$i++){ $d=$today+$i*86400; $b=strtotime(date('Y-m-d',$d).' +1 day'); if($b>$last){ $new[]='PARTITION p'.date('Ymd',$d)." VALUES LESS THAN ('".date('Y-m-d',$b)." 00:00:00')"; } }
 if($new){ $pdo->exec('ALTER TABLE log_events REORGANIZE PARTITION pmax INTO ('.implode(', ',$new).', PARTITION pmax VALUES LESS THAN (MAXVALUE))'); $out[]=count($new).' partitions added'; }
 $cut=strtotime(date('Y-m-d').' -'.max(1,(int)$keep).' day'); $dr=0; foreach($pi['parts'] as [$n,$b]){ $bt=strtotime($b); if($bt!==false&&$bt<=$cut&&preg_match('/^p[0-9a-z]+$/i',$n)){ $pdo->exec('ALTER TABLE log_events DROP PARTITION '.$n); $dr++; } } if($dr) $out[]=$dr.' old partitions dropped';
 return implode(', ',$out); }
// Syslog över TCP (RFC 6587): antingen "LÄNGD MEDDELANDE" (octet counting) eller en rad per meddelande (LF/CRLF).
// Tar de kompletta meddelandena ur bufferten $b och lämnar resten. $bad sätts om strömmen är ogiltig/för stor. $final=true: tillåt sista raden utan radslut.
function syslog_tcp_frames(&$b,&$bad,$final=false){ $out=[]; $bad=false; $max=1048576;
 while($b!==''){
  if(preg_match('/^(\d{1,7}) /',$b,$m)){ $len=(int)$m[1]; $hl=strlen($m[0]); if($len>$max||$len<1){ $bad=true; $b=''; break; } if(strlen($b)<$hl+$len){ if($final){ $out[]=substr($b,$hl); $b=''; } break; } $out[]=substr($b,$hl,$len); $b=substr($b,$hl+$len); continue; }
  if(preg_match('/^\d{1,7}$/',$b)&&!$final) break; // längdsiffror som ännu inte är kompletta
  $p=strpos($b,"\n"); if($p===false){ if(strlen($b)>$max){ $bad=true; $b=''; } elseif($final){ $out[]=$b; $b=''; } break; }
  $line=rtrim(substr($b,0,$p),"\r"); $b=substr($b,$p+1); if($line!=='') $out[]=$line; }
 return array_values(array_filter($out,fn($x)=>trim($x)!=='')); }
