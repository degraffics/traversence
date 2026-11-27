<?php
// app/lib/HubOpening.php — Opening a hub and W14, authority sources staged ahead of every hub (decisions/0164 R17, §7.1;
// 0163 §8 stage 10, §10's hub line; H1–H5):
//   - a hub's stage on its readiness meter (Planned → Seeding → Opening budget → Soft open → Open), moved by a director;
//     marking a hub Planned starts W14 for its state (H1), and a state's first hub stays Planned until its R8 record is signed
//   - W14's staged sources (`authority_sources`): each state's licence and registry sources, status staged and switched
//     off, with their real use (bulk data, lookup only, blocked, claim-time check: Run 2), their terms and their reader's
//     test. Government pages, or a vendor page one links to, its link kept as provenance (H2). Arizona's and New
//     Mexico's are staged by hand from Run 2 (the database update), as 0163 §8 stage 10 allows
//   - for a hub already open (Arizona's, New Mexico's), a newly staged source switches on from its card once the switch-on
//     check's hub line passes for it (H3)
//   - one Pulse case, "Hubs" (H4): a step per thing a person does in a hub's life, each a button
//   - the opening budget, set per hub, named in the switch-on check when it isn't (H5)
// W14 runs only on the master, its workflow and its sources' switches; shipped off.
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class HubOpening
{
    public const STAGES = ['planned' => 'Planned', 'seeding' => 'Seeding', 'budget' => 'Opening budget', 'soft' => 'Soft open', 'open' => 'Open'];
    public const USES = ['bulk' => 'Bulk data', 'lookup' => 'Lookup only (paced)', 'blocked' => 'Blocked (a person, or a public-records request for the list)', 'claim' => 'Claim-time check (a number the owner gives)'];
    public const KEY = 'hubs:all';

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM hub_stages LIMIT 0'); $pdo->query('SELECT 1 FROM authority_sources LIMIT 0'); return true; } catch (Throwable) { return false; }
    }

    /** W14's workflow row on the switches (paused), added once. */
    public static function ensureWorkflow(PDO $pdo): void
    {
        try {
            $ex = $pdo->query("SELECT COUNT(*) FROM crawl_workflows WHERE wf = 'W14'")->fetchColumn();
            if ((int) $ex) return;
            $cols = []; foreach ($pdo->query('SHOW COLUMNS FROM crawl_workflows')->fetchAll(PDO::FETCH_ASSOC) as $c) $cols[$c['Field']] = $c;
            $want = ['wf' => 'W14', 'name' => 'Authority sources', 'label' => 'Authority sources', 'paused' => 1, 'paused_at' => date('Y-m-d H:i:s'), 'share_pct' => 5, 'why' => 'Added paused (Release B): a person switches it on'];
            $row = [];
            foreach ($cols as $f => $c) {
                if (array_key_exists($f, $want)) { $row[$f] = $want[$f]; continue; }
                if ($c['Null'] === 'NO' && $c['Default'] === null && !str_contains((string) $c['Extra'], 'auto_increment')) $row[$f] = preg_match('/int|dec/i', (string) $c['Type']) ? 0 : (preg_match('/date|time/i', (string) $c['Type']) ? date('Y-m-d H:i:s') : '');
            }
            $pdo->prepare('INSERT IGNORE INTO crawl_workflows (`' . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
        } catch (Throwable $x) { error_log('W14 row: ' . $x->getMessage()); }
    }

    /** Hubs with their stage, state and budget: [id => ['name', 'stage', 'state', 'budget', 'set_at']]. */
    public static function hubs(PDO $pdo): array
    {
        $out = [];
        try {
            // a hub with no stage set takes it from hubs.status (2026-11-17, after Start: every hub without a row counted as
            // Open, so the 18 hubs still planned each held the master switch): 'planned' is Planned, anything else Open
            $sql = "SELECT h.id, h.name, %s AS hstatus, s.stage, s.state, s.opening_budget_usd, s.set_at,
                           (SELECT z.state FROM geo_hubs g JOIN micro_clusters mc ON mc.geo_hub_id = g.id JOIN zip_coordinates z ON z.zip = mc.primary_zip WHERE g.hub_id = h.id LIMIT 1) AS zstate
                      FROM hubs h LEFT JOIN hub_stages s ON s.hub_id = h.id ORDER BY h.id";
            try { $rows = $pdo->query(sprintf($sql, 'h.status'))->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable) { $rows = $pdo->query(sprintf($sql, "''"))->fetchAll(PDO::FETCH_ASSOC); }
            // a hub's own states (0170 §2's geography): every state of its micro-clusters' zips, or the one set on its stage row
            $own = [];
            try { foreach ($pdo->query('SELECT DISTINCT g.hub_id, z.state FROM geo_hubs g JOIN micro_clusters mc ON mc.geo_hub_id = g.id JOIN zip_coordinates z ON z.zip = mc.primary_zip')->fetchAll(PDO::FETCH_ASSOC) as $z) if ((string) $z['state'] !== '') $own[(int) $z['hub_id']][] = strtoupper((string) $z['state']); } catch (Throwable) {}
            foreach ($rows as $r)
                $out[(int) $r['id']] = ['name' => (string) $r['name'], 'stage' => (string) ($r['stage'] ?: ((string) $r['hstatus'] === 'planned' ? 'planned' : 'open')), 'state' => strtoupper((string) ($r['state'] ?: $r['zstate'] ?: '')),
                                        'states' => $r['state'] ? [strtoupper((string) $r['state'])] : array_values(array_unique($own[(int) $r['id']] ?? ($r['zstate'] ? [strtoupper((string) $r['zstate'])] : []))),
                                        'budget' => $r['opening_budget_usd'] !== null ? (float) $r['opening_budget_usd'] : null, 'set_at' => (string) ($r['set_at'] ?? '')];
        } catch (Throwable $x) { error_log('hubs: ' . $x->getMessage()); }
        return $out;
    }

    /** A director moves a hub's stage (H1: Planned starts W14 for its state). */
    public static function setStage(PDO $pdo, int $hub, string $stage, int $uid, string $state = ''): string
    {
        if (!isset(self::STAGES[$stage])) throw new InvalidArgumentException('Which stage?');
        $h = self::hubs($pdo)[$hub] ?? null;
        if (!$h) throw new InvalidArgumentException('That hub isn\'t here.');
        $state = strtoupper($state ?: $h['state']);
        if ($stage !== 'planned' && !self::lawSigned($pdo, $state) && !self::anyOpen($pdo, $state)) throw new InvalidArgumentException('A state\'s first hub stays Planned until its jurisdiction record (R8) is signed.');
        $pdo->prepare('INSERT INTO hub_stages (hub_id, stage, state, set_by, set_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE stage = VALUES(stage), state = VALUES(state), set_by = VALUES(set_by), set_at = VALUES(set_at)')
            ->execute([$hub, $stage, $state ?: null, $uid ?: null]);
        return $h['name'] . ': ' . self::STAGES[$stage] . ($stage === 'planned' ? '. W14 stages ' . $state . '\'s authority sources (while W14 is on).' : '.');
    }

    public static function setBudget(PDO $pdo, int $hub, float $usd, int $uid): void
    {
        $pdo->prepare('INSERT INTO hub_stages (hub_id, stage, opening_budget_usd, set_by, set_at) VALUES (?, \'open\', ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE opening_budget_usd = VALUES(opening_budget_usd), set_by = VALUES(set_by), set_at = VALUES(set_at)')
            ->execute([$hub, max(0, round($usd, 2)), $uid ?: null]);
    }

    private static function lawSigned(PDO $pdo, string $state): bool
    {
        try { $st = $pdo->prepare("SELECT COUNT(*) FROM jurisdictions WHERE UPPER(code) = ? AND signed_at IS NOT NULL"); $st->execute([$state]); return (int) $st->fetchColumn() > 0; } catch (Throwable) { return false; }
    }

    private static function anyOpen(PDO $pdo, string $state): bool
    {
        foreach (self::hubs($pdo) as $h) if ($h['state'] === $state && in_array($h['stage'], ['soft', 'open'], true)) return true;
        return false;
    }

    /** A state's staged sources: [[id, kind, name, url, host, real_use, terms_url, terms_read_at, reader_ok_at, enabled, provenance]]. */
    public static function sources(PDO $pdo, string $state = ''): array
    {
        try {
            $st = $pdo->prepare('SELECT * FROM authority_sources' . ($state !== '' ? ' WHERE state = ?' : '') . ' ORDER BY state, kind, id');
            $st->execute($state !== '' ? [$state] : []);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) { return []; }
    }

    /** The switch-on check's hub lines (0163 §10, H3, H5): one per hub past Planned. [what, ok, why, fix, scope].
     *  They hold W14 only (Jason, 2026-11-17, after Start held on 20 of them; 0170 §2: a hub's stage is about our team's
     *  support, never about gathering or showing data): unread terms or an untested reader keep W14 off, nothing else. */
    public static function lines(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [['Opening a geo-hub: its authority sources\' terms and readers, and its opening budget set (H3, H5)', false, 'Its database update (2026-11-16_hubs) hasn\'t run yet.', ['Database updates', '/admin/config/dbupdates'], 'W14']];
        $L = [];
        foreach (self::hubs($pdo) as $id => $h) {
            if ($h['stage'] === 'planned') continue;
            $miss = [];
            // no state known: no sources staged for it, never every state's (sources('') is all of them)
            if (!$h['states']) { $L[] = ['Opening a geo-hub: ' . $h['name'] . ' (' . self::STAGES[$h['stage']] . ')', true, 'Its state isn\'t recorded (no stage row with a state, no micro-cluster with a zip), so no authority sources are staged for it yet.', ['Hubs (Pulse)', '/admin/pulse'], 'W14']; continue; }
            $srcs = []; foreach ($h['states'] as $stt) $srcs = array_merge($srcs, self::sources($pdo, $stt));
            foreach ($srcs as $s) {
                if ((string) $s['real_use'] === 'blocked') continue;   // never read by the crawler: a person, or a records request
                if ($s['terms_read_at'] === null) $miss[] = $s['name'] . ': its terms not read';
                elseif ((int) $s['enabled'] && $s['real_use'] !== 'claim' && $s['reader_ok_at'] === null) $miss[] = $s['name'] . ': its reader not tested';
            }
            $early = in_array($h['stage'], ['seeding', 'budget'], true);
            if ($early && $h['budget'] === null) $miss[] = 'opening budget not set';
            $L[] = ['Opening a geo-hub: ' . $h['name'] . ' (' . self::STAGES[$h['stage']] . ')', !$miss, $miss ? ucfirst(implode('; ', array_slice($miss, 0, 6))) . '.' : 'Its authority sources\' terms read and readers passing' . ($early ? ', its opening budget set ($' . number_format((float) $h['budget'], 2) . ')' : '') . '.', ['Hubs (Pulse)', '/admin/pulse'], 'W14'];
        }
        return $L ?: [['Opening a geo-hub: its authority sources\' terms and readers, and its opening budget set (H3, H5)', true, 'No hub is past Planned without its sources ready.', null, 'W14']];
    }

    // ---- the Hubs case (H4) ------------------------------------------------------------------------------------------

    public static function pulseItems(PDO $pdo): array
    {
        if (!self::ready($pdo)) return [];
        self::ensureWorkflow($pdo);   // W14's row, paused, the first time Pulse looks
        $n = 0;
        foreach (self::sources($pdo) as $s) if ($s['terms_read_at'] === null || ((int) $s['enabled'] === 0 && (string) $s['status'] === 'staged' && $s['real_use'] !== 'blocked')) $n++;
        foreach (self::hubs($pdo) as $h) if (in_array($h['stage'], ['seeding', 'budget'], true) && $h['budget'] === null) $n++;
        if (!$n) return [];
        return [[self::KEY, 'update', 'Hubs: ' . $n . ' step' . ($n === 1 ? '' : 's') . ' for a person', 'Authority sources whose terms to read or that can be switched on, and hubs waiting on a stage or their opening budget (decisions/0164 R17, §7.1).', gmdate('Y-m-d H:i:s') . ' UTC']];
    }

    public static function pulseSteps(PDO $pdo, callable $s): array
    {
        $st = [];
        $stages = array_map(fn ($k, $v) => [$k, $v], array_keys(self::STAGES), self::STAGES);
        foreach (self::hubs($pdo) as $id => $h) {
            $st[] = $s($h['name'] . ': ' . self::STAGES[$h['stage']], 'A director moves it when its targets are met. Planned starts W14 for ' . ($h['state'] ?: 'its state') . '.', ['form', 'hub_stage:' . $id, 'Move it', [['stage', 'Stage', 'select', $h['stage'], $stages]]]);
            if (in_array($h['stage'], ['seeding', 'budget'], true)) $st[] = $s($h['name'] . ': its opening budget' . ($h['budget'] === null ? ' (not set)' : ' ($' . number_format((float) $h['budget'], 2) . ')'), 'A one-time allowance, separate from upkeep, spent highest value first (0163 §2).', ['form', 'hub_budget:' . $id, 'Set it', [['usd', 'Dollars', 'text', $h['budget'] !== null ? (string) $h['budget'] : '']]]);
        }
        foreach (self::sources($pdo) as $r) {
            $id = (int) $r['id']; $use = self::USES[$r['real_use']] ?? $r['real_use'];
            if ($r['terms_read_at'] === null) $st[] = ['t' => $r['state'] . ', ' . $r['name'] . ': read its terms', 'h' => $use . '. Robots.txt, its terms of use and any data licence: whether automated reading is allowed, and how fast.', 'act' => ['do', 'hub_terms:' . $id, 'Terms read: allowed'], 'auto' => false, 'ext' => ['Its terms', (string) ($r['terms_url'] ?: $r['url'])]];
            elseif (!(int) $r['enabled'] && $r['real_use'] !== 'blocked') $st[] = $s($r['state'] . ', ' . $r['name'] . ': switch it on', $use . '. For an open hub it switches on from here once the hub line passes (H3).', ['do', 'hub_on:' . $id, 'Switch it on']);
        }
        return $st ?: [$s('Nothing waits on a person', 'The case closes by itself.', ['resolve'])];
    }

    public static function pulseDo(PDO $pdo, string $what, array $a, int $uid): string
    {
        if (!preg_match('/^hub_(stage|budget|terms|on):(\d+)$/', $what, $m)) throw new InvalidArgumentException('Unknown step.');
        $id = (int) $m[2];
        switch ($m[1]) {
            case 'stage': return self::setStage($pdo, $id, (string) ($a['stage'] ?? ''), $uid);
            case 'budget': self::setBudget($pdo, $id, (float) ($a['usd'] ?? 0), $uid); return 'Opening budget set.';
            case 'terms': $pdo->prepare('UPDATE authority_sources SET terms_read_at = UTC_TIMESTAMP(), terms_by = ? WHERE id = ?')->execute([$uid ?: null, $id]); return 'Its terms are read and allow it.';
            case 'on':
                $r = $pdo->prepare('SELECT * FROM authority_sources WHERE id = ?'); $r->execute([$id]); $r = $r->fetch(PDO::FETCH_ASSOC);
                if (!$r) throw new InvalidArgumentException('That source isn\'t here.');
                $hubOk = true;
                foreach (self::lines($pdo) as $l) if (str_contains((string) $l[0], 'Opening a geo-hub') && !$l[1]) $hubOk = false;
                if ($r['terms_read_at'] === null) throw new InvalidArgumentException('Read its terms first.');
                if (!$hubOk) throw new InvalidArgumentException('The switch-on check\'s hub line isn\'t green yet (H3).');
                $pdo->prepare("UPDATE authority_sources SET enabled = 1, status = 'on', on_by = ?, on_at = UTC_TIMESTAMP() WHERE id = ?")->execute([$uid ?: null, $id]);
                return $r['name'] . ' is on (its service card and the master switch still decide when it\'s read).';
        }
        throw new InvalidArgumentException('Unknown step.');
    }
}
