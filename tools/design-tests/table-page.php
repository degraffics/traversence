<?php
// The table standard's test page (design/table-standard.md, T1): the real tv_table(), kit CSS, tokens and admin-kit.js,
// against a mock source that table.mjs serves. Run: TV_ROOT=<a site checkout with app/ and website/> php -S 127.0.0.1:8791 table-page.php
$root = rtrim((string) getenv('TV_ROOT'), '/');
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/js/[\w.-]+\.js$#', $p)) { header('Content-Type: text/javascript'); readfile($root . '/website' . $p); return true; }
if ($p !== '/') { http_response_code(404); return true; }
if (!function_exists('tv_nonce')) { function tv_nonce(): string { return ''; } }
require $root . '/app/ui/theme-tokens.php';
require $root . '/app/admin/kit-table.php';
$view = $_GET['view'] ?? 'list';
$svg = fn ($d) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' . $d . '</svg>';
$menu = [['read', 'Mark read', $svg('<path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/>')], ['pass', 'Pass it on', $svg('<path d="M15 5l5 5-5 5"/><path d="M4 19v-4a5 5 0 0 1 5-5h11"/>')],
    ['pin', 'Pin in your own list', $svg('<path d="M12 17v5"/><path d="M9 3h6l-1 6 4 4H6l4-4z"/>')], ['protect', 'Protect', $svg('<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>')]];
$cfg = ['src' => '/mock/' . $view, 'title' => $view === 'empty' ? 'Nothing yet' : 'Processes', 'sub' => 'means', 'noData' => 'no history yet',
    'columns' => [['name', 'Process', 'name'], ['stage', 'Stage', 'status'], ['when', 'Last check', 'when'], ['runs', 'Runs today', 'count'], ['day', 'Last 24 hours', 'count'], ['speed', 'Speed', 'count']],
    'act' => ['check', 'Check'], 'menu' => $menu, 'open' => '#{id}'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Table test</title>
<?= tv_theme_tokens() ?>
<?php require $root . '/app/admin/kit-css.php'; ?>
<style>body{margin:0;padding:16px;font-family:var(--tv-font-ui);background:var(--tv-canvas)}.ak-body{max-width:1100px;margin:0 auto;background:var(--tv-sec);border:1px solid var(--tv-sec-edge);border-radius:1rem;padding:1rem}</style>
</head><body><main class="ak-body"><?= tv_table($cfg) ?></main>
<script>window.AK_API = '/admin/api/'; document.addEventListener('tv-table:act', function (e) { e.preventDefault(); document.body.setAttribute('data-last-act', e.detail.key + ':' + e.detail.id); });</script>
<script src="/js/admin-kit.js"></script></body></html>
