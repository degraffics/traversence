// Google's card says which account it signs in as (Jason, 2026-11-23: he opened its Billing link and had to guess the
// account; the card read "Setup capture: Sign in not recorded"), on the real Service register with its own form handler,
// at 1280 and 390:
//   not recorded: "Signs in as: not recorded" with Record it, which lands on the open setup capture's one-field form;
//   a wrong email is refused with why, nothing saved; Record saves it: "Signs in as: <email> · project <id>" on its top
//   line, Setup capture no longer says "Sign in not recorded"; its Console and Credentials links carry authuser and
//   project, Billing authuser only (a billing account's page, not a project's), each "opens as <email>";
//   Undo (asks first) puts back "not recorded"; a provider with no account hint (Tavily) names the account beside its link,
//   its address unchanged; nothing wider than the window.
// A finished step replaces its form (Jason, 2026-11-23): after Record, Setup capture is one line "Signs in as … · recorded
//   by …, <time>" with Change (the form, reopened in place) and Undo beside it; no email box left open; the notice says only
//   "Recorded." (it never repeats the line, nor promises an Undo that isn't on screen); the top line links to them.
// Run after the start schema: node google-signin.cjs <root>
const { chromium } = require('playwright'); const { spawn } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]);
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const port = 8960 + Math.floor(Math.random() * 30);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root, REGISTER: 'full' }, stdio: 'ignore' });
const card = (p, k) => p.evaluate(k => { const c = document.getElementById('mc-' + k); if (!c) return null; const s = c.querySelector('.mc-signin'); const st = c.querySelector('.mc-setup');
  const links = [...c.querySelectorAll('.mc-slot a[target=_blank]')].map(a => ({ text: a.textContent.trim(), href: a.getAttribute('href'), as: (() => { const m = a.parentElement.querySelector('.mc-as'); return m ? m.firstChild.textContent.trim() + ' ' + m.querySelector('.mc-asv').textContent : ''; })() }));
  return { signin: s ? (() => { const k = s.cloneNode(true); k.querySelectorAll('form').forEach(f => f.remove()); return k.textContent.replace(/\s+/g, ' ').trim(); })() : null, setup: st ? st.querySelector('summary').textContent.replace(/\s+/g, ' ').trim() : '', open: st ? st.open : null,
    form: !!(st && [...st.querySelectorAll('input[name=email]')].some(i => i.getBoundingClientRect().width > 0)), undo: !!(st && [...st.querySelectorAll('.mc-done button')].some(b => b.textContent.trim() === 'Undo')),
    line: st && st.querySelector('.mc-done > span') ? st.querySelector('.mc-done > span').textContent.replace(/\s+/g, ' ').trim() : '', change: !!(st && st.querySelector('.mc-done .mc-change > summary')), links }; }, k);
