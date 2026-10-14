# 0084. The crawler's web search can't run past its allowance; listings keep the other names they go by

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0062 (web search usage on the dashboard), 0074 and 0081 (merging the same place), 0083

## Context

- **Tavily** emailed Jason on 2026-10-14: 80% of October's allowance used. The worker could make up to 13 web searches
  a run (`MAX_SEARCHES` 10, `LEARN_PER_RUN` 3). Once its schedule really ran every 5 minutes (2026-10-14), that's up
  to about 3,700 a day against a plan of 1,000 a month. The worker reported its count to the site (0062) but never
  stopped at the allowance.
- **Infinia at Show Low:** the NPI Registry brought "Infinia At Showlow" at the same address, phone and ZIP, and the same
  kind of place, as the listed "Tall Pines Care And Rehab, Inc". It's the same facility under a new name. The Helper
  asked a person. Jason: "Maybe we have an alias name for these issues."

## Decision

### 1. Web search has a hard stop and a pace (`workers/crawler/crawler.py`, `/api/crawl/usage.php`)

**Before each run, the worker asks the site how many searches it may make.** The site keeps the month's and the day's
count, and allows the smallest of three numbers:
- **The stop:** never past 90% of the plan's monthly allowance (`SEARCH_MONTHLY` on Railway; `WEBSEARCH_STOP_PCT`
  in `.env` changes the 90).
- **The pace:** no further into the allowance than the month is gone. By the end of the 10th of a 30-day month,
  that's a third. So the allowance lasts the whole month instead of going in the first runs.
- **`MAX_SEARCHES`** per run, as before.

**When it searches nothing:**
- `WEBSEARCH=off` in `.env` stops web search from the site, with no Railway change.
- If the site can't be asked, the worker makes no searches that run.

**Counting:**
- A run stopped at its time limit still reports the searches it made.
- Settings shows the service, this month's count against the allowance, where it stops, and today's pace.

**Tavily itself** stops at the plan's limit unless PAYGO (pay as you go) or auto-upgrade is turned on in its
dashboard. Both stay off, so nothing is billed past the plan. Brave, the fallback, is paid per search: its key isn't
set.

### 2. Other names a place goes by (`app/lib/Aliases.php`, meta `aka`)

**What a listing keeps:** a list of its other names: a former name, its legal name, a registry's spelling.
- **Shown** on its page as "Also known as …".
- **Searched:** the main search, and the directory's search box.
- **Matched by the crawler** as its name. Next month's NPI file bringing "Infinia At Showlow" again is simply the same
  place: it merges, nothing is asked.

**Where the names come from:**
- **When records or listings become one,** the names left behind are kept: the crawler's merge (gaps only), a
  person's merge in the Helper, "Merge into one" in Find & edit, and Rebuild's renames.
- **In the editor:** "Other names it goes by", one per line.
- **A mere spelling isn't kept** ("Dist 3" and "District #3" are one name).

**The rule that caught Infinia:** a record with the same phone, street address and ZIP as a listing, under another
name, and with the same kind of place, is that place. It merges on its own (gaps only), and its name becomes an other
name. Another kind of place there (a billing company at a hospital, a pharmacy in a grocery store) is still "covered":
not listed again, and nothing copied.

**A place's name is not a person's:** a name with "at", "of", "on" or "by" in it ("Infinia At Showlow") is never read
as a person's name. It had been, because it has three words and no business word, and a person's name at a facility
is kept apart from the facility.

- Checked in the sandbox:
  - the NPI record merged into Tall Pines, with "Infinia At Showlow" kept as its other name;
  - the next match reads "same";
  - "Also known as" shows on the page, and both searches find Tall Pines by "Infinia".
  - Every other existing match reads as before. The two that changed only moved between "place" and "other"
    (neither is a duplicate).

## Google (sign-in and Maps): what's needed before building

Jason has a Google Cloud project, "TRVS Crawler".

**Sign-in:** "Continue with Google", asking only for name and email (openid, email, profile).
- An account with the same verified email is linked.
- Our own sign-up steps (18+, the terms) still apply.
- Needs an OAuth client (a Web application) with `https://traversence.com/api/auth/google.php` as its redirect, and
  the consent screen published.
- Its ID and secret go in `.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`), never in a chat or the repository.

**Google as a source:**
- Google's terms allow keeping its place ID and checking a place against Google (does it exist, is it closed).
- Its details (hours, phone, reviews) can only be shown on a Google map, credited to Google. They can't be copied
  into our listings to keep.
- So Google verifies and links; it doesn't fill in listings.

**Spending:** before any Maps call goes live, the project needs:
- a budget with an email alert (Billing → Budgets & alerts);
- per-day quotas on each Maps service (APIs & Services → each API → Quotas);
- an API key restricted to traversence.com and to those services.

## Next

A Services & usage page in Settings: every outside service (paid or free, plan, allowance, key, billing link), metered
through one counter with hard caps, alerts at 80% and 100%, and the month's estimated cost.
