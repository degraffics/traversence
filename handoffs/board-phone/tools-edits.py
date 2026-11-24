# board-phone's edit to app/admin/tools.php (config/alarms: the Alarms table), re-applied on the newest live copy just before
# zipping (it shares tools.php with order-m2b's pending zip): Open is the board's one count (02-state-model P9;
# ControlAlarms::counts): every open trip, seen or not, and the warnings not yet seen; notices are under Notices.
import sys
p = sys.argv[1]; s = open(p).read()
old = """                $state = $a['cleared_at'] !== null ? 'cleared' : ($a['ack_at'] !== null ? 'ack' : '');
                $counts[$state]++; $counts['all']++; if ($state !== 'cleared') $counts[(string) $a['level']] = ($counts[(string) $a['level']] ?? 0) + 1;
                $hit = $f === 'all' || $f === $state || (in_array($f, ['trip', 'warning', 'notice'], true) && $a['level'] === $f && $state !== 'cleared');"""
new = """                $state = $a['cleared_at'] !== null ? 'cleared' : ($a['ack_at'] !== null ? 'ack' : '');
                // Open is the board's one count (02-state-model P9; ControlAlarms::counts): every open trip, seen or not, and
                // the warnings not yet seen; notices are under Notices (board-phone, 2026-11-24: this tile said 32, the light 12)
                $one = $state !== 'cleared' && ($a['level'] === 'trip' || ($a['level'] === 'warning' && $state === ''));
                if ($one) $counts['']++; if ($state !== '') $counts[$state]++; $counts['all']++; if ($state !== 'cleared') $counts[(string) $a['level']] = ($counts[(string) $a['level']] ?? 0) + 1;
                $hit = $f === 'all' || ($f === '' ? $one : $f === $state) || (in_array($f, ['trip', 'warning', 'notice'], true) && $a['level'] === $f && $state !== 'cleared');"""
assert s.count(old) == 1, 'tools.php: config/alarms list changed; re-read it'
s = s.replace(old, new); open(p, 'w').write(s)
