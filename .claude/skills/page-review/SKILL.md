---
name: page-review
description: Review Traversence pages the site audit flagged or that changed (decision 0145 §2, Phase 4) — read each page's audit results, screenshots, paths and governing decisions, give every finding a verdict (fix the page, draft an amendment, missing decision or conflict), rank them by how many paths they block, and append proposals to site-audit/proposals.md for Jason. Use when Jason asks to review the site, the audit, the findings, Site health, or a page against the decisions.
---

# The page review

Decision 0145 §2: the audit doesn't only check pages against the rules; it improves the rules. This review turns the
audit's findings into proposals Jason approves, rejects or rewords. It runs in a Claude Code session (no cost to
Traversence, never the site's paid AI) and **changes nothing on the site and no decision**: it only appends to
`site-audit/proposals.md`.

## 1. Get current

1. `git fetch -q origin main` and merge it (CLAUDE.md "One copy"); the results are committed by the daily Site checks run.
2. The live mirror, read only, for the code behind a page: `degraffics/traverse-3way-sync` (`sync-storage/` = `website/`,
   `sync-app/` = `app/`). Clone shallow if it isn't there (its CLAUDE.md).
3. Regenerate what's derived, so the review reads today's state:
   ```
   python3 tools/page-audit/page-map.py
   python3 tools/page-audit/findings.py
   python3 tools/page-audit/review.py inputs --mirror ../traverse-3way-sync > "$SCRATCH/review-inputs.md"
   ```
   (`$SCRATCH`: the session's scratchpad; the inputs file is never committed.) `--pages /a,/b` limits it to pages Jason
   named; `--top N` changes how many (15 by default).

## 2. Read, for each page in the inputs (most paths blocked first)

- **The findings** (ids `f-…`), what the browser checks saw (`site-audit/rendered.md`, `results/rendered.json`), and the
  paths through it with what got in their way (`site-audit/paths.md`, `results/paths.json`).
- **The screenshots.** They're kept with the run, not in git: the run's Artifacts (`screenshots`), named
  `<who>-<page>.png` (rendered checks) and `path-<path>-<who>-<n>-<what>.png` (paths). If the session can download them
  (`gh run download <run id> -n screenshots`), look at the ones named in the inputs; if not, say the verdict rests on the
  results alone.
- **The page itself** in the mirror (its file in the inputs), enough to see why.
- **The decisions and docs that govern it** (the inputs list them with the section that names the page; read those
  sections), and the standards for its kind of page in `CLAUDE.md`. `site-audit/rules.md` says which rule each finding
  comes from.
- **The proposals already written.** A finding a decided proposal covers needs no new proposal: note it as "decided in
  P_n_, not built yet" in the summary instead.

## 3. A verdict for every finding

| Verdict | When | What to write |
|---|---|---|
| **fix** | The page breaks a rule that still fits | What to change on the page, the rule, the evidence |
| **amend** | The page does better than the rule, or the rule no longer fits what the site does | The rule's current words, the proposed words, why (the evidence), which check changes with it |
| **missing** | No rule covers it (including pages no decision names: page-map.md "Named by no decision") | Which decision should govern it, or the new decision's one-line rule |
| **conflict** | Two decisions (or a decision and `CLAUDE.md`) disagree about it | Both, quoted with their sections, and the choices |

Be honest about the runner: a path marked **stuck** ("?") may be the page (a visitor can't find it either) or the
runner (a changed selector in `tools/page-audit/paths.mjs`). Look at the page before deciding; when it's the runner,
fix the runner in the same session instead of proposing.

Look actively for amendments and conflicts, not only fixes: the first review must produce at least one drafted
amendment and list any conflicts found (phase-4 "Done when").

**Cost and effectiveness** (the inputs' last section): each becomes a finding with a proposed fix (cache it, index it,
call less, drop it). What the data can't show yet (calls and cost per useful result for outside services other than AI,
cache hit rates, query timings) is a **missing** finding naming what the health report or staging should add, never a
guess.

## 4. Rank and group

- Rank by **how many paths a finding blocks** (`blocks`), then severity, then how many paths it touches.
- Group findings that one change fixes into one proposal (one page fix, one amended rule).

## 5. Append to `site-audit/proposals.md`

Keep its format: a new `## From the review of <date> (findings of <generated_at>, mirror <sha>)` section, then one
`### P<n>. <short title> (verdict: <fix|amend|missing|conflict>)` per proposal, numbered on from the last
(`python3 tools/page-audit/review.py next-id`). Each:

- the evidence, in plain words (what a visitor sees, which path it blocks, which account);
- **Findings:** the `f-…` ids it covers (this links the proposal into the Pulse "Site health" case and `findings.json`);
- **Decisions it touches:** with sections;
- **Proposal:** what to do;
- no **Decided** line: that's Jason's.

Never change a decision, `CLAUDE.md` or the site in the review itself (CLAUDE.md "Build on what's there": going
against a decision needs Jason's OK first).

## 6. Finish

1. `python3 tools/page-audit/findings.py` again, so each finding carries its new proposal (`proposal`, `proposal_url`).
2. `python3 tools/page-audit/review.py done --mirror ../traverse-3way-sync` (remembers the review, so the next one also
   picks up pages changed since).
3. Commit `site-audit/proposals.md`, `site-audit/results/findings.json`, `site-audit/results/review-state.json` and
   `site-audit/page-map.md` / `results/page-map.json`, and finish in `main` (CLAUDE.md "One copy").
4. Tell Jason: how many findings, the ranked proposals (one line each, with verdict), the amendments and conflicts, and
   what's decided but not built yet.
