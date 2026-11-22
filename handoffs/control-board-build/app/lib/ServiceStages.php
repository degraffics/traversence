<?php
// app/lib/ServiceStages.php — each outside service's stage (decisions/0173 §2): Proposed, Added off, Key in, Active, Paused,
// Retired, Removed. A stage comes from what's true now (Automation's switch, its keys, the retired records), never a word
// typed in; each change is logged here with who, when and why (service_stages), the switch's own presses included
// (Automation::set writes here too), so a card's history is one list.
declare(strict_types=1);

final class ServiceStages
{
    /** stage => [its word, 0169 §5's step, what it means]. */
    public const STAGES = [
        'proposed' => ['Proposed', 'Choose', 'Named on the register as a build request; no code calls it.'],
        'added'    => ['Added', 'Install', 'A release shipped it; it waits for its key (and, if it shipped off, for a person to activate it).'],
        'keyin'    => ['Key in', 'Configure', 'Its key is in place; it waits, paused, for a person to activate it.'],
        'active'   => ['Active', 'Use', 'Called by its workflows, inside its caps and paces.'],
        'paused'   => ['Paused', 'Update', 'Nothing of ours calls it until it\'s activated again; its key stays.'],
        'retired'  => ['Retired', 'Remove', 'Kept as a record: nothing calls it, and the card says why and what replaced it.'],
        'removed'  => ['Removed', 'Remove', 'Its code and key are gone; the record stays.'],
    ];

    /** The register's tone for each stage (the admin's one status look). */
    public const TONE = ['proposed' => 'info', 'added' => 'info', 'keyin' => 'info', 'active' => 'ok', 'paused' => 'info', 'retired' => 'info', 'removed' => 'info'];

    /**
     * Retired services that have no card of their own (decisions/0173 §4: Overpass is the first record). key => [name, why,
     * what replaced it, when, where it was said]. OpenStreetMap itself stays Active: our copy, the tiles and the credit.
     */
    // generated from app/lib/service-defs.php (decisions/0173 §4): edit that file, then run tools/page-audit/service-defs.php --write
    public const RETIRED = [
        'overpass' => ['Overpass (OpenStreetMap\'s live query server)', 'Its community asked, on its forum, that its public servers not be used as a back end for apps and websites (release #204).', 'our own copy from Geofabrik (Arizona, New Mexico, Utah, Colorado and Nevada)', '2026-11-19', 'https://community.openstreetmap.org/t/150369'],
    ];

    /**
     * A service's stage now: [stage, words]. $keys is Operations::keys() for it ([[name, where, present|null]]), $off its
     * paused line (Automation::serviceOff) or null, $addedOff whether it shipped paused (Operations::ADDED_OFF).
     */
    public static function decide(array $keys, ?string $off, bool $addedOff, bool $everActive): array
    {
        $missing = array_values(array_filter($keys, fn ($k) => $k[2] === false && !str_contains($k[1], '(')));   // a key for one use only, "(…)", as Operations::status
        if ($off !== null) {
            if ($addedOff && !$everActive) return $missing ? ['added', 'Off, waiting for its key: ' . implode(', ', array_column($missing, 0)) . '.'] : ['keyin', 'Its key is in place; it waits for Activate.'];
            return ['paused', $off];
        }
        if ($missing) return ['added', 'Switched on, but its key isn\'t in place yet: ' . implode(', ', array_column($missing, 0)) . '. Nothing can call it until it is.'];
        if (array_filter($keys, fn ($k) => $k[2] === null)) return ['active', 'Switched on; its key is kept on Railway, where the site can\'t read it (the crawler reports whether it has it).'];   // words follow what's known
        return ['active', $keys ? 'Its key is in place and it\'s switched on.' : 'Needs no key; it\'s switched on.'];
    }

