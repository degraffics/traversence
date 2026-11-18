# Builder playbook: how a build session works (Jason, 2026-11-18)

Jason, 2026-11-18: "the last couple of sessions have started to make mistakes like failed releases or deviations in
handling communication or processes already established so address the way sessions work to be thorough, efficient,
and accurate while expediting good code and communications." Every build session reads this before its first release.
Each rule below comes from a real mistake (in brackets) so it isn't repeated.

## 1. Before writing code

- **Read the decisions and the live code for the area first**, and the hand-off you were given. If Jason "already
  outlined" it, it's in a decision: find it (`grep -rl` in `decisions/`) before proposing anything. [The hero and stock
  photos process was re-proposed though 0058 §30, 0138 and 0143 §4–5 already set it.]
- **Start from the newest sync**: fetch the mirror, and if a release went live after its last "Sync:" commit, ask the
  session manager to run one. Never build on a copy held earlier in the session. [#117's follow-up was nearly built on 01:32.]
- **Claim before writing** (`checkout.py claim`), as CLAUDE.md says.

## 2. Building

- **Fix it where it's seen.** A step, a warning or a held line carries the button that fixes it, on that card; another
  tool opens in a drawer (`at_hand`, `data-peek-frame`), never a new page. [Jason: "Fix it can't take me to another
  page. I already explained this."]
- **Every action has its way back on the same card.** A "Leave it paused" needs a "Switch it on"; a hold names what
  lifts it. [W1 stayed paused with no way to resume it on the card.]
- **A press comes back to where it was pressed**, with its answer beside it (`TvAsk.say`), never a reload at the top.
  [Start's buttons reloaded the page, so "Check again" looked like nothing happened.]
- **A hold is as narrow as its cause**: one red line holds only what uses it; one timeout is retried, not a stop.
  [The hub lines held the whole system; W1 stopped on one Overpass timeout.]
- **Words stay true.** When behaviour changes, change every notice, note, FAQ entry and code header that described the
  old behaviour, in the same release. [The "until Release B" notes; Start's header still describing the old pacing.]
- **Downloads and reports work on a phone** (a file, or a page that opens). [Start's Report didn't download.]

## 3. Before sending a release

- **Phone check at 390px** for every page changed: `tools/page-audit/layout-probe.mjs` (or Playwright at 390 wide):
  no sideways scroll, cards 3px from the edges, nothing cut off. [The Start card was 428px wide; buttons were cut off.]
- **Press every new button once** in a rendered test (or on staging after it lands) and say which you did. Never write
  "tested" for something only rendered with sample data: say "rendered with sample state; the real run happens on live".
- **`send.py` refuses** (since 2026-11-18): a zip name sent before (use `-2`), a `switch` the site doesn't know (use
  `""`), a migration that isn't add-only, data changes without `"data_reason"`. Don't work around a refusal; fix the cause.
  [#70 and #863 used MODIFY; #108 and #109 had `"switch":"none"` and stuck at Planned; #106 re-sent a name and stalled.]
- **One coherent release, not a string of patches.** Gather the fixes for one area into one zip after checking them
  together; a follow-up release only for something found after it went live. [#115–#119 were five releases in two
  hours on one card, each needing Jason's OK.]

- **A build that fails on staging is yours to clear, not Jason's** (Jason, 2026-11-18: "having to require me to remove
  bad releases is not helpful. Since you create them, you need a way to deal with issues"). It withdraws itself (the
  site, since release releases-self-withdraw). Watch its record in `deliveries/releases/` after the merge. On
  "Withdrawn by itself", fix the cause, send the fix as a new name (`-2`) with `"replaces": ["<the failed zip's
  name>"]` in its `release.json`, and tell Jason what failed and what the fix is. Never ask him to press Withdraw.
  [#121's migration used the column name `stored`, which MySQL reserves; Jason was left to withdraw it.]

## 4. Communication

- **Ask Jason yourself, in your own chat** (Jason, 2026-11-18: "the same question is being asked twice in two
  different sessions"): the decision pop-up (AskUserQuestion: Approve, Not now, Needs changes) and a push notification,
  never a plain line and never a Pulse case. Then merge the PR yourself and send the session manager his answer, the link and the merge, so it runs the sync.
  Don't also ask the session manager to ask him.
- **Merge your own approved PR; only the session manager runs the sync and records deliveries** (Jason, 2026-11-18:
  sessions merge, no waiting). Merge only after his Approve in your own pop-up, one PR at a time. A build session's PR carries its own
  release zip, hand-off and decision notes, not `deliveries/` verification. [#891 conflicted with #890: both recorded
  #117's verification.]
- **Short, plain messages**: what changed, what Jason will see, what's next; links as full URLs.
- **Say what's outside `main`** if anything is; never call work saved when it's only on a branch.

## 5. Finishing

- Wait for the session manager's "verified live" message, then check the page once more if you can.
- Update your hand-off file (what's built, what's left, the sync it's built on), say "done" to the session manager,
  and stop. The session manager archives finished sessions.
