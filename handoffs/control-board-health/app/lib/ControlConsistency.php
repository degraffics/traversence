<?php
// app/lib/ControlConsistency.php — the board's daily consistency check (handoffs/control-board-plan.md step 5: "each open
// alarm's hold, words and Fix it match the map"). Every alarm is drawn where a person reads it, the board's row
// (tv_cb_alarms), its Pulse item (ControlAlarms::pulseBody) and, for an outside service or a worker, its module card
// (tv_module_card), and each surface must say what the one engine says (decisions/0176 §2–3: one thread, one shape;
// 02 §5.4: the part's plain name, never job:, wf: or svc:):
//   title   the thread's title on the surface;     status  its status words, up to its first moment;
//   names   no raw id (job:, wf:, svc:, task:, ep:, wk:, run:, pf:);   time  no raw {t:…} token;
//   fix     Fix it on the board's row where its cause names one spot;  off  a closed alarm off the board;
//   ir      a trip (with the engine) has its Incident Report (02 P12).
// Read by /api/health.php (its "consistency" section, fetched daily by GitHub) and by the bench's scenario test
// (tools/control-board-tests/scenario/run.php views()), so the live check and the tests are the same code. Read-only:
// it changes nothing, sends nothing. What it returns is alarm ids, keys and words only: nothing about a person.
declare(strict_types=1);

final class ControlConsistency
{
    /** The checks and what each means, for the report's reader. */
    public const CHECKS = ['title' => 'the thread\'s title on this surface', 'status' => 'its status words on this surface',
        'names' => 'a plain name, never a raw id', 'time' => 'no raw time token', 'fix' => 'Fix it on the board\'s row',
        'off' => 'a closed alarm off the board', 'ir' => 'a trip has its Incident Report', 'draw' => 'the surface draws'];

    private static bool $loaded = false;

    private static function load(): void
    {
        if (self::$loaded) return;
        $a = __DIR__ . '/../admin/';
        require_once __DIR__ . '/ControlAlarms.php'; require_once __DIR__ . '/ControlRegister.php'; require_once __DIR__ . '/Thread.php';
        require_once $a . 'widgets.php'; require_once __DIR__ . '/../ui/app-shell.php';
        require_once $a . 'preflight-console.php'; require_once $a . 'control-console.php';
        require_once $a . 'operations-console.php'; require_once $a . 'module-cards.php';
        if (!isset($_SESSION) || !is_array($_SESSION)) $_SESSION = [];   // the drawers read a form token; none is needed to read
        $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? '';
        self::$loaded = true;
    }

