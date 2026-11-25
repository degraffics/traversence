// Piece 2's tickets on the Work queue (P2-1; handoffs/piece2-team.md §4.1, §4.2, §8), rendered on the bench at 1280 and 390
// through board-router.php (the board as board-panels-router.php draws it, with Piece 2's actions as api.php answers them):
//   - each row's type and stage, the stage saying what it needs; a Tier 3 waiting for the Founder first, in amber words;
//   - the Team icon beside the filter, opening System Configuration → Team beside the board; the filter's types and stages;
//   - Add a task with its type; a Work ticket's page: the stage strip, the gate in words, Move greyed with what it needs,
//     "No decision applies", Move to Building, ✎ back a stage with a reason; a Resolution's Decision Brief (four parts, the
//     options, the recommendation once), Choose B: Decided, its precedent ticket named; one filled button in the queue;
//     the panel's × closes it; nothing wider than the window; no page error.
// Run: node tickets.cjs <root> [shots dir]   (it resets lv_main at each width)
const { chromium } = require('playwright'); const { spawn, execFileSync } = require('child_process'); const path = require('path');
const { view } = require('../control-board-tests/board-view-go.cjs');
const root = path.resolve(process.argv[2]); const shots = process.argv[3] || '';
let pass = 0, fail = 0; const t = (n, ok, got) => { if (ok) { pass++; console.log('  ok  ' + n); } else { fail++; console.log('FAIL  ' + n + (got !== undefined ? ' — ' + JSON.stringify(got).slice(0, 300) : '')); } };
const T = __dirname, CB = path.join(__dirname, '../control-board-tests');
const seed = () => execFileSync('bash', ['-c', 'mariadb -utv -ptv -h127.0.0.1 -e "DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main CHARACTER SET utf8mb4" && mariadb -utv -ptv -h127.0.0.1 lv_main < '
  + T + '/../release-b-tests/start-schema.sql && php ' + CB + '/board-panels-seed.php ' + root + '/app >/dev/null 2>&1; php ' + CB + '/work-queue-seed.php ' + root + '/app >/dev/null && php ' + T + '/tickets-seed.php ' + root + '/app']).toString().trim().split(' ');
const port = 8975 + Math.floor(Math.random() * 20);
const srv = spawn('php', ['-S', '127.0.0.1:' + port, path.join(T, 'board-router.php')], { env: { ...process.env, ROOT: root, FOUNDER: '1' }, stdio: 'ignore' });   // the Founder signed in
const srv2 = spawn('php', ['-S', '127.0.0.1:' + (port + 40), path.join(T, 'board-router.php')], { env: { ...process.env, ROOT: root }, stdio: 'ignore' });   // a staff member
const home = 'http://127.0.0.1:' + port + '/admin/config/control';
const W = '#cb-wq', P = '#cb-sp-wq';
const noSide = p => p.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
const openTask = async (p, id) => { await p.evaluate(() => document.querySelectorAll('#cb-wq .wq-grp > details').forEach(d => { d.open = true; }));
  await p.click(W + ' #wq-' + id + ' .wq-go'); await p.waitForSelector(P + ' .wqp[data-wqp-id="' + id + '"]', { timeout: 8000 }); await p.waitForTimeout(300); };
