<?php
/**
 * Plugin Name:       Ascend STEM-a-lotl: Rescue Lab
 * Plugin URI:        https://ascendstemacademy.com/
 * Description:       A physics puzzle game for the Axolotl Games family: 40 puzzles where kids build ramps, bouncers, fans and blocks to roll a food pellet to Lucas the axolotl through wind, bees, water, fish and waterfalls. Shortcode: [ascend_rescue_lab]. Parent summary: [ascend_rescue_lab_summary].
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ascend STEM Academy
 * License:           GPL-2.0-or-later
 * Text Domain:       ascend-rescue-lab
 *
 * ---------------------------------------------------------------------------
 * HOW PUZZLES OPEN (all configurable, see ascend_rl_config below)
 *
 *   - Puzzles 1..freeLevels (default 3) are free for everyone.
 *   - Daily unlock: on each calendar day (site timezone) that a logged-in
 *     student visits, the lowest-numbered locked puzzle opens for free.
 *     One per day. Days the student does not visit are not banked.
 *   - Pond Keys: one key (keyCost) opens any locked puzzle right away, for good.
 *   - Full Pond Pass: opens every puzzle.
 *   - Special pellet skins can be bought with Pond Keys (skinPrices).
 *
 * KEYS are the site's existing Pond Keys, owned by the Ascend Axolotl Games
 * plugin (user meta ascend_games_skips). This plugin only calls its public
 * functions ascend_games_skips(), ascend_games_consume_skip(),
 * ascend_games_has_pass() and ascend_games_pass_url(); it never edits the
 * balance directly. WooCommerce fulfilment and refunds stay in that plugin.
 *
 * MCP: every ascend_rl_* option is exposed to Royal MCP for reading and
 * writing (same filters the games plugin uses), so settings and puzzle text
 * can be changed from Claude without touching code.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASCEND_RL_VERSION', '2.0.0' );
define( 'ASCEND_RL_LEVELS', 40 );
define( 'ASCEND_RL_PROGRESS_META', 'ascend_rl_progress' );
define( 'ASCEND_RL_OWNED_META', 'ascend_rl_owned' );
define( 'ASCEND_RL_DAILY_META', 'ascend_rl_last_daily' );
define( 'ASCEND_RL_SKINS_META', 'ascend_rl_skins' );
define( 'ASCEND_RL_LOG_META', 'ascend_rl_unlock_log' );

/** Puzzle names and ideas, for the parent summary and admin screens. */
function ascend_rl_catalog() {
	return array(
		1 => array( 'First Bite', 'Ramps and gravity' ), 2 => array( 'Hilltop Picnic', 'Starting height' ), 3 => array( 'The Long Roll', 'Speed from slopes' ),
		4 => array( 'Boing!', 'Bouncers store energy' ), 5 => array( 'Stone Wall', 'Going over obstacles' ), 6 => array( 'Easy Does It', 'Too fast to catch' ),
		7 => array( 'Stepping Stones', 'Planning a path' ), 8 => array( 'Over the Log', 'Round obstacles' ), 9 => array( 'Low Ceiling', 'Tunnels and clearance' ),
		10 => array( 'Meadow Feast', 'Putting it together' ), 11 => array( 'First Breeze', 'Wind is a push' ), 12 => array( 'Headwind', 'Pushing against the wind' ),
		13 => array( 'Fan Club', 'Fans make wind' ), 14 => array( 'Bee Careful', 'Moving obstacles' ), 15 => array( 'Updraft', 'Lift from below' ),
		16 => array( 'Crosswinds', 'Winds in two directions' ), 17 => array( 'Busy Bees', 'Timing' ), 18 => array( 'Kite Hill', 'Climbing a hill' ),
		19 => array( 'Windy Gap', 'Crossing a gap' ), 20 => array( 'Windmill Picnic', 'Putting it together' ), 21 => array( 'Splash Down', 'Water slows things down' ),
		22 => array( 'Go With the Flow', 'Currents carry things' ), 23 => array( 'Against the Current', 'Working against a current' ), 24 => array( 'Fish Crossing', 'Moving water life' ),
		25 => array( 'Island Picnic', 'Landing on target' ), 26 => array( 'Deep Dive', 'Sinking takes time' ), 27 => array( 'School of Fish', 'Many moving things' ),
		28 => array( 'Stream Race', 'Fast water' ), 29 => array( 'Bubble Lift', 'Upward push in water' ), 30 => array( 'Pond Party', 'Putting it together' ),
		31 => array( 'The Falls', 'Waterfalls pull down' ), 32 => array( 'Behind the Falls', 'Using a force' ), 33 => array( 'Misty Ledge', 'Keeping height' ),
		34 => array( 'Canyon Wind', 'Wind vs waterfall' ), 35 => array( 'Salmon Leap', 'Everything moves' ), 36 => array( 'Canyon Gap', 'Arcs and ceilings' ),
		37 => array( 'Twin Falls', 'Precision' ), 38 => array( 'Bee Canyon', 'Choosing a route' ), 39 => array( 'Rapids', 'Strong currents' ),
		40 => array( "Lucas\u{2019}s Feast", 'Everything you learned' ),
	);
}

