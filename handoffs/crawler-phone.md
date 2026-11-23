# Insights → Crawler at phone width (queue item (a) after #258)

2026-11-23, the Control Board build session. `crawler-phone.zip`, 1 file (`app/admin/crawler-console.php`), built on Sync
2026-11-23 01:36 UTC (`8b91df0`).

| What Jason saw | Cause (measured on the bench) | Fix | Source |
|---|---|---|---|
| The task table cut off on the left in the alarm's side panel on his phone | A 6-column table, 622px wide in a 334px box that scrolls sideways (the runs table 506px) | Below 768px each row is a card with labelled lines; at 1280 still a table | decisions/0058 §11; design/table-standard.md (phones) |
| "Last: Failed (RuntimeError('site /api/crawl/loads.php said 503: b HTML PUBLIC "-//W3C//DTD…" | The worker's error text shown as is | `OutsideServices::plainWhy`: "…said 503: 503 Service Unavailable" (tasks and runs) | principle 4; plainWhy's own rule |
| "From ." | A task with no source named | Left out when empty | words stay true |
| An empty "Its queue" (seen on the card) | Nothing queued printed nothing | "nothing waiting" | words stay true |

**Proof:** `tools/control-board-tests/crawler-phone.cjs` (with `crawler-phone-page.php`), **11 of 11** on the fix: no
sideways scroll in the page or any table at 390 and 360, cards below 768px, a table at 1280, the failure in words, no
"From .", every queue says something. The same test on today's live code: **6 fail** (it catches what he saw).
zipcheck clean. No FAQ entry (staff only).

**Admin's test:** on a phone, Insights → Crawler's tasks and runs read as cards, top to bottom, with Run first and Switch
on/off under each; nothing to scroll sideways.
