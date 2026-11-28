# Hometown Theater pilot: drafts for Editorial

*Drafted from the hand-off folder (`README.md`, `offer.md`, `go-code-kit.md`, `dinner-and-a-movie.md`, `touch-points.md`) and `decisions/0134`. Nothing here is live or sent. The Founder makes the offer in person with Sales (14). Anything the files don't support is marked **[GAP]**.*

**Five things to settle before these go out:**

1. **Restaurant offers changed.** The latest 0134 note ("the organizer sells, participants get exposure") replaces the discount-code plan. A restaurant's offer is now a line its owner writes, used in person at the restaurant. `offer.md`'s "GO Code promotion ('show your ticket: 10% off')" is rewritten to match.
2. **Where a restaurant's poster code goes.** The files give three answers:
   * `offer.md`: the night's page.
   * `go-code-kit.md` table: the trail.
   * The CMO's ruling: an unclaimed restaurant uses its own listing's Share code, which "points at the listing page only."
   
   Marked [GAP] throughout.
3. **QR tickets and the door check-in.** 0134 §6b keeps these for Core and up, and says Free and Basic get "no QR tickets or door support." The map (F4, F5) shows the code path with no plan limit, and the pilot note moves the §6b module to Q1. Which rule applies on the night is [GAP].
4. **How buyers pay.** `offer.md` lists "bank transfer" and "cash." 0134 lists pay link, Zelle, check, or pay at the door, and also bans "pay at the door" notes. I used pay link, Zelle and check, and flagged the rest.
5. **Who "participants" are.** I read it as 0134's participating businesses (the restaurants). A message to ticket buyers is not drafted here. Say if you want one.

---

## 1. The offer to the Hometown Theater

**A community night at the Hometown Theater**

**What we'd like to do with you**
One community night during your December run of *Avengers: Doomsday*. It's reported to open about Dec 18, 2026, and the date is yours to choose. You also pick the form:
- a block of seats at a regular showing, or
- a special screening of your own.

**What Traversence does for you, at no cost**
- **Your listing.** You're already on Traversence (listing #8807). Claim it and it's yours to edit.
- **An event page for the night.** It's linked from your listing and from the St. Johns guide.
- **Tickets for the night.** They sell from your listing as an *Event tickets* item: seats at a date and time, with a set number of seats. [GAP: whether the QR ticket and door check-in come with your plan. See "Five things," item 3.]
- **"Dinner and a movie."** From your event page, you invite St. Johns restaurants to take part ("Dinner before the show").
  - Each one that says yes shows on your page with its own offer, used in person at the restaurant.
  - Together they make the evening's trail, with *Add to a trip* and *Send to Maps*.
  - It's by invitation only, never paid placement. You can remove a restaurant, and a restaurant can leave, each with Undo.
  - If this tool isn't ready by about Dec 14–15, our Sales team sets it up with you by hand.
