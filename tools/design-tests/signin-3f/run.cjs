// Release 3f, sign-in (handoffs/signin-3f.md; screens proposal A7, A9–A12, B7, B8; decisions/0100's note of 2026-11-22),
// rendered at 1280 and 390 on the harness (handoffs/design-harness/README.md) with seed.sql. Every flow is pressed in a
// real browser: the tabs, the social buttons' words, no cart, bell or search on the sign-in pages, the code box (its
// placeholder, its clock time, a pasted code with spaces, the 6th digit signing in, the email's link signing in by itself),
// Can't get the email? (×, Esc, a click outside, a refusal, sent), its owners' case in Pulse with every guard rail (who,
// email, last sign-in, the contact not verified; each button asks first; greyed with the reason for a non-owner and with
// cPanel off), each button pressed (Change work email with the old address told, Reset the mailbox password and New setup
// link shown once, Close), the staff log, and the add-an-authenticator notice dismissed and remembered.
// Run: node run.cjs [base] [tailwind browser build] — env MAIL (the harness's mail log), CPANEL (its cPanel stand-in log),
// ENVF (the harness .env), SEED (this folder's seed.sql); mysql on the path with the harness database tvh.
const { chromium } = require('playwright');
const fs = require('fs'), cp = require('child_process'), path = require('path');
const BASE = process.argv[2] || 'http://127.0.0.1:8790', TW = process.argv[3] || '';
const MAIL = process.env.MAIL || '/tmp/claude-0/mail.log', CPANEL = process.env.CPANEL || '/tmp/claude-0/cpanel.log';
const ENVF = process.env.ENVF || '/home/user/.env', SEED = process.env.SEED || path.join(__dirname, 'seed.sql');
let pass = 0, fail = 0; const ok = (c, m) => { c ? pass++ : fail++; console.log((c ? '  ✓ ' : '  ✗ ') + m); };
const sql = q => cp.execFileSync('mysql', ['-N', '-B', 'tvh', '-e', q]).toString().trim();
const mails = () => (fs.existsSync(MAIL) ? fs.readFileSync(MAIL, 'utf8').trim().split('\n').filter(Boolean).map(l => JSON.parse(l)) : []);
const lastCode = to => { const m = mails().filter(x => x.to === to && /^\d{6} is your/.test(x.subject)).pop(); return m && m.subject.slice(0, 6); };
const cpanel = on => { let e = fs.readFileSync(ENVF, 'utf8').replace(/^HARNESS_CPANEL=.*\n?/m, ''); if (on) e += 'HARNESS_CPANEL=' + CPANEL + '\n'; fs.writeFileSync(ENVF, e); };
const sleep = ms => new Promise(r => setTimeout(r, ms));

async function page(b, w) {
  const c = await b.newContext({ viewport: { width: w, height: w > 800 ? 900 : 844 }, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) Chrome/130 Safari/537.36', timezoneId: 'America/Phoenix' });
  await c.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: BASE });
  const p = await c.newPage(); p.errs = [];
  p.on('pageerror', e => p.errs.push(String(e)));
  await p.route(/^https?:\/\/(?!127\.0\.0\.1)/, r => TW && /cdn\.tailwindcss\.com/.test(r.request().url()) ? r.fulfill({ contentType: 'application/javascript', path: TW }) : r.abort());
  return p;
}
const go = async (p, u) => { await p.goto(BASE + u, { waitUntil: 'load' }); await p.waitForTimeout(500); };
async function toCode(p, sid, pw) {   // the staff ID and password: on to the code step
  await go(p, '/admin/login.php'); await p.fill('#sid', sid); await p.fill('#pw', pw);
  await Promise.all([p.waitForNavigation(), p.click('button.b')]); await p.waitForTimeout(400);
}
async function signIn(p, sid, pw, to) {   // the whole staff sign-in, the code typed digit by digit (it sends at the 6th)
  await toCode(p, sid, pw); const code = lastCode(to);
  await p.click('#code'); await Promise.all([p.waitForNavigation(), p.keyboard.type(code, { delay: 40 })]); await p.waitForTimeout(500);
}
const noSideways = async (p, w, m) => { const sw = await p.evaluate(() => document.documentElement.scrollWidth); ok(sw <= w, m + ': no sideways scroll (' + sw + ')'); };

