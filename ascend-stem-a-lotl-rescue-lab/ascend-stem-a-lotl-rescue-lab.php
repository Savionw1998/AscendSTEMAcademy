<?php
/**
 * Plugin Name:       Ascend STEM-a-lotl: Rescue Lab
 * Plugin URI:        https://ascendstemacademy.com/
 * Description:       An engineering game for the Axolotl Games family. Kids build ramps, bridges, gears, circuits and rover programs to bring a research station back to life. Shortcode: [ascend_rescue_lab]. Parent summary: [ascend_rescue_lab_summary].
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Ascend STEM Academy
 * License:           GPL-2.0-or-later
 * Text Domain:       ascend-rescue-lab
 *
 * ---------------------------------------------------------------------------
 * INTEGRATION WITH THE EXISTING KEY SYSTEM (Ascend Axolotl Games plugin)
 *
 * Keys are the site's Pond Keys: the shared plugin stores them in the user
 * meta `ascend_games_skips` and exposes ascend_games_skips(),
 * ascend_games_consume_skip(), ascend_games_has_pass() and
 * ascend_games_pass_url(). This plugin never touches those meta keys directly:
 * it calls the shared functions when they exist, and reports the key system
 * as unavailable when they do not. WooCommerce fulfilment, refunds and the
 * Full Pond Pass stay exactly as they are.
 *
 * Accounting difference, stated plainly: in the daily puzzle games one Pond
 * Key opens one extra puzzle *today*. Here one Pond Key opens one mission
 * *permanently* on the student account (its retries, challenges and
 * variations included). Ownership is recorded in the user meta
 * `ascend_rl_owned` and is the only authority for paid access; the browser
 * never decides it.
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASCEND_RL_VERSION', '1.0.0' );
define( 'ASCEND_RL_LEVELS', 10 );
define( 'ASCEND_RL_FREE_LEVELS', 3 );
define( 'ASCEND_RL_KEY_COST', 1 );
define( 'ASCEND_RL_PROGRESS_META', 'ascend_rl_progress' );
define( 'ASCEND_RL_OWNED_META', 'ascend_rl_owned' );
define( 'ASCEND_RL_UNLOCK_LOG_META', 'ascend_rl_unlock_log' );

/* ------------------------------------------------------------------ assets */

