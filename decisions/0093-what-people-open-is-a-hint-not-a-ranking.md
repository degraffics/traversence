# 0093. What people open is a hint, never a ranking factor

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** 0061 (search that learns), 0067 ("most picked"), 0092 (words with two meanings)
- **Amends:** 0067's rule that the kinds people opened after the same words lead the suggestions, "first and sure"

## Context

Jason, on how search should predict what people mean:
- "What people actually opened should not have much bearing on how we use words to predict routes. The usage
  reinforces trends more than solves."
- "Most picked can be helpful as a suggestion, but it can also be construed as a ranking factor that can be manipulated
  for better results."

Until now, `Learning::picks` (kinds opened after the same words, at least 3 taps) went to the top of the suggestions
and was marked sure, even when the words didn't lead there. Every tap counted, so one person tapping a kind three times
could put it first for everyone.

## Decision

**Routes come from meaning:** the word's own meanings and how common each is, how our own data uses it, and the search
in front of it (the words around it, the place, the season). What people opened is never one of them. This also governs
the meaning map planned in 0092.

**"Most picked" is a label, never a position** (`UniversalSearch::meant`):
- A kind the words already lead to shows "most picked for …" in its line, and stays where its meaning puts it.
- A kind the words don't lead to may follow at the very end, never first, never "sure".

**Harder to game** (`Learning::bumpOpen`, `Learning::picks`):
- A tap counts once per visit, for those words, that kind and that day.
- Our team (decisions/0087) and bots never count.
- A kind is labeled only once it was opened on at least 3 different days.

**What's kept:** the counts are still collected for Insights and reports, and for the learning review's "Most opened" and
"May also help" hints, which show apart from the results. They're harder to game now too.

## Checked on a copy of the site

Suggestions for "tubing" (the two activities, then hardware, pipe and plumbing suppliers):
- 50 taps on Plumbers in one day: no change at all;
- taps over 3 days on Plumbers (60) and Hardware (6): Hardware Stores reads "most picked for 'tubing'" in the same place,
  and Plumbers, which the word doesn't lead to, doesn't move up.

The 24 standard searches are unchanged.

## Amendment, the same day: clicks are for SEO and internal improvement only

Jason: "real users across at least 3 different days: use clicks for SEO and internal improvements, not information
ranking."
- **Clicks never add a result.** A kind the words don't lead to is no longer added to suggestions at all because of
  clicks. A kind they do lead to may carry the "most picked" label, and that's all.
- **The learned results ("What most people searching this open", "Often opened after these words") are no longer in
  public results** (`learned` is always empty). They stay in Admin → Search learning, where a person reviews them and
  decides what to teach the search. That's the internal improvement.
- **The counts stay for Insights, reports and SEO** (which pages and words matter), counted as above: real visitors only,
  once per visit, on at least 3 different days.

**Checked:** 60 taps over 3 days on Plumbers for "tubing" add nothing to the suggestions, a search's results carry no
learned items, and the 24 standard searches are unchanged.

## Amendment: "Maybe try" instead of "what other people often search"

Jason: "'What other people often search' infers that we see and keep what other people are typing and then offer it to
a stranger… doesn't really feel aligned. But our system seeing a pattern and suggesting it in a way that could benefit
the user does. Maybe we offer a 'Maybe try' as an option where this pattern exists."

**Before:** learned phrases were mixed into the typing predictions, looking like the search's own.

**Now:** they're offered apart, under **Maybe try** (`UniversalSearch::maybeTry`, `maybe_try`, `js/search-panel.js`):
- at most 2, and only where a pattern exists;
- a phrase searched by real visitors on **at least 3 different days**, that found something;
- made only of words the search already understands, so never a name, an address or anything personal;
- never a repeat of a prediction already shown.

**The record of searches** (`Situations::logOutcome`, also behind Missed searches and Insights) now counts real visitors
only: no team, no bots, once per visit for those words and day. The FAQ says "Maybe try" instead of "what other people
often search".

**Checked:** "hiking trails for kids", searched 9 times on one day, isn't offered. Searched on 3 different days, it's
offered under Maybe try, apart from the predictions. The 24 standard searches are otherwise unchanged.

## Amendment: a pattern is how strongly it stands out, not a day count

Jason: "We don't need limits like this. A pattern can evolve in a day or take years, but we should be able to make sense
of the pattern and apply it in a way that enhances the UI and UX without needing a rigid timeline."

**Replaces** every "at least 3 different days" above, and the 90-day window behind them. There's no time window and no
fixed count. A pattern is judged by **how strongly it stands out**, at the confidence the visits give
(`Learning::wilson`, the cautious lower bound of a share, so a few visits count for less than many):
- **"Most picked"** (`Learning::picks`): a kind is labeled when its share of the visits that opened anything after those
  words stands out (`Learning::PATTERN`, 0.30 at that confidence). 5 of 6 visits: labeled. 1 visit: not.
