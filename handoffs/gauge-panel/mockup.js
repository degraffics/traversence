(function(){
const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
const toast=(msg,undo)=>{const t=$('.toast');t.innerHTML='';const p=document.createElement('span');p.textContent=msg;t.append(p);
 if(undo){const b=document.createElement('button');b.className='b sm';b.type='button';b.textContent='Undo';b.onclick=()=>{toast('Undone: '+undo);};t.append(b);}
 t.hidden=false;clearTimeout(t._h);t._h=setTimeout(()=>t.hidden=true,5200);};
// the mockup's own switch between the two panels
$$('[data-show]').forEach(b=>b.onclick=()=>{$$('[data-show]').forEach(x=>x.setAttribute('aria-pressed',x===b));$$('[data-view]').forEach(v=>v.hidden=v.dataset.view!==b.dataset.show);});
// a segment or a legend chip filters the list; pressing it again shows all
function filt(sp,f){const on=f&&f!=='all'&&sp._f!==f?f:null;sp._f=on;
 $$('.it[data-b]',sp).forEach(li=>li.hidden=on&&li.dataset.b!==on);
 $$('[data-f]',sp).forEach(x=>{if(x.classList.contains('seg')||x.classList.contains('lg'))x.setAttribute('aria-pressed',x.dataset.f===on);});
 const n=$$('.it[data-b]:not([hidden])',sp).length,h=$('#lh-t',sp),all=$('.lh [data-f=all]',sp);
 if(h)h.textContent=on?n+' '+($('.lg[data-f='+on+']',sp)||{textContent:''}).textContent.replace(/\s*\d+$/,'').trim().toLowerCase():'All '+n+' things';if(all)all.hidden=!on;
 if(on){const l=$('.items',sp);l&&l.scrollIntoView({block:'nearest',behavior:'smooth'});}}
document.addEventListener('click',ev=>{const t=ev.target.closest('button,a');if(!t)return;const sp=t.closest('.sp');
 if(t.dataset.f!==undefined&&sp){filt(sp,t.dataset.f);return;}
 if(t.classList.contains('ex')){const ib=t.closest('.it').querySelector('.ib');const o=ib.hidden;ib.hidden=!o;t.setAttribute('aria-expanded',o);return;}
 if(t.classList.contains('spx')){toast('Closes the panel (also Esc or a click outside).');return;}
 if(t.dataset.peekFrame!==undefined){ev.preventDefault();toast('Opens '+t.getAttribute('href')+' beside the board (Peek), with Back to this panel.');return;}
 if(t.getAttribute('href')&&t.getAttribute('href').startsWith('#site-health')){ev.preventDefault();toast('Scrolls to its row in Site health, on the Development page under the gauges.');return;}
 if(t.dataset.send!==undefined){toast('Queued for the owning build session, with a note of what and why. The row now says "In a build: queued 7:42 PM MST".','the request is withdrawn');return;}
 if(t.dataset.sign!==undefined){toast(t.dataset.sign==='scope'?'Signed off: written to the logbook with your name and time. The gauge leaves Planning.':(t.dataset.sign==='target'?'Target approved: written to the logbook. The gauge moves on once its check passes.':'Signed off: this counts as reviewed, written to the logbook.'),'the sign-off is taken back');return;}
 if(t.dataset.path){toast('Opens the '+t.textContent.replace(/\s*·.*/,'')+' path: each one a row that opens its own page.');return;}
 if(t.dataset.open&&sp){ev.preventDefault();sub(sp,t.dataset.open,t.dataset.id);return;}
 if(t.dataset.back!==undefined&&sp){t.closest('.sub').remove();return;}
});
document.addEventListener('keydown',ev=>{if(ev.key==='Escape'){const s=$('.sub');if(s)s.remove();else toast('Esc closes the panel.');}});
// a finding or a check opens in place, over the panel, with Back (one panel at a time, 0175 release 2a)
function sub(sp,kind,id){const d=document.createElement('div');d.className='sub';let h='<button type="button" class="b sm" data-back>← Back</button>';
 if(kind==='finding'&&FIND[id]){const f=FIND[id];h+='<p class="crumb">Site health · '+f.rule+'</p><h3>'+esc(f.title)+'</h3><p>'+esc(f.detail)+'</p>'
  +(f.shot?'<a href="'+esc(f.shot)+'" target="_blank" rel="noopener"><div class="shot">The screenshot from the audit run (opens full size)</div></a>':'<div class="shot">This finding has no screenshot: the run that found it took none. The page itself: Open the page.</div>')
  +'<p class="meta">In the way of '+f.blocks+' path'+(f.blocks===1?'':'s')+'.</p><div class="acts"><a class="b" href="'+esc(f.page)+'" data-peek-frame>Open the page</a>'
  +(f.proposal?'<a class="b" href="'+esc(f.proposal)+'" target="_blank" rel="noopener">Its fix route: the proposal ↗</a>':'')+'<button type="button" class="b go" data-send="f">Send to a build</button><button type="button" class="b" data-sign="mark">Mark fixed</button><button type="button" class="b" data-sign="aside">Set aside, with why</button></div>';}
 else if(kind==='check'&&REG[id]){const c=REG[id];const built=/^built/.test(c.status);h+='<p class="crumb">The check register · '+id+'</p><h3>'+esc(c.name)+'</h3>'
  +'<p><b>'+(built?'Its last run: ':'Not built yet. ')+'</b>'+esc(built?c.latest:'')+'</p><p class="meta">Passes when: '+esc(c.target)+'</p><p class="meta">Runs: '+esc(c.runs)+'</p>'
  +(built?'<div class="acts"><a class="b" href="'+esc(c.evidence)+'" target="_blank" rel="noopener">Its full result ↗</a><button type="button" class="b go" data-send="c">Send the failures to a build</button></div>'
   :'<p><b>What building it needs:</b> '+esc(c.evidence)+'.</p><p><b>In the build queue:</b> not yet. Sending it queues it for its owner with this gauge as the reason.</p><div class="acts"><button type="button" class="b go" data-send="c">Send to a build</button></div>');}
 else h+='<h3>What will check it</h3><p>Nothing applies until it\'s built: once it\'s live, the audit adds the checks its kind needs (check-register.md).</p>';
 d.innerHTML=h;sp.append(d);d.querySelector('[data-back]').focus();}
function esc(s){return String(s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
})();
