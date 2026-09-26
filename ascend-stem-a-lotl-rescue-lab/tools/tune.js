#!/usr/bin/env node
/* Finds a reference solution for every puzzle and every optional challenge by
 * seeded random search, and writes them to tests/solutions.json. The tests then
 * replay those designs, so shipping content is always proven solvable.
 *
 *   node ascend-stem-a-lotl-rescue-lab/tools/tune.js            # all puzzles
 *   node ascend-stem-a-lotl-rescue-lab/tools/tune.js 5 12 40    # some puzzles
 *
 * Existing solutions that still pass are kept, so re-tuning is cheap.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const Levels = require('../assets/levels.js');
const Engine = require('../assets/engine.js');

const OUT = path.join(__dirname, '..', 'tests', 'solutions.json');
const BUDGET = Number(process.env.BUDGET || 60000);

function rng(seed) { let s = seed >>> 0; return () => { s = (s * 1664525 + 1013904223) >>> 0; return s / 4294967296; }; }
const pick = (r, a) => a[Math.floor(r() * a.length)];
const between = (r, a, b) => Math.round(a + r() * (b - a));

/* A random design from the tray. The first part tends to sit under the dispenser. */
function randomDesign(spec, r) {
  const sp = spec.scene.spawn, parts = [];
  spec.tray.forEach(t => {
    const n = Math.floor(r() * (t.count + 1));
    for (let i = 0; i < n; i++) {
      const near = parts.length === 0 && r() < 0.7;
      const x = near ? between(r, sp.x - 6, sp.x + 14) : between(r, 4, 96);
      const y = near ? between(r, sp.y + 5, sp.y + 22) : between(r, 6, 54);
      if (t.type === 'fan') parts.push({ type: 'fan', x, y, dir: pick(r, ['E', 'E', 'W', 'N', 'S']) });
      else {
        const p = { type: t.type, x, y, angle: between(r, -12, 12) * 5 };
        if (t.len) p.len = t.len;
        parts.push(p);
      }
    }
  });
  return { parts };
}

/* Small nudges around a near-miss, to polish a design that almost works. */
function mutate(d, r) {
  const out = JSON.parse(JSON.stringify(d));
  if (!out.parts.length) return out;
  const p = pick(r, out.parts);
  const k = r();
  if (k < 0.35) p.x += between(r, -4, 4);
  else if (k < 0.7) p.y += between(r, -4, 4);
  else if (p.angle != null) p.angle += pick(r, [-10, -5, 5, 10]);
  else p.dir = pick(r, ['E', 'W', 'N', 'S']);
  return out;
}

function distanceToLucas(spec, run) {
  const b = run.sim.body, m = Engine.mouthZone(spec.scene.lucas);
  return Math.hypot(b.x - m.x, b.y - m.y);
}

function search(spec, rule, seed, budget) {
  const r = rng(seed);
  let best = null, bestD = Infinity;
  for (let i = 0; i < budget; i++) {
    const d = (best && r() < 0.5) ? mutate(best, r) : randomDesign(spec, r);
    if (!Engine.validateDesign(spec, d).ok) continue;
    const run = Engine.runToEnd(spec, d);
    if (run.ok && (!rule || Engine.checkRule(spec, d, run, rule))) return { design: Engine.normalizeDesign(spec, d), t: run.t, tries: i + 1 };
    const dist = distanceToLucas(spec, run) + (run.ok ? 0 : 5);
    if (dist < bestD) { bestD = dist; best = d; }
  }
  return null;
}

function passes(spec, design, rule) {
  const run = Engine.runToEnd(spec, design);
  return run.ok && (!rule || Engine.checkRule(spec, design, run, rule));
}

const only = process.argv.slice(2).map(Number).filter(Boolean);
const store = fs.existsSync(OUT) ? JSON.parse(fs.readFileSync(OUT, 'utf8')) : {};
let failures = 0;
for (const level of Levels.LEVELS) {
  if (only.length && !only.includes(level.id)) continue;
  const spec = Engine.resolve(level);
  const entry = store[level.id] || {};
  const report = [];
  const empty = Engine.runToEnd(spec, Engine.newDesign(spec));
  if (empty.ok) { report.push('STARTING LAYOUT ALREADY WINS'); failures++; }
  const jobs = [['main', null]].concat(level.challenges.map(c => [c.id, c.rule]));
  jobs.forEach(([key, rule], j) => {
    if (entry[key] && passes(spec, entry[key], rule)) { report.push(key + ' kept'); return; }
    const hit = search(spec, rule, level.id * 1000 + j, BUDGET);
    if (hit) { entry[key] = hit.design; report.push(key + ' found (' + hit.tries + ' tries, ' + hit.t.toFixed(1) + 's)'); }
    else { delete entry[key]; report.push(key + ' NOT FOUND'); failures++; }
  });
  store[level.id] = entry;
  console.log(`#${level.id} ${level.name}: ${report.join('; ')}`);
  fs.writeFileSync(OUT, JSON.stringify(store, null, 1) + '\n');
}
console.log(failures ? failures + ' problem(s)' : 'all puzzles and challenges solved');
process.exit(failures ? 1 : 0);
