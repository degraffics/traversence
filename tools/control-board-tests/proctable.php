<?php
// control-board-table (2026-11-19, design/table-standard.md T1; the session manager): the board's parts in the admin's one
// table: a group row per system, each part with its status (a word with its colour), stage, where it stands, last 24
// hours, speed and OK; Check, and ⋯ with words (a greyed item says why); a part opens its own page in a drawer (rule 6);
// the Show filter by light with counts; the search. Also the alarm link that landed nowhere, and blocks named by their
// reason (Jason, 2026-11-19).
// Run: mariadb < ../release-b-tests/start-schema.sql && php proctable.php <root>/app
declare(strict_types=1);
$app = $argv[1]; $root = dirname($app);
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';
require_once $app . '/lib/Database.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
require_once $app . '/lib/ControlAlarms.php';
$pdo->exec('DROP TABLE IF EXISTS control_alarms'); ControlAlarms::ensure($pdo);

// 1. the rows: a group per system, its parts under it
[$rows, $total, $counts] = tv_cb_table_rows($pdo, []);
$labels = array_map(fn ($r) => $r['_group']['label'] ?? '', $rows);
$t('a group row per system, in the board\'s order', $labels === array_values(ControlRegister::SYSTEMS), $labels);
$t('…the total counts groups (one row each for paging)', $total === count($rows));
$all = []; foreach ($rows as $g) foreach ($g['_group']['rows'] as $k) $all[$k['id']] = $k;
$t('every part is in its group', count($all) === count(ControlRegister::processes($pdo)) && $counts[''] === count($all), [count($all), $counts['']]);
$w1 = $all['wf:W1'] ?? null;
$t('a part: its name, its line under it, a status word with its tone, its stage', $w1 && $w1['name'] !== '' && $w1['sub'] !== '' && isset($w1['light']['tone'], $w1['light']['word']) && $w1['stage'] !== '', $w1);
$t('…opens its own page (its ref)', $w1 && $w1['ref'] === 'wf-W1');
$t('…empty history is null, so the table says "no history yet" once (never a column of "–")', $w1 && $w1['runs'] === null && $w1['speed'] === null && $w1['ok'] === null, $w1);
$t('…"Working." doesn\'t repeat the pill', !array_filter($all, fn ($k) => preg_match('/^working\.?$/i', trim((string) $k['where']))));
$t('…an action not open to it says why', $w1 && isset($w1['_off']['maintback']) && $w1['_off']['maintback'] === 'It isn\'t out for maintenance.' && isset($w1['_off']['resolve']));

// 2. the Show filter and the search
$some = array_key_first(array_filter($counts, fn ($n, $k) => $k !== '' && $n > 0, ARRAY_FILTER_USE_BOTH));
[$f] = tv_cb_table_rows($pdo, ['show' => $some]);
$only = []; foreach ($f as $g) foreach ($g['_group']['rows'] as $k) $only[] = $k['light']['word'];
$t('Show filters to one light (counts per light for its menu)', $only && count(array_unique($only)) === 1 && count($only) === $counts[$some], [$some, count($only), $counts[$some]]);
[$s] = tv_cb_table_rows($pdo, ['q' => 'listings']);
$names = []; foreach ($s as $g) foreach ($g['_group']['rows'] as $k) $names[] = $k['name'];
$t('the search finds a part by its words', in_array('W1 Listings', $names, true) && count($names) < count($all), $names);

// 3. an alarm on a part: where it stands, and its Check and start again
$id = ControlAlarms::raise($pdo, 'x:w1', 'wf:W1', 'trip', 'held', 'W1 is held', '', '', '', [], true, false);
[$r2] = tv_cb_table_rows($pdo, []);
$w1b = null; foreach ($r2 as $g) foreach ($g['_group']['rows'] as $k) if ($k['id'] === 'wf:W1') $w1b = $k;
$t('a tripped part: red, its alarm is where it stands, Check and start again open to it', $w1b && $w1b['light']['tone'] === 'bad' && str_starts_with($w1b['where'], 'Trip: W1 is held') && $w1b['trip_id'] === $id && !isset($w1b['_off']['resolve']), $w1b);
$t('…and Start it greyed, saying why', $w1b && str_contains((string) ($w1b['_off']['start'] ?? ''), 'Check and start again'));

