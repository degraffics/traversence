# ADR 0150: Vendor links (one page of a vendor's work orders, 30 days)

**Status:** Accepted (Jason, 2026-11-09). Built: `vendor-links_20261109.zip`.

## Why

The browser checks (decisions/0145) signed in as the test Vendor and got a 403 on `/admin/`: the Vendor level opens
nothing, and nothing told it so. Talking it through, Jason wanted a token landing page for vendors: "token page show:
list of work orders. On click opens the full work order", and "vendor link expire: 30 days, or after 30 days the landing
page has a request a new link button to reactivate". Each work order already had its own private page (`/wo.php`,
decisions/0101 addendum), so a vendor with several jobs juggled several emails and links.

## Decision

1. **One link per vendor, `/vendor.php?v=<token>`.** It lists every work order we've sent them: open ones first (soonest
   due), then those finished in the last 90 days; never drafts. Each shows its number, project, must-have date, their job
   number and where it stands. A tap opens the full work order (`/wo.php`), the same page its email links to. No
   account or password; no client prices, billing or contacts (as 0101).
2. **Links work 30 days.** The vendor's link and each work order's own link (was 60 days) work for 30 days. A work
   order's 30 days run from when it's sent, not from its draft. Opening a work order from the vendor's page renews its
   link if that has run out.
3. **After 30 days, Request a new link.** The expired page (answering 410) says so and offers **Request a new link**.
   The new link is emailed to the address on file (the vendor's orders email, or the one on the work order), never shown
   on the page, so an old or forwarded link can't reopen anything by itself. A link changes only once its email has gone;
   then the old one stops working. At most 3 requests a day for a vendor or a work order; each is logged
   (`vendor_link_requests`). Closed work orders (cancelled, declined, rejected) get no new link.
   **Never a dead end** (Jason, 2026-11-09): "this should always have a back up where an ask for another send or ask for
   support is present instead of lock outs." Every state where a link doesn't work (expired, replaced, unknown, a closed
   work order, past the daily limit, an email that failed) shows **Didn't get it, or need a hand?**: *Send a new link to
   my email* (sent when the address is the vendor's on file or on one of their open work orders; the answer is the same
   either way, so the page never says whose address it is), *Ask our team* (their note to our support inbox), and our
   email address. Past the daily limit, an ask goes to our team to send by hand instead of being refused. A guard of 40
   help asks a day across all vendors keeps the inbox from a flood; past it, the page still gives our email address.
4. **How vendors get it.** Every work order email carries "All your work orders from us, on one page" with the vendor's
   working link (a new one when there's none or it has expired). Back-Office → Vendors shows each vendor's link status
   and **Email their link** / **Email a new link** (the old one stops working), greyed out with the reason when there's
   no orders email.
5. **The Vendor staff level lands on "Your tickets".** `/admin/` and Dashboard → Today show "Nothing is assigned to you
   right now. Your Service Manager will send you a ticket" instead of a 403 (AOS §9A: a Vendor opens nothing except a
   ticket). Ticket-scoped access for that level is still to build.
6. **Cost.** These are system emails, which cost nothing (Jason, 2026-11-09; `CLAUDE.md`, No income, no expense).

## Built

- `app/migrations/2027-02-17_vendor_links.sql`: `vendors.portal_token`, `portal_expires_at`; `vendor_link_requests`.
- `app/lib/Vendors.php`: `link()`, `byLink()`, `emailLink()`, `mayRequest()`, `workOrders()`, `LINK_DAYS = 30`; the help
  box (`helpBox()`, `handleHelp()`, `askByEmail()`, `askTeam()`).
- `app/lib/WorkOrders.php`: `DAYS = 30`, `byTokenAny()`, `renewLink()`, `urlForVendor()`; links counted from sending;
  the vendor's link in the work order email.
- `website/vendor.php` (new), `website/wo.php` (expired page with Request a new link).
- `app/admin/sections.php` and `website/admin/api/vendors.php` (`do: link`): the staff button.
- `app/bootstrap.php`: the Vendor level's landing. `app/ui/faq.md`: two entries for vendors.

Work orders sent before this keep the 60 days they were given.
