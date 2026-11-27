<?php require 'config.php'; require 'lib.php'; header('Content-Type: application/json'); header('Cache-Control: no-store');
function out($a,$c=200){ http_response_code($c); echo json_encode($a); exit; }
if(!hash_equals(csrf(),(string)($_SERVER['HTTP_X_CSRF']??''))) out(['error'=>'csrf'],400);
$a=$_GET['a']??''; $in=json_decode((string)file_get_contents('php://input'),true)?:[]; $rp=wa_rp(); $pdo=db();
function chal($k){ $c=b64u(random_bytes(32)); $_SESSION['wa']=['c'=>$c,'t'=>time(),'k'=>$k]; return $c; }
if($a==='login_options'){ out(['publicKey'=>['challenge'=>chal('webauthn.get'),'rpId'=>$rp,'userVerification'=>'required','timeout'=>120000]]); }
if($a==='login_verify'){ $r=$in['response']??[]; $cdj=b64u_dec($r['clientDataJSON']??''); $ad=b64u_dec($r['authenticatorData']??''); $sig=b64u_dec($r['signature']??''); $cid=(string)($in['id']??'');
 $e=wa_client($cdj,'webauthn.get'); if($e!=='') out(['error'=>$e],400);
 $q=$pdo->prepare('SELECT * FROM user_passkeys WHERE cred_id=?'); $q->execute([$cid]); $pk=$q->fetch(); if(!$pk||strlen($ad)<37) out(['error'=>'unknown passkey'],400);
 if(!hash_equals(hash('sha256',$rp,true),substr($ad,0,32))) out(['error'=>'rp'],400);
 $fl=ord($ad[32]); if(!($fl&1)||!($fl&4)) out(['error'=>'user verification required'],400);
 if(openssl_verify($ad.hash('sha256',$cdj,true),$sig,$pk['pubkey'],OPENSSL_ALGO_SHA256)!==1) { applog('warning','auth','Failed passkey login (signature) from '.client_ip()); out(['error'=>'signature'],400); }
 $sc=unpack('N',substr($ad,33,4))[1]; $old=(int)$pk['sign_count']; if(($sc>0||$old>0)&&$sc<=$old) out(['error'=>'counter'],400);
 $u=login_user_by_id($pk['user_id']); if(!$u||!empty($u['disabled'])) out(['error'=>t('Kontot är inaktiverat.','The account is disabled.')],403);
 if(!empty($u['locked'])) out(['error'=>login_locked_msg((int)$u['lmin'])],403);
 $pdo->prepare('UPDATE user_passkeys SET sign_count=?,last_used=NOW() WHERE id=?')->execute([$sc,$pk['id']]);
 login_done($u,' (passkey)',false); out(['ok'=>true,'url'=>'index.php']); }
need_login();
$uid=(int)$_SESSION['u']['id'];
if($a==='reg_options'){ $q=$pdo->prepare('SELECT cred_id FROM user_passkeys WHERE user_id=?'); $q->execute([$uid]);
 out(['publicKey'=>['challenge'=>chal('webauthn.create'),'rp'=>['name'=>'VM Monitor','id'=>$rp],'user'=>['id'=>b64u((string)$uid),'name'=>$_SESSION['u']['username'],'displayName'=>$_SESSION['u']['username']],
  'pubKeyCredParams'=>[['type'=>'public-key','alg'=>-7],['type'=>'public-key','alg'=>-257]],'authenticatorSelection'=>['residentKey'=>'required','requireResidentKey'=>true,'userVerification'=>'required'],'attestation'=>'none','timeout'=>120000,
  'excludeCredentials'=>array_map(fn($c)=>['type'=>'public-key','id'=>$c],$q->fetchAll(PDO::FETCH_COLUMN))]]); }
if($a==='reg_verify'){ $r=$in['response']??[]; $cdj=b64u_dec($r['clientDataJSON']??''); $e=wa_client($cdj,'webauthn.create'); if($e!=='') out(['error'=>$e],400);
 try{ $att=b64u_dec($r['attestationObject']??''); $o=0; $m=cbor_dec($att,$o); $ad=$m['authData']??''; if(strlen($ad)<55) throw new Exception('authData');
  if(!hash_equals(hash('sha256',$rp,true),substr($ad,0,32))) throw new Exception('rp'); $fl=ord($ad[32]); if(!($fl&1)||!($fl&4)||!($fl&0x40)) throw new Exception('flags');
  $l=unpack('n',substr($ad,53,2))[1]; $cid=substr($ad,55,$l); $o=55+$l; $key=cbor_dec($ad,$o); $pem=cose_pem($key); if(!$pem||$cid==='') throw new Exception('unsupported key');
  if(b64u($cid)!==(string)($in['id']??'')) throw new Exception('id');
  $sc=unpack('N',substr($ad,33,4))[1]; $nm=trim(substr((string)($in['name']??''),0,80))?:'Passkey';
  $pdo->prepare('INSERT INTO user_passkeys(user_id,cred_id,pubkey,sign_count,name) VALUES(?,?,?,?,?)')->execute([$uid,b64u($cid),$pem,$sc,$nm]);
 }catch(Throwable $x){ out(['error'=>$x->getMessage()],400); }
 unset($_SESSION['mfa_t']); applog('info','auth','Passkey added: '.$_SESSION['u']['username']); out(['ok'=>true]); }
out(['error'=>'bad request'],400);
