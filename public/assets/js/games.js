'use strict';
(() => {
  const world=document.querySelector('[data-game]');if(!world)return;
  const slug=world.dataset.game,stage=document.getElementById('game-stage');
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const progress=document.getElementById('game-progress-bar'),stepLabel=document.getElementById('game-progress-text'),scoreLabel=document.getElementById('game-score'),timeLabel=document.getElementById('game-time');
  let attempt=null,task=null,step=0,total=5,points=0,deadline=0,tick=null,music=null,playing=false,locked=false;
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  async function post(url,payload) {
    const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},credentials:'same-origin',body:JSON.stringify(payload)});
    let result;try{result=await response.json();}catch{throw Error('No valid response was received from the server.');}
    if(!response.ok||!result.ok)throw Error(result.message||'Something went wrong.');return result.data;
  }
  const notes={
    'ai-or-real':'Appearance alone is not proof. Check the source, the context and any Content Credentials together.',
    'phishing-detective':'A fake email (phishing) tries to trick you into handing over a password, a code or money. The addresses and people here are fictional.',
    'threat-hunter':'Examine the evidence in the scene; only one item is a real security risk.',
    'password-hero':'Only sample passwords are used in these tasks.',
    'think-before-sharing':'The people and details in these stories are fictional.',
    'cyber-shield':'Each question has exactly one correct answer.'
  };
  function update() {stepLabel.textContent=`${step} / ${total}`;progress.style.width=`${Math.round(step/total*100)}%`;scoreLabel.textContent=points;}
  function notify(message) {stage.innerHTML=`<div class="game-error"><div class="big-emoji">⚠️</div><h2>Something went wrong</h2><p>${esc(message)}</p><a class="btn btn-game" href="/dashboard">Back to Player Dashboard</a></div>`;}
  function startCountdown(remaining) {
    deadline=Date.now()+remaining*1000;clearInterval(tick);
    const run=()=>{let secs=Math.max(0,Math.ceil((deadline-Date.now())/1000));timeLabel.textContent=`⏱ ${String(Math.floor(secs/60)).padStart(2,'0')}:${String(secs%60).padStart(2,'0')}`;timeLabel.classList.toggle('time-low',secs<=20);
      if(secs===0){clearInterval(tick);locked=true;stage.innerHTML=`<div class="result-stage"><span class="result-medal">⏳</span><h2>Time's up!</h2><p>This round could not be finished. You can try again in a new game.</p><a class="btn btn-game" href="/dashboard">Back to Player Dashboard →</a></div>`;}
    };run();tick=setInterval(run,250);
  }
  function audioSource() {return world.dataset.musicUrl||'/assets/sounds/main-theme.mp3';}
  function setSoundLabel(text){const btn=document.querySelector('.sound-toggle');if(btn){btn.textContent=text;btn.setAttribute('aria-pressed',playing?'true':'false');}}
  function prepareMusic(){if(!music){music=new Audio(audioSource());music.loop=true;music.volume=.22;music.preload='auto';}}
  function playMusic(){prepareMusic();return music.play().then(()=>{playing=true;setSoundLabel('♫ Music: On');}).catch(()=>{playing=false;setSoundLabel('♫ Turn Music On');throw Error('autoplay');});}
  function toggleMusic(){
    prepareMusic();
    if(playing){music.pause();playing=false;localStorage.setItem('siber-music','off');setSoundLabel('♫ Music: Off');}
    else{localStorage.setItem('siber-music','on');playMusic().catch(()=>{setSoundLabel('♫ Music unavailable');});}
  }
  document.querySelector('.sound-toggle')?.addEventListener('click',toggleMusic);
  if(localStorage.getItem('siber-music')!=='off'){
    playMusic().catch(()=>{
      const resume=()=>{playMusic().catch(()=>{});window.removeEventListener('pointerdown',resume);window.removeEventListener('keydown',resume);};
      window.addEventListener('pointerdown',resume,{once:true});window.addEventListener('keydown',resume,{once:true});
    });
  } else setSoundLabel('♫ Music: Off');
  document.addEventListener('visibilitychange',()=>{if(document.hidden&&playing){music.pause();}else if(!document.hidden&&playing){music.play().catch(()=>{});}});
  window.addEventListener('pagehide',()=>{if(music)music.pause();clearInterval(tick);});
  async function begin() {
    const button=document.getElementById('game-start');if(button){button.disabled=true;button.textContent='Preparing the game...';}
    try{const data=await post('/api/game/start',{game:slug});attempt=data.attempt_id;step=data.step;fortress=Array.from({length:data.step},(_,i)=>({label:`Decision ${i+1}`,ok:null}));total=data.total;points=data.score;locked=false;update();startCountdown(data.remaining);renderTask(data.task,data.mode);}
    catch(error){notify(error.message);}
  }
  document.getElementById('game-start')?.addEventListener('click',begin);
  function optionCards(items,className='answer-grid') {
    return `<div class="${className}">${items.map((o,i)=>`<button type="button" class="choice-button choose-option" data-choice="${esc(o.id)}"><span class="choice-letter">${String.fromCharCode(65+i)}</span><span>${esc(o.text)}</span><span class="choice-arrow">↗</span></button>`).join('')}</div>`;
  }
  function stageShell(t,inner,mode){const labels=['','BEGINNER','BASIC','INTERMEDIATE','ADVANCED','EXPERT'];return `<div class="stage-body"><div class="stage-head"><div class="stage-overline">${esc(t.title)} <span class="difficulty-tag">${labels[t.difficulty]||'ADAPTED'}</span> ${mode==='practice'?'<span class="practice-tag">PRACTICE</span>':''}</div><h2>${esc(t.prompt)}</h2></div>${inner}<p class="stage-note">${esc(notes[slug]||'')}</p></div>`;}
  // Cyber Threat Hunter: sweep a dark floor plan with a flashlight, inspect evidence and flag the single risk.
  const plans={
    office:{name:'Open office',svg:'<rect x="4" y="4" width="152" height="92"/><path d="M20 4h30M70 4h30" class="bp-window"/><rect x="14" y="16" width="30" height="12"/><circle cx="29" cy="34" r="3"/><rect x="14" y="62" width="30" height="12"/><circle cx="29" cy="56" r="3"/><rect x="70" y="36" width="42" height="24" rx="10"/><circle cx="76" cy="31" r="2.6"/><circle cx="91" cy="31" r="2.6"/><circle cx="106" cy="31" r="2.6"/><circle cx="76" cy="65" r="2.6"/><circle cx="91" cy="65" r="2.6"/><circle cx="106" cy="65" r="2.6"/><rect x="128" y="12" width="18" height="10"/><circle cx="146" cy="86" r="4"/><path d="M60 96V84A12 12 0 0 1 72 96" class="bp-door"/><text x="14" y="12">OPEN OFFICE</text><text x="78" y="50">MEETING</text><text x="127" y="27">PRINTER</text>'},
    cafe:{name:'Cafe',svg:'<rect x="4" y="4" width="152" height="92"/><path d="M110 8H152M110 8V50H152" /><path d="M20 96h40M80 96h40" class="bp-window"/><circle cx="30" cy="28" r="7"/><circle cx="30" cy="70" r="7"/><circle cx="66" cy="48" r="7"/><circle cx="96" cy="76" r="7"/><circle cx="21" cy="28" r="2.4"/><circle cx="39" cy="28" r="2.4"/><circle cx="21" cy="70" r="2.4"/><circle cx="39" cy="70" r="2.4"/><circle cx="57" cy="48" r="2.4"/><circle cx="75" cy="48" r="2.4"/><circle cx="87" cy="76" r="2.4"/><circle cx="105" cy="76" r="2.4"/><path d="M4 40V52A12 12 0 0 0 16 40" class="bp-door"/><text x="118" y="16">BAR</text><text x="44" y="14">SEATING AREA</text>'},
    home:{name:'Home',svg:'<rect x="4" y="4" width="152" height="92"/><path d="M92 4V60M4 62H62M92 60H156"/><rect x="12" y="12" width="40" height="10"/><rect x="22" y="30" width="20" height="8"/><path d="M12 54H52" class="bp-thick"/><rect x="104" y="12" width="34" height="26"/><rect x="106" y="14" width="12" height="6"/><rect x="124" y="14" width="12" height="6"/><rect x="100" y="72" width="30" height="10"/><circle cx="115" cy="88" r="3"/><rect x="8" y="72" width="46" height="8"/><path d="M62 62V74A12 12 0 0 0 74 62" class="bp-door"/><text x="58" y="12">LIVING ROOM</text><text x="104" y="48">BEDROOM</text><text x="100" y="68">STUDY</text><text x="8" y="90">KITCHEN</text>'},
    hotel:{name:'Hotel room',svg:'<rect x="4" y="4" width="152" height="92"/><path d="M118 60V96M118 60H156"/><rect x="18" y="20" width="46" height="36"/><rect x="21" y="23" width="18" height="8"/><rect x="43" y="23" width="18" height="8"/><rect x="8" y="22" width="8" height="8"/><rect x="66" y="22" width="8" height="8"/><rect x="96" y="12" width="44" height="10"/><circle cx="118" cy="28" r="3"/><path d="M96 52H140" class="bp-thick"/><rect x="76" y="74" width="12" height="16"/><path d="M40 96V84A12 12 0 0 1 52 96" class="bp-door"/><text x="22" y="64">BED</text><text x="96" y="10">DESK</text><text x="124" y="78">BATHROOM</text><text x="96" y="58">TV</text>'},
    library:{name:'Library',svg:'<rect x="4" y="4" width="152" height="92"/><rect x="10" y="12" width="52" height="4"/><rect x="10" y="26" width="52" height="4"/><rect x="10" y="40" width="52" height="4"/><rect x="10" y="54" width="52" height="4"/><rect x="10" y="68" width="52" height="4"/><rect x="80" y="20" width="32" height="14"/><rect x="80" y="58" width="32" height="14"/><circle cx="86" cy="16" r="2.4"/><circle cx="106" cy="16" r="2.4"/><circle cx="86" cy="76" r="2.4"/><circle cx="106" cy="76" r="2.4"/><rect x="126" y="10" width="26" height="8"/><rect x="128" y="11" width="6" height="5"/><rect x="138" y="11" width="6" height="5"/><path d="M130 96V84A12 12 0 0 1 142 96" class="bp-door"/><text x="10" y="9">SHELVES</text><text x="80" y="44">READING TABLES</text><text x="124" y="26">COMPUTER</text>'},
    class:{name:'Classroom',svg:'<rect x="4" y="4" width="152" height="92"/><path d="M40 8H120" class="bp-thick"/><rect x="65" y="14" width="30" height="8"/><rect x="18" y="34" width="20" height="8"/><rect x="52" y="34" width="20" height="8"/><rect x="86" y="34" width="20" height="8"/><rect x="120" y="34" width="20" height="8"/><rect x="18" y="54" width="20" height="8"/><rect x="52" y="54" width="20" height="8"/><rect x="86" y="54" width="20" height="8"/><rect x="120" y="54" width="20" height="8"/><rect x="18" y="74" width="20" height="8"/><rect x="52" y="74" width="20" height="8"/><rect x="86" y="74" width="20" height="8"/><rect x="120" y="74" width="20" height="8"/><path d="M4 70V82A12 12 0 0 0 16 70" class="bp-door"/><text x="72" y="30">BOARD</text>'},
    phone:{name:'Desk',svg:'<rect x="4" y="4" width="152" height="92"/><rect x="20" y="8" width="48" height="84" rx="7"/><rect x="38" y="11" width="12" height="3" rx="1.5"/><rect x="27" y="22" width="8" height="8" rx="2"/><rect x="40" y="22" width="8" height="8" rx="2"/><rect x="53" y="22" width="8" height="8" rx="2"/><rect x="27" y="36" width="8" height="8" rx="2"/><rect x="40" y="36" width="8" height="8" rx="2"/><rect x="53" y="36" width="8" height="8" rx="2"/><rect x="27" y="50" width="8" height="8" rx="2"/><rect x="40" y="50" width="8" height="8" rx="2"/><path d="M26 80H62"/><rect x="90" y="18" width="56" height="36" rx="2"/><path d="M86 58H150L156 72H80Z"/><text x="70" y="12">PHONE</text><text x="90" y="14">LAPTOP</text>'},
  };
  const planAlias={kafe:'cafe'};
  const touchScreen=()=>matchMedia('(pointer: coarse)').matches;
  function huntMarkup(c) {
    const plan=plans[planAlias[c.scene]||c.scene]||plans.office,objects=c.objects||[];
    const tag=i=>String(i+1).padStart(2,'0');
    const markers=objects.map((o,i)=>`<button type="button" class="hunt-marker choose-option" data-index="${i}" data-choice="${esc(o.id)}" style="left:${Math.max(6,Math.min(92,Number(o.x)||10))}%;top:${Math.max(8,Math.min(88,Number(o.y)||30))}%" aria-label="Evidence ${tag(i)}: ${esc(o.label)}"><span>${tag(i)}</span></button>`).join('');
    const log=objects.map((o,i)=>`<li data-index="${i}"><button type="button" disabled><span class="hunt-log-tag">${tag(i)}</span><span class="hunt-log-label">Not found yet</span><span class="hunt-log-state" aria-hidden="true"></span></button></li>`).join('');
    return `<div class="hunt-ops"><div class="hunt-main"><div class="hunt-board" data-hunt-board><svg class="hunt-plan" viewBox="0 0 160 100" preserveAspectRatio="none" aria-hidden="true">${plan.svg}</svg><div class="hunt-dark" aria-hidden="true"></div><div class="hunt-hint" aria-hidden="true">${touchScreen()?'Move your finger across the scene':'Move the flashlight across the scene'}</div>${markers}<div class="hunt-coord" aria-hidden="true">${esc(plan.name).toLocaleUpperCase('en-US')} · FLOOR PLAN</div></div><ol class="hunt-log" aria-label="Evidence list">${log}</ol></div>
<aside class="hunt-file" aria-label="Case file"><div class="hunt-file-head"><span>CASE FILE</span><strong>${esc(c.location||plan.name)}</strong></div><div class="hunt-meter"><span>Evidence found</span><b data-hunt-count>0 / ${objects.length}</b><div class="hunt-meter-bar"><i data-hunt-bar></i></div></div><div class="hunt-detail" data-hunt-detail aria-live="polite"><p class="hunt-empty">Scan the scene. When an evidence tag appears, tap it to inspect, then flag the single risk.</p></div><button type="button" class="hunt-lights" data-hunt-lights>Light up the whole area</button></aside></div>`;
  }
  function initHunt(c) {
    const objects=c.objects||[],board=stage.querySelector('[data-hunt-board]'),detail=stage.querySelector('[data-hunt-detail]');
    const markers=[...board.querySelectorAll('.hunt-marker')],logItems=[...stage.querySelectorAll('.hunt-log li')];
    const found=new Set(),cleared=new Set();let current=-1;
    const radius=()=>board.clientWidth<560?62:96;
    function reveal(i){
      if(found.has(i))return;found.add(i);markers[i].classList.add('is-found');
      const li=logItems[i],b=li.querySelector('button');b.disabled=false;li.querySelector('.hunt-log-label').textContent=objects[i].label;
      stage.querySelector('[data-hunt-count]').textContent=`${found.size} / ${objects.length}`;stage.querySelector('[data-hunt-bar]').style.width=`${found.size/objects.length*100}%`;
    }
    function inspect(i){
      if(locked)return;reveal(i);current=i;
      markers.forEach((m,j)=>m.classList.toggle('is-selected',j===i));logItems.forEach((li,j)=>li.classList.toggle('is-selected',j===i));
      const o=objects[i];
      detail.innerHTML=`<div class="hunt-evidence"><span class="hunt-evidence-icon" aria-hidden="true">${esc(o.emoji)}</span><div><small>EVIDENCE ${String(i+1).padStart(2,'0')}</small><strong>${esc(o.label)}</strong></div></div><p class="hunt-question">Does this item give an attacker an opening?</p><div class="hunt-actions"><button type="button" class="hunt-flag choose-option" data-choice="${esc(o.id)}">Flag as risk</button><button type="button" class="hunt-clear">Clear, next one</button></div>`;
      detail.querySelector('.hunt-flag').addEventListener('click',e=>submit(o.id,markers[i]));
      // On phones the panel sits below the scene; scroll it into view so the player sees it open.
      const box=detail.getBoundingClientRect();if(box.top<0||box.bottom>innerHeight)detail.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'center'});
      detail.querySelector('.hunt-clear').addEventListener('click',()=>{cleared.add(i);logItems[i].classList.add('is-cleared');markers[i].classList.add('is-cleared');const next=markers.findIndex((_,j)=>found.has(j)&&!cleared.has(j)&&j!==i);next>=0?inspect(next):(current=-1,markers.forEach(m=>m.classList.remove('is-selected')),logItems.forEach(li=>li.classList.remove('is-selected')),detail.innerHTML='<p class="hunt-empty">You marked the evidence you found as clear. Keep scanning or re-inspect an item from the list.</p>');});
    }
    function scan(clientX,clientY){
      const box=board.getBoundingClientRect(),x=clientX-box.left,y=clientY-box.top,r=radius();
      board.style.setProperty('--mx',`${x}px`);board.style.setProperty('--my',`${y}px`);board.classList.add('is-scanning');
      markers.forEach((m,i)=>{const mb=m.getBoundingClientRect();if(Math.hypot(mb.left+mb.width/2-clientX,mb.top+mb.height/2-clientY)<r*.8)reveal(i);});
    }
    board.addEventListener('pointermove',e=>scan(e.clientX,e.clientY));
    board.addEventListener('pointerdown',e=>scan(e.clientX,e.clientY));
    markers.forEach((m,i)=>{m.addEventListener('click',()=>inspect(i));m.addEventListener('focus',()=>reveal(i));});
    logItems.forEach((li,i)=>li.querySelector('button').addEventListener('click',()=>inspect(i)));
    stage.querySelector('[data-hunt-lights]').addEventListener('click',e=>{board.classList.add('is-lit');markers.forEach((_,i)=>reveal(i));e.currentTarget.disabled=true;});
  }
  function huntResult(res,choice){
    const board=stage.querySelector('[data-hunt-board]');if(!board)return;
    board.classList.add('is-lit','is-resolved');
    board.querySelectorAll('.hunt-marker').forEach(m=>{m.classList.add('is-found');m.classList.remove('is-selected');m.classList.toggle('is-risk',m.dataset.choice===res.correct_choice);m.classList.toggle('is-wrong',m.dataset.choice===choice&&!res.correct);});
    stage.querySelector('[data-hunt-lights]')?.setAttribute('disabled','');
    const risk=board.querySelector(`.hunt-marker[data-choice="${CSS.escape(res.correct_choice||'')}"]`),detail=stage.querySelector('[data-hunt-detail]');
    if(risk&&detail)detail.innerHTML=`<div class="hunt-verdict ${res.correct?'is-hit':'is-miss'}"><small>${res.correct?'RISK CORRECTLY IDENTIFIED':'THE REAL RISK'}</small><strong>${esc(risk.querySelector('span').textContent)} · ${esc(risk.getAttribute('aria-label').replace(/^Evidence \d+: /,''))}</strong></div>`;
  }
  // Phishing Detective: four questions to check on each email; a helper only, it does not affect the score.
  const mailChecks=['Does the sender address belong to the real organization?','Where does the link actually go?','Is it rushing or scaring you?','Is it asking for a password, a code or money?'];
  function mailChecklist(){return `<div class="mail-checklist"><strong>Detective checklist</strong><ul>${mailChecks.map(q=>`<li><button type="button" class="mail-check" aria-pressed="false">${esc(q)}</button></li>`).join('')}</ul></div>`;}
  // Password Hero: an account fortress built over the round; each right decision adds a layer, each wrong one a crack.
  let fortress=[];
  const layerName=t=>{const part=(String(t.title).split('·')[1]||'').trim();return part&&!/^\d+$/.test(part)?part:`Decision ${step+1}`;};
  function fortressMarkup(t){
    const built=fortress.filter(f=>f.ok===true).length;
    const slots=Array.from({length:total},(_,i)=>{const f=fortress[i];
      if(f)return `<li class="${f.ok===true?'is-built':f.ok===false?'is-cracked':'is-done'}"><span>${f.ok===true?'Built':f.ok===false?'Cracked':'Done'}</span>${esc(f.label)}</li>`;
      return i===fortress.length?`<li class="is-current" data-fortress-current><span>This decision</span>${esc(layerName(t))}</li>`:'<li class="is-empty"><span>Up next</span>&nbsp;</li>';}).join('');
    return `<div class="fortress" aria-label="Account fortress"><div class="fortress-head"><strong>Account fortress</strong><span data-fortress-count>${built} / ${total} layers intact</span></div><ol class="fortress-wall">${slots}</ol></div>`;
  }
  function fortressResult(res){
    fortress.push({label:layerName(task),ok:res.correct});
    const slot=stage.querySelector('[data-fortress-current]');
    if(slot){slot.className=res.correct?'is-built':'is-cracked';slot.querySelector('span').textContent=res.correct?'Built':'Cracked';slot.removeAttribute('data-fortress-current');}
    const count=stage.querySelector('[data-fortress-count]');if(count)count.textContent=`${fortress.filter(f=>f.ok===true).length} / ${total} layers intact`;
    if(task.content?.password_test)stage.querySelector('.feedback-area')?.prepend(passwordBench(task.content.options||[]));
  }
  // Shown after answering; showing it earlier would give away "which is strongest" questions.
  function passwordBench(opts){
    const box=document.createElement('div');box.className='pw-bench';
    box.innerHTML=`<strong>Test your answer</strong><p>How long would an attacker making 1 million guesses per second need to crack these passwords?</p><ul>${opts.map((o,i)=>`<li><code>${esc(o.text)}</code><button type="button" class="pw-test" data-pw="${i}">Test</button><div class="pw-result" hidden><div class="pw-bar"><i></i></div><span></span></div></li>`).join('')}</ul>`;
    box.querySelectorAll('.pw-test').forEach(b=>b.addEventListener('click',()=>{
      const r=window.passwordStrength(opts[Number(b.dataset.pw)].text),out=b.nextElementSibling;
      out.hidden=false;out.dataset.level=r.level;out.querySelector('span').textContent=r.time;b.remove();
      requestAnimationFrame(()=>{out.querySelector('i').style.width=`${Math.max(4,r.score)}%`;});
    }));
    return box;
  }
  // Think Before You Share: a realistic "share post" screen; after answering, a reach simulation for the chosen audience.
  const reach={public:{label:'Public',views:3400,shots:17,shares:42},friends:{label:'Friends',views:180,shots:4,shares:3},close:{label:'Close friends',views:12,shots:1,shares:0}};
  // Only questions where the player is about to share something (share_mode) use the share screen.
  // Situations such as an incoming message, a quiz or a friend's message use a plain card; an audience picker makes no sense there.
  function shareMarkup(c){
    const name=String(c.author||''),avatar=name?`<span class="share-avatar" aria-hidden="true">${esc(name.charAt(0).toLocaleUpperCase('en-US'))}</span>`:'';
    const who=name?`<div class="share-author">${avatar}<div><strong>${esc(name)}</strong>${c.handle?`<small>${esc(c.handle)}</small>`:''}</div></div>`:'';
    if(c.share_mode!=='draft'&&c.share_mode!=='draft_safe')return `<div class="share-phone is-situation"><div class="share-top"><span>What's happening?</span></div>${who}<p class="share-text">${esc(c.post||'')}</p></div><div class="decision-label">What should be done?</div>`;
    return `<div class="share-phone" data-share data-mode="${esc(c.share_mode)}" data-audience="public"><div class="share-top"><span>Post draft</span><span class="share-badge" data-share-badge>Public</span></div>${who}<p class="share-text">${esc(c.post||'')}</p><div class="share-loc" data-share-loc hidden>Location added: where you are right now</div><div class="share-controls"><div class="share-seg" role="group" aria-label="Who can see this">${Object.entries(reach).map(([k,v])=>`<button type="button" data-aud="${k}" aria-pressed="${k==='public'}">${v.label}</button>`).join('')}</div><label class="share-switch"><input type="checkbox" data-share-geo><span>Add location</span></label></div></div><div class="decision-label">What should you do before sharing?</div>`;
  }
  function initShare(){
    const card=stage.querySelector('[data-share]');if(!card)return;
    card.querySelectorAll('[data-aud]').forEach(b=>b.addEventListener('click',()=>{if(locked)return;card.dataset.audience=b.dataset.aud;card.querySelectorAll('[data-aud]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));card.querySelector('[data-share-badge]').textContent=reach[b.dataset.aud].label;}));
    card.querySelector('[data-share-geo]').addEventListener('change',e=>{card.querySelector('[data-share-loc]').hidden=!e.target.checked;});
  }
  function shareResult(){
    const card=stage.querySelector('[data-share]');if(!card)return;
    card.querySelectorAll('button,input').forEach(x=>x.disabled=true);
    if(card.dataset.mode!=='draft')return; // on safe-share examples a "this many people saw it" warning would send the wrong message
    const r=reach[card.dataset.audience],geo=card.querySelector('[data-share-geo]').checked;
    const sim=document.createElement('div');sim.className='share-reach';
    sim.innerHTML=`<strong>If this post had been shared with "${esc(r.label)}", within 24 hours:</strong><div class="share-stats"><div><b data-to="${r.views}">0</b><small>people saw it</small></div><div><b data-to="${r.shots}">0</b><small>screenshots</small></div><div><b data-to="${r.shares}">0</b><small>reshares</small></div></div><p>${geo?'Location was on: everyone who saw it also learned where you were. ':''}A smaller audience slows the spread, but that is not enough: even close friends can take a screenshot and pass it on. Details such as ID numbers, addresses or keys should never be shared with any audience. This is a sample simulation.</p>`;
    card.after(sim);
    const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
    sim.querySelectorAll('[data-to]').forEach(el=>{const to=Number(el.dataset.to);if(reduce||!to){el.textContent=to.toLocaleString('en-US');return;}const t0=performance.now();const run=now=>{const p=Math.max(0,Math.min(1,(now-t0)/1200));el.textContent=Math.round(to*(1-(1-p)**3)).toLocaleString('en-US');if(p<1)requestAnimationFrame(run);};requestAnimationFrame(run);});
  }
  function renderTask(t,mode) {
    if(!t)return;task=t;locked=false;
    const c=t.content||{},opts=c.options||[];
    let body='';
    if(t.kind==='ai'){
      body=`<div class="visual-duel">${opts.map((o,i)=>`<button class="visual-card choose-option" data-choice="${esc(o.id)}"><span class="visual-label">IMAGE ${i===0?'A':'B'}</span><img src="${esc(c.images?.[o.id]||'')}" alt="Image whose source you need to judge" loading="eager"><span class="visual-select">Choose this image ↗</span></button>`).join('')}</div><div class="mini-tip">🔍 Look at the details, but do not base your choice on “weird pixels” alone.</div>`;
    }else if(t.kind==='mail'){
      body=`<div class="mail-client"><div class="mail-sidebar"><div class="mail-top">✉ INBOX <span>●</span></div><div class="mail-folder is-selected">▣ Inbox <b>1</b></div><div class="mail-folder">☆ Starred</div><div class="mail-folder">▤ Drafts</div><div class="mail-folder">⌁ Spam</div></div><div class="mail-preview"><div class="mail-topbar">← Inbox <span>⋯</span></div><h3>${esc(c.subject||t.title)}</h3><div class="sender-line"><span class="sender-circle">✉</span><div><strong>From</strong><code>${esc(c.sender||'sample@example.com')}</code></div></div><div class="mail-body">${esc(c.body||'')}</div><div class="mail-link"><span>Link / attachment</span>${esc(c.preview||'This email has no link.')}</div></div></div>${mailChecklist()}<div class="decision-label">Is this email fake or trustworthy?</div>${optionCards(opts,'answer-grid two-columns')}`;
    }else if(t.kind==='hunt'){
      body=huntMarkup(c);
    }else if(t.kind==='password'){
      body=`${fortressMarkup(t)}<div class="password-choices">${opts.map(o=>`<button type="button" class="password-choice choose-option" data-choice="${esc(o.id)}"><span>${esc(o.text)}</span><span class="pass-arrow">↗</span></button>`).join('')}</div>`;
    }else if(t.kind==='story'){
      body=`${shareMarkup(c)}${optionCards(opts,'answer-grid')}`;
    }else if(t.kind==='quiz'){
      body=`<div class="quiz-spotlight"><div class="quiz-category">🏆 ${esc(c.category||'Cybersecurity')}</div><div class="quiz-glow">⚡</div></div>${optionCards(opts,'answer-grid two-columns')}`;
    }else{body=optionCards(opts);}
    stage.innerHTML=stageShell(t,body,mode);
    stage.querySelectorAll('.mail-check').forEach(b=>b.addEventListener('click',()=>b.setAttribute('aria-pressed',b.getAttribute('aria-pressed')==='true'?'false':'true')));
    if(t.kind==='story')initShare();
    if(t.kind==='hunt')initHunt(c);
    else stage.querySelectorAll('.choose-option').forEach(b=>b.addEventListener('click',()=>submit(b.dataset.choice,b)));
  }
  function confetti(){
    if(matchMedia('(prefers-reduced-motion: reduce)').matches)return;
    const layer=document.createElement('div');layer.className='confetti-layer';layer.setAttribute('aria-hidden','true');
    const colors=['#6d35d4','#ffb443','#26c49a','#ff638f','#54a7ef','#ffe17a'];
    for(let i=0;i<64;i++){const p=document.createElement('i');p.style.setProperty('--x',`${Math.random()*100}vw`);p.style.setProperty('--delay',`${Math.random()*.45}s`);p.style.setProperty('--spin',`${360+Math.random()*720}deg`);p.style.background=colors[i%colors.length];layer.appendChild(p);}
    document.body.appendChild(layer);setTimeout(()=>layer.remove(),2600);
  }
  async function submit(choice,button){
    if(locked)return;locked=true;button.classList.add('selected');stage.querySelectorAll('.choose-option').forEach(b=>b.disabled=true);
    try {
      const res=await post('/api/game/answer',{attempt_id:attempt,question_id:task.id,choice});
      step=res.step;points=res.score;update();
      if(task.kind==='hunt')huntResult(res,choice);
      if(task.kind==='story')shareResult();
      if(res.correct)confetti();
      const feedback=document.createElement('div');feedback.className='feedback-block '+(res.correct?'correct':'incorrect');
      feedback.innerHTML=`<div class="feedback-icon">${res.correct?'🎉':'💡'}</div><div><strong>${esc(res.feedback_title||'Answer checked')}</strong>${!res.correct&&res.correct_answer?`<div class="correct-answer">Correct option: <b>${esc(res.correct_answer)}</b></div>`:''}<p>${esc(res.explanation)}</p><small>${res.correct?'You can use this tip in real life too.':'On the next question, check the source, the context and the requested action one by one.'}</small></div>`;
      const box=document.createElement('div');box.className='feedback-area';box.appendChild(feedback);
      const next=document.createElement('button');next.className='btn btn-game btn-next';next.textContent=res.finished?'See Result →':'Next Task →';
      next.addEventListener('click',()=>res.finished?showResult(res):renderTask(res.next,res.mode));box.appendChild(next);stage.appendChild(box);
      if(task.kind==='password')fortressResult(res);
      feedback.scrollIntoView({behavior:'smooth',block:'nearest'});
      if(res.finished){clearInterval(tick);}
    } catch(error){notify(error.message);}
  }
  function showResult(result){
    const msg=points>=80?'Outstanding work!':points>=50?'Nice progress!':'Every try is a fresh start!';
    stage.innerHTML=`<div class="result-stage"><div class="result-medal">${points>=80?'🏆':points>=50?'🌟':'💪'}</div><div class="eyebrow">ADVENTURE COMPLETE</div><h2>${msg}</h2><p>Your score for this game:</p><div class="result-score">${points}<small>/ 100</small></div>${result.mode==='practice'?'<div class="practice-banner">Practice round: this score was not added to the leaderboard.</div>':'<div class="success-banner">✅ Your score has been saved to your account.</div>'}<a href="/dashboard" class="btn btn-game btn-xl">Back to Player Dashboard ➜</a><small class="autoback-note">You will return to the dashboard automatically in 8 seconds.</small><a href="/leaderboard" class="inline-link">View Leaderboard ↗</a></div>`;
    setTimeout(()=>{if(document.visibilityState!=='hidden')window.location.assign('/dashboard');},8000);
  }
})();
