<?php
// app/lib/ServiceHealth.php — an outside service's issues: identify them, stop calling, ask for help (Jason, 2026-11-14:
// "1. if an api we use has an issue we need to identify and address said issue. 2. the system must stop trying with an
// API [that] has an issue until it is resolved. 3. The system must ask for help regarding the exact issue it is facing
// while still [performing] duties that have no issues.").
//
// One open issue per server and caller (the crawler on Railway, or the site on Bluehost: a ban on one isn't on the other):
//   1. Identify: each failure is classified (KINDS: a ban, a refused key, a used-up quota, a slow-down, a timeout, a server
//      error, an answer we can't read) and kept with its evidence: the status, what the server said, when, where.
//   2. Stop: a ban, a refused key or a used-up quota holds every call to that server until a person marks it resolved, or
//      its light probe (PROBES: one request at the slow pace the provider allows, e.g. Overpass /api/status once a day)
//      says it's lifted. A slow-down, timeouts and server errors back off: 10 minutes, doubling to 6 hours; the first
//      answer after resolves them. Never got round by changing the agent (that's what turns a name ban into an address ban).
//   3. Ask for help: one Pulse case per issue (Pulse::items, 'svc:<id>'), naming the issue and its evidence, the service's
//      contact links and "Check it now" (the probe); a passing check closes it (no Mark resolved, 0171 §7's note). Work that doesn't need that server carries on; a
//      crawler task whose service is held reports "held", never failed (CrawlService::report).
// The crawler's side: its plan carries holds() and dueProbes(), its report the issues (worker 2.5). The site's own callers
// (OpenPlaces, DeepLook) ask allowed() first and tell failed() / answered() after.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/OutsideServices.php';

final class ServiceHealth
{
    /** A ban still refused after this long (daily checks) goes to a person to evaluate (decisions/0164 §6.1, 0163 §8 stage 1). */
    public const EVAL_AFTER = 3 * 86400;

    /** kind => [label, stop|back, what it means and what to do] */
    public const KINDS = [
        'ban' => ['Refused us', 'stop', 'The service refuses our requests (403, 406 or 418): usually a block set by its operators on our name (the User-Agent) or our address. Asking again while blocked makes it worse, and changing our name to get round it earns an address ban. Ask them why and what they need from us; then press Check it now once they\'ve lifted it: a passing check closes it.'],
        'auth' => ['Our key was refused', 'stop', 'The service says our key or login isn\'t valid (401): expired, revoked, or the wrong one in the settings. Make a new key in the service\'s console and put it where the card says, then press Check it now: a passing check closes it.'],
        'quota' => ['Over its quota or plan', 'stop', 'The service says we\'ve used our allowance (402, or a quota message): calls stop until the plan renews or is raised. Check the plan and the month\'s use, then press Check it now: a passing check closes it.'],
        'rate' => ['Asked us to slow down', 'back', 'Too many requests (429). Calls wait (its Retry-After, or 10 minutes, doubling) and start again by themselves; a case opens only if it keeps happening.'],
        'timeout' => ['Didn\'t answer', 'back', 'No answer in time, or the connection was refused or reset. Calls wait and are tried again by themselves; a case opens only if it keeps happening (a server that\'s down for long, or our queries too big for it).'],
        'server' => ['Its server failed', 'back', 'The service answered with a server error (5xx). Calls wait and are tried again by themselves; a case opens only if it keeps happening.'],
        'malformed' => ['Answered with something we can\'t read', 'back', 'The answer wasn\'t what the service promises (not JSON, or its own error remark). Calls wait and are tried again; if it keeps happening the service may have changed how it answers.'],
    ];
    public const CALLERS = ['crawler' => 'the crawler, on Railway', 'site' => 'the site, on Bluehost'];
    /**
     * Light probes, host => [url, minutes between, agent]: one request at the pace the provider allows, sent from where
     * the issue is (the crawler's plan, or the site). 'as_refused': exactly the agent that was refused, so the answer says
     * whether that name is still blocked (Overpass's status page also names the caller's address and its slots).
     */
    public const PROBES = [
    ];
    /**
     * A service's servers, kept as settings (Jason, 2026-11-14: "server lists become settings, not code"): service => role
     * => [label, the path asked]. Each is on the service's card in Outside services: add one, pause one, see why one is
     * held. The pre-use check (no open issue, not backing off, inside the budget and pace) is the only gate: a server is
     * never taken off a list to stop it, or put back to start it.
     */
    public const ROLES = ['osm' => ['overpass' => ['Overpass servers (OpenStreetMap queries)', '/api/interpreter']]];
    /**
     * Overpass is retired (Jason, 2026-11-19, "Stop entirely"): OpenStreetMap's community answered our statement
     * (community.openstreetmap.org/t/150369) that an app's fair share of the public Overpass servers is about 50 requests a
     * day, and "Stop using the public OSM overpass server altogether. It is intended to help OSM editors and not as a
     * backend for unrelated apps/websites." No Overpass server is handed out, probed or budgeted; we read our own copy
     * (Geofabrik's weekly state files, open_places). For good: nothing retries or reactivates it.
     */
    public const OSM_RETIRED = 'Retired 2026-11-19: OpenStreetMap\'s community asked apps like ours not to use the public Overpass servers (community.openstreetmap.org/t/150369), so we stopped entirely. We read our own copy of OpenStreetMap (Geofabrik, weekly) instead.';
    public const OSM_RETIRED_URL = 'https://community.openstreetmap.org/t/150369';
    /** Before the database update: the servers as they were seeded (decisions/0065's mirrors; overpass-api.de held by its ban). */
    private const SEED = ['overpass' => ['https://overpass-api.de/api/interpreter', 'https://overpass.private.coffee/api/interpreter', 'https://overpass.kumi.systems/api/interpreter', 'https://maps.mail.ru/osm/tools/overpass/api/interpreter']];

