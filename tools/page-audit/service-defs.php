<?php
// tools/page-audit/service-defs.php — the outside services' lists, generated from app/lib/service-defs.php (decisions/0173 §4).
// service-defs.php is the one place a person edits: one entry per service. This writes the lists the code reads from it:
// OutsideServices::COMPANIES, HOSTS, NOTICES, CONTACT, LEDGER; Operations::KEYS, CAPS, ADDED_OFF; StagingGate::ANSWERS;
// ServiceStages::RETIRED (the records of services retired or removed).
//   php tools/page-audit/service-defs.php --write <app>   rewrite the lists in <app>/lib from <app>/lib/service-defs.php
//   php tools/page-audit/service-defs.php --check <app>   exit 1 (and say how to fix it) when a list differs from the file
// zipcheck.py runs --check on every zip that carries one of these files.
declare(strict_types=1);

const TV_SD_MARK = '    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write';
// [class file, constant, field, how it's drawn]
const TV_SD_LISTS = [
    ['OutsideServices', 'COMPANIES', 'company', 'company'], ['OutsideServices', 'HOSTS', 'hosts', 'line'], ['OutsideServices', 'CONTACT', 'contact', 'line'],
    ['OutsideServices', 'LEDGER', 'ledger', 'line'], ['OutsideServices', 'NOTICES', 'notices', 'line'],
    ['Operations', 'KEYS', 'keys', 'line'], ['Operations', 'CAPS', 'caps', 'line'], ['Operations', 'ADDED_OFF', 'added_off', 'list'],
    ['StagingGate', 'ANSWERS', 'staging', 'line'],
    ['ServiceStages', 'RETIRED', 'record', 'line'],
];

function tv_sd_str(string $s): string { return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'"; }
function tv_sd_val($v): string
{
    if (is_string($v)) return tv_sd_str($v);
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_int($v) || is_float($v)) return var_export($v, true);
    if ($v === null) return 'null';
    $list = array_keys($v) === range(0, count($v) - 1) || $v === [];
    $parts = [];
    foreach ($v as $k => $x) $parts[] = ($list ? '' : tv_sd_val($k) . ' => ') . tv_sd_val($x);
    return '[' . implode(', ', $parts) . ']';
}

/** The block for one list, from the definitions: the marker line, then the constant as the code declares it. */
function tv_sd_block(array $defs, string $const, string $field, string $how): string
{
    if ($how === 'list') {
        $keys = array_keys(array_filter($defs, fn ($d) => !empty($d[$field])));
        return TV_SD_MARK . "\n    public const " . $const . ' = ' . tv_sd_val($keys) . ';';
    }
    $h = TV_SD_MARK . "\n    public const " . $const . " = [\n";
    foreach ($defs as $k => $d) {
        if (!array_key_exists($field, $d)) continue;
        $v = $d[$field];
        if ($how === 'company') {
            [$name, $what, $price, $links, $usage, $plan] = $v;
            $h .= '        ' . tv_sd_str($k) . ' => [' . tv_sd_str($name) . ', ' . tv_sd_str($what) . ",\n            " . tv_sd_str($price) . ",\n            " . tv_sd_val($links) . ",\n            " . tv_sd_val($usage) . ', ' . tv_sd_str($plan) . "],\n";
        } else $h .= '        ' . tv_sd_str($k) . ' => ' . tv_sd_val($v) . ",\n";
    }
    return $h . '    ];';
}

/** Where a list is in its class file now: [start offset, length] of the marker (if any) and the declaration through its end. */
function tv_sd_find(string $src, string $const): ?array
{
    if (!preg_match('/^    public const ' . $const . ' = \[/m', $src, $m, PREG_OFFSET_CAPTURE)) return null;
    $start = $m[0][1];
    $rest = substr($src, $start);
    $first = strtok($rest, "\n");
    if (preg_match('/\];\s*(\/\/.*)?$/', $first) && substr_count($first, '[') === substr_count($first, ']')) $len = strlen($first);   // on one line
    else { if (!preg_match('/^    \];/m', $rest, $e, PREG_OFFSET_CAPTURE)) return null; $len = $e[0][1] + 6; }
    $mark = TV_SD_MARK . "\n";
    if (substr($src, $start - strlen($mark), strlen($mark)) === $mark) { $start -= strlen($mark); $len += strlen($mark); }
    return [$start, $len];
}

$mode = $argv[1] ?? ''; $app = rtrim($argv[2] ?? '', '/');
if (!in_array($mode, ['--write', '--check'], true) || !is_file($app . '/lib/service-defs.php')) { fwrite(STDERR, "usage: php service-defs.php --write|--check <app folder holding lib/service-defs.php>\n"); exit(2); }
$defs = include $app . '/lib/service-defs.php';
if (!is_array($defs)) { fwrite(STDERR, "lib/service-defs.php must return the array of services\n"); exit(2); }
$files = []; $off = [];
foreach (TV_SD_LISTS as [$cls, $const, $field, $how]) {
    $path = $app . '/lib/' . $cls . '.php';
    $files[$path] = $files[$path] ?? (string) file_get_contents($path);
    $at = tv_sd_find($files[$path], $const);
    if (!$at) { fwrite(STDERR, "$cls::$const isn't in $path\n"); exit(2); }
    $want = tv_sd_block($defs, $const, $field, $how);
    if (substr($files[$path], $at[0], $at[1]) !== $want) { $off[] = "$cls::$const"; $files[$path] = substr_replace($files[$path], $want, $at[0], $at[1]); }
}
if ($mode === '--check') {
    if (!$off) { echo "The outside services' lists match app/lib/service-defs.php.\n"; exit(0); }
    echo 'These lists differ from app/lib/service-defs.php: ' . implode(', ', $off) . ". Edit the service in app/lib/service-defs.php, then run: php tools/page-audit/service-defs.php --write <app>\n";
    exit(1);
}
foreach ($files as $path => $src) file_put_contents($path, $src);
echo $off ? 'Written from app/lib/service-defs.php: ' . implode(', ', $off) . ".\n" : "Already matching app/lib/service-defs.php.\n";
