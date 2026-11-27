/*
 * js/tv-ask.js — the site's own questions and notices, never the browser's (Jason, 2026-10-10; decisions/0058 §11).
 * No confirm(), alert() or prompt() anywhere: they're the browser's grey boxes, they block the page and they look
 * like someone else is talking.
 *
 *   TvAsk.confirm(msg, {anchor, yes, no, danger}) → Promise<boolean>
 *       An inline alert right under what you tapped, with the two answers. With no anchor, a small lightbox.
 *   TvAsk.say(msg, {anchor, bad})
 *       A notice (Jason, 2026-10-28): a green ✓ "Success:" or a red ! "Error:", the message, and × to close. Under what you
 *       tapped; with no anchor, a pop-up in the middle of the screen (tap the background or × to close; a success also
 *       closes itself). bad: true / false says which; left out, the wording decides ("didn't", "can't", "try again"…).
 *   A status line a page shows where it stands (<p data-tv-status="ok|info|bad" [data-tv-label="All clear:"]>…</p>) is drawn
 *       as the same box in its place, so it doesn't read as plain text (Jason, 2026-10-28). With data-tv-keep it has no ×
 *       (a line about something still open, which closes only when its check passes: decisions/0176 §3).
 *   A page's own message after a form posts (<p data-tv-flash="ok|error">…</p>) shows the same way, beside the form you
 *   sent (remembered as you send it), or in the middle when that form isn't on the page.
 *   TvAsk.ask(label, value, {anchor, yes, multiline}) → Promise<string|null>
 *       A lightbox form with one field; null when cancelled.
 *   TvAsk.peek(url, title)
 *       A page (a listing, a place) in a pop-up card over this one, so checking it doesn't leave what you're doing.
 *       Declarative: <a href="/listing/view.php?id=5" data-peek-frame>. "Open the full page" is in its corner.
 *   if (!TvAsk.gate(button, msg, {yes, danger})) return;
 *       For a click handler: asks inline under the button; on Yes it taps the button again and the handler carries on.
 *
 * Declarative, for plain forms and buttons:  data-confirm="Hide it from the site?"  (data-yes="Hide it", data-danger)
 * on a <form> or a submit <button>: the submit waits for the inline answer, then goes ahead as tapped.
 */
