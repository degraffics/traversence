# ADR 0155: Add-ons (plug-in modules) and 1Password, resold and managed from our site

**Status:** Proposed (Jason, 2026-11-11, in conversation). Waits on 1Password's partner agreement (§6). Builds on
`decisions/0035`/`0037` (a person is their own account; a business is a listing they manage), `0098` (the order system
and products), `0108` (plans), `0152` (Outside services), `0154` §6 (the Shop and one cart) and CLAUDE.md "No income, no
expense".

## Context

Jason needs a password manager as an admin with many outside services (0152), and sees it as something to offer:
"password manager is an excellent addition to services and tools we offer"; "resell with inline API management if
possible"; "since our system focus' on a single user as an independent entity I think 1password works for us. I see this
as a plugin add-on where the marketplace could be used to purchase it's subscription and then the system onboards the
module being plugged into the user account."

Never built or hosted by us: a vault holds the keys to everything a person or business has, and keeping one safely takes
encryption no server can read, outside audits and breach response. On shared hosting it would put every customer at risk.

## Decision

### 1. Add-ons: one pattern for plug-in modules

An **add-on** is a product in the order system (0098) with four parts, so the next one plugs in the same way:
1. **Sold in the Shop** and its one cart (0154 §6; the Marketplace stays members' own buying and selling), or bundled
   into a plan. Charged first (QuickBooks Pay), so it never costs us before it's paid.
2. **Onboarding:** when it's paid, the site calls the company's API to create or enable it for that account.
3. **A module** on the account's dashboard (a member's, or a business's portal): status, renewal date, the company's
   app, and Cancel.
4. **Upkeep:** renew, pause, cancel and the hand-off (§4), driven by the order system's subscription; usage read back
   from the company where its API gives it.
A company we resell gets a card in Outside services (0152) with its seats, accounts, usage and bills.

### 2. 1Password, two programmes

| Who | 1Password plan | Programme and API | Managed from |
|---|---|---|---|
| A member, for themselves | Individual or Families | Partner programme, **Partnership API** (individual and family accounts only) | Their dashboard |
| A business and its team | Teams Starter Pack (10 users minimum, up to 20; 5 guests) or Business (1 user minimum; 20 guests) | MSP / channel programme, **Accounts API for Partners** (provision, update, cancel and link entitlements; usage by billing cycle) | The business portal's Team page |

- **Seats:** a business buys a number of seats; its owner gives one to a team member on the Team page, and takes it back
  there. Each person signs in to the business's 1Password with their own account. Seats used, free, who and the monthly
  cost show on the Team page; never anyone's passwords.
- **Business includes a free Families plan for each user**, so a business's staff never need a second, personal seat
  from us. A member buys personally only when not covered by a business.
- **Shared vaults** are the business's 1Password account (that's where sharing and the owner's control live: a member's
  personal vault can't be shared or recovered by a business). Guests (5 or 20 by plan) cover contractors without a seat;
  items can also be shared with people who don't have 1Password.
- **Enterprise** (quote-based, for large organizations) is not resold; a large client is quoted case by case.
- **Traversence's own use:** a Teams Starter Pack or Business account now, for the logins of every outside service.

### 3. Onboarding stays on our site, except the master password

Buying, creating the account, seats and status are all on our site. The one step on 1Password's side is the person
choosing their master password (and getting their Secret Key): only they may ever hold it, or the vault is no longer theirs
alone, and handling it would make us liable for every vault. That step opens in a new tab; when they're back, our page
reads the account's status through the API and shows **Activated ✓**. We keep an account's ID and status, never its
contents.

### 4. Control and the hand-off

While subscribed through us, the account is ours to manage: billing, seats, renewal, end date. Before an end date the
person or business is offered renewal. Leaving is a **transfer of ownership**: they confirm, are reminded to check their
vault, and we set the end date (Partnership API) or unlink the business (MSP); from then on they continue with 1Password
directly. The vault's contents are never ours to hold back.

### 5. Price

What we charge comes from the partner agreement: 1Password sets minimum pricing floors, and its wholesale rates and
margins are given privately once we're accepted. 1Password's list prices (1password.com, 2026-11-11): **Business $8.99 a
user a month**, billed yearly, 1 user minimum (adds Okta, Entra ID, OneLogin and Duo sign-in, Watchtower alerts, role-based
vault sharing); **Teams Starter Pack $2.49 a user a month**, billed yearly, 10 users minimum (so $24.90 a month at least),
up to 20 (sharing, security alerts, role-based permissions, developer tools). Both have a 14-day free trial. A business of
fewer than 10 people is usually better on Business ($8.99 × its people) than the Starter Pack's 10-seat minimum.
The Shop's price covers 1Password's rate, the card fee and our margin; nothing is sold at a loss.

### 6. To confirm with 1Password (Jason)

Reported in conversation (2026-11-11), to be confirmed in the written partner agreement:
- the Accounts API covers creating and managing Business (and Teams) accounts and their seats;
- one partner can resell both personal accounts (Partnership API) and business accounts (MSP / Accounts API);
- the minimum pricing floors, wholesale rates and margins;
- whether the MSP console's management is all available through the API.
Contact: 1Password's partner programme, and mspprogram@1password.com for the MSP track.

### 7. Build order (after acceptance)

1. The add-on pattern (§1): product kind, onboarding call, dashboard module, upkeep, Outside services card.
2. Members: 1Password personal accounts (Partnership API; tested on 1Password's test domains first).
3. Businesses: seats and the Team page (Accounts API), the hand-off, usage on the Outside services card.
4. "Password manager setup" as a White Glove service: moving a business's logins in, staff and two-step sign-in set up,
   training; billable work in the order system.
