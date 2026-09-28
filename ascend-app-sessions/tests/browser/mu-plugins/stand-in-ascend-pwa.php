<?php
/*
 * Local test stand-in for the Ascend PWA plugin (its source is not in this repository). Like the
 * live site, it answers /manifest.json itself (the URL the published Android app was built from)
 * and knows nothing about Ascend App Sessions: no filter call, its own Content-Length, ETag and
 * Last-Modified, and a 304 for a matching If-None-Match.
 */
add_action( 'init', function () {
	if ( '/manifest.json' !== parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) || isset( $_GET['stand_in_off'] ) ) {
		return;
	}
	$body = wp_json_encode( array(
		'name'             => 'Ascend STEM Academy',
		'short_name'       => 'Ascend STEM',
		'start_url'        => '/',
		'scope'            => '/',
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'background_color' => '#FFFFFF',
		'theme_color'      => '#009CDE',
		'icons'            => array( array( 'src' => home_url( '/old-icon.png' ), 'sizes' => '512x512', 'type' => 'image/png' ) ),
	) );
	$etag = '"' . md5( $body ) . '"';
	header( 'X-Test-Original-ETag: ' . $etag );
	if ( ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) === $etag ) {
		status_header( 304 );
		exit;
	}
	header( 'Content-Type: application/json' );
	header( 'Content-Length: ' . strlen( $body ) );
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', 1790000000 ) . ' GMT' );
	header( 'X-Test-DoNotCachePage: ' . ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ? 'yes' : 'no' ) );
	echo $body;
	exit;
} );
add_action( 'wp_head', function () {
	echo '<link rel="manifest" href="' . esc_url( home_url( '/manifest.json' ) ) . '">' . "\n";
} );
