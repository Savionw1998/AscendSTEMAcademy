<?php
/**
 * Plugin Name: Ascend PWA (local test stand-in)
 * Description: Stands in for the real Ascend PWA plugin, whose source is not in this repository. Same folder name, so it loads after Ascend App Sessions as on the live site. Serves the manifest the live site served on 2026-09-28, in one of three ways (option standin_pwa_mode, set by standin.php): "init" answers /manifest.json from an init hook, "include" answers it straight from this file, "static" leaves it to a manifest.json file on disk.
 */

function standin_pwa_manifest() {
	// As served by https://ascendstemacademy.com/manifest.json on 2026-09-28.
	return array(
		'name'             => 'Ascend STEM Academy',
		'short_name'       => 'Ascend STEM',
		'start_url'        => '/',
		'scope'            => '/',
		'display'          => 'standalone',
		'orientation'      => 'portrait',
		'theme_color'      => '#009CDE',
		'background_color' => '#FFFFFF',
		'icons'            => array(
			array( 'src' => '/wp-content/uploads/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => '/wp-content/uploads/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
			array( 'src' => '/wp-content/uploads/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
		),
	);
}

function standin_pwa_serve() {
	if ( '/manifest.json' !== parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) || isset( $_GET['stand_in_off'] ) ) {
		return;
	}
	$body = json_encode( standin_pwa_manifest(), JSON_UNESCAPED_SLASHES );
	$etag = '"' . md5( $body ) . '"';
	header( 'X-Test-Original-ETag: ' . $etag );
	if ( ( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) === $etag ) {
		http_response_code( 304 );
		exit;
	}
	header( 'Content-Type: application/json' );
	header( 'Content-Length: ' . strlen( $body ) );
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', 1790000000 ) . ' GMT' );
	header( 'X-Test-DoNotCachePage: ' . ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ? 'yes' : 'no' ) );
	echo $body;
	exit;
}

$standin_pwa_mode = get_option( 'standin_pwa_mode', 'init' );
if ( 'include' === $standin_pwa_mode ) {
	standin_pwa_serve();
} elseif ( 'init' === $standin_pwa_mode ) {
	add_action( 'init', 'standin_pwa_serve' );
}
add_action( 'wp_head', function () {
	echo '<link rel="manifest" href="/manifest.json">' . "\n";
} );
