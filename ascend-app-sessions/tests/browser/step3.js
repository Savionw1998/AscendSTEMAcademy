// Step 3: manifest shortcuts and icons. A stand-in for the Ascend PWA plugin (knowing nothing about
// this plugin) serves the manifest the live site served, in each way it could be doing it:
//   init    - from an init hook
//   include - straight from its main file, while plugins load
//   static  - a real manifest.json file the web server sends without running WordPress
// Chromium's own manifest parser reads the result.
const { execFileSync } = require('child_process');
const fs = require('fs'), path = require('path');
const { EXE, chromium, BASE, SITE, check, done, newContext, login } = require('./lib');

const env = Object.assign({}, process.env, { ASA_PORT: new URL(BASE).port });
const standIn = (mode) => execFileSync('php', ['standin.php', mode], { cwd: SITE, env }).toString().trim();
const NAMES = JSON.stringify(['Time Card', 'Students', 'Parents', 'Games']);
const names = (m) => JSON.stringify((m.shortcuts || []).map((s) => s.name));
const keepsPwaFields = (m) => m.short_name === 'Ascend STEM' && m.theme_color === '#009CDE' && m.orientation === 'portrait' && m.start_url === '/' && m.background_color === '#FFFFFF';

(async () => {
  const browser = await chromium.launch({ executablePath: EXE });
  try {
    // Served by WordPress: rewritten on the way out, whenever the PWA plugin answers.
    for (const mode of ['init', 'include']) {
      standIn(mode);
      const ctx = await newContext(browser);
      const raw = await ctx.request.get(BASE + '/manifest.json');
      const headers = raw.headers();
      const body = await raw.text();
      check(`[${mode}] /manifest.json served as JSON`, raw.status() === 200 && /json/.test(headers['content-type'] || ''), [raw.status(), headers['content-type']]);
      const served = JSON.parse(body);
      check(`[${mode}] it has the four shortcuts and the new icons`, names(served) === NAMES && /ascend-app-sessions\/assets\/icons\/icon-192\.png/.test(served.icons[0].src), served);
      check(`[${mode}] the PWA plugin's own fields are kept`, keepsPwaFields(served));
      check(`[${mode}] no stale Content-Length, ETag or Last-Modified from before the rewrite`, (!headers['content-length'] || Number(headers['content-length']) === Buffer.byteLength(body)) && !headers['etag'] && !headers['last-modified'], headers);
      const again = await ctx.request.get(BASE + '/manifest.json', { headers: { 'If-None-Match': headers['x-test-original-etag'] } });
      check(`[${mode}] a browser holding the PWA plugin's old ETag still gets the new manifest (200, not 304)`, again.status() === 200 && names(JSON.parse(await again.text())) === NAMES, again.status());
      check(`[${mode}] manifest is never page-cached (DONOTCACHEPAGE)`, headers['x-test-donotcachepage'] === 'yes');
      const status = JSON.parse(standIn('status'));
      check(`[${mode}] recorded as served by WordPress`, status && status.served_by_wordpress > 0, status);
      // If the PWA plugin does not answer (e.g. deactivated), WordPress's own page is passed through untouched.
      const off = await ctx.request.get(BASE + '/manifest.json?stand_in_off=1');
      check(`[${mode}] a non-manifest reply at that URL is left alone`, off.status() === 404 && /<html/i.test(await off.text()));
      await ctx.close();
    }

    // A static file: WordPress never sees the request, so the file itself is updated from an admin page.
    standIn('static');
    const file = path.join(SITE, 'manifest.json');
    const original = fs.readFileSync(file, 'utf8');
    const ctx = await newContext(browser);
    const before = await ctx.request.get(BASE + '/manifest.json');
    check('[static] before any admin page: the old file, straight from the web server', before.status() === 200 && names(JSON.parse(await before.text())) === '[]');
    const page = await ctx.newPage();
    await login(page, 'admin', 'admin-pass-123');
    await page.goto(BASE + '/wp-admin/');
    const after = await ctx.request.get(BASE + '/manifest.json');
    const updated = JSON.parse(await after.text());
    check('[static] after an admin page: the file has the four shortcuts and the new icons', names(updated) === NAMES && /ascend-app-sessions\/assets\/icons\/icon-512\.png/.test(updated.icons[1].src), updated);
    check("[static] the PWA plugin's own fields are kept", keepsPwaFields(updated));
    check('[static] the original is kept as manifest.json.before-ascend-app', fs.readFileSync(file + '.before-ascend-app', 'utf8') === original);
    check('[static] recorded as updated', JSON.parse(standIn('status')).static_file === 'updated');
    const mtime = fs.statSync(file).mtimeMs;
    await page.goto(BASE + '/wp-admin/');
    check('[static] next admin page: up to date, the file is not rewritten', JSON.parse(standIn('status')).static_file === 'up to date' && fs.statSync(file).mtimeMs === mtime);
    fs.writeFileSync(file, original); // the PWA plugin writing its own version again
    await page.goto(BASE + '/wp-admin/');
    check('[static] overwritten again later: the next admin page updates it again', names(JSON.parse(fs.readFileSync(file, 'utf8'))) === NAMES);
    // (A read-only file and its admin notice are covered by tests/plugin-test.php: these tests run as root.)
    await ctx.close();

    // Chromium's manifest parser, on the WordPress-served version.
    standIn('init');
    const c2 = await newContext(browser);
    const p2 = await c2.newPage();
    await p2.goto(BASE + '/');
    const cdp = await c2.newCDPSession(p2);
    const res = await cdp.send('Page.getAppManifest');
    check('Chromium parses the manifest without errors', res.errors.length === 0 && /\/manifest\.json$/.test(res.url), [res.url, res.errors]);
    const m = JSON.parse(res.data);
    check('shortcuts: Time Card, Students, Parents, Games', names(m) === NAMES);
    check('shortcut URLs', JSON.stringify(m.shortcuts.map((s) => new URL(s.url).pathname)) === JSON.stringify(['/time-card-tracker/', '/user/', '/account/', '/guess-a-lotl/']));

    const load = (src) => p2.evaluate((u) => new Promise((ok) => { const i = new Image(); i.onload = () => ok([i.naturalWidth, i.naturalHeight]); i.onerror = () => ok(null); i.src = u; }), src);
    let all = true;
    for (const s of m.shortcuts) {
      for (const icon of s.icons) {
        const wh = await load(icon.src);
        all = all && !!wh && wh[0] === 96 && wh[1] === 96;
      }
    }
    check('all 8 shortcut icons load as 96x96 images', all);
    const sizes = [];
    for (const icon of m.icons) sizes.push(await load(icon.src));
    check('app icons load: 192, 512, maskable 512', JSON.stringify(sizes) === JSON.stringify([[192, 192], [512, 512], [512, 512]]) && m.icons[2].purpose === 'maskable', sizes);
    const r = await c2.request.get(m.icons[0].src);
    check('icons are served as image/png', r.status() === 200 && r.headers()['content-type'] === 'image/png');
    await c2.close();
  } finally {
    standIn('init');
    await browser.close();
  }
  done();
})().catch((e) => { console.error(e); try { standIn('init'); } catch (_) {} process.exit(2); });
