<?php
// tools/piece3-tests/piece3-spend-undo-static.php — reads app/lib/TeamRunner.php as it is after this release and checks:
//   1. log() books cost_micros from STEP_COST_MICROS (the spending limit per agent counts something).
//   2. undo() claims the run before it acts and gives the claim back on failure (a second Undo is refused, even at once).
//   3. The decision tree's rule data is unchanged in shape, and every step still costs $0, so the fingerprint is unchanged.
// No database and no outside call. Nothing here moves money. Run: php tools/piece3-tests/piece3-spend-undo-static.php
declare(strict_types=1);

$root = getenv('TV_ROOT') ?: dirname(__DIR__, 2);
$file = $root . '/app/lib/TeamRunner.php';
$fails = 0;
function check(string $name, bool $ok, string $why = ''): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . ($ok || $why === '' ? '' : ' (' . $why . ')') . "\n"; if (!$ok) $fails++; }
function body(string $src, string $start): string
{
    $i = strpos($src, $start); if ($i === false) return '';
    $j = strpos($src, "\n    }\n", $i); return $j === false ? '' : substr($src, $i, $j - $i);
}

$src = @file_get_contents($file);
check('TeamRunner.php is readable', is_string($src) && $src !== '', $file);
if (!is_string($src)) { echo "FAIL: stopped\n"; exit(1); }

// 1. log() books the cost
$log = body($src, 'private static function log(PDO $pdo');
check('log() found', $log !== '');
check('log() inserts cost_micros', str_contains($log, 'touches, cost_micros) VALUES'));
$vi = strpos($log, 'VALUES ('); $ve = $vi === false ? false : strpos($log, "')", $vi);
$qs = ($vi !== false && $ve !== false) ? substr_count(substr($log, $vi, $ve - $vi), '?') : -1;
$ci = strpos($log, 'INSERT INTO team_runs ('); $ce = $ci === false ? false : strpos($log, ') VALUES', $ci);
$cols = ($ci !== false && $ce !== false) ? count(explode(',', substr($log, $ci + strlen('INSERT INTO team_runs ('), $ce - $ci - strlen('INSERT INTO team_runs (')))) : -1;
check('log() has 13 columns and 12 placeholders (at is UTC_TIMESTAMP())', $cols === 13 && $qs === 12, 'columns ' . $cols . ', placeholders ' . $qs);
check('log() reads the cost from STEP_COST_MICROS', str_contains($log, 'self::STEP_COST_MICROS[$step]'));
check('log() books a cost only for done or proposed', str_contains($log, "in_array(\$state, ['done', 'proposed'], true)"));
check('log() still refuses a done step with no Undo', str_contains($log, "if (\$state === 'done' && (trim(\$undoHow) === '' || \$undo === null))"));
$refuse = strpos($log, "\$state = 'refused'"); $costAt = strpos($log, '$cost = ');
check('the cost is worked out after the no-Undo refusal', $refuse !== false && $costAt !== false && $refuse < $costAt);

// 2. undo() claims first
$undo = body($src, 'public static function undo(PDO $pdo, int $rid');
check('undo() found', $undo !== '');
$claimAt = strpos($undo, 'WHERE id = ? AND undone_at IS NULL');
$actAt = strpos($undo, 'GaugeTasks::move(');
check('undo() claims the run before it acts', $claimAt !== false && $actAt !== false && $claimAt < $actAt);
check('undo() refuses when its claim changed no row', str_contains($undo, 'rowCount() !== 1'));
check('undo() gives the claim back on failure', str_contains($undo, "SET undone_at = NULL, undone_by = '' WHERE id = ? AND undone_by = ?") || str_contains($undo, "SET undone_at = NULL, undone_by = \\'\\' WHERE id = ? AND undone_by = ?"));
check('undo() still refuses a proposal', str_contains($undo, 'A proposal changes nothing: Accept or Dismiss it.'));
check('undo() refuses an unknown step before claiming', ($k = strpos($undo, 'This kind of run has no Undo.')) !== false && $claimAt !== false && $k < $claimAt);

// 3. the rules are unchanged (the tree's approval stays)
try {
    require_once $file;
    $keys = array_keys(TeamRunner::ruleData());
    check('ruleData() keys unchanged', $keys === ['steps', 'step_at', 'ceilings', 'territories', 'tier5_words', 'tier1_words', 'step_cost'], implode(',', $keys));
    check('every step still costs $0', array_sum(TeamRunner::STEP_COST_MICROS) === 0 && array_keys(TeamRunner::STEP_COST_MICROS) === ['gate', 'handoff', 'audit', 'brief'] || array_sum(TeamRunner::STEP_COST_MICROS) === 0, json_encode(TeamRunner::STEP_COST_MICROS));
    echo 'INFO: fingerprint without the database ' . substr(TeamRunner::fingerprint(), 0, 12) . " (compare with the same line before this release on staging)\n";
} catch (Throwable $x) { check('TeamRunner loads', false, $x->getMessage()); }

echo $fails === 0 ? "ALL PASS\n" : $fails . " FAIL\n";
exit($fails === 0 ? 0 : 1);
