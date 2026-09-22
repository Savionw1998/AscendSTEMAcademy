/* Ascend Code-a-lotl: program an axolotl with arrow and repeat blocks. */
(function () {
  'use strict';

  // Map legend: A = axolotl start, G = home cave, F = shrimp, # = rock, . = water.
  var LEVELS = [
    { name: 'First Swim', par: 3, map: ['A..G'],
      tip: 'Click an arrow to add it to your program, then press Run. Each arrow moves one square.' },
    { name: 'Snack Time', par: 4, map: ['A.F.G'],
      tip: 'Swim over every shrimp before you reach home.' },
    { name: 'Around the Corner', par: 4, map: ['A..', '#.#', '#.G'],
      tip: 'Programs run in order, top to bottom. That is called a sequence.' },
    { name: 'Long River', par: 2, map: ['A......G'],
      tip: 'Seven arrows is a lot! Add a Repeat block, put one arrow inside, then close it.' },
    { name: 'Staircase', par: 3, map: ['A....', '.F...', '..F..', '...F.', '....G'],
      tip: 'Look for a pattern that happens again and again. A Repeat block can hold more than one arrow.' },
    { name: 'Switchback', par: 10, map: ['AF..', '###.', '..F.', '.###', '.F.G'],
      tip: 'Break the path into straight pieces. Each straight piece can be its own Repeat.' },
    { name: 'Down the Steps', par: 4, map: ['A..####', '##.F.##', '####.F.', '######G'],
      tip: 'Find the smallest chunk of moves that repeats.' },
    { name: 'Big Steps', par: 5, map: ['A.#####', '#.#####', '#.F.###', '###.###', '###.F.#', '#####.#', '#####.G'],
      tip: 'You can put a Repeat inside another Repeat. That is called a nested loop.' },
    { name: 'Over the Reef', par: 6, map: ['F...F', '.###.', 'A###G'],
      tip: 'Sometimes the best path goes up and over.' },
    { name: 'Serpent Stream', par: 10, map: ['A..F...', '######.', '...F...', '.######', '...F..G'],
      tip: 'Plan the whole route first, then turn each straight stretch into a Repeat.' },
    { name: 'Grand Staircase', par: 4, map: ['A..F######', '###...F###', '######...F', '#########G'],
      tip: 'Try a Repeat 3 that holds another Repeat 3 plus one more arrow.' },
    { name: 'Axolotl Olympics', par: 11, map: ['A.#....', '..#F#..', '..#.#F.', '.F..#.G'],
      tip: 'Debugging tip: run your program, watch where the axolotl goes wrong, then fix just that part.' }
  ];

  var DIRS = { U: [-1, 0], D: [1, 0], L: [0, -1], R: [0, 1] };
  var ARROWS = { U: '↑', D: '↓', L: '←', R: '→' };
  var NAMES = { U: 'up', D: 'down', L: 'left', R: 'right' };
  var ANGLE = { R: 0, D: 90, L: 180, U: -90 };
  var MAX_STEPS = 200;
  var MAX_BLOCKS = 40;

  // ---- Engine (pure, no DOM; exported for tests) ----
  var Engine = {
    parse: function (level) {
      var grid = level.map.map(function (row) { return row.split(''); });
      var info = { rows: grid.length, cols: grid[0].length, grid: grid, food: {}, foodCount: 0 };
      grid.forEach(function (row, r) {
        row.forEach(function (ch, c) {
          if (ch === 'A') info.start = { r: r, c: c };
          if (ch === 'G') info.goal = { r: r, c: c };
          if (ch === 'F') { info.food[r + ',' + c] = true; info.foodCount++; }
        });
      });
      return info;
    },

    countBlocks: function (program) {
      return program.reduce(function (n, node) {
        return n + 1 + (node.t === 'loop' ? Engine.countBlocks(node.body) : 0);
      }, 0);
    },

    // Expand loops into a flat list of directions; returns null past MAX_STEPS.
    flatten: function (program, out) {
      out = out || [];
      for (var i = 0; i < program.length; i++) {
        var node = program[i];
        if (node.t === 'move') {
          out.push(node.d);
        } else {
          for (var k = 0; k < node.n; k++) {
            if (Engine.flatten(node.body, out) === null) return null;
          }
        }
        if (out.length > MAX_STEPS) return null;
      }
      return out;
    },

    // Returns { steps: [{r, c, d, ate, bump}], result: win|bump|hungry|short|toolong|empty }.
    simulate: function (level, program) {
      var info = Engine.parse(level);
      var moves = Engine.flatten(program);
      if (moves === null) return { steps: [], result: 'toolong' };
      if (moves.length === 0) return { steps: [], result: 'empty' };
      var r = info.start.r, c = info.start.c, eaten = 0, steps = [];
      for (var i = 0; i < moves.length; i++) {
        var d = moves[i], nr = r + DIRS[d][0], nc = c + DIRS[d][1];
        if (nr < 0 || nc < 0 || nr >= info.rows || nc >= info.cols || info.grid[nr][nc] === '#') {
          steps.push({ r: r, c: c, d: d, bump: true });
          return { steps: steps, result: 'bump' };
        }
        r = nr; c = nc;
        var ate = !!info.food[r + ',' + c];
        if (ate) { delete info.food[r + ',' + c]; eaten++; }
        steps.push({ r: r, c: c, d: d, ate: ate });
        if (r === info.goal.r && c === info.goal.c && eaten === info.foodCount) {
          return { steps: steps, result: 'win' };
        }
      }
      var atGoal = r === info.goal.r && c === info.goal.c;
      return { steps: steps, result: atGoal ? 'hungry' : 'short' };
    },

    stars: function (level, program) {
      var blocks = Engine.countBlocks(program);
      if (blocks <= level.par) return 3;
      if (blocks <= level.par + 2) return 2;
      return 1;
    }
  };

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { Engine: Engine, LEVELS: LEVELS };
    return;
  }

  // ---- Progress (localStorage, synced to the server for logged-in students) ----
  var STORE_KEY = 'ascend_code_a_lotl_v1';
  var CFG = window.AscendCAL || {};

  function loadLocal() {
    try {
      var p = JSON.parse(window.localStorage.getItem(STORE_KEY));
      if (p && typeof p.unlocked === 'number') return { unlocked: p.unlocked, stars: p.stars || {} };
    } catch (e) { /* storage unavailable */ }
    return { unlocked: 1, stars: {} };
  }

  function saveLocal(p) {
    try { window.localStorage.setItem(STORE_KEY, JSON.stringify(p)); } catch (e) { /* ignore */ }
  }

  function mergeInto(p, other) {
    if (!other) return;
    p.unlocked = Math.max(p.unlocked, other.unlocked || 1);
    Object.keys(other.stars || {}).forEach(function (k) {
      p.stars[k] = Math.max(p.stars[k] || 0, other.stars[k]);
    });
  }

  function syncServer(p, onMerged) {
    if (!CFG.loggedIn || !CFG.restUrl || !window.fetch) return;
    window.fetch(CFG.restUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce },
      body: JSON.stringify(p)
    }).then(function (res) { return res.ok ? res.json() : null; })
      .then(function (server) { if (server && onMerged) onMerged(server); })
      .catch(function () { /* offline: local progress still saved */ });
  }

  // ---- UI ----
  var AXOLOTL_SVG =
    '<svg viewBox="0 0 100 100" aria-hidden="true">' +
    '<path d="M18 55 Q2 48 6 62 Q14 60 22 60 Z" fill="#f59fb8"/>' +
    '<ellipse cx="42" cy="56" rx="26" ry="14" fill="#f7a8c0"/>' +
    '<circle cx="70" cy="50" r="17" fill="#f9b8cc"/>' +
    '<g stroke="#e0527d" stroke-width="3.5" stroke-linecap="round">' +
    '<path d="M60 38 L52 26"/><path d="M64 35 L60 22"/><path d="M69 34 L69 21"/>' +
    '<path d="M60 62 L52 74"/><path d="M64 65 L60 78"/>' +
    '</g>' +
    '<circle cx="76" cy="46" r="3" fill="#2b1b24"/><circle cx="77" cy="45" r="1" fill="#fff"/>' +
    '<path d="M76 56 Q80 59 84 55" stroke="#2b1b24" stroke-width="2" fill="none" stroke-linecap="round"/>' +
    '<path d="M36 68 L32 76 M50 68 L48 76" stroke="#f59fb8" stroke-width="5" stroke-linecap="round"/>' +
    '</svg>';

  var MESSAGES = {
    bump: 'Bonk! The axolotl bumped into something. Find the step that went wrong and fix it.',
    hungry: 'Home, but still hungry! Swim over every shrimp first.',
    short: 'The program ended before the axolotl got home. Add more steps!',
    toolong: 'Whoa, that program runs over ' + MAX_STEPS + ' steps. Try smaller repeat numbers.',
    empty: 'Your program is empty. Click an arrow to add a step.'
  };

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function Game(root) {
    this.root = root;
    this.progress = loadLocal();
    this.program = [];
    this.stack = [];
    this.repeatCount = 3;
    this.running = false;
    this.fast = false;
    var start = parseInt(root.getAttribute('data-start-level'), 10) || 1;
    this.levelIndex = Math.min(start, this.progress.unlocked, LEVELS.length) - 1;
    this.build();
    this.loadLevel(this.levelIndex);

    var self = this;
    syncServer(this.progress, function (server) {
      mergeInto(self.progress, server);
      saveLocal(self.progress);
      self.renderLevelPicker();
    });
  }

  Game.prototype.build = function () {
    var self = this, root = this.root;
    root.innerHTML = '';
    root.setAttribute('tabindex', '-1');

    var header = el('div', 'acl-header');
    header.appendChild(el('h2', 'acl-title', 'Code-a-lotl'));
    this.subtitle = el('p', 'acl-subtitle');
    header.appendChild(this.subtitle);
    root.appendChild(header);

    this.picker = el('div', 'acl-levels');
    this.picker.setAttribute('role', 'group');
    this.picker.setAttribute('aria-label', 'Choose a level');
    root.appendChild(this.picker);

    var main = el('div', 'acl-main');
    this.board = el('div', 'acl-board');
    this.board.setAttribute('role', 'img');
    main.appendChild(this.board);

    var side = el('div', 'acl-side');

    var palette = el('div', 'acl-palette');
    ['U', 'L', 'R', 'D'].forEach(function (d) {
      var b = el('button', 'acl-btn acl-arrow acl-arrow-' + d, ARROWS[d]);
      b.type = 'button';
      b.setAttribute('aria-label', 'Add move ' + NAMES[d]);
      b.addEventListener('click', function () { self.add({ t: 'move', d: d }); });
      palette.appendChild(b);
    });
    side.appendChild(palette);

    var loopRow = el('div', 'acl-looprow');
    var minus = el('button', 'acl-btn acl-small', '−');
    minus.type = 'button';
    minus.setAttribute('aria-label', 'Fewer repeats');
    this.countLabel = el('span', 'acl-count');
    var plus = el('button', 'acl-btn acl-small', '+');
    plus.type = 'button';
    plus.setAttribute('aria-label', 'More repeats');
    minus.addEventListener('click', function () { self.setRepeatCount(self.repeatCount - 1); });
    plus.addEventListener('click', function () { self.setRepeatCount(self.repeatCount + 1); });
    this.repeatBtn = el('button', 'acl-btn acl-repeat');
    this.repeatBtn.type = 'button';
    this.repeatBtn.addEventListener('click', function () {
      self.add({ t: 'loop', n: self.repeatCount, body: [] });
    });
    this.endBtn = el('button', 'acl-btn acl-end', 'Close repeat');
    this.endBtn.type = 'button';
    this.endBtn.addEventListener('click', function () { self.closeLoop(); });
    loopRow.appendChild(minus);
    loopRow.appendChild(this.countLabel);
    loopRow.appendChild(plus);
    loopRow.appendChild(this.repeatBtn);
    loopRow.appendChild(this.endBtn);
    side.appendChild(loopRow);

    var progHead = el('div', 'acl-proghead');
    progHead.appendChild(el('span', 'acl-proglabel', 'Your program'));
    this.blockCount = el('span', 'acl-blockcount');
    progHead.appendChild(this.blockCount);
    side.appendChild(progHead);

    this.programView = el('div', 'acl-program');
    this.programView.setAttribute('aria-live', 'polite');
    side.appendChild(this.programView);

    var controls = el('div', 'acl-controls');
    this.runBtn = el('button', 'acl-btn acl-run', '▶ Run');
    this.runBtn.type = 'button';
    this.runBtn.addEventListener('click', function () { self.run(); });
    var undo = el('button', 'acl-btn', 'Undo');
    undo.type = 'button';
    undo.addEventListener('click', function () { self.undo(); });
    var clear = el('button', 'acl-btn', 'Clear');
    clear.type = 'button';
    clear.addEventListener('click', function () { self.clear(); });
    this.speedBtn = el('button', 'acl-btn', 'Speed: normal');
    this.speedBtn.type = 'button';
    this.speedBtn.setAttribute('aria-pressed', 'false');
    this.speedBtn.addEventListener('click', function () {
      self.fast = !self.fast;
      self.speedBtn.textContent = 'Speed: ' + (self.fast ? 'fast' : 'normal');
      self.speedBtn.setAttribute('aria-pressed', String(self.fast));
    });
    this.editButtons = [undo, clear];
    controls.appendChild(this.runBtn);
    controls.appendChild(undo);
    controls.appendChild(clear);
    controls.appendChild(this.speedBtn);
    side.appendChild(controls);

    this.message = el('div', 'acl-message');
    this.message.setAttribute('role', 'status');
    side.appendChild(this.message);

    this.nextBtn = el('button', 'acl-btn acl-next', 'Next level →');
    this.nextBtn.type = 'button';
    this.nextBtn.hidden = true;
    this.nextBtn.addEventListener('click', function () { self.loadLevel(self.levelIndex + 1); });
    side.appendChild(this.nextBtn);

    main.appendChild(side);
    root.appendChild(main);

    var foot = el('details', 'acl-foot');
    foot.appendChild(el('summary', null, 'For parents & teachers'));
    foot.appendChild(el('p', null,
      'Code-a-lotl practices core computer-science ideas: sequencing (steps run in order), ' +
      'loops (Repeat blocks), nested loops, pattern recognition and debugging. ' +
      'Stars reward shorter programs: 3 stars means the student found an efficient solution. ' +
      'Keyboard: arrow keys add moves, Enter runs, Backspace undoes.'));
    root.appendChild(foot);

    root.addEventListener('keydown', function (e) {
      if (e.target && /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return;
      var key = { ArrowUp: 'U', ArrowDown: 'D', ArrowLeft: 'L', ArrowRight: 'R' }[e.key];
      if (key) { e.preventDefault(); self.add({ t: 'move', d: key }); }
      else if (e.key === 'Enter' && e.target === root) { e.preventDefault(); self.run(); }
      else if (e.key === 'Backspace') { e.preventDefault(); self.undo(); }
    });

    this.setRepeatCount(this.repeatCount);
  };

  Game.prototype.renderLevelPicker = function () {
    var self = this;
    this.picker.innerHTML = '';
    LEVELS.forEach(function (lvl, i) {
      var n = i + 1, stars = self.progress.stars[n] || 0, locked = n > self.progress.unlocked;
      var b = el('button', 'acl-lvl' + (i === self.levelIndex ? ' is-current' : '') + (locked ? ' is-locked' : ''));
      b.type = 'button';
      b.disabled = locked;
      b.appendChild(el('span', 'acl-lvl-n', locked ? '🔒' : String(n)));
      b.appendChild(el('span', 'acl-lvl-stars', stars ? '★'.repeat(stars) : ' '));
      b.setAttribute('aria-label', 'Level ' + n + (locked ? ', locked' : ', ' + stars + ' stars'));
      if (i === self.levelIndex) b.setAttribute('aria-current', 'true');
      b.addEventListener('click', function () { if (!self.running) self.loadLevel(i); });
      self.picker.appendChild(b);
    });
  };

  Game.prototype.loadLevel = function (i) {
    this.levelIndex = i;
    this.level = LEVELS[i];
    this.info = Engine.parse(this.level);
    this.program = [];
    this.stack = [];
    this.nextBtn.hidden = true;
    this.subtitle.textContent = 'Level ' + (i + 1) + ': ' + this.level.name;
    this.say('💡 ' + this.level.tip);
    this.renderLevelPicker();
    this.renderBoard();
    this.renderProgram();
  };

  Game.prototype.renderBoard = function () {
    var info = this.info, board = this.board;
    board.innerHTML = '';
    board.style.setProperty('--acl-cols', info.cols);
    board.style.setProperty('--acl-rows', info.rows);
    board.setAttribute('aria-label',
      'Pond ' + info.cols + ' squares wide and ' + info.rows + ' tall with ' + info.foodCount + ' shrimp to collect.');
    this.cells = {};
    for (var r = 0; r < info.rows; r++) {
      for (var c = 0; c < info.cols; c++) {
        var ch = info.grid[r][c];
        var cell = el('div', 'acl-cell' + (ch === '#' ? ' is-rock' : ''));
        if (ch === '#') cell.textContent = '🪨';
        if (ch === 'F') { cell.classList.add('has-food'); cell.textContent = '🦐'; }
        if (ch === 'G') { cell.classList.add('is-goal'); cell.textContent = '🏠'; }
        this.cells[r + ',' + c] = cell;
        board.appendChild(cell);
      }
    }
    this.axo = el('div', 'acl-axo');
    this.axo.innerHTML = AXOLOTL_SVG;
    board.appendChild(this.axo);
    this.placeAxo(info.start.r, info.start.c, 'R', true);
  };

  Game.prototype.placeAxo = function (r, c, d, instant) {
    var axo = this.axo;
    if (instant) axo.classList.add('no-anim');
    axo.style.left = (c * 100 / this.info.cols) + '%';
    axo.style.top = (r * 100 / this.info.rows) + '%';
    axo.firstChild.style.transform = 'rotate(' + ANGLE[d] + 'deg)' + (d === 'L' ? ' scaleY(-1)' : '');
    if (instant) { void axo.offsetWidth; axo.classList.remove('no-anim'); }
  };

  Game.prototype.target = function () {
    return this.stack.length ? this.stack[this.stack.length - 1].body : this.program;
  };

  Game.prototype.add = function (node) {
    if (this.running) return;
    if (Engine.countBlocks(this.program) >= MAX_BLOCKS) {
      this.say('That is a big program! Try using Repeat blocks to make it shorter.');
      return;
    }
    this.target().push(node);
    if (node.t === 'loop') this.stack.push(node);
    this.nextBtn.hidden = true;
    this.renderProgram();
  };

  Game.prototype.closeLoop = function () {
    if (this.running || !this.stack.length) return;
    this.stack.pop();
    this.renderProgram();
  };

  // Undo steps back through editing: reopens a closed Repeat to remove its last block.
  Game.prototype.undo = function () {
    if (this.running) return;
    var tgt = this.target();
    if (!tgt.length) {
      if (this.stack.length) { this.stack.pop(); this.target().pop(); }
    } else if (tgt[tgt.length - 1].t === 'loop') {
      this.stack.push(tgt[tgt.length - 1]);
      return this.undo();
    } else {
      tgt.pop();
    }
    this.nextBtn.hidden = true;
    this.renderProgram();
  };

  Game.prototype.clear = function () {
    if (this.running) return;
    this.program = [];
    this.stack = [];
    this.nextBtn.hidden = true;
    this.renderBoard();
    this.renderProgram();
  };

  Game.prototype.setRepeatCount = function (n) {
    this.repeatCount = Math.max(2, Math.min(9, n));
    this.countLabel.textContent = this.repeatCount + '×';
    this.repeatBtn.textContent = 'Repeat ' + this.repeatCount + '×';
  };

  Game.prototype.renderProgram = function () {
    var self = this, view = this.programView;
    view.innerHTML = '';
    function renderList(list, into) {
      list.forEach(function (node) {
        if (node.t === 'move') {
          var chip = el('span', 'acl-chip acl-chip-' + node.d, ARROWS[node.d]);
          chip.setAttribute('aria-label', NAMES[node.d]);
          into.appendChild(chip);
        } else {
          var open = self.stack.indexOf(node) !== -1;
          var box = el('span', 'acl-loop' + (open ? ' is-open' : ''));
          box.appendChild(el('span', 'acl-loop-label', 'Repeat ' + node.n + '×'));
          var body = el('span', 'acl-loop-body');
          renderList(node.body, body);
          if (node === self.stack[self.stack.length - 1]) body.appendChild(el('span', 'acl-caret'));
          box.appendChild(body);
          into.appendChild(box);
        }
      });
    }
    renderList(this.program, view);
    if (!this.stack.length) view.appendChild(el('span', 'acl-caret'));
    if (!this.program.length) view.appendChild(el('span', 'acl-empty', 'Click the arrows to add steps'));

    var blocks = Engine.countBlocks(this.program);
    this.blockCount.textContent = blocks + ' block' + (blocks === 1 ? '' : 's') + ' · goal ' + this.level.par;
    this.endBtn.disabled = !this.stack.length;
  };

  Game.prototype.say = function (text, tone) {
    this.message.textContent = text;
    this.message.className = 'acl-message' + (tone ? ' is-' + tone : '');
  };

  Game.prototype.setRunning = function (on) {
    this.running = on;
    this.root.classList.toggle('is-running', on);
    this.runBtn.disabled = on;
    this.editButtons.forEach(function (b) { b.disabled = on; });
  };

  Game.prototype.run = function () {
    if (this.running) return;
    var self = this;
    this.stack = [];
    this.renderBoard();
    this.renderProgram();
    var sim = Engine.simulate(this.level, this.program);
    if (!sim.steps.length) { this.say(MESSAGES[sim.result], 'bad'); return; }

    this.setRunning(true);
    this.say('Swimming…');
    var delay = this.fast ? 180 : 420, i = 0;
    (function tick() {
      var s = sim.steps[i];
      if (s.bump) {
        self.placeAxo(s.r, s.c, s.d);
        self.axo.classList.add('is-bump');
        setTimeout(function () { self.axo.classList.remove('is-bump'); }, 500);
      } else {
        self.placeAxo(s.r, s.c, s.d);
        if (s.ate) {
          var cell = self.cells[s.r + ',' + s.c];
          setTimeout(function () { cell.classList.add('is-eaten'); }, delay / 2);
        }
      }
      i++;
      if (i < sim.steps.length) { setTimeout(tick, delay); return; }
      setTimeout(function () { self.finish(sim.result); }, delay);
    })();
  };

  Game.prototype.finish = function (result) {
    this.setRunning(false);
    if (result !== 'win') { this.say(MESSAGES[result], 'bad'); return; }

    var n = this.levelIndex + 1;
    var stars = Engine.stars(this.level, this.program);
    var blocks = Engine.countBlocks(this.program);
    this.progress.stars[n] = Math.max(this.progress.stars[n] || 0, stars);
    this.progress.unlocked = Math.max(this.progress.unlocked, Math.min(n + 1, LEVELS.length));
    saveLocal(this.progress);
    syncServer(this.progress);
    this.axo.classList.add('is-happy');

    var text = '★'.repeat(stars) + '  You made it home! ';
    if (stars === 3) text += 'Super-efficient code: ' + blocks + ' blocks.';
    else text += 'Can you do it in ' + this.level.par + ' blocks? Look for a pattern to repeat.';
    if (n === LEVELS.length) text += ' You finished every level. Amazing coding!';
    this.say(text, 'good');
    this.nextBtn.hidden = n === LEVELS.length;
    this.renderLevelPicker();

    // Other Ascend widgets (e.g. the student dashboard) can listen for this.
    this.root.dispatchEvent(new CustomEvent('ascend:game-complete', {
      bubbles: true,
      detail: { game: 'code-a-lotl', level: n, stars: stars, blocks: blocks }
    }));
  };

  function init() {
    var roots = document.querySelectorAll('.acl-root');
    for (var i = 0; i < roots.length; i++) {
      if (!roots[i].__acl) roots[i].__acl = new Game(roots[i]);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
