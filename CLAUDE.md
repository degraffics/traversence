# Working notes for Claude sessions on this repo

## Pushing to GitHub from a cloud Cowork/Claude Code session

This repo (`degraffics/traversence` on GitHub) is version-controlled here as
the durable backup of Traversence's governance and technical documentation
(charter, PRD, commercial model, architecture, brand, phases, regions,
routes, workflow, future-considerations, `decisions/*.md`, the live
`traversence-platform-spec.md`, and Claude Docs exports under `claude-docs/`).

Cloud sessions reach GitHub through a local egress proxy. Two environment
variables (`GH_TOKEN`, `GITHUB_TOKEN`) show up as `proxy-injected`, and git is
configured to rewrite both SSH remote forms (`git@github.com:...` and
`ssh://git@github.com/...`) to HTTPS so the proxy can intercept the request
and inject a credential automatically — normal `git push`/`git pull` over
HTTPS, no manual token handling.

**The injection is gated per session, per repo.** A push can still fail with:

```
remote: access denied by the git proxy: <owner>/<repo> is not in this
session's authorized repository set, so the proxy will not inject a
credential for it. To fix, add the repository to the session's sources.
```

This means the proxy mechanism exists but this particular repo hasn't been
authorized for *this* session. It is not a token, config, or gitconfig
problem in this working tree — nothing in this repo or in `~/.gitconfig` can
fix it. If you hit this:

1. Don't retry the push as-is or try to route around it (no alternate auth,
   no disabling the proxy) — same guidance as any other 403 from the agent
   proxy (see `/root/.ccr/README.md`).
2. Tell Jason plainly that the push is blocked on repository authorization
   for this session, and give him the options:
   - Add `degraffics/traversence` to this session's authorized repository
     set (however that's surfaced in the Cowork/Claude session UI — this
     hasn't been pinned down from inside the session itself).
   - Supply a personal access token with push rights, and push with that
     directly instead of relying on the proxy.
   - Push from his own machine instead, if a folder/computer is linked via
     the remote-devices bridge and has its own git/GitHub access.
3. Local commits are safe either way — commit normally in the working copy
   even if the push has to wait. Don't let a blocked push stop you from
   getting the work committed.

Authorization is scoped to the session, so a prior successful push does not
guarantee the next session (or even a later point in the same one, if it
gets recreated) will have the same repo authorized — check by attempting the
push and reading the error, rather than assuming.

## Keep the FAQ current (decisions/0057)

`faq.md` is the site's FAQ and its only source: it ships as `website/includes/faq.md` and renders at `/faq.php`.
With every build, add or update its entries for whatever changed. Write them from a visitor's point of view, describe
what the site actually does, and say "coming" for anything not built yet. Include the updated `includes/faq.md` in
the delivery zip. Question anchors come from the wording, so rewording a question breaks links to it; check with
`grep -rn "faq.php#"` first.

## Phone layout and visual standard (decisions/0058 §11): apply to every page, every build

Space on a phone is at a premium. Every page, new or changed, follows this:

- **Edge to edge.** Below 768px, cards, lists of cards and panels run edge to edge with **3px** each side and **3px**
  between them, with small corners (`.5rem`). Text (headings, paragraphs, labels) keeps a small margin. The page
  frame (`includes/app-shell.php`) does this for the known card classes and Tailwind `rounded-xl/2xl border` cards;
  anything else opts in with `class="tv-bleed"`. Don't add per-page negative margins.
- **Card anatomy:**
  - the **name is the link**, top left, in brand brown (`#92400E`, underlined);
  - the **Link button** is in the top-right corner;
  - the **actions** (Call, Directions, See on map, Website, Contact, Peek) are a column of **30px round icon
    buttons down the right edge, under the Link button**, with `title`/`aria-label` and no text, icons in
    `#B45309`;
  - the **details** sit on the left.
- **Icons are the brand's flat SVGs** (`tv_icon()`), not emoji, for anything you tap. The active state is sage
  `#8FBCA8` with a soft glow, like the top bar; the rest state is flat cream with a brown ring.
- **No breadcrumbs on content pages.** The page says where it is. Its kind, category, agency, name and activity
  pills **search for more** (`TvSearch.open('<words> near <place>')`).
- **Filters live in the search** (its Quick picks), not in rows on the page. Show only active filters, each with ×.
- **Phone menus:** the ☰ tools button with a drop-down list (left), the person's profile and role (right).
- **Count outbound actions** with `data-out="call|directions|website|contact" data-e="<listing id>"`. This stores
  counts only, never who tapped.
- Check every page at 390px wide before delivering: card edges at 3px, no sideways scroll.