function ascend_rl_register_assets() {
	$base = plugin_dir_url( __FILE__ ) . 'assets/';
	wp_register_style( 'ascend-rescue-lab', $base . 'rescue-lab.css', array(), ASCEND_RL_VERSION );
	wp_register_script( 'ascend-rescue-lab-levels', $base . 'levels.js', array(), ASCEND_RL_VERSION, true );
	wp_register_script( 'ascend-rescue-lab-engine', $base . 'engine.js', array( 'ascend-rescue-lab-levels' ), ASCEND_RL_VERSION, true );
	wp_register_script( 'ascend-rescue-lab-progress', $base . 'progress.js', array( 'ascend-rescue-lab-levels' ), ASCEND_RL_VERSION, true );
	wp_register_script( 'ascend-rescue-lab', $base . 'rescue-lab.js', array( 'ascend-rescue-lab-engine', 'ascend-rescue-lab-progress' ), ASCEND_RL_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'ascend_rl_register_assets' );

/* --------------------------------------------------------------- settings */

function ascend_rl_defaults() {
	return array(
		'ascend_rl_mascot_url'       => 'https://ascendstemacademy.com/wp-content/uploads/2026/09/stem-a-lotl-scientist-sticker.png',
		'ascend_rl_mascot_happy_url' => 'https://ascendstemacademy.com/wp-content/uploads/2026/09/lucas-axolotl-wink.png',
		'ascend_rl_hub_url'          => home_url( '/user/' ),
	);
}
function ascend_rl_option( $key ) {
	$defaults = ascend_rl_defaults();
	$v        = get_option( $key, null );
	return ( null === $v || '' === $v ) ? ( isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' ) : $v;
}

/* ------------------------------------------------------- key-system bridge */

/** True when the Ascend Axolotl Games plugin's key functions are loaded. */
function ascend_rl_keys_available() {
	return function_exists( 'ascend_games_skips' ) && function_exists( 'ascend_games_consume_skip' );
}
function ascend_rl_has_pass( $user_id ) {
	return function_exists( 'ascend_games_has_pass' ) ? (bool) ascend_games_has_pass( $user_id ) : false;
}
function ascend_rl_key_balance( $user_id ) {
	return ascend_rl_keys_available() ? (int) ascend_games_skips( $user_id ) : null;
}
/** Where a parent buys a Pond Key: the shared plugin's link, else the product by SKU. */
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
	if ( function_exists( 'ascend_games_pass_url' ) ) {
		$u = ascend_games_pass_url( 'life' );
		if ( $u ) {
			return $u;
		}
	}
	return '';
}

function ascend_rl_owned( $user_id ) {
	$raw = get_user_meta( $user_id, ASCEND_RL_OWNED_META, true );
	$out = array();
	if ( is_array( $raw ) ) {
		foreach ( $raw as $id ) {
			$id = (int) $id;
			if ( $id > ASCEND_RL_FREE_LEVELS && $id <= ASCEND_RL_LEVELS ) {
				$out[] = $id;
			}
		}
	}
	sort( $out );
	return array_values( array_unique( $out ) );
}

/**
 * Redeem one Pond Key for one mission, atomically and idempotently.
 *
 * - Already owned (or pass holder): success without spending. A retry after a
 *   dropped connection therefore never costs a second key.
 * - MySQL GET_LOCK serialises concurrent attempts for the same student, and the
 *   balance is re-read inside the lock, so two taps cannot double-spend.
 * - Returns a WP_Error naming the reason otherwise; nothing is spent on error.
 */
function ascend_rl_unlock_level( $user_id, $level ) {
	global $wpdb;
	$level = (int) $level;
	if ( $level < 1 || $level > ASCEND_RL_LEVELS ) {
		return new WP_Error( 'bad_level', 'That mission does not exist.' );
	}
	if ( $level <= ASCEND_RL_FREE_LEVELS ) {
		return array( 'owned' => ascend_rl_owned( $user_id ), 'spent' => 0, 'already' => true );
	}
	if ( ascend_rl_has_pass( $user_id ) || in_array( $level, ascend_rl_owned( $user_id ), true ) ) {
		return array( 'owned' => ascend_rl_owned( $user_id ), 'spent' => 0, 'already' => true );
	}
	if ( ! ascend_rl_keys_available() ) {
		return new WP_Error( 'keys_unavailable', 'The key system is not available right now. No key was taken.' );
	}

	$lock   = 'ascend_rl_unlock_' . (int) $user_id;
	$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) );
	if ( '1' !== (string) $locked ) {
		return new WP_Error( 'busy', 'Another unlock is in progress. Please try again.' );
	}
	try {
		wp_cache_delete( $user_id, 'user_meta' ); // re-read inside the lock
		if ( in_array( $level, ascend_rl_owned( $user_id ), true ) ) {
			return array( 'owned' => ascend_rl_owned( $user_id ), 'spent' => 0, 'already' => true );
		}
		if ( (int) ascend_games_skips( $user_id ) < ASCEND_RL_KEY_COST ) {
			return new WP_Error( 'no_keys', 'No Pond Key on this account yet.' );
		}
		if ( ! ascend_games_consume_skip( $user_id ) ) {
			return new WP_Error( 'no_keys', 'No Pond Key on this account yet.' );
		}
		$owned   = ascend_rl_owned( $user_id );
		$owned[] = $level;
		sort( $owned );
		update_user_meta( $user_id, ASCEND_RL_OWNED_META, array_values( array_unique( $owned ) ) );
		$log   = get_user_meta( $user_id, ASCEND_RL_UNLOCK_LOG_META, true );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array( 'level' => $level, 'time' => time(), 'cost' => ASCEND_RL_KEY_COST );
		update_user_meta( $user_id, ASCEND_RL_UNLOCK_LOG_META, array_slice( $log, -50 ) );
		do_action( 'ascend_rl_level_unlocked', $user_id, $level );
		return array( 'owned' => ascend_rl_owned( $user_id ), 'spent' => ASCEND_RL_KEY_COST, 'already' => false );
	} finally {
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/* --------------------------------------------------------------- progress */

/** Server-side sanitiser mirroring progress.js: only earned facts survive. */
function ascend_rl_sanitize_progress( $raw ) {
	$raw = is_array( $raw ) ? $raw : array();
	$out = array( 'v' => 1, 'levels' => array(), 'settings' => array( 'sound' => true, 'ghost' => true, 'assist' => false ), 'runs' => 0, 'fails' => 0, 'practice' => array() );
	if ( isset( $raw['settings'] ) && is_array( $raw['settings'] ) ) {
		foreach ( array( 'sound', 'ghost', 'assist' ) as $k ) {
			if ( isset( $raw['settings'][ $k ] ) && is_bool( $raw['settings'][ $k ] ) ) {
				$out['settings'][ $k ] = $raw['settings'][ $k ];
			}
		}
	}
	$out['runs']  = max( 0, (int) ( $raw['runs'] ?? 0 ) );
	$out['fails'] = max( 0, (int) ( $raw['fails'] ?? 0 ) );
	if ( isset( $raw['practice'] ) && is_array( $raw['practice'] ) ) {
		foreach ( $raw['practice'] as $k => $v ) {
			if ( preg_match( '/^\d{4}-W\d{2}$/', (string) $k ) && true === $v ) {
				$out['practice'][ $k ] = true;
			}
		}
	}
	if ( isset( $raw['levels'] ) && is_array( $raw['levels'] ) ) {
		foreach ( $raw['levels'] as $id => $src ) {
			$id = (int) $id;
			if ( $id < 1 || $id > ASCEND_RL_LEVELS || ! is_array( $src ) ) {
				continue;
			}
			$lv = array( 'done' => ! empty( $src['done'] ), 'badges' => array(), 'variations' => array(), 'designs' => array(), 'stage' => max( 0, (int) ( $src['stage'] ?? 0 ) ), 'best' => null );
			foreach ( array( 'rescue', 'efficiency', 'invention' ) as $b ) {
				if ( isset( $src['badges'][ $b ] ) && true === $src['badges'][ $b ] ) {
					$lv['badges'][ $b ] = true;
				}
			}
			if ( isset( $src['variations'] ) && is_array( $src['variations'] ) ) {
				foreach ( $src['variations'] as $v => $flag ) {
					if ( preg_match( '/^\d+$/', (string) $v ) && true === $flag ) {
						$lv['variations'][ (string) $v ] = true;
					}
				}
			}
			if ( isset( $src['designs'] ) && is_array( $src['designs'] ) ) {
				foreach ( array_slice( $src['designs'], 0, 6 ) as $d ) {
					if ( is_array( $d ) && isset( $d['design'] ) && strlen( wp_json_encode( $d['design'] ) ) < 4000 ) {
						$lv['designs'][] = array(
							'name'      => mb_substr( sanitize_text_field( (string) ( $d['name'] ?? 'Design' ) ), 0, 40 ),
							'ts'        => (int) ( $d['ts'] ?? 0 ),
							'variation' => (int) ( $d['variation'] ?? 0 ),
							'design'    => $d['design'],
							'stats'     => isset( $d['stats'] ) && is_array( $d['stats'] ) ? $d['stats'] : array(),
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
	$out['runs']     = max( $a['runs'], $b['runs'] );
	$out['fails']    = max( $a['fails'], $b['fails'] );
	$out['practice'] = $a['practice'] + $b['practice'];
	if ( $b['runs'] > $a['runs'] ) {
		$out['settings'] = $b['settings'];
	}
	foreach ( $b['levels'] as $id => $lb ) {
		if ( ! isset( $out['levels'][ $id ] ) ) {
			$out['levels'][ $id ] = $lb;
			continue;
		}
		$la               = $out['levels'][ $id ];
		$la['done']       = $la['done'] || $lb['done'];
		$la['stage']      = max( $la['stage'], $lb['stage'] );
		$la['badges']     = $la['badges'] + $lb['badges'];
		$la['variations'] = $la['variations'] + $lb['variations'];
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

/** The payload the front end needs: progress plus server-authoritative access. */
function ascend_rl_state_payload( $user_id ) {
	return array(
		'progress'      => ascend_rl_get_progress( $user_id ),
		'owned'         => ascend_rl_owned( $user_id ),
		'hasPass'       => ascend_rl_has_pass( $user_id ),
		'keys'          => ascend_rl_key_balance( $user_id ),
		'keysAvailable' => ascend_rl_keys_available(),
	);
}

/* ------------------------------------------------------------------- REST */

function ascend_rl_register_routes() {
	register_rest_route(
		'ascend-rl/v1',
		'/progress',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => 'is_user_logged_in',
				'callback'            => function () {
					return ascend_rl_state_payload( get_current_user_id() );
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => 'is_user_logged_in',
				'callback'            => function ( WP_REST_Request $request ) {
					$user_id  = get_current_user_id();
					$body     = $request->get_json_params();
					$incoming = isset( $body['progress'] ) ? $body['progress'] : $body;
					$merged   = ascend_rl_merge_progress( ascend_rl_get_progress( $user_id ), $incoming );
					update_user_meta( $user_id, ASCEND_RL_PROGRESS_META, $merged );
					/** Other Ascend plugins (dashboard, transcript) can react to a save. */
					do_action( 'ascend_rl_progress_saved', $user_id, $merged );
					return ascend_rl_state_payload( $user_id );
				},
			),
		)
	);
	register_rest_route(
		'ascend-rl/v1',
		'/unlock',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'permission_callback' => 'is_user_logged_in',
			'callback'            => function ( WP_REST_Request $request ) {
				$user_id = get_current_user_id();
				$body    = $request->get_json_params();
				$result  = ascend_rl_unlock_level( $user_id, isset( $body['level'] ) ? (int) $body['level'] : 0 );
				if ( is_wp_error( $result ) ) {
					return new WP_REST_Response( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), 'no_keys' === $result->get_error_code() ? 402 : 409 );
				}
				$payload           = ascend_rl_state_payload( $user_id );
				$payload['unlock'] = $result;
				return $payload;
			},
		)
	);
}
add_action( 'rest_api_init', 'ascend_rl_register_routes' );

/* -------------------------------------------------------------- shortcode */

/** [ascend_rescue_lab] — the game. Guests can play the free missions. */
function ascend_rl_shortcode() {
	wp_enqueue_style( 'ascend-rescue-lab' );
	wp_enqueue_script( 'ascend-rescue-lab' );

	$logged_in = is_user_logged_in();
	$config    = array(
		'preview'        => false,
		'loggedIn'       => $logged_in,
		'restUrl'        => esc_url_raw( rest_url( 'ascend-rl/v1/progress' ) ),
		'nonce'          => $logged_in ? wp_create_nonce( 'wp_rest' ) : '',
		'hubUrl'         => esc_url_raw( ascend_rl_option( 'ascend_rl_hub_url' ) ),
		'loginUrl'       => esc_url_raw( home_url( '/login/' ) ),
		'mascotUrl'      => esc_url_raw( ascend_rl_option( 'ascend_rl_mascot_url' ) ),
		'mascotHappyUrl' => esc_url_raw( ascend_rl_option( 'ascend_rl_mascot_happy_url' ) ),
		'buyUrl'         => esc_url_raw( ascend_rl_buy_url() ),
		'passUrl'        => esc_url_raw( ascend_rl_pass_url() ),
	);
	wp_add_inline_script( 'ascend-rescue-lab', 'window.AscendRL = ' . wp_json_encode( $config ) . ';', 'before' );

	return '<div class="rl-root" id="rl-game"><noscript>' . esc_html__( 'STEM-a-lotl: Rescue Lab needs JavaScript turned on to play.', 'ascend-rescue-lab' ) . '</noscript></div>';
}
add_shortcode( 'ascend_rescue_lab', 'ascend_rl_shortcode' );

/** [ascend_rescue_lab_summary] — compact parent summary for the student dashboard. */
function ascend_rl_summary_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	$user_id  = get_current_user_id();
	$p        = ascend_rl_get_progress( $user_id );
	$names    = array( 1 => 'Supply Slide', 'Rover Crossing', 'Cargo Balance', 'Gentle Landing', 'Wind Works', 'Gear Lift', 'Light the Lab', 'Rover Routine', 'Loop the Route', 'Research Station Rescue' );
	$concepts = array( 1 => 'ramp angle and gravity', 'beams and supports', 'balance', 'launch energy and angle', 'forces', 'gear ratios', 'closed circuits', 'sequencing and debugging', 'loops', 'planning with earlier ideas' );
	$done     = array();
	$badges   = 0;
	$designs  = 0;
	foreach ( $p['levels'] as $id => $lv ) {
		if ( $lv['done'] ) {
			$done[] = (int) $id;
		}
		$badges  += count( $lv['badges'] );
		$designs += count( $lv['designs'] );
	}
	sort( $done );
	$example = '';
	if ( $p['runs'] > 0 && count( $done ) ) {
		$example = sprintf( 'Example of improvement: %d experiment runs so far, %d of them misses that were followed by a change and a retry; %d missions ended in a delivery.', $p['runs'], $p['fails'], count( $done ) );
	}
	ob_start();
	?>
	<div class="rl-summary" style="border:1px solid #e4e1da;border-radius:14px;padding:14px 16px;font-size:14px;line-height:1.5;">
		<strong style="color:#173e63;">STEM-a-lotl: Rescue Lab</strong>
		<ul style="margin:6px 0 0 1.1em;padding:0;">
			<li>Missions completed: <?php echo (int) count( $done ); ?> of <?php echo (int) ASCEND_RL_LEVELS; ?><?php echo $done ? ' (' . esc_html( implode( ', ', array_map( function ( $id ) use ( $names ) { return $names[ $id ]; }, $done ) ) ) . ')' : ''; ?></li>
			<li>Concepts practised: <?php echo $done ? esc_html( implode( '; ', array_map( function ( $id ) use ( $concepts ) { return $concepts[ $id ]; }, $done ) ) ) : 'none yet'; ?></li>
			<li>Badges earned: <?php echo (int) $badges; ?> &middot; Saved inventions: <?php echo (int) $designs; ?></li>
			<?php if ( $example ) : ?><li><?php echo esc_html( $example ); ?></li><?php endif; ?>
		</ul>
		<p style="margin:8px 0 0;font-size:12px;color:#62676c;">Practice observations from play, not a validated assessment, attendance record, official credit or certification.</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ascend_rescue_lab_summary', 'ascend_rl_summary_shortcode' );

/* ------------------------------------------------------------------ admin */

add_action( 'admin_menu', function () {
	// Sits under the shared "Axolotl Games" menu when that plugin is active.
	$parent = menu_page_url( 'ascend-games', false ) ? 'ascend-games' : 'options-general.php';
	add_submenu_page( $parent, 'Rescue Lab', 'Rescue Lab', 'manage_options', 'ascend-rescue-lab', 'ascend_rl_admin_page' );
}, 20 );

function ascend_rl_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['ascend_rl_save'] ) && check_admin_referer( 'ascend_rl_settings' ) ) {
		update_option( 'ascend_rl_mascot_url', esc_url_raw( wp_unslash( $_POST['mascot_url'] ?? '' ) ) );
		update_option( 'ascend_rl_mascot_happy_url', esc_url_raw( wp_unslash( $_POST['mascot_happy_url'] ?? '' ) ) );
		update_option( 'ascend_rl_hub_url', esc_url_raw( wp_unslash( $_POST['hub_url'] ?? '' ) ) );
		echo '<div class="updated"><p>Saved.</p></div>';
	}
	if ( isset( $_POST['ascend_rl_grant'] ) && check_admin_referer( 'ascend_rl_grant' ) ) {
		$uid = (int) ( $_POST['user_id'] ?? 0 );
		$lvl = (int) ( $_POST['level'] ?? 0 );
		if ( $uid && $lvl > ASCEND_RL_FREE_LEVELS && $lvl <= ASCEND_RL_LEVELS ) {
			$owned   = ascend_rl_owned( $uid );
			$owned[] = $lvl;
			update_user_meta( $uid, ASCEND_RL_OWNED_META, array_values( array_unique( $owned ) ) );
			echo '<div class="updated"><p>Mission ' . (int) $lvl . ' granted to user ' . (int) $uid . ' (no key spent).</p></div>';
		}
	}
	$keys = ascend_rl_keys_available();
	echo '<div class="wrap"><h1>STEM-a-lotl: Rescue Lab</h1>';
	echo '<p>Shortcode <code>[ascend_rescue_lab]</code> renders the game; <code>[ascend_rescue_lab_summary]</code> renders the parent summary (add it to the /user/ dashboard page).</p>';
	echo '<p>Key system: ' . ( $keys ? '<strong>connected</strong> to the Ascend Axolotl Games plugin (Pond Keys). One key opens one mission permanently.' : '<strong>not found</strong>. Missions 4&ndash;10 cannot be unlocked with keys until the Ascend Axolotl Games plugin is active; the free missions still work.' ) . '</p>';
	echo '<p>Buy link in use: <code>' . esc_html( ascend_rl_buy_url() ?: '(none: no Pond Key product found)' ) . '</code></p>';
	echo '<form method="post">';
	wp_nonce_field( 'ascend_rl_settings' );
	echo '<table class="form-table">';
	echo '<tr><th>Header mascot image URL</th><td><input type="url" class="regular-text" name="mascot_url" value="' . esc_attr( ascend_rl_option( 'ascend_rl_mascot_url' ) ) . '"></td></tr>';
	echo '<tr><th>Happy reaction image URL</th><td><input type="url" class="regular-text" name="mascot_happy_url" value="' . esc_attr( ascend_rl_option( 'ascend_rl_mascot_happy_url' ) ) . '"></td></tr>';
	echo '<tr><th>Games hub URL (exit link)</th><td><input type="url" class="regular-text" name="hub_url" value="' . esc_attr( ascend_rl_option( 'ascend_rl_hub_url' ) ) . '"></td></tr>';
	echo '</table><p><button class="button button-primary" name="ascend_rl_save" value="1">Save</button></p></form>';
	echo '<h2>Grant a mission (support use: refunds, goodwill)</h2><form method="post">';
	wp_nonce_field( 'ascend_rl_grant' );
	echo '<p><label>User ID <input type="number" name="user_id" min="1"></label> <label>Mission <input type="number" name="level" min="4" max="10"></label> <button class="button" name="ascend_rl_grant" value="1">Grant without a key</button></p></form>';
	echo '<h2>Recent unlocks</h2>';
	$users = get_users( array( 'meta_key' => ASCEND_RL_UNLOCK_LOG_META, 'number' => 50 ) );
	if ( ! $users ) {
		echo '<p>None yet.</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>Student</th><th>Mission</th><th>When</th><th>Keys spent</th></tr></thead><tbody>';
		foreach ( $users as $u ) {
			$log = get_user_meta( $u->ID, ASCEND_RL_UNLOCK_LOG_META, true );
			foreach ( array_reverse( is_array( $log ) ? $log : array() ) as $row ) {
				echo '<tr><td>' . esc_html( $u->display_name ) . '</td><td>' . (int) $row['level'] . '</td><td>' . esc_html( wp_date( 'Y-m-d H:i', (int) $row['time'] ) ) . '</td><td>' . (int) $row['cost'] . '</td></tr>';
			}
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

/** Show progress on the WP user profile for support staff. */
add_action( 'show_user_profile', 'ascend_rl_profile_box' );
add_action( 'edit_user_profile', 'ascend_rl_profile_box' );
function ascend_rl_profile_box( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$p = ascend_rl_get_progress( $user->ID );
	$done = array_keys( array_filter( $p['levels'], function ( $lv ) { return $lv['done']; } ) );
	echo '<h2>Rescue Lab</h2><table class="form-table"><tr><th>Missions done</th><td>' . esc_html( $done ? implode( ', ', $done ) : 'none' ) . '</td></tr>';
	echo '<tr><th>Owned (keys)</th><td>' . esc_html( implode( ', ', ascend_rl_owned( $user->ID ) ) ?: 'none' ) . '</td></tr>';
	echo '<tr><th>Runs / misses</th><td>' . (int) $p['runs'] . ' / ' . (int) $p['fails'] . '</td></tr></table>';
}
