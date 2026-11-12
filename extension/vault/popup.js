// Traversence Vault extension: the toolbar pop-up. Shows whether the vault is connected, the logins for the site in
// this tab (only that exact site), and Fill, Open the Vault page, and Disconnect.
(async function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };
  var ask = function (m) { return new Promise(function (res) { chrome.runtime.sendMessage(m, function (r) { void chrome.runtime.lastError; res(r || {}); }); }); };
  var VAULT = 'https://traversence.com/admin/config/vault';
  function link(text, href, go) { var a = document.createElement('a'); a.className = 'btn' + (go ? ' go' : ''); a.href = href; a.target = '_blank'; a.rel = 'noopener'; a.textContent = text; return a; }
  function button(text, fn, go) { var b = document.createElement('button'); b.type = 'button'; b.className = go ? 'go' : ''; b.textContent = text; b.addEventListener('click', fn); return b; }
  function status(t, k) { var p = $('#st'); p.textContent = t; p.className = 's ' + (k || ''); }

  var s = await ask({ t: 'status' }), acts = $('#acts');
  if (!s.connected) {
    status((s.ended ? 'Disconnected: ' + s.ended + ' ' : 'Not connected. ') + 'Press Connect: the Vault page opens, you unlock it, and you\'re brought back here.', s.ended ? 'bad' : '');
    var here = (await chrome.tabs.query({ active: true, currentWindow: true }))[0];
    acts.appendChild(button('Connect', async function () { await ask({ t: 'connect', tabId: here ? here.id : 0 }); window.close(); }, true));
    $('#foot').textContent = 'A connection lasts 12 hours or until the browser closes, in every tab. It ends at once if the vault is locked or you disconnect it.';
    return;
  }
  VAULT = s.base + '/admin/config/vault';
  var until = new Date(s.until);
  status('Connected as ' + s.email + ' until ' + until.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', timeZoneName: 'short' }) + '.', 'ok');
  var tab = (await chrome.tabs.query({ active: true, currentWindow: true }))[0];
  var r = tab ? await ask({ t: 'tablist', tabId: tab.id, url: tab.url }) : {};
  var box = $('#list');
  if (r.origin && r.list && r.list.length) {
    r.list.forEach(function (it) {
      var d = document.createElement('div'); d.className = 'it';
      var t = document.createElement('div'); t.innerHTML = '<div class="n"></div><div class="u"></div>';
      t.querySelector('.n').textContent = it.name; t.querySelector('.u').textContent = it.user || '(no user name)';
      d.appendChild(t);
      d.appendChild(button('Fill', async function (e) {
        var b = e.target; b.disabled = true; b.textContent = '…';
        var x = await ask({ t: 'fillTab', id: it.id, tabId: tab.id, url: tab.url });
        if (x.ok) window.close(); else { b.disabled = false; b.textContent = 'Fill'; status(x.error || 'That didn\'t work.', 'bad'); }
      }, true));
      box.appendChild(d);
    });
  } else {
    var p = document.createElement('p'); p.className = 's';
    p.textContent = r.origin ? 'No saved logins for ' + new URL(r.origin).hostname + ' yet.' : 'Logins fill only on secure (https) sites.';
    box.appendChild(p);
  }
  if (r.origin) {
    var sv = button('Save this site\'s login', async function () {
      sv.disabled = true; sv.textContent = 'Saving…';
      var x = await ask({ t: 'grabTab', tabId: tab.id, url: tab.url });
      sv.disabled = false; sv.textContent = 'Save this site\'s login';
      status(x.ok ? x.message : (x.error || 'That didn\'t work.'), x.ok ? 'ok' : 'bad');
      if (x.ok) setTimeout(function () { location.reload(); }, 1500);   // the list then shows what was just saved
    });
    var tip = document.createElement('small'); tip.textContent = 'Fill in the user name and password on the page (or let your browser fill them), then press Save. Or just sign in: the vault offers to save it on the next page.';
    box.appendChild(sv); box.appendChild(tip);
  }
  acts.appendChild(link('Open the Vault page', VAULT));
  acts.appendChild(button('Disconnect', async function () { await ask({ t: 'lock' }); status('Disconnected. The vault key is gone from this browser.', ''); box.innerHTML = ''; acts.innerHTML = ''; acts.appendChild(link('Open the Vault page', VAULT, true)); }));
  $('#foot').textContent = 'Every password filled is emailed to you with a link to lock the vault.';
})();
