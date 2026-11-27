<?php
// B.2 step 1's harness: the Bluehost card's "The databases" part row with the Backups line, by state (?s=found|none|unread)
function tv_w_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES); }
function tv_icon($n, $c = '') { return '<svg class="' . $c . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3h7v7"/><path d="M10 14L21 3"/><path d="M21 14v7H3V3h7"/></svg>'; }
require __DIR__ . '/../app/lib/SearchIndex.php';
require __DIR__ . '/../app/admin/module-cards.php';
$at = time() - 600;
$S = ['found' => ['at' => $at, 'ok' => true, 'backups' => ['2026-11-20','2026-11-21','2026-11-22','2026-11-23','2026-11-24','2026-11-25','2026-11-26'], 'latest' => '2026-11-26', 'count' => 7, 'jetbackup' => ['jetbackup5'], 'why' => ''],
      'none' => ['at' => $at, 'ok' => true, 'backups' => [], 'latest' => '', 'count' => 0, 'jetbackup' => [], 'why' => ''],
      'unread' => ['at' => $at, 'ok' => false, 'backups' => [], 'latest' => '', 'count' => 0, 'jetbackup' => null, 'why' => '', 'said' => 'The feature “backup” is not enabled for this account.']];
SearchIndex::$v = json_encode($S[$_GET['s'] ?? 'found']);
require_once __DIR__ . '/../app/lib/StagingGate.php'; StagingGate::pretend(false);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bluehost card: The databases</title>
<style>:root{--tv-canvas:#E4DCC5;--tv-card-a:#F6F1E4;--tv-crimson:#8C3A23;--tv-ink:#2A1D14;--tv-line:color-mix(in srgb,#3E2B1F 20%,#E4DCC5);--tv-meta:color-mix(in srgb,#2A1D14 35%,#7A6A52);--tv-moss:#6B7F62;--tv-ease:150ms ease-out}body{font-family:system-ui,sans-serif;background:#FBF7EE;color:#3A2E1F;margin:0;padding:12px}.card{background:#fff;border:1px solid #E5D9BF;border-radius:.5rem;padding:12px;max-width:900px}.mc-part{border-top:1px solid #EEE4CF;padding:.5rem 0}.mc-part p{margin:.2rem 0}.mc-sub{font-size:.9rem;color:#6b5d49}.mc-role{font-size:.75rem;background:#F1E8D4;border-radius:999px;padding:1px 8px;margin-left:.3rem}@media(max-width:767px){body{padding:3px}.card{border-radius:.5rem;padding:10px}}</style>
<?= tv_mc_css() ?></head><body><div class="card"><h3 style="margin:.2rem 0">Bluehost <small style="font-weight:400">Host</small></h3>
<div class="mc-part" id="mc-part-site-db"><p><b>The databases</b> <span class="mc-role mc-role-s">Site part</span></p>
<?= tv_mc_host_backups(new PDO('sqlite::memory:')) ?>
<p class="mc-state">● On · Working <span class="mc-rest">its check passed Nov 27, 3:30 AM</span></p><p><span class="mc-ic">✓</span> Check now</p></div>
<div class="mc-part"><p><b>Database updates</b> <span class="mc-role mc-role-s">Site part</span></p><p class="mc-state">● On · Working</p></div></div>
<script src="/js/tv-ask.js"></script><script src="/js/tv-time.js"></script></body></html>
