<?php
// app/lib/CPanel.php — calls into our hosting account's cPanel with the API token in .env (CPANEL_HOST, CPANEL_USER,
// CPANEL_TOKEN; see CPanelMail.php for where each comes from). uapi() for cPanel's current API, api2() for the older
// one, which is where cron jobs are still managed (module Cron). The token is only ever sent to our own cPanel.
declare(strict_types=1);

final class CPanel
{
    public static function ready(): bool
    {
        return trim((string) getenv('CPANEL_HOST')) !== '' && trim((string) getenv('CPANEL_USER')) !== '' && trim((string) getenv('CPANEL_TOKEN')) !== '';
    }

    /** cPanel's current API: /execute/<Module>/<function>. Returns its data. */
    public static function uapi(string $module, string $fn, array $q = []): mixed
    {
        $d = self::get('/execute/' . rawurlencode($module) . '/' . rawurlencode($fn) . '?' . http_build_query($q), $fn);
        if ((int) ($d['status'] ?? 0) !== 1) throw new RuntimeException('cPanel: ' . trim(strip_tags((string) (($d['errors'] ?? [])[0] ?? 'it didn\'t work.'))));
        return $d['data'] ?? null;
    }

    /** cPanel's older API (API 2). Returns its data rows. */
    public static function api2(string $module, string $fn, array $q = []): array
    {
        $user = trim((string) getenv('CPANEL_USER'));
        $d = self::get('/json-api/cpanel?' . http_build_query(['cpanel_jsonapi_user' => $user, 'cpanel_jsonapi_apiversion' => 2, 'cpanel_jsonapi_module' => $module, 'cpanel_jsonapi_func' => $fn] + $q), $fn);
        $r = (array) ($d['cpanelresult'] ?? []);
        $err = (string) ($r['error'] ?? '');
        if ($err !== '' || (isset($r['event']['result']) && (int) $r['event']['result'] !== 1)) throw new RuntimeException('cPanel: ' . trim(strip_tags($err ?: 'it didn\'t work.')));
        return (array) ($r['data'] ?? []);
    }

