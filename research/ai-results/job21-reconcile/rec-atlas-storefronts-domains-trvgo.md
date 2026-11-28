# Platform atlas: storefronts-domains-trvgo (merged)

`research/platform-atlas/storefronts-domains-trvgo.md` · *Draft for human review. This entry reconciles two independent passes.*

**How to read the markers**

- **No marker:** both passes support the statement.
- **single-pass (pass1)** or **single-pass (pass2):** only that pass says it.
- **passes differ:** the passes conflict, or give different citations for the same point.
- All file:line citations are copied from the pass that made them.

**Both inputs are incomplete.**

- Pass1 stops in the middle of section (b), at `AccountDomains::termsFor()`. It has no (c), (d) or (e).
- Pass2 stops early in section (d). It has no (e).
- Section (e) is therefore empty below. Section (c) is entirely single-pass (pass2).

**Coverage**

Code files:
- `shop/domains.php`, `shop/index.php` and `app/lib/Storefront.php` (361 lines) are shown in full. Single-pass (pass1). Pass2 only says that "every code file is capped".
- `app/lib/Domains.php` is shown to line 520.
- `app/lib/AccountDomains.php` is shown to about line 453.

Other sources, single-pass (pass2):
- ADRs 0139, 0169, 0137, 0098 and 0096
- the charter, the brand guide and the PRD
- `site-audit/pages.md`

Not shown, according to both passes:
- the trvgo.us router `trvgo/index.php`
- `app/ui/storefront-view.php` and `app/ui/domains-view.php`
- `app/business/storefront.php`
- `workers/storefront-edge/`
- `/user/domains/`
- the `domains_tick` job
- the migrations

Not shown, single-pass (pass1):
- `trvgo/.htaccess`
- `Wallet`, `Billing`, `Orders` and `QuickBooksPay`
- the Name.com helpers (`namecom()`, `api()`, `ncDown()`, `asSandbox()`, `lookupLive()`) beyond `Domains.php:520`

Statements about the parts that are not shown come from the ADRs, not from code.

---

## (a) What each person can do, page by page

### Resident or traveler (visitor)

**Who counts as a visitor.** Passes differ: pass1 says "signed in or not"; pass2 says "who isn't signed in".

**`trvgo.us/<shop>`: a business's storefront**
- It shows the listing's public details, its published items and its pages as tabs (0139 §5a).
- It also shows the brand's live locations. Single-pass (pass2).
- Brand locations are reached at `trvgo.us/<brand>/<location>` (`Storefront.php:3-4`, `:121-137`).
- `trvgo.us/<shop>/<page>` addresses a single page. Single-pass (pass2).
- Buying, offers and messages happen on the item page on traversence.com.
  - Pass2 names the buttons: "Buy now" and "Add to cart".
  - Pass1 adds offers and messages.
- The page carries no script, no session and no cookie (0139 §5a). Single-pass (pass2).

**A storefront that isn't showing** gives a plain "isn't open right now" page (0139 §5a).
- Pass1 lists the causes as paused, unbuilt or hidden. It notes that the view drawing the page is not shown.
- Pass2 also lists "a plan below Core".

**A business's own domain**
- Once a connected domain is live, `trvgo.us/<shop>` returns a 301 redirect to it (0139 §5b).
- Pass2 also cites `Storefront.php:58-64`. Pass2 says the domain is served through the Fly.io edge. Single-pass (pass2).

**`trvgo.us/<code>`: a GO Code scan.** It shares the router, and shop names can never collide with codes (`Storefront.php:75-86`; 0139 §2). Single-pass (pass1) for this surface. Pass2 mentions the shared namespace only under 0096 in section (c).

**`pages.md`.** The storefront route is not listed in `site-audit/pages.md`.
- Passes differ on why. Pass1 calls `pages.md` the routing register. Pass2 says it only covers traversence.com.

**No visitor-specific surface.** There is no resident- or traveler-specific surface, and no Directory or Discovery integration in the code shown. Single-pass (pass2).

**`/shop/` as a guest**
- GO Codes and a Join prompt (`shop/index.php:96-103`). Pass1 adds "what a free account and a business claim add".
- The Domains department card (`:51-54`). Pass2 says this card appears for every account.

**`/shop/domains.php` as a guest:** a prompt to sign in or join (`shop/domains.php:30-32`).

### Member

**`/shop/`** (`shop/index.php:75-95`)
- Plans, storage packs, GO Codes, "Keep one video in 4K" and a month of Pro.
- A Pro trial. Single-pass (pass1).
- Everything goes into one cart (`:107-115`). Single-pass (pass1).
- A "Your domains" link to `/user/domains/` (`:53`). Single-pass (pass1).

**`/shop/domains.php`:** search a name and add it to the one cart with registrant details.
- Pass2 cites `shop/domains.php:2-6`, `:34`.
- The following details are single-pass (pass1) in section (a). Pass2 covers most of them in section (b).
  - Search covers the typed name plus `Domains::TLDS`: com, net, shop, store, co, us, biz, app and ai (`AccountDomains.php:132-176`; `Domains.php:33`).
  - Terms are 1, 2, 3, 4, 5 or 10 years, limited to what Name.com sells for that ending (`AccountDomains.php:41`, `:190-210`).
  - The member must confirm 18+ and give full registrant details (`:259-289`).
  - Payment is from the wallet (`:386+`).

