<?php
// app/lib/Gemini.php — The Gemini client (Google AI Studio's free tier, no billing linked; decisions/0164 §0.8, R5, R13;
// 0163 §11). Gemini reads PUBLIC PAGES ONLY (extraction); it is never a finder and its word weighs 0. The free tier lets
// Google use what we send, so nothing private ever goes to it, enforced HERE, once, not per workflow:
//   - only a feature whose input class is public (AiClient::INPUT) may call it (P2-3); every other is refused
//   - a page is cleaned before it's sent (safe()): R6's private details (emails, ID and account numbers, people beside
//     birth, death, court or address words) removed (P2-1), and any protected, confidential or nation-sensitive name
//     taken out (T1, R7)
//   - the prompt is built by the system from our own vocabulary (prompt(): a kind from OpenPlaces::KINDS, a town from
//     zip_coordinates, a field from our list), never a person's words (P2-2)
//   - every call goes through the gates: the master and the feature's switches (Automation), the service switch (added
//     off), ServiceHealth (holds, bans), staging's answer (StagingGate::hold); it is paced through the day with 20% held
//     for on-demand work, and background work stops at 80% (0163 §11). When the day's allowance is used the work waits:
//     no fallback to Claude.
// Shipped off: Gemini's service is paused (Operations::ADDED_OFF) and no feature is routed to it until a director moves
// one on AI use, after the sample comparison (0164 §2.3).
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Gemini
{
    public const HOST = 'generativelanguage.googleapis.com';
    public const MODELS = ['gemini-flash-lite-latest' => 'Flash-Lite (free tier)', 'gemini-flash-latest' => 'Flash (free tier)'];
    public const MODEL = 'gemini-flash-lite-latest';
    public const SERVICE = 'gemini';

    /** Prompts built by the system: template => [text with {fields}, the fields' vocabularies]. Nothing else is ever sent. */
    public const PROMPTS = [
        'extract_places' => ["Read the public web page below. List each business, organization or public place it names in {town}, {state}, as JSON lines with the keys name, street, city, zip, phone, website, kind. Use kind values from this list only: {kinds}. Leave a key empty when the page doesn't say it. Don't guess and don't add anything the page doesn't say. Never include people's names unless the person is the business.\n\nPAGE:\n{page}", ['town' => 'town', 'state' => 'state', 'kinds' => 'kinds', 'page' => 'page']],
        'extract_compare' => ["Read the public web page below. List each business, organization or public place it names, with its street, city, phone and website as the page gives them, as JSON lines with the keys name, street, city, phone, website. Leave a key empty when the page doesn't say it. Don't guess.\n\nPAGE:\n{page}", ['page' => 'page']],
        'extract_facts' => ["Read the public web page below about {name}. Give, as JSON with the keys hours, phone, website, street, open, the facts the page states about it. Leave a key empty when the page doesn't say it. Don't guess.\n\nPAGE:\n{page}", ['name' => 'listing', 'page' => 'page']],
    ];

    // ---- the guards -------------------------------------------------------------------------------------------------

    /** May this feature send to Gemini? null when it may, else why not (P2-3). */
    public static function refused(string $feature): ?string
    {
        require_once __DIR__ . '/AiClient.php';
        $class = AiClient::INPUT[$feature] ?? 'unknown';
        if ($class !== 'public') return 'Gemini\'s free tier lets Google use what it\'s sent, so only public pages go to it (decisions/0164 §0.8); this feature carries ' . (AiClient::INPUT_CLASSES[$class] ?? 'something not declared') . ', so it stays on Claude.';
        return null;
    }

    /**
     * A public page made safe to send: [text, what was removed (kind => count)]. R6's private details and protected,
     * confidential or nation-sensitive names are removed (whole sentences where a person or a held name appears).
     */
    public static function safe(string $text, ?PDO $pdo = null): array
    {
        $gone = [];
        $cut = function (string $re, string $kind, string $with = '') use (&$text, &$gone): void { $n = 0; $text = (string) preg_replace($re, $with, $text, -1, $n); if ($n) $gone[$kind] = ($gone[$kind] ?? 0) + $n; };
        $text = (string) preg_replace('/\s+/u', ' ', strip_tags($text));
        $cut('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', 'email', '[email removed]');
        $cut('/\b\d{3}-\d{2}-\d{4}\b/', 'id number', '[removed]');
        $cut('/\b(?:\d[ -]?){13,19}\b/', 'account number', '[removed]');
        // split into sentences; drop those about a private person (R6) or naming a held place (R7, T1)
        $sent = preg_split('/(?<=[.!?])\s+/u', $text) ?: [$text];
        $people = '/\b(born|died|passed away|obituar|survived by|funeral|memorial service|arrest|sentenced|convicted|court|lawsuit|divorce|custody|lives at|resides|residence of|home address|his home|her home|their home|age \d{1,3}|aged \d{1,3}|\d{1,3}, of [A-Z])/i';
        require_once __DIR__ . '/Landmarks.php';
        require_once __DIR__ . '/ListingSafety.php';
        $held = self::heldNames($pdo);
        $out = [];
        foreach ($sent as $s) {
            if (preg_match($people, $s) && preg_match('/\b[A-Z][a-z]+ [A-Z][a-z]+\b/u', $s)) { $gone['a private person'] = ($gone['a private person'] ?? 0) + 1; continue; }
            if (preg_match(Landmarks::SENSITIVE, $s)) { $gone['a protected place'] = ($gone['a protected place'] ?? 0) + 1; continue; }
            if (preg_match(ListingSafety::CONFIDENTIAL_NAME, $s)) { $gone['a confidential place'] = ($gone['a confidential place'] ?? 0) + 1; continue; }
            $low = mb_strtolower($s); $hit = false;
            foreach ($held as $n) if ($n !== '' && str_contains($low, $n)) { $hit = true; break; }
            if ($hit) { $gone['a held name'] = ($gone['a held name'] ?? 0) + 1; continue; }
            $out[] = $s;
        }
        return [trim(implode(' ', $out)), $gone];
    }

    /** Names held from the public (protected, nation-sensitive, story only): lower case, for safe(). */
    private static function heldNames(?PDO $pdo): array
    {
        static $n = null;
        if ($n !== null) return $n;
        $n = [];
        if (!$pdo) return $n;
        try {
            require_once __DIR__ . '/Landmarks.php';
            $classes = implode(',', array_map(fn ($c) => $pdo->quote($c), Landmarks::NATION_SENSITIVE_CLASSES));
            $st = $pdo->query("SELECT name FROM landmarks WHERE status IN ('hidden', 'story_only', 'waiting', 'closed') OR (COALESCE(nation, '') <> '' AND fclass IN ($classes)) LIMIT 20000");
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $x) { $x = mb_strtolower(trim((string) $x)); if (mb_strlen($x) >= 5) $n[] = $x; }
        } catch (Throwable) {}
        return $n;
    }

    /**
     * A prompt built from our own vocabulary (P2-2): each field checked against where it must come from; a value that
     * isn't ours (a person's words) refuses the whole prompt. Returns the text, or throws InvalidArgumentException.
     */
    public static function prompt(PDO $pdo, string $template, array $vars): string
    {
        if (!isset(self::PROMPTS[$template])) throw new InvalidArgumentException('No such prompt.');
        [$text, $fields] = self::PROMPTS[$template];
        $put = [];
        foreach ($fields as $k => $vocab) {
            $v = $vars[$k] ?? null;
            if ($v === null) throw new InvalidArgumentException('The prompt needs ' . $k . '.');
            switch ($vocab) {
                case 'page':
                    [$v] = self::safe((string) $v, $pdo); $v = mb_substr($v, 0, 60000); break;
                case 'town':
                    $st = $pdo->prepare('SELECT COUNT(*) FROM zip_coordinates WHERE LOWER(city) = LOWER(?)'); $st->execute([(string) $v]);
                    if (!(int) $st->fetchColumn()) throw new InvalidArgumentException('A town not in our locations data: only our own vocabulary goes to Gemini.');
                    break;
                case 'state':
                    if (!preg_match('/^[A-Z]{2}$/', (string) $v)) throw new InvalidArgumentException('A state must be its two letters.');
                    break;
                case 'kinds':
                    require_once __DIR__ . '/OpenPlaces.php';
                    $ks = (array) $v; foreach ($ks as $x) if (!isset(OpenPlaces::KINDS[$x])) throw new InvalidArgumentException('A kind not in OpenPlaces::KINDS: only our own vocabulary goes to Gemini.');
                    $v = implode(', ', $ks ?: array_keys(OpenPlaces::KINDS)); break;
                case 'listing':   // a listing's name as we list it (not a person's, not held)
                    $st = $pdo->prepare("SELECT category_id FROM entities WHERE name = ? AND is_suppressed = 0 LIMIT 1"); $st->execute([(string) $v]);
                    $cat = $st->fetchColumn();
                    if ($cat === false) throw new InvalidArgumentException('A name not as we list it: only our own vocabulary goes to Gemini.');
                    require_once __DIR__ . '/ListingSafety.php';
                    if (ListingSafety::isConfidential($cat !== null ? (int) $cat : null, (string) $v)) throw new InvalidArgumentException('A confidential listing never leaves in a prompt (T1).');
                    break;
            }
            $put['{' . $k . '}'] = (string) $v;
        }
        return strtr($text, $put);
    }

    // ---- the allowance (0163 §11) -----------------------------------------------------------------------------------

    /** Today's calls and the last minute's: [today, minute]. Counted from ai_jobs (failed calls included). */
    public static function usedToday(PDO $pdo): array
    {
        try {
            $st = $pdo->query("SELECT SUM(created_at >= CURDATE()) d, SUM(created_at >= NOW() - INTERVAL 1 MINUTE) m FROM " . Database::t('ai_jobs') . " WHERE provider = 'gemini' AND created_at >= CURDATE() - INTERVAL 1 DAY");
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return [(int) $r['d'], (int) $r['m']];
        } catch (Throwable) { return [0, 0]; }
    }

    /** Remembers the first time today something happened ('bgstop', 'reserve_low'): its UTC time. */
    private static function mark(PDO $pdo, string $what): void
    {
        try { require_once __DIR__ . '/SearchIndex.php'; $k = 'gemini:' . $what . ':' . gmdate('Y-m-d'); if (SearchIndex::state($pdo, $k) === '') SearchIndex::setState($pdo, $k, (string) time()); } catch (Throwable $x) {}
    }

    /**
     * Gemini's warnings (gap review F4): its allowance is paced to be used every day, so use alone never warns. Only the
     * unexpected: background work at its stop before 18:00 UTC three days running (more work than allowance: slow a feature
     * or move one back to Claude), or the visitors' reserve under 5% on a day (a visitor may wait). Lines for the case.
     */
    public static function unexpected(PDO $pdo): array
    {
        $out = [];
        try {
            require_once __DIR__ . '/SearchIndex.php';
            $early = 0;
            for ($i = 0; $i < 3; $i++) { $d = gmdate('Y-m-d', time() - $i * 86400); $t = (int) SearchIndex::state($pdo, 'gemini:bgstop:' . $d); if ($t > 0 && $t < strtotime($d . ' 18:00:00 UTC')) $early++; }
            if ($early === 3) $out[] = 'Background work reached its stop before 18:00 UTC three days running: there\'s more work than Gemini\'s free allowance. Slow a feature, or move one back to Claude (AI use).';
            foreach ([gmdate('Y-m-d'), gmdate('Y-m-d', time() - 86400)] as $d) if ((int) SearchIndex::state($pdo, 'gemini:reserve_low:' . $d) > 0) { $out[] = 'The visitors\' reserve fell under 5% on ' . $d . ': a visitor may have waited.'; break; }
        } catch (Throwable $x) {}
        return $out;
    }

    public static function limits(PDO $pdo): array
    {
        require_once __DIR__ . '/Thresholds.php';
        $g = fn (string $k, float $d) => (function () use ($pdo, $k, $d) { try { return (float) Thresholds::get($pdo, $k); } catch (Throwable) { return $d; } })();
        return ['day' => (int) $g('gemini_daily', 1000), 'minute' => (int) $g('gemini_per_minute', 15), 'reserve' => (int) $g('gemini_reserve_pct', 20)];
    }

    /** May a call go now? null, or why it waits. $onDemand: a visitor waiting (W5, Find it, a search) may use the reserve. */
    public static function room(PDO $pdo, bool $onDemand): ?string
    {
        $lim = self::limits($pdo); [$day, $min] = self::usedToday($pdo);
        if ($min >= $lim['minute']) return 'Gemini\'s ' . $lim['minute'] . ' a minute are used: it waits a minute.';
        if ($day >= $lim['day']) return 'Gemini\'s ' . $lim['day'] . ' calls for today are used: the work waits until tomorrow (no fallback to Claude).';
        $bg = (int) floor($lim['day'] * (100 - $lim['reserve']) / 100);
        // what F4 warns on (gap review F4): when background work first hit its stop each day, and a reserve under 5%
        if ($day >= $bg) self::mark($pdo, 'bgstop');
        if ($day >= (int) floor($lim['day'] * 0.95)) self::mark($pdo, 'reserve_low');
        if (!$onDemand && $day >= $bg) return 'Background work stops at ' . (100 - $lim['reserve']) . '% of today\'s calls (' . $bg . '): the rest is held for visitors waiting.';
        // paced through the day: background work no further into the allowance than the day is gone
        $spent = (time() - strtotime('today')) / 86400;
        if (!$onDemand && $day > max(20, $bg * $spent + 20)) return 'Paced through the day: ' . $day . ' calls by now is ahead of the day\'s pace.';
        return null;
    }

    // ---- the call ----------------------------------------------------------------------------------------------------

    /**
     * One read of a public page. $feature: an AI feature whose input class is public. Returns ['ok', 'text', 'why'].
     * Never sends anything the guards refuse; never falls back to Claude.
     */
    public static function read(PDO $pdo, string $feature, string $template, array $vars, bool $onDemand = false): array
    {
        $no = fn (string $why) => ['ok' => false, 'text' => '', 'why' => $why];
        if (($w = self::refused($feature)) !== null) return $no($w);
        require_once __DIR__ . '/AiClient.php';
        if (!AiClient::on('gemini')) return $no('Gemini isn\'t connected (GEMINI_API_KEY in .env).');
        require_once __DIR__ . '/Automation.php';
        if (($w = Automation::feature($pdo, $feature)) !== null) return $no($w);
        if (($w = Automation::provider('gemini')) !== null) return $no($w);
        if (($w = Automation::serviceOff($pdo, self::SERVICE)) !== null) return $no($w);
        require_once __DIR__ . '/ServiceHealth.php';
        if (($w = ServiceHealth::allowed($pdo, self::HOST)) !== null) return $no($w);   // holds, bans and staging's answer (StagingGate::hold)
        $c = AiClient::feature($pdo, $feature);
        if (!$c['enabled']) return $no('This feature is switched off on AI use.');
        if (($w = self::room($pdo, $onDemand)) !== null) return $no($w);
        try { $text = self::prompt($pdo, $template, $vars); } catch (InvalidArgumentException $x) { return $no($x->getMessage()); }
        require_once __DIR__ . '/StagingGate.php';
        $url = 'https://' . self::HOST . '/v1beta/models/' . (isset(self::MODELS[$c['model']]) ? $c['model'] : self::MODEL) . ':generateContent';
        if (($w = StagingGate::hold($url, 'Gemini: ' . $feature)) !== null) return $no($w);
        $key = AiClient::geminiKey();
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
            CURLOPT_POSTFIELDS => json_encode(['contents' => [['parts' => [['text' => $text]]]], 'generationConfig' => ['temperature' => 0]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $body = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        $j = is_string($body) ? json_decode($body, true) : null;
        $out = (string) ($j['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $ok = $code === 200 && $out !== '';
        AiClient::record($pdo, $feature, 0, $ok ? 'done' : 'failed', ['provider' => 'gemini', 'model' => $c['model'], 'in' => (int) ($j['usageMetadata']['promptTokenCount'] ?? 0), 'out' => (int) ($j['usageMetadata']['candidatesTokenCount'] ?? 0), 'micros' => 0]);   // counted per call, failed calls included ($0 on the free tier)
        if (!$ok) {
            ServiceHealth::siteFailed($pdo, $url, $code, is_string($body) ? mb_substr($body, 0, 2000) : '', $err, 'Gemini ' . $feature);
            return $no($code === 429 ? 'Gemini\'s allowance answered "too many": the work waits.' : 'Gemini didn\'t answer (' . ($code ?: $err) . '): the work waits.');
        }
        return ['ok' => true, 'text' => $out, 'why' => ''];
    }
}
