/* Search-based solver used by the tests. It proves each authored level,
 * variation and challenge is solvable by actually finding a design that the
 * engine accepts, rather than trusting a hand-written answer.
 */
'use strict';
const Levels = require('../assets/levels.js');
const Engine = require('../assets/engine.js');

function range(a, b, step) { const out = []; for (let v = a; v <= b + 1e-9; v += step) out.push(v); return out; }

function tryDesign(spec, design, rule, extra) {
  const run = Engine.runToEnd(spec, design);
  if (!run.ok) return null;
  if (rule && !Engine.checkRule(spec, design, run, rule, extra)) return null;
  return { design, run };
}

/* Candidate designs per mode. Generators yield designs lazily so the search
   stops at the first hit. */
function* physicsCandidates(spec) {
  const sc = spec.scene;
  if (sc.launcher) {
    for (const power of sc.launcher.powers) for (const angle of sc.launcher.angles) {
      yield { parts: [], launcher: { power, angle } };
    }
    const pad = sc.zones[0];
    for (const power of sc.launcher.powers) for (const angle of sc.launcher.angles) {
      for (const x of range(pad.x + 2, pad.x + pad.w, 2)) yield { parts: [{ type: 'bumper', x, y: pad.y + pad.h, h: 8 }], launcher: { power, angle } };
    }
    return;
  }
  const ramps = spec.tray.filter(t => t.type === 'ramp');
  if (ramps.length) {
    const len = ramps[0].len;
    for (const angle of range(-60, 60, 5)) for (const y of range(14, 50, 2)) for (const x of range(8, 80, 2)) {
      yield { parts: [{ type: 'ramp', x, y, angle, len }] };
    }
    if (Engine.trayLimit(spec, 'ramp') >= 2) {
      for (const a1 of [15, 25, 35]) for (const x1 of range(14, 40, 4)) for (const y1 of range(16, 34, 4))
        for (const a2 of [-35, -25, -15, 15, 25, 35]) for (const x2 of range(30, 80, 4)) for (const y2 of range(30, 50, 4))
          yield { parts: [{ type: 'ramp', x: x1, y: y1, angle: a1, len }, { type: 'ramp', x: x2, y: y2, angle: a2, len }] };
    }
    return;
  }
  const fans = Engine.trayLimit(spec, 'fan'), barriers = Engine.trayLimit(spec, 'barrier');
  if (fans) {
    const dirs = ['E', 'W', 'N', 'S'];
    for (const dir of dirs) for (const y of range(8, 52, 4)) for (const x of range(6, 94, 4)) yield { parts: [{ type: 'fan', x, y, dir }] };
    if (barriers) {
      for (const dir of ['E', 'W']) for (const y of range(8, 44, 6)) for (const x of range(6, 94, 6))
        for (const ba of [-30, -15, 0, 15, 30]) for (const by of range(20, 50, 6)) for (const bx of range(20, 90, 6))
          yield { parts: [{ type: 'fan', x, y, dir }, { type: 'barrier', x: bx, y: by, angle: ba, len: 12 }] };
    }
    if (fans >= 2) {
      for (const d1 of ['E', 'W']) for (const y1 of range(8, 44, 6)) for (const x1 of range(6, 94, 6))
        for (const d2 of ['E', 'W', 'N']) for (const y2 of range(8, 50, 6)) for (const x2 of range(6, 94, 6))
          yield { parts: [{ type: 'fan', x: x1, y: y1, dir: d1 }, { type: 'fan', x: x2, y: y2, dir: d2 }] };
    }
  }
}

function* bridgeCandidates(spec) {
  const sc = spec.scene, y = sc.cliffs[0].y, gapL = sc.cliffs[0].x2, gapR = sc.cliffs[1].x1;
  const posts = Engine.trayLimit(spec, 'post'), long = Engine.trayLimit(spec, 'beam', 30), short = Engine.trayLimit(spec, 'beam', 16);
  if (long) {
    for (const x of range(gapL - 4, gapR + 4, 2)) {
      yield { parts: [{ type: 'beam', x, y, angle: 0, len: 30 }] };
      if (posts) for (const px of range(gapL + 2, gapR - 2, 2)) yield { parts: [{ type: 'beam', x, y, angle: 0, len: 30 }, { type: 'post', x: px, h: 16 }] };
    }
  }
  if (short >= 2 && posts) {
    for (const px of range(gapL + 4, gapR - 4, 2)) for (const x1 of range(gapL - 8, px, 2)) for (const x2 of range(px, gapR + 8, 2)) {
      yield { parts: [{ type: 'beam', x: x1, y, angle: 0, len: 16 }, { type: 'beam', x: x2, y, angle: 0, len: 16 }, { type: 'post', x: px, h: 16 }] };
    }
    if (posts >= 2) {
      for (const p1 of range(gapL + 4, gapR - 4, 4)) for (const p2 of range(p1 + 4, gapR - 2, 4))
        for (const x1 of range(gapL - 6, gapR, 2)) for (const x2 of range(x1, gapR + 8, 2))
          yield { parts: [{ type: 'beam', x: x1, y, angle: 0, len: 16 }, { type: 'beam', x: x2, y, angle: 0, len: 16 }, { type: 'post', x: p1, h: 16 }, { type: 'post', x: p2, h: 16 }] };
    }
  }
}