- **GO Code posters** for your lobby and box office window. Your account includes 5 free codes. Restaurants get posters too. [GAP: what a restaurant's code opens.]
- **A short story after the night.** Nothing is said about you without your OK.

**What it costs you**
Nothing from us. Traversence takes **0%**: no fee and no cut of any sale.
- Buyers pay you directly, the way you choose: Zelle, check or a pay link. You mark each order paid. [GAP: `offer.md` also lists bank transfer and cash, which 0134 doesn't.]
- Your regular showtimes appear on the event page as information only. We add no ticket link of ours, and your box office is untouched.

**What we ask**
- Claim your listing.
- Agree the night's date, seats and price.
- Let us put up the posters.
- Add your door staff to your team on Traversence, so they can check tickets in. (Source: map F5. [GAP: plan, see item 3.])

**The film**
We use its title as plain fact only. No studio artwork or logos. We use your own licensed promotional material only with your permission.

**Next step**
Scan the code on this page to claim your listing, or we'll start the claim with you today. [GAP: claims are verified by our staff (`Claims::verify`). The files don't say how long that takes, so "done today" isn't promised.]

*Notes for Editorial:*
- The event page can't go up until the listing is claimed (F1, `Events::canPost`). The build itself is due live about Dec 10–12. Neither date is stated to the theater; add them if Sales wants to.
- Buyers must sign in or make an account to buy (F7). The theater may want to know this for walk-ups.

---

## 2. The participants' invitation (to a St. Johns restaurant)

*Sent by the theater from its event. A person presses Send, never an agent.*
- *Until the tool is live (about Dec 14–15), Sales (14) asks each restaurant by hand.*
- *An unclaimed restaurant's invitation waits until real email sending is on. That's the Founder's call.*

**Subject:** Dinner before the show: join the Hometown Theater's community night

Hi [restaurant name],

The Hometown Theater is holding a community night on [date: GAP, the theater's to set]. They'd like you to be one of the places to eat before the show.

**If you say yes:**
- **You appear on the night's event page.** Your card shows your name, an offer in your own words, Directions, and a code that brings people to you.
- **Your listing shows "Part of [event name]."**
- **You're a stop on the "Dinner and a movie" trail.** Guests can add it to a trip or send the evening to Maps, with the theater last.
- **Your offer is yours.** You write it, and guests use it in person at your place. Nothing is sold online for you, and there are no codes to set up.
- **A poster for your register:** "Dinner here, then the movie at the Hometown Theater. Scan for the night." [GAP: what its code opens. See "Five things," item 2.]
- **A short story after the night.** We ask your OK before anything about you is said.

**How it works**
- It's by invitation only. No one pays to be included or moved up.
- You can leave at any time, with Undo.
- **Cost to you:** [GAP. No file says outright whether taking part or claiming is free for a restaurant.]

**Not managing your listing on Traversence yet?**
Claim it first. Until you claim, no offer shows for you.

**[Accept]** · **[No thanks]** [GAP: the real button names in the participants tool (P1, `Events::answer`) aren't in the files.]

*Notes for Editorial:*
- Restaurants aren't chosen yet. Editorial (12) picks them by the rule in `dinner-and-a-movie.md`: open that evening, nearest first, no paid placement.
- Before any poster is printed, check each listing for duplicates (the CMO's addendum).
- Editorial checks an offer line only for anything misleading. The owner's words are shown as written (0134).
- [GAP: whether the offer line is required.]

---

## 3. The touch-point map

*The CMO (7) owns the whole map. Each "Works today" status comes from `touch-points.md`. "Not walked" means the code path exists but no one has gone through it live; that happens in the Dec 15–16 rehearsal on staging.*

### Before the night: the theater (host)

| # | Moment | What we say | Button | Owner | Works today |
|---|---|---|---|---|---|
| 1 | Offer, in person, with the claim poster | Section 1. Poster: "Your theater is on Traversence. Scan to claim it, free." | Scan → #8807's claim page | Founder + Sales (14); CMO (7) for the poster | Offer drafted; claim not walked |
| 2 | Claims #8807 (D4, F9) | [GAP: claim page words] | [GAP: claim button name] | Sales (14); claim queue staff | Not walked |
| 3 | Posts the event (F1) | [GAP: event form words] | Business portal → Events | Frontend & Shop builds it; the theater posts | **No.** E1 and E2 are due about Dec 10–12, and the listing must be claimed first |
| 4 | Publishes the Event tickets item and turns on its Marketplace (F2) | [GAP] | [GAP] | The theater; Frontend & Shop for the path | Not walked |
| 5 | Adds door staff to its team (F5) | [GAP] | Team invite (`join.php`) | The theater | Not walked |
| 6 | Invites restaurants (P1) | "Dinner before the show" | [GAP] | The theater presses it; Sales (14) does it by hand until the tool is live | **No.** P1 is built, not live |

### Before the night: restaurants (participants)

| # | Moment | What we say | Button | Owner | Works today |
|---|---|---|---|---|---|
| 7 | Gets the invitation (D5: `biz.event_invite`, claimed restaurants only) | Section 2 | [GAP: Accept / No thanks] | Sales (14); Editorial (12) for the words | **No.** Unclaimed restaurants wait on email sending [GAP: the email mode wasn't read live] |
| 8 | Claims its listing (D4) | [GAP] | [GAP] | Sales (14); claim queue staff | Not walked |
| 9 | Accepts: its card appears on the event page, and "Part of [event]" on its listing (P2, D3) | The owner's own offer line, as written | — | The restaurant; Frontend & Shop | **No.** P2 isn't live; Sales sets up a hand showcase if needed |
| 10 | Register poster goes up | "Dinner here, then the movie at the Hometown Theater. Scan for the night." | Scan → [GAP: trail, night's page, or own listing?] | CMO (7); Marketing (13) | Duplicate check comes first |

### Visitors, guests and members

| # | Moment | What we say | Button | Owner | Works today |
|---|---|---|---|---|---|
| 11 | Scans the lobby poster (D1) | "Community night at the Hometown Theater. Scan for tickets and dinner nearby." If not on sale yet, the page says so. | Scan → event page | IT (11) for the router; CMO (7) for the posters | Not walked. Scans are counted only, never who scanned |
| 12 | Visits listing #8807 (D2) | The listing's facts, plus "Coming up" | Link to the event | Editorial (12), then the theater once claimed | Listing row confirmed live; the public page not walked |
| 13 | Opens the event page (F1) | The night's details. Regular showtimes as information only, with no ticket link | **Get tickets**; Link; like and comment | Frontend & Shop; the theater | **No**, until E1 and E2 are live |
| 14 | Browses restaurants and the trail (F6) | Each restaurant's name and offer line | **Directions**, **Add to a trip**, its GO Code, **Send the evening to Maps** | Frontend & Shop; each restaurant | **No**, until P2 is live |
| 15 | Signs up as a guest (F7) | Buying, liking or commenting asks a guest to sign in | Sign in / **Create account** | Frontend & Shop | Not walked. **Gap:** no buying without an account |
| 16 | Returns as a member (F8) | Their own tickets and trips | Your tickets; Your trips | Frontend & Shop | Not walked |
| 17 | Buys tickets (F2) | Pays the theater's way; the theater marks the order paid | [GAP: checkout button name] | The theater; Frontend & Shop; CFO (4) for the sandbox purchase and refund | Not walked |
| 18 | Gets the purchase email (F3) | "Paid: your order from Hometown Theater", the ticket lines ("a code each for the door"), and the order link | Order link | Frontend & Shop; IT (11) | **Gap:** sent as plain text, not the branded template |
| 19 | Opens the QR pass (F4) | One printable ticket per seat | Your tickets → ticket | Frontend & Shop | Not walked [GAP: plan, see item 3] |
| 20 | Checked in at the door (F5) | If a ticket can't be checked in, the page says why | **Check in** (the theater's team only) | The theater's staff | Not walked [GAP: plan, see item 3] |

### Throughout and after

| # | Moment | What we say | Button | Owner | Works today |
|---|---|---|---|---|---|
| 21 | Pushes and emails to the theater, restaurants and buyers (D5) | `biz.order`, `biz.event_invite` [GAP: words] | Each links to its thing | IT (11); Editorial (12) | **Gap:** no kind exists for "accepted" or "removed" |
| 22 | The story afterwards (D6) | How the night went, with the theater's and each restaurant's OK | — | Editorial (12) | **Partly.** No event story type exists; suggested as a section of the St. Johns guide story |

*Open items for the CMO (7) and PM (8):*
- Decide the restaurant poster target.
- Decide the plan question for QR tickets and the door check-in.
- Decide on the branded purchase email (F3).
- Decide on Notify kinds for "accepted" and "removed."
- Confirm #8807 is claimed by about Dec 3.
- Confirm the email-sending mode (the Founder's call).