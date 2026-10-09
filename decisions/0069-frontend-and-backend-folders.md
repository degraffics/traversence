# 0069. Frontend and backend folders: website/ and app/

Date: 2026-10-09
Status: Accepted (Jason, 2026-10-09: "APP/ & website/ let's do it")

## Context

Everything lived in one folder, `website/`, which is the web root: pages and endpoints, but also the code library
(`api/lib/`), its data (6.7 MB of word data), the SQL migrations, command-line scripts, page templates (`includes/`),
and PHP's own error log. With the `.htaccess` live on 2026-10-09, a browser could open all of them:
`/includes/faq.md`, `/api/migrations/*.sql`, `/api/lib/*.php`, `/error_log`. A database dump had sat in the web root
too. Notes folders (`_notes`, `_SUPPORT`, `_concepts`, `_pending`) were inside `website/` and stayed off the server
only because the sync skipped them by name.

The domain's document root is `public_html/traversence`; its `.htaccess` serves every traversence.com request from
`website/`. The same folder is also reachable under the hosting account's main domain at `/traversence/`.

## Decision

1. **Two folders on the server and in OneDrive, side by side:**
   - `website/` (frontend): only what a browser should reach. Pages, the endpoints browsers and the crawler call
     (`api/*.php`, `api/crawl/`, `api/auth/`, `user/api/`, `listing/api/`), `js/`, `image/`, `uploads/`, `_errors/`.
     Page addresses don't change.
   - `app/` (backend): never served.
     - `app/lib/` the classes (was `api/lib/`, with `Database.php`);
     - `app/data/` the word data and `US.txt` (was `api/lib/data/`);
     - `app/ui/` the shared page parts: app shell, header, page shell, engage bar, FAQ text (was `includes/`);
     - `app/bootstrap.php`, `app/middleware/`;
     - `app/migrations/` the SQL, run by hand in phpMyAdmin;
     - `app/scripts/` the command-line tools.
2. **GLOBAL** (what every page shares) splits by who runs it: the PHP half is `app/ui/`, the browser half stays in
   `website/js/` and `website/image/`.
3. **The `.htaccess`** refuses `app/` on any host, refuses the backend's old places inside `website/` (`includes/`,
   `scripts/`, `api/lib|migrations|scripts|middleware`) on both addresses, and refuses file types that are never pages
   (`.sql`, `.md`, `.log`, `.bak`, `.zip`, `.ini`, `.sh`, `.py`, `.tsv`, `error_log`). Tested on Apache 2.4 with the
   same layout (both addresses, 30 checks).
4. **The sync keeps both folders,** in one run with one OneDrive sign-in, each with its own record and GitHub copy
   (`sync-storage/` for `website/`, `sync-app/` for `app/`).
5. **Notes stay out of the site folders.** `_notes`, `_SUPPORT`, `_concepts` and `_pending` move to the project's
   other OneDrive folders.
6. **Paths in code.** Each file reaches the other folder by its own relative path (`__DIR__ . '/../app/ui/header.php'`
   from a root page, `__DIR__ . '/../../app/lib/Auth.php'` one folder down). `app/lib/` is as deep as `api/` was, so
   `Database.php` still finds the `.env` four folders up. Two hash salts that used their file's folder keep the old
   value, so stored hashes still match.

## How it was moved

A script rewrote every `__DIR__` path by meaning: what each path pointed at before, where that file is now, and the
path from the file's new place. Seven paths were fixed by hand.

To test it, two copies of the site ran side by side, the old layout and the new, on identical test databases. 188
requests (every page signed out and as an admin, plus the main API calls) gave the same result, apart from the folder
names in two admin messages. The browser tests for search and the location prompt pass on the new layout. No new
errors: the server logs match line for line.

Removed as dead, broken before the move:
- `api/directory.php` (needed a `db_config.php` that never existed);
- `market-placeholder/`;
- `image/includes/` (stray copies of two templates).

## Consequences

- Page and endpoint addresses are unchanged; nothing changes for visitors.
- A new build delivers into two folders. CLAUDE.md says where things live.
- The FAQ's text is `app/ui/faq.md`.
- Later, optional: the browser half of GLOBAL could move to `website/assets/` (it would change asset addresses).
