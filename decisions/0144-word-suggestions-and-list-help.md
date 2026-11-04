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
PHP `key => [name, help]`, converted by `tv_w_fields()`). The kit draws a small **i** beside the list. Tapping it opens a
**pop-up** over the form (Jason, 2026-11-04: "If the icon is clicked popup the info", never laid out in the form): each
choice with what it is, the current one ticked; tapping one picks it and closes it, as do ×, Esc and a tap outside. The nine kinds use `ItemKinds::choices()`, each with an example
("like a holster, a saddle or a jar of honey").

## 3. The rule

Every new form: a field of words gets suggestions (`data-spell`, or a textarea), and a list whose choices need
explaining gets their meanings (the ⓘ). Never `alert()`-style help, never a separate help page for what a choice means.

## 4. Our own words, and the kinds a name allows (Jason, 2026-11-04; built on the 14:24 UTC sync)

"Snickerdoodliee" got no suggestion, because no general dictionary has "snickerdoodle" (WordNet, the commonest 100,000,
nor the free spelling lists checked). And: "a word should be known and the context of the word or words should align the
scope of the item into what is an allowed kind. Animals can't be made to order, or Hot Spicy Tamales can't be services."

- **Our own words** (`Spelling::ours()`): `app/data/word-local.php`, hand-kept and grouped by what each word names (food,
  plants, animals, made things, work, places): this region's baked goods, Southwest and Native foods, crafts, ranch and
  outdoor gear, trades, stays. Plus System Configuration → the Marketplace's **Words our spelling knows** (our team's
  list), plus words the site learns: a word in published items from two or more businesses (cached an hour). All are known
  words and offered as suggestions.
- **What a name is** (`ItemKinds::fit()`): the name's main noun (the last noun; "box of cookies" reads the cookies, "puppy
  for sale" the puppy, adjectives skipped), by our own words or WordNet's commonest common sense
  (`app/data/word-senses.php`, `workers/lexicon/build_senses.py`). Food, plants and substances: Physical goods, Made to
  order, Harvest & perishables, Subscriptions. Animals: Physical goods only. Places: Bookings, Rentals, Tickets, Services.
  Made things, work, events, writing and people more loosely. Words in the name add a kind ("rental", "class",
  "membership", "PDF", "custom"). A noun nobody knows limits nothing.
- **Where it holds:** the catalog's Add and Details refuse a kind that doesn't fit, with why; publishing refuses one too
  (items made before); a Square import takes Square's type unless the name says otherwise. In the form, kinds that don't
  fit grey out as the name is typed, with a line under the list naming those that fit.
- **The list's pop-up** (Jason: "the popup looks bad, need better CSS UI UX practices"): a dialog over a dimmed page, a
  bottom sheet on a phone and centered wider; each choice a row with a radio, its name and what it is, the current one
  marked, hover never looking like a choice; the kinds that fit first, the rest under "Not for …" by name only. Esc, ×
  or a tap outside closes it; arrows move between choices.

