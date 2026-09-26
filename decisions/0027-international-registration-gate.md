# ADR 0027: International Registration Gate — Home Location & Current-Location Dual-Signal

**Status:** Accepted (2026-09-24)

## Context

Per `decisions/0025`'s GDPR position (out of scope on an intent/targeting basis), Traversence has a real interest in demonstrating active, good-faith steps not to serve EU/UK residents, rather than resting solely on stated intent — this materially strengthens that position if it's ever questioned, at low cost. Separately, `session.php` already reads `users.home_cluster_id` for search auto-scope, but no registration-time mechanism populates it — a real, independent product gap this same design closes.

## Decision

### 1. Home location — captured from every registrant, for the product purpose

Country is captured from every registrant. If Country is United States, a Zip is additionally captured; City/State are auto-resolved from the existing `zip_coordinates` table (the same lookup `PlaceScope.php` and `session.php` already use for auto-seeded cluster naming) rather than free-typed, avoiding spelling mismatches against the database. This resolves `home_cluster_id` at registration time, closing the existing `session.php` gap. For any other country, `home_cluster_id` stays null — `session.php` already handles this gracefully, and no non-U.S. postal-code data model exists yet to resolve against.

### 2. Current location (IP) — a silent background compliance signal, decoupled from the product purpose

The registration request's IP address is checked against a local IP-to-country database (e.g. MaxMind GeoLite2 Country) — a flat local lookup, no external API dependency, consistent with this codebase's existing no-external-dependency posture (`Mailer.php`'s own hand-rolled SMTP client). This is never shown to the user as a question; it runs silently in the background.

### 3. Routing logic — narrow by design; both traveler false-positive directions resolve automatically

Home location and current-location IP answer different questions — "where do you live" versus "where are you right now" — and only the second is what GDPR Article 3(2) actually cares about. Only when both signals agree the registrant is currently in the EU/UK does anything different happen; either signal alone indicating otherwise is enough to let registration proceed normally, with no manual step:

- Home = non-EU/UK, IP = EU/UK (e.g. an American traveling through Europe): passes through automatically.
- Home = EU/UK, IP = non-EU/UK (e.g. a UK resident who has already landed in the U.S.): passes through automatically — per Article 3(2)'s location-at-time-of-processing test, this person is not "in the Union" at the moment of the activity in question.
- Home = EU/UK, IP = EU/UK: the only case routed differently — see below.

### 4. The narrow exception case — deferred, manual, not self-service

Per direction, when both signals agree the registrant is currently in the EU/UK, self-service registration is not offered. The registration form shows a clear, honest message ("Traversence is a U.S. regional business directory and isn't currently available to residents of the EU or UK") with a support contact for a manual accommodation (e.g. a traveler planning a U.S. trip who wants an account before arriving) — an admin-run script mirroring the existing hand-run `scripts/migrate_listings.php` pattern, calling `Auth::register()` directly, not a new UI feature.

This is a deliberate choice, not a technical limitation: a rare, individually-granted, user-initiated accommodation is a materially weaker "this platform targets the EU/UK" signal than an automated, self-service EU/UK signup path would be. The latter would require the full GDPR compliance program that `decisions/0025` currently avoids needing — real operational consent capture, the DSAR pipeline (`decisions/0026`) actually serving that population end-to-end, EU-U.S. Data Privacy Framework self-certification for cross-border data transfer, and a review of whether Article 27's EU-representative requirement applies. Building that program is recorded in `future-considerations.md` for reconsideration if international demand ever justifies it — it is explicitly not committed to here.

### 5. Scope of the gate

This applies only at registration (the point of identity/personal-data intake), never at browsing — anonymous directory access remains open worldwide, same as any public website, preserving nearly all of the pre-trip travel-planning use case without any gate at all.

## Consequences

`routes.md`'s `/register` row gains the home-location fields and the geoblock logic described above. `architecture.md` §24's data-collection inventory gains the new home-location field (country, and zip for U.S. registrants) with its stated purpose (regional search scoping). The alternative — serving EU/UK users properly with no manual exception — is recorded in `future-considerations.md` rather than built now, consistent with how `decisions/0025` keeps GDPR out of scope specifically by not deliberately targeting that population.
