<?php require 'config.php'; require 'lib.php';
// OAuth 2.0 / OpenID Connect (authorization code + PKCE). Fungerar med t.ex. Pocket ID, Authentik, Keycloak, Authelia, Zitadel, Google m.fl.
function so_fail($m,$log=''){ if($log!=='') applog('warning','auth','SSO: '.$log.' ('.client_ip().')'); $_SESSION['flash']=$m; header('Location: login.php'); exit; }
if(!oidc_on()) so_fail(t('SSO är inte aktiverat.','SSO is not enabled.'));
[$d,$de]=oidc_disc(); if(!$d) so_fail(t('SSO-leverantören kunde inte nås: ','The SSO provider could not be reached: ').$de,'discovery failed: '.$de);
$ru=base_url().'oauth.php'; $cid=trim(setting('oidc_client_id','')); $sec=secret('oidc_secret');
if(($_GET['a']??'')==='login'){
 $st=bin2hex(random_bytes(16)); $nn=bin2hex(random_bytes(16)); $ver=b64u(random_bytes(32));
 $_SESSION['oidc']=['s'=>$st,'n'=>$nn,'v'=>$ver,'t'=>time()];
 $sc=trim(setting('oidc_scopes',''))?:'openid profile email';
 if(!preg_match('/(^| )openid( |$)/',$sc)) $sc='openid '.$sc;
 header('Location: '.$d['authorization_endpoint'].(strpos($d['authorization_endpoint'],'?')===false?'?':'&').http_build_query(['response_type'=>'code','client_id'=>$cid,'redirect_uri'=>$ru,'scope'=>$sc,'state'=>$st,'nonce'=>$nn,'code_challenge'=>b64u(hash('sha256',$ver,true)),'code_challenge_method'=>'S256'])); exit; }
// ---- callback
$S=$_SESSION['oidc']??null; unset($_SESSION['oidc']);
if(!$S||time()-(int)$S['t']>600) so_fail(t('Inloggningen tog för lång tid eller startades inte här – försök igen.','The sign-in took too long or was not started here – try again.'));
if(isset($_GET['error'])) so_fail(t('SSO-leverantören nekade inloggningen: ','The SSO provider denied the sign-in: ').substr(preg_replace('/[^\w .:-]/','',(string)($_GET['error_description']??$_GET['error'])),0,150),'provider error '.substr((string)$_GET['error'],0,60));
if(empty($_GET['code'])||!hash_equals($S['s'],(string)($_GET['state']??''))) so_fail(t('Ogiltigt svar från SSO-leverantören (state).','Invalid response from the SSO provider (state).'),'state mismatch');
$post=['grant_type'=>'authorization_code','code'=>(string)$_GET['code'],'redirect_uri'=>$ru,'code_verifier'=>$S['v']]; $hd=[]; $am=$d['token_endpoint_auth_methods_supported']??['client_secret_basic','client_secret_post'];
if(in_array('client_secret_post',$am,true)||!in_array('client_secret_basic',$am,true)){ $post['client_id']=$cid; $post['client_secret']=$sec; } else $hd[]='Authorization: Basic '.base64_encode(rawurlencode($cid).':'.rawurlencode($sec));
[$code,$b,$e]=oidc_http($d['token_endpoint'],$post,$hd); $tk=json_decode($b,true);
if($code!==200||!is_array($tk)||empty($tk['id_token'])) so_fail(t('SSO-leverantören gav ingen giltig token','The SSO provider did not return a valid token').' ('.($e?:'HTTP '.$code).').','token exchange failed: '.($e?:'HTTP '.$code).' '.substr($b,0,120));
$pp=explode('.',$tk['id_token']); $cl=count($pp)===3?json_decode(b64u_dec($pp[1]),true):null; if(!is_array($cl)) so_fail(t('Ogiltig id_token.','Invalid id_token.'),'bad id_token');
$aud=(array)($cl['aud']??[]);
if(rtrim((string)($cl['iss']??''),'/')!==rtrim($d['issuer'],'/')||!in_array($cid,$aud,true)||(int)($cl['exp']??0)<time()-60||!hash_equals($S['n'],(string)($cl['nonce']??''))||empty($cl['sub'])) so_fail(t('id_token kunde inte verifieras.','The id_token could not be verified.'),'id_token validation failed');
$rc=trim((string)setting('oidc_role_claim','')); $rcv=function($c,$k){ $x=$c; foreach(explode('.',$k) as $p){ if(!is_array($x)||!array_key_exists($p,$x)) return null; $x=$x[$p]; } return $x; };
if((empty($cl['email'])||empty($cl['preferred_username'])||($rc!==''&&$rcv($cl,$rc)===null))&&!empty($d['userinfo_endpoint'])&&!empty($tk['access_token'])){ [$uc,$ub]=oidc_http($d['userinfo_endpoint'],null,['Authorization: Bearer '.$tk['access_token']]); $ui=json_decode($ub,true); if($uc===200&&is_array($ui)&&($ui['sub']??'')===$cl['sub']) $cl=array_merge($ui,$cl); }
$sub=substr((string)$cl['sub'],0,255); $em=trim((string)($cl['email']??'')); $ev=$cl['email_verified']??null; $pdo=db();
try{ $q=$pdo->prepare('SELECT * FROM users WHERE oidc_sub=?'); $q->execute([$sub]); $u=$q->fetch(); }catch(Throwable $x){ so_fail(t('Databasen saknar kolumnen oidc_sub – kör sql/3.02.sql.','The database is missing the oidc_sub column – run sql/3.02.sql.')); }
if(!$u&&$em!==''&&$ev!==false&&$ev!=='false'){ $q=$pdo->prepare("SELECT * FROM users WHERE LOWER(email)=LOWER(?) AND email<>''"); $q->execute([$em]); $m=$q->fetchAll();
 if(count($m)===1){ if(!empty($m[0]['oidc_sub'])&&$m[0]['oidc_sub']!==$sub) so_fail(t('Kontot är redan kopplat till en annan SSO-identitet.','The account is already linked to a different SSO identity.'),'email match but different sub for '.$m[0]['username']); $u=$m[0]; $pdo->prepare('UPDATE users SET oidc_sub=? WHERE id=?')->execute([$sub,$u['id']]); applog('info','auth','SSO identity linked to '.$u['username']); } }
