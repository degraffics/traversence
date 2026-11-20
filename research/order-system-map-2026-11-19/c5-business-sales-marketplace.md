# C5 · Businesses' own sales: the order system as built

Read-only map of the live mirror `degraffics/traverse-3way-sync` (paths below: `app/` = `sync-app/`, `website/` =
`sync-storage/`, `trvgo/` = `sync-trvgo/`). Scope: orders that are a **business's** sales (Marketplace checkout, the
business's own Quotes & invoices, Orders), and where they meet Traversence's own order system. Line numbers are the
mirror's as read on 2026-11-19.

Read in full: `app/lib/MarketCheckout.php` (782), `MarketPay.php` (418), `KindCheckout.php` (302), `ItemKinds.php` (371),
`BizQuotes.php` (375), `BizFiles.php` (196), `BizCodes.php` (114), `app/lib/Notify.php` (634), `app/business/checkout.php`
(310), `website/market/cart.php` (218), `market/order.php` (111), `market/item.php` (187), `website/api/market-pay.php`
(100), `api/market-webhook.php` (79), `website/q.php` (181), `website/q-file.php` (22), `website/business/files.php` (42),
`website/business/api.php` (132), `website/business/router.php` (402), `app/ui/storefront-view.php` (209),
`trvgo/index.php` (189). Read in part: `app/admin/tools.php` 852-1022 and 1624-1670; `BizMessages::personSend`
(185-210); `MarketBiz::message` (526-540); `api/market.php` 55-70; `market-ui.php` 85-100; `Bookings.php` 170-236;
`Inform.php` 85-100, 136-144; `MarketSquare::sold` 219-231; `MemberPulse.php` 110-120; `user/market/index.php` 134-146;
`scripts/market_tick.php`; `Scheduler.php` 30, 69; `BusinessData.php` 36-50; `staging-tables.php` 45-60;
`StagingSite.php` 55-75; headers of `Orders.php`, `ClientOrders.php`; `QuickBooksPay::testHere` 92-107.

---

## 1. Function inventory

Format: `name (line)`: what it does · tables · callers (file:line) · decision / Jason notes quoted.

### app/lib/MarketCheckout.php
Header (2-18): "the Marketplace, step 4: buying from a business (decisions/0135, "Step 4 decided")". "orders our ledger
(market_orders) … The business's own copy is an order in its walled Orders (BizQuotes), written by the server through the
same gate as its portal: a manual order when placed, a card order only once the gateway confirms it's paid." "The totals
follow the business's own invoices exactly (BizQuotes::sums)". Constants: `METHODS` (29: zelle, bank, check, pickup,
other), `QUOTE_METHOD` (31, map to BizQuotes::METHODS; pickup→cash), `STATUSES` (32), `CARD_HOLD_MIN = 35` (33, "a little
past the gateway's own pay window (Stripe's session: 31 minutes)"), `MAX_QTY = 999` (34), `TEST_NOTE` (461).

