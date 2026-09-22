<?php
/**
 * Plugin Name:       Ascend Code-a-lotl
 * Description:       A coding puzzle game: kids program an axolotl with arrow and repeat blocks to collect shrimp and swim home. Teaches sequencing, loops and debugging. Shortcode: [ascend_code_a_lotl]
 * Version:           1.0.0
 * Author:            Ascend STEM Academy
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       ascend-code-a-lotl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASCEND_CAL_VERSION', '1.0.0' );
define( 'ASCEND_CAL_LEVELS', 12 );
define( 'ASCEND_CAL_META_KEY', 'ascend_cal_progress' );

/**
 * Register (but do not enqueue) assets; the shortcode enqueues them only on pages that use it.
 */
function ascend_cal_register_assets() {
	$base = plugin_dir_url( __FILE__ ) . 'assets/';
	wp_register_style( 'ascend-code-a-lotl', $base . 'code-a-lotl.css', array(), ASCEND_CAL_VERSION );
	wp_register_script( 'ascend-code-a-lotl', $base . 'code-a-lotl.js', array(), ASCEND_CAL_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'ascend_cal_register_assets' );

/**
 * Clean a progress payload down to { unlocked: int, stars: { level: 0-3 } }.
 *
 * @param mixed $raw Untrusted input.
 * @return array
 */
function ascend_cal_sanitize_progress( $raw ) {
	$raw      = is_array( $raw ) ? $raw : array();
	$unlocked = isset( $raw['unlocked'] ) ? (int) $raw['unlocked'] : 1;
	$unlocked = max( 1, min( ASCEND_CAL_LEVELS, $unlocked ) );
	$stars    = array();
	if ( isset( $raw['stars'] ) && is_array( $raw['stars'] ) ) {
		foreach ( $raw['stars'] as $level => $count ) {
			$level = (int) $level;
			if ( $level >= 1 && $level <= ASCEND_CAL_LEVELS ) {
				$stars[ $level ] = max( 0, min( 3, (int) $count ) );
			}
		}
	}
	return array(
		'unlocked' => $unlocked,
		'stars'    => (object) $stars,
	);
}

/**
 * Merge two progress records, keeping the best result for each level.
 */
function ascend_cal_merge_progress( $a, $b ) {
	$stars = (array) $a['stars'];
	foreach ( (array) $b['stars'] as $level => $count ) {
		$stars[ $level ] = max( isset( $stars[ $level ] ) ? $stars[ $level ] : 0, $count );
	}
	return array(
		'unlocked' => max( $a['unlocked'], $b['unlocked'] ),
		'stars'    => (object) $stars,
	);
}

/**
 * REST routes so a logged-in student's progress follows them across devices.
 */
function ascend_cal_register_routes() {
	register_rest_route(
		'ascend-cal/v1',
		'/progress',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => 'is_user_logged_in',
				'callback'            => function () {
					return ascend_cal_sanitize_progress( get_user_meta( get_current_user_id(), ASCEND_CAL_META_KEY, true ) );
				},
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => 'is_user_logged_in',
				'callback'            => function ( WP_REST_Request $request ) {
					$user_id  = get_current_user_id();
					$stored   = ascend_cal_sanitize_progress( get_user_meta( $user_id, ASCEND_CAL_META_KEY, true ) );
					$incoming = ascend_cal_sanitize_progress( $request->get_json_params() );
					$merged   = ascend_cal_merge_progress( $stored, $incoming );
					update_user_meta( $user_id, ASCEND_CAL_META_KEY, array(
						'unlocked' => $merged['unlocked'],
						'stars'    => (array) $merged['stars'],
					) );
					/**
					 * Fires after a student's Code-a-lotl progress is saved, so other Ascend
					 * plugins (dashboard, transcript) can react.
					 */
					do_action( 'ascend_cal_progress_saved', $user_id, $merged );
					return $merged;
				},
			),
		)
	);
}
add_action( 'rest_api_init', 'ascend_cal_register_routes' );

/**
 * [ascend_code_a_lotl start_level="1"]
 */
function ascend_cal_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'start_level' => 1 ), $atts, 'ascend_code_a_lotl' );

	wp_enqueue_style( 'ascend-code-a-lotl' );
	wp_enqueue_script( 'ascend-code-a-lotl' );

	$config = array(
		'loggedIn' => is_user_logged_in(),
		'restUrl'  => esc_url_raw( rest_url( 'ascend-cal/v1/progress' ) ),
		'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
	);
	wp_add_inline_script( 'ascend-code-a-lotl', 'window.AscendCAL = ' . wp_json_encode( $config ) . ';', 'before' );

	$start = max( 1, min( ASCEND_CAL_LEVELS, (int) $atts['start_level'] ) );

	return sprintf(
		'<div class="acl-root" data-start-level="%d"><noscript>%s</noscript></div>',
		$start,
		esc_html__( 'Code-a-lotl needs JavaScript turned on to play.', 'ascend-code-a-lotl' )
	);
}
add_shortcode( 'ascend_code_a_lotl', 'ascend_cal_shortcode' );
