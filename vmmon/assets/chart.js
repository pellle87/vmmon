const T=(s,en)=>LANG==='en'?en:s;
const fmtT=(t,span)=>{const d=new Date(t*1000);return span<=172800?d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}):d.toLocaleDateString([],{day:'2-digit',month:'2-digit'})};
const niceMax=v=>{if(v<=0)return 1;const p=Math.pow(10,Math.floor(Math.log10(v))),f=v/p;return(f<=1?1:f<=2?2:f<=5?5:10)*p};
const fmtBps=v=>v>=1e9?(v/1e9).toFixed(v>=1e10?0:1)+' Gb/s':v>=1e6?(v/1e6).toFixed(v>=1e7?0:1)+' Mb/s':v>=1e3?(v/1e3).toFixed(0)+' kb/s':Math.round(v)+' b/s';
const fmtPm=v=>(v>=10?Math.round(v):(+v).toFixed(1))+'/min';
// pts: [{t,k,k_max}], series: [{k,c,n}], o: {w,h,interactive,auto,fmt}
// Linjefärgerna följer temat: standardfärgerna byts mot temats accent/ok/varning/fel-färger (så att de syns även på ljusa teman).
function themeSer(series){const cs=getComputedStyle(document.documentElement),m={'#60a5fa':'--acc','#34d399':'--ok','#fbbf24':'--warn','#f87171':'--bad'};return series.map(s=>{const v=m[String(s.c).toLowerCase()],c=v&&cs.getPropertyValue(v).trim();return c?Object.assign({},s,{c:c}):s})}
function mountChart(el,pts,series,o){o=o||{};series=themeSer(series);el._m=[pts,series,o];el.setAttribute('data-mc','1');const W=o.fit?Math.max(320,el.clientWidth||640):(o.w||640),H=o.h||200,L=o.auto?56:30,R=8,Tp=8,B=20,iw=W-L-R,ih=H-Tp-B,fv=o.fmt||(v=>v+'%');
 if(!pts.length){el.innerHTML='<div class="mut" style="padding:24px 8px">'+T('Ingen data ännu – historiken fylls på medan servern övervakas.','No data yet – history builds up while the server is monitored.')+'</div>';return}
 const t0=pts[0].t,t1=pts[pts.length-1].t,span=Math.max(1,t1-t0);let ymax=100;
 if(o.auto){let mx=0;pts.forEach(p=>series.forEach(s=>{mx=Math.max(mx,p[s.k]||0,p[s.k+'_max']||0)}));ymax=niceMax(mx)}
 const x=t=>L+(t-t0)/span*iw,y=v=>Tp+ih-Math.min(ymax,v)/ymax*ih;let g='';
 [0,.25,.5,.75,1].forEach(f=>{const v=ymax*f;g+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" class="gl"/><text x="${L-4}" y="${y(v)+3}" class="ax" text-anchor="end">${o.auto?fv(v):v}</text>`});
 for(let i=0;i<=4;i++){const t=t0+span*i/4;g+=`<text x="${x(t)}" y="${H-5}" class="ax" text-anchor="${i==0?'start':i==4?'end':'middle'}">${fmtT(t,span)}</text>`}
 const path=k=>{let d='',pen=false;pts.forEach(p=>{const v=p[k];if(v==null){pen=false;return}d+=(pen?'L':'M')+x(p.t).toFixed(1)+','+y(v).toFixed(1)+' ';pen=true});return d};
 series.forEach(s=>{if(span>=3600)g+=`<path d="${path(s.k+'_max')}" fill="none" stroke="${s.c}" stroke-opacity=".3" stroke-width="1"/>`;
  g+=`<path d="${path(s.k)}" fill="none" stroke="${s.c}" stroke-width="1.7" stroke-linejoin="round"/>`;
  if(pts.length===1&&pts[0][s.k]!=null)g+=`<circle cx="${x(t0)}" cy="${y(pts[0][s.k])}" r="3" fill="${s.c}"/>`});
 el.innerHTML=`<div class="cwrap"><svg viewBox="0 0 ${W} ${H}">${g}<line class="cur" y1="${Tp}" y2="${Tp+ih}" visibility="hidden"/></svg><div class="ctip"></div></div>`;
 if(!o.interactive)return;
 const w=$(el).find('.cwrap'),tip=w.find('.ctip'),cur=w.find('.cur');
 w.on('mousemove',ev=>{const r=w[0].getBoundingClientRect(),px=(ev.clientX-r.left)/r.width*W,t=t0+(px-L)/iw*span;let b=pts[0];pts.forEach(p=>{if(Math.abs(p.t-t)<Math.abs(b.t-t))b=p});
  cur.attr({x1:x(b.t),x2:x(b.t),visibility:'visible'});
  tip.html('<b>'+new Date(b.t*1000).toLocaleString()+'</b>'+series.map(s=>b[s.k]==null?'':`<div style="color:${s.c}">${$('<i>').text(s.n??'').html()}: ${fv(b[s.k])}${b[s.k+'_max']!=null&&span>=3600?' (max '+fv(b[s.k+'_max'])+')':''}</div>`).join('')).css({left:Math.max(4,Math.min(ev.clientX-r.left+12,r.width-190)),top:8}).show()})
  .on('mouseleave',()=>{tip.hide();cur.attr('visibility','hidden')});}
let _rt;window.addEventListener('resize',()=>{clearTimeout(_rt);_rt=setTimeout(()=>document.querySelectorAll('[data-mc]').forEach(el=>el._m&&el.clientWidth&&mountChart(el,...el._m)),250)});
