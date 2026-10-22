# 0108: Sales & Growth: the merchant funnel, AI Assist credits, OCR, promotions, plans and onboarding

Status: Accepted (2026-10-21) by Jason. Wave 3, workspace 7. Billing and White Glove are pulled forward from waves 4
and 6, as the minimum needed to make sales real.

## Purpose

The CRM turns unclaimed directory listings into paying subscribers or managed (White Glove) clients:

1. **Source:** the crawler, imports and state data supply unclaimed listings, and the card scanner adds businesses met
   in person.
2. **Score:** each lead is scored, with the reasons shown.
3. **Reach out:** claim invites by email, SMS or a GO Code; calls are logged.
4. **Convert:** the owner verifies the listing, chooses a model and a tier, and puts a card on file.
5. **Onboard:**
   - self-service (§7), or
   - White Glove (§8), with an onboarding board and the Asset Digitizing Studio.

The consumer funnel (Post-and-Connect quotes, merchant gateways) stays in Wave 5 (Market, ADR 0031).

## Decisions

### Payments and plans

- **QuickBooks is our only processor** for our own sales: plans, services, GO Codes and client orders. Stripe and
  Square come later, for merchants (Wave 5).
- **The subscription lives on our platform:** plan, trial, promo and access.
- **Renewals:** to be settled.
  - **A:** QuickBooks recurring schedules with autopay, which we verify.
  - **B:** our daily scheduled task charges the saved card through QuickBooks Payments and writes the Sales Receipt
    into QuickBooks.
  - Test A in the sandbox first; fall back to B.
- **Trials (30, 60 or 90 days, no commitment):**
  - Automatic notices are sent 7 days before, 1 day before, and on the day it ends, inviting the owner to subscribe.
  - With no subscription, the account drops to the **Free plan** when the trial ends. The listing stays up.
- **Profit floor:** no discount, promo or combination of them may take a sale below **65% profit**. This replaces the
  AOS's 45%-off cap. Plans need a cost figure; until one is set, a cost percentage in Settings stands in for it.

### AI Assist credits

- **One credit system** for every AI-assisted action: OCR scans, photo → product, rewrites and drafts.
- **Monthly allowance by tier:**

  | Tier | Credits a month |
  |---|---|
  | Free | 0 (no AI on Free) |
  | Basic | 10 |
  | Core | 50 |
  | Strategic | 250 |
  | Cornerstone | 500 |

