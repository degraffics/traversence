(function(){
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const esc=s=>String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const MON='Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec'.split(' ');
const now=()=>{const d=new Date(),h=d.getUTCHours();return MON[d.getUTCMonth()]+' '+d.getUTCDate()+', '+(h%12||12)+':'+String(d.getUTCMinutes()).padStart(2,'0')+' '+(h<12?'AM':'PM')+' UTC';};
const toast=msg=>{const t=$('.toast');t.textContent=msg;t.hidden=false;clearTimeout(t._h);t._h=setTimeout(()=>t.hidden=true,4200);};
// the mockup's own switch between the two panels
$$('[data-show]').forEach(b=>b.onclick=()=>{$$('[data-show]').forEach(x=>x.setAttribute('aria-pressed',x===b));$$('[data-view]').forEach(v=>v.hidden=v.dataset.view!==b.dataset.show);});
// a segment or a legend chip filters the list (every match shown); pressing it again shows the first 10 again
function filt(sp,f){const on=f&&f!=='all'&&sp._f!==f?f:null;sp._f=on;const ul=$('.items',sp);
 $$('.it[data-b]',sp).forEach(li=>li.hidden=!!(on&&li.dataset.b!==on));ul.classList.toggle('all',!!on);
 $$('.seg,.lg',sp).forEach(x=>x.setAttribute('aria-pressed',x.dataset.f===on));
 const n=$$('.it[data-b]:not([hidden])',sp).length,h=$('#lh-t',sp),all=$('.lh [data-f=all]',sp),more=$('[data-more].more',sp);
 h.textContent=on?n+' · '+($('.lg[data-f='+on+']',sp).textContent.replace(/\s*\d+$/,'').trim()):'What needs you first';all.hidden=!on;if(more)more.hidden=!!on;
 if(on)ul.scrollIntoView({block:'nearest',behavior:'smooth'});}
// a finished step replaces its form (Jason, 2026-11-23): the line, then Undo puts the button back
function done(btn,line){const box=btn.closest('.acts')||btn.parentNode;const d=document.createElement('div');d.className='done';
 d.innerHTML='<span>'+line+'</span><button type="button" class="sl" data-undo>Undo</button>'+(btn.dataset.send!==undefined?'<button type="button" class="sl" data-demo>(mockup: show it cleared)</button>':'');
 d.querySelector('[data-undo]').onclick=()=>{btn.hidden=false;d.remove();toast('Undone.');};
 // step 3 confirmed by its check, then step 4: the next step as its own button (CLAUDE.md "Every action is a task")
 const demo=d.querySelector('[data-demo]');if(demo)demo.onclick=()=>{const nx=btn.dataset.next||'';box.hidden=true;const it=btn.closest('.it');if(it)$$('.chip.c-fail',it).forEach(c=>c.remove());
  d.innerHTML='<span><b class="ok">Cleared:</b> its check passed, '+now()+'.</span>'+(nx?'<button type="button" class="b go sm" data-send="next" data-what="'+esc(nx)+'">Next: '+esc(nx)+'</button>':'<span>Nothing more: every step met.</span>');};
 btn.hidden=true;box.after(d);}
document.addEventListener('click',ev=>{const t=ev.target.closest('button,a');const leg=$('.dleg:not([hidden])');
 if(leg&&!ev.target.closest('.dleg,.info')){leg.hidden=true;$('.info[aria-expanded=true]').setAttribute('aria-expanded','false');}
 if(!t)return;const sp=t.closest('.sp');
 if(t.classList.contains('info')){const l=document.getElementById(t.getAttribute('aria-controls'));l.hidden=!l.hidden;t.setAttribute('aria-expanded',!l.hidden);return;}
 if(t.dataset.dlx!==undefined){t.closest('.dleg').hidden=true;$('.info').setAttribute('aria-expanded','false');return;}
 if(t.dataset.f!==undefined&&sp){filt(sp,t.dataset.f);return;}
 if(t.dataset.more!==undefined&&sp){$('.items',sp).classList.add('all');t.hidden=true;return;}
 if(t.classList.contains('ex')){const ib=t.closest('.it').querySelector('.ib');const o=ib.hidden;ib.hidden=!o;t.setAttribute('aria-expanded',o);return;}
 if(t.classList.contains('spx')){toast('Closes the panel (also Esc or a click outside).');return;}
 if(t.dataset.peekFrame!==undefined){ev.preventDefault();toast('Opens '+t.getAttribute('href')+' beside the board (Peek), with Back to this panel.');return;}
 const href=t.getAttribute('href')||'';
 if(href.startsWith('#ac-')){ev.preventDefault();const c=$(href);c.scrollIntoView({block:'center',behavior:'smooth'});c.classList.add('flash');setTimeout(()=>c.classList.remove('flash'),1400);c.querySelector('.b').focus({preventScroll:true});return;}
 if(href.startsWith('#site-health')){ev.preventDefault();toast('Scrolls to its row in Site health, on the Development page under the gauges.');return;}
 if(t.dataset.send!==undefined){done(t,'<b>Fixing:</b> '+esc(t.dataset.what||'the issue')+' · sent to a build for its owning session · Jason, '+now()+'. It clears itself once its check passes.');return;}
 if(t.dataset.sign!==undefined){const s=t.dataset.sign;done(t,s==='scope'?'<b>Scope signed off</b> by Jason, '+now()+'. The gauge leaves Planning.':s==='target'?'<b>Target approved</b> by Jason, '+now()+'.':'<b>Done</b> by Jason, '+now()+'.');return;}
 if(t.dataset.path){toast('Opens the '+t.textContent.replace(/\s*·.*/,'')+' path: each one a row that opens its own page.');return;}
 if(t.dataset.open&&sp){ev.preventDefault();sub(sp,t.dataset.open,t.dataset.id);return;}
 if(t.dataset.back!==undefined&&sp){t.closest('.sub').remove();return;}
});
document.addEventListener('keydown',ev=>{if(ev.key!=='Escape')return;const l=$('.dleg:not([hidden])');if(l){l.hidden=true;return;}const s=$('.sub');if(s)s.remove();else toast('Esc closes the panel.');});
// a finding or a check opens in place, over the panel, with Back (one panel at a time, 0175 release 2a); one filled button
function sub(sp,kind,id){const d=document.createElement('div');d.className='sub';let h='<button type="button" class="b sm ol" data-back>← Back</button>';
 if(kind==='finding'&&FIND[id]){const f=FIND[id];h+='<p class="crumb">Site health</p><h3>'+esc(f.title)+'</h3><p>'+esc(f.detail)+'</p>'
  +(f.shot?'<a href="'+esc(f.shot)+'" target="_blank" rel="noopener"><div class="shot">The screenshot from the audit run (opens full size)</div></a>':'<div class="shot">The run that found it took no screenshot. Open the page to see it.</div>')
  +'<p class="meta">In the way of '+f.blocks+' path'+(f.blocks===1?'':'s')+'.</p><p>It clears by itself once the next audit run no longer finds it.</p><div class="acts"><button type="button" class="b go" data-send="f" data-what="'+esc(f.title)+'">Fix it</button><a class="b ol" href="'+esc(f.page)+'" data-peek-frame>Open the page</a>'
  +(f.proposal?'<a class="b ol" href="'+esc(f.proposal)+'" target="_blank" rel="noopener">Its fix: the proposal ↗</a>':'')+'</div>'
  +'<details class="src"><summary>Where this comes from</summary><p class="meta">Finding '+esc(id)+', rule '+esc(f.rule)+'</p></details>';}
 else if((kind==='check'||kind==='reach')&&REG[id]){const c=REG[id];const built=/^built/.test(c.status);h+='<p class="crumb">The checks</p><h3>'+esc(c.name)+'</h3>'
  +'<p><b>'+(built?'Its last run: ':'Not built yet.')+'</b> '+esc(built?c.latest:'')+'</p><p class="meta">Passes when: '+esc(c.target)+'</p><p class="meta">Runs: '+esc(c.runs)+'</p>'
  +(kind==='reach'?'<p><b>It doesn\'t reach this item yet.</b> A build maps the item to what the check runs on.</p><div class="acts"><button type="button" class="b go" data-send="c" data-what="'+esc(c.name.split(':')[0].toLowerCase())+' reaching it">Add a check</button><a class="b ol" href="'+esc(c.evidence)+'" target="_blank" rel="noopener">Its full result ↗</a></div>'
   :built?'<div class="acts"><button type="button" class="b go" data-send="c" data-what="'+esc(c.name.split(':')[0])+' failures">Fix it</button><a class="b ol" href="'+esc(c.evidence)+'" target="_blank" rel="noopener">Its full result ↗</a></div>'
   :'<p><b>What building it needs:</b> '+esc(c.evidence)+'.</p><p><b>In the build queue:</b> not yet.</p><div class="acts"><button type="button" class="b go" data-send="c" data-what="building '+esc(c.name.split(':')[0].toLowerCase())+'">Fix it: build the check</button></div>')
  +'<details class="src"><summary>Where this comes from</summary><p class="meta">Check id '+esc(id)+' in the check register</p></details>';}
 else h+='<h3>What will check it</h3><p>Nothing applies until it\'s built: once it\'s live, the audit adds the checks its kind needs.</p>';
 d.innerHTML=h;sp.append(d);d.querySelector('[data-back]').focus();}
})();
