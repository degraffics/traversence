# 0111: Customers (CRM), built in our admin first

Status: Built (2026-10-22). Jason, 2026-10-22: "Admin should have any and everything the business would." The CRM is
the first gap closed (business-portal-architecture.md, "Admin first").

## What it is

**Sales & Growth → Customers** (`/admin/growth/customers`): one record per person or company that has bought from
Traversence.

- **Built from what they bought:**
  - orders that went to them (not drafts or voided ones), with what was paid, refunded and disputed (`order_ledger`);
  - plans, with their paid charges (`subscriptions`, `subscription_charges`);
  - matched by email.
- **Kept up to date by itself:** opening the list brings in anything new since the last time. Details found on orders
  fill in blanks; once someone edits a name, company or phone by hand, the orders don't overwrite it.
- **Added by hand:** "Add a customer" for a lead or a customer from before the site.
- **Numbers:** customers, new this month, returning, VIP, inactive, lifetime value.
- **Segments**, worked out from the numbers, never stored:
  - New: first purchase in the last 30 days;
  - Returning: two orders or more, or an order and a plan;
  - VIP: $500 or more (`Customers::VIP_CENTS`, to become a setting);
  - On a plan: an active, trial or past-due plan;
  - Inactive: nothing in six months and no active plan;
  - Lead: no purchase yet.
- **Tags:** any the team makes. Retail, Wholesale, Client order and GO Codes to start with.
- **The customer drawer:**
  - details, segments, tags and lifetime value;
  - their member card and listing;
  - notes and activity: notes, orders, plans and charges, newest first;
  - three actions: Add a note, Tags, Edit details.
- **Who:** staff in Sales, Customer Success and Finance; Permissions can change it (`growth/customers`,
  `growth/customers.edit`, `growth/customers.create`).

## Where its data lives (decisions/0110)

- Five tables: `crm_customers`, `crm_tags`, `crm_customer_tags`, `crm_notes` and `crm_sync`.
- They're the admin's. On first use they're made **in the admin database** (when it's set and the main login can reach
  it) and recorded in `data_moves`, so `Database::t()` finds them there. Nothing to import.
- They're part of group 5 (Our sales), so Undo and Move on Data separation carry them too.
- Every record has an `owner_id`: 0 is Traversence. A business's own customers carry its listing when the business
  portal scopes the tools, in the businesses' own database.

## Two engine additions (for every tool)

- **Filters built from data:** a tool's `filters` can be a function, so the tag filter lists the tags there are, with
  their counts.
- **Actions in the drawer:**
  - a tool's detail can carry `acts`, buttons that open a small form (text, notes, checkboxes) and post
    `action: act` to `/admin/api/<module>/<tool>`;
  - access is checked by the tool's `.edit` rule;
  - it can also carry a `timeline`.

## Coming next for the CRM

- Messages in the activity: Pulse cases and replies by the customer's email.
- Merging two records of one person.
- Price lists per segment (Wholesale): the B2B step.
- Email campaigns to a segment: the Marketing step.
- The business's own customers: the business portal step.
