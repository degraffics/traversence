/* js/admin-staging.js — System Configuration → Staging (decisions/0145 Phase 3): the Set up staging and Start staging over
 * buttons, and Check the way in (decisions/0159 step 1); and Staging against live (decisions/0165 §5: data-stg2 buttons). Each asks first, shows its spinner while it works (TvAsk.busy), then the site's notice beside it (TvAsk.say).
 * A refresh goes a step at a time: the page asks again until it's done, saying what it's copying. Set up's sign-ins are
 * shown once, here, to copy into the GitHub secret CHECK_ACCOUNTS; leave the page and they're gone. */
(function () {
  var box = document.getElementById('stg');
  if (!box) return;
  var csrf = box.getAttribute('data-csrf') || '', now = box.querySelector('[data-stg-now]'), out = box.querySelector('[data-stg-out]');
  var tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

  function post(action) {
    var body = { action: action, tz: tz };
    return fetch('/admin/api/config/staging', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: { message: 'Something went wrong (the server didn\'t answer clearly). Try again.' } }; }); })
      .then(function (j) { if (!j.ok) throw new Error((j.error && j.error.message) || 'Something went wrong. Try again.'); return j; });
  }
  function say(msg, btn, bad) { if (window.TvAsk) TvAsk.say(msg, { anchor: btn, bad: !!bad }); }
  var bar = box.querySelector('[data-stg-bar]');
  function line(p) {
    if (!now || !p) return;
    if (bar && p.of) {
      var pct = p.done ? 100 : Math.max(1, Math.min(99, Math.round(p.pos / p.of * 100)));
      bar.hidden = false; bar.setAttribute('aria-valuenow', pct); bar.firstChild.style.width = pct + '%';
      if (p.done && !p.ok) bar.firstChild.style.background = '#B91C1C';
    }
    now.hidden = false;
    now.textContent = p.done ? (p.message || 'Finished.') : Math.round(p.pos / p.of * 100) + '% · step ' + (p.pos + 1) + ' of ' + p.of + ': ' + p.now + '…';
  }
  function el(tag, cls, text) { var x = document.createElement(tag); if (cls) x.className = cls; if (text != null) x.textContent = text; return x; }

  // the sign-ins, shown once (as Test accounts shows them)
  function showBlock(block, log) {
    out.innerHTML = '';
    if (log && log.length) { var ul = el('ul', 'ak-note'); log.forEach(function (l) { ul.appendChild(el('li', '', l)); }); out.appendChild(ul); }
    if (!block) return;
    var card = el('div', 'ak-card tv-bleed'); card.style.borderColor = '#8FBCA8';
    card.appendChild(el('h3', 'ak-h', 'The sign-ins, shown once')).style.marginTop = '0';
    card.appendChild(el('p', 'ak-note', 'Copy this into GitHub: degraffics/traversence → Settings → Secrets and variables → Actions → CHECK_ACCOUNTS (Update). It holds the test accounts\' new sign-ins and staging\'s address and viewing password. It isn\'t kept here: leave this page and it\'s gone. Lost or exposed? Press Replace sign-ins on Test accounts for new ones.'));
    var ta = el('textarea'); ta.readOnly = true; ta.rows = 14; ta.spellcheck = false; ta.value = block; ta.style.cssText = 'width:100%;font:12px ui-monospace,monospace;margin-top:.5rem';
    var copy = el('button', 'ak-b go', 'Copy'); copy.type = 'button';
    copy.addEventListener('click', function () { navigator.clipboard.writeText(ta.value).then(function () { say('Copied: now paste it into the GitHub secret.', copy); }, function () { ta.select(); say('Couldn\'t copy by itself: the text is selected, so copy it with your keyboard.', copy, true); }); });
    card.appendChild(ta); card.appendChild(copy); out.appendChild(card);
  }

  // a refresh, step after step, until it's done. A lost answer (the connection dropped, "Failed to fetch") isn't the end:
  // the server carries on with that step, so the page waits and asks again, up to 8 times in a row, before saying so.
  function wait(ms) { return new Promise(function (ok) { setTimeout(ok, ms); }); }
  function carryOn(btn, first, lost) {
    lost = lost || 0;
    var go = first ? Promise.resolve(first) : post('refresh').then(function (j) { return j.progress; });
    return go.then(function (p) {
      line(p);
      if (!p || p.done) return p;
      return wait(p.busy ? 3000 : 300).then(function () { return carryOn(btn); });
    }, function (err) {
      if (!(err instanceof TypeError) || lost >= 8) throw err;   // a real answer saying no is shown at once
      if (now) { now.hidden = false; now.textContent = 'The server is still working on this step (the page lost its answer); asking again in 15 seconds…'; }
      return wait(15000).then(function () { return carryOn(btn, null, lost + 1); });
    });
  }
  function finished(btn, p) {
    if (!p) return;
    say(p.ok ? 'Staging is refreshed: ' + (p.message || 'done.') : (p.message || 'The refresh stopped. Try again.'), btn, !p.ok);
    if (p.ok) setTimeout(function () { location.reload(); }, 4000);
  }

  box.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-stg]');
    if (!btn || btn.disabled) return;
    var what = btn.getAttribute('data-stg');
    if (what === 'setup' && !TvAsk.gate(btn, 'Set up staging.trvgo.us now? It also gives every test account a new sign-in: the old ones stop working until you paste the new block into CHECK_ACCOUNTS.', { yes: 'Set up staging' })) return;
    if (what === 'refresh' && !box.hasAttribute('data-stg-running') && !TvAsk.gate(btn, btn.getAttribute('data-confirm') || 'Start staging over? It copies live over staging; what was done there is lost. Publish first to keep it.', { yes: btn.getAttribute('data-yes') || 'Start over', danger: true })) return;
    if (what === 'wayin') { wayIn(btn); return; }
    TvAsk.busy(btn, true); driving = true;
    var job = what === 'setup'
      ? post('setup').then(function (j) { showBlock(j.block, j.log); say(j.message, btn); return carryOn(btn, j.progress); })
      : carryOn(btn);
    job.then(function (p) { TvAsk.busy(btn, false); driving = false; finished(btn, p); })
       .catch(function (err) { TvAsk.busy(btn, false); driving = false; if (err instanceof TypeError) watch(); say(err.message + ' Still stuck? Write to support@traversence.com.', btn, true); });
  });

  // Check the way in (decisions/0159 step 1): staging asked several ways, which layer answered each, and what's on disk
  // there; shown as rows, with the same as text to copy and send (no secret is in it: the pass and keys show as •••)
  function wayIn(btn) {
    TvAsk.busy(btn, true);
    post('wayin').then(function (j) {
      TvAsk.busy(btn, false);
      out.innerHTML = '';
      var card = el('div', 'ak-card tv-bleed');
      card.appendChild(el('h3', 'ak-h', 'The way in, checked')).style.marginTop = '0';
      (j.rows || []).forEach(function (r) {
        var row = el('div', 'ak-res'), b = el('b', r[1], r[0]), sp = el('span', '', r[2]);
        sp.appendChild(el('small', '', r[3])); row.appendChild(b); row.appendChild(sp); card.appendChild(row);
      });
      var ta = el('textarea'); ta.readOnly = true; ta.rows = 8; ta.spellcheck = false; ta.value = j.text || ''; ta.style.cssText = 'width:100%;font:12px ui-monospace,monospace;margin-top:.5rem';
      var copy = el('button', 'ak-b go', 'Copy the answers'); copy.type = 'button';
      copy.addEventListener('click', function () { navigator.clipboard.writeText(ta.value).then(function () { say('Copied: paste it to Claude.', copy); }, function () { ta.select(); say('Couldn\'t copy by itself: the text is selected, so copy it from there.', copy, true); }); });
      card.appendChild(ta); card.appendChild(copy); out.appendChild(card);
      say(j.message || 'Checked.', btn);
    }).catch(function (err) { TvAsk.busy(btn, false); say(err.message + ' Still stuck? Write to support@traversence.com.', btn, true); });
  }

  // a refresh already going (the server carries it on by itself): show where it is, every few seconds, until it's done
  var driving = false, watching = false;
  function watch() {
    if (watching) return; watching = true;
    (function poll() {
    if (driving) { watching = false; return; }   // the button's own steps are showing it
    fetch('/admin/api/config/staging?action=status', { credentials: 'include' }).then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok || !j.progress) return;
        line(j.progress);
        if (j.progress.done) { watching = false; finished(box.querySelector('[data-stg="refresh"]'), j.progress); return; }
        setTimeout(poll, 5000);
      }).catch(function () { setTimeout(poll, 15000); });
    })();
  }
  if (box.hasAttribute('data-stg-running')) watch();
})();