const press = async (p, sel) => { await Promise.all([p.waitForResponse(r => r.url().includes('/admin/config/control') && r.request().method() === 'GET' && r.url().includes('wqtask'), { timeout: 10000 }), p.click(sel)]); await p.waitForTimeout(400); };
(async () => {
  await new Promise(r => setTimeout(r, 700));
  const b = await chromium.launch();
  try {
    for (const w of [1280, 390]) {
      const L = w + 'px: ';
      const [res, work] = seed(); t(L + 'the seed: a Tier 3 waiting and a Work ticket at Planning', +res > 0 && +work > 0);
      const ctx = await b.newContext({ viewport: { width: w, height: 900 } }); const p = await ctx.newPage();
      const errs = []; p.on('pageerror', e => errs.push(e.message));
      await p.goto(home); await p.waitForTimeout(900); await view(p, 'development'); await p.waitForTimeout(400);
      // the queue
      const first = await p.$eval(W + ' .wq-main > li', li => ({ tk: li.getAttribute('data-wq-tk'), st: li.getAttribute('data-wq-st'), txt: li.querySelector('.wq-h').textContent }));
      t(L + 'the Tier 3 first: Resolution, "Waiting for you"', first.tk === 'resolution' && first.st === 'waiting' && /Resolution/.test(first.txt) && /Waiting for you/.test(first.txt), first);
      t(L + 'waiting in amber words (the hold colour), never red', await p.$eval(W + ' #wq-' + res + ' .wq-pill', x => getComputedStyle(x).color) === await p.$eval(W + ' .wq-p-late', x => getComputedStyle(x).color));
      t(L + 'the summary counts it: "1 waiting for you"', /1 waiting for you/.test(await p.textContent(W + ' .wq-r1')));
      const wr = await p.$eval(W + ' #wq-' + work, li => li.querySelector('.wq-h').textContent);
      t(L + 'a Work row says its stage and what it needs: "Planning · needs a decision attached…"', /Work/.test(wr) && /Planning · needs a decision attached/.test(wr), wr);
      const team = await p.$eval(W + ' .wq-team', a => ({ href: a.getAttribute('href'), peek: a.hasAttribute('data-peek-frame'), title: a.title, svg: !!a.querySelector('svg') }));
      t(L + 'the Team icon beside the filter: a brand icon with its title, opening the Team beside the board', team.href === '/admin/config/team' && team.peek && /Open the Team/.test(team.title) && team.svg, team);
      t(L + 'one filled button in the queue: Add a task', (await p.$$eval(W + ' .cbg-b:not(.cbg-o)', a => a.filter(x => x.getClientRects().length).map(x => x.textContent.trim()))).join('|') === 'Add a task');
      await p.click(W + ' [data-wq-fb]'); const items = await p.$$eval(W + ' [data-wq-f]', a => a.map(x => x.textContent.replace(/\s+/g, ' ').trim()));
      t(L + 'the filter, one button: Waiting for you, the three types and Work\'s four stages, each with its count', items.includes('Waiting for you 1') && items.includes('Resolution tickets 1') && items.some(x => /^Work at Planning \d+$/.test(x)) && items.some(x => /^Audit tickets 0$/.test(x)), items);
      await p.click(W + ' [data-wq-f="tk:resolution"]'); await p.waitForTimeout(200);
      t(L + 'filtered to Resolution tickets: one row shown', await p.$$eval(W + ' .wq-main .wq-it', a => a.filter(x => x.getClientRects().length).length) === 1);
      await p.click(W + ' [data-wq-fb]'); await p.click(W + ' [data-wq-f="all"]'); await p.waitForTimeout(200);
      // Add a task with its type
      await p.click(W + ' [data-wq=add]'); await p.selectOption(W + ' [data-wq-form] select[name=gauge]', { index: 1 });
      t(L + 'Add a task: Type, Work by default, each with what it is', await p.$eval(W + ' [data-wq-form] select[name=ticket]', s => s.value === 'work' && s.options.length === 3 && /Tier 3 decision/.test(s.options[2].textContent)));
      await p.selectOption(W + ' [data-wq-form] select[name=ticket]', 'audit');
      await p.fill(W + ' [data-wq-form] input[name=what]', 'A test audit finding'); await p.fill(W + ' [data-wq-form] textarea[name=why]', 'rendered test');
      await p.click(W + ' [data-wq-form] [type=submit]'); await p.waitForTimeout(1300);
      t(L + 'added as an Audit ticket at Found', await p.$$eval(W + ' .wq-it[data-wq-tk="audit"]', a => a.some(x => x.getAttribute('data-wq-stage') === 'found' && /A test audit finding/.test(x.textContent))));
      // a Work ticket's page: the strip, the gate, No decision applies, Move to Building, back with a reason
      await openTask(p, work);
      t(L + 'its stage strip: Planning current, of four', await p.$eval(P + ' .wqp-strip', o => o.children.length === 4 && o.querySelector('[aria-current=step]').textContent === 'Planning'));
      t(L + 'the gate in words: "To move to Building: a decision attached (or "No decision applies")"', /To move to Building:\s*a decision attached/.test(await p.textContent(P + ' .wqp-gate')), await p.textContent(P + ' .wqp-gate'));
      t(L + 'Move to Building greyed, its title saying what it needs', await p.$eval(P + ' [data-wqp=move]', x => x.disabled && /It needs a decision attached/.test(x.title)));
      if (shots) await p.screenshot({ path: shots + '/ticket-work-' + w + '.png' });
      await press(p, P + ' [data-wqp=noadr]');
      t(L + 'No decision applies: said in its decisions, the gate passes', /No decision applies/.test(await p.textContent(P + ' .wqp-adrs')) && /Ready to move to Building/.test(await p.textContent(P + ' .wqp-gate')));
      await press(p, P + ' [data-wqp=move]');
      t(L + 'Move to Building: the strip says Building', await p.$eval(P + ' .wqp-strip [aria-current=step]', x => x.textContent) === 'Building');
      t(L + 'Building\'s gate: its release named; ✎ to go back', /To move to Implementation:\s*its release named/.test(await p.textContent(P + ' .wqp-gate')) && await p.isVisible(P + ' [data-wqp=back]'));
      await p.click(P + ' [data-wqp=back]'); await p.waitForSelector(P + ' form.wqp-xf');
      await p.fill(P + ' form.wqp-xf textarea[name=why]', 'scope changed');
      await press(p, P + ' form.wqp-xf [type=submit]');
      t(L + '✎ back with a reason: Planning again', await p.$eval(P + ' .wqp-strip [aria-current=step]', x => x.textContent) === 'Planning');
      t(L + 'the queue row follows: Planning', await p.$eval(W + ' #wq-' + work, x => x.getAttribute('data-wq-stage')) === 'planning');
      t(L + 'no sideways scroll with the task open', await noSide(p));
      await p.click(P + ' .cb-spx'); await p.waitForTimeout(400); t(L + '× closes the task', await p.$eval(P, x => x.hidden || !x.getClientRects().length));
      // the Resolution: the brief, Choose B
      await openTask(p, res);
      const br = await p.textContent(P + ' .wqp');
      t(L + 'the Decision Brief: the decision, why it needs you, the boundary, both options, the impact, the cost', ['The Decision', 'Why it needs you', 'Privacy', 'Option A', 'Option B', 'Impact if delayed', 'Cost', 'Gemini stays public-only'].every(x => br.includes(x)));
      t(L + 'the recommendation marked once, on its option, and first among the answers', (br.match(/Recommended/g) || []).length === 2 && await p.$eval(P + ' .wqp-rec', x => /Option B/.test(x.textContent)) && await p.$eval(P + ' [data-wqp=ans]', x => x.textContent === 'Choose B (recommended)'));
      t(L + 'inside an option\'s box: "The option", "Pros", "Cons", never "Option A" twice', !/Option A\s*The option[\s\S]*Option A/.test(await p.$eval(P + ' .wqp-opt', x => x.textContent)));
      t(L + 'the answers are outlined buttons on the page, not a pop-up', await p.$$eval(P + ' .wqp-ans .cbg-b', a => a.length === 3 && a.every(x => x.classList.contains('cbg-o'))));
      if (shots) await p.screenshot({ path: shots + '/ticket-brief-' + w + '.png' });
      { const p2 = await ctx.newPage(); await p2.goto('http://127.0.0.1:' + (port + 40) + '/admin/config/control'); await p2.waitForTimeout(900); await view(p2, 'development'); await p2.waitForTimeout(300); await openTask(p2, res);
        t(L + 'signed in as a staff member, the answers are greyed with why: only the Founder answers a Tier 3', await p2.$$eval(P + ' .wqp-ans .cbg-b', a => a.length === 3 && a.every(x => x.disabled && /Only the Founder/.test(x.title)))); await p2.close(); }
      await press(p, P + ' [data-wqp=ans][data-a=b]');
      const dec = await p.textContent(P + ' [data-tv-status=ok]');
      t(L + 'Choose B: Decided, with who, and the precedent ticket that records it', /Decided:\s*Option B · Jason \(test\)/.test(dec) && /Task #\d+ records it as precedent/.test(dec), dec);
      t(L + 'the strip says Decided', await p.$eval(P + ' .wqp-strip [aria-current=step]', x => x.textContent) === 'Decided');
      t(L + 'the precedent ticket is in the queue, priority 1', await p.$$eval(W + ' .wq-it', a => a.some(x => /Record the precedent of task #/.test(x.textContent) && /P1/.test(x.textContent))));
      t(L + 'nothing wider than the window', await noSide(p));
      t(L + 'no page error', errs.length === 0, errs);
      await ctx.close();
    }
  } catch (e) { fail++; console.log('FAIL  ' + e.message.split('\n')[0]); }
  await b.close(); srv.kill(); srv2.kill();
  console.log('\n' + pass + ' passed, ' + fail + ' failed'); process.exit(fail ? 1 : 0);
})();
