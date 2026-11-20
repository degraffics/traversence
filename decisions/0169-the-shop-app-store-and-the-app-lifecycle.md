# 0169: The Shop's App store, and one lifecycle for every app

Status: accepted (Jason, 2026-11-16: "perfect", with the order of the first apps). §1a, Domains in the Shop and the
Domains tab, built 2026-11-17 (releases #71–#77, #81–#83, #86; `handoffs/domains.md`); sales stay off until Domain sales
is set. The App store and the lifecycle (§1–§6) not built; first in the queue in `handoffs/session-manager.md`.
Builds on: 0035/0037 (a person is their own account; a business is a listing they manage), 0098 (the order system),
0108 (plans), 0135 (businesses' own Stripe and Square), 0152 (Outside services), 0154 §6 (the Shop and one cart), 0155
(add-ons and 1Password), 0166 (payments and bookkeeping), 0168 (Facebook and Instagram).
Replaces: 0155 §1 (its add-on pattern becomes this lifecycle; the rest of 0155 stands). Changes how 0166's connectors,
0135's gateways and 0168's connections are built: each as an app.

## The idea

Jason, 2026-11-16, while tracing Stripe's keys: "I see this as a market for apps that integrate into our platform, so
when a subscription begins they have apps they can integrate. For ecommerce, payments, they can select the app that they
want and its integration into our system is the start to finish onboarding for that specific app and it covers
everything from setup - configuration - and use - removal." Then: event tickets and appointment booking "could fall
under this apps add-ons too"; "they dont have to be businesses per say. users can have apps specific like gallery and
storage"; "the shop under our traversence cart can become this apps market as well"; "the shop will have departments or
categories, one being the app store."

Each integration so far was designing its own connect, set up, use and remove: 0135's gateways, 0155's add-ons, 0166's
connectors, 0168's Page connection. This makes them one thing.

## Decision

### 1. The Shop has departments; one is the App store

- The Shop (0154 §6) is where Traversence sells its own things, with **one cart and one checkout** for every department.
  Departments, to be settled when the Shop is laid out, such as: Plans, **App store**, Print and custom work, GO Codes,
  Services (Market 360, White Glove), Gift certificates.
- Each department shows its items its own way (apps: what's in your plan and what the app can access; print: proofs and
  options).
- The Shop shows each account what fits who it is (a member, a business), never what that account can't use.

### 1a. Domains: a Shop department, and a Domains tab on the account (Jason, 2026-11-17)

Jason: "I would like a domains category on the shop page when I can search for domains and purchase/register them. The
users would get a domains tab where they can manage them. … we can offer these as standalone products and then
integrate the domain buy process into the storefront process."
- **Shop → Domains:** search a name, see it and the usual endings with our price a year (Name.com's cost plus
  `DOMAIN_MARKUP_CENTS`, premium names referred to us), add to the one cart, pay first, then it's registered through
  Name.com in the buyer's name with WHOIS privacy. A standalone product: no storefront and no particular plan needed.
  Any account can buy, member or business; the registrant must be 18 or older (0058) and give real contact details
  (ICANN's rule; Name.com emails a confirmation).
- **The account's Domains tab** (members' account pages and the business portal): every domain the account holds, its
  expiry, **renew now**, **auto-renew** on or off (charged first, two weeks ahead, as 0139 does today), **where it
  points** (its storefront; forward to another address; or its own DNS records, edited there through Name.com's API;
  or nameservers set elsewhere), **email records** when SES sends for it, **transfer away** (unlock and the code shown
  once), and its history. A domain brought from elsewhere can be listed too, for pointing only.
- **The Storefront app uses the same domains:** its address step offers "one of your domains", "buy one" (the same
  Shop search, inside the step) or "bring one", so a domain bought on its own can later become a storefront's address,
  and one bought for a storefront lives in the Domains tab like any other.
- **Today's code** (`Domains`, 0139 5b) ties one domain to one storefront and charges the plan's card. This separates
  them: a domain belongs to the account; a storefront *uses* one. Existing storefront domains move across with nothing
  lost.
- **Two products** (Jason, 2026-11-17: "Domain Purchase, domain renewal. Two products"): **Domain Purchase** and
  **Domain Renewal**, each a catalog product with its own QuickBooks link, price set per sale; every order line carries
  Name.com's actual cost with Name.com as the vendor, for 0166's ledger (income and cost accounts chosen in 0166's
  setup step, not in code).
- "No test moves real money" and "no income, no expense" hold: charged before Name.com is paid, test accounts and
  test mode always on Name.com's sandbox.
- **Built (release `domains-wallet`, 2026-11-17; handoffs/domains.md):** Shop → Domains (`/shop/domains.php`) and the
  Domains tab (`/user/domains/`, Dashboard → Domains, the portal's Domains), `app/lib/AccountDomains.php` with its own
  table `account_domains`; the storefront's Connect your domain and `business_domains` are unchanged (Jason, 2026-11-17:
  "Shop for domains. Not the storefront app, that goes into the queue"); a business's storefront domain shows on its
  tab read only. Two catalog products, **Domain Purchase** and **Domain Renewal** (Jason: "Two products"), each line with
  Name.com as vendor and its actual cost. Paid from the account's wallet (0166 §6a). What goes wrong is handled on the
  order, with a Pulse case (Try again, Refund, Email the owner), and Something isn't right opens a support ticket (Jason:
  "Order processing should address situation like failed renewals and new purchases. And support tickets for
  issues."); no separate admin list of accounts' domains.
- **Note, 2026-11-17 (Jason: "I did say domains where to be separated from storefronts right. Finding and buying was to
  be its own process."):** the build had opened domain sales on `DOMAINS_MODE`, the storefront's "Own domains on
  storefronts" setting. Domain sales now have their own switch, `DOMAIN_SALES_MODE` ("Domain sales": off, test or live;
  unset is off), on the Name.com card beside `DOMAINS_MODE` and `NAMECOM_ENV`. It alone governs Shop → Domains, the
  Domains tab's buying, renewals and auto-renew, and the wallet's domain charges; `DOMAINS_MODE` is the storefront's
  Connect your domain only (0139). The sandbox rules hold on the new switch (test mode, test accounts, the staging copy;
  live refused while `NAMECOM_ENV` is sandbox), and a sale's Name.com calls follow only the sales switch
  (`Domains::forSales`). Release `domain-sales-switch`.
- **Note (2026-11-17, releases #71–#86):** built as #71 (#70 never went live), the sales switch #72. Since: Test lets
  the Founder in (#74); a test searches Name.com live, read only, and buys in the sandbox (#75; Jason: "Live prices and
  testing purchase sounds like a much better UI and UX for me"); Name.com's Core API, 1 to 10 years at its real total,
  only the terms it sells, the cost a year shown (#76, #77, #81); the registrant's confirmation followed until done,
  Critical in Pulse at 3 days left (#81); the prices our account pays on the Name.com card (#82); .app and .ai (#83);
  "available", not "free" (#86).

### 2. Apps belong to accounts, members and businesses alike

Every account is its own entity, so apps are for **accounts**:
- **Members:** e.g. Gallery and storage (more storage, imports, backups), Facebook personal, calendars, outdoor tracks,
  1Password.
- **Businesses:** e.g. payments, bookkeeping, Facebook business, Tickets, Appointments, the Storefront, shipping.
- Each app says which kinds of account it's for.
- Traversence's own running services (QuickBooks for our books, SES, Cloudflare…) stay in Outside services (0152) and
  Communications (0167); they aren't apps a person adds.

### 3. Two kinds of app

- **Our own apps:** features built into Traversence, turned on per account (Tickets, Appointments and bookings,
  Subscriptions and memberships, Quotes and invoices, the Storefront, Gallery storage…).
- **Connected apps:** an outside service linked to the account's own account there (Stripe, Square, QuickBooks,
  Facebook, calendars…), always by signing in and approving, **never by pasting a key**.
- **Only our own and those we build and vet,** for now. The design leaves room for outside developers later.

### 4. Getting an app

- **In your plan:** shown "In your plan", with **Set up**.
- **Free:** **Get** installs it at once, no cart.
- **Paid:** **Add to cart**, charged first through the order system (monthly or yearly), so it never costs us before it's
  paid ("no income, no expense"); setup starts by itself once paid.
- **Not in your plan:** "Upgrade to get it", or add it alone where it's sold alone.
- **When a subscription begins,** onboarding suggests the apps for that plan and kind of account.

### 5. One lifecycle for every app

1. **Choose:** what it does, which plans include it, its cost (the service's and ours), what it can see and do in your
   account; a **"Which should I use?"** helper where there's a choice (Stripe or Square); **"Don't have an account?
   Here's what you'll need"** (business details, a bank account, ID, days to the first payout).
2. **Install:** sign in and approve, with every permission listed.
3. **Configure:** the app's own guided steps (Stripe: payouts and a test purchase; QuickBooks: A or B and the chart of
   accounts, 0166 §4; Facebook: which Page and which posts, 0168).
4. **Use:** where it shows up in Traversence, a status card, health checks; a problem becomes a Pulse case with a fix
   button.
5. **Update:** new permissions or new terms are asked for again; nothing changes silently.
6. **Remove:** access revoked at the service's end; a plain statement of what happens to the data and what replaces it
   ("card checkout turns off; Zelle and pickup stay on"); refunds and payouts still owed keep working until they close;
   a paid app's subscription ends under the order system's rules.

After install, the app lives on the account's **Apps** page (status, settings, renewal, Remove). **The Shop is where you
find and buy; Apps is where you run them.**

### 6. Behind it

- Each app is **defined once**: kind (ours or connected), accounts it's for, category, plans, price, permissions, its
  setup steps, its checks, its webhooks, its removal. The next app means filling in that definition and its connector,
  not designing a new flow.
- Tokens are stored sealed in the database, per account, never in `.env` (only our platform keys are there).
- Staff see every installed app across accounts in the admin, with errors in Pulse.
- Every app's outside calls pass `StagingGate::hold()` (0165); test accounts never reach a live gateway (0145).

## Build order (each a release Jason reviews)

1. **The Shop with departments, the App store, and the app lifecycle** (§1–§6): the Apps page, the app definition, the
   staff view. Today's Stripe and Square connections (0135) keep working as they are until their app is built.
2. Then the first apps, in Jason's order:
   1. **QuickBooks Bookkeeper** (0166 steps 1–2: our ledger, the QuickBooks connector, the setup step);
   2. **QuickBooks Payments** (0166 step 3's payments layer, with QuickBooks Payments as its first processor);
   3. **Stripe Payments** (0135's connection moved in, with the choosing helper);
   4. **Facebook Personal** (0168 Phase 1);
   5. **Facebook Business** (0168 Phase 3 on).

   6. **Storefront** (decisions/0139 moved in; Jason, 2026-11-17): Configure walks through the shop name and look
      (`trvgo.us/<name>` works at once), then **your address**: keep `trvgo.us/<name>` (free), use a domain you own (the
      records shown and checked for you), or buy one through Name.com (search, price, buy, connected by itself); then
      pages, Preview, Go live. Remove says what happens: the storefront closes, a bought domain stays the business's
      (its transfer code shown), a brought domain's records can be pointed elsewhere.

   The others (Square, Tickets, Appointments, Gallery storage, calendars, 1Password…) are discussed next.

## Consequences

- One place to find, buy and run everything; plans read as a set of apps.
- Each new integration is smaller to build and the same to use.
- A release goes ahead of 0166, so payments and bookkeeping are built as apps from the start and nothing is rebuilt.

**Note (Jason, 2026-11-20, decisions/0175): the App store builds on 0175.** §5's lifecycle is folded into 0175's eight stages (Choose and Install → 1, Configure → 1–2, Use → 3–5, Update → 6, Remove → 7–8), with every detail here kept inside them. §6's app definition becomes 0175 §4's one definition, shared with our platform services. Release 1 (the App store) builds on 0175's releases, not on a model of its own.
