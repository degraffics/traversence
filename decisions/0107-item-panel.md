# 0107: The item panel, proof versions with several files, and item statuses from the vendor

Status: Built (2026-10-21). Jason's decisions:
- an item panel instead of a status dropdown on the invoice;
- see earlier versions of uploads, and add several files, not just one;
- item-level vendor matching with automatic statuses, but an item **defaults to the invoice's (order's) vendor job**
  unless one is set on the item itself.

## The order page (admin/client-order.php)

- **Row:** each item shows:
  - a **status pill**, coloured by group:
    - Before approval: amber;
    - Approved: blue;
    - Out the door: green;
    - Exceptions: red;
    - Housekeeping: grey;
  - its **tags**;
  - the vendor's own status as a small note (our team only). The note comes from the item's own job, or else the
    order's job.
  - Tapping the pill, the note or the panel icon opens the item panel.
- **Item panel:** a split view on the right. On a desktop the order stays visible beside it; on a phone it fills the
  screen. Close with × or Esc. After a save, the page comes back to the same item and tab (`#item=<line>:<tab>`).
  Tabs:
  - **Details:**
    - the item, its options, quantity × rate, and status;
    - **tags** (our team only), with quick Rush, Reprint and Sample buttons that add or remove each.
  - **Status:**
    - the status picker (all groups) and tracking;
    - the item's history: every event recorded for it.
    - The client is never emailed from here; **Send an update** does that.
  - **Proofs:**
    - every version, newest first, each with its files, status, dates and the client's note;
    - **upload a new version** with one file or several (up to 12).
  - **Files:** artwork, reference and shipping labels, several at a time. Artwork can be PDF, images, SVG, TIFF, EPS,
    PSD, AI or ZIP. Anything that isn't a PDF or an image downloads instead of opening.
  - **Shipping:** where it ships, opening the existing shipping dialog.
  - **Vendor:**
    - the jobs that cover this item, with what their status does to it;
    - **This job is for**: the whole order, or one item;
    - the item's margin, when its own job has costs (our team only).
- The row's **+ Upload proof** also takes several files at once.

## Proof versions

- A version is one or more files with the same version number (`order_proofs.version`).
- Uploading a new one marks the previous waiting or changes-requested version **Replaced**. Every version and file is
  kept.
- The client's page shows every file of the latest version together. **Approve** and **Make changes** apply to the
  version as a whole (`decideProof` updates every file of that version).

## Item statuses from the vendor

- **Which job covers an item:**
  - A vendor job is either for one item (`vendor_jobs.line_id`) or for the whole order (null).
  - An item with a job of its own follows that job.
  - Every other printed item follows the order's job. Shipping lines, GO Codes and cancelled items are skipped.
  - When **Paste from 4OVER** imports a project, it matches the project to an item by the ship-to name and street
    number in the item's title, note or shipping address. It sets the item only on a clear match; otherwise the job
    stays on the order.
- **The mapping** (`VendorOrders::itemStatusFor`):

  | The vendor says | The item becomes |
  |---|---|
  | Processing, Prepress, Batch Imposition, Printing | In Production |
  | Cutting, Finishing, Packaging | In Finishing |
  | Complete: Shipped (with tracking) | Shipped, with the tracking |
  | Ready for pickup | Ready for pickup |
  | Delivered | Delivered |

- **Only forward:**
  - A vendor status never moves an item back.
  - Items on a hold (Payment Hold, Collections, Holding) or in housekeeping are left alone.
- **No email:** the client isn't emailed by the sync.
- **When it runs** (`VendorOrders::syncItems`):
  - adding a job;
  - updating a job;
  - importing from 4OVER;
  - changing which item a job is for.

## Data

`app/migrations/2026-12-18_item_panel.sql` adds `order_lines.tags` (MySQL-safe, guarded). Settings lists it.

## In the Pulse

- **For** (the order's case): *Add a vendor job* and a job's *Update* both pick what the job is for:
  - the whole order (the default);
  - or one of its printed items.
  - The default is the item itself when the order has only one.
- Each job in the case's list says what it's for.
- A job can't be set to an item from another order.

## On the Vendors page

- Each job row says which item it's for, with the item's status. The item's name opens the order with that item's
  panel on its Vendor tab.
- A job with no item says "the whole order".
- The search also finds jobs by item name.

## Fix (2026-10-21)

- Moving a job between the whole order and an item now applies every job on the order again, not only the one that
  moved.
- Each job still only moves items forward. So an item that has its own job "Complete: Shipped" shows Shipped even
  after another job is moved onto it.

