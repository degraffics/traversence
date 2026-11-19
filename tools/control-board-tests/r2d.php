<?php
// A check shown part by part (Jason, 2026-11-18: "Red x but green checks too?"): a workflow's check keeps each part's own
// state, "what holds it" is the part that's red (never one whose words say "no red line"), and the board lists the parts
// with their marks. Run: mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration>
//      && php r2d.php <root>/app
declare(strict_types=1);
$app = $argv[1];
if (is_file($app . '/admin/Access.php')) require_once $app . '/admin/Access.php';   // the test root's stand-in
else { final class Access { public static function can($p, $o = []) { return [true, '']; } public static function staff() { return ['name' => 'Jason']; } } }
require_once $app . '/lib/Database.php'; require $app . '/lib/Preflight.php';
$pdo = Database::connection();
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '') . "\n"; } };
Preflight::results($pdo);
$put = function ($id, $v) use ($pdo) { $pdo->prepare('REPLACE INTO preflight_results (id, v, at) VALUES (?, ?, NOW())')->execute([$id, json_encode($v)]); };
// W0's dry run red (a half-size batch), its need answered
$put('rw:batch', ['s' => 'bad', 'said' => 'The indexer sent 125 addresses in one batch; it was smaller than a full batch (250).', 'at' => time()]);
$put('nd:pages', ['s' => 'ok', 'said' => 'Answered by its first source. 1. W0 Indexer on Railway: works.', 'at' => time()]);
$r = Preflight::run($pdo, 'wf:W0', 'test');
$parts = $r['extra']['parts'] ?? [];
$by = []; foreach ($parts as [$txt, $st]) $by[strtok($txt, ':')] = $st;
$t('W0 red: its dry run is red', $r['s'] === 'bad' && ($by['Dry run'] ?? '') === 'bad', [$r['s'], $by]);
$t('…its gate, with "no red line", is green, not what holds it', ($by['Its gate'] ?? '') !== 'bad' && !str_starts_with((string) $r['said'], 'What holds it: Its gate'), [$by, mb_substr((string) $r['said'], 0, 120)]);
$t('…what holds it names the dry run', str_starts_with((string) $r['said'], 'What holds it: Dry run'), mb_substr((string) $r['said'], 0, 120));
$t('…its needs part is green', ($by['Needs'] ?? '') === 'ok', $by);
$t('…"Now:" carries no mark of its own', array_key_exists('Now', $by) && $by['Now'] === null, $by);
// the board: Holds it first, every part with its mark
require $app . '/admin/widgets.php'; require $app . '/ui/app-shell.php'; $_SESSION = ['csrf_token' => 'x'];
require $app . '/admin/preflight-console.php'; require $app . '/admin/control-console.php';
$_GET['proc'] = 'wf-W0';   // the row's detail is on its own page since the parts table (2026-11-19: the table standard, rule 6)
$html = tv_admin_mod_control($pdo); unset($_GET['proc']);
$i = strpos($html, 'id="cb-p-wf-W0"'); $seg = substr($html, $i, (int) strpos($html, '</details>', $i) - $i);
$t('the board lists W0\'s check part by part', substr_count($seg, '<li>') >= 3 && str_contains($seg, 'class="cb-parts"'), mb_substr(strip_tags($seg), 0, 300));
$ul = substr($seg, (int) strpos($seg, 'class="cb-parts"'));
$t('…its line says what holds it: the dry run, not the gate', (bool) preg_match('#Holds it:</b> Dry run#', $seg) && !preg_match('#Holds it:</b> Its gate#', $seg), mb_substr(strip_tags($seg), 0, 300));
$t('…its parts on a toggle, the red one first', str_contains($seg, 'class="cb-more"') && strpos($ul, 'Dry run') < strpos($ul, 'Its gate'), mb_substr(strip_tags($ul), 0, 300));
$t('…its row: the Holding stage in amber (held, not tripped: 3d), and the hold in a few words (its test batch is its own alarm, one per issue)', str_contains($seg, 'cb-pill-hold">Holding') && str_contains($seg, 'Holds it: Dry run'), mb_substr(strip_tags($seg), 0, 300));
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
