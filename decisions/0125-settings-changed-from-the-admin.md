# 0125: Plain settings changed from the admin, not File Manager

Status: Built (2026-10-24). Jason has no terminal on the host and asked whether the site could reach `.env` itself
("Can't you reach the env through cPanel now?" … "Yes"). Settings already said "Changing a setting here, instead of
in .env, is coming."

## What

- **Settings → How it's set up → Change here** lists the settings that can be changed from the page, each with its
  value, what it does, its `.env` name and an **Edit** button (the site's own one-field box, no browser dialogs).
- **Only plain settings** (`EnvSettings::EDITABLE`): the AWS sign-in link, AWS region, S3 bucket, public photo
  address, the AI spending cap, the email sender name and address, the postal address for marketing emails, and the
  claims phone number. **Keys, passwords and tokens are never editable from a page.** They stay set by hand in `.env`,
  so a stolen admin login can't read or replace them.
- **Who:** the action `config.env`, by default the Founder and Executives (System Configuration → Permissions can
  change it). Anyone else sees the button greyed out with the reason.
- **Each change:**
  - is checked for its kind: an AWS sign-in address must be `…signin.aws.amazon.com`, a region looks like `us-east-2`,
    a bucket name follows S3's rules, addresses must be https, money a dollar amount, plus email and phone formats;
    no quotes, backslashes or line breaks. The region and bucket can't be emptied from the page.
  - keeps the file before it (`.env.bak-<date-time>`, the last 5, beside `.env`, same permissions);
  - replaces only its own line (or adds one) and writes the whole new file beside the old, then swaps it in, under a
    lock;
  - goes in the action log: who, the setting, and the value before and after (they're not secrets).
- If the server won't let the site write `.env`, it says so and nothing changes; it's back to File Manager.

## Tested (local copy)

- AWS sign-in link set to `https://trvgo.signin.aws.amazon.com/console`. The line was written, a copy of the file was
  kept, it was logged, and after a reload the card and the AWS "Sign in" link use it. Every other line in `.env` was
  unchanged.
- A sender name with a space was written quoted, and reads back without the quotes.
- `http://…` for the public photo address was refused ("Write the full address, starting https://") and nothing was
  written.
- Checked at 390 px wide: no sideways scroll.
