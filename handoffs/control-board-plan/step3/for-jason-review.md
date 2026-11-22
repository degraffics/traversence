# For Jason's review: the control board, step 3

One page, in step with `03-cards.md`'s "For Jason's review" list (items 1–11). Each item says what it is, what we
recommend, and where it comes from. **Decided:** 2 items (5, 9). **Open:** 9 items.

| # | Item | Status |
|---|---|---|
| 1 | QuickBooks' processing fees | Open |
| 2 | The shared web search cap's unit | Open |
| 3 | The Communications Dispatcher as a critical part | Open |
| 4 | SES's bounce and complaint warnings | Open |
| 5 | The IRS load's partial delete | **Decided: "Fix in the build" (Jason, 2026-11-21)** |
| 6 | Overture's and Foursquare's licences | Open |
| 7 | The trvgo.us redirect while the edge is down | Open |
| 8 | Thresholds still marked des | Open |
| 9 | Delivery objectives: "Is it delivering?" | **Adopted: drawn in `board/index.html` (Jason, 2026-11-21)** |
| 10 | 1Password for our own staff | Open |
| 11 | The two sages | Open |

**1. QuickBooks' processing fees (open).** QuickBooks' card shows "fees not read yet", because nothing reads what
Intuit charged us. **Recommended:** read the fees in step 4's ledger export, as a spec item. It's a read of our own
account at no cost. Until then the chip stays "fees not read yet", with Console beside it. *Source: 03 §19.6, 3.*

**2. The shared web search cap's unit (open).** Tavily and Brave share one allowance, `websearch_monthly`, which
counts searches, as the code does. Brave costs $0.005 a search and Tavily $0.008 a credit, so the same count means
different money. **Recommended:** keep the count, which the code enforces, with a dollars line beside it on both
cards, unless you'd rather cap dollars. *Source: 03 §18.6, 2.*

**3. The Communications Dispatcher as a critical part (open).** The register has no dispatcher part today. Alarms,
sign-in codes and notices all go through it. **Recommended:** register it in step 4 as critical for procedures, with
`svc:aws` (SES) as one of its email providers. *Source: 03 §20.6, 1.*

**4. SES's bounce and complaint warnings (open).** The rates are read at no cost. The warning levels drawn are AWS's
review levels, 5% bounce and 0.1% complaint (inferred). **Recommended:** keep those levels, checked against AWS's own
docs in step 4. *Source: 03 §20.6, 5; item 8 lists it with the other thresholds.*

**5. The IRS load's partial delete (decided).** When one state's file loads and the other's rows are all refused, the
load's finish removes the second state's nonprofits (`IrsBulk::finish()`). **Decided: "Fix in the build" (Jason,
2026-11-21).** 02 row 38b's shape check comes before any write, and nothing is deleted below 90% of last month's rows.
It's a step-4 build item, with no live action. The 90% is the number you saw; it stays marked des. *Source: 03 §22.5,
§22.6 IRS 2; 02 row 38b.*

**6. Confirm Overture's (CDLA-Permissive-2.0) and Foursquare's (Apache-2.0) licences: loads already running (open).**
decisions/0164 R9 lets these open datasets be stored and shown with credit only once their licences are confirmed
(Decision 9). Their cards say "licence: confirmation not recorded". W1 added 205 in run #1290 (your screenshot of run
#1290's page: W1 · Added 205 · Changed 4 · Sent to Review 709; overture_bulk failed at 380 s). **Recommended:**
confirm both, or hold what they add until you do. *Source: 03 §22.6, All five 3.*

**7. Stop the trvgo.us redirect while the edge is down (open).** trvgo.us/<shop> sends visitors on to a shop's own
domain (a 301, cached 5 minutes). So when the Fly.io edge is down, those shops' trvgo.us addresses fail too, and the
source map's fallback ("the storefront's trvgo.us address still works") doesn't hold. **Recommended:** serve the shop
on trvgo.us while the board's edge check (the spec sensor on `/__edge`) is red. *Source: 03 §25.2, §25.4, 2.*

**8. Thresholds still marked des (open).** **Recommended:** approve these, or give your own numbers:
- 24 hours before a domain's certificate failure is told to the business's team and ours (03 §25.4, 3);
- SES's 5% bounce and 0.1% complaint (item 4);
- the IRS floor of 90% of last month's rows (item 5: the fix is approved, the number is still des).

*Source: 03 §25.4, 3.*

**9. Delivery objectives: is the platform delivering for users? (adopted: drawn in the interactive board, `board/index.html`, approved by Jason, 2026-11-21).**
A fourth top-line question on the board, "Is it delivering?", with objectives and targets in seven groups:
1. the site is there: pages under about 2.5 s, 99.5% of the time; sign-in working end to end; the phone layout;
2. people find what they came for: search quality; at least 3 results within 5 mi (30 for the outdoors, decisions/0060);
   paths complete;
3. what they see is true: freshness, completeness, nothing private or sacred leaks;
4. money and commitments kept: payments, renewals, orders, email delivered;
5. knowledge keeps growing: gathering reconciles, and the Review queue's age;
6. within our means: the caps;
7. the team is told and things recover: trips reach a person, the self-recovery rate, Incident Reports.

Each objective is a Service module with its own check, radius, and Fix it pointing to its parts. Each is a warning
first. Many of the measures already exist: the search, layout, paths and photos probes, and the health report.
**Recommended:** add them to step 3's design; you set the targets once. *Source: the session manager's proposal,
2026-11-21; drawn as the board's first view.*

**10. 1Password for our own staff (open).** It's linked from each card and called by nothing (03 §26.24), and decisions/0156's
Vault now holds staff logins. **Recommended:** Retired for our own staff once the Vault is in use; kept only as 0156's
"1Password through us" option for customers. *Source: 03 §26.24; the session manager, 2026-11-21.*

**11. Two sages (#92BEA5 brand token, #8FBCA8 icon active in CLAUDE.md): one, or both on purpose? (open).** The board keeps both as they are. *Source: brand.md §4; CLAUDE.md, phone layout; 03 §28.5.*
