# ADR 0038: Ownership Transfer — Non-Removable Owner, Acceptance-Gated Transfer, Financial Responsibility Follows Ownership

**Status:** Accepted (2026-09-25)

## Context

`decisions/0018` gave a listing real multi-user access (owner, manager) but explicitly flagged what it didn't solve: "what happens when an owner leaves the business entirely with managers still active... there's no 'promote a manager to owner' transfer flow." A listing can't be left ownerless, and a listing's paid tier (`decisions/0035`'s per-listing Subscription) has to have someone financially responsible for it at all times — an owner change can't silently leave that ambiguous either. Per direction, this ADR closes that gap.

## Decision

**The owner cannot be removed.** Unlike a manager — who the owner can remove, or who can step down themselves (`decisions/0018`) — there is no "remove owner" action. A listing always has exactly one owner; the only way that changes is a Transfer.

**Ownership transfers to an active user, and the transfer must be accepted, per direction.** The current owner initiates a transfer naming a specific active user (the same registered, email-confirmed-user requirement `decisions/0018` already holds invitees to — no transferring ownership to a guest or an unregistered email). The named user must explicitly accept before ownership actually moves — mirroring the same confirmation-token/accept pattern already used for manager invites (`decisions/0018`) rather than inventing a new mechanism. Until accepted, the current owner remains the owner in full; a pending, unaccepted transfer changes nothing about who holds authority in the meantime.

**Financial responsibility transfers with ownership, per direction.** The listing's Subscription (`decisions/0035`) — its tier, its billing cadence, its grant source (paid or promotional, `decisions/0034`) — stays exactly as it was; what changes is who is financially responsible for it. On acceptance, that responsibility moves from the previous owner to the new one, matching the general principle that whoever holds real authority over the listing is also who's accountable for what it costs.

**What happens to existing delegates (managers, an Executive designation, configured roles below manager) is a real choice, not a fixed platform default, per direction.** At proposal, the outgoing owner chooses: keep every existing delegate as-is, or remove all of them as part of the transfer. **The receiving user gets the same choice, independently, at acceptance, per direction.** Both sides get a real say over the roster they're handing off or taking on — this isn't the outgoing owner's call alone, and it isn't silently inherited or silently wiped by platform default either. **On disagreement, the incoming owner's choice wins, per direction.** If the outgoing owner picked keep and the incoming owner picks remove (or vice versa), the incoming owner's choice is what actually takes effect once the transfer completes — consistent with financial responsibility and real authority both landing on the new owner at that same moment; whoever is about to own and pay for the listing gets the final say over who else has access to it.

## Consequences

`listing_access`'s owner row needs a real transfer mechanism — a pending-transfer state (proposed, to-user, expiry) distinct from the row itself changing hands only on acceptance, plus a `POST /listing/api/transfer.php`-shaped pair of endpoints (propose/accept, mirroring `decisions/0018`'s invite/accept shape) — not yet designed, flagged here rather than assumed. The Subscription record (`decisions/0035`) needs its own explicit "financially responsible party" reference, separate from the listing/tier fields it already carries, so that reference is what actually moves on transfer acceptance — also not yet designed. The keep-all/remove-all choice above (and its two-sided outgoing/incoming shape, with the incoming owner's choice governing on disagreement) needs its own UI at both the proposal and acceptance steps — not designed yet. `decisions/0018`'s "deliberately out of scope" note about this exact gap is superseded by this ADR.
