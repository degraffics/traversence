// Traversence Vault extension: the background (decisions/0156 §8). It holds the vault key the Vault page gave it, in the
// browser's memory only (chrome.storage.session: never written to disk, gone when the browser closes, not readable by
// pages or content scripts), opens sealed logins here, and hands a login only to a page on the exact site it was saved
// for. The server sees only sealed data; each password filled is logged and emailed to the owner.
'use strict';

const IDLE_MS = 30 * 60 * 1000;   // forgets the key after 30 minutes unused
const te = new TextEncoder(), td = new TextDecoder();
chrome.storage.session.setAccessLevel && chrome.storage.session.setAccessLevel({ accessLevel: 'TRUSTED_CONTEXTS' }).catch(() => {});

// ---- bytes and keys -----------------------------------------------------------------------------------------------------
const b64u = buf => { const b = new Uint8Array(buf); let s = ''; for (const x of b) s += String.fromCharCode(x); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); };
const unb64u = s => { s = String(s).replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '='; const r = atob(s), b = new Uint8Array(r.length); for (let i = 0; i < r.length; i++) b[i] = r.charCodeAt(i); return b; };
const rnd = n => crypto.getRandomValues(new Uint8Array(n));
const hex = buf => Array.from(new Uint8Array(buf), x => x.toString(16).padStart(2, '0')).join('');
const aes = raw => crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
async function seal(key, bytes, aad) { const iv = rnd(12); const ct = await crypto.subtle.encrypt({ name: 'AES-GCM', iv, additionalData: te.encode(aad) }, key, bytes); return { v: 1, iv: b64u(iv), ct: b64u(ct) }; }
async function open(key, box, aad) { return new Uint8Array(await crypto.subtle.decrypt({ name: 'AES-GCM', iv: unb64u(box.iv), additionalData: te.encode(aad) }, key, unb64u(box.ct))); }

let keys = null;   // { vk, siteKey } made from the stored key, kept while this worker lives
async function vault(touch = true) {
  const s = (await chrome.storage.session.get('v')).v;
  if (!s) return null;
  if (Date.parse(s.until) < Date.now() || Date.now() - (s.last || 0) > IDLE_MS) { await forget(); return null; }
  if (!keys || keys.token !== s.token) {
    const raw = unb64u(s.vk), hk = await crypto.subtle.importKey('raw', raw, 'HKDF', false, ['deriveKey']);
    keys = { token: s.token, vk: await aes(raw), siteKey: await crypto.subtle.deriveKey({ name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(32), info: te.encode('tv-vault-site-v1') }, hk, { name: 'HMAC', hash: 'SHA-256', length: 256 }, false, ['sign']), items: null };
    raw.fill(0);
  }
  if (touch) { s.last = Date.now(); await chrome.storage.session.set({ v: s }); }
  return s;
}
async function forget() { keys = null; await chrome.storage.session.clear(); chrome.action.setBadgeText({ text: '' }); }
const siteOf = async origin => hex(await crypto.subtle.sign('HMAC', keys.siteKey, te.encode(origin.toLowerCase())));
const passMark = async pass => hex(await crypto.subtle.sign('HMAC', keys.siteKey, te.encode('pw:' + pass))).slice(0, 32);

