# 0110: Data separation: three realms (member, business, admin), walls in the database, not only in the code

Status: Proposed (Jason, 2026-10-22: "a clear line of separation for data. USER/LISTING/ADMIN router to specific
datasets. Even if the tools are shared we need 100% data security no matter what comes at us.")

## The principle

Tools are shared code; **data is not shared**.

- Every request is in exactly one realm: **Member**, **Business** or **Admin**.
- Each realm reaches only its own dataset through its own door.
- A business's door is narrowed again to that one business.

No single bug, forgotten filter or tampered request should be enough to cross: crossing has to get past several
independent walls at once. No system can promise 100%, but this design makes a leak need several separate failures
together, and every attempt is logged.

## The five walls

### 1. Separate routers (the realm is fixed by the door you came in)

| Realm | Addresses | Who gets in | Session |
|---|---|---|---|
| Member | `/user/…`, `/user/api/…` | a signed-in person | their member session |
| Business | `/business/<listing>/…`, `/business/api/…` | a person with a role on that listing (`listing_memberships`) | member session + the business they chose, re-checked on every request |
| Admin | `/admin/…`, `/admin/api/…` | a staff login (two-step) | staff session |

- The realm is set by the router, never by a parameter.
- A business request names its listing in the address. The router checks the person's role on that listing on every
  request, then **forgets the address**: from there on, the business comes only from the verified membership.

### 2. Separate database accounts (the database itself says no)

Three MySQL users, created in cPanel, each with its own password in `.env`:

| DB user | Can read / write | Can never touch |
|---|---|---|
| `tv_member` | public data (directory, places, published content); the person's own records | business-private tables (customers, cost and markup, orders of other people, gateway keys); staff tables, logs, settings |
| `tv_business` | public data; business tables **only through the tenant views** (wall 3) | other businesses' rows (the views hide them); members' private data (messages, address book, contacts); staff tables, logs, settings, our QuickBooks keys |
| `tv_admin` | everything, with every read of a business's private data logged | — |

A bug in business code that tries `SELECT * FROM staff_accounts` or another member's messages fails in MySQL with
"access denied", whatever the code says.

### 3. Tenant views (one business can only see itself)

- Every business-owned table has a required `tenant_id`.
- The business DB user isn't given the tables themselves. It gets **views** that show only the current business's
  rows:
  - `b_orders = SELECT … FROM orders WHERE tenant_id = tv_tenant()`;
  - and the same for customers, items, quotes, promotions, files and the rest.
- `tv_tenant()` returns the business set for this connection, once, by the router (wall 1). It can't be changed
  afterwards in the same request.
- **Write triggers** refuse any insert or update whose `tenant_id` isn't the connection's business.

So even a query with no `WHERE` at all returns only that business's rows.

### 4. One data gateway per realm in the code

- Shared tools never get a raw database connection. They get the realm's gateway (`MemberData`, `BusinessData`,
  `AdminData`), which runs on that realm's DB user.
- The business gateway adds the tenant filter itself, and reloads a record to check its owner before any change.
- **A build check** fails the build if a business or member tool contains raw SQL outside its gateway.

### 5. Files, secrets and watching

- **Files:**
  - each business's and member's uploads live in their own folder outside the web root (or their own S3 prefix);
  - served only through the realm's check;
  - never by a guessable public path.
- **Secrets** (a business's gateway keys, tokens) are encrypted with a key that only the server holds (`.env`). Admin
  screens show "connected", never the key.
- **Watching:**
  - every refused access (wrong realm, wrong business, denied by MySQL) is a security event in the log;
  - repeated refusals lock the session and alert the Founder.
- **Tested on every build:**
  - business A tries every tool, record and file of business B;
  - a member tries every business and admin address;
  - business code tries the staff tables.
  - Every attempt must fail, or the zip isn't delivered.

## Our team and a business's data

The admin realm can see everything, but:
- a business's private data (customers, orders, cost and markup) is opened only through support access (a standing
  grant or a ticket, decisions/0102), with the "agent is in" banner, and every view logged;
- oversight screens (counts, moderation of public posts, disputes) use what's public or what the case needs.

## What it needs from Jason

- **cPanel → MySQL Databases:** create two more database users (`…_member`, `…_business`) on the same database. I'll
  supply the exact privileges as a script to run once in phpMyAdmin.
- **Put their passwords in `.env`.**
- **Confirm Bluehost allows the views, the one function and the triggers.** A small test script checks before
  anything relies on them. If it doesn't, walls 1, 2, 4 and 5 still stand, and wall 3 moves into the gateway.

## Order

This comes first: before any business tool moves into the business portal.

1. The three routers and the gateways.
2. The DB users and the views.
3. The test suite.
4. Then the tools move across, one at a time, each passing the tests.

## Revised (2026-10-22): separate databases, because cPanel grants per database

Bluehost's cPanel gives a database user its privileges **per whole database**, and a shared-hosting user can't run
`GRANT` for single tables or views. So the database wall is drawn by **database**, not by table. Jason has created the
databases:

| Database | Holds | Its user |
|---|---|---|
| `degraff1_traversence` (today's) | public data, our own business (tenant 0), staff and settings | `degraff1_TRAV` (admin), as now |
| `degraff1_trv_listings` | **every business's private data**: catalog, customers, quotes, merchant orders, gateway keys, storefront settings, business files index | `degraff1_trv_listings` (its own user, this database only) |
| `degraff1_trv_users` | **members' private data** (messages, address book, contacts, saved items, settings), moved in a later step | `degraff1_trv_users` (its own user, this database only) |

- **Business realm:**
  - connects as `trv_listings`, with **no access to the main database at all**;
  - what it needs from public data (its own listing's name, address, categories) is handed in by the router as read-only values.
  - So a business-side bug can't reach staff tables, members, or our keys: MySQL refuses.
- **Within `trv_listings`:**
  - every table has `tenant_id`;
  - the tenant views and write triggers (wall 3) keep one business to itself (tested on Bluehost first);
  - the gateway (wall 4) does the same in the code.
- **Admin realm:** `TRAV` is added to both new databases (all privileges), with every read of a business's private data
  logged (0102 support access).
- **Member realm:** moves its private tables to `trv_users` in its own step, after the business side. Until then,
  members run as today.
