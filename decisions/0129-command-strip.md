# 0129: The command strip on record pages, and section controls

Status: accepted (Jason, 2026-10-28)

## Why

On an order, "Save and close" at the foot of the work order form read as "save and close the order". The page has no
order-wide save, since each part saves on its own, so the button made a work order nobody meant to make
(CP0002).

## What

- **The command strip**, always there, pinned just under the header (`app/ui/command-strip.php`, `js/tv-strip.js`):
  - **left:** **Back**, the record's name and status pills, **"N unsaved"** (tap to go to it), and the latest notice
    (echoed from the page's notices);
  - **right, the master commands:** **Save**, **Send** (to the client), **Close** (back to the list) and **More ▾**.
- **Master Save** saves every section with changes, one at a time, as its own button would (links wrapped past the
  firewall). It stops at the first that doesn't save and reloads with what each said. It never makes anything new: a
  form that adds something (an item, a project, a new work order) or whose button asks first (a payment, a send) waits
  for its own button, and the strip says which.
- **Close and Back** ask before leaving anything unsaved (TvAsk, never a browser dialog).
- **Send and More** trigger the page's own buttons, so their questions and rules still apply.
- **"Unsaved"** is judged against the page as it stands once its own scripts have run, so nothing counts until you
  change it.
- **Sections** start at each heading (`h2.hb-h`); each gets a bar with a dot when something in it isn't saved, and
  Close/Open to fold it.

## On the order page

- The strip shows: the order number and client, status, stage, sandbox. **Send** is "Send to the client" (draft or sent)
  or "Send the client an update" (approved or paid). **More**: What the client sees, Money, Reopen for changes,
  Duplicate, Documents and files, Cancel the order.
- **Work orders:** the New work order form starts closed and opens on "+ New work order". Its button is
  **Save work order ▾**, with Save draft (keep editing), Save and start another, and Save and send to vendor.
- **Delete draft:** a work order that was never sent can be deleted (`WorkOrders::deleteDraft`); a line stays in the
  order's history. Sent ones are still cancelled, and the vendor is told.

## Next

The same strip on the client, listing, product and vendor pages.

## Revised (Jason, 2026-10-28)

- The strip sits **above the page**, in place of its title and breadcrumbs. The admin **Shortcuts** move into ☰, so the
  strip has the whole width.
- **One button for the master commands:** it reads **Save** when something's changed and **Close** otherwise. Its ▾
  holds Save, Save and close, Send (to the client) and Close.
- **☰** holds the record's other actions. On the order: What the client sees, Money, Documents and files, Cancel, and
  the page's own Reopen for changes, Open as the client and Duplicate controls, moved there as they are.
- **No repeats:** the order's old top row (status pills, those buttons) is gone. "Client opened it" became a pill in
  the strip.

## Revised again (Jason, 2026-10-28, from a phone): slimmer, and the status moves out of the strip

On a phone the strip took about a quarter of the screen and stayed there: three status pills wrapped onto extra lines,
and the business name repeated the header. Now:
- **One line, 44px:** Back, "Order #9", the master button (Save, with a count when more than one form is changed, or
  Close) with ▾, and ☰. No pills, and no latest-notice text in the strip.
- **It slides away as you scroll down and comes back as you scroll up.** It stays in view while something is unsaved,
  or while one of its menus is open.
- **Where the order stands** goes in a notices box under the strip, shown when the page loads: the state, the
  production stage, test (sandbox), and when the client opened it. Close it with × once read. It stays closed (per
  order, in this browser) until what it says changes, then shows again.
- **The notices box says whose turn it is (2026-10-28).** "Approved: waiting on the client's payment", "With the vendor:
  work order issued". When the client opened the order shows only while it's theirs to approve (sent). Otherwise it read
  as if the client owed a reply when the vendor had the job.
