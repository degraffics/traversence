# Traversence Platform-Wide UI/UX & CSS Architecture Redesign Directive

Jason, 2026-11-18, worked out with Gemini and given to the session manager verbatim. It replaces
`design/admin-design-directive-2026-11-18.md` (kept as history) and is the brief for the design session. brand.md §4's
note of the same date records the palette decision.

---

**Core Objective:** Rebuild the CSS foundational layers and interface structures across the entire Traversence platform to deliver a calm, intuitive, and easy-to-use user interface. The redesign eliminates visual clutter and cognitive friction, establishing an experience grounded in harmonic balance and balanced energies that makes navigating and operating the platform effortless for every user.

## 1. Spatial Flow & Simplicity (Eliminating Vertical "Stacking")

* **Streamlined Header Strata:** Eliminate multi-tiered utility bars, redundant sub-navigation strips, and scattered filter controls. Consolidate functional layers into a single, cohesive, intuitive header per view. Treat generous whitespace as an open, calming current that guides user focus naturally without visual fatigue.
* **Unified Filtering & Search Model:** Enforce a single, clean dropdown filter model rather than sprawling horizontal pill rows (`brand.md`, `CLAUDE.md`). Standardize on one prominent, easily accessible search input (`Ctrl + K` / Global Platform Search) so users never have to hunt across competing search bars. Exception: Module-specific lists (such as Pulse) retain a dedicated, clearly labeled local filter box ("Filter this list"), distinct from the global platform search. The Get Local / Directory section retains its dedicated search/refine inputs per ADR 0058.
* **Single Scroll Discipline:** Maintain a clean, single-primary-scrollbar architecture across all platform views (per ADR `0080`) to preserve a natural, grounded reading rhythm and prevent layout shifting.

## 2. Harmonic Balance & Alert Calibration (Calming the Energy)

* **Intelligent Notification Rollups:** Replace cascading, repetitive walls of individual warning or error rows with clean, grouped summary banners across system feeds and modules (limited strictly to system warnings, errors, and background data feeds—never grouping people's individual direct messages or posts, per ADR 0172):
  `[!] 5 data sync streams require your attention — Click to expand resolution.`
  (Collective actions handle the batch cleanly; discrete steps address individual items).
* **Balanced Color & Emotional Frequency:**
  * System Red (`#a62b2b`) is strictly reserved for genuine system-halting emergencies, critical infrastructure failures (Bluehost, Railway, GitHub), or open active Trips.
  * Grounded Amber (`#8f5600`) is designated for routine timeouts, waiting states, or non-blocking holds (marked as `"Held"`), maintaining a calm, regulated atmosphere rather than triggering false alarm fatigue.
  * Muted Sage (`#92BEA5`) and Burnished Copper (`#C85A17`) anchor active health and primary interactive cues in visual harmony with the warm canvas.

## 3. Canonical Token Binding & Material Integrity (`brand.md` / `css-theme-brand`)

All styling across every CSS file, shared shell (`app/ui/app-shell.php`), and component template must strictly bind to our established design tokens:

* **Canvas & Surfaces:** Primary warm aged vellum canvas (`#EFE3C8`), deep umber structural top bars (`#3E2B1F`), slate-umbra atmospheric voids (`#231D17`), and elevated micro-gradient cards (`#E6D8B5` → `#D8C79D`) that evoke natural parchment and relief maps.
  *Note (Jason, 2026-11-19):* the canvas and the cards here are replaced by three levels, one warm hue: page `#E4DCC5`, section `#EDE6D5`, card `#F6F1E4`, fields the card colour with a darker edge (brand.md §4's note).
* **Typography Hierarchy:** Warm Near-Black (`#2A1D14`) serif headings (`Merriweather` / `Playfair Display`) for editorial warmth; modern sans-serif (`Inter` / `system-ui`) for crisp data displays and UI controls; Muted Taupe (`#7A6A52`) for supportive metadata.
* **Track & Cartographic Accents:** Muted Sage (`#92BEA5`) for Resident ("Get Local") and active states; Deep Burnished Copper (`#C85A17`) for Traveler ("Let's Explore") and primary actions; Warm Amethyst (`#7D6B8D`) for Community ("Connect"); Oxidized River Blue (`#3B5A6B`), Faded Forest Moss (`#6B7F62`), and Crimson Thread (`#8C3A23`) for cartographic features and routing.
* **Motion, Accessibility & Mobile Geometry:** Enforce smooth `150ms ease-out` mechanical dampening transitions, guarantee a minimum `4.5:1` contrast ratio against `#EFE3C8`, render visible focus indicator rings in active system tokens, and maintain edge-to-edge mobile geometry below 768px with strict `3px` card gaps, rounded corners, and zero horizontal scrolling at `390px` (per `0058 §11`).

## 4. Repository Structure & Implementation Scope (Phased Rollout)

To prevent build drift and maintain stability across live operations, the platform-wide CSS rebuild will execute in strict, sequential releases housed within our tracked repository structure (`design/css-theme-brand.md` and related source paths):

* **Release 1:** Shared colour/spacing tokens and the shared app shell (`app/ui/app-shell.php`).
* **Release 2:** Admin and operational control surfaces.
* **Release 3:** Public pages, rolled out one distinct area at a time.

## 5. Session Persona, Guardrails & Zero-Complacency Protocol

* **Role Definition:** Act as an expert Principal UI/UX Architect and CSS Systems Engineer specializing in high-integrity, human-scale digital platforms.
* **Zero-Complacency Protocol:** Stop assuming default AI outputs are successful. You must rigorously audit your own work against our strict baselines before presenting code.
* **Mandatory Pre-Flight Checks:** Verify token integrity (`brand.md` literal hex values only), single-scroll compliance (ADR `0080`), header consolidation, and mobile edge-to-edge integrity (`390px` viewports per `0058 §11`).
* **Anti-Pattern Rejection:** Instantly reject sprawling filter pill rows, competing global search bars, generic tech-startup white cards, and improper alarm red coloring.
* **Required Response Structure:** When presenting work, omit conversational cheerleading. Detail your token compliance, rule verification, and clean production code.

*Note (Jason, 2026-11-27: "Yes to the stylesheet", to the CEO's Decision):* the site's styling is built once as its
own stylesheet with the tailwindcss CLI over the site's files, served from our server, in place of Tailwind's Play CDN
(`cdn.tailwindcss.com`) at runtime. It ends the CDN-caused layout shift sitewide and drops a third-party script, at $0;
decisions/0135 and 0098's outside-services rows ("To self-host as a built stylesheet (then dropped from the list)") are
carried out by it. The Frontend & Shop session builds it with the design releases, through the Design reviewer, in the
session manager's order; C.5a and C.5b stay as they are meanwhile.
