# 0089. No session files for machines; empty sessions go after a day

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** 0079 (signed in for 30 days, sessions in `app/storage/sessions`)

## Context

The three-way sync failed after 44 minutes (TRAVERSE-3WAY-SYNC run #115). It was copying `app/storage/`, including
7,626 session files, to OneDrive and git (fixed in TRAVERSE-3WAY-SYNC#1: the app's `storage/` never syncs). Of those
files, 7,610 held only a form token (`csrf_token`, 83 bytes). Each came from a single request by something with no
cookie:
- the crawler worker, every few minutes;
- search engines;
- link previews.

None of these ever sign in. They were kept 30 days, and each file uses a disk block and one of the hosting account's
file slots. Jason: "we can have thousands of useless files being created, it will consume our shared server space."

## Decision (`app/bootstrap.php`)

**Machines get no session file.** These requests run with an in-memory session that's never saved:
- a request carrying the crawler token (`X-Crawler-Token` or `Authorization: Bearer`);
- a request with no session cookie from a known bot, link preview or script client.

**Empty sessions go after a day.** A session file holding nothing but its form token, untouched for 24 hours, is
deleted. Signed-in sessions keep their 30 days.
- The sweep runs at most once an hour, on whichever request comes first (`app/storage/sessions-swept` marks the time).
- It reads only files up to 120 bytes and deletes only exact token-only files.
- It never touches the current session.

**Checked on a copy of the site:**
- four machine visits to `/login.php` added no files, and a browser visit added one;
- the sweep removed an old token-only session and kept an old signed-in one and a new token-only one;
- it didn't run again within the hour.

## After uploading

Delete the files in `app/storage/sessions/` once. The copies made by the sync then can't sign anyone in. Everyone signs
in again.
