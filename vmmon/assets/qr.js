/* Minimal QR code generator (byte mode, error correction M, versions 1-20). Own implementation, no dependencies. */
(function(g){
var ECC=[0,10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26],BLK=[0,1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14];
function rawMods(v){var r=(16*v+128)*v+64;if(v>=2){var a=Math.floor(v/7)+2;r-=(25*a-10)*a-55;if(v>=7)r-=36}return r}
function dataCw(v){return Math.floor(rawMods(v)/8)-ECC[v]*BLK[v]}
var EXP=[],LOG=[];(function(){var x=1;for(var i=0;i<255;i++){EXP[i]=x;LOG[x]=i;x<<=1;if(x&256)x^=0x11d}for(i=255;i<512;i++)EXP[i]=EXP[i-255]})();
function mul(a,b){return a&&b?EXP[LOG[a]+LOG[b]]:0}
function rsGen(d){var r=[1];for(var i=0;i<d;i++){var n=new Array(r.length+1).fill(0);for(var j=0;j<r.length;j++){n[j]^=r[j];n[j+1]^=mul(r[j],EXP[i])}r=n}return r}
function rsRem(data,d){var gen=rsGen(d),res=new Array(d).fill(0);data.forEach(function(b){var f=b^res.shift();res.push(0);for(var i=0;i<d;i++)res[i]^=mul(gen[i+1],f)});return res}
function utf8(s){return Array.from(unescape(encodeURIComponent(s)),function(c){return c.charCodeAt(0)})}
function build(text){
 var by=utf8(text),v,bits;
 for(v=1;v<=20;v++){var cc=v<10?8:16,need=4+cc+by.length*8;if(need<=dataCw(v)*8)break}
 if(v>20)throw new Error('too long');
 bits=[];function put(val,n){for(var i=n-1;i>=0;i--)bits.push((val>>>i)&1)}
 put(4,4);put(by.length,v<10?8:16);by.forEach(function(b){put(b,8)});
 var cap=dataCw(v)*8;put(0,Math.min(4,cap-bits.length));while(bits.length%8)bits.push(0);
 for(var p=0xEC;bits.length<cap;p^=0xEC^0x11)put(p,8);
 var cw=[];for(var i=0;i<bits.length;i+=8){var b=0;for(var j=0;j<8;j++)b=b<<1|bits[i+j];cw.push(b)}
 var nb=BLK[v],ecl=ECC[v],raw=Math.floor(rawMods(v)/8),ns=nb-raw%nb,sl=Math.floor(raw/nb),blocks=[],k=0;
 for(i=0;i<nb;i++){var dl=sl-ecl+(i<ns?0:1),d=cw.slice(k,k+dl);k+=dl;var e=rsRem(d,ecl);if(i<ns)d.push(0);blocks.push([d,e,i<ns])}
 var all=[];for(i=0;i<sl-ecl+1;i++)blocks.forEach(function(bl,idx){if(i!==sl-ecl||!bl[2])all.push(bl[0][i])});
 for(i=0;i<ecl;i++)blocks.forEach(function(bl){all.push(bl[1][i])});
 return place(v,all);
}
function place(v,all){
 var n=v*4+17,m=[],f=[];for(var y=0;y<n;y++){m.push(new Array(n).fill(false));f.push(new Array(n).fill(false))}
 function set(x,y,d){if(x>=0&&x<n&&y>=0&&y<n){m[y][x]=d;f[y][x]=true}}
 function finder(cx,cy){for(var dy=-4;dy<=4;dy++)for(var dx=-4;dx<=4;dx++){var d=Math.max(Math.abs(dx),Math.abs(dy));set(cx+dx,cy+dy,d!==2&&d!==4)}}
 finder(3,3);finder(n-4,3);finder(3,n-4);
 for(var i=0;i<n;i++){set(6,i,i%2===0);set(i,6,i%2===0)}
 finder(3,3);finder(n-4,3);finder(3,n-4);
 if(v>=2){var na=Math.floor(v/7)+2,st=Math.ceil((v*4+4)/(na*2-2))*2,pos=[6];for(var pp=n-7;pos.length<na;pp-=st)pos.splice(1,0,pp);
  pos.forEach(function(ax,ai){pos.forEach(function(ay,aj){if((ai===0&&aj===0)||(ai===0&&aj===na-1)||(ai===na-1&&aj===0))return;for(var dy=-2;dy<=2;dy++)for(var dx=-2;dx<=2;dx++)set(ax+dx,ay+dy,Math.max(Math.abs(dx),Math.abs(dy))!==1)})})}
 function fmt(mask){var d=0<<3|mask,r=d;for(var i=0;i<10;i++)r=(r<<1)^((r>>>9)*0x537);var bits=((d<<10)|r)^0x5412;
  for(i=0;i<=5;i++)set(8,i,((bits>>>i)&1)!==0);set(8,7,((bits>>>6)&1)!==0);set(8,8,((bits>>>7)&1)!==0);set(7,8,((bits>>>8)&1)!==0);for(i=9;i<15;i++)set(14-i,8,((bits>>>i)&1)!==0);
  for(i=0;i<8;i++)set(n-1-i,8,((bits>>>i)&1)!==0);for(i=8;i<15;i++)set(8,n-15+i,((bits>>>i)&1)!==0);set(8,n-8,true)}
 fmt(0);
 if(v>=7){var r=v;for(i=0;i<12;i++)r=(r<<1)^((r>>>11)*0x1F25);var vb=(v<<12)|r;for(i=0;i<18;i++){var bit=((vb>>>i)&1)!==0,a=n-11+i%3,b=Math.floor(i/3);set(a,b,bit);set(b,a,bit)}}
 var idx=0,bitsAll=[];all.forEach(function(c){for(var i=7;i>=0;i--)bitsAll.push((c>>>i)&1)});
 for(var right=n-1;right>=1;right-=2){if(right===6)right=5;for(var vert=0;vert<n;vert++)for(var j=0;j<2;j++){var x=right-j,up=((right+1)&2)===0,y=up?n-1-vert:vert;if(!f[y][x]&&idx<bitsAll.length){m[y][x]=bitsAll[idx++]===1}}}
 var fm=f.map(function(r){return r.slice()});
 function apply(mask){for(var y=0;y<n;y++)for(var x=0;x<n;x++){if(fm[y][x])continue;var inv;switch(mask){case 0:inv=(x+y)%2===0;break;case 1:inv=y%2===0;break;case 2:inv=x%3===0;break;case 3:inv=(x+y)%3===0;break;case 4:inv=(Math.floor(x/3)+Math.floor(y/2))%2===0;break;case 5:inv=x*y%2+x*y%3===0;break;case 6:inv=(x*y%2+x*y%3)%2===0;break;default:inv=((x+y)%2+x*y%3)%2===0}if(inv)m[y][x]=!m[y][x]}}
 function pen(){var s=0,y,x,run,i;for(var pass=0;pass<2;pass++){for(y=0;y<n;y++){run=1;for(x=1;x<n;x++){var a=pass?m[x][y]:m[y][x],b=pass?m[x-1][y]:m[y][x-1];if(a===b){run++;if(run===5)s+=3;else if(run>5)s++}else run=1}}}
  for(y=0;y<n-1;y++)for(x=0;x<n-1;x++){var c=m[y][x];if(c===m[y][x+1]&&c===m[y+1][x]&&c===m[y+1][x+1])s+=3}
  var pat=[1,0,1,1,1,0,1,0,0,0,0],pat2=pat.slice().reverse();
  for(pass=0;pass<2;pass++)for(y=0;y<n;y++)for(x=0;x<=n-11;x++){var ok1=true,ok2=true;for(i=0;i<11;i++){var c2=(pass?m[x+i][y]:m[y][x+i])?1:0;if(c2!==pat[i])ok1=false;if(c2!==pat2[i])ok2=false}if(ok1||ok2)s+=40}
  var dark=0;m.forEach(function(r){r.forEach(function(c){if(c)dark++})});s+=(Math.ceil(Math.abs(dark*20-n*n*10)/(n*n))-1)*10;return s}
 var best=0,bp=1e9;for(var mk=0;mk<8;mk++){apply(mk);fmt(mk);var p=pen();if(p<bp){bp=p;best=mk}apply(mk)}
 apply(best);fmt(best);return m;
}
g.qrMatrix=function(t){return build(t)};
g.qrSvg=function(text,px){var m=build(text),n=m.length,q=4,s='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '+(n+q*2)+' '+(n+q*2)+'" width="'+(px||220)+'" height="'+(px||220)+'" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="';
 for(var y=0;y<n;y++)for(var x=0;x<n;x++)if(m[y][x])s+='M'+(x+q)+' '+(y+q)+'h1v1h-1z';return s+'"/></svg>'};
if(typeof module!=='undefined')module.exports=g;
})(typeof window!=='undefined'?window:globalThis);
