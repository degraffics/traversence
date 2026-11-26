<?php
// app/lib/StagingApi.php — the actions on staging as a whole (decisions/0165 §5), one place for both doors: System
// Configuration → Staging on the live admin (website/admin/api.php) and the site manager on staging (its bar, through
// website/api/release-bar.php, as the person the bar belongs to with the permissions it carries). Runs on the live site.
// Each action checks its own permission: Founder for the switches and Start staging over (StagingSite::canManage),
// releases.publish to preview, publish, choose a conflict, mark a row and send what was held, releases.schedule to
// schedule, releases.rollback to roll back.
declare(strict_types=1);

require_once __DIR__ . '/StagingSite.php';
require_once __DIR__ . '/StagingSync.php';
require_once __DIR__ . '/StagingPublish.php';
require_once __DIR__ . '/StagingGate.php';
require_once __DIR__ . '/Releases.php';

final class StagingApi
{
    public const ACTS = ['summary', 'mirror', 'jobs', 'preview', 'plan', 'publish', 'carry', 'carryon', 'rollback', 'schedule', 'unschedule', 'conflicts', 'settle', 'keeplive',
        'mark', 'held', 'sendme', 'tosend', 'sendnow', 'startover_losses'];

    private static function need(string $perm): void
    {
        if ($perm === 'founder') { [$ok, $why] = StagingSite::canManage(); if (!$ok) throw new RuntimeException($why); return; }
        [$ok, $why] = Releases::can($perm);
        if (!$ok) throw new RuntimeException($why);
    }

    /** One action. $who: [user id, name, email to send "Send to me" to, time zone]. Returns the answer's fields (with 'message'). */
    public static function act(PDO $pdo, string $act, array $in, array $who): array
    {
        [$uid, $name, $email, $tz] = $who + [0, '', '', ''];
        if (!StagingSite::isSetUp($pdo)) throw new RuntimeException('Staging isn\'t set up: System Configuration → Staging → Set up staging.');
        switch ($act) {
            case 'summary': return self::summary($pdo);
            case 'mirror': self::need('founder'); return ['message' => StagingSite::keepCaughtUp($pdo, !empty($in['on']), $uid, $tz)];
            case 'jobs': self::need('founder'); return ['message' => StagingSite::jobs($pdo, !empty($in['on']), $uid)];
            case 'startover_losses': return ['losses' => StagingSite::startOverLosses($pdo)];
            case 'preview':
                self::need('releases.publish');
                @set_time_limit(600);
                $pv = StagingPublish::preview($pdo, $uid);
                return ['preview' => $pv, 'message' => self::previewLine($pv)];
            case 'plan':
                self::need('releases.publish');
                $pv = StagingPublish::state($pdo, 'preview');
                return ['rows' => $pv ? StagingPublish::planRows((string) $pv['id'], 300, preg_match('/^[a-z]{0,10}$/', (string) ($in['only'] ?? '')) ? (string) ($in['only'] ?? '') : '') : []];
            case 'publish':
                self::need('releases.publish');
                $run = StagingPublish::start($pdo, $uid, $tz);
                self::kick();
                $run = StagingPublish::carry($pdo, 20);
                return ['run' => self::runView($run), 'message' => $run['message'] ?? 'Publishing: it carries on on the server, step by step. You can close this page; an email says when it\'s done.'];
            case 'carry':
                $run = StagingPublish::state($pdo, 'publish');
                if ($run && in_array($run['state'] ?? '', ['running', 'undoing'], true)) { self::need('releases.publish'); $run = StagingPublish::carry($pdo, 15); }
                return ['run' => $run ? self::runView($run) : null];
            case 'carryon':
                self::need('releases.publish');
                $run = StagingPublish::carryOn($pdo); self::kick();
                return ['run' => self::runView(StagingPublish::carry($pdo, 15)), 'message' => 'Carried on from where it stopped.'];
            case 'rollback':
                $run = StagingPublish::rollBack($pdo, $uid); self::kick();
                return ['run' => self::runView(StagingPublish::carry($pdo, 15)), 'message' => 'Rolling back: last write first. It carries on on the server.'];
            case 'schedule':
                self::need('releases.schedule');
                $at = strtotime((string) ($in['at'] ?? '') . ' ' . ($tz !== '' ? $tz : 'UTC'));
                if (!$at || $at < time() + 300) throw new RuntimeException('Choose a time at least 5 minutes from now.');
                if (!Releases::inWindow($pdo, $at)) throw new RuntimeException('That time is outside the release window (System Configuration → Releases): choose one inside it.');
                StagingSite::putState($pdo, 'scheduled', ['at' => date('c', $at), 'by' => $uid, 'tz' => $tz]);
                return ['message' => 'Scheduled. At its time the preview is done again; it stops (and says why) if a new conflict appears.'];
            case 'unschedule':
                self::need('releases.schedule');
                StagingSite::putState($pdo, 'scheduled', null);
                return ['message' => 'Taken off the schedule.'];
            case 'conflicts': self::need('releases.publish'); return ['conflicts' => self::conflictsView()];
            case 'settle':
                self::need('releases.publish');
                $realm = (string) ($in['realm'] ?? ''); $t = (string) ($in['tbl'] ?? ''); $k = (string) ($in['k'] ?? '');
                if (!isset(StagingSync::pairs()[$realm]) || !preg_match('/^[a-z0-9_]{1,64}$/', $t)) throw new RuntimeException('That conflict isn\'t here.');
                $choice = $in['choice'] ?? '';
                if (!in_array($choice, ['live', 'staging'], true) && !is_array($choice)) throw new RuntimeException('Choose Keep live, Use staging, or a choice per field.');
                StagingSync::settle($realm, $t, $k, $choice, $uid);
                return ['message' => $choice === 'live' ? 'Kept live\'s: staging took it too.' : 'Chosen: Publish carries it.', 'conflicts' => self::conflictsView()];
            case 'keeplive':
                self::need('releases.publish');
                $n = StagingSync::keepLiveForRest($uid);
                return ['message' => 'Kept live\'s for the rest (' . $n . ').', 'conflicts' => []];
            case 'mark':
                self::need('releases.publish');
                $realm = (string) ($in['realm'] ?? '');
                if (!isset(StagingSync::pairs()[$realm])) throw new RuntimeException('That row isn\'t here.');
                StagingPublish::mark($realm, (string) ($in['tbl'] ?? ''), (string) ($in['k'] ?? ''), (string) ($in['as'] ?? ''));
                return ['message' => ($in['as'] ?? '') === 'test' ? 'Marked test: left out. Preview again to see it.' : 'Marked work: carried. Preview again to see it.'];
            case 'held': return ['held' => self::heldView(min(200, max(1, (int) ($in['limit'] ?? 50))))];
            case 'sendme':   // a held email to the staff member's own address, marked as from staging; never to the real recipient
                $h = self::heldRow((int) ($in['id'] ?? 0));
                if ($h['kind'] !== 'mail') throw new RuntimeException('Only an email can be sent to you.');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Your account has no email address to send it to.');
                require_once __DIR__ . '/Mailer.php';
                Mailer::send($email, '[From staging] ' . (string) $h['subject'], "Held on staging (it was for " . $h['target'] . "; it wasn't sent to them).\n\n" . (string) $h['body_text']);
                return ['message' => 'Sent to you (' . $email . '), marked as from staging. Nothing went to ' . $h['target'] . '.'];
            case 'tosend':
                $t = StagingPublish::state($pdo, 'to_send');
                return ['items' => $t ? array_values(array_filter((array) $t['items'], fn ($x) => !self::replayed((int) $x['id']))) : []];
            case 'sendnow':
                self::need('releases.publish');
                return ['message' => StagingPublish::sendNow($pdo, (int) ($in['id'] ?? 0), !empty($in['send']), $uid)];
        }
        throw new RuntimeException('Unknown action.');
    }