(function () {
  if (window.TvAsk) return;
  var css = '.tv-ask{flex-basis:100%;width:100%;box-sizing:border-box;margin:.45rem 0;padding:.65rem .75rem;border:1px solid color-mix(in srgb,var(--tv-hold) 35%,var(--tv-canvas));border-left:4px solid var(--tv-icon,#B45309);'
    + 'border-radius:.6rem;background:var(--tv-card-a);color:var(--tv-ink);font-size:.9rem;line-height:1.35;text-align:left;animation:tvAskIn .14s ease-out}'
    + '.tv-ask.bad{border-color:color-mix(in srgb,var(--tv-crimson) 35%,var(--tv-canvas));border-left-color:color-mix(in srgb,var(--tv-crimson) 35%,var(--tv-canvas));background:var(--tv-card-a)}.tv-ask.ok{border-color:color-mix(in srgb,var(--tv-moss) 40%,var(--tv-canvas));border-left-color:color-mix(in srgb,var(--tv-moss) 40%,var(--tv-canvas));background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas))}'
    + '.tv-ask p{margin:0}.tv-ask-acts{display:flex;gap:.45rem;flex-wrap:wrap;margin-top:.55rem}'
    + '.tv-ask-b{font:inherit;font-size:.86rem;font-weight:700;border-radius:.6rem;padding:.45rem .95rem;cursor:pointer;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink)}'
    + '.tv-ask-b.yes{background:var(--tv-card-b);border-color:var(--tv-line-strong);color:var(--tv-ink)}.tv-ask.danger .tv-ask-b.yes{background:var(--tv-crimson);border-color:color-mix(in srgb,var(--tv-crimson) 35%,var(--tv-canvas));color:#fff}'
    + '.tv-ask-x{float:right;margin:-.2rem -.25rem 0 .5rem;border:0;background:none;color:var(--tv-meta);font-size:1.1rem;line-height:1;cursor:pointer;padding:.15rem .3rem}'
    + '.tv-ask-lb{position:fixed;inset:0;z-index:2000;background:rgba(42,27,18,.55);display:flex;align-items:center;justify-content:center;padding:12px}'
    + '.tv-ask-lb .tv-ask{max-width:26rem;margin:0;box-shadow:0 18px 40px -12px rgba(0,0,0,.55);border-left-width:1px;padding:1rem}'
    + '.tv-ask-in{width:100%;box-sizing:border-box;margin-top:.5rem;background:var(--tv-field);border:1px solid var(--tv-line-strong);border-radius:.55rem;padding:.5rem .65rem;font:inherit;font-size:.95rem}'
    // choose(): a setting's choices, each with what it does (Jason, 2026-11-17: never a blank box you must know the answer to)
    + '.tv-ch{display:grid;gap:.4rem;margin-top:.55rem}.tv-ch label{display:flex;gap:.55rem;align-items:flex-start;padding:.5rem .6rem;border:1px solid var(--tv-line);border-radius:.55rem;background:var(--tv-card-a);cursor:pointer}'
    + '.tv-ch label:has(input:checked){border-color:var(--tv-line-strong);background:var(--tv-card-a);box-shadow:0 0 0 1px var(--tv-line-strong) inset}.tv-ch input{margin:.2rem 0 0;accent-color:var(--tv-link);flex:none}'
    + '.tv-ch b{display:block;font-size:.92rem}.tv-ch small{display:block;color:var(--tv-meta);font-size:.8rem;line-height:1.3;margin-top:.1rem}.tv-ch em{font-style:normal;font-size:.72rem;font-weight:700;color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));margin-left:.35rem}'
    + '.tv-ask-toast{position:fixed;left:50%;top:.8rem;transform:translateX(-50%);z-index:2010;width:min(26rem,calc(100vw - 24px));box-shadow:0 12px 30px -10px rgba(0,0,0,.45);transition:opacity .3s}'
    + '.tv-pk{position:relative;width:min(64rem,100%);height:min(90vh,60rem);background:var(--tv-canvas);border-radius:.8rem;overflow:hidden;box-shadow:0 22px 50px -14px rgba(0,0,0,.6);display:flex;flex-direction:column}'
    + '.tv-pk-h{display:flex;align-items:center;gap:.5rem;padding:.45rem .5rem .45rem .85rem;background:var(--tv-umber);color:var(--tv-on-umber);font-weight:700;font-size:.9rem}'
    + '.tv-pk-h span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
    + '.tv-pk-h a,.tv-pk-h button{flex:none;border:1px solid var(--tv-taupe);background:none;color:var(--tv-on-umber);border-radius:999px;font:inherit;font-size:.78rem;font-weight:600;padding:.3rem .7rem;cursor:pointer;text-decoration:none}'
    + '.tv-pk-h button{width:30px;height:30px;padding:0;font-size:1.1rem;line-height:1}'
    + '.tv-pk-h .tv-pk-back[hidden]{display:none}'
    + '.tv-pk iframe{flex:1;width:100%;border:0;background:var(--tv-canvas)}'
    + '@media (max-width:767px){.tv-ask-lb:has(.tv-pk){padding:3px}.tv-pk{height:calc(100dvh - 6px);border-radius:.5rem}}'
    // a pop-up that scrolls like a page (Jason, 2026-10-14: no scroll bar inside the box): the page inside grows to its
    // full height, the dimmed layer scrolls, and the pop-up's bar stays at the top
    + '.tv-ask-lb.tv-pk-fit{overflow-y:auto;align-items:flex-start;overscroll-behavior:contain}.tv-pk-fit .tv-pk{height:auto;overflow:visible;margin:0 auto}'
    + '.tv-pk-fit .tv-pk-h{position:sticky;top:-12px;z-index:2;border-radius:.8rem .8rem 0 0}.tv-pk-fit .tv-pk iframe{flex:none;height:70vh}'
    + '@media (max-width:767px){.tv-pk-fit .tv-pk{height:auto;min-height:calc(100dvh - 6px)}.tv-pk-fit .tv-pk-h{top:-3px}}'
    // notices (Jason, 2026-10-28): an icon block on the left, a bold label, the message, × on the right
    + '.tv-note{display:flex;align-items:stretch;flex-basis:100%;width:100%;box-sizing:border-box;margin:.45rem 0;border:1px solid color-mix(in srgb,var(--tv-moss) 40%,var(--tv-canvas));border-radius:.4rem;background:color-mix(in srgb,var(--tv-moss) 18%,var(--tv-canvas));color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink));font-size:.92rem;line-height:1.4;text-align:left;overflow:hidden;animation:tvAskIn .14s ease-out}'
    + '.tv-note-i{flex:none;width:2.6rem;display:flex;align-items:center;justify-content:center;background:var(--tv-moss)}.tv-note-i svg{width:1.35rem;height:1.35rem}'
    + '.tv-note-t{flex:1;min-width:0;padding:.6rem .4rem .6rem .75rem;overflow-wrap:anywhere;white-space:pre-line}.tv-note-t b{font-weight:800;margin-right:.3rem}'
    + '.tv-note-x{flex:none;align-self:flex-start;border:0;background:none;color:var(--tv-meta);transition:color var(--tv-ease);font-size:1.25rem;line-height:1;cursor:pointer;padding:.5rem .65rem}.tv-note-x:hover{color:color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))}'
    + '.tv-note.bad{border-color:color-mix(in srgb,var(--tv-crimson) 35%,var(--tv-canvas));background:color-mix(in srgb,var(--tv-crimson) 10%,var(--tv-canvas));color:var(--tv-crimson)}.tv-note.bad .tv-note-i{background:var(--tv-crimson)}.tv-note.bad .tv-note-x{color:var(--tv-crimson)}.tv-note.bad .tv-note-x:hover{color:var(--tv-crimson)}'
    + '.tv-note.info{border-color:var(--tv-line);background:var(--tv-card-a);color:var(--tv-ink)}.tv-note.info .tv-note-i{background:var(--tv-icon,#B45309)}.tv-note.info .tv-note-x{color:var(--tv-meta)}.tv-note.info .tv-note-x:hover{color:var(--tv-ink)}'
    + '.tv-ask-lb .tv-note{max-width:30rem;margin:0;box-shadow:0 18px 40px -12px rgba(0,0,0,.55)}.tv-ask-lb .tv-note-t{padding:.9rem .5rem .9rem .9rem;font-size:1rem}'
    + '@keyframes tvAskIn{from{opacity:0;transform:translateY(-3px)}to{opacity:1;transform:none}}';
  // busy: a spinner on whatever was clicked while it works (Jason, 2026-11-02: a click that doesn't finish at once shows it's working)
  css += '.tv-busy{position:relative;cursor:progress!important;opacity:.85}.tv-busy::before{content:"";display:inline-block;width:.9em;height:.9em;margin-right:.45em;vertical-align:-.12em;'
    + 'border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:tvSpin .7s linear infinite}@keyframes tvSpin{to{transform:rotate(360deg)}}';
  var st = document.createElement('style'); st.textContent = css; (document.head || document.documentElement).appendChild(st);

  var open = null;   // one inline question at a time
  // A tool shown inside an admin area page (an iframe with data-fit, grown to its full height so only the page scrolls):
  // its pop-ups, lightboxes and toasts open on the page around it, over everything, instead of inside the frame.
  var host = (function () { try { var f = window.frameElement; return f && (f.hasAttribute('data-fit') || f.hasAttribute('data-host')) && parent.TvAsk ? parent.TvAsk : null; } catch (e) { return null; } })();
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function place(box, anchor) {
    var at = anchor && (anchor.closest('form') || anchor);
    if (at && at.parentNode) at.parentNode.insertBefore(box, at.nextSibling); else document.body.appendChild(box);
    // beside something in a grid or a row: the notice takes a line of its own, the full width, never squeezed into a column.
    // In a flex row that wraps, it takes the whole line; in one that doesn't wrap (an alarm's row: its mark, then its card),
    // it goes under the row instead (2026-11-24: Check and start again's answer stood as a tall green bar beside W1's card)
    try { var ps = getComputedStyle(box.parentNode), pd = ps.display;
      if (/grid/.test(pd)) box.style.gridColumn = '1 / -1';
      else if (/flex/.test(pd) && !/column/.test(ps.flexDirection)) {
        if (ps.flexWrap !== 'nowrap') box.style.flex = '1 0 100%';
        else if (box.parentNode.parentNode) box.parentNode.parentNode.insertBefore(box, box.parentNode.nextSibling);
      } } catch (x) {}
    try { box.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); } catch (x) {}
  }
  function lightbox(box, onCancel) {
    var lb = el('div', 'tv-ask-lb'); lb.appendChild(box); document.body.appendChild(lb);
    lb.addEventListener('click', function (e) { if (e.target === lb) onCancel(); });
    return lb;
  }

  function confirmAsk(msg, o) {
    if (host && !(o && o.anchor)) return host.confirm(msg, o);
    o = o || {};
    if (open) open(false);
    return new Promise(function (res) {
      var box = el('div', 'tv-ask' + (o.danger ? ' danger' : '')); box.setAttribute('role', 'alertdialog'); box.setAttribute('aria-live', 'assertive');
      box.appendChild(el('p', '', msg));
      var acts = el('div', 'tv-ask-acts'), yes = el('button', 'tv-ask-b yes', o.yes || 'Yes'), no = el('button', 'tv-ask-b', o.no || 'Cancel');
      yes.type = no.type = 'button'; acts.appendChild(yes); acts.appendChild(no); box.appendChild(acts);
      var lb = null, prev = document.activeElement;
      var done = function (v) {
        if (!box.isConnected && !(lb && lb.isConnected)) return;
        (lb || box).remove(); document.removeEventListener('keydown', key, true); open = null;
        if (prev && prev.focus && v === false) try { prev.focus({ preventScroll: true }); } catch (x) {}
        res(v);
      };
      var key = function (e) { if (e.key === 'Escape') { e.stopPropagation(); done(false); } };
      yes.addEventListener('click', function (e) { e.stopPropagation(); done(true); });
      no.addEventListener('click', function (e) { e.stopPropagation(); done(false); });
      document.addEventListener('keydown', key, true);
      if (o.anchor) place(box, o.anchor); else lb = lightbox(box, function () { done(false); });
      open = done;
      setTimeout(function () { try { yes.focus({ preventScroll: true }); } catch (x) {} }, 0);
    });
  }

  // a message's kind from its wording, when the caller didn't say: problems say so ("didn't", "can't", "try again")
  var BAD = /(didn[’']t|couldn[’']t|can[’']t|cannot|wasn[’']t|isn[’']t|aren[’']t|won[’']t|doesn[’']t|not (sent|saved|found|allowed|here)|\bno (signal|longer)\b|error|failed|wrong|invalid|blocked|unavailable|expired|try again|first\b|^choose|^enter|^add |^type|^pick|^give|^tick|^only|^up to|too (big|large|long|many)|missing|required|staff only|not (yet )?(approved|ready))/i;
  var ICON = { ok: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#fff"/><path d="M6.8 12.4l3.4 3.4 7-7.2" fill="none" stroke="color-mix(in srgb,var(--tv-moss) 50%,var(--tv-ink))" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
               info: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#fff"/><circle cx="12" cy="7.2" r="1.6" fill="var(--tv-icon,#B45309)"/><path d="M12 11v6.5" stroke="var(--tv-icon,#B45309)" stroke-width="2.8" stroke-linecap="round"/></svg>',
               bad: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="#fff"/><path d="M12 6.5v7" stroke="var(--tv-crimson)" stroke-width="2.8" stroke-linecap="round"/><circle cx="12" cy="17.3" r="1.6" fill="var(--tv-crimson)"/></svg>' };
  function note(msg, bad) {
    var box = el('div', 'tv-note' + (bad ? ' bad' : '')); box.setAttribute('role', bad ? 'alert' : 'status');
    var i = el('div', 'tv-note-i'); i.innerHTML = ICON[bad ? 'bad' : 'ok'];
    var t = el('div', 'tv-note-t'); t.appendChild(el('b', '', bad ? 'Error:' : 'Success:')); t.appendChild(document.createTextNode(String(msg)));
    var x = el('button', 'tv-note-x', '×'); x.type = 'button'; x.setAttribute('aria-label', 'Close');
    box.appendChild(i); box.appendChild(t); box.appendChild(x);
    return box;
  }
  function say(msg, o) {
    if (host && !(o && o.anchor)) return host.say(msg, o);
    o = o || {};
    var bad = o.bad === true || (o.bad !== false && BAD.test(String(msg)));
    var box = note(msg, bad), x = box.querySelector('.tv-note-x');
    if (o.anchor) {
      var at = o.anchor.closest('form') || o.anchor, nx = at.nextSibling;
      if (nx && nx.classList && (nx.classList.contains('tv-note') || (nx.classList.contains('tv-ask') && !nx.querySelector('.tv-ask-acts')))) nx.remove();   // replace the last notice here
      x.addEventListener('click', function (e) { e.stopPropagation(); box.remove(); });
      place(box, o.anchor);
      return box;
    }
    // nowhere to put it: a pop-up in the middle (tap the background or ×); a success closes itself after a while
    document.querySelectorAll('.tv-ask-lb.tv-note-lb').forEach(function (n) { n.remove(); });
    var lb, key = function (e) { if (e.key === 'Escape') shut(); };
    var shut = function () { if (lb && lb.isConnected) lb.remove(); document.removeEventListener('keydown', key, true); };
    x.addEventListener('click', function (e) { e.stopPropagation(); shut(); });
    lb = lightbox(box, shut); lb.classList.add('tv-note-lb');
    document.addEventListener('keydown', key, true);
    if (!bad && !o.stay) setTimeout(shut, 4500);
    setTimeout(function () { try { x.focus({ preventScroll: true }); } catch (e) {} }, 0);
    return box;
  }

  // ---- a page's own message after a form posts, shown where you were (Jason, 2026-10-28) ---------------------------
  // As a form is sent, what it was is remembered (its "do", its item, or its place); when the page comes back with a
  // message (data-tv-flash), the message shows beside that form, or in the middle when that form isn't on the page.
  function formKey(f) {
    var d = f.elements && f.elements['do'], k = { path: location.pathname, at: Date.now() };
    if (d && d.value) { k['do'] = d.value; ['line', 'id', 'wo', 'proof', 'file', 'job'].forEach(function (n) { if (f.elements[n] && f.elements[n].value) k[n] = f.elements[n].value; }); }
    var a = f.closest('[id]'); if (a) k.near = a.id;
    return k;
  }
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f || f.tagName !== 'FORM' || (f.getAttribute('method') || '').toLowerCase() !== 'post') return;
    try { sessionStorage.setItem('tv-sent', JSON.stringify(formKey(f))); } catch (x) {}
  }, true);
  function visible(f) { return !!f.getClientRects().length && (!f.checkVisibility || f.checkVisibility()); }
  function shown(f) {   // on the page: a closed section it's in opens again (it was open when you sent it)
    var opened = [];
    for (var d = f.closest('details'); d; d = d.parentElement && d.parentElement.closest('details')) if (!d.open) { d.open = true; opened.push(d); }
    if (visible(f)) return true;
    opened.forEach(function (d) { d.open = false; }); return false;
  }
  function findForm(k) {
    var best = null;
    if (k['do']) document.querySelectorAll('form').forEach(function (f) {
      if (best || !f.elements['do'] || f.elements['do'].value !== k['do']) return;
      if (['line', 'wo', 'proof', 'file', 'job'].some(function (n) { return k[n] && f.elements[n] && f.elements[n].value !== k[n]; })) return;
      if (shown(f)) best = f;
    });
    // the form itself is gone (what it did changed the page): beside the section it was in, only when that section is that
    // one form's (a section of many forms would put it beside the wrong one), and never by opening a closed menu
    if (!best && k.near) { var n = document.getElementById(k.near); if (n && n.querySelectorAll('form').length <= 1 && n !== document.body && !n.closest('details:not([open])') && visible(n)) best = n.querySelector('form') || n; }
    return best;
  }
  function flashes() {
    var k = null; try { k = JSON.parse(sessionStorage.getItem('tv-sent') || 'null'); sessionStorage.removeItem('tv-sent'); } catch (x) {}
    if (k && (k.path !== location.pathname || Date.now() - (k.at || 0) > 120000)) k = null;
    document.querySelectorAll('[data-tv-flash]').forEach(function (m) {
      if (m.hidden || m.__tvDone || !m.textContent.trim()) return;   // the page showed it its own way already
      m.__tvDone = true;
      var bad = /^(error|err|bad|no|0|false)$/i.test(m.getAttribute('data-tv-flash')) ? true : (/^(ok|success|1|true)$/i.test(m.getAttribute('data-tv-flash')) ? false : undefined);
      var at = k ? findForm(k) : null, text = m.textContent.trim();
      m.hidden = true; m.style.display = 'none';
      if (at) { var b = say(text, { anchor: at, bad: bad }); setTimeout(function () { try { b.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (x) {} }, 50); }
      else say(text, { bad: bad, stay: bad !== false });
    });
  }
  window.addEventListener('load', function () { setTimeout(flashes, 150); });

  // ---- a page's status lines, drawn as the notice box where they stand ("No listings need you", "3 were merged") ----
  function statuses(root) {
    (root || document).querySelectorAll('[data-tv-status]').forEach(function (m) {
      var k = (m.getAttribute('data-tv-status') || 'ok').toLowerCase(), bad = /^(bad|error|err)$/.test(k), info = k === 'info';
      var box = note('', bad), t = box.querySelector('.tv-note-t'), lab = t.querySelector('b');
      if (info) { box.classList.add('info'); box.querySelector('.tv-note-i').innerHTML = ICON.info; box.setAttribute('role', 'status'); }
      lab.textContent = m.getAttribute('data-tv-label') || (bad ? 'Error:' : (info ? 'Note:' : 'Success:'));
      t.removeChild(t.lastChild);   // the empty text note() added
      while (m.firstChild) t.appendChild(m.firstChild);   // keeps its links and bold
      if (t.childNodes[1] && t.childNodes[1].nodeType === 3) t.childNodes[1].nodeValue = t.childNodes[1].nodeValue.replace(/^\s*[✓!]\s*/, '');
      if (m.classList.contains('tv-bleed')) box.classList.add('tv-bleed');
      // a line about something still open (a service's issue, decisions/0176 §3) has no ×: it closes only when its check
      // passes, so nothing may look like it dismisses the problem (data-tv-keep)
      if (m.hasAttribute('data-tv-keep')) box.querySelector('.tv-note-x').remove(); else box.querySelector('.tv-note-x').addEventListener('click', function () { box.remove(); });
      m.replaceWith(box);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { statuses(); }); else statuses();   // after the page's own scripts (which may place it themselves)

  function ask(label, value, o) {
    if (host) return host.ask(label, value, Object.assign({}, o || {}, { anchor: null }));
    o = o || {};
    if (open) open(false);
    return new Promise(function (res) {
      var box = el('form', 'tv-ask'); box.setAttribute('role', 'dialog');
      var l = el('label', '', label); l.style.fontWeight = '700'; box.appendChild(l);
      var inp = o.multiline ? el('textarea', 'tv-ask-in') : el('input', 'tv-ask-in'); if (o.multiline) inp.rows = 3;
      inp.value = value || ''; l.appendChild(inp);
      var acts = el('div', 'tv-ask-acts'), yes = el('button', 'tv-ask-b yes', o.yes || 'Save'), no = el('button', 'tv-ask-b', 'Cancel');
      yes.type = 'submit'; no.type = 'button'; acts.appendChild(yes); acts.appendChild(no); box.appendChild(acts);
      var lb;
      var done = function (v) { if (!lb.isConnected) return; lb.remove(); document.removeEventListener('keydown', key, true); open = null; res(v); };
      var key = function (e) { if (e.key === 'Escape') { e.stopPropagation(); done(null); } };
      box.addEventListener('submit', function (e) { e.preventDefault(); done(inp.value); });
      no.addEventListener('click', function () { done(null); });
      document.addEventListener('keydown', key, true);
      lb = lightbox(box, function () { done(null); });
      open = function () { done(null); };
      setTimeout(function () { inp.focus(); if (inp.select) inp.select(); }, 0);
    });
  }

  /**
   * A setting's choices, each with its meaning: choices [[value, name, what it does], …]; resolves to the value picked
   * (o.multi: tick boxes, values joined with commas), or null on Cancel. The current one is marked "Now".
   */
  function choose(label, choices, value, o) {
    if (host && host.choose) return host.choose(label, choices, value, o);
    o = o || {};
    if (open) open(false);
    var now = String(value || '').split(',').map(function (x) { return x.trim(); });
    return new Promise(function (res) {
      var box = el('form', 'tv-ask'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', label);
      var l = el('div', '', label); l.style.fontWeight = '700'; box.appendChild(l);
      if (o.note) { var n = el('p', '', o.note); n.style.cssText = 'font-size:.82rem;color:var(--tv-meta);margin-top:.2rem'; box.appendChild(n); }
      var list = el('div', 'tv-ch'), nm = 'tvch' + Math.random().toString(36).slice(2, 8), first = null;
      (choices || []).forEach(function (c) {
        var row = el('label'), inp = document.createElement('input'), t = el('span');
        inp.type = o.multi ? 'checkbox' : 'radio'; inp.name = nm; inp.value = c[0]; inp.checked = now.indexOf(String(c[0])) >= 0;
        var b = el('b', '', c[1]); if (inp.checked && !o.multi) b.appendChild(el('em', '', 'Now'));
        t.appendChild(b); if (c[2]) t.appendChild(el('small', '', c[2]));
        row.appendChild(inp); row.appendChild(t); list.appendChild(row);
        if (!first || (inp.checked && !first.checked)) first = inp;   // focus the current choice, else the first
      });
      box.appendChild(list);
      var acts = el('div', 'tv-ask-acts'), yes = el('button', 'tv-ask-b yes', o.yes || 'Save'), no = el('button', 'tv-ask-b', 'Cancel');
      yes.type = 'submit'; no.type = 'button'; acts.appendChild(yes); acts.appendChild(no); box.appendChild(acts);
      var lb;
      var done = function (v) { if (!lb.isConnected) return; lb.remove(); document.removeEventListener('keydown', key, true); open = null; res(v); };
      var key = function (e) { if (e.key === 'Escape') { e.stopPropagation(); done(null); } };
      box.addEventListener('submit', function (e) {
        e.preventDefault();
        var picked = [].filter.call(list.querySelectorAll('input'), function (i) { return i.checked; }).map(function (i) { return i.value; });
        if (!o.multi && !picked.length) { say('Choose one first.', { anchor: yes, bad: true }); return; }
        done(picked.join(','));
      });
      no.addEventListener('click', function () { done(null); });
      document.addEventListener('keydown', key, true);
      lb = lightbox(box, function () { done(null); });
      open = function () { done(null); };
      setTimeout(function () { try { (first || yes).focus({ preventScroll: true }); } catch (x) {} }, 0);
    });
  }

  function gate(b, msg, o) {
    if (b.__tvOk) { b.__tvOk = false; return true; }
    confirmAsk(msg, Object.assign({ anchor: b }, o || {})).then(function (ok) { if (ok) { b.__tvOk = true; b.click(); b.__tvOk = false; } });
    return false;
  }

  // One part in the panel (0175's "one place"; Jason, 2026-11-23: "when I am opening the google service registry from the way I
  // routed into the panel shouldn't be the only thing in that panel vs having all services?"): a page opened in a side panel
  // or a drawer at a part's address (#svc-google, #svc-google-key, #job-sales_tick, #mc-…) shows that part's card alone;
  // "Open the full page" in the panel's header has the whole list.
  (function () {
    var framed = window.parent !== window && /[?&](peek|embed|panel)=1\b/.test(location.search);
    if (!framed) return;
    var st = null;
    function only() {
      var id = decodeURIComponent((location.hash || '').slice(1));
      document.querySelectorAll('[data-part-off]').forEach(function (x) { x.removeAttribute('data-part-off'); });
      if (!/^(svc|job|mc|wf|pf)-[\w.-]+$/.test(id)) return;
      var el = document.getElementById(id); if (!el) return;
      var part = el.closest('.os-r, .os-part, .mc-card, [id^="job-"], [data-part]') || el;
      if (!st) { st = document.createElement('style'); st.textContent = '[data-part-off]{display:none!important}'; document.head.appendChild(st); }
      for (var n = part; n && n !== document.body; n = n.parentElement) {
        var p = n.parentElement; if (!p) break;
        for (var i = 0; i < p.children.length; i++) { var c = p.children[i]; if (c !== n && !/^(SCRIPT|STYLE|LINK|DIALOG)$/.test(c.tagName) && !c.classList.contains('tv-pk') && !c.matches('[role=dialog],.tv-note,.tv-ask,[data-tv-flash]')) c.setAttribute('data-part-off', ''); }
      }
      if (part.tagName === 'DETAILS') part.open = true;
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { setTimeout(only, 0); }); else setTimeout(only, 0);
    window.addEventListener('hashchange', only);
  })();

  // One panel at a time (decisions/0175 §6; Jason, 2026-11-20: a listing opened from W1's panel stacked a second header over
  // the first): a link inside a panel replaces what the panel shows, with Back to where it was; never a panel in a panel.
  var inPeek = (function () { try { return window.parent !== window && /[?&]peek=1/.test(location.search) && parent.TvAsk && parent.location.origin === location.origin; } catch (e) { return false; } })();
  var cur = null;   // the open panel: {f, t, full, back, stack: [[url, title]], url, title}
  function peekSrc(url) {
    var hi = url.indexOf('#'), base = hi < 0 ? url : url.slice(0, hi);
    return base + (base.indexOf('?') >= 0 ? '&' : '?') + 'embed=1&peek=1' + (hi < 0 ? '' : url.slice(hi));
  }
  function peekShow(url, title) {
    cur.url = url; cur.title = title || 'Preview';
    cur.f.src = peekSrc(url); cur.f.title = cur.title; cur.t.textContent = cur.title; cur.full.href = url;
    cur.back.hidden = !cur.stack.length;
  }
  // inside an admin drawer's frame, or with one open over this page: that drawer is the one panel and shows it, with Back
  // (Jason, 2026-11-23: a register entry opened from a process's page in an alarm's drawer stacked a third header)
  var kitHost = (function () { try { var f = window.frameElement; return f && f.closest && f.closest('.ak-dr') && parent.TvAdmin && parent.TvAdmin.frame ? parent.TvAdmin : null; } catch (e) { return null; } })();
  function peek(url, title, opts) {
    if (!(opts && opts.inside)) {
      if (kitHost) return kitHost.frame(url, title || 'Preview');
      if (window.TvAdmin && TvAdmin.isOpen && TvAdmin.isOpen() && !(opts && opts.owner)) return TvAdmin.frame(url, title || 'Preview');
    }
    if (inPeek && !(opts && opts.inside)) return parent.TvAsk.peek(url, title, { replace: true });   // in a panel: the panel shows it, with Back
    if (host) return host.peek(url, title, { owner: document, fit: !!(opts && opts.fit), replace: !!(opts && opts.replace) });   // over the whole page, not inside the tool's frame
    if (opts && opts.replace && cur && open) { cur.stack.push([cur.url, cur.title]); peekShow(url, title); return; }
    var owner = (opts && opts.owner) || document;   // whose page asked: it hears when the pop-up is done or closed
    if (open) open(false);
    // its own settings go before the #, never after it: "/admin/config/register#svc-aws" opens on that card (Jason, 2026-11-19:
    // "Open card shows the specific place to fix issues"); after the #, they became part of the anchor and it landed nowhere
    var u = peekSrc(url);
    var box = el('div', 'tv-pk'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', title || 'Preview');
    var h = el('div', 'tv-pk-h'), back = el('a', 'tv-pk-back', '← Back'), t = el('span', '', title || 'Preview'), full = el('a', '', 'Open the full page'), x = el('button', '', '×');
    back.href = '#'; back.hidden = true; back.setAttribute('aria-label', 'Back to what this panel showed before');
    full.href = url; full.target = '_blank'; full.rel = 'noopener'; x.type = 'button'; x.setAttribute('aria-label', 'Close');
    h.appendChild(back); h.appendChild(t); h.appendChild(full); h.appendChild(x); box.appendChild(h);
    var f = document.createElement('iframe'); f.src = u; f.title = title || 'Preview'; box.appendChild(f);
    cur = { f: f, t: t, full: full, back: back, stack: [], url: url, title: title || 'Preview' };
    back.addEventListener('click', function (e) { e.preventDefault(); if (!cur || !cur.stack.length) return; var p = cur.stack.pop(); peekShow(p[0], p[1]); });
    var lb, key = function (e) { if (e.key === 'Escape') close(); };
    // the page inside can say it's done (Review's pop-ups: {tvReviewDone}) or ask to close (Esc pressed inside it)
    var msg = function (e) {
      if (e.source !== f.contentWindow || e.origin !== location.origin || !e.data) return;
      if (e.data.tvPeekClose) close();
      if (e.data.tvReviewDone !== undefined) {
        owner.dispatchEvent(new CustomEvent('tv-review-done', { detail: e.data, bubbles: true }));
        setTimeout(close, 1300);   // long enough to see the ✓, then back to the list
      }
    };
    var close = function () {
      if (lb && lb.isConnected) lb.remove(); document.removeEventListener('keydown', key, true); window.removeEventListener('message', msg); open = null; cur = null;
      owner.dispatchEvent(new CustomEvent('tv-peek-closed', { detail: { url: url }, bubbles: true }));
    };
    window.addEventListener('message', msg);
    x.addEventListener('click', close); document.addEventListener('keydown', key, true);
    lb = lightbox(box, close); open = close;
    if (opts && opts.fit) { lb.classList.add('tv-pk-fit'); f.setAttribute('data-host', ''); fitFrame(f); }
    f.addEventListener('load', function () { try { var d = f.contentDocument; if (d && d.title && cur && cur.f === f) t.textContent = cur.title || d.title.replace(/\s*\|\s*Traversence$/, ''); } catch (e) {} });
    setTimeout(function () { try { x.focus({ preventScroll: true }); } catch (e) {} }, 0);
  }
  // inside a pop-up: Esc closes it, unless one of the page's own questions is open
  if (window.parent !== window && /[?&]peek=1/.test(location.search)) document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !open && !document.querySelector('.tv-ask-lb')) { try { parent.postMessage({ tvPeekClose: 1 }, location.origin); } catch (x) {} }
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[data-peek-frame]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return;   // a deliberate new tab still works
    e.preventDefault(); peek(a.getAttribute('href'), a.getAttribute('data-peek-frame') || a.textContent.trim(), { fit: a.hasAttribute('data-peek-fit') });
  });

  // An admin area page's tool frame grows to fit what's in it, so there's one scroll bar: the page's (decisions/0078).
  function fitFrame(f) {
    if (!f || f.__tvFit) return; f.__tvFit = true;
    f.setAttribute('scrolling', 'no');
    var ro = null, set = function () {
      try {
        var d = f.contentDocument; if (!d || !d.body) return;
        d.documentElement.style.overflow = 'hidden';
        var h = Math.ceil(d.body.getBoundingClientRect().height) + 24;
        if (Math.abs(h - f.offsetHeight) > 2) f.style.height = Math.max(240, h) + 'px';
      } catch (e) {}
    };
    var hook = function () {
      if (ro) ro.disconnect();
      try { var d = f.contentDocument; if (d && d.body && window.ResizeObserver) { ro = new ResizeObserver(set); ro.observe(d.body); } } catch (e) {}
      set();
    };
    f.addEventListener('load', hook);
    if (f.contentDocument && f.contentDocument.readyState === 'complete') hook();
  }

  // ---- Pill rows that never scroll sideways (Jason, 2026-10-14): what fits shows, the rest are under "More ▾" ----
  // Any row marked data-more (tabs, chips, filters). The chosen one always stays in view. Rows built or changed later
  // by a page's own script are picked up on their own. Children marked data-keep (a row's own buttons, like the profile's +
  // and layout switch) never fold: they stay at the row's end, after More (Jason, 2026-11-11).
  var tvmCss = '.tvpills{flex-wrap:nowrap!important;overflow:visible!important}.tvm-wrap{position:relative;flex:none;display:inline-flex}'
    + '.tvm-btn{cursor:pointer;white-space:nowrap}.tvm-menu{position:absolute;right:0;top:calc(100% + 4px);z-index:60;background:var(--tv-card-a);border:1px solid var(--tv-line-strong);'
    + 'border-radius:.7rem;box-shadow:0 10px 24px rgba(42,27,18,.18);padding:.3rem;display:flex;flex-direction:column;gap:.15rem;min-width:12rem;max-height:70vh;overflow:auto}'
    + '.tvm-menu[hidden]{display:none}.tvm-menu>*{display:flex!important;width:100%;justify-content:space-between;align-items:center;gap:.5rem;border-radius:.5rem!important;'
    + 'white-space:nowrap;margin:0!important;flex:none!important;box-sizing:border-box}';
  var ON = function (x) { return x.classList.contains('on') || x.classList.contains('active') || x.getAttribute('aria-selected') === 'true' || x.hasAttribute('aria-current'); };
  function more(row) {
    if (!row || row.__tvm) return; row.__tvm = true;
    if (!document.getElementById('tvm-css')) { var st = document.createElement('style'); st.id = 'tvm-css'; st.textContent = tvmCss; document.head.appendChild(st); }
    row.classList.add('tvpills');   // not .tvm: that's the map's (js/map-view.js), fixed to the foot of the screen
    var wrap = el('span', 'tvm-wrap'), btn = document.createElement('button'), menu = el('div', 'tvm-menu');
    btn.type = 'button'; btn.setAttribute('aria-haspopup', 'true'); btn.setAttribute('aria-expanded', 'false'); menu.hidden = true;
    wrap.appendChild(btn); wrap.appendChild(menu);
    var busy = false, n = 0;
    var items = function () {
      var all = [].slice.call(row.children).filter(function (c) { return c !== wrap && !c.hasAttribute('data-keep'); }).concat([].slice.call(menu.children));
      all.forEach(function (c) { if (c.__tvmI === undefined) c.__tvmI = 1e6 + (n++); });
      return all.sort(function (a, b) { return a.__tvmI - b.__tvmI; });
    };
    var close = function () { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); };
    var layout = function () {
      if (busy) return; busy = true;
      try {
        var all = items();
        if (!all.length) { if (wrap.parentNode) wrap.remove(); return; }
        if (!all.some(function (c) { return c.__tvmI < 1e6; })) all.forEach(function (c, i) { c.__tvmI = i; });   // first sight: their order as given
        var keep = [].slice.call(row.children).filter(function (c) { return c.hasAttribute('data-keep'); });
        all.forEach(function (c) { row.appendChild(c); });
        keep.forEach(function (c) { row.appendChild(c); });   // always last, always shown
        if (wrap.parentNode) wrap.remove();
        if (row.scrollWidth <= row.clientWidth + 1) { close(); return; }
        var first = all[0];
        btn.className = 'tvm-btn ' + String(first.className || '').replace(/\b(on|active)\b/g, '');
        btn.textContent = 'More 99 ▾';   // measured at its widest, so it never ends up cut off at the edge
        row.insertBefore(wrap, keep[0] || null);
        var gap = parseFloat(getComputedStyle(row).columnGap) || 0;
        var room = row.clientWidth - wrap.offsetWidth - gap - keep.reduce(function (t, c) { return t + c.offsetWidth + gap; }, 0);
        var act = all.filter(ON)[0], used = act ? act.offsetWidth + gap : 0, out = [];
        all.forEach(function (c) {
          if (c === act) return;
          var w = c.offsetWidth + gap;
          if (!out.length && used + w <= room) used += w; else out.push(c);
        });
        if (!out.length) { wrap.remove(); return; }
        out.forEach(function (c) { menu.appendChild(c); });
        // what's waiting in the hidden ones shows on More, so nothing that needs you is out of sight
        var waiting = 0;
        out.forEach(function (c) { [].forEach.call(c.querySelectorAll('b'), function (x) { var v = parseInt(String(x.textContent).replace(/\D/g, ''), 10); if (v) waiting += v; }); });
        btn.textContent = 'More ';
        if (waiting) { var bb = el('b', '', waiting > 99 ? '99+' : String(waiting)); btn.appendChild(bb); btn.appendChild(document.createTextNode(' ')); }
        btn.appendChild(document.createTextNode('▾'));
        btn.setAttribute('aria-label', 'More: ' + out.length + ' more' + (waiting ? ', ' + waiting + ' waiting' : ''));
      } finally { setTimeout(function () { busy = false; }, 0); }
    };
    var soon = null, later = function () { if (busy) return; cancelAnimationFrame(soon); soon = requestAnimationFrame(layout); };
    btn.addEventListener('click', function (e) {
      e.stopPropagation(); menu.hidden = !menu.hidden; btn.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
      if (!menu.hidden) { menu.style.left = ''; menu.style.right = ''; var r = menu.getBoundingClientRect(); if (r.left < 8) { menu.style.left = '0'; menu.style.right = 'auto'; } }
    });
    menu.addEventListener('click', function () { setTimeout(function () { close(); later(); }, 0); });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
    if (window.ResizeObserver) new ResizeObserver(later).observe(row); else window.addEventListener('resize', later);
    if (window.MutationObserver) new MutationObserver(function (ms) {
      // the page redrew the row (innerHTML): what was under More belongs to the old row, so it goes
      if (!busy && ms.some(function (m) { return [].indexOf.call(m.removedNodes, wrap) >= 0; })) { menu.innerHTML = ''; n = 0; close(); }
      later();
    }).observe(row, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'aria-selected', 'aria-current', 'hidden'] });
    layout();
  }
  function moreAll(root) { [].forEach.call((root || document).querySelectorAll('[data-more]'), more); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { moreAll(); }); else moreAll();
  if (window.MutationObserver) new MutationObserver(function (ms) {
    ms.forEach(function (m) { [].forEach.call(m.addedNodes, function (x) { if (x.nodeType === 1) { if (x.hasAttribute('data-more')) more(x); else if (x.querySelector) moreAll(x); } }); });
  }).observe(document.documentElement, { childList: true, subtree: true });

  // ---- An SVG QR download asks its printed size first (Jason, 2026-10-16; decisions/0094) -------------------------
  // Any link to /api/qr.php with format=svg&download=1 and no size: a pop-up of sizes (the code itself, in inches; the
  // clear margin is added around it), then the download. The sizes match Qr::SIZES. The last one picked is remembered.
  var QR_SIZES = [['0.8', 'Smallest, scanned up close'], ['1', 'Business card'], ['1.5', 'Flyer, menu, label'], ['2', 'Postcard, table tent'],
                  ['3', 'Sign, window, door'], ['4', 'Poster, scanned from about 3 ft'], ['8', 'Poster, from about 6 ft'], ['12', 'Banner, from about 10 ft']];
  var qrCss = '.tv-ask-lb.tvqs-lb{z-index:1900}.tvqs{max-width:24rem}.tvqs h3{margin:0 0 .15rem;font-size:1rem}.tvqs .l{display:grid;gap:.3rem;margin-top:.55rem}'
    + '.tvqs .l button{display:flex;justify-content:space-between;gap:.6rem;align-items:baseline;text-align:left;font:inherit;font-size:.88rem;padding:.45rem .7rem;'
    + 'border:1px solid var(--tv-line-strong);border-radius:.6rem;background:var(--tv-card-a);color:var(--tv-ink);cursor:pointer}.tvqs .l button b{white-space:nowrap}'
    + '.tvqs .l button span{color:var(--tv-meta);font-size:.8rem;text-align:right}.tvqs .l button.on{border-color:var(--tv-sage);box-shadow:0 0 0 3px rgba(143,188,168,.35)}'
    + '.tvqs .c{display:flex;gap:.4rem;align-items:center;margin-top:.55rem;font-size:.86rem}.tvqs .c input{width:5.5rem;margin:0}.tvqs small{display:block;margin-top:.5rem;color:var(--tv-meta);font-size:.78rem}';
  function cm(i) { return (Math.round(i * 2.54 * 10) / 10) + ' cm'; }
  function qrSize(href) {
    if (!document.getElementById('tvqs-css')) { var c = document.createElement('style'); c.id = 'tvqs-css'; c.textContent = qrCss; document.head.appendChild(c); }
    if (open) open(false);
    var last = null; try { last = localStorage.getItem('tvQrSize'); } catch (e) {}
    var box = el('div', 'tv-ask tvqs'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Print size');
    var x = el('button', 'tv-ask-x', '×'); x.type = 'button'; x.setAttribute('aria-label', 'Close'); box.appendChild(x);
    box.appendChild(el('h3', '', 'How big will it be printed?'));
    box.appendChild(el('p', '', 'The size of the code itself. The file is a vector, so it stays sharp at any size; this sets the size it opens at.'));
    var list = el('div', 'l'), lb;
    var go = function (v) {
      v = Math.max(0.5, Math.min(120, parseFloat(v) || 1));
      try { localStorage.setItem('tvQrSize', String(v)); } catch (e) {}
      close(); location.href = href + '&size=' + v;
    };
    QR_SIZES.forEach(function (z) {
      var b = el('button', z[0] === last ? 'on' : ''); b.type = 'button';
      b.appendChild(el('b', '', z[0] + ' in · ' + cm(+z[0]))); b.appendChild(el('span', '', z[1]));
      b.addEventListener('click', function () { go(z[0]); }); list.appendChild(b);
    });
    box.appendChild(list);
    var own = el('form', 'c'), lab = el('label', '', 'Another size:'), inp = el('input', 'tv-ask-in'), ok = el('button', 'tv-ask-b yes', 'Download');
    inp.type = 'number'; inp.min = '0.5'; inp.max = '120'; inp.step = '0.1'; inp.placeholder = 'inches'; inp.setAttribute('aria-label', 'Size in inches');
    if (last && !QR_SIZES.some(function (z) { return z[0] === last; })) inp.value = last;
    ok.type = 'submit'; own.appendChild(lab); own.appendChild(inp); own.appendChild(el('span', '', 'in')); own.appendChild(ok); own.noValidate = true;
    own.addEventListener('submit', function (e) { e.preventDefault(); if (!inp.value) { inp.focus(); return; } go(inp.value); });
    box.appendChild(own);
    box.appendChild(el('small', '', 'A clear margin is included around it: leave it white when you place it. Too small or seen from too far away, phones can\'t read it (about 1 in across for every 10 in of distance).'));
    var key = function (e) { if (e.key === 'Escape') close(); };
    var close = function () { if (lb && lb.isConnected) lb.remove(); document.removeEventListener('keydown', key, true); open = null; };
    x.addEventListener('click', close); document.addEventListener('keydown', key, true);
    lb = lightbox(box, close); lb.classList.add('tvqs-lb'); open = close;   // over the Share pop-up too
    setTimeout(function () { try { (list.querySelector('.on') || list.firstChild).focus({ preventScroll: true }); } catch (e) {} }, 0);
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href*="/api/qr.php"]');
    if (!a || e.defaultPrevented) return;
    var h = a.getAttribute('href') || '';
    if (!/[?&]format=svg\b/.test(h) || !/[?&]download=1\b/.test(h) || /[?&]size=/.test(h)) return;
    e.preventDefault(); qrSize(h.replace(/&amp;/g, '&'));
  });

  // TvAsk.busy(el, true): spinner on, disabled, "Working…" for screen readers; TvAsk.busy(el, false): back as it was.
  // Use on any click that waits on the server: busy on before the fetch, off when it answers (then TvAsk.say the result).
  function busy(el, on) {
    if (!el) return;
    if (on) { if (el.classList.contains('tv-busy')) return; el.classList.add('tv-busy'); el.dataset.tvWasDisabled = el.disabled ? '1' : ''; el.disabled = true; el.setAttribute('aria-busy', 'true'); }
    else { el.classList.remove('tv-busy'); el.disabled = el.dataset.tvWasDisabled === '1'; el.removeAttribute('aria-busy'); }
  }
  // ---- a field the browser won't send (required, wrong format): the site's notice beside it, not the browser's
  // bubble (Jason, 2026-11-02: "Please fill out this field" on the staff sign-in). One notice per try, on the first.
  var invalidShown = false;
  document.addEventListener('invalid', function (e) {
    var f = e.target; if (!f || !f.form) return;
    e.preventDefault();
    if (invalidShown) return;
    invalidShown = true; setTimeout(function () { invalidShown = false; }, 0);
    var lab = f.labels && f.labels[0] ? f.labels[0].textContent.trim() : (f.getAttribute('aria-label') || f.getAttribute('placeholder') || '');
    var msg = f.validity && f.validity.valueMissing ? 'Fill in ' + (lab ? '"' + lab + '"' : 'this field') + ' first.' : (f.validationMessage || 'Check this field.');
    try { f.focus(); } catch (x) {}
    say(msg, { anchor: f, bad: true });
  }, true);

  window.TvAsk = { busy: busy, confirm: confirmAsk, say: say, note: note, flashes: flashes, statuses: statuses, ask: ask, choose: choose, gate: gate, peek: peek, fitFrame: fitFrame, more: more, qrSize: qrSize };

  // ---- links past the host's firewall --------------------------------------------------------------------------
  // Bluehost's ModSecurity refuses a request whose field holds a link like ".../profile.php?id=…" (it looks like an
  // attack). Anything with a web address in it goes as "tvb64:" + base64; the server unwraps it (Response::unwrap).
  var LINK = /:\/\/|www\./i;
  var wrap = function (v) { try { return 'tvb64:' + btoa(unescape(encodeURIComponent(v))); } catch (e) { return v; } };
  window.tvSafe = function safe(v) {
    if (typeof v === 'string') return LINK.test(v) ? wrap(v) : v;
    if (Array.isArray(v)) return v.map(safe);
    if (v && typeof v === 'object') { var o = {}; Object.keys(v).forEach(function (k) { o[k] = safe(v[k]); }); return o; }
    return v;
  };
  // ordinary forms: the typed field keeps showing what was typed; a hidden copy carries the wrapped value
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f || String(f.getAttribute('method') || '').toLowerCase() !== 'post') return;
    Array.prototype.forEach.call(f.elements, function (el) {
      if (!el.name || el.disabled || !/^(TEXTAREA|INPUT)$/.test(el.tagName) || /^(hidden|file|checkbox|radio|submit|button|password)$/i.test(el.type || '')) return;
      if (!LINK.test(el.value)) return;
      var h = document.createElement('input'); h.type = 'hidden'; h.name = el.name; h.value = wrap(el.value); h.setAttribute('data-tv-wrapped', '1');
      el.setAttribute('data-tv-name', el.name); el.removeAttribute('name'); f.appendChild(h);
    });
  });
  // back to the page from the browser's cache: the fields as they were
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('input[data-tv-wrapped]').forEach(function (h) { h.remove(); });
    document.querySelectorAll('[data-tv-name]').forEach(function (el) { el.name = el.getAttribute('data-tv-name'); el.removeAttribute('data-tv-name'); });
  });
  // anything still calling alert() gets the site's toast instead of the browser's box
  window.alert = function (m) { say(m); };

  // data-confirm on a form, or on the submit button that was tapped
  var passed = new WeakSet();
  document.addEventListener('submit', function (e) {
    var f = e.target, sub = e.submitter || null;
    var src = sub && sub.hasAttribute && sub.hasAttribute('data-confirm') ? sub : (f.hasAttribute && f.hasAttribute('data-confirm') ? f : null);
    if (!src) return;
    if (passed.has(f)) { passed.delete(f); return; }
    e.preventDefault(); e.stopImmediatePropagation();
    confirmAsk(src.getAttribute('data-confirm'), { anchor: sub || f, yes: src.getAttribute('data-yes') || 'Yes', danger: src.hasAttribute('data-danger') }).then(function (ok) {
      if (!ok) return;
      passed.add(f);
      if (f.requestSubmit) f.requestSubmit(sub && sub.form === f ? sub : undefined);
      else { if (sub && sub.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = sub.name; h.value = sub.value; f.appendChild(h); } f.submit(); }
    });
  }, true);
})();

