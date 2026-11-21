# The control board: step 3, the Vault portal-links add-on (spec only, 2026-11-21)

Written by the session manager at Jason's request ("Vault Addon Service Module — Active-Account Publishing & Portal Link
Mapping (Spec-Only)"). It runs alongside Operations' cards and mockups (`03-cards.md`) and doesn't hold up Railway's first
pass. **No code, no schema change, no live action.** Built on `02-state-model.md` (§0a, §2.1, §2.2, §3a, §8, rule PL),
`decisions/0156` (the Vault), `0152` (Outside services' sign-in line), `0173` (one definition in `service-defs.php`), and
requirement R38 (integrations: declared touch points, the owner's hooks only, a named fallback). Read against the live
record at Sync 12:26 UTC (`37a212e`).

Each line is tagged **[src]** (sourced: a decision or Jason's words), **[des]** (designed here; needs Jason's OK, listed
in §7), or **[obs]** (observed in the live code).

## 1. What it is, in one paragraph

Our provider pages (billing, keys, console, sign-in) come today only from the documented link lists in
`service-defs.php`, and most slots are empty [obs, 02 §8]. Jason keeps the real ones in his Vault, which only his browser
can open [src 0156 §2]. This add-on lets him, from an unlocked Vault in his own browser, **publish chosen provider page
addresses** to the board. A published address is a plain URL. It is never a password, never a username, and never the
sealed item. The board then fills the matching empty slot on that module's card. The add-on also shows **which
providers have an account on file in his Vault**, as a yes and a date, and nothing more. It is a convenience: it never
holds anything, raises no trip, and the board works the same without it.

## 2. The module's manifest (§8 fields)

| Field | Value |
|---|---|
| `id`, `name` | `vault_portal_links`, "Provider pages from the Vault" |
| `company` | Traversence (our own add-on; no outside company) |
| `role` | **Service** [src: report §3; 02 §2.1] |
| `level` | **integration** add-on (R38): it touches the core only through the manifest's `portal_links` reader and the Vault's own publish hook. It never writes to core tables, alarms, holds or switches [src R38] |
| `touch_points` | (1) the Vault's one-time page (0156 §4) and, later, the staff extension (0156 §8), both of which publish; (2) the `portal_links` reader the cards use; (3) the board's log |
| `hosted_by` | Jason's browser (publishing) and Bluehost (storing the published URLs) [des] |
| `criticality` | none. It's never on a need's source list and never on a duty [des] |
| `feeds` | each paid or keyed module's card, Zone 4, "At <Provider>" row. No workflow |
| `required_keys` | none. It uses the Vault's own sign-in and fresh-proof rules [src 0156 §3] |
| `check` | (a) the store answers a read; (b) the publish endpoint is up; (c) the Vault's code seal matches its delivery (0156 §4). **It never calls a provider, and it never needs the Vault to be unlocked** [des] |
| `incident_radius_rules` | **Held: nothing, ever. Unaffected: everything.** Its own fault only greys its own card's Publish button, with the reason [des; P5, PL] |
| `fallback` | the documented links alone, with the "No … page registered for <Provider> yet" wording where a slot is empty [src O5 answer; 02 §8] |
| `fix_it_routes` | a rejected publish goes to that item's row in the Vault's publish list, with the reason [des] |
| `stage` | Jason's eight stages, §3 below |

## 3. Its eight stages (02 §3a), and locked vs unlocked

| Stage | For this add-on |
|---|---|
| 1. Selection and configuration | this definition, and its domain rules (§4.3) |
| 2. Integration (pre-flight) | checks (a)–(c). A failed check greys Publish on its own card; nothing else changes |
| 3. Activation | a person (Jason) switches it on |
| 4. Assigned | to each module's Zone 4. Never to a workflow |
| 5. Operational | readiness = coverage (how many priority slots are filled) and freshness (when each was last published) |
| 6. Maintenance | Publish greys with "In maintenance until <time>". Published links still show |
| 7. Deactivation | a person. Cards fall back to the documented links only. Published records are kept, not shown |
| 8. Removal | a person. The published records are deleted (they're only URLs), and the decision is kept in the log |

**Locked or unlocked is not a state of the add-on.** [des]
- **Vault locked:** nothing new can be published. What was published still shows, because it's stored as plain URLs. The
  board raises nothing about the lock; a locked Vault is normal.
- **Vault unlocked:** the Publish list appears inside the Vault's one-time page. Leaving it, or the Vault's 10-minute
  lock (0156 §4), simply ends publishing.
- **Stale:** a link not re-confirmed in 180 days shows "Published Nov 21, 2026 · not confirmed since" on the card. That's a
  word on the card, never an alarm. *(The 180 days is a proposal: §7 Q5.)*

## 4. The publish contract

### 4.1 What happens
1. In the unlocked Vault, an item for a provider (e.g. his Railway login) shows **Publish a page from this item**. [des]
2. He picks the provider from the module list (`service-defs` ids only, never free text) and the **one** type: Billing,
   Keys, Console or Sign in. He picks the URL from the addresses the item already holds (its sealed login address, or a
   page address saved in the item). Nothing is typed fresh. [des; src O5: "never invented"]
3. The browser sends only what's in §4.2. The server checks it (§4.3) and either stores it or refuses it, saying why.
4. The card's slot fills at once, with its source chip. The board's log records "Railway's console page published from
   the Vault by Jason, Nov 21, 3:40 PM MST". The record names only the type and provider. [src: 0156 §4 records; Times rule]
5. **Unpublish** sits beside each published slot, on the card and in the Vault. It removes only that slot. [src: a way back
   for every action]

### 4.2 What travels, and what never does
- **Sent:** `module_id`, `type`, `url`, the device's time zone, and a random publication id made in the browser.
- **Added by the server:** who and when.
- **Never sent, stored, logged or put in any alarm, thread, email, push or Pulse row** [src 0156 §2; Jason]:
  - the password, username, email or notes;
  - one-time codes or recovery words;
  - the Vault item's id or its sealed blob;
  - any query string, and any fragment that holds a value.
- **The account indicator:** "Account on file in the Vault · yes · Nov 21". It's sent as a separate, explicit flag, with
  no name and no account label. *(An optional label is §7 Q4.)*

### 4.3 The server's checks (each a refusal with its reason, never a silent fix)
- `https` only, with no user or password in the URL.
- **The host must belong to that provider, matched by registrable domain** (corrected 2026-11-21 after Jason's check). The URL's registrable domain (the name before the public suffix, e.g. `console.anthropic.com` → `anthropic.com`, `accounts.google.com` → `google.com`) must equal the registrable domain of one of that module's documented hosts in `service-defs.php` (links and contacts), or of a host in its `portal_domains` (below). Subdomains, sub-paths and ports are accepted: `console.anthropic.com/settings/limits`, `account.brave.com`, `accounts.intuit.com` all pass. `github.com` is refused for Railway: "That address isn't one of Railway's known domains." Matching uses the public suffix list, never a plain "ends with". [des, from 0156 §2's "warns where it doesn't match the company's known login page"]
  - *Why "registrable domain", not "host or parent":* read literally, "a parent of the URL's host must appear" would refuse real pages. Gemini's documented hosts are `aistudio.google.com` and `ai.google.dev`, so its sign-in page `accounts.google.com` would fail. Brave's only documented host is `api-dashboard.search.brave.com`, so `account.brave.com` would fail.
  - **Checked against the live definitions** (`37a212e`). Each module's documented registrable domains:
    - Anthropic: anthropic.com.
    - Google Cloud: google.com.
    - Gemini: google.com, google.dev.
    - Tavily: tavily.com.
    - Brave Search: brave.com.
    - Railway: railway.com.
    - Bluehost: bluehost.com.
    - QuickBooks: intuit.com.
    - GitHub: github.com.

    With the registrable-domain rule, every page named in §5 passes, except the **three known alternates** below.
  - **`portal_domains`, a new manifest field** for a provider's other real domains, each with its source. Never learned
    by itself. The three known today:
    - **Anthropic:** `claude.com`. Anthropic's Console has moved to `platform.claude.com`, so a current console, keys or
      billing URL saved in the Vault may be on `claude.com`.
    - **Railway:** `railway.app`, its older domain. Vault items saved before the move may hold it.
    - **Bluehost's cPanel:** it can sit on the server's own host name or on our domain with port 2083 (`CPANEL_HOST` in
      `.env`). The host's name isn't a secret; its value is read from `.env` at the check, never stored in the defs.
  - **A refusal never dead-ends** (§7 Q7): when only the domain is unknown, the refusal names it and offers you "This is
    <Provider>'s domain". Pressing it records the domain under that module, with your name and the date, and publishes.
    The next release that touches `service-defs.php` folds it in as a sourced entry.
- **Strip, never guess:** a query string is refused, not cut off ("This address carries values; save the plain page
  address in the item"). A fragment is allowed only as a path (`#/cpanel`), never `name=value`.
- **One URL per module and type** from the Vault. Publishing again replaces it, and the log keeps the old one.
- **The type is never inferred** from the URL, and a slot of one type never stands in for another. [src PL; O5]

## 5. Priority coverage (02 §8, O5)

The documented links today [obs, `service-defs.php` at `37a212e`], with the labels 02 §8 couldn't map:

| Module | Billing | Keys | Console | Sign in | Ambiguous labels today |
|---|---|---|---|---|---|
| Anthropic | Usage | Keys | Console | — | **Limits** (spend and rate limits page) |
| Google AI Studio (Gemini) | — | Keys | — | — | **AI Studio** (unmapped); **Rate limits** (a docs page) |
| Google Cloud | Billing | Credentials | Console | — | — |
| Tavily | — | — | Dashboard | — | — |
| Brave Search | — | — | API dashboard | — | — |
| Railway | Usage | — | Dashboard | — | — |
| Bluehost | — | — | — | Account | **cPanel** |
| GitHub | Billing | — | — | — | **The sync** (our own Actions page) |
| QuickBooks (Intuit) | — | — | — | — | **QuickBooks** (the app); **Developer app** |

**Locked by Jason (2026-11-21): the link-label rules.** Mockups and specs follow these now:
1. **Fixed order:** always Billing · Keys · Console · Sign in, under "At <Provider>".
2. **Label format:** the slot type is the main label. The provider's own label is added in small type only where it
   differs, e.g. "Billing · Usage", "Billing · Limits", "Console · Dashboard".
3. **Source tags:** "Documented", or "From your Vault · <date>".
4. **Missing links:** "No <type> page registered for <Provider> yet", with "Publish from your Vault" for Jason. Fix it
   names its page type and never borrows another link.
5. **Phone (390px):** two slots per row, no sideways scroll.
6. **Label mappings:** the rulings below are **approved**:
   - cPanel and AI Studio → Console;
   - QuickBooks (the app) → Console, Developer app → Keys;
   - Limits → Billing;
   - Google's Rate limits and GitHub's The sync are moved off the provider portal slots.

**Rulings on the ambiguous labels** (§7 Q2), approved by Jason on 2026-11-21:
- **Limits (Anthropic):** stays a second **billing**-type page, shown as "Limits" under Billing. It holds the spend limit.
- **Rate limits (Google):** a documentation page, **not a portal slot**. It moves to the card's Zone 2 "Its limits"
  line, so Billing stays empty until a real billing page is published.
- **AI Studio:** **console**.
- **cPanel (Bluehost):** **console**. It's the host's control panel.
- **The sync (GitHub):** **not a portal slot**. It's our own workflow's page, so it belongs to the Sync part's card as
  "Its runs". GitHub's Console stays empty until published.
- **QuickBooks (the app):** **console**. **Developer app:** **keys**, since the app's credentials live there.

**Left for the Vault after those rulings:** each of these stays "No … page registered for <Provider> yet" until
published.
- **Anthropic:** Sign in.
- **Google AI Studio:** Billing, Sign in.
- **Tavily:** Billing, Keys, Sign in.
- **Brave Search:** Billing, Keys, Sign in.
- **Railway:** Keys (the project's variables page), Sign in.
- **Bluehost:** Billing, Keys.
- **GitHub:** Keys (the token settings page), Console, Sign in.
- **QuickBooks:** Billing, Sign in.

## 6. Rendering notes for the four-zone card (hand-off to `03-cards.md`)

Zone 4 keeps its two rows (02 §8; report §1). They are never mixed:
- **On our system:** the board's own actions, such as Check now, Take out and Fix it.
- **At <Provider>:** four slots in a fixed order: **Billing · Keys · Console · Sign in.** Each slot is one of:
  - a link, labelled by its type (with the provider's label in small type where it differs, e.g. "Billing · Limits"),
    and a source chip: **Documented**, or **From your Vault · Nov 21**;
  - the words "No <billing / keys / console / sign-in> page registered for <Provider> yet". The link is greyed with that
    reason, and for Jason it carries "Publish from your Vault" (it opens the Vault, which asks for its own proof).
- A fault's Fix it names its type ("Fix it: Railway's keys page"). When that slot is empty, it says so in words and never
  falls back to another slot [src PL rows 128–135].
- The account indicator sits on the identity line: "Account on file in your Vault".
- **At 390px** the four slots wrap two per row, with no sideways scroll [src 0058 §11].
- **Every published URL opens in a new tab.** Sign in never fills anything. Filling is the extension's own job, and only
  on the exact site (0156 §8).

## 7. Questions for Jason (each with a recommendation)

**Jason's answers (2026-11-21):** "Approved all six recommendations in 04-vault-portal-links.md §7": (1) show both and
record his pick, with no silent replacement; (2) §5's label rulings; (3) publishing is owner-only; (4) no account label;
(5) the 180-day note, as words only; (6) the Vault's one-time page first, then the staff extension. "Still
specs/mockups only—no code or live-site changes." Q7 came from his domain check after that. He answered it in the pop-up: "Confirm on the spot (Recommended)".

1. **When the Vault and the documented list both have the same type and they differ.** *Recommended:* the documented
   link stays shown, and the card adds "Your Vault has a different <type> page", offering both. Your choice is recorded.
   Neither replaces the other by itself.
2. **The ambiguous labels:** adopt §5's rulings? *Recommended:* yes.
3. **Who can publish and who can see published links.** *Recommended:* publishing by you only (owner), since it's your
   Vault. Seeing them follows the board's access (executives and above), as the documented links do today.
4. **An optional account label** (e.g. "Traversence org" on the indicator). *Recommended:* no. It invites a username or
   email onto the board.
5. **Staleness.** *Recommended:* 180 days before "not confirmed since" shows, as a word only, never an alarm.
6. **Where publishing happens.** *Recommended:* first in the Vault's one-time page (built, code-sealed), and in the staff
   extension once its stage allows (0156 §9).
7. **An unknown domain on a real page.** *Recommended:* the refusal names the domain and offers "This is <Provider>'s
   domain" (owner only). That records it with your name and date, and the next defs release folds it in, so a valid
   page is never stuck.

## 8. Proof (step 4 tests; designed)

- **No secret moves:** the publish payload, the stored record, the log line and every alarm, thread, email, push and
  Pulse row are scanned for the item's username, password and notes, and none appear. A query string or a value
  fragment is refused.
- **Domains:** every §5 page and the three alternates pass (`console.anthropic.com/settings/limits`, `platform.claude.com/…`, `accounts.google.com`, `account.brave.com`, `accounts.intuit.com`, `railway.app/…`, the cPanel host with port 2083). Another provider's domain (`github.com` for Railway) is refused with its reason. A look-alike (`anthropic.com.evil.net`, `railway-app.com`) is refused, because matching is by registrable domain. A domain you confirm is recorded with who and when.
- **Strict typing:** an empty slot shows "No … page registered", and Fix it never borrows another slot's URL.
- **Non-interference:** with the add-on failing, deactivated or removed, every other module's state, holds, alarms and
  messages are unchanged. Its fault greys only its own Publish button.
- **Lock states:** with the Vault locked, published links show and nothing is raised. With it unlocked, Publish and
  Unpublish work, each logged, and Unpublish restores the slot's earlier state.
- **Precedence** (after Q1): a differing Vault URL never silently replaces a documented one.
- **Rendering:** the cards at 1280 and 390, slot order, chips and wording, with no sideways scroll.

## 9. What it needs later (when it's built; not now)

- A small store of published links: module, type, URL, who, when, zone, publication id, unpublished when. Its staging
  class is in `app/lib/staging-tables.php`.
- The `portal_links` reader in the manifest. Published links join the documented ones by type, after Q1's rule.
- A publish list in the Vault's one-time page, under the code seal.

Nothing here is built until the step-3 design is approved, and only as part of the one release.
