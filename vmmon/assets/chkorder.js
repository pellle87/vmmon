/* Dra en kontrollrad (grepet ⋮⋮) för att ändra ordning. Sparas i server_checks.sort och styr även ordningen på dashboarden. */
(function(){var tb=document.getElementById('chk-tbl');if(!tb)return;var en=(typeof LANG!=='undefined'&&LANG==='en'),drag=null;
function rows(){return[].slice.call(tb.querySelectorAll('tr[data-cid]'))}
function save(){var ids=rows().map(function(r){return r.getAttribute('data-cid')}),b=new URLSearchParams();b.set('t',tb.getAttribute('data-t'));b.set('sid',tb.getAttribute('data-sid'));b.set('ids',JSON.stringify(ids));
 fetch('api/chkorder.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(function(r){return r.json()}).then(function(d){if(!d.ok)throw 0}).catch(function(){alert(en?'Could not save the order (has sql/3.09.sql been run?)':'Kunde inte spara ordningen (har sql/3.09.sql körts?)')})}
tb.addEventListener('mousedown',function(e){var g=e.target.closest('.grip');if(!g)return;var tr=g.closest('tr');tr.setAttribute('draggable','true')});
tb.addEventListener('mouseup',function(){rows().forEach(function(r){r.removeAttribute('draggable')})});
tb.addEventListener('dragstart',function(e){var tr=e.target.closest&&e.target.closest('tr[data-cid]');if(!tr||tr.getAttribute('draggable')!=='true'){return}
 [].forEach.call(tb.querySelectorAll('tr.gr'),function(x){x.remove()});drag=tr;tr.classList.add('dragging');e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain',tr.getAttribute('data-cid'))}catch(x){}});
tb.addEventListener('dragover',function(e){if(!drag)return;var tr=e.target.closest('tr[data-cid]');if(!tr||tr===drag)return;e.preventDefault();var r=tr.getBoundingClientRect(),after=e.clientY>r.top+r.height/2;tr.parentNode.insertBefore(drag,after?tr.nextSibling:tr)});
tb.addEventListener('drop',function(e){if(drag)e.preventDefault()});
tb.addEventListener('dragend',function(){if(!drag)return;drag.classList.remove('dragging');rows().forEach(function(r){r.removeAttribute('draggable')});drag=null;save()});
})();
