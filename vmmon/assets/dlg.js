/* Egna dialogrutor i stället för webbläsarens confirm()/alert(). Webbläsare kan blockera de inbyggda rutorna och de går inte att utforma.
   confirm() kan inte väntas in synkront, så rutan visas och åtgärden (skicka formuläret / klicka igen) utförs om användaren bekräftar.
   Fungerar för både inline-hanterare (onsubmit="return confirm(..)") och skript som anropar confirm(). */
(function(){
 var EN=(document.documentElement.lang||'sv')!=='sv', nC=window.confirm, bypass=false, prevFocus=null, cur=null;
 var CSS='.dlg-o{position:fixed;inset:0;z-index:99999;background:rgba(5,8,14,.62);display:flex;align-items:center;justify-content:center;padding:16px;animation:dlgf .12s ease-out}@keyframes dlgf{from{opacity:0}to{opacity:1}}'
  +'.dlg{background:var(--card,#161b25);color:var(--tx,#e4e8ef);border:1px solid var(--bd2,#2f3a52);border-radius:12px;box-shadow:0 18px 50px rgba(0,0,0,.5);max-width:440px;width:100%;padding:20px 22px}'
  +'.dlg h4{margin:0 0 8px;font-size:15px}.dlg p{margin:0 0 18px;white-space:pre-wrap;line-height:1.45;font-size:14px;word-break:break-word}.dlg .db{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}';
 function close(){ if(!cur) return; cur.remove(); cur=null; document.removeEventListener('keydown',key,true); try{ if(prevFocus&&prevFocus.focus) prevFocus.focus(); }catch(e){} }
 function key(e){ if(!cur) return; if(e.key==='Escape'){ e.preventDefault(); e.stopPropagation(); cur._cancel(); } else if(e.key==='Tab'){ var b=[].slice.call(cur.querySelectorAll('button')); if(!b.length) return; var i=b.indexOf(document.activeElement); e.preventDefault(); b[(i+(e.shiftKey?-1:1)+b.length)%b.length].focus(); } }
 function show(msg,title,okLabel,danger,onOk,onCancel){
  if(!document.getElementById('dlg-css')){ var s=document.createElement('style'); s.id='dlg-css'; s.textContent=CSS; document.head.appendChild(s); }
  close(); prevFocus=document.activeElement;
  var o=document.createElement('div'); o.className='dlg-o'; o.setAttribute('role','presentation');
  var d=document.createElement('div'); d.className='dlg'; d.setAttribute('role','alertdialog'); d.setAttribute('aria-modal','true');
  var h=document.createElement('h4'); h.textContent=title; var p=document.createElement('p'); p.textContent=msg; var bt=document.createElement('div'); bt.className='db';
  var ok=document.createElement('button'); ok.type='button'; ok.className='btn'+(danger?' danger':''); ok.textContent=okLabel;
  o._cancel=function(){ close(); if(onCancel) onCancel(); };
  ok.onclick=function(){ close(); if(onOk) onOk(); };
  if(onOk){ var ca=document.createElement('button'); ca.type='button'; ca.className='btn'; ca.style.cssText='background:var(--btn,#252c3a);color:var(--tx,#e4e8ef)'; ca.textContent=EN?'Cancel':'Avbryt'; ca.onclick=o._cancel; bt.appendChild(ca); }
  bt.appendChild(ok); d.appendChild(h); d.appendChild(p); d.appendChild(bt); o.appendChild(d);
  o.addEventListener('mousedown',function(e){ if(e.target===o) o._cancel(); });
  document.body.appendChild(o); cur=o; document.addEventListener('keydown',key,true); (onOk&&danger?bt.firstChild:ok).focus(); }
 window.confirm=function(msg){
  if(bypass){ bypass=false; return true; }
  var ev=window.event; if(!ev||!document.body) return nC.call(window,msg);
  var t=ev.target, form=null, sub=null, el=null;
  if(ev.type==='submit'){ form=ev.target; sub=ev.submitter||null; if(!form||!form.requestSubmit) return nC.call(window,msg); }
  else if(ev.type==='click'&&t&&t.click){ el=t; } else return nC.call(window,msg);
  msg=String(msg==null?'':msg); var danger=/ta bort|radera|rensa|delete|remove|clear|återställ|restore|reset/i.test(msg);
  show(msg,EN?'Please confirm':'Bekräfta',danger?(/rensa|clear/i.test(msg)?(EN?'Clear':'Rensa'):(/återställ|restore|reset/i.test(msg)?(EN?'Restore':'Återställ'):(EN?'Delete':'Ta bort'))):'OK',danger,function(){
   bypass=true; try{ if(form) form.requestSubmit(sub); else el.click(); }finally{ bypass=false; } });
  return false; };
 window.alert=function(msg){ if(!document.body){ return; } show(String(msg==null?'':msg),'VM Monitor','OK',false,null,null); };
})();
