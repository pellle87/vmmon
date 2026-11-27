(function(){
var D=window.NM||{nodes:[],links:[],pos:{}},svg=document.getElementById('nm');if(!svg)return;
var NS='http://www.w3.org/2000/svg',W=150,H=46,saved=D.pos||{},ADM=!!window.NMADM,tip=document.getElementById('nmtip');
var N={},deg={};D.nodes.forEach(function(n){N[n.id]=n;n.x=null;n.y=null;deg[n.id]=0});
D.links.forEach(function(l){if(N[l.a]&&N[l.b]){deg[l.a]++;deg[l.b]++}});
var RANK={firewall:0,router:0,switch:1,nas:3,san:3,bmc:3,ap:2,host:2,server:3,unknown:4};
function layout(){
 var done={},layers=[],roots=D.nodes.filter(function(n){return deg[n.id]>0});
 // rot: brandvägg/router med flest kopplingar, annars flest kopplingar
 roots.sort(function(a,b){var ra=(RANK[a.k]===0?0:1),rb=(RANK[b.k]===0?0:1);return ra-rb||deg[b.id]-deg[a.id]});
 var adj={};D.links.forEach(function(l){if(!N[l.a]||!N[l.b])return;(adj[l.a]=adj[l.a]||[]).push(l.b);(adj[l.b]=adj[l.b]||[]).push(l.a)});
 var comp=[];roots.forEach(function(r){if(done[r.id])return;var q=[r.id],lv={};lv[r.id]=0;done[r.id]=1;var all=[];
  while(q.length){var c=q.shift();all.push(c);(adj[c]||[]).forEach(function(o){if(!done[o]){done[o]=1;lv[o]=lv[c]+1;q.push(o)}})}
  comp.push({lv:lv,all:all})});
 var y0=0,rowsAll=[];
 comp.forEach(function(c){var by={};c.all.forEach(function(id){(by[c.lv[id]]=by[c.lv[id]]||[]).push(id)});
  Object.keys(by).map(Number).sort(function(a,b){return a-b}).forEach(function(k){by[k].sort(function(a,b){return (RANK[N[a].k]||5)-(RANK[N[b].k]||5)||N[a].n.localeCompare(N[b].n)});rowsAll.push({y:y0,ids:by[k]});y0+=110});
  y0+=40});
 var iso=D.nodes.filter(function(n){return !deg[n.id]});iso.sort(function(a,b){return a.n.localeCompare(b.n)});
 var per=Math.max(6,Math.ceil(Math.sqrt(iso.length*2)));
 iso.forEach(function(n,i){n.x=(i%per)*(W+20);n.y=y0+40+Math.floor(i/per)*70;n.iso=1});
 var maxw=0;rowsAll.forEach(function(r){maxw=Math.max(maxw,r.ids.length)});
 rowsAll.forEach(function(r){var tw=r.ids.length*(W+30);r.ids.forEach(function(id,i){N[id].x=(maxw*(W+30)-tw)/2+i*(W+30);N[id].y=r.y})});
}
layout();D.nodes.forEach(function(n){if(saved[n.id]){n.x=saved[n.id][0];n.y=saved[n.id][1]}});
D.nodes.forEach(function(n){n.iso=!deg[n.id]});
function el(t,a,p){var e=document.createElementNS(NS,t);for(var k in a)e.setAttribute(k,a[k]);if(p)p.appendChild(e);return e}
var vb={x:-40,y:-40,w:1200,h:700},gL,gN,els={};
function build(){while(svg.firstChild)svg.removeChild(svg.firstChild);gL=el('g',{},svg);gN=el('g',{},svg);els={};
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
  g.addEventListener('pointermove',function(e){if(!g._drag)return;var k=vb.w/svg.getBoundingClientRect().width,dx=(e.clientX-sx)*k,dy=(e.clientY-sy)*k;if(!moved&&Math.abs(dx)+Math.abs(dy)<4*k)return;if(!ADM&&!moved){moved=true}moved=true;if(ADM){n.x=ox+dx;n.y=oy+dy;pos()}});
  g.addEventListener('pointerup',function(e){g._drag=false;if(!moved){if(n.u)location.href=n.u}else if(ADM){var o={};o[n.id]=[n.x,n.y];post(o)}});
  g.addEventListener('mouseenter',function(){hl(n.id,1)});g.addEventListener('mouseleave',function(){hl(n.id,0)})});
 pos();view()}
