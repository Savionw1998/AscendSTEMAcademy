<?php
/**
 * Astra Child — theme bootstrap for Ascend STEM Academy.
 *
 * ---------------------------------------------------------------------------
 * RECONSTRUCTED, NOT THE LIVE FILE.
 *
 * The production child theme's functions.php was not available when this
 * repository was set up (the file supplied was the Astra *parent* theme's
 * functions.php). This file reproduces only the behaviour that style.css
 * documents: the child stylesheet is enqueued on wp_enqueue_scripts at
 * priority 15, so it lands earlier than the Customizer's inline CSS did.
 *
 * If the live site has additional PHP overrides, replace this file wholesale
 * with the real one from wp-content/themes/astra-child/ on the server. Do not
 * merge the two — assume this file is the incomplete one.
 * ---------------------------------------------------------------------------
 *
 * @package astra-child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Child theme version. Keep in step with the Version: header in style.css.
 */
define( 'ASCEND_CHILD_VERSION', '1.1.0' );

/**
 * Enqueue the child stylesheet.
 *
 * Astra does not serve its own style.css to the front end — it compiles and
 * enqueues 'astra-theme-css' instead — so the child theme depends on that
 * handle rather than enqueueing the parent's style.css by hand.
 *
 * Priority 15 is deliberate and is relied upon by the rules in style.css.
 * See the cascade-order note at the top of that file before changing it.
 */
function ascend_child_enqueue_styles() {
	$dependencies = wp_style_is( 'astra-theme-css', 'registered' )
		? array( 'astra-theme-css' )
		: array();

	wp_enqueue_style(
		'astra-child-style',
		get_stylesheet_uri(),
		$dependencies,
		ascend_child_style_version()
	);
}
add_action( 'wp_enqueue_scripts', 'ascend_child_enqueue_styles', 15 );

/**
 * Cache-busting version for style.css.
 *
 * Uses the file's modification time locally so edits appear on refresh, and
 * the declared theme version everywhere else.
 *
 * @return string Version string.
 */
function ascend_child_style_version() {
	$stylesheet = get_stylesheet_directory() . '/style.css';

	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $stylesheet ) ) {
		return (string) filemtime( $stylesheet );
	}

	return ASCEND_CHILD_VERSION;
}
