(function(){
var D=window.NM||{nodes:[],links:[],pos:{}},svg=document.getElementById('nm');if(!svg)return;
var NS='http://www.w3.org/2000/svg',W=150,H=46,saved=D.pos||{},ADM=!!window.NMADM,tip=document.getElementById('nmtip');
var N={},deg={};D.nodes.forEach(function(n){N[n.id]=n;n.x=null;n.y=null;deg[n.id]=0});
D.links.forEach(function(l){if(N[l.a]&&N[l.b]){deg[l.a]++;deg[l.b]++}});
var RANK={firewall:0,router:0,switch:1,nas:3,san:3,bmc:3,ap:2,host:2,server:3,unknown:4};
var ISL=[];
function layout(){
 var done={},roots=D.nodes.filter(function(n){return deg[n.id]>0});
 roots.sort(function(a,b){var ra=(RANK[a.k]===0?0:1),rb=(RANK[b.k]===0?0:1);return ra-rb||deg[b.id]-deg[a.id]});
 var adj={};D.links.forEach(function(l){if(!N[l.a]||!N[l.b])return;(adj[l.a]=adj[l.a]||[]).push(l.b);(adj[l.b]=adj[l.b]||[]).push(l.a)});
 var comp=[];roots.forEach(function(r){if(done[r.id])return;var q=[r.id],lv={};lv[r.id]=0;done[r.id]=1;var all=[];
  while(q.length){var c=q.shift();all.push(c);(adj[c]||[]).forEach(function(o){if(!done[o]){done[o]=1;lv[o]=lv[c]+1;q.push(o)}})}
  comp.push({lv:lv,all:all})});
 comp.sort(function(a,b){return b.all.length-a.all.length});
 comp.forEach(function(c,i){var by={};c.all.forEach(function(id){(by[c.lv[id]]=by[c.lv[id]]||[]).push(id)});
  var ks=Object.keys(by).map(Number).sort(function(a,b){return a-b}),maxw=0;ks.forEach(function(k){maxw=Math.max(maxw,by[k].length)});
  c.w=(maxw-1)*(W+30)+W;c.h=(ks.length-1)*110+H;c.loc={};
  ks.forEach(function(k,j){by[k].sort(function(a,b){return (RANK[N[a].k]||5)-(RANK[N[b].k]||5)||N[a].n.localeCompare(N[b].n)});
   var tw=(by[k].length-1)*(W+30)+W;by[k].forEach(function(id,m){c.loc[id]=[(c.w-tw)/2+m*(W+30),j*110]})});
  c.label=(window.NMX.is||'Net')+' '+(i+1)+' · '+N[c.all[0]].n+' · '+c.all.length});
 var iso=D.nodes.filter(function(n){return !deg[n.id]});iso.sort(function(a,b){return a.n.localeCompare(b.n)});
 if(iso.length){var per=Math.max(6,Math.ceil(Math.sqrt(iso.length*2))),c={all:[],loc:{},label:(window.NMX.iu||'Unlinked')+' · '+iso.length};
  iso.forEach(function(n,i){c.all.push(n.id);c.loc[n.id]=[(i%per)*(W+20),Math.floor(i/per)*70]});
  c.w=(Math.min(per,iso.length)-1)*(W+20)+W;c.h=(Math.ceil(iso.length/per)-1)*70+H;comp.push(c)}
 var x=0,y=0,rh=0,LIM=2600,GAP=90;
 comp.forEach(function(c,i){if(x>0&&x+c.w>LIM){x=0;y+=rh+GAP;rh=0}
  c.all.forEach(function(id){N[id].x=x+c.loc[id][0];N[id].y=y+c.loc[id][1];N[id].isl=i});
  x+=c.w+GAP;rh=Math.max(rh,c.h);ISL.push({ids:c.all,label:c.label})});
}
layout();D.nodes.forEach(function(n){if(saved[n.id]){n.x=saved[n.id][0];n.y=saved[n.id][1]}});
D.nodes.forEach(function(n){n.iso=!deg[n.id]});
function GR(){var c=document.getElementById('nmgrid');return !c||c.checked}
function snap(v){return GR()?Math.round(v/10)*10:v}
function el(t,a,p){var e=document.createElementNS(NS,t);for(var k in a)e.setAttribute(k,a[k]);if(p)p.appendChild(e);return e}
var vb={x:-40,y:-40,w:1200,h:700},gL,gN,gI,els={},gr1,gr2,pt1,pt2,pp1,pp2;
function build(){while(svg.firstChild)svg.removeChild(svg.firstChild);var df=el('defs',{},svg);pt1=el('pattern',{id:'nmp1',patternUnits:'userSpaceOnUse'},df);pp1=el('path',{'class':'nmg1',fill:'none'},pt1);
 pt2=el('pattern',{id:'nmp2',patternUnits:'userSpaceOnUse'},df);pp2=el('path',{'class':'nmg2',fill:'none'},pt2);
 gr1=el('rect',{fill:'url(#nmp1)','pointer-events':'none'},svg);gr2=el('rect',{fill:'url(#nmp2)','pointer-events':'none'},svg);
 gI=el('g',{},svg);gL=el('g',{},svg);gN=el('g',{},svg);els={};
 ISL.forEach(function(I){var g=el('g',{'class':'nmi'},gI);I._r=el('rect',{rx:16},g);I._t=el('text',{'class':'nmit'},g);I._t.textContent=I.label;I._g=g;
  var d=null;I._t.addEventListener('pointerdown',function(e){if(e.button||!ADM)return;e.stopPropagation();d={x:e.clientX,y:e.clientY,o:I.ids.map(function(id){return [N[id].x,N[id].y]})};I._t.setPointerCapture(e.pointerId)});
  I._t.addEventListener('pointermove',function(e){if(!d)return;var k=vb.w/svg.getBoundingClientRect().width,dx=snap((e.clientX-d.x)*k),dy=snap((e.clientY-d.y)*k);I.ids.forEach(function(id,i){N[id].x=d.o[i][0]+dx;N[id].y=d.o[i][1]+dy});pos()});
  I._t.addEventListener('pointerup',function(){if(!d)return;d=null;var o={};I.ids.forEach(function(id){o[id]=[N[id].x,N[id].y]});post(o)})});
 D.links.forEach(function(l,i){if(!N[l.a]||!N[l.b])return;var g=el('g',{'class':'nml'+(l.m?' man':'')},gL);l._g=g;l._l=el('line',{},g);l._h=el('line',{'class':'hit'},g);
  l._a=el('text',{'class':'pt'},g);l._b=el('text',{'class':'pt'},g);
  l._h.addEventListener('mousemove',function(e){var s=(l.m?(window.NMX.m+(l.l?': '+l.l:'')):(l.p||''))+' · '+N[l.a].n+(l.ap?' ['+l.ap+']':'')+' ⇄ '+N[l.b].n+(l.bp?' ['+l.bp+']':'');tip.textContent=s;tip.hidden=false;tip.style.left=(e.clientX-svg.getBoundingClientRect().left+12)+'px';tip.style.top=(e.clientY-svg.getBoundingClientRect().top+12)+'px'});
  l._h.addEventListener('mouseleave',function(){tip.hidden=true})});
 D.nodes.forEach(function(n){var g=el('g',{'class':'nmn k-'+n.k+' s-'+n.st},gN);g.setAttribute('data-id',n.id);var r=el('rect',{width:W,height:H,rx:9},g);
  var t=el('text',{x:W/2,y:19,'text-anchor':'middle','class':'nm1'},g);t.textContent=n.n.length>20?n.n.slice(0,19)+'…':n.n;
  var s=el('text',{x:W/2,y:35,'text-anchor':'middle','class':'nm2'},g);s.textContent=n.ip||n.k;els[n.id]=g;n._g=g;
  var moved=false,sx,sy,ox,oy;
  g.addEventListener('pointerdown',function(e){if(e.button)return;e.stopPropagation();sx=e.clientX;sy=e.clientY;ox=n.x;oy=n.y;moved=false;g.setPointerCapture(e.pointerId);
   g._drag=true});
  g.addEventListener('pointermove',function(e){if(!g._drag)return;var k=vb.w/svg.getBoundingClientRect().width,dx=(e.clientX-sx)*k,dy=(e.clientY-sy)*k;if(!moved&&Math.abs(dx)+Math.abs(dy)<4*k)return;if(!ADM&&!moved){moved=true}moved=true;if(ADM){n.x=snap(ox+dx);n.y=snap(oy+dy);pos()}});
  g.addEventListener('pointerup',function(e){g._drag=false;if(!moved){if(n.u)location.href=n.u}else if(ADM){var o={};o[n.id]=[n.x,n.y];post(o)}});
  g.addEventListener('mouseenter',function(){hl(n.id,1)});g.addEventListener('mouseleave',function(){hl(n.id,0)})});
 pos();view()}
function hl(id,on){D.links.forEach(function(l){if(!l._g)return;var m=l.a===id||l.b===id;l._g.classList.toggle('hl',on&&m)})}
function pos(){var showU=document.getElementById('nmunk').checked,showI=document.getElementById('nmiso').checked,ports=document.getElementById('nmports').checked,q=(document.getElementById('nmq').value||'').toLowerCase();
 D.nodes.forEach(function(n){var vis=!((n.k==='unknown'&&!showU)||(n.iso&&!showI&&n.k!=='unknown'));n._v=vis;n._g.style.display=vis?'':'none';n._g.setAttribute('transform','translate('+n.x+','+n.y+')');n._g.classList.toggle('dim',!!q&&n.n.toLowerCase().indexOf(q)<0&&(n.ip||'').indexOf(q)<0);n._g.classList.toggle('hit',!!q&&(n.n.toLowerCase().indexOf(q)>=0||(n.ip||'').indexOf(q)>=0))});
 ISL.forEach(function(I){var x0=1e9,y0=1e9,x1=-1e9,y1=-1e9,any=0;I.ids.forEach(function(id){var n=N[id];if(!n._v)return;any=1;x0=Math.min(x0,n.x);y0=Math.min(y0,n.y);x1=Math.max(x1,n.x+W);y1=Math.max(y1,n.y+H)});
  I._g.style.display=any?'':'none';if(!any)return;I._r.setAttribute('x',x0-26);I._r.setAttribute('y',y0-40);I._r.setAttribute('width',x1-x0+52);I._r.setAttribute('height',y1-y0+66);I._t.setAttribute('x',x0-12);I._t.setAttribute('y',y0-17)});
 D.links.forEach(function(l){if(!l._g)return;var a=N[l.a],b=N[l.b];var vis=a._v&&b._v;l._g.style.display=vis?'':'none';if(!vis)return;
  var x1=a.x+W/2,y1=a.y+H/2,x2=b.x+W/2,y2=b.y+H/2;[l._l,l._h].forEach(function(e){e.setAttribute('x1',x1);e.setAttribute('y1',y1);e.setAttribute('x2',x2);e.setAttribute('y2',y2)});
  l._g.classList.toggle('bad',a.st==='bad'||b.st==='bad');
  function clip(ax,ay,bx,by,t){var dx=bx-ax,dy=by-ay,s=Math.min(Math.abs(W/2/(dx||1e-9)),Math.abs(H/2/(dy||1e-9)));return [ax+dx*s,ay+dy*s,dx,dy]}
  var p1=clip(x1,y1,x2,y2),p2=clip(x2,y2,x1,y1);
  var f=0.2,ax=p1[0]+(p2[0]-p1[0])*f,ay=p1[1]+(p2[1]-p1[1])*f,bx=p2[0]+(p1[0]-p2[0])*f,by=p2[1]+(p1[1]-p2[1])*f;
  l._a.setAttribute('x',ax);l._a.setAttribute('y',ay);l._a.setAttribute('text-anchor','middle');l._a.textContent=ports?l.ap:'';
  l._b.setAttribute('x',bx);l._b.setAttribute('y',by);l._b.setAttribute('text-anchor','middle');l._b.textContent=ports?l.bp:''})}
function view(){svg.setAttribute('viewBox',vb.x+' '+vb.y+' '+vb.w+' '+vb.h);grid()}
function grid(){if(!gr1)return;var on=GR();gr1.style.display=gr2.style.display=on?'':'none';if(!on)return;
 var sc=svg.getBoundingClientRect().width/vb.w||1,st=10;while(st*sc<8)st*=2;var mj=st*5,sw=1/sc;
 pt1.setAttribute('width',st);pt1.setAttribute('height',st);pp1.setAttribute('d','M'+st+' 0H0V'+st);pp1.setAttribute('stroke-width',sw);
 pt2.setAttribute('width',mj);pt2.setAttribute('height',mj);pp2.setAttribute('d','M'+mj+' 0H0V'+mj);pp2.setAttribute('stroke-width',sw);
 [gr1,gr2].forEach(function(r){r.setAttribute('x',vb.x);r.setAttribute('y',vb.y);r.setAttribute('width',vb.w);r.setAttribute('height',vb.h)})}
function fit(){var xs=[],ys=[];D.nodes.forEach(function(n){if(n._v){xs.push(n.x,n.x+W);ys.push(n.y,n.y+H)}});if(!xs.length)return;var x0=Math.min.apply(0,xs),x1=Math.max.apply(0,xs),y0=Math.min.apply(0,ys),y1=Math.max.apply(0,ys),r=svg.getBoundingClientRect(),ar=r.width/Math.max(1,r.height),w=(x1-x0)+80,h=(y1-y0)+80;if(w/h<ar)w=h*ar;else h=w/ar;vb={x:(x0+x1)/2-w/2,y:(y0+y1)/2-h/2,w:w,h:h};view()}
function post(o){if(!ADM)return;var b=new URLSearchParams();b.set('t',window.NMT);b.set('a','pos');b.set('pos',JSON.stringify(o));fetch('netmap.php',{method:'POST',body:b,credentials:'same-origin'})}
svg.addEventListener('wheel',function(e){e.preventDefault();var r=svg.getBoundingClientRect(),f=e.deltaY<0?0.88:1.14,mx=vb.x+(e.clientX-r.left)/r.width*vb.w,my=vb.y+(e.clientY-r.top)/r.height*vb.h;vb.x=mx-(mx-vb.x)*f;vb.y=my-(my-vb.y)*f;vb.w*=f;vb.h*=f;view()},{passive:false});
var pan=null;svg.addEventListener('pointerdown',function(e){if(e.target!==svg&&e.target.closest&&e.target.closest('.nmn'))return;pan={x:e.clientX,y:e.clientY,vx:vb.x,vy:vb.y};svg.setPointerCapture(e.pointerId);svg.classList.add('pan')});
svg.addEventListener('pointermove',function(e){if(!pan)return;var k=vb.w/svg.getBoundingClientRect().width;vb.x=pan.vx-(e.clientX-pan.x)*k;vb.y=pan.vy-(e.clientY-pan.y)*k;view()});
svg.addEventListener('pointerup',function(){pan=null;svg.classList.remove('pan')});
['nmports','nmunk','nmiso','nmq'].forEach(function(id){document.getElementById(id).addEventListener(id==='nmq'?'input':'change',pos)});
var gc=document.getElementById('nmgrid');try{if(gc&&localStorage.getItem('vmmon_nmgrid')==='0')gc.checked=false}catch(e){}
if(gc)gc.addEventListener('change',function(){try{localStorage.setItem('vmmon_nmgrid',gc.checked?'1':'0')}catch(e){}grid()});
window.addEventListener('resize',grid);
document.getElementById('nmfit').addEventListener('click',fit);
build();fit();
})();
