# Phase 5: Planned, phased releases

Decision 0145 §5, §6.

## Goal

Every change goes out the same way:
- approved;
- simulated on staging;
- released on a schedule or when Jason presses Publish;
- in steps;
- with an instant way back;
- recorded in git.

## What gets built

1. **Release records** in git (`deliveries/releases/`), one per release:
   - the approved proposals it carries;
   - files, migrations and switch;
   - checks and results on staging;
   - the schedule or "on publish";
   - the rollout steps;
   - the rollback;
   - what happened.
2. **The Releases page** in System Configuration:
   - each release and where it is: planned, on staging, vetted, scheduled, live at step 2 of 3, paused, rolled back;
   - its check results;
   - the buttons:
     - **Publish:** go live now.
     - **Schedule:** a date and time.
     - **Publish next step.**
     - **Pause.**
     - **Turn off:** the switch, for everyone, at once.
     - **Roll back:** the files to the previous version.

   Publish and Schedule only appear once the plan is approved and staging has passed.
3. **Phased steps:**
   - staff and test accounts, then a share of members (default 25%), then everyone;
   - each release is set to advance **on schedule** (default 24 hours clean to members, then 72 hours clean to everyone)
     or **on publish**;
   - the audit runs at each step;
   - a failed check pauses the rollout and alerts you; undoing it stays your call.
4. **Rollback of files:** each release keeps the previous version of every file it changes. Roll back re-applies them
   (by a rollback zip until Phase 6, by the release's previous tag after). Migrations are add-only (decision 0145 §6),
   so a rollback never touches data.
5. **Urgent fixes:** Publish now with a reason. Recorded and checked afterwards like any release.
6. **Each release as a Pulse case**, in your daily work and morning report, with its next step as a button.

## What Jason provides

1. **Approval** of this phase.
2. **Nothing to decide per person.** Publish, schedule, turn off and roll back are four permissions in System
   Configuration → Permissions, Founder only by default. Give them to a role whenever you want that role to have them.
   Staff onboarding only assigns the role.
3. **The rollout defaults**: the steps (staff and test, 25%, everyone), and the timing for "advance on schedule" (24 h,
   then 72 h). Change them if you like.
4. **Release windows**: times releases may go live on schedule (suggested: weekdays, 6 am–6 pm Arizona). "On publish"
   is any time.
5. **Upload habit, until Phase 6:**
   - releases still reach the server as zips you upload;
   - the release tells you when;
   - the rollback zip is made ready alongside it.

## Done when

- [ ] A small real improvement goes through every stage: proposal, plan, staging, scheduled, steps, everyone.
- [ ] A deliberate test release is rolled back with Turn off and with Roll back, and the audit confirms the site is as
      before.
- [ ] An urgent fix goes out with a reason, and its record and checks appear afterwards.
- [ ] Releases show in Pulse and the morning report.

## Rollout and rollback

The Releases page is itself released this way: to you first, then the staff who may publish. Rollback: its switch.

## Cost

None.

## Built (releases3_20261113, 2026-11-13)

**How a release travels** (Jason, 2026-11-13: "Upload on the page", in place of FTP uploads until Phase 6).
- **Upload:** the release zip goes up on System Configuration → Releases, through the one uploader (target `release_zip`).
  - It holds files at their real paths, plus an optional `release.json` (title, why, proposals, switch).
  - Dotfiles, notes folders, `app/storage` and `bootstrap-super-admin.php` are refused.
- **Staging:** the server copies the release to staging.trvgo.us, where it runs its migrations
  (`website/api/staging-updates.php`, staging only, locked by staging's HEALTH_TOKEN) and its checks.
- **Publish:** keeps the previous copy of every live file in `app/storage/releases/<id>/old/`, then writes the new ones.
- **Roll back:** puts those copies back and removes the files the release added.
- **FTP:** once the plan is approved, **Download the zip** and **Download the rollback zip** offer the same by FTP. The
  rollback zip includes a list of the files to delete.

**The staging bar** (Jason, 2026-11-13: "go from admin to the staging site, a staging command bar used to track changes,
save, schedule, publish").
- **Getting there:** **Open staging** (top of Releases) and **Open on staging** (each staged release) open
  staging.trvgo.us past its viewing password.
  - You're signed in as **yourself** by default: staging's copy of your own account and staff login, with the same ids
    and your name put back after the refresh replaced it (Jason, 2026-11-13: the test accounts are the audit's).
  - Or choose one of the audit's test accounts to see it as they would, or look signed out.
  - The live site signs a link that works for 10 minutes, once (`Releases::enterUrl`, key RELEASE_BAR_KEY in staging's
    .env).
  - `/stg-enter.php` on staging checks it, then gives the browser staging's pass (a cookie that staging's rules and
    `Staging::guard()` accept) and the bar (a cookie good for 12 hours).
  - If staging's server refuses the cookie rule, the rules are written again without it, and the viewing password is
    asked as before (`StagingSite::barKeys`).
- **The bar** (`js/stg-bar.js`) sits at the foot of every staging page:
  - which release you're trying, with a chooser when several are on staging;
  - **Changes:** what it changes, its pages as links on staging, its files, its checks;
  - **Note:** what you see on this page, kept in the release's record;
  - **Sign it off**, **Schedule** and **Publish**;
  - **Live admin**, to go back.
- **How it acts:** through `/api/stg-bar.php` (staging) to `/api/release-bar.php` (live), signed both ways. It acts as
  you, with the release permissions you had when you opened staging.
- **Publish:** copies exactly the files tried on staging. It stops if one of them changed on the live site since the
  release was built.

**Pieces**
- **Library:** `app/lib/Releases.php`.
- **The page:** `tv_admin_mod_releases` (System Configuration → Releases), with `js/admin-releases.js`. It sits behind the
  switch `releases` (staff and test accounts first).
- **The schedule:** the job `releases_tick` (every 10 minutes; Scheduler::AUTO).
- **Permissions:** `releases.publish`, `.schedule`, `.turn_off` and `.rollback` in System Configuration → Permissions,
  Founder only by default (`Access::can`, so Executives pass as with every rule).
- **Pulse:** a case for each release waiting on someone or paused, with its next move as a button. It reaches the
  morning report through Pulse.
- **Git records:** `deliveries/releases/<date>-<id>-<slug>.json`, written by the server through
  `DocsRepo::saveRecord` (GITHUB_DOCS_TOKEN).

**Checks** (`Releases::checks`), on staging before sign-off and on live after publishing and before each step:
- every PHP file parses;
- migrations only add (no DROP, RENAME, TRUNCATE, MODIFY, CHANGE or DELIMITER);
- the migrations ran on staging;
- the pages it touches, plus the home and FAQ pages, answer without a server error. A server that can't reach itself
  only notes it.
- no new PHP fatal or parse errors in the error logs while those pages were asked.

A failed check on live pauses the release and emails whoever approved the plan. Undoing it stays a person's call.

**Defaults** (System Configuration → Releases → Rollout defaults):
- 25% of members at step 2;
- 24 h clean before members, then 72 h before everyone;
- weekdays, 6 am to 6 pm Arizona time.

**Not yet:**
- The browser checks (`site-checks.yml`) don't run per release. They run daily, on staging for the paths that change
  something; Site health shows their findings.
- The "Done when" walkthroughs below: the first real release, the deliberate rollback test and an urgent fix. They're
  done on the live page, with Jason.