// ---- word suggestions (Jason, 2026-11-04: "word suggestions and spell check are essential features that keep our data
// pristine and aid the UX"). On a field marked data-spell, and on every box of text (a textarea not marked
// spellcheck="false"): the browser's own spell check, and once typing pauses, the site's suggestions from our own word list
// (/api/spell.php, app/lib/Spelling.php) under the field: "Holstr: Holster · Host". Tap one to put it in; "Keep it" leaves
// the word as typed and remembers it on this device (a name, a brand). Nothing is ever changed without a tap.
(function () {
  var KEEP = 'tvSpellKeep', keep = {};
  try { (JSON.parse(localStorage.getItem(KEEP) || '[]') || []).forEach(function (w) { keep[w] = 1; }); } catch (e) {}
  function save() { try { localStorage.setItem(KEEP, JSON.stringify(Object.keys(keep).slice(-300))); } catch (e) {} }
  var st = document.createElement('style');
  st.textContent = '.tv-spell{display:flex;flex-wrap:wrap;gap:.25rem .4rem;align-items:center;margin:.25rem 0 0;font:400 .82rem var(--tv-font-ui);color:var(--tv-meta)}'
    + '.tv-spell b{font-weight:700;color:var(--tv-crimson);text-decoration:underline wavy var(--tv-icon,#B45309) 1px;text-underline-offset:3px}'
    + '.tv-spell button{font:inherit;border:1px solid var(--tv-line-strong);background:var(--tv-card-a);color:var(--tv-ink);border-radius:999px;padding:.08rem .55rem;cursor:pointer}'
    + '.tv-spell button.go{background:var(--tv-umber);border-color:var(--tv-umber);color:var(--tv-on-umber)}.tv-spell button.k{border:0;background:none;text-decoration:underline;color:var(--tv-meta);padding:0 .2rem}';
  document.head.appendChild(st);
  function on(el) { return el && ((el.matches('input[data-spell]') ) || (el.tagName === 'TEXTAREA' && el.getAttribute('spellcheck') !== 'false')) && !el.readOnly && !el.disabled; }
  function box(el) { var b = el.__tvSpell; if (!b) { b = document.createElement('div'); b.className = 'tv-spell'; b.setAttribute('aria-live', 'polite'); el.insertAdjacentElement('afterend', b); el.__tvSpell = b; } return b; }
  function clear(el) { if (el.__tvSpell) { el.__tvSpell.remove(); el.__tvSpell = null; } }
  function swap(el, from, to) {
    var re = new RegExp('(^|[^\\p{L}])' + from.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![\\p{L}])', 'u');
    el.value = el.value.replace(re, function (m, a) { return a + to; });
    el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  function ask(el) {
    var t = el.value.trim();
    if (t.length < 3 || /https?:|www\.|\?/.test(t) || t === el.__tvSpellAsked) return;
    el.__tvSpellAsked = t;
    fetch('/api/spell.php?t=' + encodeURIComponent(t.slice(0, 600)), { credentials: 'include' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d || el.value.trim() !== t) return;
      var ws = (d.words || []).filter(function (w) { return !keep[w.word.toLowerCase()]; }).slice(0, 3);
      if (!ws.length) { clear(el); return; }
      var b = box(el);
      b.innerHTML = ws.map(function (w, i) {
        return '<span><b>' + w.word.replace(/[<>&"]/g, '') + '</b>: ' + w.try.map(function (c, j) { return '<button type="button" class="' + (j ? '' : 'go') + '" data-i="' + i + '" data-c="' + j + '">' + c.replace(/[<>&"]/g, '') + '</button>'; }).join(' ')
          + ' <button type="button" class="k" data-k="' + i + '">Keep it</button></span>';
      }).join('');
      b.onclick = function (e) {
        var x = e.target.closest('button'); if (!x) return;
        if (x.hasAttribute('data-k')) { keep[ws[+x.getAttribute('data-k')].word.toLowerCase()] = 1; save(); }
        else swap(el, ws[+x.getAttribute('data-i')].word, ws[+x.getAttribute('data-i')].try[+x.getAttribute('data-c')]);
        el.__tvSpellAsked = ''; ask(el); el.focus();
      };
    }).catch(function () {});
  }
  var timer;
  document.addEventListener('input', function (e) { var el = e.target; if (!on(el)) return; clearTimeout(timer); timer = setTimeout(function () { ask(el); }, 900); }, true);
  document.addEventListener('focusout', function (e) { if (on(e.target)) ask(e.target); }, true);
})();
