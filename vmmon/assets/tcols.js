/* Kolumnhantering för tabeller med data-tc="<sida>": dra rubriker för att flytta, dra i kanten för att ändra bredd,
   knappen Kolumner visar/döljer kolumner (inklusive extra kolumner som är dolda från start). Allt sparas per användare (api/tcols.php).
   Tabellen själv är schemat: <th data-k="nyckel" class="tx"> = extra kolumn, dold som standard. En rubrik utan text är åtgärdskolumnen och ligger alltid sist. */
(function(){
 var SV=(document.documentElement.lang||'sv')==='sv';
 function L(sv,en){ return SV?sv:en; }

 var openM=null;
 function closeAll(){ document.querySelectorAll('.tcp,.tcm').forEach(function(m){ m.hidden=true; }); openM=null; }
 function place(m,anchor){ var r=anchor.getBoundingClientRect(); m.style.visibility='hidden'; m.style.top='0'; m.style.right='auto'; m.style.left='0'; var w=m.offsetWidth,h=m.offsetHeight;
  var left=Math.max(8,Math.min(window.innerWidth-w-8,r.right-w)), top=r.bottom+4; if(top+h>window.innerHeight-8&&r.top-h-4>8) top=r.top-h-4;
  m.style.left=left+'px'; m.style.top=top+'px'; m.style.visibility=''; }
 document.addEventListener('click',function(e){ if(openM&&!openM.contains(e.target)) closeAll(); });
 window.addEventListener('resize',closeAll); window.addEventListener('scroll',function(e){ if(openM&&!openM.contains(e.target)) closeAll(); },true);
 // åtgärdsknapparna i sista kolumnen samlas i en rullgardinsmeny
 function actions(tb){ [].slice.call(tb.querySelectorAll('td.inline')).forEach(function(td){
   if(td.getAttribute('data-tcm')||!td.children.length) return; td.setAttribute('data-tcm','1');
   var b=document.createElement('button'); b.type='button'; b.className='btn sm tcmb'; b.textContent=L('Hantera','Modify')+' ▾';
   var m=document.createElement('div'); m.className='tcm'; m.hidden=true; while(td.firstChild) m.appendChild(td.firstChild); td.appendChild(b); td.appendChild(m);
   [].forEach.call(m.querySelectorAll('.btn'),function(x){ x.classList.remove('danger','warn','go'); });
   [].forEach.call(m.querySelectorAll('form'),function(f){ var bt=f.querySelector('button'), ai=f.querySelector('input[name=a]'); var del=(ai&&/^del/i.test(ai.value))||/^(ta bort|delete)$/i.test(((bt&&bt.textContent)||'').trim()); if(!del) return;
    var oc=f.getAttribute('onsubmit')||'', mm=/confirm\('([\s\S]*)'\)/.exec(oc), msg=mm?mm[1].replace(/\\'/g,"'"):L('Är du säker på att du vill ta bort detta? Det går inte att ångra.','Are you sure you want to delete this? This cannot be undone.');
    f.removeAttribute('onsubmit'); f.addEventListener('submit',function(ev){ if(!window.confirm(msg)) ev.preventDefault(); }); });
   b.addEventListener('click',function(e){ e.stopPropagation(); var o=m.hidden; closeAll(); if(o){ m.hidden=false; place(m,b); openM=m; } }); }); }
 function setup(tb){
  if(tb.getAttribute('data-tcd')==='1') return; var hr=tb.rows[0]; if(!hr) return;
  var ths=[].slice.call(hr.cells), n=ths.length;
  var rows=[].slice.call(tb.rows).slice(1).filter(function(r){ return r.cells.length===n; });
  // tabeller utan åtgärdskolumn får en tom sista kolumn där Kolumner-knappen ligger
  var lastTh=ths[n-1]; if(!((lastTh.textContent||'').trim()===''&&!lastTh.getAttribute('data-k'))){ var nt=document.createElement('th'); hr.appendChild(nt); ths.push(nt); rows.forEach(function(r){ r.appendChild(document.createElement('td')); }); n++; }
  var cols=ths.map(function(th,i){ var k=th.getAttribute('data-k')||('c'+i); var act=(th.textContent||'').trim()===''&&!th.getAttribute('data-k'); return {k:k,th:th,label:(th.getAttribute('data-l')||th.textContent||k).replace(/\s+/g,' ').trim(),x:th.classList.contains('tx'),act:act,cells:rows.map(function(r){ return r.cells[i]; })}; });
  var by={}; cols.forEach(function(c){ by[c.k]=c; });
  var defOrder=cols.filter(function(c){ return !c.act; }).map(function(c){ return c.k; }), act=cols.filter(function(c){ return c.act; });
  var defVis=cols.filter(function(c){ return !c.act&&!c.x; }).map(function(c){ return c.k; });
  var cfg=null; try{ var raw=tb.getAttribute('data-cfg'); if(raw) cfg=JSON.parse(raw); }catch(e){}
  var st={o:defOrder.slice(),v:defVis.slice(),w:{}};
  if(cfg&&typeof cfg==='object'){
   var o=(cfg.o||[]).filter(function(k){ return by[k]&&!by[k].act; }); defOrder.forEach(function(k){ if(o.indexOf(k)<0) o.push(k); }); st.o=o;
   var known={}; (cfg.o||[]).forEach(function(k){ known[k]=1; });
   st.v=o.filter(function(k){ return known[k]?(cfg.v||[]).indexOf(k)>=0:!by[k].x; });
   if(cfg.w&&!Array.isArray(cfg.w)) st.w=cfg.w; }
  // omslag för sidscroll vid breda tabeller
  var wrap=document.createElement('div'); wrap.className='tcw'; tb.parentNode.insertBefore(wrap,tb); wrap.appendChild(tb);
  var btn=document.createElement('button'); btn.type='button'; btn.className='tcb'; btn.textContent=L('Kolumner','Columns')+' ▾';
  var pop=document.createElement('div'); pop.className='tcp'; pop.hidden=true; document.body.appendChild(pop);
  var actTh=cols.filter(function(c){ return c.act; })[0]; if(actTh){ actTh.th.setAttribute('data-nosort','1'); actTh.th.classList.add('tca'); actTh.th.appendChild(btn); }

  function cellTitles(){ rows.forEach(function(r){ [].forEach.call(r.cells,function(td){ if(td.title||td.classList.contains('inline')||td.querySelector('a,button,form,input,.bc,div')) return; var t=(td.textContent||'').replace(/\s+/g,' ').trim(); if(t.length>3) td.title=t; }); }); }
  var timer=null, key=tb.getAttribute('data-tc');
  function save(reset){ clearTimeout(timer); timer=setTimeout(function(){ var body={t:tb.getAttribute('data-t'),k:'cols:'+key,v:reset?'':JSON.stringify(st)};
   try{ var x=new XMLHttpRequest(); x.open('POST','api/tcols.php'); x.setRequestHeader('Content-Type','application/x-www-form-urlencoded'); x.send(Object.keys(body).map(function(q){ return q+'='+encodeURIComponent(body[q]); }).join('&')); }catch(e){} },400); }
  function vis(k){ return st.v.indexOf(k)>=0; }
  function apply(){
   var order=st.o.concat(act.map(function(c){ return c.k; }));
   order.forEach(function(k){ var c=by[k]; hr.appendChild(c.th); c.cells.forEach(function(td,ri){ if(td&&rows[ri]) rows[ri].appendChild(td); }); });
   cols.forEach(function(c){ var h=!c.act&&!vis(c.k); c.th.classList.toggle('tc-h',h); c.cells.forEach(function(td){ if(td) td.classList.toggle('tc-h',h); }); });
   if(!c0){ c0=1; cols.forEach(function(c){ if(!c.act&&vis(c.k)) c.nat=c.th.offsetWidth; }); }
   widths(); drawPop(); }
  var c0=0;
  function widths(){
   var tw=tb.offsetWidth||(tb.parentNode&&tb.parentNode.clientWidth)||1000, actW=act.length?(tb.querySelector('td.inline')?130:125):0, avail; (function(){ var mx=0; [].forEach.call(tb.rows,function(r){ var td=r.cells[r.cells.length-1]; if(td&&td.tagName==='TD'&&!td.classList.contains('inline')){ var w=0; [].forEach.call(td.children,function(ch){ w+=ch.offsetWidth+6; }); mx=Math.max(mx,w); } }); if(mx+34>actW) actW=mx+34; })();
   avail=Math.max(120,tw-actW);
   var vc=cols.filter(function(c){ return !c.act&&vis(c.k); }), wt=vc.map(function(c){ return st.w[c.k]||c.nat||120; }), sum=wt.reduce(function(a,b){ return a+b; },0)||1;
   tb.classList.add('tc-fix'); tb.style.width='100%';
   cols.forEach(function(c){ if(!c.act&&!vis(c.k)) c.th.style.width=''; });
   vc.forEach(function(c,i){ c.th.style.width=(wt[i]/sum*avail/tw*100).toFixed(3)+'%'; });
   act.forEach(function(c){ c.th.style.width=(actW/tw*100).toFixed(3)+'%'; var ix=c.th.cellIndex; [].forEach.call(tb.rows,function(r,ri){ if(ri&&r.cells[ix]) r.cells[ix].classList.add('tcact'); }); }); }
  function snapshot(){ cols.forEach(function(c){ if(!c.act&&vis(c.k)){ var w=c.th.offsetWidth; if(w) st.w[c.k]=w; } }); }
  function move(k,target,after){ var o=st.o.filter(function(x){ return x!==k; }); var i=o.indexOf(target); o.splice(after?i+1:i,0,k); st.o=o; apply(); save(); }
  // dubbelklick på kolumnkant: bredden anpassas till den bredaste innehållet (som Excel)
  function autofit(c){ snapshot(); var vc=cols.filter(function(q){ return !q.act&&vis(q.k); }), hs=[].slice.call(tb.querySelectorAll('.tcr')), m=0, rg=document.createRange();
   hs.forEach(function(x){ x.style.display='none'; });
   [c.th].concat(c.cells).forEach(function(el){ if(!el||el.classList.contains('tc-h')) return; var w;
    if(el.tagName==='TD'&&el.querySelector('.bc,div,progress,table')){ w=c.nat||0; } else { rg.selectNodeContents(el); w=rg.getBoundingClientRect().width; var cs=getComputedStyle(el); w+=(parseFloat(cs.paddingLeft)||0)+(parseFloat(cs.paddingRight)||0)+(parseFloat(cs.borderLeftWidth)||0)+(parseFloat(cs.borderRightWidth)||0); if(el.tagName==='TH') w+=14; }
    if(w>m) m=w; });
   hs.forEach(function(x){ x.style.display=''; });
   var others=vc.filter(function(q){ return q!==c; }), cur=st.w[c.k]||c.th.offsetWidth, tot=others.reduce(function(a,q){ return a+(st.w[q.k]||q.th.offsetWidth); },0), want=Math.max(40,Math.ceil(m)+2), maxW=cur+tot-others.length*40; if(want>maxW) want=Math.max(40,maxW);
   var d=want-cur; if(others.length&&tot>0){ others.forEach(function(q){ var w=st.w[q.k]||q.th.offsetWidth; st.w[q.k]=Math.max(40,w-d*(w/tot)); }); }
   st.w[c.k]=want; widths(); save(); }
  // dra och släpp rubriker
  var dragK=null;
  cols.forEach(function(c){ if(c.act) return; var th=c.th; th.draggable=true; th.classList.add('tcd');
   th.addEventListener('dragstart',function(e){ if(rs){ e.preventDefault(); return; } dragK=c.k; try{ e.dataTransfer.setData('text/plain',c.k); e.dataTransfer.effectAllowed='move'; }catch(x){} th.classList.add('tc-drag'); });
   th.addEventListener('dragover',function(e){ if(dragK&&dragK!==c.k){ e.preventDefault(); var r=th.getBoundingClientRect(), a=e.clientX>r.left+r.width/2; th.classList.toggle('tc-ovl',!a); th.classList.toggle('tc-ovr',a); } });
   th.addEventListener('dragleave',function(){ th.classList.remove('tc-ovl','tc-ovr'); });
   th.addEventListener('drop',function(e){ e.preventDefault(); var r=th.getBoundingClientRect(), a=e.clientX>r.left+r.width/2; th.classList.remove('tc-ovl','tc-ovr'); if(dragK&&dragK!==c.k) move(dragK,c.k,a); dragK=null; });
   th.addEventListener('dragend',function(){ dragK=null; cols.forEach(function(q){ q.th.classList.remove('tc-drag','tc-ovl','tc-ovr'); }); });
   // ändra bredd
   var h=document.createElement('span'); h.className='tcr'; th.appendChild(h);
   h.addEventListener('mousedown',function(e){ e.preventDefault(); e.stopPropagation(); snapshot(); var vc=cols.filter(function(q){ return !q.act&&vis(q.k); }), nx=vc[vc.indexOf(c)+1]||null, x0=e.clientX, a0=st.w[c.k]||th.offsetWidth, b0=nx?(st.w[nx.k]||nx.th.offsetWidth):0; rs=true; document.body.classList.add('tc-resizing');
    function mv(ev){ var dx=ev.clientX-x0; if(nx){ dx=Math.max(40-a0,Math.min(b0-40,dx)); st.w[c.k]=a0+dx; st.w[nx.k]=b0-dx; } else st.w[c.k]=Math.max(40,a0+dx); widths(); }
    function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); document.body.classList.remove('tc-resizing'); setTimeout(function(){ rs=false; },60); save(); }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up); });
   h.addEventListener('click',function(e){ e.stopPropagation(); });
   h.addEventListener('dblclick',function(e){ e.stopPropagation(); e.preventDefault(); autofit(c); });
  });
  var rs=false;
  tb.addEventListener('click',function(e){ if(rs){ e.stopPropagation(); e.preventDefault(); } },true);
  // kolumnmeny
  function drawPop(){ pop.innerHTML=''; var tt=document.createElement('div'); tt.className='tch'; tt.textContent=L('Visa kolumner','Show columns'); pop.appendChild(tt);
   st.o.forEach(function(k){ var c=by[k], lab=document.createElement('label'); lab.className='tci'; var cb=document.createElement('input'); cb.type='checkbox'; cb.checked=vis(k); cb.disabled=cb.checked&&st.v.length<=1;
    cb.addEventListener('change',function(){ if(cb.checked){ if(st.v.indexOf(k)<0) st.v.push(k); } else st.v=st.v.filter(function(x){ return x!==k; }); apply(); save(); });
    var sp=document.createElement('span'); sp.textContent=c.label; lab.appendChild(cb); lab.appendChild(sp); pop.appendChild(lab); });
   var rb=document.createElement('button'); rb.type='button'; rb.className='btn sm danger tcrs'; rb.textContent=L('Återställ standard','Reset to default');
   rb.addEventListener('click',function(){ st={o:defOrder.slice(),v:defVis.slice(),w:{}}; apply(); save(true); }); pop.appendChild(rb); }
  btn.addEventListener('click',function(e){ e.stopPropagation(); var o=pop.hidden; closeAll(); if(o){ pop.hidden=false; place(pop,btn); openM=pop; } });
  // en rad som byts ut via ajax (Testa nu m.m.) kommer från servern i grundordning: lägg in den i kolumnschemat igen
  tb._tcRow=function(oldTr,newTr){ var ix=rows.indexOf(oldTr); if(ix<0) return false; while(newTr.cells.length<n) newTr.appendChild(document.createElement('td')); rows[ix]=newTr; cols.forEach(function(c,i){ c.cells[ix]=newTr.cells[i]; }); actions(tb); cellTitles(); apply(); return true; };
  tb.setAttribute('data-tcd','1'); actions(tb); cellTitles(); apply(); window.addEventListener('resize',widths);
 }
 function init(){ [].slice.call(document.querySelectorAll('table[data-tc]')).forEach(setup); }
 if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
