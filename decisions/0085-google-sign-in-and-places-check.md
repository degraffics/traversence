# 0085. Continue with Google; the crawler checks listings against Google, paced and capped

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0079 (standing), 0084 (web search allowance; the Google notes)

## Context

Jason set up a Google Cloud project, "TRVS Crawler", with an OAuth client for traversence.com and the Places API
(New) turned on. He asked for it to be used as a sign-in and as a source, without unforeseen costs. The Google side
was set up step by step on 2026-10-14:

- **The Maps key** works only for Places API (New), with no website restriction (it's used from the server, never on
  a page). It's kept only in Railway as `GOOGLE_PLACES_KEY`.
- **Google's daily limits:** Text Search 200 a day and Get Place 100 a day. Every other Places request (photos,
  reviews, nearby search, media, content summaries) is 0; autocomplete is 1, the lowest it allows.
- **A $10 monthly budget** (alerts only), emailing at 50%, 90% and 100% of actual spend. Spend cap enforcement doesn't
  cover Maps, so the daily limits are the real stop.
- **The OAuth client:**
  - origins `https://traversence.com`, `https://www.traversence.com` and `https://trvgo.us`;
  - redirects `https://traversence.com/api/auth/google.php` and the www. form;
  - published (In production, External), with no logo, so no brand review is needed. Adding the logo later means
    submitting for brand verification.
  - The client ID and secret are in `.env` on Bluehost (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`).

## Decision

### 1. Continue with Google (`app/lib/GoogleAuth.php`, `/api/auth/google.php`, `app/ui/google-button.php`)

**What Google gives us:** only openid, email and profile. That's the person's Google ID (`users.google_sub`,
`2026-11-21_google_sign_in.sql`), their email (only when Google has confirmed it) and their name. Never a Google
password, contacts or anything else.

**The flow:**
- A one-time state is kept in the session (15 minutes).
- The code is exchanged server to server, with our secret.
- The ID token's issuer, audience, expiry and confirmed email are checked.

**Who gets signed in:**
- **The account with that Google ID.**
- **Otherwise, the account with that email, which is then linked.** If that email was never confirmed, its password
  is cleared and all its sessions are ended first. A stranger who registered someone else's address keeps no way in.
- **A different Google account using the same email** is refused, with a note to sign in with the password.
- **A revoked account** gets the same message as password sign-in (0079).

**Someone new finishes signing up on `/register.php?google=1`.** They don't type an email or a password, but go
through everything else everyone does: 18 or older (also checked on the server), verification, privacy choices and
the terms, and an invite is accepted if there was one. The account's email is confirmed, because Google confirmed it.
There's no password; "Forgot password?" sets one.

**Where the button shows:** "Continue with Google" on sign-in and "Sign up with Google" on sign-up, in Google's own
style. It shows only when the keys are in `.env` and the database update has run.

**What Settings shows:** whether it's on, and the database update in Database updates.

**The privacy page** says what Google sends and that nothing else is received.

### 2. The crawler checks listings against Google (`workers/crawler/crawler.py` `places_check`, `/api/crawl/places.php`)

**What each check asks:** one Text Search (New) per listing, for only the place's ID, name, address and status. The
fewer fields, the cheaper the call.

**Which listings the site picks:**
- on the site and not marked closed;
- never one whose address is kept private (`ListingSafety`, such as shelters): those are never sent anywhere;
- not checked in the last 120 days;
- the newest first.

**When Google's place is the listing:** when it has the listing's name (or one of its other names, 0084), and its
street number. A listing with no street number matches on its ZIP, only when Google's address has none either.

**What's kept:** only Google's place ID, the date it was checked, and whether it matched. Google's terms allow keeping
the ID; its name, address, hours, photos and reviews are not stored.

**Closed:** a place Google lists as permanently closed becomes a "Closed?" item in Review (once per listing). A person
checks; nothing is closed on Google's word alone.

**The pace:** the same as web search (`/api/crawl/usage.php` with `service: 'places'`, counted apart).
- Never past 90% of `GOOGLE_PLACES_MONTHLY` (Railway, default 3,000), spread over the month.
- At most `PLACES_PER_RUN` (default 5) a run.
- `GOOGLE_PLACES=off` in `.env` stops it.
- Google's daily limits and the budget sit above all of this.
- If Google refuses (a 403 or 429), the run stops checking.

**What Settings shows** under Paid services the crawler uses: this month's checks, and where it stops.

**Tested in the sandbox:**
- Sign-in, tested with a stand-in for Google's answer:
  - a new person, refused without 18+, then created and signed in;
  - the same person again, signed straight in;
  - the wrong audience, refused;
  - an unconfirmed account, its password cleared on linking;
  - a confirmed account, linked;
  - a revoked account, the revoked message;
  - another Google account with the same email, refused.
- The Places check, with a stand-in for Google's answer: of three "Holbrook Unified School Dist 3" listings, only
  the one at Google's street number matched. Google's "closed" became one Review item, and the month's count read
  3 of 3,000.

## Not done

- Google's details shown on a Google map, credited to Google: the terms allow that, but we don't need it.
- The app's logo on Google's sign-in screen, after brand verification.
- A second, browser-only key restricted to traversence.com, if a Google map is ever put on a page.

## Fix, 2026-10-14

The button didn't show on the live site. The site reads `.env` when it first connects to the database, and the check
for the Google keys ran before that. Now it connects first, and the sign-in and sign-up pages load the site's settings
before anything is printed. Checked with the keys only in `.env`, as on the server: the button shows on both pages.

## Amendment, 2026-10-14: a day's share

The first live readings: 25 Google checks within the first few runs. The pace lets the month-to-date share go in one
day (on the 14th, up to about 1,200, until Google's own 200 a day stopped it). Now both counters, web search and the
Google check, also allow no more in a day than the month's stop divided by the days in the month: about 88 Google
checks and 29 searches a day. An unused morning never turns into a burst later. Settings shows the day's share and
today's count. A site-only change (`/api/crawl/usage.php`): the crawler needs nothing new.

## Fix, 2026-10-14: Bluehost's firewall and Google's answer

The first live sign-in came back "Not Acceptable!" (ModSecurity). Google's answer lists the granted scopes as links
(`scope=… https://www.googleapis.com/auth/userinfo.email …`), and the host's firewall refuses a request carrying a link
to another site. Google now answers after "#" (`response_mode=fragment`), which the browser never sends to the server.
A small page at the same address reads it and sends on only the code and state. The button starts with `?start=1`.
Checked with a stand-in for Google answering the way it does: the server saw only `?code=…&state=…`, and the sign-in
finished.
