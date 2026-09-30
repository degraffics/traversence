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

## Progress (2026-09-30): step 2, open links

Links are visible to the other side: people see **Linked to you** in Linked Connections (with Remove); a business's
Business Insights lists who linked it, with each person's marketing status. Fairness for earlier links: a link made
before links were open only shows once its owner has seen the one-time notice ("Links are now open connections…",
in Linked Connections and the Notification Center) and pressed **Got it**, or it was made after they consented to
Linking. Links of people who paused Linking don't show. The consent box is now an on-page **lightbox** (page faded,
chip and box beside the corner); "Configuration settings" shows a quick review of your tools inside the box instead of
leaving the page. `api/lib/Links.php` (`linkedBy`, `removeFollower`, `needsOpenNotice`), migration
`2026-10-01_open_links.sql` (`user_settings.open_links_ack`).

## Progress (2026-09-30): step 3, message requests

A message is **delivered** when the recipient has linked the sender, they're connected, or the recipient accepted or
replied before (the sender linking the recipient isn't enough, or links would become a way around requests). Anyone
else's first message arrives as a **message request**: one message, then the sender waits; the recipient chooses
Accept, Decline or Block (replying accepts). Up to 10 new requests a day per sender. Who-can-message-me now has three
choices, "requests" being the new default (existing "connections only" settings, the old default, moved to it).
Every conversation has **Report** (the last messages go to Admin → Reports), **Block** and **Help**. The Notification
Center counts message requests. Migration `2026-10-01_message_requests.sql` (`message_members.status`, the policy
enum, `content_reports.kind` gains `thread`). Guests and listing message types follow in steps 4 and 6.

## Progress (2026-09-30): step 4, typed listing messages

Every message a listing sends carries a type: **Sales, Support, General inquiry or Relationship management**, chosen
by the sender each time (the offers feature is always Sales; "link to us / subscribe again" asks are Relationship
management). A listing can write to anyone who has openly linked it (Business Insights → "Message someone who linked
you"), or reply within 30 days of the person's last message. **Sales** goes only to people subscribed to its offers
who haven't muted Sales; the other types follow the open link or the conversation. In each business conversation the
person has **Message types**, to mute any type from that listing (offers and asks skip people who muted them), and
**Report** on each message: "It's Sales, labelled as something else", spam or harassment. Reports reach Admin →
Reports with the message and the type it was sent as. Migration `2026-10-01_typed_messages.sql`
(`business_messages.msg_type`, `business_message_mutes`, `content_reports` kind `biz_message` and reason
`mislabelled`). Also fixed: a duplicate section tag in the account hub hid Business Insights.
