# Proposals

Decision 0145 §2. Improvements drawn from the audit's findings, each with its evidence and verdict. Jason approves,
rejects or rewords each one; nothing changes until he does.

**Verdicts:**
- **fix:** the page breaks a rule.
- **amend:** the rule should change.
- **missing:** no rule covers it.
- **conflict:** two rules disagree.

## From the first run (2026-11-08, mirror `08b8643`)

### P1. The server's PHP version: amend `CLAUDE.md` (verdict: amend)

`CLAUDE.md` says the live server runs PHP 8.0. But code on the live site uses the `never` return type, which needs
PHP 8.1. It is in `app/lib/Response.php`, which every endpoint uses, and in four other files. The site works, so the
server must run 8.1 or newer.

**Proposal:** the health report (Phase 2) reads the real version. `CLAUDE.md`'s rule and check R04 then name that version.

### P2. Pages without the session or account bar (verdict: fix, or accept with a reason)

Seven pages draw without the session or the account bar (R05):
- `login.php`, `register.php`, `verify-email.php`, `email-revert.php`: sign-in pages, where no bar may be right. Signed
  in, they could send you on to your dashboard instead.
- `forgot-password.php`, `reset-password.php`: the same.
- `admin/chamber-builder.php`: an old admin page with no session at all.

**Proposal:** sign-in pages redirect signed-in visitors to the dashboard, and are listed as accepted exceptions. The
chamber builder is archived (it also has the only remaining browser dialogs, P3).

### P3. The last browser dialogs (verdict: fix)

Three `alert()`/`confirm()` calls remain, all in `admin/chamber-builder.php` (R02). Archive the page, or move them to
`TvAsk`.

### P4. Emoji in what pages show (verdict: fix)

29 places use emoji where the brand's icons belong (R10). Examples: 📍 and 🧭 on journeys and Discovery, and 👉 on the
order sign-off.

**Proposal:** swap them for `tv_icon()` in one tidy-up release.

### P5. Endpoints without the security token (verdict: accept with reasons, or fix)

Six endpoints take a POST without the security token (R07). Five are public by design and should be listed as accepted
exceptions, each with its reason:
- `api/out.php`: tap counts;
- `api/short.php`: short links;
- `api/locate.php`: location;
- `api/qr.php`: QR images;
- `api/csp-report.php`: browser security reports.

`listing/api/referral/confirm.php` needs a look: it should have a token, or a reason it doesn't.

### P6. The page tree (verdict: missing)

72 pages are on the server but in neither `routes.md` nor `archive.md` (R09).

**Proposal:** approve the generated `page-tree.md` as the page tree, replacing the hand-kept `routes.md`. Then mark each
unlisted page live, archived, or to remove.
