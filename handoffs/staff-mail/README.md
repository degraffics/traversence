# Staff mail: staging's news and staff hidden addresses (held, 2026-11-26)

Approved by Jason via the session manager: "Do (a), (b) and (c) as one admin release after ba-proof-fixes."
Built on Sync 11:50 UTC (f43fe5b). Patch: `staff-mail.patch` (app/lib/Mailer.php, Notify.php, StagingSite.php, StagingPublish.php).

- **Notify kind `staging.done`** (staff, Team, email on): "Staging is refreshed" and a publish's result reach the staff
  login that pressed through Notify by its staff id (`StagingSite::tellPerson`); a member who pressed gets their own email.
- **Mailer::send**: an address at `…@staff.traversence.invalid` goes to that login's active `staff_accounts.email`, as
  `Notify::addressOf` resolves it; none, an ended login, or any other `.invalid` is refused with why and logged in
  `comms_route` as `refused`, "not sent: no deliverable address". Covers the callers that mailed a staff login's hidden
  address: StagingApi sendme, Releases (~1616), AiAssist, Vault, GuideAudits, Takedowns, Services, Claims, and
  admin/Workflow.php's "email the owner" step. Already safe: Releases' review email (~840) and DailyReport::owners()
  skip or resolve `.invalid`; admin/tools.php's gallery notice mails members and business teams only.
- Test: `tools/mail-tests/staff-address.php <root>/app`, 10/10 on MariaDB.
- Next: rebuild on the newest sync after ba-proof-fixes is live, roll back proof, FAQ entry, send.
