<?php
// ANCHOR: replaces     public static function refresh(PDO $pdo, bool $force = false): void through             error_log('SearchIndex::refresh: ' . $e->getMessage());
// (the catch's and the method's closing braces after that line stay as they are)
// What changed: the catch-up is the scheduled job search_index_tick (Scheduler::JOBS), which calls refresh($pdo, true, true).
// While the job has run in the last 30 minutes, a visitor's search no longer does the work (decisions/0181: "search is
// running really slow"). If the job stops, searches take it back up after 30 minutes, as before.
    public static function refresh(PDO $pdo, bool $force = false, bool $byJob = false): void
    {
        try {
            if (!self::ready($pdo)) return;
            if (!$force && !$byJob) {
                $job = self::state($pdo, 'refresh_job_at');
                if ($job !== '' && strtotime($job) > time() - 1800) return;   // the scheduled job keeps it current
            }
            if ($byJob) self::setState($pdo, 'refresh_job_at', date('Y-m-d H:i:s'));
            $last = self::state($pdo, 'refreshed_at');
            if (!$force && $last !== '' && strtotime($last) > time() - 900) return;
            self::setState($pdo, 'refreshed_at', date('Y-m-d H:i:s'));
            $mark = self::state($pdo, 'listing_mark');
            if ($mark !== '') {
                // listings changed since the mark, oldest first, 600 at a time. The mark moves to the last one taken, so each
                // run carries on where the last stopped (2026-11-10: it moved only when fewer than 300 had changed, so with
                // the crawler busy it re-read the same 300 every time and new listings, Dana Point's, never reached search)
                $now = date('Y-m-d H:i:s'); $n = 600;
                $st = $pdo->prepare('SELECT id, updated_at FROM entities WHERE updated_at > ? ORDER BY updated_at, id LIMIT ' . $n);
                $st->execute([$mark]); $er = $st->fetchAll(PDO::FETCH_ASSOC);
                $st = $pdo->prepare('SELECT entity_id, MAX(updated_at) u FROM entity_metadata WHERE updated_at > ? GROUP BY entity_id ORDER BY u LIMIT ' . $n);
                $st->execute([$mark]); $mr = $st->fetchAll(PDO::FETCH_ASSOC);
                $ids = array_values(array_unique(array_merge(array_map(fn ($r) => (int) $r['id'], $er), array_map(fn ($r) => (int) $r['entity_id'], $mr))));
                if ($ids) foreach (array_chunk($ids, 300) as $chunk) self::indexListings($pdo, $chunk);
                // as far as both lists reached: a full list stops at its last row; one with room left reached now
                $back = fn (string $t) => date('Y-m-d H:i:s', strtotime($t) - 1);   // a second back: others changed that same second are read again
                $reach = [count($er) >= $n ? $back((string) end($er)['updated_at']) : $now, count($mr) >= $n ? $back((string) end($mr)['u']) : $now];
                $next = min($reach);
                if ($next <= $mark) $next = date('Y-m-d H:i:s', strtotime($mark) + 1);   // a second full of changes: step past it
                self::setState($pdo, 'listing_mark', $next);
            }
            foreach (['story', 'group', 'topic'] as $k) self::rebuildSmall($pdo, $k);
            // landmarks added after the federal data (by hand, or found for a guide on demand: ids from 900000000), new since
            // last time; they were only indexed on a full rebuild (2026-11-10: Four Corners Monument, Dana Point's places)
            if (class_exists('Landmarks') || is_file(__DIR__ . '/Landmarks.php')) {
                require_once __DIR__ . '/Landmarks.php';
                if (Landmarks::ready($pdo)) {
                    $lm = self::state($pdo, 'landmark_added_mark');
                    if ($lm === '') $lm = 'g' . str_pad((string) (Landmarks::MANUAL_FROM - 1), 10, '0', STR_PAD_LEFT);
                    [$n, $next] = self::landmarksAfter($pdo, $lm, 300);
                    if ($n > 0) self::setState($pdo, 'landmark_added_mark', $next);
                    // each landmark filed under one of our categories by its source's class (2026-11-10: "from categories not
                    // guesses"); a few filed now are re-indexed at once, many go through the walk below
                    $filed = Landmarks::categorize($pdo);
                    if (count($filed) > 300) { self::setState($pdo, 'landmark_kind_walk3', 'g'); }
                    else foreach ($filed as $fid) self::refreshLandmark($pdo, (int) $fid);
                    // every landmark again under its kind, 1000 a run until done (once, and after a big filing)
                    $wk = self::state($pdo, 'landmark_kind_walk3');   // 3: with its activities (Landmarks::activities, 2026-11-10)
                    if ($wk === '') $wk = 'g';
                    if ($wk !== 'done') { [$wn, $wnext] = self::landmarksAfter($pdo, $wk, 1000); self::setState($pdo, 'landmark_kind_walk3', $wn < 1000 ? 'done' : $wnext); }
                }
            }
        } catch (Throwable $e) {
            error_log('SearchIndex::refresh: ' . $e->getMessage());
