// A board page opened the way a person does at that width (board-phone, 2026-11-24): the rail at 768px and wider, the ☰
// beside Alerts on a phone (Jason, 2026-11-24: "a hamburger menu for pages like we've used before"). Shared by the tests.
module.exports.view = async (p, v) => {
  const rail = p.locator('nav.cb-rail [data-cb-rail="' + v + '"]');
  if (await rail.isVisible()) return rail.click();
  await p.click('.cb-pmenu > summary'); await p.waitForTimeout(150);
  return p.click('.cb-pml [data-cb-prail="' + v + '"]');
};
