// Google's card says which account it signs in as (Jason, 2026-11-23: he opened its Billing link and had to guess the
// account; the card read "Setup capture: Sign in not recorded"), on the real Service register with its own form handler,
// at 1280 and 390:
//   not recorded: "Signs in as: not recorded" with Record it, which lands on the open setup capture's one-field form;
//   a wrong email is refused with why, nothing saved; Record saves it: "Signs in as: <email> · project <id>" on its top
//   line, Setup capture no longer says "Sign in not recorded"; its Console and Credentials links carry authuser and
//   project, Billing authuser only (a billing account's page, not a project's), each "opens as <email>";
//   Undo (asks first) puts back "not recorded"; a provider with no account hint (Tavily) names the account beside its link,
//   its address unchanged; nothing wider than the window.
// A finished step replaces its form, and a record is edited, never undone (Jason, 2026-11-23; CLAUDE.md #1315): after
//   Record, Setup capture is one line "Signs in as … · recorded by …, <time>" with a pencil to Edit (the field, reopened in
//   place), the value before it under History, no Undo anywhere; no email box left open; the notice only "Recorded."; the
//   portal links are icons whose titles name where they open and as which account ("action icons with tool tips").
// Run after the start schema: node google-signin.cjs <root>
const { chromium } = require('playwright'); const { spawn } = require('child_process'); const path = require('path');
const root = path.resolve(process.argv[2]);
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 400) : '')); } };
const port = 8960 + Math.floor(Math.random() * 30);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'board-panels-router.php')], { env: { ...process.env, ROOT: root, REGISTER: 'full' }, stdio: 'ignore' });
const card = (p, k) => p.evaluate(k => { const c = document.getElementById('mc-' + k); if (!c) return null; const s = c.querySelector('.mc-signin'); const st = c.querySelector('.mc-setup');
  const links = [...c.querySelectorAll('.mc-slot a[target=_blank]')].map(a => ({ text: a.textContent.trim(), href: a.getAttribute('href'), title: a.getAttribute('title') || '', label: a.getAttribute('aria-label') || '' }));
  return { signin: s ? (() => { const k = s.cloneNode(true); k.querySelectorAll('form').forEach(f => f.remove()); return k.textContent.replace(/\s+/g, ' ').trim(); })() : null, setup: st ? st.querySelector('summary').textContent.replace(/\s+/g, ' ').trim() : '', open: st ? st.open : null,
    form: !!(st && [...st.querySelectorAll('input[name=email]')].some(i => i.getBoundingClientRect().width > 0)), undo: [...c.querySelectorAll('button, summary, a')].some(b => b.textContent.trim() === 'Undo'), hist: st && st.querySelector('.mc-hist') ? st.querySelector('.mc-hist').textContent.replace(/\s+/g, ' ').trim() : '',
    line: st && st.querySelector('.mc-done > span') ? st.querySelector('.mc-done > span').textContent.replace(/\s+/g, ' ').trim() : '', change: !!(st && st.querySelector('.mc-done .mc-change > summary')), links }; }, k);
