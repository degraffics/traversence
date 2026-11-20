# Jason's "Order Processing Review" against what's built and decided (2026-11-20)

Jason, 2026-11-20: "review this and let's see how it helps" (a .docx: an outside write-up of a hybrid order model, eight
pillars). Checked by the session manager against `research/order-system-map-2026-11-19.md` (the map, live code at Sync
2026-11-19 16:21) and the decisions it cites. The write-up is a checklist, not a source: nothing in it is built unless a
decision or Jason's own words say so (builder-playbook §3). Each row: **Built** (decided and live), **Decided, not
built**, or **Open** (no decision: a question for Jason).

| Its idea | Here | Where |
|---|---|---|
| One parent order, line items each with their own state | **Built** | `orders` + `order_lines.item_status` (~25 statuses), three status layers (map §3) |
| Proof approval is the hard gate before production | **Built** | signed final approval (initials, two boxes); approve blocks while a proof waits; workflow's `proofs_approved` before production (map §3 steps 7–8). Open: a printed item with no proof can be sent (map §5, "ask Jason") |
| Design and production as separate lines (design fee earned at sign-off; production refundable until plated) | **Open** | projects and parts exist, creative items' finals release when there's no print (map §3a); no rule on a cancel after approval. Linked open item: a refund of printed work doesn't cancel the open work order (map §5) |
| Vendor routing with print-ready files | **Built** | work orders, vendor's page `/wo.php`, file preference per vendor, 4over paste (map §3 steps 11–12, §3a) |
| Blind drop-ship, our brand as sender | **Built (method)** | `WorkOrders::DELIVERY` has `blind_drop`; 0099 names our return address for blind shipping (d1). Not checked: what the packing slip carries |
| Tracking back to the client under our brand | **Built** | vendor marks shipped with tracking; "on its way" email when the last item is out (map §3 step 12) |
| Pay in full, deposit, or later; capture at a milestone | **Built (our way)** | in full, deposit %, invoice later, outside, Release to production before payment (0101 10-28). No card authorize-then-capture for our own orders |
| Webhook idempotency | **Built** | `order_ledger.request_key` unique (inferred, map §6); Marketplace: each webhook event once |
| Subscriptions: renewals, failed cards, proration | **Built (plans)** | `subscriptions` + `subscription_charges`, card on file; a declined plan ends on Free (0108). Marketplace renewals have no webhook handler (map §6) |
| One-off digital products | **Built** | one-offs (0154), Marketplace downloads (c5) |
| Revenue by kind, vendor cost matched to the invoice, net of card fees | **Decided** | 0098: margin = paid − (vendor costs + payment fee); Cost tab COGS (0130); 0166 §2 the site's own ledger, only the wallet built |
| Sales tax by item kind | **Built (QuickBooks)** | tax at approval on the QuickBooks invoice (0101); by-kind taxability is QuickBooks' setting, not ours |
| Reorder: exact repeat vs update and reprint | **Built** | `Collateral::reorder`: same art (approved proof reused, client confirms with initials) or with changes (new proof round), at today's prices (0101 10-28, 10-30) |
| Version-locked proofs, approved file only to the vendor, audit trail | **Built (mostly)** | proof versions, signed approval, files released only when paid (0101 10-27). Not built: a hash of the exact approved file |
| Artwork preflight (bleed, resolution, colour, fonts) | **Open, waiting** | nothing checks artwork (map §3a); 0176 R4's order file check waits on Jason's limits |
| Client uploads artwork; nudges when assets are missing | **Open** | today only our team uploads; no rule either way (map §3a) |
| Design capacity (slots per week) | **Open** | no decision |
| Self-serve cart for print | **Different on purpose** | print is quoted and built by our team; the Shop's card is "Ask for a quote" (map §2) |
| Set expectations before money is taken | **Decided today** | 0135 note (Jason, 2026-11-20): stocked or back-ordered known before paying; made to order and services confirm first |

What it adds for the Orders session: the **Open** rows are questions for Jason, asked one at a time with the map's six,
never built as choices. The order file check's limits are already on his list.
