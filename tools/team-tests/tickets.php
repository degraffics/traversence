<?php
// Piece 2's tickets (P2-1; handoffs/piece2-team.md §3.1, §8; Jason's Q2 A and Q3 A, 2026-11-25) on a real MySQL or MariaDB:
// the schema migration and the stage seed run as the site runs them (DbUpdates::statements), twice, over the Work queue as it
// is live (before-schema.sql); today's open tasks given their stage; Add a task as Work, Audit or Resolution; each Work gate
// passing and failing with its words; one stage at a time, back with a reason; the moves the system makes by its own checks
// (release named → Implementation, release live → Confirmation, every step confirmed → Done; no steps → Done at once; a task
// with no stage done when its release is live, as #281); a Resolution's brief, Send to the Founder, the answer, its precedent
// ticket and the Resolution closing with it; the counts and the gauge with "waiting"; report() passing Team roles' names.
// Run: php tickets.php <root>/app   (it resets lv_main from before-schema.sql)
declare(strict_types=1);
$app = rtrim($argv[1] ?? '', '/'); if (!is_dir($app . '/lib')) { fwrite(STDERR, "usage: php tickets.php <root>/app\n"); exit(2); }
require_once $app . '/lib/Database.php'; require_once $app . '/lib/DbUpdates.php';
$pdo = Database::connection(); $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('DROP DATABASE IF EXISTS lv_main'); $pdo->exec('CREATE DATABASE lv_main CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $pdo->exec('USE lv_main');
foreach (DbUpdates::statements((string) file_get_contents(__DIR__ . '/before-schema.sql')) as $q) { if (trim($q) !== '') $pdo->exec($q); }
$pdo->exec("CREATE TABLE IF NOT EXISTS site_releases (id INT AUTO_INCREMENT PRIMARY KEY, zip_name VARCHAR(190) NOT NULL DEFAULT '', state VARCHAR(20) NOT NULL DEFAULT '', live_at DATETIME NULL)");
$pass = 0; $fail = 0;
$t = function (string $n, bool $ok, $got = null) use (&$pass, &$fail) { if ($ok) { $pass++; echo "  ok  $n\n"; } else { $fail++; echo "FAIL  $n" . ($got !== null ? ' — ' . json_encode($got) : '') . "\n"; } };
$q = fn (string $sql, array $a = []) => (function () use ($pdo, $sql, $a) { $s = $pdo->prepare($sql); $s->execute($a); return $s; })();
$ins = fn (string $what, string $extra = '', array $a = []) => (function () use ($pdo, $what, $extra, $a) { $pdo->prepare("INSERT INTO control_gauge_tasks (at, tz, gauge, item, kind, what, bucket, value, who, uid, why, source$extra) VALUES (UTC_TIMESTAMP(), '', 'area:test', ?, 'fix', ?, 'task', '', 'Jason', 1, 'why', 'src'" . str_repeat(', ?', count($a)) . ")")->execute(array_merge([$what, $what], $a)); return (int) $pdo->lastInsertId(); })();

// 0. today's queue, as it is live before Piece 2
$old = [$ins('old open'), $ins('old taken, scoped', ', owner, goal', ['Orders session', 'a goal']), $ins('old taken, no goal', ', owner', ['Orders session']), $ins('old fixing', ', owner, rel', ['Orders session', 'x.zip'])];
$ins('old done', ', cleared_at', [gmdate('Y-m-d H:i:s')]);
// 1. the two migrations, as the site runs them, twice
foreach ([1, 2] as $i) foreach (['2026-11-25_tickets.sql', '2026-11-25_tickets_stage.sql'] as $f) foreach (DbUpdates::statements((string) file_get_contents($app . '/migrations/' . $f)) as $s) { $st = $pdo->query($s); if ($st) $st->closeCursor(); }
$stages = $q('SELECT what, ticket, stage FROM control_gauge_tasks ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$t('the migrations run twice; every old task is a Work ticket', count(array_filter($stages, fn ($r) => $r['ticket'] === 'work')) === 5, $stages);
$t('today\'s stages: open → Planning, taken and scoped → Building, taken unscoped → Planning, a release named → Implementation, done → none',
    array_column($stages, 'stage') === ['planning', 'building', 'planning', 'implementation', null], array_column($stages, 'stage'));
$t('the stage seed makes no table (0162: it would be cleared unrun)', DbUpdates::checks((string) file_get_contents($app . '/migrations/2026-11-25_tickets_stage.sql')) === []);
$t('the schema file changes no data', !preg_match('/\bUPDATE\s+\w+\s+SET|DELETE\s+FROM|INSERT\s+INTO/i', (string) file_get_contents($app . '/migrations/2026-11-25_tickets.sql')));

require_once $app . '/lib/GaugeTasks.php'; require_once $app . '/lib/ControlLog.php';
$J = 'Jason (test)';
// 2. Add a task: Work, Audit, Resolution
$w = GaugeTasks::add($pdo, 'area:test', 'Build the thing', 'it fixes X', 'src', $J, 1, 'America/Phoenix');
$a = GaugeTasks::add($pdo, 'area:test', 'Late job found', 'the audit', 'audit', $J, 1, '', 'audit');
$r = GaugeTasks::add($pdo, 'area:test', 'May staff text go to Gemini\'s paid key?', 'privacy', '0164 §0.8', $J, 1, '', 'resolution');
$row = fn ($id) => GaugeTasks::task($pdo, $id);
$t('Add a task: Work starts at Planning, Audit at Found, Resolution at Brief', $row($w)['stage'] === 'planning' && $row($a)['stage'] === 'found' && $row($r)['stage'] === 'brief');
$t('a Resolution starts its brief with the decision in its words', GaugeTasks::brief($pdo, $r)['decision'] === 'May staff text go to Gemini\'s paid key?');
try { GaugeTasks::add($pdo, 'area:test', 'x', 'y', '', $J, 1, '', 'nonsense'); $t('an unknown type is refused', false); } catch (RuntimeException $x) { $t('an unknown type is refused', true); }

// 3. Planning's gate, and a move one stage at a time
$m = GaugeTasks::gate($pdo, $row($w));
$t('Planning\'s gate names all four: a goal, Done when, a decision, someone', count($m) === 4 && str_contains(implode(',', $m), 'a goal') && str_contains(implode(',', $m), 'No decision applies'), $m);
try { GaugeTasks::move($pdo, $w, 'building', $J); $t('Move to Building is refused while its gate fails, saying what it needs', false); } catch (RuntimeException $x) { $t('Move to Building is refused while its gate fails, saying what it needs', str_starts_with($x->getMessage(), 'It needs a goal'), $x->getMessage()); }
try { GaugeTasks::move($pdo, $w, 'implementation', $J); $t('two stages at once is refused', false); } catch (RuntimeException $x) { $t('two stages at once is refused', str_contains($x->getMessage(), 'one stage at a time')); }
GaugeTasks::edit($pdo, $w, ['goal' => 'Do it', 'done_when' => 'It works'], $J); GaugeTasks::assign($pdo, $w, $J, $J);
$t('"No decision applies" answers the decision part of the gate', str_starts_with(GaugeTasks::noDecision($pdo, $w, $J), 'Saved') && GaugeTasks::gate($pdo, $row($w)) === []);
$t('… and shows as a decision row, detached with ✎ like any other', array_column(GaugeTasks::adrs($pdo, $w), 'adr') === ['none'] && str_contains(GaugeTasks::adrKeep($pdo, $w, [], $J), 'none detached') && GaugeTasks::noDecision($pdo, $w, $J) !== '');
$t('Move to Building passes, and says so', GaugeTasks::move($pdo, $w, 'building', $J, '', 'America/Phoenix') === 'Moved to Building.');
$mv = GaugeTasks::moves($pdo, $w); $t('the move is kept: from, to, who, zone', end($mv)['from_stage'] === 'planning' && end($mv)['to_stage'] === 'building' && end($mv)['who'] === $J && end($mv)['tz'] === 'America/Phoenix');
$t('… and in its History', str_contains(json_encode(GaugeTasks::history($pdo, $w), JSON_UNESCAPED_UNICODE), 'moved: Planning → Building'));
try { GaugeTasks::move($pdo, $w, 'planning', $J, ''); $t('back without a reason is refused', false); } catch (RuntimeException $x) { $t('back without a reason is refused', str_contains($x->getMessage(), 'Say why')); }
$t('back a stage with a reason', str_starts_with(GaugeTasks::move($pdo, $w, 'planning', $J, 'scope changed'), 'Back to Planning') && $row($w)['stage'] === 'planning');
GaugeTasks::move($pdo, $w, 'building', $J);
try { GaugeTasks::move($pdo, $w, 'confirmation', $J); $t('a person can\'t move it to Confirmation', false); } catch (RuntimeException $x) { $t('a person can\'t move it to Confirmation', str_contains($x->getMessage(), 'one stage') || str_contains($x->getMessage(), 'by itself')); }

// 4. the system's own moves: release named → Implementation; live → Confirmation; steps confirmed → Done
GaugeTasks::release($pdo, $w, 'thing.zip', $J); GaugeTasks::queue($pdo);
$t('its release named: Building → Implementation by itself', $row($w)['stage'] === 'implementation');
$s1 = GaugeTasks::stepAdd($pdo, $w, 'see', 'check it', 'confirm', $J);
$pdo->exec("INSERT INTO site_releases (zip_name, state, live_at) VALUES ('thing.zip', 'live', UTC_TIMESTAMP())"); GaugeTasks::queue($pdo);
$t('its release live: Implementation → Confirmation, not Done while a step is open (Q2 A)', $row($w)['stage'] === 'confirmation' && $row($w)['cleared_at'] === null);
$qrow = array_values(array_filter(GaugeTasks::queue($pdo), fn ($x) => (int) $x['id'] === $w))[0];
$t('… its gate says it waits for its steps', GaugeTasks::gate($pdo, $qrow)[0] === 'every step is confirmed (it moves by itself)');
GaugeTasks::stepEdit($pdo, $s1, ['done' => 1], $J); GaugeTasks::queue($pdo);
$t('every step confirmed: Done by itself, saying why', $row($w)['cleared_at'] !== null && str_contains((string) $row($w)['done_how'], 'every step is confirmed'), $row($w)['done_how']);
// a Work ticket with no steps: Done at once once live (as before); an old one with no stage: done when live (#281)
$w2 = GaugeTasks::add($pdo, 'area:test', 'No steps', 'w', '', $J, 1); GaugeTasks::noDecision($pdo, $w2, $J); GaugeTasks::edit($pdo, $w2, ['goal' => 'g', 'done_when' => 'd'], $J); GaugeTasks::assign($pdo, $w2, $J, $J);
GaugeTasks::move($pdo, $w2, 'building', $J); GaugeTasks::release($pdo, $w2, 'thing.zip', $J); GaugeTasks::queue($pdo);
$t('no steps: Done at once once its release is live, through Implementation and Confirmation', $row($w2)['cleared_at'] !== null && count(GaugeTasks::moves($pdo, $w2)) === 3, count(GaugeTasks::moves($pdo, $w2)));
$pdo->exec("UPDATE control_gauge_tasks SET stage = NULL WHERE id = " . $old[0]); GaugeTasks::release($pdo, $old[0], 'thing.zip', $J); GaugeTasks::queue($pdo);
$t('a task with no stage is done when its release is live, as #281', $row($old[0])['cleared_at'] !== null && $row($old[0])['done_how'] === 'Done: thing.zip is live');
$t('the old fixing task moves on when its release goes live', (function () use ($pdo, $old, $row) { $pdo->exec("INSERT INTO site_releases (zip_name, state, live_at) VALUES ('x.zip', 'live', UTC_TIMESTAMP())"); GaugeTasks::queue($pdo); return $row($old[3])['cleared_at'] !== null; })());

// 5. Audit tickets don't move by a person's press here (P2-4's audit moves them)
try { GaugeTasks::move($pdo, $a, 'diagnosing', $J); $t('an Audit ticket isn\'t moved by hand', false); } catch (RuntimeException $x) { $t('an Audit ticket isn\'t moved by hand', str_contains($x->getMessage(), 'by itself')); }

// 6. a Resolution: the brief, Send, the answer, its precedent
$t('the brief\'s gate lists what\'s missing', count(GaugeTasks::gate($pdo, $row($r))) === 10, GaugeTasks::gate($pdo, $row($r)));
try { GaugeTasks::sendBrief($pdo, $r, $J); $t('Send is refused until the brief is whole', false); } catch (RuntimeException $x) { $t('Send is refused until the brief is whole', str_starts_with($x->getMessage(), 'It needs')); }
foreach (['why_you' => 'privacy', 'boundary' => 'privacy', 'a_text' => 'Paid key', 'a_pros' => 'more', 'a_cons' => 'risk', 'b_text' => 'Public only', 'b_pros' => 'safe', 'b_cons' => 'less', 'recommend' => 'b', 'delay_impact' => 'safe on hold', 'cost' => 'none'] as $k => $v) GaugeTasks::briefEdit($pdo, $r, $k, $v, $J);
try { GaugeTasks::briefEdit($pdo, $r, 'boundary', 'lunch', $J); $t('a boundary must be one of 0179\'s', false); } catch (RuntimeException $x) { $t('a boundary must be one of 0179\'s', true); }
$t('a brief edit keeps the old words in History', str_contains(json_encode(GaugeTasks::history($pdo, $r)), 'brief edited: recommended'));
try { GaugeTasks::answer($pdo, $r, 'a', '', $J); $t('no answer before it\'s sent', false); } catch (RuntimeException $x) { $t('no answer before it\'s sent', str_contains($x->getMessage(), 'send it first')); }
$t('Send to the Founder: Waiting for you', str_starts_with(GaugeTasks::sendBrief($pdo, $r, $J), 'Sent') && $row($r)['stage'] === 'waiting');
$st = array_values(array_filter(GaugeTasks::queue($pdo), fn ($x) => (int) $x['id'] === $r))[0]['state'];
$t('its state in the queue: waiting (shown "Waiting for you")', $st === 'waiting', $st);
try { GaugeTasks::briefEdit($pdo, $r, 'cost', 'x', $J); $t('the brief can\'t change while it\'s with the Founder', false); } catch (RuntimeException $x) { $t('the brief can\'t change while it\'s with the Founder', str_contains($x->getMessage(), 'back to Brief')); }
try { GaugeTasks::answer($pdo, $r, 'o', '', $J); $t('"In my words" needs words', false); } catch (RuntimeException $x) { $t('"In my words" needs words', true); }
$msg = GaugeTasks::answer($pdo, $r, 'b', '', $J, 'America/Phoenix');
$t('Choose B: Decided, and a precedent ticket opens', $row($r)['stage'] === 'decided' && preg_match('/Task #(\d+) records it/', $msg, $mm), $msg);
$p = (int) $mm[1]; $pr = $row($p);
$t('the precedent ticket: Work, Planning, priority 1, from the Resolution, naming 0179 rule 4', $pr['ticket'] === 'work' && $pr['stage'] === 'planning' && (int) $pr['priority'] === 1 && (int) $pr['parent_id'] === $r && str_contains($pr['why'], '0179 rule 4'));
$t('the answer is kept with who and zone', GaugeTasks::brief($pdo, $r)['answer'] === 'b' && GaugeTasks::brief($pdo, $r)['answered_by'] === $J && GaugeTasks::brief($pdo, $r)['answer_tz'] === 'America/Phoenix');
$t('a correction while the precedent isn\'t recorded: kept in History', str_starts_with(GaugeTasks::answer($pdo, $r, 'a', '', $J), 'Saved') && str_contains(json_encode(GaugeTasks::history($pdo, $r)), 'answer corrected: B'));
GaugeTasks::queue($pdo); $t('the Resolution stays open while its precedent isn\'t done', $row($r)['cleared_at'] === null);
GaugeTasks::withdraw($pdo, $p, 'test', $J); GaugeTasks::queue($pdo); $t('a withdrawn precedent doesn\'t close it', $row($r)['cleared_at'] === null);
$p2 = GaugeTasks::add($pdo, 'area:test', 'Record the precedent again', 'w', '', $J, 1, '', 'work', $r);
$pdo->exec("UPDATE control_gauge_tasks SET cleared_at = UTC_TIMESTAMP(), done_how = 'Done: note written' WHERE id = $p2"); GaugeTasks::queue($pdo);
$t('its precedent done: the Resolution closes by itself, naming it', $row($r)['cleared_at'] !== null && str_contains((string) $row($r)['done_how'], 'precedent is recorded (task #' . $p2 . ')'), $row($r)['done_how']);

// 7. counts, the gauge, report()
$r2 = GaugeTasks::add($pdo, 'area:test', 'Another Tier 3', 'w', '', $J, 1, '', 'resolution');
foreach (['why_you', 'boundary', 'a_text', 'a_pros', 'a_cons', 'b_text', 'b_pros', 'b_cons', 'recommend', 'delay_impact'] as $k) GaugeTasks::briefEdit($pdo, $r2, $k, $k === 'boundary' ? 'money' : ($k === 'recommend' ? 'a' : 'x'), $J);
GaugeTasks::sendBrief($pdo, $r2, $J);
try { GaugeTasks::move($pdo, $r2, 'decided', $J); $t('only the Founder\'s answer decides it, never a move', false); } catch (RuntimeException $x) { $t('only the Founder\'s answer decides it, never a move', str_contains($x->getMessage(), 'answer decides')); }
$c = GaugeTasks::counts(GaugeTasks::queue($pdo)); $t('the counts say one waiting', $c['waiting'] === 1, $c);
if (is_file($app . '/lib/ControlGauges.php')) { require_once $app . '/lib/ControlGauges.php'; try { $g = ControlGauges::queueGauge($pdo); $t('the gauge counts a waiting ticket as open and on time', !str_contains($g['reading_text'], 'late') || $c['late'] > 0, $g['reading_text']); } catch (Throwable $x) { $t('the gauge reads', false, $x->getMessage()); } }
GaugeTasks::assign($pdo, $r2, 'system · Executive Translator & Briefing Lead', $J);
$rep = GaugeTasks::report($pdo); $one = array_values(array_filter($rep['requests'], fn ($x) => $x['id'] === $r2))[0];
$t('report() carries its type, stage and a Team role\'s name (a role, not a person)', $one['ticket'] === 'resolution' && $one['stage'] === 'waiting' && $one['owner'] === 'system · Executive Translator & Briefing Lead', $one);
GaugeTasks::assign($pdo, $r2, 'Jane Staff', $J); $rep = GaugeTasks::report($pdo); $one = array_values(array_filter($rep['requests'], fn ($x) => $x['id'] === $r2))[0];
$t('… and still never a person\'s name (0145 §1)', $one['owner'] === 'a staff member');
$t('the two new tables have their staging class (R14)', str_contains((string) file_get_contents($app . '/lib/staging-tables.php'), "'control_gauge_task_moves'") && str_contains((string) file_get_contents($app . '/lib/staging-tables.php'), "'control_gauge_task_briefs'"));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
