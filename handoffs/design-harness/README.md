# The design session's harness (2026-11-20): how to stand it up again

Test-only files, never shipped. The harness lived in the container at `/home/user/x`, which doesn't survive a new session.
1. `apt-get install -y mariadb-server php8.3-mysql`, then `mysqld_safe --user=mysql &`. `INSTALL SONAME 'sql_errlog'`
   logs failed queries to `/var/lib/mysql/sql_errors.log`.
2. Database `tvh`, user `tv`/`tv`. `/home/user/.env` holds `DB_HOST=127.0.0.1`, `DB_DATABASE=tvh`, `DB_USERNAME=tv`,
   `DB_PASSWORD=tv`. `Database::envPath()` is four folders up from `app/lib`.
3. `root/` = the mirror's `sync-storage` as `website/` and `sync-app` as `app/`, with the release's `out/` laid over.
4. Run `php -S 127.0.0.1:8790 -t root/website srv.php`. `srv.php` routes `/admin/...` to the admin router (the built-in
   server ignores .htaccess).
5. Run `fix.py <urls>`. It loads the pages, reads the error log, and adds missing tables and columns until the pages
   stop erroring. Seed the rows a test needs. Set `SID=` for the cookie it sends.
6. Sign-in: copy `login-staff.php` (user 1, staff, passes the two-step) or `login-member.php` (user 2) to
   `root/website/test/`, then request it with the cookie `traversence_session=harness00…0` (…2 for the member), with a
   browser user agent (machines get no session file).
7. `contrast-probe.cjs`: every text element's contrast at 1280 and 390, plus sideways scroll and script errors.
Careful: tools/notify-tests write stand-in files (Database.php, ListingMessages.php, Standing.php) into the app folder
you give them. Give them a copy.
