/* STEM-a-lotl: Rescue Lab — authored level content.
 *
 * Shared by the game (browser), the standalone preview and the node tests, so it
 * is a plain UMD-ish script with no DOM access. Scene units: 100 wide x 60 tall,
 * y grows downward, ground at y = 56. All levels are deterministic: the same
 * design under the same variation always produces the same run (see engine.js).
 *
 * Every level has:
 *   free         — first three levels are free (the site's existing key rule:
 *                  one Pond Key permanently opens one premium level)
 *   variations   — the main mission first, then included variations; each is
 *                  solvable (tests/levels.test.js proves it with a search)
 *   challenges   — two optional badges: 'efficiency' and 'invention'
 *   reward       — the station area that visibly repairs on success
 */
(function (root, factory) {
  if (typeof module !== 'undefined' && module.exports) module.exports = factory();
  else root.AscendRLLevels = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var GROUND = 56;

  function seg(x1, y1, x2, y2, opts) {
    var s = { x1: x1, y1: y1, x2: x2, y2: y2 };
    if (opts) for (var k in opts) s[k] = opts[k];
    return s;
  }
  function ground() { return seg(-20, GROUND, 120, GROUND, { kind: 'ground' }); }
  function platform(x1, x2, y) { return seg(x1, y, x2, y, { kind: 'platform' }); }
  function wall(x, y1, y2) { return seg(x, y1, x, y2, { kind: 'wall' }); }

  var LEVELS = [
    /* ------------------------------------------------------------------ 1 */
    {
      id: 1, slug: 'supply-slide', name: 'Supply Slide', free: true, mode: 'physics',
      area: 'dock', tool: 'Ramp', concept: 'Ramp angle, gravity and observing motion',
      objective: 'Deliver the supply capsule into the collection basket.',
      intro: 'The supply dock is closed. Tilt the ramp so the capsule rolls into the basket.',
      reward: 'The supply dock opens.',
      explain: 'A steeper ramp lets gravity speed the capsule up more, so it travels farther before it lands. You matched the slope to the basket.',
      reflect: 'What changed when you tilted the ramp more?',
      scene: {
        spawn: { x: 16, y: 6 }, capsule: { r: 2.2 },
        static: [ground()],
        zones: [{ type: 'basket', x: 50, y: 48, w: 12, h: 8 }]
      },
      tray: [{ type: 'ramp', len: 22, count: 1 }],
      initial: [{ type: 'ramp', x: 24, y: 14, angle: 0, len: 22 }],
      variations: [
        { name: 'Main mission' },
        { name: 'Farther basket', scene: { zones: [{ type: 'basket', x: 60, y: 48, w: 12, h: 8 }] }, tray: [{ type: 'ramp', len: 30, count: 1 }], initial: [{ type: 'ramp', x: 26, y: 14, angle: 0, len: 30 }] },
        { name: 'Small basket', scene: { zones: [{ type: 'basket', x: 52, y: 48, w: 8, h: 8 }] } }
      ],
      challenges: [
        { id: 'quick', badge: 'efficiency', text: 'Quick delivery: the capsule settles within 4 seconds.', rule: { type: 'maxTime', s: 4 } },
        { id: 'gentle', badge: 'invention', text: 'Gentle slope: deliver with the ramp tilted 12° or less.', rule: { type: 'partAngleMax', part: 'ramp', deg: 12 } }
      ]
    },

    /* ------------------------------------------------------------------ 2 */
    {
      id: 2, slug: 'rover-crossing', name: 'Rover Crossing', free: true, mode: 'bridge',
      area: 'bridge', tool: 'Beams and supports', concept: 'Beams, supports and stable structures',
      objective: 'Build a crossing so the rover reaches the far side.',
      intro: 'The walkway collapsed. Lay beams across the gap. Long spans need a support underneath.',
      reward: 'A bridge connects two areas of the station.',
      explain: 'A beam only holds weight between its supports. Adding a post in the middle halves the span, so nothing sags.',
      reflect: 'Where did the beam need help most?',
      scene: {
        rover: { x: 10, y: 37, r: 3 },
        cliffs: [{ x1: 0, x2: 30, y: 40 }, { x1: 58, x2: 100, y: 40 }],
        static: [ground(), platform(0, 30, 40), wall(30, 40, GROUND), platform(58, 100, 40), wall(58, 40, GROUND)],
        zones: [{ type: 'goal', x: 84, y: 28, w: 14, h: 12 }],
        maxSpan: 20
      },
      tray: [{ type: 'beam', len: 30, count: 1 }, { type: 'beam', len: 16, count: 2 }, { type: 'post', h: 16, count: 2 }],
      initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'Wider gap', scene: { cliffs: [{ x1: 0, x2: 26, y: 40 }, { x1: 62, x2: 100, y: 40 }], static: [ground(), platform(0, 26, 40), wall(26, 40, GROUND), platform(62, 100, 40), wall(62, 40, GROUND)] }, tray: [{ type: 'beam', len: 30, count: 1 }, { type: 'beam', len: 16, count: 2 }, { type: 'post', h: 16, count: 3 }] },
        { name: 'Short beams only', tray: [{ type: 'beam', len: 16, count: 2 }, { type: 'post', h: 16, count: 2 }] }
      ],
      challenges: [
        { id: 'two', badge: 'efficiency', text: 'Lean build: cross using only 2 parts.', rule: { type: 'maxParts', n: 2 } },
        { id: 'short', badge: 'invention', text: 'Short-beam bridge: cross without using the long beam.', rule: { type: 'noPartLen', part: 'beam', len: 30 } }
      ]
    },

    /* ------------------------------------------------------------------ 3 */
    {
      id: 3, slug: 'cargo-balance', name: 'Cargo Balance', free: true, mode: 'balance',
      area: 'greenhouse', tool: 'Cargo crates', concept: 'Mass distribution and balance',
      objective: 'Load every crate so the delivery cart stays level.',
      intro: 'The greenhouse needs supplies. Place all the crates so the cart balances on its pivot.',
      reward: 'The greenhouse receives its supplies and blooms.',
      explain: 'A crate far from the pivot tips the cart more than the same crate near it. You matched the turning effect on each side.',
      reflect: 'Which crate had the biggest effect on the tilt?',
      scene: { slots: [-3, -2, -1, 0, 1, 2, 3], crates: [3, 2, 1, 1], blocked: [] },
      tray: [], initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'Five crates', scene: { slots: [-3, -2, -1, 0, 1, 2, 3], crates: [3, 3, 2, 1, 1], blocked: [] } },
        { name: 'Broken middle', scene: { slots: [-3, -2, -1, 0, 1, 2, 3], crates: [2, 2, 1, 1], blocked: [0] } }
      ],
      challenges: [
        { id: 'edge', badge: 'efficiency', text: 'Big lever: balance with the heaviest crate on an end slot.', rule: { type: 'heavyOnEdge' } },
        { id: 'hollow', badge: 'invention', text: 'Hollow middle: balance with the centre slot empty.', rule: { type: 'centreEmpty' } }
      ]
    },

    /* ------------------------------------------------------------------ 4 */
    {
      id: 4, slug: 'gentle-landing', name: 'Gentle Landing', free: false, mode: 'physics',
      area: 'launcher', tool: 'Launcher', concept: 'Stored energy, trajectory and changing one variable',
      objective: 'Launch the capsule onto the landing pad without overshooting.',
      intro: 'The delivery launcher is back but untuned. Set its power and angle, then run. Change one thing at a time.',
      reward: 'The delivery launcher starts working.',
      explain: 'More power stores more energy, so the capsule flies farther. The angle decides how much of that goes up versus along.',
      reflect: 'Did power or angle change the landing spot more?',
      scene: {
        launcher: { x: 12, y: 50, power: 3, angle: 45, powers: [1, 2, 3, 4, 5, 6], angles: [15, 30, 45, 60, 75] },
        capsule: { r: 2.2 },
        static: [ground(), platform(52, 68, 34), wall(52, 34, GROUND), wall(68, 34, GROUND)],
        zones: [{ type: 'pad', x: 52, y: 26, w: 16, h: 8 }]
      },
      tray: [{ type: 'bumper', h: 8, count: 1 }],
      initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'High pad', scene: { static: [ground(), platform(60, 76, 26), wall(60, 26, GROUND), wall(76, 26, GROUND)], zones: [{ type: 'pad', x: 60, y: 18, w: 16, h: 8 }] } },
        { name: 'Far pad', scene: { static: [ground(), platform(70, 86, 44), wall(70, 44, GROUND), wall(86, 44, GROUND)], zones: [{ type: 'pad', x: 70, y: 36, w: 16, h: 8 }] } }
      ],
      challenges: [
        { id: 'nobumper', badge: 'efficiency', text: 'Pure aim: land with launcher settings alone, no bumper.', rule: { type: 'maxParts', n: 0 } },
        { id: 'lob', badge: 'invention', text: 'High lob: land with the angle set to 60° or more.', rule: { type: 'launcherAngleMin', a: 60 } }
      ]
    },

    /* ------------------------------------------------------------------ 5 */
    {
      id: 5, slug: 'wind-works', name: 'Wind Works', free: false, mode: 'physics',
      area: 'vents', tool: 'Fans and barriers', concept: 'Forces and comparing alternative routes',
      objective: 'Use fans and barriers to guide the falling capsule into the basket.',
      intro: 'The ventilation station is quiet. Fans push the capsule sideways while gravity pulls it down.',
      reward: 'The ventilation station returns.',
      explain: 'Two forces acted at once: gravity pulling down and the fan pushing sideways. Together they bent the capsule’s path.',
      reflect: 'What would happen with the fan turned the other way?',
      scene: {
        spawn: { x: 14, y: 6 }, capsule: { r: 2.2 },
        static: [ground()],
        zones: [{ type: 'basket', x: 50, y: 46, w: 12, h: 10 }]
      },
      tray: [{ type: 'fan', count: 2 }, { type: 'barrier', len: 12, count: 2 }],
      initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'Blow it back', scene: { spawn: { x: 82, y: 6 }, zones: [{ type: 'basket', x: 40, y: 46, w: 12, h: 10 }] } },
        { name: 'Over the pipe', scene: { static: [ground(), platform(36, 48, 36), wall(36, 36, GROUND), wall(48, 36, GROUND)], zones: [{ type: 'basket', x: 62, y: 46, w: 12, h: 10 }] } }
      ],
      challenges: [
        { id: 'onefan', badge: 'efficiency', text: 'One breeze: deliver with a single fan and nothing else.', rule: { type: 'maxParts', n: 1 } },
        { id: 'barrier', badge: 'invention', text: 'Bounce route: deliver using at least one barrier.', rule: { type: 'minPartType', part: 'barrier', n: 1 } }
      ]
    },

    /* ------------------------------------------------------------------ 6 */
    {
      id: 6, slug: 'gear-lift', name: 'Gear Lift', free: false, mode: 'gears',
      area: 'platform', tool: 'Gears', concept: 'Gear ratios and mechanical advantage',
      objective: 'Choose gears so the lift can raise the crate to the observation platform.',
      intro: 'The motor is small. A bigger gear on the lift turns slower but with more force.',
      reward: 'The observation platform becomes accessible.',
      explain: 'The lift gear has more teeth than the motor gear, so it turns slower but with more turning force. That is mechanical advantage.',
      reflect: 'What did the middle gear change, and what did it not change?',
      scene: {
        motor: { teeth: 8, rpm: 120 },
        slots: ['idler', 'lift'],
        lift: { height: 24, unitsPerRev: 2, needRatio: 3, timeLimit: 30 },
        gears: [8, 16, 24, 32]
      },
      tray: [], initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'Heavy crate', scene: { motor: { teeth: 8, rpm: 120 }, slots: ['idler', 'lift'], lift: { height: 24, unitsPerRev: 2, needRatio: 4, timeLimit: 30 }, gears: [8, 16, 24, 32] } },
        { name: 'Hurry up', scene: { motor: { teeth: 8, rpm: 120 }, slots: ['idler', 'lift'], lift: { height: 24, unitsPerRev: 2, needRatio: 3, timeLimit: 20 }, gears: [8, 16, 24, 32] } }
      ],
      challenges: [
        { id: 'direct', badge: 'efficiency', text: 'Direct drive: lift the crate with no middle gear.', rule: { type: 'noIdler' } },
        { id: 'sameway', badge: 'invention', text: 'Same spin: make the lift gear turn the same way as the motor.', rule: { type: 'liftSameDirection' } }
      ]
    },

    /* ------------------------------------------------------------------ 7 */
    {
      id: 7, slug: 'light-the-lab', name: 'Light the Lab', free: false, mode: 'circuit',
      area: 'lab', tool: 'Wires and a switch', concept: 'Closed circuits and simple diagnosis',
      objective: 'Connect the battery, the switch and the lamp in one closed loop.',
      intro: 'The lab is dark. Electricity only flows around a complete loop. Tap a tile to rotate it; tap the switch to open or close it.',
      reward: 'The laboratory lights up.',
      explain: 'Current needs an unbroken loop from the battery, through the lamp, and back. The switch is a gap you can open and close.',
      reflect: 'What happened when the switch was open?',
      scene: {
        rows: 3, cols: 5,
        fixed: [{ r: 1, c: 1, kind: 'battery' }, { r: 1, c: 3, kind: 'lamp' }],
        blocked: [],
        needSwitch: true
      },
      tray: [{ type: 'wire', count: 8 }, { type: 'corner', count: 6 }, { type: 'switch', count: 1 }],
      initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'Around the pipe', scene: { rows: 3, cols: 5, fixed: [{ r: 1, c: 1, kind: 'battery' }, { r: 1, c: 3, kind: 'lamp' }], blocked: [{ r: 1, c: 2 }], needSwitch: true } },
        { name: 'Two lamps', scene: { rows: 3, cols: 5, fixed: [{ r: 1, c: 1, kind: 'battery' }, { r: 0, c: 3, kind: 'lamp' }, { r: 2, c: 3, kind: 'lamp' }], blocked: [{ r: 1, c: 2 }], needSwitch: true } }
      ],
      challenges: [
        { id: 'tight', badge: 'efficiency', text: 'Tight loop: light the lab using 6 tiles or fewer.', rule: { type: 'maxTiles', n: 6 } },
        { id: 'grand', badge: 'invention', text: 'Grand tour: build a loop that uses 8 tiles or more.', rule: { type: 'minTiles', n: 8 } }
      ]
    },

    /* ------------------------------------------------------------------ 8 */
    {
      id: 8, slug: 'rover-routine', name: 'Rover Routine', free: false, mode: 'code',
      area: 'rover', tool: 'Action blocks', concept: 'Sequencing and debugging',
      objective: 'Arrange action blocks so the rover delivers to every station.',
      intro: 'The helper rover follows your blocks in order, top to bottom. Deliver at each station marker.',
      reward: 'A helper rover joins the station.',
      explain: 'The rover ran your steps in exactly the order you gave them. Watching where it went wrong told you which step to fix.',
      reflect: 'Which block did you change after the first run?',
      scene: {
        rows: 4, cols: 6,
        map: ['......', '.S....', '......', 'R..S..'],
        start: { r: 3, c: 0, dir: 'E' },
        loops: false, maxBlocks: 24
      },
      tray: [], initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'Rocky path', scene: { rows: 4, cols: 6, map: ['....S.', '.##...', '..#...', 'R.....'], start: { r: 3, c: 0, dir: 'E' }, loops: false, maxBlocks: 24 } },
        { name: 'Three stops', scene: { rows: 4, cols: 6, map: ['S.....', '......', '..S..S', 'R.....'], start: { r: 3, c: 0, dir: 'E' }, loops: false, maxBlocks: 24 } }
      ],
      challenges: [
        { id: 'tidy', badge: 'efficiency', text: 'Tidy program: deliver everywhere in 11 blocks or fewer.', rule: { type: 'maxBlocks', n: 11 } },
        { id: 'noturnback', badge: 'invention', text: 'No left turns: deliver everywhere using only right turns.', rule: { type: 'noBlock', block: 'L' } }
      ]
    },

    /* ------------------------------------------------------------------ 9 */
    {
      id: 9, slug: 'loop-the-route', name: 'Loop the Route', free: false, mode: 'code',
      area: 'routes', tool: 'Repeat blocks', concept: 'Patterns and efficient algorithms',
      objective: 'Replace repeated rover instructions with a loop.',
      intro: 'The rover now services several stations. Find the pattern that repeats and wrap it in a Repeat block.',
      reward: 'The rover services several stations.',
      explain: 'A loop runs the same steps again without you writing them twice. Fewer blocks, same route.',
      reflect: 'How many blocks did the loop save?',
      scene: {
        rows: 4, cols: 7,
        map: ['.......', 'R.S.S.S', '.......', '.......'],
        start: { r: 1, c: 0, dir: 'E' },
        loops: true, maxBlocks: 24
      },
      tray: [], initial: [],
      variations: [
        { name: 'Main mission' },
        { name: 'Staircase', scene: { rows: 4, cols: 7, map: ['......S', '....S..', '..S....', 'R......'], start: { r: 3, c: 0, dir: 'N' }, loops: true, maxBlocks: 24 } },
        { name: 'Square patrol', scene: { rows: 4, cols: 7, map: ['R..S...', '.......', '.......', 'S..S...'], start: { r: 0, c: 0, dir: 'E' }, loops: true, maxBlocks: 24 } }
      ],
      challenges: [
        { id: 'loopy', badge: 'efficiency', text: 'Loop master: deliver everywhere in 7 blocks or fewer.', rule: { type: 'maxBlocks', n: 7 } },
        { id: 'nested', badge: 'invention', text: 'Double loop: use a Repeat block inside another Repeat block.', rule: { type: 'nestedLoop' } }
      ]
    },

    /* ----------------------------------------------------------------- 10 */
    {
      id: 10, slug: 'research-station-rescue', name: 'Research Station Rescue', free: false, mode: 'sequence',
      area: 'station', tool: 'Everything you learned', concept: 'Planning and applying earlier ideas',
      objective: 'Bring the whole station online in three stages.',
      intro: 'One last rescue. Deliver power cells, close the main circuit, then send the rover on its final route. Each stage is saved as a checkpoint.',
      reward: 'The full station comes alive in a celebration.',
      explain: 'You planned three systems in a row and used what each earlier mission taught: slopes, loops of current, and step-by-step instructions.',
      reflect: 'Which earlier mission helped you most here?',
      stages: [
        {
          name: 'Stage 1: Power cell drop', mode: 'physics',
          scene: { spawn: { x: 16, y: 6 }, capsule: { r: 2.2 }, static: [ground()], zones: [{ type: 'basket', x: 64, y: 48, w: 12, h: 8 }] },
          tray: [{ type: 'ramp', len: 22, count: 2 }], initial: [{ type: 'ramp', x: 24, y: 14, angle: 0, len: 22 }]
        },
        {
          name: 'Stage 2: Main breaker', mode: 'circuit',
          scene: { rows: 3, cols: 5, fixed: [{ r: 0, c: 1, kind: 'battery' }, { r: 2, c: 3, kind: 'lamp' }], blocked: [{ r: 1, c: 2 }], needSwitch: true },
          tray: [{ type: 'wire', count: 8 }, { type: 'corner', count: 6 }, { type: 'switch', count: 1 }], initial: []
        },
        {
          name: 'Stage 3: Final route', mode: 'code',
          scene: { rows: 4, cols: 7, map: ['R..S...', '..#....', '..#.S..', '......S'], start: { r: 0, c: 0, dir: 'E' }, loops: true, maxBlocks: 24 },
          tray: [], initial: []
        }
      ],
      tray: [], initial: [], scene: {},
      variations: [{ name: 'Main mission' }],
      challenges: [
        { id: 'clean', badge: 'efficiency', text: 'Clean sweep: finish all three stages with no failed runs.', rule: { type: 'noFailedRuns' } },
        { id: 'loopfinal', badge: 'invention', text: 'Loop finale: use a Repeat block in the final route.', rule: { type: 'usesLoop' } }
      ]
    }
  ];

  /* Station areas, in the order they appear on the map, and the decoration each
     completed mission earns. A small deterministic collection: no randomness. */
  var AREAS = [
    { id: 'dock', label: 'Supply dock', decor: 'Dock flag' },
    { id: 'bridge', label: 'Bridge', decor: 'Bridge lanterns' },
    { id: 'greenhouse', label: 'Greenhouse', decor: 'Sunflower' },
    { id: 'launcher', label: 'Launcher', decor: 'Launch pennant' },
    { id: 'vents', label: 'Ventilation', decor: 'Wind sock' },
    { id: 'platform', label: 'Observation platform', decor: 'Telescope' },
    { id: 'lab', label: 'Laboratory', decor: 'Lab poster' },
    { id: 'rover', label: 'Rover bay', decor: 'Rover bell' },
    { id: 'routes', label: 'Rover routes', decor: 'Route signs' },
    { id: 'station', label: 'Whole station', decor: 'Celebration lights' }
  ];

  var FREE_LEVELS = 3;
  var KEY_COST = 1;

  return { LEVELS: LEVELS, AREAS: AREAS, FREE_LEVELS: FREE_LEVELS, KEY_COST: KEY_COST, GROUND: GROUND };
});
