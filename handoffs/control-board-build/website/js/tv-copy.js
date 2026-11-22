/* js/tv-copy.js — Copy by section (the control board's build, D15; Jason, live-reads C6, 2026-11-22: "Probably need a copy
 * by section feature"): every section and card marked data-copy="<its name>" gets a Copy button that puts what it shows on
 * the clipboard as plain text: its headings and rows, as the page lays them out (a table's cells by tabs), never what's
 * hidden (a closed fold, a hidden row, a field's hidden value) and never its icons. The answer is the site's notice
 * (TvAsk.say), beside the button. A section drawn again later (the board's redraws) gets its button again.
 * In pages: <section data-copy="Alarms">…</section>; the button goes in its <summary> for a fold, else in a child marked
 * data-copy-at, else first inside it. TvCopy.scan(root) for content added by hand; TvCopy.text(el) is the text it copies. */
(function () {
  if (window.TvCopy) return;
  var st = document.createElement('style');
  st.textContent = '.tv-copy-b{font:600 11px/1.2 system-ui,sans-serif;padding:.12rem .45rem;border:1px solid var(--tv-card-edge,#E3DBC6);border-radius:.3rem;background:#FFFDF8;color:var(--tv-meta,#6b5d49);cursor:pointer;margin-left:.4rem;vertical-align:middle;float:right}'
    + '.tv-copy-b:hover{border-color:#B45309;color:#92400E}.tv-copy-b:focus-visible{outline:2px solid var(--tv-river,#3B5A6B);outline-offset:1px}'
    + 'summary>.tv-copy-b{float:none;margin-left:auto}.tv-copy-b.tv-copy-abs{position:absolute;top:.25rem;right:.25rem;float:none;margin:0;z-index:1}'
    + '[data-tv-copying] .tv-copy-b,[data-tv-copying] svg,[data-tv-copying] [data-copy-skip],[data-tv-copying] .cb-spx,[data-tv-copying] [aria-label=Close]{display:none!important}';
  (document.head || document.documentElement).appendChild(st);

  function name(el) {
    var n = el.getAttribute('data-copy');
    if (n) return n;
    var h = el.querySelector('h1,h2,h3,h4,summary');
    return h ? h.textContent.trim().slice(0, 60) : 'this section';
  }
  function place(el) {
    if (el.__tvCopy) return;
    var at = el.querySelector(':scope > [data-copy-at]') || (el.tagName === 'DETAILS' ? el.querySelector(':scope > summary') : null)
      || el.querySelector(':scope > h2, :scope > h3, :scope > h4');
    if (at && at.querySelector(':scope > .tv-copy-b')) { el.__tvCopy = 1; return; }
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'tv-copy-b'; b.textContent = 'Copy';
    b.setAttribute('aria-label', 'Copy ' + name(el)); b.title = 'Copy what this shows, as text';
    if (at) at.appendChild(b);
    else {   // straight inside a card or a grid: in its top-right corner, never taking a cell of its layout
      b.classList.add('tv-copy-abs'); if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
      el.insertBefore(b, el.firstChild);
    }
    el.__tvCopy = 1;
  }
  // what the section shows, as text: the layout's own line breaks (innerText), icons and the Copy buttons left out
  function text(el) {
    el.setAttribute('data-tv-copying', '');
    var t = '';
    try { t = el.innerText || el.textContent || ''; } finally { el.removeAttribute('data-tv-copying'); }
    return t.replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
  }
  function write(t) {
    if (navigator.clipboard && window.isSecureContext !== false) return navigator.clipboard.writeText(t);
    return new Promise(function (ok, no) {
      var a = document.createElement('textarea'); a.value = t; a.setAttribute('readonly', ''); a.style.position = 'fixed'; a.style.left = '-9999px';
      document.body.appendChild(a); a.select();
      var done = false; try { done = document.execCommand('copy'); } catch (x) {}
      a.remove(); done ? ok() : no(new Error('copy refused'));
    });
  }
  function say(msg, b, bad) { if (window.TvAsk && TvAsk.say) TvAsk.say(msg, { anchor: b, bad: !!bad }); }
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.tv-copy-b'); if (!b) return;
    e.preventDefault(); e.stopPropagation();   // inside a fold's summary: copy, never open or close it
    var el = b.closest('[data-copy]'); if (!el) return;
    var t = text(el);
    write(t).then(function () { say('Copied: ' + name(el) + ' (' + t.split('\n').filter(Boolean).length + ' lines).', b); },
      function () { say('Your browser didn\'t allow copying: select the text and copy it.', b, true); });
  }, true);
  function scan(root) { (root || document).querySelectorAll('[data-copy]').forEach(place); }
  window.TvCopy = { scan: scan, text: text };
  function go() {
    scan(document);
    if (window.MutationObserver) new MutationObserver(function (ms) {
      ms.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType !== 1) return; if (n.hasAttribute('data-copy')) place(n); if (n.querySelectorAll) scan(n); }); });
    }).observe(document.body, { childList: true, subtree: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();
