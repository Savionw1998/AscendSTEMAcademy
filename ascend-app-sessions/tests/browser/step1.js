// Step 1: keep app users logged in.
const { setCookieOf, openApp, EXE, chromium, BASE, DAY, check, done, sessions, newContext, login, authCookie, now } = require('./lib');

(async () => {
  const browser = await chromium.launch({ executablePath: EXE });

  // --- Detection -----------------------------------------------------------
  {
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await page.goto(BASE + '/');
    check('plain web visit: no asa_app cookie', !(await ctx.cookies()).some((c) => c.name === 'asa_app'));
    check('detection script is in <head>', (await page.locator('head script#ascend-app-detect-js, head script#ascend-app-detect').count()) === 1);
    await ctx.close();
  }
  {
    // First launch: the TWA's navigation carries Referer: android-app://<package>/ (desktop Chromium
    // refuses that referrer on navigations, so send the request directly).
    const ctx = await newContext(browser);
    const resp = await ctx.request.get(BASE + '/', { headers: { Referer: 'android-app://com.ascendstemacademy.twa/' } });
    const setCookie = await setCookieOf(resp);
    check('android-app referrer: PHP sets asa_app=1', /asa_app=1/.test(setCookie), setCookie);
    check('PHP cookie is Path=/, SameSite=Lax, 1 year', /path=\//i.test(setCookie) && /samesite=lax/i.test(setCookie) && /max-age=31536000/i.test(setCookie), setCookie);
    const c = (await ctx.cookies()).find((x) => x.name === 'asa_app');
    check('android-app referrer: browser keeps asa_app=1 for a year', !!c && c.value === '1' && Math.abs(c.expires - now() - 365 * DAY) < 120, c);
    await ctx.close();
    const ctxWeb = await newContext(browser);
    const other = await ctxWeb.request.get(BASE + '/', { headers: { Referer: 'https://www.google.com/' } });
    check('web referrer: PHP sets no asa_app', !/asa_app/.test(await setCookieOf(other)));
    await ctxWeb.close();

    // The in-page script, run against a TWA-like document.referrer (and the https Secure flag).
    const page = await (await (await newContext(browser)).newPage());
    await page.goto(BASE + '/');
    const code = await page.locator('head script#ascend-app-detect').textContent();
    const run = (referrer, standalone, protocol) => { const d = { referrer, cookie: '' }; const w = { matchMedia: () => ({ matches: standalone }) }; new Function('document', 'window', 'location', code)(d, w, { protocol }); return d.cookie; };
    check('script: android-app referrer sets cookie', /^asa_app=1; Max-Age=31536000; Path=\/; SameSite=Lax; Secure$/.test(run('android-app://com.ascendstemacademy.twa/', false, 'https:')), run('android-app://com.ascendstemacademy.twa/', false, 'https:'));
    check('script: normal referrer + browser tab sets nothing', run('https://www.google.com/', false, 'https:') === '');
    check('script: standalone with no referrer sets cookie', /^asa_app=1;/.test(run('', true, 'https:')));
  }
  {
    const { ctx, page } = await openApp('/');
    check('app window matches display-mode: standalone', await page.evaluate(() => matchMedia('(display-mode: standalone)').matches));
    const c = (await ctx.cookies()).find((x) => x.name === 'asa_app');
    check('display-mode standalone: script sets asa_app=1 (Lax, 1 year)', !!c && c.value === '1' && c.sameSite === 'Lax' && Math.abs(c.expires - now() - 365 * DAY) < 120, c);
    await ctx.close();
  }

  // --- Login form in app mode (cookie set manually) -------------------------
  {
    const ctx = await newContext(browser, { app: true });
    const page = await ctx.newPage();
    await page.goto(BASE + '/login/');
    check('app login: no visible "Keep me signed in" checkbox', (await page.locator('.um-login input[type=checkbox][name=rememberme]').count()) === 0);
    check('app login: hidden rememberme=1', (await page.locator('.um-login input[type=hidden][name=rememberme][value="1"]').count()) === 1);
    check('app login: username autocomplete=username', (await page.getAttribute('#user_login-4414', 'autocomplete')) === 'username');
    check('app login: password autocomplete=current-password', (await page.getAttribute('#user_password-4414', 'autocomplete')) === 'current-password');
    await ctx.close();
  }
  {
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await page.goto(BASE + '/login/');
    check('web login: "Keep me signed in" still shown', await page.locator('.um-login .um-field-c:has(input[name=rememberme])').isVisible());
    check('web login: autocomplete untouched', (await page.getAttribute('#user_login-4414', 'autocomplete')) === 'off');
    await ctx.close();
  }

  // --- Session length by role ------------------------------------------------
  async function loginExpiry(user, opts) {
    sessions(user, true);
    const ctx = await newContext(browser, opts);
    const page = await ctx.newPage();
    await login(page, user, user === 'admin' ? 'admin-pass-123' : null, opts);
    const c = await authCookie(ctx);
    const s = sessions(user);
    await ctx.close();
    return { cookieDays: c ? (c.expires === -1 ? 'session' : (c.expires - now()) / DAY) : null, sessionDays: s.sessions.length ? (s.sessions[0] - now()) / DAY : null };
  }
  let r = await loginExpiry('family1', { app: true });
  check('family (um_student) in app: cookie ~90 days', typeof r.cookieDays === 'number' && Math.abs(r.cookieDays - 90.5) < 0.1, r);
  check('family in app: server session 90 days', Math.abs(r.sessionDays - 90) < 0.1, r);
  r = await loginExpiry('family1', {});
  check('family on web, remember unticked: browser-session cookie, 2-day session', r.cookieDays === 'session' && Math.abs(r.sessionDays - 2) < 0.1, r);
  r = await loginExpiry('family1', { remember: true });
  check('family on web, remember ticked: 14 days (unchanged)', Math.abs(r.cookieDays - 14.5) < 0.1, r);
  for (const u of ['teacher1', 'editor1', 'admin']) {
    r = await loginExpiry(u, { app: true });
    check(`${u} in app: keeps default 14 days`, Math.abs(r.cookieDays - 14.5) < 0.1 && Math.abs(r.sessionDays - 14) < 0.1, r);
  }

  // --- Login page served from cache (rendered for the web), opened in the app --
  {
    const web = await newContext(browser);
    const cached = await (await (await web.newPage()).goto(BASE + '/login/')).text();
    await web.close();
    sessions('family1', true);
    const ctx = await newContext(browser, { app: true });
    const page = await ctx.newPage();
    await page.route(BASE + '/login/', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: cached }));
    await page.goto(BASE + '/login/');
    await page.waitForLoadState('load');
    const box = page.locator('.um-login input[type=checkbox][name=rememberme]');
    check('cached login page: checkbox ticked by app.js', await box.isChecked());
    check('cached login page: "Keep me signed in" hidden by app.js', !(await page.locator('.um-login .um-field-c:has(input[name=rememberme])').isVisible()));
    check('cached login page: autocomplete set by app.js', (await page.getAttribute('#user_login-4414', 'autocomplete')) === 'username' && (await page.getAttribute('#user_password-4414', 'autocomplete')) === 'current-password');
    await page.unroute(BASE + '/login/');
    await page.fill('#user_login-4414', 'family1');
    await page.fill('#user_password-4414', 'Test-pass-12345');
    await Promise.all([page.waitForNavigation(), page.click('#um-submit-btn')]);
    const c = await authCookie(ctx);
    check('cached login page: login still gets 90 days', !!c && Math.abs((c.expires - now()) / DAY - 90.5) < 0.1, c && (c.expires - now()) / DAY);
    await ctx.close();
  }

  // --- Sliding renewal -----------------------------------------------------
  {
    sessions('family1', true);
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    await login(page, 'family1', null, { remember: true }); // 14-day web login
    const before = await authCookie(ctx);
    const tokenBefore = decodeURIComponent(before.value).split('|')[2];
    await ctx.addCookies([{ name: 'asa_app', value: '1', url: BASE }]);

    const rest = await page.request.get(BASE + '/wp-json/', { headers: { 'Sec-Fetch-Dest': 'empty' } });
    check('renewal: never on REST requests', !/wordpress_logged_in/.test(await setCookieOf(rest)));

    const resp = await page.goto(BASE + '/guess-a-lotl/');
    const after = await authCookie(ctx);
    const tokenAfter = decodeURIComponent(after.value).split('|')[2];
    const s = sessions('family1');
    check('renewal: 14-day login opened in app becomes 90 days', Math.abs((after.expires - now()) / DAY - 90.5) < 0.1, (after.expires - now()) / DAY);
    check('renewal: same session token kept (nonces stay valid)', tokenBefore === tokenAfter);
    check('renewal: one session, extended to 90 days', s.sessions.length === 1 && Math.abs((s.sessions[0] - now()) / DAY - 90) < 0.1, s);
    check('renewal: last renewal recorded in user meta', Math.abs(Number(s.renewed) - now()) < 60, s);
    check('renewal: logged-in app page not cacheable', (await resp.allHeaders())['x-test-donotcachepage'] === 'yes' && /no-store/.test((await resp.allHeaders())['cache-control'] || ''));

    const resp2 = await page.goto(BASE + '/hex-a-lotl/');
    check('renewal: not repeated while 90 days remain', !/wordpress_logged_in/.test(await setCookieOf(resp2)));
    await ctx.close();

    // Another short login the same day: the once-a-day limit holds.
    const ctx2 = await newContext(browser);
    const p2 = await ctx2.newPage();
    await login(p2, 'family1', null, { remember: true });
    await ctx2.addCookies([{ name: 'asa_app', value: '1', url: BASE }]);
    const resp3 = await p2.goto(BASE + '/guess-a-lotl/');
    check('renewal: at most once a day per family', !/wordpress_logged_in/.test(await setCookieOf(resp3)));
    await ctx2.close();
  }

  // --- Cache headers ---------------------------------------------------------
  {
    const ctx = await newContext(browser);
    const page = await ctx.newPage();
    let resp = await page.goto(BASE + '/guess-a-lotl/');
    check('logged-out web page: still cacheable', (await resp.allHeaders())['x-test-donotcachepage'] === 'no');
    await ctx.addCookies([{ name: 'asa_app', value: '1', url: BASE }]);
    resp = await page.goto(BASE + '/guess-a-lotl/');
    const h = await resp.allHeaders();
    check('logged-out app page: DONOTCACHEPAGE + no-store', h['x-test-donotcachepage'] === 'yes' && /no-store/.test(h['cache-control'] || ''), h);
    await ctx.close();

    const web = await newContext(browser);
    const wp = await web.newPage();
    await login(wp, 'family1', null, { remember: true });
    const wh = await (await wp.goto(BASE + '/guess-a-lotl/')).allHeaders();
    // (WordPress core itself sends no-cache headers to logged-in users.)
    check('logged-in web page: DONOTCACHEPAGE', wh['x-test-donotcachepage'] === 'yes', wh);
    await web.close();
  }

  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(2); });
