// Step 2: return to where you tapped.
const { restrictTimeCard: restrict, openApp, EXE, chromium, BASE, check, done, sessions, newContext, login } = require('./lib');

const path = (url) => new URL(url).pathname + new URL(url).search;
const TC = BASE + '/time-card-tracker/';

async function submitLogin(page, user) {
  await page.fill('#user_login-4414', user);
  await page.fill('#user_password-4414', 'Test-pass-12345');
  await Promise.all([page.waitForNavigation(), page.click('#um-submit-btn')]);
}

(async () => {
  const browser = await chromium.launch({ executablePath: EXE });

  // Live since 2026-09-28: UM itself sends logged-out visitors to login ("Redirect user"); the
  // login form then brings them back.
  restrict('redirect');
  {
    const ctx = await newContext(browser);
    const resp = await ctx.request.get(TC, { maxRedirects: 0 });
    check('live setting: logged out, 302 to /login/?redirect_to=<page>', resp.status() === 302 && (resp.headers()['location'] || '') === BASE + '/login/?redirect_to=' + encodeURIComponent(TC), resp.headers()['location']);
    const page = await ctx.newPage();
    await page.goto(TC);
    await submitLogin(page, 'family1');
    check('live setting: back on the Time Card after login', page.url() === TC && (await page.locator('#tc-content').isVisible()), page.url());
    await ctx.close();
  }
  restrict('off');
  {
    const ctx = await newContext(browser);
    const resp = await ctx.request.get(TC, { maxRedirects: 0 });
    check('restriction off: page is public, no redirect', resp.status() === 200);
    await ctx.close();
  }

  // Pages restricted with "Show access restricted message": this plugin sends logged-out visitors
  // to login -> back to the page.
  restrict('message');
  {
    const ctx = await newContext(browser);
    const resp = await ctx.request.get(TC, { maxRedirects: 0 });
    const loc = resp.headers()['location'] || '';
    check('restricted page, logged out: 302 to /login/?redirect_to=<page>', resp.status() === 302 && loc === BASE + '/login/?redirect_to=' + encodeURIComponent(TC), loc);
    const page = await ctx.newPage();
    await page.goto(TC);
    check('browser lands on the login page', path(page.url()).startsWith('/login/?redirect_to='));
    check('login form carries redirect_to = the page', (await page.getAttribute('input[name=redirect_to]', 'value')) === TC);
    await submitLogin(page, 'family1');
    check('after login: back on the Time Card', page.url() === TC, page.url());
    check('Time Card content visible', await page.locator('#tc-content').isVisible());
    await ctx.close();
  }
  {
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await login(page, 'teacher1');
    await page.goto(TC);
    check('um_faculty can open the Time Card', page.url() === TC && (await page.locator('#tc-content').isVisible()));
    await ctx.close();
  }
  {
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await login(page, 'subscriber1');
    const resp = await page.goto(TC);
    check('logged in without an allowed role: no redirect loop, UM message stays', page.url() === TC && resp.status() === 200 && (await page.locator('#tc-content').count()) === 0);
    await ctx.close();
  }

  // Where a login goes when it was not sent from anywhere, and unsafe redirect_to values.
  async function loginFrom(url) {
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await page.goto(url);
    await submitLogin(page, 'family1');
    const u = page.url();
    await ctx.close();
    return u;
  }
  let u = await loginFrom(BASE + '/login/');
  check('plain /login/: goes to the dashboard (/user/...)', path(u).startsWith('/user/'), u);
  u = await loginFrom(BASE + '/login/?redirect_to=' + encodeURIComponent('https://evil.example/phish'));
  check('off-site redirect_to: ignored, dashboard instead', new URL(u).host === new URL(BASE).host && path(u).startsWith('/user/'), u);
  u = await loginFrom(BASE + '/login/?redirect_to=' + encodeURIComponent(BASE + '/logout/'));
  check('redirect_to=/logout/: ignored (no instant logout)', path(u).startsWith('/user/'), u);
  u = await loginFrom(BASE + '/login/?redirect_to=guess-a-lotl');
  check('redirect_to relative to the login page: ignored', path(u).startsWith('/user/'), u);
  u = await loginFrom(BASE + '/login/?redirect_to=' + encodeURIComponent('//evil.example/x'));
  check('protocol-relative off-site redirect_to: ignored', new URL(u).host === new URL(BASE).host && path(u).startsWith('/user/'), u);
  u = await loginFrom(BASE + '/login/?redirect_to=%2Fguess-a-lotl%2F');
  check('relative redirect_to works', path(u) === '/guess-a-lotl/', u);

  // Dashboard and account while logged out.
  {
    const ctx = await newContext(browser);
    const r = await ctx.request.get(BASE + '/user/', { maxRedirects: 0 });
    check('/user/ logged out: login with redirect_to=/user/ (was: home page)', r.status() === 302 && (r.headers()['location'] || '') === BASE + '/login/?redirect_to=' + encodeURIComponent(BASE + '/user/'), r.headers()['location']);
    const page = await ctx.newPage();
    await page.goto(BASE + '/account/');
    await submitLogin(page, 'family1');
    check('/account/ logged out: login, then back to /account/', path(page.url()) === '/account/', page.url());
    await ctx.close();
  }

  // Start URL.
  {
    const ctx = await newContext(browser, { app: true });
    const r0 = await ctx.request.get(BASE + '/', { maxRedirects: 0 });
    check('app, logged out: home page shows', r0.status() === 200);
    const page = await ctx.newPage();
    await login(page, 'family1');
    const r1 = await ctx.request.get(BASE + '/', { maxRedirects: 0 });
    check('app, logged in: / goes to /user/', r1.status() === 302 && r1.headers()['location'] === BASE + '/user/', r1.headers()['location']);
    await ctx.close();
    const web = await newContext(browser);
    const wp = await web.newPage();
    await login(wp, 'family1');
    const r2 = await web.request.get(BASE + '/', { maxRedirects: 0 });
    check('website, logged in: home page still shows', r2.status() === 200);
    await web.close();
  }

  // The whole thing in a standalone app window: tap Time Card logged out, log in, come back.
  {
    sessions('family1', true);
    restrict('redirect');
    const { ctx, page } = await openApp('/time-card-tracker/');
    check('app window: Time Card tap lands on login', path(page.url()).startsWith('/login/?redirect_to='));
    check('app window: "Keep me signed in" is not shown', !(await page.locator('.um-login .um-field-c:has(input[name=rememberme])').isVisible()));
    await submitLogin(page, 'family1');
    check('app window: back on the Time Card after login', page.url() === TC, page.url());
    await page.goto(BASE + '/');
    check('app window: start URL now opens the dashboard', path(page.url()).startsWith('/user/'), page.url());
    await ctx.close();
  }

  restrict('redirect'); // leave the fixture as live
  await browser.close();
  done();
})().catch((e) => { console.error(e); restrict('redirect'); process.exit(2); });
