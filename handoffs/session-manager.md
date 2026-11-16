# Hand-off: the session manager

For the Claude session that manages Jason's other sessions (CLAUDE.md, "The session manager"). Written 2026-11-14 by the
first one, moved to a fresh session at 600K context. Jason works mostly from his phone: short messages, one line per
item, decisions as "reply go".

## The job

Keep sessions fast and affordable, and keep Jason told only of what needs him.
- **Watch:** context size (hand off past ~400K), restarts, cost, sessions waiting on Jason, release clashes, file claims
  (`tools/page-audit/checkout.py list`, decisions/0161), stuck syncs (traverse-3way-sync `sync.yml`), Railway deploys.
- **Propose, Jason approves:** splitting, a fresh session per phase (the old one updates its `handoffs/` file and stops),
  starting a session for a problem he reports. At most about three working sessions at once.
- **Without asking:** archive a session that has said it's done or handed off, work in `main`, nothing waiting on him
  (Jason, 2026-11-14). Never archive one waiting on him, the session manager itself, or "Community Build" (he keeps it).
  If the permission check refuses an archive, ask him for "archive it".
- **Relay:** when Jason answers here for another session ("merge it", a choice), send it to that session with
  `send_message` in his words. Never approve anything on his behalf.

## How the checks run

- **Hourly, while he's checked in:** a scheduled task named "Hourly session check" (cron `14 * * * *`) fires into the
  session manager. "Checking out" disables it; "checking in" (or "check in") enables it. Its prompt lists every check.
- **Notify** (PushNotification) only when something needs him: a session waiting, or a proposal. One line.
- **Claims check without stray files:** `git status -s` empty, then `git checkout -B <own branch> origin/main`, push it,
  `checkout.py list`. Never leave main's files as uncommitted changes (the stop hook complains).
- **Railway:** a token for `backboard.railway.com` is saved on the "cloud" environment; query
  `https://backboard.railway.com/graphql/v2` (deployments, `deploymentLogs`). Project "gracious-sparkle": services
  `traversence` (the crawler worker, cron every 5 minutes) and `video-worker`. "CRASHED" on a cron service means a run
  exited non-zero; read the logs before anyone presses Restart.
- **Reading another session cheaply:** `get_session` (size, restarts, cost, last summary) and `list_events` with
  `kinds: ["result"]`. Large results land in a file: parse with python, don't load them whole. Reading never wakes it.

## Rules Jason set (all in CLAUDE.md)

- Every build is a release through git; he approves each merge in chat; no zips for him to upload.
- Both repos: sessions add `traversence` / `traverse-3way-sync` with add_repo without asking. The permission check may
  still refuse a session's own add or `create_session` with pre-approved tools: then he types "add the sync repo" there.
- FAQ clashes resolve themselves; check-out per 0161; "how it works" questions go to the documents, not to him.
- Cost: on Max 5x ($100/month); `cost_usd` on sessions is API-rate equivalent, not a bill. Watch the 5-hour window.
  The archived "Get" session is 903K context (~$2,700 equivalent): read its record, don't wake it.

## Paused (2026-11-14)

- **`decisions/0163` and `decisions/0164` are accepted** (Jason, 2026-11-14: "all approved"; no shadow mode; 0164 §14
  settled). **The crawler and the automation stay paused** until the build stages (0163 §8: operations stages 2–6, then
  7–11 for the Knowledge Engine's new parts) and the switch-on check (0163 §10) are done and Jason switches them on.
  Each stage is a release he approves, one session per stage from its handoff (`handoffs/crawler-revamp.md`).
- **The Railway crawler's cron** was removed through the API (~15:55 UTC); `railway.json` still says every 5 minutes, so
  a worker deploy restarts it (details at the top of `handoffs/crawler-revamp.md`); the automation master switch (release
  #48) makes any run that starts end at once. The hourly check expects no crawler runs meanwhile.

## Where things stand (2026-11-16, 14:30 UTC)

- **Built and live:** decisions/0165 (#56, #57), Release B (#58–#67), the photo fix (#68), and Start the system plus the
  review link's login redirect (#69). Nothing is running. Automation and the crawler stay off until Jason presses
  **Start the system** (keys first: `handoffs/release-b.md`).
- **Keys, traced with Jason by service on 2026-11-16:**
  - GitHub: `GITHUB_SYNC_TOKEN`, read-only on both repos.
  - Railway: limits set, compute $25 stop and $10 alert, agent $10 and $5; `RAILWAY_API_TOKEN` made; `HF_TOKEN` added.
  - Google and Gemini: done. m2servcore owns the projects and billing. The Places key is restricted to Places API (New),
    with SearchText at 200 a day and a $10 budget. `GOOGLE_PLACES_MONTHLY`=1000 on Railway. One Gemini key, …rCWw, on the
    free tier.
  - Still to go: AWS, Cloudflare R2, Stripe, Square, the others, and Supabase last.
- **Queued after the usage reset (~Nov 17 23:00 UTC):**
  1. **QuickBooks setup step**, which needs a decision note (Jason, 2026-11-16):
     - On connecting, it asks **who manages the chart of accounts and products**:
       - **A, Traversence manages:** the site suggests and creates income accounts and QuickBooks products per revenue
         stream.
       - **B, QuickBooks is the master:** the site only reads and matches, never creates.
     - It chooses the deposit and refund bank account, and checks sales tax.
     - The choices live in the site's settings, not `.env`, so `QB_INCOME_ACCOUNT_ID` and `QB_REFUND_ACCOUNT_ID` are
       retired.
     - **The catalog owns the link** (Jason: "this should be addressed by when a product is created"). Creating or
       editing a product in the catalog checks QuickBooks and assigns it there: in A it creates the QuickBooks product
       on save, and in B it's picked from QuickBooks' list. A product with no QuickBooks match can't go on sale. The
       sale-time hold stays only as a safety net.
     - **Each catalog product carries both sides** (Jason, 2026-11-16): its **income** account, and its **expense** or cost
       account (cost of goods sold for what we buy or make, such as printing, shipping or a supplier's fee), with the
       cost amount. In QuickBooks that's the product's income and purchase accounts.
     - **Payment processing is set up separately from products** (Jason: "payment processing vs bookkeeping"):
       - which processor takes each kind of payment: QuickBooks Payments, Stripe or Square;
       - where the money lands: a clearing account or "undeposited funds" until the payout, then the bank;
       - processor fees, booked to a "Merchant fees" expense;
       - payouts and refunds leaving the processor or the bank.

       A refund touches both sides: it reverses the product's income and cost (bookkeeping), and money leaves through
       its processor (processing).
     - **Marketplace sales for businesses** (Stripe Connect, Square) are the business's money, not Traversence income.
       Only the platform fee is our income.
     - Traversence itself starts in B.
  2. **The cleanup session:**
     - Pulse #31's 62 unused names;
     - Pulse #22's Site health items;
     - Data cleanup's "From visitors" label;
     - `/admin/crawler.php`'s outdated "Cron Schedule */5" instruction (Start the system sets the cron now).
  3. **Release B's small leftovers:** P3-5 (the owner's "Check these") and P3-2's Facebook-link case.
- **Jason's own, any time:** the OSM forum statement (`research/osm-overpass-2026-11-13.md` §2), and the SES dashboard
  check for bounces lost while the bot check blocked them.