    /** A page's words: tags and styles out, spaces collapsed. */
    public static function text(string $h): string
    {
        $h = (string) preg_replace(['~<(style|script)\b.*?</\1>~s', '/<(details|summary|dt|dd|p|li|div|br)\b[^>]*>/'], ['', ' '], $h);
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5)));
    }

    /** Raw ids in what a person reads, each with a little of its context (the bench's own "stand-in:" words left out). */
    public static function rawIds(string $t): array
    {
        $t = (string) preg_replace('/stand-in: \S+/', '', $t);
        if (!preg_match_all('/(?<![\w\/.@-])(?:job|wf|svc|task|ep|wk|run|pf):[a-z0-9_][\w:.-]*/i', $t, $m, PREG_OFFSET_CAPTURE)) return [];
        return array_map(fn ($x) => $x[0] . ' in "…' . mb_substr($t, max(0, (int) $x[1] - 70), 110) . '…"', array_slice($m[0], 0, 2));
    }

    /** Its status up to its first moment (a time is drawn per surface: the viewer's zone on a page, UTC in text). */
    public static function statusHead(string $status): string
    {
        return trim((string) preg_replace('/\s*(\{t:|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d).*$/su', '', mb_substr($status, 0, 70)), " .:,");
    }

    /**
     * Every alarm in the table (open, and closed ones still kept) checked on each surface. ['alarms' => n, 'open' => n,
     * 'checks' => n, 'mismatches' => [['id', 'key', 'level', 'surface', 'check', 'expected', 'got']], 'by_check' => [...]].
     */
    public static function check(PDO $pdo, int $limit = 300): array
    {
        self::load();
        $rows = $pdo->query('SELECT * FROM control_alarms ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)))->fetchAll(PDO::FETCH_ASSOC);
        $out = ['alarms' => count($rows), 'open' => 0, 'checks' => 0, 'mismatches' => [], 'by_check' => []];
        if (!$rows) return $out;
        $names = []; try { foreach (ControlRegister::processes($pdo) as $k => $p) $names[$k] = (string) $p[1]; } catch (Throwable $x) {}
        $btn = fn (string $act, string $label, bool $ok, string $why, string $id = '', array $attr = []) => '<button data-cb="' . htmlspecialchars($act) . '"' . ($ok ? '' : ' disabled') . '>' . htmlspecialchars($label) . '</button>';
        $pid = fn (string $x) => 'cb-p-' . str_replace(':', '-', $x);
        $open = array_values(array_filter(ControlAlarms::list($pdo, 1000), fn ($a) => $a['cleared_at'] === null));   // as the console reads them
        $out['open'] = count($open);
        $boardHtml = $open ? tv_cb_alarms($open, ControlAlarms::counts($pdo), $names, $pid, true, '', $btn) : '';
        $board = self::text($boardHtml);
        $miss = function (array $a, string $surface, string $check, string $expected, $got) use (&$out): void {
            $out['mismatches'][] = ['id' => (int) $a['id'], 'key' => (string) $a['akey'], 'level' => (string) $a['level'], 'surface' => $surface, 'check' => $check,
                'expected' => mb_substr($expected, 0, 200), 'got' => mb_substr(is_string($got) ? $got : (string) json_encode($got, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 0, 240)];
            $out['by_check'][$check] = ($out['by_check'][$check] ?? 0) + 1;
        };
        $t = function (bool $ok, array $a, string $surface, string $check, string $expected, $got) use (&$out, $miss): bool {
            $out['checks']++; if (!$ok) $miss($a, $surface, $check, $expected, $got); return $ok;
        };
        foreach ($rows as $a) {
            $id = (int) $a['id'];
            try {
                $f = ControlAlarms::facts($pdo, $a); $push = Thread::push($f);
                $title = (string) ($f['title'] ?? ''); $head = self::statusHead((string) ($push['body'] ?? ''));
                $pulse = self::text((string) (ControlAlarms::pulseBody($pdo, ['thing' => 'alarm:' . $id], 0)['html'] ?? ''));
                if ($a['cleared_at'] === null) {
                    $li = preg_match('~<li id="cb-alarm-' . $id . '".*?</li>~s', $boardHtml, $mm) ? self::text($mm[0]) : '';
                    $t($li !== '' && str_contains($li, $title), $a, 'board', 'title', $title, mb_substr($li, 0, 200));
                    $t($head === '' || str_contains($li, $head), $a, 'board', 'status', $head, mb_substr($li, 0, 200));
                    $t(!self::rawIds($li), $a, 'board', 'names', 'plain names', self::rawIds($li));
                    $fix = Thread::fixOf(['fix' => ControlAlarms::fixOf($pdo, $a)]);
                    if ($fix) $t(str_contains($li, (string) ($fix['label'] ?? 'Fix it')), $a, 'board', 'fix', (string) ($fix['label'] ?? 'Fix it'), mb_substr($li, 0, 200));
                    if ($a['level'] === 'trip' && !empty($a['tripped_at']) && array_key_exists('incident_id', $a)) $t((int) $a['incident_id'] > 0, $a, 'board', 'ir', 'an IR-####', 'none');
                } else $t(!preg_match('~id="cb-alarm-' . $id . '"~', $boardHtml), $a, 'board', 'off', 'not on the board', 'on the board');
                $t(str_contains($pulse, $title), $a, 'pulse', 'title', $title, mb_substr($pulse, 0, 200));
                $t($head === '' || str_contains($pulse, $head), $a, 'pulse', 'status', $head, mb_substr($pulse, 0, 200));
                $t(!self::rawIds($pulse), $a, 'pulse', 'names', 'plain names', self::rawIds($pulse));
                $t(!str_contains($pulse, '{t:'), $a, 'pulse', 'time', 'no {t:…}', mb_substr($pulse, max(0, (int) mb_strpos($pulse, '{t:') - 60), 120));
                $proc = (string) $a['proc']; $key = str_starts_with($proc, 'svc:') ? substr($proc, 4) : (str_starts_with($proc, 'wk:') ? $proc : '');
                if ($key !== '' && $a['cleared_at'] === null && tv_mc_manifest($key)) {
                    $card = self::text(tv_module_card($pdo, $key, ['can' => true]));
                    $t(str_contains($card, $title), $a, 'card', 'title', $title, mb_substr($card, 0, 200));
                    $t($head === '' || str_contains($card, $head), $a, 'card', 'status', $head, mb_substr($card, 0, 200));
                    $t(!self::rawIds($card), $a, 'card', 'names', 'plain names', self::rawIds($card));
                    $t(!str_contains($card, '{t:'), $a, 'card', 'time', 'no {t:…}', mb_substr($card, max(0, (int) mb_strpos($card, '{t:') - 60), 120));
                }
            } catch (Throwable $x) { $out['checks']++; $miss($a, 'any', 'draw', 'it draws', get_class($x) . ': ' . $x->getMessage()); }
        }
        $out['checks']++;
        if (str_contains($board, '{t:')) $miss(['id' => 0, 'akey' => '', 'level' => ''], 'board', 'time', 'no {t:…}', mb_substr($board, max(0, (int) mb_strpos($board, '{t:') - 60), 120));
        return $out;
    }
}
