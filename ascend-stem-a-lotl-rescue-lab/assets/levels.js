/* STEM-a-lotl: Rescue Lab — the 40 authored puzzles.
 *
 * Every puzzle is a journey: a food pellet leaves the dispenser and must reach
 * Lucas the axolotl. Four worlds of ten, one new idea at a time:
 *   Meadow (ramps, bouncers, gravity) · Windy Hills (wind, fans, bees)
 *   Pond (water, currents, fish, bubbles) · Waterfall Canyon (falls + everything)
 *
 * Scene units: 100 wide x 60 tall, y grows downward, ground at y = 56.
 * Shared by the game, the preview and the tests (tests/solutions.json holds a
 * verified reference solution for every puzzle and every challenge).
 * Text fields can be overridden per site through the ascend_rl_level_text
 * option (see the plugin), without touching the physics.
 */
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) module.exports = factory();
  else root.AscendRLLevels = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var GROUND = 56;

  /* ---- terrain helpers: each adds collision segments and a drawable fill ---- */
  function seg(x1, y1, x2, y2, mat) { return { x1: x1, y1: y1, x2: x2, y2: y2, mat: mat || 'grass' }; }
  function S(theme) { return { theme: theme, solids: [], fills: [], rocks: [], wind: [], water: [], falls: [], bugs: [], fish: [], bubbles: [] }; }
  function ground(s, x1, x2, mat) {
    mat = mat || (s.theme === 'canyon' ? 'rock' : 'grass');
    s.solids.push(seg(x1, GROUND, x2, GROUND, mat));
    s.fills.push({ shape: 'rect', x: x1, y: GROUND, w: x2 - x1, h: 8, mat: mat });
    return s;
  }
  function ledge(s, x1, x2, y, mat) {
    mat = mat || (s.theme === 'canyon' ? 'rock' : 'grass');
    s.solids.push(seg(x1, y, x2, y, mat), seg(x1, y, x1, GROUND, mat), seg(x2, y, x2, GROUND, mat));
    s.fills.push({ shape: 'rect', x: x1, y: y, w: x2 - x1, h: GROUND - y + 8, mat: mat });
    return s;
  }
  function slab(s, x1, x2, y1, y2, mat) {
    mat = mat || 'rock';
    s.solids.push(seg(x1, y1, x2, y1, mat), seg(x1, y2, x2, y2, mat), seg(x1, y1, x1, y2, mat), seg(x2, y1, x2, y2, mat));
    s.fills.push({ shape: 'rect', x: x1, y: y1, w: x2 - x1, h: y2 - y1, mat: mat });
    return s;
  }
  function hill(s, x1, y1, x2, y2, mat) {
    mat = mat || 'grass';
    s.solids.push(seg(x1, y1, x2, y2, mat));
    if (y1 < GROUND) s.solids.push(seg(x1, y1, x1, GROUND, mat));
    if (y2 < GROUND) s.solids.push(seg(x2, y2, x2, GROUND, mat));
    s.fills.push({ shape: 'poly', pts: [[x1, y1], [x2, y2], [x2, GROUND + 8], [x1, GROUND + 8]], mat: mat });
    return s;
  }
  function pillar(s, x, y, w, mat) { return ledge(s, x - (w || 4) / 2, x + (w || 4) / 2, y, mat || 'rock'); }
  function pond(s, x1, x2, surface, current) { s.water.push({ x: x1, y: surface, w: x2 - x1, h: GROUND - surface, current: current || 0 }); return s; }
  function bed(s, x1, x2) { return ground(s, x1, x2, 'sand'); }
  function falls(s, x1, x2, top) { s.falls.push({ x: x1, y: top || 0, w: x2 - x1, h: GROUND - (top || 0) }); return s; }
  function wind(s, x, y, w, h, fx, gust, fy) { s.wind.push({ x: x, y: y, w: w, h: h, fx: fx, fy: fy || 0, gust: gust || 0 }); return s; }
  function bee(s, x, y, ax, ay, period, phase) { s.bugs.push({ x: x, y: y, ax: ax, ay: ay, period: period, phase: phase || 0 }); return s; }
  function fish(s, x, y, range, period, phase) { s.fish.push({ x: x, y: y, range: range, period: period, phase: phase || 0 }); return s; }
  function rock(s, x, y, r) { s.rocks.push({ x: x, y: y, r: r }); return s; }
  function bubbles(s, x1, x2, top, fy) { s.bubbles.push({ x: x1, y: top, w: x2 - x1, h: GROUND - top, fy: fy }); return s; }
  function at(s, spawn, lucas, extra) {
    s.spawn = { x: spawn[0], y: spawn[1] };
    s.lucas = { x: lucas[0], y: lucas[1], face: lucas[2] || -1 };
    if (extra) for (var k in extra) s[k] = extra[k];
    return s;
  }

  var R22 = { type: 'ramp', len: 22 }, R16 = { type: 'ramp', len: 16 }, R30 = { type: 'ramp', len: 30 };
  function tray() { var out = []; for (var i = 0; i < arguments.length; i += 2) { var t = JSON.parse(JSON.stringify(arguments[i])); t.count = arguments[i + 1]; out.push(t); } return out; }
  var BOUNCER = { type: 'bouncer' }, FAN = { type: 'fan' }, BLOCK = { type: 'block' };

  /* challenge helpers */
  function lean(n) { return { id: 'lean', badge: 'efficiency', text: 'Lean build: feed Lucas using ' + n + ' part' + (n === 1 ? '' : 's') + ' or fewer.', rule: { type: 'maxParts', n: n } }; }
  function quick(s) { return { id: 'quick', badge: 'invention', text: 'Hungry Lucas: feed him within ' + s + ' seconds.', rule: { type: 'maxTime', s: s } }; }
  function gentle(d) { return { id: 'gentle', badge: 'invention', text: 'Gentle slopes: keep every part tilted ' + d + '° or less.', rule: { type: 'maxAngle', deg: d } }; }
  function avoid(kinds, word) { return { id: 'avoid', badge: 'invention', text: 'Clean run: feed Lucas without touching ' + word + '.', rule: { type: 'avoid', kinds: kinds } }; }
  function without(part, word, badge) { return { id: 'no-' + part, badge: badge || 'invention', text: 'Inventor: feed Lucas without using ' + word + '.', rule: { type: 'noPart', part: part } }; }
  function use(event, kind, text) { return { id: 'use-' + event, badge: 'invention', text: text, rule: { type: 'touch', event: event, kind: kind } }; }
  function dodge(event, text) { return { id: 'dodge-' + event, badge: 'invention', text: text, rule: { type: 'noTouch', event: event } }; }

  function L(id, world, name, s, tr, o) {
    var lvl = { id: id, world: world, name: name, scene: s, tray: tr, initial: o.initial || [], intro: o.intro, lesson: o.lesson, idea: o.idea, hint: o.hint || '', challenges: o.challenges };
    return lvl;
  }

  var LEVELS = [
    /* ============================== World 1: Meadow ============================== */
    L(1, 1, 'First Bite', at(ground(S('meadow'), -20, 120), [16, 6], [58, GROUND]), tray(R22, 1), {
      initial: [{ type: 'ramp', x: 24, y: 14, angle: 0, len: 22 }],
      idea: 'Ramps and gravity',
      intro: 'Lucas is hungry! Tilt the ramp so the pellet rolls to him.',
      lesson: 'A flat ramp holds the pellet still. Tilt it, and gravity pulls the pellet along the slope toward Lucas.',
      hint: 'Tap the ramp, then press Rotate + once or twice.',
      challenges: [gentle(10), quick(4)]
    }),
    L(2, 1, 'Hilltop Picnic', at(ledge(ground(S('meadow'), -20, 120), 58, 120, 40), [18, 6], [74, 40]), tray(R30, 1), {
      idea: 'Starting height',
      intro: 'Lucas is having a picnic on the hill. The pellet has to land up there.',
      lesson: 'The pellet can only end up lower than where it starts. Starting high gave it enough height to reach the hilltop.',
      challenges: [gentle(25), quick(4)]
    }),
    L(3, 1, 'The Long Roll', at(ground(S('meadow'), -20, 120), [8, 6], [90, GROUND]), tray(R22, 2), {
      idea: 'Speed from slopes',
      intro: 'Lucas is way over on the far side of the meadow.',
      lesson: 'The steeper and longer the slope, the more speed the pellet builds, and speed carries it across the flat grass.',
      challenges: [quick(5), gentle(30)]
    }),
    L(4, 1, 'Boing!', at(ledge(ground(S('meadow'), -20, 120), 66, 120, 34), [22, 6], [80, 34]), tray(BOUNCER, 1, R22, 1), {
      idea: 'Bouncers store energy',
      intro: 'Lucas climbed onto a tall rock. Try the springy bouncer.',
      lesson: 'The bouncer squashes when the pellet lands and springs back, sending the pellet up and over to Lucas.',
      challenges: [lean(1), without('ramp', 'a ramp')]
    }),
    L(5, 1, 'Stone Wall', at(pillar(ground(S('meadow'), -20, 120), 48, 32, 4), [14, 6], [80, GROUND]), tray(R22, 2), {
      idea: 'Going over obstacles',
      intro: 'A stone wall stands between the pellet and Lucas.',
      lesson: 'The pellet kept moving after it left the ramp. That forward motion carried it over the wall while gravity pulled it down.',
      challenges: [lean(1), quick(4)]
    }),
    L(6, 1, 'Easy Does It', at(ground(S('meadow'), -20, 120), [30, 4], [62, GROUND], { eatSpeed: 32 }), tray(R22, 2), {
      idea: 'Too fast to catch',
      intro: 'Lucas is sleepy today. If the pellet zooms by, he will miss it.',
      lesson: 'A shorter drop and a gentler slope made the pellet slow enough for Lucas to catch.',
      challenges: [lean(1), gentle(20)]
    }),
    L(7, 1, 'Stepping Stones', at(ledge(ledge(ground(S('meadow'), -20, 120), 40, 58, 48, 'rock'), 66, 120, 40), [12, 6], [80, 40]), tray(R16, 2, BOUNCER, 1), {
      idea: 'Planning a path',
      intro: 'Lucas is at the top of the stepping stones.',
      lesson: 'Breaking the trip into steps helped: each part only had to get the pellet to the next spot.',
      challenges: [lean(2), quick(4)]
    }),
    L(8, 1, 'Over the Log', at(rock(ground(S('meadow'), -20, 120), 48, 51, 5), [16, 6], [80, GROUND]), tray(R22, 1, BOUNCER, 1), {
      idea: 'Round obstacles',
      intro: 'A mossy log is in the way. Over it, or off it?',
      lesson: 'A round log sends the pellet off at an angle. Where it hits decides where it goes next.',
      challenges: [lean(1), use('bump', 'rock', 'Log roll: bounce the pellet off the log on the way.')]
    }),
    L(9, 1, 'Low Ceiling', at(slab(ground(S('meadow'), -20, 120), 30, 74, 26, 32), [12, 6], [88, GROUND]), tray(R22, 2), {
      idea: 'Tunnels and clearance',
      intro: 'A rock shelf hangs over the meadow. The pellet has to fit underneath.',
      lesson: 'A low, fast roll kept the pellet under the shelf. Rolling needs less room than flying.',
      challenges: [quick(4), gentle(30)]
    }),
    L(10, 1, 'Meadow Feast', at(ledge(pillar(ground(S('meadow'), -20, 120), 40, 36, 4), 66, 120, 36), [14, 6], [86, 36]), tray(R22, 2, BOUNCER, 1), {
      idea: 'Putting it together',
      intro: 'A wall and a hill. Use everything you have learned in the meadow.',
      lesson: 'Ramps give speed, bouncers give height, and planning the path got the pellet all the way to the picnic.',
      challenges: [lean(2), quick(4)]
    }),

    /* =========================== World 2: Windy Hills =========================== */
    L(11, 2, 'First Breeze', at(wind(ground(S('hills'), -20, 120), 20, 8, 50, 44, 40, 3), [14, 6], [76, GROUND]), tray(R22, 1), {
      idea: 'Wind is a push',
      intro: 'The wind is blowing across the hills today. Watch how it moves the pellet.',
      lesson: 'Gravity pulled the pellet down while the wind pushed it sideways. Two pushes at once make a curved path.',
      challenges: [gentle(15), quick(3)]
    }),
    L(12, 2, 'Headwind', at(wind(ground(S('hills'), -20, 120), 30, 0, 90, 56, -35, 0), [10, 6], [84, GROUND]), tray(R30, 1, FAN, 1), {
      idea: 'Pushing against the wind',
      intro: 'The wind blows toward the pellet. It will need extra help.',
      lesson: 'The wind pushed back, so the pellet needed more speed, or a fan pushing harder the other way, to reach Lucas.',
      challenges: [gentle(35), quick(4)]
    }),
    L(13, 2, 'Fan Club', at(ground(S('hills'), -20, 120), [14, 6], [56, GROUND]), tray(FAN, 1), {
      idea: 'Fans make wind',
      intro: 'No ramps today, just a fan. Where should the breeze blow?',
      lesson: 'The fan pushed air, and the moving air pushed the pellet sideways as it fell. The longer it stayed in the breeze, the farther it drifted.',
      challenges: [quick(2), { id: 'float', badge: 'invention', text: 'Floaty: let the pellet drift at least 2.5 seconds before Lucas eats it.', rule: { type: 'minTime', s: 2.5 } }]
    }),
    L(14, 2, 'Bee Careful', at(bee(ground(S('hills'), -20, 120), 50, 40, 10, 4, 3), [14, 6], [80, GROUND]), tray(R22, 2), {
      idea: 'Moving obstacles',
      intro: 'A busy bee buzzes back and forth. Try not to bonk it.',
      lesson: 'The bee is in a different place every moment. Changing the pellet’s path or speed changes when it passes the bee.',
      challenges: [avoid(['bee'], 'the bee'), lean(1)]
    }),
    L(15, 2, 'Updraft', at(pillar(ground(S('hills'), -20, 120), 50, 26, 4), [16, 6], [78, GROUND]), tray(FAN, 1, R22, 1), {
      idea: 'Lift from below',
      intro: 'A tall stone wall. Can a fan lift the pellet over?',
      lesson: 'A fan blowing upward pushed against gravity and lifted the pellet high enough to clear the wall.',
      challenges: [gentle(30), quick(4)]
    }),
    L(16, 2, 'Crosswinds', at(wind(wind(ground(S('hills'), -20, 120), 0, 0, 60, 28, 45, 4), 30, 28, 70, 28, -40, 3), [10, 4], [80, GROUND]), tray(R22, 1, FAN, 1), {
      idea: 'Winds in two directions',
      intro: 'High wind blows right, low wind blows left. Lucas waits on the far side.',
      lesson: 'The pellet was pushed one way up high and the other way down low. Adding up all the pushes tells you where it lands.',
      challenges: [lean(1), without('fan', 'the fan')]
    }),
    L(17, 2, 'Busy Bees', at(bee(bee(ground(S('hills'), -20, 120), 40, 30, 8, 5, 2.6), 66, 44, 9, 3, 3.4, 1), [12, 6], [86, GROUND]), tray(R22, 2, BLOCK, 1), {
      idea: 'Timing',
      intro: 'Two bees are out collecting nectar. Plan a path between them.',
      lesson: 'Two moving obstacles make timing tricky. A faster or slower path changes which gaps the pellet meets.',
      challenges: [avoid(['bee'], 'any bee'), lean(2)]
    }),
    L(18, 2, 'Kite Hill', at(wind(ledge(hill(ground(S('hills'), -20, 120), 40, GROUND, 82, 32), 82, 120, 32), 36, 10, 64, 46, 30, 2.5), [12, 6], [92, 32]), tray(R22, 1, BOUNCER, 1), {
      idea: 'Climbing a hill',
      intro: 'Lucas is flying a kite at the top of the hill. The wind helps if you let it.',
      lesson: 'Rolling uphill trades speed for height. The wind’s extra push gave the pellet enough energy to reach the top.',
      challenges: [lean(1), use('wind', null, 'Wind rider: let the wind push the pellet on the way.')]
    }),
    L(19, 2, 'Windy Gap', at(wind(ground(ground(S('hills'), -20, 40), 64, 120), 30, 8, 40, 42, 30, 3), [12, 6], [84, GROUND]), tray(R30, 1, FAN, 1), {
      idea: 'Crossing a gap',
      intro: 'There is a gap in the hill. Do not let the pellet fall in!',
      lesson: 'To cross a gap the pellet needs enough sideways speed before gravity pulls it down. Wind can supply some of that speed.',
      challenges: [without('fan', 'the fan', 'efficiency'), quick(4)]
    }),
    L(20, 2, 'Windmill Picnic', at(bee(wind(ledge(pillar(ground(S('hills'), -20, 120), 58, 40, 4), 80, 120, 44), 50, 0, 50, 40, -30, 3), 70, 30, 6, 3, 3), [16, 4], [90, 44]), tray(R22, 2, FAN, 1, BOUNCER, 1), {
      idea: 'Putting it together',
      intro: 'Wind, a wall and a bee guard Lucas’s picnic spot.',
      lesson: 'Every force on the way, gravity, the wind and your fan, added up to one path. Change one and the whole journey changes.',
      challenges: [lean(2), avoid(['bee'], 'the bee')]
    }),

    /* ============================== World 3: Pond ============================== */
    L(21, 3, 'Splash Down', at(pond(bed(ledge(S('pond'), -20, 44, 38), 44, 120), 44, 120, 38, 0), [14, 6], [72, GROUND]), tray(R22, 1), {
      idea: 'Water slows things down',
      intro: 'Lucas lives in the pond! Get the pellet into the water above him.',
      lesson: 'In water the pellet slowed down (drag) and sank gently because the water held it up a little (buoyancy).',
      challenges: [gentle(15), quick(5)]
    }),
    L(22, 3, 'Go With the Flow', at(pond(bed(ledge(S('pond'), -20, 34, 36), 34, 120), 34, 120, 36, 30), [12, 6], [90, GROUND]), tray(R22, 1), {
      idea: 'Currents carry things',
      intro: 'The pond has a current today. It flows toward Lucas.',
      lesson: 'The current is moving water, and it carried the sinking pellet along with it, all the way to Lucas.',
      challenges: [gentle(10), quick(5)]
    }),
    L(23, 3, 'Against the Current', at(pond(bed(ledge(S('pond'), -20, 30, 34), 30, 120), 30, 120, 34, -22), [12, 6], [78, GROUND]), tray(R30, 1, BOUNCER, 1), {
      idea: 'Working against a current',
      intro: 'This time the current flows away from Lucas.',
      lesson: 'The current pushed the pellet back, so it had to enter the water close to Lucas and sink before it drifted away.',
      challenges: [quick(5), gentle(40)]
    }),
    L(24, 3, 'Fish Crossing', at(fish(pond(bed(ledge(S('pond'), -20, 36, 36), 36, 120), 36, 120, 36, 0), 66, 46, 12, 3), [14, 6], [82, GROUND]), tray(R22, 2), {
      idea: 'Moving water life',
      intro: 'A friendly fish swims across the pond. It might bump the pellet.',
      lesson: 'The fish and the pellet were both moving. When they met, both changed direction, so timing and path both mattered.',
      challenges: [avoid(['fish'], 'the fish'), lean(1)]
    }),
    L(25, 3, 'Island Picnic', at(ledge(pond(bed(ledge(S('pond'), -20, 24, 30), 24, 120), 24, 120, 40, 0), 70, 90, 40, 'rock'), [16, 6], [80, 40]), tray(R22, 1, BOUNCER, 1), {
      idea: 'Landing on target',
      intro: 'Lucas is sunbathing on a rock island. Miss, and the pellet sinks.',
      lesson: 'To land on the island the pellet needed just the right speed. Too slow falls short, too fast flies over.',
      challenges: [quick(4), dodge('splash', 'Dry delivery: feed Lucas without the pellet touching the water.')]
    }),
    L(26, 3, 'Deep Dive', at(pond(bed(ledge(S('pond'), -20, 26, 24), 26, 120), 26, 120, 24, 18), [10, 4], [88, GROUND]), tray(R22, 2), {
      idea: 'Sinking takes time',
      intro: 'The pond is deep today. Lucas waits at the very bottom.',
      lesson: 'A deep pond means a long, slow sink. The gentle current had lots of time to carry the pellet along.',
      challenges: [lean(1), quick(7)]
    }),
    L(27, 3, 'School of Fish', at(fish(fish(fish(pond(bed(ledge(S('pond'), -20, 30, 34), 30, 120), 30, 120, 34, 0), 50, 42, 6, 2.5), 64, 50, 8, 3.2, 1), 74, 40, 6, 2, 2), [12, 6], [86, GROUND]), tray(R22, 2, BLOCK, 1), {
      idea: 'Many moving things',
      intro: 'A whole school of fish is swimming by.',
      lesson: 'With many moving fish, the safest path was the one that spent the least time where they swim.',
      challenges: [quick(11), lean(1)]
    }),
    L(28, 3, 'Stream Race', at(rock(pond(bed(ledge(S('pond'), -20, 20, 30), 20, 120), 20, 120, 44, 40), 60, 52, 3.5), [12, 6], [92, GROUND]), tray(R30, 1, BLOCK, 1), {
      idea: 'Fast water',
      intro: 'A fast stream races toward Lucas. Rocks stick up from the bottom.',
      lesson: 'The fast current did most of the work. The pellet just had to get into the stream without getting stuck behind the rock.',
      challenges: [lean(1), use('bump', 'rock', 'Rock hop: bounce off the rock in the stream.')]
    }),
    L(29, 3, 'Bubble Lift', at(slab(ledge(bubbles(pond(bed(ledge(S('pond'), -20, 30, 30), 30, 120), 30, 120, 30, 10), 52, 62, 30, -70), 62, 100, 44, 'rock'), 62, 100, 20, 30, 'rock'), [12, 6], [80, 44]), tray(R22, 2), {
      idea: 'Upward push in water',
      intro: 'Lucas is hiding in an underwater cave. Bubbles rise from a spring by the entrance.',
      lesson: 'The pellet sank past the cave, then the rising bubbles lifted it back up and the current carried it inside. An upward push can beat gravity.',
      challenges: [gentle(30), quick(7)]
    }),
    L(30, 3, 'Pond Party', at(rock(fish(pond(bed(ledge(S('pond'), -20, 30, 32), 30, 120), 30, 120, 32, 15), 58, 44, 8, 2.8), 70, 50, 3), [12, 6], [90, GROUND]), tray(R22, 2, BOUNCER, 1, BLOCK, 1), {
      idea: 'Putting it together',
      intro: 'Fish, rocks and a current. It is a pond party!',
      lesson: 'Drag, buoyancy, the current and the fish all acted at once. Watching each run showed which one to plan around.',
      challenges: [lean(1), avoid(['fish'], 'the fish')]
    }),

    /* ========================= World 4: Waterfall Canyon ========================= */
    L(31, 4, 'The Falls', at(falls(ledge(ground(S('canyon'), -20, 120), -20, 30, 30), 46, 52, 0), [12, 6], [80, GROUND]), tray(R30, 1), {
      idea: 'Waterfalls pull down',
      intro: 'A waterfall pours down between the pellet and Lucas.',
      lesson: 'Falling water pulls things down with it. Moving fast sideways meant less time in the waterfall.',
      challenges: [quick(5), gentle(30)]
    }),
    L(32, 4, 'Behind the Falls', at(pond(bed(falls(ground(S('canyon'), -20, 36), 44, 52, 0), 36, 64), 36, 64, 46, 0), [12, 6], [50, GROUND]), tray(R22, 1), {
      idea: 'Using a force',
      intro: 'Lucas is splashing in the pool right under the waterfall.',
      lesson: 'Sometimes a force helps. The waterfall pulled the pellet straight down into Lucas’s pool.',
      challenges: [use('falls', null, 'Waterslide: ride the waterfall down to Lucas.'), gentle(15)]
    }),
    L(33, 4, 'Misty Ledge', at(ledge(falls(ledge(ground(S('canyon'), -20, 120), -20, 20, 20), 40, 46, 0), 58, 120, 46), [12, 6], [72, 46]), tray(R22, 1, BOUNCER, 1), {
      idea: 'Keeping height',
      intro: 'Lucas is on a misty ledge past the falls.',
      lesson: 'The pellet had to keep enough height to land on the ledge. Every bit of height lost to the waterfall was hard to get back.',
      challenges: [lean(1), quick(4)]
    }),
    L(34, 4, 'Canyon Wind', at(falls(wind(ground(S('canyon'), -20, 120), 20, 10, 40, 30, 35, 3), 62, 68, 0), [12, 6], [84, GROUND]), tray(R22, 1, FAN, 1), {
      idea: 'Wind vs waterfall',
      intro: 'Wind blows through the canyon toward a waterfall.',
      lesson: 'The wind gave the pellet sideways speed, and that speed decided whether it crossed the falls or got pulled down.',
      challenges: [without('fan', 'the fan', 'efficiency'), quick(4)]
    }),
    L(35, 4, 'Salmon Leap', at(fish(fish(pond(bed(falls(ground(S('canyon'), -20, 30), 40, 46, 0), 30, 120), 30, 120, 44, 20), 60, 50, 10, 2.4), 80, 48, 8, 3, 1.5), [14, 6], [88, GROUND]), tray(R22, 2), {
      idea: 'Everything moves',
      intro: 'Salmon leap in the plunge pool below the falls.',
      lesson: 'The waterfall, the current and the fish all moved the pellet. Watching the ghost trail showed which one changed the path most.',
      challenges: [quick(9), lean(1)]
    }),
    L(36, 4, 'Canyon Gap', at(slab(ground(ground(S('canyon'), -20, 36), 60, 120), 40, 70, 18, 22), [12, 6], [84, GROUND]), tray(R30, 1, BOUNCER, 1), {
      idea: 'Arcs and ceilings',
      intro: 'A deep gap, and a rock arch overhead. Not too high, not too low.',
      lesson: 'The pellet flew in a curve called an arc. It had to be long enough to cross the gap but low enough to miss the arch.',
      challenges: [without('bouncer', 'the bouncer', 'efficiency'), quick(3)]
    }),
    L(37, 4, 'Twin Falls', at(falls(falls(ground(S('canyon'), -20, 120), 34, 40, 0), 64, 70, 0), [12, 6], [52, GROUND]), tray(R22, 2), {
      idea: 'Precision',
      intro: 'Two waterfalls, and Lucas sits right between them.',
      lesson: 'Landing between the falls needed just enough speed: through the first gap, then stopping before the second.',
      challenges: [quick(4), lean(1)]
    }),
    L(38, 4, 'Bee Canyon', at(falls(bee(bee(ground(S('canyon'), -20, 120), 36, 34, 8, 4, 2.8), 56, 44, 7, 4, 3.6, 2), 72, 78, 0), [12, 6], [88, GROUND]), tray(R22, 2, FAN, 1), {
      idea: 'Choosing a route',
      intro: 'Bees buzz in the canyon and a waterfall guards Lucas.',
      lesson: 'There was more than one path. Comparing runs showed which route dodged the bees and crossed the falls.',
      challenges: [avoid(['bee'], 'any bee'), without('fan', 'the fan')]
    }),
    L(39, 4, 'Rapids', at(rock(rock(pond(bed(ledge(S('canyon'), -20, 26, 30), 26, 120), 26, 120, 40, 45), 50, 52, 3), 72, 50, 3), [12, 6], [94, GROUND]), tray(R22, 1, BLOCK, 2), {
      idea: 'Strong currents',
      intro: 'White-water rapids rush toward Lucas.',
      lesson: 'A strong current carries things fast. Rocks in the way can stop the pellet or send it bouncing.',
      challenges: [lean(1), avoid(['rock'], 'a rock')]
    }),
    L(40, 4, 'Lucas’s Feast', at(fish(pond(bed(falls(bee(wind(ground(S('canyon'), -20, 60), 16, 6, 30, 30, 30, 3), 44, 30, 6, 4, 3), 60, 66, 0), 60, 120), 60, 120, 42, 10), 82, 50, 8, 3), [12, 4], [92, GROUND]), tray(R22, 2, BOUNCER, 1, FAN, 1, BLOCK, 1), {
      idea: 'Everything you learned',
      intro: 'The grand feast! Wind, a bee, a waterfall and a fish stand between the pellet and Lucas.',
      lesson: 'You used gravity, wind, water and timing together, testing and improving until the whole journey worked. That is how engineers solve big problems.',
      challenges: [lean(2), avoid(['bee', 'fish'], 'the bee or the fish')]
    })
  ];

  var WORLDS = [
    { id: 1, name: 'Meadow', theme: 'meadow', blurb: 'Ramps, bouncers and gravity' },
    { id: 2, name: 'Windy Hills', theme: 'hills', blurb: 'Wind, fans and bees' },
    { id: 3, name: 'Pond', theme: 'pond', blurb: 'Water, currents and fish' },
    { id: 4, name: 'Waterfall Canyon', theme: 'canyon', blurb: 'Waterfalls and everything together' }
  ];

  /* Pellet skins. `rule` decides how each is earned; `keys` means it is bought
     with Pond Keys (price configurable on the site). Deterministic, never random. */
  var SKINS = [
    { id: 'pink', name: 'Classic Pellet', colors: ['#f7a8c0', '#e0527d'], pattern: 'plain', rule: { type: 'free' } },
    { id: 'blueberry', name: 'Blueberry', colors: ['#8fb8ff', '#3056c9'], pattern: 'dots', rule: { type: 'stars', n: 5 } },
    { id: 'lime', name: 'Lime Swirl', colors: ['#d8f59a', '#5c9e1c'], pattern: 'swirl', rule: { type: 'world', world: 1 } },
    { id: 'sunny', name: 'Sunflower', colors: ['#ffe27a', '#e09a00'], pattern: 'petals', rule: { type: 'world', world: 2 } },
    { id: 'mango', name: 'Mango', colors: ['#ffc38a', '#e2671b'], pattern: 'stripes', rule: { type: 'stars', n: 30 } },
    { id: 'bubble', name: 'Bubble', colors: ['#d9f4ff', '#2aa6d6'], pattern: 'bubbles', rule: { type: 'world', world: 3 } },
    { id: 'lava', name: 'Lava Rock', colors: ['#ff9b6b', '#8a2b0f'], pattern: 'cracks', rule: { type: 'world', world: 4 } },
    { id: 'rainbow', name: 'Rainbow', colors: ['#ff8fb1', '#7bd3ff'], pattern: 'rainbow', rule: { type: 'stars', n: 80 } },
    { id: 'golden', name: 'Golden Shrimp', colors: ['#fff0a8', '#c99700'], pattern: 'shine', rule: { type: 'stars', n: 120 } },
    { id: 'galaxy', name: 'Galaxy', colors: ['#6d5bd0', '#141033'], pattern: 'stars', trail: 'sparkle', rule: { type: 'keys' } },
    { id: 'pearl', name: 'Ocean Pearl', colors: ['#ffffff', '#b9c7d8'], pattern: 'shine', trail: 'bubbles', rule: { type: 'keys' } },
    { id: 'disco', name: 'Disco Ball', colors: ['#e8ecf2', '#7a8494'], pattern: 'tiles', trail: 'sparkle', rule: { type: 'keys' } }
  ];

  /* Defaults for everything the site can change through the ascend_rl_config
     option (readable and writable over MCP). The plugin sends the live values. */
  var CONFIG = { freeLevels: 3, keyCost: 1, dailyUnlock: true, skinPrices: { galaxy: 1, pearl: 1, disco: 1 } };

  return { LEVELS: LEVELS, WORLDS: WORLDS, SKINS: SKINS, CONFIG: CONFIG, GROUND: GROUND, FREE_LEVELS: CONFIG.freeLevels, KEY_COST: CONFIG.keyCost };
});
