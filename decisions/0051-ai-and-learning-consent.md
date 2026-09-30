# ADR 0051: AI & Learning Consent — One Account-Level Consent for the Learning System

**Status:** Accepted (2026-09-30), direction from Jason. Consolidates and refines `decisions/0010` (AI consent),
`decisions/0011` (classify at intake, abstraction out), `decisions/0049` (activation pattern) and `decisions/0050`
(linking and data-use policy). Where those differ on AI use, this ADR governs.

## Context

Traversence learns from what happens on the platform: recognizing patterns and topics, finding sub-groups and local
names that recur, spotting missing information, and giving the crawler "insider" leads (context that points it to
things public sources should confirm). The goal is a better experience for each person and a more relevant,
self-building directory and guide for everyone. It is explicitly **not** tracking, ad profiling or solicitation.

Asking for AI consent tool by tool would scatter statements everywhere. One clear, account-level consent is simpler
for people and cleaner to operate.

## Decision

### 1. One consent: "AI & Learning"

- **Members** give it once, at the account level, through the `decisions/0049` pattern (it appears as a tool in App
  Settings → Your tools, with the same Activate → ⓘ Consent steps). It covers AI Assist and the learning system; no
  per-tool AI statements.
- **Guests** are asked **once per session** ("Allow AI & Learning for this visit?"). Guest activity stays anonymous,
  session-scoped, and is never joined to an account unless the person signs up and consents.
- **Optional:** declining keeps a full account (links, Address Book, groups, messages). Only AI Assist and learning
  from their content are off.
- **Purposes stated in the consent** (one consent, specific purposes):
  1. improving your own experience: suggestions, relevance, AI Assist;
  2. improving Traversence for everyone through patterns that don't identify anyone: topics, sub-groups, missing
     information, leads for the crawler to confirm;
  3. never: advertising, selling, solicitation, or sensitive labels (`decisions/0050`).
- **Revocable and visible:** a member can see what the system has learned about them, reset it, or switch it off.

### 2. What the learning system may read, by source

| Source | Author consented | Author declined (or guest who said no) |
|---|---|---|
| Public posts, reviews, community content | Read in context; kept only as abstractions (`decisions/0011`) | **Counted, not read** (§3) |
| Group posts and group chats (incl. members-only) | Read in context; kept only as abstractions | **Counted, not read** |
| Links, saved places, activity on Traversence | Used for their own experience; de-identified patterns for everyone | Not used for learning |
| **Private messages** | **Never** in shared learning; only their own AI Assist reads them | Never |
| **Contact cards** | **Never** in shared learning; only their own AI Assist reads them | Never |

Consent is per **author**: in a group chat, each message follows its own writer's choice, automatically.

### 3. "Counted, not read" for people who decline

No AI reads or summarizes a decliner's content, and nothing about them enters the learning system. Their public and
group posts can only add to **anonymous counts of terms the system already knows** (place names, sub-group names,
categories), by plain matching with no AI. Only the number is kept: never the text, the context, or the author. The
decline message says so plainly: "Even if you decline, we count mentions of place names in posts to keep the
directory accurate. Never who, never what was said."

### 4. Leads, not facts

What the learning system finds (a recurring sub-group name, a place people mention that has no listing, a missing
detail) becomes a **lead** for the crawler or a reviewer. Leads never carry who said something; the crawler confirms
them from public sources, and the `decisions/0044` guardrails and people decide what publishes. Topics still need
recurrence across independent mentions before they count (`decisions/0011`).

### 5. Unchanged guardrails

No sensitive inferences (health, recovery, religion, ethnicity, tribal membership, sexuality, politics, finances);
tribal lands as sovereign nations and no sacred or restricted site pinpointed (`decisions/0043`); raw text never
stored in the shared corpus (`decisions/0011`); AI providers must not train on Traversence data (`decisions/0050`).

## Consequences

- `decisions/0050`'s open question on members-only group posts is resolved: included for consenting authors, as
  abstractions; decliners' posts are counted only.
- `decisions/0010`'s opt-in default stands; this ADR is its single, account-level form.
- Build needs: a consent record per member (and per guest session); per-author filtering in anything that feeds the
  learning system; a term-count job for decliners' posts; a "What Traversence has learned about you" view with reset.
- Wording for the AI & Learning consent (summary, explanation, and the decline line) is drafted for Jason's approval
  before use, and reviewed legally before public launch.
- The "chat-vectorization-queue" in `architecture.md` must follow this ADR and `decisions/0011` (consented authors
  only; abstractions, not raw embeddings of conversations) before it is built.
