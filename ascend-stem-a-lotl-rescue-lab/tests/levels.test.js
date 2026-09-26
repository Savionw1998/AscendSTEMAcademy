/* Run with: node --test ascend-stem-a-lotl-rescue-lab/tests/
 *
 * The shipping gate for content and physics: every one of the 40 puzzles and
 * every star challenge replays a verified reference design (tests/solutions.json,
 * produced by tools/tune.js), no starting layout wins by itself, and the
 * physics behaves like the textbook where we say it does.
 */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const Levels = require('../assets/levels.js');
const Engine = require('../assets/engine.js');
const SOL = require('./solutions.json');

const LEVELS = Levels.LEVELS;

test('content: 40 puzzles in 4 worlds of 10, with copy and two star challenges each', () => {
  assert.equal(LEVELS.length, 40);
  Levels.WORLDS.forEach(w => assert.equal(LEVELS.filter(l => l.world === w.id).length, 10, w.name));
  LEVELS.forEach((l, i) => {
    assert.equal(l.id, i + 1);
    assert.ok(l.name && l.intro && l.lesson && l.idea, l.name + ' copy');
    assert.equal(l.challenges.length, 2, l.name);
    assert.ok(l.tray.length && l.tray.every(t => t.count > 0), l.name + ' tray');
    const s = l.scene;
    assert.ok(s.spawn.x > 0 && s.spawn.x < 100 && s.spawn.y > 0 && s.spawn.y < 56, l.name + ' spawn');
    assert.ok(s.lucas.x > 0 && s.lucas.x < 100 && s.lucas.y <= 56, l.name + ' Lucas');
    assert.ok(new Set(l.challenges.map(c => c.id)).size === 2, l.name + ' challenge ids');
  });
  assert.equal(Levels.CONFIG.freeLevels, 3);
});

for (const level of LEVELS) {
  test(`#${level.id} ${level.name}: reference design feeds Lucas; stars achievable; start does not win`, () => {
    const spec = Engine.resolve(level), ref = SOL[level.id];
    assert.ok(ref && ref.main, 'missing reference solution; run tools/tune.js');
    assert.ok(Engine.validateDesign(spec, ref.main).ok, 'reference must fit the tray');
    const run = Engine.runToEnd(spec, ref.main);
    assert.equal(run.result, 'success', JSON.stringify(run.diag));
    assert.ok(run.t < Engine.MAX_TIME);
    for (const ch of level.challenges) {
      assert.ok(ref[ch.id], 'missing reference for challenge ' + ch.id);
      assert.ok(Engine.validateDesign(spec, ref[ch.id]).ok);
      const r = Engine.runToEnd(spec, ref[ch.id]);
      assert.ok(r.ok && Engine.checkRule(spec, ref[ch.id], r, ch.rule), 'challenge ' + ch.id + ' not met');
    }
    const start = Engine.runToEnd(spec, Engine.newDesign(spec));
    assert.equal(start.ok, false, 'the starting layout must not feed Lucas by itself');
    const j = Engine.journey(run.sim);
    assert.match(j.text, /Lucas gobbled it up/);
  });
}

test('determinism: same design, same result, timing and trail, twice over', () => {
  for (const level of LEVELS) {
    const spec = Engine.resolve(level);
    const a = Engine.runToEnd(spec, SOL[level.id].main), b = Engine.runToEnd(spec, SOL[level.id].main);
    assert.equal(a.t, b.t); assert.deepEqual(a.trail, b.trail); assert.deepEqual(a.events, b.events);
  }
});

test('reset: a fresh sim starts at the dispenser with no leftover state', () => {
  const spec = Engine.resolve(LEVELS[0]);
  const s1 = Engine.createSim(spec, SOL[1].main); while (s1.step()) { /* run */ }
  const s2 = Engine.createSim(spec, SOL[1].main);
  assert.equal(s2.t, 0); assert.equal(s2.events.length, 0);
  assert.deepEqual([s2.body.x, s2.body.y, s2.body.vx, s2.body.vy], [spec.scene.spawn.x, spec.scene.spawn.y, 0, 0]);
});

