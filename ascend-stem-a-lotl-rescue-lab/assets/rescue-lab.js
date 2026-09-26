/* STEM-a-lotl: Rescue Lab — game front end.
 *
 * Loads after levels.js, engine.js, progress.js and art.js. Reads the page
 * config from window.AscendRL (written by the plugin shortcode, or by the
 * standalone preview). Renders inside .rl-root.
 *
 * Loop: build → run → watch the pellet's journey → improve → Lucas eats.
 * Runs step the engine's fixed time step from an accumulator, so frame rate
 * never changes an outcome, and everything pauses when the tab is hidden.
 */
(function () {
  'use strict';
  if (typeof window === 'undefined') return;
  var Levels = window.AscendRLLevels, Engine = window.AscendRLEngine, Progress = window.AscendRLProgress, Art = window.AscendRLArt;
  if (!Levels || !Engine || !Progress || !Art) return;

  var CFG = window.AscendRL || {};
  var STORE_KEY = 'ascend_rescue_lab_v2', DRAFT_KEY = 'ascend_rescue_lab_drafts_v2', DEMO_KEY = 'ascend_rescue_lab_demo_v2';
  var GROUND = Levels.GROUND;
  var REDUCED = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var GUIDE = 'Lucas';
  var CONFIG = merge(Levels.CONFIG, CFG.config || {});

  // site-level text overrides (name, intro, lesson, hint) set over MCP or wp-admin
  if (CFG.levelText && typeof CFG.levelText === 'object') {
    Levels.LEVELS.forEach(function (l) {
      var o = CFG.levelText[l.id];
      if (o && typeof o === 'object') ['name', 'intro', 'lesson', 'hint'].forEach(function (k) { if (typeof o[k] === 'string' && o[k]) l[k] = o[k]; });
    });
  }

  /* ------------------------------------------------------------ helpers */
  function merge(a, b) { var o = JSON.parse(JSON.stringify(a)); for (var k in b) if (b[k] != null) o[k] = b[k]; return o; }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function btn(cls, text, onclick, label) { var b = el('button', cls, text); b.type = 'button'; if (label) b.setAttribute('aria-label', label); if (onclick) b.addEventListener('click', onclick); return b; }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
  function starText(n) { return '★★★'.slice(0, n) + '☆☆☆'.slice(0, 3 - n); }
  function plural(n, w) { return n + ' ' + w + (n === 1 ? '' : 's'); }
  function trayLabel(t) { var s = Engine.PART_SPEC[t.type].label; return t.type === 'ramp' ? s + ' ' + t.len : s; }
  function lget(k) { try { return JSON.parse(window.localStorage.getItem(k)); } catch (e) { return null; } }
  function lset(k, v) { try { window.localStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* storage unavailable */ } }

  /* ------------------------------------------------------ authorities */
  /* The server is the only authority for which puzzles and key skins a
     student owns. The preview uses a local demo authority instead, labelled
     as a demo everywhere it shows, and never touches real keys. */
  function api(path, method, body) {
    var base = String(CFG.restUrl || '').replace(/[/]+$/, '');
    return window.fetch(base + '/' + path, {
      method: method || 'GET', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); });
  }

  var ServerAuthority = {
    available: function () { return !!(CFG.loggedIn && CFG.restUrl && window.fetch); },
    load: function (cb) { if (!this.available()) return cb(null); api('state').then(function (r) { cb(r.ok ? r.body : null); }).catch(function () { cb(null); }); },
    daily: function (cb) { if (!this.available()) return cb(null); api('daily', 'POST', {}).then(function (r) { cb(r.ok ? r.body : null); }).catch(function () { cb(null); }); },
    unlock: function (id, cb) { api('unlock', 'POST', { level: id }).then(function (r) { cb(r.ok ? { ok: true, body: r.body } : { error: (r.body && r.body.message) || 'error' }); }).catch(function () { cb({ error: 'network' }); }); },
    buySkin: function (id, cb) { api('skin', 'POST', { skin: id }).then(function (r) { cb(r.ok ? { ok: true, body: r.body } : { error: (r.body && r.body.message) || 'error' }); }).catch(function () { cb({ error: 'network' }); }); },
    push: function (progress, cb) { if (!this.available()) return; api('progress', 'POST', { progress: progress }).then(function (r) { if (r.ok && cb) cb(r.body); }).catch(function () { /* offline: local copy keeps playing */ }); }
  };

  var DemoAuthority = {
    state: function () {
      var s = lget(DEMO_KEY) || {};
      return { mode: s.mode || 'review', owned: s.owned || {}, lastDaily: s.lastDaily || '', keys: s.keys == null ? 3 : s.keys, ownedSkins: s.ownedSkins || [], dayOffset: s.dayOffset || 0 };
    },
    save: function (s) { lset(DEMO_KEY, s); },
    today: function (s) { var d = new Date(); d.setDate(d.getDate() + (s.dayOffset || 0)); return Progress.dayKey(d); },
    payload: function (s, opened) {
      return { owned: s.mode === 'review' ? allOwned() : s.owned, hasPass: false, keys: s.keys, keysAvailable: true, ownedSkins: s.mode === 'review' ? Levels.SKINS.filter(function (k) { return k.rule.type === 'keys'; }).map(function (k) { return k.id; }) : s.ownedSkins, lastDaily: s.lastDaily, today: this.today(s), config: CONFIG, dailyOpened: opened || null, demo: s.mode };
    },
    available: function () { return true; },
    load: function (cb) { cb(this.payload(this.state())); },
    daily: function (cb) {
      var s = this.state();
      if (s.mode === 'review') return cb(this.payload(s));
      var r = Progress.claimDaily({ owned: s.owned, lastDaily: s.lastDaily, config: CONFIG }, this.today(s));
      s.owned = r.state.owned; s.lastDaily = r.state.lastDaily; this.save(s);
      cb(this.payload(s, r.opened));
    },
    unlock: function (id, cb) {
      var s = this.state();
      if (s.owned[id]) return cb({ ok: true, body: this.payload(s) });
      if (s.keys < CONFIG.keyCost) return cb({ error: 'No demo keys left.' });
      s.keys -= CONFIG.keyCost; s.owned[id] = 'key'; this.save(s); cb({ ok: true, body: this.payload(s) });
    },
    buySkin: function (id, cb) {
      var s = this.state(), price = (CONFIG.skinPrices || {})[id] || 1;
      if (s.ownedSkins.indexOf(id) !== -1) return cb({ ok: true, body: this.payload(s) });
      if (s.keys < price) return cb({ error: 'No demo keys left.' });
      s.keys -= price; s.ownedSkins.push(id); this.save(s); cb({ ok: true, body: this.payload(s) });
    },
    push: function () { /* demo progress stays in this browser */ },
    setMode: function (mode) { var s = this.state(); s.mode = mode; this.save(s); },
    nextDay: function () { var s = this.state(); s.dayOffset = (s.dayOffset || 0) + 1; this.save(s); },
    reset: function () { lset(DEMO_KEY, { mode: 'student' }); }
  };
  function allOwned() { var o = {}; Levels.LEVELS.forEach(function (l) { o[l.id] = 'review'; }); return o; }

  var authority = CFG.preview ? DemoAuthority : ServerAuthority;

  /* ------------------------------------------------------------- store */
  var store = {
    progress: Progress.empty(), access: { owned: {}, hasPass: false, config: CONFIG, lastDaily: '' }, keys: null, keysAvailable: false,
    ownedSkins: {}, today: '', dailyOpened: null, demo: null, synced: false, drafts: {},
    loadLocal: function () {
      var raw = lget(STORE_KEY); if (raw) this.progress = Progress.sanitize(raw);
      var d = lget(DRAFT_KEY); if (d && typeof d === 'object') this.drafts = d;
    },
    saveLocal: function () { lset(STORE_KEY, this.progress); lset(DRAFT_KEY, this.drafts); },
    apply: function (data) {
      if (!data) return;
      if (data.progress) this.progress = Progress.merge(this.progress, data.progress);
      var owned = {};
      if (Array.isArray(data.owned)) data.owned.forEach(function (id) { owned[id] = 'key'; });
      else if (data.owned && typeof data.owned === 'object') Object.keys(data.owned).forEach(function (id) { owned[id] = data.owned[id]; });
      if (data.config) CONFIG = merge(CONFIG, data.config);
      this.access = { owned: owned, hasPass: !!data.hasPass, config: CONFIG, lastDaily: data.lastDaily || '' };
      this.keys = typeof data.keys === 'number' ? data.keys : null;
      this.keysAvailable = !!data.keysAvailable;
      this.ownedSkins = {}; var self = this;
      (data.ownedSkins || []).forEach(function (id) { self.ownedSkins[id] = true; });
      this.today = data.today || this.today;
      if (data.dailyOpened) this.dailyOpened = data.dailyOpened;
      this.demo = data.demo || null;
      this.synced = true;
    },
    save: function () {
      this.saveLocal();
      var self = this;
      authority.push(this.progress, function (data) { self.apply(data); self.saveLocal(); });
    },
    getDraft: function (id) { return this.drafts[id] ? clone(this.drafts[id]) : null; },
    setDraft: function (id, design) { this.drafts[id] = clone(design); this.saveLocal(); }
  };

  /* -------------------------------------------------------------- audio */
  var audio = {
    ctx: null,
    ready: function () {
      if (!store.progress.settings.sound) return null;
      try { if (!this.ctx) this.ctx = new (window.AudioContext || window.webkitAudioContext)(); if (this.ctx.state === 'suspended') this.ctx.resume(); return this.ctx; } catch (e) { return null; }
    },
    tone: function (freq, dur, type, vol, slide) {
      var c = this.ready(); if (!c) return;
      try {
        var o = c.createOscillator(), g = c.createGain();
        o.type = type || 'sine'; o.frequency.value = freq;
        if (slide) o.frequency.exponentialRampToValueAtTime(slide, c.currentTime + dur);
        g.gain.value = vol || 0.05; g.gain.exponentialRampToValueAtTime(0.0001, c.currentTime + dur);
        o.connect(g); g.connect(c.destination); o.start(); o.stop(c.currentTime + dur);
      } catch (e) { /* ignore */ }
    },
    place: function () { this.tone(520, 0.08, 'triangle'); },
    run: function () { this.tone(330, 0.12, 'square', 0.03); },
    bounce: function () { this.tone(220, 0.06, 'sine', 0.03); },
    boing: function () { this.tone(300, 0.25, 'sine', 0.06, 700); },
    splash: function () { this.tone(900, 0.2, 'triangle', 0.03, 200); },
    buzz: function () { this.tone(180, 0.15, 'sawtooth', 0.02); },
    fail: function () { var s = this; s.tone(260, 0.18); setTimeout(function () { s.tone(200, 0.22); }, 140); },
    gulp: function () { var s = this; s.tone(420, 0.1, 'sine', 0.06, 260); setTimeout(function () { [523, 659, 784, 1047].forEach(function (f, i) { setTimeout(function () { s.tone(f, 0.16, 'triangle', 0.05); }, i * 100); }); }, 180); }
  };

  /* ================================================================ Game */
  function Game(root) {
    this.root = root;
    this.screen = 'map';
    this.sim = null; this.raf = null; this.acc = 0; this.lastFrame = 0; this.clock = 0; this.ghost = {}; this.failedRuns = {};
    this.build();
    this.showMap();
    var self = this;
    authority.load(function (data) {
      if (data) { store.apply(data); store.saveLocal(); }
      authority.daily(function (d2) {
        if (d2) { store.apply(d2); store.saveLocal(); }
        if (d2 && d2.dailyOpened) self.live.textContent = 'A new puzzle opened today: #' + d2.dailyOpened + '!';
        self.refreshHeader(); if (self.screen === 'map') self.showMap();
        store.save();
      });
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) self.stopLoop(); else if (self.screen === 'level') self.startLoop(); });
    window.addEventListener('pagehide', function () { store.saveLocal(); });
    window.addEventListener('resize', function () { if (self.screen === 'level') { self.sizeCanvas(); self.draw(); } });
  }

  Game.prototype.build = function () {
    var root = this.root, self = this;
    root.innerHTML = '';
    if (!root.id) root.id = 'rl-game';
    var head = el('div', 'rl-head');
    if (CFG.mascotUrl) { var m = el('div', 'rl-mascot'); var img = el('img'); img.alt = 'STEM-a-lotl, the Ascend axolotl mascot'; img.onerror = function () { m.hidden = true; }; img.src = CFG.mascotUrl; m.appendChild(img); head.appendChild(m); }
    head.appendChild(el('h1', 'rl-title', 'STEM-a-lotl: Rescue Lab'));
    head.appendChild(el('p', 'rl-subtitle', 'Build a path, roll the pellet, feed Lucas. 40 puzzles about how things move.'));
    root.appendChild(head);
    this.stats = el('div', 'rl-stats'); root.appendChild(this.stats);
    var row = el('div', 'rl-toggle-row');
    this.helpBtn = btn('rl-toggle', 'How to play ▾', function () { self.toggleHelp(); });
    this.soundBtn = btn('rl-toggle', '', function () { self.setSetting('sound', !store.progress.settings.sound); });
    this.ghostBtn = btn('rl-toggle', '', function () { self.setSetting('ghost', !store.progress.settings.ghost); });
    this.assistBtn = btn('rl-toggle', '', function () { self.setSetting('assist', !store.progress.settings.assist); });
    [this.helpBtn, this.soundBtn, this.ghostBtn, this.assistBtn].forEach(function (b) { row.appendChild(b); });
    root.appendChild(row);
    this.help = el('div', 'rl-instructions'); this.help.hidden = true; root.appendChild(this.help);
    this.live = el('div', 'rl-live'); this.live.setAttribute('aria-live', 'polite'); root.appendChild(this.live);
    this.area = el('div', 'rl-play-area'); root.appendChild(this.area);
    var foot = el('details', 'rl-foot'); foot.appendChild(el('summary', null, 'For parents & teachers')); this.footBody = el('div'); foot.appendChild(this.footBody); root.appendChild(foot);
    var exit = el('div', 'rl-exit'); var a = el('a', null, '← Back to the games hub'); a.href = CFG.hubUrl || '/user/'; exit.appendChild(a); root.appendChild(exit);
    this.overlay = el('div', 'rl-overlay'); this.overlay.setAttribute('role', 'dialog'); this.overlay.setAttribute('aria-modal', 'true');
    this.overlay.addEventListener('click', function (e) { if (e.target === self.overlay) self.closeOverlay(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && self.overlay.classList.contains('show')) self.closeOverlay(); });
    document.body.appendChild(this.overlay);
    this.refreshHeader();
  };

  Game.prototype.setSetting = function (k, v) { store.progress.settings[k] = v; store.save(); this.refreshHeader(); if (this.screen === 'level') { this.draw(); if (k === 'assist') this.renderDiag(); } };

  Game.prototype.refreshHeader = function () {
    var s = store.progress.settings, sum = Progress.summary(store.progress);
    this.soundBtn.textContent = 'Sound: ' + (s.sound ? 'on' : 'off'); this.soundBtn.setAttribute('aria-pressed', String(s.sound));
    this.ghostBtn.textContent = 'Ghost trail: ' + (s.ghost ? 'on' : 'off'); this.ghostBtn.setAttribute('aria-pressed', String(s.ghost));
    this.assistBtn.textContent = 'Assist: ' + (s.assist ? 'on' : 'off'); this.assistBtn.setAttribute('aria-pressed', String(s.assist));
    var keys;
    if (store.demo === 'review') keys = 'Keys: <strong>review</strong>';
    else if (store.demo) keys = 'Demo keys: <strong>' + store.keys + '</strong>';
    else if (!CFG.loggedIn) keys = 'Keys: <strong>log in</strong>';
    else if (store.access.hasPass) keys = 'Pond Pass: <strong>active</strong>';
    else if (store.keys != null) keys = 'Pond Keys: <strong>' + store.keys + '</strong>';
    else keys = 'Pond Keys: <strong>…</strong>';
    this.stats.innerHTML = '<div class="rl-stat">Meals: <strong>' + sum.fed + '</strong> / ' + Levels.LEVELS.length + '</div>' +
      '<div class="rl-stat">Stars: <strong>' + sum.stars + '</strong> / ' + sum.maxStars + '</div>' +
      '<div class="rl-stat">' + keys + '</div>';
    this.footBody.innerHTML = '<p>Rescue Lab practises engineering thinking with real physics: build a path, run it, watch what happens and change one thing. Each puzzle ends with a recap of the pellet’s journey and the science behind it: gravity and slopes, bouncing, wind and fans, drag and buoyancy in water, currents, waterfalls and timing around moving things.</p>' +
      '<p>So far: ' + plural(sum.fed, 'puzzle') + ' solved, ' + plural(sum.stars, 'star') + ', ' + plural(sum.designs, 'saved invention') + ', ' + plural(sum.runs, 'experiment run') + '.' + (sum.ideas.length ? ' Ideas practised: ' + esc(sum.ideas.join('; ')) + '.' : '') + '</p>' +
      '<p>These are practice observations from play, not a graded assessment, attendance or course credit.</p>' +
      '<p>How puzzles open: the first ' + CONFIG.freeLevels + ' are free. ' + (CONFIG.dailyUnlock ? 'After that, one more opens free each day the student visits (missed days do not add up). ' : '') + 'A Pond Key opens a puzzle right away and it stays open for good. The Full Pond Pass opens everything. Special pellet colours can also be bought with Pond Keys; everything else is earned by playing.</p>';
  };

  Game.prototype.toggleHelp = function () {
    var hidden = this.help.hidden;
    this.help.innerHTML = '<p><strong>Build.</strong> Tap a part in the tray, then tap the scene to place it. Drag parts to move them, or drag the yellow handle to tilt. With a part selected you can also use the arrow keys, and [ or ] to tilt.</p>' +
      '<p><strong>Run.</strong> Press Run and watch the pellet’s journey. If it misses, ' + GUIDE + ' tells you what really happened. Your build stays put, so change one thing and try again.</p>' +
      '<p><strong>Feed Lucas.</strong> He eats the pellet when it reaches the pink ring around his mouth, as long as it is not going too fast. Every meal ends with the lesson from that journey.</p>' +
      '<p><strong>Stars.</strong> One for feeding Lucas and two for the optional challenges. Stars and finished worlds unlock new pellet colours.</p>';
    this.help.hidden = !hidden;
    this.helpBtn.textContent = hidden ? 'Hide instructions ▴' : 'How to play ▾';
  };

  /* --------------------------------------------------------------- map */
  Game.prototype.showMap = function () {
    this.stopSim(); this.stopLoop();
    this.screen = 'map';
    var area = this.area, self = this;
    area.innerHTML = '';
    var next = this.nextLevel();

    var hero = el('div', 'rl-hero');
    var hc = el('canvas', 'rl-hero-canvas'); hc.setAttribute('aria-hidden', 'true');
    hero.appendChild(hc);
    var hb = el('div', 'rl-hero-body');
    hb.appendChild(el('h2', null, next ? (Progress.fed(store.progress) ? 'Next up: ' + next.name : 'Lucas is hungry!') : 'Unlock more puzzles'));
    hb.appendChild(el('p', null, next ? 'Puzzle ' + next.id + ' · ' + next.idea : 'Every open puzzle has all its stars. A new one opens tomorrow.'));
    var play = btn('rl-btn primary big', next && Progress.fed(store.progress) ? 'Play puzzle ' + next.id : 'Play', function () { if (next) self.openLevel(next.id); });
    play.disabled = !next;
    hb.appendChild(play);
    hero.appendChild(hb);
    area.appendChild(hero);
    this.drawHero(hc);

    area.appendChild(this.dailyCard());
    if (CFG.preview) area.appendChild(this.demoCard());
    else if (!CFG.loggedIn) { var n2 = el('div', 'rl-notice'); n2.innerHTML = 'The first ' + CONFIG.freeLevels + ' puzzles are free to play now. <a href="' + esc(CFG.loginUrl || '/login/') + '">Log in</a> to keep progress, get a free puzzle every day and use Pond Keys.'; area.appendChild(n2); }

    Levels.WORLDS.forEach(function (w) {
      var levels = Levels.LEVELS.filter(function (l) { return l.world === w.id; });
      var done = levels.filter(function (l) { return (store.progress.levels[l.id] || {}).done; }).length;
      var sec = el('section', 'rl-world rl-world-' + w.theme);
      var h = el('div', 'rl-world-head');
      h.innerHTML = '<h3>' + esc(w.name) + '</h3><span>' + esc(w.blurb) + ' · ' + done + '/10 fed</span>';
      sec.appendChild(h);
      var grid = el('div', 'rl-levels');
      levels.forEach(function (lvl) {
        var st = Progress.levelState(store.progress, lvl, store.access);
        var locked = st.access === 'locked';
        var b = btn('rl-level' + (locked ? ' is-locked' : '') + (next && lvl.id === next.id ? ' is-next' : '') + (st.done ? ' is-done' : ''), '', function () { if (locked) self.openUnlock(lvl); else self.openLevel(lvl.id); });
        var chip;
        if (st.done) chip = st.status === 'mastery' ? ['mastery', 'All stars'] : ['done', 'Fed'];
        else if (st.access === 'free') chip = ['free', 'Free'];
        else if (!locked && String(store.dailyOpened) === String(lvl.id)) chip = ['today', 'New today'];
        else if (!locked) chip = ['owned', 'Open'];
        else chip = ['locked', 'Locked'];
        b.innerHTML = '<span class="rl-lnum">' + lvl.id + '</span><span class="rl-lname">' + esc(lvl.name) + '</span><span class="rl-lstars" aria-hidden="true">' + starText(st.badgeCount) + '</span><span class="rl-chip ' + chip[0] + '">' + chip[1] + '</span>';
        b.setAttribute('aria-label', 'Puzzle ' + lvl.id + ', ' + lvl.name + ', ' + (locked ? 'locked' : st.badgeCount + ' of 3 stars'));
        grid.appendChild(b);
      });
      sec.appendChild(grid);
      area.appendChild(sec);
    });

    area.appendChild(this.lockerCard());
    this.refreshHeader();
  };

  Game.prototype.drawHero = function (canvas) {
    var w = canvas.clientWidth || 160, h = Math.round(w * 0.6), dpr = window.devicePixelRatio || 1;
    canvas.width = Math.round(w * dpr); canvas.height = Math.round(h * dpr);
    var c = canvas.getContext('2d'), s = w / 36;
    c.setTransform(dpr * s, 0, 0, dpr * s, 0, 0);
    var g = c.createLinearGradient(0, 0, 0, 21.6); g.addColorStop(0, '#dff1fa'); g.addColorStop(1, '#f7fbfd'); c.fillStyle = g; c.fillRect(0, 0, 36, 21.6);
    c.fillStyle = '#9c6b45'; c.fillRect(0, 18, 36, 4); c.fillStyle = '#6fbf4a'; c.fillRect(0, 17.4, 36, 1.2);
    var skin = Progress.skinById(store.progress.settings.skin) || Levels.SKINS[0];
    Art.lucas(c, { x: 24, y: 18, face: -1 }, { t: 1, open: 0.6, chew: -1 }, false);
    Art.pellet(c, { x: 8, y: 15.4, r: 2.4, angle: 0.4 }, skin, 0);
  };

  Game.prototype.dailyCard = function () {
    var card = el('div', 'rl-card rl-daily'), msg;
    var nl = Progress.nextLocked(store.access);
    if (store.demo === 'review') msg = '<strong>Review mode:</strong> every puzzle is open so you can check them all.';
    else if (!CFG.loggedIn && !CFG.preview) msg = '<strong>A new puzzle every day.</strong> Log in and one more puzzle opens free each day you visit.';
    else if (store.access.hasPass) msg = '<strong>Pond Pass:</strong> every puzzle is open.';
    else if (!nl) msg = '<strong>Every puzzle is open!</strong> Go back for the stars you missed.';
    else if (!CONFIG.dailyUnlock) msg = 'Puzzle ' + nl.id + ' opens with a Pond Key.';
    else if (store.dailyOpened) msg = '<strong>Today’s free puzzle:</strong> #' + store.dailyOpened + ' just opened. Come back tomorrow for #' + nl.id + '.';
    else if (store.access.lastDaily && store.access.lastDaily === store.today) msg = '<strong>Today’s free puzzle is open.</strong> Puzzle ' + nl.id + ' opens free tomorrow, or now with a Pond Key.';
    else msg = '<strong>A new puzzle every day.</strong> Puzzle ' + nl.id + ' opens free the next time you visit.';
    card.innerHTML = '<p>' + msg + '</p>';
    return card;
  };

  Game.prototype.demoCard = function () {
    var self = this, card = el('div', 'rl-card sand rl-demo');
    var s = DemoAuthority.state();
    card.innerHTML = '<h3>Preview controls</h3><p>This is a preview, not the live site. Keys here are pretend and nothing is bought. On the site, keys come from the parent shop.</p>';
    var row = el('div', 'rl-row left');
    var review = btn('rl-btn' + (s.mode === 'review' ? ' blue' : ''), 'Review all 40', function () { DemoAuthority.setMode('review'); self.reloadAuthority(); });
    var student = btn('rl-btn' + (s.mode !== 'review' ? ' blue' : ''), 'Play as a student', function () { if (s.mode === 'review') DemoAuthority.reset(); self.reloadAuthority(); });
    review.setAttribute('aria-pressed', String(s.mode === 'review')); student.setAttribute('aria-pressed', String(s.mode !== 'review'));
    row.appendChild(review); row.appendChild(student);
    if (s.mode !== 'review') {
      row.appendChild(btn('rl-btn', 'Pretend it’s tomorrow', function () { DemoAuthority.nextDay(); self.reloadAuthority(); }));
      row.appendChild(btn('rl-btn', 'Reset demo', function () { DemoAuthority.reset(); self.reloadAuthority(); }));
    }
    card.appendChild(row);
    return card;
  };

  Game.prototype.reloadAuthority = function () {
    var self = this;
    store.dailyOpened = null;
    authority.load(function (d) { store.apply(d); authority.daily(function (d2) { store.apply(d2); if (d2 && d2.dailyOpened) self.live.textContent = 'Demo: puzzle ' + d2.dailyOpened + ' opened for the day.'; self.showMap(); }); });
  };

  Game.prototype.nextLevel = function () {
    var i, st;
    for (i = 0; i < Levels.LEVELS.length; i++) { st = Progress.levelState(store.progress, Levels.LEVELS[i], store.access); if (!st.done && st.access !== 'locked') return Levels.LEVELS[i]; }
    for (i = 0; i < Levels.LEVELS.length; i++) { st = Progress.levelState(store.progress, Levels.LEVELS[i], store.access); if (st.access !== 'locked' && st.badgeCount < 3) return Levels.LEVELS[i]; }
    return null;
  };

  /* ------------------------------------------------------ pellet locker */
  Game.prototype.lockerCard = function () {
    var self = this, sec = el('section', 'rl-locker');
    sec.appendChild(el('h3', null, 'Pellet locker'));
    sec.appendChild(el('p', 'rl-fine', 'Pick the pellet Lucas eats. Earn new colours with stars and finished worlds. Colours change the look, never the physics.'));
    var grid = el('div', 'rl-skins');
    Levels.SKINS.forEach(function (skin) {
      var st = Progress.skinState(store.progress, skin, store.ownedSkins), chosen = store.progress.settings.skin === skin.id;
      var b = btn('rl-skin' + (st.open ? '' : ' is-locked') + (chosen ? ' is-chosen' : ''), '', function () {
        if (st.open) { store.progress.settings.skin = skin.id; store.save(); self.showMap(); self.live.textContent = skin.name + ' pellet chosen.'; }
        else if (st.keys) self.openSkin(skin);
        else self.live.textContent = skin.name + ': ' + st.how + '.';
      });
      var sw = el('canvas', 'rl-swatch'); sw.width = 64; sw.height = 64; sw.setAttribute('aria-hidden', 'true');
      var sc = sw.getContext('2d'); sc.setTransform(64 / 6, 0, 0, 64 / 6, 0, 0); Art.pellet(sc, { x: 3, y: 2.9, r: 2.4, angle: 0.3 }, skin, 0);
      b.appendChild(sw);
      b.appendChild(el('span', 'rl-skin-name', skin.name));
      b.appendChild(el('span', 'rl-skin-how', chosen ? 'Chosen' : (st.open ? 'Tap to use' : (st.keys ? plural((CONFIG.skinPrices || {})[skin.id] || 1, 'Pond Key') : st.how))));
      b.setAttribute('aria-pressed', String(chosen));
      grid.appendChild(b);
    });
    sec.appendChild(grid);
    return sec;
  };

  Game.prototype.openSkin = function (skin) {
    var self = this, m = el('div', 'rl-modal'), price = (CONFIG.skinPrices || {})[skin.id] || 1;
    var html = '<h3>' + esc(skin.name) + ' pellet</h3><canvas class="rl-swatch big" width="96" height="96" aria-hidden="true"></canvas>' +
      '<p>A special pellet colour' + (skin.trail ? ' with a ' + skin.trail + ' trail' : '') + '. It changes how the pellet looks, not how it rolls. Yours to keep on this student account.</p>';
    var canBuy = false;
    if (store.demo) { html += '<p class="rl-keys">Demo price: ' + plural(price, 'key') + ' · demo keys left: ' + store.keys + '</p><p class="rl-fine">Pretend keys. Nothing is bought in the preview.</p>'; canBuy = store.keys >= price; }
    else if (!CFG.loggedIn) html += '<p class="rl-fine">Log in to the student account to use Pond Keys.</p>';
    else if (!store.keysAvailable) html += '<p class="rl-fine">The key system is not reachable right now. Nothing was spent.</p>';
    else { html += '<p class="rl-keys">Cost: ' + plural(price, 'Pond Key') + ' · You have ' + store.keys + (store.keys >= price ? ' → ' + (store.keys - price) + ' after' : '') + '</p>'; canBuy = store.keys >= price; }
    m.innerHTML = html;
    var sc = m.querySelector('canvas').getContext('2d'); sc.setTransform(16, 0, 0, 16, 0, 0); Art.pellet(sc, { x: 3, y: 2.9, r: 2.5, angle: 0.3 }, skin, 0);
    var row = el('div', 'rl-row');
    if (canBuy) {
      var u = btn('rl-btn pass', 'Get it for ' + plural(price, 'key'), function () {
        u.disabled = true; u.textContent = 'Working…';
        authority.buySkin(skin.id, function (res) {
          if (res.ok) { store.apply(res.body); store.progress.settings.skin = skin.id; store.save(); self.closeOverlay(); self.showMap(); self.live.textContent = skin.name + ' pellet is yours!'; }
          else { u.disabled = false; u.textContent = 'Get it for ' + plural(price, 'key'); m.appendChild(el('p', 'rl-fine', res.error === 'network' ? 'No connection. Nothing was spent.' : res.error + ' No key was taken.')); }
        });
      });
      row.appendChild(u);
    } else if (CFG.loggedIn && store.keysAvailable && CFG.buyUrl && !store.demo) { var a = el('a', 'rl-btn pass', 'Get Pond Keys (parent shop)'); a.href = CFG.buyUrl; row.appendChild(a); }
    row.appendChild(btn('rl-btn', 'Not now', function () { self.closeOverlay(); }));
    m.appendChild(row);
    this.showOverlay(m);
  };

  /* ------------------------------------------------------- unlock flow */
  Game.prototype.openUnlock = function (lvl) {
    var self = this, m = el('div', 'rl-modal'), cost = CONFIG.keyCost, nl = Progress.nextLocked(store.access);
    var html = '<h3>Puzzle ' + lvl.id + ': ' + esc(lvl.name) + '</h3><p class="rl-sub">' + esc(lvl.intro) + '</p>' +
      '<ul><li><strong>Idea:</strong> ' + esc(lvl.idea) + '</li><li><strong>Tools:</strong> ' + esc(lvl.tray.map(function (t) { return trayLabel(t) + ' ×' + t.count; }).join(', ')) + '</li><li><strong>Included:</strong> the puzzle and both star challenges, replayable forever</li></ul>';
    if (CONFIG.dailyUnlock && nl) html += '<p class="rl-fine">' + (nl.id === lvl.id ? 'This is the next free daily puzzle. It opens free on your next day of play.' : 'Free daily puzzles open in order. Puzzle ' + nl.id + ' is next in line.') + '</p>';
    var canUnlock = false;
    if (store.demo) { html += '<p class="rl-keys">Demo: ' + plural(cost, 'key') + ' · demo keys left: ' + store.keys + '</p><p class="rl-fine">Pretend keys. Nothing is bought in the preview.</p>'; canUnlock = store.keys >= cost; }
    else if (!CFG.loggedIn) html += '<p class="rl-keys">Cost: ' + plural(cost, 'Pond Key') + '</p><p class="rl-fine">Log in to the student account to use keys. A parent buys keys in the Ascend shop; they never expire.</p>';
    else if (!store.keysAvailable) html += '<p class="rl-fine">The key system is not reachable right now, so this puzzle cannot be opened with a key yet. Your progress is safe.</p>';
    else { html += '<p class="rl-keys">Cost: ' + plural(cost, 'Pond Key') + ' · You have ' + (store.keys == null ? '…' : store.keys) + (store.keys != null && store.keys >= cost ? ' → ' + (store.keys - cost) + ' after' : '') + '</p><p class="rl-fine">One key opens this puzzle for good on this student account. Replaying never costs another key.</p>'; canUnlock = store.keys != null && store.keys >= cost; }
    m.innerHTML = html;
    var row = el('div', 'rl-row');
    if (!CFG.loggedIn && !CFG.preview) { var a = el('a', 'rl-btn pass', 'Log in'); a.href = CFG.loginUrl || '/login/'; row.appendChild(a); }
    else if (canUnlock) {
      var u = btn('rl-btn pass', 'Open with ' + plural(cost, 'key'), function () {
        u.disabled = true; u.textContent = 'Opening…';
        authority.unlock(lvl.id, function (res) {
          if (res.ok) { store.apply(res.body); store.saveLocal(); self.closeOverlay(); self.refreshHeader(); self.openLevel(lvl.id); self.live.textContent = 'Puzzle ' + lvl.id + ' is open for good.'; }
          else { u.disabled = false; u.textContent = 'Open with ' + plural(cost, 'key'); m.appendChild(el('p', 'rl-fine', res.error === 'network' ? 'No connection. Nothing was spent. Try again when you are back online.' : res.error + ' No key was taken.')); }
        });
      });
      row.appendChild(u);
    } else if (!store.demo && store.keysAvailable) {
      if (CFG.buyUrl) { var b = el('a', 'rl-btn pass', 'Get a Pond Key (parent shop)'); b.href = CFG.buyUrl; row.appendChild(b); }
      if (CFG.passUrl) { var p = el('a', 'rl-btn', 'Full Pond Pass'); p.href = CFG.passUrl; row.appendChild(p); }
    }
    row.appendChild(btn('rl-btn', 'Not now', function () { self.closeOverlay(); }));
    m.appendChild(row);
    this.showOverlay(m);
  };

  Game.prototype.showOverlay = function (content) { this.overlay.innerHTML = ''; this.overlay.appendChild(content); this.overlay.classList.add('show'); var f = content.querySelector('button, a'); if (f) f.focus(); };
  Game.prototype.closeOverlay = function () { this.overlay.classList.remove('show'); this.overlay.innerHTML = ''; };

  /* ------------------------------------------------------------- level */
  Game.prototype.openLevel = function (levelId) {
    var lvl = Levels.LEVELS[levelId - 1];
    if (Progress.accessOf(lvl, store.access) === 'locked') { this.openUnlock(lvl); return; }
    this.stopSim();
    this.screen = 'level';
    this.level = lvl;
    this.spec = Engine.resolve(lvl);
    this.design = store.getDraft(lvl.id) || Engine.newDesign(this.spec);
    this.undo = []; this.selected = null; this.placing = null; this.lastRun = null; this.drag = null; this.finalSim = null; this.eatenAt = -1; this.recent = [];
    if (!this.failedRuns[lvl.id]) this.failedRuns[lvl.id] = 0;
    this.renderLevel();
    this.say(lvl.intro);
    this.live.textContent = '';
    this.startLoop();
  };

  Game.prototype.renderLevel = function () {
    var self = this, lvl = this.level, area = this.area, world = Levels.WORLDS[lvl.world - 1];
    area.innerHTML = '';
    var head = el('div', 'rl-levelhead');
    var h = el('div'); h.innerHTML = '<span class="rl-lnum">' + esc(world.name) + ' · Puzzle ' + lvl.id + ' of ' + Levels.LEVELS.length + '</span>'; h.appendChild(el('h2', null, lvl.name)); head.appendChild(h);
    head.appendChild(btn('rl-btn', '← Map', function () { self.showMap(); }));
    area.appendChild(head);
    var obj = el('div', 'rl-objective');
    this.guide = el('div', 'rl-guide'); obj.appendChild(this.guide);
    this.bubble = el('div', 'rl-bubble'); obj.appendChild(this.bubble); area.appendChild(obj);

    this.sceneWrap = el('div', 'rl-scene-wrap');
    this.canvas = el('canvas', 'rl-scene'); this.canvas.setAttribute('role', 'img'); this.canvas.setAttribute('tabindex', '0');
    this.canvas.setAttribute('aria-label', 'Puzzle scene. ' + lvl.intro + ' Select a part and use arrow keys to move it, [ and ] to tilt, Delete to remove, N for the next part, Enter to run.');
    this.sceneWrap.appendChild(this.canvas); area.appendChild(this.sceneWrap);
    this.bindPointer();

    this.tray = el('div', 'rl-tray'); area.appendChild(this.tray);
    this.tools = el('div', 'rl-tools'); area.appendChild(this.tools);
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
    this.renderTray(); this.renderTools(); this.renderChallenges(); this.renderShelf();
    this.draw();
    if (this.canvas.scrollIntoView && window.innerWidth < 700) this.canvas.scrollIntoView({ block: 'nearest' });
  };

  /* A little Lucas portrait next to his speech bubble. */
  Game.prototype.drawGuide = function (mood) {
    this.guide.innerHTML = '';
    var c = el('canvas'); c.width = 96; c.height = 96; c.setAttribute('aria-hidden', 'true'); this.guide.appendChild(c);
    var x = c.getContext('2d'); x.setTransform(96 / 22, 0, 0, 96 / 22, 0, 0);
    Art.lucas(x, { x: 12, y: 19, face: -1 }, { t: 0.5, open: mood === 'happy' ? 0.7 : 0, chew: -1, sad: mood === 'puzzled' }, false);
    this.guide.className = 'rl-guide' + (mood === 'happy' ? ' happy' : (mood === 'puzzled' ? ' puzzled' : ''));
  };

  Game.prototype.say = function (text, mood) {
    if (!this.bubble) return;
    this.bubble.innerHTML = '<span class="rl-who">' + GUIDE + ' the axolotl</span>' + esc(text);
    this.drawGuide(mood || 'idle');
  };
  Game.prototype.setMessage = function (text) { this.message.textContent = text || ''; };

  /* ------------------------------------------------------ tray / tools */
  Game.prototype.renderTray = function () {
    var self = this, spec = this.spec, tray = this.tray;
    tray.innerHTML = '';
    spec.tray.forEach(function (t) {
      var used = Engine.trayUsed(self.design, t.type, t.len), left = t.count - used, on = !!(self.placing && self.placing.type === t.type && self.placing.len === t.len);
      var b = btn('rl-tray-btn', '', function () { self.placing = on ? null : t; self.selected = null; self.renderTray(); self.renderTools(); self.draw(); self.setMessage(self.placing ? 'Tap the scene to place the ' + trayLabel(t).toLowerCase() + '.' : ''); });
      b.innerHTML = '<span class="rl-ico rl-ico-' + t.type + '" aria-hidden="true"></span> ' + esc(trayLabel(t)) + ' <small>×' + left + '</small>';
      b.disabled = left <= 0 || !!self.sim;
      b.setAttribute('aria-pressed', String(on));
      tray.appendChild(b);
    });
  };

  Game.prototype.renderTools = function () {
    var self = this, tools = this.tools;
    tools.innerHTML = '';
    if (this.sim || this.selected == null) return;
    var p = this.design.parts[this.selected];
    if (!p) { this.selected = null; return; }
    tools.appendChild(el('span', 'rl-toolname', Engine.PART_SPEC[p.type].label + (p.angle != null ? ' · ' + p.angle + '°' : '')));
    if (p.angle != null) {
      tools.appendChild(btn('rl-btn', '↺ Tilt −15°', function () { self.pushUndo(); p.angle = clamp((p.angle || 0) - 15, -75, 75); self.afterEdit(); }));
      tools.appendChild(btn('rl-btn', '↻ Tilt +15°', function () { self.pushUndo(); p.angle = clamp((p.angle || 0) + 15, -75, 75); self.afterEdit(); }));
      tools.appendChild(btn('rl-btn', 'Flat', function () { self.pushUndo(); p.angle = 0; self.afterEdit(); }));
    }
    if (p.type === 'fan') tools.appendChild(btn('rl-btn', 'Turn fan (blows ' + ({ E: 'right', S: 'down', W: 'left', N: 'up' })[p.dir || 'E'] + ')', function () { self.pushUndo(); p.dir = { E: 'S', S: 'W', W: 'N', N: 'E' }[p.dir || 'E']; self.afterEdit(); }));
    tools.appendChild(btn('rl-btn', 'Remove', function () { self.pushUndo(); self.design.parts.splice(self.selected, 1); self.selected = null; self.afterEdit(); }));
  };

  Game.prototype.pushUndo = function () { this.undo.push(clone(this.design)); if (this.undo.length > 40) this.undo.shift(); };
  Game.prototype.doUndo = function () {
    if (this.sim) return;
    if (!this.undo.length) { this.setMessage('Nothing to undo.'); return; }
    this.design = this.undo.pop(); this.selected = null; this.placing = null; this.afterEdit();
  };
  Game.prototype.resetDesign = function () {
    if (this.sim) return;
    this.pushUndo(); this.design = Engine.newDesign(this.spec); this.selected = null; this.placing = null; this.afterEdit();
    this.setMessage('Build reset to the start. Undo brings it back.');
  };
  Game.prototype.afterEdit = function () {
    Engine.normalizeDesign(this.spec, this.design);
    this.finalSim = null; this.eatenAt = -1;
    store.setDraft(this.level.id, this.design);
    this.renderTray(); this.renderTools(); this.draw();
    this.successBox.innerHTML = '';
    if (!this.raf) this.startLoop();
  };

  /* ------------------------------------------------------------ canvas */
  Game.prototype.sizeCanvas = function () {
    if (!this.canvas) return;
    var w = Math.max(260, this.sceneWrap.clientWidth || 600), h = Math.round(w * 0.6), dpr = window.devicePixelRatio || 1;
    this.canvas.style.width = w + 'px'; this.canvas.style.height = h + 'px';
    this.canvas.width = Math.round(w * dpr); this.canvas.height = Math.round(h * dpr);
    this.scale = w / 100; this.dpr = dpr;
  };
  Game.prototype.toScene = function (e) { var r = this.canvas.getBoundingClientRect(); return { x: (e.clientX - r.left) / this.scale, y: (e.clientY - r.top) / this.scale }; };

  Game.prototype.startLoop = function () {
    if (this.raf) return;
    var self = this;
    this.lastFrame = 0;
    this.raf = requestAnimationFrame(function (t) { self.frame(t); });
  };
  Game.prototype.stopLoop = function () { if (this.raf) { cancelAnimationFrame(this.raf); this.raf = null; } };

  /* One loop drives both ambient animation and runs. Frame time only
     decides how many fixed steps to take; it never changes an outcome. */
  Game.prototype.frame = function (now) {
    this.raf = null;
    if (this.screen !== 'level' || document.hidden) return;
    if (!this.lastFrame) this.lastFrame = now;
    var dt = Math.min(0.1, (now - this.lastFrame) / 1000); this.lastFrame = now;
    if (!REDUCED || this.sim) this.clock += dt;
    if (this.sim) {
      this.acc += dt;
      var running = true, before = this.sim.events.length;
      while (this.acc >= Engine.DT && running) { running = this.sim.step(); this.acc -= Engine.DT; }
      var b = this.sim.body; this.recent.push([b.x, b.y]); if (this.recent.length > 16) this.recent.shift();
      for (var i = before; i < this.sim.events.length; i++) this.sound(this.sim.events[i]);
      if (!running) this.finishRun();
      this.draw();
    } else this.draw();
    var self = this;
    if (this.sim || !REDUCED) this.raf = requestAnimationFrame(function (t) { self.frame(t); });
  };

  Game.prototype.sound = function (e) {
    if (e.type === 'boing') audio.boing(); else if (e.type === 'bounce') audio.bounce(); else if (e.type === 'splash') audio.splash(); else if (e.type === 'bump' && e.kind === 'bee') audio.buzz(); else if (e.type === 'bump') audio.bounce();
  };

  Game.prototype.draw = function () {
    if (!this.canvas || this.screen !== 'level') return;
    var c = this.canvas.getContext('2d');
    c.setTransform(this.dpr * this.scale, 0, 0, this.dpr * this.scale, 0, 0);
    c.clearRect(0, 0, 100, 60);
    var live = this.sim || this.finalSim, sc = this.spec.scene;
    var t = this.sim ? this.sim.t : (this.finalSim ? this.finalSim.t : (REDUCED ? 0 : this.clock));
    var body = live ? live.body : { x: sc.spawn.x, y: sc.spawn.y, r: Engine.PELLET_R, angle: 0 };
    var eaten = !!(this.finalSim && this.finalSim.result === 'success');
    var skin = Progress.skinById(store.progress.settings.skin) || Levels.SKINS[0];
    if (!Progress.skinState(store.progress, skin, store.ownedSkins).open) skin = Levels.SKINS[0];
    Art.scene(c, sc, {
      t: t, clock: this.clock, parts: this.design.parts, selected: this.selected, preview: this.placing && this.hover ? this.previewPart(this.hover) : null,
      ghost: !live && store.progress.settings.ghost ? this.ghost[this.level.id] : null, showPaths: !this.sim, showRing: !eaten,
      lucas: { t: this.clock, open: live && live.nearMouth && !eaten ? 0.9 : 0, chew: eaten ? this.clock - this.eatenAt : -1, sad: !!(this.finalSim && !eaten) },
      body: eaten ? null : body, skin: skin, recent: this.sim ? this.recent : null
    });
  };

  Game.prototype.previewPart = function (pt) {
    var t = this.placing, p = { type: t.type, x: Math.round(pt.x), y: Math.round(pt.y) };
    if (t.type === 'fan') p.dir = 'E';
    else { p.angle = t.type === 'ramp' ? 15 : 0; if (t.len) p.len = t.len; }
    return p;
  };

  /* --------------------------------------------------- pointer + keys */
  Game.prototype.bindPointer = function () {
    var self = this, cv = this.canvas;
    cv.addEventListener('pointerdown', function (e) { self.onDown(e); });
    cv.addEventListener('pointermove', function (e) { self.onMove(e); });
    cv.addEventListener('pointerup', function (e) { self.onUp(e); });
    cv.addEventListener('pointercancel', function (e) { self.onUp(e, true); });
    cv.addEventListener('pointerleave', function () { self.hover = null; });
    cv.addEventListener('keydown', function (e) { self.onKey(e); });
  };

  Game.prototype.onDown = function (e) {
    if (this.sim) return;
    e.preventDefault();
    var pt = this.toScene(e);
    try { this.canvas.setPointerCapture(e.pointerId); } catch (x) { /* ignore */ }
    if (this.placing) {
      var lim = Engine.trayLimit(this.spec, this.placing.type, this.placing.len), used = Engine.trayUsed(this.design, this.placing.type, this.placing.len);
      if (used >= lim) { this.placing = null; this.renderTray(); return; }
      this.pushUndo(); var p = this.previewPart(pt); this.design.parts.push(p); this.selected = this.design.parts.length - 1;
      if (used + 1 >= lim) this.placing = null;
      audio.place(); this.afterEdit(); this.setMessage('Placed. Drag it to move, or drag the yellow handle to tilt.'); return;
    }
    var hit = this.hitPart(pt);
    if (hit != null) {
      var part = this.design.parts[hit];
      this.selected = hit;
      var s = Engine.partSegments(part)[0];
      var handle = part.angle != null && s && Math.hypot(pt.x - s.x2, pt.y - s.y2) < 3.4;
      this.pushUndo();
      this.drag = { kind: handle ? 'rotate' : 'move', idx: hit, ox: pt.x - part.x, oy: pt.y - part.y, moved: false };
      this.canvas.classList.add('grabbing'); this.renderTools(); this.draw();
    } else { this.selected = null; this.renderTools(); this.draw(); }
  };

  Game.prototype.onMove = function (e) {
    var pt = this.toScene(e); this.hover = pt;
    if (!this.drag) { if (this.placing && REDUCED) this.draw(); return; }
    e.preventDefault();
    var p = this.design.parts[this.drag.idx];
    if (this.drag.kind === 'rotate') { var a = Math.atan2(pt.y - p.y, pt.x - p.x) * 180 / Math.PI; p.angle = clamp(Math.round(a / 5) * 5, -75, 75); }
    else { p.x = clamp(pt.x - this.drag.ox, 2, 98); p.y = clamp(pt.y - this.drag.oy, 2, GROUND - 1); }
    this.drag.moved = true;
    if (REDUCED) this.draw();
  };

  Game.prototype.onUp = function (e, cancelled) {
    this.canvas.classList.remove('grabbing');
    if (!this.drag) return;
    var d = this.drag; this.drag = null;
    if (!d.moved) this.undo.pop();
    this.afterEdit();
    if (d.moved && !cancelled) this.setMessage(d.kind === 'rotate' ? 'Tilted to ' + this.design.parts[d.idx].angle + '°.' : 'Moved.');
  };

  Game.prototype.onKey = function (e) {
    if (this.sim) return;
    var p = this.selected != null ? this.design.parts[this.selected] : null, step = e.shiftKey ? 5 : 1, used = true;
    if (e.key === 'Enter') { e.preventDefault(); this.run(); return; }
    if (p && e.key === 'ArrowLeft') { this.pushUndo(); p.x = clamp(p.x - step, 2, 98); }
    else if (p && e.key === 'ArrowRight') { this.pushUndo(); p.x = clamp(p.x + step, 2, 98); }
    else if (p && e.key === 'ArrowUp') { this.pushUndo(); p.y = clamp(p.y - step, 2, GROUND - 1); }
    else if (p && e.key === 'ArrowDown') { this.pushUndo(); p.y = clamp(p.y + step, 2, GROUND - 1); }
    else if (p && p.angle != null && (e.key === '[' || e.key === ']')) { this.pushUndo(); p.angle = clamp(p.angle + (e.key === ']' ? 5 : -5), -75, 75); }
    else if (p && (e.key === 'Delete' || e.key === 'Backspace')) { this.pushUndo(); this.design.parts.splice(this.selected, 1); this.selected = null; }
    else if (e.key === 'n' || e.key === 'N') { if (this.design.parts.length) this.selected = ((this.selected == null ? -1 : this.selected) + 1) % this.design.parts.length; }
    else used = false;
    if (used) { e.preventDefault(); this.afterEdit(); }
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

  /* --------------------------------------------------------------- run */
  Game.prototype.run = function () {
    if (this.sim) return;
    var v = Engine.validateDesign(this.spec, this.design);
    if (!v.ok) { this.setMessage(v.issues[0]); return; }
    this.selected = null; this.placing = null;
    this.successBox.innerHTML = ''; this.diagBox.innerHTML = ''; this.finalSim = null; this.eatenAt = -1; this.recent = [];
    this.sim = Engine.createSim(this.spec, this.design);
    this.renderTray(); this.renderTools();
    this.runBtn.disabled = true; this.undoBtn.disabled = true; this.resetBtn.disabled = true;
    this.setMessage('Here goes the pellet…'); audio.run();
    this.acc = 0;
    this.stopLoop(); this.startLoop();
  };

  Game.prototype.stopSim = function () { this.sim = null; };

  Game.prototype.finishRun = function () {
    var sim = this.sim, spec = this.spec, lvl = this.level, self = this;
    this.sim = null; this.finalSim = sim;
    this.runBtn.disabled = false; this.undoBtn.disabled = false; this.resetBtn.disabled = false;
    var run = { ok: sim.result === 'success', result: sim.result, diag: sim.diag, t: sim.t, trail: sim.trail, events: sim.events, sim: sim };
    this.lastRun = run;
    this.ghost[lvl.id] = sim.trail;
    store.progress.runs++;
    this.renderTray(); this.renderTools();
    if (!run.ok) {
      store.progress.fails++; this.failedRuns[lvl.id]++;
      store.save();
      this.setMessage('Not yet. Your build is still here.');
      this.say(run.diag.text, 'puzzled'); audio.fail();
      this.renderDiag();
      return;
    }
    this.eatenAt = this.clock;
    var before = Progress.earnedSkinIds(store.progress, store.ownedSkins);
    var badges = [];
    lvl.challenges.forEach(function (ch) { if (Engine.checkRule(spec, self.design, run, ch.rule)) badges.push(ch.badge); });
    var wasDone = !!(store.progress.levels[lvl.id] && store.progress.levels[lvl.id].done);
    var prev = clone((store.progress.levels[lvl.id] || { badges: {} }).badges);
    Progress.recordSuccess(store.progress, lvl.id, badges, { time: run.t, parts: this.design.parts.length });
    var newSkins = Progress.earnedSkinIds(store.progress, store.ownedSkins).filter(function (id) { return before.indexOf(id) === -1; });
    store.save();
    this.refreshHeader();
    this.setMessage('Yum! Lucas ate it.'); this.say('Yum, thank you! ' + run.diag.text, 'happy'); audio.gulp();
    this.renderSuccess(run, badges, prev, wasDone, newSkins);
    this.renderChallenges(); this.renderShelf();
    try { this.root.dispatchEvent(new CustomEvent('ascend:game-complete', { bubbles: true, detail: { game: 'rescue-lab', level: lvl.id, badges: badges, time: run.t } })); } catch (e) { /* ignore */ }
  };

  Game.prototype.renderDiag = function () {
    var run = this.lastRun, box = this.diagBox, self = this;
    if (!box) return;
    box.innerHTML = '';
    if (!run || run.ok) return;
    var j = Engine.journey(run.sim);
    var d = el('div', 'rl-diag bad');
    d.innerHTML = '<p><strong>What happened:</strong> ' + esc(run.diag.text) + '</p><p class="rl-journey">' + esc(j.text) + '</p>' + (run.diag.hint ? '<p class="rl-hint">' + esc(run.diag.hint) + '</p>' : '');
    var row = el('div', 'rl-row left');
    row.appendChild(btn('rl-btn blue', '↻ Run again', function () { self.run(); }));
    if (store.progress.settings.assist && this.failedRuns[this.level.id] >= 2) row.appendChild(btn('rl-btn', 'Give me a nudge', function () { self.say(self.level.hint || self.nudge()); }));
    d.appendChild(row);
    box.appendChild(d);
  };

  Game.prototype.nudge = function () {
    var code = this.lastRun && this.lastRun.diag.code, sc = this.spec.scene;
    if (code === 'missed_part') return 'Put a part right under the food dispenser so the pellet lands on it.';
    if (code === 'too_fast' || code === 'overshot') return 'Too much speed. Make your ramp less steep, or start it lower.';
    if (code === 'stuck') return 'The pellet is resting on your part. Tilt the part more, maybe 20° or 30°.';
    if (code === 'swept') return 'Waterfalls pull straight down. Either cross them fast, or use them to drop onto Lucas.';
    if (code === 'bumped') return 'Watch the dotted line: that is where the bee goes. Change the pellet’s speed so they are not in the same place at the same time.';
    if (code === 'fell') return 'The pellet needs more sideways speed to cross the gap. A longer or steeper ramp helps.';
    if ((sc.wind || []).length) return 'Watch which way the wind blows. Let it carry the pellet toward Lucas, or push back with a fan.';
    if ((sc.water || []).length) return 'In water the pellet sinks slowly and drifts with the current. Drop it in upstream of Lucas.';
    return 'Change just one thing, run again, and compare with the ghost trail from last time.';
  };

  Game.prototype.renderSuccess = function (run, badges, prev, wasDone, newSkins) {
    var lvl = this.level, self = this, box = el('div', 'rl-success');
    var j = Engine.journey(run.sim), earned = Object.keys(store.progress.levels[lvl.id].badges).length;
    var fresh = badges.filter(function (b) { return !prev[b]; });
    var html = '<h3>' + (wasDone ? 'Yum, again!' : 'Lucas is fed!') + ' <span class="rl-stars" aria-label="' + earned + ' of 3 stars">' + starText(earned) + '</span></h3>' +
      '<div class="rl-journey-card"><p class="rl-eyebrow">The journey</p><p>' + esc(j.text) + '</p>' +
      '<p class="rl-facts">Top speed ' + j.topSpeed + ' units per second · longest time in the air ' + j.airtime + ' s</p></div>' +
      '<div class="rl-lesson"><p class="rl-eyebrow">The lesson</p><p>' + esc(lvl.lesson) + '</p>' +
      (j.notes.length ? '<ul>' + j.notes.map(function (n) { return '<li>' + esc(n) + '</li>'; }).join('') + '</ul>' : '') + '</div>';
    if (fresh.length) html += '<p class="rl-reward">New star' + (fresh.length > 1 ? 's' : '') + ': ' + fresh.map(function (b) { var c = lvl.challenges.filter(function (x) { return x.badge === b; })[0]; return c ? esc(c.text.split(':')[0]) : esc(b); }).join(', ') + '</p>';
    newSkins.forEach(function (id) { var s = Progress.skinById(id); html += '<p class="rl-reward">New pellet unlocked: ' + esc(s.name) + '! Pick it in the pellet locker.</p>'; });
    box.innerHTML = html;
    var row = el('div', 'rl-row');
    var nextLvl = Levels.LEVELS[lvl.id] || null, nextOpen = nextLvl && Progress.accessOf(nextLvl, store.access) !== 'locked';
    if (nextLvl) row.appendChild(btn('rl-btn primary', nextOpen ? 'Next puzzle →' : 'Next puzzle (locked)', function () { self.openLevel(nextLvl.id); }));
    var untried = lvl.challenges.filter(function (c) { return !store.progress.levels[lvl.id].badges[c.badge]; })[0];
    if (untried) row.appendChild(btn('rl-btn blue', 'Try for a star', function () { self.successBox.innerHTML = ''; self.finalSim = null; self.eatenAt = -1; self.say('Star challenge: ' + untried.text); self.challengeBox.scrollIntoView({ block: 'nearest' }); }));
    row.appendChild(btn('rl-btn', 'Save design', function () {
      var lv = store.progress.levels[lvl.id], name = 'Design ' + ((lv.designs || []).length + 1) + ' · ' + run.t.toFixed(1) + ' s';
      Progress.saveDesign(store.progress, lvl.id, self.design, name, { time: Math.round(run.t * 100) / 100, parts: self.design.parts.length }, Date.now());
      store.save(); self.renderShelf(); self.setMessage('Saved to your inventions shelf.');
    }));
    row.appendChild(btn('rl-btn', 'Map', function () { self.showMap(); }));
    box.appendChild(row);
    this.successBox.innerHTML = ''; this.successBox.appendChild(box);
    this.diagBox.innerHTML = '';
  };

  Game.prototype.renderChallenges = function () {
    var lvl = this.level, box = this.challengeBox, lv = store.progress.levels[lvl.id] || { badges: {} };
    box.innerHTML = '<h4>Stars</h4>';
    [{ badge: 'rescue', text: 'Feed Lucas.' }].concat(lvl.challenges).forEach(function (c) {
      var d = el('div', 'rl-challenge' + (lv.badges[c.badge] ? ' earned' : ''));
      d.innerHTML = '<span class="rl-cstar" aria-hidden="true">' + (lv.badges[c.badge] ? '★' : '☆') + '</span><span>' + esc(c.text) + (lv.badges[c.badge] ? ' <em>Earned</em>' : '') + '</span>';
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
      it.appendChild(el('span', null, d.name + ' · ' + plural(d.design.parts.length, 'part')));
      it.appendChild(btn(null, 'Load', function () { if (self.sim) return; self.pushUndo(); self.design = clone(d.design); self.afterEdit(); self.setMessage('Loaded. Run it or change it.'); }));
      items.appendChild(it);
    });
    box.appendChild(items);
  };

  /* --------------------------------------------------------------- init */
  function init() {
    store.loadLocal();
    var roots = document.querySelectorAll('.rl-root');
    for (var i = 0; i < roots.length; i++) if (!roots[i].__rl) roots[i].__rl = new Game(roots[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  window.AscendRescueLab = { store: store };
})();
