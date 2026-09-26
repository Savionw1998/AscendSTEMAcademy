'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const Progress = require('../assets/progress.js');
const Levels = require('../assets/levels.js');

test('merge keeps the best of both records and never loses an earned badge or design', () => {
  const a = Progress.empty();
  Progress.recordSuccess(a, 1, 0, ['efficiency'], { time: 5.2, parts: 1 });
  Progress.saveDesign(a, 1, 0, { parts: [{ type: 'ramp', x: 24, y: 14, angle: 10, len: 22 }] }, 'Design A', {}, 100);
  const b = Progress.empty();
  Progress.recordSuccess(b, 1, 1, ['invention'], { time: 4.1, parts: 1 });
  Progress.recordSuccess(b, 2, 0, [], { time: 6, parts: 2 });
  Progress.saveDesign(b, 1, 1, { parts: [{ type: 'ramp', x: 26, y: 14, angle: 5, len: 22 }] }, 'Design B', {}, 200);
  const m = Progress.merge(a, b);
  assert.deepEqual(m.levels[1].badges, { rescue: true, efficiency: true, invention: true });
  assert.deepEqual(m.levels[1].variations, { 0: true, 1: true });
  assert.equal(m.levels[1].best.time, 4.1);
  assert.equal(m.levels[1].designs.length, 2);
  assert.equal(m.levels[2].done, true);
  // merge is symmetric for earned content
  const m2 = Progress.merge(b, a);
  assert.deepEqual(m2.levels[1].badges, m.levels[1].badges);
});

test('sanitize drops junk: unknown levels, bad badge names, oversized design lists', () => {
  const raw = { levels: { 99: { done: true }, 1: { done: 'yes', badges: { hacker: true, rescue: true }, designs: new Array(20).fill({ name: 'x', design: {} }) } }, settings: { sound: 'no' } };
  const p = Progress.sanitize(raw);
  assert.equal(p.levels[99], undefined);
  assert.deepEqual(p.levels[1].badges, { rescue: true });
  assert.equal(p.levels[1].designs.length, Progress.MAX_DESIGNS);
  assert.equal(p.settings.sound, true);
});

test('ownership is never read from the progress record', () => {
  const p = Progress.sanitize({ owned: [4, 5, 6], levels: { 4: { done: true, owned: true } } });
  assert.equal(p.owned, undefined);
  const lvl = Levels.LEVELS[3];
  assert.equal(Progress.levelState(p, lvl, {}, false).access, 'locked');
  assert.equal(Progress.levelState(p, lvl, { 4: true }, false).access, 'owned');
  assert.equal(Progress.levelState(p, lvl, {}, true).access, 'owned');
  assert.equal(Progress.levelState(p, Levels.LEVELS[0], {}, false).access, 'free');
});

test('level status: new, completed, mastery', () => {
  const p = Progress.empty();
  const lvl = Levels.LEVELS[0];
  assert.equal(Progress.levelState(p, lvl, {}, false).status, 'new');
  Progress.recordSuccess(p, 1, 0, []);
  assert.equal(Progress.levelState(p, lvl, {}, false).status, 'completed');
  Progress.recordSuccess(p, 1, 0, ['efficiency', 'invention']);
  assert.equal(Progress.levelState(p, lvl, {}, false).status, 'mastery');
});

test('decorations are deterministic and grow with play', () => {
  const p = Progress.empty();
  assert.deepEqual(Progress.decorations(p), []);
  Progress.recordSuccess(p, 1, 0, []);
  assert.deepEqual(Progress.decorations(p), [{ id: 'dock', label: 'Dock flag' }]);
  Progress.recordSuccess(p, 1, 0, ['efficiency', 'invention']);
  assert.equal(Progress.decorations(p).length, 2, 'three badges earn a bonus decoration');
});

test('practice challenge uses free content only for a free player and is stable within a week', () => {
  const p = Progress.empty();
  const key = Progress.weekKey(new Date(2026, 8, 26));
  assert.equal(key, '2026-W39');
  const a = Progress.practice(p, key, {}, false), b = Progress.practice(p, key, {}, false);
  assert.ok(a.level.free);
  assert.equal(a.level.id, b.level.id);
  assert.equal(a.challenge.id, b.challenge.id);
  assert.equal(a.done, false);
});

test('parent summary counts practice observations', () => {
  const p = Progress.empty();
  Progress.recordSuccess(p, 1, 0, ['efficiency']);
  Progress.recordSuccess(p, 3, 0, []);
  p.runs = 9; p.fails = 4;
  const s = Progress.summary(p);
  assert.equal(s.missions, 2);
  assert.equal(s.badges, 3);
  assert.deepEqual(s.concepts, [Levels.LEVELS[0].concept, Levels.LEVELS[2].concept]);
  assert.equal(s.runs, 9);
});
