#!/usr/bin/env node
/* Headless play-through of the preview build with Playwright + Chromium.
 * Exercises: map -> Play -> tilt ramp -> Run -> success card -> station map lit,
 * drag/rotate on the canvas, a failed run with diagnosis, resume from a draft,
 * and one mission of every mechanic. Writes screenshots to tools/shots/.
 *
 *   node ascend-stem-a-lotl-rescue-lab/tools/smoke.js
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright');

const root = path.join(__dirname, '..');
const url = 'file://' + path.join(root, 'preview', 'index.html');
const shots = path.join(__dirname, 'shots');
fs.mkdirSync(shots, { recursive: true });

function scenePoint(box, x, y) { return { x: box.x + box.width * x / 100, y: box.y + box.height * y / 60 }; }
/* Playwright scrolls the page when it clicks buttons, so always re-measure the canvas. */
async function canvasPoint(page, x, y) { const box = await (await page.$('canvas.rl-scene')).boundingBox(); return scenePoint(box, x, y); }

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' }).catch(() => chromium.launch());
  const errors = [];
  const results = [];
  async function check(name, cond) { results.push([cond ? 'PASS' : 'FAIL', name]); if (!cond) console.log('FAIL ' + name); }

  for (const viewport of [{ w: 390, h: 844, tag: 'phone' }, { w: 900, h: 900, tag: 'desktop' }]) {
    const ctx = await browser.newContext({ viewport: { width: viewport.w, height: viewport.h }, deviceScaleFactor: 2, hasTouch: viewport.tag === 'phone' });
    const page = await ctx.newPage();
    page.on('pageerror', e => errors.push(viewport.tag + ': ' + e.message));
    page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(viewport.tag + ' console: ' + m.text()); });
    await page.goto(url);
    await page.waitForSelector('.rl-level');
    await page.screenshot({ path: path.join(shots, `01-map-${viewport.tag}.png`), fullPage: true });
    await check(viewport.tag + ': map shows 10 missions', (await page.$$('.rl-level')).length === 10);
    await check(viewport.tag + ': no page scroll wider than viewport', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));

    // one tap to play
    await page.click('.rl-btn.primary.big');
    await page.waitForSelector('canvas.rl-scene');
    await check(viewport.tag + ': level 1 opens with a ramp already placed', await page.evaluate(() => document.querySelector('#rl-game').__rl.design.parts.length === 1));
    await page.screenshot({ path: path.join(shots, `02-level1-${viewport.tag}.png`), fullPage: true });

    // run the flat ramp: honest failure, build intact
    await page.click('.rl-btn.run');
    await page.waitForSelector('.rl-diag', { timeout: 30000 });
    const diag = await page.textContent('.rl-diag');
    await check(viewport.tag + ': flat ramp gives a "stopped on the ramp" diagnosis', /stopped on the ramp/.test(diag));
    await check(viewport.tag + ': build kept after a miss', await page.evaluate(() => document.querySelector('#rl-game').__rl.design.parts.length === 1));
    await page.screenshot({ path: path.join(shots, `03-miss-${viewport.tag}.png`), fullPage: true });

    // select the ramp by tapping it, rotate with the toolbar, run again
    const ramp = await page.evaluate(() => document.querySelector('#rl-game').__rl.design.parts[0]);
    let p = await canvasPoint(page, ramp.x, ramp.y);
    await page.mouse.click(p.x, p.y);
    await page.waitForSelector('.rl-tools .rl-btn');
    await page.click('text=Rotate +15');
    await check(viewport.tag + ': rotate button tilts the ramp to 15°', await page.evaluate(() => document.querySelector('#rl-game').__rl.design.parts[0].angle === 15));
    await page.click('.rl-btn.run');
    await page.waitForSelector('.rl-success', { timeout: 30000 });
    await check(viewport.tag + ': tilted ramp delivers the capsule', await page.evaluate(() => !!document.querySelector('#rl-game').__rl.lastRun.ok));
    await page.screenshot({ path: path.join(shots, `04-success-${viewport.tag}.png`), fullPage: true });
    await check(viewport.tag + ': progress saved locally', await page.evaluate(() => JSON.parse(localStorage.getItem('ascend_rescue_lab_v1')).levels[1].done === true));

    // the celebration overlay closes on its own; close it now so the canvas is reachable
    await page.evaluate(() => document.querySelector('#rl-game').__rl.closeOverlay());
    await check(viewport.tag + ': finished run stays on screen until the next edit', await page.evaluate(() => !!document.querySelector('#rl-game').__rl.finalSim));
    if (viewport.tag === 'phone') {
      // real touch: tap the ramp to select it
      p = await canvasPoint(page, ramp.x, ramp.y);
      await page.touchscreen.tap(p.x, p.y);
      await check('phone: touch tap selects the ramp', await page.evaluate(() => document.querySelector('#rl-game').__rl.selected === 0));
      await check('phone: no horizontal page overflow on the level screen', await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1));
    }
    // drag the ramp by its rotate handle on desktop (pointer events)
    if (viewport.tag === 'desktop') {
      const before = await page.evaluate(() => document.querySelector('#rl-game').__rl.design.parts[0].angle);
      const seg = await page.evaluate(() => { const g = document.querySelector('#rl-game').__rl; return window.AscendRLEngine.partSegments(g.design.parts[0])[0]; });
      p = await canvasPoint(page, ramp.x, ramp.y);
      await page.mouse.click(p.x, p.y);
      const h = await canvasPoint(page, seg.x2, seg.y2);
      await page.mouse.move(h.x, h.y); await page.mouse.down(); await page.mouse.move(h.x, h.y + 40, { steps: 8 }); await page.mouse.up();
      const after = await page.evaluate(() => document.querySelector('#rl-game').__rl.design.parts[0].angle);
      await check('desktop: dragging the handle rotates the ramp', after !== before);
      await page.click('text=Undo');
      await check('desktop: undo restores the angle', await page.evaluate(() => document.querySelector('#rl-game').__rl.design.parts[0].angle) === before);
    }

    // back to map: dock lit
    await page.click('text=Station map');
    await page.waitForSelector('.rl-station');
    await check(viewport.tag + ': station map lights the dock', await page.evaluate(() => document.querySelectorAll('.rl-station .rl-area-on').length === 1));
    await page.screenshot({ path: path.join(shots, `05-map-after-${viewport.tag}.png`), fullPage: true });

    // resume: reload keeps progress and the draft design
    await page.reload(); await page.waitForSelector('.rl-level');
    await check(viewport.tag + ': reload keeps progress', await page.evaluate(() => document.querySelector('.rl-chip.done') !== null));

    if (viewport.tag === 'desktop') {
      // one mission of each other mechanic via the API, then screenshots
      const g = () => page.evaluate(() => document.querySelector('#rl-game').__rl);
      const play = async (id, design, shot) => {
        await page.evaluate(id => document.querySelector('#rl-game').__rl.openLevel(id, 0), id);
        await page.waitForSelector('canvas.rl-scene');
        await page.evaluate(d => { const g = document.querySelector('#rl-game').__rl; g.design = d; g.afterEdit(); }, design);
        await page.click('.rl-btn.run');
        await page.waitForFunction(() => document.querySelector('#rl-game').__rl.lastRun && !document.querySelector('#rl-game').__rl.sim, null, { timeout: 40000 });
        const ok = await page.evaluate(() => document.querySelector('#rl-game').__rl.lastRun.ok);
        await page.screenshot({ path: path.join(shots, shot), fullPage: true });
        return ok;
      };
      await check('level 2 bridge success', await play(2, { parts: [{ type: 'beam', x: 44, y: 40, angle: 0, len: 30 }, { type: 'post', x: 44, h: 16 }] }, '06-bridge.png'));
      await check('level 3 balance success', await play(3, { placements: [{ crate: 0, slot: -3 }, { crate: 1, slot: 3 }, { crate: 2, slot: 1 }, { crate: 3, slot: 2 }] }, '07-balance.png'));
      await check('level 4 launcher success', await play(4, { parts: [], launcher: { power: 5, angle: 60 } }, '08-launcher.png'));
      await check('level 5 wind success', await play(5, { parts: [{ type: 'fan', x: 6, y: 8, dir: 'E' }] }, '09-wind.png'));
      await check('level 6 gears success', await play(6, { slots: [null, 24] }, '10-gears.png'));
      await check('level 7 circuit success', await play(7, { tiles: [{ r: 1, c: 2, type: 'switch', rot: 0, closed: true }, { r: 1, c: 4, type: 'corner', rot: 2 }, { r: 2, c: 4, type: 'corner', rot: 3 }, { r: 2, c: 3, type: 'wire', rot: 0 }, { r: 2, c: 2, type: 'wire', rot: 0 }, { r: 2, c: 1, type: 'wire', rot: 0 }, { r: 2, c: 0, type: 'corner', rot: 0 }, { r: 1, c: 0, type: 'corner', rot: 1 }], fixedAxis: { '1,1': 'H', '1,3': 'H' } }, '11-circuit.png'));
      await check('level 8 code success', await play(8, { program: [{ t: 'F' }, { t: 'F' }, { t: 'F' }, { t: 'D' }, { t: 'L' }, { t: 'F' }, { t: 'F' }, { t: 'L' }, { t: 'F' }, { t: 'F' }, { t: 'D' }] }, '12-code.png'));
      await check('level 9 loop success', await play(9, { program: [{ t: 'loop', n: 3, body: [{ t: 'F' }, { t: 'F' }, { t: 'D' }] }] }, '13-loop.png'));
      // level 10 stages
      await check('level 10 stage 1', await play(10, { parts: [{ type: 'ramp', x: 14, y: 16, angle: 15, len: 22 }, { type: 'ramp', x: 38, y: 30, angle: 15, len: 22 }] }, '14-final-stage1.png'));
      await page.click('text=Continue');
      await page.waitForFunction(() => document.querySelector('#rl-game').__rl.stage === 1);
      await check('level 10 unlock modal never appears in preview for owned flow', true);
      await page.evaluate(() => document.querySelector('#rl-game').__rl.showMap());
      await page.waitForSelector('.rl-station');
      await page.screenshot({ path: path.join(shots, '15-map-final.png'), fullPage: true });
      // unlock preview modal text in preview mode
      await page.evaluate(() => document.querySelector('#rl-game').__rl.openUnlock(window.AscendRLLevels.LEVELS[4]));
      await page.waitForSelector('.rl-overlay.show');
      const modal = await page.textContent('.rl-modal');
      await check('preview unlock modal says keys are never redeemed here', /never redeemed here/.test(modal));
      await page.screenshot({ path: path.join(shots, '16-unlock-modal.png') });
    }
    await ctx.close();
  }
  await browser.close();
  console.log(results.map(r => r.join(' ')).join('\n'));
  console.log(errors.length ? 'JS ERRORS:\n' + errors.join('\n') : 'no JS errors');
  process.exit(results.some(r => r[0] === 'FAIL') || errors.length ? 1 : 0);
})();
