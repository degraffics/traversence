=== FILE: files/tools/css-build/tailwind.config.cjs ===
/*
 * Traversence: the site's stylesheet, built once with the tailwindcss CLI.
 * Decision: Jason, 2026-11-27, "Yes to the stylesheet" (note on design/css-theme-brand.md).
 * This replaces the Play CDN (cdn.tailwindcss.com). To build, run tools/css-build/build.sh.
 *
 * DRAFT. The folder list below is taken from paths named in decisions/0058 and
 * design/css-theme-brand.md. It is not a listing of the repo.
 * tools/css-tests/stylesheet-test.php fails when a top-level folder that holds
 * PHP is missing from this list. The lane adds or removes folders until it passes.
 */
module.exports = {
  content: [
    './*.php',
    './admin/**/*.php',
    './api/**/*.php',
    './app/**/*.php',
    './discovery/**/*.php',
    './hub/**/*.php',
    './includes/**/*.php',
    './journeys/**/*.php',
    './listing/**/*.php',
    './place/**/*.php',
    './user/**/*.php',
    './js/**/*.js',
  ],
  // LANE: copy here, unchanged, the live page's inline `tailwind.config = { ... }`
  // (the script next to the CDN tag). File not shown.
  // If the CDN tag carries ?plugins=..., add each plugin here and in build.sh.
  theme: {
    extend: {},
  },
  // Classes the CLI cannot see belong here. These are classes built by string
  // concatenation in PHP or JS ('bg-' . $tone), and classes that live in
  // database content. The Play CDN found them at runtime; the CLI does not.
  safelist: [],
  plugins: [],
};
=== END FILE ===

=== FILE: files/tools/css-build/input.css ===
/* Traversence site stylesheet source. Built to css/site.css by tools/css-build/build.sh. */
@tailwind base;
@tailwind components;
@tailwind utilities;

/*
 * LANE: move here, unchanged, the contents of every <style type="text/tailwindcss">
 * block in the live pages. Those blocks only work with the Play CDN. File not shown.
 * tools/css-tests/stylesheet-test.php fails while any such block remains in a page.
 */
=== END FILE ===

=== FILE: files/tools/css-build/build.sh ===
#!/bin/sh
# Builds css/site.css from the site's files with the tailwindcss CLI.
# Run this in the lane session, never on Bluehost (no node there).
# css/site.css is committed and goes in the deploy zip.
#
# Version: v3 is the line the Play CDN (cdn.tailwindcss.com) serves.
# LANE: confirm the exact version against the live tag before trusting 3.4.17.
# If the live tag loads plugins (?plugins=forms,typography,...), use this instead:
#   npx --yes -p tailwindcss@3.4.17 -p @tailwindcss/forms tailwindcss ...
set -eu
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
npx --yes tailwindcss@3.4.17 \
  -c tools/css-build/tailwind.config.cjs \
  -i tools/css-build/input.css \
  -o css/site.css \
  --minify
if [ -f tools/css-tests/stylesheet-test.php ]; then
  php tools/css-tests/stylesheet-test.php
else
  echo "FAIL tools/css-tests/stylesheet-test.php not installed"
  exit 1
fi
=== END FILE ===

=== FILE: files/app/ui/site-css.php ===
<?php
declare(strict_types=1);
/**
 * The site's built stylesheet. It replaces the Play CDN script (Jason, 2026-11-27).
 *
 * Echo tv_site_css_tag() in <head>, where the cdn.tailwindcss.com script was.
 * The ?v= value is the file's content hash. A rebuild reaches visitors at once,
 * and re-uploading the same bytes does not bust caches.
 *
 * DRAFT: this assumes css/ sits beside app/ under the web root. File layout not shown.
 */

if (!function_exists('tv_site_css_tag')) {
    function tv_site_css_tag(): string
    {
        static $tag = null;
        if ($tag !== null) {
            return $tag;
        }
        $rel  = '/css/site.css';
        $file = dirname(__DIR__, 2) . $rel;
        if (is_file($file)) {
            $v = substr((string) md5_file($file), 0, 10);
        } else {
            $v = '0';
            error_log('tv_site_css_tag: css/site.css is missing; run tools/css-build/build.sh');
        }
        $tag = '<link rel="stylesheet" href="'
            . htmlspecialchars($rel . '?v=' . $v, ENT_QUOTES, 'UTF-8') . '">';
        return $tag;
    }
}
=== END FILE ===

