<?php
// app/lib/Scheduler.php — our scheduled scripts (app/scripts/*_tick.php, ai_worker.php) and their cron jobs in cPanel,
// set from System Configuration → Scheduled tasks through the cPanel API (CPanel::api2, module Cron). Each script
// records its own runs (run() at its top), so the page shows when each last ran and whether it worked, whether or not
// cPanel can be reached from here. Cron runs on the server's clock.
declare(strict_types=1);

require_once __DIR__ . '/AdminTables.php';
require_once __DIR__ . '/CPanel.php';

final class Scheduler
{
    /** Our jobs: key (its script, app/scripts/<key>.php) => [name, what it does, the preset it starts on, how late is "late" in minutes]. A daily
     * job's "late" is a day plus two hours (1560), never less than its own schedule: seven daily jobs said Late hours before they were
     * due (2026-11-24, Scheduled tasks on live). */
    public const JOBS = [
        'control_tick' => ['The System Control Board\'s watch', 'Every 5 minutes: takes out and puts back the maintenance windows set ahead, and catches what goes quiet (W0 Indexer, a crawler run that never reported, a site job that stopped or never finished) as alarms on the board, and emails a warning left unacknowledged a day (decisions/0171 §5).', '5min', 30],
        'team_audit_tick' => ['The Team\'s daily audit', 'On the staging copy only, while the Team\'s runner is switched on: the Operations & Reconciliation Manager reads Site health\'s findings, late or failed jobs and open alarms, opens one Audit ticket per new finding, and a day later confirms it (opening the Work or Resolution ticket it calls for) or clears it as noise (decisions/0179\'s Piece 2). On the live site it does nothing and logs nothing.', 'daily', 1560],
        'founder_mail_tick' => ['Email to the Founder', 'Every 10 minutes on the live site: today\'s daily batch and the session manager\'s messages, each emailed once to the Founder through the site\'s own email (FounderMail, Notify). Logs only when it sends.', '10min', 60],
        'team_tick' => ['The Team\'s runner', 'On the staging copy only, while its switch is on: takes one ticket from the Work queue, does one step for one working role, logs it with Undo, and stops (decisions/0179\'s Piece 2). On the live site it does nothing and logs nothing.', '10min', 60],
        'ai_worker' => ['AI worker', 'Reads queued AI jobs: scanned business cards now, menus and brochures with the Studio.', 'minute', 15],
        'standing_tick' => ['Notices of action', 'Moves notices whose 24 hours ran out (page loads do it too, every few minutes).', '10min', 60],
        'sales_tick' => ['Lead scores', 'Scores the listings worth a call and gives new listings in a territory to its rep.', 'hourly', 180],
        'files_tick' => ['Files to the file store', 'Moves files still on the server into their owners\' folders in the file store (Cloudflare R2), a batch at a time: files from before (decisions/0124), and any upload the store didn\'t take at the time, which waits on the server and is tried again here (decisions/0163\'s source map: file storage, else uploads wait).', '10min', 60],
        'work_orders_tick' => ['Work order reminders', 'Asks vendors for an update: a work order not accepted by 9:00 am the next business day, 24 hours before its must-have time, and at it.', 'hourly', 180],
        'guide_finds_tick' => ['Find it and guide drafts', 'Looks up the names a guide mentions that we don\'t have yet (our crawler first, then web search with Claude) and sends what it confirms through the listings pipeline, which publishes the ready ones; links names once they\'re listed. And drafts one guide a run with Claude (asked-for ones first, then unwritten city and community guides), published by its score. And, while the Live events template is set running, looks for one place\'s upcoming events.', '15min', 60],
        'listings_tick' => ['Listings pipeline', 'Matches the crawler\'s waiting listings again, merges the ones we already have and publishes the ready ones by rule, without anyone opening Review.', '15min', 60],
        'guide_live_tick' => ['Guide weather and roads', 'Keeps each guide\'s weather (National Weather Service) and Arizona\'s road work (AZ 511) fresh ahead of visitors, so a guide never waits on them.', '15min', 60],
        'got_it_tick' => ['Did it arrive?', 'Reminds a client to tell us they have their order (that completes it), 72 hours after the last of it shipped or was ready for pickup, once.', 'hourly', 180],
        'qb_orders_tick' => ['QuickBooks invoices on orders', 'Reads the QuickBooks invoice each open order names (one made here, or one made by hand and linked by its number) and records any payment QuickBooks shows that isn\'t on the order yet, so the order moves on by itself (M1b). Writes nothing to QuickBooks.', 'hourly', 180],
        'pay_wait_tick' => ['Payment reminders', 'Reminds a client who chose wire or check and hasn\'t given a confirmation code (and tells support): 2 hours after, 9:00 am the next business day, and 4 pm with the other ways to pay.', '15min', 60],
        'services_tick' => ['Services & usage', 'The service register\'s upkeep (today\'s caps recorded once; watching Railway and the sync, when it\'s switched on), and the month\'s report on the 1st by email (decisions/0133, 0163). The 50/75/95% warnings are Pulse cases.', 'hourly', 180],
        'takedowns_tick' => ['Takedowns and the guide audit', 'Hides what someone asked us to take down once 24 hours pass without a person deciding (a protected reason hides it at once), and draws the quarterly sample of published guides to audit (decisions/0133).', '10min', 60],
        'heritage_tick' => ['Heritage catalog', 'Reads each guide\'s place (the Library of Congress\'s records the crawler found, its dossier and research) into vetted facts for the heritage catalog and the guides\' "Walking in the footsteps" chapter, files them in the Topic index, keeps topic summaries current and researches the most asked-for thin topics.', 'hourly', 180],
        'daily_report' => ['Daily report', 'Emails the Founder one report a day at 5 am Arizona time (Stream settings): every open Pulse case with a button to its step, and the system status. It checks each hour and sends once the hour comes.', 'hourly2', 180],
        'market_tick' => ['Marketplace check', 'Asks sellers "Still available?" of every post that\'s due, and takes off the ones asked at the last check that nobody confirmed (decisions/0135 §8). Closes offers nobody answered in 7 days and reminds a trade\'s other side to confirm (§4).', 'daily', 1560],
        'mail_sync' => ['Mailboxes in Pulse', 'Reads every connected mailbox (ours by IMAP, outside ones by IMAP, POP or Microsoft\'s Graph) into its readers\' Pulse inbox, and keeps read state in step (decisions/0172 §4.7). A refused password becomes a Reconnect message in Pulse.', '5min', 60],
        'mail_keep' => ['Mailboxes: keep 90 days', 'Our working copy of connected mailboxes keeps the last 90 days for search and threading; the mailbox itself keeps everything (decisions/0172 §4.7).', 'daily', 1560],
        'pulse_followup' => ['Pulse: follow-ups', 'Brings back, unread and on top, each item someone flagged to follow up once its time comes, and tells them (decisions/0172, Flags and tags in Pulse).', '10min', 60],
        'booking_remind' => ['Booking reminders', 'Reminds customers a day before a stay or rental and 2 hours before check-in, once each (decisions/0172 §2.5).', '15min', 60],
        'listing_messages_tick' => ['Messages left on unclaimed listings', 'Paces visitors\' requests to unclaimed listings\' emails (the 10-day notice, paused ones resumed), deletes unconfirmed guest emails after 48 hours and expires messages never claimed after 90 days, telling their senders (decisions/0172 §3.3).', 'hourly', 120],
        'notify_release' => ['Quiet hours: held email', 'Sends the email that waited for someone\'s quiet hours once they end (decisions/0172, the one dispatcher). Messages already read in their Pulse aren\'t emailed.', '10min', 60],
        'notice_digest' => ['Notices by email: weekly digest', 'Sends each person who chose a weekly email of their notices one email of the week\'s notices, through Amazon SES (decisions/0137 §6, §8). Runs daily; each person gets one every 7 days.', 'daily', 1560],
        'ses_setup_tick' => ['Email sending setup', 'Sets up Amazon SES by itself (decisions/0137 §8): the sending domains, their DNS records through the cPanel API, bounce reports, the check with AWS, asking for production access, and one test once live. Does nothing once the setup is done.', '15min', 60],
        'domains_tick' => ['Businesses\' domains', 'Checks businesses\' own domains on their storefronts (decisions/0139 §3): DNS and the certificate for the ones waiting, live ones once a day, and renews domains bought through us two weeks before they run out.', '15min', 60],
        'billing_tick' => ['Plans and renewals', 'Renews what\'s due on the card on file, retries declined cards, ends trials and sends the reminders.', 'daily', 1560],
        'region_tick' => ['Sign-up region check', 'Keeps the EU/EEA/UK address ranges for the sign-up check fresh (decisions/0027): downloads DB-IP\'s free country file (CC BY 4.0) once a month; daily it only checks.', 'daily', 1560],
        'guide_ondemand_tick' => ['Guides on demand', 'Carries on guides a searcher asked for after they leave, writes the ones waiting for the allowance (most asked first), and writes each first look in full once its deeper research is in (decisions/0147).', '5min', 30],
        'journeys_tick' => ['Suggested journeys', 'Builds each live guide\'s suggested journeys from its own places by rules (no AI), three guides a run, again weekly or when the guide changes; asks openrouteservice once for a town\'s travel times and keeps them (decisions/0146).', 'hourly', 180],
        'supabase_keepalive' => ['Supabase keep-alive', 'One tiny read a day so the free Supabase project doesn\'t pause (decisions/0164 §5.2). Under Supabase\'s own switch, not the master: it gathers nothing and costs nothing.', 'daily', 1560],
        'page_read_tick' => ['Pages waiting to be read', 'Pages whose reading had to stop are read into facts by the AI that AI use routes "Read a public page into facts" to (decisions/0164 §2.3; gap review P1-1). Shipped off: it runs only while automation, W3 and that feature are on. Not set up in cPanel by itself.', 'hourly', 300],
        'vectors_tick' => ['Vector index', 'Public listings\' text turned into vectors on Supabase for semantic search (decisions/0164 §5.2). Shipped off: it runs only while automation, W3, Supabase and AI use\'s "vector index" are on. Not set up in cPanel by itself.', 'hourly', 300],
        'identity_recheck_tick' => ['Identity recheck', 'Live listings checked against each other (decisions/0164 §6.1): exact twins merged by themselves with Undo, variations vetted, the rest to "The engine needs a person". Shipped off: it runs only while automation, W2 and its task are on. Not set up in cPanel by itself: add it here, in a quiet hour.', 'daily', 1560],
        'data_cleanup_tick' => ['Data cleanup', 'A pass for every rule of Insights → Data cleanup, once a day, so what the crawler made stays in line with today\'s rules. Shipped off: it does nothing until its daily pass is switched on there, and only while automation is on. Not set up in cPanel by itself: add it here when it\'s wanted.', 'daily', 1560],
        'landmark_photos_tick' => ['Landmark and place photos', 'Looks on Wikimedia Commons for a free photo of each shown landmark that has none (taken nearby and named for it), 20 an hour, then of the parks, monuments, visitor centres and historic sites on our cards (15 an hour, never a place kept private), and keeps the best with its credit and licence. One with none found is looked at again after 30 days.', 'hourly', 180],
        'like_watch_tick' => ['Like checks', 'Looks at the last hour\'s likes: takes machine likes (perfectly timed, or more likes than views) off the count, reports groups of new accounts and guests to Safety in Pulse, and deletes like records older than 30 days.', 'hourly', 180],
        'earned_storage_tick' => ['Earned storage', 'Counts each member\'s qualifying followers and friends who joined, and adds the storage they\'ve earned: 1 GB per 500 followers and per friend, up to 25 GB, 5 GB a month at most (decisions/0154 §7).', 'daily', 1500],
        'moments_tick' => ['Moments', 'Ends posts whose 3 or 7 days are up (deletes them and their photos and videos, unless kept or waiting on a report), tells people a day before, lets go of uploads never posted, and releases reported posts\' held copies after their 90 days (decisions/0153).', 'hourly', 180],
        'guest_tick' => ['Guests\' 30 days', 'Deletes the hashed connections behind guests\' likes after 30 days (decisions/0009); the likes stay in the counts.', 'daily', 1560],
        'vault_digest_tick' => ['Vault email summaries', 'Sends the vault\'s hourly and daily email summaries to whoever chose them (the vault\'s Emails), only when something happened. Alerts about devices, recovery words and locks always go at once.', 'hourly', 180],
        'releases_tick' => ['Releases', 'Publishes a release when its scheduled time comes, and moves live releases on a step (members, then everyone) once their hours have passed clean, inside the release window (System Configuration → Releases). A failed check pauses it and emails whoever approved it.', '10min', 60],
        'staging_carry_tick' => ['Staging refresh, carried on', 'Works through a staging refresh on the server once one is started (System Configuration → Staging, or the weekly Staging refresh), so the page needn\'t stay open. Does nothing when no refresh is running.', 'minute', 15],
        'audit_clock_tick' => ['Site audit clock', 'Asks GitHub to run the site audit\'s checks when their hour comes (the code checks at 7:00 and 19:00 UTC, the browser checks and paths at 9:00, the search probe at 10:00), because GitHub\'s own schedule skips them when it\'s busy (decisions/0145). Site health in Pulse says when one hasn\'t run in 26 hours. Does nothing on staging.', 'hourly', 180],
        'staging_tick' => ['Staging refresh (stopped)', 'The weekly copy of live over staging stopped with decisions/0165: staging catches up from live instead, keeping its own work. Start staging over is a button on System Configuration → Staging. It does nothing now and can be removed.', 'weekly', 10140],
        'staging_push_tick' => ['Staging: live\'s changes pushed', 'Sends each change on the live site to staging within a minute (live\'s outbox), for the rows staging didn\'t touch; a row both changed goes on the conflicts list (decisions/0165 §3). Does nothing until Keep staging caught up is switched on (System Configuration → Staging).', 'minute', 15],
        'staging_night_tick' => ['Staging: the nightly full pass', 'Every night (2:13 am Arizona): every row\'s fingerprint against staging\'s base, which finds a missed push, a delete the database cascaded and a change made outside the code; copies again the reference tables live changed (decisions/0165 §3). Does nothing until Keep staging caught up is on.', 'nightly', 1560],
        'frontier_tick' => ['The index\'s upkeep (W0)', 'Keeps W0 Indexer\'s frontier in order (decisions/0164 §1.4): sites not decided yet classified by the machine (a tribal nation\'s set aside for a person), places\' own websites ranked first, junk seen often made a skip rule, URLs never read in six months kept as their hash, its size recorded for the pre-flight. Database only: no outside call, so it runs whatever the switches say.', 'hourly', 180],
        'search_index_tick' => ['Search index catch-up', 'Every 15 minutes: brings search up to date with the listings changed since its last look (600 at a time, carrying on where it stopped), rebuilds stories, groups and topics, and indexes new landmarks (SearchIndex::refresh). It used to run inside a visitor\'s search, which slowed that search (decisions/0181); a search still does it when this job hasn\'t run in 30 minutes. Database only: no outside call.', '15min', 60],
        'preflight_tick' => ['Pre-flight', 'Runs every pre-flight test (System Configuration → Pre-flight) each night at 2 am Arizona time, and within 15 minutes of a .env change: real, free, read-only tests of every service, setting, workflow and task, red lines to Pulse ("Pre-flight"). It never starts, pauses or changes anything.', '15min', 60],
        'system_start_tick' => ['Start the system, carried on', 'Works through Start the system (System Configuration → Automation) once it\'s pressed: the keys, watching, the switch-on check, Railway\'s schedule, the master switch, then each workflow and new task one at a time behind its gate (decisions/0163 §10). Does nothing until it\'s pressed, and never on staging.', '10min', 120],
        'staging_publish_tick' => ['Staging: Publish, carried on', 'Works through a Publish of staging on the server once it\'s started (the site manager), a step at a time, so the page can close; starts a scheduled one at its time after a fresh preview (decisions/0165 §4). Does nothing when none is running.', 'minute', 15],
    ];

