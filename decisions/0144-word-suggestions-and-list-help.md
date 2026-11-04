# ADR 0144: Word Suggestions and the List ⓘ

**Status:** Accepted (Jason, 2026-11-04). Built on the 13:48 UTC sync.

## Why

Jason: "Things like word suggestions and spell check are essential features that keep our data pristine and aid the UX",
and for a list like the nine item kinds, a "small i icon for education … so users can learn and pick the correct kind."

## 1. Word suggestions

- **Where:** every field of words: a field marked `data-spell`, and every `textarea` not marked `spellcheck="false"`. The
  admin kit (`js/admin-kit.js`, `words()`) marks names, descriptions and notes, and leaves codes, amounts, dates and the
  structured boxes (prices, options) alone.
- **Two layers:** the browser's own spell check (`spellcheck="true"`), and once typing pauses, the site's suggestions
  under the field ("Holstr: **Holster** · Host · Hosts · Keep it"), from `/api/spell.php` → `app/lib/Spelling.php`.
- **Our own words, no cost:** a word is known when WordNet has it (`data/word-list.php`, plurals and verb forms through
  `WordFamilies`) or it's among the 15,000 commonest (`data/word-rank.php`). The frequency list comes from the web and
  holds misspellings, so a rarer word there counts only when no far commoner real word is a letter off ("teh", "recieve").
  Only real words are offered, a swap of neighbors counts as one change, closest and commonest first.
- **Never changes anything itself:** a tap puts a word in; **Keep it** leaves a name or brand as typed and is remembered on
  that device. Signed in only.

## 2. The list ⓘ

A list whose choices each mean something carries the meaning as each option's third part (`[key, name, what it is]`; in
PHP `key => [name, help]`, converted by `tv_w_fields()`). The kit draws a small **i** beside the list: it opens each
choice with what it is, and tapping one picks it. The nine kinds use `ItemKinds::choices()`, each with an example
("like a holster, a saddle or a jar of honey").

## 3. The rule

Every new form: a field of words gets suggestions (`data-spell`, or a textarea), and a list whose choices need
explaining gets their meanings (the ⓘ). Never `alert()`-style help, never a separate help page for what a choice means.