    /** The site manager's line: "Staging · 41 changes · 2 held · 1 conflict · caught up 2:10 AM MST", and what's under it. */
    public static function summary(PDO $pdo): array
    {
        $rel = Releases::onStaging($pdo);
        $changes = 0; $conflicts = 0; $held = 0;
        foreach (StagingSync::pairs() as $realm => [$c, , $s]) {
            try { $changes += (int) $c->query('SELECT COUNT(*) FROM ' . StagingSync::q($s, 'stg_changes'))->fetchColumn(); } catch (Throwable) {}
            try { $conflicts += (int) $c->query('SELECT COUNT(*) FROM ' . StagingSync::q($s, 'stg_conflicts') . ' WHERE settled_at IS NULL')->fetchColumn(); } catch (Throwable) {}
        }
        $p = StagingSync::pairs();
        if (isset($p['main'])) { try { $held = (int) $p['main'][0]->query('SELECT COUNT(*) FROM ' . StagingSync::q($p['main'][2], 'staging_held') . ' WHERE replayed_at IS NULL')->fetchColumn(); } catch (Throwable) {} }
        $push = StagingSite::get($pdo, 'pushed'); $night = StagingSite::get($pdo, 'night');
        $caught = max($push ? (int) strtotime((string) $push['at']) : 0, $night ? (int) strtotime((string) $night['at']) : 0);
        $root = StagingSite::stagingRoot($pdo);
        $env = $root !== '' ? StagingSite::readEnvFile(dirname($root, 2) . '/.env') : [];
        $ung = StagingSite::get($pdo, 'ungated');
        $run = StagingPublish::state($pdo, 'publish');
        $last = StagingPublish::state($pdo, 'last_publish');
        return [
            'releases' => array_map(fn ($r) => ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'ok' => (bool) $r['checks_ok'] && Releases::reviewed($pdo, (int) $r['id']), 'replaced' => Releases::replacedIn($pdo, $r)], $rel),
            'changes' => $changes, 'held' => $held, 'conflicts' => $conflicts, 'caught_up' => $caught ?: null, 'mirror' => StagingSite::mirrorOn($pdo), 'mirror_state' => StagingSite::mirrorState($pdo),
            'jobs' => !empty(StagingSite::get($pdo, 'jobs')['on']), 'night' => $night, 'live_keys' => StagingGate::liveKeys($env), 'ungated' => $ung ? count((array) $ung['calls']) : null,
            'ungated_list' => $ung ? array_slice((array) $ung['calls'], 0, 30) : [], 'preview' => StagingPublish::state($pdo, 'preview'), 'run' => $run ? self::runView($run) : null,
            'last' => $last, 'scheduled' => StagingPublish::state($pdo, 'scheduled'), 'data_note' => '',
        ];
    }

    public static function line(array $s): string
    {
        $n = count($s['releases']);
        return 'Staging · ' . ($n ? $n . ' upload' . ($n === 1 ? '' : 's') . ' waiting · ' : '') . $s['changes'] . ' change' . ($s['changes'] === 1 ? '' : 's') . ' · ' . $s['held'] . ' held · '
             . $s['conflicts'] . ' conflict' . ($s['conflicts'] === 1 ? '' : 's')
             . ($s['mirror'] ? '' : ' · ' . (['turning_on' => 'keeping caught up: turning on', 'stuck' => 'keeping caught up didn\'t turn on'][$s['mirror_state']['state'] ?? ''] ?? 'not kept caught up'));
    }

    public static function previewLine(array $pv): string
    {
        $parts = [];
        foreach ($pv['counts'] as $kind => $c) $parts[] = $kind . ': ' . implode(', ', array_map(fn ($w, $n) => $n . ' ' . $w, array_keys($c), $c));
        return 'Preview: ' . count($pv['releases']) . ' release' . (count($pv['releases']) === 1 ? '' : 's') . ' (' . $pv['files'] . ' files), ' . $pv['rows'] . ' rows' . ($parts ? ' (' . implode('; ', $parts) . ')' : '')
             . ', ' . $pv['conflicts'] . ' conflict' . ($pv['conflicts'] === 1 ? '' : 's') . ', about ' . $pv['minutes'] . ' min.' . ($pv['blocking'] ? ' Publish waits: ' . implode(' ', $pv['blocking']) : ' Publish is open.');
    }

    public static function runView(array $run): array
    {
        return ['id' => (string) ($run['id'] ?? ''), 'state' => (string) ($run['state'] ?? ''), 'step' => StagingPublish::stepName((string) ($run['steps'][$run['pos'] ?? 0] ?? 'finish')),
                'pos' => (int) ($run['pos'] ?? 0), 'of' => count((array) ($run['steps'] ?? [])), 'message' => (string) ($run['message'] ?? ''), 'log' => array_slice((array) ($run['log'] ?? []), -12),
                'late' => count((array) ($run['late'] ?? [])), 'left' => array_slice((array) ($run['left'] ?? []), 0, 50), 'started_at' => (string) ($run['started_at'] ?? ''), 'finished_at' => (string) ($run['finished_at'] ?? ''),
                'tz' => (string) ($run['tz'] ?? '')];
    }

    public static function conflictsView(): array
    {
        $out = [];
        foreach (StagingSync::conflicts(100) as $x) {
            $out[] = ['realm' => $x['realm'], 'tbl' => $x['tbl'], 'k' => $x['k'], 'kind' => StagingPublish::kindOf((string) $x['tbl']), 'why' => $x['why'], 'found_at' => $x['found_at'],
                      'clash' => json_decode((string) $x['clash'], true) ?: [], 'base' => json_decode((string) $x['base_row'], true), 'staging' => json_decode((string) $x['stg_row'], true), 'live' => json_decode((string) $x['live_row'], true)];
        }
        return $out;
    }

    public static function heldView(int $limit): array
    {
        $p = StagingSync::pairs(); if (!isset($p['main'])) return [];
        [$c, , $s] = $p['main'];
        try { return $c->query('SELECT id, kind, service, target, subject, ref, why, actor, created_at, replayed_at, replayed_to FROM ' . StagingSync::q($s, 'staging_held') . ' ORDER BY id DESC LIMIT ' . $limit)->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable) { return []; }
    }

    private static function heldRow(int $id): array
    {
        $p = StagingSync::pairs(); [$c, , $s] = $p['main'];
        $st = $c->prepare('SELECT * FROM ' . StagingSync::q($s, 'staging_held') . ' WHERE id = ?'); $st->execute([$id]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        if (!$h) throw new RuntimeException('That held item isn\'t there any more.');
        return $h;
    }

    private static function replayed(int $id): bool
    {
        try { return self::heldRow($id)['replayed_at'] !== null; } catch (Throwable) { return true; }
    }

    /** Makes sure the server carries a publish on (staging_publish_tick in cPanel), and starts a run of it now where it can. */
    private static function kick(): void
    {
        require_once __DIR__ . '/Scheduler.php';
        try {
            if (CPanel::ready()) { $lines = Scheduler::cronLines(); if (!Scheduler::lineFor($lines, 'staging_publish_tick')) Scheduler::add('staging_publish_tick', 'minute'); }
        } catch (Throwable $e) { error_log('staging publish kick (cron): ' . $e->getMessage()); }
    }
}
