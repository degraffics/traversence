# 0094. TRVGO: short links and QR codes

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** commercial.md §0.A and §1 (commercial-2, Jason 2026-10-13), 0034 (trials until billing opens), 0044/0076
  (private shelter locations), 0051 (counts, never who; never advertising), 0058 (page and card standards), 0069 (folders)

## Context

commercial-2 makes **trvgo.us** Traversence's edge: short codes and QR routing for physical touchpoints, and later a
merchant's own-domain storefront. Jason's direction (2026-10-15):
- the QR code generator is a **standalone service, no listing required**;
- **Free gets static codes; dynamic codes and short links are Core and up**;
- the short codes are **used by our own sharing tools** too, so it isn't only listings but people's actions on the site;
- if a plan ends, codes **fall back** (nothing printed breaks);
- **no review gating and no ad pixels**;
- clients' own domains are **staged for later**.

## Decision

### trvgo.us is its own domain and folder

- On Bluehost it's an **addon domain** whose document root is `public_html/traversence/trvgo/`, a third folder beside
  `website/` and `app/`. It serves only the router (`trvgo/index.php`, `trvgo/.htaccess`), never the site's pages.
  It reads the same database through `app/lib/`. Bluehost's AutoSSL covers it.
- `trvgo.us` alone opens the QR service at traversence.com/qr/; `trvgo.us/{code}` goes where the code points.
- A scan starts **no session and sets no cookie**: it loads only the database connection and the code lookup.

### Codes

`short_codes` (`app/lib/ShortCodes.php`, migration `2026-11-27_short_codes.sql`). Six characters from
`23456789abcdefghjkmnpqrstuvwxyz` (nothing to misread off a sign: no 0/1/i/l/o); typed in capitals still works.
- **page:** a page on the site, made by its Share button. One code per page, the same for everyone, never edited. Only
  our own pages; never admin pages or APIs (`ShortCodes::sitePath`).
- **url:** a member's or a business's link.
- **vcard:** a contact card. Either a listing's own (always its current public details; a shelter's private location
  never on it) or one a member writes.

Redirects are **302** (temporary), so a changeable code can be pointed somewhere new; no browser keeps the old one.

### Static is free for anyone; saved codes need a plan

- **Static codes** hold the link, text, phone, text message, email, Wi-Fi or contact card itself. Anyone makes them at
  /qr/, signed in or not; nothing typed is kept. Free.
- **Saved codes** are trvgo.us codes: change where they go after printing, switch off, scan counts. They need the **QR
  plan** (a person, `member_plans`, product `qr`) or a listing on **Core and up** (`ShortCodes::DYNAMIC_TIERS`). Until
  billing opens the QR plan starts as a trial, like a listing's paid plan (0034).
- **When a plan ends, nothing printed breaks:** a business's codes go to its listing page; a person's keep their last
  destination and can't be changed until the plan is back on. Renaming and recoloring stay open.
- The look (dark and light colors, round dots) is checked so every code scans: the code darker than its background,
  contrast 4:1 or more.

### Drawn on our own server

chillerlan/php-qrcode 5.0.5 (MIT; with php-settings-container 3.3.0) in `app/vendor/`, loaded by
`app/vendor/qr-autoload.php`, no Composer. Never an outside generator, which would see every link. SVG for print, PNG
for screens. A trvgo.us link makes a small, easy code (25×25 modules).

### Counts, never who

`short_scans`: per code, day and kind of device (iPhone, Android, computer, other). No IP, location, account or cookie.
Link previews and bots aren't counted. No ad pixels or retargeting anywhere on trvgo.us.

### Sharing uses short links

`js/short-link.js` (every page):
- `tvShort(url)` gives the page's trvgo.us link. Share buttons warm it as they come into view or are touched, so the
  phone's share sheet opens with the short link straight away. Wired into the Engage bar's Share, the photo viewer's
  "Share outside Traversence" and a journey's Copy link.
- `tvQr()` / `[data-qr]` shows the page's QR card (scan, download for print, copy link). It's on the Engage bar, a
  listing's action column and a journey.

### Save contact

A listing's action column gains **Save contact** (`/api/vcard.php?listing=`): a vCard 3.0 the phone adds to Contacts.
It's counted for the business as `save` in `listing_actions` (Business Insights, "Contacts saved"), not for our team or
the listing's own people.

### Misuse

Admin → Safety → **Short codes** (`/admin/short-codes.php`) finds a code (by code, address, name or email) and switches
it off for everyone with a reason the owner sees. The owner can't switch it back on; codes are kept, never deleted.
Each member has a guard of 500 codes.

### Not now

- **Clients' own domains** (CNAME, automatic certificates): later, on Cloudflare for SaaS or a small Railway server,
  since Bluehost can't issue certificates for other people's domains.
- **The $4.95 designed static code** (logo, print-ready vector files): the design options are open to everyone until
  billing opens; logo upload comes with storage.
- **Device and schedule routing, lead forms, event check-in:** later, if wanted. Never review gating or pixels.

## Checked on a copy of the site

- **Codes read back exactly:** seven static codes (link, round brand colors at high error correction, Wi-Fi with
  special characters, text message, email, an accented contact card, long text) read back exactly.
- **Colors that won't scan are refused:** too-close or light-on-dark colors.
- **Sharing:**
  - a page's code is the same from its path or its full address;
  - admin pages, other sites and scripts are refused;
  - a scan redirects (302), capitals work, an unknown code says it isn't active (404), trvgo.us opens /qr/;
  - a Facebook link preview isn't counted; no session file is made.
- **A person's saved codes:**
  - saving is refused without the plan; the trial starts it;
  - a code changes where it goes, switches off (404) and on;
  - another person's code is refused;
  - when the plan ends the code still works, a change is refused, renaming works.
- **A business's saved codes:**
  - a Free listing can't save codes and a Core listing can;
  - the listing card carries its current details;
  - dropped to Free, its code goes to the listing page;
  - Save contact is counted.
- **Our team's switch-off:** the code stops, the owner sees why and can't switch it back on.
- **At 390px:**
  - /qr/ (preview as you type, My codes with the right-edge actions and the 30-day bars) and a listing (Save contact,
    QR, Share copies the trvgo.us link) fit with no sideways scroll;
  - cards sit 3px from the edges.
- **Search:** the 24 standard searches are unchanged.

**Addendum (2026-10-16, print SVG).** The SVG download is drawn by `Qr::svg()` itself from the code's matrix, not by
the library's SVG writer, which drew every square separately and the light squares too (hundreds of white boxes, hard to
recolor in Illustrator). Now: one `<rect id="background">` in the light color, and one compound `<path id="code">` whose
squares are merged into outlines (holes wound the other way, so it fills right under either fill rule). Rounded dots are
one compound path of circles, with the corner squares and alignment square traced as shapes. A preview's faint corners
are a second path. Checked module by module against the matrix (no differences). PNG is unchanged.
Revised the same day (Jason): a downloaded SVG (`download=1`) has **no background**, only the code's path; the
4-module quiet zone stays in the viewBox as empty space. SVGs shown on a page keep the light rectangle. The PNG keeps
its background.
