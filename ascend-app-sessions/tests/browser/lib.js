// Shared helpers for the local browser tests.
// Paths and URL can be overridden: ASA_BASE, ASA_SITE, CHROME (a Chromium binary), PLAYWRIGHT (module path).
const { chromium } = require(process.env.PLAYWRIGHT || 'playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.ASA_BASE || 'http://localhost:8080';
const EXE = process.env.CHROME || undefined;
const SITE = process.env.ASA_SITE || require('path').join(__dirname, '.site');
const DAY = 86400;

let fails = 0, n = 0;
function check(name, cond, extra) {
  n++;
  console.log((cond ? 'PASS ' : 'FAIL ') + name + (extra !== undefined && !cond ? '  -> ' + JSON.stringify(extra) : ''));
  if (!cond) fails++;
}
function done() {
  console.log(`\n${n - fails}/${n} passed`);
  process.exit(fails ? 1 : 0);
}
function sessions(user, reset) {
  return JSON.parse(execFileSync('php', ['sessions.php', user].concat(reset ? ['reset'] : []), { cwd: SITE }).toString());
}
async function newContext(browser, { app = false, viewport } = {}) {
  const ctx = await browser.newContext({ viewport: viewport || { width: 412, height: 860 } });
  if (app) await ctx.addCookies([{ name: 'asa_app', value: '1', url: BASE }]);
  return ctx;
}
async function login(page, user, pass, { remember } = {}) {
  await page.goto(BASE + '/login/');
  await page.fill('#user_login-4414', user);
  await page.fill('#user_password-4414', pass || 'Test-pass-12345');
  if (remember) {
    // UM hides the real input behind an icon; tick it the way a click on the label would.
    await page.evaluate(() => { const b = document.querySelector('.um-login input[type=checkbox][name=rememberme]'); if (b) b.checked = true; });
  }
  await Promise.all([page.waitForNavigation(), page.click('#um-submit-btn')]);
}
// A real display-mode: standalone window (Chromium --app), the closest local stand-in for the TWA.
const fs = require('fs'), os = require('os'), path = require('path');
async function openApp(urlPath, { cookies = [], viewport = { width: 412, height: 860 } } = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'pw-app-'));
  const ctx = await chromium.launchPersistentContext(dir, { executablePath: EXE, headless: true, viewport, args: ['--app=' + BASE + '/blank-for-app-window'] });
  if (cookies.length) await ctx.addCookies(cookies.map((c) => Object.assign({ url: BASE }, c)));
  const page = ctx.pages()[0] || (await ctx.waitForEvent('page'));
  await page.goto(BASE + urlPath);
  return { ctx, page };
}
async function authCookie(ctx) {
  return (await ctx.cookies()).find((c) => c.name.startsWith('wordpress_logged_in_'));
}
// Set-Cookie header(s) of a page response or an API response, as one string.
async function setCookieOf(resp) {
  if (typeof resp.headersArray === 'function' && typeof resp.allHeaders !== 'function') {
    return resp.headersArray().filter((h) => h.name.toLowerCase() === 'set-cookie').map((h) => h.value).join('\n');
  }
  return (await resp.allHeaders())['set-cookie'] || '';
}
// Switch Ultimate Member's restriction on the Time Card (6097) on or off; live has it off.
function restrictTimeCard(on) {
  execFileSync('php', ['restrict.php', '6097', on ? 'on' : 'off'], { cwd: SITE, env: Object.assign({}, process.env, { ASA_PORT: new URL(BASE).port }) });
}
const now = () => Math.floor(Date.now() / 1000);

module.exports = { restrictTimeCard, setCookieOf, openApp, EXE, chromium, BASE, SITE, DAY, check, done, sessions, newContext, login, authCookie, now };