test('designs snap to whole units and 5° steps so drags are reproducible', () => {
  const d = Engine.normalizeDesign(null, { parts: [{ type: 'ramp', x: 24.4, y: 13.6, angle: 12.4, len: 22 }, { type: 'bouncer', x: 1, y: 1, angle: 88 }] });
  assert.deepEqual(d.parts[0], { type: 'ramp', x: 24, y: 14, angle: 10, len: 22 });
  assert.equal(d.parts[1].angle, 75);
});

/* ------------------------------------------------------ physics sanity */

function flatScene(extra) {
  return Object.assign({ theme: 'meadow', spawn: { x: 10, y: 10 }, lucas: { x: 200, y: 56, face: -1 }, solids: [], fills: [], rocks: [], wind: [], water: [], falls: [], bugs: [], fish: [], bubbles: [] }, extra || {});
}
function simIn(scene, parts, seconds) {
  const spec = { scene, tray: [{ type: 'ramp', len: 60, count: 5 }], initial: [], challenges: [] };
  const sim = Engine.createSim(spec, { parts: parts || [] });
  const out = [];
  while (sim.running && sim.t < seconds) { sim.step(); out.push({ t: sim.t, x: sim.body.x, y: sim.body.y, vx: sim.body.vx, vy: sim.body.vy }); }
  return { sim, out };
}

test('free fall matches g: after 0.5 s the pellet has fallen about ½·g·t²', () => {
  const { out } = simIn(flatScene({ spawn: { x: 50, y: 2 } }), [], 0.5);
  const last = out[out.length - 1];
  assert.ok(Math.abs((last.y - 2) - 0.5 * Engine.GRAVITY * 0.25) < 0.6, 'fell ' + (last.y - 2));
});

function slopeAccel(mat, deg) {
  const a = deg * Math.PI / 180;
  const scene = flatScene({ spawn: { x: 12, y: 8 }, solids: [{ x1: 5, y1: 10, x2: 95, y2: 10 + Math.tan(a) * 90, mat }] });
  const onSlope = simIn(scene, [], 1.2).out.filter(p => p.t > 0.5), first = onSlope[0], last = onSlope[onSlope.length - 1];
  return (Math.hypot(last.vx, last.vy) - Math.hypot(first.vx, first.vy)) / (last.t - first.t);
}

test('rolling down a slope accelerates at 5/7 · g · sin θ (solid ball), less with rolling resistance', () => {
  for (const deg of [10, 20, 30]) {
    const expected = 5 / 7 * Engine.GRAVITY * Math.sin(deg * Math.PI / 180), ice = slopeAccel('ice', deg);
    assert.ok(Math.abs(ice - expected) / expected < 0.05, deg + '° on ice: measured ' + ice.toFixed(2) + ' expected ' + expected.toFixed(2));
    assert.ok(slopeAccel('wood', deg) < ice, 'wooden ramps have a little rolling resistance');
  }
});

test('water: the pellet sinks much slower than it falls in air (drag and buoyancy)', () => {
  const air = simIn(flatScene({ spawn: { x: 50, y: 10 } }), [], 1).out.pop();
  const wet = simIn(flatScene({ spawn: { x: 50, y: 10 }, water: [{ x: 0, y: 0, w: 100, h: 56, current: 0 }] }), [], 1).out.pop();
  assert.ok(wet.vy > 0 && wet.vy < air.vy / 5, 'air ' + air.vy.toFixed(1) + ' water ' + wet.vy.toFixed(1));
});