// the record put back to "not recorded" between the two widths (a record has no Undo: the site's own state store, on the bench)
const forget = () => require('child_process').spawnSync('php', ['-r', 'require $argv[1] . "/lib/Database.php"; require $argv[1] . "/lib/SearchIndex.php"; $p = Database::connection(); foreach (["google", "tavily"] as $s) SearchIndex::setState($p, "signin:" . $s, "");', root + '/app']);
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
      t(L + 'Record saves it: the notice says only "Recorded.", and the top line "Signs in as: ops@traversence.example · project traversence-prod"', /^ok: Google Cloud: Recorded\.$/.test(await flash(p)) && c.signin === 'Signs in as: ops@traversence.example · project traversence-prod', [await flash(p), c.signin]);
      t(L + '…its Edit beside it is an icon titled "Edit the sign-in email"', await p.getAttribute('#mc-google .mc-signin .mc-ic', 'title') === 'Edit the sign-in email');
      t(L + '…Setup capture no longer says "Sign in not recorded"; one line, "Signs in as … · recorded …", with Edit (a pencil)', !/Sign in not recorded/.test(c.setup) && /^Signs in as ops@traversence\.example, project traversence-prod · recorded/.test(c.line) && c.change, c);
      t(L + '…no Undo anywhere on the card: a record is edited, never undone', !c.undo, c);
      const boxes = await p.locator('#mc-google-setup input[name=email]').evaluateAll(a => a.length);
      const shown = await p.locator('#mc-google-setup input[name=email]').first().isVisible();
      t(L + '…no email box left open beside it (the form replaced by its line)', boxes === 1 && !shown, { boxes, shown });
      t(L + '…Setup capture\'s summary has one arrow (the fold\'s own) and no stray "Record"', /^Setup capture: (complete|Sign in not recorded.*)$/.test(c.setup) && !/▸/.test(c.setup), c.setup);
      const by = n => c.links.find(l => new RegExp(n).test(l.title) || new RegExp(n).test(l.href)) || {};
      const con = by('console.cloud.google.com/(\\?|$)'), bill = by('/billing'), cred = by('apis/credentials');
      const q = h => { try { return Object.fromEntries(new URL(h).searchParams); } catch (e) { return {}; } };
      t(L + 'its Console and Credentials links open in that account and project (authuser, project)', q(con.href).authuser === 'ops@traversence.example' && q(con.href).project === 'traversence-prod' && (!cred.href || (q(cred.href).authuser === 'ops@traversence.example' && q(cred.href).project === 'traversence-prod')), [con, cred]);
      t(L + '…Billing in that account, with no project (a billing account\'s page)', bill.href && q(bill.href).authuser === 'ops@traversence.example' && !('project' in q(bill.href)), bill);
      t(L + '…each is an icon whose title says where it opens and as which account ("Open Google Cloud billing as ops@traversence.example")', c.links.filter(l => /google\.com/.test(l.href)).every(l => / as ops@traversence\.example$/.test(l.title) && l.title === l.label && l.text === '') && c.links.some(l => /^Open Google Cloud billing as /.test(l.title)), c.links);
      // Edit: the field reopened in place, filled; saved with another email, the one before it under History
      t(L + '…and the setup fold closes: the top line is the record now', c.open === false, c.open);
      await p.click('#mc-google .mc-signin [data-mc-edit]'); await p.waitForTimeout(250);
      t(L + 'Edit (the top line\'s pencil) reopens the one-field form in place, filled with the email, focused', await p.evaluate(() => { const i = document.querySelector('#mc-google-setup .mc-change input[name=email]'); return !!i && i.getBoundingClientRect().width > 0 && i.value === 'ops@traversence.example' && document.activeElement === i; }));
      await p.fill('#mc-google-setup .mc-change input[name=email]', 'billing@traversence.example');
      await Promise.all([p.waitForNavigation().catch(() => {}), p.click('#mc-google-setup .mc-change form button')]); await p.waitForTimeout(600);
      c = await card(p, 'google');
      t(L + '…saved: the line has the new email, the one before it under History', /^Signs in as billing@traversence\.example/.test(c.line) && /^History ?Before: ops@traversence\.example, project traversence-prod$/.test(c.hist), [c.line, c.hist]);
      // a provider with no account hint
      const tv = await card(p, 'tavily');
      if (tv) {
        await p.goto(`http://127.0.0.1:${port}/admin/config/register#svc-tavily`); await p.waitForTimeout(700);
        await p.fill('#mc-tavily-setup input[name=email]', 'ops@traversence.example');
        await Promise.all([p.waitForNavigation().catch(() => {}), p.click('#mc-tavily-setup form button:has-text("Record")')]); await p.waitForTimeout(600);
        const c2 = await card(p, 'tavily');
        t(L + 'a provider with no account hint (Tavily): its address unchanged, its title names the account to sign in as', c2.links.length && c2.links.every(l => !/authuser/.test(l.href)) && /\(sign in as ops@traversence\.example\)$/.test(c2.links[0].title) && !(await p.$('#mc-tavily-setup input[name=project]')), c2.links);
      }
      const sw = await p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
      t(L + 'nothing wider than the window', sw);
      forget();
      await p.close();
    } catch (x) { t(w + 'px: the walk through finished', false, String(x.message).split('\n')[0]); } }
  } finally { srv.kill(); await b.close(); }
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
