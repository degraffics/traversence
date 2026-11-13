# Releases inbox (decisions/0159 §5, Phase 6)

Claude's finished builds wait here as release zips (files at their real paths, `app/…`, `website/…`, `trvgo/…`, with a
`release.json`). Put one here with `tools/page-audit/send.py`, which checks it and logs it first, then merge it into
`main`.

The live site looks here every 10 minutes (its releases job), or at once from the site manager on staging (**Releases ▾ →
Check for builds**). It fetches each zip with its GitHub key, checks its bytes against git's id, and makes it a release
on staging. Then it deletes the zip from here; git's history keeps it. Only `.zip` files are read; this README stays.
