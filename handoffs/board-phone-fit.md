# The board at phone width with live content

2026-11-23, the Control Board build session. `board-phone-fit.zip`, 1 file (`app/admin/control-console.php`), built on
Sync 2026-11-23 01:52 UTC (`4a94342`). From Jason's screenshot ("Control: layout issue"): the board scrolled sideways on
his phone.

| What | Cause (measured on the bench, live code with the live alarms snapshot) | Fix | Source |
|---|---|---|---|
| The whole first view (Is it delivering?) 468px wide at 390 | The gauges' source line printed GitHub's raw address, one unbreakable word; the view is a grid whose single column took that width | The line names the file as a link ("gauges.json ↗"), the address its target; the view's column is `minmax(0,1fr)`; notes wrap long words | decisions/0058 §11 (nothing scrolls sideways) |
| Maintenance 396px at 390 | Its form's grid column took the form's width | The same `minmax(0,1fr)`; the picker and labels may shrink | 0058 §11 |
| Reports' scope picker 379px at 360 (cut off inside its card) | A select sized to its longest option | Capped at its label's width | 0058 §11 |

**Why the earlier tests missed it:** they seeded a few short alarms and read the gauges from a local file. The new test
`tools/control-board-tests/board-live.cjs` (with `board-live-seed.php`, in `rendered-all.sh`) seeds the live alarms
snapshot (`site-audit/results/alarms.json`) and checks every view and stage tab at 390 and 360.

**Proof:** `board-live.cjs` **31 of 31** on the fix (every view and stage tab at 390 and 360); on today's live code it
fails **7** (the deliver view at 476px, maintenance at 396px, the raw address, the report picker). The rendered suite on
the fix: 339 pass; lines' one failure is the same on live (recorded). board-phone 6/6 and board-views 90/90 re-run on the
final file. zipcheck clean.

**Noted for later (not in this release):** an alarm raised before #258 keeps the title it was raised with
("index.commoncrawl.org: Its server failed (the crawler, on Railway)"); new ones use the plain words. Words follow the
state (principle 4): a pass that redraws old titles is its own item.

**Admin's test:** on a phone the board fits the screen on every tab; the gauges' source is a link named "gauges.json".
