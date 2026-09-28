// Step 3: manifest shortcuts and icons, as Chromium's own manifest parser reads them.
const { EXE, chromium, BASE, check, done, newContext } = require('./lib');

(async () => {
  const browser = await chromium.launch({ executablePath: EXE });
  const ctx = await newContext(browser);
  const page = await ctx.newPage();
  await page.goto(BASE + '/');
  const cdp = await ctx.newCDPSession(page);
  const res = await cdp.send('Page.getAppManifest');
  check('Chromium parses the manifest without errors', res.errors.length === 0, res.errors);
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

  await browser.close();
  done();
})().catch((e) => { console.error(e); process.exit(2); });
