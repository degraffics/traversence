# ADR 0057: The FAQ Grows With Every Build

**Status:** Accepted (2026-10-01) by Jason.

## Context

Features are landing quickly: the directory, search, the place picker, journeys, contributors, the age gate and
suggestions. Each one brings questions a visitor will ask, such as "why isn't my journey on the front page?" Writing
an FAQ after launch would mean reconstructing the rules of every feature from memory. Writing it while each feature
is built keeps the answers accurate and cheap.

## Decision

1. **One source.** `faq.md` at the repo root is the FAQ. The site renders the same file at `/faq.php`; it ships as
   `website/includes/faq.md`. There is no second copy to keep in step.
2. **Format.** `## Topic`, then `### Question` with its answer below. Answers use paragraphs and `- ` lists;
   `**bold**` and `[links](/path)` work. Everything else is shown as plain text, and only site-relative or `https://`
   links become links.
3. **Every build updates it.** A feature isn't finished until its FAQ entries are added or changed. Each answer is
   written from a visitor's point of view, in plain words. It describes what the site actually does, and says
   "coming" for anything not built yet.
4. **Questions are linkable.** Each question has a stable anchor made from its wording (for example,
   `/faq.php#how-does-my-journey-get-onto-the-front-page`). Pages can link straight to an answer, as the journey
   editor does with "Where will my journey show?". Rewording a question changes its anchor, so check for links to it.
5. **Where it's found.** The FAQ is linked from the site menu, the phone menu, the footer and the About section of
   the site rail. It has a search box, and "Make a suggestion" for anything it doesn't answer.

## Consequences

- Missing answers become visible. When a visitor searches the FAQ and finds nothing, the page offers "Make a
  suggestion", so the gaps come back to us.
- A staff or partner FAQ (admin tools, the crawler, claiming and verification) can follow the same pattern as a
  separate file, when it's needed.
