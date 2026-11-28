<!-- DRAFT. faq.md is not shown; the lane places these under the site/performance section. Status: coming. -->

### Why did pages sometimes jump around while they loaded?

**Coming.** Our pages used to get their styling from an outside service after they had started to appear. That is why text and cards could shift for a moment. We are moving to one stylesheet built ahead of time and served from our own server, so a page arrives already styled and stays still.

### Does Traversence load code from other companies to style its pages?

**Coming.** Today, one outside script (Tailwind's CDN) styles our pages. Soon our own stylesheet, served from Traversence's server, will replace it. Your browser will then no longer contact that service when you open a page. It costs you nothing and changes nothing you need to do.
