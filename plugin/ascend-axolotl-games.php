<?php
/**
 * Plugin Name:       Ascend Axolotl Games
 * Plugin URI:        https://ascendstemacademy.com/
 * Description:       All four daily axolotl puzzle games in one plugin: Guess-a-lotl, Connect-a-lotl, Hex-a-lotl, and Cross-a-lotl. Replaces the four separate game plugins. Every shortcode, option key, user meta key, AJAX action, and version constant is preserved exactly, so student progress and the dashboard carry over untouched.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ascend STEM Academy
 * License:           GPL-2.0-or-later
 *
 * ---------------------------------------------------------------------------
 * REPLACES: ascend-guess-a-lotl, ascend-connect-a-lotl, ascend-hex-a-lotl,
 *           ascend-cross-a-lotl. Deactivate all four after activating this.
 *
 * ROLLBACK is lossless. Every storage key is byte-identical to the originals,
 * so deactivating this and reactivating the four old plugins restores the
 * previous setup with no data loss.
 * ---------------------------------------------------------------------------
 */

if (!defined('ABSPATH')) exit;

define('ASCEND_GAMES_VERSION', '2.0.0');


/* ======================================================================
   SHARED LAYER
   Everything the four games have in common: one parent admin menu, an
   overview screen, the Royal MCP option bridge, and the one-time
   Hex-a-lotl progress-log migration.
   ====================================================================== */

/**
 * The four games, in play order. Used by the overview screen and the MCP
 * bridge so a new game only has to be described in one place.
 */
function ascend_games_registry() {
    return [
        'axw' => [
            'label'     => 'Guess-a-lotl',
            'shortcode' => 'ascend_guess_a_lotl',
            'url'       => 'https://ascendstemacademy.com/guess-a-lotl/',
            'bank'      => 'ascend_axw_words',
            'bank_noun' => 'words',
            'settings'  => ['ascend_axw_max_guesses', 'ascend_axw_mascot_url'],
            'constant'  => 'ASCEND_AXW_VERSION',
            'admin'     => 'ascend-axw',
        ],
        'axc' => [
            'label'     => 'Connect-a-lotl',
            'shortcode' => 'ascend_connect_a_lotl',
            'url'       => 'https://ascendstemacademy.com/connect-a-lotl/',
            'bank'      => 'ascend_axc_puzzles',
            'bank_noun' => 'puzzles',
            'settings'  => ['ascend_axc_max_mistakes', 'ascend_axc_mascot_url'],
            'constant'  => 'ASCEND_AXC_VERSION',
            'admin'     => 'ascend-axc',
        ],
        'axh' => [
            'label'     => 'Hex-a-lotl',
            'shortcode' => 'ascend_hex_a_lotl',
            'url'       => 'https://ascendstemacademy.com/hex-a-lotl/',
            'bank'      => 'ascend_axh_puzzles',
            'bank_noun' => 'ponds',
            'settings'  => ['ascend_axh_unlock_pct', 'ascend_axh_longest_discount_pct', 'ascend_axh_mascot_url'],
            'constant'  => 'ASCEND_AXH_VERSION',
            'admin'     => 'ascend-axh',
        ],
        'axx' => [
            'label'     => 'Cross-a-lotl',
            'shortcode' => 'ascend_cross_a_lotl',
            'url'       => 'https://ascendstemacademy.com/cross-a-lotl/',
            'bank'      => 'ascend_axx_puzzles',
            'bank_noun' => 'themes',
            'settings'  => ['ascend_axx_max_hints', 'ascend_axx_mascot_url'],
            'constant'  => 'ASCEND_AXX_VERSION',
            'admin'     => 'ascend-axx',
        ],
    ];
}

/* ----------------------------------------------------------------------
   Parent admin menu.

   Registered at priority 9 so it exists before each game's own
   add_submenu_page() calls run at the default priority 10.
   ---------------------------------------------------------------------- */

add_action('admin_menu', function () {
    add_menu_page(
        'Axolotl Games',
        'Axolotl Games',
        'manage_options',
        'ascend-games',
        'ascend_games_overview_page',
        'dashicons-games',
        58
    );
    add_submenu_page(
        'ascend-games',
        'Overview',
        'Overview',
        'manage_options',
        'ascend-games',
        'ascend_games_overview_page'
    );
}, 9);

/**
 * Overview screen: how much content each game has left, and where to edit it.
 *
 * The bank size matters operationally — a game whose bank is nearly exhausted
 * leaves students at a dead end, which is invisible from the games themselves.
 */
function ascend_games_overview_page() {
    if (!current_user_can('manage_options')) return;

    $games = ascend_games_registry();
    echo '<div class="wrap"><h1>Axolotl Games</h1>';
    echo '<p>All four daily puzzle games. Each row links to its own student roster and puzzle bank.</p>';
    echo '<table class="widefat striped"><thead><tr>'
       . '<th>Game</th><th>Version</th><th>Bank size</th><th>Shortcode</th><th>Manage</th>'
       . '</tr></thead><tbody>';

    foreach ($games as $pfx => $g) {
        $bank  = get_option($g['bank'], []);
        $count = is_array($bank) ? count($bank) : 0;
        $ver   = defined($g['constant']) ? constant($g['constant']) : '—';

        // Flag a bank that will run out within a week of daily play.
        $warn = ($count > 0 && $count < 7);
        $cell = $count . ' ' . esc_html($g['bank_noun']);
        if ($count === 0) {
            $cell = '<strong style="color:#b32d2e;">empty</strong>';
        } elseif ($warn) {
            $cell .= ' <span style="color:#b32d2e;" title="Under a week of daily play">&#9888;</span>';
        }

        printf(
            '<tr><td><strong>%s</strong><br><a href="%s" target="_blank" rel="noopener">view page</a></td>'
            . '<td>%s</td><td>%s</td><td><code>[%s]</code></td>'
            . '<td><a href="%s">Students</a> &nbsp;|&nbsp; <a href="%s">Puzzle bank</a></td></tr>',
            esc_html($g['label']),
            esc_url($g['url']),
            esc_html($ver),
            $cell,
            esc_html($g['shortcode']),
            esc_url(admin_url('admin.php?page=' . $g['admin'])),
            esc_url(admin_url('admin.php?page=' . $g['admin'] . '-settings'))
        );
    }

    echo '</tbody></table>';
    echo '<p style="margin-top:16px;color:#666;">A warning triangle means fewer than seven entries remain — '
       . 'students playing daily will reach the end of that bank within a week.</p>';
    echo '</div>';
}

/* ----------------------------------------------------------------------
   Royal MCP bridge.

   Opts every game DATA option into Royal MCP's readable AND writable
   allowlists, so puzzle banks and settings can be edited over MCP without
   a plugin re-upload. Royal MCP enforces write ⊆ readable, so each key is
   registered on both filters.

   This replaces the standalone bridge Code Snippet — that snippet can be
   deleted once this plugin is active. Both registering the same keys would
   be harmless (the lists are de-duplicated), but there is no reason to
   maintain it in two places.

   Scope is game content only. No code path, no student progress, no
   credential is exposed here.
   ---------------------------------------------------------------------- */

/* ======================================================================
   PASSES

   The games stay one-puzzle-a-day. A pass is what lifts that limit, and
   it is sold through the WooCommerce store already on this site.

   Three things are deliberately simple here:

   - No recurring billing. WooCommerce Subscriptions is not installed, so
     a "monthly" pass is a one-off purchase that grants 30 days. Nothing
     auto-renews and nothing can fail to renew.
   - Time is stored as one expiry timestamp per student, with -1 meaning
     a lifetime unlock. Buying more time extends from whichever is later,
     now or the current expiry, so a renewal bought early is never lost.
   - Fulfilment is idempotent. Woo fires the status hooks more than once
     in normal operation, so processed order IDs are recorded per student
     and a repeat firing grants nothing.
   ====================================================================== */

define('ASCEND_PASS_UNTIL_META',  'ascend_games_pass_until');
define('ASCEND_PASS_SKIPS_META',  'ascend_games_skips');
define('ASCEND_PASS_ORDERS_META', 'ascend_games_pass_orders');

/**
 * The sellable passes, each mapped to the option holding its Woo product ID.
 * Setting a product ID is what switches a pass on; ones left at 0 are simply
 * never offered, so the store can be built up a product at a time.
 */
function ascend_games_pass_products() {
    return [
        'skip'  => ['option' => 'ascend_games_product_skip',  'label' => 'Next Puzzle',  'grant' => 'skip'],
        'month' => ['option' => 'ascend_games_product_month', 'label' => '30-Day Pass',  'grant' => 'days', 'days' => 30],
        'year'  => ['option' => 'ascend_games_product_year',  'label' => 'Annual Pass',  'grant' => 'days', 'days' => 365],
        'life'  => ['option' => 'ascend_games_product_life',  'label' => 'Full Unlock',  'grant' => 'life'],
    ];
}

/* An unlimited pass: lifetime, or time-based and not yet expired. */
function ascend_games_has_pass($user_id) {
    if (!$user_id) return false;
    $until = get_user_meta($user_id, ASCEND_PASS_UNTIL_META, true);
    if ($until === '' || $until === null) return false;
    if ((int) $until === -1) return true;
    return (int) $until > time();
}

function ascend_games_pass_expires($user_id) {
    $until = (int) get_user_meta($user_id, ASCEND_PASS_UNTIL_META, true);
    return ($until === -1 || $until > time()) ? $until : 0;
}

/* Single-use "open the next puzzle" credits, bought one at a time. */
function ascend_games_skips($user_id) {
    return $user_id ? max(0, (int) get_user_meta($user_id, ASCEND_PASS_SKIPS_META, true)) : 0;
}

function ascend_games_consume_skip($user_id) {
    $n = ascend_games_skips($user_id);
    if ($n < 1) return false;
    update_user_meta($user_id, ASCEND_PASS_SKIPS_META, $n - 1);
    return true;
}

function ascend_games_grant_skips($user_id, $qty = 1) {
    update_user_meta($user_id, ASCEND_PASS_SKIPS_META, ascend_games_skips($user_id) + max(1, (int) $qty));
}

function ascend_games_grant_days($user_id, $days) {
    $current = (int) get_user_meta($user_id, ASCEND_PASS_UNTIL_META, true);
    if ($current === -1) return; // lifetime already; time cannot improve on it
    $base = max(time(), $current);
    update_user_meta($user_id, ASCEND_PASS_UNTIL_META, $base + ((int) $days * DAY_IN_SECONDS));
}

function ascend_games_grant_life($user_id) {
    update_user_meta($user_id, ASCEND_PASS_UNTIL_META, -1);
}

/**
 * The pass products created in this site's store, seeded once so the feature
 * works on upload with nothing to wire up by hand.
 *
 * add_option() only writes when the key is absent, so an ID changed later
 * (in wp-admin or over MCP) is never clobbered by this. Point an option at a
 * different product to swap what a pass sells, or set it to 0 to withdraw
 * that pass — the games stop offering anything they cannot link to.
 */
function ascend_games_seed_pass_products() {
    $seed = [
        'ascend_games_product_skip'  => 6988,
        'ascend_games_product_month' => 6989,
        'ascend_games_product_year'  => 6990,
        'ascend_games_product_life'  => 6991,
    ];
    foreach ($seed as $option => $product_id) {
        if (get_option($option, null) === null) add_option($option, $product_id);
    }
}
add_action('admin_init', 'ascend_games_seed_pass_products');

/* All four buy links at once, for handing to a game's front end. */
function ascend_games_pass_urls() {
    $urls = [];
    foreach (array_keys(ascend_games_pass_products()) as $key) {
        $urls[$key] = ascend_games_pass_url($key);
    }
    return $urls;
}

/* Where to send a student who wants to buy. Empty when that product has not
   been created yet, which is the signal not to offer it at all. */
function ascend_games_pass_url($key) {
    $all = ascend_games_pass_products();
    if (!isset($all[$key])) return '';
    $pid = (int) get_option($all[$key]['option']);
    if ($pid < 1) return '';
    $url = get_permalink($pid);
    return $url ? $url : '';
}

/**
 * Turn a paid order into access. Bound to both 'processing' and 'completed'
 * because a digital order can settle on either depending on the gateway.
 */
function ascend_games_fulfil_order($order_id) {
    if (!function_exists('wc_get_order')) return;
    $order = wc_get_order($order_id);
    if (!$order) return;

    $user_id = $order->get_user_id();
    if (!$user_id) return; // guest checkout has no account to unlock

    $processed = get_user_meta($user_id, ASCEND_PASS_ORDERS_META, true);
    $processed = is_array($processed) ? $processed : [];
    if (in_array((int) $order_id, $processed, true)) return;

    $by_product = [];
    foreach (ascend_games_pass_products() as $pass) {
        $pid = (int) get_option($pass['option']);
        if ($pid > 0) $by_product[$pid] = $pass;
    }
    if (empty($by_product)) return;

    $granted = false;
    foreach ($order->get_items() as $item) {
        $pid = (int) $item->get_product_id();
        if (!isset($by_product[$pid])) continue;
        $pass = $by_product[$pid];
        $qty  = max(1, (int) $item->get_quantity());

        if ($pass['grant'] === 'skip')      ascend_games_grant_skips($user_id, $qty);
        elseif ($pass['grant'] === 'days')  ascend_games_grant_days($user_id, $pass['days'] * $qty);
        else                                ascend_games_grant_life($user_id);
        $granted = true;
    }

    if ($granted) {
        $processed[] = (int) $order_id;
        update_user_meta($user_id, ASCEND_PASS_ORDERS_META, $processed);
    }
}
add_action('woocommerce_order_status_processing', 'ascend_games_fulfil_order');
add_action('woocommerce_order_status_completed',  'ascend_games_fulfil_order');

function ascend_games_mcp_options() {
    $keys = [];
    foreach (ascend_games_registry() as $g) {
        $keys[] = $g['bank'];
        foreach ($g['settings'] as $s) $keys[] = $s;
    }
    foreach (ascend_games_pass_products() as $pass) $keys[] = $pass['option'];
    return $keys;
}

foreach (['royal_mcp_readable_options', 'royal_mcp_writable_options'] as $ascend_games_filter) {
    add_filter($ascend_games_filter, function ($options) {
        $options = is_array($options) ? $options : [];
        return array_values(array_unique(array_merge($options, ascend_games_mcp_options())));
    });
}
unset($ascend_games_filter);

/**
 * Refuse a write that would empty a puzzle bank that currently has content.
 *
 * A bank silently emptied by a malformed write leaves students with nothing
 * to play and produces no error anywhere. Keeping the previous value is
 * always the safer failure.
 */
function ascend_games_guard_bank($value, $old_value, $option) {
    $incoming_empty = (!is_array($value) || count($value) === 0);
    $had_content    = (is_array($old_value) && count($old_value) > 0);
    return ($incoming_empty && $had_content) ? $old_value : $value;
}

foreach (ascend_games_registry() as $ascend_games_g) {
    add_filter('pre_update_option_' . $ascend_games_g['bank'], 'ascend_games_guard_bank', 10, 3);
    add_action('update_option_' . $ascend_games_g['bank'], function () {
        wp_cache_flush();
        if (function_exists('w3tc_flush_all')) w3tc_flush_all();
    });
}
unset($ascend_games_g);

/* ----------------------------------------------------------------------
   Hex-a-lotl progress-log migration.

   Hex-a-lotl shipped writing axh_solved_log with its own shape, while the
   student dashboard reads {prefix}_puzzle_log in the shared shape that the
   other three games use. The result: the Hex stats card was always empty and
   the Hex Flawless badge could never unlock.

   This converts existing history once per user. The original axh_solved_log
   entries are left completely untouched, so the migration is reversible and
   re-running it is harmless.

   Historical entries predate any record of whether the longest word was
   found, so found_largest is false for them rather than guessed. New solves
   record it accurately.
   ---------------------------------------------------------------------- */

define('ASCEND_GAMES_MIGRATION', 1);

add_action('init', function () {
    if (!is_user_logged_in()) return;

    $user_id = get_current_user_id();
    if ((int) get_user_meta($user_id, 'ascend_games_migrated', true) >= ASCEND_GAMES_MIGRATION) return;

    $old = get_user_meta($user_id, 'axh_solved_log', true);
    $new = get_user_meta($user_id, 'axh_puzzle_log', true);

    if (is_array($old) && $old && !is_array($new)) {
        $converted = [];
        foreach ($old as $entry) {
            if (!is_array($entry)) continue;
            $idx = isset($entry['puzzleIndex']) ? (int) $entry['puzzleIndex'] : count($converted);
            $converted[] = [
                'puzzle'        => $idx + 1,
                'found_largest' => false,
            ];
        }
        if ($converted) update_user_meta($user_id, 'axh_puzzle_log', $converted);
    }

    update_user_meta($user_id, 'ascend_games_migrated', ASCEND_GAMES_MIGRATION);
});



/* ======================================================================
   GUESS-A-LOTL  (prefix axw_)
   ====================================================================== */

define('ASCEND_AXW_VERSION', '1.1.0');

/* ============================================================
   DEFAULT WORD BANK — ~100 real, verified 5-letter words themed
   around axolotls, regeneration, camouflage, senses, and general
   STEM. Editable any time from wp-admin, no code changes needed.
   ============================================================ */
function ascend_axw_default_words() {
    return [
        'LIMBS','CELLS','HEALS','GROWS','MOLTS','GENES','BONES','ORGAN','NERVE','BLOOD',
        'VEINS','SCARS','SKINS','WOUND','PULSE','TRAIT','VITAL','ADAPT','LARVA','GILLS',
        'ATOMS','FORCE','ORBIT','VOLTS','WAVES','SOLID','GASES','PLANT','ROOTS','LEAFY',
        'FUNGI','YEAST','OZONE','CLOUD','STORM','OCEAN','RIVER','MAGMA','CRUST','SPARK',
        'COLOR','SHADE','BLEND','TONES','LIGHT','TINTS','GLOWS','OPTIC','PRISM','PATCH',
        'SPOTS','SCALE','LAYER','VISOR','MIMIC','TRICK','CLOAK','MASKS','HIDES','SHIFT',
        'SOUND','SONAR','RADAR','SENSE','TOUCH','SMELL','TASTE','SIGHT','BRAIN','SIGNS',
        'FIELD','DEPTH','DRIFT','FLOWS','ALERT','WATCH','TRACK','GUARD','JOINT','TOOTH',
        'SCALY','SLIMY','MOIST','PONDS','NICHE','PREYS','HUNTS','SWIMS','CRAWL','GLIDE',
        'FLOAT','DIVES','SPAWN','CLONE','BREED','HATCH','MOLDS','SKULL','CLAWS','SNOUT',
    ];
}

register_activation_hook(__FILE__, function () {
    if (!get_option('ascend_axw_words')) {
        add_option('ascend_axw_words', ascend_axw_default_words());
    }
    if (get_option('ascend_axw_mascot_url', null) === null) {
        add_option('ascend_axw_mascot_url', '');
    }
    if (!get_option('ascend_axw_max_guesses')) {
        add_option('ascend_axw_max_guesses', 7);
    }
});

function ascend_axw_get_words() {
    $words = get_option('ascend_axw_words', ascend_axw_default_words());
    return is_array($words) ? array_values(array_map('strtoupper', $words)) : ascend_axw_default_words();
}

function ascend_axw_max_guesses() {
    $n = (int) get_option('ascend_axw_max_guesses', 7);
    return $n > 0 ? $n : 7;
}

function ascend_axw_today_key() {
    return current_time('Y-m-d');
}

/* Each student's current word is simply the next one they haven't solved yet —
   fully individualized, no shared calendar puzzle. */
function ascend_axw_current_word($user_id) {
    $words = ascend_axw_get_words();
    $progress = (int) get_user_meta($user_id, 'axw_progress', true);
    if ($progress >= count($words)) return null;
    return $words[$progress];
}

function ascend_axw_is_complete($user_id) {
    $total = count(ascend_axw_get_words());
    if ($total === 0) return false;
    return (int) get_user_meta($user_id, 'axw_progress', true) >= $total;
}

/* One attempt per day regardless of win or loss. */
function ascend_axw_can_attempt_today($user_id) {
    return get_user_meta($user_id, 'axw_last_attempt', true) !== ascend_axw_today_key();
}

/* ============================================================
   WORD-GUESS SCORING (server side, never trusts the client)
   ============================================================ */
function ascend_axw_score_guess($guess, $answer) {
    $guess = strtoupper($guess);
    $answer_letters = str_split(strtoupper($answer));
    $result = array_fill(0, 5, 'absent');

    for ($i = 0; $i < 5; $i++) {
        if ($guess[$i] === $answer_letters[$i]) {
            $result[$i] = 'correct';
            $answer_letters[$i] = null;
        }
    }
    for ($i = 0; $i < 5; $i++) {
        if ($result[$i] === 'correct') continue;
        $pos = array_search($guess[$i], $answer_letters, true);
        if ($pos !== false) {
            $result[$i] = 'present';
            $answer_letters[$pos] = null;
        }
    }
    return $result;
}

/* ============================================================
   AJAX: GET STATE
   ============================================================ */
add_action('wp_ajax_ascend_axw_get_state', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axw_nonce', 'nonce');

    $user_id  = get_current_user_id();
    $today    = ascend_axw_today_key();
    $total    = count(ascend_axw_get_words());
    $progress = (int) get_user_meta($user_id, 'axw_progress', true);
    $complete = ascend_axw_is_complete($user_id);

    $last_attempt    = get_user_meta($user_id, 'axw_last_attempt', true);
    $attempted_today = ($last_attempt === $today);
    $today_result    = $attempted_today ? get_user_meta($user_id, 'axw_attempt_result', true) : '';

    $guess_date  = get_user_meta($user_id, 'axw_guess_date', true);
    $raw_guesses = ($guess_date === $today) ? get_user_meta($user_id, 'axw_today_guesses', true) : [];
    $raw_guesses = is_array($raw_guesses) ? $raw_guesses : [];

    $current_word = ascend_axw_current_word($user_id);
    $feedback_log = [];
    if ($current_word) {
        foreach ($raw_guesses as $g) {
            $feedback_log[] = ['guess' => $g, 'result' => ascend_axw_score_guess($g, $current_word)];
        }
    }

    wp_send_json_success([
        'total'          => $total,
        'progress'       => $progress,
        'complete'       => $complete,
        'streak'         => (int) get_user_meta($user_id, 'axw_streak', true),
        'attemptedToday' => $attempted_today,
        'todayResult'    => $today_result,
        'guesses'        => $feedback_log,
        'revealedWord'   => ($today_result === 'won') ? ($current_word ?: get_user_meta($user_id, 'axw_last_word', true)) : null,
        'maxGuesses'     => ascend_axw_max_guesses(),
    ]);
});

/* ============================================================
   AJAX: SUBMIT GUESS
   ============================================================ */
add_action('wp_ajax_ascend_axw_submit_guess', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axw_nonce', 'nonce');

    $user_id = get_current_user_id();
    $guess   = strtoupper(sanitize_text_field($_POST['guess'] ?? ''));

    if (!preg_match('/^[A-Z]{5}$/', $guess)) wp_send_json_error('Enter a 5-letter word.');
    if (ascend_axw_is_complete($user_id)) wp_send_json_error('You’ve found every word!');

    $today       = ascend_axw_today_key();
    $last_attempt = get_user_meta($user_id, 'axw_last_attempt', true);
    if ($last_attempt === $today) wp_send_json_error('You already played today’s word. Come back tomorrow.');

    $answer = ascend_axw_current_word($user_id);
    $max    = ascend_axw_max_guesses();

    $guess_date = get_user_meta($user_id, 'axw_guess_date', true);
    $guesses = ($guess_date === $today) ? get_user_meta($user_id, 'axw_today_guesses', true) : [];
    $guesses = is_array($guesses) ? $guesses : [];
    if (count($guesses) >= $max) wp_send_json_error('No tries left today.');

    $result = ascend_axw_score_guess($guess, $answer);
    $guesses[] = $guess;
    update_user_meta($user_id, 'axw_today_guesses', $guesses);
    update_user_meta($user_id, 'axw_guess_date', $today);

    $won         = ($guess === $answer);
    $out_of_tries = (count($guesses) >= $max);
    $new_progress = null;
    $new_streak   = null;

    if ($won || $out_of_tries) {
        update_user_meta($user_id, 'axw_last_attempt', $today);
        update_user_meta($user_id, 'axw_attempt_result', $won ? 'won' : 'lost');
        update_user_meta($user_id, 'axw_last_word', $answer);

        $total_attempts = (int) get_user_meta($user_id, 'axw_total_attempts', true);
        update_user_meta($user_id, 'axw_total_attempts', $total_attempts + 1);

        if (!$won) {
            $total_losses = (int) get_user_meta($user_id, 'axw_total_losses', true);
            update_user_meta($user_id, 'axw_total_losses', $total_losses + 1);
        }

        if ($won) {
            $current = (int) get_user_meta($user_id, 'axw_progress', true);
            update_user_meta($user_id, 'axw_progress', $current + 1);

            $found = get_user_meta($user_id, 'axw_found_words', true);
            $found = is_array($found) ? $found : [];
            $found[] = ['word' => $answer, 'date' => $today];
            update_user_meta($user_id, 'axw_found_words', $found);

            $win_guess_number = count($guesses); // which try (1-indexed) the win happened on
            $dist = get_user_meta($user_id, 'axw_win_distribution', true);
            $dist = is_array($dist) ? $dist : [];
            $dist[$win_guess_number] = (int) ($dist[$win_guess_number] ?? 0) + 1;
            update_user_meta($user_id, 'axw_win_distribution', $dist);

            $yesterday   = date('Y-m-d', strtotime($today . ' -1 day'));
            $streak_date = get_user_meta($user_id, 'axw_last_streak_date', true);
            $streak      = (int) get_user_meta($user_id, 'axw_streak', true);
            $streak      = ($streak_date === $yesterday) ? $streak + 1 : 1;
            update_user_meta($user_id, 'axw_streak', $streak);
            update_user_meta($user_id, 'axw_last_streak_date', $today);

            $longest_streak = (int) get_user_meta($user_id, 'axw_longest_streak', true);
            if ($streak > $longest_streak) update_user_meta($user_id, 'axw_longest_streak', $streak);

            $new_progress = $current + 1;
            $new_streak   = $streak;
        }
    }

    wp_send_json_success([
        'result'       => $result,
        'won'          => $won,
        'outOfTries'   => $out_of_tries,
        'revealedWord' => $won ? $answer : null,
        'guessNumber'  => count($guesses),
        'newProgress'  => $new_progress,
        'newStreak'    => $new_streak,
    ]);
});

/* ============================================================
   SHORTCODE — [ascend_guess_a_lotl]
   ============================================================ */
add_shortcode('ascend_guess_a_lotl', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in to play.</p>';
    }

    $user_id  = get_current_user_id();
    $total    = count(ascend_axw_get_words());
    $progress = (int) get_user_meta($user_id, 'axw_progress', true);
    $streak   = (int) get_user_meta($user_id, 'axw_streak', true);
    $level    = max(1, (int) floor($progress / 5) + 1);
    $nonce    = wp_create_nonce('ascend_axw_nonce');
    $ajaxurl  = admin_url('admin-ajax.php');
    $mascot   = get_option('ascend_axw_mascot_url', '');

    ob_start();
    ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&display=swap');
#axw-game{--blue:#009CDE;--green:#1D4010;--lgreen:#BFE0B4;max-width:598px;margin:0 auto;padding:25px 15px 50px;font-family:Roboto,Arial,sans-serif;color:#24332a;zoom:1.15;}
#axw-game *{box-sizing:border-box;}
.axw-title{text-align:center;font-size:clamp(28px,5vw,42px);margin:0;color:#173e63;font-weight:700;letter-spacing:0;font-family:'Baloo 2',Roboto,Arial,sans-serif;}
.axw-subtitle{text-align:center;color:#62676c;margin:10px 0 20px;font-size:15px;}
.axw-stats{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.axw-stat{background:white;border:1px solid #e4e1da;border-radius:14px;padding:9px 17px;font-size:14px;}
.axw-stat strong{color:var(--blue);}
.axw-mascot{text-align:center;margin-bottom:6px;}
.axw-mascot img{max-width:150px;width:100%;height:auto;}
.axw-word-grid{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;}
.axw-word{width:74px;min-height:44px;display:flex;align-items:center;justify-content:center;border-radius:10px;font-size:13px;font-weight:700;}
.axw-word.unlocked{color:white;background:var(--green);}
.axw-word.just-unlocked{animation:axwUnlock .7s cubic-bezier(.34,1.56,.64,1);}
@keyframes axwUnlock{0%{transform:scale(.3) rotate(-10deg);opacity:0;box-shadow:0 0 0 0 rgba(29,64,16,.5);}55%{transform:scale(1.18) rotate(5deg);opacity:1;box-shadow:0 0 0 8px rgba(29,64,16,.15);}100%{transform:scale(1) rotate(0deg);box-shadow:0 0 0 0 rgba(29,64,16,0);}}
.axw-word.locked{background:#f4f2ec;border:1px dashed #d0ccc2;color:#aaa;}
.axw-word.current{background:white;border:2px solid var(--blue);color:var(--blue);}
.axw-locked-toggle{display:block;margin:10px auto 0;background:transparent;border:1px dashed #c9c5b8;border-radius:8px;padding:8px 16px;font-size:12px;color:#77705f;cursor:pointer;font-family:inherit;}
.axw-locked-toggle:hover{border-color:#999;color:#555;}
#axw-locked-grid{margin-top:10px;}
.axw-toggle-row{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:10px;}
.axw-toggle-row .axw-locked-toggle{margin:0;}
.axw-instructions{margin-top:10px;background:#eef6fb;border:1px solid #d4e7f2;border-radius:12px;padding:14px 16px;font-size:13px;color:#24332a;text-align:left;max-width:380px;margin-left:auto;margin-right:auto;}
.axw-game-progress{text-align:center;font-size:15px;font-weight:700;margin:16px 0 6px;}
.axw-live{text-align:center;font-size:13px;color:#1474aa;margin-bottom:14px;}
.axw-board{max-width:380px;margin:10px auto 5px;}
.axw-message{text-align:center;min-height:26px;font-size:14px;font-weight:700;margin-bottom:8px;}
.axw-guess-grid{display:grid;gap:7px;padding:4px;}
.axw-row{display:grid;grid-template-columns:repeat(5,1fr);gap:7px;}
.axw-letter{height:52px;border:2px solid #d3d6da;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:24px;line-height:1;font-weight:700;text-transform:uppercase;background:white;color:#24332a;transition:border-color .1s ease;}
.axw-letter.filled{border-color:var(--gray,#888);animation:axwPop .12s ease;}
.axw-letter.revealing{animation:axwReveal .55s ease forwards;}
.axw-letter.correct{background:var(--green);color:white;border-color:var(--green);}
.axw-letter.present{background:var(--blue);color:white;border-color:var(--blue);}
.axw-letter.absent{background:#787c7e;color:white;border-color:#787c7e;}
.axw-letter.win-bounce{animation:axwBounce .6s ease;}
.axw-row.shake{animation:axwShake .45s ease;}
.axw-submit-row{display:flex;justify-content:center;padding:16px 0;}
.axw-submit-btn{background:var(--blue);color:white;border:0;border-radius:12px;padding:11px 30px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;}
.axw-submit-btn:disabled{opacity:.4;cursor:default;}
.axw-key-wrap{display:flex;flex-wrap:wrap;justify-content:center;align-content:flex-start;gap:8px;padding:2px 6px;}
.axw-key{min-width:36px;height:42px;padding:0 6px;border:1px solid #d7dadd;border-radius:10px;background:#fff;color:#24332a;font-family:inherit;font-weight:700;font-size:14px;cursor:pointer;}
.axw-key:hover{border-color:var(--blue);}
.axw-key.correct{background:var(--green);color:white;border-color:var(--green);}
.axw-key.present{background:var(--blue);color:white;border-color:var(--blue);}
.axw-key.absent{background:#787c7e;color:white;border-color:#787c7e;}
.axw-key-back{min-width:58px;font-size:13px;}
.axw-tip{margin-top:16px;border-radius:12px;padding:12px 16px;background:#eef6fb;border:1px solid #d4e7f2;color:#1474aa;font-size:13px;text-align:center;}
.axw-done{margin-top:16px;border-radius:12px;padding:16px;background:#f4f2ec;border:1px solid #e0dccf;color:#5f5a4d;font-size:14px;text-align:center;}
@keyframes axwPop{0%{transform:scale(.9);}45%{transform:scale(1.06);}100%{transform:scale(1);}}
@keyframes axwReveal{0%{transform:rotateX(0deg);}50%{transform:rotateX(90deg);}51%{background:var(--reveal-bg,#787c7e);border-color:var(--reveal-bg,#787c7e);color:#fff;}100%{transform:rotateX(0deg);background:var(--reveal-bg,#787c7e);border-color:var(--reveal-bg,#787c7e);color:#fff;}}
@keyframes axwBounce{0%,20%{transform:translateY(0);}40%{transform:translateY(-14px);}55%{transform:translateY(0);}70%{transform:translateY(-6px);}85%,100%{transform:translateY(0);}}
@keyframes axwShake{10%,90%{transform:translateX(-3px);}20%,80%{transform:translateX(5px);}30%,50%,70%{transform:translateX(-7px);}40%,60%{transform:translateX(7px);}}
@media(max-width:420px){.axw-word{width:64px;font-size:12px;}.axw-key{min-width:30px;font-size:13px;}}
</style>

<div id="axw-game">
  <?php if ($mascot): ?><div class="axw-mascot"><img src="<?php echo esc_url($mascot); ?>" alt="Axolotl"></div><?php endif; ?>
  <h1 class="axw-title">Guess-a-lotl</h1>
  <p class="axw-subtitle">The axolotl word game. One a day. Find them all.</p>

  <div class="axw-stats">
    <div class="axw-stat">Streak: <strong id="axw-streak"><?php echo esc_html($streak); ?></strong></div>
    <div class="axw-stat">Found: <strong id="axw-found"><?php echo esc_html($progress); ?></strong> / <?php echo esc_html($total); ?></div>
    <div class="axw-stat">Level: <strong id="axw-level"><?php echo esc_html($level); ?></strong></div>
  </div>

  <div class="axw-toggle-row">
    <button type="button" id="axw-instructions-toggle" class="axw-locked-toggle">How to play &#9662;</button>
  </div>
  <div id="axw-instructions-panel" class="axw-instructions" style="display:none;"></div>
  <div class="axw-live" id="axw-live-badge"></div>
  <div id="axw-play-area"></div>
</div>

<script>
(function(){
var ASCEND_AXW = { ajaxurl: <?php echo wp_json_encode($ajaxurl); ?>, nonce: <?php echo wp_json_encode($nonce); ?> };
var REVEAL_COLOR = {correct:'var(--green)', present:'var(--blue)', absent:'#787c7e'};
var REVEAL_STAGGER = 220;

var state = null, currentGuess = "", guessesShown = 0, gameOver = false, revealingLock = false;

function api(action, data){
  var body = new URLSearchParams(Object.assign({action:action, nonce:ASCEND_AXW.nonce}, data||{}));
  return fetch(ASCEND_AXW.ajaxurl, {method:'POST', credentials:'same-origin', body:body}).then(function(r){return r.json();});
}

function loadState(){
  api('ascend_axw_get_state', {}).then(function(resp){
    if(!resp.success){ message(resp.data || 'Could not load your progress.'); return; }
    state = resp.data;
    renderAll();
  });
}

function renderAll(){
  document.getElementById('axw-streak').textContent = state.streak;
  document.getElementById('axw-found').textContent = state.progress;
  document.getElementById('axw-level').textContent = Math.max(1, Math.floor(state.progress/5)+1);
  renderInstructions();
  renderPlayArea();
}

function renderInstructions(){
  var panel = document.getElementById('axw-instructions-panel');
  panel.innerHTML =
    '<p style="margin:0 0 8px;">Guess the hidden 5-letter word in ' + (state.maxGuesses || 7) + ' tries or fewer.</p>' +
    '<p style="margin:0 0 8px;"><span style="background:#1D4010;color:#fff;border-radius:4px;padding:1px 6px;">Green</span> = right letter, right spot.</p>' +
    '<p style="margin:0 0 8px;"><span style="background:#009CDE;color:#fff;border-radius:4px;padding:1px 6px;">Blue</span> = right letter, wrong spot.</p>' +
    '<p style="margin:0 0 8px;"><span style="background:#787c7e;color:#fff;border-radius:4px;padding:1px 6px;">Gray</span> = letter isn\u2019t in the word.</p>' +
    '<p style="margin:0;">One attempt per day. Solve it to unlock the next word \u2014 miss it and the same word comes back tomorrow.</p>';

  var instrToggle = document.getElementById('axw-instructions-toggle');
  instrToggle.onclick = function(){
    var hidden = panel.style.display === 'none';
    panel.style.display = hidden ? '' : 'none';
    instrToggle.textContent = hidden ? 'Hide instructions \u25b4' : 'How to play \u25be';
  };

  var badge = document.getElementById('axw-live-badge');
  badge.textContent = state.complete ? '' : (state.attemptedToday ? 'Played today \u2014 come back tomorrow' : '');
}

function renderPlayArea(){
  var area = document.getElementById('axw-play-area');
  if (state.complete){
    area.innerHTML = '<div class="axw-done">You\u2019ve found every word! \ud83c\udf89 More will be added soon.</div>';
    return;
  }

  area.innerHTML =
    '<div class="axw-board">' +
      '<div id="axw-message" class="axw-message"></div>' +
      '<div id="axw-guess-grid" class="axw-guess-grid"></div>' +
      '<div class="axw-submit-row"><button type="button" id="axw-submit-btn" class="axw-submit-btn">Submit guess</button></div>' +
      '<div id="axw-keyboard" class="axw-key-wrap"></div>' +
    '</div>' +
    '<div class="axw-tip">One attempt per day. Solve it to unlock the next word \u2014 miss it and you get another try tomorrow.</div>';

  createGrid();
  createKeyboard();
  currentGuess = "";
  gameOver = state.attemptedToday;
  guessesShown = 0;

  (state.guesses || []).forEach(function(entry){
    paintRow(guessesShown, entry.guess, entry.result);
    guessesShown++;
  });

  var btn = document.getElementById('axw-submit-btn');
  btn.onclick = submitGuess;
  btn.disabled = gameOver;

  if (state.attemptedToday){
    message(state.todayResult === 'won' ? '\u2713 Solved! Come back tomorrow for your next word.' : 'Not quite \u2014 come back tomorrow to try again!');
  }
}

function createGrid(){
  var grid = document.getElementById('axw-guess-grid');
  grid.innerHTML = '';
  for (var r = 0; r < state.maxGuesses; r++){
    var row = document.createElement('div');
    row.className = 'axw-row';
    for (var c = 0; c < 5; c++){
      var cell = document.createElement('div');
      cell.className = 'axw-letter';
      cell.id = 'axw-letter-' + r + '-' + c;
      row.appendChild(cell);
    }
    grid.appendChild(row);
  }
}

function createKeyboard(){
  var wrap = document.getElementById('axw-keyboard');
  wrap.innerHTML = '';
  'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').forEach(function(k){
    var b = document.createElement('button');
    b.className = 'axw-key'; b.textContent = k;
    b.onclick = function(){ keyPress(k); };
    wrap.appendChild(b);
  });
  var back = document.createElement('button');
  back.className = 'axw-key axw-key-back'; back.textContent = '\u232B Delete';
  back.onclick = function(){ keyPress('\u232B'); };
  wrap.appendChild(back);
}

function keyPress(key){
  if (gameOver || revealingLock) return;
  if (key === '\u232B'){ currentGuess = currentGuess.slice(0,-1); refreshRow(); return; }
  if (/^[A-Z]$/.test(key) && currentGuess.length < 5){ currentGuess += key; refreshRow(); }
}

document.addEventListener('keydown', function(e){
  if (!state || gameOver || revealingLock) return;
  if (e.key === 'Enter') submitGuess();
  else if (e.key === 'Backspace') keyPress('\u232B');
  else if (/^[a-zA-Z]$/.test(e.key)) keyPress(e.key.toUpperCase());
});

function refreshRow(){
  for (var c = 0; c < 5; c++){
    var cell = document.getElementById('axw-letter-' + guessesShown + '-' + c);
    if (!cell) continue;
    cell.textContent = currentGuess[c] || '';
    cell.classList.toggle('filled', !!currentGuess[c]);
  }
}

function shakeRow(row){
  var rowEl = document.querySelectorAll('#axw-guess-grid .axw-row')[row];
  if (!rowEl) return;
  rowEl.classList.remove('shake');
  void rowEl.offsetWidth;
  rowEl.classList.add('shake');
}

function paintRow(row, guess, result){
  for (var i = 0; i < 5; i++){
    var cell = document.getElementById('axw-letter-' + row + '-' + i);
    if (!cell) continue;
    cell.textContent = guess[i];
    cell.classList.add(result[i]);
    updateKeyboard(guess[i], result[i]);
  }
}

function animateReveal(row, guess, result, callback){
  var btn = document.getElementById('axw-submit-btn');
  revealingLock = true;
  if (btn) btn.disabled = true;

  for (var i = 0; i < 5; i++){
    (function(i){
      setTimeout(function(){
        var cell = document.getElementById('axw-letter-' + row + '-' + i);
        if (!cell) return;
        cell.textContent = guess[i];
        cell.style.setProperty('--reveal-bg', REVEAL_COLOR[result[i]]);
        cell.classList.remove('filled');
        cell.classList.add('revealing');
        updateKeyboard(guess[i], result[i]);
        cell.addEventListener('animationend', function handler(){
          cell.classList.remove('revealing');
          cell.classList.add(result[i]);
          cell.removeEventListener('animationend', handler);
        });
      }, i * REVEAL_STAGGER);
    })(i);
  }

  setTimeout(function(){
    revealingLock = false;
    if (btn) btn.disabled = gameOver;
    if (callback) callback();
  }, 4 * REVEAL_STAGGER + 600);
}

function winBounce(row){
  for (var i = 0; i < 5; i++){
    (function(i){
      setTimeout(function(){
        var cell = document.getElementById('axw-letter-' + row + '-' + i);
        if (cell) cell.classList.add('win-bounce');
      }, i * 90);
    })(i);
  }
}

function updateKeyboard(letter, status){
  document.querySelectorAll('.axw-key').forEach(function(b){
    if (b.textContent !== letter) return;
    if (status === 'correct'){ b.classList.remove('present','absent'); b.classList.add('correct'); }
    else if (status === 'present' && !b.classList.contains('correct')){ b.classList.remove('absent'); b.classList.add('present'); }
    else if (status === 'absent' && !b.classList.contains('correct') && !b.classList.contains('present')){ b.classList.add('absent'); }
  });
}

function message(text){ var m = document.getElementById('axw-message'); if (m) m.textContent = text; }

function submitGuess(){
  if (gameOver || revealingLock) return;
  if (currentGuess.length !== 5){ message('Enter a 5-letter word.'); shakeRow(guessesShown); return; }
  var guess = currentGuess, row = guessesShown;

  api('ascend_axw_submit_guess', {guess: guess}).then(function(resp){
    if (!resp.success){ message(resp.data || 'Something went wrong.'); shakeRow(row); return; }
    guessesShown++;
    currentGuess = '';

    animateReveal(row, guess, resp.data.result, function(){
      if (resp.data.won){
        gameOver = true;
        state.progress = resp.data.newProgress;
        state.streak = resp.data.newStreak;
        state.attemptedToday = true;
        state.todayResult = 'won';
        winBounce(row);
        message('\u2713 Solved! Come back tomorrow for your next word.');
        document.getElementById('axw-streak').textContent = state.streak;
        document.getElementById('axw-found').textContent = state.progress;
        document.getElementById('axw-level').textContent = Math.max(1, Math.floor(state.progress/5)+1);
      } else if (resp.data.outOfTries){
        gameOver = true;
        state.attemptedToday = true;
        state.todayResult = 'lost';
        message('Not quite \u2014 come back tomorrow to try again!');
      }
      var badge = document.getElementById('axw-live-badge');
      if (badge) badge.textContent = 'Played today \u2014 come back tomorrow';
      var btn = document.getElementById('axw-submit-btn');
      if (btn) btn.disabled = gameOver;
    });
  });
}

loadState();
})();
</script>
    <?php
    return ob_get_clean();
});

/* ============================================================
   ADMIN
   ============================================================ */
add_action('admin_menu', function () {
    add_submenu_page('ascend-games', 'Guess-a-lotl – Students', 'Guess-a-lotl: Students', 'manage_options', 'ascend-axw', 'ascend_axw_admin_students_page');
    add_submenu_page('ascend-games', 'Guess-a-lotl – Word Bank & Settings', 'Guess-a-lotl: Word Bank & Settings', 'manage_options', 'ascend-axw-settings', 'ascend_axw_admin_settings_page');
});

function ascend_axw_admin_students_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['axw_reset_user']) && check_admin_referer('axw_reset_user')) {
        $reset_id = (int) $_POST['axw_reset_user'];
        ascend_axw_reset_user($reset_id);
        echo '<div class="notice notice-success"><p>All progress reset for that student.</p></div>';
    }

    if (isset($_POST['axw_reset_today']) && check_admin_referer('axw_reset_today')) {
        $reset_id = (int) $_POST['axw_reset_today'];
        ascend_axw_reset_today($reset_id);
        echo '<div class="notice notice-success"><p>Today’s attempt cleared for that student — they can play again today. Overall progress and streak were left untouched.</p></div>';
    }

    if (isset($_POST['axw_reset_all']) && check_admin_referer('axw_reset_all')) {
        $users = get_users(['fields' => ['ID']]);
        foreach ($users as $u) ascend_axw_reset_user($u->ID);
        echo '<div class="notice notice-success"><p><strong>Every student’s progress has been reset.</strong> Word bank and settings are untouched — only per-student progress, streaks, and guess history were cleared.</p></div>';
    }

    echo '<div class="wrap"><h1>Guess-a-lotl — student roster</h1>';

    echo '<form method="post" onsubmit="return confirm(\'Reset EVERY student\u2019s progress, streak, and guess history? This cannot be undone.\');" style="margin-bottom:14px;">';
    wp_nonce_field('axw_reset_all');
    echo '<button type="submit" name="axw_reset_all" class="button button-secondary" style="color:#a00;border-color:#a00;">Reset game database (reset ALL students)</button>';
    echo '</form>';

    echo '<table class="widefat striped"><thead><tr><th>Student</th><th>Level</th><th>Streak</th><th>Words found</th><th>Last played</th><th>Found words (admin only)</th><th>Actions</th></tr></thead><tbody>';

    $total = count(ascend_axw_get_words());
    $users = get_users(['fields' => ['ID', 'display_name']]);
    $shown = 0;
    foreach ($users as $u) {
        $progress = (int) get_user_meta($u->ID, 'axw_progress', true);
        $streak   = (int) get_user_meta($u->ID, 'axw_streak', true);
        $last     = get_user_meta($u->ID, 'axw_last_attempt', true);
        if ($progress === 0 && $streak === 0 && !$last) continue;
        $shown++;
        $level = max(1, (int) floor($progress / 5) + 1);

        $found = get_user_meta($u->ID, 'axw_found_words', true);
        $found = is_array($found) ? $found : [];
        $found_list = '(none yet)';
        if (!empty($found)) {
            $rows = array_map(function ($f) { return esc_html($f['word'] . ' — ' . $f['date']); }, $found);
            $found_list = '<details><summary>' . count($found) . ' word' . (count($found) !== 1 ? 's' : '') . '</summary>' . implode('<br>', $rows) . '</details>';
        }

        echo '<tr><td>' . esc_html($u->display_name) . '</td><td>' . $level . '</td><td>' . $streak . '</td><td>' . $progress . ' / ' . $total . '</td><td>' . ($last ? esc_html($last) : '—') . '</td><td>' . $found_list . '</td><td>';

        echo '<form method="post" onsubmit="return confirm(\'Clear TODAY\u2019s attempt only for ' . esc_js($u->display_name) . '? Their overall progress and streak stay as-is.\');" style="margin:0 0 4px;">';
        wp_nonce_field('axw_reset_today');
        echo '<input type="hidden" name="axw_reset_today" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small">Reset today only</button></form>';

        echo '<form method="post" onsubmit="return confirm(\'Reset ALL progress for ' . esc_js($u->display_name) . '? This cannot be undone.\');" style="margin:0;">';
        wp_nonce_field('axw_reset_user');
        echo '<input type="hidden" name="axw_reset_user" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small" style="color:#a00;">Reset all progress</button></form>';

        echo '</td></tr>';
    }
    if (!$shown) echo '<tr><td colspan="7">No students have played yet.</td></tr>';
    echo '</tbody></table></div>';
}

function ascend_axw_reset_user($user_id) {
    foreach (['axw_progress', 'axw_streak', 'axw_last_attempt', 'axw_attempt_result', 'axw_guess_date', 'axw_today_guesses', 'axw_last_word', 'axw_last_streak_date', 'axw_found_words'] as $key) {
        delete_user_meta($user_id, $key);
    }
}

/* Clears only today's in-progress/completed attempt, leaving overall progress, streak,
   and the found-words history untouched — lets a student replay today without losing anything. */
function ascend_axw_reset_today($user_id) {
    foreach (['axw_last_attempt', 'axw_attempt_result', 'axw_guess_date', 'axw_today_guesses', 'axw_last_word'] as $key) {
        delete_user_meta($user_id, $key);
    }
}

function ascend_axw_admin_settings_page() {
    if (!current_user_can('manage_options')) return;
    wp_enqueue_media();

    if (isset($_POST['ascend_axw_save']) && check_admin_referer('ascend_axw_settings')) {
        $raw = sanitize_textarea_field($_POST['words_raw'] ?? '');
        $words = array_values(array_unique(array_filter(array_map('trim', explode("\n", strtoupper($raw))), function($w){
            return preg_match('/^[A-Z]{5}$/', $w);
        })));
        if (!empty($words)) update_option('ascend_axw_words', $words);

        $max_guesses = (int) ($_POST['max_guesses'] ?? 7);
        update_option('ascend_axw_max_guesses', $max_guesses > 0 ? $max_guesses : 7);
        update_option('ascend_axw_mascot_url', esc_url_raw($_POST['mascot_url'] ?? ''));

        echo '<div class="notice notice-success"><p>Saved. ' . count($words) . ' valid 5-letter words in the bank.</p></div>';
    }

    $words   = ascend_axw_get_words();
    $mascot  = get_option('ascend_axw_mascot_url', '');
    $maxg    = ascend_axw_max_guesses();

    echo '<div class="wrap"><h1>Guess-a-lotl — word bank &amp; settings</h1>';
    echo '<p>Words are stored server-side only — students only ever receive per-letter correct/present/absent feedback while guessing, never the word bank itself.</p>';
    echo '<form method="post">';
    wp_nonce_field('ascend_axw_settings');

    echo '<h2>Game rules</h2>';
    echo '<p><label><strong>Guesses allowed per attempt</strong><br><input type="number" name="max_guesses" min="1" max="15" value="' . esc_attr($maxg) . '" style="width:80px;"></label></p>';

    echo '<h2>Mascot image (optional)</h2>';
    echo '<input type="hidden" id="axw_mascot_url" name="mascot_url" value="' . esc_attr($mascot) . '">';
    echo '<div id="axw_mascot_preview">' . ($mascot ? '<img src="' . esc_url($mascot) . '" style="max-width:150px;display:block;margin-bottom:8px;">' : '<p style="color:#777;">No image set.</p>') . '</div>';
    echo '<button type="button" class="button" id="axw_mascot_btn">Choose image</button> <button type="button" class="button" id="axw_mascot_clear">Remove</button>';

    echo '<h2 style="margin-top:24px;">Word bank</h2>';
    echo '<p class="description">Type a 5-letter word and press Enter or comma to add it as a tag. Click the &times; on a tag to remove it. Currently ' . count($words) . ' words.</p>';
    echo '<div id="axw-tag-box" style="border:1px solid #ddd;border-radius:8px;padding:10px;background:#fff;max-width:700px;">';
    echo '<div id="axw-tag-list" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;"></div>';
    echo '<input type="text" id="axw-tag-entry" placeholder="Type a 5-letter word&hellip;" style="width:100%;border:1px solid #ccc;border-radius:6px;padding:8px;" maxlength="5">';
    echo '</div>';
    echo '<textarea name="words_raw" id="axw-words-hidden" style="display:none;">' . esc_textarea(implode("\n", $words)) . '</textarea>';

    echo '<p style="margin-top:16px;"><button type="submit" name="ascend_axw_save" class="button button-primary">Save changes</button></p></form></div>';

    echo '<script>
    (function(){
      var words = ' . wp_json_encode($words) . '.slice();
      var list = document.getElementById("axw-tag-list");
      var hidden = document.getElementById("axw-words-hidden");
      var entry = document.getElementById("axw-tag-entry");

      function sync(){ hidden.value = words.join("\\n"); }
      function render(){
        list.innerHTML = "";
        words.forEach(function(w, i){
          var chip = document.createElement("span");
          chip.style.cssText = "background:#f4f2ec;border:1px solid #ddd;border-radius:14px;padding:4px 8px 4px 12px;font-size:13px;display:inline-flex;align-items:center;gap:6px;";
          chip.innerHTML = w + " <button type=\"button\" style=\"border:0;background:none;cursor:pointer;color:#a00;font-weight:700;\" aria-label=\"Remove\">&times;</button>";
          chip.querySelector("button").onclick = function(){ words.splice(i,1); render(); sync(); };
          list.appendChild(chip);
        });
      }
      function tryAdd(){
        var v = entry.value.trim().toUpperCase();
        entry.value = "";
        if (!/^[A-Z]{5}$/.test(v)){ if(v) alert("Words must be exactly 5 letters (A-Z)."); return; }
        if (words.indexOf(v) !== -1){ return; }
        words.push(v); render(); sync();
      }
      entry.addEventListener("keydown", function(e){
        if (e.key === "Enter" || e.key === ","){ e.preventDefault(); tryAdd(); }
      });
      entry.addEventListener("blur", function(){ if (entry.value.trim()) tryAdd(); });
      render(); sync();

      document.getElementById("axw_mascot_btn").addEventListener("click", function(e){
        e.preventDefault();
        var frame = wp.media({title:"Select axolotl artwork", button:{text:"Use this image"}, multiple:false});
        frame.on("select", function(){
          var att = frame.state().get("selection").first().toJSON();
          document.getElementById("axw_mascot_url").value = att.url;
          document.getElementById("axw_mascot_preview").innerHTML = "<img src=\"" + att.url + "\" style=\"max-width:150px;display:block;margin-bottom:8px;\">";
        });
        frame.open();
      });
      document.getElementById("axw_mascot_clear").addEventListener("click", function(e){
        e.preventDefault();
        document.getElementById("axw_mascot_url").value = "";
        document.getElementById("axw_mascot_preview").innerHTML = "<p style=\"color:#777;\">No image set.</p>";
      });
    })();
    </script>';
}


/* ======================================================================
   CONNECT-A-LOTL  (prefix axc_)
   ====================================================================== */

define('ASCEND_AXC_VERSION', '1.0.0');

/* ============================================================
   LOCKED DECISIONS (mirrors Guess-a-lotl's design)
   - One attempt per day per student, win or lose.
   - Progress is per-student and sequential through the puzzle
     bank (each student's "current puzzle" = next unsolved one),
     not a shared site-wide daily puzzle.
   - Losing (running out of mistakes) does NOT advance progress —
     the same puzzle comes back tomorrow for another attempt.
   - A puzzle's 16 words are always visible on the board (that's
     how Connections-style games work); the secret is the
     grouping, not the words, so nothing is "hidden" the way
     Guess-a-lotl hides its answer word.
   - The day's board order is shuffled once and stored, so a page
     refresh mid-attempt never re-shuffles or costs a mistake.
   - Solved category rows always render in fixed tier order
     (lightest -> deepest color) regardless of the order the
     student actually solved them in, matching the genre norm.
   ============================================================ */

/* ============================================================
   DEFAULT PUZZLE BANK — 6 starter puzzles, axolotl/STEM themed.
   Each puzzle = 4 categories in tier order (0 = easiest/lightest
   color -> 3 = hardest/deepest color), each category = a label
   plus exactly 4 words. Fully editable from wp-admin, no code
   changes needed — see Puzzle Bank & Settings.
   ============================================================ */
function ascend_axc_default_puzzles() {
    return [
        [
            ['label' => 'AXOLOTL BODY PARTS',    'words' => ['GILLS', 'LIMBS', 'TAIL', 'SNOUT']],
            ['label' => 'THINGS THAT REGENERATE','words' => ['SKIN', 'HAIR', 'NAILS', 'CORAL']],
            ['label' => 'FRESHWATER HABITATS',   'words' => ['POND', 'LAKE', 'MARSH', 'STREAM']],
            ['label' => '___ CELL',              'words' => ['STEM', 'NERVE', 'SOLAR', 'BLOOD']],
        ],
        [
            ['label' => 'SHADES OF PINK',         'words' => ['BLUSH', 'CORAL', 'SALMON', 'ROSE']],
            ['label' => 'CAMOUFLAGE SYNONYMS',    'words' => ['BLEND', 'CLOAK', 'MASK', 'HIDE']],
            ['label' => 'STATES OF MATTER',       'words' => ['SOLID', 'LIQUID', 'PLASMA', 'VAPOR']],
            ['label' => '___ SENSE',              'words' => ['SIXTH', 'COMMON', 'LATERAL', 'NON']],
        ],
        [
            ['label' => 'AMPHIBIANS',             'words' => ['FROG', 'TOAD', 'NEWT', 'AXOLOTL']],
            ['label' => 'LAB EQUIPMENT',          'words' => ['BEAKER', 'FLASK', 'PIPETTE', 'BURNER']],
            ['label' => 'SIMPLE MACHINES',        'words' => ['LEVER', 'PULLEY', 'WEDGE', 'SCREW']],
            ['label' => 'TYPES OF ENERGY',        'words' => ['SOLAR', 'WIND', 'THERMAL', 'KINETIC']],
        ],
        [
            ['label' => 'GARDEN THINGS',          'words' => ['SEED', 'SOIL', 'LEAF', 'ROOT']],
            ['label' => 'WAYS TO MEASURE',        'words' => ['RULER', 'SCALE', 'GAUGE', 'TIMER']],
            ['label' => 'SPACE TERMS',            'words' => ['COMET', 'STAR', 'MOON', 'ORBIT']],
            ['label' => 'CODING TERMS',           'words' => ['LOOP', 'ARRAY', 'DEBUG', 'INPUT']],
        ],
        [
            ['label' => 'WEATHER WORDS',          'words' => ['STORM', 'CLOUD', 'BREEZE', 'FROST']],
            ['label' => 'LAYERS OF THE EARTH',    'words' => ['CRUST', 'MANTLE', 'CORE', 'MAGMA']],
            ['label' => 'MATH TERMS',             'words' => ['SUM', 'RATIO', 'ANGLE', 'GRAPH']],
            ['label' => 'ADAPTATION WORDS',       'words' => ['MIMIC', 'MOLT', 'EVOLVE', 'THRIVE']],
        ],
        [
            ['label' => 'POND LIFE',              'words' => ['TADPOLE', 'MINNOW', 'SNAIL', 'ALGAE']],
            ['label' => 'THE FIVE SENSES',        'words' => ['SIGHT', 'SMELL', 'TOUCH', 'HEARING']],
            ['label' => 'SCIENTIFIC TOOLS',       'words' => ['MICROSCOPE', 'TELESCOPE', 'COMPASS', 'MAGNET']],
            ['label' => 'REGENERATION WORDS',     'words' => ['RENEW', 'REBUILD', 'RESTORE', 'REPAIR']],
        ],
    ];
}

register_activation_hook(__FILE__, function () {
    if (!get_option('ascend_axc_puzzles')) {
        add_option('ascend_axc_puzzles', ascend_axc_default_puzzles());
    }
    if (get_option('ascend_axc_mascot_url', null) === null) {
        add_option('ascend_axc_mascot_url', '');
    }
    if (!get_option('ascend_axc_max_mistakes')) {
        add_option('ascend_axc_max_mistakes', 4);
    }
});

/* ============================================================
   CORE HELPERS
   ============================================================ */
function ascend_axc_normalize_puzzles($raw) {
    if (!is_array($raw)) return [];
    $clean = [];
    foreach ($raw as $puzzle) {
        if (!is_array($puzzle) || count($puzzle) !== 4) continue;
        $cats = [];
        $all_words = [];
        $ok = true;
        foreach (array_values($puzzle) as $cat) {
            $label = isset($cat['label']) ? trim((string) $cat['label']) : '';
            $words = isset($cat['words']) && is_array($cat['words']) ? $cat['words'] : [];
            $words = array_values(array_unique(array_filter(array_map(function ($w) {
                return strtoupper(trim((string) $w));
            }, $words))));
            if ($label === '' || count($words) !== 4) { $ok = false; break; }
            $cats[] = ['label' => $label, 'words' => $words];
            $all_words = array_merge($all_words, $words);
        }
        if (!$ok) continue;
        if (count(array_unique($all_words)) !== 16) continue; // words must be unique across all 4 categories
        $clean[] = $cats;
    }
    return $clean;
}

function ascend_axc_get_puzzles() {
    $puzzles = get_option('ascend_axc_puzzles', ascend_axc_default_puzzles());
    $clean = ascend_axc_normalize_puzzles($puzzles);
    return !empty($clean) ? $clean : ascend_axc_default_puzzles();
}

function ascend_axc_max_mistakes() {
    $n = (int) get_option('ascend_axc_max_mistakes', 4);
    return $n > 0 ? $n : 4;
}

function ascend_axc_today_key() {
    return current_time('Y-m-d');
}

/* Each student's current puzzle is the next one they haven't solved yet —
   fully individualized, no shared calendar puzzle. */
function ascend_axc_current_puzzle($user_id) {
    $puzzles = ascend_axc_get_puzzles();
    $progress = (int) get_user_meta($user_id, 'axc_progress', true);
    if ($progress >= count($puzzles)) return null;
    return $puzzles[$progress];
}

function ascend_axc_is_complete($user_id) {
    $total = count(ascend_axc_get_puzzles());
    if ($total === 0) return false;
    return (int) get_user_meta($user_id, 'axc_progress', true) >= $total;
}

/* Builds (and persists) today's shuffled board for a puzzle the first time
   the student loads it — reused for the rest of the day so a refresh never
   re-shuffles or resets progress mid-attempt. */
function ascend_axc_ensure_today_board($user_id, $puzzle) {
    $today = ascend_axc_today_key();
    $guess_date = get_user_meta($user_id, 'axc_guess_date', true);

    if ($guess_date === $today) {
        $board = get_user_meta($user_id, 'axc_today_board', true);
        if (is_array($board) && count($board) === 16) {
            return $board;
        }
    }

    $board = [];
    foreach ($puzzle as $tier => $cat) {
        foreach ($cat['words'] as $w) {
            $board[] = ['word' => $w, 'tier' => $tier];
        }
    }
    shuffle($board);

    update_user_meta($user_id, 'axc_today_board', $board);
    update_user_meta($user_id, 'axc_guess_date', $today);
    update_user_meta($user_id, 'axc_today_solved', []);
    update_user_meta($user_id, 'axc_today_mistakes', 0);

    return $board;
}

/* ============================================================
   AJAX: GET STATE
   ============================================================ */
add_action('wp_ajax_ascend_axc_get_state', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axc_nonce', 'nonce');

    $user_id  = get_current_user_id();
    $today    = ascend_axc_today_key();
    $total    = count(ascend_axc_get_puzzles());
    $progress = (int) get_user_meta($user_id, 'axc_progress', true);
    $complete = ascend_axc_is_complete($user_id);

    if ($complete) {
        wp_send_json_success([
            'total'    => $total,
            'progress' => $progress,
            'complete' => true,
            'streak'   => (int) get_user_meta($user_id, 'axc_streak', true),
        ]);
    }

    $puzzle = ascend_axc_current_puzzle($user_id);
    $board  = ascend_axc_ensure_today_board($user_id, $puzzle);

    $last_attempt    = get_user_meta($user_id, 'axc_last_attempt', true);
    $attempted_today = ($last_attempt === $today);
    $today_result    = $attempted_today ? get_user_meta($user_id, 'axc_attempt_result', true) : '';

    $solved   = get_user_meta($user_id, 'axc_today_solved', true);
    $solved   = is_array($solved) ? $solved : [];
    $mistakes = (int) get_user_meta($user_id, 'axc_today_mistakes', true);

    $solved_categories = [];
    foreach ($solved as $tier) {
        if (isset($puzzle[$tier])) {
            $solved_categories[] = ['tier' => $tier, 'label' => $puzzle[$tier]['label'], 'words' => $puzzle[$tier]['words']];
        }
    }

    $remaining_words = [];
    foreach ($board as $tile) {
        if (!in_array($tile['tier'], $solved, true)) {
            $remaining_words[] = $tile['word'];
        }
    }

    wp_send_json_success([
        'total'            => $total,
        'progress'         => $progress,
        'complete'         => false,
        'puzzleNumber'     => $progress + 1,
        'streak'           => (int) get_user_meta($user_id, 'axc_streak', true),
        'attemptedToday'   => $attempted_today,
        'todayResult'      => $today_result,
        'maxMistakes'      => ascend_axc_max_mistakes(),
        'mistakesUsed'     => $mistakes,
        'solvedCategories' => $solved_categories,
        'remainingWords'   => $remaining_words,
        'gameOver'         => $attempted_today,
    ]);
});

/* ============================================================
   AJAX: SUBMIT GUESS (a group of 4 selected words)
   ============================================================ */
add_action('wp_ajax_ascend_axc_submit_guess', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axc_nonce', 'nonce');

    $user_id = get_current_user_id();
    $today   = ascend_axc_today_key();

    if (ascend_axc_is_complete($user_id)) wp_send_json_error('You’ve solved every puzzle!');

    $last_attempt = get_user_meta($user_id, 'axc_last_attempt', true);
    if ($last_attempt === $today) wp_send_json_error('You already played today’s puzzle. Come back tomorrow.');

    $selection = isset($_POST['words']) && is_array($_POST['words']) ? $_POST['words'] : [];
    $selection = array_values(array_unique(array_map(function ($w) {
        return strtoupper(sanitize_text_field($w));
    }, $selection)));
    if (count($selection) !== 4) wp_send_json_error('Select exactly 4 words.');

    $puzzle = ascend_axc_current_puzzle($user_id);
    $board  = ascend_axc_ensure_today_board($user_id, $puzzle);

    $solved   = get_user_meta($user_id, 'axc_today_solved', true);
    $solved   = is_array($solved) ? $solved : [];
    $mistakes = (int) get_user_meta($user_id, 'axc_today_mistakes', true);
    $max      = ascend_axc_max_mistakes();

    if ($mistakes >= $max) wp_send_json_error('No tries left today.');

    // Confirm every selected word is a still-unsolved tile on today's board.
    $board_words = [];
    foreach ($board as $tile) {
        if (!in_array($tile['tier'], $solved, true)) $board_words[$tile['word']] = $tile['tier'];
    }
    foreach ($selection as $w) {
        if (!isset($board_words[$w])) wp_send_json_error('That selection isn’t valid — try again.');
    }

    sort($selection);
    $matched_tier = null;
    foreach ($puzzle as $tier => $cat) {
        if (in_array($tier, $solved, true)) continue;
        $cat_words = $cat['words'];
        sort($cat_words);
        if ($cat_words === $selection) { $matched_tier = $tier; break; }
    }

    $response = [
        'mistakesUsed' => $mistakes,
        'mistakesMax'  => $max,
        'wonAll'       => false,
        'lost'         => false,
    ];

    if ($matched_tier !== null) {
        $solved[] = $matched_tier;
        update_user_meta($user_id, 'axc_today_solved', $solved);

        $response['matched'] = true;
        $response['tier']    = $matched_tier;
        $response['label']   = $puzzle[$matched_tier]['label'];
        $response['words']   = $puzzle[$matched_tier]['words'];

        if (count($solved) >= 4) {
            // WON — advance progress, log the puzzle, update the win streak.
            update_user_meta($user_id, 'axc_last_attempt', $today);
            update_user_meta($user_id, 'axc_attempt_result', 'won');

            $total_attempts = (int) get_user_meta($user_id, 'axc_total_attempts', true);
            update_user_meta($user_id, 'axc_total_attempts', $total_attempts + 1);

            $current = (int) get_user_meta($user_id, 'axc_progress', true);
            update_user_meta($user_id, 'axc_progress', $current + 1);

            $log = get_user_meta($user_id, 'axc_puzzle_log', true);
            $log = is_array($log) ? $log : [];
            // "found_largest" is reused here (same key the dashboard's game-stats
            // card already reads for every puzzle-type game) to mean a flawless
            // solve — every group found with zero mistakes.
            $log[] = ['puzzle' => $current + 1, 'found_largest' => ($mistakes === 0)];
            update_user_meta($user_id, 'axc_puzzle_log', $log);

            $yesterday   = date('Y-m-d', strtotime($today . ' -1 day'));
            $streak_date = get_user_meta($user_id, 'axc_last_streak_date', true);
            $streak      = (int) get_user_meta($user_id, 'axc_streak', true);
            $streak      = ($streak_date === $yesterday) ? $streak + 1 : 1;
            update_user_meta($user_id, 'axc_streak', $streak);
            update_user_meta($user_id, 'axc_last_streak_date', $today);

            $longest_streak = (int) get_user_meta($user_id, 'axc_longest_streak', true);
            if ($streak > $longest_streak) update_user_meta($user_id, 'axc_longest_streak', $streak);

            $response['wonAll']      = true;
            $response['newProgress'] = $current + 1;
            $response['newStreak']   = $streak;
        }
    } else {
        $response['matched'] = false;

        // "One away" hint — helpful without giving away the actual grouping.
        $one_away = false;
        foreach ($puzzle as $tier => $cat) {
            if (in_array($tier, $solved, true)) continue;
            $overlap = count(array_intersect($cat['words'], $selection));
            if ($overlap === 3) { $one_away = true; break; }
        }
        $response['oneAway'] = $one_away;

        $mistakes++;
        update_user_meta($user_id, 'axc_today_mistakes', $mistakes);
        $response['mistakesUsed'] = $mistakes;

        if ($mistakes >= $max) {
            update_user_meta($user_id, 'axc_last_attempt', $today);
            update_user_meta($user_id, 'axc_attempt_result', 'lost');

            $total_attempts = (int) get_user_meta($user_id, 'axc_total_attempts', true);
            update_user_meta($user_id, 'axc_total_attempts', $total_attempts + 1);

            $total_losses = (int) get_user_meta($user_id, 'axc_total_losses', true);
            update_user_meta($user_id, 'axc_total_losses', $total_losses + 1);

            $response['lost'] = true;
        }
    }

    wp_send_json_success($response);
});

/* ============================================================
   SHORTCODE — [ascend_connect_a_lotl]
   ============================================================ */
add_shortcode('ascend_connect_a_lotl', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in to play.</p>';
    }

    $user_id  = get_current_user_id();
    $total    = count(ascend_axc_get_puzzles());
    $progress = (int) get_user_meta($user_id, 'axc_progress', true);
    $streak   = (int) get_user_meta($user_id, 'axc_streak', true);
    $level    = max(1, (int) floor($progress / 3) + 1);
    $nonce    = wp_create_nonce('ascend_axc_nonce');
    $ajaxurl  = admin_url('admin-ajax.php');
    $mascot   = get_option('ascend_axc_mascot_url', '');

    ob_start();
    ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&display=swap');
#axc-game{--blue:#009CDE;--dblue:#045C82;--green:#1D4010;--lgreen:#BFE0B4;--pink:#F5B9CF;--dpink:#E07FA3;max-width:598px;margin:0 auto;padding:25px 15px 50px;font-family:Roboto,Arial,sans-serif;color:#24332a;zoom:1.15;}
#axc-game *{box-sizing:border-box;}
.axc-title{text-align:center;font-size:clamp(28px,5vw,42px);margin:0;color:#173e63;font-weight:700;letter-spacing:0;font-family:'Baloo 2',Roboto,Arial,sans-serif;}
.axc-subtitle{text-align:center;color:#62676c;margin:10px 0 20px;font-size:15px;}
.axc-stats{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.axc-stat{background:white;border:1px solid #e4e1da;border-radius:14px;padding:9px 17px;font-size:14px;}
.axc-stat strong{color:var(--blue);}
.axc-mascot{text-align:center;margin-bottom:6px;}
.axc-mascot img{max-width:150px;width:100%;height:auto;}
.axc-toggle-row{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:2px;}
.axc-locked-toggle{display:block;margin:10px auto 0;background:transparent;border:1px dashed #c9c5b8;border-radius:8px;padding:8px 16px;font-size:12px;color:#77705f;cursor:pointer;font-family:inherit;}
.axc-locked-toggle:hover{border-color:#999;color:#555;}
.axc-instructions{margin-top:10px;background:#eef6fb;border:1px solid #d4e7f2;border-radius:12px;padding:14px 16px;font-size:13px;color:#24332a;text-align:left;max-width:420px;margin-left:auto;margin-right:auto;}
.axc-live{text-align:center;font-size:13px;color:#1474aa;margin-bottom:14px;min-height:16px;}
.axc-board{max-width:440px;margin:10px auto 5px;}
.axc-message{text-align:center;min-height:22px;font-size:14px;font-weight:700;margin-bottom:10px;}
.axc-solved-row{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border-radius:10px;padding:12px 8px;margin-bottom:8px;text-align:center;animation:axcSolvedIn .4s ease;}
.axc-solved-row .axc-solved-label{font-size:12px;font-weight:700;letter-spacing:.3px;}
.axc-solved-row .axc-solved-words{font-size:14px;font-weight:700;}
@keyframes axcSolvedIn{0%{transform:scale(.85);opacity:0;}100%{transform:scale(1);opacity:1;}}
.axc-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:7px;}
.axc-tile{min-height:64px;border:2px solid #d3d6da;border-radius:10px;display:flex;align-items:center;justify-content:center;text-align:center;font-size:12.5px;line-height:1.15;font-weight:700;text-transform:uppercase;background:white;color:#24332a;cursor:pointer;padding:6px 4px;user-select:none;transition:transform .08s ease,border-color .1s ease,background .1s ease;}
.axc-tile:hover{border-color:var(--blue);}
.axc-tile.selected{background:var(--green);color:#fff;border-color:var(--green);transform:translateY(-2px);}
.axc-tile.disabled{cursor:default;opacity:.45;}
.axc-tile.shake{animation:axcShake .45s ease;}
.axc-tile.gone{animation:axcGone .3s ease forwards;}
@keyframes axcShake{10%,90%{transform:translateX(-3px);}20%,80%{transform:translateX(5px);}30%,50%,70%{transform:translateX(-7px);}40%,60%{transform:translateX(7px);}}
@keyframes axcGone{100%{opacity:0;transform:scale(.7);}}
.axc-mistakes{display:flex;align-items:center;justify-content:center;gap:8px;margin:14px 0 6px;font-size:12px;color:#5F5E5A;}
.axc-dot{width:12px;height:12px;border-radius:50%;background:var(--blue);}
.axc-dot.used{background:var(--dpink);opacity:.5;}
.axc-actions{display:flex;justify-content:center;gap:10px;padding:14px 0 4px;flex-wrap:wrap;}
.axc-btn{border:0;border-radius:12px;padding:10px 22px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;}
.axc-btn.primary{background:var(--blue);color:#fff;}
.axc-btn.primary:disabled{opacity:.4;cursor:default;}
.axc-btn.secondary{background:#f4f2ec;color:#24332a;border:1px solid #ddd;}
.axc-tip{margin-top:16px;border-radius:12px;padding:12px 16px;background:#eef6fb;border:1px solid #d4e7f2;color:#1474aa;font-size:13px;text-align:center;}
.axc-done{margin-top:16px;border-radius:12px;padding:16px;background:#f4f2ec;border:1px solid #e0dccf;color:#5f5a4d;font-size:14px;text-align:center;}
@media(max-width:420px){.axc-tile{font-size:11px;min-height:56px;}}
</style>

<div id="axc-game">
  <?php if ($mascot): ?><div class="axc-mascot"><img src="<?php echo esc_url($mascot); ?>" alt="Axolotl"></div><?php endif; ?>
  <h1 class="axc-title">Connect-a-lotl</h1>
  <p class="axc-subtitle">Find the hidden connections. Four groups, one shot a day.</p>

  <div class="axc-stats">
    <div class="axc-stat">Streak: <strong id="axc-streak"><?php echo esc_html($streak); ?></strong></div>
    <div class="axc-stat">Solved: <strong id="axc-found"><?php echo esc_html($progress); ?></strong> / <?php echo esc_html($total); ?></div>
    <div class="axc-stat">Level: <strong id="axc-level"><?php echo esc_html($level); ?></strong></div>
  </div>

  <div class="axc-toggle-row">
    <button type="button" id="axc-instructions-toggle" class="axc-locked-toggle">How to play &#9662;</button>
  </div>
  <div id="axc-instructions-panel" class="axc-instructions" style="display:none;"></div>
  <div class="axc-live" id="axc-live-badge"></div>
  <div id="axc-play-area"></div>
</div>

<script>
(function(){
var ASCEND_AXC = { ajaxurl: <?php echo wp_json_encode($ajaxurl); ?>, nonce: <?php echo wp_json_encode($nonce); ?> };
var TIER_STYLE = [
  {bg:'#BFE0B4', text:'#1D4010'},
  {bg:'#F5B9CF', text:'#7A2A48'},
  {bg:'#009CDE', text:'#ffffff'},
  {bg:'#1D4010', text:'#ffffff'}
];

var state = null, selected = [], gameOver = false, busy = false;

function api(action, data){
  var params = new URLSearchParams();
  params.append('action', action);
  params.append('nonce', ASCEND_AXC.nonce);
  Object.keys(data||{}).forEach(function(k){
    var v = data[k];
    if (Array.isArray(v)) v.forEach(function(item){ params.append(k+'[]', item); });
    else params.append(k, v);
  });
  return fetch(ASCEND_AXC.ajaxurl, {method:'POST', credentials:'same-origin', body:params}).then(function(r){return r.json();});
}

function loadState(){
  api('ascend_axc_get_state', {}).then(function(resp){
    if(!resp.success){ message(resp.data || 'Could not load your progress.'); return; }
    state = resp.data;
    selected = [];
    renderAll();
  });
}

function renderAll(){
  document.getElementById('axc-streak').textContent = state.streak;
  document.getElementById('axc-found').textContent = state.progress;
  document.getElementById('axc-level').textContent = Math.max(1, Math.floor(state.progress/3)+1);
  renderInstructions();
  renderPlayArea();
}

function renderInstructions(){
  var panel = document.getElementById('axc-instructions-panel');
  panel.innerHTML =
    '<p style="margin:0 0 8px;">16 words. 4 hidden groups of 4. Tap 4 words that belong together, then Submit.</p>' +
    '<p style="margin:0 0 8px;">Groups get trickier from ' +
      '<span style="background:#BFE0B4;color:#1D4010;border-radius:4px;padding:1px 6px;">easiest</span> to ' +
      '<span style="background:#1D4010;color:#fff;border-radius:4px;padding:1px 6px;">hardest</span> — watch for words that seem to fit more than one group.</p>' +
    '<p style="margin:0 0 8px;">You get ' + (state.maxMistakes || 4) + ' mistakes. One attempt per day \u2014 miss it and the same puzzle comes back tomorrow.</p>' +
    '<p style="margin:0;">Solve all 4 groups to unlock the next puzzle.</p>';

  var instrToggle = document.getElementById('axc-instructions-toggle');
  instrToggle.onclick = function(){
    var hidden = panel.style.display === 'none';
    panel.style.display = hidden ? '' : 'none';
    instrToggle.textContent = hidden ? 'Hide instructions \u25b4' : 'How to play \u25be';
  };

  var badge = document.getElementById('axc-live-badge');
  badge.textContent = state.complete ? '' : (state.attemptedToday ? 'Played today \u2014 come back tomorrow' : '');
}

function renderPlayArea(){
  var area = document.getElementById('axc-play-area');
  if (state.complete){
    area.innerHTML = '<div class="axc-done">You\u2019ve solved every puzzle! \ud83c\udf89 More will be added soon.</div>';
    return;
  }

  gameOver = !!state.gameOver;

  var solvedHtml = '';
  (state.solvedCategories || []).slice().sort(function(a,b){ return a.tier - b.tier; }).forEach(function(cat){
    var style = TIER_STYLE[cat.tier] || TIER_STYLE[0];
    solvedHtml += '<div class="axc-solved-row" style="background:' + style.bg + ';color:' + style.text + ';">' +
      '<div class="axc-solved-label">' + escapeHtml(cat.label) + '</div>' +
      '<div class="axc-solved-words">' + cat.words.map(escapeHtml).join(' \u00b7 ') + '</div>' +
    '</div>';
  });

  var dots = '';
  for (var i = 0; i < (state.maxMistakes || 4); i++){
    dots += '<span class="axc-dot' + (i < state.mistakesUsed ? ' used' : '') + '"></span>';
  }

  area.innerHTML =
    '<div class="axc-board">' +
      '<div id="axc-message" class="axc-message"></div>' +
      '<div id="axc-solved-wrap">' + solvedHtml + '</div>' +
      '<div id="axc-grid" class="axc-grid"></div>' +
      '<div class="axc-mistakes"><span>Mistakes:</span>' + dots + '</div>' +
      '<div class="axc-actions">' +
        '<button type="button" id="axc-shuffle-btn" class="axc-btn secondary">Shuffle</button>' +
        '<button type="button" id="axc-deselect-btn" class="axc-btn secondary">Deselect all</button>' +
        '<button type="button" id="axc-submit-btn" class="axc-btn primary">Submit</button>' +
      '</div>' +
    '</div>' +
    '<div class="axc-tip">One attempt per day. Solve all 4 groups to unlock the next puzzle \u2014 miss it and you get another try tomorrow.</div>';

  buildGrid((state.remainingWords || []).slice());

  document.getElementById('axc-shuffle-btn').onclick = function(){
    var words = Array.prototype.slice.call(document.querySelectorAll('#axc-grid .axc-tile')).map(function(t){ return t.dataset.word; });
    for (var i = words.length - 1; i > 0; i--){
      var j = Math.floor(Math.random() * (i + 1));
      var tmp = words[i]; words[i] = words[j]; words[j] = tmp;
    }
    buildGrid(words);
  };
  document.getElementById('axc-deselect-btn').onclick = function(){ selected = []; refreshTiles(); };
  document.getElementById('axc-submit-btn').onclick = submitGuess;

  refreshTiles();

  if (state.attemptedToday){
    message(state.todayResult === 'won' ? '\u2713 Solved! Come back tomorrow for your next puzzle.' : 'Out of tries \u2014 come back tomorrow to try again!');
  }
}

function escapeHtml(s){
  var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
}

function buildGrid(words){
  var grid = document.getElementById('axc-grid');
  grid.innerHTML = '';
  words.forEach(function(w){
    var tile = document.createElement('div');
    tile.className = 'axc-tile';
    tile.textContent = w;
    tile.dataset.word = w;
    tile.onclick = function(){ toggleTile(w); };
    grid.appendChild(tile);
  });
  refreshTiles();
}

function refreshTiles(){
  document.querySelectorAll('#axc-grid .axc-tile').forEach(function(tile){
    var isSel = selected.indexOf(tile.dataset.word) !== -1;
    tile.classList.toggle('selected', isSel);
    tile.classList.toggle('disabled', gameOver);
  });
  var btn = document.getElementById('axc-submit-btn');
  if (btn) btn.disabled = gameOver || selected.length !== 4 || busy;
}

function toggleTile(word){
  if (gameOver || busy) return;
  var idx = selected.indexOf(word);
  if (idx !== -1){ selected.splice(idx, 1); }
  else if (selected.length < 4){ selected.push(word); }
  refreshTiles();
}

function shakeSelected(){
  document.querySelectorAll('#axc-grid .axc-tile.selected').forEach(function(tile){
    tile.classList.remove('shake');
    void tile.offsetWidth;
    tile.classList.add('shake');
  });
}

function message(text){ var m = document.getElementById('axc-message'); if (m) m.textContent = text; }

function submitGuess(){
  if (gameOver || busy || selected.length !== 4) return;
  busy = true;
  refreshTiles();
  var guess = selected.slice();

  api('ascend_axc_submit_guess', {words: guess}).then(function(resp){
    busy = false;
    if (!resp.success){ message(resp.data || 'Something went wrong.'); shakeSelected(); refreshTiles(); return; }
    var data = resp.data;

    if (data.matched){
      selected = [];
      var style = TIER_STYLE[data.tier] || TIER_STYLE[0];
      var row = document.createElement('div');
      row.className = 'axc-solved-row';
      row.style.background = style.bg; row.style.color = style.text;
      row.innerHTML = '<div class="axc-solved-label">' + escapeHtml(data.label) + '</div><div class="axc-solved-words">' + data.words.map(escapeHtml).join(' \u00b7 ') + '</div>';
      document.getElementById('axc-solved-wrap').appendChild(row);

      guess.forEach(function(w){
        var tile = document.querySelector('#axc-grid .axc-tile[data-word="' + CSS.escape(w) + '"]');
        if (tile) tile.classList.add('gone');
      });
      setTimeout(function(){
        var remaining = Array.prototype.slice.call(document.querySelectorAll('#axc-grid .axc-tile'))
          .map(function(t){ return t.dataset.word; })
          .filter(function(w){ return guess.indexOf(w) === -1; });
        buildGrid(remaining);
      }, 300);

      if (data.wonAll){
        gameOver = true;
        state.progress = data.newProgress;
        state.streak = data.newStreak;
        state.attemptedToday = true;
        state.todayResult = 'won';
        message('\u2713 Solved! Come back tomorrow for your next puzzle.');
        document.getElementById('axc-streak').textContent = state.streak;
        document.getElementById('axc-found').textContent = state.progress;
        document.getElementById('axc-level').textContent = Math.max(1, Math.floor(state.progress/3)+1);
        var badge = document.getElementById('axc-live-badge');
        if (badge) badge.textContent = 'Played today \u2014 come back tomorrow';
      } else {
        message('Nice \u2014 that\u2019s one group! Keep going.');
      }
    } else {
      shakeSelected();
      selected = [];
      var mistakeDots = document.querySelectorAll('.axc-dot');
      if (mistakeDots[data.mistakesUsed - 1]) mistakeDots[data.mistakesUsed - 1].classList.add('used');
      message(data.oneAway ? 'So close \u2014 one away!' : 'Not quite \u2014 try another group.');

      if (data.lost){
        gameOver = true;
        state.attemptedToday = true;
        state.todayResult = 'lost';
        message('Out of tries \u2014 come back tomorrow to try again!');
        var badge2 = document.getElementById('axc-live-badge');
        if (badge2) badge2.textContent = 'Played today \u2014 come back tomorrow';
      }
    }
    refreshTiles();
  });
}

loadState();
})();
</script>
    <?php
    return ob_get_clean();
});

/* ============================================================
   ADMIN
   ============================================================ */
add_action('admin_menu', function () {
    add_submenu_page('ascend-games', 'Connect-a-lotl – Students', 'Connect-a-lotl: Students', 'manage_options', 'ascend-axc', 'ascend_axc_admin_students_page');
    add_submenu_page('ascend-games', 'Connect-a-lotl – Puzzle Bank & Settings', 'Connect-a-lotl: Puzzle Bank & Settings', 'manage_options', 'ascend-axc-settings', 'ascend_axc_admin_settings_page');
});

function ascend_axc_admin_students_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['axc_reset_user']) && check_admin_referer('axc_reset_user')) {
        ascend_axc_reset_user((int) $_POST['axc_reset_user']);
        echo '<div class="notice notice-success"><p>All progress reset for that student.</p></div>';
    }
    if (isset($_POST['axc_reset_today']) && check_admin_referer('axc_reset_today')) {
        ascend_axc_reset_today((int) $_POST['axc_reset_today']);
        echo '<div class="notice notice-success"><p>Today’s attempt cleared for that student — they can play again today. Overall progress and streak were left untouched.</p></div>';
    }
    if (isset($_POST['axc_reset_all']) && check_admin_referer('axc_reset_all')) {
        $users = get_users(['fields' => ['ID']]);
        foreach ($users as $u) ascend_axc_reset_user($u->ID);
        echo '<div class="notice notice-success"><p><strong>Every student’s progress has been reset.</strong> Puzzle bank and settings are untouched — only per-student progress, streaks, and history were cleared.</p></div>';
    }

    echo '<div class="wrap"><h1>Connect-a-lotl — student roster</h1>';

    echo '<form method="post" onsubmit="return confirm(\'Reset EVERY student\u2019s progress, streak, and puzzle history? This cannot be undone.\');" style="margin-bottom:14px;">';
    wp_nonce_field('axc_reset_all');
    echo '<button type="submit" name="axc_reset_all" class="button button-secondary" style="color:#a00;border-color:#a00;">Reset game database (reset ALL students)</button>';
    echo '</form>';

    echo '<table class="widefat striped"><thead><tr><th>Student</th><th>Level</th><th>Streak</th><th>Puzzles solved</th><th>Last played</th><th>History (admin only)</th><th>Actions</th></tr></thead><tbody>';

    $total = count(ascend_axc_get_puzzles());
    $users = get_users(['fields' => ['ID', 'display_name']]);
    $shown = 0;
    foreach ($users as $u) {
        $progress = (int) get_user_meta($u->ID, 'axc_progress', true);
        $streak   = (int) get_user_meta($u->ID, 'axc_streak', true);
        $last     = get_user_meta($u->ID, 'axc_last_attempt', true);
        if ($progress === 0 && $streak === 0 && !$last) continue;
        $shown++;
        $level = max(1, (int) floor($progress / 3) + 1);

        $log = get_user_meta($u->ID, 'axc_puzzle_log', true);
        $log = is_array($log) ? $log : [];
        $log_list = '(none yet)';
        if (!empty($log)) {
            $rows = array_map(function ($e) {
                $flawless = !empty($e['found_largest']) ? ' — flawless' : '';
                return esc_html('Puzzle ' . $e['puzzle'] . ': solved' . $flawless);
            }, $log);
            $log_list = '<details><summary>' . count($log) . ' puzzle' . (count($log) !== 1 ? 's' : '') . '</summary>' . implode('<br>', $rows) . '</details>';
        }

        echo '<tr><td>' . esc_html($u->display_name) . '</td><td>' . $level . '</td><td>' . $streak . '</td><td>' . $progress . ' / ' . $total . '</td><td>' . ($last ? esc_html($last) : '—') . '</td><td>' . $log_list . '</td><td>';

        echo '<form method="post" onsubmit="return confirm(\'Clear TODAY\u2019s attempt only for ' . esc_js($u->display_name) . '? Their overall progress and streak stay as-is.\');" style="margin:0 0 4px;">';
        wp_nonce_field('axc_reset_today');
        echo '<input type="hidden" name="axc_reset_today" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small">Reset today only</button></form>';

        echo '<form method="post" onsubmit="return confirm(\'Reset ALL progress for ' . esc_js($u->display_name) . '? This cannot be undone.\');" style="margin:0;">';
        wp_nonce_field('axc_reset_user');
        echo '<input type="hidden" name="axc_reset_user" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small" style="color:#a00;">Reset all progress</button></form>';

        echo '</td></tr>';
    }
    if (!$shown) echo '<tr><td colspan="7">No students have played yet.</td></tr>';
    echo '</tbody></table></div>';
}

function ascend_axc_reset_user($user_id) {
    foreach (['axc_progress', 'axc_streak', 'axc_last_attempt', 'axc_attempt_result', 'axc_guess_date', 'axc_today_board', 'axc_today_solved', 'axc_today_mistakes', 'axc_last_streak_date', 'axc_longest_streak', 'axc_total_attempts', 'axc_total_losses', 'axc_puzzle_log'] as $key) {
        delete_user_meta($user_id, $key);
    }
}

/* Clears only today's in-progress/completed attempt, leaving overall progress, streak,
   and puzzle history untouched — lets a student replay today without losing anything. */
function ascend_axc_reset_today($user_id) {
    foreach (['axc_last_attempt', 'axc_attempt_result', 'axc_guess_date', 'axc_today_board', 'axc_today_solved', 'axc_today_mistakes'] as $key) {
        delete_user_meta($user_id, $key);
    }
}

function ascend_axc_admin_settings_page() {
    if (!current_user_can('manage_options')) return;
    wp_enqueue_media();

    if (isset($_POST['ascend_axc_save']) && check_admin_referer('ascend_axc_settings')) {
        $max_mistakes = (int) ($_POST['max_mistakes'] ?? 4);
        update_option('ascend_axc_max_mistakes', $max_mistakes > 0 ? $max_mistakes : 4);
        update_option('ascend_axc_mascot_url', esc_url_raw($_POST['mascot_url'] ?? ''));

        $decoded = json_decode(stripslashes($_POST['puzzles_json'] ?? '[]'), true);
        $clean = ascend_axc_normalize_puzzles($decoded);
        $submitted_count = is_array($decoded) ? count($decoded) : 0;
        if (!empty($clean)) {
            update_option('ascend_axc_puzzles', $clean);
            $dropped = $submitted_count - count($clean);
            $notice = 'Saved. ' . count($clean) . ' valid puzzle' . (count($clean) !== 1 ? 's' : '') . ' in the bank.';
            if ($dropped > 0) $notice .= ' (' . $dropped . ' puzzle' . ($dropped !== 1 ? 's were' : ' was') . ' skipped — each puzzle needs exactly 4 groups of exactly 4 unique words, with no word repeated across groups.)';
            echo '<div class="notice notice-success"><p>' . esc_html($notice) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>No valid puzzles found — the puzzle bank was left unchanged. Each puzzle needs exactly 4 groups of exactly 4 unique words.</p></div>';
        }
    }

    $puzzles = ascend_axc_get_puzzles();
    $mascot  = get_option('ascend_axc_mascot_url', '');
    $maxm    = ascend_axc_max_mistakes();

    echo '<div class="wrap"><h1>Connect-a-lotl — puzzle bank &amp; settings</h1>';
    echo '<p>Students only ever see the 16 words on the board — which words belong together is never sent until a group is correctly guessed.</p>';
    echo '<form method="post">';
    wp_nonce_field('ascend_axc_settings');

    echo '<h2>Game rules</h2>';
    echo '<p><label><strong>Mistakes allowed per attempt</strong><br><input type="number" name="max_mistakes" min="1" max="10" value="' . esc_attr($maxm) . '" style="width:80px;"></label></p>';

    echo '<h2>Mascot image (optional)</h2>';
    echo '<input type="hidden" id="axc_mascot_url" name="mascot_url" value="' . esc_attr($mascot) . '">';
    echo '<div id="axc_mascot_preview">' . ($mascot ? '<img src="' . esc_url($mascot) . '" style="max-width:150px;display:block;margin-bottom:8px;">' : '<p style="color:#777;">No image set.</p>') . '</div>';
    echo '<button type="button" class="button" id="axc_mascot_btn">Choose image</button> <button type="button" class="button" id="axc_mascot_clear">Remove</button>';

    echo '<h2 style="margin-top:24px;">Puzzle bank</h2>';
    echo '<p class="description">Each puzzle needs exactly 4 groups, in order from easiest (top, light green) to hardest (bottom, deep green). Give each group a label and exactly 4 words, comma-separated. A word can only appear once per puzzle. Currently ' . count($puzzles) . ' puzzle' . (count($puzzles) !== 1 ? 's' : '') . '.</p>';
    echo '<div id="axc-puzzle-list"></div>';
    echo '<p><button type="button" class="button" id="axc-add-puzzle">+ Add another puzzle</button></p>';
    echo '<textarea name="puzzles_json" id="axc-puzzles-hidden" style="display:none;"></textarea>';

    echo '<p style="margin-top:16px;"><button type="submit" name="ascend_axc_save" class="button button-primary">Save changes</button></p></form></div>';

    $tier_colors = ['#BFE0B4', '#F5B9CF', '#009CDE', '#1D4010'];
    $tier_text   = ['#1D4010', '#7A2A48', '#ffffff', '#ffffff'];

    echo '<script>
    (function(){
      var puzzles = ' . wp_json_encode($puzzles) . ';
      var tierColors = ' . wp_json_encode($tier_colors) . ';
      var tierText = ' . wp_json_encode($tier_text) . ';
      var list = document.getElementById("axc-puzzle-list");
      var hidden = document.getElementById("axc-puzzles-hidden");

      function blankPuzzle(){
        return [
          {label:"", words:["","","",""]},
          {label:"", words:["","","",""]},
          {label:"", words:["","","",""]},
          {label:"", words:["","","",""]}
        ];
      }

      function sync(){
        var out = [];
        document.querySelectorAll(".axc-puzzle-block").forEach(function(block){
          var cats = [];
          block.querySelectorAll(".axc-cat-row").forEach(function(row){
            var label = row.querySelector(".axc-cat-label").value.trim();
            var words = row.querySelector(".axc-cat-words").value.split(",").map(function(w){ return w.trim(); }).filter(Boolean);
            cats.push({label: label, words: words});
          });
          out.push(cats);
        });
        hidden.value = JSON.stringify(out);
      }

      function renderPuzzle(puzzle, index){
        var block = document.createElement("div");
        block.className = "axc-puzzle-block";
        block.style.cssText = "border:1px solid #ddd;border-radius:8px;padding:12px 14px;margin-bottom:14px;background:#fff;max-width:760px;";

        var header = document.createElement("div");
        header.style.cssText = "display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;";
        header.innerHTML = "<strong>Puzzle " + (index+1) + "</strong>";
        var removeBtn = document.createElement("button");
        removeBtn.type = "button"; removeBtn.className = "button button-small";
        removeBtn.style.color = "#a00";
        removeBtn.textContent = "Remove puzzle";
        removeBtn.onclick = function(){ block.remove(); renumber(); sync(); };
        header.appendChild(removeBtn);
        block.appendChild(header);

        for (var t = 0; t < 4; t++){
          var cat = puzzle[t] || {label:"", words:["","","",""]};
          var row = document.createElement("div");
          row.className = "axc-cat-row";
          row.style.cssText = "display:flex;align-items:center;gap:8px;margin-bottom:6px;";

          var swatch = document.createElement("span");
          swatch.style.cssText = "display:inline-block;width:16px;height:16px;border-radius:4px;flex:0 0 auto;background:" + tierColors[t] + ";border:1px solid #ccc;";
          row.appendChild(swatch);

          var labelInput = document.createElement("input");
          labelInput.type = "text"; labelInput.className = "axc-cat-label";
          labelInput.placeholder = "Group label";
          labelInput.value = cat.label || "";
          labelInput.style.cssText = "width:220px;";
          labelInput.addEventListener("input", sync);
          row.appendChild(labelInput);

          var wordsInput = document.createElement("input");
          wordsInput.type = "text"; wordsInput.className = "axc-cat-words";
          wordsInput.placeholder = "WORD1, WORD2, WORD3, WORD4";
          wordsInput.value = (cat.words || []).join(", ");
          wordsInput.style.cssText = "flex:1;min-width:260px;";
          wordsInput.addEventListener("input", sync);
          row.appendChild(wordsInput);

          block.appendChild(row);
        }

        list.appendChild(block);
      }

      function renumber(){
        document.querySelectorAll(".axc-puzzle-block").forEach(function(block, i){
          block.querySelector("strong").textContent = "Puzzle " + (i+1);
        });
      }

      puzzles.forEach(renderPuzzle);
      if (!puzzles.length) renderPuzzle(blankPuzzle(), 0);
      sync();

      document.getElementById("axc-add-puzzle").addEventListener("click", function(){
        renderPuzzle(blankPuzzle(), document.querySelectorAll(".axc-puzzle-block").length);
        sync();
      });

      document.getElementById("axc_mascot_btn").addEventListener("click", function(e){
        e.preventDefault();
        var frame = wp.media({title:"Select axolotl artwork", button:{text:"Use this image"}, multiple:false});
        frame.on("select", function(){
          var att = frame.state().get("selection").first().toJSON();
          document.getElementById("axc_mascot_url").value = att.url;
          document.getElementById("axc_mascot_preview").innerHTML = "<img src=\"" + att.url + "\" style=\"max-width:150px;display:block;margin-bottom:8px;\">";
        });
        frame.open();
      });
      document.getElementById("axc_mascot_clear").addEventListener("click", function(e){
        e.preventDefault();
        document.getElementById("axc_mascot_url").value = "";
        document.getElementById("axc_mascot_preview").innerHTML = "<p style=\"color:#777;\">No image set.</p>";
      });
    })();
    </script>';
}


/* ======================================================================
   HEX-A-LOTL  (prefix axh_)
   ====================================================================== */

define('ASCEND_AXH_VERSION', '1.5.0');

/* ============================================================
   DEFAULT PUZZLE BANK — two verified, real-word puzzles to start.
   Each puzzle: a center letter (required in every word), 6 outer
   letters, and a curated list of valid words (uppercase, letters
   may repeat, 4+ letters, must include the center letter, must use
   only the 7 puzzle letters). Add unlimited puzzles from wp-admin.
   ============================================================ */
function ascend_axh_default_puzzles() {
    return [
        [
            'center' => 'T',
            'outer'  => ['A', 'D', 'P', 'O', 'L', 'E'],
            'words'  => [
                'ADAPT', 'ADAPTED', 'ADEPT', 'ADOPT', 'ADOPTED', 'ALLOT', 'ALLOTTED', 'ALTO', 'APPELLATE', 'ATOLL',
                'ATOP', 'DATA', 'DATE', 'DATED', 'DEALT', 'DELETE', 'DELETED', 'DELTA', 'DEPLETE', 'DEPLETED',
                'DEPOT', 'DEPT', 'DOLT', 'DOTE', 'DOTED', 'DOTTED', 'ELATE', 'ELATED', 'LAPTOP', 'LATE', 'LATTE',
                'LEAPT', 'LEPT', 'LOOT', 'LOOTED', 'LOTTO', 'OPTED', 'PALATAL', 'PALATE', 'PALETTE', 'PALLET',
                'PALPATE', 'PALPATED', 'PATE', 'PATELLA', 'PATELLAE', 'PATTED', 'PEAT', 'PELLET', 'PELLETED',
                'PELT', 'PELTED', 'PETAL', 'PETTED', 'PLATE', 'PLATED', 'PLATELET', 'PLEAT', 'PLEATED', 'PLOT',
                'PLOTTED', 'POET', 'POTATO', 'POTTED', 'TADPOLE', 'TALE', 'TALL', 'TAPE', 'TAPED', 'TAPPED',
                'TATTED', 'TATTLE', 'TATTLED', 'TATTLETALE', 'TATTOO', 'TATTOOED', 'TEAL', 'TEAPOT', 'TEAT',
                'TEED', 'TEEPEE', 'TEETOTAL', 'TELL', 'TELLTALE', 'TEPEE', 'TOAD', 'TODDLE', 'TODDLED', 'TOED',
                'TOLD', 'TOLL', 'TOLLED', 'TOOL', 'TOOLED', 'TOOT', 'TOOTED', 'TOPPED', 'TOPPLE', 'TOPPLED',
                'TOTAL', 'TOTALED', 'TOTALLED', 'TOTE', 'TOTED', 'TOTTED',
            ],
        ],
        [
            'center' => 'R',
            'outer'  => ['S', 'T', 'O', 'A', 'G', 'E'],
            'words'  => [
                'AERATE', 'AERATES', 'AERATOR', 'AERATORS', 'AGAR', 'AGGREGATE', 'AGGREGATES', 'AGGRESSOR',
                'AGGRESSORS', 'AGREE', 'AGREES', 'AORTA', 'AORTAE', 'AORTAS', 'AREA', 'AREAS', 'ARES', 'ARGOT',
                'ARGOTS', 'AROSE', 'ARREARS', 'ARREST', 'ARRESTS', 'ARROGATE', 'ARROGATES', 'ARTS', 'ASTER',
                'ASTERS', 'ATTAR', 'EAGER', 'EAGERER', 'EAGEREST', 'EARS', 'EATER', 'EATERS', 'EGRESS', 'EGRESSES',
                'EGRET', 'EGRETS', 'ERAS', 'ERASE', 'ERASER', 'ERASERS', 'ERASES', 'ERGO', 'ERGS', 'ERRATA',
                'ERRATAS', 'ERROR', 'ERRORS', 'ERRS', 'ESTER', 'ESTERS', 'GARAGE', 'GARAGES', 'GAROTE', 'GAROTES',
                'GAROTTE', 'GAROTTES', 'GARRET', 'GARRETS', 'GARROTE', 'GARROTES', 'GARROTTE', 'GARROTTES',
                'GARTER', 'GARTERS', 'GEAR', 'GEARS', 'GORE', 'GORES', 'GORGE', 'GORGES', 'GORSE', 'GRATE',
                'GRATER', 'GRATERS', 'GRATES', 'GREASE', 'GREASES', 'GREAT', 'GREATER', 'GREATEST', 'GREATS',
                'GREET', 'GREETS', 'GROG', 'GROSS', 'GROSSER', 'GROSSES', 'GROSSEST', 'GROTTO', 'GROTTOES',
                'GROTTOS', 'OARS', 'OGRE', 'OGRES', 'ORATE', 'ORATES', 'ORATOR', 'ORATORS', 'ORES', 'OTTER',
                'OTTERS', 'RAGA', 'RAGAS', 'RAGE', 'RAGES', 'RAGS', 'RAGTAG', 'RAGTAGS', 'RARE', 'RARER', 'RARES',
                'RAREST', 'RASTER', 'RATE', 'RATES', 'RATS', 'REAR', 'REARS', 'REGATTA', 'REGATTAS', 'REGGAE',
                'REGRESS', 'REGRESSES', 'REGRET', 'REGRETS', 'REORG', 'REORGS', 'RESET', 'RESETS', 'RESORT',
                'RESORTS', 'REST', 'RESTART', 'RESTARTS', 'RESTATE', 'RESTATES', 'RESTORE', 'RESTORER',
                'RESTORERS', 'RESTORES', 'RESTS', 'RETORT', 'RETORTS', 'RETREAT', 'RETREATS', 'RETROGRESS', 'ROAR',
                'ROARS', 'ROAST', 'ROASTER', 'ROASTERS', 'ROASTS', 'ROES', 'ROGER', 'ROGERS', 'ROOST', 'ROOSTER',
                'ROOSTERS', 'ROOSTS', 'ROOT', 'ROOTER', 'ROOTS', 'ROSE', 'ROSEATE', 'ROSES', 'ROSETTE', 'ROSETTES',
                'ROSTER', 'ROSTERS', 'ROSTRA', 'ROTATE', 'ROTATES', 'ROTE', 'ROTOR', 'ROTORS', 'ROTS', 'SAGER',
                'SAREE', 'SAREES', 'SEAR', 'SEARS', 'SEER', 'SEERS', 'SEGREGATE', 'SEGREGATES', 'SERA', 'SERE',
                'SERER', 'SEREST', 'SERGE', 'SETTER', 'SETTERS', 'SOAR', 'SOARS', 'SORE', 'SORER', 'SORES',
                'SOREST', 'SORT', 'SORTA', 'SORTER', 'SORTERS', 'SORTS', 'STAGGER', 'STAGGERS', 'STAR', 'STARE',
                'STARES', 'STARS', 'START', 'STARTER', 'STARTERS', 'STARTS', 'STATER', 'STEER', 'STEERAGE',
                'STEERS', 'STEREO', 'STEREOS', 'STORAGE', 'STORE', 'STORES', 'STRATA', 'STREET', 'STREETS',
                'STRESS', 'STRESSES', 'TARE', 'TARES', 'TARGET', 'TARGETS', 'TARO', 'TAROS', 'TAROT', 'TAROTS',
                'TARS', 'TART', 'TARTAR', 'TARTARS', 'TARTER', 'TARTEST', 'TARTS', 'TASER', 'TASERS', 'TASTER',
                'TASTERS', 'TATTER', 'TATTERS', 'TEAR', 'TEARGAS', 'TEARGASES', 'TEARS', 'TEASER', 'TEASERS',
                'TEETER', 'TEETERS', 'TERROR', 'TERRORS', 'TERSE', 'TERSER', 'TERSEST', 'TESTER', 'TESTERS',
                'TOASTER', 'TOASTERS', 'TORE', 'TORS', 'TORSO', 'TORSOS', 'TORT', 'TORTE', 'TORTES', 'TORTS',
                'TOTTER', 'TOTTERS', 'TREAS', 'TREAT', 'TREATS', 'TREE', 'TREES', 'TRESS', 'TRESSES', 'TROT',
                'TROTS', 'TROTTER', 'TROTTERS', 'TSAR', 'TSARS',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'D', 'I', 'R', 'S', 'T'],
            'words'  => [
                'ADDED', 'ADDER', 'ADDERS', 'ADDRESS', 'ADDRESSED', 'ADDRESSEE', 'ADDRESSEES', 'ADDRESSES',
                'AERATE', 'AERATED', 'AERATES', 'AERIE', 'AERIES', 'AIDE', 'AIDED', 'AIDES', 'AIRED', 'AIRIER',
                'AIRIEST', 'AREA', 'AREAS', 'ARES', 'ARISE', 'ARISES', 'ARREARS', 'ARREST', 'ARRESTED', 'ARRESTS',
                'ARTERIES', 'ARTIER', 'ARTIEST', 'ARTISTE', 'ARTISTES', 'ARTSIER', 'ARTSIEST', 'ASIDE', 'ASIDES',
                'ASTER', 'ASTERS', 'ASTRIDE', 'ATTEST', 'ATTESTED', 'ATTESTS', 'ATTIRE', 'ATTIRED', 'ATTIRES',
                'DADDIES', 'DAIRIES', 'DAISES', 'DAISIES', 'DARE', 'DARED', 'DARES', 'DARTED', 'DATE', 'DATED',
                'DATES', 'DDED', 'DEAD', 'DEADER', 'DEADEST', 'DEAR', 'DEARER', 'DEAREST', 'DEARS', 'DEED',
                'DEEDED', 'DEEDS', 'DEER', 'DEERS', 'DEITIES', 'DERIDE', 'DERIDED', 'DERIDES', 'DESERT',
                'DESERTED', 'DESERTER', 'DESERTERS', 'DESERTS', 'DESIDERATA', 'DESIRE', 'DESIRED', 'DESIRES',
                'DESIST', 'DESISTED', 'DESISTS', 'DESSERT', 'DESSERTS', 'DETER', 'DETERRED', 'DETERS', 'DETEST',
                'DETESTED', 'DETESTS', 'DIARIES', 'DIED', 'DIERESES', 'DIERESIS', 'DIES', 'DIET', 'DIETARIES',
                'DIETED', 'DIETER', 'DIETERS', 'DIETS', 'DIRE', 'DIRER', 'DIREST', 'DIRTIED', 'DIRTIER', 'DIRTIES',
                'DIRTIEST', 'DISASTER', 'DISASTERS', 'DISEASE', 'DISEASED', 'DISEASES', 'DISSED', 'DISSES',
                'DISTASTE', 'DISTASTES', 'DISTRESS', 'DISTRESSED', 'DISTRESSES', 'DITTIES', 'DREAD', 'DREADED',
                'DREADS', 'DREARIER', 'DREARIEST', 'DRESS', 'DRESSED', 'DRESSER', 'DRESSERS', 'DRESSES',
                'DRESSIER', 'DRESSIEST', 'DRIED', 'DRIER', 'DRIERS', 'DRIES', 'DRIEST', 'EARS', 'EASE', 'EASED',
                'EASES', 'EASIER', 'EASIEST', 'EAST', 'EATER', 'EATERIES', 'EATERS', 'EATS', 'EDDIED', 'EDDIES',
                'EDIT', 'EDITED', 'EDITS', 'EERIE', 'EERIER', 'EERIEST', 'EIDER', 'EIDERS', 'ERAS', 'ERASE',
                'ERASED', 'ERASER', 'ERASERS', 'ERASES', 'ERRATA', 'ERRATAS', 'ERRED', 'ERRS', 'ESTATE', 'ESTATES',
                'ESTER', 'ESTERS', 'IDEA', 'IDEAS', 'IDES', 'IRATE', 'IRISES', 'IRRADIATE', 'IRRADIATED',
                'IRRADIATES', 'IRRITATE', 'IRRITATED', 'IRRITATES', 'ITERATE', 'ITERATED', 'ITERATES', 'RADIATE',
                'RADIATED', 'RADIATES', 'RAIDED', 'RAIDER', 'RAIDERS', 'RAISE', 'RAISED', 'RAISES', 'RARE',
                'RARED', 'RARER', 'RARES', 'RAREST', 'RARITIES', 'RASTER', 'RATE', 'RATED', 'RATES', 'RATTED',
                'RATTIER', 'RATTIEST', 'READ', 'READER', 'READERS', 'READIED', 'READIER', 'READIES', 'READIEST',
                'READS', 'REAR', 'REARED', 'REARS', 'REDDER', 'REDDEST', 'REDID', 'REDRESS', 'REDRESSED',
                'REDRESSES', 'REDS', 'REED', 'REEDIER', 'REEDIEST', 'REEDS', 'REIS', 'REITERATE', 'REITERATED',
                'REITERATES', 'REREAD', 'REREADS', 'RESET', 'RESETS', 'RESIDE', 'RESIDED', 'RESIDES', 'RESIST',
                'RESISTED', 'RESISTER', 'RESISTERS', 'RESISTS', 'REST', 'RESTART', 'RESTARTED', 'RESTARTS',
                'RESTATE', 'RESTATED', 'RESTATES', 'RESTED', 'RESTS', 'RETARD', 'RETARDED', 'RETARDS', 'RETIRE',
                'RETIRED', 'RETIREE', 'RETIREES', 'RETIRES', 'RETREAD', 'RETREADED', 'RETREADS', 'RETREAT',
                'RETREATED', 'RETREATS', 'RETRIED', 'RETRIES', 'RIDDED', 'RIDE', 'RIDER', 'RIDERS', 'RIDES',
                'RISE', 'RISER', 'RISERS', 'RISES', 'RITE', 'RITES', 'SADDER', 'SADDEST', 'SADES', 'SAREE',
                'SAREES', 'SATE', 'SATED', 'SATES', 'SATIATE', 'SATIATED', 'SATIATES', 'SATIRE', 'SATIRES', 'SEAR',
                'SEARED', 'SEARS', 'SEAS', 'SEASIDE', 'SEASIDES', 'SEAT', 'SEATED', 'SEATS', 'SEDATE', 'SEDATED',
                'SEDATER', 'SEDATES', 'SEDATEST', 'SEED', 'SEEDED', 'SEEDIER', 'SEEDIEST', 'SEEDS', 'SEER',
                'SEERS', 'SEES', 'SERA', 'SERE', 'SERER', 'SEREST', 'SERIES', 'SERRATED', 'SERRIED', 'SETS',
                'SETTEE', 'SETTEES', 'SETTER', 'SETTERS', 'SIDE', 'SIDED', 'SIDES', 'SIERRA', 'SIERRAS', 'SIESTA',
                'SIESTAS', 'SIRE', 'SIRED', 'SIRES', 'SISES', 'SISSIER', 'SISSIES', 'SISSIEST', 'SISTER',
                'SISTERS', 'SITE', 'SITED', 'SITES', 'SITTER', 'SITTERS', 'STAIDER', 'STAIDEST', 'STARE', 'STARED',
                'STARES', 'STARRED', 'STARRIER', 'STARRIEST', 'STARTED', 'STARTER', 'STARTERS', 'STATE', 'STATED',
                'STATER', 'STATES', 'STATESIDE', 'STEAD', 'STEADIED', 'STEADIER', 'STEADIES', 'STEADIEST',
                'STEADS', 'STEED', 'STEEDS', 'STEER', 'STEERED', 'STEERS', 'STIES', 'STIRRED', 'STIRRER',
                'STIRRERS', 'STREET', 'STREETS', 'STRESS', 'STRESSED', 'STRESSES', 'STRIATED', 'STRIDE', 'STRIDES',
                'TARDIER', 'TARDIEST', 'TARE', 'TARED', 'TARES', 'TARRED', 'TARRIED', 'TARRIER', 'TARRIES',
                'TARRIEST', 'TARTER', 'TARTEST', 'TASER', 'TASERED', 'TASERS', 'TASTE', 'TASTED', 'TASTER',
                'TASTERS', 'TASTES', 'TASTIER', 'TASTIEST', 'TATTED', 'TATTER', 'TATTERED', 'TATTERS', 'TEAR',
                'TEARED', 'TEARIER', 'TEARIEST', 'TEARS', 'TEAS', 'TEASE', 'TEASED', 'TEASER', 'TEASERS', 'TEASES',
                'TEAT', 'TEATS', 'TEED', 'TEES', 'TEETER', 'TEETERED', 'TEETERS', 'TERRARIA', 'TERRIER',
                'TERRIERS', 'TERSE', 'TERSER', 'TERSEST', 'TEST', 'TESTATE', 'TESTATES', 'TESTED', 'TESTER',
                'TESTERS', 'TESTES', 'TESTIER', 'TESTIEST', 'TESTIS', 'TESTS', 'TIDE', 'TIDED', 'TIDES', 'TIDIED',
                'TIDIER', 'TIDIES', 'TIDIEST', 'TIED', 'TIER', 'TIERS', 'TIES', 'TIRADE', 'TIRADES', 'TIRE',
                'TIRED', 'TIREDER', 'TIREDEST', 'TIRES', 'TRADE', 'TRADED', 'TRADER', 'TRADERS', 'TRADES', 'TREAD',
                'TREADS', 'TREAS', 'TREAT', 'TREATED', 'TREATIES', 'TREATISE', 'TREATISES', 'TREATS', 'TREE',
                'TREED', 'TREES', 'TRESS', 'TRESSES', 'TRIED', 'TRIES', 'TRITE', 'TRITER', 'TRITEST',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['L', 'P', 'R', 'S', 'T', 'U'],
            'words'  => [
                'EELS', 'ELLS', 'ELSE', 'ERRS', 'ERUPT', 'ERUPTS', 'ESTER', 'ESTERS', 'LEER', 'LEERS', 'LEES',
                'LEPER', 'LEPERS', 'LEPT', 'LESS', 'LESSEE', 'LESSEES', 'LESSER', 'LEST', 'LETS', 'LETTER',
                'LETTERS', 'LETUP', 'LETUPS', 'LURE', 'LURES', 'LUSTER', 'LUSTRE', 'LUTE', 'LUTES', 'PEEL',
                'PEELS', 'PEEP', 'PEEPER', 'PEEPERS', 'PEEPS', 'PEER', 'PEERLESS', 'PEERS', 'PEES', 'PELLET',
                'PELLETS', 'PELT', 'PELTS', 'PEPPER', 'PEPPERS', 'PEPS', 'PERT', 'PERTER', 'PERTEST', 'PERUSE',
                'PERUSES', 'PEST', 'PESTER', 'PESTERS', 'PESTLE', 'PESTLES', 'PESTS', 'PETER', 'PETERS', 'PETREL',
                'PETRELS', 'PETS', 'PLUSES', 'PLUSSES', 'PREP', 'PREPS', 'PRES', 'PRESET', 'PRESETS', 'PRESS',
                'PRESSES', 'PRESSURE', 'PRESSURES', 'PULLER', 'PULLERS', 'PULLET', 'PULLETS', 'PULSE', 'PULSES',
                'PUPPET', 'PUPPETEER', 'PUPPETEERS', 'PUPPETS', 'PURE', 'PUREE', 'PUREES', 'PURER', 'PUREST',
                'PURPLE', 'PURPLER', 'PURPLES', 'PURPLEST', 'PURSE', 'PURSER', 'PURSERS', 'PURSES', 'PURSUE',
                'PURSUER', 'PURSUERS', 'PURSUES', 'PUSSES', 'PUSTULE', 'PUSTULES', 'PUTTER', 'PUTTERS', 'REEL',
                'REELS', 'REPEL', 'REPELS', 'REPLETE', 'REPLETES', 'REPRESS', 'REPRESSES', 'REPS', 'REPULSE',
                'REPULSES', 'REPUTE', 'REPUTES', 'RESELL', 'RESELLS', 'RESET', 'RESETS', 'RESETTLE', 'RESETTLES',
                'RESP', 'RESPELL', 'RESPELLS', 'RESPELT', 'REST', 'RESTLESS', 'RESTS', 'RESULT', 'RESULTS',
                'RETELL', 'RETELLS', 'REUSE', 'REUSES', 'RUES', 'RULE', 'RULER', 'RULERS', 'RULES', 'RUPEE',
                'RUPEES', 'RUPTURE', 'RUPTURES', 'RUSE', 'RUSES', 'RUSSET', 'RUSSETS', 'RUSTLE', 'RUSTLER',
                'RUSTLERS', 'RUSTLES', 'SEEP', 'SEEPS', 'SEER', 'SEERS', 'SEES', 'SELL', 'SELLER', 'SELLERS',
                'SELLS', 'SEPTET', 'SEPTETS', 'SEPTETTE', 'SEPTETTES', 'SERE', 'SERER', 'SEREST', 'SETS', 'SETTEE',
                'SETTEES', 'SETTER', 'SETTERS', 'SETTLE', 'SETTLER', 'SETTLERS', 'SETTLES', 'SETUP', 'SETUPS',
                'SLEEP', 'SLEEPER', 'SLEEPERS', 'SLEEPLESS', 'SLEEPS', 'SLEET', 'SLEETS', 'SLEPT', 'SLUE', 'SLUES',
                'SPELL', 'SPELLER', 'SPELLERS', 'SPELLS', 'SPELT', 'SPLUTTER', 'SPLUTTERS', 'SPREE', 'SPREES',
                'SPUTTER', 'SPUTTERS', 'STEEL', 'STEELS', 'STEEP', 'STEEPER', 'STEEPEST', 'STEEPLE', 'STEEPLES',
                'STEEPS', 'STEER', 'STEERS', 'STEP', 'STEPPE', 'STEPPES', 'STEPS', 'STREET', 'STREETS', 'STREP',
                'STRESS', 'STRESSES', 'STUTTER', 'STUTTERER', 'STUTTERERS', 'STUTTERS', 'SUES', 'SUET', 'SUPER',
                'SUPERS', 'SUPERUSER', 'SUPERUSERS', 'SUPPER', 'SUPPERS', 'SUPPLE', 'SUPPLER', 'SUPPLEST',
                'SUPPRESS', 'SUPPRESSES', 'SURE', 'SURER', 'SUREST', 'SURPLUSES', 'SUTURE', 'SUTURES', 'TEEPEE',
                'TEEPEES', 'TEES', 'TEETER', 'TEETERS', 'TELL', 'TELLER', 'TELLERS', 'TELLS', 'TEPEE', 'TEPEES',
                'TERSE', 'TERSER', 'TERSEST', 'TEST', 'TESTER', 'TESTERS', 'TESTES', 'TESTS', 'TREE', 'TREELESS',
                'TREES', 'TRESS', 'TRESSES', 'TRESTLE', 'TRESTLES', 'TRUE', 'TRUER', 'TRUES', 'TRUEST', 'TRUSSES',
                'TRUSTEE', 'TRUSTEES', 'TULLE', 'TURRET', 'TURRETS', 'TURTLE', 'TURTLES', 'TUSSLE', 'TUSSLES',
                'UPPER', 'UPPERS', 'UPSET', 'UPSETS', 'USELESS', 'USER', 'USERS', 'USES', 'USURER', 'USURERS',
                'USURPER', 'USURPERS', 'UTERUS', 'UTERUSES', 'UTTER', 'UTTERS',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['A', 'E', 'H', 'I', 'P', 'R'],
            'words'  => [
                'AERIES', 'AIRS', 'AIRSHIP', 'AIRSHIPS', 'APES', 'APHASIA', 'APIARIES', 'APPEARS', 'APPEASE',
                'APPEASER', 'APPEASERS', 'APPEASES', 'APPRAISE', 'APPRAISER', 'APPRAISERS', 'APPRAISES', 'APPRISE',
                'APPRISES', 'APPS', 'APSE', 'APSES', 'AREAS', 'ARES', 'ARIAS', 'ARISE', 'ARISES', 'ARREARS',
                'ASHES', 'ASHIER', 'ASPIRE', 'ASPIRES', 'ASPS', 'EARS', 'EASE', 'EASES', 'EASIER', 'ERAS', 'ERASE',
                'ERASER', 'ERASERS', 'ERASES', 'ERRS', 'ESPIES', 'HAIRS', 'HARES', 'HARPIES', 'HARPS', 'HARRIES',
                'HARSH', 'HARSHER', 'HASH', 'HASHEESH', 'HASHES', 'HASHISH', 'HASP', 'HASPS', 'HEAPS', 'HEARERS',
                'HEARS', 'HEARSE', 'HEARSES', 'HEIRESS', 'HEIRESSES', 'HEIRS', 'HERESIES', 'HERPES', 'HERS',
                'HIES', 'HIPPIES', 'HIPS', 'HIRES', 'HISS', 'HISSES', 'IRIS', 'IRISES', 'PAIRS', 'PAPAS', 'PAPERS',
                'PAPS', 'PARAPHRASE', 'PARES', 'PARIAHS', 'PARISH', 'PARISHES', 'PARRIES', 'PARS', 'PARSE',
                'PARSER', 'PARSES', 'PASHA', 'PASHAS', 'PEARS', 'PEAS', 'PEASE', 'PEEPERS', 'PEEPS', 'PEERS',
                'PEES', 'PEPPERS', 'PEPS', 'PERHAPS', 'PERISH', 'PERISHES', 'PERSPIRE', 'PERSPIRES', 'PHASE',
                'PHASES', 'PHISH', 'PHISHER', 'PHISHERS', 'PHRASE', 'PHRASES', 'PIERS', 'PIES', 'PIPERS', 'PIPES',
                'PIPS', 'PRAIRIES', 'PRAISE', 'PRAISES', 'PREPARES', 'PREPPIES', 'PREPS', 'PRES', 'PRESS',
                'PRESSES', 'PRIES', 'PRISSIER', 'RAISE', 'RAISES', 'RAPIERS', 'RAPPERS', 'RAPS', 'RARES', 'RASH',
                'RASHER', 'RASHERS', 'RASHES', 'RASP', 'RASPIER', 'RASPS', 'REAPERS', 'REAPPEARS', 'REAPPRAISE',
                'REAPS', 'REARS', 'REHASH', 'REHASHES', 'REHEARSE', 'REHEARSES', 'REHIRES', 'REIS', 'REPAIRS',
                'REPHRASE', 'REPHRASES', 'REPRESS', 'REPRESSES', 'REPRISE', 'REPRISES', 'REPS', 'RESP', 'RESPIRE',
                'RESPIRES', 'RHEAS', 'RIPPERS', 'RIPS', 'RISE', 'RISER', 'RISERS', 'RISES', 'SAPPHIRE',
                'SAPPHIRES', 'SAPPIER', 'SAPS', 'SAREE', 'SAREES', 'SARI', 'SARIS', 'SASH', 'SASHES', 'SEAR',
                'SEARS', 'SEAS', 'SEEP', 'SEEPS', 'SEER', 'SEERS', 'SEES', 'SEPIA', 'SEPSIS', 'SERA', 'SERAPH',
                'SERAPHS', 'SERE', 'SERER', 'SERIES', 'SHAH', 'SHAHS', 'SHAPE', 'SHAPES', 'SHARE', 'SHARES',
                'SHARIA', 'SHARIAH', 'SHARP', 'SHARPER', 'SHARPERS', 'SHARPS', 'SHEAR', 'SHEARER', 'SHEARERS',
                'SHEARS', 'SHEEP', 'SHEEPISH', 'SHEER', 'SHEERER', 'SHEERS', 'SHERRIES', 'SHES', 'SHIES', 'SHIP',
                'SHIPPER', 'SHIPPERS', 'SHIPS', 'SHIPSHAPE', 'SHIRE', 'SHIRES', 'SHIRR', 'SHIRRS', 'SIERRA',
                'SIERRAS', 'SIPS', 'SIRE', 'SIRES', 'SIRS', 'SISES', 'SISSIER', 'SISSIES', 'SPAR', 'SPARE',
                'SPARER', 'SPARES', 'SPARS', 'SPARSE', 'SPARSER', 'SPAS', 'SPEAR', 'SPEARS', 'SPHERE', 'SPHERES',
                'SPIES', 'SPIRAEA', 'SPIRAEAS', 'SPIRE', 'SPIREA', 'SPIREAS', 'SPIRES', 'SPREE', 'SPREES',
                'SPRIER',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'C', 'D', 'R', 'S', 'V'],
            'words'  => [
                'ACCEDE', 'ACCEDED', 'ACCEDES', 'ACCESS', 'ACCESSED', 'ACCESSES', 'ACED', 'ACES', 'ACRE', 'ACRES',
                'ADDED', 'ADDER', 'ADDERS', 'ADDRESS', 'ADDRESSED', 'ADDRESSEE', 'ADDRESSEES', 'ADDRESSES',
                'ADVERSE', 'ADVERSER', 'ARCADE', 'ARCADES', 'ARCED', 'AREA', 'AREAS', 'ARES', 'ARREARS', 'AVER',
                'AVERRED', 'AVERS', 'AVERSE', 'CADAVER', 'CADAVERS', 'CADRE', 'CADRES', 'CARDED', 'CARE', 'CARED',
                'CAREER', 'CAREERED', 'CAREERS', 'CARES', 'CARESS', 'CARESSED', 'CARESSES', 'CARVE', 'CARVED',
                'CARVER', 'CARVERS', 'CARVES', 'CASCADE', 'CASCADED', 'CASCADES', 'CASE', 'CASED', 'CASES', 'CAVE',
                'CAVED', 'CAVES', 'CEASE', 'CEASED', 'CEASES', 'CEDAR', 'CEDARS', 'CEDE', 'CEDED', 'CEDES',
                'CRAVE', 'CRAVED', 'CRAVES', 'CREASE', 'CREASED', 'CREASES', 'CREED', 'CREEDS', 'CRESS', 'DARE',
                'DARED', 'DARES', 'DDED', 'DEAD', 'DEADER', 'DEAR', 'DEARER', 'DEARS', 'DEAVES', 'DECADE',
                'DECADES', 'DECEASE', 'DECEASED', 'DECEASES', 'DECREASE', 'DECREASED', 'DECREASES', 'DECREE',
                'DECREED', 'DECREES', 'DEED', 'DEEDED', 'DEEDS', 'DEER', 'DEERS', 'DESERVE', 'DESERVED',
                'DESERVES', 'DREAD', 'DREADED', 'DREADS', 'DRESS', 'DRESSED', 'DRESSER', 'DRESSERS', 'DRESSES',
                'EARS', 'EASE', 'EASED', 'EASES', 'EAVE', 'EAVES', 'ERAS', 'ERASE', 'ERASED', 'ERASER', 'ERASERS',
                'ERASES', 'ERRED', 'ERRS', 'EVADE', 'EVADED', 'EVADES', 'EVER', 'EVES', 'RACE', 'RACED', 'RACER',
                'RACERS', 'RACES', 'RARE', 'RARED', 'RARER', 'RARES', 'RAVE', 'RAVED', 'RAVES', 'READ', 'READER',
                'READERS', 'READS', 'REAR', 'REARED', 'REARS', 'RECD', 'RECEDE', 'RECEDED', 'RECEDES', 'RECESS',
                'RECESSED', 'RECESSES', 'REDDER', 'REDRESS', 'REDRESSED', 'REDRESSES', 'REDS', 'REED', 'REEDS',
                'REEVE', 'REEVED', 'REEVES', 'REREAD', 'REREADS', 'RESERVE', 'RESERVED', 'RESERVES', 'REVERE',
                'REVERED', 'REVERES', 'REVERSE', 'REVERSED', 'REVERSES', 'REVS', 'REVVED', 'SACRED', 'SADDER',
                'SADES', 'SAREE', 'SAREES', 'SAVE', 'SAVED', 'SAVER', 'SAVERS', 'SAVES', 'SCARCE', 'SCARCER',
                'SCARE', 'SCARED', 'SCARES', 'SCARRED', 'SCARVES', 'SEAR', 'SEARED', 'SEARS', 'SEAS', 'SECEDE',
                'SECEDED', 'SECEDES', 'SECS', 'SEED', 'SEEDED', 'SEEDS', 'SEER', 'SEERS', 'SEES', 'SERA', 'SERE',
                'SERER', 'SERVE', 'SERVED', 'SERVER', 'SERVERS', 'SERVES', 'SEVER', 'SEVERE', 'SEVERED', 'SEVERER',
                'SEVERS', 'VASE', 'VASES', 'VEER', 'VEERED', 'VEERS', 'VERSE', 'VERSED', 'VERSES', 'VERVE',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['C', 'D', 'I', 'L', 'P', 'S'],
            'words'  => [
                'CEDE', 'CEDED', 'CEDES', 'CELL', 'CELLI', 'CELLS', 'CLIPPED', 'DDED', 'DECIDE', 'DECIDED',
                'DECIDES', 'DEED', 'DEEDED', 'DEEDS', 'DEEP', 'DEEPS', 'DEICE', 'DEICED', 'DEICES', 'DELI',
                'DELIS', 'DELL', 'DELLS', 'DESPISE', 'DESPISED', 'DESPISES', 'DICE', 'DICED', 'DICES', 'DIDDLE',
                'DIDDLED', 'DIDDLES', 'DIED', 'DIES', 'DIESEL', 'DIESELED', 'DIESELS', 'DILLIES', 'DIPPED',
                'DISCIPLE', 'DISCIPLES', 'DISPEL', 'DISPELLED', 'DISPELS', 'DISSED', 'DISSES', 'ECLIPSE',
                'ECLIPSED', 'ECLIPSES', 'EDDIED', 'EDDIES', 'EELS', 'ELIDE', 'ELIDED', 'ELIDES', 'ELLIPSE',
                'ELLIPSES', 'ELLIPSIS', 'ELLS', 'ELSE', 'EPIC', 'EPICS', 'ESPIED', 'ESPIES', 'ICED', 'ICES',
                'ICICLE', 'ICICLES', 'IDES', 'IDLE', 'IDLED', 'IDLES', 'ISLE', 'ISLES', 'LEES', 'LEIS', 'LESS',
                'LESSEE', 'LESSEES', 'LICE', 'LIDDED', 'LIED', 'LIES', 'LILIES', 'LISLE', 'LISPED', 'PECS',
                'PEDDLE', 'PEDDLED', 'PEDDLES', 'PEED', 'PEEL', 'PEELED', 'PEELS', 'PEEP', 'PEEPED', 'PEEPS',
                'PEES', 'PEPPED', 'PEPS', 'PIDDLE', 'PIDDLED', 'PIDDLES', 'PIECE', 'PIECED', 'PIECES', 'PIED',
                'PIES', 'PILE', 'PILED', 'PILES', 'PILLED', 'PIPE', 'PIPED', 'PIPES', 'PIPPED', 'PLED', 'PLIED',
                'PLIES', 'SECEDE', 'SECEDED', 'SECEDES', 'SECS', 'SEED', 'SEEDED', 'SEEDLESS', 'SEEDS', 'SEEP',
                'SEEPED', 'SEEPS', 'SEES', 'SELL', 'SELLS', 'SEPSIS', 'SIDE', 'SIDED', 'SIDES', 'SIDLE', 'SIDLED',
                'SIDLES', 'SILLIES', 'SIPPED', 'SISES', 'SISSIES', 'SLED', 'SLEDDED', 'SLEDS', 'SLEEP',
                'SLEEPLESS', 'SLEEPS', 'SLICE', 'SLICED', 'SLICES', 'SLIDE', 'SLIDES', 'SLIPPED', 'SPEC',
                'SPECCED', 'SPECIE', 'SPECIES', 'SPECS', 'SPED', 'SPEED', 'SPEEDED', 'SPEEDS', 'SPELL', 'SPELLED',
                'SPELLS', 'SPICE', 'SPICED', 'SPICES', 'SPIED', 'SPIEL', 'SPIELED', 'SPIELS', 'SPIES', 'SPILLED',
                'SPLICE', 'SPLICED', 'SPLICES',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['G', 'I', 'L', 'N', 'R', 'S'],
            'words'  => [
                'EELS', 'EERIE', 'EERIER', 'EERINESS', 'EGGING', 'EGGS', 'EGIS', 'EGRESS', 'EGRESSES', 'ELEGIES',
                'ELLS', 'ELSE', 'ENERGIES', 'ENGINE', 'ENGINEER', 'ENGINEERS', 'ENGINES', 'ENSIGN', 'ENSIGNS',
                'ERGS', 'ERRING', 'ERRS', 'GEEING', 'GEES', 'GEESE', 'GELLING', 'GELS', 'GENE', 'GENES', 'GENESES',
                'GENESIS', 'GENIE', 'GENIES', 'GENII', 'GENRE', 'GENRES', 'GENS', 'GIGGLE', 'GIGGLER', 'GIGGLERS',
                'GIGGLES', 'GIGGLIER', 'GINGER', 'GINSENG', 'GLEE', 'GLEN', 'GLENS', 'GNEISS', 'GREEN', 'GREENER',
                'GREENING', 'GREENNESS', 'GREENS', 'GRILLE', 'GRILLES', 'GRISLIER', 'ILLNESS', 'ILLNESSES',
                'INGRESS', 'INGRESSES', 'INLINE', 'INNER', 'INSIGNE', 'INSIGNES', 'IRISES', 'ISLE', 'ISLES',
                'LEER', 'LEERIER', 'LEERING', 'LEERS', 'LEES', 'LEGGIER', 'LEGGIN', 'LEGGING', 'LEGGINGS',
                'LEGGINS', 'LEGLESS', 'LEGS', 'LEIS', 'LENS', 'LENSES', 'LESS', 'LESSEE', 'LESSEES', 'LESSEN',
                'LESSENING', 'LESSENS', 'LESSER', 'LIEGE', 'LIEGES', 'LIEN', 'LIENS', 'LIES', 'LILIES', 'LINE',
                'LINEN', 'LINENS', 'LINER', 'LINERS', 'LINES', 'LINGER', 'LINGERER', 'LINGERERS', 'LINGERIE',
                'LINGERING', 'LINGERINGS', 'LINGERS', 'LIRE', 'LISLE', 'NEGLIG', 'NEGLIGEE', 'NEGLIGEES',
                'NEGLIGS', 'NINE', 'NINES', 'NINNIES', 'REEL', 'REELING', 'REELS', 'REGRESS', 'REGRESSES',
                'REGRESSING', 'REIGN', 'REIGNING', 'REIGNS', 'REIN', 'REINING', 'REINS', 'REIS', 'RELIES',
                'RENEGE', 'RENEGES', 'RENEGING', 'RESELL', 'RESELLING', 'RESELLS', 'RESIGN', 'RESIGNING',
                'RESIGNS', 'RESIN', 'RESINS', 'RILE', 'RILES', 'RINGER', 'RINGERS', 'RINSE', 'RINSES', 'RISE',
                'RISEN', 'RISER', 'RISERS', 'RISES', 'SEEING', 'SEEINGS', 'SEEN', 'SEER', 'SEERS', 'SEES', 'SELL',
                'SELLER', 'SELLERS', 'SELLING', 'SELLS', 'SENILE', 'SENSE', 'SENSELESS', 'SENSES', 'SENSING',
                'SERE', 'SERENE', 'SERENENESS', 'SERENER', 'SERER', 'SERGE', 'SERIES', 'SIEGE', 'SIEGES', 'SIGNER',
                'SIGNERS', 'SILLIER', 'SILLIES', 'SILLINESS', 'SINE', 'SINGE', 'SINGEING', 'SINGER', 'SINGERS',
                'SINGES', 'SINGLE', 'SINGLES', 'SINNER', 'SINNERS', 'SIRE', 'SIREN', 'SIRENS', 'SIRES', 'SISES',
                'SISSIER', 'SISSIES', 'SLIER', 'SNEER', 'SNEERING', 'SNEERS',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['A', 'E', 'F', 'L', 'T', 'U'],
            'words'  => [
                'ALAS', 'ALES', 'ALTS', 'ASTUTE', 'ASTUTEST', 'ATLAS', 'ATLASES', 'ATTEST', 'ATTESTS', 'EASE',
                'EASEL', 'EASELS', 'EASES', 'EAST', 'EATS', 'EELS', 'ELATES', 'ELLS', 'ELSE', 'ESTATE', 'ESTATES',
                'FALLS', 'FALSE', 'FALSEST', 'FAST', 'FASTEST', 'FASTS', 'FATES', 'FATS', 'FATTEST', 'FAULTLESS',
                'FAULTS', 'FEAST', 'FEASTS', 'FEATS', 'FEELS', 'FEES', 'FELLEST', 'FELLS', 'FELTS', 'FEST',
                'FESTAL', 'FESTS', 'FETUS', 'FETUSES', 'FLATS', 'FLATTEST', 'FLEAS', 'FLEES', 'FLEETEST', 'FLEETS',
                'FLUES', 'FLUFFS', 'FLUTES', 'FUELS', 'FULLEST', 'FULLS', 'FUSE', 'FUSES', 'FUSS', 'FUSSES',
                'LASE', 'LASES', 'LAST', 'LASTS', 'LATEST', 'LATS', 'LATTES', 'LEAFLESS', 'LEAFLETS', 'LEAFS',
                'LEAS', 'LEASE', 'LEASES', 'LEAST', 'LEES', 'LEFTEST', 'LEFTS', 'LESS', 'LESSEE', 'LESSEES',
                'LEST', 'LETS', 'LUAUS', 'LULLS', 'LUST', 'LUSTFUL', 'LUSTS', 'LUTES', 'SAFE', 'SAFES', 'SAFEST',
                'SALE', 'SALES', 'SALSA', 'SALSAS', 'SALT', 'SALTEST', 'SALTS', 'SALUTE', 'SALUTES', 'SATE',
                'SATES', 'SEAL', 'SEALS', 'SEAS', 'SEAT', 'SEATS', 'SEES', 'SELF', 'SELFLESS', 'SELL', 'SELLS',
                'SETS', 'SETTEE', 'SETTEES', 'SETTLE', 'SETTLES', 'SLAT', 'SLATE', 'SLATES', 'SLATS', 'SLEET',
                'SLEETS', 'SLUE', 'SLUES', 'STAFF', 'STAFFS', 'STALE', 'STALES', 'STALEST', 'STALL', 'STALLS',
                'STAT', 'STATE', 'STATELESS', 'STATES', 'STATS', 'STATUE', 'STATUES', 'STATUETTE', 'STATUETTES',
                'STATUS', 'STATUSES', 'STATUTE', 'STATUTES', 'STEAL', 'STEALS', 'STEEL', 'STEELS', 'STUFF',
                'STUFFS', 'SUES', 'SUET', 'SUFFUSE', 'SUFFUSES', 'SULFATE', 'SULFATES', 'TALES', 'TALLEST',
                'TASTE', 'TASTEFUL', 'TASTELESS', 'TASTES', 'TATS', 'TATTLES', 'TAUTEST', 'TEALS', 'TEAS', 'TEASE',
                'TEASEL', 'TEASELS', 'TEASES', 'TEATS', 'TEES', 'TELLS', 'TELLTALES', 'TEST', 'TESTATE',
                'TESTATES', 'TESTES', 'TESTS', 'TUFTS', 'TUSSLE', 'TUSSLES', 'TUTUS', 'ULULATES', 'USEFUL',
                'USELESS', 'USES', 'USUAL',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'D', 'H', 'L', 'P', 'S'],
            'words'  => [
                'ADDED', 'ADDLE', 'ADDLED', 'ADDLES', 'AHEAD', 'ALES', 'APED', 'APES', 'APPALLED', 'APPEAL',
                'APPEALED', 'APPEALS', 'APPEASE', 'APPEASED', 'APPEASES', 'APPLE', 'APPLES', 'APSE', 'APSES',
                'ASHED', 'ASHES', 'ASLEEP', 'DALE', 'DALES', 'DAPPLE', 'DAPPLED', 'DAPPLES', 'DASHED', 'DASHES',
                'DDED', 'DEAD', 'DEAL', 'DEALS', 'DEED', 'DEEDED', 'DEEDS', 'DEEP', 'DEEPS', 'DELL', 'DELLS',
                'EASE', 'EASED', 'EASEL', 'EASELS', 'EASES', 'EELS', 'ELAPSE', 'ELAPSED', 'ELAPSES', 'ELLS',
                'ELSE', 'HALE', 'HALED', 'HALES', 'HAPLESS', 'HASHED', 'HASHEESH', 'HASHES', 'HEAD', 'HEADED',
                'HEADLESS', 'HEADS', 'HEAL', 'HEALED', 'HEALS', 'HEAP', 'HEAPED', 'HEAPS', 'HEED', 'HEEDED',
                'HEEDLESS', 'HEEDS', 'HEEL', 'HEELED', 'HEELS', 'HELD', 'HELP', 'HELPED', 'HELPLESS', 'HELPS',
                'LADE', 'LADED', 'LADES', 'LADLE', 'LADLED', 'LADLES', 'LAPEL', 'LAPELS', 'LAPPED', 'LAPSE',
                'LAPSED', 'LAPSES', 'LASE', 'LASED', 'LASES', 'LASHED', 'LASHES', 'LEAD', 'LEADED', 'LEADS',
                'LEAP', 'LEAPED', 'LEAPS', 'LEAS', 'LEASE', 'LEASED', 'LEASES', 'LEASH', 'LEASHED', 'LEASHES',
                'LEES', 'LESS', 'LESSEE', 'LESSEES', 'PADDED', 'PADDLE', 'PADDLED', 'PADDLES', 'PALE', 'PALED',
                'PALES', 'PALLED', 'PEAL', 'PEALED', 'PEALS', 'PEAS', 'PEASE', 'PEDAL', 'PEDALED', 'PEDALLED',
                'PEDALS', 'PEDDLE', 'PEDDLED', 'PEDDLES', 'PEED', 'PEEL', 'PEELED', 'PEELS', 'PEEP', 'PEEPED',
                'PEEPS', 'PEES', 'PEPPED', 'PEPS', 'PHASE', 'PHASED', 'PHASES', 'PLEA', 'PLEAD', 'PLEADED',
                'PLEADS', 'PLEAS', 'PLEASE', 'PLEASED', 'PLEASES', 'PLED', 'SADDLE', 'SADDLED', 'SADDLES', 'SADES',
                'SALE', 'SALES', 'SAPPED', 'SASHES', 'SEAL', 'SEALED', 'SEALS', 'SEAS', 'SEED', 'SEEDED',
                'SEEDLESS', 'SEEDS', 'SEEP', 'SEEPED', 'SEEPS', 'SEES', 'SELL', 'SELLS', 'SEPAL', 'SEPALS',
                'SHADE', 'SHADED', 'SHADES', 'SHALE', 'SHAPE', 'SHAPED', 'SHAPELESS', 'SHAPES', 'SHED', 'SHEDS',
                'SHEEP', 'SHES', 'SHLEP', 'SHLEPP', 'SHLEPPED', 'SHLEPPS', 'SHLEPS', 'SLAPPED', 'SLASHED',
                'SLASHES', 'SLED', 'SLEDDED', 'SLEDS', 'SLEEP', 'SLEEPLESS', 'SLEEPS', 'SPADE', 'SPADED', 'SPADES',
                'SPED', 'SPEED', 'SPEEDED', 'SPEEDS', 'SPELL', 'SPELLED', 'SPELLS', 'SPLASHED', 'SPLASHES',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['D', 'G', 'I', 'N', 'R', 'W'],
            'words'  => [
                'DDED', 'DEED', 'DEEDED', 'DEEDING', 'DEER', 'DEGREE', 'DEIGN', 'DEIGNED', 'DEIGNING', 'DENIED',
                'DENIER', 'DERIDE', 'DERIDED', 'DERIDING', 'DERRINGER', 'DEWIER', 'DIED', 'DIGGER', 'DINE',
                'DINED', 'DINER', 'DINGED', 'DINGIER', 'DINNED', 'DINNER', 'DINNERED', 'DINNERING', 'DIRE',
                'DIRER', 'DIRGE', 'DREDGE', 'DREDGED', 'DREDGER', 'DREDGING', 'DREW', 'DRIED', 'DRIER', 'EDDIED',
                'EDGE', 'EDGED', 'EDGER', 'EDGIER', 'EDGING', 'EERIE', 'EERIER', 'EGGED', 'EGGING', 'EIDER',
                'ENDED', 'ENDING', 'ENGENDER', 'ENGENDERED', 'ENGINE', 'ENGINEER', 'ENGINEERED', 'ERRED', 'ERRING',
                'EWER', 'GEED', 'GEEING', 'GENDER', 'GENE', 'GENIE', 'GENII', 'GENRE', 'GIDDIER', 'GIGGED',
                'GINGER', 'GINNED', 'GIRDED', 'GIRDER', 'GREED', 'GREEDIER', 'GREEN', 'GREENED', 'GREENER',
                'GREENING', 'GREW', 'GRINDER', 'GRINNED', 'INDEED', 'INNER', 'NEED', 'NEEDED', 'NEEDIER',
                'NEEDING', 'NERD', 'NERDIER', 'NEWER', 'NINE', 'REDDEN', 'REDDENED', 'REDDENING', 'REDDER',
                'REDID', 'REDREW', 'REED', 'REEDIER', 'REIGN', 'REIGNED', 'REIGNING', 'REIN', 'REINDEER', 'REINED',
                'REINING', 'REND', 'RENDER', 'RENDERED', 'RENDERING', 'RENDING', 'RENEGE', 'RENEGED', 'RENEGING',
                'RENEW', 'RENEWED', 'RENEWING', 'REWIND', 'REWINDING', 'REWIRE', 'REWIRED', 'REWIRING', 'RIDDED',
                'RIDDEN', 'RIDE', 'RIDER', 'RIDGE', 'RIDGED', 'RIGGED', 'RINGED', 'RINGER', 'WEDDED', 'WEDDER',
                'WEDDING', 'WEDGE', 'WEDGED', 'WEDGIE', 'WEDGING', 'WEED', 'WEEDED', 'WEEDER', 'WEEDIER',
                'WEEDING', 'WEEING', 'WEENIE', 'WEER', 'WEIR', 'WEIRD', 'WEIRDER', 'WEND', 'WENDED', 'WENDING',
                'WERE', 'WIDE', 'WIDEN', 'WIDENED', 'WIDENING', 'WIDER', 'WIENER', 'WIGGED', 'WINDED', 'WINDIER',
                'WINE', 'WINED', 'WINGED', 'WINGER', 'WINNER', 'WIRE', 'WIRED', 'WIRIER', 'WREN', 'WRIER',
                'WRINGER',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'D', 'G', 'R', 'T', 'V'],
            'words'  => [
                'ADAGE', 'ADDED', 'ADDER', 'ADVERT', 'ADVERTED', 'AERATE', 'AERATED', 'AGATE', 'AGAVE', 'AGED',
                'AGGRAVATE', 'AGGRAVATED', 'AGGREGATE', 'AGGREGATED', 'AGREE', 'AGREED', 'AREA', 'AVER', 'AVERAGE',
                'AVERAGED', 'AVERRED', 'AVERT', 'AVERTED', 'DAGGER', 'DARE', 'DARED', 'DARTED', 'DATE', 'DATED',
                'DDED', 'DEAD', 'DEADER', 'DEAR', 'DEARER', 'DEED', 'DEEDED', 'DEER', 'DEGRADE', 'DEGRADED',
                'DEGREE', 'DETER', 'DETERRED', 'DRAGGED', 'DREAD', 'DREADED', 'DREDGE', 'DREDGED', 'DREDGER',
                'EAGER', 'EAGERER', 'EATER', 'EAVE', 'EDGE', 'EDGED', 'EDGER', 'EGGED', 'EGRET', 'ERRATA', 'ERRED',
                'EVADE', 'EVADED', 'EVER', 'GADDED', 'GADGET', 'GAGE', 'GAGED', 'GAGGED', 'GARAGE', 'GARAGED',
                'GARRET', 'GARTER', 'GATE', 'GATED', 'GAVE', 'GEAR', 'GEARED', 'GEED', 'GRADE', 'GRADED', 'GRADER',
                'GRATE', 'GRATED', 'GRATER', 'GRAVE', 'GRAVED', 'GRAVER', 'GREAT', 'GREATER', 'GREED', 'GREET',
                'GREETED', 'RAGE', 'RAGED', 'RAGGED', 'RAGGEDER', 'RARE', 'RARED', 'RARER', 'RATE', 'RATED',
                'RATTED', 'RAVAGE', 'RAVAGED', 'RAVE', 'RAVED', 'READ', 'READER', 'REAR', 'REARED', 'REDDER',
                'REED', 'REEVE', 'REEVED', 'REGARD', 'REGARDED', 'REGATTA', 'REGGAE', 'REGRET', 'REGRETTED',
                'REREAD', 'RETARD', 'RETARDED', 'RETREAD', 'RETREADED', 'RETREAT', 'RETREATED', 'REVERE',
                'REVERED', 'REVERT', 'REVERTED', 'REVVED', 'TAGGED', 'TARE', 'TARED', 'TARGET', 'TARGETED',
                'TARRED', 'TARTER', 'TATTED', 'TATTER', 'TATTERED', 'TEAR', 'TEARED', 'TEAT', 'TEED', 'TEETER',
                'TEETERED', 'TRADE', 'TRADED', 'TRADER', 'TREAD', 'TREAT', 'TREATED', 'TREE', 'TREED', 'VATTED',
                'VEER', 'VEERED', 'VEGETATE', 'VEGETATED', 'VERGE', 'VERGED', 'VERVE', 'VETTED',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['C', 'F', 'N', 'O', 'R', 'S'],
            'words'  => [
                'CENSER', 'CENSERS', 'CENSOR', 'CENSORS', 'COERCE', 'COERCES', 'COFFEE', 'COFFEES', 'COFFER',
                'COFFERS', 'CONCERN', 'CONCERNS', 'CONE', 'CONES', 'CONFER', 'CONFERENCE', 'CONFERRER', 'CONFERS',
                'CONFESS', 'CONFESSES', 'CONFESSOR', 'CONFESSORS', 'CONSES', 'CORE', 'CORES', 'CORNER', 'CORNERS',
                'CORONER', 'CORONERS', 'CRESS', 'CRONE', 'CRONES', 'CROONER', 'CROONERS', 'CROSSER', 'CROSSES',
                'CROSSNESS', 'ENCORE', 'ENCORES', 'ENFORCE', 'ENFORCER', 'ENFORCERS', 'ENFORCES', 'ENSCONCE',
                'ENSCONCES', 'EONS', 'ERROR', 'ERRORS', 'ERRS', 'ESSENCE', 'ESSENCES', 'FECES', 'FEES', 'FENCE',
                'FENCER', 'FENCERS', 'FENCES', 'FENS', 'FERN', 'FERNS', 'FOES', 'FORCE', 'FORCES', 'FORE',
                'FORENOON', 'FORENOONS', 'FORES', 'FORESEE', 'FORESEEN', 'FORESEES', 'FREE', 'FREER', 'FREES',
                'FRESCO', 'FRESCOES', 'FRESCOS', 'NEOCON', 'NEOCONS', 'NEON', 'NOES', 'NONCE', 'NONE', 'NONSENSE',
                'NOOSE', 'NOOSES', 'NOSE', 'NOSES', 'OFFENSE', 'OFFENSES', 'OFFER', 'OFFERS', 'ONCE', 'ONENESS',
                'ONES', 'ORES', 'RECESS', 'RECESSES', 'REEF', 'REEFER', 'REEFERS', 'REEFS', 'REENFORCE',
                'REENFORCES', 'REFER', 'REFEREE', 'REFEREES', 'REFERENCE', 'REFERENCES', 'REFERS', 'REFS', 'ROES',
                'ROOFER', 'ROOFERS', 'ROSE', 'ROSES', 'SCENE', 'SCENES', 'SCONCE', 'SCONCES', 'SCONE', 'SCONES',
                'SCORE', 'SCORER', 'SCORERS', 'SCORES', 'SCREEN', 'SCREENS', 'SECS', 'SEEN', 'SEER', 'SEERS',
                'SEES', 'SENSE', 'SENSES', 'SENSOR', 'SENSORS', 'SERE', 'SERENE', 'SERENENESS', 'SERENER', 'SERER',
                'SERF', 'SERFS', 'SNEER', 'SNEERS', 'SNORE', 'SNORER', 'SNORERS', 'SNORES', 'SOCCER', 'SOONER',
                'SORCERER', 'SORCERERS', 'SORCERESS', 'SORE', 'SORENESS', 'SORER', 'SORES',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['C', 'I', 'L', 'N', 'P', 'S'],
            'words'  => [
                'CELL', 'CELLI', 'CELLS', 'ECLIPSE', 'ECLIPSES', 'EELS', 'ELLIPSE', 'ELLIPSES', 'ELLIPSIS', 'ELLS',
                'ELSE', 'EPIC', 'EPICS', 'ESPIES', 'ESSENCE', 'ESSENCES', 'ICES', 'ICICLE', 'ICICLES', 'ICINESS',
                'ILLNESS', 'ILLNESSES', 'INCENSE', 'INCENSES', 'INCISE', 'INCISES', 'INCLINE', 'INCLINES',
                'INLINE', 'ISLE', 'ISLES', 'LEES', 'LEIS', 'LENS', 'LENSES', 'LESS', 'LESSEE', 'LESSEES', 'LESSEN',
                'LESSENS', 'LICE', 'LICENCE', 'LICENCES', 'LICENSE', 'LICENSEE', 'LICENSEES', 'LICENSES', 'LIEN',
                'LIENS', 'LIES', 'LILIES', 'LINE', 'LINEN', 'LINENS', 'LINES', 'LISLE', 'NICE', 'NICENESS',
                'NIECE', 'NIECES', 'NINE', 'NINEPIN', 'NINEPINS', 'NINES', 'NINNIES', 'NIPPLE', 'NIPPLES', 'PECS',
                'PEEL', 'PEELS', 'PEEP', 'PEEPS', 'PEES', 'PENCE', 'PENCIL', 'PENCILS', 'PENES', 'PENICILLIN',
                'PENILE', 'PENIS', 'PENISES', 'PENNIES', 'PENNILESS', 'PENS', 'PEPS', 'PEPSIN', 'PIECE', 'PIECES',
                'PIES', 'PILE', 'PILES', 'PINE', 'PINES', 'PIPE', 'PIPELINE', 'PIPELINES', 'PIPES', 'PLIES',
                'SCENE', 'SCENES', 'SCENIC', 'SCIENCE', 'SCIENCES', 'SECS', 'SEEN', 'SEEP', 'SEEPS', 'SEES',
                'SELL', 'SELLS', 'SENILE', 'SENSE', 'SENSELESS', 'SENSES', 'SEPSIS', 'SILENCE', 'SILENCES',
                'SILLIES', 'SILLINESS', 'SINCE', 'SINE', 'SISES', 'SISSIES', 'SLEEP', 'SLEEPINESS', 'SLEEPLESS',
                'SLEEPS', 'SLICE', 'SLICES', 'SNIPE', 'SNIPES', 'SPEC', 'SPECIE', 'SPECIES', 'SPECS', 'SPELL',
                'SPELLS', 'SPICE', 'SPICES', 'SPICINESS', 'SPIEL', 'SPIELS', 'SPIES', 'SPINE', 'SPINELESS',
                'SPINES', 'SPLEEN', 'SPLEENS', 'SPLICE', 'SPLICES', 'SPLINE', 'SPLINES',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'D', 'F', 'O', 'R', 'W'],
            'words'  => [
                'ADDED', 'ADDER', 'ADORE', 'ADORED', 'ADWARE', 'AFFORDED', 'AREA', 'AWARDED', 'AWARE', 'AWED',
                'DARE', 'DARED', 'DDED', 'DEAD', 'DEADER', 'DEADWOOD', 'DEAF', 'DEAFER', 'DEAR', 'DEARER', 'DEED',
                'DEEDED', 'DEER', 'DEFER', 'DEFERRED', 'DODDER', 'DODDERED', 'DOER', 'DOFFED', 'DRAWER', 'DREAD',
                'DREADED', 'DREW', 'DWARFED', 'ERODE', 'ERODED', 'ERRED', 'ERROR', 'EWER', 'FADE', 'FADED', 'FARE',
                'FARED', 'FARROWED', 'FEAR', 'FEARED', 'FEDORA', 'FEED', 'FEEDER', 'FEWER', 'FODDER', 'FORDED',
                'FORE', 'FOREWORD', 'FORWARDED', 'FORWARDER', 'FREE', 'FREED', 'FREER', 'FREEWARE', 'OARED',
                'ODDER', 'OFFED', 'OFFER', 'OFFERED', 'ORDER', 'ORDERED', 'OWED', 'RARE', 'RARED', 'RARER',
                'RAWER', 'READ', 'READER', 'REAR', 'REARED', 'REARWARD', 'REDDER', 'REDO', 'REDRAW', 'REDREW',
                'REDWOOD', 'REED', 'REEF', 'REEFED', 'REEFER', 'REFER', 'REFEREE', 'REFEREED', 'REFERRED',
                'REFFED', 'REORDER', 'REORDERED', 'REREAD', 'REWARD', 'REWARDED', 'REWORD', 'REWORDED', 'ROARED',
                'RODE', 'RODEO', 'ROOFED', 'ROOFER', 'ROWED', 'ROWER', 'WADDED', 'WADE', 'WADED', 'WADER', 'WAFER',
                'WARDED', 'WARDER', 'WARE', 'WARFARE', 'WARRED', 'WEAR', 'WEARER', 'WEDDED', 'WEDDER', 'WEED',
                'WEEDED', 'WEEDER', 'WEER', 'WERE', 'WOODED', 'WOOED', 'WOOER', 'WOOFED', 'WOOFER', 'WORDED',
                'WORE', 'WOWED',
            ],
        ],
        [
            'center' => 'N',
            'outer'  => ['A', 'E', 'G', 'I', 'P', 'T'],
            'words'  => [
                'AGAIN', 'AGEING', 'AGENT', 'AGING', 'AGITATING', 'ANGINA', 'ANTE', 'ANTEING', 'ANTENNA',
                'ANTENNAE', 'ANTI', 'ANTIGEN', 'APING', 'ATTAIN', 'ATTAINING', 'EATEN', 'EATING', 'EGGING',
                'ENGAGE', 'ENGAGING', 'ENGINE', 'ENTENTE', 'GAGGING', 'GAGING', 'GAIN', 'GAINING', 'GANG',
                'GANGING', 'GANNET', 'GAPING', 'GATING', 'GEEING', 'GENE', 'GENIE', 'GENII', 'GENT', 'GENTIAN',
                'GETTING', 'GIANT', 'GIGGING', 'GINNING', 'GNAT', 'IGNITE', 'IGNITING', 'INANE', 'INAPT', 'INEPT',
                'INITIATE', 'INITIATING', 'INNATE', 'INNING', 'INPATIENT', 'INTENT', 'NAGGING', 'NAPE', 'NAPPING',
                'NEAT', 'NEGATE', 'NEGATING', 'NETTING', 'NINE', 'NINEPIN', 'NINETEEN', 'NIPPING', 'NITE', 'PAEAN',
                'PAGAN', 'PAGEANT', 'PAGINATE', 'PAGINATING', 'PAGING', 'PAIN', 'PAINING', 'PAINT', 'PAINTING',
                'PANE', 'PANG', 'PANNING', 'PANT', 'PANTIE', 'PANTING', 'PATENT', 'PATENTING', 'PATIENT', 'PATINA',
                'PATINAE', 'PATINE', 'PATTING', 'PEEING', 'PEEPING', 'PEGGING', 'PENITENT', 'PENNANT', 'PENNING',
                'PENT', 'PEPPING', 'PETTING', 'PIEING', 'PIGGING', 'PIGPEN', 'PIING', 'PINE', 'PING', 'PINGING',
                'PINING', 'PINNATE', 'PINNING', 'PINT', 'PIPING', 'PIPPIN', 'PIPPING', 'PITTING', 'TAGGING',
                'TAINT', 'TAINTING', 'TANG', 'TANGENT', 'TANNIN', 'TANNING', 'TAPING', 'TAPPING', 'TATTING',
                'TEEING', 'TEEN', 'TEENAGE', 'TENANT', 'TENANTING', 'TENET', 'TENPIN', 'TENT', 'TENTING', 'TIEING',
                'TINE', 'TING', 'TINGE', 'TINGEING', 'TINGING', 'TINNING', 'TINT', 'TINTING', 'TIPPING',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['C', 'D', 'I', 'K', 'L', 'S'],
            'words'  => [
                'CEDE', 'CEDED', 'CEDES', 'CELL', 'CELLI', 'CELLS', 'CLICKED', 'DDED', 'DECIDE', 'DECIDED',
                'DECIDES', 'DECK', 'DECKED', 'DECKLE', 'DECKLES', 'DECKS', 'DEED', 'DEEDED', 'DEEDS', 'DEICE',
                'DEICED', 'DEICES', 'DELI', 'DELIS', 'DELL', 'DELLS', 'DESK', 'DESKS', 'DICE', 'DICED', 'DICES',
                'DIDDLE', 'DIDDLED', 'DIDDLES', 'DIED', 'DIES', 'DIESEL', 'DIESELED', 'DIESELS', 'DIKE', 'DIKED',
                'DIKES', 'DILLIES', 'DISLIKE', 'DISLIKED', 'DISLIKES', 'DISSED', 'DISSES', 'EDDIED', 'EDDIES',
                'EELS', 'EKED', 'EKES', 'ELIDE', 'ELIDED', 'ELIDES', 'ELKS', 'ELLS', 'ELSE', 'ICED', 'ICES',
                'ICICLE', 'ICICLES', 'IDES', 'IDLE', 'IDLED', 'IDLES', 'ISLE', 'ISLES', 'KEEL', 'KEELED', 'KEELS',
                'KICKED', 'KIDDED', 'KIDDIE', 'KIDDIES', 'KISSED', 'KISSES', 'LEEK', 'LEEKS', 'LEES', 'LEIS',
                'LESS', 'LESSEE', 'LESSEES', 'LICE', 'LICKED', 'LIDDED', 'LIED', 'LIES', 'LIKE', 'LIKED', 'LIKES',
                'LILIES', 'LISLE', 'SECEDE', 'SECEDED', 'SECEDES', 'SECS', 'SEED', 'SEEDED', 'SEEDLESS', 'SEEDS',
                'SEEK', 'SEEKS', 'SEES', 'SELL', 'SELLS', 'SICKED', 'SICKLE', 'SICKLES', 'SIDE', 'SIDED',
                'SIDEKICK', 'SIDEKICKS', 'SIDES', 'SIDLE', 'SIDLED', 'SIDLES', 'SILLIES', 'SISES', 'SISSIES',
                'SKIDDED', 'SKIED', 'SKIES', 'SLED', 'SLEDDED', 'SLEDS', 'SLEEK', 'SLEEKED', 'SLEEKS', 'SLICE',
                'SLICED', 'SLICES', 'SLICKED', 'SLIDE', 'SLIDES',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['B', 'D', 'I', 'L', 'M', 'R'],
            'words'  => [
                'BEDDED', 'BEDDER', 'BEER', 'BELIE', 'BELIED', 'BELL', 'BELLE', 'BELLED', 'BELLIED', 'BERIBERI',
                'BERM', 'BERRIED', 'BIBLE', 'BIDDER', 'BIDE', 'BIDED', 'BIER', 'BILE', 'BILLED', 'BIRDED',
                'BIRDIE', 'BIRDIED', 'BLED', 'BLEED', 'BLEEDER', 'BRED', 'BREED', 'BREEDER', 'BRIBE', 'BRIBED',
                'BRIDE', 'BRIDLE', 'BRIDLED', 'BRIER', 'BRIMMED', 'DDED', 'DEED', 'DEEDED', 'DEEM', 'DEEMED',
                'DEER', 'DELI', 'DELL', 'DERIDE', 'DERIDED', 'DIBBLE', 'DIBBLED', 'DIDDLE', 'DIDDLED', 'DIED',
                'DIME', 'DIMER', 'DIMMED', 'DIMMER', 'DIRE', 'DIRER', 'DRIBBLE', 'DRIBBLED', 'DRIBBLER', 'DRIED',
                'DRIER', 'DRILLED', 'EBBED', 'EDDIED', 'EDIBLE', 'EERIE', 'EERIER', 'EIDER', 'ELDER', 'ELIDE',
                'ELIDED', 'EMBED', 'EMBEDDED', 'EMBER', 'EMBLEM', 'EMIR', 'ERRED', 'IDLE', 'IDLED', 'IDLER',
                'IMBED', 'IMBEDDED', 'IMBIBE', 'IMBIBED', 'LEER', 'LEERED', 'LEERIER', 'LEMME', 'LIBEL', 'LIBELED',
                'LIBELER', 'LIBELLED', 'LIBELLER', 'LIDDED', 'LIED', 'LIMBER', 'LIMBERED', 'LIME', 'LIMED',
                'LIMIER', 'LIRE', 'MEDDLE', 'MEDDLED', 'MEDDLER', 'MELD', 'MELDED', 'MEMBER', 'MEME', 'MERE',
                'MERRIER', 'MIDDLE', 'MILDER', 'MILE', 'MILER', 'MILLED', 'MILLER', 'MIME', 'MIMED', 'MIRE',
                'MIRED', 'REBEL', 'REBELLED', 'REDDER', 'REDEEM', 'REDEEMED', 'REDEEMER', 'REDID', 'REED',
                'REEDIER', 'REEL', 'REELED', 'RELIED', 'REMEDIED', 'REMEMBER', 'REMEMBERED', 'RIBBED', 'RIDDED',
                'RIDDLE', 'RIDDLED', 'RIDE', 'RIDER', 'RILE', 'RILED', 'RIME', 'RIMED', 'RIMMED',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'C', 'D', 'M', 'N', 'O'],
            'words'  => [
                'ACCEDE', 'ACCEDED', 'ACED', 'ACME', 'ACNE', 'ADDED', 'ADDEND', 'ADDENDA', 'ADMEN', 'AEON', 'AMEN',
                'AMEND', 'AMENDED', 'ANEMONE', 'ANODE', 'CADENCE', 'CAME', 'CAMEO', 'CANE', 'CANED', 'CANNED',
                'CANNONADE', 'CANNONADED', 'CANNONED', 'CANOE', 'CANOED', 'CEDE', 'CEDED', 'COCOONED', 'CODDED',
                'CODE', 'CODED', 'COED', 'COME', 'COMMANDED', 'COMMENCE', 'COMMENCED', 'COMMEND', 'COMMENDED',
                'COMMODE', 'CONCEDE', 'CONCEDED', 'CONDEMN', 'CONDEMNED', 'CONDONE', 'CONDONED', 'CONE', 'CONNED',
                'COOED', 'DAEMON', 'DAME', 'DAMMED', 'DANCE', 'DANCED', 'DDED', 'DEACON', 'DEAD', 'DEADEN',
                'DEADENED', 'DEAN', 'DECADE', 'DECADENCE', 'DECODE', 'DECODED', 'DEED', 'DEEDED', 'DEEM', 'DEEMED',
                'DEMAND', 'DEMANDED', 'DEMEAN', 'DEMEANED', 'DEMO', 'DEMOED', 'DEMON', 'DOME', 'DOMED', 'DONE',
                'DONNED', 'DOOMED', 'EDAMAME', 'EDEMA', 'EMCEE', 'EMCEED', 'EMEND', 'EMENDED', 'ENCODE', 'ENCODED',
                'ENDED', 'ENEMA', 'MACE', 'MACED', 'MADAME', 'MADDEN', 'MADDENED', 'MADE', 'MADMEN', 'MANE',
                'MANNED', 'MEAD', 'MEAN', 'MECCA', 'MEME', 'MEMO', 'MENACE', 'MENACED', 'MEND', 'MENDED', 'MOANED',
                'MODDED', 'MODE', 'MODEM', 'MOOED', 'MOONED', 'NAME', 'NAMED', 'NEED', 'NEEDED', 'NEOCON', 'NEON',
                'NODDED', 'NODE', 'NONCE', 'NONE', 'OCEAN', 'OMEN', 'ONCE',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'D', 'H', 'I', 'O', 'R'],
            'words'  => [
                'ADDED', 'ADDER', 'ADHERE', 'ADHERED', 'ADORE', 'ADORED', 'AERIE', 'AHEAD', 'AIDE', 'AIDED',
                'AIRED', 'AIRHEAD', 'AIRIER', 'AREA', 'DARE', 'DARED', 'DDED', 'DEAD', 'DEADER', 'DEAR', 'DEARER',
                'DEED', 'DEEDED', 'DEER', 'DERIDE', 'DERIDED', 'DIARRHEA', 'DIARRHOEA', 'DIED', 'DIEHARD', 'DIODE',
                'DIRE', 'DIRER', 'DODDER', 'DODDERED', 'DOER', 'DREAD', 'DREADED', 'DREARIER', 'DRIED', 'DRIER',
                'EDDIED', 'EERIE', 'EERIER', 'EIDER', 'ERODE', 'ERODED', 'ERRED', 'ERROR', 'HAIRED', 'HAIRIER',
                'HARDER', 'HARDHEADED', 'HARDIER', 'HARE', 'HARED', 'HARRIED', 'HEAD', 'HEADED', 'HEADER',
                'HEADIER', 'HEAR', 'HEARD', 'HEARER', 'HEED', 'HEEDED', 'HEIR', 'HERD', 'HERDED', 'HERDER', 'HERE',
                'HERO', 'HIDE', 'HIDED', 'HIED', 'HIRE', 'HIRED', 'HOARDED', 'HOARDER', 'HOARIER', 'HOED',
                'HOODED', 'HOODIE', 'HOODOOED', 'HORDE', 'HORDED', 'IDEA', 'OARED', 'ODDER', 'ORDER', 'ORDERED',
                'RADIOED', 'RAIDED', 'RAIDER', 'RARE', 'RARED', 'RARER', 'READ', 'READER', 'READIED', 'READIER',
                'REAR', 'REARED', 'REDDER', 'REDHEAD', 'REDHEADED', 'REDID', 'REDO', 'REED', 'REEDIER', 'REHI',
                'REHIRE', 'REHIRED', 'REORDER', 'REORDERED', 'REREAD', 'RHEA', 'RIDDED', 'RIDE', 'RIDER', 'ROARED',
                'RODE', 'RODEO',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['E', 'G', 'H', 'I', 'T', 'W'],
            'words'  => [
                'EGGS', 'EGIS', 'EIGHTHS', 'EIGHTIES', 'EIGHTIETHS', 'EIGHTS', 'ESTHETE', 'ESTHETES', 'EWES',
                'GEES', 'GEESE', 'GETS', 'GIGS', 'GIST', 'HEIGHTS', 'HEIST', 'HEISTS', 'HEWS', 'HIES', 'HIGHEST',
                'HIGHS', 'HISS', 'HISSES', 'HITS', 'SEES', 'SEETHE', 'SEETHES', 'SETS', 'SETTEE', 'SETTEES',
                'SEWS', 'SHEET', 'SHEETS', 'SHES', 'SHIES', 'SHIT', 'SHITS', 'SHITTIEST', 'SIEGE', 'SIEGES',
                'SIGH', 'SIGHS', 'SIGHT', 'SIGHTS', 'SISES', 'SISSIES', 'SISSIEST', 'SITE', 'SITES', 'SITS',
                'STEW', 'STEWS', 'STIES', 'SWEET', 'SWEETEST', 'SWEETIE', 'SWEETIES', 'SWEETISH', 'SWEETS', 'SWIG',
                'SWIGS', 'SWISH', 'SWISHES', 'SWISHEST', 'TEES', 'TEETHES', 'TEST', 'TESTES', 'TESTIEST', 'TESTIS',
                'TESTS', 'THEES', 'THEIST', 'THEISTS', 'THESE', 'THESES', 'THESIS', 'THIGHS', 'THIS', 'TIES',
                'TIGHTEST', 'TIGHTS', 'TWEETS', 'TWIGGIEST', 'TWIGS', 'TWIST', 'TWISTS', 'TWITS', 'WEES', 'WEEST',
                'WEIGHS', 'WEIGHTIEST', 'WEIGHTS', 'WEST', 'WETS', 'WETTEST', 'WHETS', 'WHIST', 'WHITES',
                'WHITEST', 'WHITISH', 'WHITS', 'WIGHTS', 'WIGS', 'WISE', 'WISES', 'WISEST', 'WISH', 'WISHES',
                'WIST', 'WITS', 'WITTIEST',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['F', 'I', 'N', 'R', 'T', 'U'],
            'words'  => [
                'EERIE', 'EERIER', 'EFFETE', 'ENNUI', 'ENTENTE', 'ENTER', 'ENTIRE', 'ENURE', 'FEET', 'FEINT',
                'FERN', 'FERRET', 'FETTER', 'FIEF', 'FIERIER', 'FIFE', 'FIFTEEN', 'FINE', 'FINER', 'FINITE',
                'FIRE', 'FITTER', 'FREE', 'FREER', 'FRET', 'FRIER', 'FRITTER', 'FRUITIER', 'FUNNER', 'FUNNIER',
                'FURNITURE', 'FURRIER', 'FUTURE', 'IFFIER', 'INERT', 'INFER', 'INFINITE', 'INNER', 'INTENT',
                'INTER', 'INTERFERE', 'INTERN', 'INTERNE', 'INTERNEE', 'INTERNET', 'INURE', 'NETTER', 'NEUTER',
                'NIFTIER', 'NINE', 'NINETEEN', 'NITE', 'NITER', 'NURTURE', 'NUTRIENT', 'NUTTIER', 'REEF', 'REEFER',
                'REENTER', 'REFER', 'REFEREE', 'REFERENT', 'REFINE', 'REFINER', 'REFIT', 'REFUTE', 'REIN',
                'RENNET', 'RENT', 'RENTER', 'RERUN', 'RETINUE', 'RETIRE', 'RETIREE', 'RETURN', 'RETURNEE',
                'REUNITE', 'RIFE', 'RIFER', 'RITE', 'RUNE', 'RUNNER', 'RUNNIER', 'TEEN', 'TEENIER', 'TEETER',
                'TENET', 'TENT', 'TENURE', 'TERN', 'TERRIER', 'TIER', 'TINE', 'TINIER', 'TINNIER', 'TIRE', 'TREE',
                'TRITE', 'TRITER', 'TRUE', 'TRUER', 'TUNE', 'TUNER', 'TUREEN', 'TURNER', 'TURRET', 'UNFETTER',
                'UNITE', 'UNTIE', 'UNTRUE', 'UNTRUER', 'URINE', 'UTERI', 'UTERINE', 'UTTER',
            ],
        ],
        [
            'center' => 'A',
            'outer'  => ['D', 'I', 'L', 'M', 'N', 'S'],
            'words'  => [
                'ADDS', 'ADMAN', 'ADMIN', 'ADMINS', 'AIDS', 'AILS', 'AIMS', 'ALAS', 'ALIAS', 'ALMS', 'AMID',
                'AMISS', 'ANAL', 'ANIMAL', 'ANIMALS', 'ANIMISM', 'ANNALS', 'DADS', 'DAIS', 'DAMS', 'DIAL', 'DIALS',
                'DISDAIN', 'DISDAINS', 'DISMAL', 'DISMISSAL', 'DISMISSALS', 'IMAM', 'IMAMS', 'INLAID', 'INLAND',
                'ISLAND', 'ISLANDS', 'LADS', 'LAID', 'LAIN', 'LAMA', 'LAMAS', 'LAMS', 'LAND', 'LANDS', 'LANDSLID',
                'LLAMA', 'LLAMAS', 'MADAM', 'MADAMS', 'MADMAN', 'MADS', 'MAID', 'MAIDS', 'MAIL', 'MAILMAN',
                'MAILS', 'MAIM', 'MAIMS', 'MAIN', 'MAINLAND', 'MAINLANDS', 'MAINS', 'MAINSAIL', 'MAINSAILS',
                'MALL', 'MALLS', 'MAMA', 'MAMAS', 'MAMMA', 'MAMMAL', 'MAMMALIAN', 'MAMMALIANS', 'MAMMALS',
                'MAMMAS', 'MANIA', 'MANIAS', 'MANNA', 'MANS', 'MIASMA', 'MIASMAS', 'MIDLAND', 'MIDLANDS', 'MINIMA',
                'MINIMAL', 'MINIMALISM', 'MISLAID', 'MISSAL', 'MISSALS', 'NAIAD', 'NAIADS', 'NAIL', 'NAILS',
                'NASAL', 'NASALS', 'SADISM', 'SAID', 'SAIL', 'SAILS', 'SALAAM', 'SALAAMS', 'SALAD', 'SALADS',
                'SALAMI', 'SALAMIS', 'SALSA', 'SALSAS', 'SAND', 'SANDAL', 'SANDALS', 'SANDMAN', 'SANDS', 'SANS',
                'SIMIAN', 'SIMIANS', 'SISAL', 'SLAIN', 'SLAM', 'SLAMS', 'SMALL', 'SMALLS', 'SNAIL', 'SNAILS',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'F', 'R', 'S', 'W', 'Y'],
            'words'  => [
                'AERY', 'AREA', 'AREAS', 'ARES', 'ARREARS', 'AWARE', 'AWES', 'AYES', 'EARS', 'EASE', 'EASES',
                'EASY', 'EERY', 'ERAS', 'ERASE', 'ERASER', 'ERASERS', 'ERASES', 'ERRS', 'ESSAY', 'ESSAYS', 'EWER',
                'EWERS', 'EWES', 'EYES', 'FARE', 'FARES', 'FEAR', 'FEARS', 'FEES', 'FERRY', 'FEWER', 'FREE',
                'FREER', 'FREES', 'FREEWARE', 'FREEWAY', 'FREEWAYS', 'FRYER', 'FRYERS', 'RARE', 'RAREFY', 'RARER',
                'RARES', 'RAWER', 'REAR', 'REARS', 'REEF', 'REEFER', 'REEFERS', 'REEFS', 'REFER', 'REFEREE',
                'REFEREES', 'REFERS', 'REFS', 'SAFE', 'SAFER', 'SAFES', 'SAREE', 'SAREES', 'SAWYER', 'SAWYERS',
                'SEAFARER', 'SEAFARERS', 'SEAR', 'SEARS', 'SEAS', 'SEAWAY', 'SEAWAYS', 'SEER', 'SEERS', 'SEES',
                'SEESAW', 'SEESAWS', 'SERA', 'SERE', 'SERER', 'SERF', 'SERFS', 'SEWER', 'SEWERS', 'SEWS', 'SWEAR',
                'SWEARER', 'SWEARERS', 'SWEARS', 'WAFER', 'WAFERS', 'WARE', 'WARES', 'WARFARE', 'WAYFARER',
                'WAYFARERS', 'WEAR', 'WEARER', 'WEARERS', 'WEARS', 'WEARY', 'WEER', 'WEES', 'WERE', 'WRYER',
                'YEAR', 'YEARS', 'YEAS', 'YESES', 'YEWS',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['D', 'O', 'P', 'R', 'W', 'Y'],
            'words'  => [
                'DDED', 'DEED', 'DEEDED', 'DEEP', 'DEEPER', 'DEER', 'DEWDROP', 'DEWY', 'DODDER', 'DODDERED',
                'DOER', 'DOPE', 'DOPED', 'DOPEY', 'DREW', 'DROOPED', 'DROPPED', 'DROPPER', 'DRYER', 'DYED', 'DYER',
                'EDDY', 'EERY', 'ERODE', 'ERODED', 'ERRED', 'ERROR', 'EWER', 'EYED', 'ODDER', 'ORDER', 'ORDERED',
                'OWED', 'PEED', 'PEEP', 'PEEPED', 'PEEPER', 'PEER', 'PEERED', 'PEEWEE', 'PEPPED', 'PEPPER',
                'PEPPERED', 'PEPPERY', 'PEPPY', 'PEWEE', 'PODDED', 'POOPED', 'POORER', 'POPE', 'POPPED', 'PORE',
                'PORED', 'POWDER', 'POWDERED', 'POWDERY', 'POWER', 'POWERED', 'POWWOWED', 'PREP', 'PREPPED',
                'PREPPY', 'PREY', 'PREYED', 'PRODDED', 'PROPER', 'PROPERER', 'PROPPED', 'PYRE', 'REDDER', 'REDO',
                'REDREW', 'REDWOOD', 'REED', 'REEDY', 'REORDER', 'REORDERED', 'REWORD', 'REWORDED', 'RODE',
                'RODEO', 'ROPE', 'ROPED', 'ROWED', 'ROWER', 'WEDDED', 'WEDDER', 'WEED', 'WEEDED', 'WEEDER',
                'WEEDY', 'WEEP', 'WEEPER', 'WEEPY', 'WEER', 'WERE', 'WOODED', 'WOOED', 'WOOER', 'WORDED', 'WORE',
                'WOWED', 'WRYER', 'YORE',
            ],
        ],
        [
            'center' => 'R',
            'outer'  => ['A', 'E', 'G', 'I', 'M', 'P'],
            'words'  => [
                'AERIE', 'AGAR', 'AGREE', 'AIRIER', 'AMEER', 'AMIR', 'AMPERAGE', 'AMPERE', 'APPEAR', 'AREA',
                'ARIA', 'EAGER', 'EAGERER', 'EERIE', 'EERIER', 'EMERGE', 'EMIR', 'EMPIRE', 'EPIGRAM', 'GAMER',
                'GAMIER', 'GARAGE', 'GEAR', 'GERM', 'GRAM', 'GRAMMAR', 'GREP', 'GRIM', 'GRIME', 'GRIMIER',
                'GRIMMER', 'GRIP', 'GRIPE', 'GRIPPE', 'IMPAIR', 'MAPPER', 'MARE', 'MARIA', 'MARRIAGE', 'MEAGER',
                'MERE', 'MERGE', 'MERGER', 'MERRIER', 'MIRAGE', 'MIRE', 'PAGER', 'PAIR', 'PAMPER', 'PAPER', 'PARE',
                'PEAR', 'PEEPER', 'PEER', 'PEERAGE', 'PEPPER', 'PEPPIER', 'PERIGEE', 'PERM', 'PIER', 'PIGGIER',
                'PIPER', 'PRAIRIE', 'PRAM', 'PREMIER', 'PREMIERE', 'PREP', 'PREPARE', 'PREPPIE', 'PREPPIER',
                'PRIG', 'PRIM', 'PRIME', 'PRIMER', 'PRIMMER', 'PRIMP', 'RAGA', 'RAGE', 'RAMP', 'RAMPAGE', 'RAPIER',
                'RAPPER', 'RARE', 'RARER', 'REAM', 'REAMER', 'REAP', 'REAPER', 'REAPPEAR', 'REAR', 'REARM',
                'REEMERGE', 'REGGAE', 'REGIME', 'REMARRIAGE', 'REPAIR', 'RIME', 'RIPE', 'RIPER', 'RIPPER',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['I', 'L', 'N', 'O', 'T', 'Y'],
            'words'  => [
                'ILLS', 'INNS', 'INSIST', 'INSISTS', 'INSTIL', 'INSTILL', 'INSTILLS', 'INSTILS', 'IONS', 'LILTS',
                'LINTS', 'LIONS', 'LIST', 'LISTS', 'LOINS', 'LOLLS', 'LOONS', 'LOOTS', 'LOSS', 'LOST', 'LOTIONS',
                'LOTS', 'NITS', 'NOISILY', 'NOISY', 'NOSY', 'NOTIONS', 'NYLONS', 'OILS', 'ONIONS', 'SILL', 'SILLS',
                'SILLY', 'SILO', 'SILOS', 'SILT', 'SILTS', 'SINS', 'SISSY', 'SITS', 'SLILY', 'SLIT', 'SLITS',
                'SLOT', 'SLOTS', 'SLYLY', 'SNIT', 'SNITS', 'SNOOT', 'SNOOTS', 'SNOOTY', 'SNOT', 'SNOTS', 'SNOTTY',
                'SOIL', 'SOILS', 'SOLI', 'SOLO', 'SOLOIST', 'SOLOISTS', 'SOLOS', 'SOLS', 'SONNY', 'SONS', 'SOON',
                'SOOT', 'SOOTY', 'SOTS', 'STILL', 'STILLS', 'STILT', 'STILTS', 'STINT', 'STINTS', 'STONILY',
                'STONY', 'STOOL', 'STOOLS', 'STYLI', 'STYLIST', 'STYLISTS', 'TILLS', 'TILTS', 'TINS', 'TINTS',
                'TOILS', 'TOLLS', 'TONS', 'TONSIL', 'TONSILS', 'TOOLS', 'TOOTS', 'TOSS', 'TOST', 'TOTS', 'TOYS',
                'TTYS',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['D', 'K', 'O', 'R', 'V', 'W'],
            'words'  => [
                'DDED', 'DEED', 'DEEDED', 'DEER', 'DODDER', 'DODDERED', 'DOER', 'DOVE', 'DREW', 'DROVE', 'DROVER',
                'EKED', 'ERODE', 'ERODED', 'ERRED', 'ERROR', 'EVER', 'EVOKE', 'EVOKED', 'EWER', 'ODDER', 'ORDER',
                'ORDERED', 'OVER', 'OVERDO', 'OVERDREW', 'OVERRODE', 'OVERWORK', 'OVERWORKED', 'OWED', 'REDDER',
                'REDO', 'REDREW', 'REDWOOD', 'REED', 'REEK', 'REEKED', 'REEVE', 'REEVED', 'REORDER', 'REORDERED',
                'REVERE', 'REVERED', 'REVOKE', 'REVOKED', 'REVVED', 'REWORD', 'REWORDED', 'REWORK', 'REWORKED',
                'RODE', 'RODEO', 'ROOKED', 'ROVE', 'ROVED', 'ROVER', 'ROWED', 'ROWER', 'VEER', 'VEERED', 'VERVE',
                'VOODOOED', 'VOWED', 'WEDDED', 'WEDDER', 'WEED', 'WEEDED', 'WEEDER', 'WEEK', 'WEER', 'WERE',
                'WOKE', 'WOODED', 'WOOED', 'WOOER', 'WORDED', 'WORE', 'WORKED', 'WORKER', 'WORKWEEK', 'WOVE',
                'WOWED',
            ],
        ],
        [
            'center' => 'L',
            'outer'  => ['C', 'I', 'O', 'S', 'T', 'Y'],
            'words'  => [
                'CLIT', 'CLITS', 'CLOT', 'CLOTS', 'CLOY', 'CLOYS', 'COIL', 'COILS', 'COLIC', 'COLITIS', 'COLOSSI',
                'COLS', 'COLT', 'COLTS', 'COOL', 'COOLLY', 'COOLS', 'COSTLY', 'COYLY', 'CYCLIC', 'CYCLIST',
                'CYCLISTS', 'ICILY', 'ILLICIT', 'ILLICITLY', 'ILLS', 'IOCTL', 'LICIT', 'LILT', 'LILTS', 'LILY',
                'LIST', 'LISTS', 'LOCI', 'LOCO', 'LOLL', 'LOLLS', 'LOOT', 'LOOTS', 'LOSS', 'LOST', 'LOTS', 'LOTTO',
                'OILS', 'OILY', 'SCOLIOSIS', 'SILICOSIS', 'SILL', 'SILLS', 'SILLY', 'SILO', 'SILOS', 'SILT',
                'SILTS', 'SLILY', 'SLIT', 'SLITS', 'SLOT', 'SLOTS', 'SLYLY', 'SOIL', 'SOILS', 'SOLI', 'SOLICIT',
                'SOLICITS', 'SOLO', 'SOLOIST', 'SOLOISTS', 'SOLOS', 'SOLS', 'STILL', 'STILLS', 'STILT', 'STILTS',
                'STOOL', 'STOOLS', 'STYLI', 'STYLIST', 'STYLISTIC', 'STYLISTS', 'SYSTOLIC', 'TILL', 'TILLS',
                'TILT', 'TILTS', 'TOIL', 'TOILS', 'TOLL', 'TOLLS', 'TOOL', 'TOOLS',
            ],
        ],
        [
            'center' => 'N',
            'outer'  => ['A', 'G', 'I', 'L', 'P', 'U'],
            'words'  => [
                'AGAIN', 'AGING', 'AILING', 'ALIGN', 'ALIGNING', 'ALINING', 'ANAL', 'ANGINA', 'ANGLING', 'ANNUAL',
                'ANNUL', 'ANNULLING', 'APING', 'APPALLING', 'GAGGING', 'GAGING', 'GAIN', 'GAINING', 'GALLING',
                'GANG', 'GANGING', 'GANGLIA', 'GANGLING', 'GAPING', 'GAUGING', 'GIGGING', 'GIGGLING', 'GINNING',
                'GLUING', 'GULLING', 'GULPING', 'IGUANA', 'INNING', 'LAGGING', 'LAIN', 'LAPPING', 'LINGUAL',
                'LINING', 'LUGGING', 'LULLING', 'LUNG', 'LUNGING', 'LUPIN', 'NAGGING', 'NAIL', 'NAILING',
                'NAPPING', 'NIPPING', 'NULL', 'PAGAN', 'PAGING', 'PAIN', 'PAINING', 'PALING', 'PALLING', 'PANG',
                'PANNING', 'PIGGING', 'PIING', 'PILING', 'PILLAGING', 'PILLING', 'PING', 'PINGING', 'PINING',
                'PINNING', 'PINUP', 'PIPING', 'PIPPIN', 'PIPPING', 'PLAGUING', 'PLAIN', 'PLAN', 'PLANING',
                'PLANNING', 'PLUGGING', 'PLUGIN', 'PLUNGING', 'PULLING', 'PULPING', 'PUNNING', 'PUPPING', 'ULNA',
                'UNPIN', 'UNPINNING', 'UNPLUG', 'UNPLUGGING', 'UPPING',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['C', 'D', 'I', 'L', 'P', 'U'],
            'words'  => [
                'CEDE', 'CEDED', 'CELL', 'CELLI', 'CLIPPED', 'CLUE', 'CLUED', 'CUDDLE', 'CUDDLED', 'CUED',
                'CULLED', 'CUPPED', 'DDED', 'DECIDE', 'DECIDED', 'DEDUCE', 'DEDUCED', 'DEED', 'DEEDED', 'DEEP',
                'DEICE', 'DEICED', 'DELI', 'DELL', 'DELUDE', 'DELUDED', 'DEUCE', 'DICE', 'DICED', 'DIDDLE',
                'DIDDLED', 'DIED', 'DIPPED', 'DUDE', 'DUDED', 'DUEL', 'DUELED', 'DUELLED', 'DULLED', 'DUPE',
                'DUPED', 'EDDIED', 'ELIDE', 'ELIDED', 'ELUDE', 'ELUDED', 'EPIC', 'ICED', 'ICICLE', 'IDLE', 'IDLED',
                'LICE', 'LIDDED', 'LIED', 'LIEU', 'LULLED', 'PEDDLE', 'PEDDLED', 'PEED', 'PEEL', 'PEELED', 'PEEP',
                'PEEPED', 'PELLUCID', 'PEPPED', 'PIDDLE', 'PIDDLED', 'PIECE', 'PIECED', 'PIED', 'PILE', 'PILED',
                'PILEUP', 'PILLED', 'PIPE', 'PIPED', 'PIPPED', 'PLED', 'PLIED', 'PUDDLE', 'PUDDLED', 'PULLED',
                'PULPED', 'PUPPED', 'UPPED',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['A', 'D', 'G', 'S', 'W', 'Y'],
            'words'  => [
                'ADAGE', 'ADAGES', 'ADDED', 'AGED', 'AGES', 'AWED', 'AWES', 'AYES', 'DDED', 'DEAD', 'DEED',
                'DEEDED', 'DEEDS', 'DEWY', 'DYED', 'DYES', 'EASE', 'EASED', 'EASES', 'EASY', 'EDDY', 'EDGE',
                'EDGED', 'EDGES', 'EDGEWAYS', 'EDGY', 'EGGED', 'EGGS', 'ESSAY', 'ESSAYED', 'ESSAYS', 'EWES',
                'EYED', 'EYES', 'GADDED', 'GAGE', 'GAGED', 'GAGES', 'GAGGED', 'GASES', 'GEED', 'GEEGAW', 'GEEGAWS',
                'GEES', 'GEESE', 'GEWGAW', 'GEWGAWS', 'SADES', 'SAGE', 'SAGES', 'SAGGED', 'SAWED', 'SEAS',
                'SEAWAY', 'SEAWAYS', 'SEAWEED', 'SEDGE', 'SEED', 'SEEDED', 'SEEDS', 'SEEDY', 'SEES', 'SEESAW',
                'SEESAWED', 'SEESAWS', 'SEWAGE', 'SEWED', 'SEWS', 'SWAGGED', 'SWAYED', 'WADDED', 'WADE', 'WADED',
                'WADES', 'WAGE', 'WAGED', 'WAGES', 'WAGGED', 'WEDDED', 'WEDGE', 'WEDGED', 'WEDGES', 'WEDS', 'WEED',
                'WEEDED', 'WEEDS', 'WEEDY', 'WEES', 'YAWED', 'YEAS', 'YESES', 'YESSED', 'YEWS',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['A', 'H', 'M', 'N', 'T', 'U'],
            'words'  => [
                'ANTS', 'ANUS', 'ASTHMA', 'AUNTS', 'AUTUMNS', 'HAMS', 'HASH', 'HATS', 'HAUNTS', 'HUMANS', 'HUMMUS',
                'HUMS', 'HUMUS', 'HUNTS', 'HUNTSMAN', 'HUSH', 'HUTS', 'MAHATMAS', 'MAMAS', 'MAMMAS', 'MANHUNTS',
                'MANS', 'MASH', 'MAST', 'MASTS', 'MATS', 'MATTS', 'MUSH', 'MUSS', 'MUST', 'MUSTS', 'MUTANTS',
                'MUTTS', 'MUUMUUS', 'NUNS', 'NUTS', 'SANS', 'SASH', 'SAUNA', 'SAUNAS', 'SHAH', 'SHAHS', 'SHAM',
                'SHAMAN', 'SHAMANS', 'SHAMS', 'SHAT', 'SHUN', 'SHUNS', 'SHUNT', 'SHUNTS', 'SHUSH', 'SHUT', 'SHUTS',
                'SMASH', 'SMUT', 'SMUTS', 'STASH', 'STAT', 'STATS', 'STATUS', 'STUN', 'STUNS', 'STUNT', 'STUNTS',
                'SUMS', 'SUNS', 'SUNTAN', 'SUNTANS', 'TAMS', 'TANS', 'TATS', 'TAUNTS', 'THUS', 'TUNAS', 'TUNS',
                'TUSH', 'TUTUS', 'UNMANS',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['C', 'I', 'M', 'R', 'T', 'U'],
            'words'  => [
                'CITE', 'CRIER', 'CRIME', 'CRITTER', 'CRUET', 'CRUMMIER', 'CURE', 'CURER', 'CURIE', 'CURTER',
                'CUTE', 'CUTER', 'CUTTER', 'ECRU', 'EERIE', 'EERIER', 'EMCEE', 'EMETIC', 'EMIR', 'EMIT', 'ERECT',
                'EUTECTIC', 'ICIER', 'IMMURE', 'ITEM', 'MEET', 'MEME', 'MERCURIC', 'MERE', 'MERIT', 'MERRIER',
                'METE', 'METER', 'METRIC', 'MICE', 'MIME', 'MIMETIC', 'MIRE', 'MITE', 'MITER', 'MUMMER', 'MUTE',
                'MUTER', 'MUTTER', 'RECITE', 'RECRUIT', 'RECRUITER', 'RECTUM', 'RECUR', 'REMIT', 'RETIRE',
                'RETIREE', 'RICE', 'RIME', 'RITE', 'RUMMER', 'TEEM', 'TEETER', 'TERM', 'TERMITE', 'TERRIER',
                'TIER', 'TIME', 'TIMER', 'TIRE', 'TREE', 'TRICE', 'TRIMMER', 'TRITE', 'TRITER', 'TRUCE', 'TRUE',
                'TRUER', 'TURMERIC', 'TURRET', 'UTERI', 'UTTER',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['L', 'O', 'R', 'T', 'V', 'Y'],
            'words'  => [
                'EERY', 'ERROR', 'EVER', 'EVERY', 'EVOLVE', 'EYELET', 'LEER', 'LEERY', 'LETTER', 'LEVEE', 'LEVEL',
                'LEVELER', 'LEVER', 'LEVY', 'LOOTER', 'LORE', 'LOTTERY', 'LOVE', 'LOVELY', 'LOVER', 'LYRE', 'OLEO',
                'OTTER', 'OVER', 'OVERLY', 'OVERT', 'OVERTLY', 'REEL', 'REEVE', 'RELY', 'RETELL', 'RETOOL',
                'RETORT', 'RETRY', 'REVEL', 'REVELER', 'REVELLER', 'REVELRY', 'REVERE', 'REVERT', 'REVERY',
                'REVOLT', 'REVOLVE', 'REVOLVER', 'ROLE', 'ROLLER', 'ROOTER', 'ROTE', 'ROVE', 'ROVER', 'TEETER',
                'TELL', 'TELLER', 'TERROR', 'TERRY', 'TORE', 'TORTE', 'TOTE', 'TOTTER', 'TREE', 'TROLLEY',
                'TROTTER', 'VEER', 'VELVET', 'VELVETY', 'VERVE', 'VERY', 'VETO', 'VOLE', 'VOLLEY', 'VOTE', 'VOTER',
                'YELL', 'YORE',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['A', 'D', 'M', 'N', 'T', 'U'],
            'words'  => [
                'ADDS', 'ANTS', 'ANUS', 'AUNTS', 'AUTUMNS', 'DADS', 'DAMS', 'DAUNTS', 'DUDS', 'DUNS', 'DUST',
                'DUSTMAN', 'DUSTS', 'MADAMS', 'MADS', 'MAMAS', 'MAMMAS', 'MANS', 'MAST', 'MASTS', 'MATS', 'MATTS',
                'MUSS', 'MUST', 'MUSTS', 'MUTANTS', 'MUTTS', 'MUUMUUS', 'NUNS', 'NUTS', 'SAND', 'SANDMAN', 'SANDS',
                'SANS', 'SAUNA', 'SAUNAS', 'SMUT', 'SMUTS', 'STAND', 'STANDS', 'STAT', 'STATS', 'STATUS', 'STUD',
                'STUDS', 'STUN', 'STUNS', 'STUNT', 'STUNTS', 'SUDS', 'SUMS', 'SUNS', 'SUNTAN', 'SUNTANS', 'TADS',
                'TAMS', 'TANS', 'TATS', 'TAUNTS', 'TUNAS', 'TUNS', 'TUTUS', 'UNMANS',
            ],
        ],
        [
            'center' => 'E',
            'outer'  => ['J', 'K', 'N', 'R', 'S', 'U'],
            'words'  => [
                'EKES', 'ENSUE', 'ENSUES', 'ENSURE', 'ENSURES', 'ENURE', 'ENURES', 'ERRS', 'JEER', 'JEERS',
                'JEJUNE', 'JERK', 'JERKS', 'JUNKER', 'JUNKERS', 'KEEN', 'KEENER', 'KEENNESS', 'KEENS', 'KENS',
                'KNEE', 'KNEES', 'NUKE', 'NUKES', 'NURSE', 'NURSES', 'REEK', 'REEKS', 'RERUN', 'RERUNS', 'REUSE',
                'REUSES', 'RUES', 'RUNE', 'RUNES', 'RUNNER', 'RUNNERS', 'RUSE', 'RUSES', 'SEEK', 'SEEKER',
                'SEEKERS', 'SEEKS', 'SEEN', 'SEER', 'SEERS', 'SEES', 'SENSE', 'SENSES', 'SERE', 'SERENE',
                'SERENENESS', 'SERENER', 'SERER', 'SNEER', 'SNEERS', 'SUES', 'SUNKEN', 'SURE', 'SURENESS', 'SURER',
                'UNSEEN', 'UNSURE', 'USER', 'USERS', 'USES', 'USURER', 'USURERS',
            ],
        ],
        [
            'center' => 'I',
            'outer'  => ['F', 'L', 'S', 'T', 'U', 'W'],
            'words'  => [
                'FILL', 'FILLS', 'FIST', 'FISTFUL', 'FISTFULS', 'FISTS', 'FITFUL', 'FITS', 'FLIT', 'FLITS',
                'FLUTIST', 'FLUTISTS', 'FULFIL', 'FULFILL', 'FULFILLS', 'FULFILS', 'ILLS', 'ILLUS', 'LIFT',
                'LIFTS', 'LILT', 'LILTS', 'LIST', 'LISTS', 'SIFT', 'SIFTS', 'SILL', 'SILLS', 'SILT', 'SILTS',
                'SITS', 'SITU', 'SLIT', 'SLITS', 'STIFF', 'STIFFS', 'STILL', 'STILLS', 'STILT', 'STILTS', 'SUIT',
                'SUITS', 'SWIFT', 'SWIFTS', 'SWILL', 'SWILLS', 'TIFF', 'TIFFS', 'TILL', 'TILLS', 'TILT', 'TILTS',
                'TWILL', 'TWIST', 'TWISTS', 'TWIT', 'TWITS', 'WILFUL', 'WILL', 'WILLFUL', 'WILLS', 'WILT', 'WILTS',
                'WIST', 'WISTFUL', 'WITS',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['E', 'H', 'I', 'N', 'P', 'X'],
            'words'  => [
                'ESPIES', 'EXES', 'EXPENSE', 'EXPENSES', 'HENS', 'HEXES', 'HIES', 'HIPPIES', 'HIPS', 'HISS',
                'HISSES', 'INNS', 'NINEPINS', 'NINES', 'NINNIES', 'NIPS', 'NIXES', 'PEEPS', 'PEES', 'PENES',
                'PENIS', 'PENISES', 'PENNIES', 'PENS', 'PEPS', 'PEPSIN', 'PHISH', 'PIES', 'PINES', 'PINS', 'PIPES',
                'PIPPINS', 'PIPS', 'PIXIES', 'SEEN', 'SEEP', 'SEEPS', 'SEES', 'SENSE', 'SENSES', 'SEPSIS', 'SHEEN',
                'SHEEP', 'SHEEPISH', 'SHES', 'SHIES', 'SHIN', 'SHINE', 'SHINES', 'SHININESS', 'SHINNIES', 'SHINS',
                'SHIP', 'SHIPS', 'SINE', 'SINS', 'SIPS', 'SISES', 'SISSIES', 'SIXES', 'SNIP', 'SNIPE', 'SNIPES',
                'SNIPS', 'SPHINX', 'SPHINXES', 'SPIES', 'SPIN', 'SPINE', 'SPINES', 'SPINS',
            ],
        ],
        [
            'center' => 'I',
            'outer'  => ['G', 'L', 'M', 'N', 'S', 'Y'],
            'words'  => [
                'GIGGING', 'GIGGLING', 'GIGGLY', 'GIGS', 'GILL', 'GILLS', 'GINNING', 'GINS', 'ILLS', 'INNING',
                'INNINGS', 'INNS', 'ISMS', 'LILY', 'LIMING', 'LIMN', 'LIMNING', 'LIMNS', 'LIMY', 'LINING',
                'LININGS', 'LYING', 'MILLING', 'MILLS', 'MILS', 'MIMING', 'MINGLING', 'MINI', 'MINIM', 'MINIMS',
                'MINING', 'MINIS', 'MISS', 'MISSING', 'NINNY', 'SIGN', 'SIGNING', 'SIGNINGS', 'SIGNS', 'SILL',
                'SILLS', 'SILLY', 'SIMS', 'SING', 'SINGING', 'SINGLING', 'SINGLY', 'SINGS', 'SINNING', 'SINS',
                'SISSY', 'SLILY', 'SLIM', 'SLIMMING', 'SLIMS', 'SLIMY', 'SLING', 'SLINGING', 'SLINGS', 'SMILING',
                'SMILINGLY',
            ],
        ],
        [
            'center' => 'N',
            'outer'  => ['F', 'G', 'I', 'K', 'O', 'R'],
            'words'  => [
                'FINING', 'FINK', 'FINKING', 'FIRING', 'FOGGING', 'FORGING', 'FORGOING', 'FORKING', 'FRINGING',
                'FROGGING', 'GIGGING', 'GINGKO', 'GINKGO', 'GINNING', 'GOING', 'GONG', 'GONGING', 'GONK',
                'GOOFING', 'GOON', 'GORGING', 'GORING', 'GRIFFIN', 'GRIN', 'GRINGO', 'GRINNING', 'GROIN',
                'GROKKING', 'IGNORING', 'IKON', 'INFO', 'INFRINGING', 'INKING', 'INNING', 'IRKING', 'IRON',
                'IRONING', 'KING', 'KINK', 'KINKING', 'KNIFING', 'KRONOR', 'NOGGIN', 'NOOK', 'NOON', 'OFFING',
                'OINK', 'OINKING', 'ONGOING', 'ONION', 'ORIGIN', 'RIFFING', 'RIGGING', 'RING', 'RINGING', 'RINK',
                'ROOFING', 'ROOKING',
            ],
        ],
        [
            'center' => 'I',
            'outer'  => ['C', 'G', 'K', 'L', 'N', 'P'],
            'words'  => [
                'CLICK', 'CLICKING', 'CLING', 'CLINGING', 'CLINIC', 'CLINK', 'CLINKING', 'CLIP', 'CLIPPING',
                'GIGGING', 'GIGGLING', 'GILL', 'GINNING', 'ICING', 'INCING', 'INCLINING', 'INKING', 'INKLING',
                'INNING', 'KICK', 'KICKING', 'KILN', 'KILNING', 'KING', 'KINGPIN', 'KINK', 'KINKING', 'LICK',
                'LICKING', 'LIKING', 'LINING', 'LINK', 'LINKING', 'NICK', 'NICKING', 'NIPPING', 'PICK', 'PICKING',
                'PICKLING', 'PICNIC', 'PICNICKING', 'PIGGING', 'PIING', 'PIKING', 'PILING', 'PILL', 'PILLING',
                'PING', 'PINGING', 'PINING', 'PINK', 'PINKING', 'PINNING', 'PIPING', 'PIPPIN', 'PIPPING',
            ],
        ],
        [
            'center' => 'S',
            'outer'  => ['A', 'D', 'N', 'O', 'W', 'Y'],
            'words'  => [
                'ADDS', 'ANNOYS', 'ANONS', 'DADOS', 'DADS', 'DAWNS', 'DAYS', 'DODOS', 'DONS', 'DOODADS', 'DOWNS',
                'NAYS', 'NODS', 'NOSY', 'NOWADAYS', 'ODDS', 'OWNS', 'SAND', 'SANDS', 'SANDY', 'SANS', 'SAWN',
                'SAWS', 'SAYS', 'SNOW', 'SNOWS', 'SNOWY', 'SODA', 'SODAS', 'SODS', 'SONNY', 'SONS', 'SOON', 'SOWN',
                'SOWS', 'SOYA', 'SWAN', 'SWANS', 'SWAY', 'SWAYS', 'SWOON', 'SWOONS', 'SYNOD', 'SYNODS', 'WADS',
                'WANDS', 'WAYS', 'WOODS', 'WOODSY', 'WOOS', 'WOWS', 'YAWNS', 'YAWS',
            ],
        ],
        [
            'center' => 'O',
            'outer'  => ['C', 'L', 'R', 'T', 'U', 'Y'],
            'words'  => [
                'CLOT', 'CLOUT', 'CLOY', 'COLOR', 'COLT', 'COOL', 'COOLLY', 'COOT', 'COURT', 'COURTLY', 'COYLY',
                'CUTOUT', 'LOCO', 'LOLL', 'LOOT', 'LORRY', 'LOTTO', 'LOUT', 'OCCULT', 'OCCUR', 'OUTCRY', 'ROCOCO',
                'ROLL', 'ROOT', 'ROTOR', 'ROUT', 'TOLL', 'TOOL', 'TOOT', 'TORT', 'TOUR', 'TOUT', 'TROLL', 'TROLLY',
                'TROT', 'TROUT', 'TROY', 'TRYOUT', 'TUTOR', 'TYRO', 'YOUR',
            ],
        ],
        [
            'center' => 'A',
            'outer'  => ['B', 'I', 'L', 'N', 'O', 'R'],
            'words'  => [
                'ABBR', 'ALBINO', 'ALIBI', 'ANAL', 'ANION', 'ANON', 'ARBOR', 'ARIA', 'BABOON', 'BAIL', 'BALL',
                'BALLOON', 'BANAL', 'BANANA', 'BANI', 'BAOBAB', 'BARB', 'BARBARIAN', 'BARN', 'BARON', 'BARONIAL',
                'BARRIO', 'BLAB', 'BOAR', 'BOLA', 'BRAIN', 'BRAN', 'BRIAR', 'LABIA', 'LABIAL', 'LABOR', 'LAIN',
                'LAIR', 'LANOLIN', 'LIAR', 'LIBRARIAN', 'LIRA', 'LLANO', 'LOAN', 'NABOB', 'NAIL', 'ORAL', 'RABBI',
                'RAIL', 'RAIN', 'ROAN', 'ROAR',
            ],
        ],
        [
            'center' => 'T',
            'outer'  => ['A', 'B', 'I', 'L', 'O', 'U'],
            'words'  => [
                'ABBOT', 'ABOUT', 'ABUT', 'ALIT', 'ALLOT', 'ALTO', 'ATOLL', 'AUTO', 'BAILOUT', 'BAIT', 'BALLOT',
                'BLAT', 'BLOAT', 'BLOT', 'BOAT', 'BOBTAIL', 'BOLT', 'BOOT', 'BOUT', 'BUILT', 'BUTT', 'IOTA',
                'LILT', 'LOOT', 'LOTTO', 'LOUT', 'OBIT', 'TABOO', 'TABU', 'TAIL', 'TALL', 'TATTOO', 'TAUT',
                'TIBIA', 'TILL', 'TILT', 'TOIL', 'TOLL', 'TOOL', 'TOOT', 'TOTAL', 'TOUT', 'TUBA', 'TUTU',
            ],
        ],
        [
            'center' => 'L',
            'outer'  => ['A', 'B', 'E', 'F', 'I', 'X'],
            'words'  => [
                'ABLE', 'AFFABLE', 'ALFALFA', 'ALIBI', 'AXIAL', 'AXLE', 'BABBLE', 'BABEL', 'BAFFLE', 'BAIL',
                'BAILIFF', 'BALE', 'BALL', 'BEFALL', 'BEFELL', 'BELIE', 'BELIEF', 'BELL', 'BELLE', 'BIBLE', 'BILE',
                'BILL', 'BLAB', 'EXILE', 'FABLE', 'FAIL', 'FALL', 'FALLIBLE', 'FEEBLE', 'FEEL', 'FELL', 'FILE',
                'FILIAL', 'FILL', 'FIXABLE', 'FLAB', 'FLAIL', 'FLAX', 'FLEA', 'FLEE', 'FLEX', 'FLEXIBLE', 'LABEL',
                'LABIA', 'LABIAL', 'LEAF', 'LIABLE', 'LIBEL', 'LIEF', 'LIFE',
            ],
        ],
        [
            'center' => 'R',
            'outer'  => ['A', 'D', 'K', 'O', 'W', 'Y'],
            'words'  => [
                'ARDOR', 'ARRAY', 'ARROW', 'ARROYO', 'AWARD', 'AWKWARD', 'AWRY', 'DARK', 'DOOR', 'DOORWAY', 'DORK',
                'DORKY', 'DORY', 'DOWRY', 'DRAW', 'DRAY', 'DRYAD', 'ODOR', 'OKRA', 'RADAR', 'ROAD', 'ROADWAY',
                'ROADWORK', 'ROAR', 'ROOD', 'ROOK', 'ROWDY', 'WARD', 'WARY', 'WAYWARD', 'WOODWORK', 'WORD',
                'WORDY', 'WORK', 'WORKADAY', 'WORKDAY', 'WORRY', 'YARD',
            ],
        ],
        [
            'center' => 'O',
            'outer'  => ['D', 'H', 'I', 'R', 'T', 'Y'],
            'words'  => [
                'DHOTI', 'DITTO', 'DODO', 'DOOR', 'DORY', 'DOTH', 'DOTTY', 'DROID', 'HOOD', 'HOODOO', 'HOOT',
                'HORRID', 'HORROR', 'IDIOT', 'ODDITY', 'ODOR', 'RIOT', 'ROOD', 'ROOT', 'ROTOR', 'THYROID', 'TIRO',
                'TODDY', 'TOOT', 'TOOTH', 'TOOTHY', 'TORRID', 'TORT', 'TRIO', 'TROD', 'TROT', 'TROTH', 'TROY',
                'TYRO',
            ],
        ],
        [
            'center' => 'T',
            'outer'  => ['F', 'I', 'J', 'S', 'U', 'Y'],
            'words'  => [
                'FIFTY', 'FIST', 'FISTS', 'FITS', 'FUSTY', 'JIUJITSU', 'JUJITSU', 'JUJUTSU', 'JUST', 'JUSTIFY',
                'JUTS', 'SIFT', 'SIFTS', 'SITS', 'SITU', 'STIFF', 'STIFFS', 'STUFF', 'STUFFS', 'STUFFY', 'SUIT',
                'SUITS', 'TIFF', 'TIFFS', 'TTYS', 'TUFT', 'TUFTS', 'TUTU', 'TUTUS',
            ],
        ],
        [
            'center' => 'N',
            'outer'  => ['A', 'F', 'G', 'I', 'Q', 'U'],
            'words'  => [
                'AGAIN', 'AGING', 'ANGINA', 'FAIN', 'FANG', 'FANNING', 'FAUN', 'FAUNA', 'FINING', 'FUNGI',
                'GAFFING', 'GAGGING', 'GAGING', 'GAIN', 'GAINING', 'GANG', 'GANGING', 'GAUGING', 'GIGGING',
                'GINNING', 'IGUANA', 'INNING', 'NAGGING', 'QUAFFING',
            ],
        ],
        [
            'center' => 'N',
            'outer'  => ['B', 'I', 'M', 'R', 'U', 'V'],
            'words'  => [
                'BRUIN', 'BURN', 'MINI', 'MINIM', 'MINIMUM', 'NIMBI', 'NUMB', 'RUIN', 'VIBURNUM',
            ],
        ],
    ];
}

register_activation_hook(__FILE__, function () {
    if (!get_option('ascend_axh_puzzles')) {
        add_option('ascend_axh_puzzles', ascend_axh_default_puzzles());
    }
    if (get_option('ascend_axh_mascot_url', null) === null) {
        add_option('ascend_axh_mascot_url', '');
    }
    if (!get_option('ascend_axh_unlock_pct')) {
        add_option('ascend_axh_unlock_pct', 50);
    }
    if (get_option('ascend_axh_longest_discount_pct', null) === null) {
        add_option('ascend_axh_longest_discount_pct', 10);
    }
});

/* ============================================================
   PUZZLE HELPERS
   ============================================================ */
function ascend_axh_get_puzzles() {
    $puzzles = get_option('ascend_axh_puzzles', ascend_axh_default_puzzles());
    if (!is_array($puzzles) || empty($puzzles)) return ascend_axh_default_puzzles();

    $clean = [];
    foreach ($puzzles as $p) {
        if (!isset($p['center']) || !isset($p['outer']) || !is_array($p['outer'])) continue;
        $center = strtoupper(substr(trim($p['center']), 0, 1));
        $outer  = array_slice(array_values(array_unique(array_map(function ($l) {
            return strtoupper(substr(trim($l), 0, 1));
        }, $p['outer']))), 0, 6);
        $words = isset($p['words']) && is_array($p['words']) ? array_values(array_map('strtoupper', $p['words'])) : [];
        $pool  = isset($p['pool'])  && is_array($p['pool'])  ? array_values(array_map('strtoupper', $p['pool']))  : [];
        if ($center === '' || count($outer) !== 6) continue;
        $clean[] = ['center' => $center, 'outer' => $outer, 'words' => $words, 'pool' => $pool];
    }
    return !empty($clean) ? $clean : ascend_axh_default_puzzles();
}

function ascend_axh_puzzles_count() {
    return count(ascend_axh_get_puzzles());
}

/* All 7 letters for a puzzle, center first. */
function ascend_axh_puzzle_letters($puzzle) {
    return array_merge([$puzzle['center']], $puzzle['outer']);
}

function ascend_axh_is_pangram($word, $puzzle) {
    $word_letters = array_unique(str_split(strtoupper($word)));
    $puzzle_letters = ascend_axh_puzzle_letters($puzzle);
    sort($word_letters);
    sort($puzzle_letters);
    return $word_letters === $puzzle_letters;
}

/* ------------------------------------------------------------------
   TWO DICTIONARIES PER POND

   'words' is the curated BONUS bank. It is the pond's yardstick: max
   score, the unlock threshold, the longest-word target and the career
   rank are all still measured against it alone, exactly as before.

   'pool' is the wider English dictionary filtered to this pond's seven
   letters. Pool words are accepted and scored, but they never enter
   max_score. A student who finds them simply climbs toward the same
   threshold faster, which is the whole point: no more "that word isn't
   in the library" on an ordinary English word.
   ------------------------------------------------------------------ */
function ascend_axh_pool($puzzle) {
    return (isset($puzzle['pool']) && is_array($puzzle['pool'])) ? $puzzle['pool'] : [];
}

/* Path to the shipped word list. One word per line, uppercase; it sits
   beside this file so a plugin update carries both together. */
function ascend_axh_dictionary_path() {
    return plugin_dir_path(__FILE__) . 'hex-a-lotl-dictionary.txt';
}

/**
 * Rebuild the pool for every pond that has none (or all of them when
 * forced), in a single streaming pass over the dictionary.
 *
 * The file is read line by line rather than loaded into an array: the word
 * list runs to a few hundred thousand entries and holding it in memory all
 * at once is the kind of thing that trips a modest shared-hosting limit.
 * One pass tests each word against every pond being rebuilt, so the cost is
 * one read of the file no matter how many ponds need filling.
 *
 * Bonus words are removed from the pool so the two banks stay disjoint and
 * a word is only ever scored one way.
 *
 * Returns the number of ponds rebuilt.
 */
function ascend_axh_rebuild_pools($force = false) {
    $path = ascend_axh_dictionary_path();
    if (!is_readable($path)) return 0;

    $puzzles = ascend_axh_get_puzzles();
    $targets = [];
    foreach ($puzzles as $i => $pz) {
        if (!$force && !empty($pz['pool'])) continue;
        $targets[$i] = [
            'center'  => $pz['center'],
            'allowed' => array_flip(array_merge([$pz['center']], $pz['outer'])),
            'pool'    => [],
        ];
    }
    if (empty($targets)) return 0;

    $fh = fopen($path, 'r');
    if (!$fh) return 0;

    while (($line = fgets($fh)) !== false) {
        $word = strtoupper(trim($line));
        $len  = strlen($word);
        if ($len < 4) continue;

        foreach ($targets as $i => $t) {
            if (strpos($word, $t['center']) === false) continue;
            $fits = true;
            for ($k = 0; $k < $len; $k++) {
                if (!isset($t['allowed'][$word[$k]])) { $fits = false; break; }
            }
            if ($fits) $targets[$i]['pool'][] = $word;
        }
    }
    fclose($fh);

    foreach ($targets as $i => $t) {
        $bonus = array_flip($puzzles[$i]['words']);
        $puzzles[$i]['pool'] = array_values(array_filter($t['pool'], function ($w) use ($bonus) {
            return !isset($bonus[$w]);
        }));
    }

    update_option('ascend_axh_puzzles', $puzzles);
    return count($targets);
}

/* Fill in any missing pools once per plugin version. This is what makes the
   feature self-maintaining: a pond added from wp-admin, or one whose letters
   were changed, gets its pool on the next admin page load rather than
   silently falling back to bonus words only. */
add_action('admin_init', function () {
    if (get_option('ascend_axh_pool_build') === ASCEND_AXH_VERSION) return;
    ascend_axh_rebuild_pools(false);
    update_option('ascend_axh_pool_build', ASCEND_AXH_VERSION);
});

function ascend_axh_is_bonus_word($word, $puzzle) {
    return in_array(strtoupper($word), $puzzle['words'], true);
}

/* Bonus words keep the longest-word premium; pool words score their
   length. Anything else would let the pool inflate the target it is
   being measured against. */
function ascend_axh_word_score($word, $puzzle) {
    $len = strlen($word);
    if (!ascend_axh_is_bonus_word($word, $puzzle)) return $len;
    $longest = ascend_axh_longest_word_len($puzzle);
    $is_biggest = ($longest > 0 && $len === $longest);
    return $len + ($is_biggest ? 7 : 0);
}

function ascend_axh_max_score($puzzle) {
    $total = 0;
    foreach ($puzzle['words'] as $w) {
        $total += ascend_axh_word_score($w, $puzzle);
    }
    return $total;
}

/* Server-authoritative validation against the pond's two banks: the
   curated bonus list and the wider pool. Still no live/external lookup —
   both banks are stored with the pond. */
function ascend_axh_validate_word($raw, $puzzle, $already_found) {
    $word = strtoupper(trim($raw));
    if (strlen($word) < 4) return ['ok' => false, 'reason' => 'too_short', 'word' => $word];
    if (!preg_match('/^[A-Z]+$/', $word)) return ['ok' => false, 'reason' => 'bad_chars', 'word' => $word];

    $letters = ascend_axh_puzzle_letters($puzzle);
    foreach (str_split($word) as $ch) {
        if (!in_array($ch, $letters, true)) return ['ok' => false, 'reason' => 'bad_letters', 'word' => $word];
    }
    if (strpos($word, $puzzle['center']) === false) return ['ok' => false, 'reason' => 'missing_center', 'word' => $word];
    if (in_array($word, $already_found, true)) return ['ok' => false, 'reason' => 'already_found', 'word' => $word];

    $is_bonus = in_array($word, $puzzle['words'], true);
    if (!$is_bonus && !in_array($word, ascend_axh_pool($puzzle), true)) {
        return ['ok' => false, 'reason' => 'not_in_list', 'word' => $word];
    }

    return ['ok' => true, 'word' => $word, 'bonus' => $is_bonus];
}

/* ============================================================
   RANKS — axolotl-flavored version of the classic Spelling Bee tiers.
   The baseline rank is now a CAREER rank: it's based on cumulative
   score across every puzzle the student has actually played, so it
   evolves gradually over many puzzles instead of resetting to
   "Hatchling" the instant a fresh, empty puzzle loads. "Legendary
   Lotl" is a separate, temporary flag shown only while the student's
   CURRENT puzzle has met its unlock threshold.
   ============================================================ */
function ascend_axh_rank_tiers() {
    return [
        [0,  'Hatchling'],
        [2,  'Wiggler'],
        [5,  'Paddler'],
        [8,  'Regrower'],
        [15, 'Camouflager'],
        [25, 'Adapter'],
        [40, 'Sensor'],
        [50, 'Axolotl'],
        [70, 'Ancient One'],
    ];
}

function ascend_axh_rank_for_pct($pct) {
    $label = 'Hatchling';
    foreach (ascend_axh_rank_tiers() as $tier) {
        if ($pct >= $tier[0]) $label = $tier[1];
    }
    return $label;
}

function ascend_axh_rank_for($career_score, $career_max, $current_unlocked) {
    if ($current_unlocked) return 'Legendary Lotl';
    if ($career_max <= 0) return 'Hatchling';
    return ascend_axh_rank_for_pct(($career_score / $career_max) * 100);
}

/* ============================================================
   UNLOCK SETTINGS — points needed to move to the next pond, as a
   percentage of that pond's max possible score. Finding the pond's
   longest word BEFORE reaching that threshold knocks a few points
   off what's still needed, as a reward — but it's never the sole
   unlock condition on its own anymore.
   ============================================================ */
function ascend_axh_unlock_pct() {
    $n = (int) get_option('ascend_axh_unlock_pct', 50);
    return ($n > 0 && $n <= 100) ? $n : 50;
}

function ascend_axh_longest_discount_pct() {
    $n = (int) get_option('ascend_axh_longest_discount_pct', 10);
    return ($n >= 0 && $n <= 100) ? $n : 10;
}

function ascend_axh_required_score($puzzle, $found_longest) {
    $max = ascend_axh_max_score($puzzle);
    $pct = ascend_axh_unlock_pct();
    if ($found_longest) $pct = max(5, $pct - ascend_axh_longest_discount_pct());
    return (int) ceil($max * $pct / 100);
}

/* ============================================================
   PER-STUDENT STATE
   Progress is individualized: each student has their own "active"
   puzzle (axh_progress) that they're working toward unlocking the
   next one. Found words are stored PER PUZZLE INDEX, so students
   can freely revisit and keep adding words to any already-unlocked
   (current or past) puzzle — puzzles beyond their active index stay
   locked until the points threshold is met on the active one.
   ============================================================ */
function ascend_axh_progress($user_id) {
    return (int) get_user_meta($user_id, 'axh_progress', true);
}

function ascend_axh_is_all_complete($user_id) {
    $total = ascend_axh_puzzles_count();
    return $total > 0 && ascend_axh_progress($user_id) >= $total;
}

function ascend_axh_puzzle_at($idx) {
    $puzzles = ascend_axh_get_puzzles();
    return isset($puzzles[$idx]) ? $puzzles[$idx] : null;
}

function ascend_axh_words_for($user_id, $idx) {
    $map = get_user_meta($user_id, 'axh_words_by_index', true);
    $map = is_array($map) ? $map : [];
    $words = isset($map[$idx]) && is_array($map[$idx]) ? $map[$idx] : [];
    return $words;
}

function ascend_axh_set_words_for($user_id, $idx, $words) {
    $map = get_user_meta($user_id, 'axh_words_by_index', true);
    $map = is_array($map) ? $map : [];
    $map[$idx] = $words;
    update_user_meta($user_id, 'axh_words_by_index', $map);
}

function ascend_axh_score_words($words, $puzzle) {
    $total = 0;
    foreach ($words as $w) $total += ascend_axh_word_score($w, $puzzle);
    return $total;
}

function ascend_axh_longest_word_len($puzzle) {
    $max = 0;
    foreach ($puzzle['words'] as $w) $max = max($max, strlen($w));
    return $max;
}

function ascend_axh_found_longest_in($words, $puzzle) {
    $longest = ascend_axh_longest_word_len($puzzle);
    if ($longest <= 0) return false;
    foreach ($words as $w) {
        if (strlen($w) === $longest) return true;
    }
    return false;
}

function ascend_axh_found_pangram_in($words, $puzzle) {
    foreach ($words as $w) {
        if (ascend_axh_is_pangram($w, $puzzle)) return true;
    }
    return false;
}

/* Score/max totals across every puzzle the student has actually put
   words into (0 through their active index) — the basis for the
   career rank. Untouched future/empty slots don't drag it down. */
function ascend_axh_career_stats($user_id) {
    $progress = ascend_axh_progress($user_id);
    $score = 0; $max = 0;
    for ($i = 0; $i <= $progress; $i++) {
        $puzzle = ascend_axh_puzzle_at($i);
        if (!$puzzle) continue;
        $words = ascend_axh_words_for($user_id, $i);
        if (empty($words)) continue;
        $score += ascend_axh_score_words($words, $puzzle);
        $max   += ascend_axh_max_score($puzzle);
    }
    return ['score' => $score, 'max' => $max];
}

function ascend_axh_today_key() {
    return current_time('Y-m-d');
}

/* Streak = consecutive calendar days with at least one word found.
   Missing a day breaks the streak but never touches puzzle progress. */
function ascend_axh_bump_streak($user_id) {
    $today = ascend_axh_today_key();
    $last  = get_user_meta($user_id, 'axh_last_play_date', true);
    if ($last === $today) return;
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
    $streak = ($last === $yesterday) ? ((int) get_user_meta($user_id, 'axh_streak', true) + 1) : 1;
    update_user_meta($user_id, 'axh_streak', $streak);
    update_user_meta($user_id, 'axh_last_play_date', $today);
}

function ascend_axh_reset_user($user_id) {
    foreach (['axh_progress', 'axh_words_by_index', 'axh_current_words', 'axh_streak', 'axh_last_play_date', 'axh_lifetime_words', 'axh_solved_log'] as $key) {
        delete_user_meta($user_id, $key);
    }
}

/* Clears only the in-progress words on the ACTIVE puzzle, leaving overall
   progress, streak, and every other (past) puzzle's words untouched. */
function ascend_axh_reset_current_puzzle($user_id) {
    ascend_axh_set_words_for($user_id, ascend_axh_progress($user_id), []);
}

/* ============================================================
   AJAX: GET STATE — accepts an optional view_index so a student can
   browse back through any puzzle from 0 up to their active index.
   Anything beyond the active index stays locked and is never returned.
   ============================================================ */
add_action('wp_ajax_ascend_axh_get_state', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axh_nonce', 'nonce');

    $user_id  = get_current_user_id();
    $total    = ascend_axh_puzzles_count();
    $progress = ascend_axh_progress($user_id);
    $complete = ascend_axh_is_all_complete($user_id);

    $view_index = isset($_POST['view_index']) ? (int) $_POST['view_index'] : $progress;
    $view_index = max(0, min($view_index, $progress));
    $puzzle = ascend_axh_puzzle_at($view_index);

    $career = ascend_axh_career_stats($user_id);

    $payload = [
        'puzzleIndex'   => $progress,
        'viewIndex'     => $view_index,
        'isPast'        => $view_index < $progress,
        'hasPass'       => ascend_games_has_pass($user_id),
        'skips'         => ascend_games_skips($user_id),
        'passUrls'      => ascend_games_pass_urls(),
        'totalPuzzles'  => $total,
        'allComplete'   => $complete,
        'streak'        => (int) get_user_meta($user_id, 'axh_streak', true),
        'lifetimeWords' => (int) get_user_meta($user_id, 'axh_lifetime_words', true),
        'mascotUrl'     => get_option('ascend_axh_mascot_url', ''),
        'unlockPct'         => ascend_axh_unlock_pct(),
        'longestDiscountPct'=> ascend_axh_longest_discount_pct(),
    ];

    if ($puzzle) {
        $found = ascend_axh_words_for($user_id, $view_index);
        $found_detail = array_map(function ($w) use ($puzzle) {
            $pang = ascend_axh_is_pangram($w, $puzzle);
            return [
                'word'    => $w,
                'score'   => ascend_axh_word_score($w, $puzzle),
                'pangram' => $pang,
                'bonus'   => ascend_axh_is_bonus_word($w, $puzzle),
            ];
        }, $found);
        $score = array_sum(array_column($found_detail, 'score'));
        $max   = ascend_axh_max_score($puzzle);
        $pangram_found = ascend_axh_found_pangram_in($found, $puzzle);
        $found_longest = ascend_axh_found_longest_in($found, $puzzle);
        $required = ascend_axh_required_score($puzzle, $found_longest);
        $unlocked = $score >= $required;
        $is_active = ($view_index === $progress);

        $payload['center']         = $puzzle['center'];
        $payload['outer']          = $puzzle['outer'];
        $payload['foundWords']     = $found_detail;
        $payload['score']          = $score;
        $payload['maxScore']       = $max;
        $payload['requiredScore']  = $required;
        $payload['unlocked']       = $unlocked;
        $payload['rank']           = ascend_axh_rank_for($career['score'], $career['max'], $is_active && $unlocked);
        $payload['pangramFound']   = $pangram_found;
        $payload['totalWords']     = count($puzzle['words']);
        $payload['longestLen']     = ascend_axh_longest_word_len($puzzle);
        $payload['foundLongest']   = $found_longest;
    }

    wp_send_json_success($payload);
});

/* ============================================================
   AJAX: SUBMIT WORD — accepts an optional puzzle_index so a student
   can add words to any already-unlocked (current or past) puzzle.
   Anything beyond their active index is rejected server-side.
   ============================================================ */
add_action('wp_ajax_ascend_axh_submit_word', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axh_nonce', 'nonce');

    $user_id  = get_current_user_id();
    $progress = ascend_axh_progress($user_id);
    if (ascend_axh_is_all_complete($user_id)) wp_send_json_error(['reason' => 'all_complete']);

    $idx = isset($_POST['puzzle_index']) ? (int) $_POST['puzzle_index'] : $progress;
    if ($idx < 0 || $idx > $progress) wp_send_json_error(['reason' => 'locked_puzzle']);

    $puzzle = ascend_axh_puzzle_at($idx);
    if (!$puzzle) wp_send_json_error(['reason' => 'no_puzzle']);

    $raw = sanitize_text_field($_POST['word'] ?? '');
    $found = ascend_axh_words_for($user_id, $idx);
    $check = ascend_axh_validate_word($raw, $puzzle, $found);

    if (!$check['ok']) {
        wp_send_json_error(['reason' => $check['reason'], 'word' => $check['word']]);
    }

    $word = $check['word'];
    $found[] = $word;
    ascend_axh_set_words_for($user_id, $idx, $found);
    update_user_meta($user_id, 'axh_lifetime_words', (int) get_user_meta($user_id, 'axh_lifetime_words', true) + 1);
    ascend_axh_bump_streak($user_id);

    $pangram = ascend_axh_is_pangram($word, $puzzle);
    $word_score = ascend_axh_word_score($word, $puzzle);

    $score = ascend_axh_score_words($found, $puzzle);
    $max   = ascend_axh_max_score($puzzle);
    $pangram_found = ascend_axh_found_pangram_in($found, $puzzle);
    $found_longest = ascend_axh_found_longest_in($found, $puzzle);
    $required = ascend_axh_required_score($puzzle, $found_longest);
    $unlocked = $score >= $required;
    $is_active = ($idx === $progress);

    $career = ascend_axh_career_stats($user_id);

    wp_send_json_success([
        'word'          => $word,
        'wordScore'     => $word_score,
        'bonus'         => !empty($check['bonus']),
        'pangram'       => $pangram,
        'score'         => $score,
        'maxScore'      => $max,
        'requiredScore' => $required,
        'unlocked'      => $unlocked,
        'rank'          => ascend_axh_rank_for($career['score'], $career['max'], $is_active && $unlocked),
        'pangramFound'  => $pangram_found,
        'streak'        => (int) get_user_meta($user_id, 'axh_streak', true),
        'lifetimeWords' => (int) get_user_meta($user_id, 'axh_lifetime_words', true),
        'totalWords'    => count($puzzle['words']),
        'longestLen'    => ascend_axh_longest_word_len($puzzle),
        'foundLongest'  => $found_longest,
    ]);
});

/* ============================================================
   AJAX: ADVANCE TO NEXT PUZZLE — only once the ACTIVE puzzle's score
   meets its points threshold (discounted if the longest word was
   already found). Past puzzles are never cleared, so they stay
   revisitable afterward.
   ============================================================ */
add_action('wp_ajax_ascend_axh_advance_puzzle', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axh_nonce', 'nonce');

    $user_id  = get_current_user_id();
    $progress = ascend_axh_progress($user_id);
    $puzzle   = ascend_axh_puzzle_at($progress);
    if (!$puzzle) wp_send_json_error(['reason' => 'no_puzzle']);

    $found = ascend_axh_words_for($user_id, $progress);
    $found_longest = ascend_axh_found_longest_in($found, $puzzle);
    $required = ascend_axh_required_score($puzzle, $found_longest);
    $score = ascend_axh_score_words($found, $puzzle);

    /* A pass opens ponds outright. Without one, a single-use credit can be
       spent instead — but only when the student asks for it by name, so a
       stray click on "next pond" can never silently burn one. */
    if ($score < $required && !ascend_games_has_pass($user_id)) {
        $spend = !empty($_POST['use_skip']);
        if (!$spend || !ascend_games_consume_skip($user_id)) {
            wp_send_json_error([
                'reason'        => 'threshold_not_met',
                'score'         => $score,
                'requiredScore' => $required,
                'skips'         => ascend_games_skips($user_id),
                'passUrls'      => ascend_games_pass_urls(),
            ]);
        }
    }

    $log = get_user_meta($user_id, 'axh_solved_log', true);
    $log = is_array($log) ? $log : [];
    $log[] = ['puzzleIndex' => $progress, 'date' => ascend_axh_today_key(), 'score' => $score, 'wordsFound' => count($found)];
    update_user_meta($user_id, 'axh_solved_log', $log);

    /* Dashboard contract: every game also writes {prefix}_puzzle_log in the
       shared shape ['puzzle'=>N,'found_largest'=>bool]. Hex-a-lotl shipped
       without this, so its stats card and Flawless badge never had data to
       read. 'found_largest' means the pond's longest word was found, which
       mirrors Connect's zero-mistakes and Cross's zero-hints. The original
       axh_solved_log row is still written above, untouched, as a rollback. */
    $plog = get_user_meta($user_id, 'axh_puzzle_log', true);
    $plog = is_array($plog) ? $plog : [];
    $plog[] = ['puzzle' => $progress + 1, 'found_largest' => (bool) $found_longest];
    update_user_meta($user_id, 'axh_puzzle_log', $plog);

    update_user_meta($user_id, 'axh_progress', $progress + 1);

    $complete = ascend_axh_is_all_complete($user_id);
    $next_idx = $progress + 1;
    $next = ascend_axh_puzzle_at($next_idx);

    $payload = ['allComplete' => $complete, 'puzzleIndex' => $next_idx];
    if ($next) {
        $payload['center']     = $next['center'];
        $payload['outer']      = $next['outer'];
        $payload['maxScore']   = ascend_axh_max_score($next);
        $payload['totalWords'] = count($next['words']);
        $payload['longestLen'] = ascend_axh_longest_word_len($next);
        $payload['requiredScore'] = ascend_axh_required_score($next, false);
    }
    wp_send_json_success($payload);
});

/* ============================================================
   SHORTCODE — [ascend_hex_a_lotl]
   ============================================================ */
add_shortcode('ascend_hex_a_lotl', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in to play.</p>';
    }

    $user_id  = get_current_user_id();
    $total    = ascend_axh_puzzles_count();
    $progress = ascend_axh_progress($user_id);
    $streak   = (int) get_user_meta($user_id, 'axh_streak', true);
    $nonce    = wp_create_nonce('ascend_axh_nonce');
    $ajaxurl  = admin_url('admin-ajax.php');
    $mascot   = get_option('ascend_axh_mascot_url', '');

    ob_start();
    ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&display=swap');
#axh-game{--blue:#009CDE;--green:#1D4010;--lgreen:#BFE0B4;--pink:#F5B9CF;--darkpink:#E07FA3;--hexw:clamp(84px,19.5vw,120px);--hexh:calc(var(--hexw) * 0.8660254038);max-width:460px;margin:0 auto;padding:25px 15px 50px;font-family:Roboto,Arial,sans-serif;color:#24332a;}
#axh-game *{box-sizing:border-box;}
.axh-title{text-align:center;font-size:clamp(28px,5vw,42px);margin:0;color:#173e63;font-weight:700;font-family:'Baloo 2',Roboto,Arial,sans-serif;}
.axh-subtitle{text-align:center;color:#62676c;margin:10px 0 20px;font-size:15px;}
.axh-stats{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.axh-stat{background:white;border:1px solid #e4e1da;border-radius:14px;padding:9px 17px;font-size:14px;}
.axh-stat strong{color:var(--blue);}
.axh-mascot{text-align:center;margin-bottom:6px;}
.axh-mascot img{max-width:150px;width:100%;height:auto;}
.axh-toggle-row{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;}
.axh-locked-toggle{display:block;margin:10px auto 0;background:transparent;border:1px dashed #c9c5b8;border-radius:8px;padding:8px 16px;font-size:12px;color:#77705f;cursor:pointer;font-family:inherit;}
.axh-locked-toggle:hover{border-color:#999;color:#555;}
.axh-instructions{margin-top:10px;background:#eef6fb;border:1px solid #d4e7f2;border-radius:12px;padding:14px 16px;font-size:13px;color:#24332a;text-align:left;}
.axh-live{text-align:center;font-size:13px;color:#1474aa;margin-bottom:4px;}
.axh-message{text-align:center;min-height:18px;font-size:14px;font-weight:700;margin:4px 0;}
.axh-message.good{color:var(--green);}
.axh-message.bad{color:var(--darkpink);}
.axh-word-display{text-align:center;font-size:28px;font-weight:700;letter-spacing:2px;min-height:34px;margin:2px 0 8px;text-transform:uppercase;color:#24332a;}
.axh-word-display .axh-center-typed{color:var(--darkpink);}
.axh-hive-wrap{position:relative;width:calc(var(--hexw) * 2.5);height:calc(var(--hexh) * 3);margin:0 auto 58px;}
.axh-hex{position:absolute;width:var(--hexw);height:var(--hexh);clip-path:polygon(25% 0%,75% 0%,100% 50%,75% 100%,25% 100%,0% 50%);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:calc(var(--hexw) * 0.36);cursor:pointer;user-select:none;background:var(--lgreen);color:#173e63;z-index:1;}
.axh-hex::before{content:'';position:absolute;inset:-4px;clip-path:inherit;background:transparent;z-index:-1;transition:background-color .12s ease;}
.axh-hex:hover::before{background-color:var(--blue);}
.axh-hex:active::before{background-color:var(--darkpink);}
.axh-hex.axh-center{background:var(--darkpink);color:#fff;z-index:2;}
.axh-hex.axh-center:hover::before{background-color:var(--green);}
.axh-hex-center{top:50%;left:50%;transform:translate(-50%,-50%);}
.axh-hex-top{top:calc(50% - var(--hexh));left:50%;transform:translate(-50%,-50%);}
.axh-hex-bottom{top:calc(50% + var(--hexh));left:50%;transform:translate(-50%,-50%);}
.axh-hex-ur{top:calc(50% - (var(--hexh) / 2));left:calc(50% + (var(--hexw) * 0.75));transform:translate(-50%,-50%);}
.axh-hex-lr{top:calc(50% + (var(--hexh) / 2));left:calc(50% + (var(--hexw) * 0.75));transform:translate(-50%,-50%);}
.axh-hex-ul{top:calc(50% - (var(--hexh) / 2));left:calc(50% - (var(--hexw) * 0.75));transform:translate(-50%,-50%);}
.axh-hex-ll{top:calc(50% + (var(--hexh) / 2));left:calc(50% - (var(--hexw) * 0.75));transform:translate(-50%,-50%);}
.axh-hex.axh-pop{animation:axhPop .15s ease;}
@keyframes axhPop{0%{transform:translate(-50%,-50%) scale(.85);}60%{transform:translate(-50%,-50%) scale(1.1);}100%{transform:translate(-50%,-50%) scale(1);}}
.axh-controls{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:22px 0 6px;}
.axh-btn{border:0;border-radius:12px;padding:11px 20px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;background:#fff;border:1px solid #d7dadd;color:#24332a;}
.axh-btn:hover{border-color:var(--blue);}
.axh-btn.axh-submit{background:var(--blue);color:#fff;border:0;}
.axh-btn:disabled{opacity:.4;cursor:default;}
.axh-progress-wrap{max-width:380px;margin:18px auto 0;}
.axh-progress-labels{display:flex;justify-content:space-between;font-size:12px;color:#62676c;margin-bottom:5px;}
.axh-progress-track{position:relative;background:#eceae3;border-radius:20px;height:16px;overflow:hidden;border:1px solid #e0dccf;}
.axh-progress-fill{height:100%;background:var(--blue);border-radius:20px;transition:width .35s ease,background-color .3s ease;position:relative;z-index:1;}
.axh-progress-fill.axh-unlocked{background:var(--green);}
.axh-progress-fill.axh-gold{background:linear-gradient(90deg,#e8b923,#f7d774);}
.axh-progress-marker{position:absolute;top:0;bottom:0;width:2px;background:rgba(0,0,0,.28);z-index:2;transition:left .35s ease;}
.axh-progress-note{font-size:11px;color:#9a9384;text-align:center;margin-top:6px;}
.axh-longest-badge{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:10px;padding:8px 10px;border-radius:10px;background:#f4f2ec;border:1px dashed #d0ccc2;color:#9a9384;font-size:12px;font-weight:700;text-align:center;transition:background-color .3s ease,color .3s ease,border-color .3s ease;}
.axh-longest-badge.axh-found{background:var(--pink);border:1px solid var(--darkpink);color:#7a2d4c;}
.axh-longest-badge.axh-found.axh-gold{background:linear-gradient(90deg,#e8b923,#f7d774);border-color:#c99a10;color:#5c4308;}
.axh-found-summary{text-align:center;font-size:14px;margin:14px 0 6px;}
.axh-found-summary strong{color:var(--green);}
.axh-found-list{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin-top:10px;}
.axh-found-chip{background:#fff;border:1px solid #e4e1da;border-radius:14px;padding:5px 11px;font-size:12px;font-weight:700;}
.axh-found-chip.axh-pangram-chip{background:var(--darkpink);color:#fff;border-color:var(--darkpink);}
.axh-pass-offer{margin-top:14px;text-align:center;}
.axh-pass-line{margin:0 0 8px;font-size:13px;color:#5F5E5A;}
.axh-pass-row{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;}
.axh-btn.axh-pass-btn{text-decoration:none;display:inline-block;}
.axh-found-chip.axh-bonus-chip{background:var(--lgreen,#BFE0B4);color:var(--green,#1D4010);border-color:var(--green,#1D4010);font-weight:700;}
.axh-pangram-banner{margin-top:16px;background:var(--green);color:#fff;border-radius:12px;padding:16px;text-align:center;font-size:15px;font-weight:700;}
.axh-pangram-banner p{margin:0 0 10px;}
.axh-pangram-banner .axh-btn{background:#fff;color:var(--green);}
.axh-nav-row{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:14px;font-size:13px;color:#62676c;}
.axh-nav-btn{border:1px solid #d7dadd;background:#fff;border-radius:8px;padding:5px 10px;font-size:13px;cursor:pointer;font-family:inherit;color:#24332a;}
.axh-nav-btn:hover{border-color:var(--blue);}
.axh-nav-btn:disabled{opacity:.35;cursor:default;}
.axh-past-notice{max-width:380px;margin:0 auto 14px;background:#fdf3f7;border:1px solid var(--pink);border-radius:10px;padding:8px 12px;font-size:12px;color:#7a2d4c;text-align:center;}
.axh-past-notice a{color:var(--darkpink);font-weight:700;cursor:pointer;text-decoration:underline;}
.axh-done{margin-top:16px;border-radius:12px;padding:16px;background:#f4f2ec;border:1px solid #e0dccf;color:#5f5a4d;font-size:14px;text-align:center;}
.axh-row{display:flex;justify-content:center;gap:16px;flex-wrap:wrap;margin-top:6px;}
@media(max-width:420px){.axh-word-display{font-size:22px;}}
</style>

<div id="axh-game">
  <?php if ($mascot): ?><div class="axh-mascot"><img src="<?php echo esc_url($mascot); ?>" alt="Axolotl"></div><?php endif; ?>
  <h1 class="axh-title">Hex-a-lotl</h1>
  <p class="axh-subtitle">Find every word hiding in the pond. Every word needs the center letter.</p>

  <div class="axh-stats">
    <div class="axh-stat">Streak: <strong id="axh-streak"><?php echo esc_html($streak); ?></strong></div>
    <div class="axh-stat">Puzzle: <strong id="axh-puzzle-num"><?php echo esc_html($progress + 1); ?></strong> / <?php echo esc_html($total); ?></div>
    <div class="axh-stat">Rank: <strong id="axh-rank">—</strong></div>
  </div>

  <div class="axh-toggle-row">
    <button type="button" id="axh-instructions-toggle" class="axh-locked-toggle">How to play &#9662;</button>
  </div>
  <div id="axh-instructions-panel" class="axh-instructions" style="display:none;"></div>
  <div class="axh-live" id="axh-live-badge"></div>
  <div id="axh-play-area"></div>
</div>

<script>
(function(){
var ASCEND_AXH = { ajaxurl: <?php echo wp_json_encode($ajaxurl); ?>, nonce: <?php echo wp_json_encode($nonce); ?> };
var state = null, currentWord = "", outerOrder = [];

function api(action, data){
  var body = new URLSearchParams(Object.assign({action:action, nonce:ASCEND_AXH.nonce}, data||{}));
  return fetch(ASCEND_AXH.ajaxurl, {method:'POST', credentials:'same-origin', body:body}).then(function(r){return r.json();});
}

function loadState(viewIndex){
  var params = (viewIndex === undefined || viewIndex === null) ? {} : {view_index: viewIndex};
  api('ascend_axh_get_state', params).then(function(resp){
    if(!resp.success){ message('Could not load your progress.', false); return; }
    state = resp.data;
    outerOrder = (state.outer || []).slice();
    renderAll();
  });
}

function renderAll(){
  document.getElementById('axh-streak').textContent = state.streak;
  document.getElementById('axh-puzzle-num').textContent = state.puzzleIndex + 1;
  document.getElementById('axh-rank').textContent = state.rank || (state.allComplete ? 'Legendary Lotl' : '\u2014');
  renderInstructions();
  renderPlayArea();
}

function renderInstructions(){
  var panel = document.getElementById('axh-instructions-panel');
  panel.innerHTML =
    '<p style="margin:0 0 8px;">Make words of 4 or more letters using the 7 letters in the pond.</p>' +
    '<p style="margin:0 0 8px;">Every word must include the <span style="background:#E07FA3;color:#fff;border-radius:4px;padding:1px 6px;">center letter</span>. Letters can repeat.</p>' +
    '<p style="margin:0 0 8px;">4-letter words are worth 1 point. Longer words are worth 1 point per letter.</p>' +
    '<p style="margin:0 0 8px;">Score enough points to unlock your next pond \u2014 find this pond\u2019s <strong>longest word</strong> before you hit that threshold and you\u2019ll need slightly fewer points to unlock.</p>' +
    '<p style="margin:0;">You can always come back and keep playing any pond you\u2019ve already unlocked \u2014 only the next brand-new one stays locked.</p>';

  var toggle = document.getElementById('axh-instructions-toggle');
  toggle.onclick = function(){
    var hidden = panel.style.display === 'none';
    panel.style.display = hidden ? '' : 'none';
    toggle.textContent = hidden ? 'Hide instructions \u25b4' : 'How to play \u25be';
  };

  var badge = document.getElementById('axh-live-badge');
  badge.textContent = (!state.allComplete && state.unlocked && state.viewIndex === state.puzzleIndex) ? 'This pond is unlocked \u2014 ready when you are!' : '';
}

function renderPlayArea(){
  var area = document.getElementById('axh-play-area');
  if (state.allComplete){
    area.innerHTML = '<div class="axh-done">You\u2019ve solved every pond! \ud83c\udf89 More puzzles coming soon.</div>';
    return;
  }

  var navHtml =
    '<div class="axh-nav-row">' +
      '<button type="button" id="axh-prev-btn" class="axh-nav-btn">\u25c0 Prev pond</button>' +
      '<span>Viewing pond ' + (state.viewIndex + 1) + ' of ' + (state.puzzleIndex + 1) + ' unlocked</span>' +
      '<button type="button" id="axh-next-btn-nav" class="axh-nav-btn">Next pond \u25b6</button>' +
    '</div>';

  var pastNoticeHtml = state.isPast
    ? '<div class="axh-past-notice">You\u2019re revisiting an earlier pond. New words here still count toward your lifetime total, but only your current pond affects unlocking. <a id="axh-jump-current">Jump to current pond \u2192</a></div>'
    : '';

  area.innerHTML =
    navHtml + pastNoticeHtml +
    '<div id="axh-message" class="axh-message"></div>' +
    '<div id="axh-word-display" class="axh-word-display"></div>' +
    '<div class="axh-hive-wrap" id="axh-hive"></div>' +
    '<div class="axh-controls">' +
      '<button type="button" id="axh-delete-btn" class="axh-btn">\u232B Delete</button>' +
      '<button type="button" id="axh-shuffle-btn" class="axh-btn">\u21bb Shuffle</button>' +
      '<button type="button" id="axh-submit-btn" class="axh-btn axh-submit">Enter</button>' +
    '</div>' +
    '<div class="axh-found-summary">Score: <strong id="axh-score">' + state.score + '</strong> &nbsp;|&nbsp; Words found: <strong id="axh-found-count">' + (state.foundWords || []).length + '</strong></div>' +
    '<div class="axh-progress-wrap">' +
      '<div class="axh-progress-labels"><span>Pond progress</span><span id="axh-progress-fraction"></span></div>' +
      '<div class="axh-progress-track"><div id="axh-progress-marker" class="axh-progress-marker"></div><div id="axh-progress-fill" class="axh-progress-fill"></div></div>' +
      '<div id="axh-progress-note" class="axh-progress-note"></div>' +
      '<div id="axh-longest-badge" class="axh-longest-badge"></div>' +
    '</div>' +
    '<div class="axh-row"><button type="button" id="axh-found-toggle" class="axh-locked-toggle">Show found words &#9662;</button></div>' +
    '<div id="axh-found-list" class="axh-found-list" style="display:none;"></div>' +
    '<div id="axh-pangram-area"></div>' +
    '<div id="axh-pass-offer" class="axh-pass-offer"></div>';

  currentWord = "";
  buildHive();
  renderFoundList();
  renderPangramArea();
  renderProgress();

  document.getElementById('axh-prev-btn').disabled = state.viewIndex <= 0;
  document.getElementById('axh-prev-btn').onclick = function(){ loadState(state.viewIndex - 1); };
  document.getElementById('axh-next-btn-nav').disabled = state.viewIndex >= state.puzzleIndex;
  document.getElementById('axh-next-btn-nav').onclick = function(){ loadState(state.viewIndex + 1); };
  var jump = document.getElementById('axh-jump-current');
  if (jump) jump.onclick = function(){ loadState(state.puzzleIndex); };

  document.getElementById('axh-delete-btn').onclick = function(){ currentWord = currentWord.slice(0,-1); refreshWordDisplay(); };
  document.getElementById('axh-shuffle-btn').onclick = shuffleOuter;
  document.getElementById('axh-submit-btn').onclick = submitWord;
  document.getElementById('axh-found-toggle').onclick = function(){
    var list = document.getElementById('axh-found-list');
    var btn = document.getElementById('axh-found-toggle');
    var hidden = list.style.display === 'none';
    list.style.display = hidden ? '' : 'none';
    btn.textContent = hidden ? 'Hide found words \u25b4' : 'Show found words \u25be';
  };

  refreshWordDisplay();
}

function buildHive(){
  var wrap = document.getElementById('axh-hive');
  wrap.innerHTML = '';
  var positions = ['axh-hex-top', 'axh-hex-ur', 'axh-hex-lr', 'axh-hex-bottom', 'axh-hex-ll', 'axh-hex-ul'];

  var center = document.createElement('div');
  center.className = 'axh-hex axh-center axh-hex-center';
  center.textContent = state.center;
  center.onclick = function(){ pressLetter(state.center); };
  wrap.appendChild(center);

  outerOrder.forEach(function(letter, i){
    var hex = document.createElement('div');
    hex.className = 'axh-hex ' + positions[i];
    hex.textContent = letter;
    hex.onclick = function(){ pressLetter(letter); };
    wrap.appendChild(hex);
  });
}

function shuffleOuter(){
  for (var i = outerOrder.length - 1; i > 0; i--){
    var j = Math.floor(Math.random() * (i + 1));
    var tmp = outerOrder[i]; outerOrder[i] = outerOrder[j]; outerOrder[j] = tmp;
  }
  buildHive();
}

function pressLetter(letter){
  currentWord += letter;
  refreshWordDisplay();
  var hexes = document.querySelectorAll('.axh-hex');
  hexes.forEach(function(h){ if (h.textContent === letter){ h.classList.remove('axh-pop'); void h.offsetWidth; h.classList.add('axh-pop'); } });
}

function refreshWordDisplay(){
  var el = document.getElementById('axh-word-display');
  if (!el) return;
  var html = '';
  currentWord.split('').forEach(function(ch){
    html += (ch === state.center) ? '<span class="axh-center-typed">' + ch + '</span>' : ch;
  });
  el.innerHTML = html;
}

function message(text, good){
  var m = document.getElementById('axh-message');
  if (!m) return;
  m.textContent = text;
  m.className = 'axh-message' + (good === true ? ' good' : good === false ? ' bad' : '');
}

function renderFoundList(){
  var list = document.getElementById('axh-found-list');
  if (!list) return;
  list.innerHTML = '';
  (state.foundWords || []).forEach(function(f){
    var chip = document.createElement('span');
    chip.className = 'axh-found-chip' + (f.pangram ? ' axh-pangram-chip' : (f.bonus ? ' axh-bonus-chip' : ''));
    chip.textContent = (f.bonus && !f.pangram ? '\u2605 ' : '') + f.word + ' (' + f.score + ')';
    list.appendChild(chip);
  });
}

function renderProgress(){
  var fill = document.getElementById('axh-progress-fill');
  var frac = document.getElementById('axh-progress-fraction');
  var marker = document.getElementById('axh-progress-marker');
  var note = document.getElementById('axh-progress-note');
  var badge = document.getElementById('axh-longest-badge');
  if (!fill || !frac || !marker || !note || !badge) return;

  var score = state.score || 0;
  var max = state.maxScore || 0;
  var required = state.requiredScore || 0;
  var pct = max > 0 ? Math.min(100, (score / max) * 100) : 0;
  var markerPct = max > 0 ? Math.min(100, (required / max) * 100) : 0;
  var allFound = max > 0 && score >= max;

  frac.textContent = score + ' / ' + max + ' points';
  fill.style.width = pct + '%';
  marker.style.left = markerPct + '%';
  fill.classList.toggle('axh-unlocked', !!state.unlocked && !allFound);
  fill.classList.toggle('axh-gold', allFound);

  if (state.viewIndex !== state.puzzleIndex){
    note.textContent = allFound ? 'Every word in this pond has been found!' : 'This pond is already unlocked \u2014 keep hunting for bonus words.';
  } else if (allFound){
    note.textContent = '\ud83c\udf89 Every word in this pond has been found!';
  } else if (state.unlocked){
    note.textContent = 'Unlocked! ' + (max - score) + ' more points possible in this pond.';
  } else {
    note.textContent = (required - score) + ' more point' + ((required - score) === 1 ? '' : 's') + ' to unlock the next pond' + (state.foundLongest ? ' (discount already applied)' : '') + '.';
  }

  renderPassOffer(score, required, allFound);

  if (state.foundLongest){
    badge.textContent = '\u2b50 Longest word in this pond \u2014 found!';
    badge.classList.add('axh-found');
    badge.classList.toggle('axh-gold', allFound);
  } else {
    badge.textContent = '\u2b50 Find this pond\u2019s longest word for a discount + a highlight here';
    badge.classList.remove('axh-found', 'axh-gold');
  }
}

/* Shown only to a student who is short of the threshold on their active
   pond and has no pass. A held credit is offered as a button; otherwise the
   passes that exist in the store are offered as links. */
function renderPassOffer(score, required, allFound){
  var host = document.getElementById('axh-pass-offer');
  if (!host) return;

  var eligible = !state.hasPass && !allFound &&
                 state.viewIndex === state.puzzleIndex &&
                 !state.unlocked && score < required;
  if (!eligible){ host.innerHTML = ''; return; }

  var urls = state.passUrls || {};
  var html = '';

  if (state.skips > 0){
    html += '<button type="button" id="axh-skip-btn" class="axh-btn">' +
              'Open the next pond now (' + state.skips + ' left)' +
            '</button>';
  } else {
    var buys = [];
    if (urls.skip)  buys.push(['skip',  'Next pond']);
    if (urls.month) buys.push(['month', '30-day pass']);
    if (urls.year)  buys.push(['year',  'Annual pass']);
    if (urls.life)  buys.push(['life',  'Unlock everything']);
    if (buys.length){
      html += '<p class="axh-pass-line">In a hurry? Open the next pond without waiting:</p>' +
              '<div class="axh-pass-row">' + buys.map(function(b){
                return '<a class="axh-btn axh-pass-btn" href="' + encodeURI(urls[b[0]]) + '">' + b[1] + '</a>';
              }).join('') + '</div>';
    }
  }

  host.innerHTML = html;

  var skipBtn = document.getElementById('axh-skip-btn');
  if (skipBtn){
    skipBtn.onclick = function(){
      skipBtn.disabled = true;
      api('ascend_axh_advance_puzzle', {use_skip: 1}).then(function(resp){
        if (!resp.success){ skipBtn.disabled = false; message('Could not open the next pond.', false); return; }
        var d = resp.data;
        if (d.allComplete){ state.puzzleIndex = d.puzzleIndex; state.allComplete = true; renderPlayArea(); return; }
        loadState(d.puzzleIndex);
      });
    };
  }
}

function renderPangramArea(){
  var area = document.getElementById('axh-pangram-area');
  if (!area) return;
  if (!state.unlocked || state.viewIndex !== state.puzzleIndex){ area.innerHTML = ''; return; }
  area.innerHTML =
    '<div class="axh-pangram-banner"><p>\ud83c\udf89 Points threshold met! Keep hunting this pond for bonus words, or move on whenever you\'re ready.</p>' +
    '<button type="button" id="axh-next-btn" class="axh-btn">Next pond \u2192</button></div>';
  document.getElementById('axh-next-btn').onclick = advancePuzzle;
}

function submitWord(){
  if (!currentWord){ message('Type a word first.', false); return; }
  var word = currentWord;

  api('ascend_axh_submit_word', {word: word, puzzle_index: state.viewIndex}).then(function(resp){
    if (!resp.success){
      var reasons = {
        too_short: 'Words need 4 or more letters.',
        bad_chars: 'Letters only.',
        bad_letters: 'You can only use the 7 letters in the pond.',
        missing_center: 'That word is missing the center letter.',
        already_found: 'You already found that word.',
        not_in_list: 'That one isn\u2019t in our dictionary.',
        all_complete: 'You\u2019ve solved every pond already!',
        no_puzzle: 'No pond is available right now.',
        locked_puzzle: 'That pond isn\u2019t unlocked yet.',
      };
      message(reasons[(resp.data || {}).reason] || 'Something went wrong.', false);
      currentWord = '';
      refreshWordDisplay();
      return;
    }

    var d = resp.data;
    var justFoundLongest = !state.foundLongest && d.foundLongest;
    var justUnlocked = !state.unlocked && d.unlocked;
    state.foundWords.push({word: d.word, score: d.wordScore, pangram: d.pangram, bonus: d.bonus});
    state.score = d.score;
    state.maxScore = d.maxScore;
    state.requiredScore = d.requiredScore;
    state.unlocked = d.unlocked;
    state.rank = d.rank;
    state.pangramFound = d.pangramFound;
    state.streak = d.streak;
    state.lifetimeWords = d.lifetimeWords;
    state.totalWords = d.totalWords;
    state.longestLen = d.longestLen;
    state.foundLongest = d.foundLongest;

    currentWord = '';
    refreshWordDisplay();
    document.getElementById('axh-score').textContent = state.score;
    document.getElementById('axh-found-count').textContent = state.foundWords.length;
    document.getElementById('axh-streak').textContent = state.streak;
    document.getElementById('axh-rank').textContent = state.rank;
    renderFoundList();
    renderPangramArea();
    renderProgress();

    if (justUnlocked){
      message('\ud83d\udd13 Unlocked! +' + d.wordScore + ' point' + (d.wordScore === 1 ? '' : 's'), true);
    } else if (justFoundLongest && !d.pangram){
      message('\u2b50 That\u2019s the longest word in this pond! +' + d.wordScore + ' point' + (d.wordScore === 1 ? '' : 's'), true);
    } else if (d.pangram){
      message('\ud83c\udf89 PANGRAM! +' + d.wordScore + ' point' + (d.wordScore === 1 ? '' : 's'), true);
    } else if (d.bonus){
      message('\u2605 Bonus word! +' + d.wordScore + ' point' + (d.wordScore === 1 ? '' : 's'), true);
    } else {
      message('Nice! +' + d.wordScore + ' point' + (d.wordScore === 1 ? '' : 's'), true);
    }
  });
}

function advancePuzzle(){
  api('ascend_axh_advance_puzzle', {}).then(function(resp){
    if (!resp.success){ message('You need more points before moving on.', false); return; }
    var d = resp.data;
    if (d.allComplete){
      state.puzzleIndex = d.puzzleIndex;
      state.allComplete = true;
      renderPlayArea();
      return;
    }
    loadState(d.puzzleIndex);
  });
}

document.addEventListener('keydown', function(e){
  if (!state || state.allComplete) return;
  var tag = (document.activeElement || {}).tagName;
  if (tag === 'INPUT' || tag === 'TEXTAREA') return;
  if (e.key === 'Enter'){ submitWord(); return; }
  if (e.key === 'Backspace'){ currentWord = currentWord.slice(0,-1); refreshWordDisplay(); return; }
  if (e.key === ' '){ e.preventDefault(); shuffleOuter(); return; }
  var letter = (e.key || '').toUpperCase();
  if (/^[A-Z]$/.test(letter) && state.center && [state.center].concat(state.outer || []).indexOf(letter) !== -1){
    pressLetter(letter);
  }
});

loadState();
})();
</script>
    <?php
    return ob_get_clean();
});

/* ============================================================
   ADMIN
   ============================================================ */
add_action('admin_menu', function () {
    add_submenu_page('ascend-games', 'Hex-a-lotl – Students', 'Hex-a-lotl: Students', 'manage_options', 'ascend-axh', 'ascend_axh_admin_students_page');
    add_submenu_page('ascend-games', 'Hex-a-lotl – Puzzles & Settings', 'Hex-a-lotl: Puzzles & Settings', 'manage_options', 'ascend-axh-settings', 'ascend_axh_admin_settings_page');
});

function ascend_axh_admin_students_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['axh_reset_user']) && check_admin_referer('axh_reset_user')) {
        ascend_axh_reset_user((int) $_POST['axh_reset_user']);
        echo '<div class="notice notice-success"><p>All progress reset for that student.</p></div>';
    }
    if (isset($_POST['axh_reset_current']) && check_admin_referer('axh_reset_current')) {
        ascend_axh_reset_current_puzzle((int) $_POST['axh_reset_current']);
        echo '<div class="notice notice-success"><p>Current pond’s found words cleared for that student. Overall progress, streak, and solved-pond history were left untouched.</p></div>';
    }
    if (isset($_POST['axh_reset_all']) && check_admin_referer('axh_reset_all')) {
        $users = get_users(['fields' => ['ID']]);
        foreach ($users as $u) ascend_axh_reset_user($u->ID);
        echo '<div class="notice notice-success"><p><strong>Every student’s progress has been reset.</strong> Puzzles and settings are untouched — only per-student progress, streaks, and history were cleared.</p></div>';
    }

    echo '<div class="wrap"><h1>Hex-a-lotl — student roster</h1>';

    echo '<form method="post" onsubmit="return confirm(\'Reset EVERY student\u2019s progress, streak, and history? This cannot be undone.\');" style="margin-bottom:14px;">';
    wp_nonce_field('axh_reset_all');
    echo '<button type="submit" name="axh_reset_all" class="button button-secondary" style="color:#a00;border-color:#a00;">Reset game database (reset ALL students)</button>';
    echo '</form>';

    echo '<table class="widefat striped"><thead><tr><th>Student</th><th>Pond</th><th>Rank</th><th>Score / Needed</th><th>Words found (current)</th><th>Unlocked?</th><th>Lifetime words</th><th>Streak</th><th>Last played</th><th>Actions</th></tr></thead><tbody>';

    $puzzles = ascend_axh_get_puzzles();
    $total = count($puzzles);
    $users = get_users(['fields' => ['ID', 'display_name']]);
    $shown = 0;

    foreach ($users as $u) {
        $progress = ascend_axh_progress($u->ID);
        $streak   = (int) get_user_meta($u->ID, 'axh_streak', true);
        $lifetime = (int) get_user_meta($u->ID, 'axh_lifetime_words', true);
        $last     = get_user_meta($u->ID, 'axh_last_play_date', true);
        if ($progress === 0 && $streak === 0 && $lifetime === 0 && !$last) continue;
        $shown++;

        $puzzle = ascend_axh_puzzle_at($progress);
        $found = ascend_axh_words_for($u->ID, $progress);
        $score = $puzzle ? ascend_axh_score_words($found, $puzzle) : 0;
        $found_longest = $puzzle ? ascend_axh_found_longest_in($found, $puzzle) : false;
        $required = $puzzle ? ascend_axh_required_score($puzzle, $found_longest) : 0;
        $unlocked = $puzzle ? ($score >= $required) : true;
        $career = ascend_axh_career_stats($u->ID);
        $rank = ascend_axh_rank_for($career['score'], $career['max'], $unlocked);
        $pond_label = $puzzle ? ($progress + 1) . ' / ' . $total : 'All complete';
        $score_label = $puzzle ? $score . ' / ' . $required . ($found_longest ? ' (discount applied)' : '') : '—';

        echo '<tr><td>' . esc_html($u->display_name) . '</td><td>' . esc_html($pond_label) . '</td><td>' . esc_html($rank) . '</td><td>' . esc_html($score_label) . '</td><td>' . count($found) . '</td><td>' . ($unlocked ? 'Yes' : 'No') . '</td><td>' . $lifetime . '</td><td>' . $streak . '</td><td>' . ($last ? esc_html($last) : '—') . '</td><td>';

        echo '<form method="post" onsubmit="return confirm(\'Clear the CURRENT pond\u2019s found words for ' . esc_js($u->display_name) . '? Overall progress and streak stay as-is.\');" style="margin:0 0 4px;">';
        wp_nonce_field('axh_reset_current');
        echo '<input type="hidden" name="axh_reset_current" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small">Reset current pond</button></form>';

        echo '<form method="post" onsubmit="return confirm(\'Reset ALL progress for ' . esc_js($u->display_name) . '? This cannot be undone.\');" style="margin:0;">';
        wp_nonce_field('axh_reset_user');
        echo '<input type="hidden" name="axh_reset_user" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small" style="color:#a00;">Reset all progress</button></form>';

        echo '</td></tr>';
    }
    if (!$shown) echo '<tr><td colspan="10">No students have played yet.</td></tr>';
    echo '</tbody></table></div>';
}

function ascend_axh_admin_settings_page() {
    if (!current_user_can('manage_options')) return;
    wp_enqueue_media();

    if (isset($_POST['ascend_axh_save']) && check_admin_referer('ascend_axh_settings')) {
        $incoming = isset($_POST['puzzles']) && is_array($_POST['puzzles']) ? $_POST['puzzles'] : [];
        $clean = [];
        $dropped_total = 0;

        /* The wider word pool is not editable on this screen, so carry it
           across the save keyed by the pond's letters. Without this, one
           save from wp-admin would silently wipe every pool and the game
           would drop back to bonus words only. A pond whose letters were
           just changed has no matching pool and correctly starts empty. */
        $pool_by_letters = [];
        foreach (ascend_axh_get_puzzles() as $existing) {
            $key = $existing['center'] . '|' . implode('', $existing['outer']);
            $pool_by_letters[$key] = ascend_axh_pool($existing);
        }

        foreach ($incoming as $p) {
            $center = strtoupper(substr(trim($p['center'] ?? ''), 0, 1));
            if ($center === '' || !preg_match('/^[A-Z]$/', $center)) continue;

            $outer_raw = strtoupper($p['outer'] ?? '');
            $outer = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\s]+/', $outer_raw)), function ($l) {
                return preg_match('/^[A-Z]$/', $l);
            })));
            $outer = array_values(array_diff($outer, [$center]));
            if (count($outer) < 6) continue;
            $outer = array_slice($outer, 0, 6);

            $puzzle_letters = array_merge([$center], $outer);
            $words_raw = $p['words'] ?? '';
            $candidate_words = array_values(array_unique(array_filter(array_map('trim', explode("\n", strtoupper($words_raw))))));

            $valid_words = [];
            foreach ($candidate_words as $w) {
                if (strlen($w) < 4) continue;
                if (strpos($w, $center) === false) continue;
                $bad = false;
                foreach (str_split($w) as $ch) {
                    if (!in_array($ch, $puzzle_letters, true)) { $bad = true; break; }
                }
                if ($bad) continue;
                $valid_words[] = $w;
            }
            $dropped_total += (count($candidate_words) - count($valid_words));

            if (!empty($valid_words)) {
                $key  = $center . '|' . implode('', $outer);
                $pool = $pool_by_letters[$key] ?? [];
                $clean[] = ['center' => $center, 'outer' => $outer, 'words' => $valid_words, 'pool' => $pool];
            }
        }

        if (!empty($clean)) {
            update_option('ascend_axh_puzzles', $clean);
            // A brand-new pond, or one whose letters changed, arrives with an
            // empty pool; fill it now (forced when the admin asked for a full
            // rebuild) so it never plays a single round on bonus words alone.
            $rebuilt = ascend_axh_rebuild_pools(!empty($_POST['rebuild_pools']));
            if ($rebuilt > 0) {
                echo '<div class="notice notice-success"><p>Word pool rebuilt for ' . $rebuilt . ' pond' . ($rebuilt === 1 ? '' : 's') . '.</p></div>';
            }
        }
        update_option('ascend_axh_mascot_url', esc_url_raw($_POST['mascot_url'] ?? ''));

        $unlock_pct = (int) ($_POST['unlock_pct'] ?? 50);
        update_option('ascend_axh_unlock_pct', ($unlock_pct > 0 && $unlock_pct <= 100) ? $unlock_pct : 50);

        $discount_pct = (int) ($_POST['longest_discount_pct'] ?? 10);
        update_option('ascend_axh_longest_discount_pct', ($discount_pct >= 0 && $discount_pct <= 100) ? $discount_pct : 10);

        echo '<div class="notice notice-success"><p>Saved. ' . count($clean) . ' pond' . (count($clean) === 1 ? '' : 's') . ' saved.' . ($dropped_total > 0 ? ' ' . $dropped_total . ' word' . ($dropped_total === 1 ? ' was' : 's were') . ' dropped for not fitting that pond’s letters (too short, missing the center letter, or using a letter outside the 7).' : '') . '</p></div>';
    }

    $puzzles = ascend_axh_get_puzzles();
    $mascot  = get_option('ascend_axh_mascot_url', '');
    $unlock_pct = ascend_axh_unlock_pct();
    $discount_pct = ascend_axh_longest_discount_pct();

    echo '<div class="wrap"><h1>Hex-a-lotl — puzzles &amp; settings</h1>';
    $pool_total = 0; $pool_ponds = 0;
    foreach ($puzzles as $pz) { $n = count(ascend_axh_pool($pz)); $pool_total += $n; if ($n > 0) $pool_ponds++; }

    echo '<p>Each pond needs a center letter, exactly 6 other letters, and a list of <strong>bonus words</strong> &mdash; one per line. Bonus words are the pond&rsquo;s yardstick: its max score, the unlock threshold, the longest-word target and the career rank are all measured against these alone.</p>';
    echo '<p>Alongside them each pond carries a <strong>word pool</strong> &mdash; ordinary English words that fit the same 7 letters. Pool words are accepted and score their length, but they never raise the pond&rsquo;s max score, so finding them moves a student toward the same threshold faster. The pool is not edited here and is preserved when you save.</p>';
    echo '<p class="description">Pool coverage: <strong>' . number_format($pool_total) . '</strong> words across <strong>' . $pool_ponds . '</strong> of ' . count($puzzles) . ' ponds. A pond you add here, or whose letters you change, starts with no pool and accepts its bonus words only until one is generated.</p>';
    echo '<p class="description">Neither bank reaches the browser &mdash; only the letters do.</p>';
    echo '<form method="post">';
    wp_nonce_field('ascend_axh_settings');

    echo '<h2>Pond unlock rules</h2>';
    echo '<p class="description">Students can freely revisit any pond they’ve already unlocked, but the next NEW pond stays locked until their active pond’s score meets this threshold.</p>';
    echo '<p><label><strong>Points needed to unlock the next pond</strong> (% of that pond’s max possible score)<br>';
    echo '<input type="number" name="unlock_pct" min="1" max="100" value="' . esc_attr($unlock_pct) . '" style="width:80px;"> %</label></p>';
    echo '<p><label><strong>Discount for finding the longest word early</strong> (percentage points off the threshold above, if the longest word is found before reaching it)<br>';
    echo '<input type="number" name="longest_discount_pct" min="0" max="100" value="' . esc_attr($discount_pct) . '" style="width:80px;"> percentage points</label></p>';

    echo '<h2 style="margin-top:28px;">Word pool</h2>';
    echo '<p><label><input type="checkbox" name="rebuild_pools" value="1"> <strong>Rebuild every pond&rsquo;s word pool from the dictionary file on save</strong></label><br>';
    echo '<span class="description">Normally unnecessary &mdash; missing pools are filled automatically. Tick it after replacing hex-a-lotl-dictionary.txt with a new word list.</span></p>';

    echo '<h2 style="margin-top:28px;">Mascot image (optional)</h2>';
    echo '<input type="hidden" id="axh_mascot_url" name="mascot_url" value="' . esc_attr($mascot) . '">';
    echo '<div id="axh_mascot_preview">' . ($mascot ? '<img src="' . esc_url($mascot) . '" style="max-width:150px;display:block;margin-bottom:8px;">' : '<p style="color:#777;">No image set.</p>') . '</div>';
    echo '<button type="button" class="button" id="axh_mascot_btn">Choose image</button> <button type="button" class="button" id="axh_mascot_clear">Remove</button>';

    echo '<h2 style="margin-top:28px;">Ponds</h2>';
    echo '<div id="axh-puzzle-list"></div>';
    echo '<p><button type="button" class="button" id="axh-add-puzzle">+ Add another pond</button></p>';

    echo '<p style="margin-top:16px;"><button type="submit" name="ascend_axh_save" class="button button-primary">Save changes</button></p></form></div>';

    echo '<template id="axh-puzzle-template">' . ascend_axh_puzzle_block_html('__INDEX__', '', [], '') . '</template>';

    echo '<script>
    (function(){
      var list = document.getElementById("axh-puzzle-list");
      var puzzles = ' . wp_json_encode(array_map(function ($p) {
          return ['center' => $p['center'], 'outer' => implode(', ', $p['outer']), 'words' => implode("\n", $p['words'])];
      }, $puzzles)) . ';
      var tplHtml = document.getElementById("axh-puzzle-template").innerHTML;
      var count = 0;

      function addBlock(data){
        var idx = count++;
        var html = tplHtml.split("__INDEX__").join(idx);
        var div = document.createElement("div");
        div.innerHTML = html;
        var block = div.firstElementChild;
        if (data){
          block.querySelector(".axh-center-input").value = data.center || "";
          block.querySelector(".axh-outer-input").value = data.outer || "";
          block.querySelector(".axh-words-input").value = data.words || "";
        }
        block.querySelector(".axh-remove-btn").addEventListener("click", function(){ block.remove(); });
        list.appendChild(block);
      }

      document.getElementById("axh-add-puzzle").addEventListener("click", function(){ addBlock(null); });
      puzzles.forEach(function(p){ addBlock(p); });
      if (!puzzles.length) addBlock(null);

      document.getElementById("axh_mascot_btn").addEventListener("click", function(e){
        e.preventDefault();
        var frame = wp.media({title:"Select axolotl artwork", button:{text:"Use this image"}, multiple:false});
        frame.on("select", function(){
          var att = frame.state().get("selection").first().toJSON();
          document.getElementById("axh_mascot_url").value = att.url;
          document.getElementById("axh_mascot_preview").innerHTML = "<img src=\"" + att.url + "\" style=\"max-width:150px;display:block;margin-bottom:8px;\">";
        });
        frame.open();
      });
      document.getElementById("axh_mascot_clear").addEventListener("click", function(e){
        e.preventDefault();
        document.getElementById("axh_mascot_url").value = "";
        document.getElementById("axh_mascot_preview").innerHTML = "<p style=\"color:#777;\">No image set.</p>";
      });
    })();
    </script>';
}

function ascend_axh_puzzle_block_html($idx, $center, $outer, $words) {
    ob_start();
    ?>
<div class="axh-puzzle-block" style="border:1px solid #ddd;border-radius:8px;padding:14px;margin-bottom:14px;background:#fff;max-width:700px;">
  <p>
    <label style="margin-right:16px;"><strong>Center letter</strong><br>
      <input type="text" class="axh-center-input" name="puzzles[<?php echo esc_attr($idx); ?>][center]" maxlength="1" style="width:50px;text-transform:uppercase;text-align:center;font-weight:700;" value="<?php echo esc_attr($center); ?>">
    </label>
    <label><strong>Other 6 letters</strong> (comma or space separated)<br>
      <input type="text" class="axh-outer-input" name="puzzles[<?php echo esc_attr($idx); ?>][outer]" style="width:220px;text-transform:uppercase;" placeholder="A, D, P, O, L, E" value="<?php echo esc_attr(implode(', ', $outer)); ?>">
    </label>
  </p>
  <p style="margin:0;"><strong>Valid words</strong> (one per line — 4+ letters, must include the center letter, must only use these 7 letters; anything else is dropped automatically on save)</p>
  <textarea class="axh-words-input" name="puzzles[<?php echo esc_attr($idx); ?>][words]" rows="6" style="width:100%;margin-top:6px;"><?php echo esc_textarea($words); ?></textarea>
  <p style="margin:10px 0 0;"><button type="button" class="button button-small axh-remove-btn" style="color:#a00;">Remove this pond</button></p>
</div>
    <?php
    return ob_get_clean();
}


/* ======================================================================
   CROSS-A-LOTL  (prefix axx_)
   ====================================================================== */

define('ASCEND_AXX_VERSION', '1.0.0');
define('ASCEND_AXX_ROWS', 6);
define('ASCEND_AXX_COLS', 8);

/* ============================================================
   DESIGN DECISIONS — adapted for the Strands genre
   (This genre has no per-guess penalty or loss state — unlike
   Guess-a-lotl/Connect-a-lotl, a wrong trace just says "not it"
   with no limit. To keep individualized daily pacing consistent
   across all three games without inventing an artificial penalty
   that doesn't belong in this genre, the daily gate here works
   differently:)
   - All 16-48 letters are ALWAYS visible — nothing about the
     grid is secret. Only which connected paths spell the theme
     words is secret.
   - Within a puzzle, guesses and hints are unlimited (hints are
     capped per-puzzle by an admin setting, not per-day).
   - A puzzle can span multiple days if a student doesn't finish
     it in one sitting — progress is never lost or reset by the
     calendar.
   - The daily pacing rule: once a student FINISHES a puzzle, the
     next puzzle in their bank is visible (so they can see the new
     theme/grid) but not playable until the next calendar day —
     this is what keeps it to "one new theme a day" like the other
     two games, without capping tries inside a puzzle that's
     already in progress.
   - Spangrams must be EXACTLY 6 or 8 letters (this plugin's
     auto-layout generator places the spangram in one guaranteed-
     to-fit straight-ish line spanning the grid's short or long
     axis; that requires the length to match the axis exactly).
   ============================================================ */

/* ============================================================
   DEFAULT PUZZLE BANK — 6 starter puzzles, axolotl/STEM themed.
   Each entry is just the *content* (theme, spangram, words) —
   the actual letter grid + paths are generated once and cached
   alongside it (see ascend_axx_build_puzzle_bank()).
   ============================================================ */
function ascend_axx_default_puzzle_sources() {
    return [
        ['theme' => 'What axolotls can regrow', 'spangram' => 'REGROWTH', 'words' => ['LIMBS', 'TAIL', 'HEART', 'SPINE', 'SKIN']],
        ['theme' => 'Ways to stay hidden',       'spangram' => 'MASKED',   'words' => ['BLEND', 'CLOAK', 'HIDE', 'TRICK', 'MIMIC']],
        ['theme' => 'Science lab gear',          'spangram' => 'CHEMICAL', 'words' => ['BEAKER', 'FLASK', 'BURNER', 'GOGGLES', 'PIPETTE']],
        ['theme' => 'Out in space',              'spangram' => 'GALAXY',   'words' => ['COMET', 'STAR', 'ORBIT', 'MOON', 'ROCKET']],
        ['theme' => 'Weather watch',             'spangram' => 'FORECAST', 'words' => ['STORM', 'CLOUD', 'BREEZE', 'FROST', 'HUMID']],
        ['theme' => 'Math class',                'spangram' => 'EQUATION', 'words' => ['ANGLE', 'GRAPH', 'RATIO', 'SOLVE', 'SHAPE']],
    ];
}

register_activation_hook(__FILE__, function () {
    if (!get_option('ascend_axx_puzzles')) {
        $built = ascend_axx_build_puzzle_bank(ascend_axx_default_puzzle_sources(), []);
        add_option('ascend_axx_puzzles', $built['puzzles']);
    }
    if (get_option('ascend_axx_mascot_url', null) === null) {
        add_option('ascend_axx_mascot_url', '');
    }
    if (!get_option('ascend_axx_max_hints')) {
        add_option('ascend_axx_max_hints', 3);
    }
});

/* ============================================================
   GRID GENERATION
   ============================================================ */
function ascend_axx_content_hash($source) {
    $words = array_map('strtoupper', $source['words']);
    sort($words);
    return md5(strtoupper($source['theme']) . '|' . strtoupper($source['spangram']) . '|' . implode(',', $words));
}

/* Places the spangram in a single guaranteed-to-fit path spanning the
   grid's long axis (horizontal, needs length === COLS) or short axis
   (vertical, needs length === ROWS). Because the "forward" coordinate
   always advances by exactly 1 each step, cells can never repeat. */
function ascend_axx_place_spangram($word, $rows, $cols) {
    $len = strlen($word);
    $orientations = [];
    if ($len === $cols) $orientations[] = 'h';
    if ($len === $rows) $orientations[] = 'v';
    if (empty($orientations)) return null;

    $orientation = $orientations[array_rand($orientations)];
    $path = [];

    if ($orientation === 'h') {
        $row = mt_rand(0, $rows - 1);
        for ($i = 0; $i < $len; $i++) {
            if ($i > 0) {
                $choices = [];
                if ($row > 0) $choices[] = $row - 1;
                $choices[] = $row;
                if ($row < $rows - 1) $choices[] = $row + 1;
                $row = $choices[array_rand($choices)];
            }
            $path[] = [$row, $i];
        }
    } else {
        $col = mt_rand(0, $cols - 1);
        for ($i = 0; $i < $len; $i++) {
            if ($i > 0) {
                $choices = [];
                if ($col > 0) $choices[] = $col - 1;
                $choices[] = $col;
                if ($col < $cols - 1) $choices[] = $col + 1;
                $col = $choices[array_rand($choices)];
            }
            $path[] = [$i, $col];
        }
    }

    return $path;
}

/* Random self-avoiding walk for a regular theme word. Allowed to cross
   an already-occupied cell only when the existing letter matches the
   letter needed at that step (a genuine crossing, same as a real word
   search / Strands grid). Retries with a fresh random start+walk on
   every failed attempt. */
function ascend_axx_place_word($word, $occupied, $rows, $cols, $max_attempts = 250) {
    $len = strlen($word);
    $all_dirs = [];
    for ($dr = -1; $dr <= 1; $dr++) {
        for ($dc = -1; $dc <= 1; $dc++) {
            if ($dr === 0 && $dc === 0) continue;
            $all_dirs[] = [$dr, $dc];
        }
    }

    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $starts = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $key = $r . ',' . $c;
                if (!isset($occupied[$key]) || $occupied[$key] === $word[0]) $starts[] = [$r, $c];
            }
        }
        if (empty($starts)) return null;

        $start = $starts[array_rand($starts)];
        $path = [$start];
        $used = [$start[0] . ',' . $start[1] => true];
        $ok = true;

        for ($i = 1; $i < $len; $i++) {
            $last = $path[$i - 1];
            $dirs = $all_dirs;
            shuffle($dirs);
            $next = null;
            foreach ($dirs as $d) {
                $r = $last[0] + $d[0];
                $c = $last[1] + $d[1];
                if ($r < 0 || $r >= $rows || $c < 0 || $c >= $cols) continue;
                $key = $r . ',' . $c;
                if (isset($used[$key])) continue;
                if (isset($occupied[$key]) && $occupied[$key] !== $word[$i]) continue;
                $next = [$r, $c];
                break;
            }
            if ($next === null) { $ok = false; break; }
            $path[] = $next;
            $used[$next[0] . ',' . $next[1]] = true;
        }

        if ($ok) return $path;
    }
    return null;
}

function ascend_axx_generate_layout($spangram, $words, $rows = ASCEND_AXX_ROWS, $cols = ASCEND_AXX_COLS, $outer_attempts = 30) {
    $spangram = strtoupper($spangram);
    $words = array_map('strtoupper', $words);

    for ($try = 0; $try < $outer_attempts; $try++) {
        $occupied = []; // "r,c" => letter
        $paths = [];

        $sp_path = ascend_axx_place_spangram($spangram, $rows, $cols);
        if ($sp_path === null) return false; // length mismatch — no point retrying

        foreach ($sp_path as $i => $cell) $occupied[$cell[0] . ',' . $cell[1]] = $spangram[$i];
        $paths[$spangram] = $sp_path;

        $ordered = $words;
        usort($ordered, function ($a, $b) { return strlen($b) - strlen($a); });

        $failed = false;
        foreach ($ordered as $w) {
            $path = ascend_axx_place_word($w, $occupied, $rows, $cols);
            if ($path === null) { $failed = true; break; }
            foreach ($path as $i => $cell) $occupied[$cell[0] . ',' . $cell[1]] = $w[$i];
            $paths[$w] = $path;
        }
        if ($failed) continue;

        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $grid = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $key = $r . ',' . $c;
                $grid[$r][$c] = $occupied[$key] ?? $letters[mt_rand(0, 25)];
            }
        }

        return ['grid' => $grid, 'paths' => $paths];
    }
    return false;
}

/* Rebuilds the puzzle bank from admin-entered sources. Reuses an
   already-generated grid when a puzzle's content is unchanged (so
   saving the settings page doesn't needlessly reshuffle a puzzle a
   student may be mid-way through). Returns the new bank plus a list
   of any sources that couldn't be laid out (e.g. spangram isn't
   exactly 6 or 8 letters). */
function ascend_axx_build_puzzle_bank($sources, $existing_puzzles) {
    $by_hash = [];
    foreach ($existing_puzzles as $p) {
        if (!empty($p['content_hash'])) $by_hash[$p['content_hash']] = $p;
    }

    $built = [];
    $failed = [];
    foreach ($sources as $src) {
        $theme = trim((string) ($src['theme'] ?? ''));
        $spangram = strtoupper(trim((string) ($src['spangram'] ?? '')));
        $words = array_values(array_unique(array_filter(array_map(function ($w) {
            return strtoupper(trim((string) $w));
        }, $src['words'] ?? []))));

        if ($theme === '' || $spangram === '' || count($words) < 3) { $failed[] = $theme ?: '(untitled)'; continue; }
        if (in_array($spangram, $words, true)) { $failed[] = $theme; continue; }

        $clean_source = ['theme' => $theme, 'spangram' => $spangram, 'words' => $words];
        $hash = ascend_axx_content_hash($clean_source);

        if (isset($by_hash[$hash])) {
            $built[] = $by_hash[$hash];
            continue;
        }

        $layout = ascend_axx_generate_layout($spangram, $words);
        if ($layout === false) { $failed[] = $theme; continue; }

        $built[] = [
            'theme'        => $theme,
            'spangram'     => $spangram,
            'words'        => $words,
            'content_hash' => $hash,
            'grid'         => $layout['grid'],
            'paths'        => $layout['paths'],
        ];
    }

    return ['puzzles' => $built, 'failed' => $failed];
}

function ascend_axx_get_puzzles() {
    $puzzles = get_option('ascend_axx_puzzles', []);
    if (!is_array($puzzles) || empty($puzzles)) {
        $built = ascend_axx_build_puzzle_bank(ascend_axx_default_puzzle_sources(), []);
        update_option('ascend_axx_puzzles', $built['puzzles']);
        return $built['puzzles'];
    }
    return $puzzles;
}

function ascend_axx_max_hints() {
    $n = (int) get_option('ascend_axx_max_hints', 3);
    return $n > 0 ? $n : 3;
}

function ascend_axx_today_key() {
    return current_time('Y-m-d');
}

function ascend_axx_current_puzzle($user_id) {
    $puzzles = ascend_axx_get_puzzles();
    $progress = (int) get_user_meta($user_id, 'axx_progress', true);
    if ($progress >= count($puzzles)) return null;
    return $puzzles[$progress];
}

function ascend_axx_is_complete($user_id) {
    $total = count(ascend_axx_get_puzzles());
    if ($total === 0) return false;
    return (int) get_user_meta($user_id, 'axx_progress', true) >= $total;
}

/* Resets a student's in-progress state for their current puzzle if the
   puzzle bank content at that slot has changed since they started (see
   the content-hash comparison), so an admin edit never leaves a
   student's found-words pointing at letters that no longer exist. */
function ascend_axx_sync_puzzle_hash($user_id, $puzzle) {
    $stored_hash = get_user_meta($user_id, 'axx_puzzle_hash', true);
    if ($stored_hash !== $puzzle['content_hash']) {
        foreach (['axx_found', 'axx_hint_cells', 'axx_hints_used'] as $key) {
            delete_user_meta($user_id, $key);
        }
        update_user_meta($user_id, 'axx_puzzle_hash', $puzzle['content_hash']);
    }
}

function ascend_axx_bump_streak_if_new_day($user_id, $today) {
    $last_active = get_user_meta($user_id, 'axx_last_active_date', true);
    if ($last_active === $today) return; // already counted today

    $yesterday   = date('Y-m-d', strtotime($today . ' -1 day'));
    $streak_date = get_user_meta($user_id, 'axx_last_streak_date', true);
    $streak      = (int) get_user_meta($user_id, 'axx_streak', true);
    $streak      = ($streak_date === $yesterday) ? $streak + 1 : 1;

    update_user_meta($user_id, 'axx_streak', $streak);
    update_user_meta($user_id, 'axx_last_streak_date', $today);
    update_user_meta($user_id, 'axx_last_active_date', $today);

    $longest = (int) get_user_meta($user_id, 'axx_longest_streak', true);
    if ($streak > $longest) update_user_meta($user_id, 'axx_longest_streak', $streak);
}

/* ============================================================
   AJAX: GET STATE
   ============================================================ */
add_action('wp_ajax_ascend_axx_get_state', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axx_nonce', 'nonce');

    $user_id  = get_current_user_id();
    $today    = ascend_axx_today_key();
    $total    = count(ascend_axx_get_puzzles());
    $progress = (int) get_user_meta($user_id, 'axx_progress', true);
    $complete = ascend_axx_is_complete($user_id);

    if ($complete) {
        wp_send_json_success([
            'total' => $total, 'progress' => $progress, 'complete' => true,
            'streak' => (int) get_user_meta($user_id, 'axx_streak', true),
        ]);
    }

    $puzzle = ascend_axx_current_puzzle($user_id);
    ascend_axx_sync_puzzle_hash($user_id, $puzzle);

    $found = get_user_meta($user_id, 'axx_found', true);
    $found = is_array($found) ? $found : [];
    $hint_cells = get_user_meta($user_id, 'axx_hint_cells', true);
    $hint_cells = is_array($hint_cells) ? $hint_cells : [];
    $hints_used = (int) get_user_meta($user_id, 'axx_hints_used', true);

    $target_words = array_merge([$puzzle['spangram']], $puzzle['words']);
    $puzzle_solved = count($found) >= count($target_words);

    /* One theme a day, unless the student holds a pass. A single-use skip is
       offered here rather than spent: spending it stays an explicit action. */
    $has_pass  = ascend_games_has_pass($user_id);
    $skips     = ascend_games_skips($user_id);
    $last_done = get_user_meta($user_id, 'axx_last_complete_date', true);
    $locked_for_today = !$has_pass && empty($found) && $last_done === $today && !$puzzle_solved;

    $found_detail = [];
    foreach ($found as $w) {
        $found_detail[] = [
            'word'       => $w,
            'path'       => $puzzle['paths'][$w] ?? [],
            'isSpangram' => ($w === $puzzle['spangram']),
        ];
    }

    $visible_hint_cells = [];
    foreach ($hint_cells as $hc) {
        if (!in_array($hc['word'], $found, true)) {
            $visible_hint_cells[] = ['r' => $hc['r'], 'c' => $hc['c']];
        }
    }

    wp_send_json_success([
        'total'          => $total,
        'progress'       => $progress,
        'complete'       => false,
        'puzzleNumber'   => $progress + 1,
        'streak'         => (int) get_user_meta($user_id, 'axx_streak', true),
        'theme'          => $puzzle['theme'],
        'rows'           => ASCEND_AXX_ROWS,
        'cols'           => ASCEND_AXX_COLS,
        'grid'           => $puzzle['grid'],
        'totalWords'     => count($target_words),
        'foundCount'     => count($found),
        'spangramFound'  => in_array($puzzle['spangram'], $found, true),
        'foundWords'     => $found_detail,
        'hintCells'      => $visible_hint_cells,
        'hintsUsed'      => $hints_used,
        'hintsMax'       => ascend_axx_max_hints(),
        'puzzleSolved'   => $puzzle_solved,
        'lockedForToday' => $locked_for_today,
        'hasPass'        => $has_pass,
        'skips'          => $skips,
        'passUrls'       => ascend_games_pass_urls(),
    ]);
});

/* ============================================================
   AJAX: SUBMIT TRACED PATH
   ============================================================ */
add_action('wp_ajax_ascend_axx_submit_guess', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axx_nonce', 'nonce');

    $user_id = get_current_user_id();
    $today   = ascend_axx_today_key();

    if (ascend_axx_is_complete($user_id)) wp_send_json_error('You’ve solved every theme!');

    $puzzle = ascend_axx_current_puzzle($user_id);
    ascend_axx_sync_puzzle_hash($user_id, $puzzle);

    $found = get_user_meta($user_id, 'axx_found', true);
    $found = is_array($found) ? $found : [];
    $target_words = array_merge([$puzzle['spangram']], $puzzle['words']);
    if (count($found) >= count($target_words)) wp_send_json_error('You’ve already solved this theme.');

    if (!ascend_games_has_pass($user_id) && empty($found)
        && get_user_meta($user_id, 'axx_last_complete_date', true) === $today) {
        wp_send_json_error('This theme unlocks tomorrow — you already finished one today!');
    }

    $raw_cells = isset($_POST['cells']) && is_array($_POST['cells']) ? $_POST['cells'] : [];
    $cells = [];
    foreach ($raw_cells as $cell) {
        if (!is_array($cell) || !isset($cell['r']) || !isset($cell['c'])) continue;
        $cells[] = [(int) $cell['r'], (int) $cell['c']];
    }
    if (count($cells) < 3) wp_send_json_error('Trace a longer path.');

    $rows = ASCEND_AXX_ROWS; $cols = ASCEND_AXX_COLS;
    $seen = [];
    $prev = null;
    foreach ($cells as $cell) {
        [$r, $c] = $cell;
        if ($r < 0 || $r >= $rows || $c < 0 || $c >= $cols) wp_send_json_error('Invalid path.');
        $key = $r . ',' . $c;
        if (isset($seen[$key])) wp_send_json_error('Invalid path.');
        $seen[$key] = true;
        if ($prev !== null && (abs($prev[0] - $r) > 1 || abs($prev[1] - $c) > 1)) wp_send_json_error('Invalid path.');
        $prev = [$r, $c];
    }

    $grid = $puzzle['grid'];
    $guess = '';
    foreach ($cells as $cell) { $guess .= $grid[$cell[0]][$cell[1]]; }
    $guess = strtoupper($guess);
    $guess_rev = strrev($guess);

    if (in_array($guess, $found, true) || in_array($guess_rev, $found, true)) {
        wp_send_json_success(['matched' => false, 'alreadyFound' => true]);
    }

    $match = null;
    foreach ($target_words as $tw) {
        if (in_array($tw, $found, true)) continue;
        if ($tw === $guess || $tw === $guess_rev) { $match = $tw; break; }
    }

    if ($match === null) {
        wp_send_json_success(['matched' => false, 'alreadyFound' => false]);
    }

    $found[] = $match;
    update_user_meta($user_id, 'axx_found', $found);

    // Drop any hint markers that pointed at the word just found.
    $hint_cells = get_user_meta($user_id, 'axx_hint_cells', true);
    $hint_cells = is_array($hint_cells) ? $hint_cells : [];
    $hint_cells = array_values(array_filter($hint_cells, function ($hc) use ($match) { return $hc['word'] !== $match; }));
    update_user_meta($user_id, 'axx_hint_cells', $hint_cells);

    ascend_axx_bump_streak_if_new_day($user_id, $today);

    $response = [
        'matched'     => true,
        'word'        => $match,
        'path'        => $cells,
        'isSpangram'  => ($match === $puzzle['spangram']),
        'foundCount'  => count($found),
        'totalWords'  => count($target_words),
        'wonAll'      => false,
    ];

    if (count($found) >= count($target_words)) {
        update_user_meta($user_id, 'axx_last_complete_date', $today);

        $total_attempts = (int) get_user_meta($user_id, 'axx_total_attempts', true);
        update_user_meta($user_id, 'axx_total_attempts', $total_attempts + 1);

        $current = (int) get_user_meta($user_id, 'axx_progress', true);
        update_user_meta($user_id, 'axx_progress', $current + 1);

        $hints_used = (int) get_user_meta($user_id, 'axx_hints_used', true);
        $log = get_user_meta($user_id, 'axx_puzzle_log', true);
        $log = is_array($log) ? $log : [];
        // "found_largest" mirrors the same key the dashboard's game-stats card
        // already reads for every puzzle-type game — here it means a flawless
        // solve: every theme word found without using a single hint.
        $log[] = ['puzzle' => $current + 1, 'found_largest' => ($hints_used === 0)];
        update_user_meta($user_id, 'axx_puzzle_log', $log);

        $response['wonAll']      = true;
        $response['newProgress'] = $current + 1;
        $response['newStreak']   = (int) get_user_meta($user_id, 'axx_streak', true);
    }

    wp_send_json_success($response);
});

/* ============================================================
   AJAX: SPEND A NEXT-PUZZLE CREDIT

   Clearing the completion date is what lifts the lock: the gate is
   "did you already finish one today", so forgetting today is exactly
   equivalent to opening the next theme, and it needs no second flag
   that could drift out of step with the gate itself.
   ============================================================ */
add_action('wp_ajax_ascend_axx_use_skip', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axx_nonce', 'nonce');

    $user_id = get_current_user_id();
    if (ascend_axx_is_complete($user_id)) wp_send_json_error('You’ve solved every theme!');

    $found = get_user_meta($user_id, 'axx_found', true);
    if (!empty($found) && is_array($found)) wp_send_json_error('You’re already partway through this theme.');

    if (!ascend_games_consume_skip($user_id)) wp_send_json_error('No next-puzzle passes left.');

    delete_user_meta($user_id, 'axx_last_complete_date');
    wp_send_json_success(['skips' => ascend_games_skips($user_id)]);
});

/* ============================================================
   AJAX: USE HINT
   ============================================================ */
add_action('wp_ajax_ascend_axx_use_hint', function () {
    if (!is_user_logged_in()) wp_send_json_error('Not logged in.');
    check_ajax_referer('ascend_axx_nonce', 'nonce');

    $user_id = get_current_user_id();
    if (ascend_axx_is_complete($user_id)) wp_send_json_error('You’ve solved every theme!');

    $puzzle = ascend_axx_current_puzzle($user_id);
    ascend_axx_sync_puzzle_hash($user_id, $puzzle);

    $found = get_user_meta($user_id, 'axx_found', true);
    $found = is_array($found) ? $found : [];
    $target_words = array_merge([$puzzle['spangram']], $puzzle['words']);
    if (count($found) >= count($target_words)) wp_send_json_error('You’ve already solved this theme.');

    if (!ascend_games_has_pass($user_id) && empty($found)
        && get_user_meta($user_id, 'axx_last_complete_date', true) === ascend_axx_today_key()) {
        wp_send_json_error('This theme unlocks tomorrow.');
    }

    $hints_used = (int) get_user_meta($user_id, 'axx_hints_used', true);
    $max = ascend_axx_max_hints();
    if ($hints_used >= $max) wp_send_json_error('No hints left for this theme.');

    $hint_cells = get_user_meta($user_id, 'axx_hint_cells', true);
    $hint_cells = is_array($hint_cells) ? $hint_cells : [];
    $already_hinted = [];
    foreach ($hint_cells as $hc) $already_hinted[$hc['r'] . ',' . $hc['c']] = true;

    $unfound = array_values(array_filter($target_words, function ($w) use ($found) { return !in_array($w, $found, true); }));
    shuffle($unfound);

    $chosen_cell = null; $chosen_word = null;
    foreach ($unfound as $w) {
        $path = $puzzle['paths'][$w] ?? [];
        $candidates = array_values(array_filter($path, function ($cell) use ($already_hinted) {
            return !isset($already_hinted[$cell[0] . ',' . $cell[1]]);
        }));
        if (!empty($candidates)) {
            $chosen_cell = $candidates[array_rand($candidates)];
            $chosen_word = $w;
            break;
        }
    }

    if ($chosen_cell === null) wp_send_json_error('No new hint available for this theme.');

    $hint_cells[] = ['r' => $chosen_cell[0], 'c' => $chosen_cell[1], 'word' => $chosen_word];
    update_user_meta($user_id, 'axx_hint_cells', $hint_cells);
    update_user_meta($user_id, 'axx_hints_used', $hints_used + 1);

    wp_send_json_success([
        'cell'      => ['r' => $chosen_cell[0], 'c' => $chosen_cell[1]],
        'hintsUsed' => $hints_used + 1,
        'hintsMax'  => $max,
    ]);
});

/* ============================================================
   SHORTCODE — [ascend_cross_a_lotl]
   ============================================================ */
add_shortcode('ascend_cross_a_lotl', function () {
    if (!is_user_logged_in()) {
        return '<p>Please log in to play.</p>';
    }

    $user_id  = get_current_user_id();
    $total    = count(ascend_axx_get_puzzles());
    $progress = (int) get_user_meta($user_id, 'axx_progress', true);
    $streak   = (int) get_user_meta($user_id, 'axx_streak', true);
    $level    = max(1, (int) floor($progress / 3) + 1);
    $nonce    = wp_create_nonce('ascend_axx_nonce');
    $ajaxurl  = admin_url('admin-ajax.php');
    $mascot   = get_option('ascend_axx_mascot_url', '');

    ob_start();
    ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;700&display=swap');
#axx-game{--blue:#009CDE;--dblue:#045C82;--green:#1D4010;--lgreen:#BFE0B4;--pink:#F5B9CF;--dpink:#E07FA3;max-width:598px;margin:0 auto;padding:25px 15px 50px;font-family:Roboto,Arial,sans-serif;color:#24332a;zoom:1.1;-webkit-user-select:none;user-select:none;}
#axx-game *{box-sizing:border-box;}
.axx-title{text-align:center;font-size:clamp(28px,5vw,42px);margin:0;color:#173e63;font-weight:700;letter-spacing:0;font-family:'Baloo 2',Roboto,Arial,sans-serif;}
.axx-subtitle{text-align:center;color:#62676c;margin:10px 0 20px;font-size:15px;}
.axx-stats{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:18px;}
.axx-stat{background:white;border:1px solid #e4e1da;border-radius:14px;padding:9px 17px;font-size:14px;}
.axx-stat strong{color:var(--blue);}
.axx-mascot{text-align:center;margin-bottom:6px;}
.axx-mascot img{max-width:150px;width:100%;height:auto;}
.axx-toggle-row{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:2px;}
.axx-locked-toggle{display:block;margin:10px auto 0;background:transparent;border:1px dashed #c9c5b8;border-radius:8px;padding:8px 16px;font-size:12px;color:#77705f;cursor:pointer;font-family:inherit;}
.axx-locked-toggle:hover{border-color:#999;color:#555;}
.axx-instructions{margin-top:10px;background:#eef6fb;border:1px solid #d4e7f2;border-radius:12px;padding:14px 16px;font-size:13px;color:#24332a;text-align:left;max-width:420px;margin-left:auto;margin-right:auto;}
.axx-live{text-align:center;font-size:13px;color:#1474aa;margin-bottom:14px;min-height:16px;}
.axx-board{max-width:480px;margin:10px auto 5px;}
.axx-theme{text-align:center;background:#eef6fb;border:1px solid #d4e7f2;border-radius:12px;padding:8px 14px;font-size:13px;color:#1474aa;margin-bottom:10px;}
.axx-theme strong{color:#173e63;}
.axx-message{text-align:center;min-height:22px;font-size:14px;font-weight:700;margin-bottom:8px;}
.axx-progress-row{display:flex;justify-content:center;align-items:center;gap:14px;margin-bottom:10px;font-size:13px;color:#5F5E5A;flex-wrap:wrap;}
.axx-spangram-badge{background:#f4f2ec;border:1px solid #ddd;border-radius:20px;padding:4px 12px;font-weight:700;}
.axx-spangram-badge.found{background:var(--dblue);color:#fff;border-color:var(--dblue);}
.axx-grid-wrap{position:relative;max-width:400px;margin:0 auto;touch-action:none;}
.axx-grid{display:grid;gap:5px;}
.axx-tile{aspect-ratio:1/1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:clamp(14px,3.2vw,20px);font-weight:700;background:#f4f2ec;color:#24332a;cursor:pointer;transition:background .1s ease,color .1s ease,transform .08s ease;}
.axx-tile.found-word{background:var(--green);color:#fff;}
.axx-tile.found-spangram{background:var(--dblue);color:#fff;}
.axx-tile.selecting{background:var(--blue);color:#fff;transform:scale(1.06);}
.axx-tile.just-found{animation:axxPop .5s cubic-bezier(.2,.9,.3,1.4);}
@keyframes axxPop{0%{transform:scale(1);}45%{transform:scale(1.28);}100%{transform:scale(1);}}
.axx-tile.hinted{background:var(--pink);color:#7A2A48;animation:axxPulse 1.4s ease-in-out infinite;}
@keyframes axxPulse{0%,100%{box-shadow:0 0 0 0 rgba(224,127,163,.6);}50%{box-shadow:0 0 0 6px rgba(224,127,163,0);}}
.axx-found-list{margin-top:14px;font-size:12px;color:#5F5E5A;text-align:center;line-height:1.7;}
.axx-found-list .axx-found-tag{display:inline-block;background:#f4f2ec;border-radius:12px;padding:3px 10px;margin:2px;font-weight:700;color:#1D4010;}
.axx-found-list .axx-found-tag.spangram{background:var(--dblue);color:#fff;}
.axx-actions{display:flex;justify-content:center;gap:10px;padding:16px 0 4px;flex-wrap:wrap;}
.axx-btn{border:0;border-radius:12px;padding:10px 22px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;}
.axx-btn.primary{background:var(--dpink);color:#fff;}
.axx-btn.primary:disabled{opacity:.4;cursor:default;}
.axx-tip{margin-top:16px;border-radius:12px;padding:12px 16px;background:#eef6fb;border:1px solid #d4e7f2;color:#1474aa;font-size:13px;text-align:center;}
.axx-done{margin-top:16px;border-radius:12px;padding:16px;background:#f4f2ec;border:1px solid #e0dccf;color:#5f5a4d;font-size:14px;text-align:center;}
@media(max-width:420px){.axx-grid{gap:4px;}}
.axx-locked{margin-top:16px;border-radius:12px;padding:20px 16px;background:#eef6fb;border:1px solid #d4e7f2;color:#1474aa;font-size:14px;text-align:center;}
.axx-locked-title{font-family:'Baloo 2',Roboto,Arial,sans-serif;font-size:20px;color:#173e63;font-weight:700;}
.axx-locked-sub{margin:6px 0 14px;color:#62676c;}
.axx-pass-row{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-top:12px;}
.axx-btn.pass{background:var(--dblue);color:#fff;text-decoration:none;display:inline-block;}
.axx-btn.pass:hover{background:var(--blue);color:#fff;}
.axx-overlay{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(9,32,48,.55);opacity:0;transition:opacity .35s ease;pointer-events:none;}
.axx-overlay.show{opacity:1;}
.axx-congrats{position:relative;background:#fff;border-radius:20px;padding:26px 34px;text-align:center;box-shadow:0 18px 50px rgba(0,0,0,.28);transform:scale(.85);transition:transform .35s cubic-bezier(.2,.9,.3,1.4);max-width:88vw;}
.axx-overlay.show .axx-congrats{transform:scale(1);}
.axx-congrats-title{font-family:'Baloo 2',Roboto,Arial,sans-serif;font-size:clamp(24px,6vw,32px);color:var(--dblue);font-weight:700;line-height:1.15;}
.axx-congrats-sub{margin-top:6px;font-size:15px;color:var(--green);font-weight:700;}
.axx-congrats-next{margin-top:14px;font-size:13px;color:#62676c;}
.axx-confetti{position:absolute;inset:0;overflow:hidden;}
.axx-confetti i{position:absolute;top:-16px;width:9px;height:14px;border-radius:2px;animation:axxFall 2.4s linear forwards;}
@keyframes axxFall{0%{transform:translateY(-20px) rotate(0);opacity:1;}100%{transform:translateY(102vh) rotate(700deg);opacity:.85;}}
@media(prefers-reduced-motion:reduce){.axx-confetti{display:none;}.axx-tile.just-found{animation:none;}.axx-congrats{transition:none;transform:none;}}
</style>

<div id="axx-game">
  <?php if ($mascot): ?><div class="axx-mascot"><img src="<?php echo esc_url($mascot); ?>" alt="Axolotl"></div><?php endif; ?>
  <h1 class="axx-title">Cross-a-lotl</h1>
  <p class="axx-subtitle">Trace connected letters to find every word hidden in today's theme.</p>

  <div class="axx-stats">
    <div class="axx-stat">Streak: <strong id="axx-streak"><?php echo esc_html($streak); ?></strong></div>
    <div class="axx-stat">Solved: <strong id="axx-found"><?php echo esc_html($progress); ?></strong> / <?php echo esc_html($total); ?></div>
    <div class="axx-stat">Level: <strong id="axx-level"><?php echo esc_html($level); ?></strong></div>
  </div>

  <div class="axx-toggle-row">
    <button type="button" id="axx-instructions-toggle" class="axx-locked-toggle">How to play &#9662;</button>
  </div>
  <div id="axx-instructions-panel" class="axx-instructions" style="display:none;"></div>
  <div class="axx-live" id="axx-live-badge"></div>
  <div id="axx-play-area"></div>
</div>

<script>
(function(){
var ASCEND_AXX = { ajaxurl: <?php echo wp_json_encode($ajaxurl); ?>, nonce: <?php echo wp_json_encode($nonce); ?> };
var state = null, selecting = false, currentPath = [], busy = false;

function api(action, data){
  var params = new URLSearchParams();
  params.append('action', action);
  params.append('nonce', ASCEND_AXX.nonce);
  Object.keys(data||{}).forEach(function(k){
    var v = data[k];
    if (Array.isArray(v)) v.forEach(function(item, i){
      if (item && typeof item === 'object'){
        Object.keys(item).forEach(function(sub){ params.append(k+'['+i+']['+sub+']', item[sub]); });
      } else { params.append(k+'[]', item); }
    });
    else params.append(k, v);
  });
  return fetch(ASCEND_AXX.ajaxurl, {method:'POST', credentials:'same-origin', body:params}).then(function(r){return r.json();});
}

function loadState(){
  api('ascend_axx_get_state', {}).then(function(resp){
    if(!resp.success){ message(resp.data || 'Could not load your progress.'); return; }
    state = resp.data;
    renderAll();
  });
}

function renderAll(){
  document.getElementById('axx-streak').textContent = state.streak;
  document.getElementById('axx-found').textContent = state.progress;
  document.getElementById('axx-level').textContent = Math.max(1, Math.floor(state.progress/3)+1);
  renderInstructions();
  renderPlayArea();
}

function renderInstructions(){
  var panel = document.getElementById('axx-instructions-panel');
  panel.innerHTML =
    '<p style="margin:0 0 8px;">Every letter on the board is real \u2014 the challenge is finding which connected chains of letters spell a real word for today\u2019s theme.</p>' +
    '<p style="margin:0 0 8px;">Tap or drag through touching letters (any direction, including diagonal) to trace a word, then lift your finger/mouse to submit.</p>' +
    '<p style="margin:0 0 8px;">One special word \u2014 the <strong>spangram</strong> \u2014 stretches across the whole board and sums up the theme.</p>' +
    '<p style="margin:0;">Stuck? Use a hint (' + (state && state.hintsMax ? state.hintsMax : 3) + ' per theme) to highlight one letter from a word you haven\u2019t found yet.</p>';

  var instrToggle = document.getElementById('axx-instructions-toggle');
  instrToggle.onclick = function(){
    var hidden = panel.style.display === 'none';
    panel.style.display = hidden ? '' : 'none';
    instrToggle.textContent = hidden ? 'Hide instructions \u25b4' : 'How to play \u25be';
  };
}

function renderPlayArea(){
  var area = document.getElementById('axx-play-area');
  var badge = document.getElementById('axx-live-badge');

  if (state.complete){
    area.innerHTML = '<div class="axx-done">You\u2019ve solved every theme! \ud83c\udf89 More will be added soon.</div>';
    badge.textContent = '';
    return;
  }

  if (state.lockedForToday){
    area.innerHTML = renderLocked();
    badge.textContent = 'Played today \u2014 a new theme opens tomorrow';
    wireLocked();
    return;
  }

  badge.textContent = state.hasPass ? 'Pass active \u2014 play as many as you like' : '';

  area.innerHTML =
    '<div class="axx-board">' +
      '<div class="axx-theme">Today\u2019s theme: <strong>' + escapeHtml(state.theme) + '</strong></div>' +
      '<div id="axx-message" class="axx-message"></div>' +
      '<div class="axx-progress-row">' +
        '<span>Words found: ' + state.foundCount + ' / ' + state.totalWords + '</span>' +
        '<span class="axx-spangram-badge' + (state.spangramFound ? ' found' : '') + '">Spangram</span>' +
      '</div>' +
      '<div class="axx-grid-wrap"><div id="axx-grid" class="axx-grid"></div></div>' +
      '<div id="axx-found-list" class="axx-found-list"></div>' +
      '<div class="axx-actions"><button type="button" id="axx-hint-btn" class="axx-btn primary">Use a hint (' + (state.hintsMax - state.hintsUsed) + ' left)</button></div>' +
    '</div>' +
    '<div class="axx-tip">' + (state.hasPass
      ? 'Your pass is active \u2014 finish this theme and the next one opens straight away.'
      : 'One new theme unlocks per day \u2014 but take as many days as you need to finish the one you\u2019re on.') +
    '</div>';

  buildGrid();
  renderFoundList();

  var hintBtn = document.getElementById('axx-hint-btn');
  hintBtn.disabled = state.puzzleSolved || state.hintsUsed >= state.hintsMax;
  hintBtn.onclick = useHint;
}

function escapeHtml(s){ var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function cellKey(r,c){ return r + ',' + c; }

/* Paths arrive as [row, col] pairs from both endpoints. Objects are tolerated
   so a future payload shape cannot silently stop the highlighting again. */
function normPath(path){
  return (path || []).map(function(p){
    return Array.isArray(p) ? [p[0], p[1]] : [p.r, p.c];
  }).filter(function(p){ return p[0] != null && p[1] != null; });
}

function buildGrid(){
  var grid = document.getElementById('axx-grid');
  grid.style.gridTemplateColumns = 'repeat(' + state.cols + ', 1fr)';
  grid.innerHTML = '';

  var foundCellMap = {}; // key -> 'word' | 'spangram'
  (state.foundWords || []).forEach(function(fw){
    normPath(fw.path).forEach(function(cell){
      foundCellMap[cellKey(cell[0], cell[1])] = fw.isSpangram ? 'spangram' : 'word';
    });
  });
  var hintMap = {};
  (state.hintCells || []).forEach(function(hc){ hintMap[cellKey(hc.r, hc.c)] = true; });

  for (var r = 0; r < state.rows; r++){
    for (var c = 0; c < state.cols; c++){
      var tile = document.createElement('div');
      tile.className = 'axx-tile';
      tile.textContent = state.grid[r][c];
      tile.dataset.r = r; tile.dataset.c = c;
      var key = cellKey(r,c);
      if (foundCellMap[key] === 'spangram') tile.classList.add('found-spangram');
      else if (foundCellMap[key] === 'word') tile.classList.add('found-word');
      else if (hintMap[key]) tile.classList.add('hinted');

      tile.addEventListener('pointerdown', function(e){ onCellDown(e); });
      tile.addEventListener('pointerenter', function(e){ onCellEnter(e); });
      grid.appendChild(tile);
    }
  }

  document.addEventListener('pointerup', onPointerUp);
  document.addEventListener('pointercancel', onPointerUp);
}

function renderFoundList(){
  var wrap = document.getElementById('axx-found-list');
  if (!state.foundWords || !state.foundWords.length){ wrap.innerHTML = ''; return; }
  wrap.innerHTML = state.foundWords.map(function(fw){
    return '<span class="axx-found-tag' + (fw.isSpangram ? ' spangram' : '') + '">' + escapeHtml(fw.word) + '</span>';
  }).join(' ');
}

/* The locked screen. Only passes that actually have a product behind them
   are offered, so the store can be built up one product at a time without
   ever showing a student a dead link. */
function renderLocked(){
  var urls = state.passUrls || {};
  var out = '<div class="axx-locked">' +
    '<div class="axx-locked-title">Today\u2019s theme is done \ud83c\udf89</div>' +
    '<p class="axx-locked-sub">A new one opens tomorrow \u2014 or keep playing now.</p>';

  if (state.skips > 0){
    out += '<button type="button" id="axx-use-skip" class="axx-btn primary">' +
             'Open the next theme (' + state.skips + ' left)' +
           '</button>';
  }

  var buys = [];
  if (urls.skip)  buys.push(['skip',  'Next theme']);
  if (urls.month) buys.push(['month', '30-day pass']);
  if (urls.year)  buys.push(['year',  'Annual pass']);
  if (urls.life)  buys.push(['life',  'Unlock everything']);

  if (buys.length){
    out += '<div class="axx-pass-row">' + buys.map(function(b){
      return '<a class="axx-btn pass" href="' + encodeURI(urls[b[0]]) + '">' + b[1] + '</a>';
    }).join('') + '</div>';
  }

  return out + '</div>';
}

function wireLocked(){
  var btn = document.getElementById('axx-use-skip');
  if (!btn) return;
  btn.onclick = function(){
    if (busy) return;
    busy = true;
    btn.disabled = true;
    api('ascend_axx_use_skip', {}).then(function(resp){
      busy = false;
      if (!resp.success){ btn.disabled = false; alert(resp.data || 'Could not open the next theme.'); return; }
      loadState();
    });
  };
}

function tileAt(r,c){
  return document.querySelector('#axx-grid .axx-tile[data-r="' + r + '"][data-c="' + c + '"]');
}

function isAdjacent(a,b){
  return Math.abs(a.r-b.r) <= 1 && Math.abs(a.c-b.c) <= 1 && !(a.r===b.r && a.c===b.c);
}

function onCellDown(e){
  if (busy || state.puzzleSolved) return;
  e.preventDefault();
  var t = e.currentTarget;
  selecting = true;
  currentPath = [{r: +t.dataset.r, c: +t.dataset.c}];
  refreshSelection();
}

function onCellEnter(e){
  if (!selecting || busy) return;
  var t = e.currentTarget;
  var cell = {r: +t.dataset.r, c: +t.dataset.c};
  var last = currentPath[currentPath.length-1];
  if (last.r === cell.r && last.c === cell.c) return;

  // Backtrack one step if hovering the previous cell again.
  if (currentPath.length > 1){
    var prev = currentPath[currentPath.length-2];
    if (prev.r === cell.r && prev.c === cell.c){ currentPath.pop(); refreshSelection(); return; }
  }

  var already = currentPath.some(function(p){ return p.r===cell.r && p.c===cell.c; });
  if (already) return;
  if (!isAdjacent(last, cell)) return;

  currentPath.push(cell);
  refreshSelection();
}

function onPointerUp(){
  if (!selecting) return;
  selecting = false;
  if (currentPath.length >= 3){
    submitPath(currentPath.slice());
  } else {
    currentPath = [];
    refreshSelection();
  }
}

function refreshSelection(){
  document.querySelectorAll('#axx-grid .axx-tile').forEach(function(tile){ tile.classList.remove('selecting'); });
  currentPath.forEach(function(cell){
    var tile = tileAt(cell.r, cell.c);
    if (tile) tile.classList.add('selecting');
  });
}

function message(text){ var m = document.getElementById('axx-message'); if (m) m.textContent = text; }

function submitPath(cells){
  busy = true;
  api('ascend_axx_submit_guess', {cells: cells}).then(function(resp){
    busy = false;
    currentPath = [];
    if (!resp.success){ message(resp.data || 'Something went wrong.'); refreshSelection(); return; }
    var data = resp.data;

    if (data.alreadyFound){ message('Already found that one.'); refreshSelection(); return; }
    if (!data.matched){ message('Not it \u2014 keep looking.'); refreshSelection(); return; }

    state.foundCount = data.foundCount;
    if (data.isSpangram) state.spangramFound = true;
    state.foundWords = state.foundWords || [];
    var newPath = normPath(data.path);
    state.foundWords.push({word: data.word, path: newPath, isSpangram: data.isSpangram});

    if (data.wonAll){
      state.puzzleSolved = true;
      state.progress = data.newProgress;
      state.streak = data.newStreak;
      document.getElementById('axx-streak').textContent = state.streak;
      document.getElementById('axx-found').textContent = state.progress;
      document.getElementById('axx-level').textContent = Math.max(1, Math.floor(state.progress/3)+1);
      message('\u2713 Every word found!');
    } else {
      message(data.isSpangram ? '\u2b50 That\u2019s the spangram!' : 'Nice find!');
    }

    buildGrid();
    renderFoundList();
    flashCells(newPath);
    var hintBtn = document.getElementById('axx-hint-btn');
    if (hintBtn) hintBtn.disabled = state.puzzleSolved || state.hintsUsed >= state.hintsMax;
    var progRow = document.querySelector('.axx-progress-row span');
    if (progRow) progRow.textContent = 'Words found: ' + state.foundCount + ' / ' + state.totalWords;
    var spanBadge = document.querySelector('.axx-spangram-badge');
    if (spanBadge) spanBadge.classList.toggle('found', state.spangramFound);
    if (data.wonAll) celebrate();
  });
}

/* Pop the letters of the word that was just traced, so the lock-in reads as
   an event and not just a colour change. */
function flashCells(path){
  path.forEach(function(cell){
    var tile = tileAt(cell[0], cell[1]);
    if (!tile) return;
    tile.classList.add('just-found');
    tile.addEventListener('animationend', function(){ tile.classList.remove('just-found'); }, {once: true});
  });
}

var AXX_CONFETTI = ['#009CDE', '#045C82', '#1D4010', '#BFE0B4', '#F5B9CF', '#E07FA3'];

/* Board finished: hold the completed grid on screen for a beat, celebrate,
   then re-read from the server. Whether that lands on the next theme or on
   the locked screen is the server's call, not this function's \u2014 which is
   why it always reloads rather than assuming either outcome. loadState()
   also refreshes progress, streak and level so the header and the dashboard
   badge data stay in step. */
function celebrate(){
  var host = document.getElementById('axx-game');
  if (!host) { loadState(); return; }

  var overlay = document.createElement('div');
  overlay.className = 'axx-overlay';

  var bits = '';
  for (var i = 0; i < 30; i++){
    bits += '<i style="left:' + (Math.random() * 100).toFixed(2) + '%;' +
            'animation-delay:' + (Math.random() * 0.8).toFixed(2) + 's;' +
            'background:' + AXX_CONFETTI[i % AXX_CONFETTI.length] + '"></i>';
  }

  var last = (state.progress >= state.total);
  var next = last ? 'That was the last theme \u2014 nice work!'
                  : (state.hasPass ? 'Opening the next board\u2026'
                                   : 'Your next theme unlocks tomorrow.');
  overlay.innerHTML =
    '<div class="axx-confetti">' + bits + '</div>' +
    '<div class="axx-congrats">' +
      '<div class="axx-congrats-title">Theme solved! \ud83c\udf89</div>' +
      '<div class="axx-congrats-sub">' + escapeHtml(state.theme || '') + '</div>' +
      '<div class="axx-congrats-next">' + next + '</div>' +
    '</div>';

  host.appendChild(overlay);
  requestAnimationFrame(function(){ overlay.classList.add('show'); });

  setTimeout(function(){
    overlay.classList.remove('show');
    setTimeout(function(){ if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 400);
    loadState();
  }, 2600);
}

function useHint(){
  if (busy) return;
  busy = true;
  api('ascend_axx_use_hint', {}).then(function(resp){
    busy = false;
    if (!resp.success){ message(resp.data || 'No hint available.'); return; }
    state.hintsUsed = resp.data.hintsUsed;
    state.hintCells = state.hintCells || [];
    state.hintCells.push(resp.data.cell);
    buildGrid();
    var hintBtn = document.getElementById('axx-hint-btn');
    hintBtn.textContent = 'Use a hint (' + (state.hintsMax - state.hintsUsed) + ' left)';
    hintBtn.disabled = state.hintsUsed >= state.hintsMax;
    message('Hint revealed \u2014 look for the glowing letter.');
  });
}

loadState();
})();
</script>
    <?php
    return ob_get_clean();
});

/* ============================================================
   ADMIN
   ============================================================ */
add_action('admin_menu', function () {
    add_submenu_page('ascend-games', 'Cross-a-lotl – Students', 'Cross-a-lotl: Students', 'manage_options', 'ascend-axx', 'ascend_axx_admin_students_page');
    add_submenu_page('ascend-games', 'Cross-a-lotl – Puzzle Bank & Settings', 'Cross-a-lotl: Puzzle Bank & Settings', 'manage_options', 'ascend-axx-settings', 'ascend_axx_admin_settings_page');
});

function ascend_axx_admin_students_page() {
    if (!current_user_can('manage_options')) return;

    if (isset($_POST['axx_reset_user']) && check_admin_referer('axx_reset_user')) {
        ascend_axx_reset_user((int) $_POST['axx_reset_user']);
        echo '<div class="notice notice-success"><p>All progress reset for that student.</p></div>';
    }
    if (isset($_POST['axx_reset_puzzle']) && check_admin_referer('axx_reset_puzzle')) {
        ascend_axx_reset_current_puzzle((int) $_POST['axx_reset_puzzle']);
        echo '<div class="notice notice-success"><p>Current theme progress cleared for that student — overall level and streak stay as-is.</p></div>';
    }
    if (isset($_POST['axx_reset_all']) && check_admin_referer('axx_reset_all')) {
        $users = get_users(['fields' => ['ID']]);
        foreach ($users as $u) ascend_axx_reset_user($u->ID);
        echo '<div class="notice notice-success"><p><strong>Every student’s progress has been reset.</strong> Puzzle bank and settings are untouched.</p></div>';
    }

    echo '<div class="wrap"><h1>Cross-a-lotl — student roster</h1>';

    echo '<form method="post" onsubmit="return confirm(\'Reset EVERY student\u2019s progress, streak, and theme history? This cannot be undone.\');" style="margin-bottom:14px;">';
    wp_nonce_field('axx_reset_all');
    echo '<button type="submit" name="axx_reset_all" class="button button-secondary" style="color:#a00;border-color:#a00;">Reset game database (reset ALL students)</button>';
    echo '</form>';

    echo '<table class="widefat striped"><thead><tr><th>Student</th><th>Level</th><th>Streak</th><th>Themes solved</th><th>Current theme progress</th><th>History (admin only)</th><th>Actions</th></tr></thead><tbody>';

    $total = count(ascend_axx_get_puzzles());
    $users = get_users(['fields' => ['ID', 'display_name']]);
    $shown = 0;
    foreach ($users as $u) {
        $progress = (int) get_user_meta($u->ID, 'axx_progress', true);
        $streak   = (int) get_user_meta($u->ID, 'axx_streak', true);
        $found    = get_user_meta($u->ID, 'axx_found', true);
        $found    = is_array($found) ? $found : [];
        if ($progress === 0 && $streak === 0 && empty($found)) continue;
        $shown++;
        $level = max(1, (int) floor($progress / 3) + 1);

        $puzzle = ($progress < $total) ? ascend_axx_get_puzzles()[$progress] : null;
        $current_total = $puzzle ? count($puzzle['words']) + 1 : 0;
        $current_str = $puzzle ? (count($found) . ' / ' . $current_total . ' words on "' . esc_html($puzzle['theme']) . '"') : '—';

        $log = get_user_meta($u->ID, 'axx_puzzle_log', true);
        $log = is_array($log) ? $log : [];
        $log_list = '(none yet)';
        if (!empty($log)) {
            $rows = array_map(function ($e) {
                $flawless = !empty($e['found_largest']) ? ' — flawless (no hints)' : '';
                return esc_html('Theme ' . $e['puzzle'] . ': solved' . $flawless);
            }, $log);
            $log_list = '<details><summary>' . count($log) . ' theme' . (count($log) !== 1 ? 's' : '') . '</summary>' . implode('<br>', $rows) . '</details>';
        }

        echo '<tr><td>' . esc_html($u->display_name) . '</td><td>' . $level . '</td><td>' . $streak . '</td><td>' . $progress . ' / ' . $total . '</td><td>' . $current_str . '</td><td>' . $log_list . '</td><td>';

        echo '<form method="post" onsubmit="return confirm(\'Clear current theme progress for ' . esc_js($u->display_name) . '? Overall level and streak stay as-is.\');" style="margin:0 0 4px;">';
        wp_nonce_field('axx_reset_puzzle');
        echo '<input type="hidden" name="axx_reset_puzzle" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small">Reset current theme</button></form>';

        echo '<form method="post" onsubmit="return confirm(\'Reset ALL progress for ' . esc_js($u->display_name) . '? This cannot be undone.\');" style="margin:0;">';
        wp_nonce_field('axx_reset_user');
        echo '<input type="hidden" name="axx_reset_user" value="' . (int) $u->ID . '">';
        echo '<button type="submit" class="button button-small" style="color:#a00;">Reset all progress</button></form>';

        echo '</td></tr>';
    }
    if (!$shown) echo '<tr><td colspan="7">No students have played yet.</td></tr>';
    echo '</tbody></table></div>';
}

function ascend_axx_reset_user($user_id) {
    foreach (['axx_progress', 'axx_streak', 'axx_last_active_date', 'axx_last_streak_date', 'axx_longest_streak', 'axx_last_complete_date', 'axx_total_attempts', 'axx_puzzle_log', 'axx_found', 'axx_hint_cells', 'axx_hints_used', 'axx_puzzle_hash'] as $key) {
        delete_user_meta($user_id, $key);
    }
}

/* Clears only progress on the student's CURRENT theme, leaving overall
   level, streak, and theme history untouched. */
function ascend_axx_reset_current_puzzle($user_id) {
    foreach (['axx_found', 'axx_hint_cells', 'axx_hints_used', 'axx_puzzle_hash'] as $key) {
        delete_user_meta($user_id, $key);
    }
}

function ascend_axx_admin_settings_page() {
    if (!current_user_can('manage_options')) return;
    wp_enqueue_media();

    if (isset($_POST['ascend_axx_save']) && check_admin_referer('ascend_axx_settings')) {
        $max_hints = (int) ($_POST['max_hints'] ?? 3);
        update_option('ascend_axx_max_hints', $max_hints > 0 ? $max_hints : 3);
        update_option('ascend_axx_mascot_url', esc_url_raw($_POST['mascot_url'] ?? ''));

        $decoded = json_decode(stripslashes($_POST['puzzles_json'] ?? '[]'), true);
        $sources = is_array($decoded) ? $decoded : [];
        $existing = get_option('ascend_axx_puzzles', []);
        $result = ascend_axx_build_puzzle_bank($sources, is_array($existing) ? $existing : []);

        if (!empty($result['puzzles'])) {
            update_option('ascend_axx_puzzles', $result['puzzles']);
            $notice = 'Saved. ' . count($result['puzzles']) . ' theme' . (count($result['puzzles']) !== 1 ? 's' : '') . ' in the bank.';
            if (!empty($result['failed'])) {
                $notice .= ' Could not build a grid for: ' . esc_html(implode(', ', $result['failed'])) . ' — check that the spangram is exactly 6 or 8 letters and doesn’t repeat a theme word, and that the theme words aren’t too long or too numerous to fit a 6×8 grid.';
            }
            echo '<div class="notice notice-' . (empty($result['failed']) ? 'success' : 'warning') . '"><p>' . $notice . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>No valid themes could be built — the puzzle bank was left unchanged. Each spangram must be exactly 6 or 8 letters, and each theme needs at least 3 other words.</p></div>';
        }
    }

    $puzzles = ascend_axx_get_puzzles();
    $mascot  = get_option('ascend_axx_mascot_url', '');
    $maxh    = ascend_axx_max_hints();

    echo '<div class="wrap"><h1>Cross-a-lotl — puzzle bank &amp; settings</h1>';
    echo '<p>The full letter grid is always visible to students — only the mapping from letters to theme words is kept server-side until a student traces it correctly. Grids are generated automatically on save from the words you enter below.</p>';
    echo '<form method="post">';
    wp_nonce_field('ascend_axx_settings');

    echo '<h2>Game rules</h2>';
    echo '<p><label><strong>Hints allowed per theme</strong><br><input type="number" name="max_hints" min="0" max="10" value="' . esc_attr($maxh) . '" style="width:80px;"></label></p>';

    echo '<h2>Mascot image (optional)</h2>';
    echo '<input type="hidden" id="axx_mascot_url" name="mascot_url" value="' . esc_attr($mascot) . '">';
    echo '<div id="axx_mascot_preview">' . ($mascot ? '<img src="' . esc_url($mascot) . '" style="max-width:150px;display:block;margin-bottom:8px;">' : '<p style="color:#777;">No image set.</p>') . '</div>';
    echo '<button type="button" class="button" id="axx_mascot_btn">Choose image</button> <button type="button" class="button" id="axx_mascot_clear">Remove</button>';

    echo '<h2 style="margin-top:24px;">Puzzle bank</h2>';
    echo '<p class="description">Each theme needs a <strong>spangram</strong> (a word that sums up the theme — must be exactly 6 or 8 letters, since it’s the one that spans the grid) and at least 3 other theme words (comma-separated). The grid is 6 rows × 8 columns (48 letters). Currently ' . count($puzzles) . ' theme' . (count($puzzles) !== 1 ? 's' : '') . '.</p>';
    echo '<div id="axx-puzzle-list"></div>';
    echo '<p><button type="button" class="button" id="axx-add-puzzle">+ Add another theme</button></p>';
    echo '<textarea name="puzzles_json" id="axx-puzzles-hidden" style="display:none;"></textarea>';

    echo '<p style="margin-top:16px;"><button type="submit" name="ascend_axx_save" class="button button-primary">Save changes</button></p></form></div>';

    echo '<script>
    (function(){
      var puzzles = ' . wp_json_encode($puzzles) . ';
      var list = document.getElementById("axx-puzzle-list");
      var hidden = document.getElementById("axx-puzzles-hidden");

      function blankPuzzle(){ return {theme:"", spangram:"", words:["","","",""]}; }

      function sync(){
        var out = [];
        document.querySelectorAll(".axx-puzzle-block").forEach(function(block){
          var theme = block.querySelector(".axx-theme-input").value.trim();
          var spangram = block.querySelector(".axx-spangram-input").value.trim();
          var words = block.querySelector(".axx-words-input").value.split(",").map(function(w){ return w.trim(); }).filter(Boolean);
          out.push({theme: theme, spangram: spangram, words: words});
        });
        hidden.value = JSON.stringify(out);
      }

      function renderPuzzle(p, index){
        var block = document.createElement("div");
        block.className = "axx-puzzle-block";
        block.style.cssText = "border:1px solid #ddd;border-radius:8px;padding:12px 14px;margin-bottom:14px;background:#fff;max-width:760px;";

        var header = document.createElement("div");
        header.style.cssText = "display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;";
        header.innerHTML = "<strong>Theme " + (index+1) + "</strong>";
        var removeBtn = document.createElement("button");
        removeBtn.type = "button"; removeBtn.className = "button button-small";
        removeBtn.style.color = "#a00";
        removeBtn.textContent = "Remove theme";
        removeBtn.onclick = function(){ block.remove(); renumber(); sync(); };
        header.appendChild(removeBtn);
        block.appendChild(header);

        var row1 = document.createElement("div");
        row1.style.cssText = "display:flex;gap:8px;margin-bottom:6px;flex-wrap:wrap;";

        var themeInput = document.createElement("input");
        themeInput.type = "text"; themeInput.className = "axx-theme-input";
        themeInput.placeholder = "Theme label (e.g. Ways to stay hidden)";
        themeInput.value = p.theme || "";
        themeInput.style.cssText = "flex:1;min-width:260px;";
        themeInput.addEventListener("input", sync);
        row1.appendChild(themeInput);

        var spangramInput = document.createElement("input");
        spangramInput.type = "text"; spangramInput.className = "axx-spangram-input";
        spangramInput.placeholder = "Spangram (exactly 6 or 8 letters)";
        spangramInput.maxLength = 8;
        spangramInput.value = p.spangram || "";
        spangramInput.style.cssText = "width:220px;";
        spangramInput.addEventListener("input", sync);
        row1.appendChild(spangramInput);

        block.appendChild(row1);

        var wordsInput = document.createElement("input");
        wordsInput.type = "text"; wordsInput.className = "axx-words-input";
        wordsInput.placeholder = "Theme words, comma-separated (WORD1, WORD2, WORD3, ...)";
        wordsInput.value = (p.words || []).join(", ");
        wordsInput.style.cssText = "width:100%;";
        wordsInput.addEventListener("input", sync);
        block.appendChild(wordsInput);

        list.appendChild(block);
      }

      function renumber(){
        document.querySelectorAll(".axx-puzzle-block").forEach(function(block, i){
          block.querySelector("strong").textContent = "Theme " + (i+1);
        });
      }

      puzzles.forEach(function(p, i){ renderPuzzle(p, i); });
      if (!puzzles.length) renderPuzzle(blankPuzzle(), 0);
      sync();

      document.getElementById("axx-add-puzzle").addEventListener("click", function(){
        renderPuzzle(blankPuzzle(), document.querySelectorAll(".axx-puzzle-block").length);
        sync();
      });

      document.getElementById("axx_mascot_btn").addEventListener("click", function(e){
        e.preventDefault();
        var frame = wp.media({title:"Select axolotl artwork", button:{text:"Use this image"}, multiple:false});
        frame.on("select", function(){
          var att = frame.state().get("selection").first().toJSON();
          document.getElementById("axx_mascot_url").value = att.url;
          document.getElementById("axx_mascot_preview").innerHTML = "<img src=\"" + att.url + "\" style=\"max-width:150px;display:block;margin-bottom:8px;\">";
        });
        frame.open();
      });
      document.getElementById("axx_mascot_clear").addEventListener("click", function(e){
        e.preventDefault();
        document.getElementById("axx_mascot_url").value = "";
        document.getElementById("axx_mascot_preview").innerHTML = "<p style=\"color:#777;\">No image set.</p>";
      });
    })();
    </script>';
}
