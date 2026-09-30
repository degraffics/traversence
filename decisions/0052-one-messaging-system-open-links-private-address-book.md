# ADR 0052: One Messaging System, Open Links, and the Private Address Book

**Status:** Accepted (2026-09-30), direction from Jason. Supersedes the parts of `decisions/0047` that limited
messaging to Address Book connections and gave businesses counts only, and the "counts only" line in
`decisions/0050`. Works under `decisions/0049` (activation and consent) and `decisions/0051` (AI & Learning).

## Context

Links, messages and privacy had become tangled: private links, businesses seeing only counts, messages that only
connections could send, business conversations only after the person wrote first. Direction: the purpose of a link is
to identify a connection, and privacy is about limiting what information is available. Mixing the two makes links
murky, so they are kept apart. Safeguards are extra steps a person takes when they choose, not layers on a link.

## Decision

### 1. Links are open connections

- **Every link is visible to the other side.** The person or listing you link can see that you linked them; links
  to places and topics have no other side and stay private. No hidden or private linking.
- **Listings have the same rights as members.** A business you link sees **who** linked it, and the relationship
  works both ways (it can message you, under §4).
- **Links pull, never push:** linking pulls their posts into your feeds; it never pushes your posts to them.
- **Said at the moment of linking:** the Link consent, and a one-line note the first time you link an organization,
  says "[Name] will see that you linked them."
- **Profiles:** public or private. A private profile hides profile details (posts, groups) from people who aren't
  linked; it never hides who linked whom.

### 2. The Address Book is the private way to connect

Adding a person, business or place to your Address Book is a **private relationship you control**; the other side
does not see it. Contacts don't have to be Traversence members. From the Address Book you can message them, share
contact details with them if and when you choose (and take them back), and set how they may reach you. Nothing
happens until you act. This is the choice for someone who wants a clinic or program within reach without anyone
knowing. Contact details, identity and sensitive exchanges are always set up here, by choice, never by linking.

### 3. One messaging system for everyone

Guests, members, listings, admins and stewards share one inbox and one set of rules.

- **Linked (or an accepted conversation):** the message is delivered.
- **Not linked:** it arrives as a **message request**: Accept, Decline, or Block.
- **Every conversation** has **Report, Block and Help**; request sending is rate-limited and watched for abuse.
- The recipient's Privacy Settings can widen or narrow who may send requests.

### 4. Listing messages are typed

Every message a listing sends is labelled **Sales, Support, General inquiry or Relationship management**.

- **Sales** follows the person's marketing preferences (`decisions/0047` §3): subscribe, frequency, unsubscribe, ask
  again. The offers feature is the Sales channel.
- **Support, General inquiry, Relationship management** are allowed within something the person started or an
  existing relationship.
- The person can mute any type per business; a Sales message sent under another type can be reported as mislabelled.
- DMs are never an abusive sales funnel; text and email stay under their own read-required consent
  (`decisions/0049` §5).

### 5. Guests can ask a listing a question

- A guest can send a **General inquiry to a listing**, never to a person.
- **A simple self-hosted check** (Jason, 2026-09-30): a hidden anti-bot field, a minimum time on the form, and a
  small question a person can answer at a glance; no third-party CAPTCHA, so nothing tracks visitors
  (`decisions/0050`). Plus a daily limit per device.
- **Contact is the guest's choice:** an email, a phone number, or neither. With neither, they get a **reply code** and
  check back on the listing page.
- **Like a normal contact form:** the listing sees the guest's message and the contact they chose to give, in its
  inbox. Replies to an email go out through Traversence.
- **Validated from the listing side:** if the listing connects, it's real; if not, it can mark it **spam** or
  **couldn't reach**. Repeated spam from a device ends its guest messaging.
- **No marketing to guests, ever.** Answering their question is not marketing. "Create an account to keep this
  conversation" turns it into a normal one, with consent.

### 6. Privacy Settings (was "Personal Security")

The account hub separates contacts from privacy:

| Address Book (your contacts) | Privacy Settings |
|---|---|
| Contacts: people, businesses and places, members or not | Profile: public or private |
| Requests: connections and messages | Who can send me message requests |
| My card: what I share with whom | How people can find me (name, email, phone) |
| Groups | Blocked & reported |
| Messages | Your tools: Linking, Address Book, AI Assist & Learning (activate, deactivate, reset) |
| | Marketing preferences (moved from App Settings) |
| | Password & sign-in security |
| | Download or delete my data |

App Settings keeps everyday preferences (display, notifications).

## Consequences

- To rebuild in steps: open links (visible to the other side; businesses see who); message requests for everyone,
  with Report/Block/Help; typed listing messages with Sales tied to marketing preferences; Address Book contacts that
  can be businesses, places and non-members; guest inquiries with CAPTCHA and reply codes; the Privacy Settings
  section; the "[Name] will see that you linked them" note.
- Existing links: people and businesses already linked are told once that links are now visible both ways, and can
  unlink or move a business to their Address Book.
- Linking and Address Book consent wording (`decisions/0049`) is updated to match before build.
- Open: daily limits for requests and guest inquiries; how admins and
  stewards appear in the inbox (role labels).