- **Maybe try** (`Learning::completions`): a phrase is offered when its share of the visits that searched something
  starting the same way stands out (half of `PATTERN`) and it found something for nearly all of them (the same cautious
  share, at least half). A single visit never passes; many visits in one day can; the same few spread over years can.

**What still guards it:** each visit counts once, so **one person repeating never makes a shared pattern**; our team and
bots never count; and Maybe try is still made only of words the search understands.

**Checked:** "hiking trails for kids" is offered after 9 visits in one day, and after 6 visits spread from 2023 to 2026;
not after 1 visit, nor when it's 2 of 32 visits. The 24 standard searches are unchanged.

## Amendment: one person's pattern is theirs, and AI Assist is how it helps them

Jason: "One person repeating themselves never does, because each visit counts once. This is where AI Assist can come in.
Personal patterns that will help solve real personal concerns." And: "So if one user is presenting a pattern we offer AI
Assist as an option to enhance their UI/UX."

**Two kinds of pattern, kept apart:**
- **Shared patterns** (above): many visits, counted without who. They shape labels and Maybe try for everyone.
- **Personal patterns:** one person's own repeats (the same need searched again and again, the same kind of place, the
  same trip). They never enter shared counts and never reach anyone else. They help only that person, through
  **AI Assist**, under the one AI & Learning consent (decisions/0051, purpose 1: "improving your own experience").

**When a person shows a pattern, offer AI Assist** (planned, not built yet):
- **Without consent, nothing is kept.** The site notices only within the visit it's in (the session, held in memory,
  never written to the database): searching the same need again, refining the same words, coming back to the same
  kind of place. Judged the same way as above, by how clearly it stands out, not by a fixed count.
- **The offer is once and gentle:** a line in the search panel, in our own UI (no browser dialog), e.g. "Looking for
  this again? AI Assist can remember what you look for and help you find it faster." with **Turn on** and **Not now**.
  "Not now" holds for the visit; a guest is asked at most once a visit (decisions/0051 §1). Never shown to bots.
- **With consent, AI Assist keeps the person's own patterns** and uses them for that person: their usual places and
  kinds first in their suggestions, a reminder of the search they keep repeating, help with the concern behind it.
  Visible, resettable and switch-off-able from Privacy settings (decisions/0051 §1), never used for ads, never shared,
  no sensitive inferences (decisions/0050, 0051 §5).
- **Shared learning stays blind to it:** a person's patterns never count toward Maybe try, "most picked" or the
  learning review.

## Amendment: search teaches itself, by meaning; usage never outweighs relevance

Jason: "A person teaches the search: this should not require a person. The learning: the word and its route, never what
got picked. We need suggestions that make sense, so if this helps our search deliver relevant suggestions it's good. But
it cannot use usage to outweigh relevancy."

**The rule:** what the search learns is a word and its route (the kinds of things it means). It learns on its own, with
no person to approve it, from meaning: the word itself, the words around it, how our listings and trusted outside
sources use it. Usage (what people opened) may only order routes that are equally relevant. It never adds a route,
never raises one above a more relevant one, and never keeps one alive. The search record still drives SEO and the list
of what content to create (searches that found nothing, or nothing anyone explored).

**Found and fixed, two places where usage chose routes on its own:**
- **`Learning::run`** (words → a kind, published as a situation's phrase): its score was 45% usage. Now the score is
  relevance only: the words (×0.60) and outside sources that back it (×0.40); usage is kept for ties. "May also help"
  at 0.60, published at 0.80. No time window and no "10 searches" gate. Admin → Search learning explains it.
- **`SearchDrafts`** (applied on its own, decisions/0064):
  - the "category" draft ("read this word as the kind people opened after it") is gone. It was usage alone: on a test
    copy, 60 opens of Plumbers after "tubing" taught the search that tubing means plumbers. A word's kinds come from its
    meaning (Concepts).
  - any such change the system already made is undone on the next run, logged with Undo, unless the word's own meaning
    leads there too (`undoUsageRoutes`). A person's change is never touched.
  - a missed phrase is matched to the situation its words mean: the kinds of place they mean (Concepts, score 0.35 or
    more), each split across the situations it answers (a hardware store answers ten, a tire shop one), plus the
    situation led by the same event or action. Opens break an exact tie only. When meaning can't tell, the system
    builds a new situation instead.
  - a situation the system builds offers the kinds its words mean, never what people opened.

**What stays:** "most picked" stays a label (it never moves anything). The kinds people opened still nominate pairs for
`Learning::run` to score, but the score is relevance.

**Checked on a copy of the site, with no usage data at all:** "tire went flat on highway" → Flat tire, "car battery
dead" → Dead battery, "rent a moving truck" → Renting tools or equipment, "power went out" → Power out, "broke my
glasses" → Eyes and glasses, "dog got loose" → Pet care; "cooking class for teens" → no match, so a new situation.
60 opens of Plumbers after "tubing": score 0, nothing taught, and the earlier usage-made change was undone. The 24
standard searches are unchanged (apart from "cooking cla" finishing as "cooking classes", from a Cooking Classes kind
that the existing outside-source step added during the test run).
