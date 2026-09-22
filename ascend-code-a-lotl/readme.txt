=== Ascend Code-a-lotl ===
Contributors: Ascend STEM Academy
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A coding puzzle for the Axolotl Games family. Students program an axolotl with
arrow blocks and Repeat blocks to eat every shrimp and swim home.

== What it teaches ==
* Sequencing: steps run in order
* Loops and nested loops: Repeat blocks
* Pattern recognition and efficiency: 3 stars for solving within the block goal
* Debugging: watch the run, find the wrong step, fix it

== Install ==
1. Zip the `ascend-code-a-lotl` folder and upload it in Plugins > Add New > Upload.
2. Activate it.
3. Create a page (e.g. /code-a-lotl/) containing the shortcode: [ascend_code_a_lotl]
   Optional: [ascend_code_a_lotl start_level="4"]

== Progress ==
Progress is kept in the browser. For logged-in students it is also saved to user
meta (`ascend_cal_progress`) via REST (`/wp-json/ascend-cal/v1/progress`), so it
follows them across devices. Other plugins can hook:
* PHP: do_action( 'ascend_cal_progress_saved', $user_id, $progress )
* JS:  the `ascend:game-complete` DOM event ({ game, level, stars, blocks })

== Tests ==
node --test ascend-code-a-lotl/tests/levels.test.js
