# 0115: The business portal's foundation: one engine, scoped to a business, walled in the database

Status: Built (2026-10-23). Jason, 2026-10-22: "the business[es] are built on the same functionality we have as the
platform admins, just segmented into plans", "Admin should have any and everything the business would", and on
2026-10-23 "Go on business portal". This is step 1 of business-portal-architecture.md §5, on decisions/0110's walls.

## What a business sees

- **`/business/`** lists the businesses you're on the team of, with your role and the plan.
- **`/business/<listing>/<module>`** is that business's portal, in the site's frame. The menu has its modules (§2):
  - Dashboard;
  - Inbox;
  - Customers;
  - Quotes & invoices;
  - Catalog;
  - Orders;
  - Storefront & pages;
  - Marketing;
  - Reports;
  - Settings & team.
- **Open now:**
  - **Dashboard:** the numbers, and every tool with its state.
  - **Customers:** our CRM (decisions/0111) for that business: add, notes, tags, segments.
  - **Settings & team:** the team, what your role can do, and what your plan includes.
- **Greyed, with the reason:**
  - **Your role:** for example, "Your role (Creator) can't open Customers. The owner can change your role."
  - **Your plan:** "Included in Basic Partner and up: see plans", linking to `/plans.php?listing=<id>`.
  - **Coming:** what the tool will do, with a link to today's business dashboard meanwhile.
- **Today's business dashboard** (`/listing/businessportal.php`) has an **Open the portal** button for the selected
  listing.

## One engine: the scope

- **`Scope`** (`app/lib/Scope.php`) says whose data a tool is working on.
  - By default it's ours: owner 0, our tables wherever `Database::t()` puts them.
  - The business router switches it, once per request, to one business: the owner becomes the listing, the
    connection is the business gateway's, and every table becomes its tenant view (`b_<table>`).
- **`Customers`** reads its owner, connection and table names from the scope.
  - The same tool definition (`growth/customers` in `app/admin/tools.php`), drawer and widget kit run for us in the
    admin and for a business in its portal.
  - Inside a business it shows nothing of ours: no member cards, our orders or our plans. Its notes carry its team's
    names, handed in by the router.
- **The widget kit** talks to whichever API the page names (`window.AK_API`): `/admin/api/` for us,
  `/business/api/<listing>/` for a business.

## The walls (decisions/0110), as built

1. **Router.**
   - `business/router.php` and `business/api.php` check your role on the listing on every request, with our login
     (`Team::roleOf`).
   - No role: a 403 page or answer, and a `SECURITY` line in the error log.
   - Seeing needs the module's permission at view; changing needs edit.
2. **Its own login and connection.**
   - `BusinessData::open()` signs in as the **listings login**, on a connection of its own, and sets it to the one
     business (`@tv_tenant`).
   - That login can't reach the main database, our admin data or members' data. Checked: MySQL refuses.
3. **Tenant views and triggers in the listings database.**
   - Every business table has `owner_id` (the listing).
   - Tools read it through `b_<table>`: `WHERE owner_id = tv_tenant()`.
   - Triggers refuse any insert, update or delete of another business's row, including one that goes around the view
     to the table itself.
   - A new row is filled in as the connection's business.
4. **One gateway.** Business tools get only the gateway's connection (`Scope::pdo`). A refusal by the walls is logged
   as a security event.
5. **Tested on every check.** System Configuration → Data separation → **Run the check** adds a **Business walls**
   group: two test businesses, each on its own listings connection. Business A tries to:
   - see business B's customers;
   - change them, through its view and around it;
   - delete them, or add rows as B;
   - reach the main and admin databases.

   Every try must be refused. The test rows are removed again.
- **Limits, said plainly:**
  - **Reads that go around the views.** cPanel can only give a login all of a database or none of it, so code that
    reads a table directly instead of its view could see another business's rows. The gateway reads only through the
    views, and writes are refused in the database anyway.
  - **Changing the business on the connection.** The connection's business is a session value that its own code could
    change. Only the router sets it, and tools never run raw SQL on it.

## Roles and plans

- **Viewer** joins the seven roles (§7.3). It can see the listing, catalog, orders, customers, inbox, ledger and codes,
  and change nothing. It has no access to billing, the team, keys, exports or deleting the business.
- **`Plan`** (`app/lib/Plan.php`): the features of §4, each with the plan it starts at; `Plan::allows(tier, feature)`
  and `Plan::upgradeNote()`.
- **Back-Office → Plans & subscriptions → Plan features** (the first part of the plan configurator, §7.4):
  - sets the plan each feature starts at, or "Not offered", saved in `plan_features` (admin data, group 5);
  - Directors in Finance change it; businesses see it on their next page;
  - prices and allowances stay in `Billing::PLANS` until the configurator takes them on, with versions and the 65%
    floor check.

## Files

- **Libraries:** `app/lib/Scope.php`, `BusinessData.php`, `Plan.php`; `app/business/portal.php` (modules and the
  check).
- **Portal:** `website/business/router.php`, `api.php`, `index.php`, `.htaccess`.
- **Changed:**
  - `Customers.php`: follows the scope.
  - `Team.php`: Viewer.
  - `Database.php`: `realmOwn()`.
  - `DataCheck.php`: business walls.
  - `tools.php`: the customer card in a business.
  - `sections.php`, `manifests.php`, `DataMove.php`: plan features.
  - `admin-kit.js`: `AK_API`.
  - `listing/businessportal.php`: the button.
  - `faq.md`.

## Next (§5)

1. Team management in the portal: invite, change roles, Viewer, through a checked step that runs as admin.
2. Move our tools across one at a time, scoped the same way:
   - Quotes & invoices, Catalog and Orders first;
   - then Discount codes and GO Codes;
   - then the business's own Taxes & shipping, Price lists and Storefront & pages (built for us in 0112).
3. Support access for our team (a standing grant or a ticket, 0102), with the "agent is in" banner.

## Addendum (2026-10-23): your businesses, from every record; our team adds people

Jason, 2026-10-23: "I am not seeing my business account."

- **One list of your businesses.** A business you're on was read three different ways: the account menu from
  `listing_access` and grants, `/business/` from the team (`listing_memberships`), and today's business dashboard
  from `listing_access` only. `Team::listingsOf()` now reads all three (team, listing access, the claimed owner) and
  all three places use it, so they agree.
- **Our team puts people on a business.** Admin → Local → Listings → a listing's drawer shows **Owner & team** and
  **Assign to a user account** (or **Add a user account** once it has an owner: the account email and a role) and **Remove a user account**, with Owner & team first in the panel (`Team::adminSet()`,
  `adminRemove()`; managers in Operations, Service or Sales). Owner also makes them the listing's claimed owner (the
  owner before stays on as Manager); Owner and Manager are kept in `listing_access` too, which the listing editor
  reads. The member is emailed; every change is in the audit log (route `admin`). A staff login can't be added: the
  person's own member account is.