    /** Jobs that set themselves up in cPanel (sync()), on their usual schedule, with no one opening cPanel (Jason, 2026-10-28):
     *  every new job goes here. One removed on the Scheduled tasks page stays removed. */
    public const AUTO = ['control_tick', 'founder_mail_tick', 'team_tick', 'team_audit_tick', 'moments_tick', 'earned_storage_tick', 'guide_ondemand_tick', 'journeys_tick', 'landmark_photos_tick', 'like_watch_tick', 'guest_tick', 'region_tick', 'work_orders_tick', 'pay_wait_tick', 'got_it_tick', 'qb_orders_tick', 'guide_finds_tick', 'guide_live_tick', 'listings_tick', 'services_tick', 'heritage_tick', 'takedowns_tick', 'daily_report', 'market_tick', 'notice_digest', 'notify_release', 'listing_messages_tick', 'mail_sync', 'mail_keep', 'pulse_followup', 'booking_remind', 'ses_setup_tick', 'domains_tick', 'staging_carry_tick', 'staging_push_tick', 'staging_night_tick', 'staging_publish_tick', 'releases_tick', 'vault_digest_tick', 'audit_clock_tick', 'system_start_tick', 'frontier_tick', 'search_index_tick', 'preflight_tick', 'files_tick'];

    /** Schedules to choose from: key => [label, minute, hour, day, month, weekday]. */
    public const PRESETS = [
        'minute' => ['Every minute', '*', '*', '*', '*', '*'],
        '5min' => ['Every 5 minutes', '*/5', '*', '*', '*', '*'],
        '10min' => ['Every 10 minutes', '*/10', '*', '*', '*', '*'],
        '15min' => ['Every 15 minutes', '*/15', '*', '*', '*', '*'],
        'hourly' => ['Every hour (at :17)', '17', '*', '*', '*', '*'],
        'hourly2' => ['Every hour (at :02)', '2', '*', '*', '*', '*'],
        '6hours' => ['Every 6 hours', '17', '*/6', '*', '*', '*'],
        'daily' => ['Every day at 6:13 am', '13', '6', '*', '*', '*'],
        'nightly' => ['Every night at 2:13 am', '13', '2', '*', '*', '*'],
        'weekly' => ['Every Sunday at 3:13 am', '13', '3', '*', '*', '0'],
    ];

