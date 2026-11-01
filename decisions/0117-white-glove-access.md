# 0117: White Glove access: our agent's standing grant, automatic with the plan, scoped by the Terms

Status: Built (2026-10-23). Jason, 2026-10-23: "White glove should automatically link account for access. Ensure it is
scoped under wg description of services or terms." This builds Route 1 of decisions/0102 §5 (the standing grant) for
White Glove accounts, where 0102 already said "we set it up at onboarding".

## What happens

- **Automatic.** While a listing's plan includes White Glove (Strategic Partner, Cornerstone: commercial.md §1–2) and
  it has an account rep (Sales → its lead's rep, an active staff login), that rep holds the **White Glove** role on
  the business. Nobody has to grant it:
  - it **starts** when the plan starts (`Billing::syncTier`), or from the listing's drawer (**Give its rep White Glove
    access**) once a rep is set;
  - it **moves** when Sales gives the lead to another rep (`Leads::setOwner`). The old rep loses access at once;
  - it **ends** when the plan no longer includes White Glove, or when the rep's staff login ends (`StaffLogin::end`).
- **The owner is told** by email when it starts and whenever it moves, with what it covers and how to turn it off.
- **The owner can turn it off**, and on again, in Business portal → Settings & team → White Glove access. Turned off,
  it stays off, whatever the plan or rep does, until they turn it on.

## Scoped by the Terms

The role is fixed in code (`Team::WHITE_GLOVE`), not editable in Permissions, and matches the Terms (`/terms.php#white-glove`)
line for line:

| Area | White Glove |
|---|---|
| Listing details, catalog and content, publish, theme, QR and short codes | Change |
| Customers, orders, inbox | See |
| Community groups | See |
| Plan & billing, ledger, team & roles, domain/keys/webhooks, exports, deleting the business | No |

- **Terms** (`#white-glove`): what White Glove is; that choosing a plan with it gives the assigned agent access from the
  day it starts, without a separate step; what the access covers and doesn't; that it's logged and visible; that the
  owner can turn it off (the work that needs it waits; price unchanged, to confirm with counsel); and when it ends.
- **Where it's said before buying:** the Plans page (a note under the plans, linking the terms) and the claim flow's
  Strategic Partner line ("White Glove setup and upkeep, with your agent's access included (see Terms)").

## Seen and logged

- **The agent** opens the business from the listing's admin drawer (**Open its business portal (White Glove)**) or
  `/business/`. A banner says they're in as White Glove, with a link to the terms.
- **The owner** sees who has access, since when, and what it covers in Settings & team.
- **Action log** (`audit_log`, route `white_glove`): the grant starting, moving, ending, being turned off and on, and
  every change the agent makes through the business tools.

## Data and files

- `support_grants` (admin data, made on first use by `AdminTables`; no migration): listing, user (the staff login's
  user), role, route, granted / revoked when and by whom, why, `owner_off`.
- `app/lib/SupportAccess.php` (new); `Team.php` (the White Glove role, `roleName()`, grants in `roleOf()` and
  `listingsOf()`); `Billing.php`, `Leads.php`, `StaffLogin.php` (the triggers); `business/router.php`, `api.php`,
  `portal.php`; `admin/tools.php` (the listing drawer); `terms.php`, `plans.php`, `js/claim.js`; `faq.md`.

## Not yet

- Ticket access (Route 2, timed) and the "agent is in · End now" banner on the owner's side while the agent is working.
- White Glove sold as a service outside these plans (an engagement, admin-map Engagements): it will start the same grant.
