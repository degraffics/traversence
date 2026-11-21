## Open alarms on the live site (snapshot 2026-11-21 10:19 UTC)

Source: `site-audit/results/alarms.json` on traversence `main` (142537aa), written by `search-probe.yml` from the health
report's alarms block. The block lists **13 of the 21 open** alarms; the other 8, `system_pauses` and Pulse cases are
**needs live data** (the session manager asks Jason for them with step 3). The snapshot was taken before #247 went live
(12:22 UTC): its effect on L1 needs the next snapshot.

| # | Alarm | Level | What it holds now | What's wrong | Requirement / question |
|---|---|---|---|---|---|
| L1 | `pf:wf:W3`: Common Crawl's red line (issue #35) | trip, since 11-20 15:05 | **W3 whole** ("its tasks wait with it") | The pre-flight line's own words say "It holds only what it's for (commoncrawl); the rest can start", while the gate's trip holds W3 whole. #247 (live 12:22) should narrow it: check the next snapshot | R86–R99; Q2/Q5 |
| L2 | `task:failed:overture_bulk`: `TimeoutError` (run #1290) | trip, 09:35 | **W1 whole** | Case #51. One failure, then a trip on the clock ("Its tries ran out at 09:31 UTC"): one try, not three. Its words say "the workflow's other tasks carry on", but W1 waits whole | Q1, Q2, Q3 |
| L3 | `task:failed:foursquare_bulk`: `TimeoutError` (run #1288) | trip, 09:25 | **W1 whole** | **A second case #51**, the same pattern, 11 minutes earlier. Both are timeouts on a bulk load, which supports Q3 (our own site slow, or the load too big) | Q1, Q2, Q3 |
| L4 | `svc:issue:35`: index.commoncrawl.org 504 | trip, since 11-20 07:06 | Calls to it | Open 30 hours: "held until an admin resolves it". The crawler no longer calls this host (2.15.0 reads data.commoncrawl.org), so no call can ever pass to close it by itself. #247's site check closes a crawler back-off when the site's own call passes; check the next snapshot | Q6; R86–R99 |
| L5 | `ops:cap:anthropic` | warning, since 11-19 | — | **Words behind the state:** the title says "at 50%", the reading says 67% | R100–R113 ("words follow the state") |
| L6 | `ops:cap:google` | warning | — | Same: the title says "at 50%", the reading 54% (it happens to cross only 50) | R100–R113 |
| L7 | `job:identity_recheck_tick:stuck` | warning, since 11-20 15:15 | — | A job that "never finished" has been a warning for 19 hours: it never trips or escalates, and nothing says whether it's running now | Q19-adjacent; R40–R64 (watch) |
| L8 | `w3:offregion`: 77 sources outside our region | warning, since 11-18 | nothing (by rule) | A standing warning for three days: needs a person's choice. Is it a notice, not a warning? | R65–R85 (levels) |
| L9 | `ep:crawl/refresh:count`: received 1, counted 2 | warning | — | A counting bug in `/api/crawl/refresh` (W2): "its numbers on the board can't be trusted" | R16 (received equals counted); Q16 |
| L10 | `pf:so:commoncrawl-…`: Common Crawl (W3) held | warning | what's for commoncrawl | Says "the rest can start" while L1 holds W3 whole: two alarms disagree about one cause | R86–R99 |
| L11 | `svc:issue:26`: query.wikidata.org 429 | warning, since 11-18 | waits twice as long | Open three days for a rate wait that "starts again by itself": either it never cleared, or it keeps coming back. Can't tell which from the snapshot | R157–R166 |
| L12 | `svc:issue:22`: data.commoncrawl.org "Its server failed" | warning, since 11-18 | waits, then tries | **Wrong words:** the reading is S3's `503 SlowDown … reduce your request rate`, a rate wait (crawler 2.14.1), but the title still says "Its server failed". The issue was opened before 2.14.1, and its words never changed | R100–R113 |
| L13 | `svc:issue:18`: Census geocoder, connection reset | warning, since 11-18 | waits, then tries | Open three days. The Census backup (2.16.0) exists. Is the issue still live, or a leftover? | R86–R99 (backups) |

The snapshot's summary line, beside it:
- "The whole system, the last 24 hours: uptime 97.5%. 2 trips raised; 21 alarms open now."
- **"Records: 0 in, 0 stored, 0 refused."** In 24 hours nothing was gathered and stored, with W1 and W3 paused whole. That's the harm case #51 does, in one line.
- "Nearest a cap: Claude (AI) at 67%."
- `releases_looked`: the 10-minute job's last look reads `found: -1` (10:00 UTC). If -1 means it failed to look, a release could wait unseen: **check** in step 2 (g3, release pipeline).
