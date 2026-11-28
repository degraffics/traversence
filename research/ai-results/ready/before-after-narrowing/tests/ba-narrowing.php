<?php
// tests/ba-narrowing.php (tools/piece3-tests/ style): BeforeAfter::narrow() keeps a block only on 0179's four rules (or a rule
// it doesn't know), turns report-only rules into card lines and plan drift into a ticket line, and never passes a block whose
// list is short of its total. Pure: no database, no GitHub. TV_APP: the app folder (default ../../app from tools/<area>-tests/).
declare(strict_types=1);

$app = getenv('TV_APP') ?: dirname(__DIR__, 2) . '/app';
require_once $app . '/lib/BeforeAfter.php';

$fails = 0;
function ok(string $name, bool $cond): void { global $fails; echo ($cond ? 'PASS' : 'FAIL') . ': ' . $name . "\n"; if (!$cond) $fails++; }
function base(string $verdict, int $blocks, string $first = 'first'): array
{ return ['phase' => 'after-done', 'verdict' => $verdict, 'first' => $first, 'run' => '', 'blocks' => $blocks, 'views' => 4, 'seconds' => 60.0, 'far' => []]; }
$b = fn (string $rule, string $what = 'x') => ['who' => 'new-member', 'page' => '/x.php', 'size' => 'phone', 'rule' => $rule, 'what' => $what];

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('alt')]]);
ok('only an alt-text block: passes, reported', $v['verdict'] === 'pass' && $v['blocks'] === 0 && count($v['reports']) === 2 && $v['narrowed'] === 1);

$v = BeforeAfter::narrow(base('block', 2), ['blocks' => [$b('alt'), $b('layout', 'overlap')]]);
ok('alt plus a layout block: still blocks, one block', $v['verdict'] === 'block' && $v['blocks'] === 1 && str_contains($v['first'], 'overlap'));

$v = BeforeAfter::narrow(base('block', 3), ['blocks' => [$b('alt')]]);
ok('a list short of its total keeps the block as it was', $v['verdict'] === 'block' && $v['blocks'] === 3 && $v['first'] === 'first');

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('something-new')]]);
ok('a rule not known here still blocks', $v['verdict'] === 'block' && $v['narrowed'] === 0);

foreach (['access', 'layout', 'data', 'drift'] as $r) {
    $v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b($r)]]);
    ok('0179 rule "' . $r . '" blocks', $v['verdict'] === 'block');
}

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('plan', 'app/lib/Extra.php')]]);
ok('plan drift alone: passes, one ticket line', $v['verdict'] === 'pass' && count($v['tickets']) === 1);

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('area')]]);
ok('a high-risk territory named: never a block by itself', $v['verdict'] === 'pass');

$v = BeforeAfter::narrow(base('pass', 0), ['blocks' => [$b('layout')]]);
ok('a pass is left as it is', $v['verdict'] === 'pass' && $v['reports'] === []);

$v = BeforeAfter::narrow(base('incomplete', 0), ['blocks' => [$b('alt')]]);
ok('an incomplete look is left as it is', $v['verdict'] === 'incomplete');

$v = BeforeAfter::narrow(base('block', 1), []);
ok('no list in the file: the block stands', $v['verdict'] === 'block' && $v['reports'] === [] && $v['tickets'] === []);

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [$b('alt', 'shows check+member@traversence.com')]]);
ok('a test account\'s address is never copied to the card', !str_contains(implode(' ', $v['reports']), '@'));

$v = BeforeAfter::narrow(base('block', 1), ['blocks' => [['who' => '<b>', 'page' => 'javascript:x', 'rule' => 'alt', 'what' => ['no']]]]);
ok('bad fields aren\'t shown', !str_contains(implode(' ', $v['reports']), '<b>') && !str_contains(implode(' ', $v['reports']), 'javascript'));

ok('the four rules are 0179\'s', array_keys(BeforeAfter::RULES) === ['access', 'layout', 'data', 'drift']);
ok('no report-only rule is one of the four', array_intersect(array_keys(BeforeAfter::REPORT_ONLY + BeforeAfter::TICKET_ONLY), array_keys(BeforeAfter::RULES)) === []);

echo $fails === 0 ? "ALL PASS\n" : $fails . " FAILED\n";
exit($fails === 0 ? 0 : 1);
