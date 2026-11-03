# ADR 0142: Terms, Store Policies and the Acceptance Log

**Status:** Accepted (Jason, 2026-11-03). Phase B of 0141. The wording below is a first draft, to be shaped as
templates and reviewed by Traversence's attorney before it publishes; the structure is decided.

## 1. Every legal text is a template

Legal texts are made and kept the way content is (0133 templates, `ContentTemplates`): each is a **template** with
sections, edited in System Configuration → Content templates, with AI Assist to draft or reword a section (its AI cost
inside the plan's allowance; CLAUDE.md, no income no expense). **Every save keeps a version**; publishing one gives it a
version label (`v2026.1`) and an effective date.

| Template | Owned and edited by | Filled from | Accepted by, when |
|---|---|---|---|
| **Platform Terms** (`/terms.php`) | Traversence | Traversence's own business identity | Everyone: signing up, and again when a changed version matters |
| **Privacy Policy** (`/privacy.php`) | Traversence | Traversence's identity | Shown with the Terms |
| **Seller Agreement** | Traversence | Traversence's identity and the business's | The business, before its shop goes live; again on a new version |
| **Store policies** | The business, from Traversence's master | The business's identity (0141 §3) and its choices (returns, fulfillment, cancellations, deposits, house rules); each item kind brings defaults (0141 §5) | The buyer, at checkout |
| **Checkout disclosures** | Traversence's master, per item kind | The order and the store policies | The buyer, at checkout |

- **Placeholders fill from identity, never by hand.** A business's templates take its legal name, DBA, address,
  contact and state from its Business Identification (0141 §3). Traversence's own take Traversence's identity, set in
  System Configuration: today **Traversence.com, PO Box 3570, Saint Johns, AZ 85936, support@traversence.com**
  (interim, decisions/0019 and 0101), governed by **Arizona** law, disputes in **Apache County**. The legal entity's
  formal name goes there when Jason sets it; until then the Terms say "Traversence.com".
- **Locked clauses.** A business edits its store policies' wording and its house rules, but the clauses the Marketplace
  depends on (the seller is the business; Traversence isn't a party to the sale; disputes go to the business first)
  are locked in its template, shown but not editable.
- **A changed version** asks again only when it matters (the editor marks a version "needs acceptance"); a reworded
  one just takes effect on its date, as the Terms' "Changes" section says today.

## 2. The acceptance log

One record per acceptance, only ever added, never edited or deleted (`terms_acceptances`, MySQL; a migration, guarded):

| Field | What |
|---|---|
| `id` | Auto number |
| `kind` | `account_terms`, `seller_agreement`, `checkout`, `house_rules`, `store_policy_published` |
| `user_id` | Who accepted (a buyer, or the business person) |
| `entity_id` | The business, when there is one |
| `order_id` | The order, for a checkout |
| `doc` / `version` | Which template and which version label |
| `text_hash` | SHA-256 of the exact text shown, so a later edit can't be mistaken for what was accepted |
| `snapshot` | JSON: the policy values shown (returns window, fulfillment, deposits, house rules) |
| `ip`, `user_agent` | Where it came from |
| `accepted_at` | When |

- **Each order also keeps its own snapshot** of the store policies, so the business's later changes never rewrite an
  old order.
- **Chargeback packet** (0141 §6): an order, its receipt, and its acceptance record, as one file the business sends
  its gateway.
- **Privacy:** the Privacy Policy says these records exist, what they hold and why. Kept **7 years**. When an account
  is deleted, its acceptance records stay (they're the legal record) but the person's name and email are removed
  from them; the IP and browser are kept with the record.
- The sign-up line ("By creating an account you agree…") starts writing `account_terms` records.

## 3. The Marketplace section of the Platform Terms (draft, template v1)

> **Buying from businesses on the Marketplace**
>
> - **Who you're buying from.** Every item on the Marketplace is sold by the business that lists it, not by
>   Traversence. Your order is a contract between you and that business, under its store policies, which you see and
>   agree to before you pay. Traversence isn't a party to the sale.
> - **How you pay.** Card payments go straight to the business's own payment account (Stripe or Square), and the
>   business's name appears on your card statement. Direct payments (Zelle, bank transfer, check, cash on pickup) go
>   straight to the business, as its instructions say. Traversence never holds your money and takes no fee from the
>   sale.
> - **More than one business.** A cart with items from several businesses is paid business by business. Each one
>   sends its own receipt; we also send you a summary of the orders together.
> - **Returns, refunds and problems.** Ask the business first: its store policies set returns, cancellations,
>   deposits and pickup or delivery. If you can't resolve it, tell us (Report on the order) and we'll look at whether
>   the business is keeping to its policies and these terms. A dispute with your card company goes to the business,
>   which answers it.
> - **What the business promises.** Businesses agree to sell lawfully, describe items accurately, honor their posted
>   policies, and keep to our Seller Agreement and our list of items that can't be sold here. We may pause a
>   business's shop or remove an item that doesn't.
> - **Taxes.** Each business charges and pays its own sales tax. If you buy tax-exempt, the certificate you give
>   that business is between you and it.
> - **Your details.** Buying from a business makes you its customer: it receives what it needs to fill your order
>   (name, contact, address for delivery) and keeps it as its own record. It may send you marketing only if you agree
>   at checkout, and you can stop that any time. Linking a business is different: see Linking in the FAQ.

The **"Other companies' services"** section adds **Stripe** and **Square** (businesses' own payment processors, with
their own terms), and **Stripe Identity** (the owner ID check, 0141 §3).

## 4. What can be sold: the Marketplace rulebook (draft)

Built like the crawler's rules (0133): written rules, applied the same way to everyone, checked when an item is
published, never a silent removal (the business sees the reason), and the takedown path (0133) for reports. Our list is
**at least as strict as Stripe's and Square's own** restricted lists, since breaking theirs closes the business's
payment account; each gateway's own list still applies on top.

**Three tiers:**

| Tier | Meaning | Examples |
|---|---|---|
| **Prohibited** | Never listed | Anything illegal where it's sold or sent; weapons, firearms, ammunition, explosives and their parts; illegal drugs and drug paraphernalia; prescription medicines; counterfeit or infringing goods; stolen goods; recalled products; hazardous materials; adult sexual content and services; gambling, lotteries and raffles; securities, crypto and investment schemes; multi-level marketing; government IDs and documents; human remains; endangered species and their parts; items that promote hate or violence; anything a gateway forbids outright |
| **Restricted** | Allowed only when the business confirms its license and the item meets the conditions; card checkout only where its gateway allows it, otherwise direct payment or quote only | Alcohol (state license, no shipping where not allowed, 21+ at pickup); tobacco and vape products (license, 21+); CBD and hemp (lab-tested, legal content); cottage food and homemade food (Arizona cottage food rules, its notice shown); fresh meat, eggs and dairy (licensed producer); live animals and plants (state rules); lodging (local lodging tax and rules); event tickets resold above face value (where the law allows) |
| **Allowed with a notice** | Fine, with a disclosure shown | Perishables (freshness notice); used or as-is goods (condition shown); made-to-order (no returns once made, unless the policy says otherwise); digital goods (no refunds after download, unless the policy says otherwise) |

- **How it's checked:** the item's kind and category, and words in its title and description, against the rulebook
  when it's published. A Prohibited match isn't published, with the reason; a Restricted one asks the business to
  confirm its license and conditions (an acceptance record, §2) before it goes up; anything unclear goes to the
  admin's Review.
- **Reports:** anyone can report an item (the takedown path, 0133); a reported item stays up while it's looked at,
  unless it's clearly Prohibited.
- **Enforcement:** the standing ladder (0079) for repeats; a shop is paused, never its listing or map pin (0141
  §2.3).

## 5. Build (Phase B)

1. The legal templates in Content templates (a `legal` family), seeded with today's Terms and Privacy and the drafts
   above; Traversence's identity in System Configuration.
2. `terms_acceptances` and the sign-up record.
3. Store policies: the business's choices, its generated policy box on items, storefront, checkout and receipts.
4. The checkout line and per-kind disclosures; the order's policy snapshot.
5. The Seller Agreement before Go live; the Restricted confirmations; the rulebook check at publish.
6. The chargeback packet.

## Built: zip 2, Phase B core (2026-11-03, built on the 16:50 UTC sync)

- **The acceptance log** `terms_acceptances` exactly as §2 (migration `2027-02-02_terms_acceptances.sql`, which also
  adds `market_checkout.policies` and `market_orders.policy_json`). `Acceptance::record()` stores the SHA-256 of the
  exact text shown, the IP and browser; the class has no update or delete. Records hold a user id, never a name or email.
- **Legal templates** (`LegalTexts`, the `legal` family of content templates): Platform Terms and Privacy Policy
  (today's pages as sections, plus §3's Marketplace section, Stripe, Square and Stripe Identity under other companies,
  and a Privacy section "Records of what you agreed to": 7 years, kept without name and email after deletion), a
  Seller Agreement, the store policies' master clauses and the checkout line. Seeded once from `app/lib/legal-seed.php`
  as **drafts**, edited at System Configuration → Content templates → Legal texts (`app/admin/legal-editor.php`):
  sections in a small Markdown, every save a version, Publish with a label (`v2026.1`), an effective date and "needs
  acceptance"; going back to a version restores its sections and never the publish record. `/terms.php` and
  `/privacy.php` show the version in effect; until one is published they show today's text.
- **Identity:** `LEGAL_NAME`, `LEGAL_ADDRESS`, `LEGAL_EMAIL`, `LEGAL_STATE`, `LEGAL_COUNTY` in System Configuration →
  Settings, defaulting to Traversence.com, PO Box 3570, Saint Johns, AZ 85936, support@traversence.com, Arizona, Apache
  County.
- **Sign-up** (password, Google and the other social sign-ins) writes `account_terms` with the Terms version shown
  ('site' while today's page is shown) and the Privacy Policy's in its snapshot.
- **Store policies** at Market 360° → Money & Processing → Store policies (`app/business/policies.php`,
  `StorePolicies`): returns (final, 7, 14, 30 days, exchanges), cancellations, deposits, house rules. Saving publishes
  them and writes `store_policy_published`. The box (the master's locked clauses first, then the choices) shows on items,
  the storefront, checkout, the order page and the receipt email; E-commerce setup's Go live says whether they're set.
- **Checkout:** the line above Place order (the checkout template); placing an order, and the setup's test purchase,
  writes a `checkout` record (version `<line>|<terms>|s<store version>`) and keeps the policies on the order, so a later
  change never rewrites it.
- **Not yet:** asking members to accept a "needs acceptance" version again (the flag is kept); AI Assist on legal
  sections; kind-specific defaults (Phase C).
