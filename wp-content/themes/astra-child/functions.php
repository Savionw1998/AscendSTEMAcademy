<?php
/**
 * Ascend STEM Academy — child theme bootstrap.
 *
 * @package astra-child
 */

defined( 'ABSPATH' ) || exit;

define( 'ASCEND_CHILD_VERSION', '1.1.0' );

/**
 * Load the parent and child stylesheets.
 *
 * The child stylesheet is versioned by file modification time in local
 * development so browser caching never hides a change.
 */
function ascend_child_enqueue_styles() {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		ASCEND_CHILD_VERSION
	);

	$theme_css = '/assets/css/theme.css';
	$theme_css_path = get_stylesheet_directory() . $theme_css;

	if ( file_exists( $theme_css_path ) ) {
		wp_enqueue_style(
			'ascend-child-style',
			get_stylesheet_directory_uri() . $theme_css,
			array( 'astra-parent-style' ),
			ascend_child_asset_version( $theme_css_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ascend_child_enqueue_styles', 15 );

/**
 * Version string for a theme asset.
 *
 * Uses the file's modification time while debugging so edits show up
 * immediately, and the stable theme version in production.
 *
 * @param string $path Absolute path to the asset.
 * @return string Version string.
 */
function ascend_child_asset_version( $path ) {
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $path ) ) {
		return (string) filemtime( $path );
	}

	return ASCEND_CHILD_VERSION;
}

/**
 * Load optional feature modules from inc/.
 *
 * Drop a file in inc/ and it is included automatically — no edit needed here.
 */
function ascend_child_load_includes() {
	foreach ( glob( get_stylesheet_directory() . '/inc/*.php' ) as $module ) {
		require_once $module;
	}
}
ascend_child_load_includes();
