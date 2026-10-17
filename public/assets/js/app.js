'use strict';
(() => {
  const globalMusic=document.querySelector('[data-main-music]');
  if(globalMusic){
    let audio=new Audio(globalMusic.dataset.musicUrl||'/assets/sounds/main-theme.mp3'),enabled=false;audio.loop=true;audio.volume=.18;audio.preload='auto';
    const label=()=>{globalMusic.textContent=enabled?'♫ Music: On':'♫ Music: Off';globalMusic.setAttribute('aria-pressed',enabled?'true':'false');};
    const play=()=>audio.play().then(()=>{enabled=true;label();});
    globalMusic.addEventListener('click',()=>{if(enabled){audio.pause();enabled=false;localStorage.setItem('siber-music','off');label();}else{localStorage.setItem('siber-music','on');play().catch(()=>{globalMusic.textContent='♫ Turn Music On';});}});
    if(localStorage.getItem('siber-music')!=='off')play().catch(()=>{globalMusic.textContent='♫ Turn Music On';const resume=()=>play().catch(()=>{});window.addEventListener('pointerdown',resume,{once:true});window.addEventListener('keydown',resume,{once:true});});else label();
    document.addEventListener('visibilitychange',()=>{if(document.hidden&&enabled)audio.pause();else if(!document.hidden&&enabled)audio.play().catch(()=>{});});
    window.addEventListener('pagehide',()=>audio.pause());
  }
  document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(btn.dataset.copy || ''); btn.textContent = '✓ Copied'; }
    catch { btn.textContent = 'Copy not supported'; }
  }));
  document.querySelectorAll('[data-reveal-code]').forEach(btn=>btn.addEventListener('click',()=>{const visible=btn.dataset.visible==='1';btn.dataset.visible=visible?'0':'1';btn.textContent=visible?'•••• Show':btn.dataset.revealCode+' Hide';}));
  // Sign-up email: the participation certificate goes here; common domain typos are caught.
  const emailInput=document.querySelector('[data-email-check]');
  if(emailInput){
    const domains=['gmail.com','hotmail.com','outlook.com','live.com','yahoo.com','icloud.com','me.com','yandex.com','mynet.com','protonmail.com','msn.com'];
    const hint=document.querySelector('[data-email-hint]');
    const distance=(a,b)=>{const row=Array.from({length:b.length+1},(_,i)=>i);for(let i=1;i<=a.length;i++){let prev=row[0];row[0]=i;for(let j=1;j<=b.length;j++){const cur=row[j];row[j]=Math.min(row[j]+1,row[j-1]+1,prev+(a[i-1]===b[j-1]?0:1));prev=cur;}}return row[b.length];};
    const suggest=()=>{
      hint.textContent='';const value=emailInput.value.trim(),at=value.lastIndexOf('@');if(at<1)return;
      const domain=value.slice(at+1).toLowerCase();if(!domain||domains.includes(domain))return;
      const [best,score]=domains.map(d=>[d,distance(domain,d)]).sort((x,y)=>x[1]-y[1])[0];
      if(score<1||score>2)return;
      const fix=document.createElement('button');fix.type='button';fix.className='email-fix';fix.textContent=`${value.slice(0,at+1)}${best}`;
      fix.addEventListener('click',()=>{emailInput.value=fix.textContent;hint.textContent='Corrected.';emailInput.focus();});
      hint.append(`You typed "${domain}". Did you mean: `,fix);
    };
    emailInput.addEventListener('blur',suggest);emailInput.addEventListener('input',()=>{hint.textContent='';});
  }
  const usernameInput=document.querySelector('[data-username-check]');
  if(usernameInput){
    const status=document.getElementById('username-status'),form=usernameInput.form,submit=form?.querySelector('button[type="submit"]');let timer=0,request=0,available=false;
    const show=(message,state='checking')=>{if(!status)return;status.textContent=message;status.dataset.state=state;usernameInput.setAttribute('aria-invalid',state==='unavailable'||state==='invalid'?'true':'false');};
    usernameInput.addEventListener('input',()=>{clearTimeout(timer);available=false;if(submit)submit.disabled=true;const value=usernameInput.value.trim();if(value.length<3){show('Type at least 3 characters.','invalid');return;}show('Checking…');const current=++request;timer=setTimeout(async()=>{try{const response=await fetch('/api/username?username='+encodeURIComponent(value),{credentials:'same-origin',cache:'no-store'});const result=await response.json();if(current!==request)return;available=Boolean(result.valid&&result.available);show((available?'✓ ':'✕ ')+result.message,available?'available':(result.valid?'unavailable':'invalid'));if(submit)submit.disabled=!available;}catch{show('Could not check right now; it will be verified again when you submit.','checking');if(submit)submit.disabled=false;}},280);});
    form?.addEventListener('submit',event=>{if(!available){event.preventDefault();show('Choose an available username to continue.','unavailable');usernameInput.focus();}});
  }
  const input = document.getElementById('password-input');
  if (!input) return;
  const gauge = document.getElementById('password-gauge');
  const strength = document.getElementById('password-strength');
  const time = document.getElementById('password-time');
  const advice = document.getElementById('password-advice');
  const score = document.getElementById('password-score');
  const checks = {
    length: document.querySelector('[data-meter-check="length"]'),
    common: document.querySelector('[data-meter-check="common"]'),
    unique: document.querySelector('[data-meter-check="unique"]')
  };
  const toggle = document.getElementById('password-toggle');
  toggle?.addEventListener('click', () => { input.type = input.type === 'password' ? 'text' : 'password'; toggle.textContent = input.type === 'password' ? 'Show' : 'Hide'; });
  // Needle: 0 points -90deg, 100 points +90deg; the colour zone is picked in CSS via data-level.
  function setGauge(points, level, label) {
    gauge.style.setProperty('--needle', `${points * 1.8 - 90}deg`);
    gauge.dataset.level = level; gauge.setAttribute('aria-valuenow', String(points)); gauge.setAttribute('aria-valuetext', `${points} / 100, ${label}`);
    score.textContent = String(points); strength.textContent = label;
  }
  input.addEventListener('input', () => {
    const value = input.value; if(!value) {setGauge(0,'none','Not rated yet');time.textContent='—';advice.textContent='Try a unique passphrase of at least 15 characters.';Object.values(checks).forEach(x=>x?.classList.remove('passed'));return;}
    // Estimation lives in password-strength.js; the password never leaves the browser.
    const result = window.passwordStrength(value), points = result.score, index = result.level, common = result.common;
    const categories = [/[a-zçğıöşü]/u, /[A-ZÇĞİÖŞÜ]/u, /\d/u, /[^\p{L}\p{N}]/u].filter(re => re.test(value)).length;
    setGauge(points,['weak','fair','good','strong'][index],['Very Weak','Could Be Better','Good','Strong'][index]);time.textContent=result.time;
    checks.length?.classList.toggle('passed',value.length>=15);checks.common?.classList.toggle('passed',!common);checks.unique?.classList.toggle('passed',value.length>=12&&!/(.)\1{2,}/u.test(value));
    const tips=[];if (value.length<15) tips.push('Make it 15 characters or longer.');if(common)tips.push('Remove common passwords and guessable words such as cities, teams or names.');if(categories<2)tips.push('If you like, add different kinds of characters while keeping the length.');
    advice.textContent=tips.join(' ')||'Looks long and unique. For real accounts, also consider a password manager and MFA/passkeys.';
  });
})();
