# ADR 0156: The Vault: passwords only their owner can open

**Status:** Accepted (Jason, 2026-11-11, in conversation): stage 1 to build. Stages 2 to 4 wait on their gates (§9).
Amends `decisions/0155` (§1 below: vaults for customers, only with their explicit consent) and builds on `0152` (Outside
services and its sign-in line), `0145` (deliveries, the zip check, the sync's live mirror), `0050` (data use) and
CLAUDE.md "No income, no expense".

## Context

Jason needs his outside services' logins and passwords in reach ("I really need login and passwords with where to sign in
links"), and asked for "a two-step verification system of our own that would show a password we have saved but also have
it encrypted within the database so only when I ask for it is shows". Then: "I like this as our internal vault"; "couldnt
we also make is a system wide option … it would of course need a consent and solid terms and conditions"; "Ours as the
free, built-in vault with the option to purchase or bring in your own vault system". A vault whose key the server holds
fails to anything that reaches the server (shared hosting, a leaked FTP password, a bug in any page), so it's built so the
server can never read it.

## Decision

### 1. Who it's for

Stage 1: Traversence's own vault, for staff (owners and executives first). Later, members and businesses (§9), **only with
their explicit, recorded consent** to the vault's terms when they turn it on, withdrawn at any time (export, then delete).
Three paths, never a lock-in: **the built-in vault** (free, with consent); **1Password through us** (paid, 0155); **bring
your own** (1Password, Google, Bitwarden…: we keep only links to their items, as Outside services does, never passwords).
Import from a Google or 1Password export, and export everything, both done in the browser so the file is never readable on
our server.

### 2. Only the owner's browser can open it

- A random **vault key** locks the items; each **item has its own key**, sealed by the vault key (a passphrase changes
  without re-encrypting everything, and an item can later be shared with one person, sealed again for them).
- The vault key is stored only sealed, several ways, any one of which opens it: **passphrase + passkey** (a vault
  passphrase separate from the sign-in password, and the passkey's PRF secret where the browser has it, so the vault needs
  what you know and the device you hold); **each registered device**; **the recovery words** (§5); a **synced passkey or
  security key** kept as a spare.
- Encryption and decryption happen in the browser (Web Crypto: AES-GCM; the passphrase through a deliberately slow key
  step). The server keeps only sealed data it cannot open. No one at Traversence can read a vault.
- **Each item is sealed to its exact login address** (part of its encryption): a changed address fails to open and warns.
  Its **Sign in** opens only that address, and warns where it doesn't match the company's known login page (0152).

### 3. Opening it

- **Your fingerprint as your ID is the passkey:** Touch ID, Windows Hello or the phone's check happens on the device; only
  its signature reaches us, never the fingerprint. No device fingerprinting (tracking, and weak).
- **Registered devices only;** a new one is approved from one you have, or through recovery (§5).
- **Fresh proof for each reveal:** a passkey (or the emailed code where there's no passkey) within the last 5 minutes.
- **Location as a signal, not a wall:** a new network or country asks for an extra check and alerts; outside the U.S. is
  refused unless a trip is allowed. No hard IP allowlist.
- **Single-use reveal tokens:** 60 seconds, bound to the session and device, used once.
- **One item at a time:** the server hands over one sealed item only after the checks, never the whole vault. Limits: a few
  reveals a minute and a cap a day; anything unusual locks the vault.

### 4. The page and its code seal

- **No standing vault page:** after the checks, the site makes a **one-time page** (a minute, bound to the session and
  device, opened once), with its own rule that only its exact scripts may run, nothing from other sites, never framed.
- **The code seal:** the vault's files carry the checksum from the delivery that brought them (`deliveries/`, 0145). Before
  making each page, the server compares; any difference refuses the page, locks the vault and raises a critical Pulse case.
  The hourly audit checks the seal too (every few minutes once customers use it).
- **Showing:** Copy by default (the clipboard clears after 30 seconds); Show for 10 seconds, blurred until hovered. Locks
  after 10 minutes unused.
- **Every reveal is announced** to the owner ("Railway password copied by Jason, Phoenix, Nov 11 10:02 AM MST, this
  computer"), with **Lock the vault** in the email and in the admin, and a permanent record that can't be edited.

### 5. Recovery

- **The recovery words:** 24 words, shown once at setup on an **Emergency Kit** made in the browser: print it, or download it
  as a text file or a printable page. **Never emailed** (the server would see it, and inboxes are the most broken into);
  a reminder with no words in it is emailed instead, and once a year.
- **Used once:** recovery with the words always makes a new set at once; the old ones are dead (Jason, 2026-11-11). Recovery
  also needs the emailed code and a fresh passkey on the new device, and **alerts every device and the email** with Lock one
  tap away. No waiting period.
- **A passkey in an authenticator or a security key** (iCloud Keychain, Google Password Manager, 1Password, Microsoft
  Authenticator, a YubiKey) is a recovery key of its own. **Authenticator codes** (TOTP) are an extra check at recovery,
  never a key: the server knows their secret, so they can't unlock anything.
- Lost recovery words, with access: make new ones. A key seen by someone: change keys (a new vault key, items sealed again,
  new words).
- **Emergency access** (optional, off unless turned on): a named person may request access; the owner is alerted and only if
  they don't decline within a set time (7 days) is the person given the items chosen. Removable at any time. Accounts stay
  single-owner.
- Lost everything: the vault can't be opened, as with every strong vault; each service's own "forgot password" is the
  way back.

### 6. Super Admin Takeover (Jason: "the most strict process")

**A. Restoring the way in:** for critical accounts that have lost every way to reach their vault: the account's sign-in
given back, lost or stolen devices and old sessions cleared, a new device and passkey registered. It never reads a vault:
the owner then opens it with their own passphrase or words, so an impostor who fooled the process reaches a vault they
can't open. The process (Jason, 2026-11-11: "No second approval"):
1. **A documented request:** the reason, with ID and proof of standing (the account's credentials or, for a business, its
   documents: registration, court order, death certificate). The checks' cost is paid by the requester.
2. **Review by the super admin,** who confirms the request and whether the emergency still qualifies.
3. **Temporary access under explicit terms:** if it qualifies, the super admin issues time-limited access (enough to
   register a new device and passkey, then it ends), on terms the requester accepts first.
Kept beside it, adding no approver: a **notice** to the account's email and devices when a takeover is requested (a real
owner learns of a false request and can answer it), and a **permanent, unchangeable record** of the request, the review,
the decision and what changed, with a notice to the account afterwards.

**B. No assisted recovery (Jason, 2026-11-11: "we dont want to keep anything in case of this event, so we follow the same
existing rules standard to the industry"):** Traversence keeps nothing that could open a vault, on the server or off it. An
owner who has lost every key (passphrase, recovery words, devices and spare passkeys) has lost the vault; a takeover gives
back the account and the vault starts empty. The way back is planned ahead by the owner: the recovery words, a spare
passkey or security key, or Emergency access (§5).

### 7. Terms (before any customer, reviewed by counsel)

Traversence can't open or reset a vault, and keeps nothing that could; a lost passphrase and lost words (with no spare
key or Emergency access) mean lost data; what's kept (sealed items, the record of reveals) and for how long; the consent and how to withdraw it; the takeover
process. The FAQ says the same in plain words.

### 8. The browser extension and phone apps (later)

A Traversence browser extension (Chrome and Edge first) lists the saved logins **for the exact site only** under its
sign-in field and fills them (a fake site gets nothing), and offers to save new ones. Phones need a Traversence app as
Android's autofill service and an iPhone password provider. Passkey sign-in to Traversence itself comes with them.

### 9. Stages and their gates

1. **Traversence's own vault** (staff), **with the browser extension for staff** (Jason, 2026-11-11: "I also want the browser
   extension so I can start using it with my own vault"): zip 1 the vault, then the extension (Chrome and Edge; loaded in
   developer mode first, then an Unlisted Chrome Web Store item), then zip 2 (the one-time page and code seal, signals,
   limits, age reminders). Use it for months.
2. **For customers, opt-in with consent,** only after: an outside security audit and penetration test passed; cyber-liability
   insurance; the terms reviewed by counsel; the vault page on its own hardened host (not shared Bluehost); the code seal
   checked every few minutes; an incident plan. Then an invite-only beta for a few business owners, then everyone (free at
   a basic level; business features such as team sharing in paid plans).
3. The browser extension for customers (a public store listing), after stage 2's gates.
4. Phone apps with autofill, and passkey sign-in to Traversence.

### 10. Costs

- Running cost: almost nothing (small storage, free passkeys, free system emails). The audit and insurance are stage 2's
  real costs, quoted before it starts.

**Note (2026-11-11, built: `vault_20261111.zip`, stage 1 zip 1):** System Configuration → **Vault** (`/admin/config/vault`,
owners and executives; `Access` 'config.vault'). `app/lib/WebAuthn.php` checks passkeys (ES256 and RS256, user
verification required, the sign counter); `app/lib/Vault.php` keeps the sealed data in `vault_meta`, `vault_creds`,
`vault_items` and `vault_log` (made on first use, in the main database); `/admin/api/vault.php` is its one endpoint;
`js/tv-vault.js` does all the sealing (AES-GCM; the passphrase through PBKDF2, 600,000 rounds, joined with the passkey's PRF
secret; each login sealed to its exact sign-in address; the site index an HMAC of the address, so the server can't read
which sites). Built as decided: a fresh passkey within 5 minutes and a single-use 60-second token for each password, one
at a time; every one emailed with a lock link (no sign-in needed); 40 an hour locks the vault; it closes after 10 minutes
unused; Copy clears the clipboard after 30 seconds, Show lasts 10. A new computer is added with an emailed code and its
own passkey, and opens nothing until a device that has the vault gives it the key (sealed by the passphrase alone until
that device's first use, when it's tied to its passkey too) or the recovery words are used. Asking for the words' sealed
key counts as using them: new words must be saved before the vault opens again. Zip 2 (the one-time page, code seal,
location signals, age reminders) and the extension follow.