    public static function ensure(PDO $pdo): void
    {
        AdminTables::make($pdo, ['job_runs' => '(id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, job VARCHAR(40) NOT NULL, started_at DATETIME NOT NULL,
            finished_at DATETIME DEFAULT NULL, ok TINYINT(1) DEFAULT NULL, note VARCHAR(255) NOT NULL DEFAULT \'\', KEY idx_job_runs (job, id))',
            'job_setup' => '(job VARCHAR(40) NOT NULL PRIMARY KEY, state VARCHAR(10) NOT NULL, at DATETIME NOT NULL)']);
    }

    // ---- a script's own run --------------------------------------------------------------------------------------------

    /** Called at the top of a scheduled script: records the run, and at the end whether it finished and what it said. */
    public static function run(string $job): void
    {
        try {
            $pdo = Database::connection();
            self::ensure($pdo);
            $pdo->prepare('INSERT INTO ' . Database::t('job_runs') . ' (job, started_at) VALUES (?, NOW())')->execute([$job]);
            $id = (int) $pdo->lastInsertId();
            if (random_int(1, 50) === 1) $pdo->prepare('DELETE FROM ' . Database::t('job_runs') . ' WHERE job = ? AND started_at < NOW() - INTERVAL 14 DAY')->execute([$job]);
        } catch (Throwable $e) { error_log('scheduler: ' . $e->getMessage()); self::missed($job, $e->getMessage()); return; }   // the job still runs, unrecorded: its miss is kept for the health report
        try { self::sync($pdo); } catch (Throwable $e) { error_log('scheduler sync: ' . $e->getMessage()); }   // any running job sets up new ones (hourly at most)
        ob_start();
        register_shutdown_function(function () use ($pdo, $id) {
            $out = (string) ob_get_clean();
            echo $out;
            $e = error_get_last();
            $bad = $e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
            $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
            $note = $bad ? 'Stopped: ' . $e['message'] : (string) end($lines);
            try { $pdo->prepare('UPDATE ' . Database::t('job_runs') . ' SET finished_at = NOW(), ok = ?, note = ? WHERE id = ?')->execute([$bad ? 0 : 1, mb_substr($note, 0, 255), $id]); } catch (Throwable) {}
        });
        // a job that gathers or creates content skips while automation is paused, or all its workflows are (System
        // Configuration → Automation; Jason, 2026-11-14): recorded as a run that worked and said why, never a failure
        try {
            require_once __DIR__ . '/Automation.php';
            if (($w = Automation::job($pdo, $job)) !== null) { echo 'Paused: ' . $w . "\n"; exit(0); }
        } catch (Throwable $e) { error_log('scheduler automation: ' . $e->getMessage()); }
    }

