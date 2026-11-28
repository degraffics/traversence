<?php
// tickverdict.php's helper: runs the real /api/releases-pull.php in its own process, as release-ping.yml's and
// beforeafter.yml's pings do, with GitHub answered in place of the network (DocsRepo::$fake under TV_TEST): the inbox
// empty, the verdict file of release <id> on main through the contents API (before-done, or with a third argument
// after-done with that verdict), any other GitHub call a 404. The look's asks are recorded, never sent; with a third
// argument the decision is recorded too (what it was given and whether the call may publish, schedule, turn off or roll
// back), never made.
// Run (by tickverdict.php): TV_TEST=1 php tickverdict-pull.php <root> <id> [pass|block|incomplete]
declare(strict_types=1);
[$root, $id, $after] = [rtrim((string) ($argv[1] ?? ''), '/'), (int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')];
require_once $root . '/app/lib/DocsRepo.php'; require_once $root . '/app/lib/BeforeAfter.php';
$calls = [];
DocsRepo::$fake = function ($method, $url, $json) use ($id, $after, &$calls) {
    $calls[] = $method . ' ' . $url;
    if (str_contains($url, '/contents/releases/inbox')) return [200, '[]'];
    if ($url === 'https://api.github.com/repos/degraffics/traversence/contents/site-audit/results/beforeafter/' . $id . '.json?ref=main')
        return [200, json_encode(['encoding' => 'base64', 'content' => base64_encode(json_encode(['release' => (string) $id, 'phase' => $after !== '' ? 'after-done' : 'before-done', 'verdict' => $after !== '' ? $after : 'waiting', 'seconds' => 60, 'run' => 'https://github.com/degraffics/traversence/actions/runs/3']))])];
    return [404, ''];
};
BeforeAfter::$askFn = function ($e, $p) { return ''; };
if ($after !== '') { require_once $root . '/app/lib/Releases.php'; BeforeAfter::$decideFn = function ($rid, $v) { $c = fn ($p) => Releases::can($p)[0] ? 1 : 0; return 'DECIDED #' . $rid . ' ' . $v['verdict'] . ' grant ' . $c('releases.publish') . $c('releases.schedule') . $c('releases.turn_off') . $c('releases.rollback'); }; }
register_shutdown_function(function () use (&$calls) { fwrite(STDERR, "\nGITHUB " . json_encode($calls, JSON_UNESCAPED_SLASHES) . "\n"); });
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/api/releases-pull.php';
require $root . '/website/api/releases-pull.php';
