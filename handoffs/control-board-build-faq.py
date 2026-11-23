#!/usr/bin/env python3
"""The control board build's FAQ changes (plan §7; CLAUDE.md "Keep the FAQ current"), applied to the newest live faq.md
just before zipping: `python3 control-board-build-faq.py <live faq.md> <out faq.md>`. Each change names the text it
replaces; a change whose old text isn't there stops the script (the live copy moved: re-read it and re-apply by hand).
Questions are kept word for word (their anchors come from the wording; no page links to these: grep -rn "faq.php#")."""
import sys

src, out = sys.argv[1], sys.argv[2]
s = open(src, encoding='utf-8').read()

def rep(old, new, n=1):
    global s
    if s.count(old) != n:
        sys.exit('FAQ: expected %d of %r, found %d' % (n, old[:80], s.count(old)))
    s = s.replace(old, new)

T = "### I'm on the Traversence team. "

# 1. Where are the crawler's workflows: the Workflows page as gauge cards (workflows-page.md §5, §7)
q = T + "Where are the crawler's workflows and the Workflow board?\n"
i = s.index(q) + len(q); j = s.index('\n\n', i)
rep(s[i:j], "On **System Configuration → Workflows** (https://traversence.com/admin/config/workflows#crawler), under the admin's own "
    "workflows (orders' stages and moves, unchanged). Since the control board's build each crawler workflow, **W0 to W14**, is a "
    "gauge card: its stage, its score (how much of its setup is done until it's watched, then its reading against its target) "
    "and its state in words (\"Paused by the system (a trip): …\" for a trip, never \"paused by a person\"). **W13 Stories** is a "
    "list (it's made of other workflows' work) and **W14 Authority sources** says **Not built** until it is. Each card opens the "
    "workflow's own page in the side panel: its last run, what returns empty, its steps with their switches and sources, its "
    "settings (each save a version, with Go back to it) and this month's spend; **By workflow, this month** is under the cards. "
    "**Paused now** at the top groups what's paused (Needs attention, Waiting on it, Off on purpose), each count a filter. "
    "**Start the system** and **Check before switching on** are on the System Control Board's **Start-up** stage now "
    "(https://traversence.com/admin/config/control?tab=startup), one home for each; the page links there. "
    "https://traversence.com/admin/config/board still opens this page.")

# 2. What is the System Control Board?: its three views first
rep(T + "What is the System Control Board?\nThe control room for the whole system. Clicking **System Configuration** in the side panel opens it.\n",
    T + "What is the System Control Board?\nThe control room for the whole system. Clicking **System Configuration** in the side panel opens it.\n"
    "- **Three views first** (the control board's build): **Is it delivering?** (where it opens: three questions, what's healthy, "
    "what's held and what needs you, then a gauge card for each promise the site makes), **Development** (the 16 development "
    "gauges and Site health's list) and **Incidents** (every Incident Report, IR-####). The six stages follow as tabs after "
    "them. Each gauge card opens its snapshot; see \"What are the gauges on the System Control Board?\".\n")
rep("The board opens on the stage the system is in, a",
    "The stages open on the one the system is in (the board itself opens on Is it delivering?), a")

# 3. Why does an alarm read the same: the card, Recovered, Fixed by, an incident's thread
rep("The board shows all of it. Pulse shows everything but the details. A push shows only the title and status. The logbook keeps the finished story.",
    "The board shows all of it. Pulse shows everything but the details. A push shows only the title and status. The logbook keeps the finished story.\n\n"
    "Since the control board's build the same words come from one place, the alarm as it stands now: an outside service's card "
    "(and Outside services, and Start) shows its issue with the alarm's own title and status, so nothing reads differently from "
    "the board. A part's plain name is used everywhere (\"Lead scores (job)\", \"W2 Re-read websites\"), never a code. When the "
    "system fixes it, the same message closes as **Recovered**; when a person's check fixes it, as **Fixed by** their name. "
    "A second trip with the same cause within 15 minutes joins the first one's Incident Report: its Pulse item is updated and "
    "the email goes as a reply in the same thread, with no new subject and no second push.")

# 4. The Modules entry's Workflow board and Check before switching on sentences (moved by the build)
rep("**Workflow board** shows each workflow (W1 to W13) with its last run, output and cost, and every setting it has (its tasks' schedule and limits, the services each may use, its budgets, gates, score weights, retry ladder and the steps that need a person); each save is a version with **Go back to it**. **Check before switching on** lists",
    "Each workflow (W0 to W14) is a gauge card on **System Configuration → Workflows**, opening its own page with its last run, output and cost, and every setting it has (its tasks' schedule and limits, the services each may use, its budgets, gates, score weights, retry ladder and the steps that need a person); each save is a version with **Go back to it**. **Check before switching on**, now in the System Control Board's Start-up stage, lists")