**`/user/domains/` (the Domains tab).** The page code is not shown.
- Features named by the passes:
  - renew and auto-renew
  - DNS and email records
  - transfer away
  - history
- Passes differ on the wording of two features:
  - Pass1 has "forwarding, DNS records or nameservers"; pass2 has "where the domain points".
  - Pass1 has "Something isn't right"; pass2 has "tickets".
- Passes differ on the source:
  - Pass1 cites 0169 §1a and `AccountDomains.php:12-16`.
  - Pass2 cites `pages.md` §3 and `AccountDomains.php:12-17`.

### Business (an owner or a team member acting as the listing)

**`/shop/`:** the business's plans, packs and services (`shop/index.php:55-74`). Passes differ on the extras:
- Pass1 lists White Glove and quotes. It says billing changes are limited to those who may manage billing (`:25`, `:58`).
- Pass2 lists a Coming list: creator collaborations, licensing, and email/text/push packs.

**`/shop/domains.php?for=listing:<id>`:** buys a domain for the business (`shop/domains.php:20-23`).
- It needs Domain (edit) plus Plan & billing (edit) on the team (`AccountDomains.php:105-117`, `:263`).
- Acting as the business picks the listing automatically. Single-pass (pass1).

**`/business/<id>/domains`:** the business's Domains tab (`shop/index.php:53`). Its code is not shown.
- The storefront domain shows there read only (0169 §1a). Single-pass (pass2).

**Portal → Storefront & pages** (Core and up; 0139 §5a). The portal page is not shown; pass2 adds that `app/business/storefront.php` is not shown either. It covers:
- set-up and the shop name
- theme, logo and cover
- pages
- Preview, Go live and Pause
- the accent setting. Single-pass (pass1) in section (a).
- page states Draft, Live and Hidden. Single-pass (pass2).

The rules behind the portal page are in `Storefront::start/save/setStatus/setPhoto` (`Storefront.php:140-197`, `:223-233`). Single-pass (pass1).

**Connect your domain:** bring your own, or buy one through Name.com (0139 §5b).
- The following are single-pass (pass1):
  - For bring-your-own, the business is shown the records to add, the site checks them, and the domain goes live (0139 §3).
  - A bought domain has its records set automatically.
  - Renewal, auto-renew, the transfer code and disconnect are available (`Domains.php:176-304`, `:386-484`).

### Staff (admin)

- Market 360° → Storefronts and Market 360° → Custom domains (0139).
- The admin pages are not shown.
- `Storefront::all` (`Storefront.php:351-360`) is the list query.

---

## (b) How it works underneath

### Storefront (`app/lib/Storefront.php`)

**One storefront per listing.** The table `storefronts` is keyed by `entity_id`. Single-pass (pass1).

**Plan feature and statuses**
- The plan feature is `storefront` (`:16`).
- The plan gate is `Plan::allows` (`:106-110`). Pass2 gives the call as `Plan::allows(MarketBiz::tier(...))`.
- The statuses are Building, Live and Paused (`:17`).

**`setStatus`.** Passes differ on the line range: pass1 cites `:188-197`, pass2 `:189-197`. Their content is compatible:
- Pass1: a storefront that has been live can only pause; it never returns to Building.
- Pass2: only Live or Paused are allowed, and the plan is re-checked before going Live.

**`start()`** (`:140-152`). Each pass gives a different detail; they are compatible.
- Pass1: it builds the name from the listing's name and adds a number on a clash.
- Pass2: it creates the storefront as Building with the Canyon theme.

**Names** (`:94-104`). A name must be:
- 3–40 characters: letters, digits and single hyphens
- not reserved (`:38-40`)
- not the shape of a GO Code: 6 characters from the code alphabet (`:75-79`). Pass2 also cites `:41`.
- not an existing `short_codes` row
- unique

**Routing**
- `looksLikeShop` is the router's test (`:82-86`).
- `resolve` reaches a location only under its own brand (`:128`). Pass2 also cites `:132`. Pass1 cites `:121-137` for the range.
- `resolve` reads up to three path parts. Single-pass (pass1).

**Parent brand** (`:171-182`)
- A storefront may name a parent brand only if the editor runs both. This is checked through the `$mayParent` callback.
- A location cannot be a parent. Single-pass (pass1).

**Showing to the public** (`:200-206`) requires all three:
- status Live
- the listing not suppressed. Pass1 names the field `entities.is_suppressed`.
- a plan that still allows it

**Preview link** (`:209-218`): an HMAC keyed on the ISO week. It accepts this week's key and the key from 7 days earlier.
- Pass1 gives the key as `entity|ISO-week`.

**Look**
- There are four themes and their block styles. Pass1 cites `:19-24` and `:31-36`; pass2 cites `:19-36`.
- An accent under 3:1 contrast against the theme's cards is refused (`:167`).
- An accent between 3:1 and 4.5:1 is kept, with a warning (`:304-311`).
- Contr