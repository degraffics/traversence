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
2. **Who may publish**: only you (the Founder), or also Executives and Directors. Suggested: you, plus anyone you name.
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
