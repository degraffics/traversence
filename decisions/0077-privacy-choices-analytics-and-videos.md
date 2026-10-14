# 0077. Privacy choices: analytics and videos, asked once, off until yes

- **Status:** Accepted
- **Date:** 2026-10-10
- **Builds on:** 0049 (tool activation and consent), 0062 §4 (consent wording kept by version)

## Context

Jason: "We won't sell user data, but analytics and YouTube embeds will be used." Until now Traversence set only cookies
the site needs (signed in, a guest form's abuse guard), so no consent was needed. Analytics and YouTube videos set
cookies from other companies, which need a yes first in the EU and UK and are expected to be disclosed, with a way to
say no, under US state privacy laws. The Privacy page was a placeholder.

## Decision

1. **Two choices, off until yes, never needed.** `Consent::TOOLS` gains **Usage analytics** and **Videos from
   YouTube** (`cookie: true`), with the same versioned wording, record and "What this means" as the other tools.
   Nothing on Traversence needs either.
2. **Everyone is asked, signed in or not.** A visitor's choice lives in this browser (`tv_choices`, first-party, a
   year, only the choices). A signed-in person's is also kept on the account with the wording they agreed to, and wins
   on any browser they sign in on. `/api/choices.php` saves both.
3. **Where it's asked:**
   - **Sign-up:** "Your choices", both unticked, each with its explanation, plus "By creating an account you agree to
     our Terms and Privacy Policy". Both answers are recorded.
   - **Visitors:** a slim bar at the foot of the page (Allow, No thanks, Choices), once, and only when an analytics
     service is actually set up. It never blocks the page.
   - **Any time:** the Privacy page's "Your privacy choices" (live switches), and Privacy Settings → Your tools.
4. **What each one controls** (`website/js/consent.js`, on every page):
   - Analytics: the service's script loads only with a yes. It's set in `.env`: `ANALYTICS_SRC` (its script address,
     https) and `ANALYTICS_NAME` (shown in the bar). With neither set, there's no bar and nothing loads.
   - Videos: every YouTube or Vimeo video (Explore heroes, journeys) shows a Play cover and loads nothing until allowed,
     or until that one is tapped; "Always play videos" says yes for all; "Open it there" goes to YouTube instead.
     YouTube still loads in its privacy-enhanced (no-cookie) mode.
5. **The Privacy Policy is drafted** (`website/privacy.php`): the short version; your choices; who we are; what we
   collect; how we use it; cookies and what the browser stores (a table); analytics; videos; who we share with (never
   sold, never shared for advertising); how long we keep it; rights (US states, EU and UK); children; security;
   changes. Facts only Jason can give are highlighted in [brackets]; the whole text is to be reviewed before launch.
6. **The FAQ** answers: Does Traversence use cookies? What is Usage analytics? Why does a video show a Play button?
   Where do I change my privacy choices? (and the existing "Do you sell my information" now links the policy).

## Consequences

- Changing what analytics or videos do means a new version of their wording; everyone is asked again.
- A privacy-friendly analytics service that sets no cookies would need no consent at all; if one is chosen, the bar
  can be switched off for it.
- To fill before launch: the legal business name and address, the privacy contact, the analytics service (and its
  cookies), the email and text-message providers, how long server logs are kept, and the reply time for requests.
