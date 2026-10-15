# 0091. Delete my account, self-serve

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** 0090 (`/data-deletion.php`), 0079 (enforcement records), 0077 (privacy)

## Context

`/data-deletion.php` first asked people to email privacy@traversence.com to have an account deleted. Jason: a
self-serve button is better than an email request. Email stays for people who can't sign in.

## Decision

**Where:** Dashboard → Privacy → **Delete my account…** opens `/user/delete-account.php`. That page shows what will
happen and what the account holds now ("2 journeys, 1 business listing you run…"). The work is in
`app/lib/AccountDeletion.php`; the endpoint is `/user/api/delete-account.php`.

**To make sure it's the person:**
- They must have signed in within the last **15 minutes**. Otherwise the page says to sign in again, by any method,
  and comes straight back.
- They type **DELETE**, then confirm once more with the site's own prompt.
- The server checks all of this too (CSRF, recent sign-in, the exact word).

**Fix first:**
- An admin: another admin removes admin first, so the site always has someone who can run it.
- The owner of a group with other members: hand it over or hide it first.

**What happens, in one transaction (all or nothing):**
- **Deleted:**
  - profile and its photo files;
  - address book, contact cards, contact requests and shares;
  - connections, and groups of people (including the ones they own);
  - saved places and searches, settings, privacy choices and their log, age confirmation, marketing choices;
  - likes, mutes and reads;
  - sign-in links (Apple, Microsoft, Facebook, Google);
  - team access to listings and memberships;
  - pending email links and codes.
- **Taken down and erased:** journeys (hidden, title and text erased), stories, posts, group posts, comments, and
  experiences. Messages they sent to other people read "[Deleted]"; the other person's side stays.
- **Released:**
  - a listing they ran goes back to unclaimed, staying on the site for the business;
  - a claim in progress is withdrawn;
  - roles are revoked;
  - suggestions they made lose their name.
  - A group they own with no other members is hidden.
- **Kept without their name:** reports, enforcement records, staff decisions, counts.
- **The account:** a placeholder. Its email becomes `deleted-<id>@deleted.invalid` and every personal field is cleared.
  It has an unusable password, `deleted_at` is set (`2026-11-24_account_deletion.sql`), and every session ends. The row
  stays so the records above still point somewhere; nothing can sign into it. The email it had can sign up again as
  new.

**After deleting:** the person is signed out. A goodbye email goes to the address the account had (with "if this
wasn't you, write to us"). They land on `/data-deletion.php?done=1`, which says it's done.

**`/data-deletion.php`** now leads with deleting it yourself; email is for people who can't sign in. The FAQ has "How
do I delete my account?". Settings lists the database update.

## Checked on a copy of the site

18 checks, with a test person holding a journey, a comment, a saved place, an address-book contact, an Apple sign-in, a
message thread and a listing they ran:
- the page shows what happens, with their counts;
- an out-of-date sign-in is sent to sign in again, and the server refuses it too;
- "delete" in lower case is refused;
- an admin is refused, and their account is unchanged;
- a group owner with members is refused, and the page names the group;
- the deletion then:
  - leaves the placeholder account (email, name and area gone);
  - hides the journey and comment with their text erased;
  - removes the places, contacts, sign-in link and memberships;
  - shows their message as "[Deleted]" and keeps the other side;
  - sends the listing back to unclaimed;
  - hides their own group;
  - signs them out, so the old email can't sign in;
- the done page says so.

A first run that hit an error part-way changed nothing at all (the transaction rolled back), and said so.

## Not done

- **Uploaded photos in journeys and posts:** the posts are hidden and erased, but the image files stay in `uploads/`
  until a clean-up removes files nothing points to.
- **A grace period to undo:** deletion is immediate. The two confirmations and the recent sign-in are the guard.
