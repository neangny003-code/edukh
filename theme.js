// GameKid shared helpers
function GK_topbar(title, emoji){
  document.write(`
    <div class="topbar">
      <a class="back" href="../../index.html">← ត្រឡប់</a>
      <div class="title"><span class="emoji">${emoji}</span> ${title}</div>
    </div>
    <div class="krama-bar thin"></div>
  `);
}

function GK_confetti(container){
  const colors = ['#C0392B','#F0B429','#1F9D77','#3E8FC4','#1B2A4A'];
  const box = container || document.body;
  for(let i=0;i<26;i++){
    const p = document.createElement('div');
    p.style.position='fixed';
    p.style.left = Math.random()*100+'vw';
    p.style.top = '-10px';
    p.style.width = p.style.height = (6+Math.random()*6)+'px';
    p.style.background = colors[Math.floor(Math.random()*colors.length)];
    p.style.borderRadius = Math.random()>0.5 ? '50%':'2px';
    p.style.zIndex = 9999;
    p.style.pointerEvents='none';
    p.style.transition = 'transform 1.6s ease-in, opacity 1.6s ease-in';
    document.body.appendChild(p);
    requestAnimationFrame(()=>{
      p.style.transform = `translateY(${70+Math.random()*30}vh) rotate(${Math.random()*360}deg)`;
      p.style.opacity = '0';
    });
    setTimeout(()=>p.remove(), 1700);
  }
}

// Tiny WebAudio beep — no external assets needed
function GK_beep(freq=880, dur=0.12, type='sine'){
  try{
    const ctx = GK_beep._ctx || (GK_beep._ctx = new (window.AudioContext||window.webkitAudioContext)());
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.type = type; o.frequency.value = freq;
    g.gain.setValueAtTime(0.15, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime+dur);
    o.connect(g); g.connect(ctx.destination);
    o.start(); o.stop(ctx.currentTime+dur);
  }catch(e){}
}
function GK_correct(){ GK_beep(660,0.09); setTimeout(()=>GK_beep(880,0.14),90); }
function GK_wrong(){ GK_beep(180,0.22,'sawtooth'); }

function GK_shuffle(arr){
  for(let i=arr.length-1;i>0;i--){
    const j = Math.floor(Math.random()*(i+1));
    [arr[i],arr[j]]=[arr[j],arr[i]];
  }
  return arr;
}
