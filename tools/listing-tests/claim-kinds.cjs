// claim-kinds (Jason, 2026-11-19, approved): the short Claim button by who might claim it, and the claim form fitting them.
// Needs a copy of the site at BASE, signed in as a member who can claim, with: 7101 Lyman Lake State Park (State Parks under
// Government & Public Services, in the Nexus "Arizona State Parks & Trails" under "State of Arizona", azstateparks.com),
// 7103 Fool Hollow Lake (the same kind, no Nexus), 7102 First Baptist Church (Religious & Faith-Based), and 7004 a business.
// Step 2 and 3: the test opens a claim on 7101 for user 1 (status managed) in DB (default lv_main), then removes it.
// Run: BASE=http://127.0.0.1:8790 node claim-kinds.cjs <shots dir>
const { chromium } = require('playwright'); const { execSync } = require('child_process');
const DB = process.env.DB || 'lv_main', sql = q => execSync('mysql ' + DB, { input: q });   // the test seeds the one claim it needs, and removes it
const BASE = process.env.BASE || 'http://127.0.0.1:8790', out = process.argv[2] || '.';
let pass = 0, fail = 0; const ok = (c, m) => { if (c) pass++; else { fail++; console.log('FAIL ' + m); } };
(async () => {
  const b = await chromium.launch();
  sql("DELETE FROM listing_claims WHERE entity_id IN (7101, 7102, 7103, 7004)");
  for (const [w, h] of [[1280, 860], [390, 844]]) {
    const ctx = await b.newContext({ viewport: { width: w, height: h } }); const p = await ctx.newPage(); const errs = [];
    p.on('pageerror', e => errs.push(e.message)); await p.route(/^https?:\/\/(?!127\.0\.0\.1|localhost)/, r => r.abort());
    const btn = async id => { await p.goto(BASE + '/listing/view.php?id=' + id, { waitUntil: 'load' }); await p.waitForTimeout(300);
      return p.evaluate(() => { const a = document.querySelector('.xh a.xh-a.go[href^="/claim.php"]'); return a ? { t: a.textContent.trim(), tip: a.title, extra: !!a.parentNode.querySelector('small') } : null; }); };
    const park = await btn(7101), church = await btn(7102), biz = await btn(7004);
    ok(park && park.t === 'Staff? Claim it' && /agency that runs Lyman Lake/.test(park.tip), w + ': a state park: "Staff? Claim it" ' + JSON.stringify(park));
    ok(church && church.t === 'Run it? Claim it', w + ': a church: "Run it? Claim it"');
    ok(biz && biz.t === 'Claim it' && /Own or manage/.test(biz.tip), w + ': a business: "Claim it"');
    ok(park && !park.extra && !biz.extra, w + ': no second line under the button');
    if (w === 390) { await btn(7101); const r = await p.evaluate(() => { const a = document.querySelector('.xh a.xh-a.go'); const x = a.getBoundingClientRect(); return [x.right <= innerWidth, Math.round(x.width)]; });
      ok(r[0], w + ': the button fits ' + r); await p.screenshot({ path: out + '/claim-390-park.png', clip: { x: 0, y: 0, width: 390, height: 520 } }); }
    // the park's claim page: who runs it, roles, what's coming, no plans step words
    await p.goto(BASE + '/claim.php?listing=7101', { waitUntil: 'load' }); await p.waitForTimeout(300);
    const cp = await p.evaluate(() => ({ h1: document.querySelector('h1').textContent, runs: (document.querySelector('.cl-card h2 + p') || {}).textContent || '',
      roles: [...document.querySelectorAll('#c-role option')].map(o => o.textContent), agency: !!document.querySelector('#c-agency'),
      coming: /whole agency/.test(document.body.textContent), plan: [...document.querySelectorAll('.cl-steps li')].pop().textContent, sx: document.documentElement.scrollWidth > innerWidth }));
    ok(cp.h1 === 'Claim this place for your agency', w + ': the park\'s claim page title');
    ok(/Run by State of Arizona › Arizona State Parks & Trails/.test(cp.runs), w + ': who runs it, from its Nexus: ' + cp.runs);
    ok(cp.roles.join() === 'Site or park manager,Agency staff,Communications or public information,Other', w + ': an agency\'s roles');
    ok(cp.agency && cp.coming, w + ': a different agency can be named; the whole agency\'s claim is coming');
    ok(/Plans for agencies and nonprofits are coming/.test(cp.plan), w + ': no paid plans for an agency');
    ok(!cp.sx, w + ': no sideways scroll');
    await p.screenshot({ path: out + '/claim-' + w + '-park-page.png', fullPage: true });
    await p.goto(BASE + '/claim.php?listing=7103', { waitUntil: 'load' });
    ok(await p.evaluate(() => /don't know yet which agency/.test(document.body.textContent) && /Which agency runs it/.test(document.body.textContent)), w + ': no Nexus yet: "Which agency runs it?"');
    await p.goto(BASE + '/claim.php?listing=7102', { waitUntil: 'load' });
    const ch = await p.evaluate(() => ({ h1: document.querySelector('h1').textContent, roles: [...document.querySelectorAll('#c-role option')].map(o => o.textContent), agency: !!document.querySelector('#c-agency') }));
    ok(ch.h1 === 'Claim your organization' && ch.roles[0] === 'Director, pastor or leader' && !ch.agency, w + ': a church\'s page and roles');
    await p.goto(BASE + '/claim.php?listing=7004', { waitUntil: 'load' });
    ok(await p.evaluate(() => document.querySelector('h1').textContent === 'Claim your business' && document.querySelector('#c-role option').textContent === 'Owner' && /a paid plan/.test(document.body.textContent)), w + ': a business: unchanged');
    // after Step 1: the agency's own email first, Free with no plan list
    sql("INSERT INTO listing_claims (entity_id,user_id,status,role_note,text_code,listed_phone,claimant_phone,due_at) VALUES (7101,1,'managed','Site or park manager','TV-1234','9283374441','9285550100',NOW() + INTERVAL 2 DAY)");
    await p.goto(BASE + '/claim.php?listing=7101', { waitUntil: 'load' }); await p.waitForTimeout(400);
    const mine = await p.evaluate(() => ({ first: (document.querySelector('#cl-mine form') || {}).id, dom: (document.querySelector('#cl-dom .cl-s') || {}).textContent || '', radios: document.querySelectorAll('#cl-plan input[type=radio]').length, plan: (document.querySelector('#cl-plan') || {}).textContent || '' }));
    ok(mine.first === 'cl-dom' && /Fastest: an email at @azstateparks\.com/.test(mine.dom), w + ': Step 2 starts with the agency\'s own email: ' + mine.dom);
    ok(mine.radios === 0 && /Plans for agencies and nonprofits are coming/.test(mine.plan), w + ': Step 3: Free, no plan list');
    await p.screenshot({ path: out + '/claim-' + w + '-park-step2.png', fullPage: true });
    sql("DELETE FROM listing_claims WHERE entity_id = 7101");
    ok(!errs.length, w + ': no script errors: ' + errs.join('; ')); await ctx.close();
  }
  await b.close(); console.log(pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
