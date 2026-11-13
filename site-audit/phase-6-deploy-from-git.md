# Phase 6: Deploy from git (optional)

Decision 0145 §4. Jason decides whether and when.

## Goal

Releases and rollbacks reach the server without manual uploads. Git becomes the source of truth for the site's files,
not only the record of them.

## What changes

1. **Today:** you upload zips to the server; the 3-way sync copies the server to OneDrive and records it in git. The
   server wins any conflict (`SYNC_HOME=ftp`).
2. **After:** a vetted release becomes a tagged version in git. Publishing it (Phase 5) has the sync workflow upload
   exactly its files to staging, then to live, by FTP. It then checks every file byte for byte and records the result.
   Rolling back uploads the previous tag's files. No zips, no missed files.
3. **The sync's direction changes** (done 2026-11-13, decisions/0159 step 2): `SYNC_HOME` becomes git. Edits made directly on the server (cPanel) or in OneDrive
   are reported as drift and brought into a release, or put back. They are never silently kept or lost.
4. **The rules that stay:**
   - dotfiles (`.env`, `.htaccess`) and `app/storage/` are never deployed from git;
   - `.env` secrets stay in cPanel by hand;
   - migrations still run themselves.

## What Jason provides

1. **The decision to switch**, and a date. Until then, Phase 5 works with zips you upload.
2. **Agreement on how changes are made:** after the switch, every change to the site's files goes through a release in
   git. That includes changes from other Claude sessions, the marketplace session, and you in OneDrive. Urgent fixes
   still go out at once, as a release.
3. **Nothing new to set up for access.** The sync already holds the FTP and OneDrive secrets it needs.

## Done when

- [ ] A release publishes to staging and live from git with no upload, and every file is checked.
- [ ] A rollback re-deploys the previous tag, and the audit confirms it.
- [ ] A change made directly on the server shows as drift in the scorecard and Pulse.
- [ ] `CLAUDE.md`'s delivery rules (zips, uploads) are replaced by the release rules.

## Rollout and rollback

- Released like any other phase: staging first, one trial release, then everything.
- Rollback: set `SYNC_HOME` back to `ftp`. The sync returns to recording the server, and zips work as today.

## Cost

None. The sync's existing GitHub run time.