=== FILE: tests/stylesheet-test.php ===
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
=== END FILE ===

=== FILE: faq/faq-entries.md ===
<!-- DRAFT. faq.md is not shown; the lane places these under the site/performance section. Status: coming. -->

### Why did pages sometimes jump around while they loaded?

**Coming.** Our pages used to get their styling from an outside service after they had started to appear. That is why text and cards could shift for a moment. We are moving to one stylesheet built ahead of time and served from our own server, so a page arrives already styled and stays still.

### Does Traversence load code from other companies to style its pages?

**Coming.** Today, one outside script (Tailwind's CDN) styles our pages. Soon our own stylesheet, served from Traversence's server, will replace it. Your browser will then no longer contact that service when you open a page. It costs you nothing and changes nothing you need to do.
=== END FILE ===

=== FILE: release.json ===
{
  "name": "dnl-stylesheet-tailwind (DRAFT: a plan for the session manager, not a release to send)",
  "gate": "Not to be sent. Per handoffs/decided-not-live.md this is its own release, after C.5a and C.5b. A plan goes to the session manager first, then the Design reviewer checks it at 1280 and 390 on every page kind. The Frontend & Shop session builds it with the design releases, in the session manager's order. It moves to Live only when synced and byte-checked.",
  "what": "SKIPPED FIRST (paused or waiting for Jason, per the attached handoff): (1) M2b, how an order goes: 'Paused by Jason 2026-11-24'. (2) No Undo on an alarm's acknowledgement: 'needs a Design look and Jason's pop-up'. (3) Order #12's books: 'I will address this after holiday' (Jason, 2026-11-27). This draft touches none of them. THE ITEM: the site's stylesheet, built once with the tailwindcss CLI over the site's files and served as /css/site.css, in place of the Play CDN (cdn.tailwindcss.com). It is not paused; Jason approved it on 2026-11-27. New files: the build config, the input CSS, the build script, the tv_site_css_tag() helper, a test and FAQ entries. No live code file was attached, so no live file is rewritten here. Each live change is listed under 'verify' as 'file not shown'. No migration is needed.",
  "files": [
    "files/tools/css-build/tailwind.config.cjs",
    "files/tools/css-build/input.css",
    "files/tools/css-build/build.sh",
    "files/app/ui/site-css.php",
    "tests/stylesheet-test.php (installs at tools/css-tests/stylesheet-test.php; build.sh calls it there)",
    "faq/faq-entries.md",
    "css/site.css: generated by build.sh in the lane session, committed, and included in the deploy zip; not drafted here"
  ],
  "decisions": [
    "design/css-theme-brand.md, note (Jason, 2026-11-27: 'Yes to the stylesheet'): built once as its own stylesheet with the tailwindcss CLI over the site's files, served from our server, in place of the Play CDN; ends the CDN-caused layout shift sitewide; drops a third-party script; $0.",
    "Same note: decisions/0135 and 0098's outside-services rows ('To self-host as a built stylesheet (then dropped from the list)') are carried out by it.",
    "Same note: the Frontend & Shop session builds it with the design releases, through the Design reviewer, in the session manager's order; C.5a and C.5b stay as they are meanwhile.",
    "handoffs/decided-not-live.md: its own release, after C.5a and C.5b; a plan to the session manager first, then the Design reviewer at 1280 and 390 on every page kind.",
    "decisions/0058 §11 and design/css-theme-brand.md §3: 390px edge-to-edge, 3px gaps, zero horizontal scroll. The built CSS must reproduce what the CDN rendered, with no visual change intended.",
    "Skipped per the request: M2b (paused by Jason 2026-11-24), No Undo (waits for Jason's pop-up), Order #12's books (Jason after the holiday)."
  ],
  "tests": [
    "tools/css-tests/stylesheet-test.php: css/site.css exists, is built, and carries the v3 banner.",
    "Same test: no cdn.tailwindcss.com, no text/tailwindcss style blocks and no inline tailwind.config left in php/html/js/.htaccess.",
    "Same test: every PHP file with <head> echoes tv_site_css_tag(), unless it is listed in $headExempt.",
    "Same test: tv_site_css_tag() prints exactly one link carrying the md5 prefix of css/site.css.",
    "Same test: every top-level folder with PHP is in content[].",
    "Same test: every literal Tailwind-looking class in a class=\"...\" attribute is present in css/site.css.",
    "Visual check (Design reviewer, not a script): every page kind at 1280 and 390, against the live CDN render; existing tools/design-tests/place-page.cjs (48 checks) rerun."
  ],
  "risks": [
    "Nothing here has been run, built, tested or zip-checked. It is all a DRAFT for the lane session to verify.",
    "The attached job-19 draft was truncated at its test section. The test here is a rewrite, not the draft's.",
    "The content[] folders come from paths named in decisions/0058 and css-theme-brand.md, not from a repo listing. The test fails on any uncovered PHP folder; the lane adjusts the list.",
    "Classes the CLI cannot see will lose their styling. Examples: classes built by concatenation in PHP or JS ('bg-' . $x), classes added by JS at runtime, and classes stored in database content or in escaped strings (class=\\\"...\\\"). The Play CDN found these at runtime. They must go in safelist. The literal-class test cannot catch them; only the Design reviewer's page-by-page check can.",
    "The literal-class test matches by prefix. A class from our own non-Tailwind CSS that looks like a utility (e.g. shadow-card) gives a false FAIL until it is added to $notTailwind. Tokens with commas are skipped, because Tailwind's comma escaping is not confirmed.",
    "Version: 3.4.17 is the draft's assumption of what the Play CDN serves. If the live tag pins another version or loads plugins (?plugins=...), the output differs. Mirror the tag exactly.",
    "Cascade order: the draft claims the CDN appends its <style> at the end of <head>. This is not verified here. If our own CSS currently loses ties to Tailwind, the link must go after it; if it wins, before it.",
    "Ongoing cost: from this release on, any change that adds a class needs build.sh re-run and css/site.css re-committed, or the class silently does nothing. That includes C.5a, C.5b and every later design release. Suggest a CLAUDE.md line, which needs the session manager's OK.",
    "The helper assumes css/ sits beside app/ under the web root, and hashes the file once per request (cached in a static). File layout not shown.",
    "Preflight and base styles now load in a blocking stylesheet, not after a script runs. First paint should match the settled CDN render, but any page that relied on the brief unstyled moment (unlikely) will look different."
  ],
  "verify": [
    "app/ui/app-shell.php: file not shown. Remove the cdn.tailwindcss.com script and the inline tailwind.config script, copying the config into tailwind.config.cjs first. Move any text/tailwindcss style blocks into input.css. Add require_once for app/ui/site-css.php and echo tv_site_css_tag() where the script was, in the cascade position checked above.",
    "Every other template with its own <head>: file not shown. Candidates are admin, crawler and Dashboard pages, if they don't use the shell. The test's <head> check lists them.",
    ".htaccess or wherever CSP is set: file not shown. Remove cdn.tailwindcss.com from script-src only if it is there.",
    "decisions/0098 (outside-services row) and decisions/0135: files not shown. Mark them carried out only once this release is live, synced and byte-checked, not at draft time.",
    "faq.md: file not shown. Place the entries from faq/faq-entries.md; flip 'Coming' to live wording at release.",
    "Deploy zip manifest or exclude list: file not shown. css/site.css must be in the zip. tools/css-build/ and node_modules must not be.",
    "Open Work-queue parents for this item are in the live database and not attached. The lane links this to its task there.",
    "Confirm C.5a and C.5b are live before building, because their classes must be in the scan.",
    "Run build.sh in the lane session, then the test, then the Design reviewer at 1280 and 390 on every page kind, compared against the live CDN render.",
    "Check that zero requests go to cdn.tailwindcss.com in a browser network panel on each page kind."
  ],
  "regions": false
}
=== END FILE ===