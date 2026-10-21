# 0106: Vendor orders and jobs, margin

Status: Built (2026-10-21). Jason: model it on how 4OVER publishes orders; show margin to staff; vendor statuses stay
internal, and the client gets plain progress only.

## Model (as 4OVER publishes it)

- **Vendor order** (`vendor_orders`):
  - the vendor and their order # (e.g. 4OVER #017633948);
  - the purchase date;
  - payment, as card type and last 4 only (never a full number);
  - the order total and a note.
- **Vendor job** (`vendor_jobs`), one per project in the vendor order. One vendor order can hold jobs for several of
  our client orders. Each job has:
  - our client order and item;
  - Project/P.O. name, set name, Job ID, the job status in the vendor's own words, tracking;
  - SKU and specs, run size, turnaround, production facility, completion date;
  - ship-to and shipping method, an artwork link;
  - costs: subtotal, shipping & processing, tax, total.

`app/lib/VendorOrders.php`. Migration `2026-12-17_vendor_orders.sql` also copies the rows of the earlier
`order_vendor_jobs` (0105).

## Where it's used

- **The order's case** (Pulse):
  - *Sent to the printer* opens the job form. It adds to the latest vendor order by default (one 4OVER order holds
    several jobs) or starts a new one. Specs, shipment and artwork sit under "More details".
  - The case lists the order's vendor jobs, each with **Update** (status, tracking, completion date, Job ID, costs).
  - A vendor's tracking fills **Mark shipped**.
- **Back-Office → Vendor orders** (`/admin/vendors.php`) is laid out like 4OVER's list: Order info (spanning its
  jobs) | Project/P.O. (cost, the client order and its case, artwork) | Shipment | Set name | Job ID | Job status (with
  tracking). Statuses and tracking update in the row, vendor orders edit in place, and jobs can be added here too.
  It's searchable by order #, P.O., Job ID or client; filter by vendor or "not complete".
- **Margin (staff only):** the client's price before tax against the sum of vendor job costs, shown in the case and in
  the order page's totals ("margin $12.45 (36%) · vendor cost $22.50 · our team only"). It turns red when negative.

## The client

A client never sees a vendor, a Job ID, a vendor status or a cost. They see our plain progress through the order's
stages (In production, Ordered from the printer, Shipped with tracking, Complete). The vendor's status reaches them only
as that stage, set by our team.

## Later (step 3)

The 4OVER and Signs365 APIs fill and update these same records (statuses, tracking, completion dates, costs).
