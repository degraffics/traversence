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
