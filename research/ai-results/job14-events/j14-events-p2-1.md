# Events P2 review: events-p2 against decisions/0134 and the Hometown Theater pilot

*Draft for human review. Citations are `file:line` in the attached `handoffs/events-p2/` build unless another file is named. "Not shown" means the code that would settle it isn't attached.*

**What this covers:** E1 (the host's form and moves: `app/business/events.php`, `Events::save/clean/act/canPost`), E2 (the event page `website/event/index.php` and "Coming up here" in `website/listing/view.php`), P1 (invitations and offers: `Events::invite/answer/hostMove`, `Notify` kind `biz.event_invite`) and P2 (Taking part on the event page, "Part of" on a listing).

**Rules applied:**
- 0134 §2–§8 and Jason's answers