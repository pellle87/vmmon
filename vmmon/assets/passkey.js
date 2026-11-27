/* WebAuthn helpers (passkeys) */
(function(g){
function b2u(b){var s='',a=new Uint8Array(b);for(var i=0;i<a.length;i++)s+=String.fromCharCode(a[i]);return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')}
function u2b(s){s=s.replace(/-/g,'+').replace(/_/g,'/');while(s.length%4)s+='=';var r=atob(s),a=new Uint8Array(r.length);for(var i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a.buffer}
function tok(){var e=document.querySelector('input[name=t]');return e?e.value:''}
function call(a,body){return fetch('passkey.php?a='+a,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF':tok()},body:JSON.stringify(body||{})}).then(function(r){return r.json().then(function(j){if(!r.ok||j.error)throw new Error(j.error||'error');return j})})}
g.pkSupported=function(){return !!(window.PublicKeyCredential&&navigator.credentials)};
g.pkRegister=function(name){return call('reg_options').then(function(o){var p=o.publicKey;p.challenge=u2b(p.challenge);p.user.id=u2b(p.user.id);(p.excludeCredentials||[]).forEach(function(c){c.id=u2b(c.id)});return navigator.credentials.create({publicKey:p})}).then(function(c){return call('reg_verify',{id:c.id,name:name,response:{clientDataJSON:b2u(c.response.clientDataJSON),attestationObject:b2u(c.response.attestationObject)}})})};
g.pkLogin=function(){return call('login_options').then(function(o){var p=o.publicKey;p.challenge=u2b(p.challenge);return navigator.credentials.get({publicKey:p})}).then(function(c){var r=c.response;return call('login_verify',{id:c.id,response:{clientDataJSON:b2u(r.clientDataJSON),authenticatorData:b2u(r.authenticatorData),signature:b2u(r.signature),userHandle:r.userHandle?b2u(r.userHandle):null}})})};
})(window);
