// js/engage.js — the Engage bar and Comments on content pages (decisions/0058 §20; includes/engage-ui.php).
// Like, Share (the phone's share sheet, else copy the link), Report (a page's own item or a comment), and comments:
// post, remove, report, and reply (one level, decisions/0172 §2.3). A guest's like counts (decisions/0009); comments and
// reports open the account panel.
(function () {
  if (window.tvEngage) return; window.tvEngage = true;   // once, also when the photo viewer loads it (2026-11-19)
  var csrf = null, REASONS = { spam: 'Spam or scam', harassment: 'Harassment or hate', sacred_site: 'Shows a sacred or restricted site', private_info: 'Someone’s private information',
    people: 'Shows people without their consent', private_property: 'Private property without permission', unsafe: 'Unsafe or illegal', other: 'Something else' };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  (function () { var st = document.createElement('style'); st.textContent = '.eg-hc{margin:.5rem 0;padding:.7rem .8rem;background:#FFFBF2;border:1px solid #CBB98D;border-radius:.7rem;max-width:22rem;font-size:.88rem;color:#3E2B1F}.eg-hc p{margin:0 0 .4rem}.eg-hc b{display:block}.eg-hcw{min-height:65px}'; document.head.appendChild(st); })();
  function token() {
    if (csrf) return Promise.resolve(csrf);
    return fetch('/api/engage.php', { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) { csrf = d.csrf; return csrf; });
  }
  function post(body, opt) {
    return token().then(function (t) {
      return fetch('/api/engage.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': t || '' }, body: JSON.stringify(body) });
    }).then(function (r) { return r.json().then(function (d) {
      if (r.status === 401) {   // a guest, for something that needs an account: the account panel (js/tv-join.js), not a detour
        if (window.TvJoin) TvJoin.open({ why: d.error && d.error.message, next: location.pathname + location.search, more: d.error && d.error.more && opt && opt.more });
        else location.href = '/login.php?tab=create&next=' + encodeURIComponent(location.pathname + location.search);
        throw new Error('Sign in first.'); }
      if (!d.ok) { var er = new Error(d.error && d.error.message ? d.error.message : 'That didn’t work.'); er.code = d.error && d.error.code; er.sitekey = d.error && d.error.sitekey; throw er; }
      return d; }); });
  }
  // The "verify you're human" check, for likes (app/lib/LikeGuard.php): Cloudflare Turnstile beside the button. Usually it
  // passes by itself in a second. Resolves with its token; with no site key set up, at once with ''.
  var tsLoad = null;
  function humanCheck(sitekey, anchor) {
    if (!sitekey) return Promise.resolve('');
    if (!tsLoad) tsLoad = new Promise(function (res, rej) {
      if (window.turnstile) return res();
      var sc = document.createElement('script'); sc.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'; sc.async = true;
      sc.onload = function () { res(); }; sc.onerror = function () { tsLoad = null; rej(new Error('The check couldn’t load. Turn off any blocker for this site, or sign in to keep liking.')); };
      document.head.appendChild(sc);
    });
    return tsLoad.then(function () { return new Promise(function (res, rej) {
      var old = document.querySelector('.eg-hc'); if (old) old.remove();
      var box = document.createElement('div'); box.className = 'eg-hc'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Confirm you’re a person');
      box.innerHTML = '<p><b>One moment</b> Confirming you’re a person…</p><div class="eg-hcw"></div><button type="button" class="eg-b" data-x>Cancel</button>';
      anchor.insertAdjacentElement('afterend', box);
      box.querySelector('[data-x]').addEventListener('click', function () { box.remove(); rej(new Error('cancelled')); });
      try {
        turnstile.render(box.querySelector('.eg-hcw'), { sitekey: sitekey, size: 'flexible',
          callback: function (t) { box.remove(); res(t); },
          'error-callback': function () { box.remove(); rej(new Error('The check didn’t pass. Please try again.')); } });
      } catch (x) { box.remove(); rej(new Error('The check couldn’t start. Please try again.')); }
    }); });
  }
  // A like, and what the checks may ask first: confirm you're a person (428), Keep liking past today's 100 (429), or, for
  // a guest a few likes in, the account panel with Keep going as a guest (401, more). Each then tries the like again.
  function likeIt(like, bar, tries) {
    tries = tries || 0;
    if (window.TvAsk) TvAsk.busy(like, true);
    var again = function () { if (tries < 3) likeIt(like, bar, tries + 1); };
    post({ action: 'like', kind: bar.getAttribute('data-eg-kind'), ref: bar.getAttribute('data-eg-ref') },
         { more: function () { post({ action: 'guest_more' }).then(again).catch(function (x) { TvAsk.say(x.message, { anchor: like, bad: true }); }); } })
      .then(function (d) {
        if (window.TvAsk) TvAsk.busy(like, false);
        like.setAttribute('aria-pressed', d.liked ? 'true' : 'false'); like.querySelector('span').textContent = d.likes;
        if (d.guest && d.ask_learning && window.TvJoin) TvJoin.askLearning(like, function (on) { post({ action: 'learning', on: on }).catch(function () {}); });   // decisions/0051 §1: once a visit
      }).catch(function (x) {
        if (window.TvAsk) TvAsk.busy(like, false);
        if (x.message === 'Sign in first.' || x.message === 'cancelled') return;
        if (x.code === 'check') {
          humanCheck(x.sitekey, like).then(function (t) { return post({ action: 'like_pass', token: t }); }).then(again)
            .catch(function (y) { if (y.message !== 'cancelled') TvAsk.say(y.message, { anchor: like, bad: true }); });
          return;
        }
        if (x.code === 'daily') {
          TvAsk.confirm(x.message + ' Keep liking? You\u2019ll get another 100 for today.', { anchor: like, yes: 'Keep liking', no: 'Not now' }).then(function (yes) {
            if (!yes) return;
            humanCheck(x.sitekey, like).then(function (t) { return post({ action: 'like_more', token: t }); }).then(again)
              .catch(function (y) { if (y.message !== 'cancelled') TvAsk.say(y.message, { anchor: like, bad: true }); });
          });
          return;
        }
        TvAsk.say(x.message, { anchor: like, bad: true });
      });
  }
  function reportBox(near, what, id) {
    var old = document.querySelector('.eg-rep'); if (old) old.remove();
    var box = document.createElement('div'); box.className = 'eg-rep';
    box.innerHTML = '<b>Report</b><br><select>' + Object.keys(REASONS).map(function (k) { return '<option value="' + k + '">' + REASONS[k] + '</option>'; }).join('') + '</select><br>'
      + '<input type="text" maxlength="500" placeholder="Anything we should know (optional)" style="width:100%"><br><button type="button" class="eg-b">Send report</button> <button type="button" class="eg-b" data-x>Cancel</button> <span class="eg-note"></span>';
    near.insertAdjacentElement('afterend', box);
    box.addEventListener('click', function (e) {
      if (e.target.closest('[data-x]')) { box.remove(); return; }
      if (!e.target.closest('button.eg-b')) return;
      var n = box.querySelector('.eg-note');
      post({ action: 'report', what: what, id: id, reason: box.querySelector('select').value, note: box.querySelector('input').value })
        .then(function () { box.innerHTML = '<span class="eg-note">Thank you. Our team will look at it.</span>'; }).catch(function (x) { n.textContent = x.message; });
    });
  }
  // ⋯ on the bar (2026-11-19): one open at a time; ×, Esc or a click outside close it; it opens upward near the foot of the
  // screen. A tool the page has runs the page's own button (data-eg-click); Plan a trip here opens the place's journeys.
  // an open menu sits on the page itself (a hero's layer or a card can't cover or cut it), and goes home when it closes
  function closeMenus(but) { document.querySelectorAll('.eg-ml:not([hidden])').forEach(function (m) { if (m === but) return; m.hidden = true; if (m._link) { m._link.classList.remove('eg-hid'); m._link = null; } var h = m._home; if (h) { h.appendChild(m); var b = h.querySelector('.eg-more'); if (b) b.setAttribute('aria-expanded', 'false'); } }); }
  // the page's own Link button for this bar (link-button.js draws it from data-link): Link to runs it, and it hides while
  // ⋯ is open so the two never sit on each other (Jason, 2026-11-20)
  function pageLink(bar) {
    if (!bar) return null;
    var k = bar.getAttribute('data-eg-kind'), r = bar.getAttribute('data-eg-ref'),
        key = { listing: 'business:' + r, lm: 'place:lm:' + r, rec: 'place:rec:' + r, place: 'place:' + r }[k];
    if (!key) return null;
    var slot = document.querySelector('[data-link="' + key.replace(/"/g, '') + '"]');
    return slot ? (slot.querySelector('.tv-link') || (slot.classList.contains('tv-link') ? slot : null) || slot) : null;
  }
  function barOf(m) { var h = m && m._home; return h ? h.closest('.eg-bar') : null; }
  document.addEventListener('click', function (e) {
    var t = e.target; if (!t.closest || !t.isConnected) return;
    var lt = t.closest('[data-eg-linkto]');
    if (lt) { var lb = pageLink(barOf(lt.closest('.eg-ml'))); closeMenus(); var btn = lb && (lb.matches('button') ? lb : lb.querySelector('button'));
      if (btn) btn.click(); else if (window.TvAsk) TvAsk.say('Linking isn\'t on this page yet.', { anchor: lt }); return; }
    var more = t.closest('.eg-more');
    if (more) { var m = more._menu || (more._menu = more.nextElementSibling); m._home = more.parentNode; closeMenus(m);
      if (!m.hidden) { closeMenus(); return; }
      document.body.appendChild(m); m.hidden = false; more.setAttribute('aria-expanded', 'true');
      var pl = pageLink(more.closest('.eg-bar')); if (pl) { pl.classList.add('eg-hid'); m._link = pl; }
      if (!m.hidden) {   // placed on the screen, so a hero or a card never cuts it off; upward near the foot
        var br = more.getBoundingClientRect(), w = m.offsetWidth, h = m.offsetHeight, top = br.bottom + 6;
        if (top + h > innerHeight - 8 && br.top - h - 6 > 8) top = br.top - h - 6;
        m.style.position = 'fixed'; m.style.zIndex = 3200; m.style.right = 'auto'; m.style.bottom = 'auto'; m.style.top = Math.max(3, top) + 'px'; m.style.left = Math.max(3, Math.min(br.right - w, document.documentElement.clientWidth - w - 3)) + 'px'; }
      return; }
    if (t.closest('.eg-mx')) { closeMenus(); return; }
    var mp = t.closest('[data-eg-map]');
    if (mp) { closeMenus(); if (window.tvMapAround) tvMapAround(JSON.parse(mp.getAttribute('data-eg-map') || '{}')); return; }
    var go = t.closest('[data-eg-click]');
    if (go) { closeMenus(); var el = document.querySelector(go.getAttribute('data-eg-click')); if (el) el.click(); return; }
    var tr = t.closest('[data-eg-trip]');
    if (tr) { closeMenus(); planTrip(tr, JSON.parse(tr.getAttribute('data-eg-trip') || '{}')); return; }
    // Share and QR from ⋯ (a hero's): read the page's own bar; the answer shows beside ⋯
    var sm = t.closest('[data-eg-share-m]'), qm = t.closest('[data-eg-qr-m]');
    if (sm || qm) {
      var menu = (sm || qm).closest('.eg-ml'), home = menu && menu._home, owner = home && home.closest('.eg-bar'), btn = home && home.querySelector('.eg-more');
      closeMenus();
      var here = location.origin + location.pathname + location.search, title = (owner && owner.getAttribute('data-eg-title')) || document.title;
      if (qm) { if (window.tvQr) tvQr(here, { anchor: btn, title: title }); return; }
      var url = window.tvShort ? tvShort.now(here) : here;
      if (navigator.share) { navigator.share({ title: title, url: url }).catch(function () {}); return; }
      var said = function () { if (window.TvAsk) TvAsk.say('Link copied', { anchor: btn }); }, ask = function () { if (window.TvAsk) TvAsk.ask('Copy this link', url, { yes: 'Done' }); };
      if (navigator.clipboard) navigator.clipboard.writeText(url).then(said, ask); else ask();
      return;
    }
    var rp = t.closest('.eg-ml [data-eg-report]');
    if (rp && !rp._go) { closeMenus(); rp._go = true; setTimeout(function () { rp.click(); rp._go = false; }, 0); e.stopImmediatePropagation(); return; }   // back in its bar first: Report reads the bar
    if (rp) return;
    if (t.closest('.eg-ml a, .eg-ml [data-tv-suggest], .eg-ml [data-hm-kind]')) { setTimeout(closeMenus, 0); return; }
    if (!t.closest('.eg-ml')) closeMenus();
  });
  window.addEventListener('scroll', function () { if (document.querySelector('.eg-ml:not([hidden])')) closeMenus(); }, { passive: true });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var tp = document.querySelector('.eg-trip'); if (tp) { tp.remove(); return; }
    var open = document.querySelector('.eg-ml:not([hidden])'); if (open) { closeMenus(); var b = open.previousElementSibling; if (b) b.focus(); }
  });
  // Plan a trip here (decisions/0146): the place's ready-made journeys first, then the Trip Planner set to it
  function planTrip(btn, p) {
    var old = document.querySelector('.eg-trip'); if (old) old.remove();
    var q = new URLSearchParams({ trip: 1 }); ['key', 'lat', 'lon'].forEach(function (k) { if (p[k] != null && p[k] !== '') q.set(k, p[k]); });
    if (window.TvAsk) TvAsk.busy(btn, true);
    fetch('/api/engage.php?' + q.toString(), { credentials: 'include' }).then(function (r) { return r.json(); }).then(function (d) {
      if (window.TvAsk) TvAsk.busy(btn, false);
      var box = document.createElement('div'); box.className = 'eg-trip'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'Plan a trip here');
      var name = d.place ? d.place.name : '';
      box.innerHTML = '<button type="button" class="eg-tx" aria-label="Close" title="Close">&times;</button><h3>Plan a trip' + (name ? ' in ' + esc(name) : '') + '</h3>'
        + ((d.journeys || []).length ? '<p>Ready-made journeys here, timed stop by stop:</p><ul>' + d.journeys.map(function (j) { return '<li><a href="' + esc(j.url) + '"><b>' + esc(j.title) + '</b><small>' + esc([j.minutes ? Math.round(j.minutes / 60 * 10) / 10 + ' hours' : '', j.why].filter(Boolean).join(' · ')) + '</small></a></li>'; }).join('') + '</ul>'
          : '<p>' + (name ? 'No ready-made journeys for ' + esc(name) + ' yet.' : 'This isn\u2019t inside one of our areas yet, so there are no ready-made journeys.') + '</p>')
        + '<a class="eg-plan" href="' + esc(d.plan || '/discovery/?view=plan') + '">Open the Trip Planner' + (name ? ' for ' + esc(name) : '') + '</a>'
        + '<p>Saving a trip and adding places to it are coming.</p>';
      document.body.appendChild(box); box.querySelector('.eg-tx').focus();
      box.addEventListener('click', function (ev) { if (ev.target.closest('.eg-tx')) { box.remove(); btn.focus && btn.focus(); } });
      setTimeout(function () { document.addEventListener('click', function off(ev) { if (!box.isConnected) { document.removeEventListener('click', off); return; } if (!box.contains(ev.target)) { box.remove(); document.removeEventListener('click', off); } }); }, 0);
    }).catch(function () { if (window.TvAsk) { TvAsk.busy(btn, false); TvAsk.say('The journeys couldn\u2019t load. Try again, or open the Trip Planner.', { anchor: btn, bad: true }); } });
  }
  document.addEventListener('click', function (e) {
    var bar = e.target.closest('.eg-bar'), sec = e.target.closest('.eg-sec');
    var like = e.target.closest('[data-eg-like]');
    if (like && bar) {
      likeIt(like, bar);
      return;
    }
    var share = e.target.closest('[data-eg-share]');
    if (share && bar) {
      var here = location.origin + location.pathname + location.search;
      var data = { title: bar.getAttribute('data-eg-title') || document.title, url: window.tvShort ? tvShort.now(here) : here };   // trvgo.us (decisions/0094)
      if (navigator.share) { navigator.share(data).catch(function () {}); return; }
      var done = function () { var s = share.querySelector('span'); s.textContent = 'Link copied'; setTimeout(function () { s.textContent = 'Share'; }, 2000); };
      var copy = function () { TvAsk.ask('Copy this link', data.url, { yes: 'Done' }); };
      if (navigator.clipboard) navigator.clipboard.writeText(data.url).then(done, copy); else copy();
      return;
    }
    var rep = e.target.closest('[data-eg-report]');
    if (rep && bar) { var p = rep.getAttribute('data-eg-report').split(':'); reportBox(bar, p[0], +p[1]); return; }
    var del = e.target.closest('[data-eg-del]');
    if (del && sec) {
      if (!TvAsk.gate(del, 'Remove this comment?', { yes: 'Remove', danger: true })) return;
      if (window.TvAsk) TvAsk.busy(del, true);
      post({ action: 'uncomment', comment: +del.getAttribute('data-eg-del') }).then(function () { var cm = del.closest('.eg-cm'); (cm.classList.contains('eg-re') ? cm : (cm.closest('.eg-th') || cm)).remove(); })   // a comment takes its replies with it
        .catch(function (x) { if (window.TvAsk) TvAsk.busy(del, false); TvAsk.say(x.message, { anchor: del, bad: true }); });
      return;
    }
    var crep = e.target.closest('[data-eg-rep]');
    if (crep && sec) { reportBox(crep.closest('.eg-cm'), 'comment', +crep.getAttribute('data-eg-rep')); return; }
    // Reply (decisions/0172 §2.3): one level, a box under the comment's replies; its writer is told
    var rb = e.target.closest('[data-eg-reply]');
    if (rb && sec) {
      var th = rb.closest('.eg-th'), open = th.querySelector('.eg-rf');
      if (open) { open.querySelector('textarea').focus(); return; }
      var main = sec.querySelector('[data-eg-form] [data-eg-age]');
      var f = document.createElement('form'); f.className = 'eg-rf'; f.setAttribute('data-eg-rf', rb.getAttribute('data-eg-reply'));
      f.innerHTML = '<textarea class="eg-in" maxlength="2000" placeholder="Reply to ' + esc(th.querySelector('.eg-cm b').textContent) + ' (public)" required></textarea>'
        + (main ? '<label class="eg-note"><input type="checkbox" data-eg-age required> I am 18 or older</label>' : '')
        + '<p style="margin:.3rem 0 0"><button class="eg-b" type="submit">Post reply</button> <button class="eg-b" type="button" data-eg-rx>Cancel</button></p>';
      th.appendChild(f); f.querySelector('textarea').focus();
      return;
    }
    var rx = e.target.closest('[data-eg-rx]');
    if (rx) { rx.closest('.eg-rf').remove(); }
  });
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-eg-rf]'); if (!f) return;
    e.preventDefault();
    var sec = f.closest('.eg-sec'), t = f.querySelector('textarea'), age = f.querySelector('[data-eg-age]'), btn = f.querySelector('button[type=submit]');
    if (window.TvAsk) TvAsk.busy(btn, true);
    (age && age.checked ? post({ action: 'age' }) : Promise.resolve()).then(function () {
      return post({ action: 'comment', kind: sec.getAttribute('data-eg-kind'), ref: sec.getAttribute('data-eg-ref'), body: t.value, parent: +f.getAttribute('data-eg-rf') });
    }).then(function (d) {
      f.insertAdjacentHTML('beforebegin', '<div class="eg-cm eg-re" data-c="' + d.comment.id + '"><button type="button" data-eg-del="' + d.comment.id + '">Remove</button><b>' + esc(d.comment.name) + '</b><small>just now</small><p>' + esc(d.comment.body) + '</p></div>');
      f.remove();
      var mainAge = sec.querySelector('[data-eg-form] [data-eg-age]'); if (age && mainAge) mainAge.closest('label').remove();
      var n = document.querySelector('.eg-bar a[href="#comments"] span'); if (n) n.textContent = +n.textContent + 1;
    }).catch(function (x) { if (window.TvAsk) { TvAsk.busy(btn, false); TvAsk.say(x.message, { anchor: btn, bad: true }); } });
  });
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-eg-form]'); if (!f) return;
    e.preventDefault();
    var sec = f.closest('.eg-sec'), t = f.querySelector('textarea'), age = f.querySelector('[data-eg-age]'), msg = f.querySelector('[data-eg-msg]');
    msg.textContent = 'Posting…';
    (age && age.checked ? post({ action: 'age' }) : Promise.resolve()).then(function () {
      return post({ action: 'comment', kind: sec.getAttribute('data-eg-kind'), ref: sec.getAttribute('data-eg-ref'), body: t.value });
    }).then(function (d) {
      var none = sec.querySelector('[data-eg-none]'); if (none) none.remove();
      sec.querySelector('[data-eg-list]').insertAdjacentHTML('beforeend', '<div class="eg-th" data-th="' + d.comment.id + '"><div class="eg-cm" data-c="' + d.comment.id + '"><button type="button" data-eg-del="' + d.comment.id + '">Remove</button><button type="button" data-eg-reply="' + d.comment.id + '">Reply</button><b>' + esc(d.comment.name) + '</b><small>just now</small><p>' + esc(d.comment.body) + '</p></div></div>');
      t.value = ''; msg.textContent = ''; if (age) age.closest('label').remove();
      var n = document.querySelector('.eg-bar a[href="#comments"] span'); if (n) n.textContent = +n.textContent + 1;
    }).catch(function (x) { msg.textContent = x.message; });
  });
  // a starter begins the tip: its words, then the box, ready to type (2026-11-19); a guest's starter asks them to sign in first
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-eg-start]'); if (!b || b.hasAttribute('data-needs-account')) return;
    var t = b.closest('form') && b.closest('form').querySelector('textarea'); if (!t) return;
    var w = b.getAttribute('data-eg-start').replace(/\?$/, '') + ': '; if (t.value.indexOf(w) < 0) t.value = (t.value ? t.value.replace(/\s*$/, '\n') : '') + w;
    t.focus(); t.setSelectionRange(t.value.length, t.value.length);
  });
})();