// Staging against live (decisions/0165 §5): keep caught up, its jobs, conflicts, preview, publish, schedule, roll back,
// held and To send now. Each button asks first where it says so (data-confirm), spins while it works, says what happened
// beside it, then the section is drawn again with what's now true. A publish carries on on the server; while one runs the
// page asks every 5 seconds and shows its step.
(function () {
  var box = document.getElementById('stg2');
  if (!box) return;
  var csrf = box.getAttribute('data-csrf') || '';
  var tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
  function post(body) {
    body = Object.assign({ tz: tz }, body);
    return fetch('/admin/api/config/staging', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(window.tvSafe ? tvSafe(body) : body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: { message: 'The server didn\'t answer clearly. Try again.' } }; }); })
      .then(function (j) { if (!j.ok) throw new Error((j.error && j.error.message) || 'Something went wrong. Try again.'); return j.data || j; });
  }
  function redraw() { fetch(location.href, { credentials: 'include' }).then(function (r) { return r.text(); }).then(function (h) {
    var d = new DOMParser().parseFromString(h, 'text/html'), n = d.getElementById('stg2');
    // its status lines drawn again as the site draws them (TvAsk.statuses): without it the redraw after a press left the Now
    // line and the others as bare text, no box and no label (Jason's screen, 2026-11-26)
    if (n) { box.innerHTML = n.innerHTML; if (window.TvAsk && TvAsk.statuses) TvAsk.statuses(box); if (window.TvTime && TvTime.paint) TvTime.paint(box); watch(); }
  }); }
  box.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-stg2]');
    if (!btn || btn.disabled) return;
    if (btn.hasAttribute('data-confirm') && !TvAsk.gate(btn, btn.getAttribute('data-confirm'), { yes: btn.getAttribute('data-yes') || 'Yes', danger: btn.hasAttribute('data-danger') })) return;
    var args = {}; try { args = JSON.parse(btn.getAttribute('data-args') || '{}'); } catch (x) {}
    var what = btn.getAttribute('data-stg2');
    if (what === 'schedule') { var w = box.querySelector('[data-stg-when]'); if (!w || !w.value) { TvAsk.say('Choose the date and time first.', { anchor: btn, bad: true }); return; } args.at = w.value; }
    TvAsk.busy(btn, true);
    post(Object.assign({ action: what }, args)).then(function (j) {
      TvAsk.busy(btn, false);
      TvAsk.say(j.message || 'Done.', { anchor: btn });
      setTimeout(redraw, 900);
    }, function (err) { TvAsk.busy(btn, false); TvAsk.say(err.message + ' Still stuck? Write to support@traversence.com.', { anchor: btn, bad: true }); });
  });
  var timer = 0;
  function watch() {
    clearTimeout(timer);
    if (!box.querySelector('[data-stg2="carry"]')) return;   // a publish is running: its Carry on now button shows
    timer = setTimeout(function () { post({ action: 'carry' }).then(redraw, function () { timer = setTimeout(watch, 15000); }); }, 5000);
  }
  watch();
})();
