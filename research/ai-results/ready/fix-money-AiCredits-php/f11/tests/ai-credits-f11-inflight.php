<?php
// tests/ai-credits-f11-inflight.php — plain PHP test, prints PASS/FAIL per check (tools/<area>-tests/ style). DRAFT: not run.
// Usage: php ai-credits-f11-inflight.php [path to AiCredits.php]  (default: this release's files/app/lib/AiCredits.php)
// Needs pdo_sqlite. Loads AiCredits.php beside stub Database/Billing/Team/Thresholds in a temp folder. No money moves.
declare(strict_types=1);

$src = $argv[1] ?? __DIR__ . '/../files/app/lib/AiCredits.php';
if (!is_file($src)) { echo "FAIL AiCredits.php not found at $src\n"; exit(1); }
$dir = sys_get_temp_dir() . '/aicredits-f11-' . getmypid() . '-' . mt_rand();
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

$s = AiCredits::state($pdo, 1);
check('fresh member: 3 free asks, next is free', $s['free_left'] === 3 && $s['next'] === 'free');

// Three free asks still working (not done yet): they must already count.
$free = [job($pdo, 1, 'free', 'queued'), job($pdo, 1, 'free', 'running'), job($pdo, 1, 'free', 'running', 0, 'item_write')];
$s = AiCredits::state($pdo, 1);
check('free asks still waiting/working count against today', $s['free_left'] === 0);
check('with free asks gone, next is the own plan', $s['next'] === 'user:1');

// Two credit asks still working use both credits.
$a = job($pdo, 1, 'user:1', 'queued');
job($pdo, 1, 'user:1', 'running');
$s = AiCredits::state($pdo, 1);
check('credit asks still working count as used', $s['pots']['user:1']['used'] === 2 && $s['pots']['user:1']['left'] === 0);
check('no credits left: next null, why used', $s['next'] === null && $s['why'] === 'used');

// A failed ask is given back.
$pdo->prepare("UPDATE ai_jobs SET status = 'failed' WHERE id = ?")->execute([$a]);
$s = AiCredits::state($pdo, 1);
check('a failed credit ask is given back', $s['pots']['user:1']['left'] === 1 && $s['next'] === 'user:1');
$pdo->prepare("UPDATE ai_jobs SET status = 'failed' WHERE id = ?")->execute([$free[0]]);
$s = AiCredits::state($pdo, 1);
check('a failed free ask is given back', $s['free_left'] === 1 && $s['next'] === 'free');

// Another member's asks don't count against this member's free asks.
job($pdo, 2, 'free', 'running');
$s = AiCredits::state($pdo, 1);
check("another member's free asks don't count", $s['free_left'] === 1);

array_map('unlink', glob("$dir/*") ?: []);
@rmdir($dir);
echo $fails ? "FAIL ($fails)\n" : "PASS all\n";
exit($fails ? 1 : 0);
