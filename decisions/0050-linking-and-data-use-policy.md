# ADR 0050: Linking and Data-Use Policy — How Traversence Treats What You Connect To

**Status:** Accepted (2026-09-30), direction from Jason. Governs the Link button and everything built on it
(`decisions/0047`), the Address Book, business messages, community groups and AI suggestions (`decisions/0010`),
under the activation pattern in `decisions/0049`. Basis for a public "Our approach to linking" page.

## Context

On most social platforms, every follow, like and connection is also a data point. In general terms, the common
practices are:

- **Content ranking:** interactions train recommendation systems that decide what each person sees.
- **Targeted advertising:** interests, habits and demographics are inferred from interactions and connections and
  sold as ad targeting.
- **Network mapping:** connections are used to build a social graph, predict relationships and group ties, and
  suggest people and pages.
- **AI training:** interaction and engagement data trains the platform's machine-learning models.
- **Off-platform tracking:** activity on other websites and apps is collected (pixels, SDKs) and joined to the
  profile; limiting it takes digging through settings on each platform.

People reasonably feel that connecting to something means being watched and sold. Traversence's Link button is
deliberately close to a follow, and it touches sensitive ground: a person may link a health clinic, a recovery
program, a church, a food bank or a tribal nation's public services. Linking has to be safe to use for exactly those
things, or it isn't useful at all.

## Decision

**A link is a bookmark you control, used to serve you. It is not a data point about you for anyone else.**

### What a link is used for (and only this)

1. **Your feeds:** what you link fills your Community, Discovery and Listings feeds.
2. **Businesses you link:** they can message you in your Traversence inbox (`decisions/0047` §3), and they see a
   **count** of people linked, never who.
3. **People you follow:** they can see that you follow them, so they can remove a follower; no one else sees who you
   follow or link.
4. **AI Assist, only if you activate it** (`decisions/0049`, `decisions/0010`): your own links and activity help the
   AI serve you on Traversence, and nothing else (see "AI Assist" below).

### What Traversence commits never to do

- **No selling or renting** of links, profiles, contacts or activity to anyone: advertisers, data brokers or
  partners.
- **No behavioral ad targeting.** Businesses reach only the people who linked them, and only in the inbox. There is
  no audience built from what you do elsewhere on Traversence.
- **No sensitive inferences.** Traversence does not infer or label health conditions, recovery, religion, ethnicity,
  tribal membership, sexuality, politics or finances from what you link, and does not let a business or anyone else
  do so. Linking a clinic says nothing about you to anyone.
- **No social-graph mining.** No "people you may know" built from who you link, who links you, or your contacts'
  connections. Connections happen because a person searched for someone or was invited.
- **No AI use of your private activity.** Your links, private messages and contact cards are never used to train or
  improve AI models (ours or anyone's) and never feed any shared AI system. The only AI that ever reads them is AI
  Assist, for you, if you activate it (below).
- **Community content only in abstracted form** (`decisions/0010`, `decisions/0011`). Public contributions (reviews,
  public posts) may be read by the AI to recognize **topics** that help write guides. Only an abstract classification
  is kept: never the text, never who wrote it, and a topic counts only once it recurs across independent mentions.
  This is not training a model on you; nothing traces back to a person. Group posts follow the same rule, per
  author, under the account-level AI & Learning consent (`decisions/0051`).
- **No off-platform tracking.** No advertising pixels or tracking SDKs, and no following you around other websites.
- **No location from linking.** A link never reveals where you live or are (`decisions/0047` §2).

### AI Assist: help, not training

**Training** would make a person's activity part of a model, permanently, shaping it for everyone; that is ruled
out above. **AI Assist** is the AI reading what a task needs, at the person's request, to help that person: suggesting
places from their links, drafting a message to a business, summarizing their group's posts. It is allowed when:

1. **Activated with consent** under `decisions/0049` (and `decisions/0010`'s AI consent).
2. **Only what the task needs:** the person's own links, messages, groups or contact card, never other people's
   private data.
3. **For that person only:** nothing inferred is shown to or used for anyone else, and the sensitive-inference rule
   above still holds.
4. **Not kept for learning:** requests are used to answer and not retained for training; any AI provider must, by
   its terms, **not train on Traversence data**.
5. **Visible and revocable:** Assist is labelled wherever it's used, and can be deactivated in Your tools.

Anonymous, aggregate figures (e.g. how often Assist is used, whether answers were marked helpful) may be used to
improve the feature, never tied to a named person.

### What you control

- **Every link is visible to you** (Linked Connections), with one-tap unlink and mute.
- **Every tool is activated with consent** and can be deactivated in App Settings → Your tools (`decisions/0049`).
- **Contact details are shared only by your choice**, per person or per business, and can be taken back.
- **Text and email** only ever with explicit, read-required consent per channel (`decisions/0049` §5).
- **Export and delete:** you can download what Traversence holds about you and delete it. (To build; see
  Consequences.)

### What Traversence does use, in the aggregate

To run and improve the platform: counts (how many people link a place or business), which places and categories
are linked most, and service health. These are never tied to a named person outside Traversence, and never used to
target an individual.

## The problems this solves

- **Community resources people can safely connect to:** health care, recovery, food, faith, family and tribal
  services, without that connection becoming a profile.
- **Businesses reach people who chose them:** an honest, opted-in audience in the inbox, instead of paying to target
  people who never asked.
- **Discovery that isn't an engagement machine:** feeds come from what you chose to link, in the order things
  happen, not from a model tuned to keep you scrolling.
- **Trust as the product:** a person can link freely because nothing about them leaves the platform.

## Consequences

- Every feature is checked against this list before it ships; a feature that would need one of the "never" items is
  redesigned or not built.
- A public page, "Our approach to linking", states these commitments in plain words, linked from every activation
  panel and from Privacy.
- To build: export and delete for a person's data; a check that no third-party tracking scripts are on the site
  (e.g. replace externally hosted page scripts with self-hosted copies where practical).
- Open: legal review of the public wording before launch; what aggregate statistics, if any, are ever shared outside
  Traversence (default: none).
- **Resolved by `decisions/0051` (AI & Learning consent):** group posts, including members-only, are read in context
  and kept as abstractions only when their author has consented; posts by people who declined are never read or scanned; only @place and @topic tags they chose to add are counted.
  Private messages and contact cards never enter shared learning.
- **Inconsistency to resolve:** `architecture.md`'s "chat-vectorization-queue" (embedding live conversation streams)
  conflicts with `decisions/0011`'s rule that raw content is never persisted into the AI corpus; it must be brought in
  line with 0011 (classify at intake, store only the abstraction) or removed before anything like it is built.
