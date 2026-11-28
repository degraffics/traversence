<?php
declare(strict_types=1);
/**
 * DRAFT test for the built stylesheet. Install it at tools/css-tests/stylesheet-test.php.
 *
 * Run:   php tools/css-tests/stylesheet-test.php [repo-root]
 * Output: one PASS or FAIL line per check. Exits 1 on any FAIL.
 * It reads files only. It touches no database, no network and no money.
 */

$root = $argv[1] ?? dirname(__DIR__, 2);
$root = rtrim((string) (realpath($root) ?: $root), '/');

$passes = 0;
$fails  = 0;

function check(bool $ok, string $what, string $detail = ''): void
{
    global $passes, $fails;
    if ($ok) {
        $passes++;
        echo "PASS {$what}\n";
    } else {
        $fails++;
        echo "FAIL {$what}" . ($detail !== '' ? " :: {$detail}" : '') . "\n";
    }
}

/**
 * Lists the site's files, skipping build tooling and the document folders.
 *
 * @return string[]
 */
function tv_files(string $root, array $exts): array
{
    $anyDepth = ['.git', 'node_modules', 'vendor'];
    $topOnly  = ['tools', 'handoffs', 'research', 'decisions', 'design', 'releases', 'css', 'tests'];
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function (SplFileInfo $f, $key, RecursiveDirectoryIterator $it) use ($root, $anyDepth, $topOnly): bool {
            if ($it->hasChildren()) {
                $name = $f->getFilename();
                if (in_array($name, $anyDepth, true)) {
                    return false;
                }
                if (dirname($f->getPathname()) === $root && in_array($name, $topOnly, true)) {
                    return false;
                }
                return true;
            }
            return true;
        }
    );
    $out = [];
    foreach (new RecursiveIteratorIterator($filter) as $f) {
        /** @var SplFileInfo $f */
        $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
        if (in_array($ext, $exts, true)) {
            $out[] = $f->getPathname();
        }
    }
    sort($out);
    return $out;
}

function tv_rel(string $root, string $path): string
{
    return substr($path, strlen($root) + 1);
}

/** Escapes a class name the way Tailwind writes it in a selector. */
function tv_css_escape(string $cls): string
{
    return (string) preg_replace_callback('~[^a-zA-Z0-9_-]~', fn($m) => '\\' . $m[0], $cls);
}

// LANE: classes that come from our own non-Tailwind CSS, and so are correctly absent from css/site.css.
$notTailwind = [];
// LANE: templates with a <head> that must not get the site stylesheet (e.g. email bodies).
$headExempt = [];

// 1. The build output.
$cssFile = $root . '/css/site.css';
$css = is_file($cssFile) ? (string) file_get_contents($cssFile) : '';
check($css !== '', 'css/site.css exists and is not empty');
check($css !== '' && !str_contains($css, '@tailwind'), 'css/site.css is built (no @tailwind directives left)');
check(str_contains($css, 'tailwindcss v3'), 'css/site.css carries the tailwindcss v3 banner');

// 2. No Play CDN left anywhere the site serves.
$cdn = [];
$styleBlocks = [];
$inlineConfig = [];
$headsWithout = [];
foreach (tv_files($root, ['php', 'html', 'js', 'htaccess']) as $f) {
    $s = (string) file_get_contents($f);
    $rel = tv_rel($root, $f);
    if (str_contains($s, 'cdn.tailwindcss.com')) {
        $cdn[] = $rel;
    }
    if (preg_match('~type\s*=\s*["\']text/tailwindcss["\']~i', $s)) {
        $styleBlocks[] = $rel;
    }
    if (preg_match('~\btailwind\.config\s*=~', $s)) {
        $inlineConfig[] = $rel;
    }
    if (str_ends_with($f, '.php') && preg_match('~<head[\s>]~i', $s)
        && !str_contains($s, 'tv_site_css_tag') && !in_array($rel, $headExempt, true)) {
        $headsWithout[] = $rel;
    }
}
check($cdn === [], 'no file references cdn.tailwindcss.com', implode(', ', $cdn));
check($styleBlocks === [], 'no <style type="text/tailwindcss"> blocks remain', implode(', ', $styleBlocks));
check($inlineConfig === [], 'no inline tailwind.config scripts remain', implode(', ', $inlineConfig));
check($headsWithout === [], 'every PHP <head> echoes tv_site_css_tag()', implode(', ', $headsWithout));