function* balanceCandidates(spec) {
  const sc = spec.scene, slots = sc.slots.filter(s => sc.blocked.indexOf(s) === -1), n = sc.crates.length;
  // every assignment of crates to distinct slots
  function* assign(i, used, acc) {
    if (i === n) { yield { placements: acc.slice() }; return; }
    for (const s of slots) {
      if (used.has(s)) continue;
      used.add(s); acc.push({ crate: i, slot: s });
      yield* assign(i + 1, used, acc);
      acc.pop(); used.delete(s);
    }
  }
  yield* assign(0, new Set(), []);
}

function* gearsCandidates(spec) {
  const g = spec.scene.gears;
  for (const lift of g) yield { slots: [null, lift] };
  for (const idler of g) for (const lift of g) yield { slots: [idler, lift] };
}

/* Circuit: enumerate simple loops through the grid using a DFS over cells,
   then translate the loop into tiles. Battery / lamp axes follow the loop. */
function* circuitCandidates(spec) {
  const sc = spec.scene, blocked = new Set(sc.blocked.map(b => b.r + ',' + b.c));
  const fixed = {}; sc.fixed.forEach(f => { fixed[f.r + ',' + f.c] = f; });
  const battery = sc.fixed.find(f => f.kind === 'battery');
  const dirs = { N: [-1, 0], E: [0, 1], S: [1, 0], W: [0, -1] };
  const opp = { N: 'S', S: 'N', E: 'W', W: 'E' };
  const found = [];
  function* dfs(r, c, path, visited) {
    for (const d of ['N', 'E', 'S', 'W']) {
      const nr = r + dirs[d][0], nc = c + dirs[d][1], key = nr + ',' + nc;
      if (nr < 0 || nc < 0 || nr >= sc.rows || nc >= sc.cols || blocked.has(key)) continue;
      if (nr === battery.r && nc === battery.c && path.length >= 4) { yield path.concat([{ r: nr, c: nc, from: opp[d] }]); continue; }
      if (visited.has(key)) continue;
      visited.add(key);
      yield* dfs(nr, nc, path.concat([{ r: nr, c: nc, from: opp[d] }]), visited);
      visited.delete(key);
    }
  }
  const start = new Set([battery.r + ',' + battery.c]);
  for (const loop of dfs(battery.r, battery.c, [{ r: battery.r, c: battery.c, from: null }], start)) {
    // loop[i] entered from side loop[i].from; it exits toward loop[i+1]
    const cells = loop.slice(0, -1);
    const design = { tiles: [], fixedAxis: {} };
    let okLoop = true;
    for (let i = 0; i < cells.length; i++) {
      const cell = cells[i], next = loop[i + 1];
      const exit = next.from ? opp[next.from] : null;
      const enter = i === 0 ? loop[loop.length - 1].from : cell.from; // the battery is entered where the loop returns
      const key = cell.r + ',' + cell.c;
      const ports = [enter, exit].sort().join('');
      if (fixed[key]) {
        if (ports === 'EW') design.fixedAxis[key] = 'H'; else if (ports === 'NS') design.fixedAxis[key] = 'V'; else { okLoop = false; break; }
      } else if (ports === 'EW') design.tiles.push({ r: cell.r, c: cell.c, type: 'wire', rot: 0 });
      else if (ports === 'NS') design.tiles.push({ r: cell.r, c: cell.c, type: 'wire', rot: 1 });
      else {
        const rot = { EN: 0, ES: 1, SW: 2, NW: 3 }[ports];
        design.tiles.push({ r: cell.r, c: cell.c, type: 'corner', rot });
      }
    }
    if (!okLoop) continue;
    // all lamps must be on the loop
    const onLoop = new Set(cells.map(c => c.r + ',' + c.c));
    if (!sc.fixed.every(f => onLoop.has(f.r + ',' + f.c))) continue;
    // swap one straight wire for the (closed) switch
    const wireIdx = design.tiles.findIndex(t => t.type === 'wire');
    if (wireIdx === -1) continue;
    design.tiles[wireIdx] = Object.assign({}, design.tiles[wireIdx], { type: 'switch', closed: true });
    const wires = design.tiles.filter(t => t.type === 'wire').length, corners = design.tiles.filter(t => t.type === 'corner').length;
    if (wires > Engine.trayLimit(spec, 'wire') || corners > Engine.trayLimit(spec, 'corner')) continue;
    yield design;
  }
}

