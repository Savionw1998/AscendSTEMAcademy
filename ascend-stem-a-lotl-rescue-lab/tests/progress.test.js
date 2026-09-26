'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const Progress = require('../assets/progress.js');
const Levels = require('../assets/levels.js');

const CFG = Levels.CONFIG;

test('merge keeps the best of both records and never loses a star or design', () => {
  const a = Progress.empty();
  Progress.recordSuccess(a, 1, ['efficiency'], { time: 5.2, parts: 1 });
  Progress.saveDesign(a, 1, { parts: [{ type: 'ramp', x: 24, y: 14, angle: 10, len: 22 }] }, 'A', {}, 100);
  const b = Progress.empty();
  Progress.recordSuccess(b, 1, ['invention'], { time: 4.1, parts: 1 });
  Progress.recordSuccess(b, 12, [], { time: 6, parts: 2 });
  Progress.saveDesign(b, 1, { parts: [{ type: 'ramp', x: 26, y: 14, angle: 5, len: 22 }] }, 'B', {}, 200);
  const m = Progress.merge(a, b);
  assert.deepEqual(m.levels[1].badges, { rescue: true, efficiency: true, invention: true });
  assert.equal(m.levels[1].best.time, 4.1);
  assert.equal(m.levels[1].designs.length, 2);
  assert.equal(m.levels[12].done, true);
  assert.deepEqual(Progress.merge(b, a).levels[1].badges, m.levels[1].badges);
});

test('sanitize drops junk and ownership-looking fields', () => {
  const p = Progress.sanitize({ owned: [4, 5], levels: { 99: { done: true }, 1: { done: true, badges: { hacker: true, rescue: true }, designs: new Array(20).fill({ name: 'x', design: { parts: [] } }) } }, settings: { sound: 'no', skin: 'nope' } });
  assert.equal(p.owned, undefined);
  assert.equal(p.levels[99], undefined);
  assert.deepEqual(p.levels[1].badges, { rescue: true });
  assert.equal(p.levels[1].designs.length, Progress.MAX_DESIGNS);
  assert.equal(p.settings.sound, true);
  assert.equal(p.settings.skin, 'pink');
});

test('access: first three free, others need ownership or a pass', () => {
  const L = Levels.LEVELS;
  assert.equal(Progress.accessOf(L[2], { owned: {}, config: CFG }), 'free');
  assert.equal(Progress.accessOf(L[3], { owned: {}, config: CFG }), 'locked');
  assert.equal(Progress.accessOf(L[3], { owned: { 4: 'daily' }, config: CFG }), 'owned');
  assert.equal(Progress.accessOf(L[39], { owned: {}, hasPass: true, config: CFG }), 'owned');
  assert.equal(Progress.accessOf(L[4], { owned: {}, config: Object.assign({}, CFG, { freeLevels: 5 }) }), 'free');
});

test('daily unlock: one per calendar day, lowest locked first, no rollover', () => {
  let state = { owned: {}, lastDaily: '', config: CFG };
  let r = Progress.claimDaily(state, '2026-09-26');
  assert.equal(r.opened, 4); state = r.state;
  r = Progress.claimDaily(state, '2026-09-26');
  assert.equal(r.opened, null, 'same day opens nothing');
  state.owned[5] = 'key';
  r = Progress.claimDaily(state, '2026-10-09');
  assert.equal(r.opened, 6, 'skips key-opened puzzles; a long gap still opens only one'); state = r.state;
  assert.equal(Progress.claimDaily(state, '2026-10-09').opened, null);
  assert.equal(Progress.claimDaily({ owned: {}, lastDaily: '', config: Object.assign({}, CFG, { dailyUnlock: false }) }, '2026-10-10').opened, null);
  assert.equal(Progress.claimDaily({ owned: {}, hasPass: true, lastDaily: '', config: CFG }, '2026-10-10').opened, null);
});

test('skins: earned by stars and worlds, key skins only through ownership', () => {
  const p = Progress.empty();
  const byId = id => Progress.skinById(id);
  assert.equal(Progress.skinState(p, byId('pink')).open, true);
  assert.equal(Progress.skinState(p, byId('blueberry')).open, false);
  for (let i = 1; i <= 10; i++) Progress.recordSuccess(p, i, []);
  assert.equal(Progress.skinState(p, byId('blueberry')).open, true, '10 stars beats the 5-star rule');
  assert.equal(Progress.skinState(p, byId('lime')).open, true, 'finishing world 1');
  assert.equal(Progress.skinState(p, byId('sunny')).open, false);
  assert.equal(Progress.skinState(p, byId('galaxy')).open, false);
  assert.equal(Progress.skinState(p, byId('galaxy'), { galaxy: true }).open, true);
  assert.equal(Levels.SKINS.filter(s => s.rule.type === 'keys').length, 3);
});

test('summary counts practice observations', () => {
  const p = Progress.empty();
  Progress.recordSuccess(p, 1, ['efficiency']);
  Progress.recordSuccess(p, 21, []);
  p.runs = 9; p.fails = 4;
  const s = Progress.summary(p);
  assert.equal(s.fed, 2); assert.equal(s.stars, 3); assert.equal(s.maxStars, 120);
  assert.deepEqual(s.ideas, [Levels.LEVELS[0].idea, Levels.LEVELS[20].idea]);
});

test('dayKey formats local calendar days', () => {
  assert.equal(Progress.dayKey(new Date(2026, 8, 6)), '2026-09-06');
});
