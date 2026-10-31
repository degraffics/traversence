# 0118: The business portal: Quotes & invoices, Orders and the team

Status: Built (2026-10-23, overnight). Jason, 2026-10-23: "Go on the 4" (quotes and invoices, orders, team management
in the portal, small cleanups). Step 2 of business-portal-architecture.md §5, on decisions/0110's walls and 0115's
engine.

## Quotes & invoices (Basic Partner and up; the role needs Ledger)

- **One row from quote to paid order** (`app/lib/BizQuotes.php`), in the business's own walled data:
  `biz_quotes`, `biz_quote_lines`, `biz_payments`, `biz_quote_log` (BusinessData VERSION 3, read through the tenant
  views; the same guards as Customers and the Catalog).
- **New quote**: the customer's email (found in, or added to, Customers), their name, what it's for. In its card:
  - **Add from your catalog** (its price for that many, cost kept privately) or **Add a line** by hand;
  - **Terms & how to pay**: due on receipt / Net 15 / Net 30, a deposit %, the sales tax rate, the business's own
    pay link (https only), other ways to pay (Zelle, checks), notes, how many days the quote is good;
  - **Send** emails a private link; **Record a payment** (amount, how, date, note); **Make a copy**; **Void** (only
    without payments).
- **Statuses**: Draft → Sent → (approved) Invoice → Paid; or Declined / Void. Numbers per business: Q-0001 as a quote,
  INV-0001 once approved. The last quote's terms, tax and pay link carry over to the next.
- **The customer's page** (`/q.php?t=<business>.<token>`, noindex, no account): the quote with Approve (typing their
  name) or Decline; once approved, the invoice: paid, still due and by when, and how to pay (Pay now opens the
  business's own pay link). Print / save as PDF. The business's owner is emailed on approve and decline. The page opens
  that business's realm and reads only there; a link with another business's number is a 404. Its form key comes
  from the link itself, so it works without a session.
- **Money never passes through Traversence.** Payments are made to the business and recorded by its team. Card
  checkout through a business's own gateway is the later `card_checkout` feature (Core).

## Orders (every plan; the role needs Orders)

- Every approved or paid quote is an order. Stages: New, In production, Ready for pickup, Shipped, Done.
  **Ready** and **Shipped** email the customer (with the tracking or pickup note). The list shows what's not done yet
  by default, with the payment state.

## The team (Plan & settings → Your team)

- **Add** by email and role: someone with an account joins at once (and is emailed); anyone else gets an invitation
  (`listing_invites`, main database; `app/migrations/2026-12-25_listing_invites.sql`, also made on first use) and joins
  when they sign up or sign in with that email, confirmed (`Team::claimInvites`, run as their businesses are read).
- **Change a role** from the list; **Remove**; anyone can **Leave**. Pending invitations show with **Cancel**.
- **Rules** (`Team`): the Owner and Managers manage the team; only the Owner gives or changes the Manager role; the
  Owner can't be removed or demoted here (ownership moves only through Traversence: admin → Listings → Owner & team);
  a staff login can't be added. Owner and Manager are mirrored into `listing_access`, which the listing editor reads.
  Every change is in the action log.

## Cleanups

- The footer's "Own a business? Claim or create your listing" call-out no longer shows to anyone who runs a business,
  or to staff logins.
- The Overview's listing summary is labelled "Your listing at a glance".
- `/admin/staff.php`: the Add-a-staff-login role picker no longer pushes the page sideways at 390px.
- System Configuration → Data separation → Business walls checks that **every** business table (now 9) has its tenant
  view and its insert, update and delete guards.

## Terms and FAQ

- Terms `#business-quotes`: between the business and its customer, not us; payments go to the business; approving is
  agreeing to the quote; the business keeps its prices and taxes right and uses customers' details only for the work.
- FAQ: sending a quote or invoice; tracking orders; a customer asking whether a business's quote is safe; adding people
  to a business's team; the business-portal entry updated.

## Not yet

- Proofs and files on a quote (ours have them; a business's will use its storage allowance).
- Business-wide settings for tax rates, pay links and terms (Taxes & shipping in the portal), and card checkout.
- Ownership transfer by the owner, with the new owner's consent.