- **Overage:** à la carte (per credit) and packs, in the cart, within the profit floor.
- **Credits per action:**

  | Action | Credits |
  |---|---|
  | Menu or price-list page | 1 |
  | Photo → product (name, description, suggested category, options, tags; never the price) | 1 |
  | Rewrite a description | 1 |
  | Post (short update on the listing) | 2 |
  | Offer (dated special, can carry a GO Code) | 2 |
  | Article (long-form, Strategic and up) | 10 |
  | Journey (a contributor's trip story; only accounts that have credits) | 10 |

- **Not charged to the client:**
  - Guides (our editorial, 0043);
  - staff use: the card scanner, Sales and White Glove setup.
- **Nothing AI-made publishes by itself:** every result is a draft that a person approves.

### OCR (three providers, each switched on by its key)

- **Providers:** Google Document AI, AWS Textract and Claude, each switched on by its key in Settings → Integrations.
  Each kind of job has a first choice and a fallback.
- **Storage:** files stay in private storage outside the web root and are sent inside the API request (no public
  links).
- **Queue:** a job table, plus a scheduled task every minute that calls the provider (it returns in seconds per page)
  and saves the result to staging. No webhooks, no long-running processes.
- **Asset Digitizing Studio:** the original sits beside the rows read from it, low-confidence rows are marked, and
  **Approve and publish** commits them.
- **Limits:** pages and size per upload, a daily cap per business, and a monthly cost ceiling that pauses the queue and
  raises a Pulse alert.

### Business card scanner (built first)

- The rep photographs a card (front and back, several in a row) and the card is read into a **draft lead**.
- Before saving, it is matched against existing listings and leads by phone, website, email, or name plus town:
  - already listed: the card attaches to the listing;
  - new: a draft listing is created (the Single Home Rule sets its home).
- The card photo, who scanned it, when and where are kept. The lead goes to the rep and the territory, at the
  stage **Met in person**.
- Cards scanned without a signal are held on the phone and sent when it's back online.
- Next steps from the result: send a claim invite, text a GO Code, or book a follow-up.

### The Single Lead Workspace

A side panel, like the Pulse case and the item panel; full screen on a phone. It holds the listing, score, owner and
territory, every call and invite, quotes and stage, and links to a full page.

### Stages

Unclaimed → Contacted / Met in person → Meeting → Claimed (verified) → Quote sent → **Won** or **Lost**, plus Nurture.

### Territories

By state, Geo-Hub Zone or cluster, set up by Directors. Inbound claims and new leads go to the territory's rep, or to a
shared queue when no rep covers it. Commission comes later.

### Promotions

- **Who creates them:** Managers and Directors.
- **Fields:** start date, end date, the exact offer, and terms.
- **Activate:** issue a code, choose who it's for, set the number of codes allowed.
- **Who can use it:**
  - **General** (the default): anyone with the code;
  - **Assigned:** one user or listing, and only they can use it.
- **Where it works:** the cart and quotes accept codes.
- **First promos:**
  - 30, 60 or 90 days of plan access with no commitment;
  - 25% off creative and additional services. Products get a service-type flag: Creative, Additional service, Print,
    GO Code.
- **GO Codes:** any promo can be issued as a GO Code (link plus QR) with the code already applied. One code per
  business on a mail-out ties a scan to its lead: the lead moves to Engaged and the rep is told. Scan counts are the
  campaign's results.

### Onboarding

- **Self-service (§7):** after a claim, the owner verifies, chooses a model (Nexus, Co-Op/Chamber, Decentralized) and a
  tier, and puts a card on file through QuickBooks. Merchants' own payment gateways come in Wave 5.
- **White Glove (§8):**
  - A won deal that includes White Glove opens a board.
  - Stages: Kickoff → Assets collected → Build → Client review → Launch → 30-day check-in.
  - The board covers the contract, photography, copywriting, digitizing (the Studio) and the domain.
  - The client gets an **asset upload GO Code**: they scan it, photograph their menu and storefront, and the photos land
    on the board.
- **Contracts:** shown inline and accepted with one tap. We record name, email, time, IP address and the agreement's
  version. No e-signature for now.

### Consent by GO Code

- A GO Code can open one specific consent: texting, a photo release, service terms, data use, or a nation's
  permission.
- **Scanning isn't consent; tapping I agree is.**
- We record who agreed (signed in, or confirmed by an email or text code), what they agreed to (wording and version),
  when, and from where. Withdrawing is always one tap, or replying STOP.
- Texting consent also confirms the number by a "Reply YES" text.
- This brings part of the consent hub forward from Wave 4.

### Messaging (each channel switched on by its key)

- **Email:** working now.
- **SMS:** a provider plugs in (Twilio first).
  - The system records each person's consent to texts and handles STOP and HELP replies, with quiet hours and limits
    per person.
  - The 10DLC registration's status is shown.
- **Push:** web push uses our own keys, so it needs no outside account. App push comes with an app.
- **Claim invites can be sent automatically by rules,** at most once per business every 30 days, with an unsubscribe.

### State data sources

- **The page:** pick a state to see what it offers (an API, a bulk download, a paid file, or a web search only, which
  we don't scrape), its cost and sign-up link, and the fields it gives.
- **Connect** walks through: credentials → test → field mapping → schedule → dry run → intake.
- The pilot states (AZ, NM, UT, CO) are researched first.

## Build order

1. OCR system and the business card scanner
2. Lead workspace, stages, scoring and territories
3. Plans, subscriptions, trials and renewals through QuickBooks; AI Assist credits
4. Promotions and codes in the cart
5. Quotes → Won → billing and onboarding
6. White Glove board and the Asset Digitizing Studio
7. Messaging (SMS and push) and automatic claim invites
8. State data sources

## Open

- Renewals: decided B (the card on file, charged by us; Master AOS v44). Saving a card (`createFromToken`) still needs its sandbox test.
- Our cost per plan, for the 65% floor.

## Built: the business card scanner (2026-10-21)

- **Where:** Sales → **Scan cards** (the first tab).
  - **Scan a card** opens the camera; **Add the back** takes a second photo; **Read it** sends them.
  - Photos are made smaller on the phone (1600 px JPEG), then on the server turned the right way up and stripped of
    camera metadata.
- **Reading:**
  - Claude Haiku (`claude-haiku-5-5`, effort low, JSON-schema output) reads the card while the upload request is still
    open, so it's back in seconds.
  - `app/scripts/ai_worker.php` (cron every minute) catches anything left: a busy moment is retried up to 3 times.
  - A card scan costs well under a cent.
  - `AI_CARD_MODEL` overrides the model.
- **Checking:** the side panel holds the photos, every field (business, name, title, phone, mobile, email, website,
  street, town, state, ZIP, social, what they do, anything else), and a **suggested category** from what the card says.
  It also lists the listings it may already be (same phone, website, or similar name in town), each with
  **It's this one**.
- **Saving:**
  - The card joins the existing listing, or creates a new unclaimed listing (the crawler fills in the rest).
  - The lead goes to the rep (source `card`) with the visit logged: who they met, the person's mobile, email and social
    in the sales notes (never public), what happened, and the follow-up.
  - Afterwards: email the claim invite, copy the claim link, see or edit the listing.
- **No signal:** the scan waits on the phone (IndexedDB) and is sent when the signal is back, once.
- **No key, or over the monthly cap:** the card is kept, with **Fill it in by hand** and **Try again**.
- **Settings:** an **AI services** section shows each provider (Connected …last 4, or Not connected), this month's spend
  against `AI_MONTHLY_CAP_USD`, and whether the worker is keeping up.
- **Privacy:** card photos are staff-only (`app/storage/cards`, served only through `/admin/api/cards.php`).
  Discarding a card deletes them. The privacy page says so.
- **Data:** `2026-12-20_card_scanner.sql` adds `ai_jobs` (the queue every AI job will use) and `sales_cards`, and adds
  `card` as a lead source.
- **Not yet:**
  - the Google and AWS readers (with the Studio);
  - SMS to text a GO Code (once SMS is on);
  - the "Met in person" stage (with the stage work, step 2).

## Built: step 2, the lead workspace (2026-10-21)

- **The lead panel (Single Lead Workspace):**
  - Tapping a lead's name in any Sales list opens it beside the list; on a phone it fills the screen.
  - `/admin/sales.php?lead=N#all` opens it directly, and **Copy a link to this lead** copies that link to send a teammate.
  - The panel holds:
    - the score and why;
    - contacts (call, email, website);
    - the listing's milestone (Claimed, Verified, Paying) and any claim;
    - the stage and the lead's owner, with its territory and how it came in;
    - **Log a contact** (each outcome sets the next follow-up);
    - the claim link and invite;
    - quotes (step 5);
    - business cards scanned for it;
    - history and notes.
- **Stages:** Not contacted → Contacted → **Met in person** → Interested → **Meeting set** → Claim invite sent →
  **Quote sent** → **Won**, plus Nurture (not now), Not interested, **Lost**, and Closed or wrong number.
  - Logging an outcome moves the stage, and a stage can also be set by hand.
  - A card scan logs **Met in person**.
  - Won, Lost, Not interested and Closed leave the call lists.
  - The Funnel shows the new stages.
- **Lead score, 0 to 100, with reasons:**

  | Factor | Points |
  |---|---|
  | Reach: views and taps in the last 30 days | up to 25 |
  | Contact: phone 10, email 8, website of its own 7 (social only 3) | up to 25 |
  | Profile: address, hours, description, photos | 5 each, up to 20 |
  | People: comments on its listing, or someone started a claim | up to 15 |
  | Near a hub: distance from the center of its cluster | up to 10 |
  | Warm: met, interested or a meeting set | +5 |

  - No outside reviews are stored, so "reviews" means comments on its listing here.
  - "Best to call first" sorts by the score, which shows as a badge on every row (hover or tap it for the reasons).
  - `app/scripts/sales_tick.php` (hourly cron) scores each listing once a day; a lead is also re-scored when opened.
- **Territories** (Sales → Territories):
  - A territory is a state, a Geo-Hub Zone or a cluster, with a rep and a manager. Directors, Executives and the Founder
    set them up.
  - The most specific wins: a cluster beats its zone, and a zone beats its state.
  - Saving one gives its unowned leads to the rep.
  - New leads, inbound claims (an owner claiming the listing) and the hourly task route there.
  - With no rep, a lead waits in the **shared queue**.
  - Managers and up move a lead between reps, or back to the queue, from its panel.
- **Whose filter:** All prospects has Everyone's, Mine, and Shared queue.
- **Data:** `2026-12-21_leads.sql`:
  - adds the stages and `prospects.territory_id`;
  - adds `lead_scores` and `sales_territories`.

## Storage: lower personal space, add-on packs, annual discount on plans only (2026-10-22)

Jason's decisions (Master AOS v44, §5):
- **Personal plans:** free 2 GB; $4.95 with 25 GB; $9.95 with 50 GB.
- **Storage packs,** the same for personal accounts and listings, priced at the 65% floor on $0.04 per GB-month:
  +25 GB $2.95 · +50 GB $5.95 · +100 GB $11.95 · +250 GB $28.95 · +1 TB $114.95 · +5 TB $574.95, monthly.
- **These replace the older commercial packs in AOS §13B** (+500 GB $24.95, +1 TB $49.95), which fell below the
  floor. Every pack price is editable in the plan configurator.
- **The annual discount applies to subscriptions of service only.** Add-ons (storage and AI credit packs) are full
  price, billed on the plan's schedule: one renewal date, one charge.
- **Business allowances:** Free 2 GB, Basic 10 GB, Core 25 GB, Strategic 100 GB, Cornerstone 250 GB (confirmed with "go").
- **Renewals:** option B: our daily task charges the saved card through QuickBooks Payments and writes the Sales Receipt
  into QuickBooks (confirmed with "go"). Plan costs: a cost percentage in Settings until real figures are set.
- **To build with the S3 step:**
  - metering per owner;
  - limits at upload, with an upgrade or pack offer;
  - WebP on upload;
  - usage shown to owners and to staff.

## Built: step 3, plans and renewals (2026-10-22)

- **Plans** (`app/lib/Billing.php`), prices from Master AOS v44:
  - Listings: Free, Basic Partner $29.95, Core Partner $79.95, Strategic Partner $229.95, Cornerstone $399.95 a month.
  - People: Personal (free), Personal Plus $4.95, Personal Pro $9.95.
  - Each plan's storage and AI Assist credits: 2/10/25/100/250 GB and 0/10/50/250/500 credits; personal 2/25/50 GB and 0/10/25.
  - Yearly is 10 months' price for 12, on the plan only. Packs (storage +25 GB to +5 TB, +50 AI credits $29.95) are full
    price and billed on the plan's schedule: one renewal date, one charge.
- **Paying (option B):** the card goes from the browser to Intuit; the token is saved as a card on file in QuickBooks
  Payments (the wallet `TRV-L<listing>` or `TRV-U<user>`). We charge it at each renewal with a key per charge, so nothing
  is charged twice, and each charge is a Sales Receipt in QuickBooks and a receipt email.
- **Changes:** a bigger plan starts at once and the difference for the rest of the period is charged then; a smaller one
  (or Free) starts at the renewal. A pack added mid-period is charged for the rest of it; **Remove at renewal** takes it off then.
- **Trials:** 30, 60 or 90 days, no card. Reminders 7 days and 1 day before, and on the day. A trial ends on Free unless
  the owner keeps it (adding a card turns the same subscription paid from that day). A paid plan chosen when claiming a
  business now starts a 30-day trial (`Claims::activate`), and its listing stays noindex while on trial (0034).
- **Declines:** a declined renewal is tried again after 3 and 7 days (the plan is *past due* meanwhile, and a new card is
  charged at once); then the plan ends on Free and the listing stays up.
- **Stopping:** **Stop at the end of this period**; nothing is refunded for a period already started (Terms → Plans and
  automatic renewal).
- **Who manages a plan:** the listing's owner, or a team member with *Plan & billing* at Edit (0104); a person their own.
- **Pages:**
  - `/plans.php` for owners and members: the list of their plans; `?listing=N` or `?me=1` for one. Linked from the business
    dashboard (**Manage plan**) and the member Dashboard. API `/api/plans.php`.
  - **E-Commerce → Plans** for our team: monthly revenue (yearly counted by the month), trials, declines, every plan;
    **Start a trial**, **Stop at renewal**, **End now**; the daily run's last result.
- **Daily run:** `app/scripts/billing_tick.php` (cPanel cron, daily 6:13 am) renews, retries, ends and sends reminders.
- **Migration** `2026-12-22_billing.sql`: the Stripe-era `subscriptions` table (only claim trials used it) is kept as
  `subscriptions_old`; its open trials became 30-day trials from the day it runs.
- **Not yet:** AI Assist credit metering (the `ai_credit_usage` table is ready; credits are counted with the AI tools that
  spend them), storage metering against the allowance (S3), and promo codes on plans (step 4).


## Built: step 4, promotions and codes (2026-10-22)

- **Promotions** (`app/lib/Promotions.php`), made by Managers and Directors in **Sales → Promotions**:
  - The offer: a **free trial** of a plan (30, 60 or 90 days, no card), or a **percentage off** plans, creative,
    additional services, print or GO Codes. On plans it applies to the first 1, 3, 6 or 12 payments, or every one.
  - Dates, a limit on uses, terms, and who may use it: **anyone with the code** (general) or **one listing or member**
    (assigned). Each account uses a code once. Pause, resume or end it.
- **The 65% profit floor** is enforced:
  - Plans: up to 42% off, from `PLAN_COST_PCT` in `.env` (default 20%) until real costs are set.
  - GO Codes: up to 71% off, from `GO_CODE_COST_PCT` (default 10%).
  - Products: each line is capped by its own cost. A line with no cost entered isn't checked, so costs must be entered.
  - Packs are never discounted.
- **Where codes work:**
  - **Plans page:** a trial code starts its trial at once (it replaces a trial already running, never a paid plan); a
    percentage comes off the plan line of the first charge and of its renewals, then full price.
  - **GO Codes cart:** taken off each code's price at checkout (the invoice shows the lower prices); a cancelled unpaid
    order gives the code back.
  - **Orders we prepare (quotes):** our team or the client adds the code before approval; it comes off the eligible
    lines' prices when the client approves. Custom lines get a kind (Creative, Additional service, Print) on the
    builder; catalog products get one on the product form.
- **As a GO Code:** **Issue as GO Code** makes a trvgo.us link and QR to the offer's page (`/promo.php?c=CODE`), which
  says what it is and where to use it, with the code ready.
- **Mail-outs, one code per lead:** **Codes for leads** makes an assigned copy of a promotion, with its own GO Code, for
  each listing. A CSV (business, code, link, QR files, scans, used) feeds the mail merge.
  - The first scan moves the lead to **Interested** (the stage list has no "Engaged"; Interested is the nearest) and
    emails its rep.
  - Scans and uses show on the campaign's row.
- **Migration** `2026-12-23_promotions.sql`: `promotions`, `promotion_uses`; `products.service_type`;
  `order_lines.service_type`, `order_lines.promo_off_cents`; `orders.promo_id`; and on subscriptions `promo_id`,
  `promo_percent`, `promo_left`.