- `ready (36)`: checks market_orders, market_items.track_stock/taxable exist · market_orders, market_items · tools.php:1630,1646; checkout.php:23,248; market360.php:32; marketplace.php:227; trust.php:19,40,75,149; Inform.php:91,127; MemberPulse.php:115; market-pay.php:23; market-webhook.php:23; router.php:70; cart.php:25; item.php:35; order.php:17.
- `now (43)`: private timestamp.
- `money (45)`: cents → "$x.xx" · many (cart.php, order.php, checkout.php, tools.php:1655, Bookings.php:199).
- `gateway (49)`: the business's gateway row for the current env · market_gateways · tools.php:1659; checkout.php:30; market360.php:34; trust.php:20,40; Inform.php:93; KindCheckout.php:49; MarketSquare.php:35; MemberPulse.php:115; market-pay.php:47,56,66; cart.php:52,94,133; order.php:71.
- `connect (55)`: upsert gateway as connected, tokens sealed · market_gateways · pay-connect.php:37.
- `disconnect (66)`: MarketPay::revoke, status disconnected, tokens nulled · market_gateways · checkout.php:287.
- `broken (75)`: status broken with reason (revoked from the gateway's side) · market_gateways · market-webhook.php:39,63; self :770.
- `access (81)`: Square token, refreshed within 7 days of expiry · market_gateways · MarketSquare.php:227,272; market-pay.php:68; self :106,684,742,770.
- `payStatus (98)`: account standing (charges/payouts/due) cached on acct_json, asked again after $maxAge · market_gateways · trust.php:23,149; Inform.php:91; self :778 · "(decisions/0141 §3, Trust score)".
- `defaults (120)`: default checkout settings (pickup only, hold 3 days) · market360.php:33.
- `settings (125)`: the business's checkout settings + live flag · market_checkout · tools.php:1660; checkout.php:29,257,261; market360.php:33; Inform.php:141; MarketBiz.php:253; pay-connect.php:38; cart.php:57.
- `cardOk (135)`: plan allows 'card_checkout' + gateway connected + card switched on · market_gateways, market_checkout · checkout.php:32; item.php:124; self :206,223.
- `saveSettings (146)`: validates methods (text required except pickup), tax %, ship options (≤8), hold_days 1-14 · market_checkout · checkout.php:256.
- `seller (181)`: Seller Agreement version and last acceptance · LegalTexts, acceptances · checkout.php:148,155; trust.php:49; MarketBiz.php:252 · "(decisions/0142 §1)".
- `acceptSeller (191)`: records Seller Agreement acceptance · Acceptance · checkout.php:260.
- `setLive (200)`: go live / pause; requires Seller Agreement, Inform::mayCharge, a card or direct method · market_checkout · checkout.php:264; Inform.php:98 (unpause), 141 (pause) · "the law asks for them before selling resumes".
- `payWays (214)`: card (unless a restricted item) + direct methods · market_checkout, market_items · cart.php:58; self :357 · "a restricted item (decisions/0142 §4): a direct way of paying, not card".
- `buyable (231)`: published, live, 'buy' button, fixed price, KindCheckout::buyable, not sold out · cart.php:37; item.php:36; self :275,299,340 · "its kind (decisions/0141 §5)".
- `available (245)`: tickets: seats left; tracked stock − held; null for booking/rental/service/digital/subscription · market_items, market_orders · cart.php:38; item.php:37; self :300,342.
- `held (254)`: qty in pending/awaiting orders within hold_until (LIKE on order_lines JSON) · market_orders · self :250.
- `setStock (264)`: track_stock + stock on a published item · market_items · marketplace.php:228; MarketSquare.php:167,185,204.
- `add (271)`: add to cart; refuses own team, ALONE kinds · market_cart · market-pay.php:36.
- `setQty (284)`: change or delete cart line · market_cart · market-pay.php:39.
- `carts (291)`: buyer's carts grouped per business, each line with ok/why/left · market_cart, market_items, entities · cart.php:26; self :345.
- `cartCount (309)`: total qty in carts · market_cart · section-rails.php:128; market-pay.php:37; user/market/index.php:138.
- `totals (319)`: sub, ship, tax on taxable lines at tax_milli, total · cart.php:65; self :370 · "exactly as the business's invoice will add it up (BizQuotes::sums)".
- `place (331)`: places an order (cart or Buy now): Standing guard, checks, KindCheckout::prepare (strict), method, ship/address, totals ≥ 50¢, INSERT market_orders (card → pending, hold 35 min; direct → awaiting, hold_days); pay_now_cents/pay_mode; house-rules Acceptance; agreed(); clears cart; direct way: writeOrder + notify business + email buyer instructions · market_orders, market_cart, users, acceptances · market-pay.php:44 · "(decisions/0141 §5)", "(decisions/0142)", "(decisions/0142 §2)".
- `agreement (399)`: checkout line + StorePolicies snapshot + version · StorePolicies, LegalTexts · cart.php:139; self :414,450.
- `agreed (411)`: writes policy_json on the order and an Acceptance record; never stops an order · market_orders, acceptances · self :387,490.
- `packet (425)`: dispute packet HTML (order, receipt, policies, acceptance fingerprints) · acceptances · router.php:74 · "(decisions/0141 §6)"; "Traversence runs the Marketplace and isn't a party to the sale."
- `testAllowed (464)`: sandbox/mock only, team with 'ledger', gateway connected · checkout.php:107; cart.php:31 · "(E-commerce setup, step 5; Jason, 2026-11-02)".
- `placeTest (474)`: cancels earlier pending test; inserts $1 card order, pending · market_orders, users · market-pay.php:54.
- `testFailed (495)`: test order cancelled with the gateway's reason · market_orders · market-pay.php:58.
- `lastTest (502)`: newest test order · market_orders · checkout.php:108; market360.php:37.
- `cancel (510)`: buyer cancels pending/awaiting; tells business if a biz_quote exists ("Void it in Quotes & invoices"); booking/rental → Bookings::changed · market_orders · market-pay.php:79; Bookings.php:232 · "(biz.booking, decisions/0172 §2.5)".
- `get (522)`: order + decoded lines · market_orders · Bookings.php:181,229; KindCheckout.php:272,288; router.php:70; order.php:22; ticket.php:17.
- `byToken (531)`: order by 32-hex token · market_orders · market-pay.php:29,89; order.php:17.
- `due (541)`: pay_now_cents when a deposit, else total · market-pay.php:51,61; order.php:30,59.
- `instructions (547)`: buyer text for a direct method (amount, label, business's words, "Order N" memo, hold date; Zelle warning) · market_checkout · Bookings.php:209; order.php:31; self :393,632.
- `markPaid (566)`: once: status paid (from pending/awaiting/expired); stock −qty (not booking/rental); stock alert notify; MarketSquare::sold; unless via 'quote': writeOrder(+ref) and notify business; KindCheckout::fulfil; email buyer "Paid" · market_orders, market_items · self :604,745.
- `quotePaid (600)`: business recorded full payment on the invoice → markPaid(via 'quote') for an awaiting/expired order with that biz_quote_id · market_orders · BizQuotes.php:314.
- `note (607)`: appends a dated note · market_orders · self :583,586,591,687,700.
- `writeOrder (617)`: opens the business realm (BusinessData::open); if no biz_quote_id: BizQuotes::create + addLine per line (+ shipping line), then UPDATE biz_quotes to status approved, stage new, tax_milli, deposit_pct, due_at, pay_note, notes; saves biz_quote_id; if paid: BizQuotes::pay(card) · b_biz_quotes, b_biz_quote_lines, b_biz_payments, market_orders · self :391,586 · "in its walled data (decisions/0110)".
- `bizName (644)`: entity name.
- `notify (647)`: BizMessages::personSend from the buyer to the business's inbox, told as 'biz.order' (or '' none) · business_messages, comms_messages · self :392,518,579,588,701 · "(Urgent, decisions/0172 gap 2)".
- `mailBuyer (655)`: Mailer email to the buyer with store policies and order link · self :393,593,688 · "(decisions/0142 §5.3)".
- `rentalReturned (673)`: team marks returned; capture (hold) or refund (charge) deposit less damage; pay_mode returned · market_orders · market-pay.php:91.
- `cancelSubscription (693)`: Stripe cancel_at_period_end; note + notify · market_orders · market-pay.php:82.
- `onOrderTeam (705)`: team with 'orders' edit · Bookings.php:185; order.php:78; self :675.
- `forBuyer (710)`: buyer's orders (50) · market_orders, entities · user/market/index.php:138.
- `forEntity (718)`: business's orders · market_orders · checkout.php:162.
- `firstTime (728)`: once-per-event guard · market_pay_events · market-webhook.php:29,54.
- `confirmAndMark (736)`: card order pending/expired: MarketPay::confirm, store sub_ref, markPaid · market_orders · market-pay.php:70,76; market-webhook.php:36,60; order.php:22; self :756.
- `tick (751)`: reconcile pending card orders >10 min with a ref; list awaiting bookings/rentals past hold; expire pending/awaiting past hold_until; Bookings::changed for released; refresh Square tokens; Inform::tick; daily payStatus · market_orders, market_gateways · scripts/market_tick.php:19 (Scheduler 'daily', Scheduler.php:30) · "(decisions/0172 §2.5)", "the INFORM Consumers Act".

### app/lib/MarketPay.php
Header (2-19): "Stripe (Connect, Standard accounts) and Square (OAuth) … The money goes straight to the business; we take 0%
and never see a card". "With MARKET_PAY_ENV=live, a test account signed in pays in test mode (Jason, 2026-11-10), and test
mode never uses a live key (2026-11-11 …)". No database tables of its own.
- `env (41)`: live|sandbox|mock|off; mock refused off-CLI ("(4a review)"); live → sandbox when `QuickBooksPay::testHere()` · callers listed in §4 (tools.php, checkout.php, MarketCheckout, Inform, trust, market360).
- `localTest (58)`: CLI or localhost built-in server · Domains.php:48,59; self :44.
- `available (68)`: can businesses connect provider now · tools.php:1652-1653; checkout.php:75,80,221; pay-connect.php:54.
- `missing (79)`, `missingAll (86)`: .env names lacking · missingAll: tools.php:1652-1653.
- `testOnLive (93)`, `keyName (99)`, `key (105)`: test keys STRIPE_TEST_/SQUARE_SANDBOX_; a live key read as empty in test mode.
- `sealKey (118)`, `seal (124)`, `unseal (130)`: libsodium secretbox · seal: Inform.php:89; MarketCheckout :62,87.
- `http (142)`: curl; `StagingGate::hold` first; `ServiceHealth::seen`.
- `stripe (156)`, `square (169)`, `squareHost (167)`, `squareApi (177)`: API wrappers · squareApi: MarketSquare.php:69,102,230,239.
- `connectUrl (182)`, `finishConnect (190)`: OAuth · pay-connect.php:36,58.
- `refreshSquare (210)` · MarketCheckout :85. `revoke (220)` · MarketCheckout :70.
- `start (240)`: Stripe embedded Checkout session on the connected account (card only; subscription mode; deposit as one line; hold → capture_method manual; expires 31 min; idempotency key) or Square Web Payments params or mock · market-pay.php:48,57 · "Card only (Jason, 2026-11-03)"; "2026-11-02: Stripe retired ui_mode "embedded" for "embedded_page"".
- `stripeLines (278)`: items + "Shipping or delivery" + "Sales tax" lines.
- `chargeSquare (288)`: /v2/payments for `due`, reference_id TV-<id> · market-pay.php:68.
- `confirm (302)`: asks gateway (Stripe session / PaymentIntent for hold / Square payment); amount must equal due · MarketCheckout :742.
- `accountStatus (336)`: Stripe account requirements / Square merchant status · MarketCheckout :106 · "(decisions/0141 §3, Trust score)".
- `due (363)`: same rule as MarketCheckout::due.
- `capture (370)` (Stripe only) · MarketCheckout :683. `refund (378)` · MarketCheckout :684. `subscription (386)` · MarketCheckout :699; order.php:71.
- `stripeSigned (399)`, `squareSigned (412)`: webhook signatures · market-webhook.php:26,51.

### app/lib/KindCheckout.php
Header (2-12): "buying by kind (decisions/0141 §5, ItemKinds) … Availability comes from orders themselves". `ALONE` (20:
booking, rental, subscription), `SHIPS` (22: physical, madetoorder).
- `ready (24)`: market_tickets, market_downloads, market_orders.pay_now_cents/sub_ref exist · MarketCheckout :383,744.
- `kindOf (32)` · ticket.php:20. `buyable (39)`: hourly service → quote; ItemKinds::ready; ticket started; booking/rental/subscription need ready; subscription needs Stripe · MarketCheckout :238.
- `seatsLeft (58)` · MarketCheckout :247. `taken (69)`: paid + held qty/date overlaps from market_orders.order_lines · market_orders. `freeOn (86)`, `dates (94)`: stays/rentals date rules.
- `prepare (118)`: per-kind lines and extras (service travel fee; madetoorder specs, deposit balance, proof flag; ticket starts; harvest windows; booking dates/guests/lodging tax/house rules; rental dates/waiver/deposit, hold only on Stripe and ≤ 6 days; subscription interval/trial) · MarketCheckout :355; cart.php:53.
- `getting (198)`: "how you get it" text when nothing ships · MarketCheckout :365; cart.php:129.
- `fulfil (216)`: on paid: ticket codes per seat, download token (max_uses, expires) · market_tickets, market_downloads · MarketCheckout :591.
- `code (244)`. `tickets (252)` · checkout.php:306; order.php:63. `ticket (259)` · ticket.php:16.
- `checkIn (267)`: team checks a ticket in once; order must be paid · market_tickets · market-pay.php:85.
- `download (280)`: checks token, expiry, uses, order paid, file; counts a use · market_downloads · download.php:12. `downloads (296)` · order.php:63.

### app/lib/ItemKinds.php
Header (2-10): "the nine kinds of item a business sells (decisions/0141 §5). A kind is a full configuration … how its
orders are handled". `KINDS` (19-29, 4th field = how bought: cart|quote|dates|subscribe), `LEGACY` (32), `FIELDS` (38-95),
`RULE` (98), `GATEWAYS` (101: subscription → stripe), `TAXED` (208). No tables.
- `key (103)`, `name (110)`: callers across tools.php, MarketBiz, MarketCheckout, Products, cart.php, item.php, index.php.
- `fit (146)`, `fits (188)`, `list (195)`: name-to-kind fit · fit: MarketSquare.php:127; spell.php:14 · fits: tools.php:617,628; MarketBiz.php:246; MarketSquare.php:127 · "Jason, 2026-11-04: "the context of the word or words should align the scope of the item into what is an allowed kind…"".
- `taxed (209)` · tools.php:614,629; `taxChoices (211)` · tools.php:540 · "Jason, 2026-11-04: "I don't like having to do work that the system should already know how to handle"".
- `choices (218)` · tools.php:539,625. `options (221)`: no caller found in the mirror by `ItemKinds::options(`.
- `buy (224)` · KindCheckout :42; MarketBiz.php:141; item.php:111. `per (232)` · MarketBiz.php:431; item.php:85.
- `gatewayOk (241)` · KindCheckout :51. `clean (250)` · Products.php:139. `data (284)`, `defaultOf (292)`.
- `ready (301)` · tools.php:521,604; marketplace.php:101; KindCheckout :44; MarketBiz.php:244. `facts (313)` · tools.php:521; ItemWriter.php:36; item.php:92; ticket.php:24.

### app/lib/BizQuotes.php
Header (2-7): "a business's quotes, invoices and orders (decisions/0118 …). One row goes the whole way: a draft quote,
sent … approved … paid (payments recorded by the team, by hand …), and worked as an order through its stages. It lives in
the business's own walled data (BusinessData: b_biz_quotes and friends)". `STATUS` (14), `TERMS` (15), `METHODS` (16, incl.
'card' = "Card (your own terminal or link)"), `STAGES` (17). All tables through `Scope::t()` (business realm):
biz_quotes, biz_quote_lines, biz_payments, biz_quote_log, crm_customers, (settings via Commerce).
- `t (19)`, `now (20)`, `money (22)` · tools.php:862,994; q.php:47. `cents (25)`.
- `ref (34)`: Q-#### for draft/sent/declined, INV-#### otherwise · tools.php, router.php:194, q.php.
- `get (37)`, `fill (45)`: quote + lines + payments + log + sums · tools.php:869,927,1000; BizFiles :80; router.php:186.
- `sums (55)`: subtotal, code discount before tax, tax, total, paid, balance, deposit · "(BizCodes, decisions/0121)".
- `all (71)`: groups quotes/invoices/orders/open · tools.php:863,990.
- `create (87)`: draft; customer found or created in Customers; next number; defaults · tools.php:934; MarketCheckout :624.
- `defaults (111)`, `saveDefaults (124)` · checkout.php:42; router.php:312; business/api.php:44 · "(decisions/0119)".
- `editable (140)`, `must (145)`.
- `addLine (153)`: catalog product (Products::priceFor) or hand line · tools.php:919,920; MarketCheckout :627,628,630.
- `removeLine (178)` · tools.php:921. `setTerms (192)` · tools.php:922.
- `send (214)`: token, total, sent (or stays approved), expires; emails customer the /q.php link · tools.php:923.
- `link (234)`: `/q.php?t=<owner>.<token>` · tools.php:897,1011. `byToken (240)` · q.php:26,62; q-file.php:18.
- `approve (250)`: customer approves (name), code redeemed, due_at by terms, status approved, stage new · q.php:46.
- `setCode (270)` · tools.php:926; q.php:56. `decline (288)` · q.php:58.
- `pay (297)`: records a payment; full → paid; else sent → approved; on full calls `MarketCheckout::quotePaid` · biz_payments, biz_quotes · tools.php:924,1019; MarketCheckout :639 · "(decisions/0135 step 4)".
- `stage (320)`: approved/paid only; ready/shipped email the customer · tools.php:1018.
- `void (340)`: refused when payments recorded · tools.php:925. `copy (351)` · tools.php:927. `touch (365)`, `log (371)`.

### app/lib/BizFiles.php (proofs and files on quotes)
Header: "(decisions/0120) … The records live in the business's walled data (b_biz_files)". `STANDARD` (15, "Jason,
2026-10-24"), `STATUS` (22: waiting, approved, changes, replaced). Tables: biz_files, biz_quote_log.
- `path (27)` "(decisions/0124)"; `forQuote (35)` · tools.php:899; router.php:197; q.php:126; `get (42)` · files.php:31; q-file.php:19.
- `used (50)` "(decisions/0126)", `allowance (58)`, `size (64)`, `typeNote (69)`.
- `uploadCheck (76)` · upload-targets.php:222; `fromUpload (92)` "(Uploads, decisions/0127)" · upload-targets.php:226; `version (114)`: new version replaces an unanswered one.
- `remove (128)` · files.php:38 (a decided proof stays). `decide (140)`: customer approves or asks changes · q.php:51. `log (150)`. `send (156)` · files.php:33; q-file.php:22.
- class `BizStorageFull (189)`.

### app/lib/BizCodes.php (discount codes on quotes)
Header: "(decisions/0121). Jason, 2026-10-24: "for listings we don't need to set rules for codes"". Tables: biz_codes,
biz_code_uses (walled).
- `clean (17)`, `offer (20)`, `all (25)`, `get (33)`, `save (41)`, `byCode (66)` · tools.php 939-979 (business/codes).
- `check (74)` · BizQuotes :259,280. `off (92)` · BizQuotes :61,283. `redeem (99)` · BizQuotes :261. `uses (108)` · tools.php:958.
- Marketplace orders do not use codes: `MarketCheckout::totals` (319) and `writeOrder` (617) set no code.

### app/lib/BizMessages.php (only `personSend`, 188-210, read)
- `personSend (188)`: inserts a business_messages row from the person, then `Notify::send($tell, ['entity'=>e,'except'=>[uid]], thing 'bizmsg:<mid>', thread 'bizmsg:<e>:<uid>', pulse_ref …)`; 'biz.order' gives the order title form · business_messages, comms_messages · MarketCheckout :651; MarketBiz.php:538.

### app/lib/Notify.php (order kinds)
Register `KINDS` (70-100). Order-related: `biz.order` (73: "A Marketplace order for your business", urgent, business,
email on, can be off, system engine, cap 'orders'); `biz.booking` (85, urgent, business, cap 'orders'); member kinds
`booking.tomorrow` (90), `booking.soon` (91), `booking.changed` (92). `BODIES` (43-62): booking kinds and biz.booking drawn
by `Bookings::pulseBody`; **no body for biz.order**. `recipients (201)`: an entity's team by `Roles::CAN['org']`; for cap
'orders', roles with 'orders' or 'messages' or 'billing' also take it (211). `send (141)`: one comms_messages row per
recipient, thread folding (167-172), email/push routing, quiet hours hold only notice/general/special (184-193). Other
functions (`emailOn 260`, `pushOn 280`, `deliverEmail 470`, `deliverPush 309`, `releaseHeld 568`, `forUser 592`,
`unreadForBell 604`, `markRead 613`, etc.) are generic, not order-specific.

### website/market/cart.php
Header 2-12. One request: `?test=` (30-32) test purchase; `?item=&qty=` Buy now (33-41); `?b=` checkout of a business's cart
(42-46); KindCheckout::prepare non-strict (51-55); payWays (58); subscription → card only (59); totals (65). HTML: Your
order, kind section (dates, specs, window, house rules), How you get it / Getting it, How you pay, totals, StorePolicies box
and checkout line (139-141), Place order. JS (158-216): POST `/api/market-pay.php` action place|test; then mock button,
Stripe `createEmbeddedCheckoutPage` with `stripeAccount` (191-196), or Square `card.tokenize` → square_pay (197-212).

### website/market/order.php
Buyer / team (`ledger`) / staff view by token (17-20). Pending card → `confirmAndMark` on load (21-23). Test order → team
redirected to `/business/<e>/checkout#test` (25, "Jason, 2026-11-03"). Status texts (30-34). Lines with kx details,
deposit/balance (48-61). Paid: tickets, downloads (buyer), subscription state + Cancel, rental return for team (62-84).
Cancel (unpaid, buyer) (86). Store policies snapshot (88-89). JS posts cancel/sub_cancel/returned (92-110).

### website/market/item.php (buy parts)
Buttons from `MarketBiz::buttonsFor` (33), buy first (38). Panes: message, **quote** (104-108: qty + note →
`item_quote`), **buy** (109-125: dates form → cart.php for booking/rental; Subscribe; qty + Buy now / Add to cart), offer
(126-129). "You pay <biz> directly … Traversence never holds your money." (124, 148). JS: buy → cart.php?item= (171); cart
→ market-pay add (172-178); quote/message/offer → `tvMarketPost` → `/api/market.php` (182).

### website/api/market-pay.php
Actions (4-14): add, qty, place (41-51: place → MarketPay::start → provider_ref), test (52-61), square_pay (62-71: hold
check, chargeSquare, confirmAndMark), mock_pay (72-77, env mock only), cancel (78-80), sub_cancel (81-83), checkin (84-87),
returned (88-92). Signed in + CSRF (24-26). `$own` = buyer only (28-32).

### website/api/market-webhook.php
Stripe (25-47): signature, once, `checkout.session.completed|async_payment_succeeded` → set provider_ref if empty →
confirmAndMark; `account.application.deauthorized` → broken. Square (49-78): `payment.created|updated` COMPLETED with
reference `TV-<id>` → confirmAndMark; `oauth.authorization.revoked` → broken; inventory/catalog → MarketSquare. On error the
event row is deleted so the retry runs again (41-45, 72-76).

### website/q.php, q-file.php
q.php (2-5): "a business's quote or invoice, for its customer … No account needed: the link is the key." Opens
`BusinessData::open(owner)` (26). POST (36-64): approve, proof_ok/proof_changes, code, decline; owner emailed (40-44).
Page: lines, sums, terms, stage (121), proofs/files (124-148), Approve/code/Decline when sent (150-164), "How to pay" when
approved with balance: business's `pay_link` and/or `pay_note` (169-177), "Payments are made to <biz>, not to
Traversence." No MarketPay or gateway code on this page. q-file.php: proof or shared file by quote token.

### website/business/router.php, business/api.php, business/files.php, app/business/checkout.php
- router.php: packet download (68-75); quote files page `quotes?files=` (183-241, TvUpload target biz_file); tool pages
  incl. quotes/orders/discounts (242-268, helper notes 261-267: "Every approved quote is an order here"); checkout →
  `tv_biz_checkout` (297-300); settings: quote defaults (307-330).
- api.php: `checkout` POST → `tv_biz_checkout_act` (53-57); generic list/get/act/create on the tool after role/plan checks,
  inside `BusinessData::open` (78-125); white_glove audit (84-85).
- files.php: GET a file (quotes view), POST JSON remove (quotes edit).
- checkout.php: `tv_biz_checkout (20)`: steps 1-6 (gateway connect, direct methods, tax, shipping, holds, test purchase
  "Jason, 2026-11-02", Seller Agreement, Go live), Square catalog card, "Marketplace orders" list with Open / Open in Quotes /
  Dispute packet (161-172); `tv_biz_square_card (209)`; `tv_biz_checkout_act (245)`: save, accept_seller, live, pause,
  square_import ("Jason, 2026-11-03"), square_sync, disconnect; `tv_biz_order_kinds (295)`: per-order kind summary.

### app/admin/tools.php (ranges read)
- `business/quotes` (854-937): list/get/act (add_item, add_line, remove_line, terms, send, pay, void, code, copy)/create; `Scope::isBusiness()` only.
- `business/codes` (939-979).
- `business/orders` (981-1022): approved/paid quotes by stage; acts stage, pay.
- `market/gateways` (1624-1670): staff view (fin/ops/svc): gateways, checkout live, market_orders counts, "Their sales this week (to them, not us)".

### app/ui/storefront-view.php, trvgo/index.php (routing only)
- `tv_storefront_domain (23)`: own domain via edge key → serve. `tv_storefront_serve (38)`: no session/cookie/script; CSP `form-action 'none'` (109); item grid links to `$site . $it['url']` on traversence.com (176); "Buying, messages and offers are on Traversence" (178); store policies box (181); Message us → listing page (186). `tv_storefront_closed (196)`.
- trvgo/index.php: X-TV-Host → `tv_storefront_domain` (29-42); GO Code resolve, incl. a pending **client order** proof from `orders`+`qr_issued` (57-65); non-code path → `tv_storefront_serve` (73-79).

---

## 2. Flows

### 2a. Marketplace cart → place → pay by card (business's own Stripe or Square)

| Step | file:line | Who acts | What the buyer sees | Money |
|---|---|---|---|---|
| Item page, Buy now / Add to cart | item.php:33-38, 109-125 | buyer | price, kind facts, "You pay <biz> directly … Traversence never holds your money" | none |
| Add to cart | item.php:172-177 → market-pay.php:35-37 → MarketCheckout::add 271 | buyer | "Added to your cart." + "Your cart (n)" | none (market_cart) |
| Carts, one per business | cart.php:146-155; carts 291 | buyer | "Your order from <biz>", Check out | none |
| Checkout page | cart.php:42-68, 92-144; KindCheckout::prepare 118 (non-strict); payWays 214; totals 319; agreement 399 | buyer | lines, How you get it, How you pay ("Through <biz>'s own Stripe/Square account. Your card details go to them, never to Traversence."), totals, store policies, checkout line | none |
| Place order | cart.php:175-184 → market-pay.php:41-46 → place 331-396 | buyer | button busy | market_orders row status **pending**, hold_until +35 min (374), policy_json + Acceptance (387) |
| Start card payment | market-pay.php:47-51 → MarketPay::start 240-275 | server | Stripe embedded checkout mounted in #ct-pay (cart.php:191-196) or Square card fields (197-212) | Stripe Checkout session created on the connected account (`Stripe-Account`, 159); provider_ref = session id (49) |
| Square pay | cart.php:205-208 → market-pay.php:62-71 → MarketPay::chargeSquare 288 | buyer | "Pay $x" button → redirect to order page | charge of `due` on business's Square location, autocomplete (290-292) |
| Paid confirmed: return | order.php:21-23 → confirmAndMark 736 → MarketPay::confirm 302 | server on buyer's page load | order page | only if gateway says paid and amount = due (314, 318) |
| Paid confirmed: webhook | market-webhook.php:32-37 (Stripe), 56-61 (Square) | gateway | — | same check (confirmAndMark) |
| Paid confirmed: reconcile | tick 755-756 (daily job) | cron | — | same check |
| markPaid | markPaid 566-597 | server | email "Paid: your order from <biz>" (+ ticket/download lines) (593-595); order page "Paid:" (order.php:30) | status **paid**; stock −qty (575); Square stock (582); writeOrder → biz_quotes approved + BizQuotes::pay 'card' (639) → biz quote **paid** when due = total |
| Business told | notify 647 → BizMessages::personSend 188 → Notify 'biz.order' | server | — | message "Order #N … is paid by card … It's in your Orders." (588-589) |
| Business works the order | tools.php business/orders 981-1022 → BizQuotes::stage 320 | business team | emails at Ready / Shipped (BizQuotes 328-336) | none |

### 2b. Marketplace order by a direct (manual) method

| Step | file:line | Who acts | What the buyer sees | Money |
|---|---|---|---|---|
| Place with method zelle/bank/check/pickup/other | place 331-396 (method ≠ card) | buyer | redirect to order page (market-pay.php:46) | market_orders status **awaiting**, hold_until + hold_days (374) |
| Business's copy written at once | place 391 → writeOrder 617 (not inside try/catch here; :586 is) | server | — | biz_quotes **approved**, unpaid, pay_note = instructions (632-636) |
| Business told | place 392 → notify | server | — | "New order #N … paying by <method>. Mark it paid in Quotes & invoices when it arrives." |
| Buyer told how to pay | place 393 → mailBuyer; instructions 547-557; order.php:31 | server | "Pay $x by <method>. <business's text> Put "Order N" in the memo … held until <date>." (+ Zelle warning) | off-platform, buyer → business |
| Business records payment | tools.php:924 (quotes) or :1019 (orders) → BizQuotes::pay 297 | business team | — | biz_payments row; full → biz quote **paid** → MarketCheckout::quotePaid 600 → markPaid(via 'quote') |
| markPaid via quote | markPaid 566-597 (skips 585-590) | server | "Paid" email + tickets/downloads | market_orders **paid**; stock taken; no second business notice |
| Not paid in time | tick 760 | cron (daily) | order page "Expired:" (order.php:33) | market_orders **expired**; biz quote unchanged; bookings/rentals → Bookings::changed (757-767) |
| Buyer cancels | order.php:86,106 → market-pay.php:78-80 → cancel 510 | buyer | "Cancelled. Nothing was charged; the items are released." | market_orders **cancelled**; business told "Void it in Quotes & invoices" (518); biz quote unchanged |

### 2c. Made to order (specs, deposit, proof)

| Step | file:line | Who acts | What the buyer sees | Money |
|---|---|---|---|---|
| Item set up | ItemKinds FIELDS madetoorder 51-56 (specs, deposit_pct default 50, proof yes, lead) | business | facts "Paying: N% when you order, the rest when it's ready", "Proof: You approve a proof before it's made" (329-335) | — |
| Checkout questions | cart.php:117; KindCheckout::prepare 133-144 | buyer | one input per spec question; "Deposit: you pay $x now, and $y when it's ready" (cart.php:101) | balance = Σ line × (100−pct)/100 (142) |
| Place | place 383-385 | buyer | — | pay_now_cents = total − balance; pay_mode 'payment' |
| Card: deposit only | MarketPay::start 261-263 | server | Stripe line "Deposit on order N (of $total)" | `due` charged to business's account; Square charges `due` (291) |
| Paid | markPaid 566-597; writeOrder 634, 639 | server | email "(the deposit; the rest, $y, is invoiced when it's ready)" | biz quote deposit_pct = round(100·due/total); BizQuotes::pay records `due` → biz quote stays **approved** with balance |
| Business told | markPaid 587-589 | server | — | "Made to order: in Quotes & invoices, add your proof to it and Send; the buyer approves the proof and pays the balance from there." |
| Proof upload | router.php:183-241 (quotes?files=) → TvUpload target biz_file → upload-targets.php:222,226 → BizFiles::fromUpload 92 | business team | — | biz_files proof version, status waiting |
| Send | tools.php:923 → BizQuotes::send 214 (approved stays approved) | business team | email "Your invoice from <biz>" with /q.php link | — |
| Proof answer | q.php:49-53, 133-145 → BizFiles::decide 140 | buyer (no account) | "Approve the proof" / "Ask for changes"; owner emailed | — |
| Balance | q.php:169-177 | buyer | "How to pay $balance": pay_link (none set by writeOrder) and pay_note ("Paid by card on the Traversence Marketplace" for a card order, 632) | off-platform; team records via BizQuotes::pay → **paid**; quotePaid finds no awaiting/expired order (already paid) |
| Direct-method deposit | instructions 554 | buyer | "Pay $deposit (the deposit; the rest is invoiced when it's ready) by …" | market_orders stays **awaiting** until the biz quote is paid in full (BizQuotes::pay 313 only on full), or expires at hold_until |

### 2d. Tickets and downloads

| Step | file:line | Who acts | What the buyer sees | Money |
|---|---|---|---|---|
| Buyable | KindCheckout::buyable 39-55 (ready: date+capacity / file; event not started); seatsLeft 58 | — | "Tickets" qty with "N left" (item.php:120-121) | — |
| Checkout | prepare 145-147; getting 198 ("Tickets: on your order page" / "Download after paying"); no ship section | buyer | no "How you get it" | as 2a/2b |
| Paid → fulfil | markPaid 591 → KindCheckout::fulfil 216-242 | server | email lines: "N tickets on your order page, a code each for the door." / "download it at …/market/download.php?t=… (N times, D days)." | market_tickets (one per seat, code), market_downloads (token, max_uses = downloads×qty, expires) |
| Order page | order.php:62-69 | buyer | "Your tickets" (seat · code links to /market/ticket.php), "Your download(s)" with uses left | — |
| Door check-in | market-pay.php:84-87 → KindCheckout::checkIn 267-277 | business team | — | checked_in_at once; order must be paid |
| Download | download.php:12 → KindCheckout::download 280-294 | buyer | file, or "expired / used up. Ask the seller for a new one." | uses +1 |

### 2e. Business Quotes & invoices (portal → /q.php → paid)

| Step | file:line | Who acts | What the buyer sees | Money |
|---|---|---|---|---|
| New quote | portal /business/<id>/quotes → router.php:242-268 → tools.php:931-936 → BizQuotes::create 87 | business team | — | biz_quotes **draft**; customer added to the business's crm_customers |
| Lines, terms, code | tools.php:919-922, 926 → addLine 153, setTerms 192, setCode 270 | business team | — | deposit_pct, tax_milli, pay_link, pay_note |
| Send | tools.php:923 → send 214-232 | business team | email "Your quote from <biz> (Q-####)" with /q.php link good for valid_days | **sent** |
| Customer page | q.php:18-29, 95-180 | customer (no account) | lines, sums, terms, Approve (type name) / discount code / Decline | — |
| Approve | q.php:45-48 → approve 250-267 | customer | "Thank you: it's approved. Here's your invoice." | **approved** (INV-####), stage new, due_at; code redeemed (BizCodes::redeem) |
| Decline | q.php:57-60 → decline 288 | customer | "Declined. <biz> has been told." | **declined** |
| Pay | q.php:169-177 | customer | "Pay now" opens the business's own pay_link; pay_note text | off-platform to business |
| Record payment | tools.php:924 / 1019 → pay 297-317 | business team | invoice shows Paid / Still due | biz_payments; **paid** when full |
| Work the order | tools.php:981-1022 → stage 320 | business team | email "is ready for pickup" / "has shipped" with tracking and order link | — |
| Void / copy | tools.php:925, 927 → void 340, copy 351 | business team | page pill "Cancelled" | void refused when payments exist |

### 2f. Storefront and "Request a quote"

| Step | file:line | Who acts | What the buyer sees | Money |
|---|---|---|---|---|
| trvgo.us/<shop> or own domain | trvgo/index.php:29-42, 73-79 → storefront-view.php 23, 38 | visitor | shop grid, pages, store policies, contact | none (no script, form-action 'none', 109) |
| Item tap | storefront-view.php:176 | visitor | goes to the item page on traversence.com | — |
| Buying | storefront-view.php:178 | visitor | "Buying, messages and offers are on Traversence, where <biz> gets them." | buying is 2a/2b on traversence.com |
| Request a quote | item.php:104-108, 170 → market-ui.php:92 → api/market.php:61-64 → MarketBiz::message 526-540 → BizMessages::personSend (tell 'biz.message') | buyer (signed in) | "Sent. They'll send you a quote to approve; their reply comes to your messages." | none |
| Business side | MarketBiz.php:536-539 | business team | message "Quote request from the Marketplace: <title> × qty (price)" + "Make the quote in your business portal: Quotes & invoices → New quote." | — |
| Quote made | 2e (manual New quote) | business team | /q.php flow | no automatic link from the request to a biz_quotes row |
| When buying is refused for quote | MarketCheckout::buyable 237 ('from' price → "request a quote"); KindCheckout::buyable 43 (hourly service) | — | "Its price depends on options: request a quote." / "Priced by the hour: ask for a quote." | — |

---

## 3. States and who moves them

### market_orders.status (STATUSES, MarketCheckout:32)

| Status | Set by | Who |
|---|---|---|
| pending | place 380 (card); placeTest 487 | buyer; business team (test) |
| awaiting | place 380 (direct method) | buyer |
| paid | markPaid 569 (from pending/awaiting/expired) via confirmAndMark (order.php:22 page load, market-pay.php:70 square_pay, :76 mock_pay, webhooks, tick :756) or quotePaid :604 | gateway confirmation; business team recording full payment in BizQuotes::pay |
| cancelled | cancel 513 (from pending/awaiting); placeTest 480 (older test); testFailed 497 | buyer (order page, Pulse booking body Bookings.php:232); system for tests |
| expired | tick 760 (pending/awaiting past hold_until) | daily cron |
| refunded | no writer found (read at order.php:34, Bookings.php:201, STATUSES) | — |

Other order fields: `pay_mode` payment | subscription | hold (place 384) → returned (rentalReturned 685, team);
`provider_ref` (market-pay.php:49,59,69,75; webhooks 35,59; markPaid 569); `sub_ref` (confirmAndMark 744);
`biz_quote_id` (writeOrder 637); `policy_json` (agreed 416); `note` appended (note 609, Bookings.php:120).
Hold: stock/seats/dates count only rows with `hold_until > now` (held 256, taken 71), independent of the daily expiry.

### biz_quotes.status (BizQuotes::STATUS 14) and stage (STAGES 17)

| Status | Set by | Who |
|---|---|---|
| draft | create 101 | business team; server for Marketplace (writeOrder 624) |
| sent | send 222 | business team |
| approved | approve 264 (customer, q.php); pay 310 (partial on a sent quote); writeOrder 634 (direct UPDATE, approved_name "Ordered on the Traversence Marketplace") | customer; team; server |
| paid | pay 310 when paid ≥ total | team; server (writeOrder 639, card) |
| declined | decline 292 | customer (q.php) |
| void | void 346 (only with no payments) | team |

Stage: '' → new (approve 264, writeOrder 634, pay 310 if empty) → production / ready / shipped / done via `stage 326`
(team, business/orders tool); ready and shipped email the customer.
biz_files proof status: waiting (fromUpload 103) → approved / changes (decide 145, customer) or replaced (version 121, next
upload). market_gateways.status: connected (connect 59) / disconnected (disconnect 71) / broken (broken 77, webhooks,
tick 770). market_checkout.live: setLive 209 (team with publish; Inform pause/unpause). market_tickets.checked_in_at
(checkIn 275); market_downloads.uses (download 292).

---

## 4. Where it meets Traversence's own order system

**Separate tables, no cross-reads.**
- Business sales: `market_orders`, `market_cart`, `market_checkout`, `market_gateways`, `market_pay_events`,
  `market_tickets`, `market_downloads` (main database) and the business's walled `b_biz_quotes`, `b_biz_quote_lines`,
  `b_biz_payments`, `b_biz_quote_log`, `b_biz_files`, `b_biz_codes`, `b_biz_code_uses`, `b_crm_customers`
  (BusinessData.php:36-50, opened per business by `BusinessData::open`).
- Traversence's own: `orders`, `order_lines`, `order_ledger` … (Orders.php header 2-8: "one record for every order …
  QuickBooks invoice"); ClientOrders (decisions/0101) with its own customer page `/order.php?t=` (ClientOrders.php header).
- grep: `Orders.php`, `ClientOrders.php`, `QuickBooksPay.php`, `VendorOrders.php`, `WorkOrders.php` contain no reference
  to market_orders, MarketCheckout, biz_quotes or BizQuotes; the business-sale files contain no reference to the `orders`
  or `order_lines` tables.
- Three distinct customer-facing order pages: `/market/order.php?t=` (Marketplace), `/q.php?t=<owner>.<token>` (business
  quote/invoice), `/order.php?t=` (Traversence client order).

**Money paths.** Card money goes to the business's own Stripe/Square account (MarketPay header 2-6; Stripe-Account header
159; Square location 291). BizQuotes payments are recorded by hand ("Payments go to you, never through Traversence",
router.php:263). No QuickBooks invoice, no Traversence ledger entry for either.

**Shared code (touch points).**
- `QuickBooksPay::testHere()` only, in `MarketPay::env()` (49-52): a signed-in test account (or staging) pays businesses
  in the gateways' test mode. No other QuickBooksPay use in the business-sale files.
- `app/admin/tools.php` is one registry: `business/quotes` (854), `business/codes` (939), `business/orders` (981) sit in
  the same file as Traversence's `backoffice/orders` (40), `backoffice/work_orders` (124), `backoffice/client_match`
  (333), `backoffice/site_orders` (408, "Site orders (GO Codes)"); the business ones throw unless `Scope::isBusiness()`
  (859, 986) and run inside the business realm via business/api.php (78-125). Staff see businesses' Marketplace orders
  only as counts in `market/gateways` (1649, 1661-1662).
- Notify dispatcher: `biz.order`, `biz.booking` kinds (Notify.php:73, 85) through `BizMessages::personSend`; the same
  dispatcher carries Traversence's own kinds.
- Mailer, Acceptance, LegalTexts, StorePolicies, Standing::guard, Plan::allows('card_checkout'), Team/Roles, StagingGate
  (MarketPay::http 144), ServiceHealth (150-152), Inform (INFORM Act pause, payStatus), MarketPay::seal (also used by
  Inform.php:89 for tax IDs), MarketPay::localTest (Domains.php:48,59).
- Staging: `staging-tables.php` MONEY list (56-60) holds both `orders`/`order_lines`/… and `market_orders`/`market_*`/
  `biz_payments`/`biz_quotes`/… ("Money: never carried"); `StagingSite.php` PEOPLE (63-64) lists `biz_quotes`, `orders`,
  `market_orders`.
- trvgo.us: one router serves both a Traversence client order's GO Code proof (reads `orders` + `qr_issued`, index.php
  57-65) and businesses' storefronts (73-79); the storefront branch reads no order table.
- BizQuotes::create puts the Marketplace buyer into the business's own Customers (crm_customers, 90-96), not into any
  Traversence customer table.

---

## 5. What couldn't be traced

- **`refunded`**: in STATUSES and read by order.php:34 and Bookings.php:201; no code found that sets it.
- **Subscription renewals**: market-webhook.php handles only checkout.session.* and account/oauth events; no handler for
  later invoices. State is read live from Stripe on the order page (order.php:70-77). Nothing records renewal payments.
- **Made-to-order balance by card**: no path charges the balance through the business's gateway; q.php offers pay_link /
  pay_note only, and writeOrder sets no pay_link.
- **Cancel / expiry vs the business's copy**: cancel only messages "Void it in Quotes & invoices"; tick's expiry leaves the
  biz quote as is; no automatic void found.
- **Partial direct payments**: BizQuotes::pay calls quotePaid only when paid in full (313), so a recorded deposit leaves the
  market order awaiting; outcome at hold_until is expiry by tick (markPaid later accepts expired).
- **Expiry timing**: tick is scheduled 'daily' (Scheduler.php:30); a card order's 35-minute hold therefore stops counting
  at hold_until but its status becomes expired only at the next daily run.
- **market_orders schema**: no CREATE in the mirror (migrations delete themselves after running); columns inferred from
  INSERT/UPDATE (377-381, 384, 416, 637, 685, 744).
- Not read or only partly read: `MarketBiz` (get, buttonsFor, onTeam, canSee, publicOf), `BizMessages` beyond
  personSend, `market/ticket.php`, `market/download.php`, `business/pay-connect.php`, `market360.php`, `trust.php`,
  `marketplace.php`, `Bookings` (outside 170-236), `MarketSquare` (outside sold), `Inform` (outside the lines named),
  `Products::priceFor`, `Customers`, `Commerce`, `Scope`, `BusinessData::open` walls, `upload-targets.php` biz_file target,
  `Roles::CAN`, the rest of `tools.php`.
- Pulse display of biz.order: Notify has no BODIES entry for it; it is given `pulse_ref 'bizmsg:…'` (BizMessages 205),
  so Pulse shows it through the business messages table, which was not traced.
- Disputes: only the packet download (router.php:68-75); no dispute state or handler for chargebacks on business orders.