    /** Every service's stage for the register: key => [stage, words]. One read of the log for "ever active". */
    public static function all(PDO $pdo): array
    {
        require_once __DIR__ . '/Operations.php'; require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/OutsideServices.php';
        $was = [];
        // switched on by a person at some point: Automation's own log reaches back before this table
        try { foreach ($pdo->query("SELECT DISTINCT ref FROM automation_log WHERE kind = 'service' AND paused = 0")->fetchAll(PDO::FETCH_COLUMN) as $s) $was[(string) $s] = true; } catch (Throwable $x) {}
        try { foreach ($pdo->query("SELECT DISTINCT service FROM service_stages WHERE stage = 'active'")->fetchAll(PDO::FETCH_COLUMN) as $s) $was[(string) $s] = true; } catch (Throwable $x) {}
        $out = []; $ret = self::retiredCards($pdo);
        foreach (array_keys(OutsideServices::COMPANIES) as $k)
            $out[$k] = isset($ret[$k]) ? ['retired', $ret[$k]] : self::decide(Operations::keys($k), Automation::serviceOff($pdo, $k), in_array($k, Operations::ADDED_OFF, true), isset($was[$k]));
        return $out;
    }

    /** Services with a card whose latest stage is Retired: key => its words (why, and what replaced it). */
    public static function retiredCards(PDO $pdo): array
    {
        $out = [];
        try {
            foreach ($pdo->query('SELECT s.service, s.stage, s.why, s.replaced_by, s.at FROM service_stages s JOIN (SELECT service, MAX(id) AS id FROM service_stages GROUP BY service) m ON m.id = s.id')->fetchAll(PDO::FETCH_ASSOC) as $r)
                if ($r['stage'] === 'retired') $out[(string) $r['service']] = 'Since ' . substr((string) $r['at'], 0, 10) . ': ' . rtrim((string) $r['why'], '.') . '.' . ((string) $r['replaced_by'] !== '' ? ' Replaced by ' . $r['replaced_by'] . '.' : '') . ' Nothing of ours calls it; its record and data are kept.';
        } catch (Throwable $x) {}
        return $out;
    }

    public static function isRetired(PDO $pdo, string $svc): bool { return isset(self::retiredCards($pdo)[$svc]); }

    /**
     * Retire (decisions/0173 §2): nothing calls it (Automation's switch, paused with why), then its record: why and what
     * replaced it. Never for a business operation, and nothing is deleted. Bring it back undoes it.
     */
    public static function retire(PDO $pdo, string $svc, string $why, string $replaced, int $uid, string $who): string
    {
        require_once __DIR__ . '/Automation.php'; require_once __DIR__ . '/OutsideServices.php';
        $name = OutsideServices::COMPANIES[$svc][0] ?? '';
        if ($name === '') throw new InvalidArgumentException('No such outside service.');
        if (isset(Automation::NOT_PAUSABLE[$svc])) throw new InvalidArgumentException($name . '\'s calls are business operations (' . Automation::NOT_PAUSABLE[$svc] . '): it isn\'t retired from here.');
        if (self::isRetired($pdo, $svc)) throw new InvalidArgumentException($name . ' is already retired.');
        $why = trim($why); if ($why === '') throw new InvalidArgumentException('Say why it\'s retired, so the record tells its story.');
        if (Automation::serviceOff($pdo, $svc) === null) Automation::set($pdo, 'service', $svc, true, 'Retired: ' . $why, $uid, $who);
        self::log($pdo, $svc, 'retired', $why, $uid, $who, trim($replaced));
        return $name . ' is retired: nothing of ours calls it, and its card keeps why' . (trim($replaced) !== '' ? ' and what replaced it' : '') . '. Bring it back undoes it.';
    }

    /** Bring it back: out of Retired, still paused; Activate (through the switch-on check) switches it on. */
    public static function bringBack(PDO $pdo, string $svc, int $uid, string $who): string
    {
        require_once __DIR__ . '/OutsideServices.php';
        $name = OutsideServices::COMPANIES[$svc][0] ?? '';
        if ($name === '' || !self::isRetired($pdo, $svc)) throw new InvalidArgumentException('It isn\'t retired.');
        self::log($pdo, $svc, 'paused', 'Brought back from retired: paused until it\'s activated', $uid, $who);
        return $name . ' is back, still paused: Activate switches it on once its check passes.';
    }