// 4. the board draws the table; a part's page keeps its detail in place
$html = tv_admin_mod_control($pdo);
$t('the board draws the parts as the admin\'s one table (config/processes)', str_contains($html, 'id="cb-systems"') && str_contains($html, 'data-ak-table=') && str_contains($html, '&quot;tool&quot;:&quot;config\/processes&quot;') && !str_contains($html, 'class="ak-card tv-bleed cb-sys"'));
$t('…a part opens its own page in a drawer (?proc=)', str_contains($html, '\/admin\/config\/control?proc={ref}'));
$t('…Check visible, the rest in ⋯ with words', str_contains($html, '&quot;act&quot;:[&quot;check&quot;,&quot;Check&quot;]') && str_contains($html, 'Take out for maintenance'));
$t('…the site\'s needs and the trends by system kept, as panels of their own', str_contains($html, 'id="cb-site-needs"') && str_contains($html, 'id="cb-trends"') && str_contains($html, 'data-cb-trend="sys:engine"'));
$_GET['proc'] = 'wf-W1'; $own = tv_admin_mod_control($pdo); unset($_GET['proc']);
$t('a part\'s own page (from the drawer) keeps its row opened in place, with its detail', str_contains($own, 'id="cb-p-wf-W1"') && str_contains($own, '← The board'));
$tools = require $app . '/admin/tools.php';
$t('the tool is registered for executives, with its Show filter', isset($tools['config/processes']) && $tools['config/processes']['view']['min'] === 'executive' && $tools['config/processes']['count_by'] === 'show');

// 5. the board's script: the table's buttons act as the board's own, it reloads with the board, tabs hold it
$js = (string) file_get_contents($root . '/website/js/admin-control.js');
$t('the row buttons post the board\'s own actions (check, resolve, start1, stop, maintout, maintback)', str_contains($js, "box.addEventListener('tv-table:act'") && str_contains($js, "start: ['start1', r.id]") && str_contains($js, "maintback: ['maintback', String(r.maint_id || '')]"));
$t('…Its own page opens in a drawer', str_contains($js, "TvAsk.peek('/admin/config/control?proc='"));
$t('…the table reloads with the board\'s redraw; the tabs show it', str_contains($js, 'tabsApply(); tableReload();') && str_contains($js, "['#cb-systems', 'startup observe shutdown maintenance']"));

// 6. the component's two fixes (design session's T1): a table of only groups, and phone cards' actions
$kit = (string) file_get_contents($root . '/website/js/admin-kit.js');
$t('the table counts a group\'s members when it says a column has no data', str_contains($kit, 'r._group ? (r._group.rows || []).every(empty)'));
$css = (string) file_get_contents($app . '/admin/kit-css.php');
$t('a phone card\'s actions take the card\'s width (Check was pushed off it)', str_contains($css, '.ak-t td.akt-acts{justify-content:flex-end;padding-top:.35rem;width:auto}'));

// 7. Jason's two from the live board: the alarm link and the reason a site refused us
$t('a part\'s alarm links to its own row (never the whole list)', str_contains($own, 'title="Its alarm, with its fix"') && !str_contains($own, 'data-cb-go="cb-alarms">The alarm list'));
$t('…and a link to an alarm the filter hides switches the filter to show it', str_contains($js, "if (li && li.hidden && st) { aShow = st === 'open' ? 'open' : st; afilter();"));
require_once $app . '/lib/EngineCase.php';
$t('a block says its reason: no crawling allowed / firewall or bot check / ### failure', EngineCase::blockReason('robots.txt disallows us') === 'no crawling allowed (its robots.txt)'
    && EngineCase::blockReason('challenge on https://x/y') === 'firewall or bot check refused us' && EngineCase::blockReason('HTTP 403 on https://lawrenceks.gov/500') === '403 failure'
    && EngineCase::blockReason('auth on https://x/page/123') === '401 failure (it asks for a sign-in)');
$fr = (string) file_get_contents($app . '/lib/crawler/Frontier.php');
$t('…W0\'s held sites are titled by it', str_contains($fr, "\$s['host'] . ', ' . EngineCase::blockReason("));

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
