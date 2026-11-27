(function(){if(window.__msgraph)return;window.__msgraph=1;
var EN=(typeof LANG!=='undefined'&&LANG==='en'),tx=function(s,e){return EN?e:s};
var L=[['24h','24h'],['7d',tx('7 dagar','7 days')],['30d',tx('30 dagar','30 days')],['all',tx('Allt','All time')]];
function fmt(v){return (v>=100?Math.round(v):Math.round(v*10)/10)+' ms'}
function ready(cb){if(typeof mountChart!=='undefined')return cb();var s=document.createElement('script');s.src='assets/chart.js';s.onload=cb;document.head.appendChild(s)}
function draw(tr,id,r){var box=tr.find('.gc')[0];ready(function(){$.getJSON('api/svc_ms.php',{id:id,range:r},function(d){
 mountChart(box,d.points,[{k:'ms',c:'#34d399',n:tx('Svarstid','Response')}],{fit:true,h:170,interactive:true,auto:true,fmt:fmt});
 var v=d.points.map(function(x){return x.ms}),m=d.points.map(function(x){return x.ms_max}),lg=tr.find('.lg');
 lg.text(v.length?tx('snitt','avg')+' '+fmt(v.reduce(function(a,b){return a+b},0)/v.length)+' \u00b7 max '+fmt(Math.max.apply(null,m))+' \u00b7 min '+fmt(Math.min.apply(null,v)):'')}).fail(function(){box.innerHTML='<div class="mut" style="padding:20px 8px">'+tx('Kunde inte hämta historik (har sql/migrate_v23.sql körts?).','Could not load history (has sql/migrate_v23.sql been run?).')+'</div>'})})}
$(document).on('click','tr.svr',function(ev){if($(ev.target).closest('a,button,form,input,select,.inline').length)return;var tr=$(this),nx=tr.next('tr.gr');if(nx.length){nx.remove();tr.removeClass('open');return}
 var id=tr.data('id'),g=$('<tr class="gr"><td colspan="12"><div class="rngs"></div><div class="gc"></div><div class="lg sub"></div></td></tr>');
 L.forEach(function(x,i){$('<button type="button" class="btn sm'+(i?'':' on')+'" data-r="'+x[0]+'">'+x[1]+'</button>').appendTo(g.find('.rngs'))});
 g.find('.rngs').on('click','.btn',function(){g.find('.rngs .btn').removeClass('on');$(this).addClass('on');draw(g,id,$(this).data('r'))});
 tr.addClass('open').after(g);draw(g,id,'24h')});
})();
