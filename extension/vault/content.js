// Traversence Vault extension: in each page. Under a sign-in field it lists the logins saved for exactly this site (the
// background decides that from the address the browser reports, not from anything the page says), fills the one picked,
// and after a sign-in offers to save it. Its boxes live in a closed shadow root, out of the page's reach.
(function () {
  'use strict';
  if (window.__tvVault) return; window.__tvVault = true;
  var BROWN = '#92400E', CREAM = '#FFFBF2', SAGE = '#8FBCA8';
  var ask = function (m) { return new Promise(function (res) { try { chrome.runtime.sendMessage(m, function (r) { void chrome.runtime.lastError; res(r || null); }); } catch (e) { res(null); } }); };

  function visible(el) { var r = el.getBoundingClientRect(); return r.width > 30 && r.height > 10 && getComputedStyle(el).visibility !== 'hidden'; }
  // password boxes, including ones a site's eye icon has turned into text boxes (remembered, never marked in the page)
  var wasPw = new WeakSet();
  function markPw() { Array.prototype.forEach.call(document.querySelectorAll('input[type=password]'), function (i) { wasPw.add(i); }); }
  markPw();
  try {
    new MutationObserver(function (ms) {
      ms.forEach(function (m) { if (m.type === 'attributes' && m.oldValue === 'password' && m.target instanceof HTMLInputElement) wasPw.add(m.target); });
      markPw();
    }).observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['type'], attributeOldValue: true });
  } catch (e) {}
  function passFields() {
    markPw();
    return Array.prototype.filter.call(document.querySelectorAll('input'), function (i) { return wasPw.has(i) && !i.disabled && !i.readOnly && visible(i); });
  }
  /** The user-name field for a password field: the closest text or email box before it in the same form (or page). */
  function userField(pw) {
    var scope = pw.form || document, all = Array.prototype.slice.call(scope.querySelectorAll('input')), i = all.indexOf(pw), best = null;
    for (var k = i - 1; k >= 0; k--) {
      var x = all[k], t = (x.type || 'text').toLowerCase();
      if ((t === 'text' || t === 'email' || t === 'tel') && !x.disabled && visible(x)) { best = x; break; }
    }
    return best || scope.querySelector('input[autocomplete=username],input[type=email]');
  }
  /** The words around a box: its name, id, placeholder, label and a short row of text beside it. */
  function words(i) {
    var t = [i.name, i.id, i.placeholder, i.getAttribute('aria-label'), i.getAttribute('autocomplete')].join(' ');
    try { if (i.id) { var l = document.querySelector('label[for="' + CSS.escape(i.id) + '"]'); if (l) t += ' ' + l.textContent; } } catch (e) {}
    var p = i.closest('label'); if (p) t += ' ' + p.textContent;
    var r = i.closest('tr'); if (r && r.textContent.length < 80) t += ' ' + r.textContent;
    var e = i.parentElement; for (var n = 0; e && n < 2; n++, e = e.parentElement) if (e.textContent.length < 60) { t += ' ' + e.textContent; break; }
    return t.toLowerCase();
  }
  /** A box for a new password (sign-up, or a change form's New and Confirm), not the one you sign in with. */
  function isNew(f) {
    if ((f.getAttribute('autocomplete') || '') === 'new-password') return true;
    var t = words(f); if (/current|old|existing/.test(t)) return false;
    if (/\bnew\b|confirm|again|repeat|retype|re-enter|reenter|verify/.test(t)) return true;
    var all = passFields(); if (all.length === 3) return all.indexOf(f) > 0;
    return all.length === 2 && !all.some(function (x) { return /current|old|existing/.test(words(x)); });
  }
  /** On a sign-in that asks for the email first (and the password on the next screen): the email or user-name box. */
  function idField() {
    var c = Array.prototype.filter.call(document.querySelectorAll('input'), function (x) { var t = (x.type || 'text').toLowerCase(); return (t === 'email' || t === 'text' || t === 'tel') && !x.disabled && !x.readOnly && visible(x); });
    var best = c.filter(function (x) { return x.type === 'email' || /email|user|login|account|sign.?in/.test(words(x)); });
    return best[0] || (c.length === 1 ? c[0] : null);
  }
  function setVal(el, v) {
    if (!el) return;
    var proto = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
    el.focus(); proto.set.call(el, v);
    el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  /** Fills a login. A sign-in in two steps (the email, then Next, then the password): the email now, and the password
   *  when the next screen asks for it (kept in the extension for 3 minutes, for this tab and this site only). */
  function fill(user, pass) {
    var pws = passFields(), pw = pws.filter(function (p) { return pws.length === 1 || !isNew(p); })[0];
    if (!pw) { var f = idField(); if (!f) return false; if (user) setVal(f, user); ask({ t: 'nextpass', set: pass }); return 'step'; }
    var u = userField(pw); if (u && user) setVal(u, user);
    setVal(pw, pass); ask({ t: 'nextpass', clear: true }); return true;
  }
  var nextAsked = false;
  function maybeNext() {
    if (nextAsked || window.top !== window) return;
    var pw = passFields().filter(function (p) { return !p.value && !isNew(p); })[0]; if (!pw) return;
    nextAsked = true;
    ask({ t: 'nextpass' }).then(function (r) { if (r && r.pass && !pw.value) setVal(pw, r.pass); });
  }

  // ---- a new password: suggest a strong one, within the length the page states -------------------------------------------
  var genPass = '';
  function strong() {
    var tx = ((document.body && document.body.innerText) || '').slice(0, 20000), min = 12, max = 64, m;
    if ((m = /(\d{1,2})\s*(?:to|-|–|and)\s*(\d{1,2})\s*characters/i.exec(tx))) { min = +m[1]; max = +m[2]; }
    if ((m = /(?:minimum|at least)(?:\s+of)?\s+(\d{1,2})/i.exec(tx))) min = +m[1];
    if ((m = /(?:maximum|up to|no more than|at most)(?:\s+of)?\s+(\d{1,2})/i.exec(tx))) max = +m[1];
    var n = Math.max(min, Math.min(max, 16)), sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!#$%*+-=?@_'], all = sets.join(''), out = [];
    var r = function (k) { var b = new Uint32Array(1); crypto.getRandomValues(b); return b[0] % k; };
    sets.forEach(function (s) { out.push(s[r(s.length)]); });
    while (out.length < n) out.push(all[r(all.length)]);
    for (var i = out.length - 1; i > 0; i--) { var j = r(i + 1), x = out[i]; out[i] = out[j]; out[j] = x; }
    return out.join('');
  }
  function showNew(f) {
    var s = box(), l = s.querySelector('.l');
    l.innerHTML = '<div class="h"><b>Traversence Vault</b><button class="x" data-x aria-label="Close" title="Close">×</button></div><button class="i" data-gen role="option"><div class="n">Suggest a strong password</div><div class="u">Fills the new password and its confirm box; your vault offers to save it after you change it.</div></button>';
    forField = f; place(f);
  }

  // ---- the list under a field -------------------------------------------------------------------------------------------
  var host = null, shadow = null, forField = null, cache = null;
  function box() {
    if (host) return shadow;
    host = document.createElement('div'); host.style.cssText = 'position:absolute;z-index:2147483647;top:0;left:0';
    shadow = host.attachShadow({ mode: 'closed' });
    shadow.innerHTML = '<style>.l{font:14px/1.35 system-ui,sans-serif;background:#fff;border:1px solid #E7D9C4;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.15);min-width:240px;max-width:340px;overflow:hidden;color:#1F2937}'
      + '.h{display:flex;align-items:center;gap:6px;padding:6px 10px;background:' + CREAM + ';color:' + BROWN + ';font-weight:700;font-size:12px}.h b{flex:1}.x{border:0;background:none;color:' + BROWN + ';cursor:pointer;font-size:16px;line-height:1}'
      + 'button.i{display:block;width:100%;text-align:left;border:0;border-top:1px solid #F0E6D6;background:#fff;padding:8px 10px;cursor:pointer;font:inherit;color:inherit}button.i:hover,button.i:focus{background:#EEF6F2;outline:none}'
      + '.n{font-weight:600;color:' + BROWN + '}.u{font-size:12px;color:#78716C}.e{padding:8px 10px;font-size:12px;color:#991B1B}</style><div class="l" role="listbox" aria-label="Traversence Vault logins"></div>';
    document.documentElement.appendChild(host);
    shadow.addEventListener('click', onPick);
    return shadow;
  }
  function hide() { if (host) host.style.display = 'none'; forField = null; }
  function place(f) { var r = f.getBoundingClientRect(); host.style.display = 'block'; host.style.top = (r.bottom + scrollY + 4) + 'px'; host.style.left = (r.left + scrollX) + 'px'; }
  async function showFor(f) {
    var r = cache || await ask({ t: 'list' }); cache = r;
    if (!r || !r.list || !r.list.length) return;
    var s = box(), l = s.querySelector('.l');
    l.innerHTML = '<div class="h"><b>Traversence Vault</b><button class="x" data-x aria-label="Close" title="Close">×</button></div>';
    r.list.forEach(function (it) {
      var b = document.createElement('button'); b.className = 'i'; b.setAttribute('role', 'option'); b.setAttribute('data-id', it.id);
      var n = document.createElement('div'); n.className = 'n'; n.textContent = it.name;
      var u = document.createElement('div'); u.className = 'u'; u.textContent = it.user || '(no user name)';
      b.appendChild(n); b.appendChild(u); l.appendChild(b);
    });
    forField = f; place(f);
  }
  async function onPick(e) {
    if (e.target.closest('[data-x]')) return hide();
    if (e.target.closest('[data-gen]')) {
      genPass = strong();
      passFields().filter(isNew).forEach(function (p) { setVal(p, genPass); });
      hide(); return;
    }
    var b = e.target.closest('button.i'); if (!b) return;
    b.disabled = true; b.querySelector('.u').textContent = 'Filling…';
    var r = await ask({ t: 'fill', id: +b.getAttribute('data-id') });
    if (r && r.ok) { fill(r.user, r.pass); r.pass = ''; hide(); }
    else { var l = shadow.querySelector('.l'), er = document.createElement('div'); er.className = 'e'; er.textContent = (r && r.error) || 'That didn\'t work.'; l.appendChild(er); b.disabled = false; }
  }
  document.addEventListener('focusin', function (e) {
    var t = e.target; if (!(t instanceof HTMLInputElement)) return;
    var pws = passFields();
    if (pws.indexOf(t) >= 0 && isNew(t)) return showNew(t);
    if (!pws.length) { if (t === idField()) showFor(t); return; }   // the email step of a two-step sign-in
    if (pws.indexOf(t) >= 0 || pws.some(function (p) { return userField(p) === t; })) showFor(t);
  }, true);
  document.addEventListener('mousedown', function (e) { if (host && !host.contains(e.target) && e.target !== forField) hide(); }, true);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') hide(); }, true);

  // ---- after signing in: offer to save ---------------------------------------------------------------------------------
  function capture() {
    var filled = passFields().filter(function (p) { return p.value; });
    if (!filled.length) { var f = idField(); if (f && f.value) ask({ t: 'stepuser', user: f.value }); return; }   // the email step: remembered for the password step
    var nw = filled.filter(isNew)[0], pw = nw || filled[0];   // a change form: the new password, not the current one
    var u = userField(filled[0]);
    ask({ t: 'offer', user: u && u.type !== 'password' ? u.value : '', pass: pw.value, url: location.href, change: !!nw });
  }
  document.addEventListener('submit', capture, true);
  document.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('button,input[type=submit],[role=button]'); if (b && passFields().some(function (p) { return p.value; })) capture(); }, true);
  document.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target && e.target.type === 'password') capture(); }, true);

  var bar = null;
  async function offerBar() {
    if (window.top !== window) return;
    var o = await ask({ t: 'pending' }); if (!o || bar) return;
    bar = document.createElement('div'); bar.style.cssText = 'position:fixed;top:12px;right:12px;z-index:2147483647';
    var sh = bar.attachShadow({ mode: 'closed' });
    sh.innerHTML = '<style>.b{font:14px/1.4 system-ui,sans-serif;background:#fff;border:1px solid #E7D9C4;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.18);padding:12px 14px;max-width:320px;color:#1F2937}'
      + '.t{color:' + BROWN + ';font-weight:700;margin-bottom:4px}.r{display:flex;gap:8px;margin-top:10px}button{font:inherit;border-radius:6px;padding:6px 12px;cursor:pointer;border:1.5px solid ' + BROWN + ';background:' + CREAM + ';color:' + BROWN + '}'
      + 'button.go{background:' + BROWN + ';color:#fff}.m{margin-top:8px;font-size:13px}.m.ok{color:#1F5C43}.m.bad{color:#991B1B}[hidden]{display:none!important}select{font:inherit;width:100%;margin-top:8px;padding:5px;border:1px solid #D6C7B0;border-radius:6px}</style>'
      + '<div class="b" role="dialog" aria-label="Save to Traversence Vault"><div class="t">Traversence Vault</div><div class="q"></div><div class="r"><button class="go" data-a="save"></button><button data-a="no">Not now</button></div><div class="m" hidden></div></div>';
    var pick = null;
    if (o.choose && o.choose.length) {   // a new password with no user name on the page: which login is it for?
      sh.querySelector('.q').textContent = 'Save the new password for which ' + location.hostname + ' login?';
      pick = document.createElement('select'); pick.setAttribute('aria-label', 'Which login');
      o.choose.forEach(function (c) { var op = document.createElement('option'); op.value = c.id; op.textContent = c.user || '(no user name)'; pick.appendChild(op); });
      var nn = document.createElement('option'); nn.value = '0'; nn.textContent = 'A new login'; pick.appendChild(nn);
      sh.querySelector('.q').appendChild(pick);
      sh.querySelector('[data-a=save]').textContent = 'Save';
    } else {
      sh.querySelector('.q').textContent = o.update ? 'Update the saved password for ' + (o.user || o.name) + ' on ' + location.hostname + '?' : 'Save this login for ' + location.hostname + (o.user ? ' (' + o.user + ')' : '') + ' to your vault?';
      sh.querySelector('[data-a=save]').textContent = o.update ? 'Update' : 'Save';
    }
    document.documentElement.appendChild(bar);
    sh.addEventListener('click', async function (e) {
      var b = e.target.closest('button'); if (!b) return;
      if (b.getAttribute('data-a') === 'no') { ask({ t: 'dismiss' }); bar.remove(); bar = null; return; }
      b.disabled = true; b.textContent = 'Saving…';
      var r = await ask({ t: 'save', id: pick ? +pick.value : undefined }), m = sh.querySelector('.m'); m.hidden = false;
      m.className = 'm ' + (r && r.ok ? 'ok' : 'bad'); m.textContent = (r && (r.message || r.error)) || 'That didn\'t work.';
      sh.querySelector('.r').hidden = true; cache = null;
      setTimeout(function () { if (bar) { bar.remove(); bar = null; } }, 4000);
    });
  }
  offerBar(); setTimeout(offerBar, 1500);   // after the page loads, and after a sign-in that doesn't load a new page
  setTimeout(maybeNext, 400);
  try { var mt = 0; new MutationObserver(function () { clearTimeout(mt); mt = setTimeout(maybeNext, 300); }).observe(document.documentElement, { subtree: true, childList: true }); } catch (e) {}

  /** What's filled in now, for "Save this site's login": any frame of the page may answer; only one with a password does. */
  function grab(m, reply) {
    var pw = passFields().filter(function (p) { return p.value; })[0], u = pw ? userField(pw) : null;
    if (pw) reply({ user: u ? u.value : '', pass: pw.value, url: location.href });
  }
  chrome.runtime.onMessage.addListener(function (m, sender, reply) {   // from the pop-up, for this exact site only
    if (!m) return;
    if (m.t === 'grab') return grab(m, reply);
    if (window.top !== window) return;
    if (m.t === 'doFill' && m.origin === location.origin) reply({ ok: fill(m.user, m.pass) });
    if (m.t === 'where') reply({ href: location.href });
  });
})();