# New entries, after "Why does an alarm read the same…"
anchor = T + "Why does an alarm read the same on the board, in Pulse and on my phone?\n"
i = s.index(anchor); j = s.index('\n### ', i + len(anchor))
new = "\n".join([
 "",
 T + "What are the gauges on the System Control Board?",
 "Each gauge is one arc from 0 to 100, with its number in the middle and its stage beside it. A gauge goes through five stages: "
 "**Planning**, **Phased Development**, **Audit & Review**, **Implementation** and **Monitoring**. Until it's watched, its score is "
 "how much of its setup is done (its probe built, its target set, its check passing, its alarm wired to the board), in the "
 "stage's colour. At Monitoring it's its reading against its target: green when met, amber below, red only for a trip, grey "
 "with \"no reading yet\" when nothing has been read. **Is it delivering?** has a gauge for each promise the site makes (pages "
 "load, search answers, sign-in works, email is delivered, trips reach a person…), grouped by promise. **Development** has the "
 "16 development gauges; they stay in Planning until Jason signs each one off. Press a gauge to open its snapshot: what it "
 "measures, its target and reading, what's missing from its setup, and three paths, **Workflows ▸**, **Core Services ▸** and "
 "**Registered Services ▸**, each with what it depends on and its state, and **← Back**. Every panel closes with ×, Esc or a "
 "click outside.",
 "",
 T + "What does \"Paused by the system\" mean?",
 "It's a trip: something failed, the system tried again by itself and couldn't fix it, so it placed a pause on what the fault "
 "touches, and only that (one task, one workflow, calls to one service). It never turns a switch off: every switch stays as "
 "the person who set it left it, and the pause shows beside it. It lifts by itself when its check passes (the alarm closes as "
 "**Recovered**), or at once with **Check and start again**, which checks first. Something a person switched off reads "
 "**Off**, with who and why, and is never called paused by the system.",
 "",
 T + "What does Recovered mean on an alarm?",
 "The system fixed it by itself: its check passed again, what it paused runs again, and the alarm closed. Its message is the "
 "same one as the trip, closed, saying how long the part was down (\"Recovered: W1 Listings was down 12 min, the system fixed "
 "it\"). There's nothing to press. If a person's **Check and start again** closed it, it reads **Fixed by** their name instead.",
 "",
 T + "What is an Incident Report (IR-####)?",
 "Every trip opens one, or joins an open one with the same cause (the same host and kind) within 15 minutes. The **Incidents** "
 "view on the System Control Board lists them; each opens its page: the parts in it (each warning W-#### and trip T-####), "
 "what it held, its timeline and its evidence. Its root cause reads **Unconfirmed** until a person writes it: **Write the root "
 "cause** asks for it in words and keeps it on the report with your name (and in the logbook); **Change the root cause** edits "
 "it. Your role may not allow it; then the button is greyed with the reason.",
 "",
 T + "How do I copy part of the System Control Board?",
 "Every section and card on the board, its reports and its gauges' panels has a **Copy** button: it puts what that part shows on "
 "the clipboard as plain text (its headings and rows, a table's cells separated by tabs), never what's folded away or hidden. "
 "The answer shows beside the button.",
 "",
 T + "What happens if the whole site is down?",
 "A check outside our server (on GitHub, every 20 minutes) opens the home page and asks the site whether its databases and "
 "its watch are working. If not, it emails the Founder at once, one message while it's down, and a reply in the same thread "
 "when it's back. When the site is up again, what the check saw is added to the board as a trip with its real times and its "
 "Incident Report, and nothing is sent twice. It's built and waits for its settings (where to send it, and its own "
 "send-only email key); until they're added it sends nothing (coming).",
 "",
 T + "Where did Site health go?",
 "It's on the System Control Board's **Development** view, as its own list: each finding of the site audit with the page, who "
 "it blocks and how badly, and its buttons. Each new finding also reaches Pulse once, as its own item, never as a push. A site "
 "audit that hasn't run on time is a warning on the board, with the link to run it.",
])
s = s[:j] + new + s[j:]
open(out, 'w', encoding='utf-8').write(s)
print('FAQ written:', out)
