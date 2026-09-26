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
