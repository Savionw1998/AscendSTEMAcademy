<?php
/**
 * The app's web-manifest icons and shortcuts, and the icon files they point to (assets/icons/,
 * built from branding/logo-source-1024.png by tools/make-icons.py).
 *
 * The Ascend PWA plugin builds and serves the manifest. It takes these values with one line,
 * just before it outputs the JSON:
 *
 *   $manifest = apply_filters( 'ascend_app_manifest', $manifest );
 *
 * Without this plugin active, that filter changes nothing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ascend_app_icon_url( $file ) {
	return ASCEND_APP_URL . 'assets/icons/' . $file . '?v=' . ASCEND_APP_VERSION;
}

function ascend_app_manifest_icons() {
	return array(
		array( 'src' => ascend_app_icon_url( 'icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
		array( 'src' => ascend_app_icon_url( 'icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
		array( 'src' => ascend_app_icon_url( 'icon-maskable-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
	);
}

/**
 * Android shows up to four shortcuts on a long press of the app icon, in this order.
 */
function ascend_app_manifest_shortcuts() {
	$shortcuts = array(
		array( 'Time Card', '/time-card-tracker/', 'time-card' ),
		array( 'Students', '/user/', 'students' ),
		array( 'Parents', '/account/', 'parents' ),
		array( 'Games', '/guess-a-lotl/', 'games' ),
	);
	return array_map(
		fn( $s ) => array(
			'name'       => $s[0],
			'short_name' => $s[0],
			'url'        => home_url( $s[1] ),
			'icons'      => array(
				array( 'src' => ascend_app_icon_url( "shortcut-{$s[2]}.png" ), 'sizes' => '96x96', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => ascend_app_icon_url( "shortcut-{$s[2]}-maskable.png" ), 'sizes' => '96x96', 'type' => 'image/png', 'purpose' => 'maskable' ),
			),
		),
		$shortcuts
	);
}

function ascend_app_manifest( $manifest ) {
	$manifest              = (array) $manifest;
	$manifest['icons']     = ascend_app_manifest_icons();
	$manifest['shortcuts'] = ascend_app_manifest_shortcuts();
	return $manifest;
}
add_filter( 'ascend_app_manifest', 'ascend_app_manifest' );

/**
 * Where the Ascend PWA plugin serves the manifest; the Android app was built from this URL
 * (web_manifest_url inside the published app).
 */
function ascend_app_manifest_path() {
	return (string) apply_filters( 'ascend_app_manifest_path', '/manifest.json' );
}

/**
 * Put this plugin's icons and shortcuts into the manifest response on its way out, so the PWA
 * plugin itself does not have to change. Everything else in its manifest is kept. The response
 * is never page-cached, so the rewritten version is the one every visitor (and Bubblewrap) gets.
 */
function ascend_app_rewrite_manifest_response() {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( $path !== (string) wp_parse_url( home_url( ascend_app_manifest_path() ), PHP_URL_PATH ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	// Always answer with a full body: a "304 Not Modified" to a browser holding the PWA plugin's
	// old ETag would keep the old icons and shortcuts in that browser.
	unset( $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	$ours = ascend_app_manifest( array() ); // Worked out now, while WordPress is fully loaded.
	ob_start( fn( $body ) => ascend_app_merge_manifest_json( $body, $ours ) );
}
add_action( 'plugins_loaded', 'ascend_app_rewrite_manifest_response', PHP_INT_MIN );

/**
 * $body with $ours merged in, if $body is a web manifest; anything else (an error page) is returned as is.
 */
function ascend_app_merge_manifest_json( $body, $ours ) {
	$manifest = json_decode( (string) $body, true );
	if ( ! is_array( $manifest ) || ! ( isset( $manifest['start_url'] ) || isset( $manifest['name'] ) || isset( $manifest['short_name'] ) ) ) {
		return $body;
	}
	if ( ! headers_sent() ) {
		header_remove( 'Content-Length' ); // The body changes length.
		header_remove( 'ETag' );
		header_remove( 'Last-Modified' );
	}
	return (string) json_encode( array_merge( $manifest, $ours ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