test('currents carry, wind pushes, waterfalls pull down, bubbles lift', () => {
  const cur = simIn(flatScene({ spawn: { x: 30, y: 20 }, water: [{ x: 0, y: 10, w: 100, h: 46, current: 30 }] }), [], 1).out.pop();
  assert.ok(cur.x > 35, 'current moved it to ' + cur.x);
  const wind = simIn(flatScene({ spawn: { x: 30, y: 5 }, wind: [{ x: 0, y: 0, w: 100, h: 56, fx: -40, fy: 0, gust: 0 }] }), [], 0.8).out.pop();
  assert.ok(wind.x < 25, 'wind moved it to ' + wind.x);
  const plain = simIn(flatScene({ spawn: { x: 50, y: 5 } }), [], 0.4).out.pop();
  const falls = simIn(flatScene({ spawn: { x: 50, y: 5 }, falls: [{ x: 45, y: 0, w: 10, h: 56 }] }), [], 0.4).out.pop();
  assert.ok(falls.y > plain.y + 3, 'waterfall should pull harder than gravity alone');
  const lift = simIn(flatScene({ spawn: { x: 50, y: 50 }, water: [{ x: 0, y: 20, w: 100, h: 36, current: 0 }], bubbles: [{ x: 45, y: 20, w: 10, h: 36, fy: -70 }] }), [], 0.8).out.pop();
  assert.ok(lift.y < 50, 'bubbles should lift the pellet');
});

test('a bouncer returns most of the drop height; grass does not', () => {
  function peakAfterBounce(mat) {
    const scene = flatScene({ spawn: { x: 50, y: 10 }, solids: [{ x1: 30, y1: 50, x2: 70, y2: 50, mat }] });
    const { out } = simIn(scene, [], 2.5);
    const hit = out.findIndex(p => p.vy < 0);
    return Math.min(...out.slice(hit).map(p => p.y));
  }
  assert.ok(peakAfterBounce('bouncer') < 20, 'bouncer peak ' + peakAfterBounce('bouncer'));
  assert.ok(peakAfterBounce('grass') > 40, 'grass peak ' + peakAfterBounce('grass'));
});

test('bees and fish move on fixed timetables', () => {
  const b = { x: 50, y: 30, ax: 10, ay: 4, period: 3 };
  assert.deepEqual(Engine.bugAt(b, 1.234), Engine.bugAt(b, 1.234));
  assert.ok(Math.abs(Engine.bugAt(b, 0).x - Engine.bugAt(b, 3).x) < 1e-9, 'a bee is back where it started after one period');
  const f = { x: 60, y: 45, range: 10, period: 2 };
  assert.ok(Math.abs(Engine.fishAt(f, 0.5).x - 70) < 1e-9);
});

/* ------------------------------------------------------------ diagnoses */

test('diagnoses name what actually happened', () => {
  const s1 = Engine.resolve(LEVELS[0]);
  assert.equal(Engine.runToEnd(s1, Engine.newDesign(s1)).diag.code, 'stuck', 'a flat ramp holds the pellet');
  assert.equal(Engine.runToEnd(s1, { parts: [{ type: 'ramp', x: 80, y: 30, angle: 20, len: 22 }] }).diag.code, 'missed_part');
  const s6 = Engine.resolve(LEVELS[5]);
  const fast = Engine.runToEnd(s6, { parts: [{ type: 'ramp', x: 38, y: 20, angle: 45, len: 22 }] });
  assert.ok(['too_fast', 'overshot', 'lost', 'short'].includes(fast.diag.code), fast.diag.code);
  const gap = { scene: flatScene({ spawn: { x: 50, y: 10 }, lucas: { x: 90, y: 56, face: -1 } }), tray: [], initial: [], challenges: [] };
  assert.equal(Engine.runToEnd(gap, { parts: [] }).diag.code, 'fell', 'no ground under the pellet');
});

test('the journey recap lists the things the pellet met, in order', () => {
  const spec = Engine.resolve(LEVELS[21]);
  const run = Engine.runToEnd(spec, SOL[22].main);
  const j = Engine.journey(run.sim);
  assert.match(j.text, /splashed into the water/);
  assert.ok(j.text.indexOf('rolled down your ramp') < j.text.indexOf('splashed'));
  assert.ok(j.notes.length >= 1 && j.notes.length <= 3);
});
