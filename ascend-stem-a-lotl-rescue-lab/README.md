# STEM-a-lotl: Rescue Lab

A physics puzzle game for the Ascend STEM Academy Axolotl Games family. In each of
40 puzzles a food pellet drops from the dispenser, and the child builds a path with
ramps, bouncers, fans and stone blocks so it reaches Lucas the axolotl, who eats it.
Wind, bees, water, currents, fish, bubbles, rocks and waterfalls get in the way or help.

Loop: **build → run → watch the journey → improve → feed Lucas**. A miss is explained
from what the simulation actually did, and the build stays put for a retry. Every meal
ends with a recap of the pellet's real journey, the puzzle's lesson, and short science
notes for the things the pellet met on the way.

## The 40 puzzles

| World | Puzzles | Ideas |
| --- | --- | --- |
| Meadow | 1–10 | Ramps and gravity, starting height, speed, bouncers, walls, too fast to catch, planning, round obstacles, clearance |
| Windy Hills | 11–20 | Wind as a push, headwinds, fans, moving bees, updrafts, crosswinds, timing, hills, gaps |
| Pond | 21–30 | Drag and buoyancy, currents with and against, fish, landing on an island, deep water, fast streams, bubble lift |
| Waterfall Canyon | 31–40 | Waterfalls pull down or help, keeping height, wind vs falls, arcs under an arch, precision, strong rapids, the grand feast |

Every puzzle has three stars: feed Lucas, plus two optional challenges (a lean build,
a time limit, gentle slopes, avoiding bees or fish, riding the wind, and so on).

## How puzzles open

- Puzzles 1–3 are free for everyone, including guests.
- **Daily puzzle:** each calendar day (site timezone) a logged-in student visits, the
  lowest-numbered locked puzzle opens free. One per day. Days they don't visit are not banked.
- **Pond Keys:** one key opens the next locked puzzle straight away, for good. Keys only open puzzles
  in order, never one further ahead. Replays never cost a key.
- **Full Pond Pass:** opens every puzzle.
- The server decides all of this. A local edit cannot open a puzzle.

## Pellet colours

Twelve pellets in the pellet locker. Nine are earned: 5, 30, 80 and 120 stars, and finishing each
world. Three (Galaxy, Ocean Pearl, Disco Ball, with trails) are bought with Pond Keys, 1 key each
by default. Colours change the look only, never the physics. Nothing is random.

## Settings, including over MCP

All settings are WordPress options that Royal MCP can read and write, through the same filters the
Axolotl Games plugin uses. They are also on **Axolotl Games → Rescue Lab** in wp-admin.

| Option | What it holds |
| --- | --- |
| `ascend_rl_config` | `freeLevels` (3), `keyCost` (1), `dailyUnlock` (true), `skinPrices` ({galaxy:1, pearl:1, disco:1}). JSON strings are accepted and values are clamped. |
| `ascend_rl_level_text` | Per-puzzle text overrides: `{"12": {"name": "...", "intro": "...", "lesson": "...", "hint": "..."}}` |
| `ascend_rl_mascot_url` | Header mascot image (defaults to the STEM-a-lotl scientist sticker) |
| `ascend_rl_hub_url` | Exit link (defaults to `/user/`) |

Layouts and physics are code, not settings, because every layout is proven solvable by the tests.

## Install on the site

1. Back up the site (UpdraftPlus or WPvivid).
2. Zip this folder and upload it in **Plugins → Add New → Upload**, then activate. Keep Ascend
   Axolotl Games active; Rescue Lab uses its Pond Key functions.
3. Publish the draft page **STEM-a-lotl: Rescue Lab** (page 7303), which holds `[ascend_rescue_lab]`.
4. Add it to **Logged in Menu → Games** next to Grow-a-lotl.
5. Add `[ascend_rescue_lab_summary]` to the `/user/` dashboard for the parent summary.
6. Update the Pond Key and Full Pond Pass product text: in Rescue Lab a key opens a puzzle for good,
   and the pass opens every puzzle.

Upgrading from 1.0 keeps any keys already spent: the old ownership list is read as key-opened puzzles.
User meta: `ascend_rl_progress`, `ascend_rl_owned`, `ascend_rl_last_daily`, `ascend_rl_skins`, `ascend_rl_unlock_log`.

## Files

| Path | What it is |
| --- | --- |
| `ascend-stem-a-lotl-rescue-lab.php` | Plugin: shortcodes, REST routes (`state`, `daily`, `progress`, `unlock`, `skin`), settings, admin, MCP filters |
| `assets/levels.js` | The 40 puzzles, 4 worlds, 12 pellets and default settings |
| `assets/engine.js` | Deterministic pellet physics, diagnoses, journey recap and science notes |
| `assets/art.js` | Canvas art: Lucas, worlds, water, waterfalls, wind, bees, fish, parts, pellets |
| `assets/progress.js` | Progress record, merge, access rules, daily rule, pellet unlocks |
| `assets/rescue-lab.js` / `.css` | The game screens in the Axolotl Games style |
| `tests/` | Node tests, PHP rule tests, and `solutions.json` (a verified design for every puzzle and star) |
| `tools/tune.js` | Searches for solutions and rewrites `solutions.json` |
| `tools/build-preview.js`, `smoke.js`, `gallery.js` | Preview build, browser play-through, frame-by-frame review of all 40 |
| `preview/` | `index.html` runs anywhere; `screenshots/` shows the game |

## Physics

Fixed 1/120 s steps, bounded speeds and snapped designs make every run repeatable. The pellet
falls at g, rolls down slopes at 5/7 · g · sin θ like a solid ball (measured within 3% on ice),
and has per-material bounce and rolling resistance. In water it feels buoyancy (80% of gravity),
drag and the current. Waterfalls add a strong downward pull, bubbles lift, and fans and wind push
sideways. Bees and fish move on fixed timetables, so timing puzzles are fair. Lucas eats the pellet
when it touches the ring around his mouth slowly enough.

## Verified

```
node --test ascend-stem-a-lotl-rescue-lab/tests/          # 59 tests
php ascend-stem-a-lotl-rescue-lab/tests/plugin-test.php    # 27 checks
NODE_PATH=/opt/node22/lib/node_modules node ascend-stem-a-lotl-rescue-lab/tools/smoke.js   # 42 browser checks
```

- Every puzzle and all 80 challenges replay a verified design, and no starting layout wins by itself.
- Runs are identical twice over, and reset leaves no state behind. Free fall, rolling, water, current, wind,
  waterfall, bubble and bouncer behaviour are checked against expected physics.
- The plugin rules are checked against stand-ins for WordPress and the key API: once per day, no rollover,
  keys spent once, a busy lock refuses a second concurrent request, config and text are cleaned, and options
  are exposed to MCP.
- In Chromium on a phone viewport with touch and on desktop, the checks cover the first minute, the miss,
  tilt and feed flow, drag, undo and keyboard editing, resume after reload, the daily demo, a key opening
  only the next puzzle, and a pellet purchase. All 40 reference runs also feed Lucas in the browser.

Not verified here: the live site. This environment can't reach ascendstemacademy.com or install plugins,
so the REST routes, real Pond Key spending, WooCommerce and real devices need a check after upload.
The admin screen shows whether the key system is connected.
