# 0105: Workspaces 1 and 2, Dashboard and Pulse

Status: Built (2026-10-21). Jason approved the definitions below the same day.

## Pulse Stream (workspace 2)

Everything that needs an answer or an action, in one stream. Items are read live from where they happen; nothing is
copied (`app/lib/Pulse.php`).

**Filter chips** (each kind shows only to those whose role reaches it):

| Chip | Items | Needs the area |
|---|---|---|
| Inquiries | claims to confirm or on hold; visitors' suggestions and feedback; your own unread messages | Listings |
| Sales | prospects due a follow-up; orders sent and waiting on the client; orders approved | Sales |
| Fulfillment | orders past their due date; proofs with changes asked; approved orders to make and ship | E-Commerce |
| Payments | card disputes; payments received; refunds; QuickBooks refunds to place | E-Commerce |
| Safety | open reports; reviews asked on a notice | Safety |
| System | failed crawl jobs; the crawler stopped; files near the ceiling | Data |

Plus **Mine** (items you've taken), **Needs action** (the default), and **Done**.

**Critical items** are pinned on top in red and shown in Dashboard → Alerts:
- a card dispute;
- an order past its due date;
- a report about safety, a sacred or restricted place, or private details;
- the crawler with no work for more than a day while jobs wait;
- files over 90% of the 200,000 ceiling (counted at most hourly).

**The split drawer** opens the item's own tool beside the stream (its page in the embedded view). On a phone it fills
the screen with a back arrow. **Done** marks the item done and closes the drawer; **Full page** opens the tool in a new
tab.

**Card actions** (30px round icons down the right edge): Take it / Let it go, Done / Not done, Open the full page.
`pulse_marks` keeps who took an item and when it was done. Each mark is also written to the action log
(`audit_log`). An item's key includes what changes it (e.g. a claim's status), so a done item comes back if it
changes.

## Dashboard (workspace 1)

- **Your day:** unchanged.
- **Helper:** unchanged.
- **Alerts:** Pulse's critical items, with a count on the tab.
- **Quick actions:** tiles for what the person's role reaches: New client order, Add a listing, Find a listing, New
  prospect, Pulse, Reports, GO Codes. "New ticket" is shown as coming (Service's workspace).
- **Order terminal** and **Business numbers:** as in 0104.

## Files

- New: `app/lib/Pulse.php`, `app/ui/pulse-ui.php`, `website/admin/api/pulse.php`, `app/migrations/2026-12-14_pulse.sql`.
- Changed: `app/lib/Workspaces.php`, `website/admin/w.php`, `app/lib/StaffLogin.php` (the Pulse API is in every
  staff member's area), `app/ui/app-shell.php` (a check icon), `website/admin/settings.php`.

## Later

**Stream settings** (routing rules per department and answer timers) stays "Coming".

## Addendum (2026-10-21, later): Pulse rebuilt to Jason's mockup

The first build was a task list, which wasn't what Jason designed. The admin Pulse is the role's daily work, shown in
the same shell as the member Pulse. Workspaces 1 and 2 are now one screen (`tv_pulse_board()` in
`app/ui/pulse-ui.php`); the Workspace 2 menu entry opens it scrolled to the stream.

- **Header:**
  - the Pulse mark;
  - **Working as**: Platform Admin or a department for Founders and Executives, your own department otherwise;
  - count chips: Operational Queues, Alerts, Order Mgmt, System Health, Mine, Resolved;
  - the bell (critical cases, and those escalated to you), Stream settings;
  - **+** (post to the team) and **Search all platform events**.
- **Operational Task Queue:** critical and alert cases, each with its action link and a live **SLA clock**, filtered by
  source icon.
- **Quick Actions & Navigation:** links for what the role reaches, and a box to **post** to a department or everyone.
- **Unified Communications Hub (Pulse Stream):** a table of From (source icon and label), Severity, Subject with its
  action link, and Received.
- **Case drawer** (opens on a row):
  - Case #, source, severity, received, SLA, status, assigned, escalation, details;
  - **Open the tool**, as a pop-up of the tool's page;
  - **Quick comment** with **Post**: *Reply to the sender* (emailed, with a copy kept on the case) when a person raised
    the item, otherwise *Note to the team*;
  - **Escalate**, **Assign**, **Take it**, **Mark resolved / Reopen**;
  - **History**.

**Severities and SLAs** (Jason: double the first proposal; editable later in Stream settings):

| Severity | SLA |
|---|---|
| Critical | 2 hours |
| Alert | 8 hours |
| Update | 2 days |
| Event | no clock |

**Escalation:** Escalate sends a case to its department's Manager; still open a further SLA later, it shows as
escalated to the Director.

**Sources:** Support, Sales, Fulfillment, Payments, Safety, **Security** (staff logins locked, a failed sign-in spike
of 5+ in an hour), System and **Team** (posts).

**Admin shortcut bar** (above every admin page): Dashboards, Operations, Security, Users, Content, Logs and System,
each opening the workspaces and tools it groups. The 11 workspaces stay on the left. **Logs** (`/admin/logs.php`)
shows the action log and staff sign-ins.

**Staff profiles** (`/admin/staff-profile.php`):
- photo and name from their public profile;
- TRV ID, level and department, who they report to, who assists them, the workspaces they open;
- work email and phone, availability (in, away, out, until), about;
- their open cases.

Each person edits their own; owners edit anyone's. Their public profile shows a **Traversence team** badge only if
they turn it on.

**Migration:** `2026-12-15_pulse_cases.sql` (case numbers, escalation, `pulse_notes`, `pulse_posts`, staff profile
columns).

## Addendum (2026-10-21, evening): one case per order, and the split view

- **One case per order** (`order:ID`). Sent, approved, paid, proofs with changes, past due, to ship, refunds and
  disputes all fold into it; it takes the most pressing severity among them. A newer event reopens a resolved case.
  The case closes when the order is Complete or refunded.
- **The order in the case:**
  - a stepper: Sent → Approved → Paid → In production → Shipped / Ready for pickup → Complete (orders with nothing to
    ship skip that step);
  - **Next step** buttons: Start production, Sent to the printer, Mark shipped (asks for the tracking number in the
    case), Ready for pickup, Mark complete.

  These use the order's own stage change (`ClientOrders::setStage`), which also emails the client. They're written to
  the case history, and Complete resolves the case.
- **Split view:** the case is a fixed column on the right, and the tool opens to its left in the same overlay. It
  opens there on its own on a wide screen (1100px and up). On a phone, the tool covers the case, with "← Case" to go
  back.
- **The case card:**
  - the title on top, then chips (severity, SLA, open or resolved, who has it, escalation);
  - one action row (Take it, Assign to…, Escalate, Resolve);
  - a composer with Reply to <sender> / Note to the team;
  - the history as a timeline.
