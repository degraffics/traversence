/*
 * Traversence: the site's stylesheet, built once with the tailwindcss CLI.
 * Decision: Jason, 2026-11-27, "Yes to the stylesheet" (note on design/css-theme-brand.md).
 * This replaces the Play CDN (cdn.tailwindcss.com). To build, run tools/css-build/build.sh.
 *
 * DRAFT. The folder list below is taken from paths named in decisions/0058 and
 * design/css-theme-brand.md. It is not a listing of the repo.
 * tools/css-tests/stylesheet-test.php fails when a top-level folder that holds
 * PHP is missing from this list. The lane adds or removes folders until it passes.
 */
module.exports = {
  content: [
    './*.php',
    './admin/**/*.php',
    './api/**/*.php',
    './app/**/*.php',
    './discovery/**/*.php',
    './hub/**/*.php',
    './includes/**/*.php',
    './journeys/**/*.php',
    './listing/**/*.php',
    './place/**/*.php',
    './user/**/*.php',
    './js/**/*.js',
  ],
  // LANE: copy here, unchanged, the live page's inline `tailwind.config = { ... }`
  // (the script next to the CDN tag). File not shown.
  // If the CDN tag carries ?plugins=..., add each plugin here and in build.sh.
  theme: {
    extend: {},
  },
  // Classes the CLI cannot see belong here. These are classes built by string
  // concatenation in PHP or JS ('bg-' . $tone), and classes that live in
  // database content. The Play CDN found them at runtime; the CLI does not.
  safelist: [],
  plugins: [],
};