    /** Where a start that couldn't be recorded is kept (the server's own folder, never synced): the database refused it. */
    public static function missFile(): string { return __DIR__ . '/../storage/scheduler-missed.json'; }

    /**
     * A start the database refused (2026-11-23: some jobs didn't start on a minute when others did; "Too many connections"
     * is the usual cause on shared hosting): kept, the last 200, for /api/health.php's jobs section, so the daily report
     * shows it without anyone reading the server's error log. Never throws.
     */
    public static function missed(string $job, string $why): void
    {
        try {
            $f = self::missFile(); if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
            $h = @fopen($f, 'c+'); if (!$h) return;
            if (flock($h, LOCK_EX)) {
                $all = json_decode((string) stream_get_contents($h), true); if (!is_array($all)) $all = [];
                $all[] = ['job' => $job, 'at' => gmdate('Y-m-d\TH:i:s\Z'), 'why' => mb_substr(preg_replace('/\s+/', ' ', $why), 0, 160)];
                $all = array_slice($all, -200);
                ftruncate($h, 0); rewind($h); fwrite($h, json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); fflush($h); flock($h, LOCK_UN);
            }
            fclose($h);
        } catch (Throwable $x) {}
    }

    /** The starts the database refused: [[job, at (UTC), why]], oldest first. */
    public static function misses(): array
    {
        $all = @json_decode((string) @file_get_contents(self::missFile()), true);
        return is_array($all) ? $all : [];
    }

