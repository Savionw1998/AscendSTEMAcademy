/* STEM-a-lotl: Rescue Lab — canvas art.
 *
 * Everything is drawn in scene units (100 x 60) on a context the game has
 * already scaled. Drawing never changes the simulation: bees, fish, wind and
 * waterfalls are animated from the same time value the physics uses, so what
 * the child sees is where things really are.
 */
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) module.exports = factory(require('./engine.js'), require('./levels.js'));
  else root.AscendRLArt = factory(root.AscendRLEngine, root.AscendRLLevels);
})(typeof self !== 'undefined' ? self : this, function (Engine, Levels) {
  'use strict';
  var TAU = Math.PI * 2, GROUND = Levels.GROUND;

  function rr(c, x, y, w, h, r) {
    c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath();
  }
  function ellipse(c, x, y, rx, ry, rot) { c.beginPath(); c.ellipse(x, y, rx, ry, rot || 0, 0, TAU); }
  function label(c, text, x, y, size, color, align, weight) {
    c.font = (weight || 700) + ' ' + (size || 2.4) + 'px Roboto, Arial, sans-serif'; c.fillStyle = color || '#173e63'; c.textAlign = align || 'center'; c.textBaseline = 'middle'; c.fillText(text, x, y);
  }
  /* a tiny deterministic hash for decoration placement (never used by physics) */
  function h(n) { var x = Math.sin(n * 127.1) * 43758.5453; return x - Math.floor(x); }

  /* ------------------------------------------------------------- skies */
  var THEMES = {
    meadow: { sky: ['#bfe6fb', '#f3fbff'], far: '#cfe8c3', near: '#b4dca4' },
    hills: { sky: ['#d6ecfb', '#fbfdff'], far: '#d7e6cf', near: '#bcd6ac' },
    pond: { sky: ['#c8ecf7', '#f4fcff'], far: '#cfe7d8', near: '#b6dcc4' },
    canyon: { sky: ['#ffd9b8', '#fff4e6'], far: '#e6b48f', near: '#d49a72' }
  };

  function background(c, theme, t) {
    var th = THEMES[theme] || THEMES.meadow, g = c.createLinearGradient(0, 0, 0, 60);
    g.addColorStop(0, th.sky[0]); g.addColorStop(1, th.sky[1]);
    c.fillStyle = g; c.fillRect(0, 0, 100, 60);
    if (theme === 'canyon') {
      // layered canyon walls
      c.fillStyle = th.far; c.beginPath(); c.moveTo(0, 60); c.lineTo(0, 14); c.lineTo(8, 12); c.lineTo(14, 18); c.lineTo(22, 16); c.lineTo(26, 60); c.fill();
      c.beginPath(); c.moveTo(100, 60); c.lineTo(100, 10); c.lineTo(92, 12); c.lineTo(86, 20); c.lineTo(80, 18); c.lineTo(76, 60); c.fill();
      c.strokeStyle = 'rgba(138,74,40,.18)'; c.lineWidth = 0.4;
      for (var i = 0; i < 6; i++) { c.beginPath(); c.moveTo(0, 20 + i * 6); c.lineTo(24, 22 + i * 6); c.moveTo(78, 22 + i * 6); c.lineTo(100, 18 + i * 6); c.stroke(); }
      c.fillStyle = 'rgba(255,255,255,.5)'; ellipse(c, 60, 8, 7, 1.6); c.fill();
      return;
    }
    // drifting clouds
    c.fillStyle = 'rgba(255,255,255,.85)';
    [[18, 8, 1], [58, 5, 0.8], [86, 11, 1.1]].forEach(function (cl, i) {
      var x = ((cl[0] + t * (theme === 'hills' ? 2.2 : 0.8) * (i + 1) * 0.5) % 120) - 10, y = cl[1], s = cl[2];
      ellipse(c, x, y, 5 * s, 1.8 * s); c.fill(); ellipse(c, x + 3 * s, y - 1 * s, 3.2 * s, 1.8 * s); c.fill(); ellipse(c, x - 3 * s, y - 0.4 * s, 2.6 * s, 1.4 * s); c.fill();
    });
    // rolling hills
    c.fillStyle = th.far; c.beginPath(); c.moveTo(0, 60);
    for (var x = 0; x <= 100; x += 4) c.lineTo(x, 40 - 5 * Math.sin(x / 14) - 2 * Math.sin(x / 5.3));
    c.lineTo(100, 60); c.fill();
    c.fillStyle = th.near; c.beginPath(); c.moveTo(0, 60);
    for (x = 0; x <= 100; x += 4) c.lineTo(x, 47 - 3 * Math.sin(x / 9 + 2));
    c.lineTo(100, 60); c.fill();
    if (theme === 'hills') {
      // a windmill on the far hill, turning with the breeze
      c.fillStyle = '#f4f2ec'; c.beginPath(); c.moveTo(88, 38); c.lineTo(90.5, 22); c.lineTo(93, 38); c.fill();
      c.save(); c.translate(90.5, 22); c.rotate(t * 1.2); c.fillStyle = '#ffffff'; c.strokeStyle = '#c9c5b8'; c.lineWidth = 0.2;
      for (var b = 0; b < 4; b++) { c.rotate(TAU / 4); c.beginPath(); c.moveTo(0, 0); c.lineTo(1, -7); c.lineTo(-1, -7); c.closePath(); c.fill(); c.stroke(); }
      c.restore(); c.fillStyle = '#E07FA3'; c.beginPath(); c.arc(90.5, 22, 0.7, 0, TAU); c.fill();
    }
    if (theme === 'pond') {
      c.strokeStyle = '#6e9f5a'; c.lineWidth = 0.5;
      for (var r = 0; r < 7; r++) { var rx = 3 + r * 3.3; c.beginPath(); c.moveTo(rx, 40); c.quadraticCurveTo(rx + 0.8, 34, rx + 0.4 + h(r) * 1.5, 30 - h(r + 3) * 4); c.stroke(); }
      c.fillStyle = '#8a5a3b'; for (r = 0; r < 3; r++) { ellipse(c, 4.5 + r * 6.5, 30 - h(r + 3) * 4, 0.5, 1.6); c.fill(); }
    }
  }

  /* ----------------------------------------------------------- terrain */
  var MATFILL = {
    grass: ['#9c6b45', '#7a5234', '#6fbf4a', '#4f9a2f'], dirt: ['#9c6b45', '#7a5234', '#8f6a45', '#7a5234'],
    rock: ['#a79a8e', '#857769', '#b8ab9f', '#8b7d70'], sand: ['#e9d3a0', '#d6ba7e', '#f1dfb2', '#d6ba7e'], ice: ['#d8f0fb', '#a9d6ec', '#effaff', '#a9d6ec']
  };

  function terrain(c, scene) {
    (scene.fills || []).forEach(function (f, idx) {
      var m = MATFILL[f.mat] || MATFILL.grass;
      c.save();
      c.beginPath();
      if (f.shape === 'rect') c.rect(f.x, f.y, f.w, f.h);
      else { c.moveTo(f.pts[0][0], f.pts[0][1]); for (var i = 1; i < f.pts.length; i++) c.lineTo(f.pts[i][0], f.pts[i][1]); c.closePath(); }
      var g = c.createLinearGradient(0, f.shape === 'rect' ? f.y : 30, 0, 64); g.addColorStop(0, m[0]); g.addColorStop(1, m[1]);
      c.fillStyle = g; c.fill(); c.clip();
      // texture: pebbles for dirt/sand, strata for rock
      if (f.mat === 'rock') { c.strokeStyle = 'rgba(80,60,40,.18)'; c.lineWidth = 0.35; for (var y = 0; y < 60; y += 3.2) { c.beginPath(); c.moveTo(-20, y + h(idx) * 2); c.lineTo(120, y + 1 + h(idx + y) * 2); c.stroke(); } }
      else { c.fillStyle = 'rgba(0,0,0,.08)'; for (var k = 0; k < 40; k++) { var px = (f.shape === 'rect' ? f.x : 0) + h(k + idx * 50) * (f.shape === 'rect' ? f.w : 100), py = (f.shape === 'rect' ? f.y : 30) + 2 + h(k * 3 + idx) * 26; c.beginPath(); c.arc(px, py, 0.25 + h(k) * 0.3, 0, TAU); c.fill(); } }
      c.restore();
    });
    // top edges: grass tufts, sand ripples, rock rims
    (scene.solids || []).forEach(function (s, i) {
      if (Math.abs(s.x2 - s.x1) < 0.01) return; // walls get no top trim
      var m = MATFILL[s.mat] || MATFILL.grass;
      c.strokeStyle = m[2]; c.lineWidth = 1.6; c.lineCap = 'round';
      c.beginPath(); c.moveTo(s.x1, s.y1 + 0.6); c.lineTo(s.x2, s.y2 + 0.6); c.stroke();
      c.strokeStyle = m[3]; c.lineWidth = 0.35; c.beginPath(); c.moveTo(s.x1, s.y1 + 1.4); c.lineTo(s.x2, s.y2 + 1.4); c.stroke();
      if (s.mat === 'grass') {
        var len = Math.hypot(s.x2 - s.x1, s.y2 - s.y1), n = Math.floor(len / 2.2);
        c.strokeStyle = '#4f9a2f'; c.lineWidth = 0.3;
        for (var k = 0; k < n; k++) {
          var u = (k + h(k + i * 13)) / n, x = s.x1 + (s.x2 - s.x1) * u, y = s.y1 + (s.y2 - s.y1) * u;
          if (x < -2 || x > 102) continue;
          c.beginPath(); c.moveTo(x, y); c.lineTo(x - 0.4, y - 0.9 - h(k) * 0.6); c.moveTo(x, y); c.lineTo(x + 0.5, y - 0.8 - h(k + 1) * 0.6); c.stroke();
          if (h(k * 7 + i) > 0.9) { c.fillStyle = h(k) > 0.5 ? '#ffd166' : '#F5B9CF'; c.beginPath(); c.arc(x, y - 1.2, 0.45, 0, TAU); c.fill(); }
        }
      }
    });
  }

  /* ------------------------------------------------------ environment */
  /* Water is drawn in two passes: the body behind everything that lives in
     it, and a light veil plus the surface in front, so fish and Lucas keep
     their colours but still look underwater. */
  function waterBody(c, scene, t) {
    (scene.water || []).forEach(function (w) {
      var g = c.createLinearGradient(0, w.y, 0, w.y + w.h);
      g.addColorStop(0, 'rgba(96,190,232,.55)'); g.addColorStop(1, 'rgba(38,128,186,.65)');
      c.fillStyle = g; c.fillRect(w.x, w.y, w.w, w.h);
      c.fillStyle = 'rgba(255,255,255,.08)';
      for (var i = 0; i < 4; i++) { var rx = w.x + ((i * 23 + t * 2) % Math.max(10, w.w)); c.beginPath(); c.moveTo(rx, w.y); c.lineTo(rx + 3, w.y); c.lineTo(rx - 3, w.y + w.h); c.lineTo(rx - 6, w.y + w.h); c.fill(); }
      c.strokeStyle = '#3f8f5c'; c.lineWidth = 0.4;
      for (var k = 0; k < Math.floor(w.w / 9); k++) { var wx = w.x + 3 + k * 9 + h(k) * 3; c.beginPath(); c.moveTo(wx, GROUND); c.quadraticCurveTo(wx + Math.sin(t * 1.5 + k) * 1.2, GROUND - 3, wx + Math.sin(t * 1.5 + k) * 0.6, GROUND - 5 - h(k) * 3); c.stroke(); }
    });
    (scene.bubbles || []).forEach(function (z) {
      c.fillStyle = 'rgba(255,255,255,.14)'; c.fillRect(z.x, z.y, z.w, z.h);
      c.fillStyle = '#8fa3ad'; ellipse(c, z.x + z.w / 2, GROUND - 0.3, z.w / 2, 1); c.fill();
    });
  }
  function waterVeil(c, scene, t) {
    (scene.water || []).forEach(function (w) {
      c.fillStyle = 'rgba(64,160,210,.12)'; c.fillRect(w.x, w.y, w.w, w.h);
      c.strokeStyle = 'rgba(255,255,255,.9)'; c.lineWidth = 0.45; c.beginPath();
      for (var x = w.x; x <= w.x + w.w; x += 1) { var y = w.y + 0.35 * Math.sin(x * 0.6 + t * 3); if (x === w.x) c.moveTo(x, y); else c.lineTo(x, y); }
      c.stroke();
      if (w.current) {
        var dir = w.current > 0 ? 1 : -1, speed = Math.min(1, Math.abs(w.current) / 40);
        c.strokeStyle = 'rgba(255,255,255,' + (0.25 + speed * 0.25) + ')'; c.lineWidth = 0.35;
        for (var k = 0; k < 6; k++) {
          var cy = w.y + 3 + (k % 3) * (w.h - 6) / 2, cx = w.x + ((k * 17 + dir * t * Math.abs(w.current) * 0.4) % w.w + w.w) % w.w;
          c.beginPath(); c.moveTo(cx - dir * 2.5, cy); c.lineTo(cx, cy); c.lineTo(cx - dir * 0.9, cy - 0.7); c.moveTo(cx, cy); c.lineTo(cx - dir * 0.9, cy + 0.7); c.stroke();
        }
      }
      c.strokeStyle = 'rgba(255,255,255,.6)'; c.lineWidth = 0.2;
      for (k = 0; k < 5; k++) { var bx = w.x + h(k + 9) * w.w, by = w.y + w.h - ((t * 3 + h(k) * w.h) % w.h); c.beginPath(); c.arc(bx + Math.sin(t * 2 + k) * 0.4, by, 0.35 + h(k) * 0.3, 0, TAU); c.stroke(); }
    });
    (scene.bubbles || []).forEach(function (z) {
      c.strokeStyle = 'rgba(255,255,255,.9)'; c.lineWidth = 0.25;
      for (var k = 0; k < 14; k++) { var bx = z.x + h(k) * z.w, by = z.y + z.h - ((t * 9 + h(k + 4) * z.h) % z.h); c.beginPath(); c.arc(bx + Math.sin(t * 3 + k) * 0.5, by, 0.3 + h(k + 2) * 0.5, 0, TAU); c.stroke(); }
    });
  }

  function falls(c, scene, t) {
    (scene.falls || []).forEach(function (z) {
      var g = c.createLinearGradient(z.x, 0, z.x + z.w, 0);
      g.addColorStop(0, 'rgba(160,220,245,.75)'); g.addColorStop(0.5, 'rgba(225,246,255,.9)'); g.addColorStop(1, 'rgba(160,220,245,.75)');
      c.fillStyle = g; c.fillRect(z.x, z.y, z.w, z.h);
      c.strokeStyle = 'rgba(255,255,255,.95)'; c.lineWidth = 0.35;
      for (var i = 0; i < 7; i++) {
        var sx = z.x + 0.5 + (i + 0.5) * (z.w - 1) / 7, off = (t * 40 + h(i) * 60) % 12;
        for (var y = z.y - 12 + off; y < z.y + z.h; y += 12) { c.beginPath(); c.moveTo(sx, y); c.lineTo(sx, y + 4 + h(i) * 2); c.stroke(); }
      }
      // foam and mist at the bottom
      c.fillStyle = 'rgba(255,255,255,.9)';
      for (i = 0; i < 6; i++) { c.beginPath(); c.arc(z.x - 1 + i * (z.w + 2) / 5, z.y + z.h - 0.6 + Math.sin(t * 6 + i) * 0.3, 1 + h(i) * 0.6, 0, TAU); c.fill(); }
      c.fillStyle = 'rgba(255,255,255,.25)'; ellipse(c, z.x + z.w / 2, z.y + z.h - 2, z.w + 3, 2.4); c.fill();
    });
  }

  function wind(c, scene, t) {
    (scene.wind || []).forEach(function (z, zi) {
      var w = Engine.windAt(z, t), dir = w.fx >= 0 ? 1 : -1, strength = Math.min(1, Math.abs(w.fx) / 45);
      c.fillStyle = 'rgba(255,255,255,.10)'; c.fillRect(z.x, z.y, z.w, z.h);
      c.strokeStyle = 'rgba(255,255,255,' + (0.45 + 0.35 * strength) + ')'; c.lineWidth = 0.4; c.lineCap = 'round';
      for (var i = 0; i < 7; i++) {
        var y = z.y + 2 + (i + 0.5) * (z.h - 4) / 7, span = z.w + 16, x = z.x - 8 + ((i * 13 + dir * t * Math.abs(w.fx) * 0.9) % span + span) % span;
        c.beginPath(); c.moveTo(x, y); c.bezierCurveTo(x + dir * 2, y - 0.8, x + dir * 4, y + 0.8, x + dir * 6, y); c.stroke();
      }
      // tumbling leaves
      for (i = 0; i < 3; i++) {
        var span2 = z.w + 6, lx = z.x - 3 + ((i * 21 + zi * 7 + dir * t * Math.abs(w.fx) * 0.5) % span2 + span2) % span2, ly = z.y + z.h * (0.25 + 0.25 * i) + Math.sin(t * 3 + i) * 1.5;
        c.save(); c.translate(lx, ly); c.rotate(t * 4 + i); c.fillStyle = i % 2 ? '#8fbf4a' : '#e0a100'; ellipse(c, 0, 0, 0.9, 0.45); c.fill(); c.restore();
      }
    });
  }

  function rocks(c, scene) {
    (scene.rocks || []).forEach(function (r) {
      var g = c.createRadialGradient(r.x - r.r * 0.3, r.y - r.r * 0.4, r.r * 0.2, r.x, r.y, r.r);
      var mossy = (scene.theme === 'meadow' || scene.theme === 'hills');
      g.addColorStop(0, mossy ? '#a8845c' : '#b9aea4'); g.addColorStop(1, mossy ? '#6e4f33' : '#7d7064');
      c.fillStyle = g; c.beginPath(); c.arc(r.x, r.y, r.r, 0, TAU); c.fill();
      if (mossy) { c.strokeStyle = 'rgba(80,50,30,.4)'; c.lineWidth = 0.3; c.beginPath(); c.arc(r.x, r.y, r.r * 0.55, 0, TAU); c.stroke(); c.beginPath(); c.arc(r.x, r.y, r.r * 0.25, 0, TAU); c.stroke(); c.fillStyle = '#6fbf4a'; ellipse(c, r.x - r.r * 0.2, r.y - r.r * 0.85, r.r * 0.55, r.r * 0.22); c.fill(); }
      else { c.fillStyle = 'rgba(255,255,255,.25)'; ellipse(c, r.x - r.r * 0.35, r.y - r.r * 0.45, r.r * 0.35, r.r * 0.18, -0.4); c.fill(); }
    });
  }

  function bees(c, scene, t, showPath) {
    (scene.bugs || []).forEach(function (b) {
      if (showPath) {
        c.strokeStyle = 'rgba(224,161,0,.35)'; c.setLineDash([0.8, 0.8]); c.lineWidth = 0.3; c.beginPath();
        for (var s = 0; s <= 40; s++) { var q = Engine.bugAt(b, s / 40 * b.period); if (s) c.lineTo(q.x, q.y); else c.moveTo(q.x, q.y); }
        c.stroke(); c.setLineDash([]);
      }
      var p = Engine.bugAt(b, t), face = p.vx >= 0 ? 1 : -1, flap = Math.sin(t * 60) * 0.5 + 0.5;
      c.save(); c.translate(p.x, p.y); c.scale(face, 1);
      c.fillStyle = 'rgba(255,255,255,.75)'; c.strokeStyle = 'rgba(120,140,160,.6)'; c.lineWidth = 0.15;
      ellipse(c, -0.5, -1.7, 1.2, 0.6 + flap * 0.6, -0.5); c.fill(); c.stroke();
      ellipse(c, 0.6, -1.8, 1.1, 0.55 + flap * 0.5, 0.4); c.fill(); c.stroke();
      c.fillStyle = '#ffd166'; ellipse(c, 0, 0, 2.2, 1.6); c.fill();
      c.fillStyle = '#24332a'; c.fillRect(-1, -1.5, 0.55, 3); c.fillRect(0.2, -1.55, 0.55, 3.1);
      c.beginPath(); c.moveTo(-2.1, 0); c.lineTo(-2.9, 0.2); c.lineTo(-2.1, 0.5); c.fill();
      c.fillStyle = '#24332a'; c.beginPath(); c.arc(1.7, -0.3, 0.9, 0, TAU); c.fill();
      c.fillStyle = '#fff'; c.beginPath(); c.arc(1.95, -0.5, 0.3, 0, TAU); c.fill();
      c.strokeStyle = '#24332a'; c.lineWidth = 0.18; c.beginPath(); c.moveTo(2, -1); c.quadraticCurveTo(2.4, -2, 3, -2.1); c.stroke();
      c.restore();
    });
  }

  function fishes(c, scene, t) {
    (scene.fish || []).forEach(function (f, i) {
      var p = Engine.fishAt(f, t), wag = Math.sin(t * 10 + i) * 0.35;
      var colors = [['#ff9b4a', '#e2671b'], ['#ffc34a', '#d18a00'], ['#ff7a8a', '#c9435a']][i % 3];
      c.save(); c.translate(p.x, p.y); c.scale(p.face, 1);
      c.fillStyle = colors[1]; c.beginPath(); c.moveTo(-2, 0); c.lineTo(-3.6, -1.4 + wag); c.lineTo(-3.4, 0); c.lineTo(-3.6, 1.4 + wag); c.closePath(); c.fill();
      var g = c.createLinearGradient(0, -1.5, 0, 1.5); g.addColorStop(0, colors[0]); g.addColorStop(1, colors[1]);
      c.fillStyle = g; ellipse(c, 0, 0, 2.6, 1.5); c.fill();
      c.fillStyle = 'rgba(255,255,255,.55)'; c.beginPath(); c.moveTo(-0.6, -1.4); c.quadraticCurveTo(0.4, -2.4, 1, -1.3); c.fill();
      c.fillStyle = '#fff'; c.beginPath(); c.arc(1.5, -0.35, 0.5, 0, TAU); c.fill();
      c.fillStyle = '#24332a'; c.beginPath(); c.arc(1.65, -0.35, 0.28, 0, TAU); c.fill();
      c.restore();
    });
  }

  /* The food dispenser hangs from the top edge: a pipe down to a nozzle. */
  function dispenser(c, spawn) {
    var x = spawn.x, y = spawn.y, top = Math.max(0, y - 3.2);
    c.fillStyle = '#045C82'; rr(c, x - 1.6, -1, 3.2, top + 1, 0.6); c.fill();
    c.fillStyle = '#009CDE'; c.fillRect(x - 1.6, -1, 0.9, top + 1);
    c.fillStyle = '#045C82'; c.beginPath(); c.moveTo(x - 3, top); c.lineTo(x + 3, top); c.lineTo(x + 2, top + 1.2); c.lineTo(x - 2, top + 1.2); c.closePath(); c.fill();
    c.fillStyle = '#F5B9CF'; for (var i = 0; i < 3; i++) { c.beginPath(); c.arc(x - 1.6 + i * 1.6, top + 0.5, 0.4, 0, TAU); c.fill(); }
    c.fillStyle = 'rgba(255,255,255,.85)'; rr(c, x + 2.6, 0.6, 7.4, 3.2, 1.2); c.fill();
    label(c, 'Food', x + 6.3, 2.25, 2.1, '#045C82');
  }

  /* ------------------------------------------------------------- parts */
  function part(c, p, t, opts) {
    opts = opts || {};
    if (p.type === 'fan') return fan(c, p, t, opts);
    var s = Engine.partSegments(p)[0], ang = Math.atan2(s.y2 - s.y1, s.x2 - s.x1), len = Math.hypot(s.x2 - s.x1, s.y2 - s.y1);
    c.save(); c.translate(p.x, p.y); c.rotate(ang);
    if (p.type === 'ramp') {
      // wooden plank: the pellet rolls on its top surface, which is the physics line
      var g = c.createLinearGradient(0, -0.2, 0, 1.8); g.addColorStop(0, '#e2b173'); g.addColorStop(1, '#b07a44');
      c.fillStyle = g; rr(c, -len / 2, -0.2, len, 2, 0.6); c.fill();
      c.strokeStyle = 'rgba(110,70,35,.45)'; c.lineWidth = 0.18;
      for (var k = 0; k < 3; k++) { c.beginPath(); c.moveTo(-len / 2 + 0.8, 0.35 + k * 0.5); c.bezierCurveTo(-len / 6, 0.2 + k * 0.5, len / 6, 0.6 + k * 0.5, len / 2 - 0.8, 0.35 + k * 0.5); c.stroke(); }
      c.fillStyle = '#8a5a2b'; c.beginPath(); c.arc(-len / 2 + 1, 0.8, 0.28, 0, TAU); c.arc(len / 2 - 1, 0.8, 0.28, 0, TAU); c.fill();
      // little legs so it reads as a real ramp
      c.strokeStyle = '#8a5a2b'; c.lineWidth = 0.5; c.beginPath(); c.moveTo(-len / 4, 1.8); c.lineTo(-len / 4, 2.8); c.moveTo(len / 4, 1.8); c.lineTo(len / 4, 2.8); c.stroke();
    } else if (p.type === 'bouncer') {
      // a springy mushroom cap on a coil
      c.strokeStyle = '#8fa3ad'; c.lineWidth = 0.35; c.beginPath();
      for (k = 0; k <= 8; k++) { var zx = -len / 2 + 1.5 + k * (len - 3) / 8; c.lineTo(zx, 1.2 + (k % 2) * 1.3); }
      c.stroke();
      c.fillStyle = '#6b7f8a'; rr(c, -len / 2 + 1, 2.4, len - 2, 0.9, 0.4); c.fill();
      var cg = c.createLinearGradient(0, -1.4, 0, 1.2); cg.addColorStop(0, '#ff6f8f'); cg.addColorStop(1, '#c9435a');
      c.fillStyle = cg; c.beginPath(); c.moveTo(-len / 2, 1); c.quadraticCurveTo(0, -2.6, len / 2, 1); c.closePath(); c.fill();
      c.fillStyle = '#fff'; [[-3, 0], [0, -0.8], [3, 0]].forEach(function (d) { c.beginPath(); c.arc(d[0], d[1], 0.55, 0, TAU); c.fill(); });
    } else if (p.type === 'block') {
      var bg = c.createLinearGradient(0, -0.2, 0, 3); bg.addColorStop(0, '#b8ab9f'); bg.addColorStop(1, '#7d7064');
      c.fillStyle = bg; rr(c, -len / 2, -0.2, len, 3, 0.7); c.fill();
      c.strokeStyle = 'rgba(60,45,30,.35)'; c.lineWidth = 0.2; c.beginPath(); c.moveTo(-1, -0.2); c.lineTo(-0.3, 1.2); c.lineTo(-1.2, 2.8); c.moveTo(2.4, 0.4); c.lineTo(3.2, 1.8); c.stroke();
    }
    if (opts.selected) { c.strokeStyle = '#ffd166'; c.lineWidth = 0.6; rr(c, -len / 2 - 0.8, -1.4, len + 1.6, 4.8, 1.4); c.stroke(); }
    c.restore();
    if (opts.selected && p.angle != null) {
      // rotate handle at the right end
      c.fillStyle = '#ffd166'; c.strokeStyle = '#173e63'; c.lineWidth = 0.35; c.beginPath(); c.arc(s.x2, s.y2, 2, 0, TAU); c.fill(); c.stroke();
      c.beginPath(); c.arc(s.x2, s.y2, 1, 0.5, 5.3); c.stroke();
      label(c, (p.angle || 0) + '°', p.x, p.y - 4.2, 2.4, '#173e63');
    }
  }

  function fan(c, p, t, opts) {
    var z = Engine.fanZone(p), dx = Math.sign(z.fx), dy = Math.sign(z.fy);
    c.fillStyle = 'rgba(0,156,222,.08)'; c.fillRect(z.x, z.y, z.w, z.h);
    c.strokeStyle = 'rgba(0,156,222,.45)'; c.lineWidth = 0.35; c.lineCap = 'round';
    for (var i = 0; i < 4; i++) {
      var prog = ((t * 1.6 + i / 4) % 1), along = prog * (dx ? z.w : z.h);
      var ax = dx ? (dx > 0 ? z.x + along : z.x + z.w - along) : z.x + 2 + i * (z.w - 4) / 3;
      var ay = dy ? (dy > 0 ? z.y + along : z.y + z.h - along) : z.y + 2 + i * (z.h - 4) / 3;
      c.beginPath(); c.moveTo(ax - dx * 2, ay - dy * 2); c.lineTo(ax + dx * 2, ay + dy * 2); c.stroke();
      c.beginPath(); c.moveTo(ax + dx * 2, ay + dy * 2); c.lineTo(ax + dx * 1 - dy * 0.8, ay + dy * 1 + dx * 0.8); c.moveTo(ax + dx * 2, ay + dy * 2); c.lineTo(ax + dx * 1 + dy * 0.8, ay + dy * 1 - dx * 0.8); c.stroke();
    }
    c.save(); c.translate(p.x, p.y);
    c.fillStyle = '#045C82'; c.beginPath(); c.arc(0, 0, 3.4, 0, TAU); c.fill();
    c.fillStyle = '#e8f6fc'; c.beginPath(); c.arc(0, 0, 2.8, 0, TAU); c.fill();
    c.rotate(t * 14);
    c.fillStyle = '#009CDE'; for (i = 0; i < 4; i++) { c.rotate(TAU / 4); ellipse(c, 1.3, 0, 1.3, 0.6, 0.3); c.fill(); }
    c.fillStyle = '#045C82'; c.beginPath(); c.arc(0, 0, 0.6, 0, TAU); c.fill();
    c.restore();
    // direction nub
    c.fillStyle = '#E07FA3'; c.beginPath(); c.arc(p.x + dx * 3.6, p.y + dy * 3.6, 0.8, 0, TAU); c.fill();
    if (opts.selected) { c.strokeStyle = '#ffd166'; c.lineWidth = 0.6; c.beginPath(); c.arc(p.x, p.y, 4.4, 0, TAU); c.stroke(); }
  }

  /* ------------------------------------------------------------ pellet */
  function pellet(c, b, skin, t) {
    var r = b.r, col = skin.colors;
    c.fillStyle = 'rgba(0,0,0,.12)'; ellipse(c, b.x + 0.3, b.y + r * 0.9, r * 0.9, r * 0.3); c.fill();
    var g = c.createRadialGradient(b.x - r * 0.35, b.y - r * 0.4, r * 0.15, b.x, b.y, r);
    g.addColorStop(0, col[0]); g.addColorStop(1, col[1]);
    c.save(); c.beginPath(); c.arc(b.x, b.y, r, 0, TAU); c.fillStyle = g; c.fill(); c.clip();
    c.translate(b.x, b.y); c.rotate(b.angle || 0);
    patterns[skin.pattern] && patterns[skin.pattern](c, r, t, col);
    c.restore();
    c.fillStyle = 'rgba(255,255,255,.6)'; ellipse(c, b.x - r * 0.38, b.y - r * 0.42, r * 0.34, r * 0.22, -0.5); c.fill();
    c.strokeStyle = 'rgba(0,0,0,.12)'; c.lineWidth = 0.15; c.beginPath(); c.arc(b.x, b.y, r, 0, TAU); c.stroke();
  }
  var patterns = {
    plain: function (c, r) { c.strokeStyle = 'rgba(255,255,255,.55)'; c.lineWidth = 0.3; c.beginPath(); c.moveTo(-r, 0); c.lineTo(r, 0); c.stroke(); },
    dots: function (c, r) { c.fillStyle = 'rgba(255,255,255,.55)'; [[-0.8, -0.6], [0.7, 0.4], [-0.2, 1], [0.9, -0.9]].forEach(function (d) { c.beginPath(); c.arc(d[0], d[1], 0.32, 0, TAU); c.fill(); }); },
    swirl: function (c, r) { c.strokeStyle = 'rgba(255,255,255,.6)'; c.lineWidth = 0.3; c.beginPath(); for (var a = 0; a < 12; a += 0.3) c.lineTo(Math.cos(a) * a * 0.16, Math.sin(a) * a * 0.16); c.stroke(); },
    petals: function (c, r) { c.fillStyle = 'rgba(255,255,255,.45)'; for (var i = 0; i < 6; i++) { c.rotate(TAU / 6); ellipse(c, 1.2, 0, 0.8, 0.35); c.fill(); } c.fillStyle = '#8a5a2b'; c.beginPath(); c.arc(0, 0, 0.5, 0, TAU); c.fill(); },
    stripes: function (c, r) { c.fillStyle = 'rgba(255,255,255,.35)'; for (var i = -2; i <= 2; i++) c.fillRect(i * 1.1 - 0.25, -r, 0.5, r * 2); },
    bubbles: function (c, r) { c.strokeStyle = 'rgba(255,255,255,.8)'; c.lineWidth = 0.2; [[-0.7, -0.4, 0.5], [0.8, 0.5, 0.4], [0, 1.1, 0.3]].forEach(function (d) { c.beginPath(); c.arc(d[0], d[1], d[2], 0, TAU); c.stroke(); }); },
    cracks: function (c, r, t) { c.strokeStyle = 'rgba(255,200,80,' + (0.6 + 0.3 * Math.sin(t * 4)) + ')'; c.lineWidth = 0.28; c.beginPath(); c.moveTo(-1.6, -0.6); c.lineTo(-0.3, 0.1); c.lineTo(0.4, -0.9); c.moveTo(-0.3, 0.1); c.lineTo(0.8, 1.2); c.stroke(); },
    rainbow: function (c, r) { ['#ff6f8f', '#ffb347', '#ffe27a', '#8fdc7a', '#7bd3ff', '#a98bff'].forEach(function (col, i) { c.fillStyle = col; c.fillRect(-r, -r + i * (2 * r / 6), 2 * r, 2 * r / 6 + 0.05); }); },
    shine: function (c, r, t) { c.fillStyle = 'rgba(255,255,255,.35)'; c.rotate(t * 0.8); c.fillRect(-r, -0.35, 2 * r, 0.7); },
    stars: function (c, r) { c.fillStyle = '#fff'; [[-0.8, -0.5, 0.22], [0.9, 0.3, 0.18], [0.1, 1.1, 0.15], [0.4, -1.1, 0.12], [-1.2, 0.8, 0.12]].forEach(function (d) { c.beginPath(); c.arc(d[0], d[1], d[2], 0, TAU); c.fill(); }); },
    tiles: function (c, r) { c.strokeStyle = 'rgba(40,50,60,.35)'; c.lineWidth = 0.12; for (var i = -2; i <= 2; i++) { c.beginPath(); c.moveTo(i * 0.9, -r); c.lineTo(i * 0.9, r); c.moveTo(-r, i * 0.9); c.lineTo(r, i * 0.9); c.stroke(); } c.fillStyle = 'rgba(255,255,255,.7)'; c.fillRect(-0.9, -0.9, 0.9, 0.9); }
  };

  function trail(c, pts, skin, t) {
    if (!skin.trail || !pts || pts.length < 2) return;
    var n = Math.min(pts.length, 14);
    for (var i = 0; i < n; i++) {
      var p = pts[pts.length - 1 - i], a = 1 - i / n;
      if (skin.trail === 'sparkle') { c.fillStyle = 'rgba(255,230,120,' + (a * 0.8) + ')'; var s = 0.5 * a + 0.2; c.beginPath(); c.moveTo(p[0], p[1] - s * 2); c.lineTo(p[0] + s * 0.5, p[1] - s * 0.5); c.lineTo(p[0] + s * 2, p[1]); c.lineTo(p[0] + s * 0.5, p[1] + s * 0.5); c.lineTo(p[0], p[1] + s * 2); c.lineTo(p[0] - s * 0.5, p[1] + s * 0.5); c.lineTo(p[0] - s * 2, p[1]); c.lineTo(p[0] - s * 0.5, p[1] - s * 0.5); c.closePath(); c.fill(); }
      else { c.strokeStyle = 'rgba(255,255,255,' + (a * 0.8) + ')'; c.lineWidth = 0.18; c.beginPath(); c.arc(p[0], p[1], 0.3 + i * 0.05, 0, TAU); c.stroke(); }
    }
  }

  /* ------------------------------------------------------------- Lucas */
  /**
   * Lucas the axolotl, drawn standing with his feet at (l.x, l.y).
   * mood: { t, open (0..1 mouth), chew (seconds since eating, or -1), sad }
   */
  function lucas(c, l, mood, underwater) {
    var t = mood.t || 0, f = l.face || -1, bob = underwater ? Math.sin(t * 2) * 0.4 : 0;
    c.save(); c.translate(l.x, l.y + bob); c.scale(-f, 1); // art is drawn facing left (-x)
    // shadow
    if (!underwater) { c.fillStyle = 'rgba(0,0,0,.12)'; ellipse(c, 0.5, -0.1, 7, 0.9); c.fill(); }
    // tail
    var sway = Math.sin(t * 2.2) * 0.8;
    c.fillStyle = '#f7a8c0'; c.beginPath(); c.moveTo(3, -3.6); c.quadraticCurveTo(7, -4.5 + sway, 10.2, -6 + sway); c.quadraticCurveTo(8, -2.6 + sway * 0.5, 3.4, -1.6); c.closePath(); c.fill();
    c.fillStyle = 'rgba(224,82,125,.25)'; c.beginPath(); c.moveTo(4, -3.8); c.quadraticCurveTo(7.4, -5.8 + sway, 10.2, -6 + sway); c.quadraticCurveTo(7, -4.5 + sway, 4, -3.2); c.fill();
    // back legs
    c.fillStyle = '#f39bb6'; ellipse(c, 2.6, -0.8, 1.1, 0.8, 0.4); c.fill(); ellipse(c, -2.2, -0.8, 1.1, 0.8, -0.3); c.fill();
    // body
    var bg = c.createLinearGradient(0, -6, 0, -1); bg.addColorStop(0, '#fbc3d3'); bg.addColorStop(1, '#f39bb6');
    c.fillStyle = bg; ellipse(c, 0.6, -3.2, 4.6, 2.5); c.fill();
    c.fillStyle = 'rgba(255,255,255,.35)'; ellipse(c, 0, -2.2, 3.4, 0.9); c.fill();
    // spots
    c.fillStyle = 'rgba(224,82,125,.25)'; [[1.8, -4.4], [3, -3.7], [0.4, -4.8]].forEach(function (s) { c.beginPath(); c.arc(s[0], s[1], 0.35, 0, TAU); c.fill(); });
    // head
    var hx = -3.2, hy = -6.2;
    var gills = Math.sin(t * 3) * 0.25;
    c.strokeStyle = '#e0527d'; c.lineCap = 'round'; c.lineWidth = 0.55;
    [[-0.6, -2.3, -1.1], [0.6, -2.6, -0.2], [1.8, -2.2, 0.7]].forEach(function (g, i) {
      var bx = hx + g[0], by = hy - 1.6, ex = hx + g[2] + 1.8 + gills * (i - 1), ey = hy + g[1] - 1.2 - Math.abs(gills);
      c.beginPath(); c.moveTo(bx, by); c.quadraticCurveTo((bx + ex) / 2 + 0.6, (by + ey) / 2, ex, ey); c.stroke();
      c.fillStyle = '#e0527d'; for (var k = 1; k <= 3; k++) { var u = k / 3.4; c.beginPath(); c.arc(bx + (ex - bx) * u + 0.35, by + (ey - by) * u, 0.32, 0, TAU); c.fill(); }
    });
    var hg = c.createRadialGradient(hx - 0.8, hy - 1, 0.5, hx, hy, 3.8); hg.addColorStop(0, '#fdd3df'); hg.addColorStop(1, '#f7a8c0');
    c.fillStyle = hg; ellipse(c, hx, hy, 3.7, 3.1); c.fill();
    // cheeks
    c.fillStyle = 'rgba(224,82,125,.35)'; ellipse(c, hx - 2.2, hy + 1.1, 0.8, 0.5); c.fill();
    // eye (blinks now and then)
    var blink = (t % 4.2) < 0.12;
    if (mood.chew >= 0 && mood.chew < 1.4) { c.strokeStyle = '#2b1b24'; c.lineWidth = 0.35; c.beginPath(); c.arc(hx - 1.4, hy - 0.8, 0.6, Math.PI * 1.1, Math.PI * 1.9); c.stroke(); }
    else if (blink) { c.strokeStyle = '#2b1b24'; c.lineWidth = 0.3; c.beginPath(); c.moveTo(hx - 2, hy - 0.8); c.lineTo(hx - 0.8, hy - 0.8); c.stroke(); }
    else { c.fillStyle = '#2b1b24'; c.beginPath(); c.arc(hx - 1.4, hy - 0.8, 0.62, 0, TAU); c.fill(); c.fillStyle = '#fff'; c.beginPath(); c.arc(hx - 1.6, hy - 1.05, 0.22, 0, TAU); c.fill(); }
    // mouth
    var open = mood.chew >= 0 ? (mood.chew < 1.4 ? 0.25 + 0.25 * Math.abs(Math.sin(mood.chew * 9)) : 0) : (mood.open || 0);
    if (open > 0.05) {
      c.fillStyle = '#7a2a48'; ellipse(c, hx - 2.7, hy + 1.1, 0.9, 0.35 + open * 1.1); c.fill();
      c.fillStyle = '#ff8fb1'; ellipse(c, hx - 2.6, hy + 1.3 + open * 0.4, 0.5, 0.25); c.fill();
    } else {
      c.strokeStyle = '#7a2a48'; c.lineWidth = 0.28; c.beginPath();
      if (mood.sad) c.arc(hx - 2.4, hy + 1.8, 0.9, Math.PI * 1.15, Math.PI * 1.85); else c.arc(hx - 2.4, hy + 0.6, 0.9, Math.PI * 0.15, Math.PI * 0.85);
      c.stroke();
    }
    // front arm
    c.fillStyle = '#f39bb6'; ellipse(c, -1.6, -1.2, 0.9, 1.3, 0.5); c.fill();
    c.restore();
    // hearts after a meal
    if (mood.chew >= 0 && mood.chew < 2.4) {
      for (var i = 0; i < 3; i++) {
        var a = mood.chew - i * 0.35; if (a < 0) continue;
        var x = l.x + f * 2 + (i - 1) * 2.2 + Math.sin(a * 4 + i) * 0.6, y = l.y - 11 - a * 5;
        heart(c, x, y, 0.9, 'rgba(224,82,125,' + Math.max(0, 1 - a / 2.2) + ')');
      }
    }
  }
  function heart(c, x, y, s, color) {
    c.fillStyle = color; c.beginPath(); c.moveTo(x, y + s * 0.9);
    c.bezierCurveTo(x - s * 1.6, y - s * 0.2, x - s * 0.8, y - s * 1.4, x, y - s * 0.5);
    c.bezierCurveTo(x + s * 0.8, y - s * 1.4, x + s * 1.6, y - s * 0.2, x, y + s * 0.9); c.fill();
  }

  /** Faint feeding ring so the child can see where Lucas can reach. */
  function mouthRing(c, l, t) {
    var m = Engine.mouthZone(l);
    c.strokeStyle = 'rgba(224,127,163,' + (0.35 + 0.15 * Math.sin(t * 3)) + ')'; c.lineWidth = 0.3; c.setLineDash([0.9, 0.9]);
    c.beginPath(); c.arc(m.x, m.y, m.r + 2.2, 0, TAU); c.stroke(); c.setLineDash([]);
  }

  function ghost(c, pts) {
    if (!pts || !pts.length) return;
    c.fillStyle = 'rgba(4,92,130,.28)';
    for (var i = 0; i < pts.length; i += 2) { c.beginPath(); c.arc(pts[i][0], pts[i][1], 0.45, 0, TAU); c.fill(); }
  }

  /** Full scene in back-to-front order. */
  function scene(c, sc, o) {
    var t = o.t;
    background(c, sc.theme, o.clock);
    wind(c, sc, t);
    terrain(c, sc);
    rocks(c, sc);
    waterBody(c, sc, o.clock);
    falls(c, sc, o.clock);
    dispenser(c, sc.spawn);
    if (o.showRing) mouthRing(c, sc.lucas, o.clock);
    var underwater = (sc.water || []).some(function (w) { return Engine.inRect(sc.lucas.x, sc.lucas.y - 3, w); });
    lucas(c, sc.lucas, o.lucas, underwater);
    (o.parts || []).forEach(function (p, i) { part(c, p, o.clock, { selected: i === o.selected }); });
    if (o.preview) { c.globalAlpha = 0.45; part(c, o.preview, o.clock, {}); c.globalAlpha = 1; }
    if (o.ghost) ghost(c, o.ghost);
    fishes(c, sc, t);
    bees(c, sc, t, o.showPaths);
    if (o.body) { trail(c, o.recent, o.skin, o.clock); pellet(c, o.body, o.skin, o.clock); }
    waterVeil(c, sc, o.clock); // a light veil so things inside look submerged but keep their colours
  }

  return { scene: scene, pellet: pellet, lucas: lucas, part: part, background: background, label: label, heart: heart, THEMES: THEMES };
});
