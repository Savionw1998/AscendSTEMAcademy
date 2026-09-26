#!/usr/bin/env node
/* Plays every puzzle's reference solution in the preview build (Chromium via
 * Playwright) and saves three canvas frames per puzzle: before the run, in the
 * middle of the journey, and the end. Used to review physics and art by eye.
 *
 *   NODE_PATH=/opt/node22/lib/node_modules node ascend-stem-a-lotl-rescue-lab/tools/gallery.js [ids...]
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const url = 'file://' + path.join(root, 'preview', 'index.html');
const out = path.join(__dirname, 'shots', 'gallery');
fs.mkdirSync(out, { recursive: true });
const sol = JSON.parse(fs.readFileSync(path.join(root, 'tests', 'solutions.json'), 'utf8'));
const only = process.argv.slice(2).map(Number).filter(Boolean);

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' });
  const page = await (await browser.newContext({ viewport: { width: 760, height: 1100 }, deviceScaleFactor: 1 })).newPage();
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));
  await page.goto(url);
  await page.waitForSelector('.rl-level');
  const ids = only.length ? only : Object.keys(sol).map(Number);
  const results = [];
  for (const id of ids) {
    await page.evaluate(id => document.querySelector('.rl-root').__rl.openLevel(id), id);
    await page.waitForSelector('canvas.rl-scene');
    await page.evaluate(d => { const g = document.querySelector('.rl-root').__rl; g.design = JSON.parse(JSON.stringify(d)); g.afterEdit(); }, sol[id].main);
    const cv = await page.$('canvas.rl-scene');
    await cv.screenshot({ path: path.join(out, `${String(id).padStart(2, '0')}-a-build.png`) });
    await page.evaluate(() => document.querySelector('.rl-root').__rl.run());
    const total = await page.evaluate(() => { const g = document.querySelector('.rl-root').__rl, E = window.AscendRLEngine; return E.runToEnd(g.spec, g.design).t; });
    await page.waitForTimeout(Math.max(300, total * 1000 * 0.55));
    await cv.screenshot({ path: path.join(out, `${String(id).padStart(2, '0')}-b-mid.png`) });
    await page.waitForFunction(() => { const g = document.querySelector('.rl-root').__rl; return !g.sim && g.lastRun; }, null, { timeout: 30000 });
    await page.waitForTimeout(700);
    await cv.screenshot({ path: path.join(out, `${String(id).padStart(2, '0')}-c-end.png`) });
    const r = await page.evaluate(() => { const g = document.querySelector('.rl-root').__rl; return { ok: g.lastRun.ok, text: g.lastRun.diag.text, journey: window.AscendRLEngine.journey(g.lastRun.sim).text }; });
    results.push(`#${id} ${r.ok ? 'fed' : 'MISSED'} · ${r.journey}`);
  }
  await browser.close();
  console.log(results.join('\n'));
  console.log(errors.length ? 'JS ERRORS: ' + errors.join(' | ') : 'no JS errors');
})();
