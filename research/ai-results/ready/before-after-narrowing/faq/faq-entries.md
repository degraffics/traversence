### What does the before-and-after check stop?

Before a change goes live, the test-account advocates look at every page it touches on staging, before the change and after it. The change is stopped only when a page is made worse in one of four ways (decisions/0179):

- someone can see what they shouldn't, loses access, or gets an error (tribal nations' protected places included);
- the layout breaks, on a computer or a phone;
- data goes missing, or a core step stops working;
- a page changes that the change didn't say it would.

Anything else the advocates notice, such as an image without a description, is written on the release's card for the team to fix, and doesn't stop it. A file the change's plan didn't name opens a ticket in the Work queue; it doesn't stop the release or roll it back (decisions/0180 §8).

### Do the advocates check the site between releases?

Coming. Once it's switched on, the advocates look at staging once a day as their test accounts, on their own schedule and independent of anyone building. A problem they see on two days in a row opens one ticket in the Work queue for the team that owns that screen. They never build, move a ticket, or stop a release, and their look waits while a release is being checked.
