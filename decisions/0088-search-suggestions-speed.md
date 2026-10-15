# 0088. Search suggestions: read only the words needed, find towns by index, never wait in line

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** 0060 (two tiers: suggestions while typing), 0067 (concepts)

## Context

Jason: "It takes way too long to pull in suggestions." Profiling a suggestion request on a copy of the site found four
causes, each paid on every keystroke:

1. **The whole concepts file was read.** That's `app/data/concepts.php`: 6.7 MB, 117,000 words. Without a warm opcode
   cache it cost about a quarter of a second and 107 MB of memory. A file that size is unlikely to stay in a shared
   host's opcode cache.
2. **Towns were found by reading every ZIP.** Each word was checked as a possible town name 15 to 20 times, and each
   check read all 41,000 ZIPs. The lookups wrapped the column in `TRIM()`, `LOWER()` or a chain of `REPLACE()`, so no
   index could help, and `city` had none anyway.
3. **The listing search counted after reading.** When no name has all the words, suggestions fall back to the full
   listing search. It ran a separate count over the same rows, twice, even when it had found fewer than 5.
4. **Requests waited in line.** PHP holds a visitor's session for one request at a time. Each keystroke's request
   waited for the one before it to finish, and nothing cancelled the old ones.

## Decision

1. **The concepts are split** into a small file per first two letters (`app/storage/concepts/`), and `Concepts`
   reads only the parts the typed words start with. The largest part is 250 KB, and the data is identical to
   `concepts.php`.
   - **The site makes the split itself** (`Concepts::split()`), on the first search after `concepts.php` is uploaded
     or changed. Its time and size are kept with the split, so a new one is noticed.
   - The split takes about a second, once. One request does it while the others read the whole file.
   - If storage can't be written, the whole file is read as before.
   - Nothing extra to upload. A first version shipped the 800 split files in the zip, too many to move by hand.
2. **Town lookups use indexes** (`2026-11-22_zip_city_index.sql`). The SQL:
   - trims the names;
   - indexes `city` and `county`;
   - adds `city_key`, the name squashed ("coeur d alene" = "Coeur d'Alene"), kept by the database itself with an index.

   The queries compare the plain column (its collation already ignores case), so the indexes work. The `city_key` read
   is used only once the column exists.
3. **Fewer than 5 listings found is the count.** The count query runs only when there may be more.
4. **Suggestions let go of the session** as soon as they've read it. **The browser cancels** the request for the words
   before, and keeps answers for a minute, so backspacing or retyping shows them at once.

**A small ranking change.** Towns with the same number of ZIPs now go by population ("stor": Storm Lake, IA before
Storden, MN). Before, a tie fell in whatever order the table gave.

## Checked on a copy of the site

- **Same answers:** suggestions, kinds, finished words, forms and "knows" for 24 test searches came out identical, apart
  from the population tie-break.
- **Server time per suggestion, before → after:**

  | Typed | Before | After |
  |---|---|---|
  | "pi" | 309 ms | 53 ms |
  | "pizza" | 437 ms | 68 ms |
  | "gas sta" | 619 ms | 103 ms |
  | "live mu" | 793 ms | 120 ms |
  | "x ray" | 523 ms | 258 ms |

- **In a phone-width browser:** suggestions showed right after typing stopped, a retyped word came back from the
  browser's copy in 77 ms, and there were no errors.

## Not done

- The full listing search, which is what's left in "x ray" and other searches with no name match, still reads listing
  details stored per field (`entity_metadata`). Moving the fallback onto `search_index` would speed it up further.