if(!$u&&setting('oidc_create','0')==='1'){ $base=preg_replace('/[^A-Za-z0-9._@-]/','',(string)($cl['preferred_username']??($em!==''?explode('@',$em)[0]:'sso'))); $base=substr($base?:'sso',0,40); $un=$base; $i=1;
 while(true){ $q=$pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?'); $q->execute([$un]); if(!(int)$q->fetchColumn()) break; $un=$base.(++$i); }
 $pdo->prepare("INSERT INTO users(username,email,password_hash,role,lang,oidc_sub) VALUES(?,?,?,'user',?,?)")->execute([$un,$em!==''?substr($em,0,120):null,password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),setting('sys_lang','sv')==='sv'?'sv':'en',$sub]); applog('info','auth','SSO account created: '.$un);
 $u=login_user_by_id((int)$pdo->lastInsertId()); }
if(!$u) so_fail(t('Inget konto i VM Monitor är kopplat till din SSO-identitet. Be en administratör skapa ett konto med samma e-postadress (e-posten måste vara verifierad hos SSO-leverantören).','No VM Monitor account is linked to your SSO identity. Ask an administrator to create an account with the same email address (the email must be verified at the SSO provider).'),'no matching account for sub '.substr($sub,0,40).' email '.$em);
$u=login_user_by_id((int)$u['id']); if(!$u||!empty($u['disabled'])) so_fail(t('Kontot är inaktiverat.','The account is disabled.'),'disabled account');
$ra=trim((string)setting('oidc_role_admin','')); 
if($rc!==''&&$ra!==''){ $cv=$rcv($cl,$rc); $vals=is_array($cv)?array_map('strval',$cv):(is_string($cv)?preg_split('/[\s,]+/',$cv,-1,PREG_SPLIT_NO_EMPTY):[]); $isA=in_array($ra,$vals,true);
 if($cv===null&&isset($cl['_claim_names'])) applog('warning','auth','SSO role claim missing for '.$u['username'].' (provider sent a group overage reference – too many groups; use App roles or limit groups)');
 if($isA&&$u['role']!=='admin'){ $pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([$u['id']]); applog('info','auth','SSO role mapping: '.$u['username'].' -> admin'); }
 elseif(!$isA&&$u['role']==='admin'&&setting('oidc_role_demote','0')==='1'&&$cv!==null){ $q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE role='admin' AND IFNULL(disabled,0)=0 AND id<>?"); $q->execute([$u['id']]); if((int)$q->fetchColumn()>0){ $pdo->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$u['id']]); applog('info','auth','SSO role mapping: '.$u['username'].' -> user'); } } }
login_done($u,' (SSO)',false); $_SESSION['sso']=1; header('Location: index.php'); exit;
