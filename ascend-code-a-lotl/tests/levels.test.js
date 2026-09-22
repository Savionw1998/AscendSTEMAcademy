// Run with: node --test ascend-code-a-lotl/tests
const test = require('node:test');
const assert = require('node:assert');
const { Engine, LEVELS } = require('../assets/code-a-lotl.js');

const m = (d) => ({ t: 'move', d });
const rep = (n, ...body) => ({ t: 'loop', n, body });
const moves = (s) => s.split('').map(m);

// A reference solution for each level that meets its par.
const SOLUTIONS = [
  moves('RRR'),
  moves('RRRR'),
  moves('RDDR'),
  [rep(7, m('R'))],
  [rep(4, m('R'), m('D'))],
  [rep(3, m('R')), rep(2, m('D')), rep(3, m('L')), rep(2, m('D')), rep(3, m('R'))],
  [rep(3, m('R'), m('R'), m('D'))],
  [rep(3, m('R'), rep(2, m('D')), m('R'))],
  [rep(2, m('U')), rep(4, m('R')), rep(2, m('D'))],
  [rep(6, m('R')), rep(2, m('D')), rep(6, m('L')), rep(2, m('D')), rep(6, m('R'))],
  [rep(3, rep(3, m('R')), m('D'))],
  [rep(3, m('D')), rep(3, m('R')), rep(3, m('U')), rep(2, m('R')), rep(3, m('D')), m('R')]
];

test('every level has exactly one start and goal and a rectangular map', () => {
  LEVELS.forEach((lvl, i) => {
    const flat = lvl.map.join('');
    assert.strictEqual(flat.split('A').length - 1, 1, `level ${i + 1} start`);
    assert.strictEqual(flat.split('G').length - 1, 1, `level ${i + 1} goal`);
    lvl.map.forEach((row) => assert.strictEqual(row.length, lvl.map[0].length, `level ${i + 1} row width`));
  });
});

test('every level is winnable within par (3 stars)', () => {
  assert.strictEqual(SOLUTIONS.length, LEVELS.length);
  LEVELS.forEach((lvl, i) => {
    const sim = Engine.simulate(lvl, SOLUTIONS[i]);
    assert.strictEqual(sim.result, 'win', `level ${i + 1} (${lvl.name})`);
    assert.strictEqual(Engine.stars(lvl, SOLUTIONS[i]), 3, `level ${i + 1} par ${lvl.par}`);
  });
});

test('bumping, hunger and short programs are detected', () => {
  assert.strictEqual(Engine.simulate(LEVELS[2], moves('D')).result, 'bump');
  assert.strictEqual(Engine.simulate(LEVELS[0], moves('L')).result, 'bump');
  assert.strictEqual(Engine.simulate(LEVELS[8], moves('R')).result, 'bump');
  assert.strictEqual(Engine.simulate(LEVELS[1], moves('RR')).result, 'short');
  assert.strictEqual(Engine.simulate(LEVELS[0], []).result, 'empty');
  const hungry = { name: 't', par: 1, map: ['F', 'A', 'G'] };
  assert.strictEqual(Engine.simulate(hungry, moves('D')).result, 'hungry');
});

test('runaway loops are capped', () => {
  const huge = [rep(9, rep(9, rep(9, m('R'))))];
  assert.strictEqual(Engine.simulate(LEVELS[0], huge).result, 'toolong');
});

test('block counting includes loop blocks and nested contents', () => {
  assert.strictEqual(Engine.countBlocks([rep(3, rep(3, m('R')), m('D'))]), 4);
  assert.strictEqual(Engine.stars(LEVELS[3], moves('RRRRRRR')), 1);
  assert.strictEqual(Engine.stars(LEVELS[0], moves('RRR')), 3);
});
