<?php

declare(strict_types=1);

require_once __DIR__ . '/IntakeStager.php';
require_once __DIR__ . '/CrawlQueue.php';

/**
 * api/lib/crawler/NpiBulk.php — our own copy of the NPI Registry for the pilot states (decisions/0048).
 *
 * Once a month the worker downloads CMS's full NPI file, keeps health-care ORGANIZATIONS (not individual
 * practitioners) with a practice address in the loaded states, and sends them here in batches. They are used
 * two ways:
 *   - as references: a listing waiting for review that matches a record by name plus street number or phone
 *     is confirmed by a trusted registry, with no web search (Verify::lease calls matches());
 *   - as new places: organizations in a ZIP one of our town clusters covers, and not already listed, are
 *     staged. A place that comes only from this file needs one more reference (its own website, a directory,
 *     one of our sources) before it can publish (AutoImport); the second look goes and finds it.
 * Facts only: name, practice address, phone, main specialty, NPI number. Records missing from a newer file
 * (deactivated, moved away) are removed when the load finishes.
 */
final class NpiBulk
{
    public const STATES = ['AZ', 'NM'];
    private const EVERY_DAYS = 30;
    private const RETRY_HOURS = 6;
    /** Off since 2026-11-02 (Jason): CMS took its file page down "until further notice", so the site never asks the worker
     *  for a monthly load. Since the control board's build (D12, 03 §22 NPI 1) that's the npi_bulk task's own switch, with
     *  its why, on Automation and its workflow's page (Off on purpose), no longer a constant in code: switching the task on
     *  there turns the monthly load back on. The records already loaded keep confirming listings; single-listing NPI
     *  Registry lookups are separate and unchanged. */
    public const TASK = 'npi_bulk';