/* Code: breadth-first over short programs is too big; instead build a route by
   BFS to each station in turn, then (for loop levels) compress repeats. */
function codeSolution(spec, wantLoop, forbid) {
  const sc = spec.scene, turnL = { N: 'W', W: 'S', S: 'E', E: 'N' }, turnR = { N: 'E', E: 'S', S: 'W', W: 'N' };
  const dirs = { N: [-1, 0], E: [0, 1], S: [1, 0], W: [0, -1] };
  const stations = [];
  sc.map.forEach((row, r) => row.split('').forEach((ch, c) => { if (ch === 'S') stations.push({ r, c }); }));
  let state = { r: sc.start.r, c: sc.start.c, dir: sc.start.dir };
  const program = [];
  const remaining = stations.slice();
  function bfs(from) {
    // states: r,c,dir ; moves: F, L, R (R only if forbid L)
    const q = [{ r: from.r, c: from.c, dir: from.dir, path: [] }], seen = new Set([from.r + ',' + from.c + from.dir]);
    while (q.length) {
      const s = q.shift();
      if (remaining.some(st => st.r === s.r && st.c === s.c)) return s;
      const opts = [];
      const nr = s.r + dirs[s.dir][0], nc = s.c + dirs[s.dir][1];
      if (nr >= 0 && nc >= 0 && nr < sc.rows && nc < sc.cols && sc.map[nr][nc] !== '#') opts.push({ r: nr, c: nc, dir: s.dir, m: 'F' });
      if (forbid !== 'L') opts.push({ r: s.r, c: s.c, dir: turnL[s.dir], m: 'L' });
      opts.push({ r: s.r, c: s.c, dir: turnR[s.dir], m: 'R' });
      for (const o of opts) {
        const k = o.r + ',' + o.c + o.dir;
        if (seen.has(k)) continue;
        seen.add(k); q.push({ r: o.r, c: o.c, dir: o.dir, path: s.path.concat([o.m]) });
      }
    }
    return null;
  }
  while (remaining.length) {
    const s = bfs(state);
    if (!s) return null;
    s.path.forEach(m => program.push({ t: m }));
    program.push({ t: 'D' });
    state = { r: s.r, c: s.c, dir: s.dir };
    const idx = remaining.findIndex(st => st.r === s.r && st.c === s.c);
    remaining.splice(idx, 1);
  }
  if (!wantLoop) return program;
  // compress: pick the (start, unit) repetition that yields the fewest blocks
  const flat = program.map(b => b.t);
  let best = program, bestCount = program.length;
  for (let unit = 1; unit <= Math.floor(flat.length / 2); unit++) {
    for (let start = 0; start + 2 * unit <= flat.length; start++) {
      let reps = 1;
      while (start + (reps + 1) * unit <= flat.length && flat.slice(start, start + unit).join() === flat.slice(start + reps * unit, start + (reps + 1) * unit).join()) reps++;
      if (reps < 2) continue;
      const out = flat.slice(0, start).map(t => ({ t }));
      out.push({ t: 'loop', n: reps, body: flat.slice(start, start + unit).map(t => ({ t })) });
      flat.slice(start + reps * unit).forEach(t => out.push({ t }));
      const count = Engine.countBlocks(out);
      if (count < bestCount) { best = out; bestCount = count; }
    }
  }
  return best;
}

function candidates(spec) {
  switch (spec.mode) {
    case 'physics': return physicsCandidates(spec);
    case 'bridge': return bridgeCandidates(spec);
    case 'balance': return balanceCandidates(spec);
    case 'gears': return gearsCandidates(spec);
    case 'circuit': return circuitCandidates(spec);
  }
  return [][Symbol.iterator]();
}

/** Find a design that succeeds (and satisfies `rule` when given). */
function solve(spec, rule, extra, limit) {
  limit = limit || 400000;
  if (spec.mode === 'code') {
    const variants = [
      codeSolution(spec, false), codeSolution(spec, true), codeSolution(spec, false, 'L'), codeSolution(spec, true, 'L')
    ];
    if (rule && rule.type === 'nestedLoop') {
      // wrap the whole loop program in a 1x outer repeat: still a real nested loop
      variants.forEach(v => { if (v && v.some(b => b.t === 'loop')) variants.push([{ t: 'loop', n: 1, body: v }]); });
    }
    for (const program of variants) {
      if (!program) continue;
      const hit = tryDesign(spec, { program }, rule, extra);
      if (hit) return hit;
    }
    return null;
  }
  let n = 0;
  for (const design of candidates(spec)) {
    const hit = tryDesign(spec, design, rule, extra);
    if (hit) return hit;
    if (++n > limit) break;
  }
  return null;
}

module.exports = { solve, tryDesign, codeSolution, Levels, Engine };
