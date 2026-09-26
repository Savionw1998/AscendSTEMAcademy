/* STEM-a-lotl: Rescue Lab — simulation engine.
 *
 * Pure and deterministic: no DOM, no Date, no Math.random. A fixed time step,
 * bounded speeds and integer-snapped designs mean the same design under the same
 * level conditions always produces the same run, in the browser and in node.
 *
 * Modes:  physics (ramps, launcher, fans, barriers, bumpers)
 *         bridge  (beams + posts with a simple span rule, driven rover)
 *         balance (crates on a pivoting cart: sum of mass x distance)
 *         gears   (motor -> optional idler -> lift gear; ratio = teeth / teeth)
 *         circuit (grid of two-port tiles; a loop must pass battery, lamps, switch)
 *         code    (rover following action blocks, with Repeat blocks)
 *
 * Every simulator implements: step() -> boolean (true while running),
 * snapshot(), and after it finishes: result ('success' | a failure code) and
 * diag { code, text, hint } written only from what actually happened.
 */
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) module.exports = factory(require('./levels.js'));
  else root.AscendRLEngine = factory(root.AscendRLLevels);
})(typeof self !== 'undefined' ? self : this, function (Levels) {
  'use strict';

  var DT = 1 / 120;           // fixed simulation step, seconds
  var GRAVITY = 60;           // scene units / s^2
  var MAX_SPEED = 110;        // bounded velocity keeps every run stable
  var MAX_TIME = 14;          // seconds before a physics run is called a timeout
  var SETTLE_SPEED = 1.6;
  var SETTLE_TIME = 0.5;
  var GROUND = Levels.GROUND;

  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
  function deg2rad(d) { return d * Math.PI / 180; }
  function snapDeg(d) { return Math.round(d / 5) * 5; }

  /* ------------------------------------------------------------------ specs */

  /** Resolve a level + variation (+ stage for sequence levels) into one flat spec. */
  function resolve(level, variationIndex, stageIndex) {
    var v = level.variations[variationIndex || 0] || level.variations[0];
    var base = level;
    if (level.mode === 'sequence') base = level.stages[stageIndex || 0];
    var spec = {
      levelId: level.id, mode: base.mode, variation: variationIndex || 0, stage: stageIndex || 0,
      scene: clone(base.scene), tray: clone(base.tray || []), initial: clone(base.initial || []),
      challenges: level.challenges, name: base.name || level.name
    };
    if (v.scene) for (var k in v.scene) spec.scene[k] = clone(v.scene[k]);
    if (v.tray) spec.tray = clone(v.tray);
    if (v.initial) spec.initial = clone(v.initial);
    return spec;
  }

  /** A fresh design for a spec (the authored starting layout). */
  function newDesign(spec) {
    switch (spec.mode) {
      case 'physics':
        return { parts: clone(spec.initial), launcher: spec.scene.launcher ? { power: spec.scene.launcher.power, angle: spec.scene.launcher.angle } : null };
      case 'bridge': return { parts: clone(spec.initial) };
      case 'balance': return { placements: [] };
      case 'gears': return { slots: spec.scene.slots.map(function () { return null; }) };
      case 'circuit': return { tiles: [], fixedAxis: {} };
      case 'code': return { program: [] };
    }
    return {};
  }

  /** Snap a design to the grid so runs are reproducible after any drag. */
  function normalizeDesign(spec, design) {
    if (design.parts) design.parts.forEach(function (p) {
      p.x = Math.round(p.x); p.y = Math.round(p.y);
      if (p.angle != null) p.angle = snapDeg(p.angle);
    });
    return design;
  }

  /* Tray accounting: how many of each part the design may use. */
  function trayLimit(spec, type, len) {
    var n = 0;
    spec.tray.forEach(function (t) { if (t.type === type && (len == null || t.len === len)) n += t.count; });
    return n;
  }
  function trayUsed(design, type, len) {
    return (design.parts || []).filter(function (p) { return p.type === type && (len == null || p.len === len); }).length;
  }

  /* --------------------------------------------------------- geometry help */

  function partSegments(p) {
    var segs = [];
    if (p.type === 'ramp' || p.type === 'beam' || p.type === 'barrier') {
      var a = deg2rad(p.angle || 0), h = (p.len || 20) / 2;
      segs.push({ x1: p.x - Math.cos(a) * h, y1: p.y - Math.sin(a) * h, x2: p.x + Math.cos(a) * h, y2: p.y + Math.sin(a) * h, kind: p.type, part: p });
    } else if (p.type === 'post') {
      segs.push({ x1: p.x, y1: GROUND - (p.h || 16), x2: p.x, y2: GROUND, kind: 'post', part: p });
    } else if (p.type === 'bumper') {
      segs.push({ x1: p.x, y1: p.y - (p.h || 8), x2: p.x, y2: p.y, kind: 'bumper', part: p });
    }
    return segs;
  }

  function zoneSegments(z) {
    if (z.type === 'basket') {
      return [
        { x1: z.x, y1: z.y, x2: z.x, y2: z.y + z.h, kind: 'basket' },
        { x1: z.x + z.w, y1: z.y, x2: z.x + z.w, y2: z.y + z.h, kind: 'basket' },
        { x1: z.x, y1: z.y + z.h, x2: z.x + z.w, y2: z.y + z.h, kind: 'basket' }
      ];
    }
    if (z.type === 'pad') {
      // a landing pad has a low lip at each end
      return [
        { x1: z.x, y1: z.y + z.h - 3, x2: z.x, y2: z.y + z.h, kind: 'basket' },
        { x1: z.x + z.w, y1: z.y + z.h - 3, x2: z.x + z.w, y2: z.y + z.h, kind: 'basket' }
      ];
    }
    return [];
  }

  function inRect(x, y, z) { return x >= z.x && x <= z.x + z.w && y >= z.y && y <= z.y + z.h; }

  /* Circle vs segment: push out and reflect. Returns the contact, or null. */
  function collide(b, s, e, friction) {
    // friction is a per-second tangential loss rate; the caller scales it by DT
    var dx = s.x2 - s.x1, dy = s.y2 - s.y1, l2 = dx * dx + dy * dy;
    var t = l2 ? clamp(((b.x - s.x1) * dx + (b.y - s.y1) * dy) / l2, 0, 1) : 0;
    var px = s.x1 + dx * t, py = s.y1 + dy * t;
    var nx = b.x - px, ny = b.y - py, d = Math.sqrt(nx * nx + ny * ny);
    if (d >= b.r || d === 0) return null;
    nx /= d; ny /= d;
    var pen = b.r - d;
    b.x += nx * pen; b.y += ny * pen;
    var vn = b.vx * nx + b.vy * ny;
    if (vn < 0) {
      b.vx -= (1 + e) * vn * nx; b.vy -= (1 + e) * vn * ny;
      // tangential friction
      var tx = -ny, ty = nx, vt = b.vx * tx + b.vy * ty;
      b.vx -= vt * friction * tx; b.vy -= vt * friction * ty;
    }
    return { nx: nx, ny: ny, seg: s, vn: vn };
  }

  /* Rolling resistance per second of contact. Flat floors are grippy so a
     capsule comes to rest quickly; ramps and beams stay slick. */
  var FRICTION = { ground: 2.5, platform: 2.5, basket: 2.5, wall: 0.3, ramp: 0.15, beam: 0.3, barrier: 0.2, bumper: 0.3, post: 0.3 };
  var BOUNCE = { ground: 0.3, platform: 0.25, wall: 0.3, ramp: 0.15, beam: 0.15, barrier: 0.55, bumper: 0.05, basket: 0.25, post: 0.2 };

  /* -------------------------------------------------------- physics sim */

  function PhysicsSim(spec, design) {
    var sc = spec.scene;
    this.spec = spec; this.design = design;
    this.segs = clone(sc.static || []);
    this.zones = clone(sc.zones || []);
    var self = this;
    this.zones.forEach(function (z) { zoneSegments(z).forEach(function (s) { self.segs.push(s); }); });
    (design.parts || []).forEach(function (p) { partSegments(p).forEach(function (s) { self.segs.push(s); }); });
    this.fans = (design.parts || []).filter(function (p) { return p.type === 'fan'; });
    var r = (sc.capsule && sc.capsule.r) || 2.2;
    if (sc.launcher) {
      var L = sc.launcher, P = design.launcher || { power: L.power, angle: L.angle };
      var v = 18 + P.power * 9, a = deg2rad(P.angle);
      this.body = { x: L.x, y: L.y - 4, vx: Math.cos(a) * v, vy: -Math.sin(a) * v, r: r };
    } else {
      this.body = { x: sc.spawn.x, y: sc.spawn.y, vx: 0, vy: 0, r: r };
    }
    this.t = 0; this.settled = 0; this.running = true; this.result = null; this.diag = null;
    this.trail = []; this.events = []; this.touched = {}; this.inTarget = false; this.wasInTarget = false;
    this.steps = 0; this.lastContact = null;
  }

  PhysicsSim.prototype.snapshot = function () {
    return { body: { x: this.body.x, y: this.body.y, vx: this.body.vx, vy: this.body.vy, r: this.body.r }, t: this.t, running: this.running, result: this.result };
  };

  PhysicsSim.prototype.step = function () {
    if (!this.running) return false;
    var b = this.body, self = this;
    b.vy += GRAVITY * DT;
    this.fans.forEach(function (f) {
      var z = fanZone(f);
      if (inRect(b.x, b.y, z)) { b.vx += z.fx * DT; b.vy += z.fy * DT; }
    });
    var sp = Math.sqrt(b.vx * b.vx + b.vy * b.vy);
    if (sp > MAX_SPEED) { b.vx *= MAX_SPEED / sp; b.vy *= MAX_SPEED / sp; }
    b.x += b.vx * DT; b.y += b.vy * DT;

    var contact = null;
    for (var it = 0; it < 2; it++) {
      for (var i = 0; i < this.segs.length; i++) {
        var s = this.segs[i];
        var c = collide(b, s, BOUNCE[s.kind] != null ? BOUNCE[s.kind] : 0.2, it === 0 ? (FRICTION[s.kind] != null ? FRICTION[s.kind] : 0.3) * DT : 0);
        if (c) {
          contact = c;
          if (s.part && !this.touched[s.kind]) { this.touched[s.kind] = true; this.events.push({ t: this.t, type: 'touch', kind: s.kind }); }
          if (c.vn < -18) this.events.push({ t: this.t, type: 'bounce', kind: s.kind, x: b.x, y: b.y });
        }
      }
    }
    this.lastContact = contact;
    this.t += DT; this.steps++;
    if (this.steps % 4 === 0) this.trail.push([Math.round(b.x * 10) / 10, Math.round(b.y * 10) / 10]);

    var target = this.zones[0];
    var inside = target && inRect(b.x, b.y, target);
    if (inside && !this.inTarget) this.events.push({ t: this.t, type: 'enter_target' });
    if (!inside && this.inTarget) this.events.push({ t: this.t, type: 'exit_target' });
    if (inside) this.wasInTarget = true;
    this.inTarget = inside;

    sp = Math.sqrt(b.vx * b.vx + b.vy * b.vy);
    this.settled = sp < SETTLE_SPEED ? this.settled + DT : 0;

    if (b.y > 72 || b.x < -12 || b.x > 112) return this.finish('lost');
    if (this.settled >= SETTLE_TIME) return this.finish(inside ? 'success' : 'settled');
    if (this.t >= MAX_TIME) return this.finish('timeout');
    return true;
  };

  PhysicsSim.prototype.finish = function (code) {
    this.running = false;
    this.result = code === 'success' ? 'success' : code;
    this.diag = physicsDiag(this, code);
    return false;
  };

  function fanZone(f) {
    var dirs = { E: [1, 0], W: [-1, 0], N: [0, -1], S: [0, 1] };
    var d = dirs[f.dir || 'E'], reach = 26, width = 14, str = 85;
    // the fan blows a column of air `reach` units long in front of it
    if (d[0]) return { x: d[0] > 0 ? f.x : f.x - reach, y: f.y - width / 2, w: reach, h: width, fx: d[0] * str, fy: 0 };
    return { x: f.x - width / 2, y: d[1] > 0 ? f.y : f.y - reach, w: width, h: reach, fx: 0, fy: d[1] * str };
  }

  function physicsDiag(sim, code) {
    var b = sim.body, z = sim.zones[0], tname = z && z.type === 'pad' ? 'landing pad' : 'basket';
    var touchedPart = Object.keys(sim.touched).length > 0;
    if (code === 'success') {
      var bounces = sim.events.filter(function (e) { return e.type === 'bounce'; }).length;
      return { code: 'success', text: 'Delivered! The capsule settled in the ' + tname + ' after ' + sim.t.toFixed(1) + ' seconds' + (bounces ? ' and ' + bounces + ' bounce' + (bounces > 1 ? 's' : '') : '') + '.' };
    }
    if (code === 'lost') return { code: 'lost', text: 'The capsule left the station area.', hint: 'Aim it back toward the ' + tname + '.' };
    if (code === 'timeout') return { code: 'timeout', text: 'The capsule was still moving after ' + MAX_TIME + ' seconds and never settled.', hint: 'Something keeps it bouncing. Try a softer landing.' };
    // settled somewhere that is not the target
    if (sim.wasInTarget) return { code: 'bounced_out', text: 'The capsule reached the ' + tname + ' but bounced back out.', hint: 'It arrived too fast. Slow it down with a gentler slope or a shorter drop.' };
    var onPart = sim.lastContact && sim.lastContact.seg.part;
    if (onPart && sim.lastContact.seg.kind === 'ramp') return { code: 'stuck', text: 'The capsule stopped on the ramp at x = ' + Math.round(b.x) + '.', hint: 'Tilt the ramp so gravity can pull the capsule along it.' };
    if (!touchedPart && (sim.design.parts || []).length && !sim.spec.scene.launcher) return { code: 'missed_part', text: 'The capsule never touched your ' + sim.design.parts[0].type + '. It fell straight down and stopped at x = ' + Math.round(b.x) + '.', hint: 'Move the part under the drop point.' };
    if (z && b.x > z.x + z.w) return { code: 'overshot', text: 'The capsule overshot: it passed the ' + tname + ' and stopped ' + Math.round(b.x - (z.x + z.w)) + ' units past it.', hint: 'It had too much speed. Try a gentler slope, less power, or move the ramp closer.' };
    if (z && b.x < z.x) return { code: 'short', text: 'The capsule stopped ' + Math.round(z.x - b.x) + ' units before the ' + tname + '.', hint: 'It needs more speed or a better direction. Try a steeper slope or more power.' };
    return { code: 'settled', text: 'The capsule settled outside the ' + tname + '.', hint: 'Watch where it lands and adjust one thing.' };
  }

  /* --------------------------------------------------------- bridge sim */

  /** Structural check for one beam: where is it supported, and does any span exceed maxSpan? */
  function beamSupports(spec, design, beam) {
    var segs = partSegments(beam)[0], sc = spec.scene;
    var x1 = Math.min(segs.x1, segs.x2), x2 = Math.max(segs.x1, segs.x2);
    var yAt = function (x) { return segs.y1 + (segs.y2 - segs.y1) * ((x - segs.x1) / ((segs.x2 - segs.x1) || 1)); };
    var pts = [];
    (sc.cliffs || []).forEach(function (c) {
      var lo = Math.max(x1, c.x1), hi = Math.min(x2, c.x2);
      if (hi - lo >= 1 && Math.abs(yAt((lo + hi) / 2) - c.y) <= 2.5) { pts.push(lo); pts.push(hi); }
    });
    (design.parts || []).forEach(function (p) {
      if (p.type !== 'post') return;
      var top = GROUND - (p.h || 16);
      if (p.x >= x1 - 1 && p.x <= x2 + 1 && Math.abs(yAt(clamp(p.x, x1, x2)) - top) <= 2.5) pts.push(clamp(p.x, x1, x2));
    });
    // beams resting on other beams' ends count as supported at that point
    (design.parts || []).forEach(function (p) {
      if (p === beam || p.type !== 'beam') return;
      var o = partSegments(p)[0];
      [[o.x1, o.y1], [o.x2, o.y2]].forEach(function (end) {
        if (end[0] >= x1 - 1 && end[0] <= x2 + 1 && Math.abs(yAt(clamp(end[0], x1, x2)) - end[1]) <= 2.5) pts.push(clamp(end[0], x1, x2));
      });
    });
    pts.sort(function (a, b) { return a - b; });
    var weak = null, maxSpan = sc.maxSpan || 20;
    if (!pts.length) weak = { kind: 'unsupported', from: x1, to: x2 };
    else {
      if (pts[0] - x1 > 6) weak = { kind: 'overhang', from: x1, to: pts[0] };
      if (!weak && x2 - pts[pts.length - 1] > 6) weak = { kind: 'overhang', from: pts[pts.length - 1], to: x2 };
      for (var i = 1; i < pts.length && !weak; i++) if (pts[i] - pts[i - 1] > maxSpan) weak = { kind: 'span', from: pts[i - 1], to: pts[i] };
    }
    return { supports: pts, weak: weak, x1: x1, x2: x2 };
  }

  function BridgeSim(spec, design) {
    var sc = spec.scene, self = this;
    this.spec = spec; this.design = design;
    this.segs = clone(sc.static || []);
    this.beams = [];
    (design.parts || []).forEach(function (p) {
      var segs = partSegments(p);
      if (p.type === 'beam') { var info = beamSupports(spec, design, p); self.beams.push({ part: p, seg: segs[0], info: info, broken: false }); }
      segs.forEach(function (s) { self.segs.push(s); });
    });
    this.zones = clone(sc.zones || []);
    this.body = { x: sc.rover.x, y: sc.rover.y, vx: 0, vy: 0, r: sc.rover.r };
    this.t = 0; this.running = true; this.result = null; this.diag = null; this.trail = []; this.events = [];
    this.steps = 0; this.stuck = 0; this.contactAge = 1; this.broke = null; this.fellAt = null;
  }

  BridgeSim.prototype.snapshot = function () {
    return { body: { x: this.body.x, y: this.body.y, vx: this.body.vx, vy: this.body.vy, r: this.body.r }, t: this.t, running: this.running, result: this.result, broken: this.beams.filter(function (b) { return b.broken; }).map(function (b) { return b.part; }) };
  };

  BridgeSim.prototype.step = function () {
    if (!this.running) return false;
    var b = this.body, self = this;
    b.vy += GRAVITY * DT;
    if (this.contactAge < 0.08) { b.vx += 40 * DT; if (b.vx > 14) b.vx = 14; }
    b.x += b.vx * DT; b.y += b.vy * DT;
    var contact = false;
    for (var it = 0; it < 2; it++) {
      for (var i = 0; i < this.segs.length; i++) {
        var s = this.segs[i];
        if (s.kind === 'beam' && this.isBroken(s)) continue;
        if (collide(b, s, 0.05, it === 0 ? 1.5 * DT : 0)) contact = true;
      }
    }
    this.contactAge = contact ? 0 : this.contactAge + DT;
    // a weak beam gives way when the rover is over its weak region
    this.beams.forEach(function (bm) {
      if (bm.broken || !bm.info.weak) return;
      var w = bm.info.weak;
      if (b.x > w.from + 2 && b.x < w.to - 2 && Math.abs(b.y - (bm.seg.y1 + bm.seg.y2) / 2) < 6) {
        bm.broken = true; self.broke = bm; self.events.push({ t: self.t, type: 'beam_broke', x: b.x });
      }
    });
    this.t += DT; this.steps++;
    if (this.steps % 4 === 0) this.trail.push([Math.round(b.x * 10) / 10, Math.round(b.y * 10) / 10]);
    var sp = Math.sqrt(b.vx * b.vx + b.vy * b.vy);
    this.stuck = sp < 0.6 ? this.stuck + DT : 0;
    if (b.y > 46 && this.fellAt == null) { this.fellAt = Math.round(b.x); this.events.push({ t: this.t, type: 'fell', x: b.x }); }
    var goal = this.zones[0];
    if (goal && inRect(b.x, b.y, goal)) return this.finish('success');
    if (this.fellAt != null && (this.stuck > 0.4 || b.y > 70)) return this.finish('fell');
    if (this.stuck >= 1) return this.finish('stuck');
    if (this.t >= MAX_TIME) return this.finish('timeout');
    return true;
  };

  BridgeSim.prototype.isBroken = function (seg) {
    for (var i = 0; i < this.beams.length; i++) if (this.beams[i].part === seg.part) return this.beams[i].broken;
    return false;
  };

  BridgeSim.prototype.finish = function (code) {
    this.running = false; this.result = code;
    var d;
    if (code === 'success') d = { code: 'success', text: 'The rover crossed and reached the far side in ' + this.t.toFixed(1) + ' seconds.' };
    else if (this.broke) {
      var w = this.broke.info.weak, span = Math.round(w.to - w.from);
      d = w.kind === 'overhang'
        ? { code: 'overhang', text: 'A beam tipped: ' + span + ' units of it hung past its last support.', hint: 'Rest both ends of a beam on a cliff or a post.' }
        : { code: 'sagged', text: 'The beam sagged and gave way: ' + span + ' units had nothing underneath.', hint: 'Beams here hold ' + (this.spec.scene.maxSpan || 20) + ' units between supports. Put a post under the middle.' };
    } else if (code === 'fell') d = { code: 'fell', text: 'The rover drove off the edge at x = ' + this.fellAt + ' because nothing was there to drive on.', hint: 'Lay a beam across the gap so the rover has a road.' };
    else if (code === 'stuck') d = { code: 'stuck', text: 'The rover stopped at x = ' + Math.round(this.body.x) + ' and could not go on.', hint: 'Check for a step or a slope it cannot climb. Beams should be level with the cliffs.' };
    else d = { code: 'timeout', text: 'The rover was still going after ' + MAX_TIME + ' seconds without reaching the far side.', hint: 'Make sure the beams lead all the way across.' };
    this.diag = d;
    return false;
  };

  /* --------------------------------------------------------- balance sim */

  function BalanceSim(spec, design) {
    var sc = spec.scene;
    this.spec = spec; this.design = design;
    this.crates = sc.crates.slice();
    this.placed = {};
    var self = this, left = 0, right = 0, tl = 0, tr = 0;
    (design.placements || []).forEach(function (p) {
      var m = self.crates[p.crate];
      if (m == null) return;
      self.placed[p.crate] = p.slot;
      if (p.slot < 0) { left += m; tl += m * -p.slot; } else if (p.slot > 0) { right += m; tr += m * p.slot; }
    });
    this.torqueLeft = tl; this.torqueRight = tr; this.torque = tr - tl;
    this.unplaced = this.crates.length - Object.keys(this.placed).length;
    this.t = 0; this.running = true; this.result = null; this.diag = null; this.trail = []; this.events = [];
    this.balanced = this.unplaced === 0 && this.torque === 0;
    this.tilt = clamp(this.torque * 6, -28, 28);
  }

  BalanceSim.prototype.snapshot = function () {
    var p = clamp(this.t / 3, 0, 1);
    return { t: this.t, running: this.running, result: this.result, progress: p, tilt: this.balanced ? 0 : this.tilt * clamp(this.t / 0.8, 0, 1), cartX: this.balanced ? 20 + p * 56 : 20 + clamp(this.t / 0.8, 0, 1) * 8, torque: this.torque };
  };

  BalanceSim.prototype.step = function () {
    if (!this.running) return false;
    this.t += DT;
    if (this.unplaced > 0 && this.t >= 0.3) return this.finish('unloaded');
    if (!this.balanced && this.t >= 2.2) return this.finish('tipped');
    if (this.balanced && this.t >= 3.2) return this.finish('success');
    return true;
  };

  BalanceSim.prototype.finish = function (code) {
    this.running = false; this.result = code;
    if (code === 'success') this.diag = { code: 'success', text: 'Balanced! Turning effect on the left: ' + this.torqueLeft + ', on the right: ' + this.torqueRight + '. The cart rolled to the greenhouse.' };
    else if (code === 'unloaded') this.diag = { code: 'unloaded', text: this.unplaced + ' crate' + (this.unplaced > 1 ? 's are' : ' is') + ' still on the ground.', hint: 'Every crate needs a slot on the cart.' };
    else this.diag = { code: 'tipped', text: 'The cart tipped to the ' + (this.torque < 0 ? 'left' : 'right') + '. Turning effect left: ' + this.torqueLeft + ', right: ' + this.torqueRight + '.', hint: 'Turning effect is mass times distance from the pivot. Move a crate toward the lighter side, or farther from the middle on the lighter side.' };
    return false;
  };

  /* ----------------------------------------------------------- gears sim */

  function gearsEval(spec, design) {
    var sc = spec.scene, lift = design.slots[design.slots.length - 1], idler = design.slots.length > 1 ? design.slots[0] : null;
    if (!lift) return { ok: false, code: 'nolift', ratio: 0 };
    var ratio = lift / sc.motor.teeth;
    var rpm = sc.motor.rpm / ratio;
    var seconds = sc.lift.height / (sc.lift.unitsPerRev * rpm / 60);
    var direction = idler ? 1 : -1; // motor is +1 (clockwise); each mesh flips
    var ok = ratio >= sc.lift.needRatio && seconds <= sc.lift.timeLimit;
    var code = ok ? 'success' : (ratio < sc.lift.needRatio ? 'stall' : 'slow');
    return { ok: ok, code: code, ratio: ratio, rpm: rpm, seconds: seconds, direction: direction, idler: idler, lift: lift };
  }

  function GearsSim(spec, design) {
    this.spec = spec; this.design = design; this.ev = gearsEval(spec, design);
    this.t = 0; this.running = true; this.result = null; this.diag = null; this.trail = []; this.events = [];
    var sc = spec.scene;
    this.duration = this.ev.ok ? Math.min(this.ev.seconds, 6) : (this.ev.code === 'slow' ? 4 : 2.5);
    this.simSpeed = this.ev.ok ? this.ev.seconds / this.duration : 1; // shown faster when the real lift is slow
    this.liftHeight = sc.lift.height;
  }

  GearsSim.prototype.snapshot = function () {
    var e = this.ev, sc = this.spec.scene, motorAngle = (this.t * this.simSpeed * sc.motor.rpm / 60) * 360;
    var liftAngle = e.lift ? motorAngle / e.ratio * e.direction : 0;
    var idlerAngle = e.idler ? -motorAngle * sc.motor.teeth / e.idler : 0;
    var h = 0;
    if (e.code === 'success') h = clamp(this.t * this.simSpeed / e.seconds, 0, 1) * this.liftHeight;
    else if (e.code === 'slow') h = clamp(this.t * this.simSpeed / e.seconds, 0, 1) * this.liftHeight;
    else if (e.code === 'stall') h = Math.max(0, Math.sin(this.t * 6) * 0.6);
    return { t: this.t, running: this.running, result: this.result, motorAngle: motorAngle, idlerAngle: idlerAngle, liftAngle: e.code === 'stall' ? liftAngle * 0.05 : liftAngle, liftHeight: h, eval: e };
  };

  GearsSim.prototype.step = function () {
    if (!this.running) return false;
    this.t += DT;
    if (this.t >= this.duration) return this.finish(this.ev.code);
    return true;
  };

  GearsSim.prototype.finish = function (code) {
    var e = this.ev, sc = this.spec.scene;
    this.running = false; this.result = code;
    if (code === 'success') this.diag = { code: 'success', text: 'Lifted! Ratio ' + e.ratio + ':1, so the lift turned at ' + Math.round(e.rpm) + ' rpm and took ' + Math.round(e.seconds) + ' seconds.' };
    else if (code === 'nolift') this.diag = { code: 'nolift', text: 'There is no gear on the lift axle, so the motor spun on its own.', hint: 'Tap the lift slot and pick a gear.' };
    else if (code === 'stall') this.diag = { code: 'stall', text: 'The lift stalled. Ratio ' + e.ratio + ':1 is not enough turning force for this crate (it needs ' + sc.lift.needRatio + ':1 or more).', hint: 'A bigger gear on the lift turns slower but pulls harder.' };
    else this.diag = { code: 'slow', text: 'Strong but slow: ratio ' + e.ratio + ':1 would take ' + Math.round(e.seconds) + ' seconds, and the crate must arrive within ' + sc.lift.timeLimit + '.', hint: 'Try a slightly smaller lift gear: less force, more speed.' };
    return false;
  };

  /* --------------------------------------------------------- circuit sim */

  var SIDES = ['N', 'E', 'S', 'W'];
  var OPP = { N: 'S', S: 'N', E: 'W', W: 'E' };
  var STEP = { N: [-1, 0], E: [0, 1], S: [1, 0], W: [0, -1] };

  function tilePorts(tile) {
    // rotation 0..3 rotates ports clockwise
    var base;
    if (tile.type === 'wire' || tile.type === 'switch') base = ['E', 'W'];
    else if (tile.type === 'corner') base = ['N', 'E'];
    else if (tile.type === 'fixed') base = tile.axis === 'V' ? ['N', 'S'] : ['E', 'W'];
    else return [];
    return base.map(function (s) { return SIDES[(SIDES.indexOf(s) + (tile.rot || 0)) % 4]; });
  }

  function circuitGrid(spec, design) {
    var sc = spec.scene, grid = {};
    sc.fixed.forEach(function (f) { grid[f.r + ',' + f.c] = { type: 'fixed', kind: f.kind, r: f.r, c: f.c, axis: (design.fixedAxis || {})[f.r + ',' + f.c] || 'H' }; });
    (design.tiles || []).forEach(function (t) { if (!grid[t.r + ',' + t.c]) grid[t.r + ',' + t.c] = { type: t.type, rot: t.rot || 0, closed: !!t.closed, r: t.r, c: t.c }; });
    return grid;
  }

  function circuitEval(spec, design) {
    var sc = spec.scene, grid = circuitGrid(spec, design);
    var battery = sc.fixed.filter(function (f) { return f.kind === 'battery'; })[0];
    var lamps = sc.fixed.filter(function (f) { return f.kind === 'lamp'; });
    var bt = grid[battery.r + ',' + battery.c], ports = tilePorts(bt);
    var visited = [], r = battery.r, c = battery.c, side = ports[0], steps = 0, openAt = null, openSwitch = null, sawSwitch = false;
    while (steps++ < 100) {
      var nr = r + STEP[side][0], nc = c + STEP[side][1], key = nr + ',' + nc, t = grid[key];
      if (nr < 0 || nc < 0 || nr >= sc.rows || nc >= sc.cols || !t) { openAt = { r: r, c: c, side: side }; break; }
      var tp = tilePorts(t), enter = OPP[side];
      if (tp.indexOf(enter) === -1) { openAt = { r: nr, c: nc, side: enter, kind: 'noport' }; break; }
      if (t.type === 'fixed' && t.kind === 'battery') { if (tp[0] === enter || tp[1] === enter) return finishEval(true); break; }
      visited.push(key);
      if (t.type === 'switch') { sawSwitch = true; if (!t.closed) { openSwitch = { r: nr, c: nc }; break; } }
      side = tp[0] === enter ? tp[1] : tp[0];
      r = nr; c = nc;
    }
    return finishEval(false);

    function finishEval(closed) {
      var lampsHit = lamps.filter(function (l) { return visited.indexOf(l.r + ',' + l.c) !== -1; }).length;
      var res = { closed: closed, lampsHit: lampsHit, lampsTotal: lamps.length, sawSwitch: sawSwitch, openAt: openAt, openSwitch: openSwitch, tiles: (design.tiles || []).length, loopLength: visited.length };
      if (openSwitch) res.code = 'switch_open';
      else if (!closed) res.code = 'open';
      else if (lampsHit < lamps.length) res.code = 'no_lamp';
      else if (sc.needSwitch && !sawSwitch) res.code = 'no_switch';
      else res.code = 'success';
      res.ok = res.code === 'success';
      return res;
    }
  }

  function CircuitSim(spec, design) {
    this.spec = spec; this.design = design; this.ev = circuitEval(spec, design);
    this.t = 0; this.running = true; this.result = null; this.diag = null; this.trail = []; this.events = [];
  }
  CircuitSim.prototype.snapshot = function () { return { t: this.t, running: this.running, result: this.result, eval: this.ev, glow: clamp(this.t / 1.2, 0, 1) }; };
  CircuitSim.prototype.step = function () {
    if (!this.running) return false;
    this.t += DT;
    if (this.t >= 1.4) return this.finish(this.ev.code);
    return true;
  };
  CircuitSim.prototype.finish = function (code) {
    var e = this.ev;
    this.running = false; this.result = code;
    if (code === 'success') this.diag = { code: 'success', text: 'The loop is closed: current flows from the battery through ' + (e.lampsTotal > 1 ? 'both lamps' : 'the lamp') + ' and back. The lab lights up!' };
    else if (code === 'switch_open') this.diag = { code: 'switch_open', text: 'The switch at row ' + (e.openSwitch.r + 1) + ', column ' + (e.openSwitch.c + 1) + ' is open, so the loop has a gap there.', hint: 'Tap the switch to close it, then run again.' };
    else if (code === 'open') this.diag = { code: 'open', text: 'The path stops at row ' + (e.openAt.r + 1) + ', column ' + (e.openAt.c + 1) + ' on its ' + sideName(e.openAt.side) + ' side. Current cannot cross a gap.', hint: 'Add or rotate a tile so both neighbours connect.' };
    else if (code === 'no_lamp') this.diag = { code: 'no_lamp', text: 'The loop closed, but it skipped ' + (e.lampsTotal - e.lampsHit) + ' lamp' + (e.lampsTotal - e.lampsHit > 1 ? 's' : '') + '. Current only lights what it flows through.', hint: 'Route the loop through every lamp.' };
    else this.diag = { code: 'no_switch', text: 'The lamp lit, but there is no switch in the loop, so the lab could never be turned off.', hint: 'Swap one wire for the switch.' };
    return false;
  };
  function sideName(s) { return { N: 'top', S: 'bottom', E: 'right', W: 'left' }[s] || s; }

  /* ------------------------------------------------------------ code sim */

  var TURN_L = { N: 'W', W: 'S', S: 'E', E: 'N' };
  var TURN_R = { N: 'E', E: 'S', S: 'W', W: 'N' };
  var MAX_STEPS = 200;

  function countBlocks(program) {
    return program.reduce(function (n, b) { return n + 1 + (b.t === 'loop' ? countBlocks(b.body) : 0); }, 0);
  }
  function hasNested(program, depth) {
    depth = depth || 0;
    for (var i = 0; i < program.length; i++) {
      if (program[i].t === 'loop') { if (depth >= 1) return true; if (hasNested(program[i].body, depth + 1)) return true; }
    }
    return false;
  }
  function hasLoop(program) { return program.some(function (b) { return b.t === 'loop'; }); }
  function usesBlock(program, kind) {
    return program.some(function (b) { return b.t === kind || (b.t === 'loop' && usesBlock(b.body, kind)); });
  }
  /* Flatten with a path to each block so the UI can highlight the failing one. */
  function flatten(program, out, path) {
    out = out || []; path = path || [];
    for (var i = 0; i < program.length; i++) {
      var b = program[i], p = path.concat([i]);
      if (b.t === 'loop') { for (var k = 0; k < b.n; k++) { if (flatten(b.body, out, p) === null) return null; } }
      else out.push({ t: b.t, path: p });
      if (out.length > MAX_STEPS) return null;
    }
    return out;
  }

  function codeRun(spec, program) {
    var sc = spec.scene, steps = flatten(program);
    var stations = {}, total = 0;
    sc.map.forEach(function (row, r) { row.split('').forEach(function (ch, c) { if (ch === 'S') { stations[r + ',' + c] = false; total++; } }); });
    var r = sc.start.r, c = sc.start.c, dir = sc.start.dir, frames = [{ r: r, c: c, dir: dir }], delivered = 0, wasted = 0, events = [];
    if (steps === null) return { result: 'toolong', frames: frames, steps: [], events: events, delivered: 0, total: total };
    if (!steps.length) return { result: 'empty', frames: frames, steps: [], events: events, delivered: 0, total: total };
    for (var i = 0; i < steps.length; i++) {
      var s = steps[i];
      if (s.t === 'F') {
        var nr = r + STEP[dir][0], nc = c + STEP[dir][1];
        if (nr < 0 || nc < 0 || nr >= sc.rows || nc >= sc.cols || sc.map[nr][nc] === '#') {
          frames.push({ r: r, c: c, dir: dir, bump: true });
          return { result: 'bump', frames: frames, steps: steps, at: i, events: events, delivered: delivered, total: total, path: s.path };
        }
        r = nr; c = nc; frames.push({ r: r, c: c, dir: dir });
      } else if (s.t === 'L') { dir = TURN_L[dir]; frames.push({ r: r, c: c, dir: dir }); }
      else if (s.t === 'R') { dir = TURN_R[dir]; frames.push({ r: r, c: c, dir: dir }); }
      else if (s.t === 'D') {
        var key = r + ',' + c;
        if (stations[key] === false) { stations[key] = true; delivered++; frames.push({ r: r, c: c, dir: dir, deliver: true }); events.push({ type: 'delivered', step: i }); }
        else { wasted++; frames.push({ r: r, c: c, dir: dir, wasted: true }); events.push({ type: 'wasted', step: i, path: s.path }); }
      }
      if (delivered === total) return { result: 'success', frames: frames, steps: steps, events: events, delivered: delivered, total: total, wasted: wasted, blocks: countBlocks(program) };
    }
    return { result: 'undelivered', frames: frames, steps: steps, events: events, delivered: delivered, total: total, wasted: wasted };
  }

  function CodeSim(spec, design) {
    this.spec = spec; this.design = design; this.run = codeRun(spec, design.program || []);
    this.t = 0; this.running = true; this.result = null; this.diag = null; this.trail = []; this.events = this.run.events;
    this.frameTime = 0.42; this.frame = 0;
    var self = this;
    this.run.frames.forEach(function (f) { self.trail.push([f.c, f.r]); });
  }
  CodeSim.prototype.snapshot = function () {
    var i = Math.min(this.run.frames.length - 1, Math.floor(this.t / this.frameTime));
    return { t: this.t, running: this.running, result: this.result, frame: this.run.frames[i], frameIndex: i, run: this.run };
  };
  CodeSim.prototype.step = function () {
    if (!this.running) return false;
    this.t += DT;
    if (this.t >= this.run.frames.length * this.frameTime) return this.finish(this.run.result);
    return true;
  };
  CodeSim.prototype.finish = function (code) {
    var run = this.run;
    this.running = false; this.result = code;
    var wasted = run.wasted ? ' It also tried to deliver ' + run.wasted + ' time' + (run.wasted > 1 ? 's' : '') + ' where there was no station.' : '';
    if (code === 'success') this.diag = { code: 'success', text: 'Every station got its delivery in ' + run.blocks + ' block' + (run.blocks === 1 ? '' : 's') + '.' + wasted };
    else if (code === 'bump') this.diag = { code: 'bump', text: 'Bonk! At step ' + (run.at + 1) + ' the rover tried to move into ' + (run.frames[run.frames.length - 1] && 'a wall or the edge') + '.', hint: 'Find that block in your program and change the turn before it.', path: run.path };
    else if (code === 'empty') this.diag = { code: 'empty', text: 'The program is empty, so the rover stayed put.', hint: 'Tap the blocks to build a route.' };
    else if (code === 'toolong') this.diag = { code: 'toolong', text: 'That program runs over ' + MAX_STEPS + ' steps.', hint: 'Use smaller repeat numbers.' };
    else this.diag = { code: 'undelivered', text: 'The program finished with ' + (run.total - run.delivered) + ' of ' + run.total + ' station' + (run.total > 1 ? 's' : '') + ' still waiting.' + wasted, hint: 'Drive to each station marker and use a Deliver block there.' };
    return false;
  };

  /* ------------------------------------------------------- public API */

  function createSim(spec, design) {
    design = normalizeDesign(spec, clone(design));
    switch (spec.mode) {
      case 'physics': return new PhysicsSim(spec, design);
      case 'bridge': return new BridgeSim(spec, design);
      case 'balance': return new BalanceSim(spec, design);
      case 'gears': return new GearsSim(spec, design);
      case 'circuit': return new CircuitSim(spec, design);
      case 'code': return new CodeSim(spec, design);
    }
    throw new Error('Unknown mode ' + spec.mode);
  }

  function runToEnd(spec, design) {
    var sim = createSim(spec, design), guard = 0;
    while (sim.step() && guard++ < 200000) { /* run */ }
    return { ok: sim.result === 'success', result: sim.result, diag: sim.diag, t: sim.t, trail: sim.trail, events: sim.events, sim: sim };
  }

  /** Design problems that make a run pointless (tray overflow etc.). */
  function validateDesign(spec, design) {
    var issues = [];
    if (design.parts) {
      var seen = {};
      design.parts.forEach(function (p) { var k = p.type + ':' + (p.len || ''); seen[k] = (seen[k] || 0) + 1; });
      Object.keys(seen).forEach(function (k) {
        var bits = k.split(':'), lim = trayLimit(spec, bits[0], bits[1] ? Number(bits[1]) : null);
        if (seen[k] > lim) issues.push('Too many ' + bits[0] + 's: the tray has ' + lim + '.');
      });
    }
    return { ok: !issues.length, issues: issues };
  }

  /** Evaluate one optional challenge rule against a successful run. */
  function checkRule(spec, design, run, rule, extra) {
    extra = extra || {};
    var parts = design.parts || [];
    switch (rule.type) {
      case 'maxTime': return run.ok && run.t <= rule.s;
      case 'maxParts': return run.ok && parts.length <= rule.n;
      case 'partAngleMax': return run.ok && parts.some(function (p) { return p.type === rule.part; }) && parts.every(function (p) { return p.type !== rule.part || Math.abs(p.angle || 0) <= rule.deg; });
      case 'noPartLen': return run.ok && !parts.some(function (p) { return p.type === rule.part && p.len === rule.len; });
      case 'minPartType': return run.ok && parts.filter(function (p) { return p.type === rule.part; }).length >= rule.n;
      case 'launcherPowerMax': return run.ok && design.launcher && design.launcher.power <= rule.p;
      case 'launcherAngleMin': return run.ok && design.launcher && design.launcher.angle >= rule.a;
      case 'heavyOnEdge': {
        if (!run.ok) return false;
        var max = Math.max.apply(null, spec.scene.crates), edge = Math.max.apply(null, spec.scene.slots);
        return (design.placements || []).some(function (p) { return spec.scene.crates[p.crate] === max && Math.abs(p.slot) === edge; });
      }
      case 'centreEmpty': return run.ok && !(design.placements || []).some(function (p) { return p.slot === 0; });
      case 'noIdler': return run.ok && !design.slots[0];
      case 'liftSameDirection': return run.ok && run.sim.ev.direction === 1;
      case 'maxTiles': return run.ok && (design.tiles || []).length <= rule.n;
      case 'minTiles': return run.ok && (design.tiles || []).length >= rule.n;
      case 'maxBlocks': return run.ok && countBlocks(design.program || []) <= rule.n;
      case 'noBlock': return run.ok && !usesBlock(design.program || [], rule.block);
      case 'nestedLoop': return run.ok && hasNested(design.program || []);
      case 'usesLoop': return run.ok && hasLoop(design.program || []);
      case 'noFailedRuns': return run.ok && extra.failedRuns === 0;
    }
    return false;
  }

  return {
    DT: DT, GRAVITY: GRAVITY, MAX_TIME: MAX_TIME, GROUND: GROUND,
    resolve: resolve, newDesign: newDesign, normalizeDesign: normalizeDesign,
    createSim: createSim, runToEnd: runToEnd, validateDesign: validateDesign, checkRule: checkRule,
    trayLimit: trayLimit, trayUsed: trayUsed, partSegments: partSegments, fanZone: fanZone,
    beamSupports: beamSupports, gearsEval: gearsEval, circuitEval: circuitEval, circuitGrid: circuitGrid, tilePorts: tilePorts,
    codeRun: codeRun, countBlocks: countBlocks, flatten: flatten, inRect: inRect, clone: clone
  };
});
