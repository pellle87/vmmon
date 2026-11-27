/* Pennknapp bredvid enhetens/gruppens ikon: välj egen ikon (std/MDI/Simple Icons/egna). */
(function(){var en=(typeof LANG!=='undefined'&&LANG==='en'),T=function(sv,e){return en?e:sv};
function setm(el,u){el.style.webkitMaskImage=el.style.maskImage=u?'url('+u+')':'none'}
var pop=null,tm;function close(){if(pop){pop.remove();pop=null}}
function post(box,spec){var b=new URLSearchParams();b.set('t',box.getAttribute('data-tok'));b.set('k',box.getAttribute('data-t'));b.set('id',box.getAttribute('data-id'));b.set('icon',spec);
 fetch('api/seticon.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(function(r){return r.json()}).then(function(d){if(!d.ok)throw 0;location.reload()}).catch(function(){alert(T('Kunde inte spara ikonen (har sql/3.08.sql körts?)','Could not save the icon (has sql/3.08.sql been run?)'))})}
document.addEventListener('click',function(e){var pen=e.target.closest('.dic .pen');if(!pen){if(pop&&!e.target.closest('.icpop'))close();return}
 e.preventDefault();e.stopPropagation();if(pop){close();return}var box=pen.closest('.dic'),set='all';
 pop=document.createElement('div');pop.className='icpop';
 pop.innerHTML='<div class="ict"></div><input type="search" placeholder="'+T('Sök ikon (t.ex. server, router, plex)…','Search icon (e.g. server, router, plex)…')+'"><div class="icg"></div><div class="icm mut"></div><div class="icf"><button type="button" class="btn sm icd">'+T('Använd standard','Use default')+'</button></div>';
 function load(){var q=pop.querySelector('input').value;fetch('api/icons.php?set='+set+'&q='+encodeURIComponent(q),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){var g=pop.querySelector('.icg');g.innerHTML='';
  d.items.forEach(function(it){var b=document.createElement('button');b.type='button';b.className='ici';b.title=it.n+' ('+it.s.split(':')[0]+')';var i=document.createElement('i');i.className='ico';setm(i,it.u);b.appendChild(i);b.onclick=function(){post(box,it.s)};g.appendChild(b)});
  var m=pop.querySelector('.icm');m.textContent=!d.items.length?T('Inga träffar.','No matches.'):(d.more?T('Visar de första – förfina sökningen.','Showing the first ones – refine the search.'):'');
  if(!d.lib&&(set==='mdi'||set==='si'||(set==='all'&&q)))m.innerHTML=T('Biblioteken är inte hämtade ännu: ','The libraries are not downloaded yet: ')+'<a href="settings.php?v=icons">'+T('Inställningar → Ikoner','Settings → Icons')+'</a>'})}
 var tabs=[['all',T('Alla','All')],['std',T('Standard','Standard')],['mdi','MDI'],['si','Simple Icons'],['up',T('Egna','Custom')]],tb=pop.querySelector('.ict');
 tabs.forEach(function(t){var b=document.createElement('button');b.type='button';b.className='btn sm'+(t[0]===set?' on':'');b.textContent=t[1];b.onclick=function(){set=t[0];[].forEach.call(tb.children,function(x){x.classList.remove('on')});b.classList.add('on');load()};tb.appendChild(b)});
 pop.querySelector('.icd').onclick=function(){post(box,'')};
 pop.querySelector('input').oninput=function(){clearTimeout(tm);tm=setTimeout(load,200)};pop.onclick=function(ev){ev.stopPropagation()};
 document.body.appendChild(pop);var r=pen.getBoundingClientRect();pop.style.top=(r.bottom+window.scrollY+4)+'px';pop.style.left=Math.max(8,Math.min(r.left+window.scrollX,window.innerWidth-pop.offsetWidth-8))+'px';load();pop.querySelector('input').focus()});
document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
})();
