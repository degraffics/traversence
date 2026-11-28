<?php
// tests/ai-credits-f09-count-fails-closed.php — plain PHP test, prints PASS/FAIL per check (tools/<area>-tests/ style). DRAFT: not run.
// Usage: php ai-credits-f09-count-fails-closed.php [path to AiCredits.php]  (default: this release's files/app/lib/AiCredits.php)
// Needs pdo_sqlite. Loads AiCredits.php beside stub Database/Billing/Team/Thresholds in a temp folder. No money moves.
declare(strict_types=1);

$src = $argv[1] ?? __DIR__ . '/../files/app/lib/AiCredits.php';
if (!is_file($src)) { echo "FAIL AiCredits.php not found at $src\n"; exit(1); }
$dir = sys_get_temp_dir() . '/aicredits-f09-' . getmypid() . '-' . mt_rand();
mkdir($dir);
ini_set('error_log', $dir . '/error.log');
file_put_contents("$dir/Database.php", <<<'PHP'
<?php
final class Database { public static function t(string $name): string { return $name; } }
PHP);
file_put_contents("$dir/Billing.php", <<<'PHP'
<?php
final class Billing {
    public const PLANS = ['plus' => ['Personal Plus', 0, 0, 0, 2], 'biz' => ['Business Pro', 0, 0, 0, 50]];
    public const ADDONS = [];
    public static array $subs = [];
    public static function current($pdo, string $type, int $id): ?array { return self::$subs[$type . ':' . $id] ?? null; }
    public static function planPrice(string $plan, string $cycle): int { return ['plus' => 1000, 'biz' => 4000][$plan] ?? 0; }
    public static function addons($pdo, int $id): array { return []; }
}
PHP);
file_put_contents("$dir/Team.php", <<<'PHP'
<?php
final class Team { public static array $map = []; public static function listingsOf($pdo, int $uid): array { return self::$map[$uid] ?? []; } }
PHP);
file_put_contents("$dir/Thresholds.php", <<<'PHP'
<?php
final class Thresholds { public static array $v = ['ai_cover' => 0.5, 'ai_free_asks' => 3]; public static function get($pdo, string $k) { return self::$v[$k] ?? null; } }
PHP);
copy($src, "$dir/AiCredits.php");

class FailPdo extends PDO
{
    public string $failOn = '';
    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        if ($this->failOn !== '' && strpos($query, $this->failOn) !== false) throw new PDOException('simulated failure');
        return parent::prepare($query, $options);
    }
}

require "$dir/AiCredits.php";

$fails = 0;
function check(string $name, bool $ok): void { global $fails; echo ($ok ? 'PASS' : 'FAIL') . " $name\n"; if (!$ok) $fails++; }

$pdo = new FailPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ai_jobs (id INTEGER PRIMARY KEY, kind TEXT, status TEXT, created_by INTEGER, charged_to TEXT, cost_micros INTEGER NOT NULL DEFAULT 0, created_at TEXT)");
Billing::$subs['user:1'] = ['id' => 1, 'status' => 'active', 'plan' => 'plus', 'cycle' => 'month'];

$s = AiCredits::state($pdo, 1);
check('normal read: free asks available, unread false', $s['free_left'] === 3 && $s['next'] === 'free' && $s['unread'] === false);
check('normal read: pot not unread, 2 left', $s['pots']['user:1']['unread'] === false && $s['pots']['user:1']['left'] === 2);

// The count query fails (database error): nothing may look unused.
$pdo->failOn = 'COUNT(*)';
$s = AiCredits::state($pdo, 1);
check('count fails: no free asks', $s['free_left'] === 0);
check('count fails: pot shows no credits left', $s['pots']['user:1']['left'] === 0 && $s['pots']['user:1']['used'] === 2);
check('count fails: next is null, why used', $s['next'] === null && $s['why'] === 'used');
check('count fails: unread is true', $s['unread'] === true && $s['pots']['user:1']['unread'] === true);
check('count fails: still funded (the plan is real)', $s['funded'] === true && AiCredits::funded($pdo, 1) === true);
check('count fails: a wanted pot is not handed out', AiCredits::state($pdo, 1, 'user:1')['next'] === null);
check('count fails: the error is logged', is_file("$dir/error.log") && strpos((string) file_get_contents("$dir/error.log"), 'AiCredits::count') !== false);

$pdo->failOn = '';
$s = AiCredits::state($pdo, 1);
check('after recovery: back to normal', $s['free_left'] === 3 && $s['unread'] === false);

array_map('unlink', glob("$dir/*") ?: []);
@rmdir($dir);
echo $fails ? "FAIL ($fails)\n" : "PASS all\n";
exit($fails ? 1 : 0);
