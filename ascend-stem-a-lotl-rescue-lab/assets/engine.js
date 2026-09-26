/* STEM-a-lotl: Rescue Lab — pellet physics.
 *
 * Every puzzle is the same journey: a food pellet leaves the dispenser and has
 * to reach Lucas the axolotl, who eats it. The player places ramps, bouncers,
 * fans and stone blocks; the world adds wind, bees, water, currents, fish,
 * rocks and waterfalls.
 *
 * Pure and deterministic: no DOM, no Date, no Math.random. A fixed time step,
 * bounded speeds, integer-snapped designs and obstacles that move as a
 * function of simulation time mean the same design always gives the same run.
 *
 * The pellet rolls without slipping on slopes (acceleration 5/7 g sin θ, the
 * textbook result for a solid ball), bounces with per-material restitution,
 * and in water feels buoyancy, drag and the current.
 */
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) module.exports = factory(require('./levels.js'));
  else root.AscendRLEngine = factory(root.AscendRLLevels);
})(typeof self !== 'undefined' ? self : this, function (Levels) {
  'use strict';

  var DT = 1 / 120;           // fixed simulation step, seconds
  var GRAVITY = 60;           // scene units / s^2 (scene is 100 x 60 units)
  var MAX_SPEED = 110;        // bounded velocity keeps every run stable
  var MAX_TIME = 20;          // seconds before a run is called a timeout
  var SETTLE_SPEED = 1.6;
  var SETTLE_TIME = 0.6;
  var PELLET_R = 2.2;
  var ROLL_FACTOR = 5 / 7;    // solid ball rolling down a slope
  var WATER_LIFT = 0.8;       // buoyancy cancels 80% of gravity in water
  var WATER_DRAG = 2.2;       // per second
  var FALLS_PULL = 150;       // extra downward pull inside a waterfall
  var FAN = { reach: 26, width: 14, strength: 85 };
  var GROUND = Levels.GROUND;

  /* Materials: restitution e (bounciness) and rolling resistance f (per second). */
  var MAT = {
    grass: { e: 0.3, f: 2.5 }, dirt: { e: 0.3, f: 2.5 }, sand: { e: 0.15, f: 4 }, rock: { e: 0.35, f: 1.2 },
    ice: { e: 0.1, f: 0.03 }, wood: { e: 0.15, f: 0.15 }, bouncer: { e: 0.92, f: 0.05 }, stone: { e: 0.4, f: 0.8 }
  };

  var PART_SPEC = {
    ramp: { mat: 'wood', label: 'Ramp', angled: true },
    bouncer: { mat: 'bouncer', label: 'Bouncer', angled: true, len: 12 },
    block: { mat: 'stone', label: 'Stone block', angled: true, len: 10 },
    fan: { label: 'Fan', dir: true }
  };

  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
  function deg2rad(d) { return d * Math.PI / 180; }
  function snapDeg(d) { return Math.round(d / 5) * 5; }
  function inRect(x, y, z) { return x >= z.x && x <= z.x + z.w && y >= z.y && y <= z.y + z.h; }
  var TAU = Math.PI * 2;

  /* ----------------------------------------------------------- specs */

  function resolve(level) {
    return {
      levelId: level.id, world: level.world, scene: clone(level.scene), tray: clone(level.tray || []),
      initial: clone(level.initial || []), challenges: level.challenges, name: level.name
    };
  }

  function newDesign(spec) { return { parts: clone(spec.initial) }; }

  /** Snap a design to the grid so runs are reproducible after any drag. */
  function normalizeDesign(spec, design) {
    (design.parts || []).forEach(function (p) {
      p.x = Math.round(p.x); p.y = Math.round(p.y);
      if (p.angle != null) p.angle = clamp(snapDeg(p.angle), -75, 75);
    });
    return design;
  }

  function trayLimit(spec, type, len) {
    var n = 0;
    spec.tray.forEach(function (t) { if (t.type === type && (len == null || t.len === len)) n += t.count; });
    return n;
  }
  function trayUsed(design, type, len) {
    return (design.parts || []).filter(function (p) { return p.type === type && (len == null || p.len === len); }).length;
  }

  /* ---------------------------------------------------------- geometry */

  function partSegments(p) {
    if (p.type === 'fan') return [];
    var a = deg2rad(p.angle || 0), h = (p.len || PART_SPEC[p.type].len || 20) / 2;
    return [{ x1: p.x - Math.cos(a) * h, y1: p.y - Math.sin(a) * h, x2: p.x + Math.cos(a) * h, y2: p.y + Math.sin(a) * h, mat: PART_SPEC[p.type].mat, kind: p.type, part: p }];
  }

  function fanZone(f) {
    var dirs = { E: [1, 0], W: [-1, 0], N: [0, -1], S: [0, 1] };
    var d = dirs[f.dir || 'E'];
    if (d[0]) return { x: d[0] > 0 ? f.x : f.x - FAN.reach, y: f.y - FAN.width / 2, w: FAN.reach, h: FAN.width, fx: d[0] * FAN.strength, fy: 0 };
    return { x: f.x - FAN.width / 2, y: d[1] > 0 ? f.y : f.y - FAN.reach, w: FAN.width, h: FAN.reach, fx: 0, fy: d[1] * FAN.strength };
  }

  /** Where a bee is at time t (a gentle figure-of-eight), and its velocity. */
  function bugAt(b, t) {
    var w = TAU / b.period, ph = b.phase || 0;
    return {
      x: b.x + b.ax * Math.sin(w * t + ph), y: b.y + b.ay * Math.sin(2 * (w * t + ph)),
      vx: b.ax * w * Math.cos(w * t + ph), vy: b.ay * 2 * w * Math.cos(2 * (w * t + ph)), r: b.r || 2.4
    };
  }
  /** Where a fish is at time t (swims back and forth), its velocity and facing. */
  function fishAt(f, t) {
    var w = TAU / f.period, ph = f.phase || 0, vx = f.range * w * Math.cos(w * t + ph);
    return { x: f.x + f.range * Math.sin(w * t + ph), y: f.y + (f.bob || 0.8) * Math.sin(3 * w * t), vx: vx, vy: 0, r: f.r || 2.6, face: vx >= 0 ? 1 : -1 };
  }
  /** Gusty wind: strength swings between 70% and 100% of its peak. */
  function windAt(z, t) {
    var g = z.gust ? 0.85 + 0.15 * Math.sin(TAU * t / z.gust) : 1;
    return { fx: (z.fx || 0) * g, fy: (z.fy || 0) * g };
  }
  /** Lucas eats anything that touches this circle slowly enough. */
  function mouthZone(l) { return { x: l.x + (l.face || -1) * 1.5, y: l.y - 5.5, r: 5.5 }; }

  /* Circle vs segment: push out and reflect. */
  function collideSeg(b, s, e, friction) {
    var dx = s.x2 - s.x1, dy = s.y2 - s.y1, l2 = dx * dx + dy * dy;
    var t = l2 ? clamp(((b.x - s.x1) * dx + (b.y - s.y1) * dy) / l2, 0, 1) : 0;
    var px = s.x1 + dx * t, py = s.y1 + dy * t;
    var nx = b.x - px, ny = b.y - py, d = Math.sqrt(nx * nx + ny * ny);
    if (d >= b.r || d === 0) return null;
    nx /= d; ny /= d;
    b.x += nx * (b.r - d); b.y += ny * (b.r - d);
    var vn = b.vx * nx + b.vy * ny;
    if (vn < 0) {
      var bounce = -vn > 6 ? e : 0; // tiny impacts just settle instead of jittering
      b.vx -= (1 + bounce) * vn * nx; b.vy -= (1 + bounce) * vn * ny;
      var tx = -ny, ty = nx, vt = b.vx * tx + b.vy * ty, loss = Math.min(1, friction);
      b.vx -= vt * loss * tx; b.vy -= vt * loss * ty;
    }
    return { nx: nx, ny: ny, seg: s, vn: vn };
  }

  /* Circle vs moving circle (bees, fish, rocks), in the obstacle's frame. */
  function collideCircle(b, o, e) {
    var dx = b.x - o.x, dy = b.y - o.y, d = Math.sqrt(dx * dx + dy * dy), min = b.r + o.r;
    if (d >= min || d === 0) return null;
    var nx = dx / d, ny = dy / d;
    b.x += nx * (min - d); b.y += ny * (min - d);
    var rvx = b.vx - (o.vx || 0), rvy = b.vy - (o.vy || 0), vn = rvx * nx + rvy * ny;
    if (vn < 0) { b.vx -= (1 + e) * vn * nx; b.vy -= (1 + e) * vn * ny; }
    return { nx: nx, ny: ny, vn: vn };
  }

  /* ------------------------------------------------------------- sim */

  function Sim(spec, design) {
    var sc = spec.scene, self = this;
    this.spec = spec; this.design = design;
    this.segs = clone(sc.solids || []);
    (design.parts || []).forEach(function (p) { partSegments(p).forEach(function (s) { self.segs.push(s); }); });
    this.fans = (design.parts || []).filter(function (p) { return p.type === 'fan'; }).map(fanZone);
    this.rocks = clone(sc.rocks || []);
    this.body = { x: sc.spawn.x, y: sc.spawn.y, vx: 0, vy: 0, r: PELLET_R, angle: 0, spin: 0 };
    this.mouth = mouthZone(sc.lucas);
    this.eatSpeed = sc.eatSpeed || 70;
    this.t = 0; this.steps = 0; this.settled = 0; this.running = true; this.result = null; this.diag = null;
    this.trail = []; this.events = []; this.seen = {}; this.contactN = null; this.air = 0; this.maxAir = 0; this.topSpeed = 0;
    this.inWater = false; this.lastBump = null; this.nearMouth = false;
  }

  Sim.prototype.note = function (type, kind, once) {
    var key = type + ':' + (kind || '');
    if (once !== false && this.seen[key]) return;
    this.seen[key] = (this.seen[key] || 0) + 1;
    this.events.push({ t: Math.round(this.t * 100) / 100, type: type, kind: kind || null, x: Math.round(this.body.x), y: Math.round(this.body.y) });
  };

  Sim.prototype.snapshot = function () {
    var b = this.body;
    return { body: { x: b.x, y: b.y, vx: b.vx, vy: b.vy, r: b.r, angle: b.angle }, t: this.t, running: this.running, result: this.result };
  };

  Sim.prototype.step = function () {
    if (!this.running) return false;
    var b = this.body, sc = this.spec.scene, self = this, t = this.t;

    // 1. forces: gravity (rolling when in contact), wind, fans, water, waterfalls
    var gx = 0, gy = GRAVITY;
    if (this.contactN) {
      var n = this.contactN, gn = gx * n.nx + gy * n.ny;
      var tx = gx - gn * n.nx, ty = gy - gn * n.ny;
      gx = gn * n.nx + ROLL_FACTOR * tx; gy = gn * n.ny + ROLL_FACTOR * ty;
    }
    var water = null;
    (sc.water || []).forEach(function (w) { if (inRect(b.x, b.y, w)) water = w; });
    if (water) {
      gy -= GRAVITY * WATER_LIFT;
      gx += water.current || 0;
      if (!this.inWater) { this.note('splash', null, false); if (Math.abs(water.current || 0) > 0) this.note('current'); }
      this.inWater = true;
    } else this.inWater = false;
    (sc.bubbles || []).forEach(function (z) { if (inRect(b.x, b.y, z)) { gy += z.fy; self.note('bubbles'); } });
    (sc.falls || []).forEach(function (z) {
      if (inRect(b.x, b.y, z)) { gy += FALLS_PULL; b.vx *= Math.exp(-3 * DT); self.note('falls'); }
    });
    (sc.wind || []).forEach(function (z) {
      if (inRect(b.x, b.y, z)) { var w = windAt(z, t); gx += w.fx; gy += w.fy; self.note('wind'); }
    });
    this.fans.forEach(function (z) { if (inRect(b.x, b.y, z)) { gx += z.fx; gy += z.fy; self.note('fan'); } });
    b.vx += gx * DT; b.vy += gy * DT;
    if (water) { var k = Math.exp(-WATER_DRAG * DT); b.vx *= k; b.vy *= k; }
    var sp = Math.sqrt(b.vx * b.vx + b.vy * b.vy);
    if (sp > MAX_SPEED) { b.vx *= MAX_SPEED / sp; b.vy *= MAX_SPEED / sp; }
    b.x += b.vx * DT; b.y += b.vy * DT;

    // 2. collisions with ground, ledges and the player's parts
    var contact = null;
    for (var it = 0; it < 2; it++) {
      for (var i = 0; i < this.segs.length; i++) {
        var s = this.segs[i], m = MAT[s.mat] || MAT.grass;
        var c = collideSeg(b, s, m.e, it === 0 ? m.f * DT : 0);
        if (c) {
          if (!contact || c.ny < contact.ny) contact = c; // prefer the floor-most contact for rolling
          if (s.part) this.note('touch', s.kind);
          if (c.vn < -18) this.note(s.kind === 'bouncer' ? 'boing' : 'bounce', s.kind, s.kind === 'bouncer' ? false : true);
        }
      }
    }
    // rocks and moving obstacles
    this.rocks.forEach(function (r) { if (collideCircle(b, { x: r.x, y: r.y, r: r.r, vx: 0, vy: 0 }, 0.45)) self.note('bump', 'rock'); });
    (sc.bugs || []).forEach(function (bg) { var o = bugAt(bg, t); if (collideCircle(b, o, 0.8)) { self.note('bump', 'bee', false); self.lastBump = 'bee'; } });
    (sc.fish || []).forEach(function (f) { var o = fishAt(f, t); if (collideCircle(b, o, 0.6)) { self.note('bump', 'fish', false); self.lastBump = 'fish'; } });

    // only a floor-like contact (normal pointing up) counts for rolling
    this.contactN = contact && contact.ny < -0.2 ? contact : null;
    var vt = 0;
    if (this.contactN) { vt = b.vx * -this.contactN.ny + b.vy * this.contactN.nx; b.spin = vt / b.r; this.air = 0; }
    else { this.air += DT; if (this.air > this.maxAir) this.maxAir = this.air; }
    b.angle += b.spin * DT;

    this.t += DT; this.steps++;
    if (this.steps % 4 === 0) this.trail.push([Math.round(b.x * 10) / 10, Math.round(b.y * 10) / 10]);
    sp = Math.sqrt(b.vx * b.vx + b.vy * b.vy);
    if (sp > this.topSpeed) this.topSpeed = sp;

    // 3. did Lucas get it?
    var mz = this.mouth, dm = Math.sqrt((b.x - mz.x) * (b.x - mz.x) + (b.y - mz.y) * (b.y - mz.y));
    this.nearMouth = dm < mz.r + 12;
    if (dm < mz.r + b.r) {
      if (sp <= this.eatSpeed) return this.finish('success');
      this.note('too_fast');
    }

    this.settled = sp < SETTLE_SPEED ? this.settled + DT : 0;
    if (b.y > 72 || b.x < -12 || b.x > 112) return this.finish('lost');
    if (this.settled >= SETTLE_TIME) return this.finish('settled');
    if (this.t >= MAX_TIME) return this.finish('timeout');
    return true;
  };

  Sim.prototype.finish = function (code) {
    this.running = false;
    this.result = code;
    if (code === 'success') this.note('eaten', null, false);
    this.diag = diagnose(this, code);
    return false;
  };

  function diagnose(sim, code) {
    var b = sim.body, l = sim.spec.scene.lucas, dx = Math.round(b.x - l.x), seen = sim.seen;
    var usedPart = Object.keys(seen).some(function (k) { return k.indexOf('touch:') === 0; });
    if (code === 'success') return { code: 'success', text: 'Lucas ate the pellet ' + sim.t.toFixed(1) + ' seconds after it left the dispenser.' };
    if (seen['too_fast:']) return { code: 'too_fast', text: 'The pellet reached Lucas but zoomed past too fast for him to catch.', hint: 'Slow it down: a gentler slope, a shorter drop, or a bump on the way.' };
    if (code === 'lost') {
      if (seen['falls:']) return { code: 'swept', text: 'The waterfall pulled the pellet down and carried it away.', hint: 'Keep the pellet out of the falling water, or let it fall where Lucas is.' };
      if (b.y > 72) return { code: 'fell', text: 'The pellet fell off the edge at x = ' + Math.round(b.x) + '.', hint: 'Give it something to roll on across the gap.' };
      return { code: 'lost', text: 'The pellet rolled out of the level on the ' + (b.x < 0 ? 'left' : 'right') + '.', hint: 'Aim it back toward Lucas, or add something to stop it.' };
    }
    if (code === 'timeout') return { code: 'timeout', text: 'The pellet was still moving after ' + MAX_TIME + ' seconds and never reached Lucas.', hint: 'Something keeps it bouncing or circling. Try a calmer path.' };
    if (sim.lastBump && sim.events.filter(function (e) { return e.type === 'bump' && e.kind === sim.lastBump; }).length) {
      var who = sim.lastBump === 'bee' ? 'A bee' : 'A fish';
      if (Math.abs(dx) > 8) return { code: 'bumped', text: who + ' knocked the pellet off course. It stopped ' + Math.abs(dx) + ' units ' + (dx < 0 ? 'before' : 'past') + ' Lucas.', hint: 'Moving things are in different places at different times. Change the path or the timing.' };
    }
    var onPart = sim.contactN && sim.contactN.seg && sim.contactN.seg.part;
    if (onPart) return { code: 'stuck', text: 'The pellet stopped on your ' + PART_SPEC[onPart.type].label.toLowerCase() + ' at x = ' + Math.round(b.x) + '.', hint: 'Tilt it more so gravity can pull the pellet along it.' };
    if (!usedPart && (sim.design.parts || []).length) return { code: 'missed_part', text: 'The pellet never touched your parts. It dropped and stopped at x = ' + Math.round(b.x) + '.', hint: 'Put a part under the dispenser.' };
    if (dx * (l.face || -1) > 0 && Math.abs(dx) > 3) return { code: 'overshot', text: 'The pellet went past Lucas and stopped ' + Math.abs(dx) + ' units behind him.', hint: 'It had too much speed or the wrong angle. Try a gentler slope.' };
    return { code: 'short', text: 'The pellet stopped ' + Math.abs(dx) + ' units away from Lucas.', hint: 'It needs a little more speed or a better direction.' };
  }

  /* --------------------------------------------------- journey & lesson */

  var STEP_WORDS = {
    'touch:ramp': 'rolled down your ramp', 'boing:bouncer': 'sprang off the bouncer', 'touch:block': 'bumped your stone block',
    'fan:': 'rode the fan’s breeze', 'wind:': 'was pushed by the wind', 'splash:': 'splashed into the water',
    'current:': 'drifted with the current', 'bubbles:': 'was lifted by rising bubbles', 'falls:': 'dropped through the waterfall',
    'bump:bee': 'got knocked by a bee', 'bump:fish': 'bumped a fish', 'bump:rock': 'bounced off a rock', 'too_fast:': 'zoomed past Lucas once'
  };
  var SCIENCE = {
    'touch:ramp': 'Gravity pulls down, and a slope turns part of that pull into speed along the ramp.',
    'boing:bouncer': 'A bouncer squashes and springs back, giving the pellet’s energy back to it.',
    'fan:': 'A fan pushes air, and the moving air pushes the pellet. A sideways push bends its path.',
    'wind:': 'Wind is moving air. While gravity pulls down, the wind pushes sideways, so the path curves.',
    'splash:': 'Water holds the pellet up a little (buoyancy) and slows it (drag), so it sinks gently.',
    'current:': 'A current is moving water. Anything in it gets carried along.',
    'bubbles:': 'Rising bubbles push water upward, and the water lifts the pellet.',
    'falls:': 'A waterfall is water pulled down by gravity, and it drags whatever it touches down too.',
    'bump:bee': 'Moving obstacles are in different places at different moments, so timing matters.',
    'bump:fish': 'When two things collide, both change direction. A moving fish can push the pellet.',
    'bump:rock': 'A round rock sends the pellet off at an angle that depends on where it hits.',
    'too_fast:': 'Fast things are hard to catch. Slowing down gave Lucas time to eat.'
  };

  /** Build a short recap of what actually happened on the way to Lucas. */
  function journey(sim) {
    var steps = [], notes = [], seen = {};
    sim.events.forEach(function (e) {
      var k = e.type + ':' + (e.kind || '');
      if (seen[k] || !STEP_WORDS[k]) return;
      seen[k] = true; steps.push(STEP_WORDS[k]);
      if (SCIENCE[k]) notes.push(SCIENCE[k]);
    });
    var text = steps.length ? 'The pellet ' + listJoin(steps) : 'The pellet dropped straight down';
    text += sim.result === 'success' ? ', and Lucas gobbled it up after ' + sim.t.toFixed(1) + ' seconds.' : '.';
    return { text: text, notes: notes.slice(0, 3), time: sim.t, topSpeed: Math.round(sim.topSpeed), airtime: Math.round(sim.maxAir * 10) / 10 };
  }
  function listJoin(a) { return a.length === 1 ? a[0] : a.slice(0, -1).join(', ') + ' and ' + a[a.length - 1]; }

  /* ------------------------------------------------------- public API */

  function createSim(spec, design) { return new Sim(spec, normalizeDesign(spec, clone(design))); }

  function runToEnd(spec, design) {
    var sim = createSim(spec, design), guard = 0;
    while (sim.step() && guard++ < 10000) { /* run */ }
    return { ok: sim.result === 'success', result: sim.result, diag: sim.diag, t: sim.t, trail: sim.trail, events: sim.events, sim: sim };
  }

  function validateDesign(spec, design) {
    var issues = [], seen = {};
    (design.parts || []).forEach(function (p) { var k = p.type + ':' + (p.len || ''); seen[k] = (seen[k] || 0) + 1; });
    Object.keys(seen).forEach(function (k) {
      var bits = k.split(':'), lim = trayLimit(spec, bits[0], bits[1] ? Number(bits[1]) : null);
      if (seen[k] > lim) issues.push('Too many ' + PART_SPEC[bits[0]].label.toLowerCase() + 's: the tray has ' + lim + '.');
    });
    return { ok: !issues.length, issues: issues };
  }

  function touched(run, type, kind) { return run.events.some(function (e) { return e.type === type && (kind == null || e.kind === kind); }); }

  /** Evaluate one optional challenge rule against a finished run. */
  function checkRule(spec, design, run, rule) {
    if (!run.ok) return false;
    var parts = design.parts || [];
    switch (rule.type) {
      case 'maxParts': return parts.length <= rule.n;
      case 'maxTime': return run.t <= rule.s;
      case 'minTime': return run.t >= rule.s;
      case 'noPart': return !parts.some(function (p) { return p.type === rule.part; });
      case 'usePart': return parts.some(function (p) { return p.type === rule.part; }) && touched(run, rule.part === 'fan' ? 'fan' : (rule.part === 'bouncer' ? 'boing' : 'touch'), rule.part === 'fan' ? null : rule.part);
      case 'avoid': return !run.events.some(function (e) { return e.type === 'bump' && rule.kinds.indexOf(e.kind) !== -1; });
      case 'touch': return touched(run, rule.event, rule.kind == null ? null : rule.kind);
      case 'noTouch': return !touched(run, rule.event, rule.kind == null ? null : rule.kind);
      case 'maxAngle': return parts.every(function (p) { return p.angle == null || Math.abs(p.angle) <= rule.deg; });
    }
    return false;
  }

  return {
    DT: DT, GRAVITY: GRAVITY, MAX_TIME: MAX_TIME, GROUND: GROUND, PELLET_R: PELLET_R, MAT: MAT, PART_SPEC: PART_SPEC, FAN: FAN,
    resolve: resolve, newDesign: newDesign, normalizeDesign: normalizeDesign, createSim: createSim, runToEnd: runToEnd,
    validateDesign: validateDesign, checkRule: checkRule, trayLimit: trayLimit, trayUsed: trayUsed,
    partSegments: partSegments, fanZone: fanZone, bugAt: bugAt, fishAt: fishAt, windAt: windAt, mouthZone: mouthZone,
    journey: journey, inRect: inRect, clone: clone
  };
});
