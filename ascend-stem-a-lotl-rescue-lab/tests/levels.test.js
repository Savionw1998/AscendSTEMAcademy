/* Run with: node --test ascend-stem-a-lotl-rescue-lab/tests
 *
 * These tests are the shipping gate for content: every authored level,
 * variation and stage must be solvable by search, every optional challenge must
 * be achievable on its main mission, and the engine must be deterministic.
 */
'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { solve, tryDesign, Levels, Engine } = require('./solver.js');

const LEVELS = Levels.LEVELS;

/* Hand-authored programs for coding challenges the greedy search does not find. */
const AUTHORED = {
  '9:1:loopy': { program: [{ t: 'loop', n: 3, body: [{ t: 'R' }, { t: 'F' }, { t: 'F' }, { t: 'L' }, { t: 'F' }, { t: 'D' }] }] },
  '9:2:loopy': { program: [{ t: 'loop', n: 3, body: [{ t: 'F' }, { t: 'F' }, { t: 'F' }, { t: 'D' }, { t: 'R' }] }] }
};

test('content shape: ten levels, three free, two challenges each, station areas line up', () => {
  assert.equal(LEVELS.length, 10);
  assert.equal(LEVELS.filter(l => l.free).length, Levels.FREE_LEVELS);
  assert.deepEqual(LEVELS.slice(0, 3).map(l => l.free), [true, true, true]);
  LEVELS.forEach((l, i) => {
    assert.equal(l.id, i + 1);
    assert.equal(l.challenges.length, 2, l.name);
    assert.deepEqual(l.challenges.map(c => c.badge).sort(), ['efficiency', 'invention'], l.name);
    assert.ok(l.variations.length >= 1 && l.variations[0].name === 'Main mission', l.name);
    assert.equal(Levels.AREAS[i].id, l.area, l.name);
    assert.ok(l.objective && l.intro && l.reward && l.explain && l.reflect && l.concept, l.name + ' copy');
  });
  assert.equal(Levels.KEY_COST, 1);
});

for (const level of LEVELS) {
  const stages = level.mode === 'sequence' ? level.stages.length : 1;
  for (let v = 0; v < level.variations.length; v++) {
    for (let st = 0; st < stages; st++) {
      test(`level ${level.id} "${level.name}" variation ${v} stage ${st} is solvable`, () => {
        const spec = Engine.resolve(level, v, st);
        const hit = solve(spec);
        assert.ok(hit, 'no design found by search');
        assert.equal(hit.run.result, 'success');
        assert.equal(hit.run.diag.code, 'success');
        assert.ok(Engine.validateDesign(spec, hit.design).ok, 'solution must fit the tray');
      });
    }
  }

  if (level.mode !== 'sequence') {
    for (const ch of level.challenges) {
      test(`level ${level.id} challenge "${ch.id}" is achievable on the main mission`, () => {
        const spec = Engine.resolve(level, 0, 0);
        const hit = solve(spec, ch.rule, { failedRuns: 0 });
        assert.ok(hit, 'no design satisfies the challenge');
      });
    }
  }
}

test('authored loop programs satisfy the Loop the Route badge on every variation', () => {
  const level = LEVELS[8];
  for (let v = 0; v < level.variations.length; v++) {
    const spec = Engine.resolve(level, v);
    const design = AUTHORED[`9:${v}:loopy`] || solve(spec, level.challenges[0].rule).design;
    assert.ok(design, 'variation ' + v);
    const run = Engine.runToEnd(spec, design);
    assert.ok(run.ok, 'variation ' + v + ' ' + JSON.stringify(run.diag));
    assert.ok(Engine.checkRule(spec, design, run, level.challenges[0].rule), 'variation ' + v + ' loop badge');
  }
});

test('the authored starting layout of level 1 is not a forced failure and not an instant win', () => {
  const spec = Engine.resolve(LEVELS[0], 0);
  const run = Engine.runToEnd(spec, Engine.newDesign(spec));
  assert.equal(run.ok, false);
  assert.equal(run.diag.code, 'stuck', 'a flat ramp keeps the capsule; the diagnosis says to tilt it');
  const tilted = Engine.newDesign(spec);
  tilted.parts[0].angle = 10;
  assert.ok(Engine.runToEnd(spec, tilted).ok, 'tilting the starting ramp once is a valid first solution');
});

test('same design, same conditions: identical result, timing and trail (determinism)', () => {
  for (const level of LEVELS) {
    const spec = Engine.resolve(level, 0, 0);
    const hit = solve(spec);
    const a = Engine.runToEnd(spec, hit.design), b = Engine.runToEnd(spec, hit.design);
    assert.equal(a.result, b.result);
    assert.equal(a.t, b.t);
    assert.deepEqual(a.trail, b.trail);
    assert.deepEqual(a.diag, b.diag);
  }
});

