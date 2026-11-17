# 0169: The Shop's App store, and one lifecycle for every app

Status: accepted (Jason, 2026-11-16: "perfect", with the order of the first apps). Not built; first in the queue in
`handoffs/session-manager.md`.
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
