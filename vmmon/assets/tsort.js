/* Klickbar sortering i alla tabeller. Klicka på en kolumnrubrik: stigande, klicka igen: fallande.
   Naturlig sortering (1, 2, 10 – inte 1, 10, 2; fungerar även för IP-adresser och namn som Gi1/0/10). Tomma värden hamnar alltid sist.
   Tabeller med data-nosort hoppas över. Detaljrader (tr.gr) följer med raden ovanför. */
(function(){
 var coll=new Intl.Collator(undefined,{numeric:true,sensitivity:'base'});
 function cellVal(td){
  if(!td) return '';
  var d=td.getAttribute('data-sort'); if(d!==null) return d;
  var t=(td.textContent||'').replace(/\s+/g,' ').trim();
  if(/^[\u2013\u2014-]$/.test(t)) return '';  // – betyder saknat värde
  if(t==='') { var e=td.querySelector('[title],[class]'); t=e?(e.getAttribute('title')||e.className||''):''; }
  return t; }
 // Tolkar värden med enhet till ett jämförbart tal: storlek (B–PB), bithastighet, varaktighet (2 h 5 min, 3d 4h 5m, 45 s, 12 ms) och rena tal/procent. null = inte ett tal.
 var SZ={b:0,kb:1,mb:2,gb:3,tb:4,pb:5,kib:1,mib:2,gib:3,tib:4,pib:5}, TM={ms:.001,s:1,sek:1,m:60,min:60,h:3600,tim:3600,timmar:3600,d:86400,dag:86400,dagar:86400,day:86400,days:86400};
 function f(x){ return parseFloat(String(x).replace(',','.')); }
 function num(v){ var m,t=v.trim();
  if((m=/^(-?\d+(?:[.,]\d+)?)\s*(pib|tib|gib|mib|kib|pb|tb|gb|mb|kb|b)(?![a-z\/])/i.exec(t))) return f(m[1])*Math.pow(1024,SZ[m[2].toLowerCase()]);
  if((m=/^(-?\d+(?:[.,]\d+)?)\s*([kmgt]?)\s*(?:bit\/s|bits\/s|bps|b\/s)(?![a-z])/i.exec(t))) return f(m[1])*Math.pow(1000,'kmgt'.indexOf(m[2].toLowerCase())+1);
  if(/^(?:\d+(?:[.,]\d+)?\s*(?:ms|sek|timmar|tim|dagar|dag|days|day|min|[smhd])\b\s*)+$/i.test(t)){ var sum=0,re=/(\d+(?:[.,]\d+)?)\s*(ms|sek|timmar|tim|dagar|dag|days|day|min|[smhd])\b/gi,x; while((x=re.exec(t))) sum+=f(x[1])*TM[x[2].toLowerCase()]; return sum; }
  if(/^-?\d+(?:[.,]\d+)?\s*%?$/.test(t)) return f(t);
  return null; }
 function isDetail(r){ return r.classList.contains('gr'); }
 function isSep(r){ return r.classList.contains('grph'); }
 function setup(tb,idx){
  if(tb.hasAttribute('data-nosort')||tb.getAttribute('data-ts')==='1') return; tb.setAttribute('data-ts','1');
  var all=[].slice.call(tb.rows); if(all.length<3) return;
  var hr=all[0], ths=[].slice.call(hr.cells); if(!ths.length||!ths.every(function(c){return c.tagName==='TH'})) return;
  var body=all.slice(1), par=body[0].parentNode;
  if(!body.every(function(r){return r.parentNode===par})) return;
  // varje datarad måste ha lika många celler som rubriken (annars risk för felkopplade kolumner)
  if(!body.every(function(r){return isDetail(r)||isSep(r)||r.cells.length===ths.length})) return;
  var key='tsort:'+location.pathname+location.search+'#'+idx, state=null;
  ths.forEach(function(th,ci){
   if((th.textContent||'').trim()===''||th.hasAttribute('data-nosort')) return;
   th.classList.add('srt'); th.tabIndex=0; th.setAttribute('role','columnheader'); th.setAttribute('aria-sort','none');
   function go(){ var dir=th.getAttribute('aria-sort')==='ascending'?'descending':'ascending'; var ci=th.cellIndex; sort(ci,dir); try{sessionStorage.setItem(key,JSON.stringify({c:ci,d:dir,t:Date.now()}))}catch(e){} }
   th.addEventListener('click',function(e){ if(e.target.closest('a,button,input,select')) return; go(); });
   th.addEventListener('keydown',function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); go(); } });
  });
  function sort(ci,dir){
   var m=dir==='ascending'?1:-1, segs=[[]], seps=[];  // rader med klassen grph är gruppseparatorer: varje grupp sorteras för sig
   [].slice.call(par.rows).forEach(function(r){ if(r===hr) return; if(isSep(r)){ seps.push(r); segs.push([]); return; } var cur=segs[segs.length-1]; if(isDetail(r)&&cur.length) cur[cur.length-1].push(r); else cur.push([r]); });
   var allg=[].concat.apply([],segs), isNum=true, gi=0;
   allg.forEach(function(g){ g.v=cellVal(g[0].cells[ci]); g.i=gi++; g.n=g.v===''?null:num(g.v); if(g.v!==''&&g.n===null) isNum=false; });  // hela kolumnen är tal med enhet => sortera på värdet
   function cmp(a,b){ var ea=a.v==='',eb=b.v==='',c; if(ea||eb) return ea&&eb?a.i-b.i:(ea?1:-1); c=(isNum?(a.n-b.n):coll.compare(a.v,b.v))*m; return c||a.i-b.i; }
   segs.forEach(function(sg,si){ sg.sort(cmp); if(si>0) par.appendChild(seps[si-1]); sg.forEach(function(g){ g.forEach(function(r){ par.appendChild(r); }); }); });
   ths.forEach(function(th){ if(th.classList.contains('srt')) th.setAttribute('aria-sort',th.cellIndex===ci?dir:'none'); });
  }
  // behåll vald sortering vid sidans automatiska omladdning (loggsidan), men bara en kort stund
  try{ var s=JSON.parse(sessionStorage.getItem(key)||'null'); if(s&&Date.now()-s.t<30000&&ths[s.c]&&ths[s.c].classList.contains('srt')){ sort(s.c,s.d); sessionStorage.setItem(key,JSON.stringify({c:s.c,d:s.d,t:Date.now()})); } }catch(e){}
 }
 function init(){ [].slice.call(document.querySelectorAll('table')).forEach(setup); }
 if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