test('reset is reliable: a fresh sim never inherits state from a finished one', () => {
  const spec = Engine.resolve(LEVELS[0], 0);
  const design = solve(spec).design;
  const first = Engine.createSim(spec, design);
  while (first.step()) { /* run */ }
  const second = Engine.createSim(spec, design);
  assert.equal(second.t, 0);
  assert.equal(second.running, true);
  assert.deepEqual(second.snapshot().body, { x: spec.scene.spawn.x, y: spec.scene.spawn.y, vx: 0, vy: 0, r: 2.2 });
  while (second.step()) { /* run */ }
  assert.equal(second.t, first.t);
});

test('designs are snapped so dragging does not create unreproducible runs', () => {
  const spec = Engine.resolve(LEVELS[0], 0);
  const d = Engine.normalizeDesign(spec, { parts: [{ type: 'ramp', x: 24.4, y: 13.6, angle: 12.4, len: 22 }] });
  assert.deepEqual(d.parts[0], { type: 'ramp', x: 24, y: 14, angle: 10, len: 22 });
});

test('tray limits are enforced by validateDesign', () => {
  const spec = Engine.resolve(LEVELS[0], 0);
  const bad = { parts: [{ type: 'ramp', x: 20, y: 20, angle: 10, len: 22 }, { type: 'ramp', x: 40, y: 30, angle: 10, len: 22 }] };
  const v = Engine.validateDesign(spec, bad);
  assert.equal(v.ok, false);
  assert.match(v.issues[0], /Too many ramps/);
});

test('physics diagnoses describe the observed miss, not a guessed cause', () => {
  const spec = Engine.resolve(LEVELS[0], 0);
  const short = Engine.runToEnd(spec, { parts: [{ type: 'ramp', x: 24, y: 14, angle: 5, len: 22 }] });
  assert.ok(!short.ok);
  assert.ok(['short', 'stuck', 'settled', 'overshot'].includes(short.diag.code), short.diag.code);
  const missed = Engine.runToEnd(spec, { parts: [{ type: 'ramp', x: 60, y: 30, angle: 20, len: 22 }] });
  assert.equal(missed.diag.code, 'missed_part');
});

test('bridge: a long beam without a middle support sags; with a post it holds', () => {
  const spec = Engine.resolve(LEVELS[1], 0);
  const noPost = Engine.runToEnd(spec, { parts: [{ type: 'beam', x: 44, y: 40, angle: 0, len: 30 }] });
  assert.equal(noPost.diag.code, 'sagged');
  const withPost = Engine.runToEnd(spec, { parts: [{ type: 'beam', x: 44, y: 40, angle: 0, len: 30 }, { type: 'post', x: 44, h: 16 }] });
  assert.ok(withPost.ok, JSON.stringify(withPost.diag));
  const nothing = Engine.runToEnd(spec, { parts: [] });
  assert.equal(nothing.diag.code, 'fell');
});

test('balance: reports the turning effect on each side when the cart tips', () => {
  const spec = Engine.resolve(LEVELS[2], 0);
  const run = Engine.runToEnd(spec, { placements: [{ crate: 0, slot: -3 }, { crate: 1, slot: 1 }, { crate: 2, slot: 2 }, { crate: 3, slot: 3 }] });
  assert.equal(run.diag.code, 'tipped');
  assert.match(run.diag.text, /left: 9, right: 7/);
  const unloaded = Engine.runToEnd(spec, { placements: [{ crate: 0, slot: -1 }] });
  assert.equal(unloaded.diag.code, 'unloaded');
});

test('gears: idler changes direction but not the ratio', () => {
  const spec = Engine.resolve(LEVELS[5], 0);
  const direct = Engine.gearsEval(spec, { slots: [null, 24] });
  const idler = Engine.gearsEval(spec, { slots: [16, 24] });
  assert.equal(direct.ratio, idler.ratio);
  assert.notEqual(direct.direction, idler.direction);
  assert.equal(Engine.runToEnd(spec, { slots: [null, 8] }).diag.code, 'stall');
  assert.equal(Engine.runToEnd(spec, { slots: [null, null] }).diag.code, 'nolift');
});

test('circuit: open switch, missing lamp and open path are each named', () => {
  const spec = Engine.resolve(LEVELS[6], 0);
  const good = solve(spec).design;
  assert.equal(Engine.runToEnd(spec, good).diag.code, 'success');
  const open = Engine.clone(good);
  open.tiles.find(t => t.type === 'switch').closed = false;
  assert.equal(Engine.runToEnd(spec, open).diag.code, 'switch_open');
  const gap = Engine.clone(good);
  gap.tiles.splice(gap.tiles.findIndex(t => t.type === 'wire'), 1);
  assert.equal(Engine.runToEnd(spec, gap).diag.code, 'open');
});

test('code: a bump names the step and the failing block path', () => {
  const spec = Engine.resolve(LEVELS[7], 0);
  const run = Engine.runToEnd(spec, { program: [{ t: 'L' }, { t: 'F' }, { t: 'F' }, { t: 'F' }, { t: 'F' }] });
  assert.equal(run.diag.code, 'bump');
  assert.deepEqual(run.diag.path, [4]);
  const empty = Engine.runToEnd(spec, { program: [] });
  assert.equal(empty.diag.code, 'empty');
});