    /** Why the monthly load is off (its task's switch), or null when it's on. */
    public static function off(PDO $pdo): ?string
    {
        try {
            $st = $pdo->prepare('SELECT enabled, off_why FROM crawl_capture_tasks WHERE task = ?'); $st->execute([self::TASK]); $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r && !(int) $r['enabled']) return (string) ($r['off_why'] ?: 'Off');
            if (!$r) return 'Off: its task isn\'t set up';
        } catch (Throwable $x) { return 'Off: its task can\'t be read'; }
        return null;
    }

    /** Main specialty codes (or their first letters) to plain words; anything else is "Health care". */
    private const LABELS = [
        '261QF0400X' => 'Community health center', '261QR1300X' => 'Rural health clinic', '261QU0200X' => 'Urgent care',
        '261QP2300X' => 'Primary care clinic', '261QM0801X' => 'Mental health clinic', '261QR0405X' => 'Substance use treatment',
        '261QD0000X' => 'Dental clinic', '261QE0700X' => 'Dialysis center', '261QI0500X' => 'Infusion therapy',
        '324500000X' => 'Substance use treatment', '320800000X' => 'Residential mental health treatment',
        '251S00000X' => 'Community behavioral health', '251E00000X' => 'Home health care', '251G00000X' => 'Hospice',
        '251J00000X' => 'Nursing care', '253Z00000X' => 'In-home supportive care', '251C00000X' => 'Developmental disability services',
        '314000000X' => 'Skilled nursing facility', '313M00000X' => 'Nursing facility', '310400000X' => 'Assisted living',
        '311500000X' => 'Memory care', '385H00000X' => 'Respite care', '282N00000X' => 'Hospital', '283Q00000X' => 'Psychiatric hospital',
        '283X00000X' => 'Rehabilitation hospital', '273R00000X' => 'Psychiatric unit', '291U00000X' => 'Medical laboratory',
        '341600000X' => 'Ambulance service', '343900000X' => 'Medical transportation', '332B00000X' => 'Medical equipment and supplies',
        '335E00000X' => 'Prosthetics and orthotics', '193200000X' => 'Medical group', '193400000X' => 'Medical group',
        '3336' => 'Pharmacy', '261Q' => 'Clinic', '282N' => 'Hospital', '1223' => 'Dentist', '152W' => 'Eye care', '1041' => 'Social services',
        '101Y' => 'Counseling', '103T' => 'Psychologist', '2251' => 'Physical therapy', '225X' => 'Occupational therapy', '2355' => 'Speech therapy',
        '111N' => 'Chiropractic', '2084' => 'Psychiatry', '207Q' => 'Family medicine', '208D' => 'Family medicine', '207R' => 'Internal medicine',
        '2080' => 'Pediatrics', '207V' => 'Women\'s health', '363L' => 'Nurse practitioner', '3747' => 'Personal care services',
    ];

    public static function ready(PDO $pdo): bool
    {
        try { $pdo->query('SELECT 1 FROM ' . Database::t('npi_loads') . ' LIMIT 1'); $pdo->query('SELECT 1 FROM ' . Database::t('npi_orgs') . ' LIMIT 1'); return true; } catch (PDOException) { return false; }
    }

    public static function label(string $code): string
    {
        return self::LABELS[$code] ?? self::LABELS[substr($code, 0, 4)] ?? 'Health care';
    }

    /** Is a monthly load due? True when the last finished load is 30+ days old and no load started in the last few hours. */
    public static function status(PDO $pdo): array
    {
        $last = $pdo->query('SELECT file, finished_at FROM ' . Database::t('npi_loads') . ' WHERE finished_at IS NOT NULL ORDER BY finished_at DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: null;
        $running = $pdo->query('SELECT started_at FROM ' . Database::t('npi_loads') . ' WHERE finished_at IS NULL ORDER BY id DESC LIMIT 1')->fetchColumn();
        $off = self::off($pdo);
        $due = $off === null && (!$last || strtotime((string) $last['finished_at']) < time() - self::EVERY_DAYS * 86400)
            && (!$running || strtotime((string) $running) < time() - self::RETRY_HOURS * 3600);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . Database::t('npi_orgs') . '')->fetchColumn();
        return ['due' => $due, 'off' => $off !== null, 'off_why' => (string) $off, 'states' => self::STATES, 'last_file' => $last['file'] ?? '', 'last_finished' => $last['finished_at'] ?? null, 'records' => $count];
    }

    public static function start(PDO $pdo, string $file): int
    {
        $pdo->prepare('INSERT INTO ' . Database::t('npi_loads') . ' (file, started_at) VALUES (?, ?)')->execute([mb_substr($file, 0, 80), date('Y-m-d H:i:s')]);
        return (int) $pdo->lastInsertId();
    }

    private static function title(string $s): string
    {
        $s = ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $s))), " -/(&'.");
        return preg_replace_callback('/\b(Llc|Pllc|Pc|Pa|Lp|Llp|Md|Dds|Np|Usa|Ihs|Va|Ii|Iii|Iv|Nm|Az|Po)\b/', fn ($m) => strtoupper($m[1]), $s);
    }

    /** Upserts one batch from the worker and stages new places in our clusters. Returns [saved, staged]. */
    public static function batch(PDO $pdo, string $file, array $rows): array
    {
        $saved = 0; $new = [];
        $up = $pdo->prepare('REPLACE INTO ' . Database::t('npi_orgs') . ' (npi, name, other_name, address, city, state, zip5, phone, taxonomy, label, file, loaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach (array_slice($rows, 0, 1000) as $r) {
            $npi = preg_replace('/\D/', '', (string) ($r['npi'] ?? ''));
            $state = strtoupper(substr((string) ($r['state'] ?? ''), 0, 2));
            $name = trim((string) ($r['name'] ?? ''));
            if (strlen($npi) !== 10 || $name === '' || !in_array($state, self::STATES, true)) continue;
            $other = trim((string) ($r['other_name'] ?? ''));
            $code = substr(preg_replace('/[^0-9A-Z]/', '', strtoupper((string) ($r['taxonomy'] ?? ''))), 0, 10);
            $phone = substr(preg_replace('/\D/', '', (string) ($r['phone'] ?? '')), -10);
            $row = [(int) $npi, mb_substr(self::title($name), 0, 200), $other !== '' ? mb_substr(self::title($other), 0, 200) : null,
                    mb_substr(self::title((string) ($r['address'] ?? '')), 0, 255), mb_substr(self::title((string) ($r['city'] ?? '')), 0, 80), $state,
                    substr(preg_replace('/\D/', '', (string) ($r['zip'] ?? '')), 0, 5), strlen($phone) === 10 ? $phone : '', $code, self::label($code),
                    mb_substr($file, 0, 80), date('Y-m-d H:i:s')];
            $up->execute($row);
            $saved++;
            $new[] = $row;
        }
        return [$saved, self::stageNew($pdo, $new)];
    }

    /** ZIPs our town clusters cover. */
    private static function clusterZips(PDO $pdo): array
    {
        static $zips = null;
        if ($zips !== null) return $zips;
        $zips = [];
        foreach ($pdo->query('SELECT primary_zip, associated_zips FROM micro_clusters')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $zips[(string) $c['primary_zip']] = true;
            foreach ((array) json_decode((string) ($c['associated_zips'] ?? '[]'), true) as $z) $zips[(string) $z] = true;
        }
        return $zips;
    }

    private static function words(string $s): array
    {
        $skip = ['the', 'and', 'inc', 'llc', 'pllc', 'pc', 'of', 'at', 'dba', 'co', 'corp', 'ltd'];
        return array_values(array_diff(array_unique(preg_split('/[^a-z0-9]+/', strtolower($s), -1, PREG_SPLIT_NO_EMPTY)), $skip));
    }

    public static function sameName(string $a, string $b): bool
    {
        $wa = self::words($a); $wb = self::words($b);
        if (!$wa || !$wb) return false;
        return count(array_intersect($wa, $wb)) / min(count($wa), count($wb)) >= 0.6;
    }

    /** Stages organizations in cluster ZIPs that aren't listed or staged yet. */
    private static function stageNew(PDO $pdo, array $rows): int
    {
        $zips = self::clusterZips($pdo);
        $cands = [];
        foreach ($rows as [$npi, $name, $other, $address, $city, $state, $zip, $phone, $code, $label]) {
            if (!isset($zips[$zip]) || $address === '' || !preg_match('/\d/', $address)) continue;
            $shown = $other ?: $name;   // the "doing business as" name is what people know
            // Already listed? Same ZIP and a similar name, or the same phone.
            $st = $pdo->prepare("SELECT e.name FROM entities e LEFT JOIN entity_metadata z ON z.entity_id = e.id AND z.meta_key = 'zip'
                                   LEFT JOIN entity_metadata p ON p.entity_id = e.id AND p.meta_key = 'phone'
                                  WHERE z.meta_value = ? OR (? <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(p.meta_value, '-', ''), ' ', ''), '(', ''), ')', '') LIKE ?) LIMIT 400");
            $st->execute([$zip, $phone, '%' . $phone]);
            $listed = false;
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) if (self::sameName((string) $n, $shown) || self::sameName((string) $n, $name)) { $listed = true; break; }
            if ($listed) continue;
            $seen = $pdo->prepare("SELECT 1 FROM " . Database::t('crawler_raw_index') . " WHERE payload_type = 'listing' AND raw_payload LIKE ? LIMIT 1");
            $seen->execute(['%"npi":"' . $npi . '"%']);
            if ($seen->fetchColumn() !== false) continue;
            $url = 'https://npiregistry.cms.hhs.gov/provider-view/' . $npi;
            $c = CrawlQueue::normalize([
                'name' => $shown,
                'source' => ['start_url' => $url, 'site' => 'npiregistry.cms.hhs.gov', 'kind' => 'npi_bulk', 'registry' => 'npi', 'npi' => (string) $npi,
                             'crawled_at' => gmdate('Y-m-d\TH:i:s\Z')],
                'where' => ['address' => $address, 'city' => $city, 'state' => $state, 'zip' => $zip],
                'what' => ['offerings' => [$label], 'description' => ''],
                'when' => ['hours' => []],
                'actions' => ['phone' => $phone !== '' ? substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6) : '', 'website' => ''],
                'sources' => ['name' => [$url], 'address' => [$url]],
                'corroboration' => [],
                'registries' => ['npi'],
                'confidence' => 45 + ($phone !== '' ? 10 : 0),
            ]);
            if ($c) {
                $c['source']['kind'] = 'npi_bulk'; $c['source']['registry'] = 'npi'; $c['source']['npi'] = (string) $npi; $c['registries'] = ['npi'];
                $cands[] = $c;
            }
        }
        if (!$cands) return 0;
        // within the batch (decisions/0164 §3.1): one operator's records at one base arrive as one find with all its sources;
        // then the one identity check, read fresh, so a business already listed (under any way of writing it) isn't staged
        require_once __DIR__ . '/../ListingIdentity.php';
        $cands = ListingIdentity::fold($cands);
        $cands = array_values(array_filter($cands, fn ($c) => !in_array(ListingIdentity::resolve($pdo, $c)['kind'], ['same', 'covered'], true)));
        if (!$cands) return 0;
        $url = 'https://download.cms.gov/nppes/NPI_Files.html';   // one source record for every NPI load, reused
        $st = $pdo->prepare('SELECT id FROM ' . Database::t('crawler_sources') . ' WHERE url = ? ORDER BY id LIMIT 1');
        $st->execute([$url]);
        $sid = (int) ($st->fetchColumn() ?: IntakeStager::createSource($pdo, $url, ['create_listings'], 'NPI Registry monthly file (organizations)', 0));
        $r = IntakeStager::stage($pdo, $sid, $cands);
        return count($r['staged']) + count($r['auto_imported']);
    }

    /** The load finished: records it and removes organizations that weren't in this file. */
    public static function finish(PDO $pdo, int $loadId, string $file, int $seen, int $staged): int
    {
        $del = $pdo->prepare('DELETE FROM ' . Database::t('npi_orgs') . ' WHERE file <> ?');
        $del->execute([$file]);
        $removed = $del->rowCount();
        $pdo->prepare('UPDATE ' . Database::t('npi_loads') . ' SET finished_at = ?, rows_seen = ?, staged = ?, removed = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $seen, $staged, $removed, $loadId]);
        return $removed;
    }

    /**
     * References for a listing waiting for review: NPI organizations with a similar name and the same street
     * number or phone. Same shape as the worker's findings, so Verify::complete counts it as a trusted registry.
     */
    public static function matches(PDO $pdo, array $c): array
    {
        if (!self::ready($pdo)) return [];
        $zip = (string) ($c['where']['zip'] ?? '');
        $phone = substr(preg_replace('/\D/', '', (string) ($c['actions']['phone'] ?? '')), -10);
        $house = preg_match('/^\s*(\d+)/', (string) ($c['where']['address'] ?? ''), $m) ? $m[1] : '';
        if ($zip === '' && strlen($phone) !== 10) return [];
        $st = $pdo->prepare('SELECT npi, name, other_name, address, phone, label FROM ' . Database::t('npi_orgs') . ' WHERE zip5 = ? OR (? <> \'\' AND phone = ?) LIMIT 300');
        $st->execute([$zip, strlen($phone) === 10 ? $phone : '', $phone]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (!self::sameName((string) ($c['name'] ?? ''), (string) $r['name']) && !self::sameName((string) ($c['name'] ?? ''), (string) $r['other_name'])) continue;
            $byPhone = strlen($phone) === 10 && $r['phone'] === $phone;
            $byAddr = $house !== '' && preg_match('/^\s*' . $house . '\b/', (string) $r['address']);
            if (!$byPhone && !$byAddr) continue;
            return [['url' => 'https://npiregistry.cms.hhs.gov/provider-view/' . $r['npi'], 'kind' => 'registry', 'registry' => 'npi',
                     'evidence' => $byPhone && $byAddr ? 'both' : ($byPhone ? 'phone' : 'address'),
                     'label' => 'NPI ' . $r['npi'] . ': ' . ($r['other_name'] ?: $r['name']), 'phone' => $r['phone'], 'taxonomy' => $r['label']]];
        }
        return [];
    }
}
