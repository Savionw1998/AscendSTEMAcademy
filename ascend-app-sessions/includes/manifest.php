<?php
/**
 * The app's web-manifest icons and shortcuts, and the icon files they point to (assets/icons/,
 * built from branding/logo-source-1024.png by tools/make-icons.py).
 *
 * The Ascend PWA plugin serves the manifest at /manifest.json, the URL the Android app was built
 * from. Its source is not in this repository, so it is left alone, and this file gets the icons
 * and shortcuts into that manifest whichever way it is served:
 *
 *   - Served by WordPress: the response is rewritten on its way out (from the moment this plugin
 *     loads, which is before the Ascend PWA plugin).
 *   - A real file on the server (the web server sends it without running WordPress): the file
 *     itself is updated, checked on every admin page; the original is kept next to it.
 *
 * The result of both is recorded in the ascend_app_manifest_status option. The same values are
 * available to other code as apply_filters( 'ascend_app_manifest', $manifest ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cache-busting version for the icon URLs; the Android app's config (android/) uses the same URLs. */
define( 'ASCEND_APP_ICON_VERSION', '1.0.0' );
define( 'ASCEND_APP_MANIFEST_STATUS', 'ascend_app_manifest_status' );

function ascend_app_icon_url( $file ) {
	return ASCEND_APP_URL . 'assets/icons/' . $file . '?v=' . ASCEND_APP_ICON_VERSION;
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
 * Whether a decoded JSON value is a web app manifest.
 */
function ascend_app_is_web_manifest( $manifest ) {
	return is_array( $manifest ) && ( isset( $manifest['start_url'] ) || isset( $manifest['name'] ) || isset( $manifest['short_name'] ) );
}

/**
 * Read, and optionally update, what is known about how the manifest is served.
 */
function ascend_app_manifest_status( $changes = array() ) {
	$status = (array) get_option( ASCEND_APP_MANIFEST_STATUS, array() );
	if ( $changes && array_merge( $status, $changes ) !== $status ) {
		$status = array_merge( $status, $changes );
		update_option( ASCEND_APP_MANIFEST_STATUS, $status, false );
	}
	return $status;
}

/**
 * Served by WordPress: put this plugin's icons and shortcuts into the response on its way out,
 * however and whenever the Ascend PWA plugin produces it. Everything else in its manifest is kept.
 * The response is never page-cached, so the rewritten version is the one everyone gets.
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
	$status = ascend_app_manifest_status();
	if ( time() - (int) ( $status['served_by_wordpress'] ?? 0 ) > HOUR_IN_SECONDS ) {
		ascend_app_manifest_status( array( 'served_by_wordpress' => time() ) );
	}
	$ours = ascend_app_manifest( array() ); // Worked out now, while WordPress is fully available.
	ob_start( fn( $body ) => ascend_app_merge_manifest_json( $body, $ours ) );
}
// Right away, not on a hook: plugins load alphabetically, so this runs before the Ascend PWA plugin
// has any chance to answer the request, even from its own main file.
ascend_app_rewrite_manifest_response();

/**
 * $body with $ours merged in, if $body is a web manifest; anything else (an error page) is returned as is.
 */
function ascend_app_merge_manifest_json( $body, $ours ) {
	$manifest = json_decode( (string) $body, true );
	if ( ! ascend_app_is_web_manifest( $manifest ) ) {
		return $body;
	}
	if ( ! headers_sent() ) {
		header_remove( 'Content-Length' ); // The body changes length.
		header_remove( 'ETag' );
		header_remove( 'Last-Modified' );
	}
	return (string) json_encode( array_merge( $manifest, $ours ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

/**
 * A real file on the server: the web server sends it without running WordPress, so keep the file
 * itself up to date. Only its icons and shortcuts change; the first time, the original is saved as
 * manifest.json.before-ascend-app. Runs on admin pages, so it also catches the PWA plugin writing
 * its own version again later.
 */
function ascend_app_update_static_manifest() {
	if ( wp_doing_ajax() ) {
		return;
	}
	// The site's root folder (differs from ABSPATH when WordPress is installed in a subfolder).
	$root = function_exists( 'get_home_path' ) ? get_home_path() : ABSPATH;
	$file = $root . ltrim( ascend_app_manifest_path(), '/' );
	if ( ! is_file( $file ) ) {
		return ascend_app_manifest_status( array( 'static_file' => 'none' ) );
	}
	$manifest = json_decode( (string) file_get_contents( $file ), true );
	if ( ! ascend_app_is_web_manifest( $manifest ) ) {
		return ascend_app_manifest_status( array( 'static_file' => 'not a web manifest' ) );
	}
	$updated = array_merge( $manifest, ascend_app_manifest( array() ) );
	if ( $updated === $manifest ) {
		return ascend_app_manifest_status( array( 'static_file' => 'up to date' ) );
	}
	if ( ! wp_is_writable( $file ) ) {
		return ascend_app_manifest_status( array( 'static_file' => 'not writable' ) );
	}
	$backup = $file . '.before-ascend-app';
	if ( ! file_exists( $backup ) ) {
		copy( $file, $backup );
	}
	file_put_contents( $file, wp_json_encode( $updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n", LOCK_EX );
	return ascend_app_manifest_status( array( 'static_file' => 'updated', 'static_updated' => time() ) );
}
add_action( 'admin_init', 'ascend_app_update_static_manifest' );

/**
 * Once per plugin version: empty W3 Total Cache's page cache, so no page stored before this version
 * was installed (without the app script and styles, or with the old manifest) is served again.
 */
function ascend_app_flush_stored_copies() {
	if ( wp_doing_ajax() || ASCEND_APP_VERSION === get_option( 'ascend_app_flushed_version' ) ) {
		return;
	}
	update_option( 'ascend_app_flushed_version', ASCEND_APP_VERSION );
	if ( function_exists( 'w3tc_flush_posts' ) ) {
		w3tc_flush_posts();
	}
	if ( function_exists( 'w3tc_flush_url' ) ) {
		w3tc_flush_url( home_url( ascend_app_manifest_path() ) );
	}
}
add_action( 'admin_init', 'ascend_app_flush_stored_copies' );

/**
 * Tell the admin when the manifest file exists but cannot be updated.
 */
function ascend_app_manifest_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$status = ascend_app_manifest_status();
	if ( 'not writable' !== ( $status['static_file'] ?? '' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'Ascend App Sessions:', 'ascend-app-sessions' ),
		esc_html__( 'the file manifest.json on the server cannot be changed by WordPress, so the app shortcuts and new icons are not in it. Make the file writable (your host\'s File Manager), then open any admin page again.', 'ascend-app-sessions' )
	);
}
add_action( 'admin_notices', 'ascend_app_manifest_notice' );