(async () => {
  const b = await chromium.launch();
  for (const w of [1280, 390]) {
    console.log('— ' + w + 'px');
    cp.execSync('mysql tvh < ' + JSON.stringify(SEED)); fs.writeFileSync(MAIL, ''); fs.writeFileSync(CPANEL, ''); cpanel(false);

    // A7, B7, A9: the member sign-in
    let p = await page(b, w); await go(p, '/login.php');
    let r = await p.evaluate(() => { const s = e => getComputedStyle(e), i = document.getElementById('t-in'), u = document.getElementById('t-up');
      return { inC: s(i).color, inB: s(i).borderBottomColor, inBW: s(i).borderBottomWidth, upC: s(u).color, upB: s(u).borderBottomColor, tt: s(i).textTransform + s(u).textTransform,
        go: s(document.getElementById('go-in')).textTransform, goTxt: document.getElementById('go-in').textContent.trim(),
        fp: (l => ({ c: s(l).color, d: s(l).textDecorationLine, f: s(l).fontFamily }))(document.querySelector('a.au-link')),
        social: [...document.querySelectorAll('.tv-sbtn')].map(a => ({ t: a.innerText.trim(), v: a.offsetWidth > 0 })) }; });
    ok(r.inC === 'rgb(146, 64, 14)' && r.inB === 'rgb(146, 64, 14)' && parseFloat(r.inBW) >= 2, 'A7: the chosen tab is marked, brown words and a brown underline');
    ok(r.upC !== r.inC && r.upB !== r.inB, 'A7: the other tab is quiet (' + r.upC + ')');
    ok(r.tt === 'nonenone' && r.go === 'none' && r.goTxt === 'Sign in', 'B7: tabs and buttons in sentence case ("' + r.goTxt + '")');
    ok(r.fp.c === 'rgb(146, 64, 14)' && r.fp.d === 'underline' && !/Merriweather|Playfair|Georgia|serif$/.test(r.fp.f.split(',')[0]), 'B7: Forgot password? in the one link style (brown, underlined, the UI face)');
    ok(r.social.length === 3 && r.social.every(x => x.v && /^Continue with (Google|Microsoft|Facebook)$/.test(x.t)), 'A9: the social buttons carry their words: ' + r.social.map(x => x.t).join(' | '));
    await p.click('#t-up'); await p.waitForTimeout(150);
    r = await p.evaluate(() => ({ up: getComputedStyle(document.getElementById('t-up')).color, sel: document.getElementById('t-up').getAttribute('aria-selected'), btn: document.getElementById('go-up').textContent.trim() }));
    ok(r.up === 'rgb(146, 64, 14)' && r.sel === 'true' && r.btn === 'Create account', 'A7: Create account, once chosen, is the marked tab');
    await noSideways(p, w, '/login.php');
    if (w === 390) await p.screenshot({ path: '/tmp/claude-0/shots/3f-login-390.png', fullPage: true });

    // A10: no cart, bell or search on the four sign-in pages; still there elsewhere
    for (const u of ['/login.php', '/forgot-password.php', '/admin/login.php', '/admin/setup.php']) {
      await go(p, u); r = await p.evaluate(() => ['tv-cart', 'tv-bell', 'tv-asearch', 'tv-search'].filter(i => document.getElementById(i)));
      ok(r.length === 0, 'A10: ' + u + ' has no cart, bell or search' + (r.length ? ' (found ' + r.join(', ') + ')' : ''));
    }
    await go(p, '/faq.php'); r = await p.evaluate(() => !!document.getElementById('tv-cart') && !!document.getElementById('tv-bell'));
    ok(r, 'A10: other pages keep the cart and bell (/faq.php)');
    await p.context().close();

    // A11, A12: the code step, a pasted code with spaces, the 6th digit
    p = await page(b, w); await toCode(p, 'TRV-0002', 'sam-password-1234');
    r = await p.evaluate(() => { const t = document.querySelector('time[data-tv-clock]'), i = document.getElementById('code'); return { ph: i.placeholder, t: t && t.textContent.trim(), dt: t && t.getAttribute('datetime'), txt: t && t.parentNode.textContent }; });
    ok(r.ph === '6 digits', 'A11: the box says "6 digits"');
    const end = r.dt ? (Date.parse(r.dt) - Date.now()) / 60000 : -1;
    ok(/^\d{1,2}:\d{2}\s?[AP]M MST$/.test(r.t || '') && end > 8 && end <= 10.1, 'A11: "It works until ' + r.t + '" (the clock time, in the viewer\'s zone, ' + end.toFixed(1) + ' min away)');
    const m1 = mails().pop();
    ok(m1 && /Tap or click the code to select it, then copy\./.test(m1.html) && !/Click the code once/.test(m1.html), 'A12: the email says "Tap or click the code"');
    ok(m1 && /Signs you in with this code, on the device where you started\./.test(m1.html), 'A12: the email\'s button says it signs you in');
    // a wrong code pasted with a space: kept as 6 digits, sent at once, refused with the words
    await p.click('#code');
    await Promise.all([p.waitForNavigation(), p.evaluate(() => { const i = document.getElementById('code'); i.value = '000 000'; i.dispatchEvent(new Event('input', { bubbles: true })); })]);
    await p.waitForTimeout(300);
    r = await p.evaluate(() => ({ alert: (document.querySelector('.sl .no') || {}).textContent || '', still: !!document.getElementById('code') }));
    ok(/doesn.t match/.test(r.alert) && r.still, 'A11: a pasted "000 000" is sent at the 6th digit, and a wrong one is refused: "' + r.alert.trim() + '"');
    const code = lastCode('sam.rivera@trvgo.com');
    await p.click('#code');
    await Promise.all([p.waitForNavigation(), p.evaluate(c => { const i = document.getElementById('code'); i.value = c.slice(0, 3) + ' ' + c.slice(3); i.dispatchEvent(new Event('input', { bubbles: true })); }, code)]);
    await p.waitForTimeout(400);
    ok(!/\/admin\/login/.test(p.url()) && sql("SELECT COUNT(*) FROM staff_log WHERE staff_account_id = 2 AND event = 'signed_in'") === '1', 'A11: the right code pasted as "' + code.slice(0, 3) + ' ' + code.slice(3) + '" signs in by itself (' + new URL(p.url()).pathname + ')');

    // B8 notice: Sam (no authenticator app) sees it once on the admin side; Not now is remembered
    await go(p, '/admin/staff.php');
    r = await p.evaluate(() => { const n = document.getElementById('tv-appnudge'); if (!n) return null; const b = n.getBoundingClientRect(), at = document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2);
      return { t: n.innerText, add: n.querySelector('a').getAttribute('href'), top: !!(at && at.closest('#tv-appnudge')), inView: b.top >= 0 && b.bottom <= innerHeight && b.left >= 0 && b.right <= innerWidth }; });
    ok(r && /Add an authenticator app/.test(r.t) && r.add === '/admin/staff.php#my-app', 'B8: no authenticator app: the notice to add it, with Add it (' + (r && r.add) + ')');
    ok(r && r.top && r.inView, 'B8: the notice is on top and wholly in the window (not under the admin\'s fixed headers)');
    await noSideways(p, w, 'the admin with the notice');
    if (w === 390) await p.screenshot({ path: '/tmp/claude-0/shots/3f-nudge-390.png' });
    const nn = w === 390 ? '#tv-appnudge button[aria-label="Not now"]' : '#tv-appnudge button:has-text("Not now")';   // × at 390, the words at 1280
    await Promise.all([p.waitForNavigation(), p.click(nn)]); await p.waitForTimeout(300);
    ok(new URL(p.url()).pathname === '/admin/staff.php' && !(await p.$('#tv-appnudge')), 'B8: ' + (w === 390 ? '×' : 'Not now') + ' comes back to the same page, the notice gone');
    await go(p, '/admin/staff.php');
    ok(!(await p.$('#tv-appnudge')) && sql("SELECT COUNT(*) FROM staff_settings WHERE name = 'appnudge:2'") === '1', 'B8: Not now is remembered for this staff login (staff_settings appnudge:2)');
    await p.context().close();

    // the email's link: the code fills in and signs in by itself
    p = await page(b, w); await toCode(p, 'TRV-0002', 'sam-password-1234');
    const c2 = lastCode('sam.rivera@trvgo.com');
    const t2 = await p.context().newPage();   // the email's link opens a new tab, in the browser where the sign-in started
    await t2.route(/^https?:\/\/(?!127\.0\.0\.1)/, r => TW && /cdn\.tailwindcss\.com/.test(r.request().url()) ? r.fulfill({ contentType: 'application/javascript', path: TW }) : r.abort());
    await t2.goto(BASE + '/admin/login.php#code=' + c2, { waitUntil: 'load' }); await t2.waitForURL(u => !/\/admin\/login/.test(String(u)), { timeout: 8000 }).catch(() => {}); await t2.waitForTimeout(300);
    ok(!/\/admin\/login/.test(t2.url()), 'A11: "Sign in with this code" from the email signs in by itself (' + new URL(t2.url()).pathname + ')');
    await p.context().close();

    // B8: Can't get the email? on the code step
    p = await page(b, w); await toCode(p, 'TRV-0002', 'sam-password-1234');
    const vis = () => p.evaluate(() => !document.getElementById('sl-help').hidden);
    ok(!(await vis()), 'B8: the form starts closed');
    await p.click('#sl-help-open'); ok(await vis(), 'B8: Can\'t get the email? opens the form in place');
    await p.click('[data-help-x]'); ok(!(await vis()), 'B8: × closes it');
    await p.click('#sl-help-open'); await p.keyboard.press('Escape'); ok(!(await vis()), 'B8: Esc closes it');
    await p.click('#sl-help-open'); await p.mouse.click(5, w > 800 ? 880 : 830); ok(!(await vis()), 'B8: a click outside closes it while it\'s empty');
    await p.click('#sl-help-open'); await p.fill('#help-name', 'Sam Rivera'); await p.mouse.click(5, w > 800 ? 880 : 830); ok(await vis(), 'B8: a click outside keeps it open once something is typed');
    await noSideways(p, w, 'the code step with the form');
    if (w === 390) await p.screenshot({ path: '/tmp/claude-0/shots/3f-help-390.png', fullPage: true });
    await p.fill('#help-contact', 'call me');
    await Promise.all([p.waitForNavigation(), p.click('#sl-help button.b')]); await p.waitForTimeout(300);
    r = await p.evaluate(() => ({ err: (document.querySelector('.sl .no') || {}).textContent || '', open: !document.getElementById('sl-help').hidden, name: document.getElementById('help-name').value }));
    ok(/phone number or another email/.test(r.err) && r.open && r.name === 'Sam Rivera', 'B8: a contact that isn\'t a phone or email is refused, the form still open with what was typed');
    await p.fill('#help-contact', '(928) 555-0142');
    await Promise.all([p.waitForNavigation(), p.click('#sl-help button.b')]); await p.waitForTimeout(300);
    r = await p.evaluate(() => ({ ok: (document.querySelector('.sl .ok') || {}).textContent || '', form: !!document.getElementById('sl-help'), code: !!document.getElementById('code') }));
    ok(/Sent to the owners\. One of them will contact you at \(928\) 555-0142/.test(r.ok) && !r.form && r.code, 'B8: sent: "' + r.ok.trim().slice(0, 80) + '…", the code step still there');
    const req = sql("SELECT CONCAT(id,'|',staff_account_id,'|',contact,'|',status,'|',tz) FROM staff_mail_requests ORDER BY id DESC LIMIT 1").split('|');
    ok(req[1] === '2' && req[2] === '(928) 555-0142' && req[3] === 'open' && req[4] === 'America/Phoenix', 'B8: the request is kept for TRV-0002 with the device\'s zone (' + req[4] + ')');
    ok(sql("SELECT COUNT(*) FROM staff_log WHERE staff_account_id = 2 AND event = 'mail_help'") === '1', 'B8: the request is in Sam\'s staff log');
    // too soon again from the same browser: refused, nothing added
    await Promise.all([p.waitForNavigation(), p.evaluate(() => { const f = document.createElement('form'); f.method = 'post'; [['csrf', document.querySelector('[name=csrf]').value], ['do', 'help'], ['help_name', 'Sam'], ['help_contact', '928 555 0142']].forEach(([k, v]) => { const i = document.createElement('input'); i.name = k; i.value = v; f.appendChild(i); }); document.body.appendChild(f); f.submit(); })]);
    await p.waitForTimeout(300); r = await p.evaluate(() => (document.querySelector('.sl .no') || {}).textContent || '');
    ok(/sent a moment ago/.test(r) && sql('SELECT SUM(sends) FROM staff_mail_requests') === '1', 'B8: sending again at once is refused ("' + r.trim().slice(0, 50) + '…")');
    await p.context().close();
    const key = 'staffmail:' + req[0];

    // B8: a non-owner (TRV-0003, admin) sees the case, its buttons greyed with the reason
    p = await page(b, w); await signIn(p, 'TRV-0003', 'ada-password-1234', 'ada.admin@trvgo.com');
    await go(p, '/admin/dashboard/stream#case=' + encodeURIComponent(key)); await p.waitForTimeout(1200);
    r = await p.evaluate(() => [...document.querySelectorAll('#pb-dr-play li')].map(li => { const b = li.querySelector('button'); return { t: li.querySelector('b').textContent, dis: b ? b.disabled : null, why: (li.querySelector('.pb-off') || {}).textContent || '' }; }));
    ok(r.length === 5 && r.slice(1).every(x => x.dis === true && /Only an owner can do this/.test(x.why)), 'B8: a non-owner sees the 4 buttons greyed with the reason ("' + (r[1] && r[1].why) + '")');
    await p.context().close();

    // B8: the owner's case
    p = await page(b, w); await signIn(p, 'TRV-0001', 'owner-password-123', 'jason.owner@trvgo.com');
    ok(!(await p.$('#tv-appnudge')), 'B8: the owner (with an authenticator app) gets no notice');
    await go(p, '/admin/dashboard/stream');
    r = await p.evaluate(k => { const row = document.querySelector('.ps-row[data-key="' + k + '"]'); return row && row.innerText; }, key);
    ok(r && /Can.t get their work email: TRV-0002 · Sam Rivera/.test(r), 'B8: the owners\' case is in Pulse: "' + (r || '').split('\n').find(l => /work email/.test(l)) + '"');
    await go(p, '/admin/dashboard/today'); await go(p, '/admin/dashboard/stream#case=' + encodeURIComponent(key)); await p.waitForTimeout(1200);
    const steps = () => p.evaluate(() => [...document.querySelectorAll('#pb-dr-play li')].map(li => { const b = li.querySelector('button'); return { t: li.querySelector('b').textContent, h: (li.querySelector('small') || {}).textContent || '', b: b && b.textContent, dis: b ? b.disabled : null, ask: b && b.dataset.ask, why: (li.querySelector('.pb-off') || {}).textContent || '' }; }));
    let st = await steps();
    ok(st[0] && st[0].t === 'Check it\'s them' && st[0].b === null, 'B8: the first step is to check it\'s them, before any button');
    ok(/TRV-0002 · Sam Rivera/.test(st[0].h) && /Work email now: sam\.rivera@trvgo\.com/.test(st[0].h) && /Last sign-in: Nov \d+, 2026, \d+:\d\d [AP]M [A-Z]+\./.test(st[0].h) && /\(928\) 555-0142 — not verified/.test(st[0].h), 'B8: it shows the login, its email now, its last sign-in and the contact, not verified' + (pass && fail ? '' : '') + ' [' + st[0].h + ']');
    ok(/America|MST|Asked Nov \d+, 2026/.test(st[0].h), 'B8: when it was asked, labelled with its zone');
    ok(['Change work email', 'Reset the mailbox password', 'New setup link'].every(n => st.some(x => x.b === n && x.ask === 'Have you confirmed this is them?')), 'B8: the three buttons each ask "Have you confirmed this is them?" first');
    const box = st.find(x => x.b === 'Reset the mailbox password');
    ok(box.dis === true && /cPanel isn.t connected/.test(box.why), 'B8: with cPanel off, Reset the mailbox password is greyed with the reason');
    await noSideways(p, w, 'the case');
    if (w === 390) await p.screenshot({ path: '/tmp/claude-0/shots/3f-case-390.png', fullPage: false });
    if (w === 1280) await p.screenshot({ path: '/tmp/claude-0/shots/3f-case-1280.png', fullPage: false });

    const btn = n => p.locator('#pb-dr-play button', { hasText: n });
    // New setup link: Esc on the question does nothing; Yes makes it, shown once
    await btn('New setup link').click(); await p.waitForTimeout(200);
    ok(await p.$('.tv-ask.danger'), 'B8: New setup link asks first (danger)');
    await p.keyboard.press('Escape'); await p.waitForTimeout(200);
    ok(!(await p.$('.tv-ask')) && sql("SELECT status FROM staff_accounts WHERE id = 2") === 'active', 'B8: Esc on the question changes nothing');
    await btn('New setup link').click(); await p.waitForTimeout(200); await p.click('.tv-ask .yes'); await p.waitForTimeout(1200);
    r = await p.evaluate(() => { const v = document.querySelector('.pb-once-v'); return v && { type: v.type, val: v.value, head: document.querySelector('#pb-dr-form b').textContent, msg: document.querySelector('#pb-dr-form .pb-msg').textContent }; });
    ok(r && r.type === 'password' && /\/admin\/setup\.php\?/.test(r.val) && /Setup link for TRV-0002/.test(r.head), 'B8: the setup link is shown to the owner, hidden until asked (' + (r && r.head) + ')');
    ok(sql("SELECT CONCAT(status,'|',password_hash,'|',totp_secret) FROM staff_accounts WHERE id = 2") === 'setup||' && sql('SELECT session_version FROM users WHERE id = 3') === '1', 'B8: their sessions ended, password and authenticator cleared, waiting for setup');
    await p.click('#pb-dr-form button:has-text("Show")'); r = await p.evaluate(() => document.querySelector('.pb-once-v').type);
    ok(r === 'text', 'B8: Show reveals it');
    await p.click('#pb-dr-form button:has-text("Copy")'); await p.waitForTimeout(300);
    const clip = await p.evaluate(() => navigator.clipboard.readText().catch(() => ''));
    ok(/\/admin\/setup\.php\?/.test(clip), 'B8: Copy puts the link on the clipboard');
    await Promise.all([p.waitForNavigation(), p.click('#pb-dr-form button:has-text("Done")')]); await p.waitForTimeout(1500);
    ok(sql("SELECT COUNT(*) FROM pulse_notes WHERE item_key = '" + key + "' AND body LIKE 'Made a new setup link%'") === '1' && sql("SELECT COUNT(*) FROM staff_log WHERE staff_account_id = 2 AND event = 'reset'") === '1', 'B8: on the case\'s notes and in the staff log');

    // Change work email: asks, then its form; the old address is told
    fs.writeFileSync(MAIL, '');
    st = await steps(); await btn('Change work email').click(); await p.waitForTimeout(200); await p.click('.tv-ask .yes'); await p.waitForTimeout(300);
    ok(await p.isVisible('#pb-dr-form [name=email]'), 'B8: Yes opens the form for the new address');
    await p.fill('#pb-dr-form [name=email]', 'sam.r@example.com'); await p.click('#pb-dr-form .pb-btn.no, #pb-dr-form .pb-btn.go'); await p.waitForTimeout(800);
    r = await p.evaluate(() => (document.getElementById('pb-dr-msg') || {}).textContent || '');
    ok(/Use the work email, first\.last@trvgo\.com/.test(r) && sql('SELECT email FROM staff_accounts WHERE id = 2') === 'sam.rivera@trvgo.com', 'B8: an address outside our work domain is refused: "' + r + '"');
    await p.fill('#pb-dr-form [name=email]', 'samuel.rivera@trvgo.com'); await Promise.all([p.waitForNavigation(), p.click('#pb-dr-form .pb-btn.no, #pb-dr-form .pb-btn.go')]); await p.waitForTimeout(1500);
    const told = mails().find(m => m.to === 'sam.rivera@trvgo.com' && /work email was changed/.test(m.subject));
    ok(sql('SELECT email FROM staff_accounts WHERE id = 2') === 'samuel.rivera@trvgo.com' && told && /s•+@trvgo\.com|s•/.test(told.text), 'B8: the work email changed, and the old address told (masked new address)');
    ok(sql("SELECT COUNT(*) FROM staff_log WHERE staff_account_id = 2 AND event = 'email'") === '1', 'B8: the change is in the staff log');

    // Reset the mailbox password, with cPanel connected (the harness's stand-in)
    cpanel(true); await go(p, '/admin/dashboard/today'); await go(p, '/admin/dashboard/stream#case=' + encodeURIComponent(key)); await p.waitForTimeout(1200);
    st = await steps(); ok(st.find(x => x.b === 'Reset the mailbox password').dis === false, 'B8: with cPanel connected, Reset the mailbox password is on');
    fs.writeFileSync(MAIL, '');
    await btn('Reset the mailbox password').click(); await p.waitForTimeout(200); await p.click('.tv-ask .yes'); await p.waitForTimeout(1200);
    const asked = fs.readFileSync(CPANEL, 'utf8').trim().split('\n').filter(Boolean).map(JSON.parse).pop();
    r = await p.evaluate(() => { const v = document.querySelector('.pb-once-v'); return v && { val: v.value, head: document.querySelector('#pb-dr-form b').textContent }; });
    ok(asked && asked.email === 'samuel.rivera@trvgo.com' && r && r.val === asked.pw && /New password for samuel\.rivera@trvgo\.com/.test(r.head), 'B8: the mailbox\'s new password set in cPanel and shown once to the owner');
    ok(mails().some(m => m.to === 'samuel.rivera@trvgo.com' && /mailbox password was reset/.test(m.subject)), 'B8: the mailbox is told its password was reset');
    await Promise.all([p.waitForNavigation(), p.click('#pb-dr-form button:has-text("Done")')]); await p.waitForTimeout(1500);

    // Close it: needs words; the case leaves the open list; everything in the log
    await btn('Close it').click(); await p.waitForTimeout(200);
    ok(!(await p.$('.tv-ask')), 'B8: Close it asks nothing first (it changes no sign-in)');
    await p.fill('#pb-dr-form [name=why]', 'Called Sam on a number we had; gave the setup link and mailbox password.');
    await Promise.all([p.waitForNavigation(), p.click('#pb-dr-form .pb-btn.go')]); await p.waitForTimeout(1500);
    ok(sql('SELECT status FROM staff_mail_requests WHERE id = ' + req[0]) === 'done' && sql("SELECT COUNT(*) FROM pulse_marks WHERE item_key = '" + key + "' AND done_at IS NOT NULL") === '1', 'B8: Close it closes the request and the case');
    ok(sql("SELECT COUNT(*) FROM staff_log WHERE staff_account_id = 2 AND event IN ('mail_help','reset','email','mailbox')") === '7', 'B8: every step is in the staff log (asked; the setup link, the email and the mailbox each as done and as from the request; closed)');
    await go(p, '/admin/dashboard/stream'); r = await p.evaluate(k => !!document.querySelector('.ps-row[data-key="' + k + '"]'), key);
    ok(!r, 'B8: the closed case leaves the open list');
    ok(p.errs.length === 0, 'no script errors' + (p.errs.length ? ': ' + p.errs.join(' | ') : ''));
    await p.context().close(); cpanel(false);
  }
  await b.close();
  console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