function hl(id,on){D.links.forEach(function(l){if(!l._g)return;var m=l.a===id||l.b===id;l._g.classList.toggle('hl',on&&m)})}
function pos(){var showU=document.getElementById('nmunk').checked,showI=document.getElementById('nmiso').checked,ports=document.getElementById('nmports').checked,q=(document.getElementById('nmq').value||'').toLowerCase();
 D.nodes.forEach(function(n){var vis=!((n.k==='unknown'&&!showU)||(n.iso&&!showI&&n.k!=='unknown'));n._v=vis;n._g.style.display=vis?'':'none';n._g.setAttribute('transform','translate('+n.x+','+n.y+')');n._g.classList.toggle('dim',!!q&&n.n.toLowerCase().indexOf(q)<0&&(n.ip||'').indexOf(q)<0);n._g.classList.toggle('hit',!!q&&(n.n.toLowerCase().indexOf(q)>=0||(n.ip||'').indexOf(q)>=0))});
 D.links.forEach(function(l){if(!l._g)return;var a=N[l.a],b=N[l.b];var vis=a._v&&b._v;l._g.style.display=vis?'':'none';if(!vis)return;
  var x1=a.x+W/2,y1=a.y+H/2,x2=b.x+W/2,y2=b.y+H/2;[l._l,l._h].forEach(function(e){e.setAttribute('x1',x1);e.setAttribute('y1',y1);e.setAttribute('x2',x2);e.setAttribute('y2',y2)});
  l._g.classList.toggle('bad',a.st==='bad'||b.st==='bad');
  function clip(ax,ay,bx,by,t){var dx=bx-ax,dy=by-ay,s=Math.min(Math.abs(W/2/(dx||1e-9)),Math.abs(H/2/(dy||1e-9)));return [ax+dx*s,ay+dy*s,dx,dy]}
  var p1=clip(x1,y1,x2,y2),p2=clip(x2,y2,x1,y1);
  var f=0.2,ax=p1[0]+(p2[0]-p1[0])*f,ay=p1[1]+(p2[1]-p1[1])*f,bx=p2[0]+(p1[0]-p2[0])*f,by=p2[1]+(p1[1]-p2[1])*f;
  l._a.setAttribute('x',ax);l._a.setAttribute('y',ay);l._a.setAttribute('text-anchor','middle');l._a.textContent=ports?l.ap:'';
  l._b.setAttribute('x',bx);l._b.setAttribute('y',by);l._b.setAttribute('text-anchor','middle');l._b.textContent=ports?l.bp:''})}
function view(){svg.setAttribute('viewBox',vb.x+' '+vb.y+' '+vb.w+' '+vb.h)}
function fit(){var xs=[],ys=[];D.nodes.forEach(function(n){if(n._v){xs.push(n.x,n.x+W);ys.push(n.y,n.y+H)}});if(!xs.length)return;var x0=Math.min.apply(0,xs),x1=Math.max.apply(0,xs),y0=Math.min.apply(0,ys),y1=Math.max.apply(0,ys),r=svg.getBoundingClientRect(),ar=r.width/Math.max(1,r.height),w=(x1-x0)+80,h=(y1-y0)+80;if(w/h<ar)w=h*ar;else h=w/ar;vb={x:(x0+x1)/2-w/2,y:(y0+y1)/2-h/2,w:w,h:h};view()}
function post(o){if(!ADM)return;var b=new URLSearchParams();b.set('t',window.NMT);b.set('a','pos');b.set('pos',JSON.stringify(o));fetch('netmap.php',{method:'POST',body:b,credentials:'same-origin'})}
svg.addEventListener('wheel',function(e){e.preventDefault();var r=svg.getBoundingClientRect(),f=e.deltaY<0?0.88:1.14,mx=vb.x+(e.clientX-r.left)/r.width*vb.w,my=vb.y+(e.clientY-r.top)/r.height*vb.h;vb.x=mx-(mx-vb.x)*f;vb.y=my-(my-vb.y)*f;vb.w*=f;vb.h*=f;view()},{passive:false});
var pan=null;svg.addEventListener('pointerdown',function(e){if(e.target!==svg&&e.target.closest&&e.target.closest('.nmn'))return;pan={x:e.clientX,y:e.clientY,vx:vb.x,vy:vb.y};svg.setPointerCapture(e.pointerId);svg.classList.add('pan')});
svg.addEventListener('pointermove',function(e){if(!pan)return;var k=vb.w/svg.getBoundingClientRect().width;vb.x=pan.vx-(e.clientX-pan.x)*k;vb.y=pan.vy-(e.clientY-pan.y)*k;view()});
svg.addEventListener('pointerup',function(){pan=null;svg.classList.remove('pan')});
['nmports','nmunk','nmiso','nmq'].forEach(function(id){document.getElementById(id).addEventListener(id==='nmq'?'input':'change',pos)});
document.getElementById('nmfit').addEventListener('click',fit);
build();fit();
})();