/* ------------------------------------------------------------------ assets */

function ascend_rl_register_assets() {
	$base = plugin_dir_url( __FILE__ ) . 'assets/';
	wp_register_style( 'ascend-rescue-lab', $base . 'rescue-lab.css', array(), ASCEND_RL_VERSION );
	wp_register_script( 'ascend-rescue-lab-levels', $base . 'levels.js', array(), ASCEND_RL_VERSION, true );
	wp_register_script( 'ascend-rescue-lab-engine', $base . 'engine.js', array( 'ascend-rescue-lab-levels' ), ASCEND_RL_VERSION, true );
	wp_register_script( 'ascend-rescue-lab-progress', $base . 'progress.js', array( 'ascend-rescue-lab-levels' ), ASCEND_RL_VERSION, true );
	wp_register_script( 'ascend-rescue-lab-art', $base . 'art.js', array( 'ascend-rescue-lab-engine' ), ASCEND_RL_VERSION, true );
	wp_register_script( 'ascend-rescue-lab', $base . 'rescue-lab.js', array( 'ascend-rescue-lab-progress', 'ascend-rescue-lab-art' ), ASCEND_RL_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'ascend_rl_register_assets' );

/* ----------------------------------------------------- settings and MCP */

/** Options this plugin stores. All are readable and writable over Royal MCP. */
function ascend_rl_options() {
	return array( 'ascend_rl_config', 'ascend_rl_level_text', 'ascend_rl_mascot_url', 'ascend_rl_hub_url' );
}

function ascend_rl_premium_skins() {
	return array( 'galaxy', 'pearl', 'disco' );
}

function ascend_rl_config_defaults() {
	return array(
		'freeLevels'  => 3,
		'keyCost'     => 1,
		'dailyUnlock' => true,
		'skinPrices'  => array( 'galaxy' => 1, 'pearl' => 1, 'disco' => 1 ),
	);
}

/** Accept an array or a JSON string (MCP clients send either); return clean values. */
function ascend_rl_sanitize_config( $raw ) {
	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		$raw     = is_array( $decoded ) ? $decoded : array();
	}
	$raw = is_array( $raw ) ? $raw : array();
	$d   = ascend_rl_config_defaults();
	$out = array(
		'freeLevels'  => isset( $raw['freeLevels'] ) ? max( 0, min( ASCEND_RL_LEVELS, (int) $raw['freeLevels'] ) ) : $d['freeLevels'],
		'keyCost'     => isset( $raw['keyCost'] ) ? max( 1, min( 20, (int) $raw['keyCost'] ) ) : $d['keyCost'],
		'dailyUnlock' => isset( $raw['dailyUnlock'] ) ? filter_var( $raw['dailyUnlock'], FILTER_VALIDATE_BOOLEAN ) : $d['dailyUnlock'],
		'skinPrices'  => $d['skinPrices'],
	);
	if ( isset( $raw['skinPrices'] ) && is_array( $raw['skinPrices'] ) ) {
		foreach ( ascend_rl_premium_skins() as $id ) {
			if ( isset( $raw['skinPrices'][ $id ] ) ) {
				$out['skinPrices'][ $id ] = max( 1, min( 20, (int) $raw['skinPrices'][ $id ] ) );
			}
		}
	}
	return $out;
}

/** Per-puzzle text overrides: { "12": { "name": "...", "intro": "...", "lesson": "...", "hint": "..." } }. */
function ascend_rl_sanitize_level_text( $raw ) {
	if ( is_string( $raw ) ) {
		$decoded = json_decode( $raw, true );
		$raw     = is_array( $decoded ) ? $decoded : array();
	}
	$out = array();
	if ( is_array( $raw ) ) {
		foreach ( $raw as $id => $fields ) {
			$id = (int) $id;
			if ( $id < 1 || $id > ASCEND_RL_LEVELS || ! is_array( $fields ) ) {
				continue;
			}
			foreach ( array( 'name' => 60, 'intro' => 300, 'lesson' => 500, 'hint' => 300 ) as $k => $max ) {
				if ( isset( $fields[ $k ] ) && is_string( $fields[ $k ] ) && '' !== trim( $fields[ $k ] ) ) {
					$out[ (string) $id ][ $k ] = mb_substr( sanitize_text_field( $fields[ $k ] ), 0, $max );
				}
			}
		}
	}
	return $out;
}

function ascend_rl_config() {
	return ascend_rl_sanitize_config( get_option( 'ascend_rl_config', array() ) );
}

