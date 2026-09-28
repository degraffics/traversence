# ADR 0041: SC&T Operations Merge Into Traversence Core, With a Deferred Offsite Export Mode

**Status:** Accepted (2026-09-27)

## Context

SC&T OS is the operations backend built for SJ Portables (shedscontainersandtanks.com): a
public catalog/quote site plus an admin CRM covering multi-yard inventory, CPQ/quoting,
geo-priced delivery, and a sales pipeline. It was built and documented (`INTEGRATION.md`,
the SC&T `README.md` and `schema.sql`) as a standalone PHP/MySQL application on its own
host and database, joined to Traversence by four capabilities delivered over signed HTTP:

1. Ownership/identity handoff — Traversence hands a signed, short-lived token
   (`GET /sso.php?t=TOKEN`, HMAC-signed, 5-minute generated expiry, 15-minute hard cap) so a
   listing owner lands in the SC&T admin without a separate login.
2. Lead delivery — every SC&T quote/RFQ is pushed to Traversence as a lead
   (`POST` to a configured webhook, `X-SCT-Signature` HMAC, logged and manually retryable,
   never blocking quote capture).
3. Inventory visibility — Traversence reads live stock and starting prices from SC&T
   (`GET /api/inventory.php`, `X-API-Key`, 60-second cache) for the listing page.
4. Quote creation — a quote can be created from the Traversence side, either
   server-to-server (`POST /api/quote.php`, HMAC) or via a public `<iframe>` embed
   (`/embed/quote?tv_listing_id=&tv_user_id=`).

This standalone-plus-HTTP-integration design was the first architecture built and is fully
implemented in the SC&T codebase. It requires a listing owner to provision and maintain
their own host and MySQL database, manage a shared HMAC secret and API key, and operate a
second admin login — real cost for the common case of a listing owner who just wants
operations features inside Traversence.

Jason reversed the build order: rather than SC&T being a separate app that Traversence
integrates with, a listing owner should be able to request SC&T-style operations features
from inside Traversence itself, configure them through an in-platform setup wizard, and
have them show up in the same listing management system they already use — with no
separate host or login for the common case. He also clarified that this does not retire
the standalone design: it is the reference implementation for a self-hosted deployment a
listing owner can still choose later, administered on their own server while syncing back
to Traversence.

## Decision

SC&T's operations features (catalog/inventory, CPQ/quoting, delivery pricing, CRM
pipeline) will be built directly into Traversence's own application and database as the
**native (merged) deployment mode**, and this is the default and the near-term build
target — starting with SJ Portables as its first real instance. A listing owner requests
operations features for their listing, is configured through an in-platform wizard, and
from then on works entirely inside Traversence's existing listing management system. There
is no separate host, database, or admin login in this mode.

Despite one shared codebase and one shared database, every operational table — catalog,
inventory, quotes, CRM/pipeline state, and any operations-scoped staff accounts — is
strictly scoped by `listing_id`. Each listing's catalog, pricing, inventory, and pipeline
are isolated from every other listing's as completely as if they lived on separate
databases; nothing is pooled or shared across listings. This isolation is enforced in the
application layer (and, where practical, with foreign-key constraints), not by physical
separation.

The standalone SC&T codebase (`sct.zip`) and its `INTEGRATION.md` contract are **not
superseded** by this decision. They remain the reference implementation for a second,
future **offsite (exported) deployment mode**: a listing owner runs their own SC&T-style
backend on their own server and database, administers it themselves, and syncs back to
Traversence via the same four capabilities, implemented as the signed HTTP touchpoints
already built and documented. Building the offsite export path is not a near-term
priority and is not scheduled.

Both modes are built against the same underlying four-capability contract — ownership
handoff, lead delivery, inventory visibility, and quote creation — so that native mode's
internal implementation (direct function calls and shared-database reads, no HMAC secrets
or tokens) and offsite mode's implementation (the signed HTTP touchpoints above) are two
implementations of one contract rather than two unrelated designs. Native mode should be
built to honor that contract explicitly, so that exporting a listing to its own offsite
install later does not require re-deriving what "synced back to Traversence" has to mean.

## Consequences

A listing owner gets operations features without provisioning hosting or MySQL, without
managing a shared secret, and without a second login — the common case gets simpler and
cheaper to support. SJ Portables will not deploy the standalone SC&T PHP application as
its production system; instead, its schema and logic (catalog, quotes/CPQ, the delivery-fee
and geo-pricing rules, the quote pipeline state machine) will be ported into per-listing
scoped tables and modules inside Traversence's own core application and database.

`INTEGRATION.md` and the standalone SC&T codebase are retained as-is: they are not deleted
or rewritten, and they stand as the documented, already-built reference for offsite mode
whenever a listing owner needs or wants to self-host. No offsite work is scheduled now.

New engineering surface this decision creates: every native-mode table and query needs
explicit `listing_id` scoping from the start (no shared catalog, pricing, inventory, or
staff table may exist without it), and a setup wizard is needed to provision operations
features onto a listing. The exact mapping from SC&T's existing schema
(`products`, `units`, `stock`, `quotes`, `quote_events`, `admin_users`, etc.) to
per-listing-scoped Traversence tables is deferred to implementation planning, not decided
here. The offsite mode's timeline also remains open; building native mode against the
shared four-capability contract is meant to keep that path affordable later, but that
assumption should be revisited once native mode ships and its real internal seams are
known.
