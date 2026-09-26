# STEM-a-lotl: Rescue Lab

An engineering game for the Ascend STEM Academy Axolotl Games family. The player is a
junior engineer helping Lucas the axolotl (the STEM-a-lotl mascot) bring an
interconnected research station back to life: ramps, bridges, balanced cargo, a
launcher, fans, gears, a circuit and a programmable rover. Every delivery visibly
repairs a part of the station.

Loop: **build → run → observe → improve → celebrate**. After a miss the game says what
actually happened (from the simulation, never a guessed cause), keeps the build intact
and offers a one-tap retry.

## Contents

| Path | What it is |
| --- | --- |
| `ascend-stem-a-lotl-rescue-lab.php` | The WordPress plugin: shortcodes, REST progress sync, Pond Key unlock, admin screen |
| `assets/levels.js` | The ten authored missions: scenes, trays, variations, badge challenges, copy |
| `assets/engine.js` | Deterministic simulation (physics, bridge, balance, gears, circuit, code) and diagnosis |
| `assets/progress.js` | Progress record, monotonic merge, level access states, decorations, practice challenge |
| `assets/rescue-lab.js` | The game front end (station map, build tools, run loop, unlock flow, saves) |
| `assets/rescue-lab.css` | Styles in the Ascend Axolotl Games shell |
| `tests/` | `node --test` suite: solvability of every mission/variation/challenge, determinism, reset, merge |
| `tools/build-preview.js` | Builds the standalone preview (`preview/index.html`) and the site page body |
| `tools/smoke.js` | Playwright play-through on phone and desktop viewports with screenshots |
| `preview/` | Built preview files: `index.html` (open in any browser, no server needed), `wp-page-body.html` (paste into an HTML block on a draft page to preview inside the site's theme before installing the plugin), `artifact.html` (the claude.ai preview) |

## Install on the site

1. Back up the site (UpdraftPlus or WPvivid, both installed).
2. Zip the `ascend-stem-a-lotl-rescue-lab` folder and upload it in **Plugins → Add New → Upload**, then activate.
   The Ascend Axolotl Games plugin (v2.3.0) must stay active; Rescue Lab calls its key functions.
3. Publish the draft page **STEM-a-lotl: Rescue Lab** (page ID 7303, created by this build). It contains
   `[ascend_rescue_lab]`, like the other game pages contain their shortcodes. To see the game inside the
   theme before installing, paste `preview/wp-page-body.html` into an HTML block on any draft page; that
   copy runs in preview mode (no keys, local saves only) and can be deleted afterwards.
4. Add the page to the **Logged in Menu → Games** submenu (Appearance → Menus, menu ID 24), next to Grow-a-lotl.
5. Optional: add `[ascend_rescue_lab_summary]` to the `/user/` dashboard page next to `[ascend_game_stats]`
   for the compact parent summary.
6. Settings live under **Axolotl Games → Rescue Lab** (mascot image URLs, hub URL, a support-only
   "grant a mission without a key" form, and the unlock log).

Rollback: deactivate the plugin. It adds three user meta keys (`ascend_rl_progress`,
`ascend_rl_owned`, `ascend_rl_unlock_log`) and three options (`ascend_rl_*`) and touches
nothing else.

## The missions

| # | Mission | Mechanic and idea | Station reward | Access |
| --- | --- | --- | --- | --- |
| 1 | Supply Slide | Ramp angle, gravity, observing motion | Supply dock opens | Free |
| 2 | Rover Crossing | Beams, supports, spans | Bridge connects two areas | Free |
| 3 | Cargo Balance | Mass × distance on a pivot | Greenhouse blooms | Free |
| 4 | Gentle Landing | Launch power and angle, one variable at a time | Launcher works | 1 Pond Key |
| 5 | Wind Works | Forces: gravity plus fans, barriers | Ventilation returns | 1 Pond Key |
| 6 | Gear Lift | Gear ratio, mechanical advantage, idler direction | Observation platform | 1 Pond Key |
| 7 | Light the Lab | Closed loop, switch, open-circuit diagnosis | Lab lights up | 1 Pond Key |
| 8 | Rover Routine | Sequencing and debugging action blocks | Helper rover | 1 Pond Key |
| 9 | Loop the Route | Repeat blocks, nested loops | Rover services stations | 1 Pond Key |
| 10 | Research Station Rescue | Three checkpointed stages using earlier tools | Whole station celebrates | 1 Pond Key |

Each mission has a main mission, two optional badge challenges (efficiency, invention) and
two or three tested variations, all included in the same unlock. Every one is proven
solvable by the test suite, which searches for a solution rather than trusting a stored answer.
Badge challenges are designed for the main mission; some are impossible on a variation by
construction (for example "cross with two parts" on the wider gap) and the UI says so.

Progress states shown on the map: Free, 1 Pond Key, Owned · ready, Completed (with
variations done), Mastered (all three badges). Ownership is separate from prerequisites:
no mission requires another; the previous mission is only suggested as a warm-up.

## Reused from the existing games

- **Shell and look**: max-width centered column, Roboto body, 'Baloo 2' titles, navy `#173e63`,
  blue `#009CDE` / `#045C82`, green `#1D4010` / `#BFE0B4`, pink `#E07FA3`, stat pills, the dashed
  "How to play ▾" toggle, pale-blue instruction and tip boxes, the confetti congrats overlay and
  its `prefers-reduced-motion` handling, all taken from Guess-a-lotl / Cross-a-lotl in
  `ascend-axolotl-games.php`.
- **Mascot**: the existing STEM-a-lotl sticker artwork (media 6253, scientist) in the header and
  "Lucas the Axolotl - Winking" (media 6052) for the happy reaction, both configurable in the
  admin screen. Lucas is the guide name used in the site's own media library. The small inline
  axolotl SVG comes from the Code-a-lotl plugin and is the fallback when an image cannot load.
- **Plugin layout and progress sync**: the Code-a-lotl pattern (plugin folder + `assets/` + `tests/`,
  REST progress route with monotonic merge, `ascend:game-complete` DOM event,
  `do_action('ascend_rl_progress_saved')` for the dashboard and transcript plugins).
- **Login and hub**: `/login/` for the login link, `/user/` dashboard as the games hub exit.
- **Keys**: the Pond Key currency and functions of the Ascend Axolotl Games plugin (below).

## Key integration and the accounting difference

The site sells two things today: a **Pond Key** (product 7289, SKU `ASCEND-POND-KEY`, $1.00,
stored as a count in user meta `ascend_games_skips`) and a **Full Pond Pass** (product 7189, stored
as `ascend_games_pass_until = -1`). WooCommerce fulfilment is idempotent per order and lives in
the shared plugin; refunds and prices are untouched.

Difference, stated explicitly: in the daily puzzle games one Pond Key opens **one extra puzzle
today**. In Rescue Lab one Pond Key opens **one mission permanently** on the student account,
retries, challenges and variations included, and reopening never costs another key. Both games
spend the same currency through the shared `ascend_games_consume_skip()`; Rescue Lab records the
result in its own `ascend_rl_owned` meta and never edits the key balance directly. A Full Pond
Pass opens every Rescue Lab mission, matching its "every axolotl game" promise. The first three
missions are free; the site defines no other free-tier rule for a campaign game.

Server behaviour (`ascend_rl_unlock_level`):
- already owned or pass holder → success, nothing spent (so a retry after a lost connection is safe);
- MySQL `GET_LOCK` per student plus a re-read inside the lock → concurrent taps cannot double-spend;
- no keys → HTTP 402 with a plain message, nothing spent; key system missing → 409, nothing spent.
- Local saves can never grant paid access: `owned` and `keys` come only from the server on every load,
  and the progress sanitiser drops any ownership-looking fields. Guests can play the free missions
  with local saves only.

Buy links come from `ascend_games_pass_url('skip'|'life')` when the shared plugin provides them,
else from the Pond Key SKU. If neither exists no button is shown. The preview build shows
"Keys: preview" and states that nothing in it is a purchase.

## Saves and resume

- Progress and saved inventions: `localStorage` for everyone, merged with user meta over
  `/wp-json/ascend-rl/v1/progress` for logged-in students (GET on load, POST after each change).
  Merge is monotonic on both sides: nothing earned is lost, whichever copy is newer.
- In-progress builds autosave per mission/variation/stage as local drafts, so a closed tab or a
  backgrounded app resumes with the construction intact. The simulation pauses while the tab is hidden.
- Family model: one Ultimate Member student account per child, as the existing games use. Progress
  and ownership are per account; there is no cross-family access because every route uses the
  logged-in user only.

## Accessibility and mobile

44px targets, visible focus rings, `aria-pressed` on toggles, live regions for messages, keyboard
reachable controls, `touch-action: none` on the scene only (the page scrolls normally elsewhere),
canvas sized to the container (280–640px), pointer events for mouse, touch and pen, text scaling
through relative units, `prefers-reduced-motion` respected (no confetti, no hops, faster runs),
sound off/on toggle (short WebAudio blips, only after a tap), ghost trail toggle, and an assist
mode that gives a nudge after two misses without solving the mission.

## What was tested

- `node --test ascend-stem-a-lotl-rescue-lab/tests`: 68 tests. Every mission, variation and stage
  is solvable by search; every badge challenge is achievable on its main mission; identical
  designs give identical results, timings and trails; reset never inherits state; designs snap to
  the grid; tray limits hold; each mechanic's diagnoses name the observed failure; progress merge
  keeps the best of both and drops junk; ownership never comes from the progress record.
- `node tools/smoke.js` (Playwright, Chromium): 37 checks on a 390px phone viewport with touch and
  a 900px desktop viewport. One tap from the map into mission 1; a flat-ramp run gives the
  "stopped on the ramp" diagnosis with the build kept; rotate and run delivers; progress persists
  across reload; the dock lights on the map; touch tap selects a part; handle drag rotates and
  Undo restores; one mission of every mechanic plays to success; the preview unlock modal states
  that no key is redeemed. Screenshots in `tools/shots/`.
- `php -l` on the plugin.

Not tested here, and why: anything on the live site. This environment cannot reach
ascendstemacademy.com (network policy), and plugins cannot be installed through the site's
MCP connector. So the WordPress REST routes, the Pond Key redemption against the real
`ascend_games_*` functions, WooCommerce fulfilment end to end, Ultimate Member login, the
Astra theme's button styles around the game, and real Android devices remain to be checked
after upload. The unlock path is written against the shared plugin's public functions as
read from its source on the `claude/hexalotl-vocabulary-expansion-s2js82` branch (v2.x);
the live site runs v2.3.0, so confirm the function names still exist (the admin screen reports
"connected" or "not found").

## Integration boundary (honest list)

- **Not built**: a purchase flow. Buying keys stays in the WooCommerce shop and the parent's cart.
- **Not built**: offline downloads of owned levels. Free missions work offline from the browser cache
  after a first load; owned missions need one online load per device to learn ownership.
- **Not built**: notifications and analytics. No SDKs were added; the `ascend:game-complete` event
  and `ascend_rl_progress_saved` action are the only hooks.
- **Not changed**: the daily games' key accounting, prices, products, refunds, family sharing.
- **Parent view**: `[ascend_rescue_lab_summary]` is provided but not yet placed on `/user/`, because
  the plugin is not installed and an unknown shortcode would render as text.

## Development

```
node --test ascend-stem-a-lotl-rescue-lab/tests
node ascend-stem-a-lotl-rescue-lab/tools/build-preview.js
NODE_PATH=/opt/node22/lib/node_modules node ascend-stem-a-lotl-rescue-lab/tools/smoke.js
```

Adding a mission: append to `LEVELS` in `assets/levels.js` (one new idea per mission, a main
mission first in `variations`, two `challenges`), add its area to `AREAS`, bump `ASCEND_RL_LEVELS`
in the plugin, and run the tests: they fail until the new content is solvable.
