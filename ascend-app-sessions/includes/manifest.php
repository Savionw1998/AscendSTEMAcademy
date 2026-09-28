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