    private static function get(string $path, string $fn): array
    {
        if (!self::ready()) throw new RuntimeException('cPanel isn\'t connected: add CPANEL_HOST, CPANEL_USER and CPANEL_TOKEN to .env.');
        $host = preg_replace('#^https?://|[:/].*$#', '', trim((string) getenv('CPANEL_HOST')));
        require_once __DIR__ . '/StagingGate.php'; if (($w = StagingGate::hold('https://' . $host . ':2083' . strtok($path, '?'), 'cPanel: ' . $fn)) !== null) throw new RuntimeException($w);   // our hosting account: never changed from staging
        $ch = curl_init('https://' . $host . ':2083' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Authorization: cpanel ' . trim((string) getenv('CPANEL_USER')) . ':' . trim((string) getenv('CPANEL_TOKEN'))]]);
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        if ($raw === false) { error_log('cpanel ' . $fn . ': ' . $err); throw new RuntimeException('cPanel didn\'t answer. Check CPANEL_HOST in .env, or try again.'); }
        if ($code === 401 || $code === 403) throw new RuntimeException('cPanel refused the API token. Check CPANEL_USER and CPANEL_TOKEN in .env.');
        $d = json_decode((string) $raw, true);
        if (!is_array($d)) { error_log('cpanel ' . $fn . ': HTTP ' . $code . ' ' . mb_substr((string) $raw, 0, 200)); throw new RuntimeException('cPanel gave an answer we couldn\'t read (HTTP ' . $code . ').'); }
        return $d;
    }

    /**
     * Adds DNS records to a zone hosted here (Bluehost's nameservers: traversence.com, trvgo.us) through cPanel's API,
     * skipping any already there. $records: [[name relative to the zone, type (CNAME, MX, TXT), value, 'one'?], …]; an MX
     * value is "priority host". 'one' (a TXT that must be the only one of its kind at that name, like DMARC): a record at
     * that name starting the same way ("v=DMARC1") is changed to the new value, not joined by a second, and any other
     * such record there is removed (2026-11-10: two DMARC records had built up, so providers ignored both and Outlook
     * marked our notices Unverified). Returns [added, changed or removed, already there]. Used for the email sending
     * domains (decisions/0137 §8).
     */
    public static function dnsEnsure(string $zone, array $records): array
    {
        [$serial, $have, $lines] = self::zoneRead($zone);
        $add = []; $edit = []; $skip = 0; $remove = [];
        foreach ($records as $rec) {
            [$rel, $type, $value] = $rec;
            $one = ($rec[3] ?? '') === 'one';
            if ($one) {   // only one record of its kind at this name: any other (an older value) goes
                $tag = strtolower((string) strtok((string) $value, ';'));
                $want = self::recKey($zone, $rel, $type, (string) $value);
                $kept = isset($have[$want]);
                foreach ($lines[self::fqName($zone, $rel) . '|' . $type] ?? [] as [$li, $txt]) {
                    if (!str_starts_with(strtolower(ltrim($txt, '"')), $tag)) continue;
                    if (self::recKey($zone, $rel, $type, $txt) === $want) continue;   // the one we want
                    if (!$kept) { $kept = true; continue; }   // the first old one is changed to the new value below
                    $remove[] = $li;
                }
            }
            if (isset($have[self::recKey($zone, $rel, $type, (string) $value)])) { $skip++; continue; }
            $data = $type === 'MX' ? array_values(array_filter(explode(' ', (string) $value, 2), 'strlen')) : [(string) $value];
            if ($type === 'MX' && isset($data[1])) $data[1] = rtrim($data[1], '.') . '.';
            if ($type === 'CNAME') $data[0] = rtrim($data[0], '.') . '.';
            $row = ['dname' => $rel, 'ttl' => 3600, 'record_type' => $type, 'data' => $data];
            $old = null;
            if ($one) {
                $tag = strtolower((string) strtok((string) $value, ';'));   // "v=dmarc1"
                foreach ($lines[self::fqName($zone, $rel) . '|' . $type] ?? [] as [$li, $txt]) if (str_starts_with(strtolower(ltrim($txt, '"')), $tag)) { $old = $li; break; }
            }
            if ($old !== null) $edit[] = json_encode(['line_index' => $old] + $row, JSON_UNESCAPED_SLASHES);
            else $add[] = json_encode($row, JSON_UNESCAPED_SLASHES);
        }
        if ($add || $edit || $remove) {
            $q = ['zone' => $zone, 'serial' => $serial];
            foreach ($add as $i => $a) $q[$i === 0 ? 'add' : 'add-' . $i] = $a;   // UAPI takes a repeated value as add, add-1, add-2…
            foreach ($edit as $i => $a) $q[$i === 0 ? 'edit' : 'edit-' . $i] = $a;
            foreach (array_values(array_unique($remove)) as $i => $li) $q[$i === 0 ? 'remove' : 'remove-' . $i] = (string) $li;
            self::uapi('DNS', 'mass_edit_zone', $q);
            // read it back: cPanel answering "done" isn't the records being there (2026-11-02: it said done, the zone had none)
            [, $now] = self::zoneRead($zone);
            $miss = [];
            foreach ($records as $rec) if (!isset($now[self::recKey($zone, $rec[0], $rec[1], (string) $rec[2])])) $miss[] = $rec[1] . ' ' . self::fqName($zone, $rec[0]);
            if ($miss) throw new RuntimeException('cPanel accepted the DNS records but ' . $zone . '\'s zone doesn\'t have ' . count($miss) . ' of them (' . implode(', ', array_slice($miss, 0, 4)) . (count($miss) > 4 ? ', …' : '') . ').');
        }
        return [count($add) + count($edit) + count(array_unique($remove)), $skip];
    }

    /** The records at one name in a zone hosted here ('@' for the zone itself): [[type, value], …]. Staging's DNS check (StagingSite). */
    public static function records(string $zone, string $name): array
    {
        $want = self::fqName($zone, $name); $out = [];
        foreach ((array) self::uapi('DNS', 'parse_zone', ['zone' => $zone]) as $r) {
            if (($r['type'] ?? '') !== 'record') continue;
            if (self::fqName($zone, (string) base64_decode((string) ($r['dname_b64'] ?? ''))) !== $want) continue;
            $out[] = [(string) ($r['record_type'] ?? ''), implode(' ', array_map(fn ($x) => (string) base64_decode((string) $x), (array) ($r['data_b64'] ?? [])))];
        }
        return $out;
    }

    /** A zone's records as the server holds them: [serial, set of record keys, [name|type => [[line, text], …]]]. */
    private static function zoneRead(string $zone): array
    {
        $rows = (array) self::uapi('DNS', 'parse_zone', ['zone' => $zone]);
        $serial = ''; $have = []; $lines = [];
        foreach ($rows as $r) {
            if (($r['type'] ?? '') !== 'record') continue;
            $name = (string) base64_decode((string) ($r['dname_b64'] ?? ''));
            $data = array_map(fn ($x) => (string) base64_decode((string) $x), (array) ($r['data_b64'] ?? []));
            $type = (string) ($r['record_type'] ?? '');
            if ($type === 'SOA') { $serial = (string) ($data[2] ?? ''); continue; }
            $have[self::recKey($zone, $name, $type, implode(' ', $data))] = true;
            if (isset($r['line_index'])) $lines[self::fqName($zone, $name) . '|' . $type][] = [(int) $r['line_index'], implode('', $data)];
        }
        if ($serial === '') throw new RuntimeException('cPanel: couldn\'t read the zone\'s serial for ' . $zone . '.');
        return [$serial, $have, $lines];
    }

    /** A name as the whole name: "x._domainkey.send", "x._domainkey.send.traversence.com" and "...com." are the same record. */
    public static function fqName(string $zone, string $name): string
    {
        $n = strtolower(rtrim(trim($name), '.'));
        $z = strtolower(rtrim($zone, '.'));
        if ($n === '' || $n === '@') return $z;
        return ($n === $z || str_ends_with($n, '.' . $z)) ? $n : $n . '.' . $z;
    }

    /** One key per record, however cPanel writes it: the whole name, the type, the value without quotes or a final dot. */
    public static function recKey(string $zone, string $name, string $type, string $value): string
    {
        $v = strtolower(trim(preg_replace('/\s+/', ' ', str_replace('"', '', $value))));
        $v = preg_replace('/\.(\s|$)/', '$1', $v);
        return self::fqName($zone, $name) . '|' . strtoupper($type) . '|' . $v;
    }
}
