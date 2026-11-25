# Piece 2: a guide for vetting the Team (for Jason)

Written 2026-11-25 by the Piece 2 build session, for when you're back. It covers:

- what each release does, where to see it, and what to press;
- what the agents can and can't do at each stage;
- how to undo or roll back each release;
- the decision tree, and how approving it lets agents go further.

The design is `handoffs/piece2-team.md` (decisions/0179), and the tests are in `tools/team-tests/`.

**The one rule that holds the rest:** agents work only on the staging copy (https://staging.trvgo.us), and only while
their switch is on there. Nothing they do reaches the live site until staging is published, inside a batch you approve.
Until you approve the decision tree and raise the stage they work to, they only **propose**. A person (you or staff)
presses Accept before anything moves.

## 1. The releases, one by one

| # | Release | What it does | Where to see it | What to press |
|---|---|---|---|---|
| P2-2 | #287 `team-roster.zip` (live) | The Team: 33 roles in four departments, each with its mandate, steps, level and History | https://traversence.com/admin/config/team | A role's name opens its card. Try **✎ Edit its words**, then look at its History. |
| P2-1 | #288 `team-tickets.zip` (live) | Each Work queue task becomes a **Work**, **Audit** or **Resolution** ticket. Work moves Planning → Building → Implementation → Confirmation. A Resolution is a Decision Brief only you answer. | https://traversence.com/admin/config/control?tab=development | Open a ticket: you'll see its stage strip and what it needs to move on. On a Resolution, **Choose A / B / Answer in my words**. Only your own Founder login can answer; staff see it greyed, with why. |
| P2-3 + P2-4 | `team-runner.zip` (next) | The runner: every 10 minutes on staging, one step for one role, logged with Undo. The decision tree, and **Agents work up to**. The daily audit, which turns findings into tickets. | The decision tree and the stage: https://traversence.com/admin/config/team (top of the page). What agents found and suggested: the Work queue, link above. | **Approve the decision tree**, then **Raise to Building** (only when you're ready). In the queue: the filter's **Found by agents**, **Has suggestions** and **Words suggest Tier 3**. On a ticket: **Accept** / **Dismiss** a proposal, **Undo** a run, **It's Tier 2** / **It's Tier 3** on a flag. |
| P2-5 | `team-batch.zip` (last) | **Today's batch** at the top of the Work queue: the releases waiting for your OK, with the Tier 3 decisions waiting listed first. Your emailed answers get put on their tickets. | The Work queue, link above | **Approve the batch** (your login only), or reply to the daily summary's email |

The runner's switch is off until it's turned on, on staging only, at https://staging.trvgo.us/admin/config/switches
("Staging: the Team's runner"). The session manager turns it on after P2-3 is live, as you allowed. On the live site it
does nothing, even when switched on.

## 2. What agents can and can't do, by stage

"Agents work up to" starts at **Planning** and stays there until you raise it. You can raise it only after you've
approved the decision tree, one stage at a time. **Lower** works at any time.

| Role and its step | Up to Planning (now) | Up to Building | Up to Implementation | Confirmation |
|---|---|---|---|---|
| Project Manager: moves a Work ticket through its gate | **proposes** the move; a person's Accept makes it | makes the move itself, with Undo | the same | waits for Piece 3 |
| System Architect: writes a Building ticket's build brief | **off** | writes it, with Undo | the same | waits for Piece 3 |
| Executive Translator: drafts a Decision Brief's plain words | proposes; Accept fills them in | proposes | proposes | waits for Piece 3 |
| Operations & Reconciliation Manager: the daily audit | opens Audit tickets, each with Undo | the same | the same | waits for Piece 3 |

**Never, at any stage:**
- take a ticket themselves (only a person's **⋯ → Give it to the Team** assigns one);
- work a ticket a person has;
- answer a Tier 3;
- approve the batch or the tree, or raise the stage;
- write over a person's words;
- call an outside service or paid AI;
- act through a test account;
- touch the live site.

What each role may do comes only from Permissions (https://traversence.com/admin/config/permissions), at the role's
level and department. A step it isn't allowed to take is refused and says why.

## 3. The decision tree, and what approving it does

At the top of https://traversence.com/admin/config/team. It's drawn from the runner's own rules, so it always says
what the code does:

- **Tier 1:** only the low-risk kinds (wording, typos, the FAQ, labels, tests, the phone layout, contrast, spacing).
- **Tier 2:** everything that isn't clearly Tier 1 or Tier 3. It goes live only in a batch you approve.
- **Tier 3:** anything that **touches** a protected area: money, deleting or reshaping data, public addresses, tribal
  nations' land and sensitive sites (0066), or privacy. Each area is listed by its actual pages, files, jobs, services,
  libraries and tables. Tier 3 always comes to you as a Decision Brief.
- **Words alone never make Tier 3.** A finding only *worded* like one (say, "refund" on a trail page) stays Tier 2 and
  is flagged: "Words suggest a Tier 3 boundary: a person checks". Pressing **It's Tier 3** on it makes it a decision for you.
- The table of each role's step at each stage, and what is always yours.

**Approve the decision tree** records your approval against a fingerprint of the rules, not their wording. If anyone
later changes a rule (a tier, an area, a word list, a role's step), your approval is cleared and agents drop back to
Planning, with a line in its History. **Raise** then opens, one stage at a time. Each raise shows its evidence: what
the agents have done so far, how much people accepted, undid or dismissed, and where each number comes from. You judge
it; there are no pass marks. **Confirmation** stays greyed: "Needs the before-and-after check (Piece 3)".

## 4. Undo and roll back

| What | How to put it back |
|---|---|
| One agent step (a move, a brief, an audit ticket) | **Undo** on its line on the ticket's page. It's put back as if it never happened, and a second Undo is refused. |
| A proposal | **Dismiss** on the ticket's page |
| How far agents work | **Lower to Planning** on the Team page; its History shows who and when |
| The runner itself | Switch it off at https://staging.trvgo.us/admin/config/switches |
| A whole release | **Roll back** on its row at https://traversence.com/admin/config/releases. Every file is put back as it was, and any new files are removed. Tables a release added stay, unused (migrations only add). Each release proved its Roll back on the bench first (its `release.json`, "rollback"). |

## 5. Piece 3's blocking rules (what the before-and-after check will stop)

Until Piece 3 exists, your batch approval is the check. Once it exists, a release is blocked if its before and after
show any of these:

1. **Access or privacy leak:** someone sees or does what their account shouldn't, or private details show where they
   didn't before.
2. **Layout regression:** a page breaks at 1280 or 390 (sideways scroll, cut-off cards, missing buttons).
3. **Missing data:** rows, listings or values that were there before are gone or empty.
4. **Out-of-scope drift:** the release changes something its ticket and decision didn't name.

## 6. Where to start when you're back

1. Read Today's batch and any decision waiting for you: https://traversence.com/admin/config/control?tab=development
2. Look at what agents proposed at Planning: the filter's **Has suggestions** and **Found by agents**.
3. Read the decision tree at https://traversence.com/admin/config/team. Approve it when it says what you want, and raise
   the stage only when the evidence convinces you.
