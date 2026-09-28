<?php
/*
 * Local test stand-in for the Ascend PWA plugin (its source is not in this repository): serves a
 * web manifest at /manifest.webmanifest through the ascend_app_manifest filter, the one line the
 * real plugin needs, and links it from every page.
 */
add_action( 'parse_request', function () {
	if ( '/manifest.webmanifest' !== parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ) {
		return;
	}
	$manifest = array(
		'name'             => 'Ascend STEM Academy',
		'short_name'       => 'Ascend STEM',
		'start_url'        => home_url( '/' ),
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'background_color' => '#ffffff',
		'theme_color'      => '#009CDE',
		'icons'            => array( array( 'src' => home_url( '/old-icon.png' ), 'sizes' => '512x512', 'type' => 'image/png' ) ),
		'shortcuts'        => array( array( 'name' => 'Old shortcut', 'url' => home_url( '/' ) ) ),
	);
	$manifest = apply_filters( 'ascend_app_manifest', $manifest );
	header( 'Content-Type: application/manifest+json' );
	echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES );
	exit;
} );
add_action( 'wp_head', function () {
	echo '<link rel="manifest" href="' . esc_url( home_url( '/manifest.webmanifest' ) ) . '">' . "\n";
} );