    /** A role's servers in the order they're tried: [{id, url, host, paused, paused_why, paused_at, paused_by_name}]; paused ones only with $all. */
    public static function servers(PDO $pdo, string $service, string $role, bool $all = false): array
    {
        try {
            $st = $pdo->prepare('SELECT s.*, u.display_name paused_by_name FROM service_servers s LEFT JOIN users u ON u.id = s.paused_by WHERE s.service = ? AND s.role = ? ORDER BY s.sort, s.id');
            $st->execute([$service, $role]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            try { $st = $pdo->prepare('SELECT * FROM service_servers WHERE service = ? AND role = ? ORDER BY sort, id'); $st->execute([$service, $role]); $rows = $st->fetchAll(PDO::FETCH_ASSOC); }
            catch (Throwable $e2) { $rows = array_map(fn ($u, $i) => ['id' => 0, 'url' => $u, 'paused' => 0, 'paused_why' => null, 'paused_at' => null], self::SEED[$role] ?? [], array_keys(self::SEED[$role] ?? [])); }
        }
        $out = [];
        $retired = $service === 'osm' && $role === 'overpass';   // Overpass retired (OSM_RETIRED): listed, never handed out
        foreach ($rows as $r) {
            $r['host'] = strtolower((string) parse_url((string) $r['url'], PHP_URL_HOST));
            if ($retired) { if (!$all) continue; $r['paused'] = 1; $r['paused_why'] = self::OSM_RETIRED; $r['retired'] = true; }
            if ($all || !(int) $r['paused']) $out[] = $r;
        }
        return $out;
    }

    /** Adds a server to a role (https only, a real host name, not there already). Returns its id. */
    public static function addServer(PDO $pdo, string $service, string $role, string $url, int $uid): int
    {
        if (!isset(self::ROLES[$service][$role])) throw new InvalidArgumentException('That service has no server list.');
        $url = trim($url);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!preg_match('#^https://#i', $url) || !preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) || strlen($url) > 255)
            throw new InvalidArgumentException('Give the server\'s full address, starting https:// (ending /api/interpreter).');
        if (!str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', self::ROLES[$service][$role][1])) throw new InvalidArgumentException('Its address should end ' . self::ROLES[$service][$role][1] . '.');
        foreach (self::servers($pdo, $service, $role, true) as $s) if ($s['host'] === $host) throw new InvalidArgumentException($host . ' is on the list already.');
        $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) + 1 FROM service_servers')->fetchColumn();
        $pdo->prepare('INSERT INTO service_servers (service, role, url, sort, paused, added_by, added_at) VALUES (?, ?, ?, ?, 0, ?, UTC_TIMESTAMP())')->execute([$service, $role, $url, $sort, $uid ?: null]);
        return (int) $pdo->lastInsertId();
    }

    /** A person pauses a server (never asked while paused) or resumes it. */
    public static function pauseServer(PDO $pdo, int $id, bool $pause, int $uid, string $why = ''): void
    {
        $pdo->prepare('UPDATE service_servers SET paused = ?, paused_why = ?, paused_by = ?, paused_at = ' . ($pause ? 'UTC_TIMESTAMP()' : 'NULL') . ' WHERE id = ?')
            ->execute([$pause ? 1 : 0, $pause ? (mb_substr(trim($why), 0, 255) ?: null) : null, $pause ? ($uid ?: null) : null, $id]);
    }

    /** The service a host belongs to: its card's hosts, else a server added to one of its lists. */
    public static function serviceOf(PDO $pdo, string $host): string
    {
        $s = OutsideServices::serviceOfHost($host);
        if ($s !== '') return $s;
        try { foreach ($pdo->query('SELECT service, url FROM service_servers')->fetchAll(PDO::FETCH_ASSOC) as $r) if (strtolower((string) parse_url((string) $r['url'], PHP_URL_HOST)) === strtolower($host)) return (string) $r['service']; } catch (Throwable $e) {}
        return '';
    }

    /** A server's state for its card: [tone ok|bad|info, what it says, the issue's case key or ''] */
    public static function serverState(PDO $pdo, array $s): array
    {
        if ((int) $s['paused']) return ['info', 'Paused' . (($s['paused_by_name'] ?? '') !== '' ? ' by ' . $s['paused_by_name'] : '') . ($s['paused_at'] ? ' on ' . self::fmt((string) $s['paused_at']) : '') . (($s['paused_why'] ?? '') !== '' ? ': ' . $s['paused_why'] : '') . '. Never asked while paused.', ''];
        $why = [];
        foreach (array_keys(self::CALLERS) as $c) {
            $r = self::open($pdo, $s['host'], $c); if (!$r) continue;
            $k = self::KINDS[$r['kind']] ?? [$r['kind'], 'back'];
            $why[] = [self::holding($r), $k[0] . ' to ' . self::CALLERS[$c] . ' since ' . self::fmt((string) $r['first_at']) . (self::holding($r) ? ($k[1] === 'stop' ? ': held until resolved' . ($r['probe_url'] && !self::noProbe($r) ? ' or its daily probe answers' : '') : ': ' . lcfirst(self::waitWords($r))) : ': ' . lcfirst(self::waitWords($r))) . ' (issue #' . (int) $r['id'] . ')', 'svc:' . (int) $r['id']];
        }
        if (!$why) return ['ok', 'In use: asked in this order, inside the day\'s budget and pace.', ''];
        $held = array_filter($why, fn ($w) => $w[0]);
        return [$held ? 'bad' : 'info', implode(' · ', array_column($why, 1)), (string) $why[0][2]];
    }

    /**
     * No contact while it's held (Jason, 2026-11-18: Overpass "in the queue as an issue a person resolves"): an
     * OpenStreetMap server that refused us (a ban, a key, a quota) gets no probe, not even its status page, until a person
     * marks it resolved after OpenStreetMap releases us. Its alarm never clears by itself.
     */
    public static function noProbe(array $r): bool
    {
        return (string) ($r['service'] ?? '') === 'osm' && (self::KINDS[(string) ($r['kind'] ?? '')][1] ?? 'back') === 'stop';
    }

    /**
     * Overpass retired (OSM_RETIRED): every open OpenStreetMap issue and its alarm, and "Every Overpass server is held",
     * closed once as "Retired: we no longer use Overpass", never held for a person. Called by the board's watch and by any
     * late failure. How many closed.
     */
    public static function retireOsm(PDO $pdo): int
    {
        if (!self::ready($pdo)) return 0;
        require_once __DIR__ . '/ControlAlarms.php';
        $how = 'Retired: we no longer use Overpass. ' . self::OSM_RETIRED;
        $n = 0;
        try {
            $ids = $pdo->query("SELECT id FROM service_issues WHERE service = 'osm' AND state = 'open'")->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) {
                $pdo->prepare("UPDATE service_issues SET state = 'resolved', resolved_at = UTC_TIMESTAMP(), resolved_by = NULL, resolved_how = ?, hold_until = NULL, next_probe_at = NULL WHERE service = 'osm' AND state = 'open'")->execute([mb_substr($how, 0, 255)]);
                foreach ($ids as $id) { ControlAlarms::closeKey($pdo, 'svc:issue:' . (int) $id, $how); $n++; }
            }
            if (ControlAlarms::openOf($pdo, 'svc:osm:held')) { ControlAlarms::closeKey($pdo, 'svc:osm:held', $how); $n++; }
        } catch (Throwable $x) { error_log('retire osm: ' . $x->getMessage()); }
        return $n;
    }

    /** A server added to the Overpass list gets the same light probe: its own /api/status, every 6 hours. */
    private static function probeFor(PDO $pdo, string $host): ?array
    {
        return null;   // Overpass retired (OSM_RETIRED): its servers are never asked, not even their status pages
    }

    private const BACK_FIRST = 10;     // minutes a back-off first holds
    private const BACK_MAX = 360;      // and at most
    private const CASE_AFTER = 6;      // back-off failures in a row before it's a case for a person (or 6 hours open)

    public static function ready(PDO $pdo): bool
    {
        static $r = null;
        if ($r !== null) return $r;
        try { $pdo->query('SELECT 1 FROM service_issues LIMIT 0'); return $r = true; } catch (Throwable $e) { return $r = false; }
    }

    /** What kind of issue a failure is ('' when it isn't the service's: a 404 is a missing page). $err: no answer at all. */
    public static function classify(int $status, string $body = '', string $err = '', string $host = ''): string
    {
        $b = strtolower($body);
        // a service's own codes first, from its manifest (service-defs.php 'answers'): Tavily's 432 is its plan's limit
        if ($status > 0 && $host !== '' && ($ans = self::answersOf($host)) && isset($ans[$status])) return (string) $ans[$status];
        if ($status === 401) return 'auth';
        if ($status === 402) return 'quota';
        if ($status === 429) return preg_match('/quota|credit|exceeded your|monthly|plan limit|usage limit|billing/', $b) ? 'quota' : 'rate';
        if (in_array($status, [403, 406, 418, 451], true)) {
            if (preg_match('/quota|credits?\b|exceeded|billing/', $b)) return 'quota';
            if (preg_match('/api[ _-]?key|invalid key|unauthori[sz]ed|invalid token|subscription token/', $b)) return 'auth';
            return 'ban';
        }
        // a 503 whose answer asks us to slow down (S3's SlowDown, as data.commoncrawl.org sends: "Please reduce your request
        // rate") is a request to slow down, not a broken server: a rate wait, as a 429 is
        if ($status === 503 && preg_match('/slowdown|reduce your request rate/', $b)) return 'rate';
        if ($status >= 500 && $status <= 599) return 'server';
        if ($status > 0) return '';
        return $err !== '' ? 'timeout' : '';
    }

    /** A host's service's own answers ([status => kind]) from its manifest, or []. */
    public static function answersOf(string $host): array
    {
        static $defs = null;
        if ($defs === null) { $defs = []; try { $defs = (array) (require __DIR__ . '/service-defs.php'); } catch (Throwable $x) {} }
        $h = strtolower($host);
        foreach ($defs as $d) { if (empty($d['answers'])) continue; foreach ((array) ($d['hosts'] ?? []) as $dh) if ($h === $dh || str_ends_with($h, '.' . $dh)) return (array) $d['answers']; }
        return [];
    }

    /** The open issue at a host for a caller, or null. */
    public static function open(PDO $pdo, string $host, string $caller): ?array
    {
        if (!self::ready($pdo)) return null;
        $st = $pdo->prepare("SELECT * FROM service_issues WHERE host = ? AND caller = ? AND state = 'open' ORDER BY id DESC LIMIT 1");
        $st->execute([strtolower($host), $caller]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** Every open issue (newest first). */
    public static function openAll(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        try { return $pdo->query("SELECT * FROM service_issues WHERE state = 'open' ORDER BY last_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; }
    }

    /** Whether an issue holds calls now. */
    /**
     * Whether an issue holding its calls holds what needs it (a gate, a service's status): a refusal, a refused key or a
     * used-up quota at once; a back-off only once it has tripped at its case limit (its alarm a trip). 0171 §6, 3c.
     */
    public static function spentHold(PDO $pdo, array $r): bool
    {
        if ((self::KINDS[$r['kind']][1] ?? 'back') === 'stop') return true;
        try { require_once __DIR__ . '/ControlAlarms.php'; $a = ControlAlarms::openOf($pdo, 'svc:issue:' . (int) $r['id']); return $a !== null && $a['level'] === 'trip'; }
        catch (Throwable $x) { return true; }
    }

    public static function holding(array $r): bool
    {
        if (($r['state'] ?? '') !== 'open') return false;
        if ((self::KINDS[$r['kind']][1] ?? 'back') === 'stop') return true;
        return $r['hold_until'] !== null && strtotime((string) $r['hold_until'] . ' UTC') > time();
    }

    /**
     * Records one failure (or several of the same, $n) at a host. $ev: {status, body, error, what, task, at, retry_after}.
     * Returns the issue's id (0 before the database update, or when it isn't an issue of the service's).
     */
    public static function failed(PDO $pdo, string $host, string $kind, string $caller, array $ev, int $n = 1): int
    {
        $host = strtolower(trim($host)); $svc = self::serviceOf($pdo, $host);
        if ($host === '' || $svc === '' || !isset(self::KINDS[$kind]) || !isset(self::CALLERS[$caller]) || !self::ready($pdo)) return 0;
        $n = max(1, min(10000, $n));
        $e = ['status' => (int) ($ev['status'] ?? 0), 'body' => mb_substr(trim((string) ($ev['body'] ?? '')), 0, 300), 'error' => mb_substr((string) ($ev['error'] ?? ''), 0, 200),
              'what' => mb_substr((string) ($ev['what'] ?? ''), 0, 120), 'task' => mb_substr((string) ($ev['task'] ?? ''), 0, 40),
              'at' => self::utc((string) ($ev['at'] ?? '')), 'retry_after' => mb_substr((string) ($ev['retry_after'] ?? ''), 0, 20)];
        $stop = self::KINDS[$kind][1] === 'stop';
        $r = self::open($pdo, $host, $caller);
        if ($r) {
            $evs = json_decode((string) $r['evidence'], true) ?: [];
            $evs['latest'] = $e;
            // a stop kind outranks a back-off one: a ban after timeouts is a ban
            $k = (self::KINDS[$r['kind']][1] ?? '') === 'stop' && !$stop ? (string) $r['kind'] : $kind;
            $hits = (int) $r['hits'] + $n;
            $pdo->prepare('UPDATE service_issues SET kind = ?, hits = ?, last_at = ?, evidence = ?, hold_until = ? WHERE id = ?')
                ->execute([$k, $hits, $e['at'], json_encode($evs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), self::holdUntil($k, $hits, $e['retry_after']), (int) $r['id']]);
            self::alarm($pdo, (int) $r['id'], $svc, $host, $k, $e, $caller);
            return (int) $r['id'];
        }
        $p = self::PROBES[$host] ?? self::probeFor($pdo, $host);
        $pdo->prepare("INSERT INTO service_issues (service, host, caller, kind, state, hits, first_at, last_at, evidence, hold_until, probe_url, probe_every_min, probe_agent, next_probe_at) VALUES (?, ?, ?, ?, 'open', ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$svc, $host, $caller, $kind, $n, $e['at'], $e['at'], json_encode(['first' => $e, 'latest' => $e], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                       self::holdUntil($kind, $n, $e['retry_after']), $p[0] ?? null, $p[1] ?? null, $p[2] ?? null, $p && $stop && !self::noProbe(['service' => $svc, 'kind' => $kind]) ? gmdate('Y-m-d H:i:s', time() + 60 * (int) $p[1]) : null]);
        $id = (int) $pdo->lastInsertId();
        self::alarm($pdo, $id, $svc, $host, $kind, $e, $caller);
        return $id;
    }

    /**
     * A back-off kind holds 10 minutes, doubling with each failure in a row, at most 6 hours. A server that says when to
     * come back (Retry-After in seconds or as a date, or Overpass's status page slot time, sent as seconds) is obeyed
     * exactly (Jason, 2026-11-14), at least a minute.
     */
    private static function holdUntil(string $kind, int $hits, string $retryAfter = ''): ?string
    {
        if ((self::KINDS[$kind][1] ?? 'back') === 'stop') return null;
        $ra = trim($retryAfter);
        if ($ra !== '') {
            $sec = ctype_digit($ra) ? (int) $ra : (($t = strtotime($ra)) !== false ? $t - time() : -1);
            if ($sec >= 0) return gmdate('Y-m-d H:i:s', time() + max(60, min(self::BACK_MAX * 60, $sec)));
        }
        // a 429 with no Retry-After: twice the usual wait (20 minutes, doubling), so our pace to that host halves
        $min = min(self::BACK_MAX, self::BACK_FIRST * ($kind === 'rate' ? 2 : 1) * (2 ** min(6, max(0, $hits - 1))));
        return gmdate('Y-m-d H:i:s', time() + 60 * $min);
    }

    /** A server answered: its back-off issues for that caller are over (a ban, a key or a quota waits for a person or its probe). */
    public static function answered(PDO $pdo, string $host, string $caller): void
    {
        if (!self::ready($pdo)) return;
        $r = self::open($pdo, $host, $caller);
        if ($r && (self::KINDS[$r['kind']][1] ?? '') === 'back') self::resolve($pdo, (int) $r['id'], 0, 'It answered again.');
        // the site's own check answering closes the crawler's back-off at that host too: a fix to 0175 §3's rule ("closes as
        // Recovered when its check passes"), which wasn't true for crawler-held issues, whose calls the trip holds, so they
        // could never answer (Common Crawl's #35, 2026-11-21). Never a ban, a key or a quota (they wait for a person or their
        // probe), and never a 429 still in the wait it asked for.
        if ($caller === 'site' && ($c = self::open($pdo, $host, 'crawler')) && (self::KINDS[$c['kind']][1] ?? '') === 'back') {
            $hu = (string) ($c['hold_until'] ?? '');
            $waiting = $c['kind'] === 'rate' && $hu !== '' && !self::tripHeld($c) && strtotime($hu . ' UTC') > time();
            if (!$waiting) self::resolve($pdo, (int) $c['id'], 0, 'It answered the site\'s check again (' . gmdate('M j, H:i') . ' UTC): the crawler\'s calls start again.');
        }
    }

    /** Marks an issue resolved: calls start again (a new failure opens a new issue). */
    public static function resolve(PDO $pdo, int $id, int $uid, string $how): bool
    {
        if (!self::ready($pdo)) return false;
        $st = $pdo->prepare("UPDATE service_issues SET state = 'resolved', resolved_at = UTC_TIMESTAMP(), resolved_by = ?, resolved_how = ?, hold_until = NULL WHERE id = ? AND state = 'open'");
        $st->execute([$uid ?: null, mb_substr($how, 0, 255), $id]);
        if ($st->rowCount() > 0) { try { require_once __DIR__ . '/ControlAlarms.php';
                // closed by its check (uid 0) or a person: its alarm closes either way, trips included (Jason, 2026-11-19: "If this
                // was resolved by system or marked as resolved by admin it should be handled"): a system-closed trip goes through
                // ControlAlarms::resolve, which checks, closes it saying how, and starts again what it stopped
                // only a failure that clears by itself (a back-off kind: a timeout, a server error, a 429) closes its trip on
                // a passing check; a refusal, a refused key or a used-up quota (a stop kind) always waits for a person's
                // button (principle 1's exception; the session manager, 2026-11-19)
                $kd = $pdo->prepare('SELECT kind FROM service_issues WHERE id = ?'); $kd->execute([$id]); $back = (self::KINDS[(string) $kd->fetchColumn()][1] ?? 'back') === 'back';
                $close = function (string $k, string $h) use ($pdo, $uid, $back) {
                    if ($uid > 0) return ControlAlarms::closeKey($pdo, $k, $h);
                    $o = ControlAlarms::openOf($pdo, $k);
                    if ($back && $o && $o['level'] === 'trip' && !empty($o['tripped_at'])) return ControlAlarms::resolve($pdo, (int) $o['id'], 0, 'the system', 'Cleared by its check: ' . $h);
                    return ControlAlarms::clear($pdo, $k, $h);
                };
                $close('svc:issue:' . $id, $how);
                $sv = $pdo->prepare('SELECT service FROM service_issues WHERE id = ?'); $sv->execute([$id]);
                if ($sv->fetchColumn() === 'osm' && !array_filter(self::openAll($pdo), fn ($i) => $i['service'] === 'osm' && (self::KINDS[$i['kind']][1] ?? 'back') === 'stop'))
                    $close('svc:osm:held', 'No Overpass server refuses us now: ' . $how); } catch (Throwable $x) {} return true; }
        return false;
    }

    /**
     * Its alarm (decisions/0171 §5, §8: one alarm per issue, release 3a): a ban, a refused key or a used-up quota holds the
     * service, a trip; a back-off (slow down, timeouts, server errors) is a warning. It clears when the issue is resolved.
     */
    public static function alarm(PDO $pdo, int $id, string $svc, string $host, string $kind, array $e, string $caller): void
    {
        try {
            require_once __DIR__ . '/ControlAlarms.php';
            $stop = (self::KINDS[$kind][1] ?? 'back') === 'stop';
            $what = (string) (self::KINDS[$kind][0] ?? $kind);
            if ($svc === 'osm') { self::retireOsm($pdo); return; }   // Overpass retired (OSM_RETIRED): no alarm, ever; what's open closes
            // a back-off is the system's own fix (0171 §5): a warning while it backs off; at its limit (CASE_AFTER failures in a
            // row, or 6 hours open) a trip, its calls held until an admin resolves it
            $r = $pdo->prepare('SELECT hits, first_at, hold_until FROM service_issues WHERE id = ?'); $r->execute([$id]); $r = $r->fetch(PDO::FETCH_ASSOC) ?: ['hits' => 1, 'first_at' => gmdate('Y-m-d H:i:s'), 'hold_until' => null];
            // a 429 asks us to go slower, not to stop (the session manager, 2026-11-19, principles 1 and 2): its back-off (Retry-After,
            // else 20 minutes doubling) is the slowing, and it stays a warning that clears itself; never spent into a trip
            $spent = !$stop && $kind !== 'rate' && ((int) $r['hits'] >= self::CASE_AFTER || strtotime((string) $r['first_at'] . ' UTC') < time() - 6 * 3600);
            ControlAlarms::raise($pdo, 'svc:issue:' . $id, 'svc:' . $svc, $stop || $spent ? 'trip' : 'warning', $stop ? 'held' : 'retry',
                $host . ': ' . $what . ' (' . (self::CALLERS[$caller] ?? $caller) . ')',
                $stop ? 'Nothing calls ' . $host . ' until this is resolved; what needs it waits, everything else runs.'
                      : ($spent ? 'Its back-off ran out (' . (int) $r['hits'] . ' failures in a row): calls to ' . $host . ' are held until an admin resolves it.' : ($kind === 'rate' ? 'It asked us to slow down: calls to ' . $host . ' wait twice as long as usual, longer each time, and start again by themselves. A request to go slower, never a stop.' : 'Calls to ' . $host . ' wait a little and try again by themselves, longer each time (' . (int) $r['hits'] . ' of ' . self::CASE_AFTER . ').')),
                $stop || $spent ? 'Read the evidence on its card, fix the cause (its key, its block, its server), then press Check and start again: it checks first, and closes it when the check passes.' : 'Nothing, unless it keeps happening: it clears when the server answers again.',
                mb_substr(trim(($e['status'] ? $e['status'] . ' ' : '') . ($e['error'] !== '' ? $e['error'] : $e['body'])), 0, 255), $stop || $spent ? [] : ($kind === 'rate' ? self::rateFix($host, (string) $r['hold_until'], $id) : ['Check it now', 'svccheck', (string) $id]), true, false);
        } catch (Throwable $x) { error_log('service alarm: ' . $x->getMessage()); }
    }

    /**
     * A 429's Check it now: greyed, never past the wait or its Retry-After (the session manager, 2026-11-19: a press would
     * call the host straight after it asked us to slow down). [label, 'svccheck', id, '#wait:<the reason and the time>'].
     */
    public static function rateFix(string $host, string $holdUntil, int $id): array
    {
        $at = $holdUntil !== '' ? strtotime($holdUntil . ' UTC') : false;
        return ['Check it now', 'svccheck', (string) $id, '#wait:' . $host . ' asked us to slow down; next try ' . ($at && $at > time() ? 'at ' . gmdate('M j, g:i A', $at) . ' UTC' : 'soon') . ', by itself. Nothing to press.'];
    }

    /**
     * A rate limit tripped before 5a: a warning again, its calls back on its back-off (not held until a person acts), logged.
     * The watch does it (the session manager, 2026-11-19: Wikidata's "asked us to slow down" was a trip). Returns how many.
     */
    public static function liftRateTrips(PDO $pdo): int
    {
        if (!self::ready($pdo)) return 0;
        $n = 0;
        try {
            require_once __DIR__ . '/ControlAlarms.php';
            foreach ($pdo->query("SELECT a.id aid, i.id iid, i.hits FROM control_alarms a JOIN service_issues i ON a.akey = CONCAT('svc:issue:', i.id) WHERE a.cleared_at IS NULL AND a.level = 'trip' AND i.state = 'open' AND i.kind = 'rate'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $hu = (string) self::holdUntil('rate', (int) $r['hits']);
                $pdo->prepare('UPDATE service_issues SET hold_until = ? WHERE id = ?')->execute([$hu, (int) $r['iid']]);
                $h = (string) $pdo->query('SELECT host FROM service_issues WHERE id = ' . (int) $r['iid'])->fetchColumn();
                $pdo->prepare("UPDATE control_alarms SET level = 'warning', tripped_at = NULL, stopped = NULL, means = ?, fix = ? WHERE id = ?")
                    ->execute(['It asked us to slow down (a 429): calls wait longer each time and start again by themselves. A request to go slower, never a stop; this clears itself when it answers.', json_encode(self::rateFix($h, $hu, (int) $r['iid'])), (int) $r['aid']]);
                try { require_once __DIR__ . '/ControlLog.php'; ControlLog::add($pdo, 'resolve', 'svc:' . (string) $pdo->query('SELECT service FROM service_issues WHERE id = ' . (int) $r['iid'])->fetchColumn(), 'Issue #' . (int) $r['iid'] . ': a rate limit is a warning, not a trip', 'the system', 'Its calls are back on their back-off.'); } catch (Throwable $x) {}
                $n++;
            }
        } catch (Throwable $x) { error_log('rate trips: ' . $x->getMessage()); }
        return $n;
    }

    /** A trip holds a backing-off server's calls until it's resolved (a refusal already waits for a person). */
    /** A trip's hold has no "until": it lasts until its check passes (decisions/0175 §3). Stored as this far-off time. */
    public const TRIP_HOLD = '2099-01-01 00:00:00';

    /** Held by a trip (the system's pause, decisions/0175), not a timed back-off. */
    public static function tripHeld(array $r): bool { return (string) ($r['hold_until'] ?? '') >= self::TRIP_HOLD; }

    /** How a back-off issue's wait reads now: the system's pause, a wait still running, or one that's over (words follow the state). */
    public static function waitWords(array $r): string
    {
        if (self::tripHeld($r)) return 'Paused by the system (a trip): no calls until its check passes';
        $hu = (string) ($r['hold_until'] ?? '');
        if ($hu !== '' && strtotime($hu . ' UTC') > time()) return 'Backing off until ' . self::fmt($hu);
        return 'Its wait ended' . ($hu !== '' ? ' ' . self::fmt($hu) : '') . ': the next call tries it again';
    }

    public static function holdForTrip(PDO $pdo, int $id): bool
    {
        if (!self::ready($pdo)) return false;
        $st = $pdo->prepare("UPDATE service_issues SET hold_until = '" . self::TRIP_HOLD . "' WHERE id = ? AND state = 'open' AND kind IN ('" . implode("','", array_keys(array_filter(self::KINDS, fn ($k) => $k[1] !== 'stop'))) . "')");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** "Check it now": the probe goes out with the next run (or the site's next call), not at once from a click. */
    public static function probeSoon(PDO $pdo, int $id): bool
    {
        if (!self::ready($pdo)) return false;
        $r = $pdo->prepare('SELECT * FROM service_issues WHERE id = ?'); $r->execute([$id]); $r = $r->fetch(PDO::FETCH_ASSOC);
        if ($r && self::noProbe($r)) return false;   // no contact while held: a person resolves it
        $st = $pdo->prepare("UPDATE service_issues SET next_probe_at = UTC_TIMESTAMP() WHERE id = ? AND state = 'open' AND probe_url IS NOT NULL");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    /** What a probe's answer means, and the issue settled when it says the block is lifted. $p: {status, body, at}. */
    public static function probed(PDO $pdo, int $id, array $p): string
    {
        if (!self::ready($pdo)) return '';
        $st = $pdo->prepare('SELECT * FROM service_issues WHERE id = ?'); $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_ASSOC); if (!$r) return '';
        $status = (int) ($p['status'] ?? 0); $body = mb_substr(trim((string) ($p['body'] ?? '')), 0, 600);
        $says = $status === 200 ? 'answered 200' : ($status ? 'answered ' . $status : 'didn\'t answer');
        // Overpass's status page names the caller's address and its free slots: the answer that tells a name ban from an address ban
        if ($status === 200 && preg_match('/Connected as:\s*(\S+)/i', $body, $m)) $says .= ' (connected as ' . $m[1] . (preg_match('/(\d+)\s+slots? available now/i', $body, $s2) ? ', ' . $s2[1] . ' slots free' : '') . ')';
        $last = json_encode(['status' => $status, 'body' => $body, 'at' => self::utc((string) ($p['at'] ?? '')), 'says' => $says], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $every = max(60, (int) ($r['probe_every_min'] ?: 1440));
        $pdo->prepare('UPDATE service_issues SET last_probe = ?, next_probe_at = ? WHERE id = ?')->execute([$last, gmdate('Y-m-d H:i:s', time() + 60 * $every), $id]);
        // the automatic return (Jason, 2026-11-14): a 200 resolves it, Pulse tells the team (cameBack), and OpenStreetMap runs at
        // half for osm_ramp_days (osmRamping); refused again, a new issue holds it at once
        // decisions/0164 §6.1 (Jason, 2026-11-14): a ban or a refused key is never lifted by itself. A 200 says "looks lifted"
        // with Reactivate for a person (The engine needs a person); a quota hold still returns by itself. Still refused after
        // 3 days of daily checks, a person evaluates (Reactivate, keep checking daily or weekly, or stop) and the checks
        // pause until they decide.
        if ($r['state'] === 'open') {
            require_once __DIR__ . '/EngineCase.php';
            $when = self::fmt(self::utc((string) ($p['at'] ?? '')));
            if ($status === 200 && $r['kind'] === 'quota') self::resolve($pdo, $id, 0, 'Its probe answered 200 on ' . $when . ': the quota hold is over.');
            elseif ($status === 200) {
                EngineCase::raise($pdo, 'service', 'issue:' . $id, self::KINDS[$r['kind']][0] . ' at ' . $r['host'] . ' looks lifted: its check answered 200 on ' . $when . '. Reactivate it, or keep it held.', ['issue' => $id, 'host' => (string) $r['host'], 'lifted' => true]);
                $pdo->prepare('UPDATE service_issues SET next_probe_at = NULL WHERE id = ?')->execute([$id]);   // no more checks until a person decides
            } elseif (strtotime((string) $r['first_at'] . ' UTC') < time() - self::EVAL_AFTER) {
                EngineCase::raise($pdo, 'service', 'issue:' . $id, self::KINDS[$r['kind']][0] . ' at ' . $r['host'] . ' since ' . self::fmt((string) $r['first_at']) . ': still refused after 3 days of daily checks (' . $says . '). Evaluate it.', ['issue' => $id, 'host' => (string) $r['host'], 'lifted' => false]);
                $pdo->prepare('UPDATE service_issues SET next_probe_at = NULL WHERE id = ?')->execute([$id]);
            }
        }
        return $says;
    }

    /** The holds for a caller: host => why (the crawler's plan sends them; the worker never calls a held host). */
    public static function holds(PDO $pdo, string $caller = 'crawler'): array
    {
        $out = [];
        foreach (self::openAll($pdo) as $r) if ($r['caller'] === $caller && self::holding($r)) $out[(string) $r['host']] = self::KINDS[$r['kind']][0] . ' (issue #' . (int) $r['id'] . ')';
        // services a person paused (System Configuration → Automation): every host of theirs is held the same way, so a
        // task that needs one reports "held", never failed
        try { require_once __DIR__ . '/Automation.php'; foreach (Automation::pausedHosts($pdo) as $h => $w) $out[$h] ??= $w; } catch (Throwable $e) { error_log('service holds: ' . $e->getMessage()); }
        // sites that block us (decisions/0164 §1.2, P1-2): held until a person's Reactivate, never tried by themselves
        try { require_once __DIR__ . '/EngineCase.php'; foreach (EngineCase::heldSites($pdo) as $h => [$w]) { $out[$h] ??= 'Blocks us: held for a person\'s Reactivate'; $out['www.' . $h] ??= 'Blocks us: held for a person\'s Reactivate'; } } catch (Throwable $e) { error_log('site holds: ' . $e->getMessage()); }
        return $out;
    }

    /** The probes due for a caller: [{id, host, url, agent}]; each is pushed on so it goes out once. */
    public static function dueProbes(PDO $pdo, string $caller = 'crawler'): array
    {
        if (!self::ready($pdo)) return [];
        $out = [];
        try {
            $st = $pdo->prepare("SELECT * FROM service_issues WHERE state = 'open' AND caller = ? AND probe_url IS NOT NULL AND next_probe_at IS NOT NULL AND next_probe_at <= UTC_TIMESTAMP() LIMIT 5");
            $st->execute([$caller]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (self::noProbe($r)) { $pdo->prepare('UPDATE service_issues SET next_probe_at = NULL WHERE id = ?')->execute([(int) $r['id']]); continue; }   // no contact while held
                // pushed on by its interval now, so a run that never reports back can't send it again before then
                $pdo->prepare('UPDATE service_issues SET next_probe_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() + 60 * max(60, (int) $r['probe_every_min'])), (int) $r['id']]);
                $out[] = ['id' => (int) $r['id'], 'host' => (string) $r['host'], 'url' => (string) $r['probe_url'], 'agent' => (string) ($r['probe_agent'] ?? '')];
            }
        } catch (Throwable $e) {}
        return $out;
    }

    /**
     * The site's own callers: null when the host may be called now, else why not. A due probe for the site goes out here,
     * once (a short GET), before saying. $gated: staging's gate was already asked for this call (held() asks it first).
     */
    public static function allowed(PDO $pdo, string $host, bool $gated = false): ?string
    {
        require_once __DIR__ . '/Automation.php';
        if (($w = Automation::hostOff($pdo, $host)) !== null) return $w;   // paused by a person (System Configuration → Automation): a status, never an issue
        require_once __DIR__ . '/StagingGate.php';
        if (!$gated && ($w = StagingGate::hold('https://' . $host . '/', 'a call to ' . $host)) !== null) return $w;   // staging's answer (decisions/0165 §1); null at once on live
        $r = self::open($pdo, $host, 'site');
        if (!$r || !self::holding($r)) return null;
        if ($r['probe_url'] && $r['next_probe_at'] !== null && !self::noProbe($r) && strtotime((string) $r['next_probe_at'] . ' UTC') <= time() && function_exists('curl_init')) {
            $pdo->prepare('UPDATE service_issues SET next_probe_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s', time() + 60 * max(60, (int) $r['probe_every_min'])), (int) $r['id']]);
            $ch = curl_init((string) $r['probe_url']);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_USERAGENT => self::agent($r['probe_agent'] === 'as_refused')]);
            $b = curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            self::probed($pdo, (int) $r['id'], ['status' => $c, 'body' => is_string($b) ? strip_tags($b) : '', 'at' => gmdate('c')]);
            if ($c === 200 && $r['kind'] === 'quota') return null;   // a ban waits for a person's Reactivate (decisions/0164 §6.1)
        }
        return self::KINDS[$r['kind']][0] . ' since ' . self::fmt((string) $r['first_at']) . ' (issue #' . (int) $r['id'] . ')';
    }

    /**
     * For the site's other outside callers (Release A's list, 0163 stage 2): why a call to this URL's host must wait, or null.
     * Never throws: a broken check never stops a call on live. Staging's gate is asked first and on its own, so a failed
     * database never lets a call out of staging (2026-11-17).
     */
    public static function held(string $url): ?string
    {
        $h = (string) parse_url($url, PHP_URL_HOST);
        if ($h === '') return null;
        require_once __DIR__ . '/StagingGate.php';
        if (($w = StagingGate::hold($url, 'a call to ' . $h)) !== null) return $w;   // decisions/0165 §1; null at once on live
        try {
            require_once __DIR__ . '/Database.php';
            return self::allowed(Database::connection(), $h, true);
        } catch (Throwable $x) { return null; }
    }

    /**
     * After such a call: a failure on a host with a service card is classified and recorded (an issue, a hold), as the
     * crawler's are. A page on some other site never opens an issue (a website isn't a service). Payments record only
     * server errors and timeouts: QuickBooks answers a routine 401 before its token is refreshed. Never throws.
     */
    public static function seen(string $url, int $code, $body = '', string $err = '', string $what = '', bool $payments = false): void
    {
        if ($code >= 200 && $code < 400) return;
        try {
            $host = (string) parse_url($url, PHP_URL_HOST);
            require_once __DIR__ . '/OutsideServices.php';
            if ($host === '' || OutsideServices::serviceOfHost($host) === '') return;
            if ($payments && !in_array(self::classify($code, '', $err), ['server', 'timeout'], true)) return;
            $clean = strtok($url, '?');   // never a key from the query string
            require_once __DIR__ . '/Database.php';
            self::siteFailed(Database::connection(), (string) $clean, $code, is_string($body) ? mb_substr($body, 0, 1000) : '', $err, $what);
        } catch (Throwable $x) { error_log('service health seen: ' . $x->getMessage()); }
    }

    /** The site's own failed call: classified, recorded, held. Returns the kind ('' when it isn't the service's issue). */
    public static function siteFailed(PDO $pdo, string $url, int $status, string $body, string $curlErr = '', string $what = ''): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $kind = self::classify($status, $body, $curlErr, $host);
        if ($kind === '') return '';
        try { self::failed($pdo, $host, $kind, 'site', ['status' => $status, 'body' => trim((string) preg_replace('/\s+/', ' ', strip_tags($body))), 'error' => $curlErr, 'what' => $what ?: (string) parse_url($url, PHP_URL_PATH), 'at' => gmdate('c')]); } catch (Throwable $e) { error_log('service health: ' . $e->getMessage()); }
        return $kind;
    }

    /** The site's name to outside services, with our contact (the OSM statement's fourth promise). */
    public static function agent(bool $asRefused = false): string
    {
        $contact = preg_replace('/[^A-Za-z0-9@._+-]/', '', (string) (getenv('OUTSIDE_CONTACT') ?: self::CONTACT));
        return $asRefused || $contact === '' ? 'Traversence/1.0 (+https://traversence.com)' : 'Traversence/1.0 (+https://traversence.com; ' . $contact . ')';
    }
    /** The contact in our User-Agent (Jason, 2026-11-14: the team inbox); OUTSIDE_CONTACT in .env overrides. */
    public const CONTACT = 'support@traversence.com';

    // ---- the crawler's report (worker 2.5) -------------------------------------------------------------------------

    /** The worker's issues, probes and successful calls for one run. $b: the report body. */
    public static function fromReport(PDO $pdo, array $b): void
    {
        if (!self::ready($pdo)) return;
        try {
            foreach (array_slice((array) ($b['issues'] ?? []), 0, 40) as $i) {
                if (!is_array($i)) continue;
                // the site classifies from the evidence; the worker's own kind is a hint only (the one engine's P2: a
                // service's own answers, such as Tavily's 432 for its plan's limit, are read from its manifest)
                $h = (string) ($i['host'] ?? ''); $kind = (string) ($i['kind'] ?? '');
                if ((int) ($i['status'] ?? 0) > 0 && ($k2 = self::classify((int) $i['status'], (string) ($i['body'] ?? ''), (string) ($i['error'] ?? ''), $h)) !== '') $kind = $k2;
                self::failed($pdo, $h, $kind, 'crawler', $i, (int) ($i['n'] ?? 1));
            }
            foreach (array_slice((array) ($b['probes'] ?? []), 0, 10) as $p) if (is_array($p) && (int) ($p['id'] ?? 0)) self::probed($pdo, (int) $p['id'], $p);
            // a server that answered this run (calls more than failures, and no issue of its own this run): its back-off is over
            $bad = array_flip(array_map(fn ($i) => strtolower((string) ($i['host'] ?? '')), array_filter((array) ($b['issues'] ?? []), 'is_array')));
            $ok = [];
            foreach (array_merge([(array) ($b['calls'] ?? [])], array_map(fn ($it) => (array) ($it['calls'] ?? []), array_filter((array) ($b['items'] ?? []), 'is_array'))) as $calls)
                foreach ($calls as $h => $c) if (is_array($c) && (int) ($c[0] ?? 0) > (int) ($c[1] ?? 0) && !isset($bad[strtolower((string) $h)])) $ok[strtolower((string) $h)] = true;
            foreach (array_keys($ok) as $h) self::answered($pdo, (string) $h, 'crawler');
        } catch (Throwable $e) { error_log('service health report: ' . $e->getMessage()); }
    }

    // ---- the month's OpenStreetMap asks (the OSM statement's third promise) ----------------------------------------

    /** The town-and-kind queries asked within the cache's days (Thresholds osm_cache_days, 90): the worker doesn't ask them again. */
    public static function osmRecent(PDO $pdo): array
    {
        try { return array_map('strval', $pdo->query('SELECT qkey FROM crawl_osm_asks WHERE asked_at >= UTC_TIMESTAMP() - INTERVAL ' . self::osmCacheDays($pdo) . ' DAY ORDER BY asked_at DESC LIMIT 20000')->fetchAll(PDO::FETCH_COLUMN)); } catch (Throwable $e) { return []; }
    }

    /** How long an OpenStreetMap answer is kept before the same town (or spot) and kind is asked again. */
    public static function osmCacheDays(PDO $pdo): int
    {
        require_once __DIR__ . '/Thresholds.php';
        return max(7, (int) (Thresholds::get($pdo, 'osm_cache_days') ?? 90));
    }

    /**
     * The day's OpenStreetMap budget left for a caller (Jason, 2026-11-14: a quarter of the public guidance, 2,500 queries
     * and 250 MB a day, crawler 1,500 and site 1,000, half while a server bans us and for osm_ramp_days after):
     * {queries, bytes, limit, halved}. The day is UTC.
     */
    public static function osmBudget(PDO $pdo, string $caller): array
    {
        return ['queries' => 0, 'bytes' => 0, 'limit' => 0, 'halved' => false, 'retired' => self::OSM_RETIRED];   // Overpass retired
        require_once __DIR__ . '/Thresholds.php';
        $q = max(0, (int) (Thresholds::get($pdo, $caller === 'site' ? 'osm_daily_site' : 'osm_daily_crawler') ?? 0));
        $mb = max(0, (int) (Thresholds::get($pdo, 'osm_daily_mb') ?? 250)) * ($caller === 'site' ? 0.4 : 0.6);
        $half = self::osmRamping($pdo);
        if ($half) { $q = intdiv($q, 2); $mb /= 2; }
        $used = ['queries' => 0, 'bytes' => 0];
        try { $st = $pdo->prepare('SELECT queries, bytes FROM osm_usage WHERE day = UTC_DATE() AND caller = ?'); $st->execute([$caller]); $used = $st->fetch(PDO::FETCH_ASSOC) ?: $used; } catch (Throwable $e) {}
        return ['queries' => max(0, $q - (int) $used['queries']), 'bytes' => (int) max(0, $mb * 1000000 - (int) $used['bytes']), 'limit' => $q, 'halved' => $half];
    }

    /** Whether OpenStreetMap runs at half: a server's ban open, or the last one resolved within osm_ramp_days. */
    public static function osmRamping(PDO $pdo): bool
    {
        static $r = null;
        if ($r !== null) return $r;
        if (!self::ready($pdo)) return $r = false;
        require_once __DIR__ . '/Thresholds.php';
        $days = max(0, (int) (Thresholds::get($pdo, 'osm_ramp_days') ?? 14));
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM service_issues WHERE service = 'osm' AND kind = 'ban' AND (state = 'open' OR resolved_at >= UTC_TIMESTAMP() - INTERVAL " . $days . " DAY)");
            $st->execute();
            return $r = (int) $st->fetchColumn() > 0;
        } catch (Throwable $e) { return $r = false; }
    }

    /** Queries a minute at most, by caller (Thresholds osm_per_minute_crawler / _site; Jason, 2026-11-14: at most 1). */
    public static function osmPerMinute(PDO $pdo, string $caller): float
    {
        require_once __DIR__ . '/Thresholds.php';
        return max(0.0, (float) (Thresholds::get($pdo, $caller === 'site' ? 'osm_per_minute_site' : 'osm_per_minute_crawler') ?? 1));
    }

    /**
     * What one crawler run may use: the day's budget left, spread over the runs left in the (UTC) day, and no faster than the
     * pace (a 5-minute run: 5 a minute's worth). Plus the gap between queries the worker keeps. {queries, bytes, gap}.
     */
    public static function osmRun(PDO $pdo): array
    {
        $b = self::osmBudget($pdo, 'crawler');
        $pm = self::osmPerMinute($pdo, 'crawler');
        $runsLeft = max(1, (int) ceil((86400 - (time() % 86400)) / 300));
        $q = (int) min($b['queries'], ceil($b['queries'] / $runsLeft), floor($pm * 5));
        return ['queries' => max(0, $q), 'bytes' => (int) min($b['bytes'], ceil($b['bytes'] / $runsLeft) * 2), 'gap' => $pm > 0 ? (int) ceil(60 / $pm) : 300];
    }

    /** Whether the site may ask now: inside its pace (the last site query long enough ago). */
    public static function osmPaceOk(PDO $pdo, string $caller = 'site'): bool
    {
        $pm = self::osmPerMinute($pdo, $caller);
        if ($pm <= 0) return false;
        try {
            $st = $pdo->prepare('SELECT last_at FROM osm_usage WHERE day = UTC_DATE() AND caller = ?'); $st->execute([$caller]);
            $t = $st->fetchColumn();
            return !$t || strtotime((string) $t . ' UTC') <= time() - (int) ceil(60 / $pm);
        } catch (Throwable $e) { return true; }   // before the database update: the budget alone
    }

    /** Counts OpenStreetMap use against the day's budget. */
    public static function osmUse(PDO $pdo, string $caller, int $queries, int $bytes): void
    {
        if ($queries <= 0 && $bytes <= 0) return;
        try {
            try {
                $pdo->prepare('INSERT INTO osm_usage (day, caller, queries, bytes, last_at) VALUES (UTC_DATE(), ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE queries = queries + VALUES(queries), bytes = bytes + VALUES(bytes), last_at = UTC_TIMESTAMP()')
                    ->execute([$caller === 'site' ? 'site' : 'crawler', max(0, min(100000, $queries)), max(0, min(2000000000, $bytes))]);
            } catch (PDOException $e) {   // before last_at's database update
                $pdo->prepare('INSERT INTO osm_usage (day, caller, queries, bytes) VALUES (UTC_DATE(), ?, ?, ?) ON DUPLICATE KEY UPDATE queries = queries + VALUES(queries), bytes = bytes + VALUES(bytes)')
                    ->execute([$caller === 'site' ? 'site' : 'crawler', max(0, min(100000, $queries)), max(0, min(2000000000, $bytes))]);
            }
        } catch (Throwable $e) { error_log('osm usage: ' . $e->getMessage()); }
    }

    /** This run's: [{key, n}] (n: the places it returned). */
    public static function osmAsked(PDO $pdo, array $asked): void
    {
        if (!$asked) return;
        try {
            $ins = $pdo->prepare('INSERT INTO crawl_osm_asks (qkey, asked_at, found) VALUES (?, UTC_TIMESTAMP(), ?) ON DUPLICATE KEY UPDATE asked_at = VALUES(asked_at), found = VALUES(found)');
            foreach (array_slice($asked, 0, 200) as $a) {
                $k = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($a['key'] ?? '')));
                if (strlen($k) === 16) $ins->execute([$k, max(0, min(100000, (int) ($a['n'] ?? 0)))]);
            }
            if (random_int(1, 100) === 1) $pdo->exec('DELETE FROM crawl_osm_asks WHERE asked_at < UTC_TIMESTAMP() - INTERVAL ' . (self::osmCacheDays($pdo) + 30) . ' DAY');
        } catch (Throwable $e) { error_log('osm asks: ' . $e->getMessage()); }
    }

    // ---- asking for help: Pulse --------------------------------------------------------------------------------------

    /** The issues that need a person: every stop kind, and a back-off that keeps happening (6 in a row, or 6 hours open). */
    public static function needHelp(PDO $pdo): array
    {
        return array_values(array_filter(self::openAll($pdo), fn ($r) => (self::KINDS[$r['kind']][1] ?? '') === 'stop'
            || (int) $r['hits'] >= self::CASE_AFTER || strtotime((string) $r['first_at'] . ' UTC') < time() - 6 * 3600));
    }

    /** Servers whose block lifted by itself in the last 2 days (their probe answered 200): Pulse tells the team. */
    public static function cameBack(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        try { return $pdo->query("SELECT * FROM service_issues WHERE state = 'resolved' AND kind IN ('ban', 'auth', 'quota') AND resolved_by IS NULL AND resolved_how LIKE 'Its probe answered 200%' AND resolved_at >= UTC_TIMESTAMP() - INTERVAL 2 DAY ORDER BY resolved_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { return []; }
    }

    /** The case's title and what it says: the exact issue, the evidence, what's held, the probe's last answer. */
    /**
     * An issue as one thread in the dispatcher's one shape (decisions/0176 §3, A3; §2's six parts), read from its row and its
     * alarm as they are now (0176 §7): the card's issue box, Pulse's svc: case, Outside services' line and the Start card all
     * read these. Title: what failed, in plain words; Status: Paused by the system (a trip), held for a person, or its wait
     * (waitWords, never the stored hold_until); What it affects: what its trip paused and each need that uses the service
     * with its other sources (ControlAlarms::affects); What the system is doing: the kind's meaning; What to do: the card's
     * words, kept; Details: what came back, how often and from whom, since and last, its number. No buttons: each surface
     * adds the ones it can press.
     * Its fix (decisions/0176 §5, A5): from its kind, one spot on its card: a refused key, Its key (#svc-<k>-key, Replace
     * ready); a used-up quota, Caps and budget (#svc-<k>-caps); a refusal, the issue itself (#issue-<id>), where Check it now
     * is once they've lifted it. Their own trouble (a 5xx, no answer, a 429, an answer we can't read) has none: "The system
     * is handling it" ('theirs').
     */
    public static function facts(PDO $pdo, array $r): array
    {
        require_once __DIR__ . '/OutsideServices.php'; require_once __DIR__ . '/ControlAlarms.php'; require_once __DIR__ . '/Thread.php';
        $id = (int) $r['id']; $co = OutsideServices::COMPANIES[$r['service']][0] ?? (string) $r['service'];
        $k = self::KINDS[(string) $r['kind']] ?? [(string) $r['kind'], 'back', ''];
        $ev = json_decode((string) ($r['evidence'] ?? ''), true) ?: []; $l = (array) ($ev['latest'] ?? $ev['first'] ?? []);
        $said = trim(((int) ($l['status'] ?? 0) ? 'HTTP ' . (int) $l['status'] . ' ' : '') . ((string) ($l['error'] ?? '') !== '' ? (string) $l['error'] : (string) ($l['body'] ?? '')));
        $al = null; try { $al = ControlAlarms::openOf($pdo, 'svc:issue:' . $id); } catch (Throwable $x) {}
        $trip = $al && $al['level'] === 'trip' && !empty($al['tripped_at']);
        $back = $k[1] === 'back';
        $iso = fn (string $u) => $u === '' ? '' : '{t:' . gmdate('Y-m-d\TH:i:s\Z', (int) strtotime($u . ' UTC')) . '}';
        $status = $trip ? 'Paused by the system (a trip) since ' . $iso((string) $al['tripped_at']) . ': no calls to ' . $r['host'] . ' until its check passes. It closes as Recovered when it does.'
            : (!$back ? 'Held since ' . $iso((string) $r['first_at']) . ': no calls to ' . $r['host'] . ' until a person fixes it and its check passes.'
            : 'Since ' . $iso((string) $r['first_at']) . '. ' . (($hu = (string) ($r['hold_until'] ?? '')) !== '' && !self::tripHeld($r) && strtotime($hu . ' UTC') > time() ? 'Backing off until ' . $iso($hu) . '.' : self::waitWords($r) . '.'));
        // what to do: the card's words, kept word for word (0176 R2's plan)
        $todo = $back
            ? 'Nothing to change on our side: ' . (string) $r['host'] . ' is ' . ($r['kind'] === 'timeout' ? 'not answering' : ($r['kind'] === 'rate' ? 'asking us to slow down' : 'failing on its own server')) . '. '
              . ($trip ? 'The system tried ' . (int) $r['hits'] . ' times and stopped asking. Press Check and start again: it asks once now, and if it answers, what waited carries on; if not, it stays held and holds only what uses this service.'
                       : 'Calls wait and are tried again by themselves' . ($r['kind'] === 'rate' ? ', after the wait it asked for; nothing to press.' : '. Check it now asks once; if it answers, the issue closes and calls carry on.'))
            : 'This one needs a person: ' . $k[2] . ' Once it\'s fixed, press ' . ($trip ? 'Check and start again' : 'Check it now') . ': a passing check is what closes it.';
        $affects = ControlAlarms::affects($pdo, $al ?: ['proc' => 'svc:' . $r['service'], 'cleared_at' => null, 'level' => 'warning', 'stopped' => null, 'tripped_at' => null]);
        $det = [['What came back', $said !== '' ? OutsideServices::plainWhy($said) : 'No answer'],
                ['How often', (int) $r['hits'] . ' failure' . ((int) $r['hits'] === 1 ? '' : 's') . ' in a row, from ' . (self::CALLERS[(string) $r['caller']] ?? (string) $r['caller'])],
                ['Since', $iso((string) $r['first_at']) . ((string) $r['last_at'] !== (string) $r['first_at'] ? ', last ' . $iso((string) $r['last_at']) : '')],
                ['Issue', '#' . $id . ', ' . $k[0] . ($al ? ' (its alarm #' . (int) $al['id'] . ')' : '')]];
        $fix = self::fixOf($r); $cause = $fix['cause'];
        return ['title' => $co . ': ' . mb_strtolower($k[0]) . ' at ' . $r['host'], 'level' => $trip || !$back ? 'trip' : 'warning',
                'pill' => $trip ? 'Trip' : (!$back ? 'Held' : 'Warning'), 'status' => $status, 'affects' => $affects,
                'doing' => ($cause === 'theirs' ? Thread::HANDLING . ': ' : '') . (string) $k[2], 'todo' => $todo, 'details' => $det, 'alarm' => $al, 'trip' => $trip,
                'fix' => $cause === 'theirs' ? null : $fix, 'theirs' => $cause === 'theirs'];
    }

    /** An issue's cause and its one spot on its card (decisions/0176 §5, A5): a refused key, Its key; a used-up quota, Caps
     *  and budget; a refusal, the issue itself; anything else is their trouble ('theirs', no spot). ['cause', 'url']. */
    public static function fixOf(array $r): array
    {
        $c = ['auth' => 'key', 'quota' => 'quota', 'ban' => 'blocked'][(string) ($r['kind'] ?? '')] ?? 'theirs';
        return ['cause' => $c, 'url' => $c === 'theirs' ? '' : '/admin/config/register#' . ($c === 'blocked' ? 'issue-' . (int) $r['id'] : 'svc-' . $r['service'] . '-' . ($c === 'key' ? 'key' : 'caps'))];
    }

    public static function describe(array $r): array
    {
        $co = OutsideServices::COMPANIES[$r['service']][0] ?? $r['service'];
        $k = self::KINDS[$r['kind']] ?? [$r['kind'], 'back', ''];
        $ev = json_decode((string) $r['evidence'], true) ?: [];
        $e = $ev['latest'] ?? $ev['first'] ?? [];
        $said = trim((string) ($e['body'] ?? '')) !== '' ? '"' . mb_strimwidth((string) $e['body'], 0, 160, '…') . '"' : (trim((string) ($e['error'] ?? '')) !== '' ? mb_strimwidth((string) $e['error'], 0, 160, '…') : '');
        $why = ($e['status'] ?? 0 ? 'HTTP ' . (int) $e['status'] : 'No answer') . ' from ' . $r['host'] . (($e['what'] ?? '') !== '' ? $e['what'] : '')
            . ' to ' . (self::CALLERS[$r['caller']] ?? $r['caller']) . (($e['task'] ?? '') !== '' ? ' (' . $e['task'] . ')' : '') . ', '
            . number_format((int) $r['hits']) . ' time' . ((int) $r['hits'] === 1 ? '' : 's') . ' since ' . self::fmt((string) $r['first_at']) . ', last ' . self::fmt((string) $r['last_at']) . '.'
            . ($said !== '' ? ' It said: ' . $said . '.' : '')
            . ($k[1] === 'stop' ? ' Held: no calls to ' . $r['host'] . ' from ' . (self::CALLERS[$r['caller']] ?? $r['caller']) . ' until a person fixes it and its check passes' . ($r['probe_url'] && !self::noProbe($r) ? ' or its probe answers' : ' (we don\'t contact it while it\'s held)') . '; the rest of the work carries on.'
                               : ' ' . self::waitWords($r) . (self::tripHeld($r) ? '.' : '; it starts again by itself when it answers.'));
        if ($r['last_probe']) { $p = json_decode((string) $r['last_probe'], true) ?: []; $why .= ' Its probe (' . parse_url((string) $r['probe_url'], PHP_URL_PATH) . ') ' . ($p['says'] ?? '') . ' on ' . self::fmt((string) ($p['at'] ?? '')) . (($p['body'] ?? '') !== '' && (int) ($p['status'] ?? 0) !== 200 ? ': "' . mb_strimwidth((string) $p['body'], 0, 120, '…') . '"' : '') . '.'; }
        elseif ($r['probe_url'] && $r['next_probe_at'] && !self::noProbe($r)) $why .= ' Its probe (' . parse_url((string) $r['probe_url'], PHP_URL_PATH) . ', every ' . ((int) $r['probe_every_min'] >= 1440 ? ((int) $r['probe_every_min'] / 1440) . ' day' . ((int) $r['probe_every_min'] > 1440 ? 's' : '') : ((int) $r['probe_every_min'] / 60) . ' hours') . ') is next ' . self::fmt((string) $r['next_probe_at']) . '.';
        return [$co . ': ' . strtolower($k[0]) . ' at ' . $r['host'], $why, $k[1] === 'stop' ? 'alert' : 'update'];
    }

    /** The case's steps (Pulse::playbook): the evidence, the service's contacts and console, Check it now (a passing check closes it). */
    public static function steps(array $r): array
    {
        $k = self::KINDS[$r['kind']] ?? [$r['kind'], 'back', ''];
        $links = [];
        foreach (array_merge(OutsideServices::CONTACT[$r['service']] ?? [], OutsideServices::COMPANIES[$r['service']][3] ?? []) as [$l, $u]) if (str_starts_with((string) $u, 'https://') && count($links) < 4) $links[] = [$l, $u];
        $steps = [];
        // Fix it first (decisions/0176 §5, A5): its one spot on its card, in the case's panel; none when it's their trouble
        $fx = self::fixOf($r); $c = $fx['cause'];
        if ($c !== 'theirs' && $r['service'] !== 'osm') { require_once __DIR__ . '/Thread.php';
            $steps[] = ['t' => 'Fix it', 'h' => 'Opens beside this case: ' . Thread::FIX[$c][0] . '. ' . Thread::FIX[$c][2] . '.', 'act' => ['tool', $fx['url']], 'auto' => false]; }
        $steps[] = ['t' => 'Read what it said', 'h' => $k[2], 'act' => null, 'auto' => false];
        if ($r['service'] === 'osm') $steps[] = ['t' => 'Send the statement to its maintainers', 'h' => 'Our statement for the OpenStreetMap community, the evidence and how to reach them are written up; post it in the Community Forum (Help and support, tag overpass). Their answer decides when we ask again.', 'act' => null, 'auto' => false,
            'exts' => array_merge([['The statement', 'https://github.com/degraffics/traversence/blob/main/research/osm-overpass-2026-11-13.md']], $links)];
        elseif ($links) $steps[] = ['t' => $k[1] === 'stop' ? 'Fix it with the service' : 'If it keeps happening, ask the service', 'h' => $r['kind'] === 'auth' ? 'Make a new key in its console and put it where its card says (System Configuration → Service register).' : ($r['kind'] === 'quota' ? 'Check its plan and this month\'s use.' : 'Its status page, contact or community.'), 'act' => null, 'auto' => false, 'exts' => $links];
        $steps[] = ['t' => 'See its card', 'h' => 'Its calls by server, its settings and the crawler tasks that use it.', 'act' => ['tool', '/admin/config/register#svc-' . $r['service']], 'auto' => false];
        if ($r['probe_url'] && !self::noProbe($r)) $steps[] = ['t' => 'Check it now', 'h' => 'One light request to ' . parse_url((string) $r['probe_url'], PHP_URL_PATH) . ' with the next ' . ($r['caller'] === 'crawler' ? 'crawler run (within 5 minutes)' : 'call') . '. A 200 resolves it by itself.', 'act' => ['do', 'svc_probe', 'Check it now'], 'auto' => false];
        // resolved is the result of a passing check, never a button (Jason, 2026-11-19); only where nothing can be checked
        if (!$r['probe_url'] || self::noProbe($r)) $steps[] = ['t' => 'It\'s fixed: try it again', 'h' => 'Nothing can check it for us, so once it\'s fixed or lifted, this starts its calls again; the next call is the check, and if it fails, a new case opens with the new evidence.', 'act' => ['do', 'svc_resolve', 'Try it again'], 'auto' => false];
        return $steps;
    }

    // ---- times ---------------------------------------------------------------------------------------------------------

    /** A time from the worker or a server (ISO, any zone) as UTC for the database. */
    private static function utc(string $t): string
    {
        $ts = $t !== '' ? strtotime($t) : false;
        return gmdate('Y-m-d H:i:s', $ts !== false && $ts <= time() + 300 && $ts > time() - 86400 * 7 ? $ts : time());
    }

    /** A stored UTC time, labeled ("Nov 14, 1:05 PM UTC"). */
    public static function fmt(string $utc): string
    {
        $ts = strtotime($utc . (preg_match('/[zZ]|[+-]\d\d:?\d\d$/', $utc) ? '' : ' UTC'));
        return $ts ? gmdate('M j, g:i A', $ts) . ' UTC' : $utc;
    }
}