const flash = p => p.evaluate(() => [...document.querySelectorAll('[data-tv-flash]')].map(x => x.getAttribute('data-tv-flash') + ': ' + x.textContent.trim()).join(' | '));
(async () => {
  await new Promise(r => setTimeout(r, 900));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) { try {
      const p = await b.newPage({ viewport: { width: w, height: 900 } }); const L = w + 'px: ';
      const go = async () => { await p.goto(`http://127.0.0.1:${port}/admin/config/register#svc-google`); await p.waitForTimeout(900); };
      await go();
      let c = await card(p, 'google');
      t(L + 'not recorded: the top line says so, with Record it', c && /^Signs in as: not recorded Record it$/.test(c.signin), c && c.signin);
      t(L + '…its setup capture is open on its one-field form (never a dead end)', c && c.open && c.form && /Sign in not recorded/.test(c.setup), c);
      await p.click('#mc-google .mc-signin a'); await p.waitForTimeout(300);
      t(L + '…Record it lands on the form', /#mc-google-setup$/.test(p.url()) && await p.evaluate(() => { const r = document.querySelector('#mc-google-setup input[name=email]').getBoundingClientRect(); return r.top >= 0 && r.top < innerHeight; }), p.url());
      await p.fill('#mc-google-setup input[name=email]', 'not-an-email'); await p.evaluate(() => { const f = document.querySelector('#mc-google-setup input[name=email]'); f.type = 'text'; });
      await Promise.all([p.waitForNavigation().catch(() => {}), p.click('#mc-google-setup form button:has-text("Record")')]); await p.waitForTimeout(600);
      c = await card(p, 'google');
      t(L + 'a wrong email is refused with why; nothing saved', /^error: .*isn't an email address/.test(await flash(p)) && /not recorded/.test(c.signin), [await flash(p), c.signin]);
      await go();
      await p.fill('#mc-google-setup input[name=email]', 'Ops@Traversence.example'); await p.fill('#mc-google-setup input[name=project]', 'traversence-prod');
      await Promise.all([p.waitForNavigation().catch(() => {}), p.click('#mc-google-setup form button:has-text("Record")')]); await p.waitForTimeout(600);
      c = await card(p, 'google');
      t(L + 'Record saves it: the notice says only "Recorded.", and the top line "Signs in as: ops@traversence.example · project traversence-prod"', /^ok: Google Cloud: Recorded\.$/.test(await flash(p)) && c.signin === 'Signs in as: ops@traversence.example · project traversence-prod Change or Undo', [await flash(p), c.signin]);
      t(L + '…Setup capture no longer says "Sign in not recorded"; it shows one line, "Signs in as … · recorded …", with Change and Undo', !/Sign in not recorded/.test(c.setup) && /^Signs in as ops@traversence\.example, project traversence-prod · recorded/.test(c.line) && c.change && c.undo, c);
      const boxes = await p.locator('#mc-google-setup input[name=email]').evaluateAll(a => a.length);
      const shown = await p.locator('#mc-google-setup input[name=email]').first().isVisible();
      t(L + '…no email box left open beside it (the form replaced by its line)', boxes === 1 && !shown, { boxes, shown });
      await p.click('#mc-google-setup .mc-change > summary'); await p.waitForTimeout(200);
      t(L + '…Change reopens the one-field form in place, filled with the email', await p.evaluate(() => { const i = document.querySelector('#mc-google-setup .mc-change input[name=email]'); return !!i && i.getBoundingClientRect().width > 0 && i.value === 'ops@traversence.example'; }));
      await p.click('#mc-google-setup .mc-change > summary'); await p.waitForTimeout(150);
      const by = n => c.links.find(l => new RegExp(n).test(l.text) || new RegExp(n).test(l.href)) || {};
      const con = by('console.cloud.google.com/(\\?|$)'), bill = by('/billing'), cred = by('apis/credentials');
      const q = h => { try { return Object.fromEntries(new URL(h).searchParams); } catch (e) { return {}; } };
      t(L + 'its Console and Credentials links open in that account and project (authuser, project)', q(con.href).authuser === 'ops@traversence.example' && q(con.href).project === 'traversence-prod' && (!cred.href || (q(cred.href).authuser === 'ops@traversence.example' && q(cred.href).project === 'traversence-prod')), [con, cred]);
      t(L + '…Billing in that account, with no project (a billing account\'s page)', bill.href && q(bill.href).authuser === 'ops@traversence.example' && !('project' in q(bill.href)), bill);
      t(L + '…each says "opens as ops@traversence.example"', c.links.filter(l => /google\.com/.test(l.href) && l.as).every(l => l.as === 'opens as ops@traversence.example') && c.links.some(l => l.as), c.links);
      t(L + '…and Undo is there, beside the line', c.undo, c);
      const ux = await p.evaluate(() => { const b = [...document.querySelectorAll('#mc-google-setup .mc-done button')].find(x => x.textContent.trim() === 'Undo'); return b ? b.getAttribute('data-confirm') : ''; });
      t(L + '…its question in plain words: "Forget the recorded sign-in (ops@traversence.example)?"', ux === 'Forget the recorded sign-in (ops@traversence.example)?', ux);
      const whole = await p.evaluate(() => [...document.querySelectorAll('#mc-google .mc-asv')].every(x => x.getClientRects().length === 1 && x.getAttribute('title') === x.textContent));
      t(L + '…each "opens as" keeps the address whole on its own line (an ellipsis, its title the full address)', whole);
      const sum = await p.evaluate(() => document.querySelector('#mc-google-setup > summary').textContent.replace(/\s+/g, ' ').trim());
      t(L + '…Setup capture\'s summary has no stray "Record"', /^Setup capture: (complete|Sign in not recorded.*) ▸$/.test(sum) && !/Record ▸/.test(sum), sum);
      p.once('dialog', d => d.accept());   // never a browser dialog: TvAsk's own yes
      await p.click('#mc-google-setup .mc-done button:has-text("Undo")'); await p.waitForTimeout(400);
      const yes = p.locator('.tv-ask button:has-text("Undo"), [role=dialog] button:has-text("Undo")').last();
      if (await yes.count()) await Promise.all([p.waitForNavigation().catch(() => {}), yes.click()]); await p.waitForTimeout(700);
      c = await card(p, 'google');
      t(L + 'Undo (asked first) puts back "not recorded"; the notice says only "Undone."', /^ok: Google Cloud: Undone\.$/.test(await flash(p)) && /not recorded/.test(c.signin) && c.form, [await flash(p), c.signin]);
      t(L + '…its links are as they were', c.links.every(l => !/authuser=/.test(l.href)), c.links);
      // a provider with no account hint
      const tv = await card(p, 'tavily');
      if (tv) {
        await p.goto(`http://127.0.0.1:${port}/admin/config/register#svc-tavily`); await p.waitForTimeout(700);
        await p.fill('#mc-tavily-setup input[name=email]', 'ops@traversence.example');
        await Promise.all([p.waitForNavigation().catch(() => {}), p.click('#mc-tavily-setup form button:has-text("Record")')]); await p.waitForTimeout(600);
        const c2 = await card(p, 'tavily');
        t(L + 'a provider with no account hint (Tavily): its address unchanged, the account named beside it', c2.links.length && c2.links.every(l => !/authuser/.test(l.href)) && c2.links[0].as === 'sign in as ops@traversence.example' && !(await p.$('#mc-tavily-setup input[name=project]')), c2.links);
        await p.click('#mc-tavily-setup .mc-done button:has-text("Undo")'); await p.waitForTimeout(300);
        const y2 = p.locator('.tv-ask button:has-text("Undo"), [role=dialog] button:has-text("Undo")').last(); if (await y2.count()) await Promise.all([p.waitForNavigation().catch(() => {}), y2.click()]); await p.waitForTimeout(600);
      }
      const sw = await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
      t(L + 'nothing wider than the window', sw);
      await p.close();
    } catch (x) { t(w + 'px: the walk through finished', false, String(x.message).split('\n')[0]); } }
  } finally { srv.kill(); await b.close(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
