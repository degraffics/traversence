// tools/page-audit/paths-staging.mjs — the runners for the paths that change something (decisions/0145 Phase 3), run on
// staging only, as the test accounts, never on live. Written 2026-11-13 from the live mirror's code (the selectors and
// endpoints each names are in the comments). Each runner counts the taps a visitor makes; what it sets up through the
// site's own endpoints (a post to report, a held post to decide) and what it cleans up after the goal aren't counted.
// Everything a runner makes is named "TEST · path check", and taken down, withdrawn or left when there's no undo (a
// message, a sandbox order copy). Staging's refresh replaces it all weekly anyway.
//
// Staging's email is held, never sent (Staging::holdMail → staging_mail) and shown on System Configuration → Staging
// ("Email held here"): heldMail() reads it there as the test Executive, for sign-up's link and claim's code.

const STAMP = new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '');
const TAG = `TEST · path check ${STAMP}`;

export function stagingRunners(k) {
  const { as, ACC, firstVisible, textOf, guides, FULL } = k;

  /** A page signed in as another account on staging (for setting up and checking the other side). */
  async function pageAs(who) {
    const a = await as(who, 'staging');
    if (a.error) throw new Error(`${who}: ${a.error}`);
    return a.ctx.newPage();
  }
  /** One call to the site's endpoint, with the session's security token (Response::requireCsrf). */
  async function api(page, site, url, data, method = 'POST') {
    const s = await page.request.get(site + '/api/auth/session.php').then(r => r.json()).catch(() => ({}));
    const r = await page.request.fetch(site + url, { method, headers: { 'X-CSRF-Token': s.csrf_token || '', 'Content-Type': 'application/json' }, data: method === 'POST' ? data : undefined });
    const j = await r.json().catch(() => ({}));
    return { status: r.status(), ...j };
  }
  /** The newest held email to `to` whose subject matches, read on System Configuration → Staging; waits up to 40 s. */
  async function heldMail(site, to, subject) {
    const p = await pageAs('staff-executive');
    try {
      for (let i = 0; i < 8; i++) {
        await p.goto(site + '/admin/config/staging', { waitUntil: 'load', timeout: 45000 }).catch(() => {});
        const cards = await p.$$eval('details.ak-card', ds => ds.map(d => ({ head: (d.querySelector('summary') || d).textContent || '', body: (d.querySelector('pre') || d).textContent || '' }))).catch(() => []);
        const hit = cards.find(c => c.head.toLowerCase().includes(to.toLowerCase()) && subject.test(c.head));
        if (hit) return hit;
        await p.waitForTimeout(5000);
      }
      return null;
    } finally { await p.close().catch(() => {}); }
  }
  /** A tap inside the Pulse tool's frame (its changes don't reach the page's own watch): counted, then read by its text. */
  async function tapIn(w, frame, sel, label) {
    const el = frame.locator(sel).first();
    if (!(await el.isVisible().catch(() => false))) { w.stuck = w.stuck || `couldn't find "${label}" in the tool on ${w.here()}`; await w.shot('stuck'); return false; }
    w.taps++; await el.click({ timeout: 8000 }).catch(() => {}); await w.page.waitForTimeout(2500); return true;
  }
  /** The site's notice after an action (tv-ask.js note()): "Success: …" or "Error: …", '' when none showed. */
  const notice = async (page) => ((await page.locator('.tv-note .tv-note-t').last().innerText().catch(() => '')) || '').replace(/\s+/g, ' ').trim();
  const email = who => ACC?.members?.[who]?.email || '';

  /** A Marketplace post through the site's own form (/user/market/?new=1, user/market/index.php): kind service (no
   *  photo needed), Free. Returns its id. As a runner's steps (w) or as setup (no w: on a plain page). */
  async function marketPost(page, site, { about, w = null }) {
    const tap = async (sel, label, o) => w ? w.tap(typeof sel === 'string' ? page.locator(sel).first() : sel, label, o) : (typeof sel === 'string' ? page.locator(sel).first() : sel).click().then(() => true).catch(() => false);
    if (w) {
      await w.goto('/market/', 'the Marketplace');
      if (!(await tap('a.mk-go', 'Post something', { nav: true }))) return 0;
    } else await page.goto(site + '/user/market/?new=1', { waitUntil: 'load', timeout: 45000 });
    await tap('#um-post input[name=kind][value=service]', 'Service');
    await page.fill('#um-post [name=title]', TAG);
    await tap('#um-post input[name=price_mode][value=free]', 'Free');
    const cat = await page.$$eval('#um-post select[name=category] option', os => (os.find(o => o.value) || {}).value || '').catch(() => '');
    if (cat) await page.selectOption('#um-post select[name=category]', cat).catch(() => {});
    await page.fill('#um-post textarea[name=about]', about);
    await page.fill('#um-place', '').catch(() => {}); await page.type('#um-place', 'Show Low', { delay: 60 }).catch(() => {}); await page.waitForTimeout(2000);
    if (!(await tap('#um-place-list button[data-i]', 'Show Low'))) { if (w) w.add('dead_end', 'J01', w.here(), 'typing "Show Low" in the post\'s place offered no town'); return 0; }
    const age = page.locator('#um-post [name=over_18]').first();
    if (await age.isVisible().catch(() => false)) await tap(age, '18 or older');
    if (!(await tap('#um-send', 'Post it', { nav: true, wait: 8000 }))) return 0;
    await page.waitForURL(/#p\d+/, { timeout: 20000 }).catch(() => {});
    return +((page.url().match(/#p(\d+)/) || [])[1] || 0);
  }
  const takeDown = (page, site, id) => id ? api(page, site, '/api/market.php', { action: 'act', id, what: 'remove' }).catch(() => null) : null;

  return {
    // ---- Sign up (all steps): login.php?tab=create → the held email's link (verify-email.php) → the questions → sign in
    'sign-up': async (w, page) => {
      const addr = `check+su${STAMP}${Math.floor(Math.random() * 900 + 100)}@traversence.com`;
      const pw = `Tv!${Math.random().toString(36).slice(2, 10)}A7`;
      await w.goto('/', 'the home page');
      let up = await firstVisible(page, ['a.tvpm-up', 'a.tvn-up']);
      if (!up) { const menu = await firstVisible(page, ['#icon-menu-btn']); if (menu) await w.tap(menu, 'the menu'); up = await firstVisible(page, ['a.tvpm-up', 'a.tvn-up', 'a[href="/login.php?tab=create"]']); }
      if (!up) { w.stuck = 'no Create account in the menu'; await w.shot('noup'); return; }
      await w.tap(up, 'Create account', { nav: true });
      if (!(await w.type('#r_email', addr)) || !(await w.type('#r_password', pw)) || !(await w.type('#r_password2', pw))) return;
      await w.tap('#over_18', 'I\'m 18 or older');
      await w.tap('#go-up', 'Create account', { wait: 20000 });
      if (!(await page.locator('#au-sent').isVisible().catch(() => false))) { const said = await notice(page); w.add('dead_end', 'J01', w.here(), `Create account didn't say "Check your email"${said ? ': ' + said : ''}`); await w.shot('notsent'); return; }
      const promised = ((await page.locator('#au-sent').innerText().catch(() => '')).match(/(\w+) quick questions?/i) || [])[1] || '';
      const mail = await heldMail(w.site, addr, /Verify your Traversence account/i);
      if (!mail) { w.add('dead_end', 'J01', w.here(), 'no confirmation email arrived (staging\'s held mail)'); return; }
      const link = (mail.body.match(/https?:\/\/[^\s"'<>]+\/verify-email\.php\?token=[A-Za-z0-9%]+/) || [])[0];
      if (!link) { w.add('dead_end', 'J01', w.here(), 'the confirmation email has no link to follow'); return; }
      w.taps++;   // the link in the email
      await w.goto(w.site + link.replace(/^https?:\/\/[^/]+/, ''), 'the email\'s link');
      await page.waitForSelector('button.vs-s[data-skip]:visible, #s0 [data-tv-status="bad"]', { timeout: 20000 }).catch(() => {});   // the link is checked first
      let asked = 0;
      for (let i = 0; i < 6; i++) {   // the questions after the link: each skipped, as a visitor in a hurry would
        const skip = await firstVisible(page, ['button.vs-s[data-skip]']);
        if (!skip) break;
        asked++; await w.tap(skip, 'Skip'); await page.waitForTimeout(600);
      }
      const words = { one: 1, two: 2, three: 3, four: 4, five: 5 };
      if (promised && words[promised.toLowerCase()] && asked > words[promised.toLowerCase()]) w.add('clarity', 'J03', w.here(), `the sign-up said "${promised} quick questions"; the page asked ${asked}`);
      const go = await firstVisible(page, ['a#s5-go']);
      if (!go) { w.add('dead_end', 'J01', w.here(), 'after the questions, no way on ("You\'re all set" and its link didn\'t show)'); await w.shot('noend'); return; }
      await w.tap(go, (await go.innerText().catch(() => 'Go on')).trim(), { nav: true });
      if (/\/login\.php/.test(w.here())) {
        w.note('confirming the email doesn\'t sign you in: the last step is signing in');
        if ((await page.locator('#email').inputValue().catch(() => '')) === '') await w.type('#email', addr);
        await w.type('#password', pw);
        await w.tap('#go-in', 'Sign in', { wait: 20000 });
        await page.waitForURL(u => !/\/login\.php/.test(String(u)), { timeout: 30000 }).catch(() => {});
      }
      w.goal = /\/user\/dashboard\.php/.test(w.here());
      if (!w.goal) { w.add('dead_end', 'J01', w.here(), 'the new account didn\'t end on its dashboard'); await w.shot('nodash'); return; }
      // clean up: the account is deleted at once (user/delete-account.php, signed in within 15 minutes)
      const del = await api(page, w.site, '/user/api/delete-account.php', { confirm: 'DELETE', now: true });
      if (!del.ok) w.note(`the test account ${addr} couldn't be deleted after (${del.error?.message || del.status}); staging's next refresh removes it`);
    },

    // ---- Like as a member past 100 a day: 100 likes through the endpoint, the 101st tapped on a page → Keep liking
    'like-member': async (w, page) => {
      if (!FULL) { w.skip = 'runs in the full run only (Mondays, or Run workflow with "full"): 100 likes at a person\'s pace take about 7 minutes'; return; }
      const sj = (guides()[0]?.journeys || [])[0];
      if (!sj) { w.stuck = 'no suggested journey to tap the 101st like on'; return; }
      const site = w.site; const made = [];
      // public recreation places (kind rec, by Recreation.gov's id): their ids are taken by shape, so test ids touch
      // nothing real (since P11 a landmark, journey or guide must exist to be liked); each is unliked after
      let n = 0;
      for (let i = 0; n < 100 && i < 130; i++) {
        const ref = 'pathcheck-' + i;
        const r = await api(page, site, '/api/engage.php', { action: 'like', kind: 'rec', ref });
        if (r.ok && r.liked) { made.push(ref); n++; }
        else if (r.status === 429) break;   // today's 100 reached already
        else if (r.status === 428) { w.note(`the human check stopped the likes at ${n} (the pace checks, LikeGuard)`); break; }
        await page.waitForTimeout(3600 + Math.random() * 2400);   // under 20 a minute, at uneven gaps, as a person likes
      }
      w.note(`${n} likes made through the endpoint before the 101st`);
      await w.goto(sj.url, 'a suggested journey');
      const like = await firstVisible(page, ['[data-eg-like][aria-pressed="false"]', '[data-eg-like]']);
      if (!like) { w.stuck = 'no Like on the journey'; return; }
      await w.tap(like, 'Like (the 101st)');
      const more = await firstVisible(page, ['.tv-ask button.tv-ask-b.yes']);
      if (!more) { w.add('dead_end', 'J01', w.here(), 'at today\'s 100 likes, no "Keep liking" was offered'); await w.shot('nomore'); }
      else { await w.tap(more, 'Keep liking', { wait: 15000 }); await page.waitForTimeout(4000); w.goal = (await like.getAttribute('aria-pressed')) === 'true'; if (!w.goal) w.add('dead_end', 'J01', w.here(), 'after Keep liking, the 101st like didn\'t take'); }
      // clean up: every like unliked (they don't count for a test account either way)
      if (w.goal) await like.click().catch(() => {});
      for (const ref of made) await api(page, site, '/api/engage.php', { action: 'like', kind: 'rec', ref }).catch(() => {});
    },

    // ---- Claim my business: a listing → Claim → the emailed code (held mail) → where the claim stands → withdrawn after
    'claim': async (w, page) => {
      const tries = guides().flatMap(g => (g.journeys || []).flatMap(j => (j.stops || []).map(s => s.url))).filter(u => /\/listing\/view\.php\?id=\d+/.test(u || '')).slice(0, 8);
      let found = false;
      for (const u of tries) {
        await w.goto(u, 'a listing');
        const c = await firstVisible(page, ['a.xh-a.go[href^="/claim.php"]', 'a[href^="/claim.php?listing="]']);
        if (c) { await w.tap(c, 'Claim', { nav: true }); found = true; break; }
      }
      if (!found) { w.stuck = tries.length ? 'none of the guides\' listings tried could be claimed (all claimed?)' : 'no listing to try (the guides gave none)'; return; }
      w.taps = 1; w.pages = w.pages.slice(-2);   // the steps count from the listing that could be claimed
      const role = page.locator('#c-role'); if (await role.count()) { const v = await role.locator('option').evaluateAll(os => (os.find(o => o.value) || {}).value || ''); if (v) await role.selectOption(v).catch(() => {}); }
      for (const sel of ['#c-me', '#c-phone']) if (await page.locator(sel).isVisible().catch(() => false)) await page.fill(sel, '9285550100').catch(() => {});
      const age = page.locator('#cl-start input[name=age]').first(); if (await age.isVisible().catch(() => false)) await w.tap(age, '18 or older');
      await w.tap('#cl-start button[type=submit]', 'Email me a code', { wait: 10000 });
      const mail = await heldMail(w.site, email('member'), /code to claim/i);
      const code = mail ? ((mail.head + ' ' + mail.body).match(/\b(\d{4,8})\b is your Traversence code|code to claim .*? is:?\s*(\d{4,8})/i) || []).slice(1).find(Boolean) : '';
      if (!code) { w.add('dead_end', 'J01', w.here(), mail ? 'the claim email has no code in it' : 'no claim code arrived (staging\'s held mail)'); await w.shot('nocode'); return; }
      if (!(await w.type('#c-code', code))) return;
      await w.tap('#cl-confirm', 'Confirm', { wait: 10000 });
      const mine = await firstVisible(page, ['#cl-mine .cl-pill', '#cl-mine']);
      w.goal = !!mine;
      if (!w.goal) { w.add('dead_end', 'J01', w.here(), `the code was confirmed but the page doesn't say where the claim stands${(await notice(page)) ? ': ' + await notice(page) : ''}`); await w.shot('nostand'); return; }
      w.note(`the claim stands at: ${((await mine.innerText().catch(() => '')) || '').replace(/\s+/g, ' ').slice(0, 80)}`);
      const wd = await firstVisible(page, ['button[data-withdraw]']);
      if (wd) { await wd.click().catch(() => {}); const y = await firstVisible(page, ['.tv-ask button.tv-ask-b.yes']); if (y) await y.click().catch(() => {}); await page.waitForTimeout(2500); }
      else w.note('no Withdraw on the claim: the test claim stays until staging\'s refresh');
    },

    // ---- Edit my listing: the portal → Edit listing → the hours → Save → See it as visitors do; the hours put back after
    'edit-listing': async (w, page) => {
      await w.goto('/business/', 'your business');
      const ed = await firstVisible(page, ['a.ak-b:has-text("Edit listing")', 'a[href^="/listing/businessportal.php?id="]']);
      if (!ed) { w.stuck = 'no Edit listing in the business portal'; await w.shot('noedit'); return; }
      await w.tap(ed, 'Edit listing', { nav: true });
      let box = page.locator('#bp-edit #f-hours');
      if (!(await box.isVisible().catch(() => false)) && ACC?.listing?.id) {   // the portal's Edit listing opened another business, or a list: the test listing's own editor
        const mine = page.locator(`a[href*="businessportal.php?id=${ACC.listing.id}"]`).first();
        if (await mine.isVisible().catch(() => false)) await w.tap(mine, 'the test listing', { nav: true });
        else { w.note(`Edit listing opened ${w.here()}, not the test listing's editor`); await w.goto('/listing/businessportal.php?id=' + ACC.listing.id, 'the test listing\'s editor'); }
        box = page.locator('#bp-edit #f-hours');
      }
      if (!(await box.isVisible().catch(() => false))) { w.stuck = `no hours box on Edit listing (${w.here()})`; await w.shot('nohours'); return; }
      const old = await box.inputValue().catch(() => '');
      const hours = `Mon–Fri 9am–5pm (${TAG})`;
      await w.type('#bp-edit #f-hours', hours);
      await w.tap('#bp-edit button.bp-b.go[type=submit]', 'Save', { wait: 8000 });
      const said = ((await page.locator('#bp-edit p.bp-msg').innerText().catch(() => '')) || '').trim();
      if (!/saved/i.test(said)) { w.add('dead_end', 'J01', w.here(), `Save didn't say saved${said ? ': "' + said.slice(0, 80) + '"' : ''}`); await w.shot('nosave'); return; }
      const see = await firstVisible(page, ['#bp-edit a[href^="/listing/view.php"]', 'a:has-text("See it as visitors do")']);
      if (see) {
        await w.tap(see, 'See it as visitors do', { nav: true });
        const t = await textOf(page);
        if (t.includes(STAMP)) w.goal = true;
        else if (/not found|isn't here/i.test(t) && ACC?.listing?.id && w.here().includes('id=' + ACC.listing.id)) { w.goal = true; w.note('the test listing is hidden from the public, so its page says not found even to its owner; the hours were saved (expected for the test listing only)'); }
        else w.add('accuracy', 'J02', w.here(), 'the saved hours don\'t show on the listing\'s page');
      } else { w.goal = true; w.add('clarity', 'J03', w.here(), 'no "See it as visitors do" after saving'); }
      // put the hours back
      await page.goto(w.site + '/listing/businessportal.php?id=' + (ACC?.listing?.id || ''), { waitUntil: 'load' }).catch(() => {});
      if (await page.locator('#bp-edit #f-hours').isVisible().catch(() => false)) { await page.fill('#bp-edit #f-hours', old); await page.click('#bp-edit button.bp-b.go[type=submit]').catch(() => {}); await page.waitForTimeout(2500); }
    },

    // ---- Post on the Marketplace: Market → Post something → the form → Post it → your posts show it; taken down after
    'market-post': async (w, page) => {
      const id = await marketPost(page, w.site, { about: `${TAG}: a test service, taken down at once.`, w });
      if (!id) { if (!w.stuck && !w.problems.length) w.add('dead_end', 'J01', w.here(), `Post it didn't open the post in your list${(await notice(page)) ? ': ' + await notice(page) : ''}`); await w.shot('nopost'); return; }
      const st = ((await page.locator(`#p${id} .um-st`).innerText().catch(() => '')) || '').trim();
      w.goal = !!st; w.note(`the post's status: ${st || '(none shown)'}`);
      if (!st) w.add('clarity', 'J03', w.here(), 'the new post shows no status (Showing, or Waiting for a look)');
      await takeDown(page, w.site, id);
    },

    // ---- Message a business: the business's conversation → the message → Send → it's in the owner's inbox
    'message-business': async (w, page) => {
      const biz = ACC?.listing?.id;
      if (!biz) { w.stuck = 'no test listing in CHECK_ACCOUNTS'; return; }
      // the test listing is hidden from the public (its page says not found), so the path starts at its Message link's target
      await w.goto(`/user/dashboard.php#addressbook:biz=${biz}`, 'the business\'s conversation');
      w.note('starts at the conversation: the test listing\'s own page is hidden from members, so its Message button can\'t be tapped');
      await page.waitForTimeout(2500);
      const text = `${TAG}: is this open on Sunday?`;
      if (!(await w.type('form.ab-send textarea.ab-in', text))) return;
      await w.tap('form.ab-send button.ab-b.go', 'Send', { wait: 8000 });
      const mine = page.locator('div.ab-msg.me', { hasText: STAMP }).first();
      if (!(await mine.isVisible().catch(() => false))) { w.add('dead_end', 'J01', w.here(), `the message didn't show as sent${(await notice(page)) ? ': ' + await notice(page) : ''}`); await w.shot('nosent'); return; }
      // the owner's side: the business portal's inbox (business-inbox.js)
      const op = await pageAs('owner');
      try {
        await op.goto(`${w.site}/business/${biz}#messages`, { waitUntil: 'load', timeout: 45000 }); await op.waitForTimeout(3000);
        const th = op.locator('#messages button.ab-thread').first();
        if (await th.isVisible().catch(() => false)) { await th.click().catch(() => {}); await op.waitForTimeout(2500); }
        w.goal = (await op.locator('#messages').innerText().catch(() => '')).includes(STAMP);
        if (!w.goal) w.add('accuracy', 'J02', '/business/' + biz + '#messages', 'the message was sent but isn\'t in the owner\'s inbox');
      } finally { await op.close().catch(() => {}); }
      w.note('messages can\'t be deleted (BizMessages has no remove): the test message stays until staging\'s refresh');
    },

    // ---- Join a group: Community → a group → Join (and Agree, with a charter) → Joined; left after
    'join-group': async (w, page) => {
      await w.goto('/community/', 'Community');
      await page.waitForSelector('a.cg-g', { timeout: 15000 }).catch(() => {});   // drawn by the page's script after it loads
      const cards = await page.$$eval('a.cg-g[href*="/community/group.php?id="]', as => as.map(a => a.getAttribute('href'))).catch(() => []);
      if (!cards.length) { w.stuck = `no groups listed on Community: "${(await textOf(page)).slice(0, 140)}"`; await w.shot('nogroups'); return; }
      let joined = false;
      for (const href of cards.slice(0, 5)) {
        const card = page.locator(`a.cg-g[href="${href}"]`).first();
        if (await card.isVisible().catch(() => false)) await w.tap(card, 'a group', { nav: true }); else await w.goto(href, 'a group');
        const join = await firstVisible(page, ['button.cg-join[data-cg-join]']);
        if (!join) { await w.goto('/community/', 'Community'); w.taps = 0; continue; }   // already in it, or it runs it: the next group
        await w.tap(join, (await join.innerText().catch(() => 'Join')).trim(), { wait: 6000 });
        const agree = await firstVisible(page, ['button.cg-b.go[data-a="join"]']);
        if (agree) await w.tap(agree, (await agree.innerText().catch(() => 'Agree and join')).trim(), { nav: true, wait: 8000 });
        await page.waitForTimeout(2500);
        const on = ((await page.locator('span.cg-join.on').first().innerText().catch(() => '')) || '').trim();
        joined = /joined|asked/i.test(on);
        if (!joined) { w.add('dead_end', 'J01', w.here(), `Join didn't show Joined${(await notice(page)) ? ': ' + await notice(page) : ''}`); await w.shot('nojoin'); }
        break;
      }
      w.goal = joined;
      if (!joined && !w.problems.length) { w.stuck = w.stuck || 'every group tried was joined already or had no Join'; return; }
      const leave = await firstVisible(page, ['button.cg-b[data-a="leave"]']);
      if (leave) { await leave.click().catch(() => {}); for (let i = 0; i < 2; i++) { const y = await firstVisible(page, ['.tv-ask button.tv-ask-b.yes']); if (!y) break; await y.click().catch(() => {}); await page.waitForTimeout(1500); } }
      else w.note('no Leave group after joining: the test membership stays until staging\'s refresh');
    },

    // ---- Staff: decide a report: a member reports a test post → Pulse → the report's case → the Reports tool → Dismiss
    'staff-report': async (w, page) => {
      const cp = await pageAs('contributor'); const mp = await pageAs('member');
      let id = 0;
      try {
        id = await marketPost(cp, w.site, { about: `${TAG}: a test post to report.` });
        if (!id) { w.stuck = 'couldn\'t make the test post to report (as the contributor)'; return; }
        const r = await api(mp, w.site, '/api/engage.php', { action: 'report', what: 'market_post', id, reason: 'other', note: TAG });
        if (!r.ok) { w.stuck = `the member's report didn't go in: ${r.error?.message || r.status}`; return; }
        await w.goto('/admin/dashboard/stream', 'Pulse');
        const row = page.locator('.ps-row[data-key^="report:"]', { hasText: /market/i }).first();
        if (!(await row.isVisible().catch(() => false))) { w.add('dead_end', 'J01', w.here(), 'the member\'s report has no case in Pulse'); await w.shot('nocase'); return; }
        await w.tap(row, 'the report\'s case');
        const tool = await firstVisible(page, ['#pb-dr-play button[data-tool-url*="reports"]', '#pb-dr-tool']);
        if (!(await page.locator('#pb-tool-f').isVisible().catch(() => false))) { if (!tool) { w.stuck = 'the case has no way to the Reports tool'; await w.shot('notool'); return; } await w.tap(tool, 'Open the report'); }
        await page.waitForTimeout(3000);
        const f = page.frameLocator('#pb-tool-f');
        const card = f.locator('div.rp', { hasText: STAMP }).first();
        if (!(await card.isVisible().catch(() => false))) { w.add('accuracy', 'J02', w.here(), 'the Reports tool doesn\'t show the reported post'); await w.shot('noreport'); return; }
        if (!(await tapIn(w, card, 'button[value=dismiss]', 'Dismiss'))) return;
        const said = ((await f.locator('body').innerText().catch(() => '')) || '');
        w.goal = /\bDone\./.test(said);
        if (!w.goal) w.add('dead_end', 'J01', w.here(), 'Dismiss didn\'t say done');
        if (w.goal && !/data-tv-flash|tv-note/.test(await f.locator('body').innerHTML().catch(() => ''))) w.add('surprise', 'R03', '/admin/reports.php', 'the decision\'s answer is plain colored text, not the site\'s notice (data-tv-flash)');
        await page.goto(w.site + '/admin/dashboard/stream', { waitUntil: 'load' }).catch(() => {});
        if (await page.locator('.ps-row[data-key^="report:"]', { hasText: /market/i }).first().isVisible().catch(() => false)) w.note('a report case for a Marketplace post is still listed after the decision (it may be another report)');
      } finally { await takeDown(cp, w.site, id); await cp.close().catch(() => {}); await mp.close().catch(() => {}); }
    },

    // ---- Staff: decide a like check: Pulse → Like checks → Decide (Looks fine) → the step goes. The case comes from the
    // hourly Like checks job, so a run with none seeds one (three guests liking one test item) for the next run.
    'staff-like-check': async (w, page) => {
      await w.goto('/admin/dashboard/stream#case=likes%3Achecks', 'Pulse: Like checks');
      await page.waitForTimeout(2000);
      const step = await firstVisible(page, ['#pb-dr-play button[data-form-step]']);
      if (!step) {
        for (let i = 0; i < 3; i++) {   // seed: three guests, each a new visit, like one test item within the hour (LikeGuard "group")
          const a = await as('guest-seed-' + i, 'staging');
          if (a.error) break;
          const gp = await a.ctx.newPage();
          await api(gp, w.site, '/api/engage.php', { action: 'like', kind: 'rec', ref: 'pathcheck-seed' }).catch(() => {});
          await gp.close().catch(() => {});
        }
        w.skip = 'no like check waiting: three guest likes on a test item were made, so the hourly Like checks job opens one for the next run';
        return;
      }
      const n = await page.locator('#pb-dr-play button[data-form-step]').count();
      await w.tap(step, 'Decide');
      if (!(await page.locator('#pb-dr-form select[name=what]').isVisible().catch(() => false))) { w.add('dead_end', 'J01', w.here(), 'Decide opened no choice'); await w.shot('noform'); return; }
      await page.selectOption('#pb-dr-form select[name=what]', 'clear').catch(() => {});
      await w.tap('#pb-dr-form button.pb-btn.go', 'Decide', { nav: true, wait: 8000 });
      await page.waitForTimeout(2500);
      const left = await page.locator('#pb-dr-play button[data-form-step]').count();
      w.goal = left < n;
      if (!w.goal) w.add('dead_end', 'J01', w.here(), 'after Decide, the like check is still waiting');
      if (w.goal && !(await notice(page))) w.add('clarity', 'J03', w.here(), 'Decide shows no answer: a form step\'s message is lost on the reload (pulse-ui.php done())');
    },

    // ---- Staff: decide a held Marketplace post: a test post that mentions "deposit" is held → Pulse → Let it show
    'staff-held-post': async (w, page) => {
      const cp = await pageAs('contributor');
      let id = 0;
      try {
        id = await marketPost(cp, w.site, { about: `${TAG}: a test service; a deposit holds the date.` });
        if (!id) { w.stuck = 'couldn\'t make the held test post (as the contributor)'; return; }
        await w.goto('/admin/dashboard/stream#case=mkt%3Aheld', 'Pulse: held Marketplace posts');
        await page.waitForTimeout(2000);
        const ok = await firstVisible(page, [`#pb-dr-play button[data-do-what="mkt_ok:${id}"]`]);
        if (!ok) { w.add('dead_end', 'J01', w.here(), 'the held post has no Let it show in Pulse'); await w.shot('nobutton'); return; }
        await w.tap(ok, 'Let it show', { nav: true, wait: 8000 });
        await page.waitForTimeout(2000);
        const said = await notice(page);
        w.goal = /success/i.test(said);
        if (!w.goal) w.add('dead_end', 'J01', w.here(), `Let it show didn't say it worked${said ? ': ' + said : ''}`);
      } finally { await takeDown(cp, w.site, id); await cp.close().catch(() => {}); }
    },

    // ---- Staff: send an order: Back-Office → a sandbox order → Test copy, billed to you → Send → the held email
    'send-order': async (w, page) => {
      await w.goto('/admin/backoffice/orders', 'Back-Office: Orders');
      const sb = page.locator('details#sandbox').first();
      if (await sb.count() && !(await sb.getAttribute('open'))) await w.tap(sb.locator('summary').first(), 'Sandbox orders');
      const o = await firstVisible(page, ['details#sandbox a.hb-nm']);
      if (!o) { w.stuck = 'no sandbox order to copy on Orders'; await w.shot('noorder'); return; }
      await w.tap(o, 'a sandbox order', { nav: true });
      const ham = await firstVisible(page, ['details.cs-ham > summary']);   // the command strip's ☰ holds the page's Duplicate list (decisions/0129)
      if (ham) await w.tap(ham, '☰');
      await w.tap('details#co-dup > summary', 'Duplicate');
      const copy = await firstVisible(page, ['details#co-dup button.hb-b.go']);
      if (!copy) { w.stuck = 'no "Test copy, billed to you"'; return; }
      await w.tap(copy, 'Test copy, billed to you', { nav: true, wait: 10000 });
      const send = await firstVisible(page, ['#co-send-btn']);
      if (!send) { w.add('dead_end', 'J01', w.here(), 'the test copy has no Send'); await w.shot('nosend'); return; }
      await w.tap(send, 'Send to the client');
      const yes = await firstVisible(page, ['.tv-ask button.tv-ask-b.yes']);
      if (yes) await w.tap(yes, 'Send it', { nav: true, wait: 10000 });
      await page.waitForTimeout(2000);
      const said = (await notice(page)) || ((await page.locator('#co-flash').innerText().catch(() => '')) || '');
      w.goal = /sent/i.test(said);
      if (!w.goal) { w.add('dead_end', 'J01', w.here(), `Send didn't say sent${said ? ': ' + said.slice(0, 100) : ''}`); return; }
      const to = ACC?.staff?.['sal-staff']?.email || '';
      const mail = to ? await heldMail(w.site, to, /order from Traversence/i) : null;
      if (to && !mail) w.add('accuracy', 'J02', w.here(), 'the order was sent but no email to the client is in staging\'s held mail');
      w.note('the test copy (sandbox, "TEST · …") stays on staging until its refresh: orders aren\'t deleted');
    },
  };
}
