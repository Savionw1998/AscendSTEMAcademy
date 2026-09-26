#!/usr/bin/env node
/* Headless play-through of the preview build with Playwright + Chromium, on a
 * phone (touch) and a desktop viewport. Exercises the first minute, the
 * build/run/diagnose/retry loop, drag and keyboard editing, saving and resume,
 * the pellet locker, and the student demo's daily unlock and key rules.
 * Writes screenshots to tools/shots/.
 *
 *   NODE_PATH=/opt/node22/lib/node_modules node ascend-stem-a-lotl-rescue-lab/tools/smoke.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const url = 'file://' + path.join(root, 'preview', 'index.html');
const shots = path.join(__dirname, 'shots');
fs.mkdirSync(shots, { recursive: true });
const G = 'document.querySelector(".rl-root").__rl';

async function canvasPoint(page, x, y) { const b = await (await page.$('canvas.rl-scene')).boundingBox(); return { x: b.x + b.width * x / 100, y: b.y + b.height * y / 60 }; }
const waitRun = page => page.waitForFunction(() => { const g = document.querySelector('.rl-root').__rl; return !g.sim && g.lastRun; }, null, { timeout: 40000 });

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' });
  const errors = [], results = [];
  const check = (name, cond) => { results.push([cond ? 'PASS' : 'FAIL', name]); if (!cond) console.log('FAIL ' + name); };

  for (const vp of [{ w: 390, h: 844, tag: 'phone' }, { w: 1000, h: 900, tag: 'desktop' }]) {
    const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h }, deviceScaleFactor: 2, hasTouch: vp.tag === 'phone' });
    const page = await ctx.newPage();
    page.on('pageerror', e => errors.push(vp.tag + ': ' + e.message));
    page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(vp.tag + ' console: ' + m.text()); });
    await page.goto(url);
    await page.waitForSelector('.rl-level');
    await page.screenshot({ path: path.join(shots, `01-map-${vp.tag}.png`), fullPage: true });
    check(vp.tag + ': map shows 40 puzzles in 4 worlds', (await page.$$('.rl-level')).length === 40 && (await page.$$('.rl-world')).length === 4);
    check(vp.tag + ': pellet locker shows 12 pellets', (await page.$$('.rl-skin')).length === 12);
    check(vp.tag + ': no sideways page scroll on the map', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));

    // first minute: one tap to play, a flat ramp that honestly does not work, tilt, feed Lucas
    await page.click('.rl-hero .rl-btn.primary');
    await page.waitForSelector('canvas.rl-scene');
    check(vp.tag + ': puzzle 1 opens with a ramp already placed', await page.evaluate(`${G}.design.parts.length === 1 && ${G}.level.id === 1`));
    await page.screenshot({ path: path.join(shots, `02-level1-${vp.tag}.png`), fullPage: true });
    await page.click('.rl-btn.run');
    await waitRun(page);
    check(vp.tag + ': flat ramp is diagnosed as "stopped on your ramp"', /stopped on your ramp/.test(await page.textContent('.rl-diag')));
    check(vp.tag + ': the build is kept after a miss', await page.evaluate(`${G}.design.parts.length === 1`));
    await page.screenshot({ path: path.join(shots, `03-miss-${vp.tag}.png`), fullPage: true });

    const ramp = await page.evaluate(`${G}.design.parts[0]`);
    let p = await canvasPoint(page, ramp.x, ramp.y);
    if (vp.tag === 'phone') await page.touchscreen.tap(p.x, p.y); else await page.mouse.click(p.x, p.y);
    check(vp.tag + ': tapping the ramp selects it', await page.evaluate(`${G}.selected === 0`));
    await page.click('text=Tilt +15');
    check(vp.tag + ': Tilt +15° tilts the ramp', await page.evaluate(`${G}.design.parts[0].angle === 15`));
    await page.click('.rl-btn.run');
    await waitRun(page);
    check(vp.tag + ': the tilted ramp feeds Lucas', await page.evaluate(`${G}.lastRun.ok`));
    const card = await page.textContent('.rl-success');
    check(vp.tag + ': the end card shows the journey and the lesson', /The journey/.test(card) && /The lesson/.test(card) && /rolled down your ramp/.test(card));
    await page.waitForTimeout(600);
    await page.screenshot({ path: path.join(shots, `04-fed-${vp.tag}.png`), fullPage: true });
    check(vp.tag + ': progress is saved locally', await page.evaluate(() => JSON.parse(localStorage.getItem('ascend_rescue_lab_v2')).levels[1].done === true));
    check(vp.tag + ': no sideways page scroll on the level screen', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));

    if (vp.tag === 'desktop') {
      // drag the tilt handle, then undo; keyboard nudges
      p = await canvasPoint(page, ramp.x, ramp.y);
      await page.mouse.click(p.x, p.y);
      const before = await page.evaluate(`${G}.design.parts[0].angle`);
      const seg = await page.evaluate(`window.AscendRLEngine.partSegments(${G}.design.parts[0])[0]`);
      const h = await canvasPoint(page, seg.x2, seg.y2);
      await page.mouse.move(h.x, h.y); await page.mouse.down(); await page.mouse.move(h.x, h.y + 60, { steps: 8 }); await page.mouse.up();
      check('desktop: dragging the handle tilts the ramp', (await page.evaluate(`${G}.design.parts[0].angle`)) !== before);
      await page.click('text=Undo');
      check('desktop: Undo restores the tilt', (await page.evaluate(`${G}.design.parts[0].angle`)) === before);
      await page.focus('canvas.rl-scene');
      await page.evaluate(`${G}.selected = 0`);
      const x0 = await page.evaluate(`${G}.design.parts[0].x`);
      await page.keyboard.press('ArrowRight'); await page.keyboard.press(']');
      check('desktop: arrow keys move and ] tilts the selected part', (await page.evaluate(`${G}.design.parts[0].x`)) === x0 + 1 && (await page.evaluate(`${G}.design.parts[0].angle`)) === before + 5);

      // resume: a reload keeps progress and the in-progress build
      const draft = await page.evaluate(`JSON.stringify(${G}.design)`);
      await page.reload(); await page.waitForSelector('.rl-level');
      check('desktop: reload keeps progress', await page.evaluate(() => document.querySelector('.rl-chip.done') !== null));
      await page.evaluate(`${G}.openLevel(1)`);
      check('desktop: reload keeps the unfinished build', (await page.evaluate(`JSON.stringify(${G}.design)`)) === draft);

      // a hazard world, mid-journey, for the eye
      const sol = JSON.parse(fs.readFileSync(path.join(root, 'tests', 'solutions.json'), 'utf8'));
      for (const id of [14, 29, 35]) {
        await page.evaluate(id => document.querySelector('.rl-root').__rl.openLevel(id), id);
        await page.evaluate(d => { const g = document.querySelector('.rl-root').__rl; g.design = d; g.afterEdit(); g.run(); }, sol[id].main);
        await page.waitForTimeout(1600);
        await page.screenshot({ path: path.join(shots, `05-journey-${id}.png`) });
        await waitRun(page);
        check(`desktop: puzzle ${id} reference run feeds Lucas in the browser`, await page.evaluate(`${G}.lastRun.ok`));
      }

      // student demo: 3 free, daily unlock, no rollover, demo keys, skins
      await page.evaluate(`${G}.showMap()`);
      await page.click('text=Play as a student');
      await page.waitForSelector('.rl-daily');
      const locked = await page.$$eval('.rl-level.is-locked', els => els.length);
      check('demo: after the first visit, 4 puzzles are open and 36 locked', locked === 36);
      check('demo: the daily card names today’s free puzzle', /#4 just opened/.test(await page.textContent('.rl-daily')));
      await page.evaluate(`${G}.reloadAuthority()`);
      await page.waitForTimeout(200);
      check('demo: coming back the same day opens nothing new', (await page.$$eval('.rl-level.is-locked', els => els.length)) === 36);
      await page.click('text=Pretend it’s tomorrow');
      await page.waitForTimeout(200);
      check('demo: the next day opens puzzle 5', (await page.$$eval('.rl-level.is-locked', els => els.length)) === 35 && /#5 just opened/.test(await page.textContent('.rl-daily')));
      await page.screenshot({ path: path.join(shots, '06-demo-map.png'), fullPage: true });
      await page.evaluate(`${G}.openUnlock(window.AscendRLLevels.LEVELS[29])`);
      await page.waitForSelector('.rl-overlay.show');
      check('demo: the unlock dialog says keys are pretend', /Pretend keys/.test(await page.textContent('.rl-modal')));
      await page.screenshot({ path: path.join(shots, '07-unlock.png') });
      await page.click('text=Open with 1 key');
      await page.waitForSelector('canvas.rl-scene');
      check('demo: a key opens puzzle 30 straight away', await page.evaluate(`${G}.level.id === 30`));
      await page.evaluate(`${G}.showMap()`);
      await page.click('.rl-skin:has-text("Galaxy")');
      await page.waitForSelector('.rl-overlay.show');
      await page.screenshot({ path: path.join(shots, '08-skin.png') });
      await page.click('text=Get it for 1 key');
      await page.waitForTimeout(200);
      check('demo: buying the Galaxy pellet selects it', await page.evaluate(`window.AscendRescueLab.store.progress.settings.skin === 'galaxy'`));
      check('demo: demo keys went from 3 to 1', /Demo keys: 1/.test(await page.textContent('.rl-stats')));
      await page.screenshot({ path: path.join(shots, '09-locker.png'), fullPage: true });
    }
    await ctx.close();
  }
  await browser.close();
  console.log(results.map(r => r.join(' ')).join('\n'));
  console.log(errors.length ? 'JS ERRORS:\n' + errors.join('\n') : 'no JS errors');
  process.exit(results.some(r => r[0] === 'FAIL') || errors.length ? 1 : 0);
})();
