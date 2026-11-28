<?php
// tests/ai-credits-f10-spend-fails-closed.php — plain PHP test, prints PASS/FAIL per check (tools/<area>-tests/ style). DRAFT: not run.
// Usage: php ai-credits-f10-spend-fails-closed.php [path to AiCredits.php]  (default: this release's files/app/lib/AiCredits.php)
// Needs pdo_sqlite. Loads AiCredits.php beside stub Database/Billing/Team/Thresholds in a temp folder. No money moves.
declare(strict_types=1);

$src = $argv[1] ?? __DIR__ . '/../files/app/lib/AiCredits.php';
if (!is_file($src)) { echo "FAIL AiCredits.php not found at $src\n"; exit(1); }
$dir = sys_get_temp_dir() . '/aicredits-f10-' . getmypid() . '-' . mt_rand();
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
function job(PDO $pdo, int $uid, string $to, string $status, int $micros = 0, string $kind = 'topic_assist'): int
{
    $st = $pdo->prepare("INSERT INTO ai_jobs (kind, status, created_by, charged_to, cost_micros, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $st->execute([$kind, $status, $uid, $to, $micros, date('Y-m-d H:i:s')]);
    return (int) $pdo->lastInsertId();
}

$pdo = new FailPdo('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE ai_jobs (id INTEGER PRIMARY KEY, kind TEXT, status TEXT, created_by INTEGER, charged_to TEXT, cost_micros INTEGER NOT NULL DEFAULT 0, created_at TEXT)");
Billing::$subs['user:1'] = ['id' => 1, 'status' => 'active', 'plan' => 'plus', 'cycle' => 'month'];

// Plus pays $10 a month: cover is $5.
$s = AiCredits::state($pdo, 1);
check('normal read: cover is $5, next free, unread false', abs($s['cover'] - 5.0) < 1e-9 && $s['next'] === 'free' && $s['why'] === null && $s['unread'] === false);

// The spend query fails: the backstop must hold.
$pdo->failOn = 'SUM(cost_micros)';
$s = AiCredits::state($pdo, 1);
check('spend fails: why cover', $s['why'] === 'cover');
check('spend fails: next null (no free ask, no credit)', $s['next'] === null);
check('spend fails: unread true', $s['unread'] === true);
check('spend fails: wanted pot not handed out', AiCredits::state($pdo, 1, 'user:1')['next'] === null);
check('spend fails: the result still encodes as JSON (no INF)', json_encode(AiCredits::state($pdo, 1)) !== false);
check('spend fails: the error is logged', is_file("$dir/error.log") && strpos((string) file_get_contents("$dir/error.log"), 'AiCredits::state') !== false);

// Recovery, and the normal backstop still trips on real spend.
$pdo->failOn = '';
check('after recovery: next free again', AiCredits::state($pdo, 1)['next'] === 'free');
job($pdo, 1, 'free', 'done', 5000000);
$s = AiCredits::state($pdo, 1);
check('real spend at cover: why cover', $s['why'] === 'cover' && $s['next'] === null && $s['unread'] === false);

// A member with no plan whose spend can't be read is still 'no_plan', not 'cover'.
$pdo->failOn = 'SUM(cost_micros)';
check('no plan + spend fails: why no_plan', AiCredits::state($pdo, 9)['why'] === 'no_plan');

array_map('unlink', glob("$dir/*") ?: []);
@rmdir($dir);
echo $fails ? "FAIL ($fails)\n" : "PASS all\n";
exit($fails ? 1 : 0);