// 3. The helper.
$helper = $root . '/app/ui/site-css.php';
check(is_file($helper), 'app/ui/site-css.php exists');
if (is_file($helper)) {
    require_once $helper;
    $want = is_file($cssFile) ? substr((string) md5_file($cssFile), 0, 10) : '0';
    $tag = tv_site_css_tag();
    check(
        $tag === '<link rel="stylesheet" href="/css/site.css?v=' . $want . '">',
        'tv_site_css_tag() prints one link with the content hash',
        $tag
    );
}

// 4. The build config covers every top-level folder with PHP in it.
$cfgFile = $root . '/tools/css-build/tailwind.config.cjs';
$cfg = is_file($cfgFile) ? (string) file_get_contents($cfgFile) : '';
check($cfg !== '', 'tools/css-build/tailwind.config.cjs exists');
preg_match_all("~'\\./([A-Za-z0-9_.-]+)/~", $cfg, $m);
$covered = array_unique($m[1]);
$tops = [];
$rootPhp = false;
$phpFiles = tv_files($root, ['php']);
foreach ($phpFiles as $f) {
    $rel = tv_rel($root, $f);
    if (!str_contains($rel, '/')) {
        $rootPhp = true;
        continue;
    }
    $tops[explode('/', $rel)[0]] = true;
}
$missingDirs = array_values(array_diff(array_keys($tops), $covered));
check($missingDirs === [], 'every top-level folder with PHP is in content[]', implode(', ', $missingDirs));
check(!$rootPhp || str_contains($cfg, "'./*.php'"), "root PHP files are covered by './*.php'");

// 5. Every literal Tailwind-looking class in PHP and HTML is in the built CSS.
$util = '~^(?:[a-z0-9@\[\]&_>=.-]+:)*!?-?(?:'
    . '(?:p|px|py|pt|pr|pb|pl|ps|pe|m|mx|my|mt|mr|mb|ml|ms|me|w|h|size|min-w|min-h|max-w|max-h|gap|space|inset|top|right|bottom|left|z|order|basis|grow|shrink|col|row|grid|flex|text|font|leading|tracking|bg|from|via|to|border|rounded|ring|outline|shadow|opacity|blur|backdrop|duration|delay|ease|transition|translate|scale|rotate|cursor|select|line-clamp|aspect|object|overflow|items|justify|content|self|place|decoration|underline-offset|divide|fill|stroke|columns|indent|whitespace|break|list|accent|caret|snap|scroll|touch|animate)-[A-Za-z0-9\[\]#%().,/_:-]+'
    . '|flex|grid|block|inline|inline-block|inline-flex|inline-grid|hidden|contents|table|absolute|relative|fixed|sticky|static'
    . '|truncate|underline|no-underline|uppercase|lowercase|capitalize|italic|not-italic|sr-only|not-sr-only|container'
    . '|grow|shrink|border|rounded|shadow|ring|outline|transition|antialiased|isolate|visible|invisible|collapse)$~';
$missing = [];
foreach (tv_files($root, ['php', 'html']) as $f) {
    $s = (string) file_get_contents($f);
    if (!preg_match_all('~\bclass\s*=\s*(["\'])(.*?)\1~s', $s, $mm)) {
        continue;
    }
    foreach ($mm[2] as $val) {
        $val = (string) preg_replace('~<\?.*?\?>~s', ' ', $val);
        foreach (preg_split('~\s+~', trim($val)) ?: [] as $tok) {
            if ($tok === '' || preg_match('~[${}<>\\\\,]~', $tok) || in_array($tok, $notTailwind, true)) {
                continue;
            }
            if (!preg_match($util, $tok)) {
                continue;
            }
            $sel = '.' . tv_css_escape($tok);
            $found = false;
            $off = 0;
            while (($p = strpos($css, $sel, $off)) !== false) {
                $next = $css[$p + strlen($sel)] ?? '';
                if ($next === '' || !preg_match('~[a-zA-Z0-9_\\\\-]~', $next)) {
                    $found = true;
                    break;
                }
                $off = $p + 1;
            }
            if (!$found) {
                $missing[$tok][] = tv_rel($root, $f);
            }
        }
    }
}
$list = [];
foreach (array_slice($missing, 0, 40, true) as $tok => $where) {
    $list[] = $tok . ' (' . implode(', ', array_slice(array_unique($where), 0, 2)) . ')';
}
check($missing === [], 'every literal utility class is in css/site.css', implode('; ', $list)
    . (count($missing) > 40 ? '; and ' . (count($missing) - 40) . ' more' : ''));

echo "\n{$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
