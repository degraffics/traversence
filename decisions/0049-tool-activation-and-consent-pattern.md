# ADR 0049: Tool Activation and Consent Pattern

**Status:** Accepted (2026-09-30), from Jason's three-step sketch. Applies to every person-facing tool and every
AI feature. Builds on `decisions/0010` (AI consent) and `decisions/0047` (connection points, Address Book,
business messages).

## Context

Traversence gives people tools that change what others can see or do with them: the Link button (feeds, and
two-way messages with businesses), the Address Book (connections, contact cards, messages), community groups,
and soon AI suggestions (the Chameleon engine). Each was switched on implicitly by first use. There was no
single moment where a person agreed to a tool, no record of what they agreed to, and no one place to switch a
tool off.

Direction (Jason): one activation pattern, the same for every tool and for AI, so consent is patterned and
predictable wherever someone first meets a tool.

## Decision

### 1. The same three steps everywhere

1. **Activate.** The first time a person touches a tool that isn't active for them, the tool's corner control (the
   same top-right "paperclip" spot as the Link chain) shows an **ACTIVATE** chip instead of performing the action.
2. **Consent.** Tapping it turns the chip into **ⓘ CONSENT**, with a one-line summary of what the tool does
   shown with it (e.g. "Link things to build your feeds; businesses you link can message you").
   - Tapping **CONSENT** activates the tool at once and performs the action they were attempting.
   - Tapping **ⓘ** opens the explanation panel: "Activating: [tool name]", a short plain-language description of
     what it does, what others can see, and how to stop, with a **Consent** button and a
     **Configuration settings** link.
3. **Active.** From then on the tool simply works everywhere for that person; they are not asked again for that
   tool unless its wording changes materially (§3).

No forced read: the explanation is always one tap away and the summary is always visible, which keeps consent
informed without making it a chore. Signed-out visitors are sent to sign in first, then return to step 2.

### 2. One record per person per tool

Each activation is stored: person, tool, **wording version**, when, and where it happened (the page). Deactivation
is stored the same way. This answers "when did I agree to that, and to what?" for the person and for Traversence.

### 3. Wording is versioned and approved

Each tool has a short summary and a longer explanation, drafted in plain language and **approved by Jason before
use**. A small wording fix keeps the version; a change to what the tool does or shares is a new version, and
people are asked again the next time they use it.

### 4. One place to manage it

App Settings gets **Your tools**: every tool, whether it's active, since when, a link to its explanation, and
**Deactivate**. Deactivating **pauses** rather than deletes: e.g. links stay but drop out of feeds and businesses
can no longer message; reactivating restores them. Deleting data remains a separate, explicit action.

### 5. Read-required mode

Some consents must be read before they are given. For those tools, step 2 opens the explanation panel at once
and **the Consent button stays disabled until the person has scrolled to the end** of the text; tapping CONSENT on
the chip alone isn't possible. The record notes that the full text was shown. This mode is **required** for any
consent to **text messages or email** (per channel, per business, with the sender named and how to stop), and is
available to any other tool whose consent carries a legal requirement to read first.

### 6. What this does not replace

- **Texts and email** keep their own explicit, channel-specific opt-in (US telemarketing and commercial email
  rules; `decisions/0047` §3), always in read-required mode (§5). Activating a tool never opts anyone into text
  or email.
- **AI consent** under `decisions/0010` is delivered through this pattern (the AI suggestions tool is activated the
  same way), not bypassed by it.
- Public viewing (reading a listing, a place page, a public profile) never needs activation.

### 7. Existing users

People already using a tool are asked once, the next time they use it, so everyone ends up with a record.

## First tools under the pattern

| Tool | Activated when someone first… | Deactivate means |
|---|---|---|
| Linking (connection points) | taps a Link chain | links paused: out of feeds; businesses can't message |
| Address Book | opens the Address Book or taps Connect / Message on a person | hidden from search and requests; messages paused |
| Business messages | messages a business, or answers a business | business conversations paused |
| Community groups | joins or starts a group | memberships paused (not visible to members) |
| AI & Learning (`decisions/0051`) | is offered AI Assist or learning (members once; guests each session) | no AI Assist; their content is counted, not read |

## Consequences

- Every new tool, and every AI feature, ships with its activation wording and its row in Your tools; a tool without
  them isn't ready.
- A small consent table and one shared script (the chip and panel) serve all tools.
- The Link chain moves to the top-right corner of whatever it belongs to (Jason, 2026-09-30), which is also where
  the Activate/Consent chip appears.
- Open: exact wording per tool (to draft and approve); whether groups' "consent to be seen by members" and business
  messages fold into Linking or stay separate tools; legal review of the wording before public launch.
