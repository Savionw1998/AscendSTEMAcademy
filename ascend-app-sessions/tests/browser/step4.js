// Step 4: app-mode UI, only in a display-mode: standalone window; the website is untouched.
const { restrictTimeCard, openApp, EXE, chromium, BASE, check, done, newContext } = require('./lib');

const layout = (page) => page.evaluate(() => {
  const r = (sel) => { const e = document.querySelector(sel); return e ? e.getBoundingClientRect().toJSON() : null; };
  const vis = (sel) => { const e = document.querySelector(sel); return !!e && e.offsetParent !== null && getComputedStyle(e).visibility !== 'hidden'; };
  const mh = document.querySelector('#masthead');
  const tabs = [...document.querySelectorAll('.asa-tabbar a')].map((a) => ({ label: a.textContent.trim(), path: new URL(a.href).pathname, current: a.getAttribute('aria-current') === 'page' }));
  return {
    position: getComputedStyle(mh).position,
    headerBg: getComputedStyle(document.querySelector('#masthead .ast-primary-header-bar:not([style*="none"])') || mh).backgroundColor,
    header: mh.getBoundingClientRect().toJSON(),
    content: r('#content'),
    tcMargin: (() => { const e = document.querySelector('.elementor-element-241cfa88'); return e ? getComputedStyle(e).marginTop : null; })(),
    contentPad: getComputedStyle(document.querySelector('#content')).paddingTop,
    menuDesktop: vis('#ast-hf-menu-1'), toggle: vis('.menu-toggle'), mobileMenu: vis('#ast-hf-mobile-menu'),
    tabs, tabbar: r('.asa-tabbar'), bodyPad: parseFloat(getComputedStyle(document.body).paddingBottom),
    register: vis('.um-login a.um-button.um-alt'), login: r('.um-login #um-submit-btn'), form: r('.um-login form'),
  };
});
const current = (l) => (l.tabs.find((t) => t.current) || {}).label || null;

(async () => {
  restrictTimeCard('off'); // the website checks below look at the Time Card page logged out
  // Website: a normal browser tab, even with the asa_app cookie (it is shared with the app).
  const browser = await chromium.launch({ executablePath: EXE });
  const web = await newContext(browser, { app: true });
  const wp = await web.newPage();
  await wp.goto(BASE + '/guess-a-lotl/');
  let w = await layout(wp);
  check('web: no tab bar', w.tabs.length === 0 && w.tabbar === null);
  check('web: header still floats (Astra transparent header)', w.position === 'absolute', w.position);
  check('web: menu button still shown', w.toggle);
  check('web: live content offset untouched (90px on phones)', w.contentPad === '90px', w.contentPad);
  await wp.goto(BASE + '/login/');
  w = await layout(wp);
  check('web: Register button still shown', w.register);
  await wp.goto(BASE + '/time-card-tracker/');
  check('web: Time Card Elementor margin untouched (80px on phones)', (await layout(wp)).tcMargin === '80px');
  await wp.goto(BASE + '/contact-us/');
  check('web: hero offset untouched (100px on phones)', (await wp.evaluate(() => getComputedStyle(document.querySelector('.asa-hero')).marginTop)) === '100px');
  await browser.close();

  // App: a real display-mode: standalone window at phone size.
  const { ctx, page } = await openApp('/guess-a-lotl/');
  let a = await layout(page);
  check('app: four tabs in order, with the right links', JSON.stringify(a.tabs.map((t) => [t.label, t.path])) === JSON.stringify([['Dashboard', '/user/'], ['Time Card', '/time-card-tracker/'], ['Games', '/guess-a-lotl/'], ['Account', '/account/']]), a.tabs);
  check('app: tab bar fixed at the bottom', a.tabbar && Math.round(a.tabbar.bottom) === 860 && a.tabbar.height >= 56 && a.tabbar.height <= 64, a.tabbar);
  check('app: Games is current on Guess-a-lotl', current(a) === 'Games');
  check('app: header in the page flow, solid white, compact', a.position !== 'absolute' && a.position !== 'fixed' && a.headerBg === 'rgb(255, 255, 255)' && a.header.height <= 64, [a.position, a.headerBg, a.header.height]);
  check('app: content starts right below the header, 16px gap (no overlap)', Math.abs(a.content.top - a.header.bottom) < 1 && a.contentPad === '16px', [a.header.bottom, a.content.top, a.contentPad]);
  check('app: marketing menu and menu button hidden', !a.menuDesktop && !a.toggle && !a.mobileMenu);
  check('app: page padded so nothing hides under the tab bar', a.bodyPad >= a.tabbar.height);
  await page.goto(BASE + '/hex-a-lotl/');
  check('app: Games is current on every game page', current(await layout(page)) === 'Games');
  await page.goto(BASE + '/contact-us/');
  check('app: hero no longer pushed down (16px)', (await page.evaluate(() => getComputedStyle(document.querySelector('.asa-hero')).marginTop)) === '16px');

  restrictTimeCard('redirect'); // back to the live setting
  await page.goto(BASE + '/login/');
  a = await layout(page);
  check('app login: Register button hidden', !a.register);
  check('app login: Login button full width', a.login && Math.abs(a.login.width - a.form.width) < 2, [a.login && a.login.width, a.form && a.form.width]);
  check('app login: no tab is current', current(a) === null);
  await page.fill('#user_login-4414', 'family1');
  await page.fill('#user_password-4414', 'Test-pass-12345');
  await Promise.all([page.waitForNavigation(), page.click('#um-submit-btn')]);
  check('app: Dashboard is current on the family dashboard', current(await layout(page)) === 'Dashboard', page.url());
  await page.goto(BASE + '/time-card-tracker/');
  a = await layout(page);
  check('app: Time Card is current on the Time Card', current(a) === 'Time Card');
  check('app: Time Card not pushed down (padding 16px, Elementor 80px margin removed)', a.contentPad === '16px' && a.tcMargin === '0px', [a.contentPad, a.tcMargin]);
  await page.goto(BASE + '/account/');
  check('app: Account is current on the account page', current(await layout(page)) === 'Account');
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  const footer = await page.evaluate(() => [document.querySelector('#colophon').getBoundingClientRect().bottom, document.querySelector('.asa-tabbar').getBoundingClientRect().top]);
  check('app: end of the page scrolls clear of the tab bar', footer[0] <= footer[1] + 1, footer);
  await ctx.close();

  // App on a tablet-sized window: desktop header layout, same rules.
  const big = await openApp('/guess-a-lotl/', { viewport: { width: 1024, height: 768 } });
  a = await layout(big.page);
  check('app, 1024px: desktop menu hidden, header in flow, tab bar shown', !a.menuDesktop && a.position !== 'absolute' && a.tabs.length === 4, [a.menuDesktop, a.position]);
  await big.ctx.close();

  done();
})().catch((e) => { console.error(e); restrictTimeCard('redirect'); process.exit(2); });