    // ---- Proposed (0169 §5's Choose): a service someone would like, as a build request. Only words: it never adds an
    // address, a key or a call; a release ships its definition and code, and then it's Added.

    public static function propose(PDO $pdo, string $name, string $link, string $why, int $uid, string $who): int
    {
        $name = trim($name); $link = trim($link); $why = trim($why);
        if ($name === '' || mb_strlen($name) > 120) throw new InvalidArgumentException('Name the service (up to 120 characters).');
        if ($link !== '' && !preg_match('~^https?://[^\s]+$~i', $link)) throw new InvalidArgumentException('The link should start with https://.');
        if ($why === '') throw new InvalidArgumentException('Say what it would be for.');
        $pdo->prepare('INSERT INTO service_proposals (name, link, why, state, by_uid, by_name, at) VALUES (?, ?, ?, \'proposed\', ?, ?, UTC_TIMESTAMP())')
            ->execute([$name, mb_substr($link, 0, 255) ?: null, mb_substr($why, 0, 400), $uid ?: null, mb_substr($who, 0, 80) ?: null]);
        return (int) $pdo->lastInsertId();
    }

    /** Drop it, or Propose again (its way back). */
    public static function setProposal(PDO $pdo, int $id, bool $drop, string $who): void
    {
        $st = $pdo->prepare('UPDATE service_proposals SET state = ?, dropped_at = ' . ($drop ? 'UTC_TIMESTAMP()' : 'NULL') . ', dropped_by = ? WHERE id = ? AND state = ?');
        $st->execute([$drop ? 'dropped' : 'proposed', $drop ? (mb_substr($who, 0, 80) ?: null) : null, $id, $drop ? 'proposed' : 'dropped']);
        if (!$st->rowCount()) throw new InvalidArgumentException($drop ? 'It was already dropped.' : 'It isn\'t dropped.');
    }

    public static function proposals(PDO $pdo): array
    {
        try { return $pdo->query('SELECT * FROM service_proposals ORDER BY state = \'dropped\', id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $x) { return []; }
    }

    /** The records of services with no card (service-defs.php's stage retired or removed): [key => [stage, record]]. */
    public static function records(): array
    {
        $out = [];
        try { foreach ((array) include __DIR__ . '/service-defs.php' as $k => $d) if (in_array($d['stage'] ?? '', ['retired', 'removed'], true)) $out[$k] = [$d['stage'], $d['record'] ?? self::RETIRED[$k] ?? []]; } catch (Throwable $x) {}
        return $out;
    }

    /** One change, logged: never a key or its value, only who, when, which stage and why. */
    public static function log(PDO $pdo, string $svc, string $stage, string $why, int $uid, string $who, string $replacedBy = ''): void
    {
        if (!isset(self::STAGES[$stage])) throw new InvalidArgumentException('Unknown stage.');
        try {
            $pdo->prepare('INSERT INTO service_stages (service, stage, why, replaced_by, by_uid, by_name, at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')
                ->execute([mb_substr($svc, 0, 40), $stage, mb_substr(trim($why), 0, 255) ?: null, mb_substr($replacedBy, 0, 255) ?: null, $uid ?: null, mb_substr($who, 0, 80) ?: null]);
        } catch (Throwable $x) { error_log('service stages: ' . $x->getMessage()); }   // its table comes with the release's database update
    }

    /** A service's changes, newest first. */
    public static function history(PDO $pdo, string $svc, int $n = 8): array
    {
        try { $st = $pdo->prepare('SELECT * FROM service_stages WHERE service = ? ORDER BY id DESC LIMIT ' . max(1, min(50, $n))); $st->execute([$svc]); return $st->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $x) { return []; }
    }
}
