# 0080. UX review: one scroll bar, no sideways pills, list pages read as many businesses

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0078 (admin dashboard areas), 0058 §11 (phone layout), 0046 (crawler intake)
- **Source:** Jason's review document "Standard User UX principles & dashboard ecosystems", and two notes while it was
  worked on:
  - "frames within frames is not good";
  - "I don't want pills that I have to scroll to see. If you have to, put them into a dropdown box."

## Decision

1. **One scroll bar.**
   - An admin area page shows each tool in a frame that grows to the tool's full height (`TvAsk.fitFrame`, frames
     marked `data-fit`), so only the page scrolls.
   - Everything a tool opens as a layer opens on the page around it, over everything, never inside the frame:
     - pop-ups (`TvAsk.peek`);
     - one-field questions (`TvAsk.ask`);
     - lightbox confirms;
     - toasts.

     Inline questions under a button stay where they are.
   - The tool still hears when its pop-up is done or closed (`tv-review-done`, `tv-peek-closed` are sent to its own
     page).
2. **No pill row scrolls sideways.** Any row marked `data-more` shows what fits and puts the rest under **More ▾**,
   a drop-down:
   - the chosen pill always stays in view;
   - a count waiting in a hidden pill (Claims 2) shows on More;
   - rows a page builds later are picked up on their own.

   Applied to:
   - the admin area tabs and Listings & places;
   - Review's chips;
   - the profile tabs;
   - Pulse's source chips;
   - the Address Book tabs;
   - Let's Explore's experience kinds;
   - the directory's narrow/saved rows;
   - Marketplace categories;
   - the place picker's rows.

   Photo carousels still swipe.
3. **A page that lists many businesses is read as many businesses** (a chamber's members, a "shops in town" page).
   Crawl a site used to treat every address on such a page as another location of one business, named after the list
   site. Now it reads it as a list when:
   - there are three or more addresses;
   - most have their own name printed above them (or before them on the same line);
   - the names differ;
   - few carry the site's own name.

   Each entry is then its own proposed listing:
   - named as the list names it;
   - with only its own address and phone;
   - with nothing of the list site's (description, website, email, social) copied onto it;
   - flagged "One entry in a list of N businesses on …".

   The list site's own address (its footer) is left out. A chain's locations page ("Changepoint - Show Low",
   "Changepoint - Holbrook") still reads as one business with locations. A phone printed above the next entry's
   address is that entry's, never this one's.
4. **Every line in Crawl a site has Accept · Edit · Reject**, so a list is decided line by line:
   - **Accept** publishes it as found, or merges it as chosen in the card. If the card still needs a choice first,
     Accept opens the card at it.
   - **Edit** opens the card.
5. **Small fixes from the document:**
   - the admin dashboard's front entry is named **Admin dashboard** (was "Today");
   - the last two transitions on the nav and rail are gone.

## Not done yet: the document's other gaps, for Jason to prioritise

- **Pulse row actions are placeholders.** They show the look, but don't do anything yet: read/unread, reply/forward,
  pin, report, folders, turn into a Journey or task, link/unlink from the chain icon, batch select. Unread counts on
  the tabs are item counts.
- **One Pulse for all your roles.** Today the Pulse shows the role you're acting as. The document wants personal,
  listing and admin feeds toggled into one stream.
- **The expanded item** (event card with RSVP, product card, DM thread vs. post, link preview), per-photo actions,
  "Comment as", Publish / Save draft / Schedule and the broadcast icon are placeholders.
- **Saved places, Linked and Groups overlap.**
  - Default places are search areas.
  - Linked is follows that feed the Pulse.
  - Groups is a read-only list; communities are managed on the front end.
  - There is no "favorites" concept.

  The document asks for this to be made clear.
- **Roles vs. the document.**
  - There's no support-ticket or sales-lead system yet, so `tickets`, `leads` and `money` have no screens.
  - The staff Manager has more than the document's sales/support oversight.
- **List / block / line layouts with sort and filter** exist only on Linked and Review. The Address Book, Groups,
  Default places, the Pulse list and the other admin lists don't have them.
- **The admin dashboard has no mail composer.** Its inbox is a list of what's waiting.
