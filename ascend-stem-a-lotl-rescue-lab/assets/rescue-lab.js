/* STEM-a-lotl: Rescue Lab — game front end.
 *
 * Depends on levels.js, engine.js and progress.js (loaded first). Reads the
 * page config from window.AscendRL (written by the plugin shortcode, or by the
 * standalone preview). Everything renders inside #rl-game.
 *
 * Loop: build → run → observe → improve → celebrate. Runs are stepped with the
 * engine's fixed time step from an accumulator, so frame rate never changes an
 * outcome, and the simulation pauses when the tab is hidden.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined') return;
  var Levels = window.AscendRLLevels, Engine = window.AscendRLEngine, Progress = window.AscendRLProgress;
  if (!Levels || !Engine || !Progress) return;

  var CFG = window.AscendRL || {};
  var STORE_KEY = 'ascend_rescue_lab_v1', DRAFT_KEY = 'ascend_rescue_lab_drafts_v1';
  var GROUND = Levels.GROUND;
  var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var GUIDE = 'Lucas';

  /* ------------------------------------------------------------ helpers */
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function btn(cls, text, onclick, label) { var b = el('button', cls, text); b.type = 'button'; if (label) b.setAttribute('aria-label', label); if (onclick) b.addEventListener('click', onclick); return b; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
  function deg2rad(d) { return d * Math.PI / 180; }
  function now() { return Date.now(); }
  function partLabel(t, p) {
    if (t.type === 'ramp') return 'Ramp' + (t.len ? ' ' + t.len : '');
    if (t.type === 'beam') return 'Beam ' + t.len;
    if (t.type === 'post') return 'Support post';
    if (t.type === 'bumper') return 'Bumper';
    if (t.type === 'fan') return 'Fan';
    if (t.type === 'barrier') return 'Barrier';
    if (t.type === 'wire') return 'Wire';
    if (t.type === 'corner') return 'Corner';
    if (t.type === 'switch') return 'Switch';
    return t.type;
  }

  /* ----------------------------------------------------------- storage */
  var store = {
    progress: Progress.empty(), owned: {}, hasPass: false, keys: null, keysAvailable: false, synced: false, drafts: {},
    loadLocal: function () {
      try { var raw = JSON.parse(window.localStorage.getItem(STORE_KEY)); if (raw) this.progress = Progress.sanitize(raw); } catch (e) { /* storage unavailable: memory only */ }
      try { var d = JSON.parse(window.localStorage.getItem(DRAFT_KEY)); if (d && typeof d === 'object') this.drafts = d; } catch (e) { /* ignore */ }
    },
    saveLocal: function () {
      try { window.localStorage.setItem(STORE_KEY, JSON.stringify(this.progress)); } catch (e) { /* ignore */ }
      try { window.localStorage.setItem(DRAFT_KEY, JSON.stringify(this.drafts)); } catch (e) { /* ignore */ }
    },
    applyServer: function (data) {
      if (!data) return;
      if (data.progress) this.progress = Progress.merge(this.progress, data.progress);
      this.owned = {};
      (data.owned || []).forEach(function (id) { store.owned[id] = true; });
      this.hasPass = !!data.hasPass;
      this.keys = typeof data.keys === 'number' ? data.keys : null;
      this.keysAvailable = !!data.keysAvailable;
      this.synced = true;
    },
    pull: function (cb) {
      if (!CFG.loggedIn || !CFG.restUrl || !window.fetch) { if (cb) cb(false); return; }
      var self = this;
      window.fetch(CFG.restUrl, { credentials: 'same-origin', headers: { 'X-WP-Nonce': CFG.nonce || '' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data) { self.applyServer(data); self.saveLocal(); self.push(); } if (cb) cb(!!data); })
        .catch(function () { if (cb) cb(false); });
    },
    push: function () {
      if (!CFG.loggedIn || !CFG.restUrl || !window.fetch) return;
      var self = this;
      window.fetch(CFG.restUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' }, body: JSON.stringify({ progress: this.progress }) })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) { if (data) { self.applyServer(data); self.saveLocal(); } })
        .catch(function () { /* offline: local copy keeps playing; next load re-syncs */ });
    },
    save: function () { this.saveLocal(); this.push(); },
    unlock: function (levelId, cb) {
      if (!CFG.loggedIn || !CFG.restUrl || !window.fetch) { cb({ error: 'offline' }); return; }
      var self = this;
      window.fetch(CFG.restUrl.replace(new RegExp('[/]progress[/]?$'), '') + '/unlock', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' }, body: JSON.stringify({ level: levelId }) })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
          if (res.ok && res.body) { self.applyServer(res.body); self.saveLocal(); cb({ ok: true, body: res.body }); }
          else cb({ error: (res.body && (res.body.message || res.body.code)) || 'error' });
        })
        .catch(function () { cb({ error: 'network' }); });
    },
    draftKey: function (levelId, variation, stage) { return levelId + ':' + variation + ':' + (stage || 0); },
    getDraft: function (k) { return this.drafts[k] ? clone(this.drafts[k]) : null; },
    setDraft: function (k, design) { this.drafts[k] = clone(design); this.saveLocal(); }
  };

  /* -------------------------------------------------------------- audio */
  var audio = {
    ctx: null,
    ready: function () {
      if (!store.progress.settings.sound) return null;
      try { if (!this.ctx) this.ctx = new (window.AudioContext || window.webkitAudioContext)(); if (this.ctx.state === 'suspended') this.ctx.resume(); return this.ctx; } catch (e) { return null; }
    },
    tone: function (freq, dur, type, vol) {
      var c = this.ready(); if (!c) return;
      try {
        var o = c.createOscillator(), g = c.createGain();
        o.type = type || 'sine'; o.frequency.value = freq;
        g.gain.value = vol || 0.05; g.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + dur);
        o.connect(g); g.connect(c.destination); o.start(); o.stop(c.currentTime + dur);
      } catch (e) { /* ignore */ }
    },
    place: function () { this.tone(520, 0.08, 'triangle'); },
    run: function () { this.tone(330, 0.12, 'square', 0.03); },
    bounce: function () { this.tone(220, 0.06, 'sine', 0.03); },
    fail: function () { this.tone(260, 0.18, 'sine'); var s = this; setTimeout(function () { s.tone(200, 0.22, 'sine'); }, 140); },
    success: function () { var s = this; [523, 659, 784, 1047].forEach(function (f, i) { setTimeout(function () { s.tone(f, 0.18, 'triangle', 0.06); }, i * 110); }); }
  };

  var AXO_SVG = '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M18 55 Q2 48 6 62 Q14 60 22 60 Z" fill="#f59fb8"/><ellipse cx="42" cy="56" rx="26" ry="14" fill="#f7a8c0"/><circle cx="70" cy="50" r="17" fill="#f9b8cc"/><g stroke="#e0527d" stroke-width="3.5" stroke-linecap="round"><path d="M60 38 L52 26"/><path d="M64 35 L60 22"/><path d="M69 34 L69 21"/><path d="M60 62 L52 74"/><path d="M64 65 L60 78"/></g><circle cx="76" cy="46" r="3" fill="#2b1b24"/><circle cx="77" cy="45" r="1" fill="#fff"/><path d="M76 56 Q80 59 84 55" stroke="#2b1b24" stroke-width="2" fill="none" stroke-linecap="round"/><path d="M36 68 L32 76 M50 68 L48 76" stroke="#f59fb8" stroke-width="5" stroke-linecap="round"/></svg>';

  /* ================================================================ Game */
  function Game(root) {
    this.root = root;
    this.screen = 'map';
    this.sim = null; this.raf = null; this.acc = 0; this.lastFrame = 0;
    this.ghost = {};
    this.failedRuns = {};
    this.build();
    this.showMap();
    var self = this;
    store.pull(function () { self.refreshHeader(); if (self.screen === 'map') self.showMap(); else if (self.screen === 'level') self.renderChallenges(); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) self.pauseSim(); else self.resumeSim(); });
    window.addEventListener('pagehide', function () { store.saveLocal(); });
    window.addEventListener('resize', function () { self.sizeCanvas(); self.draw(); });
  }

  Game.prototype.build = function () {
    var root = this.root, self = this;
    root.innerHTML = '';
    root.id = 'rl-game';
    var mascot = CFG.mascotUrl;
    if (mascot) { var m = el('div', 'rl-mascot'); var img = el('img'); img.alt = 'STEM-a-lotl, the Ascend axolotl mascot'; img.onerror = function () { m.hidden = true; }; img.src = mascot; m.appendChild(img); root.appendChild(m); }
    root.appendChild(el('h1', 'rl-title', 'STEM-a-lotl: Rescue Lab'));
    root.appendChild(el('p', 'rl-subtitle', 'Build, run, watch, improve. Help ' + GUIDE + ' the axolotl bring the research station back to life.'));
    this.stats = el('div', 'rl-stats'); root.appendChild(this.stats);
    var row = el('div', 'rl-toggle-row');
    this.helpBtn = btn('rl-toggle', 'How to play ▾', function () { self.toggleHelp(); });
    this.soundBtn = btn('rl-toggle', '', function () { self.setSetting('sound', !store.progress.settings.sound); });
    this.ghostBtn = btn('rl-toggle', '', function () { self.setSetting('ghost', !store.progress.settings.ghost); });
    this.assistBtn = btn('rl-toggle', '', function () { self.setSetting('assist', !store.progress.settings.assist); });
    row.appendChild(this.helpBtn); row.appendChild(this.soundBtn); row.appendChild(this.ghostBtn); row.appendChild(this.assistBtn);
    root.appendChild(row);
    this.help = el('div', 'rl-instructions'); this.help.hidden = true; root.appendChild(this.help);
    this.live = el('div', 'rl-live'); this.live.setAttribute('aria-live', 'polite'); root.appendChild(this.live);
    this.area = el('div', 'rl-play-area'); root.appendChild(this.area);
    var foot = el('details', 'rl-foot');
    foot.appendChild(el('summary', null, 'For parents & teachers'));
    this.footBody = el('div'); foot.appendChild(this.footBody);
    root.appendChild(foot);
    var exit = el('div', 'rl-exit');
    var a = el('a', null, '← Back to the games hub'); a.href = CFG.hubUrl || '/user/'; exit.appendChild(a);
    root.appendChild(exit);
    this.overlay = el('div', 'rl-overlay'); this.overlay.setAttribute('role', 'dialog'); this.overlay.setAttribute('aria-modal', 'true');
    this.overlay.addEventListener('click', function (e) { if (e.target === self.overlay) self.closeOverlay(); });
    document.body.appendChild(this.overlay);
    this.refreshHeader();
  };

  Game.prototype.setSetting = function (k, v) { store.progress.settings[k] = v; store.save(); this.refreshHeader(); if (k === 'ghost') this.draw(); if (k === 'assist' && this.screen === 'level') this.renderDiag(); };

  Game.prototype.refreshHeader = function () {
    var s = store.progress.settings, sum = Progress.summary(store.progress);
    this.soundBtn.textContent = 'Sound: ' + (s.sound ? 'on' : 'off'); this.soundBtn.setAttribute('aria-pressed', String(s.sound));
    this.ghostBtn.textContent = 'Ghost trail: ' + (s.ghost ? 'on' : 'off'); this.ghostBtn.setAttribute('aria-pressed', String(s.ghost));
    this.assistBtn.textContent = 'Assist: ' + (s.assist ? 'on' : 'off'); this.assistBtn.setAttribute('aria-pressed', String(s.assist));
    var keys;
    if (CFG.preview) keys = 'Keys: <strong>preview</strong>';
    else if (!CFG.loggedIn) keys = 'Keys: <strong>log in</strong>';
    else if (store.hasPass) keys = 'Pond Pass: <strong>active</strong>';
    else if (store.keys != null) keys = 'Pond Keys: <strong>' + store.keys + '</strong>';
    else keys = 'Pond Keys: <strong>…</strong>';
    this.stats.innerHTML = '<div class="rl-stat">Missions: <strong>' + sum.missions + '</strong> / ' + Levels.LEVELS.length + '</div>' +
      '<div class="rl-stat">Badges: <strong>' + sum.badges + '</strong></div>' +
      '<div class="rl-stat">' + keys + '</div>';
    this.footBody.innerHTML = '<p>Rescue Lab practices engineering thinking: build something, run it, watch what happens, and change one thing. Each mission has one learning idea (ramps and gravity, beams and supports, balance, launch energy, forces, gear ratios, closed circuits, sequencing and loops).</p>' +
      '<p>Progress so far: ' + sum.missions + ' mission' + (sum.missions === 1 ? '' : 's') + ' completed, ' + sum.badges + ' badge' + (sum.badges === 1 ? '' : 's') + ', ' + sum.designs + ' saved invention' + (sum.designs === 1 ? '' : 's') + ', ' + sum.runs + ' experiment run' + (sum.runs === 1 ? '' : 's') + '. ' +
      (sum.concepts.length ? 'Ideas practised: ' + esc(sum.concepts.join('; ')) + '.' : '') + '</p>' +
      '<p>These are practice observations from play, not a graded assessment, attendance, or course credit. School records stay separate.</p>' +
      '<p>Keys: the first three missions are free. One Pond Key permanently opens one more mission, with its challenges and variations included; reopening it never costs another key.' + (CFG.loggedIn ? '' : ' Log in to use keys and keep progress across devices.') + '</p>';
  };

  Game.prototype.toggleHelp = function () {
    var hidden = this.help.hidden;
    this.help.innerHTML = '<p><strong>Build.</strong> Tap a part in the tray, then tap the scene to place it, or drag a part around. Tap a part to select it, then use Rotate, Turn or Remove.</p>' +
      '<p><strong>Run.</strong> Press Run and watch. If it misses, ' + GUIDE + ' tells you what actually happened. Your build stays where it is, so change one thing and try again.</p>' +
      '<p><strong>Celebrate.</strong> Every delivery brings part of the station back to life. Try the optional badge challenges, or save your design to the inventions shelf.</p>' +
      '<p><strong>Ghost trail</strong> shows the path from your last run. <strong>Assist</strong> offers a nudge after a couple of misses without solving the mission for you.</p>';
    this.help.hidden = !hidden;
    this.helpBtn.textContent = hidden ? 'Hide instructions ▴' : 'How to play ▾';
  };

  /* ------------------------------------------------------------- map */
  Game.prototype.showMap = function () {
    this.stopSim();
    this.screen = 'map';
    var area = this.area, self = this;
    area.innerHTML = '';
    var next = this.nextLevel();
    var top = el('div', 'rl-row');
    var play = btn('rl-btn primary big', next.done ? 'Play again' : (Progress.summary(store.progress).missions ? 'Continue: ' + next.name : 'Play'), function () { self.openLevel(next.id, 0); });
    top.appendChild(play);
    area.appendChild(top);
    area.appendChild(this.renderStation());
    if (CFG.preview) {
      var n = el('div', 'rl-notice'); n.innerHTML = 'Preview build. Missions 4–10 are opened here for review only. On the Ascend site they open with Pond Keys through the existing parent-controlled shop. Nothing here is a purchase.'; area.appendChild(n);
    } else if (!CFG.loggedIn) {
      var n2 = el('div', 'rl-notice'); n2.innerHTML = 'You can play the three free missions right away. <a href="' + esc(CFG.loginUrl || '/login/') + '">Log in</a> to keep progress across devices and use Pond Keys for missions 4–10.'; area.appendChild(n2);
    }
    var list = el('div', 'rl-levels');
    Levels.LEVELS.forEach(function (lvl) {
      var st = Progress.levelState(store.progress, lvl, store.owned, store.hasPass);
      var b = btn('rl-level' + (st.access === 'locked' && !CFG.preview ? ' is-locked' : '') + (lvl.id === next.id ? ' is-next' : ''), '', function () {
        if (st.access === 'locked' && !CFG.preview) self.openUnlock(lvl); else self.openLevel(lvl.id, 0);
      });
      b.innerHTML = '<span class="rl-lnum">Mission ' + lvl.id + '</span><span class="rl-lname">' + esc(lvl.name) + '</span><span class="rl-lconcept">' + esc(lvl.concept) + '</span>' +
        '<span class="rl-badges">' + ['rescue', 'efficiency', 'invention'].map(function (k) { return '<span class="rl-badge' + (st.badges[k] ? ' earned' : '') + '">' + (st.badges[k] ? '★' : '☆') + ' ' + k + '</span>'; }).join('') + '</span>';
      var chip;
      if (st.status === 'mastery') chip = ['mastery', 'Mastered'];
      else if (st.done) chip = ['done', 'Completed' + (lvl.variations.length > 1 ? ' · ' + st.variationsDone + '/' + lvl.variations.length + ' variations' : '')];
      else if (st.access === 'free') chip = ['free', 'Free'];
      else if (st.access === 'owned') chip = ['owned', 'Owned · ready'];
      else chip = ['locked', CFG.preview ? 'Preview' : '1 Pond Key'];
      var c = el('span', 'rl-chip ' + chip[0], chip[1]); b.appendChild(c);
      b.setAttribute('aria-label', 'Mission ' + lvl.id + ' ' + lvl.name + ', ' + chip[1]);
      list.appendChild(b);
    });
    area.appendChild(list);
    // rotating practice challenge, free or owned content only
    var pr = Progress.practice(store.progress, Progress.weekKey(new Date()), store.owned, store.hasPass);
    if (pr) {
      var card = el('div', 'rl-card' + (pr.done ? ' done' : ''));
      card.innerHTML = '<h3>This week’s practice challenge' + (pr.done ? ' ✓' : '') + '</h3><p><strong>' + esc(pr.level.name) + '</strong> · ' + esc(pr.level.variations[pr.variation].name) + '</p><p>' + esc(pr.challenge.text) + '</p><p class="rl-fine">Open all week. It never resets anything you earned.</p>';
      var r = el('div', 'rl-row left'); r.appendChild(btn('rl-btn blue', pr.done ? 'Play it again' : 'Try it', function () { self.openLevel(pr.level.id, pr.variation); })); card.appendChild(r);
      area.appendChild(card);
    }
    var sum = Progress.summary(store.progress);
    if (sum.missions >= Levels.FREE_LEVELS && !store.hasPass && !CFG.preview) {
      var nextLocked = Levels.LEVELS.filter(function (l) { return Progress.levelState(store.progress, l, store.owned, store.hasPass).access === 'locked'; })[0];
      if (nextLocked) {
        var c2 = el('div', 'rl-card sand');
        c2.innerHTML = '<h3>Nice work, engineer!</h3><p>You finished the free missions, and you can replay them any time. Next up: <strong>' + esc(nextLocked.name) + '</strong> — ' + esc(nextLocked.concept.toLowerCase()) + '.</p>';
        var r2 = el('div', 'rl-row left'); r2.appendChild(btn('rl-btn pass', 'Preview mission ' + nextLocked.id, function () { self.openUnlock(nextLocked); })); c2.appendChild(r2);
        area.appendChild(c2);
      }
    }
    this.live.textContent = '';
    this.refreshHeader();
  };

  Game.prototype.nextLevel = function () {
    for (var i = 0; i < Levels.LEVELS.length; i++) {
      var l = Levels.LEVELS[i], st = Progress.levelState(store.progress, l, store.owned, store.hasPass);
      if (!st.done && (st.access !== 'locked' || CFG.preview)) return l;
    }
    return Levels.LEVELS[0];
  };

  /* The living station map: one small SVG, ten areas, each on or off. */
  Game.prototype.renderStation = function () {
    var wrap = el('div', 'rl-station');
    var lit = {}, decor = Progress.decorations(store.progress);
    Levels.LEVELS.forEach(function (l) { var lv = store.progress.levels[l.id]; if (lv && lv.done) lit[l.area] = true; });
    var all = lit.station;
    var s = '<svg viewBox="0 0 200 74" role="img" aria-label="Station map: ' + Object.keys(lit).length + ' of 10 areas restored">';
    s += '<rect x="0" y="60" width="200" height="14" fill="#BFE0B4"/><rect x="0" y="60" width="200" height="1.2" fill="#1D4010"/>';
    var areas = [
      ['dock', 6, 34, 30, 26, 'Dock'], ['bridge', 40, 44, 26, 16, 'Bridge'], ['greenhouse', 70, 30, 26, 30, 'Greenhouse'], ['launcher', 100, 40, 20, 20, 'Launcher'], ['vents', 124, 34, 22, 26, 'Vents'],
      ['platform', 150, 8, 26, 20, 'Platform'], ['lab', 150, 32, 44, 28, 'Lab'], ['rover', 100, 10, 24, 22, 'Rover bay'], ['routes', 40, 14, 54, 12, 'Routes'], ['station', 6, 6, 28, 22, 'Core']
    ];
    areas.forEach(function (a) {
      var on = lit[a[0]] || all;
      s += '<g><rect x="' + a[1] + '" y="' + a[2] + '" width="' + a[3] + '" height="' + a[4] + '" rx="3" class="' + (on ? 'rl-area-on' : 'rl-area-off') + '" stroke-width="1"/>';
      s += '<text x="' + (a[1] + a[3] / 2) + '" y="' + (a[2] + a[4] / 2 + 2) + '" text-anchor="middle">' + a[5] + '</text>';
      if (on) s += '<circle cx="' + (a[1] + a[3] - 4) + '" cy="' + (a[2] + 4) + '" r="1.8" fill="#ffd166" class="rl-glow"/>';
      s += '</g>';
    });
    if (lit.greenhouse || all) s += '<g fill="#E07FA3"><circle cx="76" cy="26" r="2.4"/><circle cx="84" cy="24" r="2.4"/><circle cx="92" cy="27" r="2.4"/></g><g stroke="#1D4010" stroke-width="1"><path d="M76 28v4M84 26v6M92 29v3"/></g>';
    if (lit.bridge || all) s += '<path d="M40 44 Q53 36 66 44" stroke="#045C82" stroke-width="1.5" fill="none"/>';
    if (lit.rover || all) s += '<rect x="106" y="24" width="10" height="5" rx="1.5" fill="#045C82"/><circle cx="108" cy="30" r="1.6" fill="#24332a"/><circle cx="114" cy="30" r="1.6" fill="#24332a"/>';
    if (lit.launcher || all) s += '<path d="M104 40 L112 30" stroke="#E07FA3" stroke-width="2"/>';
    if (lit.vents || all) s += '<g stroke="#009CDE" stroke-width="1" fill="none"><path d="M128 40h14M128 44h14M128 48h14"/></g>';
    if (lit.lab || all) s += '<circle cx="172" cy="40" r="3" fill="#ffd166" class="rl-glow"/>';
    if (lit.platform || all) s += '<path d="M156 26 L168 12" stroke="#045C82" stroke-width="1.5"/><circle cx="168" cy="12" r="1.5" fill="#045C82"/>';
    if (lit.routes || all) s += '<path d="M42 20 H92" stroke="#E07FA3" stroke-width="1" stroke-dasharray="3 2"/>';
    if (all) s += '<g fill="#ffd166"><circle cx="20" cy="4" r="1.5" class="rl-glow"/><circle cx="60" cy="10" r="1.5" class="rl-glow"/><circle cx="130" cy="6" r="1.5" class="rl-glow"/><circle cx="190" cy="4" r="1.5" class="rl-glow"/></g>';
    s += '</svg>';
    wrap.innerHTML = s;
    var d = el('div', 'rl-decor-row');
    if (decor.length) decor.forEach(function (x) { d.appendChild(el('span', 'rl-decor', x.label)); });
    else d.appendChild(el('span', null, 'Complete missions to earn habitat decorations.'));
    wrap.appendChild(d);
    return wrap;
  };

  /* ------------------------------------------------------ unlock flow */
  Game.prototype.openUnlock = function (lvl) {
    var self = this, m = el('div', 'rl-modal');
    var keys = store.keys, cost = Levels.KEY_COST;
    var html = '<h3>Mission ' + lvl.id + ': ' + esc(lvl.name) + '</h3><p class="rl-sub">' + esc(lvl.objective) + '</p>' +
      '<ul><li><strong>New tool:</strong> ' + esc(lvl.tool) + '</li><li><strong>Practises:</strong> ' + esc(lvl.concept) + '</li><li><strong>Included:</strong> the main mission, ' + lvl.challenges.length + ' optional badge challenges and ' + lvl.variations.length + ' tested variation' + (lvl.variations.length > 1 ? 's' : '') + ', all replayable forever</li>' +
      '<li><strong>Needs:</strong> nothing else first — every mission can be played on its own' + (lvl.id > 1 ? ' (mission ' + (lvl.id - 1) + ' is a friendly warm-up)' : '') + '</li></ul>';
    if (CFG.preview) {
      html += '<p class="rl-fine">Preview build: on the Ascend site this mission opens with ' + cost + ' Pond Key from the shop. Keys are never redeemed here.</p>';
    } else if (!CFG.loggedIn) {
      html += '<p class="rl-keys">Cost: ' + cost + ' Pond Key</p><p class="rl-fine">Log in to the student account to use keys. Keys are bought by a parent in the Ascend shop and never expire.</p>';
    } else if (!store.keysAvailable) {
      html += '<p class="rl-fine">The key system is not reachable right now, so this mission cannot be opened yet. Your progress is safe; please try again later.</p>';
    } else {
      html += '<p class="rl-keys">Cost: ' + cost + ' Pond Key · You have ' + (keys == null ? '…' : keys) + (keys != null ? ' → ' + Math.max(0, keys - cost) + ' after' : '') + '</p>' +
        '<p class="rl-fine">One key opens this mission permanently on this student account. Reopening it never costs another key.</p>';
    }
    m.innerHTML = html;
    var row = el('div', 'rl-row');
    if (CFG.preview) row.appendChild(btn('rl-btn pass', 'Open for review', function () { self.closeOverlay(); self.openLevel(lvl.id, 0); }));
    else if (!CFG.loggedIn) { var a = el('a', 'rl-btn pass', 'Log in'); a.href = CFG.loginUrl || '/login/'; row.appendChild(a); }
    else if (store.keysAvailable && keys != null && keys >= cost) {
      var u = btn('rl-btn pass', 'Unlock with ' + cost + ' key', function () {
        u.disabled = true; u.textContent = 'Unlocking…';
        store.unlock(lvl.id, function (res) {
          if (res.ok) { self.closeOverlay(); self.refreshHeader(); self.openLevel(lvl.id, 0); self.live.textContent = 'Mission ' + lvl.id + ' is yours. It stays unlocked on this account.'; }
          else { u.disabled = false; u.textContent = 'Unlock with ' + cost + ' key'; var e = el('p', 'rl-fine', res.error === 'network' ? 'No connection. Nothing was spent. Try again when you are back online.' : ('Could not unlock: ' + res.error + '. No key was taken.')); m.appendChild(e); }
        });
      });
      row.appendChild(u);
    } else if (store.keysAvailable) {
      if (CFG.buyUrl) { var b = el('a', 'rl-btn pass', 'Get a Pond Key (parent shop)'); b.href = CFG.buyUrl; row.appendChild(b); }
      if (CFG.passUrl) { var p = el('a', 'rl-btn', 'Full Pond Pass'); p.href = CFG.passUrl; row.appendChild(p); }
    }
    row.appendChild(btn('rl-btn', 'Not now', function () { self.closeOverlay(); }));
    m.appendChild(row);
    this.showOverlay(m);
  };

  Game.prototype.showOverlay = function (content) { this.overlay.innerHTML = ''; this.overlay.appendChild(content); this.overlay.classList.add('show'); var f = content.querySelector('button, a'); if (f) f.focus(); };
  Game.prototype.closeOverlay = function () { this.overlay.classList.remove('show'); this.overlay.innerHTML = ''; };

  /* ----------------------------------------------------------- level */
  Game.prototype.openLevel = function (levelId, variation) {
    var lvl = Levels.LEVELS[levelId - 1];
    var st = Progress.levelState(store.progress, lvl, store.owned, store.hasPass);
    if (st.access === 'locked' && !CFG.preview) { this.openUnlock(lvl); return; }
    this.stopSim();
    this.screen = 'level';
    this.level = lvl; this.variation = variation || 0;
    this.stage = lvl.mode === 'sequence' ? Math.min((store.progress.levels[lvl.id] || {}).stage || 0, lvl.stages.length - 1) : 0;
    this.loadStage();
  };

  Game.prototype.loadStage = function () {
    var lvl = this.level;
    this.spec = Engine.resolve(lvl, this.variation, this.stage);
    var key = store.draftKey(lvl.id, this.variation, this.stage);
    this.design = store.getDraft(key) || Engine.newDesign(this.spec);
    this.undo = []; this.selected = null; this.placing = null; this.lastRun = null; this.drag = null; this.stack = []; this.finalSim = null;
    this.repeatCount = 3;
    if (!this.failedRuns[lvl.id]) this.failedRuns[lvl.id] = 0;
    this.renderLevel();
    this.say(lvl.mode === 'sequence' ? this.spec.name + '. ' + lvl.intro : lvl.intro, 'intro');
    var v = lvl.variations[this.variation];
    this.live.textContent = v.name === 'Main mission' ? '' : 'Variation: ' + v.name;
  };

  Game.prototype.renderLevel = function () {
    var self = this, lvl = this.level, spec = this.spec, area = this.area;
    area.innerHTML = '';
    var head = el('div', 'rl-levelhead');
    var h = el('div'); h.innerHTML = '<span class="rl-lnum">Mission ' + lvl.id + '</span>'; var h2 = el('h2', null, lvl.name); h.appendChild(h2); head.appendChild(h);
    head.appendChild(btn('rl-btn', '← Station map', function () { self.showMap(); }));
    area.appendChild(head);
    if (lvl.variations.length > 1) {
      var vr = el('div', 'rl-variants'); vr.setAttribute('role', 'group'); vr.setAttribute('aria-label', 'Variations');
      lvl.variations.forEach(function (v, i) {
        var done = !!((store.progress.levels[lvl.id] || { variations: {} }).variations[i]);
        var b = btn('' + (done ? 'done' : ''), v.name, function () { self.stopSim(); self.variation = i; self.loadStage(); });
        b.setAttribute('aria-pressed', String(i === self.variation)); vr.appendChild(b);
      });
      area.appendChild(vr);
    }
    if (lvl.mode === 'sequence') {
      var sb = el('div', 'rl-stagebar');
      lvl.stages.forEach(function (s, i) { sb.appendChild(el('span', i < self.stage ? 'done' : (i === self.stage ? 'on' : ''), s.name)); });
      area.appendChild(sb);
    }
    var obj = el('div', 'rl-objective');
    this.guide = el('div', 'rl-guide'); this.guide.innerHTML = AXO_SVG; obj.appendChild(this.guide);
    this.bubble = el('div', 'rl-bubble'); obj.appendChild(this.bubble); area.appendChild(obj);

    this.sceneWrap = el('div', 'rl-scene-wrap');
    this.canvas = el('canvas', 'rl-scene'); this.canvas.setAttribute('role', 'img'); this.canvas.setAttribute('aria-label', lvl.objective);
    this.sceneWrap.appendChild(this.canvas); area.appendChild(this.sceneWrap);
    this.bindPointer();

    this.tray = el('div', 'rl-tray'); area.appendChild(this.tray);
    this.tools = el('div', 'rl-tools'); area.appendChild(this.tools);
    this.editor = el('div'); area.appendChild(this.editor);

    var controls = el('div', 'rl-controls');
    this.runBtn = btn('rl-btn run', '▶ Run', function () { self.run(); });
    this.undoBtn = btn('rl-btn', 'Undo', function () { self.doUndo(); });
    this.resetBtn = btn('rl-btn', 'Reset build', function () { self.resetDesign(); });
    controls.appendChild(this.runBtn); controls.appendChild(this.undoBtn); controls.appendChild(this.resetBtn);
    area.appendChild(controls);
    this.message = el('div', 'rl-message'); this.message.setAttribute('role', 'status'); area.appendChild(this.message);
    this.diagBox = el('div'); area.appendChild(this.diagBox);
    this.successBox = el('div'); area.appendChild(this.successBox);
    this.challengeBox = el('div', 'rl-challenges'); area.appendChild(this.challengeBox);
    this.shelfBox = el('div', 'rl-shelf'); area.appendChild(this.shelfBox);

    this.sizeCanvas();
    this.renderTray(); this.renderTools(); this.renderEditor(); this.renderChallenges(); this.renderShelf();
    this.draw();
    if (this.canvas.scrollIntoView && window.innerWidth < 700) this.canvas.scrollIntoView({ block: 'nearest' });
  };

  Game.prototype.say = function (text, mood) {
    var self = this;
    if (!this.bubble) return;
    this.bubble.innerHTML = '<span class="rl-who">' + GUIDE + ' the axolotl</span>' + esc(text);
    this.guide.className = 'rl-guide' + (mood === 'happy' ? ' happy' : (mood === 'puzzled' ? ' puzzled' : ''));
    if (mood === 'happy' && CFG.mascotHappyUrl && !this.mascotFailed) {
      var img = el('img'); img.alt = ''; var g = this.guide;
      img.onerror = function () { self.mascotFailed = true; g.innerHTML = AXO_SVG; };
      img.src = CFG.mascotHappyUrl; this.guide.innerHTML = ''; this.guide.appendChild(img);
    } else this.guide.innerHTML = AXO_SVG;
  };

  Game.prototype.setMessage = function (text) { this.message.textContent = text || ''; };

  /* ------------------------------------------------------- tray/tools */
  Game.prototype.renderTray = function () {
    var self = this, spec = this.spec, tray = this.tray, mode = spec.mode;
    tray.innerHTML = '';
    if (mode === 'physics' || mode === 'bridge') {
      spec.tray.forEach(function (t) {
        var used = Engine.trayUsed(self.design, t.type, t.len), left = t.count - used;
        var b = btn('rl-tray-btn', '', function () { self.placing = (self.placing && self.placing.type === t.type && self.placing.len === t.len) ? null : t; self.selected = null; self.renderTray(); self.renderTools(); self.draw(); self.setMessage(self.placing ? 'Tap the scene to place the ' + partLabel(t).toLowerCase() + '.' : ''); });
        b.innerHTML = trayIcon(t.type) + ' ' + esc(partLabel(t)) + ' <small>×' + left + '</small>';
        b.disabled = left <= 0 || !!self.sim;
        b.setAttribute('aria-pressed', String(!!(self.placing && self.placing.type === t.type && self.placing.len === t.len)));
        tray.appendChild(b);
      });
      if (spec.scene.launcher) {
        var L = spec.scene.launcher, d = this.design.launcher || (this.design.launcher = { power: L.power, angle: L.angle });
        var dials = el('div', 'rl-dials');
        dials.appendChild(this.dial('Power', d.power, L.powers, function (v) { d.power = v; self.afterEdit(); }));
        dials.appendChild(this.dial('Angle', d.angle, L.angles, function (v) { d.angle = v; self.afterEdit(); }, '°'));
        tray.appendChild(dials);
      }
    } else if (mode === 'circuit') {
      spec.tray.forEach(function (t) {
        var used = (self.design.tiles || []).filter(function (x) { return x.type === t.type; }).length, left = t.count - used;
        var b = btn('rl-tray-btn', '', function () { self.placing = (self.placing && self.placing.type === t.type) ? null : t; self.selected = null; self.renderTray(); self.renderTools(); self.draw(); self.setMessage(self.placing ? 'Tap an empty cell to place the ' + partLabel(t).toLowerCase() + '. Tap a placed tile to rotate it.' : ''); });
        b.innerHTML = trayIcon(t.type) + ' ' + esc(partLabel(t)) + ' <small>×' + left + '</small>';
        b.disabled = left <= 0 || !!self.sim;
        b.setAttribute('aria-pressed', String(!!(self.placing && self.placing.type === t.type)));
        tray.appendChild(b);
      });
    } else if (mode === 'gears') {
      spec.scene.gears.forEach(function (teeth) {
        var b = btn('rl-tray-btn', '', function () { self.placing = { type: 'gear', teeth: teeth }; self.renderTray(); self.setMessage('Tap the middle slot or the lift slot to put this gear there.'); self.draw(); });
        b.innerHTML = trayIcon('gear') + ' ' + teeth + ' teeth';
        b.setAttribute('aria-pressed', String(!!(self.placing && self.placing.teeth === teeth)));
        b.disabled = !!self.sim;
        tray.appendChild(b);
      });
    } else if (mode === 'balance') {
      tray.appendChild(el('span', 'rl-toolname', 'Drag each crate onto a slot, or tap a crate and then a slot. Numbers are the crate masses.'));
    }
  };

  Game.prototype.dial = function (label, value, options, onchange, unit) {
    var self = this, d = el('div', 'rl-dial'), i = options.indexOf(value);
    var minus = btn('rl-btn', '−', function () { if (i > 0) { i--; onchange(options[i]); v.textContent = options[i] + (unit || ''); } }, 'Less ' + label);
    var v = el('strong', null, value + (unit || ''));
    var plus = btn('rl-btn', '+', function () { if (i < options.length - 1) { i++; onchange(options[i]); v.textContent = options[i] + (unit || ''); } }, 'More ' + label);
    d.appendChild(el('span', null, label)); d.appendChild(minus); d.appendChild(v); d.appendChild(plus);
    return d;
  };

  function trayIcon(type) {
    var m = {
      ramp: '<svg class="rl-ico" viewBox="0 0 26 16"><path d="M2 12 L24 4" stroke="#E07FA3" stroke-width="4" stroke-linecap="round"/></svg>',
      beam: '<svg class="rl-ico" viewBox="0 0 26 16"><rect x="2" y="6" width="22" height="4" rx="2" fill="#c9a36b"/></svg>',
      post: '<svg class="rl-ico" viewBox="0 0 26 16"><rect x="11" y="2" width="4" height="12" rx="1.5" fill="#8fa3ad"/></svg>',
      bumper: '<svg class="rl-ico" viewBox="0 0 26 16"><rect x="10" y="2" width="6" height="12" rx="2" fill="#ffb347"/></svg>',
      fan: '<svg class="rl-ico" viewBox="0 0 26 16"><circle cx="8" cy="8" r="6" fill="#009CDE"/><path d="M16 5h8M16 8h8M16 11h8" stroke="#009CDE" stroke-width="1.5"/></svg>',
      barrier: '<svg class="rl-ico" viewBox="0 0 26 16"><rect x="2" y="6" width="22" height="4" rx="2" fill="#045C82"/></svg>',
      wire: '<svg class="rl-ico" viewBox="0 0 26 16"><path d="M2 8h22" stroke="#045C82" stroke-width="3"/></svg>',
      corner: '<svg class="rl-ico" viewBox="0 0 26 16"><path d="M13 2v6h11" stroke="#045C82" stroke-width="3" fill="none"/></svg>',
      switch: '<svg class="rl-ico" viewBox="0 0 26 16"><path d="M2 8h8M16 8h8" stroke="#045C82" stroke-width="3"/><path d="M10 8l6-5" stroke="#E07FA3" stroke-width="2.5"/></svg>',
      gear: '<svg class="rl-ico" viewBox="0 0 26 16"><circle cx="13" cy="8" r="6" fill="none" stroke="#045C82" stroke-width="3" stroke-dasharray="2 2"/></svg>'
    };
    return m[type] || '';
  }

  Game.prototype.renderTools = function () {
    var self = this, tools = this.tools, mode = this.spec.mode;
    tools.innerHTML = '';
    if (this.sim) return;
    if ((mode === 'physics' || mode === 'bridge') && this.selected != null) {
      var p = this.design.parts[this.selected];
      if (!p) { this.selected = null; return; }
      tools.appendChild(el('span', 'rl-toolname', partLabel(p) + (p.angle != null ? ' · ' + p.angle + '°' : '')));
      if (p.type === 'ramp' || p.type === 'beam' || p.type === 'barrier') {
        tools.appendChild(btn('rl-btn', '↺ Rotate −15°', function () { self.pushUndo(); p.angle = clamp((p.angle || 0) - 15, -75, 75); self.afterEdit(); }));
        tools.appendChild(btn('rl-btn', '↻ Rotate +15°', function () { self.pushUndo(); p.angle = clamp((p.angle || 0) + 15, -75, 75); self.afterEdit(); }));
        tools.appendChild(btn('rl-btn', 'Flat', function () { self.pushUndo(); p.angle = 0; self.afterEdit(); }));
      }
      if (p.type === 'fan') tools.appendChild(btn('rl-btn', 'Turn fan (' + ({ E: 'right', S: 'down', W: 'left', N: 'up' })[p.dir || 'E'] + ')', function () { self.pushUndo(); p.dir = { E: 'S', S: 'W', W: 'N', N: 'E' }[p.dir || 'E']; self.afterEdit(); }));
      tools.appendChild(btn('rl-btn', 'Remove', function () { self.pushUndo(); self.design.parts.splice(self.selected, 1); self.selected = null; self.afterEdit(); }));
    } else if (mode === 'circuit' && this.selected) {
      var t = this.selected.tile;
      if (t.type === 'fixed') {
        tools.appendChild(el('span', 'rl-toolname', t.kind === 'battery' ? 'Battery' : 'Lamp'));
        tools.appendChild(btn('rl-btn', '↻ Turn (' + (t.axis === 'V' ? 'up-down' : 'left-right') + ')', function () { self.pushUndo(); self.design.fixedAxis[t.r + ',' + t.c] = t.axis === 'V' ? 'H' : 'V'; self.afterEdit(); }));
      } else {
        tools.appendChild(el('span', 'rl-toolname', partLabel(t)));
        tools.appendChild(btn('rl-btn', '↻ Rotate', function () { self.pushUndo(); t.rot = ((t.rot || 0) + 1) % 4; self.afterEdit(); }));
        if (t.type === 'switch') tools.appendChild(btn('rl-btn', t.closed ? 'Open switch' : 'Close switch', function () { self.pushUndo(); t.closed = !t.closed; self.afterEdit(); }));
        tools.appendChild(btn('rl-btn', 'Remove', function () { self.pushUndo(); self.design.tiles = self.design.tiles.filter(function (x) { return x !== t; }); self.selected = null; self.afterEdit(); }));
      }
    } else if (mode === 'gears') {
      tools.appendChild(el('span', 'rl-toolname', 'Tap a slot to place the chosen gear, or tap it again to clear it.'));
    } else if (mode === 'balance' && this.selected != null) {
      var m = this.spec.scene.crates[this.selected];
      tools.appendChild(el('span', 'rl-toolname', 'Crate of mass ' + m + ' selected. Tap a slot on the cart.'));
      tools.appendChild(btn('rl-btn', 'Take off cart', function () { self.pushUndo(); self.design.placements = self.design.placements.filter(function (p) { return p.crate !== self.selected; }); self.selected = null; self.afterEdit(); }));
    }
  };

  Game.prototype.pushUndo = function () { this.undo.push(clone(this.design)); if (this.undo.length > 40) this.undo.shift(); };
  Game.prototype.doUndo = function () {
    if (this.sim) return;
    if (this.spec.mode === 'code') { this.codeUndo(); return; }
    if (!this.undo.length) { this.setMessage('Nothing to undo.'); return; }
    this.design = this.undo.pop(); this.selected = null; this.placing = null; this.afterEdit(false);
  };
  Game.prototype.resetDesign = function () {
    if (this.sim) return;
    this.pushUndo(); this.design = Engine.newDesign(this.spec); this.selected = null; this.placing = null; this.stack = []; this.afterEdit(false);
    this.setMessage('Build reset to the starting layout. Undo brings it back.');
  };
  Game.prototype.afterEdit = function (keepMessage) {
    Engine.normalizeDesign(this.spec, this.design);
    this.finalSim = null;
    store.setDraft(store.draftKey(this.level.id, this.variation, this.stage), this.design);
    this.renderTray(); this.renderTools(); this.renderEditor(); this.draw();
    if (keepMessage !== true) { /* leave message */ }
    this.successBox.innerHTML = '';
  };

  /* ------------------------------------------------------------ canvas */
  Game.prototype.sizeCanvas = function () {
    if (!this.canvas) return;
    var w = Math.max(280, Math.min(640, this.sceneWrap.clientWidth || 600)), h = Math.round(w * 0.6), dpr = window.devicePixelRatio || 1;
    this.canvas.style.width = w + 'px'; this.canvas.style.height = h + 'px';
    this.canvas.width = Math.round(w * dpr); this.canvas.height = Math.round(h * dpr);
    this.scale = w / 100; this.dpr = dpr;
  };

  Game.prototype.toScene = function (e) {
    var r = this.canvas.getBoundingClientRect();
    return { x: (e.clientX - r.left) / this.scale, y: (e.clientY - r.top) / this.scale };
  };

  Game.prototype.ctx = function () {
    var c = this.canvas.getContext('2d');
    c.setTransform(this.dpr * this.scale, 0, 0, this.dpr * this.scale, 0, 0);
    return c;
  };

  Game.prototype.draw = function () {
    if (!this.canvas || this.screen !== 'level') return;
    var shown = this.sim || this.finalSim, c = this.ctx(), snap = shown ? shown.snapshot() : null, mode = this.spec.mode;
    c.clearRect(0, 0, 100, 60);
    if (mode === 'physics' || mode === 'bridge') this.drawScene(c, snap);
    else if (mode === 'balance') this.drawBalance(c, snap);
    else if (mode === 'gears') this.drawGears(c, snap);
    else if (mode === 'circuit') this.drawCircuit(c, snap);
    else if (mode === 'code') this.drawCode(c, snap);
  };

  function sky(c) {
    var g = c.createLinearGradient(0, 0, 0, 60); g.addColorStop(0, '#dff1fa'); g.addColorStop(1, '#f7fbfd');
    c.fillStyle = g; c.fillRect(0, 0, 100, 60);
    // distant station silhouette
    c.fillStyle = 'rgba(4,92,130,.08)';
    [[4, 30, 14, 26], [22, 38, 10, 18], [66, 34, 12, 22], [84, 28, 12, 28]].forEach(function (r) { roundRect(c, r[0], r[1], r[2], r[3], 2); c.fill(); });
    c.fillStyle = '#BFE0B4'; c.fillRect(0, GROUND, 100, 60 - GROUND);
    c.fillStyle = '#1D4010'; c.fillRect(0, GROUND - 0.5, 100, 0.8);
  }
  function roundRect(c, x, y, w, h, r) {
    c.beginPath(); c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath();
  }
  function label(c, text, x, y, size, color, align) {
    c.font = '700 ' + (size || 2.6) + 'px Roboto, Arial, sans-serif'; c.fillStyle = color || '#173e63'; c.textAlign = align || 'center'; c.textBaseline = 'middle'; c.fillText(text, x, y);
  }

  Game.prototype.drawScene = function (c, snap) {
    var spec = this.spec, sc = spec.scene, self = this, mode = spec.mode;
    sky(c);
    // static geometry
    (sc.static || []).forEach(function (s) {
      if (s.kind === 'ground') return;
      c.strokeStyle = '#045C82'; c.lineWidth = 1.6; c.lineCap = 'round';
      c.beginPath(); c.moveTo(s.x1, s.y1); c.lineTo(s.x2, s.y2); c.stroke();
      if (s.kind === 'platform') { c.fillStyle = 'rgba(4,92,130,.16)'; c.fillRect(Math.min(s.x1, s.x2), s.y1, Math.abs(s.x2 - s.x1), GROUND - s.y1); }
    });
    if (mode === 'bridge') {
      (sc.cliffs || []).forEach(function (cl) { c.fillStyle = '#c9c5b8'; c.fillRect(cl.x1, cl.y, cl.x2 - cl.x1, GROUND - cl.y); c.fillStyle = '#e6e3dc'; c.fillRect(cl.x1, cl.y, cl.x2 - cl.x1, 1.2); });
    }
    // zones
    (sc.zones || []).forEach(function (z) {
      if (z.type === 'basket') {
        c.fillStyle = '#d4e7f2'; c.fillRect(z.x, z.y, z.w, z.h);
        c.strokeStyle = '#045C82'; c.lineWidth = 1.2; c.beginPath(); c.moveTo(z.x, z.y); c.lineTo(z.x, z.y + z.h); c.lineTo(z.x + z.w, z.y + z.h); c.lineTo(z.x + z.w, z.y); c.stroke();
        label(c, 'Basket', z.x + z.w / 2, z.y + z.h - 2.2, 2.4, '#045C82');
      } else if (z.type === 'pad') {
        c.fillStyle = '#ffd166'; c.fillRect(z.x, z.y + z.h - 1, z.w, 1.2);
        c.fillStyle = '#e8bd4a'; for (var i = 0; i < z.w; i += 4) c.fillRect(z.x + i, z.y + z.h - 1, 2, 1.2);
        c.strokeStyle = '#045C82'; c.lineWidth = 1; c.beginPath(); c.moveTo(z.x, z.y + z.h - 3); c.lineTo(z.x, z.y + z.h); c.moveTo(z.x + z.w, z.y + z.h - 3); c.lineTo(z.x + z.w, z.y + z.h); c.stroke();
        label(c, 'Landing pad', z.x + z.w / 2, z.y + 2, 2.4, '#045C82');
      } else if (z.type === 'goal') {
        c.fillStyle = 'rgba(191,224,180,.6)'; c.fillRect(z.x, z.y, z.w, z.h);
        c.fillStyle = '#1D4010'; c.fillRect(z.x + z.w / 2 - 0.4, z.y + 2, 0.8, z.h - 2); c.fillStyle = '#E07FA3'; c.beginPath(); c.moveTo(z.x + z.w / 2, z.y + 2); c.lineTo(z.x + z.w / 2 + 5, z.y + 4); c.lineTo(z.x + z.w / 2, z.y + 6); c.fill();
        label(c, 'Far side', z.x + z.w / 2, z.y + z.h - 1.5, 2.2, '#1D4010');
      }
    });
    // spawn hopper
    if (sc.spawn) { c.fillStyle = '#045C82'; c.beginPath(); c.moveTo(sc.spawn.x - 5, sc.spawn.y - 6); c.lineTo(sc.spawn.x + 5, sc.spawn.y - 6); c.lineTo(sc.spawn.x + 2.5, sc.spawn.y - 2.4); c.lineTo(sc.spawn.x - 2.5, sc.spawn.y - 2.4); c.closePath(); c.fill(); label(c, 'Supply hopper', sc.spawn.x + 7, sc.spawn.y - 4.2, 2.2, '#045C82', 'left'); }
    // launcher
    if (sc.launcher) {
      var L = sc.launcher, d = this.design.launcher || L;
      c.fillStyle = '#045C82'; roundRect(c, L.x - 5, L.y - 2, 10, 4, 1); c.fill();
      c.save(); c.translate(L.x, L.y - 2); c.rotate(-deg2rad(d.angle)); c.fillStyle = '#009CDE'; roundRect(c, 0, -2, 12, 4, 1.5); c.fill(); c.restore();
      for (var p = 0; p < 6; p++) { c.fillStyle = p < d.power ? '#E07FA3' : '#e6e3dc'; c.fillRect(L.x - 7 + p * 2.4, L.y + 3, 1.8, 2); }
      label(c, 'Power ' + d.power + ' · ' + d.angle + '°', L.x + 2, L.y - 9, 2.4, '#045C82');
    }
    // ghost trail
    var g = this.ghost[this.ghostKey()];
    if (g && store.progress.settings.ghost && !this.sim && !this.finalSim) { c.fillStyle = 'rgba(4,92,130,.22)'; g.forEach(function (p, i) { if (i % 2) return; c.beginPath(); c.arc(p[0], p[1], 0.6, 0, Math.PI * 2); c.fill(); }); }
    // parts
    var broken = snap && snap.broken ? snap.broken : [];
    (this.design.parts || []).forEach(function (p, i) { self.drawPart(c, p, i === self.selected, broken.some(function (b) { return b.x === p.x && b.y === p.y && b.type === p.type; })); });
    // placing preview
    if (this.placing && this.hover) { c.globalAlpha = 0.45; this.drawPart(c, this.previewPart(this.hover), false, false); c.globalAlpha = 1; }
    // body
    var b = snap ? snap.body : null;
    if (!b) b = sc.launcher ? { x: sc.launcher.x, y: sc.launcher.y - 4, r: 2.2 } : (sc.spawn ? { x: sc.spawn.x, y: sc.spawn.y, r: 2.2 } : { x: sc.rover.x, y: sc.rover.y, r: sc.rover.r });
    if (mode === 'bridge') drawRover(c, b); else drawCapsule(c, b);
  };

  Game.prototype.ghostKey = function () { return this.level.id + ':' + this.variation + ':' + this.stage; };

  Game.prototype.previewPart = function (pt) {
    var t = this.placing, p = { type: t.type, x: Math.round(pt.x), y: Math.round(pt.y) };
    if (t.type === 'ramp' || t.type === 'beam' || t.type === 'barrier') { p.angle = t.type === 'ramp' ? 15 : 0; p.len = t.len; }
    if (t.type === 'post') { p.h = t.h; p.y = GROUND; }
    if (t.type === 'bumper') { p.h = t.h; }
    if (t.type === 'fan') { p.dir = 'E'; }
    return p;
  };

  Game.prototype.drawPart = function (c, p, selected, broken) {
    var segs = Engine.partSegments(p);
    if (p.type === 'fan') {
      var z = Engine.fanZone(p);
      c.fillStyle = 'rgba(0,156,222,.10)'; c.fillRect(z.x, z.y, z.w, z.h);
      c.strokeStyle = 'rgba(0,156,222,.5)'; c.lineWidth = 0.4;
      var dx = z.fx ? Math.sign(z.fx) : 0, dy = z.fy ? Math.sign(z.fy) : 0;
      for (var i = 0; i < 3; i++) {
        var ax = z.x + z.w / 2 + (dx ? (i - 1) * 7 : (i - 1) * 4), ay = z.y + z.h / 2 + (dy ? (i - 1) * 7 : (i - 1) * 4);
        c.beginPath(); c.moveTo(ax - dx * 3, ay - dy * 3); c.lineTo(ax + dx * 3, ay + dy * 3); c.stroke();
        c.beginPath(); c.moveTo(ax + dx * 3, ay + dy * 3); c.lineTo(ax + dx * 3 - dx * 1.5 - dy * 1.5, ay + dy * 3 - dy * 1.5 + dx * 1.5); c.stroke();
      }
      c.fillStyle = '#009CDE'; c.beginPath(); c.arc(p.x, p.y, 3.2, 0, Math.PI * 2); c.fill();
      c.fillStyle = '#fff'; c.beginPath(); c.arc(p.x, p.y, 1, 0, Math.PI * 2); c.fill();
      c.strokeStyle = '#fff'; c.lineWidth = 0.8; c.beginPath(); c.moveTo(p.x - 2.2, p.y); c.lineTo(p.x + 2.2, p.y); c.moveTo(p.x, p.y - 2.2); c.lineTo(p.x, p.y + 2.2); c.stroke();
      if (selected) { c.strokeStyle = '#ffd166'; c.lineWidth = 0.9; c.beginPath(); c.arc(p.x, p.y, 4.4, 0, Math.PI * 2); c.stroke(); }
      return;
    }
    var colors = { ramp: '#E07FA3', beam: '#c9a36b', post: '#8fa3ad', bumper: '#ffb347', barrier: '#045C82' };
    segs.forEach(function (s) {
      c.strokeStyle = broken ? '#e6e3dc' : colors[p.type]; c.lineWidth = p.type === 'post' ? 2.2 : 2.4; c.lineCap = 'round';
      if (broken) c.setLineDash([2, 2]);
      c.beginPath(); c.moveTo(s.x1, s.y1); c.lineTo(s.x2, s.y2); c.stroke(); c.setLineDash([]);
      if (p.type === 'ramp' || p.type === 'beam') { c.strokeStyle = 'rgba(255,255,255,.55)'; c.lineWidth = 0.6; c.beginPath(); c.moveTo(s.x1, s.y1 - 0.7); c.lineTo(s.x2, s.y2 - 0.7); c.stroke(); }
    });
    if (selected) {
      var s = segs[0];
      c.strokeStyle = '#ffd166'; c.lineWidth = 0.9; c.lineCap = 'round'; c.beginPath(); c.moveTo(s.x1, s.y1); c.lineTo(s.x2, s.y2); c.stroke();
      if (p.angle != null) {
        c.fillStyle = '#ffd166'; c.beginPath(); c.arc(s.x2, s.y2, 2.2, 0, Math.PI * 2); c.fill();
        c.strokeStyle = '#173e63'; c.lineWidth = 0.5; c.beginPath(); c.arc(s.x2, s.y2, 1.1, 0.4, 5.2); c.stroke();
        label(c, p.angle + '°', p.x, p.y - 4, 2.6, '#173e63');
      }
    }
  };

  function drawCapsule(c, b) {
    c.fillStyle = 'rgba(0,0,0,.08)'; c.beginPath(); c.ellipse(b.x, Math.min(GROUND - 0.3, b.y + b.r + 0.6), b.r, 0.6, 0, 0, Math.PI * 2); c.fill();
    c.fillStyle = '#E07FA3'; c.beginPath(); c.arc(b.x, b.y, b.r, 0, Math.PI * 2); c.fill();
    c.fillStyle = 'rgba(255,255,255,.55)'; c.beginPath(); c.arc(b.x - b.r * 0.35, b.y - b.r * 0.35, b.r * 0.35, 0, Math.PI * 2); c.fill();
    c.strokeStyle = '#fff'; c.lineWidth = 0.35; c.beginPath(); c.moveTo(b.x - b.r, b.y); c.lineTo(b.x + b.r, b.y); c.stroke();
  }
  function drawRover(c, b) {
    c.fillStyle = '#045C82'; roundRect(c, b.x - b.r, b.y - b.r * 0.9, b.r * 2, b.r * 1.3, 0.8); c.fill();
    c.fillStyle = '#24332a'; c.beginPath(); c.arc(b.x - b.r * 0.6, b.y + b.r * 0.55, b.r * 0.45, 0, Math.PI * 2); c.arc(b.x + b.r * 0.6, b.y + b.r * 0.55, b.r * 0.45, 0, Math.PI * 2); c.fill();
    c.strokeStyle = '#E07FA3'; c.lineWidth = 0.5; c.beginPath(); c.moveTo(b.x, b.y - b.r * 0.9); c.lineTo(b.x + 1, b.y - b.r * 2); c.stroke();
    c.fillStyle = '#fff'; c.fillRect(b.x + b.r * 0.2, b.y - b.r * 0.6, b.r * 0.6, b.r * 0.5);
  }

  /* balance */
  Game.prototype.balanceLayout = function () {
    var sc = this.spec.scene, slots = {};
    var cx = 50, cy = 40, spacing = 7;
    sc.slots.forEach(function (s) { slots[s] = { x: cx + s * spacing, y: cy, blocked: sc.blocked.indexOf(s) !== -1 }; });
    var ground = sc.crates.map(function (m, i) { return { x: 8 + i * 9, y: GROUND - 1.5 - m, m: m, i: i }; });
    return { cx: cx, cy: cy, spacing: spacing, slots: slots, ground: ground };
  };
  Game.prototype.drawBalance = function (c, snap) {
    var sc = this.spec.scene, lay = this.balanceLayout(), self = this;
    sky(c);
    // greenhouse at right
    c.fillStyle = 'rgba(191,224,180,.6)'; roundRect(c, 84, 30, 14, 26, 3); c.fill(); c.strokeStyle = '#1D4010'; c.lineWidth = 0.6; roundRect(c, 84, 30, 14, 26, 3); c.stroke();
    label(c, 'Greenhouse', 91, 33, 2.2, '#1D4010');
    if (snap && snap.result === 'success') { c.fillStyle = '#E07FA3'; [87, 91, 95].forEach(function (x, i) { c.beginPath(); c.arc(x, 44 - i % 2 * 3, 1.6, 0, Math.PI * 2); c.fill(); }); }
    // track
    c.strokeStyle = '#045C82'; c.lineWidth = 0.8; c.beginPath(); c.moveTo(0, GROUND - 6); c.lineTo(100, GROUND - 6); c.stroke();
    var cartX = snap ? snap.cartX - 20 + lay.cx : lay.cx, tilt = snap ? snap.tilt : 0;
    c.save(); c.translate(cartX, lay.cy + 4); c.rotate(deg2rad(tilt));
    // wheels & pivot
    c.fillStyle = '#24332a'; c.beginPath(); c.arc(-10, 9, 2, 0, Math.PI * 2); c.arc(10, 9, 2, 0, Math.PI * 2); c.fill();
    c.fillStyle = '#045C82'; c.beginPath(); c.moveTo(-3, 6); c.lineTo(3, 6); c.lineTo(0, 0); c.closePath(); c.fill();
    // platform
    c.fillStyle = '#c9a36b'; roundRect(c, -25, -4, 50, 4, 1); c.fill();
    sc.slots.forEach(function (s) {
      var x = s * lay.spacing, blocked = sc.blocked.indexOf(s) !== -1;
      c.fillStyle = blocked ? '#e05252' : (self.selected != null ? '#ffd166' : '#8fa3ad'); c.fillRect(x - 0.5, -5.5, 1, 1.5);
      label(c, blocked ? 'x' : String(s), x, -7.5, 2.2, blocked ? '#e05252' : '#62676c');
    });
    (this.design.placements || []).forEach(function (p) {
      var m = sc.crates[p.crate], x = p.slot * lay.spacing, drift = 0;
      if (snap && snap.result === 'tipped') drift = Math.sign(tilt) * clamp((snap.t - 1) * 6, 0, 12);
      drawCrate(c, x + drift, -4, m, p.crate === self.selected);
    });
    c.restore();
    // waiting crates
    lay.ground.forEach(function (g) {
      if ((self.design.placements || []).some(function (p) { return p.crate === g.i; })) return;
      drawCrate(c, g.x, GROUND - 0.5, g.m, g.i === self.selected);
    });
    if (this.drag && this.drag.crate != null) drawCrate(c, this.drag.x, this.drag.y + 2, sc.crates[this.drag.crate], true);
    if (store.progress.settings.assist) { var t = this.torqueNow(); label(c, 'Left ' + t.l + ' · Right ' + t.r, lay.cx, 24, 2.6, t.l === t.r ? '#1D4010' : '#045C82'); }
  };
  function drawCrate(c, x, yBottom, m, sel) {
    var s = 2.6 + m * 1.4;
    c.fillStyle = m === 3 ? '#045C82' : (m === 2 ? '#009CDE' : '#E07FA3'); roundRect(c, x - s / 2, yBottom - s, s, s, 0.6); c.fill();
    if (sel) { c.strokeStyle = '#ffd166'; c.lineWidth = 0.8; roundRect(c, x - s / 2, yBottom - s, s, s, 0.6); c.stroke(); }
    label(c, String(m), x, yBottom - s / 2, 2.8, '#fff');
  }
  Game.prototype.torqueNow = function () {
    var sc = this.spec.scene, l = 0, r = 0;
    (this.design.placements || []).forEach(function (p) { var m = sc.crates[p.crate]; if (p.slot < 0) l += m * -p.slot; else r += m * p.slot; });
    return { l: l, r: r };
  };

  /* gears */
  Game.prototype.gearLayout = function () {
    var slots = this.design.slots, teeth = [this.spec.scene.motor.teeth].concat(slots.map(function (t) { return t; }));
    var x = 14, out = [], y = 42;
    for (var i = 0; i < teeth.length; i++) {
      var r = teeth[i] ? teeth[i] * 0.32 + 1.5 : 4.5;
      if (i > 0) { var pr = teeth[i - 1] ? teeth[i - 1] * 0.32 + 1.5 : 4.5; x += pr + r + 0.4; }
      out.push({ x: x, y: y, r: r, teeth: teeth[i], slot: i - 1 });
    }
    return out;
  };
  Game.prototype.drawGears = function (c, snap) {
    var sc = this.spec.scene, lay = this.gearLayout(), self = this, ev = snap ? snap.eval : Engine.gearsEval(this.spec, this.design);
    sky(c);
    // tower & platform
    c.fillStyle = 'rgba(4,92,130,.16)'; c.fillRect(78, 8, 20, GROUND - 8); c.strokeStyle = '#045C82'; c.lineWidth = 1.2; c.beginPath(); c.moveTo(70, 10); c.lineTo(98, 10); c.stroke();
    label(c, 'Observation platform', 84, 6, 2.2, '#045C82');
    var h = snap ? snap.liftHeight : 0, crateY = GROUND - 3 - h * ((GROUND - 16) / sc.lift.height);
    c.strokeStyle = '#8fa3ad'; c.lineWidth = 0.5; c.beginPath(); c.moveTo(70, 10); c.lineTo(70, crateY - 3); c.stroke();
    drawCrate(c, 70, crateY, 3, false);
    lay.forEach(function (g, i) {
      var angle = snap ? (i === 0 ? snap.motorAngle : (g.slot === 0 && lay.length === 3 ? snap.idlerAngle : snap.liftAngle)) : 0;
      if (g.teeth) drawGear(c, g.x, g.y, g.r, g.teeth, angle, i === 0 ? '#045C82' : (g.slot === lay.length - 2 ? '#E07FA3' : '#009CDE'));
      else { c.strokeStyle = '#8fa3ad'; c.setLineDash([1.2, 1]); c.lineWidth = 0.6; c.beginPath(); c.arc(g.x, g.y, 4.5, 0, Math.PI * 2); c.stroke(); c.setLineDash([]); }
      label(c, i === 0 ? 'Motor ' + sc.motor.teeth + 't' : (g.slot === sc.slots.length - 1 ? 'Lift' + (g.teeth ? ' ' + g.teeth + 't' : '') : 'Middle' + (g.teeth ? ' ' + g.teeth + 't' : '')), g.x, g.y + g.r + 3, 2.2, '#173e63');
    });
    // belt from lift gear to tower
    var lift = lay[lay.length - 1]; c.strokeStyle = '#8fa3ad'; c.lineWidth = 0.5; c.beginPath(); c.moveTo(lift.x, lift.y - lift.r); c.lineTo(70, 10); c.stroke();
    label(c, ev.lift ? 'Ratio ' + ev.ratio + ':1 · needs ' + sc.lift.needRatio + ':1 · within ' + sc.lift.timeLimit + ' s' : 'Needs ' + sc.lift.needRatio + ':1 or more · within ' + sc.lift.timeLimit + ' s', 40, 8, 2.4, '#045C82');
    if (snap && snap.eval.direction) label(c, 'Motor ↻  Lift ' + (snap.eval.direction === 1 ? '↻' : '↺'), 40, 13, 2.4, '#62676c');
  };
  function drawGear(c, x, y, r, teeth, angleDeg, color) {
    c.save(); c.translate(x, y); c.rotate(deg2rad(angleDeg));
    c.fillStyle = color; c.beginPath();
    for (var i = 0; i < teeth; i++) {
      var a0 = (i / teeth) * Math.PI * 2, a1 = ((i + 0.5) / teeth) * Math.PI * 2, ro = r + 1, ri = r - 0.2;
      c.lineTo(Math.cos(a0) * ro, Math.sin(a0) * ro); c.lineTo(Math.cos(a0 + 0.25 / teeth * Math.PI * 2 * 2) * ro, Math.sin(a0 + 0.25 / teeth * Math.PI * 2 * 2) * ro);
      c.lineTo(Math.cos(a1) * ri, Math.sin(a1) * ri); c.lineTo(Math.cos(a1 + 0.25 / teeth * Math.PI * 2 * 2) * ri, Math.sin(a1 + 0.25 / teeth * Math.PI * 2 * 2) * ri);
    }
    c.closePath(); c.fill();
    c.fillStyle = '#fff'; c.beginPath(); c.arc(0, 0, r * 0.3, 0, Math.PI * 2); c.fill();
    c.fillStyle = color; c.fillRect(-0.5, -r * 0.3, 1, r * 0.3);
    c.restore();
  }

  /* circuit */
  Game.prototype.circuitLayout = function () {
    var sc = this.spec.scene, cell = Math.min(80 / sc.cols, 44 / sc.rows), w = cell * sc.cols, h = cell * sc.rows;
    return { cell: cell, ox: (100 - w) / 2, oy: 6 + (46 - h) / 2 };
  };
  Game.prototype.drawCircuit = function (c, snap) {
    var sc = this.spec.scene, lay = this.circuitLayout(), grid = Engine.circuitGrid(this.spec, this.design), self = this;
    var ev = snap ? snap.eval : null, lit = ev && ev.ok, glow = snap ? snap.glow : 0;
    c.fillStyle = lit ? '#fff8e1' : '#e9eef2'; c.fillRect(0, 0, 100, 60);
    label(c, lit ? 'The lab is lit!' : 'Laboratory (dark)', 50, 3.5, 2.6, lit ? '#9a6a00' : '#62676c');
    for (var r = 0; r < sc.rows; r++) for (var col = 0; col < sc.cols; col++) {
      var x = lay.ox + col * lay.cell, y = lay.oy + r * lay.cell, key = r + ',' + col, blocked = sc.blocked.some(function (b) { return b.r === r && b.c === col; });
      c.fillStyle = blocked ? '#8fa3ad' : '#fff'; c.strokeStyle = '#d4e7f2'; c.lineWidth = 0.4; roundRect(c, x + 0.5, y + 0.5, lay.cell - 1, lay.cell - 1, 1); c.fill(); c.stroke();
      if (blocked) label(c, 'pipe', x + lay.cell / 2, y + lay.cell / 2, 2.2, '#fff');
      var t = grid[key];
      if (t) drawTile(c, t, x, y, lay.cell, lit, glow, self.selected && self.selected.tile && self.selected.tile.r === r && self.selected.tile.c === col);
      if (ev && ev.openAt && ev.openAt.r === r && ev.openAt.c === col && !ev.ok) { c.strokeStyle = '#e05252'; c.lineWidth = 0.8; roundRect(c, x + 0.5, y + 0.5, lay.cell - 1, lay.cell - 1, 1); c.stroke(); }
    }
    if (this.placing && this.hover) { var hc = this.cellAt(this.hover); if (hc) { c.fillStyle = 'rgba(224,127,163,.25)'; c.fillRect(lay.ox + hc.c * lay.cell, lay.oy + hc.r * lay.cell, lay.cell, lay.cell); } }
  };
  function drawTile(c, t, x, y, cell, lit, glow, selected) {
    var cx = x + cell / 2, cy = y + cell / 2, ports = Engine.tilePorts(t);
    var col = lit ? '#e8a600' : '#045C82';
    c.strokeStyle = col; c.lineWidth = 1.4; c.lineCap = 'round';
    var ends = { N: [cx, y], S: [cx, y + cell], E: [x + cell, cy], W: [x, cy] };
    if (t.type === 'switch') {
      var a = ends[ports[0]], b = ends[ports[1]];
      c.beginPath(); c.moveTo(a[0], a[1]); c.lineTo((a[0] + cx) / 2, (a[1] + cy) / 2); c.stroke();
      c.beginPath(); c.moveTo(b[0], b[1]); c.lineTo((b[0] + cx) / 2, (b[1] + cy) / 2); c.stroke();
      c.strokeStyle = '#E07FA3'; c.beginPath(); c.moveTo((b[0] + cx) / 2, (b[1] + cy) / 2);
      if (t.closed) c.lineTo((a[0] + cx) / 2, (a[1] + cy) / 2); else c.lineTo((a[0] + cx) / 2 + (ports[0] === 'N' || ports[0] === 'S' ? 3 : 0), (a[1] + cy) / 2 - (ports[0] === 'E' || ports[0] === 'W' ? 3 : 0));
      c.stroke();
      label(c, t.closed ? 'closed' : 'open', cx, y + cell - 1.6, 1.8, '#62676c');
    } else {
      ports.forEach(function (p) { c.beginPath(); c.moveTo(cx, cy); c.lineTo(ends[p][0], ends[p][1]); c.stroke(); });
    }
    if (t.type === 'fixed' && t.kind === 'battery') {
      c.fillStyle = '#24332a'; roundRect(c, cx - 2.6, cy - 1.6, 5.2, 3.2, 0.5); c.fill(); c.fillStyle = '#fff'; label(c, '+ −', cx, cy, 2, '#fff');
      label(c, 'Battery', cx, y + cell - 1.6, 1.8, '#62676c');
    }
    if (t.type === 'fixed' && t.kind === 'lamp') {
      if (lit) { c.fillStyle = 'rgba(255,209,102,' + (0.5 * glow) + ')'; c.beginPath(); c.arc(cx, cy, 4.5, 0, Math.PI * 2); c.fill(); }
      c.fillStyle = lit ? '#ffd166' : '#e6e3dc'; c.beginPath(); c.arc(cx, cy, 2.4, 0, Math.PI * 2); c.fill(); c.strokeStyle = '#24332a'; c.lineWidth = 0.4; c.stroke();
      label(c, 'Lamp', cx, y + cell - 1.6, 1.8, '#62676c');
    }
    if (selected) { c.strokeStyle = '#ffd166'; c.lineWidth = 0.8; roundRect(c, x + 0.8, y + 0.8, cell - 1.6, cell - 1.6, 1); c.stroke(); }
  }
  Game.prototype.cellAt = function (pt) {
    var sc = this.spec.scene, lay = this.circuitLayout();
    var c = Math.floor((pt.x - lay.ox) / lay.cell), r = Math.floor((pt.y - lay.oy) / lay.cell);
    if (r < 0 || c < 0 || r >= sc.rows || c >= sc.cols) return null;
    return { r: r, c: c };
  };

  /* code */
  Game.prototype.codeLayout = function () {
    var sc = this.spec.scene, cell = Math.min(90 / sc.cols, 50 / sc.rows), w = cell * sc.cols, h = cell * sc.rows;
    return { cell: cell, ox: (100 - w) / 2, oy: 4 + (52 - h) / 2 };
  };
  Game.prototype.drawCode = function (c, snap) {
    var sc = this.spec.scene, lay = this.codeLayout(), run = snap ? snap.run : null, frame = snap ? snap.frame : { r: sc.start.r, c: sc.start.c, dir: sc.start.dir };
    c.fillStyle = '#eef6fb'; c.fillRect(0, 0, 100, 60);
    var delivered = {};
    if (run) run.frames.slice(0, (snap.frameIndex || 0) + 1).forEach(function (f) { if (f.deliver) delivered[f.r + ',' + f.c] = true; });
    for (var r = 0; r < sc.rows; r++) for (var col = 0; col < sc.cols; col++) {
      var x = lay.ox + col * lay.cell, y = lay.oy + r * lay.cell, ch = sc.map[r][col];
      c.fillStyle = ch === '#' ? '#8fa3ad' : ((r + col) % 2 ? '#ffffff' : '#f4f8fb'); c.strokeStyle = '#d4e7f2'; c.lineWidth = 0.4;
      roundRect(c, x + 0.4, y + 0.4, lay.cell - 0.8, lay.cell - 0.8, 1); c.fill(); c.stroke();
      if (ch === 'S') {
        var done = delivered[r + ',' + col];
        c.fillStyle = done ? '#1D4010' : '#E07FA3'; c.beginPath(); c.arc(x + lay.cell / 2, y + lay.cell / 2, lay.cell * 0.22, 0, Math.PI * 2); c.fill();
        label(c, done ? '✓' : 'S', x + lay.cell / 2, y + lay.cell / 2, lay.cell * 0.3, '#fff');
      }
    }
    // ghost trail (last route)
    var g = this.ghost[this.ghostKey()];
    if (g && store.progress.settings.ghost && !this.sim && !this.finalSim) { c.strokeStyle = 'rgba(4,92,130,.25)'; c.lineWidth = 1; c.beginPath(); g.forEach(function (p, i) { var px = lay.ox + p[0] * lay.cell + lay.cell / 2, py = lay.oy + p[1] * lay.cell + lay.cell / 2; if (i) c.lineTo(px, py); else c.moveTo(px, py); }); c.stroke(); }
    // rover
    var rx = lay.ox + frame.c * lay.cell + lay.cell / 2, ry = lay.oy + frame.r * lay.cell + lay.cell / 2, s = lay.cell * 0.34;
    c.save(); c.translate(rx, ry); c.rotate({ E: 0, S: Math.PI / 2, W: Math.PI, N: -Math.PI / 2 }[frame.dir]);
    if (frame.bump) c.translate(1.2, 0);
    c.fillStyle = '#045C82'; roundRect(c, -s, -s * 0.8, s * 2, s * 1.6, 1); c.fill();
    c.fillStyle = '#24332a'; c.fillRect(-s * 0.8, -s * 1.05, s * 0.6, s * 0.3); c.fillRect(s * 0.2, -s * 1.05, s * 0.6, s * 0.3); c.fillRect(-s * 0.8, s * 0.75, s * 0.6, s * 0.3); c.fillRect(s * 0.2, s * 0.75, s * 0.6, s * 0.3);
    c.fillStyle = '#E07FA3'; c.beginPath(); c.moveTo(s * 0.2, -s * 0.5); c.lineTo(s * 0.95, 0); c.lineTo(s * 0.2, s * 0.5); c.closePath(); c.fill();
    c.restore();
    if (frame.wasted) label(c, 'no station here', rx, ry - lay.cell * 0.6, 2.2, '#e05252');
  };

  /* ---------------------------------------------------- code editor */
  Game.prototype.renderEditor = function () {
    var self = this, ed = this.editor;
    ed.innerHTML = '';
    if (this.spec.mode !== 'code') return;
    var sc = this.spec.scene;
    var pal = el('div', 'rl-palette');
    [['F', '↑ Forward'], ['L', '↰ Turn left'], ['R', '↱ Turn right'], ['D', '⬇ Deliver']].forEach(function (b) {
      pal.appendChild(btn('rl-tray-btn', b[1], function () { self.codeAdd({ t: b[0] }); }));
    });
    if (sc.loops) {
      var minus = btn('rl-btn', '−', function () { self.repeatCount = Math.max(2, self.repeatCount - 1); self.renderEditor(); }, 'Fewer repeats');
      var count = el('span', 'rl-count', this.repeatCount + '×');
      var plus = btn('rl-btn', '+', function () { self.repeatCount = Math.min(9, self.repeatCount + 1); self.renderEditor(); }, 'More repeats');
      pal.appendChild(minus); pal.appendChild(count); pal.appendChild(plus);
      pal.appendChild(btn('rl-tray-btn', 'Repeat ' + this.repeatCount + '×', function () { self.codeAdd({ t: 'loop', n: self.repeatCount, body: [] }); }));
      var close = btn('rl-tray-btn', 'Close repeat', function () { if (self.stack.length) { self.stack.pop(); self.renderEditor(); } });
      close.disabled = !this.stack.length; pal.appendChild(close);
    }
    ed.appendChild(pal);
    var view = el('div', 'rl-program'); view.setAttribute('aria-live', 'polite');
    var prog = this.design.program || (this.design.program = []);
    var badPath = this.lastRun && this.lastRun.diag && this.lastRun.diag.path ? this.lastRun.diag.path.join('.') : null;
    (function renderList(list, into, path) {
      list.forEach(function (node, i) {
        var p = path.concat([i]).join('.');
        if (node.t === 'loop') {
          var open = self.stack.indexOf(node) !== -1, box = el('span', 'rl-loop' + (open ? ' is-open' : ''));
          box.appendChild(el('span', 'rl-loop-label', 'Repeat ' + node.n + '×'));
          var body = el('span', 'rl-loop-body'); renderList(node.body, body, path.concat([i]));
          if (node === self.stack[self.stack.length - 1]) body.appendChild(el('span', 'rl-caret'));
          box.appendChild(body); into.appendChild(box);
        } else {
          var chip = el('span', 'rl-block' + (node.t === 'D' ? ' deliver' : '') + (badPath === p ? ' is-bad' : ''), { F: '↑', L: '↰', R: '↱', D: '⬇' }[node.t]);
          chip.setAttribute('aria-label', { F: 'forward', L: 'turn left', R: 'turn right', D: 'deliver' }[node.t]);
          into.appendChild(chip);
        }
      });
    })(prog, view, []);
    if (!this.stack.length) view.appendChild(el('span', 'rl-caret'));
    if (!prog.length) view.appendChild(el('span', 'rl-empty', 'Tap the blocks to build the rover’s route'));
    ed.appendChild(view);
    var n = Engine.countBlocks(prog);
    ed.appendChild(el('div', 'rl-toolname', n + ' block' + (n === 1 ? '' : 's')));
  };
  Game.prototype.codeAdd = function (node) {
    if (this.sim) return;
    var prog = this.design.program || (this.design.program = []);
    if (Engine.countBlocks(prog) >= this.spec.scene.maxBlocks) { this.setMessage('That is a big program! Look for a pattern to repeat.'); return; }
    var target = this.stack.length ? this.stack[this.stack.length - 1].body : prog;
    target.push(node); if (node.t === 'loop') this.stack.push(node);
    audio.place(); this.afterEdit();
  };
  Game.prototype.codeUndo = function () {
    var prog = this.design.program || [], tgt = this.stack.length ? this.stack[this.stack.length - 1].body : prog;
    if (!tgt.length) { if (this.stack.length) { this.stack.pop(); var t2 = this.stack.length ? this.stack[this.stack.length - 1].body : prog; t2.pop(); } }
    else if (tgt[tgt.length - 1].t === 'loop') { this.stack.push(tgt[tgt.length - 1]); return this.codeUndo(); }
    else tgt.pop();
    this.afterEdit();
  };

  /* ---------------------------------------------------- pointer input */
  Game.prototype.bindPointer = function () {
    var self = this, cv = this.canvas;
    cv.addEventListener('pointerdown', function (e) { self.onDown(e); });
    cv.addEventListener('pointermove', function (e) { self.onMove(e); });
    cv.addEventListener('pointerup', function (e) { self.onUp(e); });
    cv.addEventListener('pointercancel', function (e) { self.onUp(e, true); });
    cv.addEventListener('pointerleave', function () { self.hover = null; if (!self.drag) self.draw(); });
  };

  Game.prototype.onDown = function (e) {
    if (this.sim) return;
    e.preventDefault();
    var pt = this.toScene(e), mode = this.spec.mode;
    try { this.canvas.setPointerCapture(e.pointerId); } catch (x) { /* ignore */ }
    if (mode === 'physics' || mode === 'bridge') {
      if (this.placing) {
        var lim = Engine.trayLimit(this.spec, this.placing.type, this.placing.len), used = Engine.trayUsed(this.design, this.placing.type, this.placing.len);
        if (used >= lim) { this.placing = null; this.renderTray(); return; }
        this.pushUndo(); var p = this.previewPart(pt); this.design.parts.push(p); this.selected = this.design.parts.length - 1;
        if (used + 1 >= lim) this.placing = null;
        audio.place(); this.afterEdit(); this.setMessage('Placed. Drag it to move, or use Rotate.'); return;
      }
      var hit = this.hitPart(pt);
      if (hit != null) {
        var part = this.design.parts[hit];
        this.selected = hit;
        var segs = Engine.partSegments(part)[0];
        var handle = part.angle != null && Math.hypot(pt.x - segs.x2, pt.y - segs.y2) < 4;
        this.pushUndo();
        this.drag = { kind: handle ? 'rotate' : 'move', idx: hit, ox: pt.x - part.x, oy: pt.y - part.y, moved: false };
        this.canvas.classList.add('grabbing'); this.renderTools(); this.draw();
      } else { this.selected = null; this.renderTools(); this.draw(); }
    } else if (mode === 'balance') {
      var lay = this.balanceLayout(), sc = this.spec.scene, self = this, found = null;
      lay.ground.forEach(function (g) { if ((self.design.placements || []).some(function (p) { return p.crate === g.i; })) return; var s = 2.6 + g.m * 1.4; if (Math.abs(pt.x - g.x) < s / 2 + 1.5 && pt.y > GROUND - s - 2 && pt.y < GROUND + 1) found = g.i; });
      (this.design.placements || []).forEach(function (p) { var x = lay.cx + p.slot * lay.spacing, s = 2.6 + sc.crates[p.crate] * 1.4; if (Math.abs(pt.x - x) < s / 2 + 1.5 && pt.y > lay.cy - s - 1 && pt.y < lay.cy + 2) found = p.crate; });
      if (found != null) { this.selected = found; this.drag = { crate: found, x: pt.x, y: pt.y, moved: false }; this.renderTools(); this.draw(); return; }
      var slot = this.slotAt(pt);
      if (slot != null && this.selected != null) { this.placeCrate(this.selected, slot); return; }
      this.selected = null; this.renderTools(); this.draw();
    } else if (mode === 'gears') {
      var lay2 = this.gearLayout();
      for (var i = 1; i < lay2.length; i++) {
        if (Math.hypot(pt.x - lay2[i].x, pt.y - lay2[i].y) < Math.max(lay2[i].r + 1.5, 6)) {
          this.pushUndo();
          var slotIdx = i - 1, cur = this.design.slots[slotIdx];
          if (this.placing && this.placing.teeth && cur !== this.placing.teeth) this.design.slots[slotIdx] = this.placing.teeth;
          else if (cur) this.design.slots[slotIdx] = null;
          else { var gs = this.spec.scene.gears; this.design.slots[slotIdx] = gs[Math.min(gs.length - 1, 2)]; }
          audio.place(); this.afterEdit(); return;
        }
      }
    } else if (mode === 'circuit') {
      var cell = this.cellAt(pt); if (!cell) return;
      var grid = Engine.circuitGrid(this.spec, this.design), t = grid[cell.r + ',' + cell.c];
      var blocked = this.spec.scene.blocked.some(function (b) { return b.r === cell.r && b.c === cell.c; });
      if (blocked) return;
      if (t) {
        if (t.type === 'fixed') { this.pushUndo(); this.design.fixedAxis[t.r + ',' + t.c] = t.axis === 'V' ? 'H' : 'V'; this.selected = { tile: Engine.circuitGrid(this.spec, this.design)[t.r + ',' + t.c] }; this.afterEdit(); return; }
        var real = this.design.tiles.filter(function (x) { return x.r === cell.r && x.c === cell.c; })[0];
        if (this.selected && this.selected.tile === real) { this.pushUndo(); if (real.type === 'switch') real.closed = !real.closed; else real.rot = ((real.rot || 0) + 1) % 4; this.afterEdit(); }
        else { this.selected = { tile: real }; this.placing = null; this.renderTray(); this.renderTools(); this.draw(); this.setMessage(real.type === 'switch' ? 'Tap again to open or close the switch.' : 'Tap again to rotate.'); }
        return;
      }
      if (this.placing) {
        var used = this.design.tiles.filter(function (x) { return x.type === self.placing.type; }).length, lim2 = Engine.trayLimit(this.spec, this.placing.type);
        if (used >= lim2) { this.placing = null; this.renderTray(); return; }
        this.pushUndo(); var tile = { r: cell.r, c: cell.c, type: this.placing.type, rot: 0, closed: true }; this.design.tiles.push(tile); this.selected = { tile: tile };
        if (used + 1 >= lim2) this.placing = null;
        audio.place(); this.afterEdit(); return;
      }
      this.selected = null; this.renderTools(); this.draw();
    }
  };

  Game.prototype.onMove = function (e) {
    var pt = this.toScene(e); this.hover = pt;
    if (this.drag) {
      e.preventDefault();
      if (this.drag.idx != null) {
        var p = this.design.parts[this.drag.idx];
        if (this.drag.kind === 'rotate') { var a = Math.atan2(pt.y - p.y, pt.x - p.x) * 180 / Math.PI; p.angle = clamp(Math.round(a / 5) * 5, -75, 75); }
        else { p.x = clamp(pt.x - this.drag.ox, 2, 98); p.y = p.type === 'post' ? GROUND : clamp(pt.y - this.drag.oy, 2, GROUND - 1); }
        this.drag.moved = true;
      } else if (this.drag.crate != null) { this.drag.x = pt.x; this.drag.y = pt.y; this.drag.moved = true; }
      this.draw();
    } else if (this.placing) this.draw();
  };

  Game.prototype.onUp = function (e, cancelled) {
    this.canvas.classList.remove('grabbing');
    if (!this.drag) return;
    var d = this.drag; this.drag = null;
    if (d.crate != null) {
      var slot = this.slotAt(this.toScene(e));
      if (!cancelled && d.moved && slot != null) this.placeCrate(d.crate, slot);
      else if (!cancelled && d.moved && this.toScene(e).y > GROUND - 8) { this.pushUndo(); this.design.placements = this.design.placements.filter(function (p) { return p.crate !== d.crate; }); this.afterEdit(); }
      else this.draw();
      return;
    }
    if (cancelled && !d.moved) { this.undo.pop(); }
    else if (!d.moved) { this.undo.pop(); }
    this.afterEdit();
    if (d.moved) this.setMessage(d.kind === 'rotate' ? 'Rotated to ' + this.design.parts[d.idx].angle + '°.' : 'Moved.');
  };

  Game.prototype.hitPart = function (pt) {
    var parts = this.design.parts || [], best = null, bestD = 4.5;
    for (var i = parts.length - 1; i >= 0; i--) {
      var p = parts[i];
      if (p.type === 'fan') { if (Math.hypot(pt.x - p.x, pt.y - p.y) < 5) return i; continue; }
      var s = Engine.partSegments(p)[0], dx = s.x2 - s.x1, dy = s.y2 - s.y1, l2 = dx * dx + dy * dy, t = l2 ? clamp(((pt.x - s.x1) * dx + (pt.y - s.y1) * dy) / l2, 0, 1) : 0;
      var d = Math.hypot(pt.x - (s.x1 + dx * t), pt.y - (s.y1 + dy * t));
      if (d < bestD) { bestD = d; best = i; }
    }
    return best;
  };
  Game.prototype.slotAt = function (pt) {
    var lay = this.balanceLayout(), sc = this.spec.scene, best = null, bestD = 4.5;
    sc.slots.forEach(function (s) { if (sc.blocked.indexOf(s) !== -1) return; var d = Math.hypot(pt.x - (lay.cx + s * lay.spacing), pt.y - lay.cy); if (d < bestD) { bestD = d; best = s; } });
    return best;
  };
  Game.prototype.placeCrate = function (crate, slot) {
    this.pushUndo();
    var pl = this.design.placements = (this.design.placements || []).filter(function (p) { return p.crate !== crate; });
    var occupant = pl.filter(function (p) { return p.slot === slot; })[0];
    if (occupant) { var old = pl.filter(function (p) { return p.crate === crate; }); pl.splice(pl.indexOf(occupant), 1); }
    pl.push({ crate: crate, slot: slot });
    this.selected = null; audio.place(); this.afterEdit();
    this.setMessage(occupant ? 'Swapped: the crate that was there is back on the ground.' : 'Crate placed at slot ' + slot + '.');
  };

  /* ------------------------------------------------------------- run */
  Game.prototype.run = function () {
    if (this.sim) return;
    var v = Engine.validateDesign(this.spec, this.design);
    if (!v.ok) { this.setMessage(v.issues[0]); return; }
    if (this.spec.mode === 'code') this.stack = [];
    this.selected = null; this.placing = null; this.renderTray(); this.renderTools(); this.renderEditor();
    this.successBox.innerHTML = ''; this.diagBox.innerHTML = ''; this.finalSim = null;
    this.sim = Engine.createSim(this.spec, this.design);
    this.runBtn.disabled = true; this.undoBtn.disabled = true; this.resetBtn.disabled = true;
    this.setMessage('Running…'); audio.run();
    this.acc = 0; this.lastFrame = 0; this.bounceCount = 0;
    var self = this;
    this.raf = requestAnimationFrame(function (t) { self.frame(t); });
  };

  Game.prototype.frame = function (t) {
    if (!this.sim) return;
    if (!this.lastFrame) this.lastFrame = t;
    var dt = Math.min(0.1, (t - this.lastFrame) / 1000); this.lastFrame = t;
    this.acc += dt * (REDUCED ? 1.6 : 1);
    var running = true;
    while (this.acc >= Engine.DT && running) { running = this.sim.step(); this.acc -= Engine.DT; }
    var ev = this.sim.events; if (ev && ev.length > this.bounceCount) { for (var i = this.bounceCount; i < ev.length; i++) if (ev[i].type === 'bounce') audio.bounce(); this.bounceCount = ev.length; }
    this.draw();
    if (running) { var self = this; this.raf = requestAnimationFrame(function (t2) { self.frame(t2); }); }
    else this.finishRun();
  };

  Game.prototype.pauseSim = function () { if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; } };
  Game.prototype.resumeSim = function () { if (this.sim && !this.raf) { this.lastFrame = 0; var self = this; this.raf = requestAnimationFrame(function (t) { self.frame(t); }); } };
  Game.prototype.stopSim = function () { this.pauseSim(); this.sim = null; };

  Game.prototype.finishRun = function () {
    var sim = this.sim, spec = this.spec, lvl = this.level, self = this;
    this.pauseSim(); this.sim = null;
    this.runBtn.disabled = false; this.undoBtn.disabled = false; this.resetBtn.disabled = false;
    var run = { ok: sim.result === 'success', result: sim.result, diag: sim.diag, t: sim.t, trail: sim.trail, events: sim.events, sim: sim };
    this.lastRun = run;
    if (spec.mode === 'physics' || spec.mode === 'bridge' || spec.mode === 'code') this.ghost[this.ghostKey()] = sim.trail;
    store.progress.runs++;
    this.renderTray(); this.renderTools(); this.renderEditor();
    if (!run.ok) {
      store.progress.fails++; this.failedRuns[lvl.id]++;
      store.save();
      this.setMessage('Not yet — your build is still here.');
      this.say(run.diag.text, 'puzzled'); audio.fail();
      this.renderDiag();
      this.drawFinal(sim);
      return;
    }
    // success
    var badges = [], extra = { failedRuns: this.failedRuns[lvl.id] };
    lvl.challenges.forEach(function (ch) { if (Engine.checkRule(spec, self.design, run, ch.rule, extra)) badges.push(ch.badge); });
    var parts = (this.design.parts || []).length || (this.design.tiles || []).length || Engine.countBlocks(this.design.program || []) || (this.design.slots || []).filter(Boolean).length;
    var stageDone = lvl.mode === 'sequence' && this.stage < lvl.stages.length - 1;
    if (stageDone) {
      var lv = Progress.level(store.progress, lvl.id); lv.stage = Math.max(lv.stage, this.stage + 1);
      store.save();
      this.setMessage('Checkpoint saved!'); this.say('Stage ' + (this.stage + 1) + ' done: ' + run.diag.text + ' Ready for the next stage?', 'happy'); audio.success();
      var box = el('div', 'rl-success'); box.innerHTML = '<h3>Stage ' + (this.stage + 1) + ' complete</h3><p class="rl-reward">Checkpoint saved. ' + esc(lvl.stages[this.stage + 1].name) + ' is next.</p>';
      var r = el('div', 'rl-row'); r.appendChild(btn('rl-btn primary', 'Continue →', function () { self.stage++; self.loadStage(); })); r.appendChild(btn('rl-btn', 'Run again', function () { self.run(); })); box.appendChild(r);
      this.successBox.innerHTML = ''; this.successBox.appendChild(box);
      this.drawFinal(sim);
      return;
    }
    var wasDone = !!(store.progress.levels[lvl.id] && store.progress.levels[lvl.id].done);
    var prevBadges = clone((store.progress.levels[lvl.id] || { badges: {} }).badges);
    Progress.recordSuccess(store.progress, lvl.id, this.variation, badges, { time: run.t, parts: parts });
    var pr = Progress.practice(store.progress, Progress.weekKey(new Date()), store.owned, store.hasPass);
    if (pr && pr.level.id === lvl.id && pr.variation === this.variation && Engine.checkRule(spec, this.design, run, pr.challenge.rule, extra)) store.progress.practice[pr.key] = true;
    if (lvl.mode === 'sequence') { Progress.level(store.progress, lvl.id).stage = 0; }
    store.save();
    this.refreshHeader();
    this.setMessage('Delivered!'); this.say(run.diag.text + ' ' + lvl.explain, 'happy'); audio.success();
    this.renderSuccess(run, badges, prevBadges, wasDone);
    this.renderChallenges(); this.renderShelf();
    this.drawFinal(sim);
    if (!REDUCED) this.confetti();
    try { this.root.dispatchEvent(new CustomEvent('ascend:game-complete', { bubbles: true, detail: { game: 'rescue-lab', level: lvl.id, variation: this.variation, badges: badges, time: run.t } })); } catch (e) { /* ignore */ }
  };

  /* Keep the finished run on screen (capsule in the basket, lamp lit) until the
     next edit or run; the design underneath is untouched. */
  Game.prototype.drawFinal = function (sim) { this.finalSim = sim; this.draw(); };

  Game.prototype.renderDiag = function () {
    var run = this.lastRun, box = this.diagBox, self = this;
    box.innerHTML = '';
    if (!run || run.ok) return;
    var d = el('div', 'rl-diag bad');
    d.innerHTML = '<p><strong>What happened:</strong> ' + esc(run.diag.text) + '</p>' + (run.diag.hint ? '<p class="rl-hint">' + esc(run.diag.hint) + '</p>' : '');
    var row = el('div', 'rl-row left');
    row.appendChild(btn('rl-btn blue', '↻ Retry', function () { self.run(); }));
    if (store.progress.settings.assist && this.failedRuns[this.level.id] >= 2) row.appendChild(btn('rl-btn', 'Give me a nudge', function () { self.nudge(); }));
    d.appendChild(row);
    box.appendChild(d);
  };

  Game.prototype.nudge = function () {
    var lvl = this.level, mode = this.spec.mode, run = this.lastRun, text;
    if (mode === 'physics' && this.spec.scene.launcher) text = 'Change only one dial at a time and watch where the capsule lands. If it flies past, try one step less power; if it drops short, one step more.';
    else if (mode === 'physics' && lvl.id === 5) text = 'Put a fan close to where the capsule falls, pointing toward the basket. The longer the capsule stays in the breeze, the farther it drifts.';
    else if (mode === 'physics') text = 'Keep the left end of the ramp under the hopper and tilt it between 10° and 25°. Then move the whole ramp left or right to fine-tune.';
    else if (mode === 'bridge') text = 'A post under the middle of the gap gives a long beam somewhere to rest. Beam ends should sit on a cliff or a post.';
    else if (mode === 'balance') { var t = this.torqueNow(); text = 'Turning effect is mass × distance. Right now left = ' + t.l + ' and right = ' + t.r + '. Move a crate on the heavier side closer to the middle, or one on the lighter side farther out.'; }
    else if (mode === 'gears') text = 'Only the lift gear’s size changes the ratio: lift teeth ÷ motor teeth. A middle gear only changes the direction.';
    else if (mode === 'circuit') text = run && run.diag.code === 'open' ? 'Look at the cell outlined in red: one of its sides has no matching wire end. Rotate that tile or its neighbour.' : 'Every tile in the loop must connect on two sides to its neighbours, and the switch has to be closed.';
    else text = 'Run it, watch the very first wrong move, and change just that block. The block that caused a bump is outlined in red.';
    this.say(text, 'intro');
  };

  Game.prototype.renderSuccess = function (run, badges, prevBadges, wasDone) {
    var lvl = this.level, self = this, box = el('div', 'rl-success'), area = Levels.AREAS[lvl.id - 1];
    var newBadges = badges.filter(function (b) { return !prevBadges[b]; });
    box.innerHTML = '<h3>' + (wasDone ? 'Delivered again!' : 'Mission complete!') + '</h3>' +
      '<p class="rl-reward">' + esc(lvl.reward) + (wasDone ? '' : ' Check the station map.') + '</p>' +
      '<p class="rl-explain">' + esc(lvl.explain) + '</p>' +
      (newBadges.length ? '<p class="rl-reward">New badge' + (newBadges.length > 1 ? 's' : '') + ': ' + newBadges.map(function (b) { return '★ ' + b; }).join(', ') + '</p>' : '') +
      (!wasDone ? '<p class="rl-fine">Habitat decoration earned: ' + esc(area.decor) + '</p>' : '');
    var reflect = el('details'); reflect.innerHTML = '<summary>What changed? (optional)</summary><p>' + esc(lvl.reflect) + '</p>';
    var ta = el('textarea'); ta.rows = 2; ta.placeholder = 'Type a thought, or skip it.'; ta.setAttribute('aria-label', lvl.reflect); reflect.appendChild(ta);
    box.appendChild(reflect);
    var row = el('div', 'rl-row');
    var nextLvl = Levels.LEVELS[lvl.id] || null;
    row.appendChild(btn('rl-btn primary', nextLvl ? 'Continue →' : 'Back to the station', function () { if (nextLvl) self.openLevel(nextLvl.id, 0); else self.showMap(); }));
    var untried = lvl.challenges.filter(function (c) { return !(store.progress.levels[lvl.id].badges[c.badge]); })[0];
    if (untried) row.appendChild(btn('rl-btn blue', 'Try a challenge', function () { self.successBox.innerHTML = ''; self.say('Challenge: ' + untried.text, 'intro'); self.challengeBox.scrollIntoView({ block: 'nearest' }); }));
    row.appendChild(btn('rl-btn', 'Save design', function () {
      var name = 'Design ' + (((store.progress.levels[lvl.id] || {}).designs || []).length + 1) + (badges.length ? ' ★' : '');
      Progress.saveDesign(store.progress, lvl.id, self.variation, self.design, name, { time: Math.round(run.t * 100) / 100, parts: (self.design.parts || []).length, badges: badges }, now());
      store.save(); self.renderShelf(); self.setMessage('Saved to your inventions shelf as “' + name + '”.');
    }));
    if (nextLvl) row.appendChild(btn('rl-btn', 'Station map', function () { self.showMap(); }));
    box.appendChild(row);
    this.successBox.innerHTML = ''; this.successBox.appendChild(box);
    this.diagBox.innerHTML = '';
  };

  Game.prototype.renderChallenges = function () {
    var lvl = this.level, box = this.challengeBox, lv = store.progress.levels[lvl.id] || { badges: {} };
    box.innerHTML = '<h4>Optional badge challenges (designed for the main mission)</h4>';
    var rescue = el('div', 'rl-challenge' + (lv.badges.rescue ? ' earned' : ''));
    rescue.innerHTML = '<span class="rl-cbadge">rescue</span><span>Complete the mission. ' + (lv.badges.rescue ? 'Earned!' : '') + '</span>';
    box.appendChild(rescue);
    lvl.challenges.forEach(function (c) {
      var d = el('div', 'rl-challenge' + (lv.badges[c.badge] ? ' earned' : ''));
      d.innerHTML = '<span class="rl-cbadge">' + esc(c.badge) + '</span><span>' + esc(c.text) + (lv.badges[c.badge] ? ' Earned!' : '') + '</span>';
      box.appendChild(d);
    });
  };

  Game.prototype.renderShelf = function () {
    var lvl = this.level, box = this.shelfBox, self = this, lv = store.progress.levels[lvl.id];
    box.innerHTML = '';
    if (!lv || !lv.designs.length) return;
    box.appendChild(el('h4', null, 'Your inventions shelf'));
    var items = el('div', 'rl-shelf-items');
    lv.designs.forEach(function (d) {
      var it = el('span', 'rl-shelf-item');
      it.appendChild(el('span', null, d.name + (d.stats && d.stats.time ? ' · ' + d.stats.time + ' s' : '') + (lvl.variations.length > 1 ? ' · ' + lvl.variations[d.variation].name : '')));
      it.appendChild(btn(null, 'Load', function () { if (self.sim) return; self.pushUndo(); self.variation = d.variation; self.spec = Engine.resolve(lvl, self.variation, self.stage); self.design = clone(d.design); self.stack = []; self.renderLevel(); self.setMessage('Loaded “' + d.name + '”. Run it or change it.'); }));
      items.appendChild(it);
    });
    box.appendChild(items);
  };

  Game.prototype.confetti = function () {
    var m = el('div', 'rl-modal'), c = el('div', 'rl-confetti'), colors = ['#009CDE', '#1D4010', '#E07FA3', '#ffd166', '#BFE0B4'], self = this;
    for (var i = 0; i < 40; i++) { var p = el('i'); p.style.left = (i * 2.5) % 100 + '%'; p.style.background = colors[i % colors.length]; p.style.animationDelay = (i % 7) * 0.12 + 's'; c.appendChild(p); }
    m.appendChild(c);
    m.innerHTML += '<h3>' + esc(this.level.reward) + '</h3><p class="rl-sub">' + esc(GUIDE) + ' is doing a happy wiggle.</p>';
    m.appendChild(btn('rl-btn primary', 'Yay!', function () { self.closeOverlay(); }));
    this.showOverlay(m);
    setTimeout(function () { if (self.overlay.classList.contains('show') && self.overlay.contains(m)) self.closeOverlay(); }, 2600);
  };

  /* ------------------------------------------------------------- init */
  function init() {
    store.loadLocal();
    var roots = document.querySelectorAll('.rl-root');
    for (var i = 0; i < roots.length; i++) if (!roots[i].__rl) roots[i].__rl = new Game(roots[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.AscendRescueLab = { store: store };
})();
