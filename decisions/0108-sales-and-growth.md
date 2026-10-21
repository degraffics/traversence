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

- Renewals: A or B, after the sandbox test.
- Our cost per plan, for the 65% floor.