    /** The last run of each job: job => row. */
    public static function lastRuns(PDO $pdo): array
    {
        self::ensure($pdo);
        $out = [];
        foreach (array_keys(self::JOBS) as $j) {
            $st = $pdo->prepare('SELECT * FROM ' . Database::t('job_runs') . ' WHERE job = ? ORDER BY id DESC LIMIT 1'); $st->execute([$j]);
            if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $f = $pdo->prepare('SELECT COUNT(*) FROM ' . Database::t('job_runs') . ' WHERE job = ? AND ok = 0 AND started_at > NOW() - INTERVAL 1 DAY'); $f->execute([$j]);
                $r['fails_today'] = (int) $f->fetchColumn();
                $out[$j] = $r;
            }
        }
        return $out;
    }

    // ---- the cron jobs in cPanel ---------------------------------------------------------------------------------------

    /** Every cron line in cPanel: [line, linekey, minute, hour, day, month, weekday, command]. */
    public static function cronLines(): array
    {
        $out = [];
        foreach (CPanel::api2('Cron', 'listcron') as $r) {
            if (!is_array($r) || !isset($r['command'])) continue;   // the list ends with a count
            $out[] = ['line' => (int) ($r['line'] ?? 0), 'linekey' => (string) ($r['linekey'] ?? ''), 'minute' => (string) $r['minute'], 'hour' => (string) $r['hour'], 'day' => (string) $r['day'],
                'month' => (string) $r['month'], 'weekday' => (string) $r['weekday'], 'command' => (string) $r['command']];
        }
        return $out;
    }

    /** Which cron line runs a job (null when none). */
    public static function lineFor(array $lines, string $job, bool $staging = false): ?array
    {
        foreach ($lines as $l) if (str_contains($l['command'], 'app/scripts/' . $job . '.php') && self::isStagingLine($l) === $staging) return $l;
        return null;
    }

    /** Is this one of staging's own copies (its scripts are in staging's folder)? Live's page and sync() never touch those. */
    public static function isStagingLine(array $l): bool { return str_contains((string) $l['command'], '/staging.trvgo.us/'); }

    // ---- staging's own copies of the jobs (decisions/0165 §1) -----------------------------------------------------------

    /** Jobs that only talk to the outside world, or run staging itself: never copied to staging. */
    /** Jobs that log a run only when they have work (a staging refresh going, live's changes to push, a release waiting):
     *  long quiet isn't a fault, so the site audit doesn't count their silence (Site health D03, 2026-11-18). */
    public const QUIET = ['founder_mail_tick' /* emails the Founder: logs only when it sends */, 'team_tick' /* the Team's runner: staging only, so on live it never logs (Piece 2) */, 'team_audit_tick' /* and its daily audit, the same */, 'staging_carry_tick', 'staging_push_tick', 'releases_tick', 'system_start_tick' /* logs only while Start the system is going (its script: "does nothing (and logs nothing) until the button is pressed"); 2026-11-20, Automation read "last ran 2 days ago … and is late" */];
    /** Jobs kept in the list but no longer run (their description says why): never late. */
    public const RETIRED = ['staging_tick'];
    public const NOT_ON_STAGING = ['founder_mail_tick', 'ses_setup_tick', 'domains_tick', 'audit_clock_tick', 'staging_tick', 'staging_carry_tick', 'staging_push_tick', 'staging_night_tick', 'staging_publish_tick', 'releases_tick'];

    /**
     * Staging's copy of each job, in cron through live's cPanel key (staging has none), pointing at staging's folder: they
     * run on staging's data under the gate. Only while "Staging runs its own jobs" is on (shipped off); off takes them out.
     * $scripts: staging's app/scripts folder. Returns [added, removed].
     */
    public static function syncStaging(bool $on, string $scripts): array
    {
        if (!CPanel::ready()) return [[], []];
        $lines = self::cronLines(); $added = []; $gone = [];
        foreach ($lines as $l) if (self::isStagingLine($l) && (!$on || !preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $m) || in_array($m[1], self::NOT_ON_STAGING, true))) {
            CPanel::api2('Cron', 'remove_line', ['line' => $l['line'], 'linekey' => $l['linekey']]);
            if (preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $m)) $gone[] = $m[1];
            $lines = self::cronLines();
        }
        if (!$on) return [[], $gone];
        $php = trim((string) getenv('CRON_PHP')) ?: '/usr/local/bin/php';
        foreach ($lines as $l) if (preg_match('#^(\S*php\S*)\s+\S*app/scripts/#', $l['command'], $m)) { $php = $m[1]; break; }
        foreach (self::AUTO as $job) {
            if (in_array($job, self::NOT_ON_STAGING, true) || !isset(self::JOBS[$job]) || !self::lineFor($lines, $job)) continue;   // only what live runs
            if (self::lineFor($lines, $job, true)) continue;
            $live = self::lineFor($lines, $job);
            CPanel::api2('Cron', 'add_line', ['command' => $php . ' ' . rtrim($scripts, '/') . '/' . $job . '.php >/dev/null 2>&1', 'minute' => ($at = self::stagingTime($live))['minute'], 'hour' => $at['hour'], 'day' => $at['day'], 'month' => $at['month'], 'weekday' => $at['weekday']]);
            $added[] = $job;
        }
        return [$added, $gone];
    }

    /** Minutes after live's start that staging's copy of a job starts (decisions/0165 §1: staging never costs live its limits;
     *  2026-11-26, the CEO's ruling: live's 6:13 daily jobs missed their start with staging's copies in the same minute against
     *  the database's 25 connections). */
    public const STAGING_OFFSET = 7;

    /** When staging's copy of a job runs: live's time, STAGING_OFFSET minutes later where live's minute is one number (a minute
     *  past :59 carries into the next hour, and past 23:59 into the next day when no day is set; where a day, month or weekday is
     *  set, or the hour is a list, a range or every N hours, it starts STAGING_OFFSET minutes earlier instead, so it never lands on another day or
     *  hour). A minute that isn't one number (every minute, every N minutes, a list) is kept as live's. */
    public static function stagingTime(array $live): array
    {
        $t = ['minute' => (string) $live['minute'], 'hour' => (string) $live['hour'], 'day' => (string) $live['day'], 'month' => (string) $live['month'], 'weekday' => (string) $live['weekday']];
        if (!preg_match('/^\d{1,2}$/', $t['minute'])) return $t;
        $m = (int) $t['minute'] + self::STAGING_OFFSET;
        if ($m < 60) { $t['minute'] = (string) $m; return $t; }
        $anyDay = $t['day'] === '*' && $t['month'] === '*' && $t['weekday'] === '*';
        if ($t['hour'] === '*') { $t['minute'] = (string) ($m - 60); return $t; }   // every hour: past :59 is the next hour's start
        if (preg_match('/^\d{1,2}$/', $t['hour']) && ((int) $t['hour'] < 23 || $anyDay)) { $t['minute'] = (string) ($m - 60); $t['hour'] = (string) (((int) $t['hour'] + 1) % 24); return $t; }
        $t['minute'] = (string) ((int) $t['minute'] - self::STAGING_OFFSET);   // a list, a range or a set day: earlier, never another day
        return $t;
    }

    /** Staging's copies moved to their time (stagingTime) where they still start with live's: the copies made before the offset,
     *  and a live line changed since. Run by sync(), hourly at most; staging's lines only, live's never touched. */
    public static function offsetStaging(): array
    {
        if (!CPanel::ready()) return [];
        $moved = [];
        foreach (self::cronLines() as $l) {
            if (!self::isStagingLine($l) || !preg_match('#app/scripts/([a-z0-9_]+)\.php#', $l['command'], $mm)) continue;
            $lines = self::cronLines(); $live = self::lineFor($lines, $mm[1]); $cur = self::lineFor($lines, $mm[1], true);
            if (!$live || !$cur) continue;
            $at = self::stagingTime($live);
            if ($cur['minute'] === $at['minute'] && $cur['hour'] === $at['hour'] && $cur['day'] === $at['day'] && $cur['month'] === $at['month'] && $cur['weekday'] === $at['weekday']) continue;
            CPanel::api2('Cron', 'edit_line', ['line' => $cur['line'], 'linekey' => $cur['linekey'], 'command' => $cur['command']] + $at);
            $moved[] = $mm[1];
        }
        return $moved;
    }

    /** The command for a job: PHP (the one our other jobs use, else CRON_PHP, else cPanel's usual), the script, output to nowhere (no email each run). */
    public static function command(string $job, array $lines = []): string
    {
        $php = trim((string) getenv('CRON_PHP')) ?: '/usr/local/bin/php';
        foreach ($lines as $l) if (preg_match('#^(\S*php\S*)\s+\S*app/scripts/#', $l['command'], $m)) { $php = $m[1]; break; }
        $script = (realpath(__DIR__ . '/../scripts') ?: __DIR__ . '/../scripts') . '/' . $job . '.php';
        return $php . ' ' . $script . ' >/dev/null 2>&1';
    }

    /**
     * Sets up the AUTO jobs cPanel doesn't have yet, on their usual schedule, through the cPanel API: at most once an hour
     * unless $now. A job removed on the page (or one we set up and someone took out in cPanel) isn't put back. Returns
     * the jobs it added.
     */
    public static function sync(PDO $pdo, bool $now = false): array
    {
        // once an hour at most, for all of it (the cron gap of 2026-11-23: every job's start, about 40 jobs, ai_worker every
        // minute, ran the five upkeep steps below before this guard, up to 300 listings re-scored each time; a pile-up holds
        // the server's processes and database connections, and the next jobs don't start). The upkeep keeps its batch sizes.
        self::ensure($pdo);
        $t = Database::t('job_setup');
        if (!$now) {
            $st = $pdo->query("SELECT at FROM $t WHERE job = '_sync'"); $last = $st ? $st->fetchColumn() : false;
            if ($last && strtotime((string) $last) > time() - 3600) return [];
        }
        $pdo->prepare("REPLACE INTO $t (job, state, at) VALUES ('_sync', 'ran', NOW())")->execute();
        try { require_once __DIR__ . '/../admin/DataMove.php'; DataMove::home($pdo); } catch (Throwable $x) { error_log('identity home: ' . $x->getMessage()); }   // the identity record lives in main (decisions/0133)
        try { require_once __DIR__ . '/Identity.php'; Identity::backfillIntake($pdo, 50); Identity::backfillListings($pdo, 50); require_once __DIR__ . '/crawler/IntakeStager.php'; IntakeStager::tidySources($pdo); require_once __DIR__ . '/ListingConfidence.php'; ListingConfidence::backfill($pdo, 300); ListingConfidence::stillOpenTick($pdo, 300); } catch (Throwable $x) { error_log('intake identities: ' . $x->getMessage()); }   // sightings from before 2026-11-01 get theirs
        try { require_once __DIR__ . '/DbUpdates.php'; DbUpdates::autoRun($pdo); } catch (Throwable $x) { error_log('db updates: ' . $x->getMessage()); }   // new migration files run themselves, at most once an hour
        try { require_once __DIR__ . '/SchemaUpkeep.php'; if (SchemaUpkeep::scanDue()) SchemaUpkeep::scanUnused($pdo); } catch (Throwable $x) { error_log('unused schema: ' . $x->getMessage()); }   // the monthly check for tables and columns no code uses (decisions/0162)
        if (!CPanel::ready()) return [];
        try { if ($mv = self::offsetStaging()) error_log('staging jobs moved to live + ' . self::STAGING_OFFSET . ' min: ' . implode(', ', $mv)); } catch (Throwable $x) { error_log('staging job offset: ' . $x->getMessage()); }
        $known = $pdo->query("SELECT job, state FROM $t")->fetchAll(PDO::FETCH_KEY_PAIR);
        $lines = self::cronLines(); $added = [];
        foreach (self::AUTO as $job) {
            if (!isset(self::JOBS[$job])) continue;
            if (self::lineFor($lines, $job)) { if (!isset($known[$job])) self::mark($pdo, $job, 'added'); continue; }
            if (isset($known[$job])) continue;   // removed, or set up before and taken out by hand: left that way
            $p = self::PRESETS[self::JOBS[$job][2]];
            CPanel::api2('Cron', 'add_line', ['command' => self::command($job, $lines), 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
            self::mark($pdo, $job, 'added'); $added[] = $job;
        }
        return $added;
    }

    private static function mark(PDO $pdo, string $job, string $state): void
    {
        try { self::ensure($pdo); $pdo->prepare('REPLACE INTO ' . Database::t('job_setup') . ' (job, state, at) VALUES (?, ?, NOW())')->execute([$job, $state]); } catch (Throwable $e) { error_log('scheduler mark: ' . $e->getMessage()); }
    }

    public static function add(string $job, string $preset): void
    {
        if (!isset(self::JOBS[$job])) throw new InvalidArgumentException('Not one of our jobs.');
        $p = self::PRESETS[$preset] ?? null; if (!$p) throw new InvalidArgumentException('Choose when it runs.');
        $lines = self::cronLines();
        if (self::lineFor($lines, $job)) throw new InvalidArgumentException('It\'s already set up in cPanel.');
        CPanel::api2('Cron', 'add_line', ['command' => self::command($job, $lines), 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
        self::mark(Database::connection(), $job, 'added');
    }

    public static function change(string $job, string $preset): void
    {
        $p = self::PRESETS[$preset] ?? null; if (!$p) throw new InvalidArgumentException('Choose when it runs.');
        $l = self::lineFor(self::cronLines(), $job); if (!$l) throw new InvalidArgumentException('It isn\'t set up in cPanel yet.');
        CPanel::api2('Cron', 'edit_line', ['line' => $l['line'], 'linekey' => $l['linekey'], 'command' => $l['command'], 'minute' => $p[1], 'hour' => $p[2], 'day' => $p[3], 'month' => $p[4], 'weekday' => $p[5]]);
    }

    public static function remove(string $job): void
    {
        $l = self::lineFor(self::cronLines(), $job); if (!$l) throw new InvalidArgumentException('It isn\'t set up in cPanel.');
        CPanel::api2('Cron', 'remove_line', ['line' => $l['line'], 'linekey' => $l['linekey']]);
        self::mark(Database::connection(), $job, 'removed');   // sync() leaves it out from now on
    }

    /** The preset a line matches ('' when it's set to something else). */
    public static function presetOf(array $l): string
    {
        foreach (self::PRESETS as $k => $p) if ([$l['minute'], $l['hour'], $l['day'], $l['month'], $l['weekday']] === array_slice($p, 1)) return $k;
        return '';
    }

    /** A cron schedule in words, as well as can be said. */
    public static function words(array $l): string
    {
        $k = self::presetOf($l);
        return $k !== '' ? self::PRESETS[$k][0] : trim($l['minute'] . ' ' . $l['hour'] . ' ' . $l['day'] . ' ' . $l['month'] . ' ' . $l['weekday']) . ' (cron)';
    }
}
