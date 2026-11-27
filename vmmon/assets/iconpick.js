(function(){function setm(el,u){el.style.webkitMaskImage=el.style.maskImage=u?'url('+u+')':'none'}var en=(typeof LANG!=='undefined'&&LANG==='en'),T=function(sv,e){return en?e:sv};
document.querySelectorAll('.icp').forEach(function(box){
 var inp=box.querySelector('input[name=icon]'),def={};try{def=JSON.parse(box.dataset.def||'{}')}catch(e){}
 var curU=box.dataset.curu||'',btn=document.createElement('button');btn.type='button';btn.className='btn icb';btn.title=T('Välj ikon','Choose icon');
 var pv=document.createElement('i');pv.className='ico';var tx=document.createElement('span');btn.appendChild(pv);btn.appendChild(tx);box.appendChild(btn);
 function defUrl(){var s=document.getElementById('src'),k='';if(s){var fg=document.querySelector('.fg[data-s="'+s.value+'"]'),ks=fg&&fg.querySelector('select.ks');k=s.value+':'+(ks?ks.value:'');if(def[k])return def[k];if(def[s.value])return def[s.value];for(var x in def)if(x.indexOf(s.value+':')===0)return def[x];return ''}return ''}
 function show(){var u=inp.value?curU:defUrl();setm(pv,u);tx.textContent=inp.value?T('Ikon','Icon'):T('Ikon (standard)','Icon (default)')}
 show();var s=document.getElementById('src');if(s)s.addEventListener('change',show);document.querySelectorAll('select.ks').forEach(function(k){k.addEventListener('change',show)});
 var pop=null,set='all',tm;
 function close(){if(pop){pop.remove();pop=null}}
 function load(){var q=pop.querySelector('input').value;fetch('api/icons.php?set='+set+'&q='+encodeURIComponent(q),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){var g=pop.querySelector('.icg');g.innerHTML='';
  d.items.forEach(function(it){var b=document.createElement('button');b.type='button';b.className='ici';b.title=it.n+' ('+it.s.split(':')[0]+')';var i=document.createElement('i');i.className='ico';setm(i,it.u);b.appendChild(i);b.onclick=function(){inp.value=it.s;curU=it.u;show();close()};g.appendChild(b)});
  var m=pop.querySelector('.icm');m.textContent=!d.items.length?T('Inga träffar.','No matches.'):(d.more?T('Visar de första – förfina sökningen.','Showing the first ones – refine the search.'):'');
  if(!d.lib&&(set==='mdi'||set==='si'||(set==='all'&&q)))m.innerHTML=T('Biblioteken är inte hämtade ännu: ','The libraries are not downloaded yet: ')+'<a href="settings.php?v=icons">'+T('Inställningar → Ikoner','Settings → Icons')+'</a>'})}
 btn.onclick=function(e){e.stopPropagation();if(pop){close();return}
  pop=document.createElement('div');pop.className='icpop';
  pop.innerHTML='<div class="ict"></div><input type="search" placeholder="'+T('Sök ikon (t.ex. docker, router, plex)…','Search icon (e.g. docker, router, plex)…')+'"><div class="icg"></div><div class="icm mut"></div><div class="icf"><button type="button" class="btn sm icd">'+T('Använd standard','Use default')+'</button></div>';
  var tabs=[['all',T('Alla','All')],['std',T('Standard','Standard')],['mdi','MDI'],['si','Simple Icons'],['up',T('Egna','Custom')]],tb=pop.querySelector('.ict');
  tabs.forEach(function(t){var b=document.createElement('button');b.type='button';b.className='btn sm'+(t[0]===set?' on':'');b.textContent=t[1];b.onclick=function(){set=t[0];[].forEach.call(tb.children,function(x){x.classList.remove('on')});b.classList.add('on');load()};tb.appendChild(b)});
  pop.querySelector('.icd').onclick=function(){inp.value='';curU='';show();close()};
  pop.querySelector('input').oninput=function(){clearTimeout(tm);tm=setTimeout(load,200)};pop.onclick=function(ev){ev.stopPropagation()};
  document.body.appendChild(pop);var r=btn.getBoundingClientRect();pop.style.top=Math.min(r.bottom+window.scrollY+4,document.body.scrollHeight)+'px';pop.style.left=Math.max(8,Math.min(r.left+window.scrollX,window.innerWidth-pop.offsetWidth-8))+'px';load();pop.querySelector('input').focus()};
 document.addEventListener('click',close);document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
});})();