// ---- the server ---------------------------------------------------------------------------------------------------------
async function api(s, body) {
  body.tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
  body.device = (navigator.userAgent.includes('Edg/') ? 'Edge' : 'Chrome');
  const r = await fetch(s.base + s.api, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Vault-Token': s.token }, body: JSON.stringify(body), credentials: 'omit', cache: 'no-store' });
  let j; try { j = await r.json(); } catch (e) { throw new Error('The vault didn\'t answer.'); }
  if (!j.ok) { if (r.status === 401) await forget(); throw new Error((j.error && j.error.message) || 'That didn\'t work.'); }
  return j;
}

/** Every login's name, address and user name, opened here (cached for a minute). */
async function items(s, fresh) {
  if (keys.items && !fresh && Date.now() - keys.items.at < 60000) return keys.items.list;
  const list = (await api(s, { do: 'items' })).items, out = [];
  for (const it of list) {
    try {
      const ik = await aes(await open(keys.vk, it.key, 'item'));
      const m = JSON.parse(td.decode(await open(ik, it.meta, 'meta')));
      out.push({ id: it.id, site: it.site, name: m.name, url: m.url, user: m.user || '', ph: m.ph || '', origin: new URL(m.url).origin });
    } catch (e) { /* an item that doesn't open with this key is skipped */ }
  }
  keys.items = { at: Date.now(), list: out };
  return out;
}
/** The logins for exactly this site (scheme, host and port), never a look-alike or a parent domain. */
async function forOrigin(s, origin) { const site = await siteOf(origin); return (await items(s)).filter(it => it.origin === origin && it.site === site); }

async function reveal(s, it, how) {
  const r = await api(s, { do: 'reveal', id: it.id, site: it.site, how: how + ': ' + it.name });
  const ik = await aes(await open(keys.vk, r.key, 'item'));
  return JSON.parse(td.decode(await open(ik, r.secret, 'login:' + it.url)));
}

/** Saves a new login, or a new password for one already saved. */
async function save(s, p) {
  const ikRaw = rnd(32), ik = await aes(ikRaw), ex = p.id ? (await items(s, true)).find(x => x.id === p.id) : null;
  const url = ex ? ex.url : p.url, name = ex ? ex.name : new URL(p.url).hostname.replace(/^www\./, ''), user = ex ? (p.user || ex.user) : p.user;
  let notes = '';
  if (ex) { try { notes = (await reveal(s, ex, 'Opened to update')).notes || ''; } catch (e) {} }
  const body = { do: 'save', id: ex ? ex.id : 0, site: await siteOf(new URL(url).origin), how: ex ? 'updated' : 'saved', key: await seal(keys.vk, ikRaw, 'item'),
    meta: await seal(ik, te.encode(JSON.stringify({ name, url, user, ph: await passMark(p.pass) })), 'meta'),
    secret: await seal(ik, te.encode(JSON.stringify({ user, pass: p.pass, notes })), 'login:' + url) };
  ikRaw.fill(0);
  await api(s, body); keys.items = null;
  return name;
}

// ---- save offers: kept for 3 minutes per tab, in memory, until the page that follows the sign-in asks ---------------------
const offerKey = tab => 'offer:' + tab;
async function offer(tab, origin, p) {
  const s = await vault(); if (!s) return;
  const mine = await forOrigin(s, origin), mark = await passMark(p.pass);
  const same = mine.find(x => x.user === p.user);
  if (same && same.ph === mark) return;                       // already saved as it is
  if (!same && mine.some(x => x.ph === mark)) return;          // the same password under another name: leave it
  await chrome.storage.session.set({ [offerKey(tab)]: { origin, url: p.url, user: p.user, pass: p.pass, id: same ? same.id : 0, name: same ? same.name : '', at: Date.now() } });
}
async function pendingFor(tab, origin) {
  const o = (await chrome.storage.session.get(offerKey(tab)))[offerKey(tab)];
  if (!o) return null;
  if (o.origin !== origin || Date.now() - o.at > 180000) { if (Date.now() - o.at > 180000) await chrome.storage.session.remove(offerKey(tab)); return null; }
  return o;
}

// ---- messages from our pages, the Vault page, and the popup -----------------------------------------------------------
const originOf = u => { try { const x = new URL(u); return x.protocol === 'https:' ? x.origin : null; } catch (e) { return null; } };

chrome.runtime.onMessageExternal.addListener((m, sender, reply) => {
  (async () => {
    const from = originOf(sender.url || sender.origin);   // only traversence.com can reach here (manifest)
    if (!from) return { ok: false };
    if (m.t === 'hello') {
      // a random name for this browser's copy of the extension (not secret), so connecting again replaces its old connection
      let install = (await chrome.storage.local.get('install')).install;
      if (!install) { install = b64u(rnd(16)); await chrome.storage.local.set({ install }); }
      const s = await vault(); return { ok: true, version: chrome.runtime.getManifest().version, connected: !!s, email: s ? s.email : '', install };
    }
    if (m.t === 'pair') {
      if (!/^[a-f0-9]{64}$/.test(m.token || '') || !m.vk || !/^\/admin\/api\/vault-ext\.php$/.test(m.api || '')) return { ok: false, error: 'That connection wasn\'t complete.' };
      await chrome.storage.session.set({ v: { vk: m.vk, token: m.token, until: m.until, email: m.email || '', base: from, api: m.api, last: Date.now() } });
      keys = null; await vault(); chrome.action.setBadgeText({ text: '' });
      return { ok: true };
    }
    if (m.t === 'unpair') { await forget(); return { ok: true }; }
    return { ok: false };
  })().then(reply, e => reply({ ok: false, error: e.message }));
  return true;
});

chrome.runtime.onMessage.addListener((m, sender, reply) => {
  (async () => {
    const fromPopup = sender.id === chrome.runtime.id && !!sender.url && sender.url.startsWith(chrome.runtime.getURL(''));   // our own pages (the pop-up), never a web page
    if (fromPopup) {
      if (m.t === 'status') { const s = await vault(); return s ? { connected: true, email: s.email, until: s.until, base: s.base } : { connected: false }; }
      if (m.t === 'lock') { const s = (await chrome.storage.session.get('v')).v; if (s) { try { await api(s, { do: 'bye' }); } catch (e) {} } await forget(); return { ok: true }; }
      // the tab's address: Chrome gives it here only while the pop-up's click allows (activeTab), so the pop-up, a page of
      // ours, passes the one it was given too
      const tab = m.tabId ? await chrome.tabs.get(m.tabId).catch(() => null) : null, origin = tab ? originOf(tab.url || m.url) : null;
      const s = await vault(); if (!s) return { connected: false };
      if (m.t === 'tablist') return { connected: true, origin, list: origin ? (await forOrigin(s, origin)).map(x => ({ id: x.id, name: x.name, user: x.user })) : [] };
      if (m.t === 'fillTab' && origin) {
        const it = (await forOrigin(s, origin)).find(x => x.id === m.id); if (!it) return { ok: false, error: 'That login isn\'t for this site.' };
        const sec = await reveal(s, it, 'Password filled');
        await chrome.tabs.sendMessage(tab.id, { t: 'doFill', origin, user: sec.user || it.user, pass: sec.pass || '' }, { frameId: 0 });
        return { ok: true };
      }
      if (m.t === 'grabTab' && origin) {
        // saves what's filled in on the page now (typed, or filled by the browser), without signing in first
        const g = await chrome.tabs.sendMessage(tab.id, { t: 'grab' }, { frameId: 0 }).catch(() => null);
        if (!g || !g.pass) return { ok: false, error: 'Nothing to save: fill in the user name and password on the page first (or let your browser fill them), then press this again.' };
        if (originOf(g.url) !== origin) return { ok: false, error: 'That page changed. Try again.' };
        const mine = await forOrigin(s, origin), same = mine.find(x => x.user === g.user);
        if (same && same.ph === await passMark(g.pass)) return { ok: true, message: 'Already in your vault as ' + same.name + '.' };
        const name = await save(s, { origin, url: String(g.url).split(/[?#]/)[0], user: String(g.user || '').slice(0, 200), pass: String(g.pass).slice(0, 500), id: same ? same.id : 0 });
        return { ok: true, message: (same ? 'Updated ' : 'Saved ') + name + ' in your vault.' };
      }
      return { ok: false };
    }
    // a page's content script: what it may have depends only on the address the browser says it's on
    const origin = originOf(sender.url), tab = sender.tab && sender.tab.id;
    if (!origin || !tab) return { ok: false };
    if (m.t === 'list') { const s = await vault(false); if (!s) return { locked: true, list: [] }; return { list: (await forOrigin(s, origin)).map(x => ({ id: x.id, name: x.name, user: x.user })) }; }
    if (m.t === 'fill') {
      const s = await vault(); if (!s) return { ok: false, error: 'The vault isn\'t connected.' };
      const it = (await forOrigin(s, origin)).find(x => x.id === m.id); if (!it) return { ok: false, error: 'That login isn\'t for this site.' };
      const sec = await reveal(s, it, 'Password filled');
      return { ok: true, user: sec.user || it.user, pass: sec.pass || '' };
    }
    if (m.t === 'offer' && m.pass) { await offer(tab, origin, { user: String(m.user || '').slice(0, 200), pass: String(m.pass).slice(0, 500), url: originOf(m.url) === origin ? String(m.url).split(/[?#]/)[0] : origin + '/' }); return { ok: true }; }
    if (m.t === 'pending') { const o = await pendingFor(tab, origin); return o ? { user: o.user, update: !!o.id, name: o.name } : null; }
    if (m.t === 'save') {
      const o = await pendingFor(tab, origin); if (!o) return { ok: false, error: 'That ran out. Sign in again to save it.' };
      const s = await vault(); if (!s) return { ok: false, error: 'The vault isn\'t connected.' };
      await chrome.storage.session.remove(offerKey(tab));
      const name = await save(s, o); return { ok: true, message: (o.id ? 'Updated ' : 'Saved ') + name + ' in your vault.' };
    }
    if (m.t === 'dismiss') { await chrome.storage.session.remove(offerKey(tab)); return { ok: true }; }
    return { ok: false };
  })().then(reply, e => reply({ ok: false, error: e.message }));
  return true;
});

// forget the key when its time is up, or after 30 minutes unused
chrome.alarms.create('tvv-idle', { periodInMinutes: 1 });
chrome.alarms.onAlarm.addListener(async a => {
  if (a.name !== 'tvv-idle') return;
  const s = (await chrome.storage.session.get('v')).v;
  if (s && (Date.parse(s.until) < Date.now() || Date.now() - (s.last || 0) > IDLE_MS)) await forget();
});
chrome.tabs.onRemoved.addListener(tab => chrome.storage.session.remove(offerKey(tab)));
