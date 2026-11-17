# Releases page: each release folds to one line (Jason, 2026-11-17)

Jason, looking at a withdrawn release's full card: "the releases should be collapsed." Outline approved by him, built by the
`releases-fold` session at the session manager's request.

## What it does (staff only, https://traversence.com/admin/config/releases)

- Each release is a `<details class="ak-card tv-bleed rel-fold" id="rel-N">`: its line is "#N · title", where it stands
  (`Releases::STATES` label, toned ok/info/bad as the status line), and when (`<time data-tv-time>`: the schedule for a
  scheduled one, labelled "goes live", with the release's zone; else its last log entry). Click it: the full card as before.
- Open by default only while it needs Jason: planned, plan approved, on staging (failed checks too), vetted, scheduled.
  Live, turned off, rolled back and withdrawn stay folded.
- `#rel-N` (Pulse, the staging bar's link, the page's own reload after an action) opens that one and scrolls to it, not
  remembered as his choice.
- What he opens or closes is remembered per release in localStorage (`tv-rel-fold:N` = `state:1|0`, try/catch), until
  the release's state changes; then the page's default again.
- Checked at 390px: 3px card edges, no sideways scroll; no browser dialogs; nothing else on the page changes. No FAQ
  entry: a staff-only page, no visitor question.

## Where it stands

First built on Sync 13:09 UTC and blocked behind release #78 (w0-indexer), whose migration failed on staging (`stored`
is a reserved word). Its corrected copy, #79 (w0-indexer-2.zip), went live 13:12 UTC; rebuilt on Sync: 2026-11-17 13:15
UTC and sent through git as `releases-fold.zip` (`release.json`, `app/admin/sections.php`, `website/js/admin-releases.js`),
`"review": false`. Left: watch for its "Release #N: Live" record, then the sync and a byte-for-byte check.
#78 is still on staging with its failed check; it's superseded by #79 and can be withdrawn on the Releases page.