add_filter( 'pre_update_option_ascend_rl_config', function ( $value ) { return ascend_rl_sanitize_config( $value ); } );
add_filter( 'pre_update_option_ascend_rl_level_text', function ( $value ) { return ascend_rl_sanitize_level_text( $value ); } );
add_filter( 'pre_update_option_ascend_rl_mascot_url', 'esc_url_raw' );
add_filter( 'pre_update_option_ascend_rl_hub_url', 'esc_url_raw' );

foreach ( array( 'royal_mcp_readable_options', 'royal_mcp_writable_options' ) as $ascend_rl_filter ) {
	add_filter( $ascend_rl_filter, function ( $options ) {
		$options = is_array( $options ) ? $options : array();
		return array_values( array_unique( array_merge( $options, ascend_rl_options() ) ) );
	} );
}
unset( $ascend_rl_filter );

/* Seed the options once so they show up in MCP listings straight away. */
add_action( 'admin_init', function () {
	if ( null === get_option( 'ascend_rl_config', null ) ) {
		add_option( 'ascend_rl_config', ascend_rl_config_defaults() );
	}
	if ( null === get_option( 'ascend_rl_level_text', null ) ) {
		add_option( 'ascend_rl_level_text', array() );
	}
} );

function ascend_rl_option_url( $key, $default ) {
	$v = get_option( $key, '' );
	return $v ? $v : $default;
}

/* ------------------------------------------------------- key-system bridge */

function ascend_rl_keys_available() {
	return function_exists( 'ascend_games_skips' ) && function_exists( 'ascend_games_consume_skip' );
}
function ascend_rl_has_pass( $user_id ) {
	return function_exists( 'ascend_games_has_pass' ) ? (bool) ascend_games_has_pass( $user_id ) : false;
}
function ascend_rl_key_balance( $user_id ) {
	return ascend_rl_keys_available() ? (int) ascend_games_skips( $user_id ) : null;
}
function ascend_rl_buy_url() {
	if ( function_exists( 'ascend_games_pass_url' ) ) {
		$u = ascend_games_pass_url( 'skip' );
		if ( $u ) {
			return $u;
		}
	}
	if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
		$pid = (int) wc_get_product_id_by_sku( 'ASCEND-POND-KEY' );
		if ( $pid ) {
			return get_permalink( $pid );
		}
	}
	return '';
}
function ascend_rl_pass_url() {
	return function_exists( 'ascend_games_pass_url' ) ? (string) ascend_games_pass_url( 'life' ) : '';
}

/* ------------------------------------------------------------- ownership */

/** Owned puzzles as { id => 'key' | 'daily' | 'grant' }. Reads the v1 list format too. */
function ascend_rl_owned( $user_id ) {
	$raw = get_user_meta( $user_id, ASCEND_RL_OWNED_META, true );
	$out = array();
	if ( is_array( $raw ) ) {
		foreach ( $raw as $k => $v ) {
			$id  = is_int( $k ) && ! is_string( $v ) ? (int) $v : (int) $k; // v1 stored a plain list of ids
			$src = is_string( $v ) ? $v : 'key';
			if ( $id >= 1 && $id <= ASCEND_RL_LEVELS ) {
				$out[ $id ] = in_array( $src, array( 'key', 'daily', 'grant' ), true ) ? $src : 'key';
			}
		}
	}
	ksort( $out );
	return $out;
}

function ascend_rl_is_open( $user_id, $level, $cfg = null ) {
	$cfg = $cfg ? $cfg : ascend_rl_config();
	return $level <= $cfg['freeLevels'] || ascend_rl_has_pass( $user_id ) || isset( ascend_rl_owned( $user_id )[ $level ] );
}

function ascend_rl_owned_skins( $user_id ) {
	$raw = get_user_meta( $user_id, ASCEND_RL_SKINS_META, true );
	return array_values( array_intersect( is_array( $raw ) ? $raw : array(), ascend_rl_premium_skins() ) );
}

