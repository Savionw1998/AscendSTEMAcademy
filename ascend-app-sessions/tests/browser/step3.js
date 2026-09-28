// Step 3: manifest shortcuts and icons. A stand-in for the Ascend PWA plugin serves /manifest.json
// the way the live site does (knowing nothing about this plugin); Chromium's own manifest parser reads it.
const { EXE, chromium, BASE, check, done, newContext } = require('./lib');

(async () => {
  const browser = await chromium.launch({ executablePath: EXE });
  const ctx = await newContext(browser);

  const raw = await ctx.request.get(BASE + '/manifest.json');
  const headers = raw.headers();
  const body = await raw.text();
  check('/manifest.json served as JSON', raw.status() === 200 && /json/.test(headers['content-type'] || ''), [raw.status(), headers['content-type']]);
  check('no stale Content-Length, ETag or Last-Modified from before the rewrite', (!headers['content-length'] || Number(headers['content-length']) === Buffer.byteLength(body)) && !headers['etag'] && !headers['last-modified'], headers);
  const again = await ctx.request.get(BASE + '/manifest.json', { headers: { 'If-None-Match': headers['x-test-original-etag'] } });
  check("a browser holding the PWA plugin's old ETag still gets the new manifest (200, not 304)", again.status() === 200 && JSON.parse(await again.text()).shortcuts.length === 4, again.status());
  check('manifest is never page-cached (DONOTCACHEPAGE)', headers['x-test-donotcachepage'] === 'yes');
  const served = JSON.parse(body);
  check("the PWA plugin's own fields are kept", served.short_name === 'Ascend STEM' && served.theme_color === '#009CDE' && served.orientation === 'portrait' && served.start_url === '/');

  const page = await ctx.newPage();
  await page.goto(BASE + '/');
  const cdp = await ctx.newCDPSession(page);
  const res = await cdp.send('Page.getAppManifest');
  check('Chromium parses the manifest without errors', res.errors.length === 0 && /\/manifest\.json$/.test(res.url), [res.url, res.errors]);
  const m = JSON.parse(res.data);
  check('shortcuts: Time Card, Students, Parents, Games', JSON.stringify(m.shortcuts.map((s) => s.name)) === JSON.stringify(['Time Card', 'Students', 'Parents', 'Games']));
  check('shortcut URLs', JSON.stringify(m.shortcuts.map((s) => new URL(s.url).pathname)) === JSON.stringify(['/time-card-tracker/', '/user/', '/account/', '/guess-a-lotl/']));

  const load = (src) => page.evaluate((u) => new Promise((ok) => { const i = new Image(); i.onload = () => ok([i.naturalWidth, i.naturalHeight]); i.onerror = () => ok(null); i.src = u; }), src);
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
  const r = await ctx.request.get(m.icons[0].src);
  check('icons are served as image/png', r.status() === 200 && r.headers()['content-type'] === 'image/png');

  // If the PWA plugin does not answer (e.g. deactivated), WordPress's own page is passed through untouched.
  const off = await ctx.request.get(BASE + '/manifest.json?stand_in_off=1');
  check('a non-manifest reply at that URL is left alone', off.status() === 404 && /<html/i.test(await off.text()));

  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(2); });
