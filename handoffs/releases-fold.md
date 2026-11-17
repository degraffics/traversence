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

Built on Sync: 2026-11-17 13:09 UTC, zip check clean, then **blocked at send**: release #78 (w0-indexer.zip, on staging,
not live) also carries `app/admin/sections.php`. Per CLAUDE.md: wait for #78 to go live and the sync, then rebuild on
the live copy.

## To finish

1. Once #78 is live and synced: fetch the mirror; `git apply` won't fit the mirror's paths, so apply
   `releases-fold-pending/releases-fold.patch` to copies of `sync-app/admin/sections.php` and
   `sync-storage/js/admin-releases.js` placed at `app/admin/…` and `website/js/…` (`patch -p1` from that folder). Only
   `tv_admin_mod_releases()` and the end of `admin-releases.js` change; re-apply by hand if #78 touched the same lines.
2. Zip `release.json` (here, `"review": false`) with the two files; `zipcheck.py`, then `send.py … --note "… built on Sync: <time>"`.
3. Claims are held (`checkout.py`, session `releases-fold`, until 17:09 UTC; renew if needed).
