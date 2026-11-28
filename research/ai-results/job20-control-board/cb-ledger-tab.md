# The Ledger tab in Modules: draft patches

## Status: not built

- **`handoffs/control-board-2026-11-24.md`** lists the Ledger tab under "Next, in the session manager's order", item 5. That entry says "one row per paid account … promo credits shown apart, never counted against the cap", and the process is "Design reviewer → session manager → Jason's pop-up".
- **`handoffs/control-board-2026-11-25.md`** says everything outstanding was moved into the Work queue (task-seed-3, PR #1368). It does not say the tab was built.
- **No attached document says it's built or live.**

## Not the board's Ledger

0175 R2b names a "Ledger" tab on the System Control Board. That is `control_ledger`: worker endpoint calls (0171 §4). It is not this spend ledger. Two tabs called "Ledger" would confuse people.

## What this draft covers

The files that would hold most of this work aren't attached:
- the Modules page;
- `Operations.php` (the service register's caps);
- `OutsideServices.php`;
- `ControlEngine.php`;
- `crawl_costs`'s columns;
- `faq.md`.

So this draft covers only:
- the row arithmetic and words;
- the one billing-link definition;
- tests.

The UI and the data wiring are left to the lane, with each gap named below.

---

## 1. Plan

1. **New `app/lib/ModulesLedger.php`.** It works out each row and the totals: % of cap, warning band, owed or credit, where the spend was read, and the gap between counts (e.g. "Google 608 vs 30"). Promo credit is shown apart and never touches % of cap. Source: the 11-24 hand-off, Next item 5.
2. **Add `ControlAlarms::portal()` and make `portalLine()` use it.** The billing link then has one definition, shared with the alarm notice's portal line (02 §8, as quoted in `portalLine`'s docblock). This follows CLAUDE.md's "defined once", per the hand-off's rules.
3. **No new table, no migration.** The tab only reads. 0171 §4 keeps the ledger to "ids and counts only".
4. **Modules page tab and its UI: file not shown.** The Modules page and `tv_table()` aren't attached. Its layout also waits on the mockup step that item 5's process requires.
5. **Data wiring: file not shown.** Each figure comes from somewhere not attached:
   - cap and our count: `Operations::register`;
   - each service's own count: its API;
   - sign-in account, plan, last and next bill;
   - each row's entries.

   These are listed as gaps in §5. Nothing is invented for them.

---

## 2. Diffs

### `app/lib/ControlAlarms.php`

The hunk's line numbers are approximate because the attached copy is truncated; `git apply` finds the hunk by its context. The hunk counts are exact.

```diff
--- a/app/lib/ControlAlarms.php
+++ b/app/lib/ControlAlarms.php
@@ -812,14 +812,26 @@
         if ($type === '' || !preg_match('/^svc:([a-z0-9_]+)/', (string) ($a['component'] ?? $a['proc']), $m)) {
             if ($type === '' || !preg_match('/^svc:([a-z0-9_]+)/', (string) $a['proc'], $m)) return '';
         }
-        require_once __DIR__ . '/ControlEngine.php';
-        $d = (array) (ControlEngine::defs()[$m[1]] ?? []); $co = (string) ($d['company'][0] ?? $m[1]); $prov = trim((string) preg_replace('/\s*\(.*\)$/', '', $co));
+        [$prov, $url] = self::portal($m[1], $type);
         $words = ['billing' => 'Billing', 'keys' => 'API keys', 'console' => 'Console'][$type];
-        $url = (string) ($d['portal_links'][$type][1] ?? '');
-        if ($url === '') {
-            $want = ['billing' => ['billing', 'limits', 'usage'], 'keys' => ['keys', 'key', 'credentials', 'api settings'], 'console' => ['dashboard', 'console']][$type];
-            foreach ($want as $w) { foreach ((array) ($d['company'][3] ?? []) as $ln) if (str_contains(mb_strtolower((string) ($ln[0] ?? '')), $w) && str_starts_with((string) ($ln[1] ?? ''), 'http')) { $url = (string) $ln[1]; break 2; } }
-        }
         return $url !== '' ? $words . ' at ' . $prov . ': ' . $url : 'No ' . ['billing' => 'billing', 'keys' => 'keys', 'console' => 'console'][$type] . ' page registered for ' . $prov . ' yet.';
     }
 
+    /**
+     * A service's provider page of one type (billing, keys, console): [the provider's name, its url or '']. From the
+     * manifest's portal_links, else its company links matched by label (02 §8), never borrowed from another type. One
+     * definition for the alarm notice's portal line and the Modules Ledger's billing link.
+     */
+    public static function portal(string $svc, string $type): array
+    {
+        if (!in_array($type, ['billing', 'keys', 'console'], true)) return ['', ''];
+        require_once __DIR__ . '/ControlEngine.php';
+        $d = (array) (ControlEngine::defs()[$svc] ?? []); $co = (string) ($d['company'][0] ?? $svc); $prov = trim((string) preg_replace('/\s*\(.*\)$/', '', $co));
+        $url = (string) ($d['portal_links'][$type][1] ?? '');
+        if ($url === '') {
+            $want = ['billing' => ['billing', 'limits', 'usage'], 'keys' => ['keys', 'key', 'credentials', 'api settings'], 'console' => ['dashboard', 'console']][$type];
+            fore