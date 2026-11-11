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
    status('Not connected. Open the Vault page, unlock it, and press Connect this browser\'s extension.', '');
    acts.appendChild(link('Open the Vault page', VAULT, true));
    $('#foot').textContent = 'The connection lasts 12 hours, until the browser closes, or 30 minutes unused, whichever comes first.';
    return;
  }
  VAULT = s.base + '/admin/config/vault';
  var until = new Date(s.until);
  status('Connected as ' + s.email + ' until ' + until.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', timeZoneName: 'short' }) + '.', 'ok');
  var tab = (await chrome.tabs.query({ active: true, currentWindow: true }))[0];
  var r = tab ? await ask({ t: 'tablist', tabId: tab.id }) : {};
  var box = $('#list');
  if (r.origin && r.list && r.list.length) {
    r.list.forEach(function (it) {
      var d = document.createElement('div'); d.className = 'it';
      var t = document.createElement('div'); t.innerHTML = '<div class="n"></div><div class="u"></div>';
      t.querySelector('.n').textContent = it.name; t.querySelector('.u').textContent = it.user || '(no user name)';
      d.appendChild(t);
      d.appendChild(button('Fill', async function (e) {
        var b = e.target; b.disabled = true; b.textContent = '…';
        var x = await ask({ t: 'fillTab', id: it.id, tabId: tab.id });
        if (x.ok) window.close(); else { b.disabled = false; b.textContent = 'Fill'; status(x.error || 'That didn\'t work.', 'bad'); }
      }, true));
      box.appendChild(d);
    });
  } else {
    var p = document.createElement('p'); p.className = 's';
    p.textContent = r.origin ? 'No saved logins for ' + new URL(r.origin).hostname + '. Sign in there and the vault offers to save it.' : 'Logins fill only on secure (https) sites.';
    box.appendChild(p);
  }
  acts.appendChild(link('Open the Vault page', VAULT));
  acts.appendChild(button('Disconnect', async function () { await ask({ t: 'lock' }); status('Disconnected. The vault key is gone from this browser.', ''); box.innerHTML = ''; acts.innerHTML = ''; acts.appendChild(link('Open the Vault page', VAULT, true)); }));
  $('#foot').textContent = 'Every password filled is emailed to you with a link to lock the vault.';
})();
