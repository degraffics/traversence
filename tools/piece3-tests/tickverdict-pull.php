<?php
// tickverdict.php's helper: runs the real /api/releases-pull.php in its own process, as beforeafter.yml's ping does, with
// GitHub answered in place of the network (DocsRepo::$fake under TV_TEST): the inbox empty, the verdict file of release
// <id> on main through the contents API, any other GitHub call a 404. The look's asks are recorded, never sent.
// Run (by tickverdict.php): TV_TEST=1 php tickverdict-pull.php <root> <id>
declare(strict_types=1);
[$root, $id] = [rtrim((string) ($argv[1] ?? ''), '/'), (int) ($argv[2] ?? 0)];
require_once $root . '/app/lib/DocsRepo.php'; require_once $root . '/app/lib/BeforeAfter.php';
$calls = [];
DocsRepo::$fake = function ($method, $url, $json) use ($id, &$calls) {
    $calls[] = $method . ' ' . $url;
    if (str_contains($url, '/contents/releases/inbox')) return [200, '[]'];
    if ($url === 'https://api.github.com/repos/degraffics/traversence/contents/site-audit/results/beforeafter/' . $id . '.json?ref=main')
        return [200, json_encode(['encoding' => 'base64', 'content' => base64_encode(json_encode(['release' => (string) $id, 'phase' => 'before-done', 'verdict' => 'waiting', 'seconds' => 60, 'run' => 'https://github.com/degraffics/traversence/actions/runs/3']))])];
    return [404, ''];
};
BeforeAfter::$askFn = function ($e, $p) { return ''; };
register_shutdown_function(function () use (&$calls) { fwrite(STDERR, "\nGITHUB " . json_encode($calls, JSON_UNESCAPED_SLASHES) . "\n"); });
$_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['REQUEST_URI'] = '/api/releases-pull.php';
require $root . '/website/api/releases-pull.php';
