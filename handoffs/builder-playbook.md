# Builder playbook: how a build session works (Jason, 2026-11-18)

Jason, 2026-11-18: "the last couple of sessions have started to make mistakes like failed releases or deviations in
handling communication or processes already established so address the way sessions work to be thorough, efficient,
and accurate while expediting good code and communications." Every build session reads this before its first release.
Each rule below comes from a real mistake (in brackets) so it isn't repeated.

## 1. Before writing code

- **Read the decisions and the live code for the area first**, and the hand-off you were given. If Jason "already
  outlined" it, it's in a decision: find it (`grep -rl` in `decisions/`) before proposing anything. [The hero and stock
  photos process was re-proposed though 0058 §30, 0138 and 0143 §4–5 already set it.]
- **Before asking Jason how something should work, quote the decision.** Search the decision for the area, then ask
  only about a real gap or a conflict, naming the sections ("0171 §6 says the system tries its own fix first; §7 says a
  trip clears by itself; your Overpass answer says a person clears it. Which applies to every trip?"). Never ask him to
  define again what a decision already defines, and never describe how today's code works as if it were the rule.
  Re-read the decision before each release: in a long session, what you read early has been condensed. [3b-3: Jason was
  asked "when should a trip wait for a person?" though 0171 §6 already said the system tries its own fix first.]
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

### The principles (Jason, 2026-11-19: "Talk with manager about the principles to avoid these requests")

Each one comes from a request Jason had to send. Build to them, and the session manager reviews against them (8).

1. **The system fixes first; a person is the last resort.** Before anything waits on a person, the system tries what it
   can: retry, re-check, re-run, start again after its check. A person is asked only for what needs one: a decision, a
   key, money, someone outside. **Exception:** a site that refused or blocked us is held until a person reactivates it,
   and the system never retries it on its own (Overpass; 0164's R-rules). Retrying is for our own failures and
   timeouts, never for someone else's refusal. [A slow call stopped W1 and waited for an admin.]
2. **Stop only for harm, never for "doing less".** A stop is for a refusal, being out of budget, or risk to data or
   money. Slowness and partial work are warnings that clear themselves. A stop is as narrow as its cause. The
   same exception as 1 applies: a refusal or block is held for a person. **When a retry or crash becomes a trip is
   already decided** (0171 §5, Jason's 3c note): a warning while the system tries again, a trip when a back-off reaches
   its case limit or a crash outlasts its tries. This principle doesn't reopen it. [Slow trips stopped W1 and W4; 4e.
   Proposing "server errors never trip" re-asked 3c's rule: "We already have a rule for how trips get set", 2026-11-19.]
3. **Every alarm carries the button that resolves it.** Never a question to get past ("What fixed it?"), never only
   "more info". If the system can't fix it, the alarm names the one thing a person must do, with the button or the
   exact link. A button that can't work yet is greyed with the reason. The fix follows CLAUDE.md's Pulse rule: a step
   doable from the alarm is a button that does it; a step at an outside service carries its link. [Resolve only asked
   for words; 4e made it "Check and start again".]
4. **Words follow the state as it is now.** An alarm, a row or a trend says what's true now, refreshed when the state
   changes, never the words it was raised with. "No data yet" is said as that, never as a judgment. [W0's passing text
   as its trip; "Uptime: not due to be on"; 4c.]
5. **The admin's test before sending.** For each state a new screen can show: what does Jason see, what does it mean for
   him, and what does he press? If there's no answer, it isn't ready. Every release summary carries one line per new
   state ("Admin's test: …"), and the session manager checks for it. ["No code names them": "i dont see how to act on it".]
6. **Never hand Jason a workaround that is itself a dead end.** If the fix isn't live yet, say what the system will do
   and when; offer a stopgap only if it's harmless, and better, make the system do it. [Typing anything into "What fixed
   it?" to get past it: "bad thought process".]
7. **Fewer questions, each one whole.** Gather the decisions for an area into one question with its evidence (counts,
   decisions cited), not a string of small asks. A cleanup the system can judge (empty in every row, no code, no
   decision) is pre-sorted by the rules and goes to him as one list to approve. Removing data or going against a
   decision always needs his OK. [The unused names: one question with three lists and the counts.]
8. **Review against the principles, not only the decision.** The session manager's review of a release summary also
   checks 1 to 7: what waits on a person, what stops, what each new state asks of him. **A principle never overrides a
   decision:** before proposing a change from a principle, find the decision that governs it and quote it; if it already
   decides the case, follow it and don't ask him again.

## 3. Before sending a release

- **Phone check at 390px** for every page changed: `tools/page-audit/layout-probe.mjs` (or Playwright at 390 wide):
  no sideways scroll, cards 3px from the edges, nothing cut off. [The Start card was 428px wide; buttons were cut off.]
- **Press every new button once** in a rendered test (or on staging after it lands) and say which you did. Never write
  "tested" for something only rendered with sample data: say "rendered with sample state; the real run happens on live".
- **Rebuild `faq.md` on the newest sync just before zipping, every time**, with only this build's entries (CLAUDE.md's
  "Just before zipping, fetch once more"). Then **run `zipcheck.py` again just before merging** your approved PR: a
  release that went live while yours waited may have changed the same file, and the check now blocks a zip that would
  take out lines put live after its base (its delivery record's `built_on`; `--base` names one). Rebuild that file on
  the newest live copy and send `-2`. [3f's faq.md, built on 22:06, took out 16 lines #147 put live at 22:18.]
- **`send.py` refuses** (since 2026-11-18): a zip name sent before (use `-2`), a `switch` the site doesn't know (use
  `""`), a migration that isn't add-only, data changes without `"data_reason"`. Don't work around a refusal; fix the cause.
  [#70 and #863 used MODIFY; #108 and #109 had `"switch":"none"` and stuck at Planned; #106 re-sent a name and stalled.]
- **Replacing a screen keeps everything it did** (Jason, 2026-11-19: "These issues need to be caught!"). When a page,
  table or control is rebuilt or moved onto a shared part, list every control and behaviour the old one had (each
  button, link, filter, count that filters, sort, keyboard key, remembered choice), and say where each lives now: kept,
  moved (to where), or dropped (only with Jason's OK, quoted). The list goes in the release summary. Test each kept
  behaviour in the rendered page, not only its PHP helper. [#192 moved the board onto the table and its system counts,
  which filtered the rows since #180, became plain text; filter.php still passed because it tests the helper.]
- **Everything that pops open has a way to close it** (Jason, 2026-11-19: "anything that pops open should have a way to
  close it"). Every pop-up, panel, menu and drawer: a visible ×, Esc and a click outside. Test all three in the
  rendered page. [Pulse's + New and Quick actions panels closed only on Esc or a click outside.]
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

- **Have the session manager review the question first** (Jason, 2026-11-18): send it your question, or the release
  summary and PR, by send_message. Ask Jason once it answers "clear to ask" or you've made its fixes, and note
  "reviewed by the session manager". No answer in 10 minutes: remind it; none in 30: ask, marked "not reviewed". [3c's Trip rule
  reached Jason with four problems the session manager would have caught.]
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
- **A multi-phase build stays in your session until every phase is built and live** (Jason, 2026-11-18): never hand a
  phase to a fresh session. Keep your hand-off file current as you go (what's built, what's left, the sync it's built
  on) as the record, not as a hand-over.
- When the whole build is live: say "done" to the session manager and stop. The session manager archives finished
  sessions.