/** Run $fn while holding a per-student MySQL lock, so concurrent taps are serialised. */
function ascend_rl_with_lock( $user_id, $fn ) {
	global $wpdb;
	$lock = 'ascend_rl_' . (int) $user_id;
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
		return new WP_Error( 'busy', 'Another request is in progress. Please try again.' );
	}
	try {
		wp_cache_delete( $user_id, 'user_meta' ); // re-read inside the lock
		return $fn();
	} finally {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

function ascend_rl_log( $user_id, $what, $cost ) {
	$log   = get_user_meta( $user_id, ASCEND_RL_LOG_META, true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array( 'what' => $what, 'time' => time(), 'cost' => $cost );
	update_user_meta( $user_id, ASCEND_RL_LOG_META, array_slice( $log, -60 ) );
}

/**
 * The daily rule: on a day the student has not claimed yet, the lowest locked
 * puzzle opens. One per day, nothing banked for missed days.
 * Returns the opened puzzle id or 0.
 */
function ascend_rl_claim_daily( $user_id ) {
	$cfg = ascend_rl_config();
	if ( ! $cfg['dailyUnlock'] || ascend_rl_has_pass( $user_id ) ) {
		return 0;
	}
	$today = current_time( 'Y-m-d' );
	$result = ascend_rl_with_lock( $user_id, function () use ( $user_id, $today, $cfg ) {
		if ( get_user_meta( $user_id, ASCEND_RL_DAILY_META, true ) === $today ) {
			return 0;
		}
		$owned = ascend_rl_owned( $user_id );
		for ( $id = $cfg['freeLevels'] + 1; $id <= ASCEND_RL_LEVELS; $id++ ) {
			if ( ! isset( $owned[ $id ] ) ) {
				$owned[ $id ] = 'daily';
				update_user_meta( $user_id, ASCEND_RL_OWNED_META, $owned );
				update_user_meta( $user_id, ASCEND_RL_DAILY_META, $today );
				ascend_rl_log( $user_id, 'daily:' . $id, 0 );
				return $id;
			}
		}
		return 0;
	} );
	return is_wp_error( $result ) ? 0 : (int) $result;
}

/** Spend keys for one puzzle. Idempotent: an owned puzzle is never charged twice. */
function ascend_rl_unlock_level( $user_id, $level ) {
	$level = (int) $level;
	$cfg   = ascend_rl_config();
	if ( $level < 1 || $level > ASCEND_RL_LEVELS ) {
		return new WP_Error( 'bad_level', 'That puzzle does not exist.' );
	}
	if ( ascend_rl_is_open( $user_id, $level, $cfg ) ) {
		return array( 'spent' => 0 );
	}
	if ( ! ascend_rl_keys_available() ) {
		return new WP_Error( 'keys_unavailable', 'The key system is not available right now.' );
	}
	return ascend_rl_with_lock( $user_id, function () use ( $user_id, $level, $cfg ) {
		$owned = ascend_rl_owned( $user_id );
		if ( isset( $owned[ $level ] ) ) {
			return array( 'spent' => 0 );
		}
		if ( (int) ascend_games_skips( $user_id ) < $cfg['keyCost'] ) {
			return new WP_Error( 'no_keys', 'Not enough Pond Keys on this account.' );
		}
		for ( $i = 0; $i < $cfg['keyCost']; $i++ ) {
			ascend_games_consume_skip( $user_id );
		}
		$owned[ $level ] = 'key';
		update_user_meta( $user_id, ASCEND_RL_OWNED_META, $owned );
		ascend_rl_log( $user_id, 'puzzle:' . $level, $cfg['keyCost'] );
		do_action( 'ascend_rl_level_unlocked', $user_id, $level );
		return array( 'spent' => $cfg['keyCost'] );
	} );
}

/** Spend keys for a special pellet skin. Idempotent like puzzles. */
function ascend_rl_buy_skin( $user_id, $skin ) {
	$cfg = ascend_rl_config();
	if ( ! in_array( $skin, ascend_rl_premium_skins(), true ) ) {
		return new WP_Error( 'bad_skin', 'That pellet is not for sale.' );
	}
	if ( in_array( $skin, ascend_rl_owned_skins( $user_id ), true ) ) {
		return array( 'spent' => 0 );
	}
	if ( ! ascend_rl_keys_available() ) {
		return new WP_Error( 'keys_unavailable', 'The key system is not available right now.' );
	}
	$price = (int) $cfg['skinPrices'][ $skin ];
	return ascend_rl_with_lock( $user_id, function () use ( $user_id, $skin, $price ) {
		$owned = ascend_rl_owned_skins( $user_id );
		if ( in_array( $skin, $owned, true ) ) {
			return array( 'spent' => 0 );
		}
		if ( (int) ascend_games_skips( $user_id ) < $price ) {
			return new WP_Error( 'no_keys', 'Not enough Pond Keys on this account.' );
		}
		for ( $i = 0; $i < $price; $i++ ) {
			ascend_games_consume_skip( $user_id );
		}
		$owned[] = $skin;
		update_user_meta( $user_id, ASCEND_RL_SKINS_META, $owned );
		ascend_rl_log( $user_id, 'skin:' . $skin, $price );
		return array( 'spent' => $price );
	} );
}

/* --------------------------------------------------------------- progress */

/** Server-side sanitiser mirroring progress.js: only earned facts survive. */
function ascend_rl_sanitize_progress( $raw ) {
	$raw   = is_array( $raw ) ? $raw : array();
	$skins = array( 'pink', 'blueberry', 'lime', 'sunny', 'mango', 'bubble', 'lava', 'rainbow', 'golden', 'galaxy', 'pearl', 'disco' );
	$out   = array( 'v' => 2, 'levels' => array(), 'settings' => array( 'sound' => true, 'ghost' => true, 'assist' => false, 'skin' => 'pink' ), 'runs' => 0, 'fails' => 0 );
	if ( isset( $raw['settings'] ) && is_array( $raw['settings'] ) ) {
		foreach ( array( 'sound', 'ghost', 'assist' ) as $k ) {
			if ( isset( $raw['settings'][ $k ] ) && is_bool( $raw['settings'][ $k ] ) ) {
				$out['settings'][ $k ] = $raw['settings'][ $k ];
			}
		}
		if ( isset( $raw['settings']['skin'] ) && in_array( $raw['settings']['skin'], $skins, true ) ) {
			$out['settings']['skin'] = $raw['settings']['skin'];
		}
	}
	$out['runs']  = max( 0, (int) ( $raw['runs'] ?? 0 ) );
	$out['fails'] = max( 0, (int) ( $raw['fails'] ?? 0 ) );
	if ( isset( $raw['levels'] ) && is_array( $raw['levels'] ) ) {
		foreach ( $raw['levels'] as $id => $src ) {
			$id = (int) $id;
			if ( $id < 1 || $id > ASCEND_RL_LEVELS || ! is_array( $src ) ) {
				continue;
			}
			$lv = array( 'done' => ! empty( $src['done'] ), 'badges' => array(), 'designs' => array(), 'best' => null );
			foreach ( array( 'rescue', 'efficiency', 'invention' ) as $b ) {
				if ( isset( $src['badges'][ $b ] ) && true === $src['badges'][ $b ] ) {
					$lv['badges'][ $b ] = true;
				}
			}
			if ( isset( $src['designs'] ) && is_array( $src['designs'] ) ) {
				foreach ( array_slice( $src['designs'], 0, 6 ) as $d ) {
					if ( is_array( $d ) && isset( $d['design']['parts'] ) && is_array( $d['design']['parts'] ) && strlen( wp_json_encode( $d['design'] ) ) < 3000 ) {
						$lv['designs'][] = array(
							'name'   => mb_substr( sanitize_text_field( (string) ( $d['name'] ?? 'Design' ) ), 0, 40 ),
							'ts'     => (int) ( $d['ts'] ?? 0 ),
							'design' => array( 'parts' => array_slice( $d['design']['parts'], 0, 12 ) ),
							'stats'  => isset( $d['stats'] ) && is_array( $d['stats'] ) ? $d['stats'] : array(),
						);
					}
				}
			}
			if ( isset( $src['best'] ) && is_array( $src['best'] ) ) {
				$lv['best'] = array( 'time' => (float) ( $src['best']['time'] ?? 0 ), 'parts' => (int) ( $src['best']['parts'] ?? 0 ) );
			}
			$out['levels'][ (string) $id ] = $lv;
		}
	}
	return $out;
}

/** Monotonic merge: nothing earned is ever lost by a sync. */
function ascend_rl_merge_progress( $a, $b ) {
	$a   = ascend_rl_sanitize_progress( $a );
	$b   = ascend_rl_sanitize_progress( $b );
	$out = $a;
	$out['runs']  = max( $a['runs'], $b['runs'] );
	$out['fails'] = max( $a['fails'], $b['fails'] );
	if ( $b['runs'] > $a['runs'] ) {
		$out['settings'] = $b['settings'];
	}
	foreach ( $b['levels'] as $id => $lb ) {
		if ( ! isset( $out['levels'][ $id ] ) ) {
			$out['levels'][ $id ] = $lb;
			continue;
		}
		$la           = $out['levels'][ $id ];
		$la['done']   = $la['done'] || $lb['done'];
		$la['badges'] = $la['badges'] + $lb['badges'];
		if ( $lb['best'] && ( ! $la['best'] || $lb['best']['time'] < $la['best']['time'] ) ) {
			$la['best'] = $lb['best'];
		}
		$seen = array();
		foreach ( $la['designs'] as $d ) {
			$seen[ wp_json_encode( $d['design'] ) ] = true;
		}
		foreach ( $lb['designs'] as $d ) {
			$k = wp_json_encode( $d['design'] );
			if ( empty( $seen[ $k ] ) ) {
				$seen[ $k ]       = true;
				$la['designs'][] = $d;
			}
		}
		usort( $la['designs'], function ( $x, $y ) { return $y['ts'] <=> $x['ts']; } );
		$la['designs']        = array_slice( $la['designs'], 0, 6 );
		$out['levels'][ $id ] = $la;
	}
	return $out;
}

function ascend_rl_get_progress( $user_id ) {
	return ascend_rl_sanitize_progress( get_user_meta( $user_id, ASCEND_RL_PROGRESS_META, true ) );
}

/** Everything the front end needs; access comes only from here. */
function ascend_rl_state_payload( $user_id, $daily_opened = 0 ) {
	$cfg = ascend_rl_config();
	return array(
		'progress'      => ascend_rl_get_progress( $user_id ),
		'owned'         => (object) ascend_rl_owned( $user_id ),
		'hasPass'       => ascend_rl_has_pass( $user_id ),
		'keys'          => ascend_rl_key_balance( $user_id ),
		'keysAvailable' => ascend_rl_keys_available(),
		'ownedSkins'    => ascend_rl_owned_skins( $user_id ),
		'config'        => $cfg,
		'today'         => current_time( 'Y-m-d' ),
		'lastDaily'     => (string) get_user_meta( $user_id, ASCEND_RL_DAILY_META, true ),
		'dailyOpened'   => $daily_opened ? $daily_opened : null,
	);
}

/* ------------------------------------------------------------------- REST */

function ascend_rl_error_response( WP_Error $e ) {
	$status = array( 'no_keys' => 402, 'bad_level' => 400, 'bad_skin' => 400 );
	return new WP_REST_Response( array( 'code' => $e->get_error_code(), 'message' => $e->get_error_message() ), $status[ $e->get_error_code() ] ?? 409 );
}

function ascend_rl_register_routes() {
	$auth = 'is_user_logged_in';
	register_rest_route( 'ascend-rl/v1', '/state', array(
		'methods' => WP_REST_Server::READABLE, 'permission_callback' => $auth,
		'callback' => function () { return ascend_rl_state_payload( get_current_user_id() ); },
	) );
	register_rest_route( 'ascend-rl/v1', '/daily', array(
		'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => $auth,
		'callback' => function () {
			$uid = get_current_user_id();
			return ascend_rl_state_payload( $uid, ascend_rl_claim_daily( $uid ) );
		},
	) );
	register_rest_route( 'ascend-rl/v1', '/progress', array(
		'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => $auth,
		'callback' => function ( WP_REST_Request $request ) {
			$uid      = get_current_user_id();
			$body     = $request->get_json_params();
			$incoming = isset( $body['progress'] ) ? $body['progress'] : $body;
			$merged   = ascend_rl_merge_progress( ascend_rl_get_progress( $uid ), $incoming );
			update_user_meta( $uid, ASCEND_RL_PROGRESS_META, $merged );
			do_action( 'ascend_rl_progress_saved', $uid, $merged );
			return ascend_rl_state_payload( $uid );
		},
	) );
	register_rest_route( 'ascend-rl/v1', '/unlock', array(
		'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => $auth,
		'callback' => function ( WP_REST_Request $request ) {
			$uid  = get_current_user_id();
			$body = $request->get_json_params();
			$r    = ascend_rl_unlock_level( $uid, isset( $body['level'] ) ? (int) $body['level'] : 0 );
			return is_wp_error( $r ) ? ascend_rl_error_response( $r ) : ascend_rl_state_payload( $uid );
		},
	) );
	register_rest_route( 'ascend-rl/v1', '/skin', array(
		'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => $auth,
		'callback' => function ( WP_REST_Request $request ) {
			$uid  = get_current_user_id();
			$body = $request->get_json_params();
			$r    = ascend_rl_buy_skin( $uid, isset( $body['skin'] ) ? sanitize_key( $body['skin'] ) : '' );
			return is_wp_error( $r ) ? ascend_rl_error_response( $r ) : ascend_rl_state_payload( $uid );
		},
	) );
}
add_action( 'rest_api_init', 'ascend_rl_register_routes' );

/* -------------------------------------------------------------- shortcode */

/** [ascend_rescue_lab]: the game. Guests can play the free puzzles. */
function ascend_rl_shortcode() {
	wp_enqueue_style( 'ascend-rescue-lab' );
	wp_enqueue_script( 'ascend-rescue-lab' );
	$logged_in = is_user_logged_in();
	$config    = array(
		'preview'   => false,
		'loggedIn'  => $logged_in,
		'restUrl'   => esc_url_raw( rest_url( 'ascend-rl/v1/' ) ),
		'nonce'     => $logged_in ? wp_create_nonce( 'wp_rest' ) : '',
		'hubUrl'    => esc_url_raw( ascend_rl_option_url( 'ascend_rl_hub_url', home_url( '/user/' ) ) ),
		'loginUrl'  => esc_url_raw( home_url( '/login/' ) ),
		'mascotUrl' => esc_url_raw( ascend_rl_option_url( 'ascend_rl_mascot_url', 'https://ascendstemacademy.com/wp-content/uploads/2026/09/stem-a-lotl-scientist-sticker.png' ) ),
		'buyUrl'    => esc_url_raw( ascend_rl_buy_url() ),
		'passUrl'   => esc_url_raw( ascend_rl_pass_url() ),
		'config'    => ascend_rl_config(),
		'levelText' => (object) ascend_rl_sanitize_level_text( get_option( 'ascend_rl_level_text', array() ) ),
	);
	wp_add_inline_script( 'ascend-rescue-lab', 'window.AscendRL = ' . wp_json_encode( $config ) . ';', 'before' );
	return '<div class="rl-root" id="rl-game"><noscript>' . esc_html__( 'STEM-a-lotl: Rescue Lab needs JavaScript turned on to play.', 'ascend-rescue-lab' ) . '</noscript></div>';
}
add_shortcode( 'ascend_rescue_lab', 'ascend_rl_shortcode' );

/** [ascend_rescue_lab_summary]: compact parent summary for the student dashboard. */
function ascend_rl_summary_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$uid     = get_current_user_id();
	$p       = ascend_rl_get_progress( $uid );
	$cat     = ascend_rl_catalog();
	$done    = array();
	$stars   = 0;
	$designs = 0;
	foreach ( $p['levels'] as $id => $lv ) {
		if ( $lv['done'] ) {
			$done[] = (int) $id;
		}
		$stars   += count( $lv['badges'] );
		$designs += count( $lv['designs'] );
	}
	sort( $done );
	$ideas = array_values( array_unique( array_map( function ( $id ) use ( $cat ) { return $cat[ $id ][1]; }, $done ) ) );
	ob_start();
	?>
	<div class="rl-summary" style="border:1px solid #e4e1da;border-radius:14px;padding:14px 16px;font-size:14px;line-height:1.5;">
		<strong style="color:#173e63;">STEM-a-lotl: Rescue Lab</strong>
		<ul style="margin:6px 0 0 1.1em;padding:0;">
			<li>Puzzles solved: <?php echo (int) count( $done ); ?> of <?php echo (int) ASCEND_RL_LEVELS; ?> &middot; Stars: <?php echo (int) $stars; ?> of <?php echo (int) ( ASCEND_RL_LEVELS * 3 ); ?></li>
			<li>Ideas practised: <?php echo $ideas ? esc_html( implode( '; ', array_slice( $ideas, 0, 12 ) ) ) : 'none yet'; ?></li>
			<li>Saved inventions: <?php echo (int) $designs; ?> &middot; Experiment runs: <?php echo (int) $p['runs']; ?> (<?php echo (int) $p['fails']; ?> misses that led to a retry)</li>
		</ul>
		<p style="margin:8px 0 0;font-size:12px;color:#5f656a;">Practice observations from play, not a validated assessment, attendance record, official credit or certification.</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ascend_rescue_lab_summary', 'ascend_rl_summary_shortcode' );

/* ------------------------------------------------------------------ admin */

add_action( 'admin_menu', function () {
	$parent = menu_page_url( 'ascend-games', false ) ? 'ascend-games' : 'options-general.php';
	add_submenu_page( $parent, 'Rescue Lab', 'Rescue Lab', 'manage_options', 'ascend-rescue-lab', 'ascend_rl_admin_page' );
}, 20 );

function ascend_rl_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['ascend_rl_save'] ) && check_admin_referer( 'ascend_rl_settings' ) ) {
		$prices = array();
		foreach ( ascend_rl_premium_skins() as $id ) {
			$prices[ $id ] = (int) ( $_POST[ 'price_' . $id ] ?? 1 );
		}
		update_option( 'ascend_rl_config', array(
			'freeLevels'  => (int) ( $_POST['free_levels'] ?? 3 ),
			'keyCost'     => (int) ( $_POST['key_cost'] ?? 1 ),
			'dailyUnlock' => ! empty( $_POST['daily_unlock'] ),
			'skinPrices'  => $prices,
		) );
		update_option( 'ascend_rl_level_text', wp_unslash( $_POST['level_text'] ?? '{}' ) );
		update_option( 'ascend_rl_mascot_url', wp_unslash( $_POST['mascot_url'] ?? '' ) );
		update_option( 'ascend_rl_hub_url', wp_unslash( $_POST['hub_url'] ?? '' ) );
		echo '<div class="updated"><p>Saved.</p></div>';
	}
	if ( isset( $_POST['ascend_rl_grant'] ) && check_admin_referer( 'ascend_rl_grant' ) ) {
		$uid = (int) ( $_POST['user_id'] ?? 0 );
		$lvl = (int) ( $_POST['level'] ?? 0 );
		if ( $uid && $lvl >= 1 && $lvl <= ASCEND_RL_LEVELS ) {
			$owned         = ascend_rl_owned( $uid );
			$owned[ $lvl ] = 'grant';
			update_user_meta( $uid, ASCEND_RL_OWNED_META, $owned );
			ascend_rl_log( $uid, 'grant:' . $lvl, 0 );
			echo '<div class="updated"><p>Puzzle ' . (int) $lvl . ' opened for user ' . (int) $uid . ' (no key spent).</p></div>';
		}
	}
	$cfg  = ascend_rl_config();
	$text = wp_json_encode( ascend_rl_sanitize_level_text( get_option( 'ascend_rl_level_text', array() ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	echo '<div class="wrap"><h1>STEM-a-lotl: Rescue Lab</h1>';
	echo '<p><code>[ascend_rescue_lab]</code> shows the game. <code>[ascend_rescue_lab_summary]</code> shows the parent summary (add it to the /user/ dashboard).</p>';
	echo '<p>Key system: ' . ( ascend_rl_keys_available() ? '<strong>connected</strong> to Ascend Axolotl Games (Pond Keys).' : '<strong>not found</strong>. Keys cannot be used until the Ascend Axolotl Games plugin is active; free and daily puzzles still work.' ) . ' Buy link: <code>' . esc_html( ascend_rl_buy_url() ?: '(none)' ) . '</code></p>';
	echo '<p>These settings are the options <code>ascend_rl_config</code>, <code>ascend_rl_level_text</code>, <code>ascend_rl_mascot_url</code> and <code>ascend_rl_hub_url</code>; Royal MCP can read and write all of them.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'ascend_rl_settings' );
	echo '<table class="form-table">';
	echo '<tr><th>Free puzzles</th><td><input type="number" min="0" max="40" name="free_levels" value="' . (int) $cfg['freeLevels'] . '"> <span class="description">Puzzles 1 to N are free for everyone.</span></td></tr>';
	echo '<tr><th>Daily free puzzle</th><td><label><input type="checkbox" name="daily_unlock" value="1"' . checked( $cfg['dailyUnlock'], true, false ) . '> Open the next locked puzzle once per day when the student visits (missed days are not banked)</label></td></tr>';
	echo '<tr><th>Pond Keys per puzzle</th><td><input type="number" min="1" max="20" name="key_cost" value="' . (int) $cfg['keyCost'] . '"></td></tr>';
	foreach ( ascend_rl_premium_skins() as $id ) {
		echo '<tr><th>Pond Keys for the ' . esc_html( ucfirst( $id ) ) . ' pellet</th><td><input type="number" min="1" max="20" name="price_' . esc_attr( $id ) . '" value="' . (int) $cfg['skinPrices'][ $id ] . '"></td></tr>';
	}
	echo '<tr><th>Puzzle text overrides</th><td><textarea name="level_text" rows="8" class="large-text code">' . esc_textarea( $text ) . '</textarea><p class="description">JSON, e.g. {"12": {"name": "Headwind", "lesson": "..."}}. Fields: name, intro, lesson, hint. Physics and layout are not editable here.</p></td></tr>';
	echo '<tr><th>Header mascot image URL</th><td><input type="url" class="regular-text" name="mascot_url" value="' . esc_attr( get_option( 'ascend_rl_mascot_url', '' ) ) . '"></td></tr>';
	echo '<tr><th>Games hub URL</th><td><input type="url" class="regular-text" name="hub_url" value="' . esc_attr( get_option( 'ascend_rl_hub_url', '' ) ) . '"></td></tr>';
	echo '</table><p><button class="button button-primary" name="ascend_rl_save" value="1">Save</button></p></form>';
	echo '<h2>Open a puzzle for a student (support use)</h2><form method="post">';
	wp_nonce_field( 'ascend_rl_grant' );
	echo '<p><label>User ID <input type="number" name="user_id" min="1"></label> <label>Puzzle <input type="number" name="level" min="1" max="40"></label> <button class="button" name="ascend_rl_grant" value="1">Open without a key</button></p></form>';
	echo '<h2>Recent unlocks and purchases</h2>';
	$users = get_users( array( 'meta_key' => ASCEND_RL_LOG_META, 'number' => 50 ) );
	if ( ! $users ) {
		echo '<p>None yet.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>Student</th><th>What</th><th>When</th><th>Keys spent</th></tr></thead><tbody>';
		foreach ( $users as $u ) {
			$log = get_user_meta( $u->ID, ASCEND_RL_LOG_META, true );
			foreach ( array_reverse( is_array( $log ) ? $log : array() ) as $row ) {
				echo '<tr><td>' . esc_html( $u->display_name ) . '</td><td>' . esc_html( $row['what'] ) . '</td><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $row['time'] ) ) . '</td><td>' . (int) $row['cost'] . '</td></tr>';
			}
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

add_action( 'show_user_profile', 'ascend_rl_profile_box' );
add_action( 'edit_user_profile', 'ascend_rl_profile_box' );
function ascend_rl_profile_box( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$p    = ascend_rl_get_progress( $user->ID );
	$done = array_keys( array_filter( $p['levels'], function ( $lv ) { return $lv['done']; } ) );
	$own  = array();
	foreach ( ascend_rl_owned( $user->ID ) as $id => $src ) {
		$own[] = $id . ' (' . $src . ')';
	}
	echo '<h2>Rescue Lab</h2><table class="form-table"><tr><th>Puzzles solved</th><td>' . esc_html( $done ? implode( ', ', $done ) : 'none' ) . '</td></tr>';
	echo '<tr><th>Opened beyond the free ones</th><td>' . esc_html( $own ? implode( ', ', $own ) : 'none' ) . '</td></tr>';
	echo '<tr><th>Last daily puzzle</th><td>' . esc_html( get_user_meta( $user->ID, ASCEND_RL_DAILY_META, true ) ?: 'never' ) . '</td></tr>';
	echo '<tr><th>Special pellets</th><td>' . esc_html( implode( ', ', ascend_rl_owned_skins( $user->ID ) ) ?: 'none' ) . '</td></tr></table>';
}
