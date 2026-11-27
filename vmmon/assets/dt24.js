// Visar datum+tid enligt inställningarna (24 h och datumformat). Webbläsarens datetime-local följer webbläsarens språk (AM/PM, mm/dd/yyyy) och kan inte styras,
// så fältet ersätts av ett datumfält (i valt format, med kalender), ett tidsfält (HH:MM) och ett dolt fält med samma namn som innehåller YYYY-MM-DDTHH:MM.
(function(){ if(window.H24===false) return;
 var DF=window.DF||'Y-m-d', sep=DF.replace(/[Ymd]/g,'').charAt(0)||'-', order=DF.replace(/[^Ymd]/g,'').split('');
 function fmt(iso){ var m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(iso); if(!m) return ''; var o={Y:m[1],m:m[2],d:m[3]}; return order.map(function(k){return o[k];}).join(sep); }
 function parse(s){ s=s.trim(); if(!s) return ''; var p=s.split(/[\/.\-\s]+/); if(p.length!==3) return null; var o={}; for(var i=0;i<3;i++) o[order[i]]=p[i]; if(!/^\d{4}$/.test(o.Y)||!/^\d{1,2}$/.test(o.m)||!/^\d{1,2}$/.test(o.d)) return null; var mo=+o.m,da=+o.d; if(mo<1||mo>12||da<1||da>31) return null; return o.Y+'-'+('0'+mo).slice(-2)+'-'+('0'+da).slice(-2); }
 var ph=order.map(function(k){return {Y:'yyyy',m:'mm',d:'dd'}[k];}).join(sep);
 [].forEach.call(document.querySelectorAll('input[type=datetime-local]'),function(inp){
  var v=inp.value||'', iso=v.slice(0,10), t=v.slice(11,16);
  var h=document.createElement('input'); h.type='hidden'; h.name=inp.name; h.value=v;
  var box=document.createElement('span'); box.style.cssText='display:inline-flex;gap:6px;align-items:center;white-space:nowrap;position:relative';
  var dd=document.createElement('input'); dd.type='text'; dd.value=fmt(iso); dd.placeholder=ph; dd.maxLength=10; dd.size=10; dd.style.width='7.5em'; dd.autocomplete='off';
  var cal=document.createElement('input'); cal.type='date'; cal.value=iso; cal.tabIndex=-1; cal.setAttribute('aria-hidden','true'); cal.style.cssText='position:absolute;left:0;top:0;width:7.5em;height:100%;opacity:0;pointer-events:none';
  var bt=document.createElement('button'); bt.type='button'; bt.className='btn sm'; bt.textContent='📅'; bt.title=ph; bt.onclick=function(){ try{ cal.showPicker(); }catch(e){ cal.focus(); cal.click(); } };
  var tt=document.createElement('input'); tt.type='text'; tt.value=t; tt.placeholder='HH:MM'; tt.maxLength=5; tt.size=5; tt.inputMode='numeric'; tt.title='HH:MM (24 h)'; tt.style.width='5em';
  function sync(){ var di=parse(dd.value); dd.setCustomValidity(di===null?ph:''); var m=/^([01]?\d|2[0-3]):([0-5]\d)$/.exec(tt.value.trim()); tt.setCustomValidity(tt.value.trim()===''||m?'':'HH:MM');
   if(!di){ h.value=''; return; } h.value=di+'T'+(m?('0'+m[1]).slice(-2)+':'+m[2]:'00:00'); }
  dd.addEventListener('input',sync); dd.addEventListener('change',function(){ var di=parse(dd.value); if(di){ dd.value=fmt(di); cal.value=di; } sync(); });
  cal.addEventListener('change',function(){ dd.value=fmt(cal.value); sync(); }); tt.addEventListener('input',sync); tt.addEventListener('change',sync);
  box.appendChild(dd); box.appendChild(bt); box.appendChild(cal); box.appendChild(tt);
  inp.parentNode.insertBefore(h,inp); inp.parentNode.insertBefore(box,inp); inp.parentNode.removeChild(inp); });
})();
