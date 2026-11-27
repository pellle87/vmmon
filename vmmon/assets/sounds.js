/* Larmljud (WebAudio, inga ljudfiler). VMSND.play(audioContext, id) spelar ett ljud. */
window.VMSND=(function(){
 // steg: [frekvens, start(s), längd(s), vågform, volym, slutfrekvens(valfri)]
 var S={
  classic:[[880,0,.26,'sine',.25],[660,.3,.26,'sine',.25],[880,.6,.26,'sine',.25]],
  chime:[[1046,0,.55,'sine',.2],[1318,.18,.55,'sine',.2],[1568,.36,.9,'sine',.2]],
  bell:[[523,0,1.3,'triangle',.22],[1046,0,1.0,'sine',.12],[523,.7,1.3,'triangle',.2],[1046,.7,1.0,'sine',.1]],
  doorbell:[[659,0,.55,'sine',.25],[523,.55,.9,'sine',.25]],
  soft:[[523,0,.45,'sine',.18],[659,.25,.45,'sine',.18],[784,.5,.7,'sine',.18]],
  pulse:[[440,0,.16,'sine',.3],[440,.25,.16,'sine',.3],[440,.5,.16,'sine',.3]],
  digital:[[1200,0,.08,'square',.09],[1200,.14,.08,'square',.09],[1200,.28,.08,'square',.09],[1200,.42,.08,'square',.09]],
  alarm:[[740,0,.17,'square',.1],[988,.18,.17,'square',.1],[740,.36,.17,'square',.1],[988,.54,.17,'square',.1],[740,.72,.17,'square',.1],[988,.9,.17,'square',.1]],
  siren:[[600,0,.55,'sawtooth',.09,1000],[1000,.55,.55,'sawtooth',.09,600],[600,1.1,.55,'sawtooth',.09,1000],[1000,1.65,.55,'sawtooth',.09,600]],
  sonar:[[1000,0,1.0,'sine',.25],[1000,1.2,1.0,'sine',.25]]
 };
 function play(AC,id){
  if(!AC||AC.state!=='running')return false;
  var seq=S[id]||S.classic,t=AC.currentTime;
  seq.forEach(function(n){
   var o=AC.createOscillator(),g=AC.createGain(),st=t+n[1],en=st+n[2];
   o.type=n[3];o.frequency.setValueAtTime(n[0],st);
   if(n[5])o.frequency.linearRampToValueAtTime(n[5],en);
   g.gain.setValueAtTime(.0001,st);g.gain.exponentialRampToValueAtTime(n[4],st+Math.min(.03,n[2]/3));g.gain.exponentialRampToValueAtTime(.0001,en);
   o.connect(g);g.connect(AC.destination);o.start(st);o.stop(en+.02);
  });
  return true;
 }
 var ctx=null;
 function preview(id){ // för Inställningar/Profil – anropas från ett klick
  try{ctx=ctx||new(window.AudioContext||window.webkitAudioContext)();if(ctx.state==='suspended'){ctx.resume().then(function(){play(ctx,id)});return}play(ctx,id)}catch(e){}
 }
 return {play:play,preview:preview,ids:Object.keys(S)};
})();
