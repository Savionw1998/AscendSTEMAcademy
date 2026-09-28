<?php
/**
 * App detection, the asa_app cookie, and keeping app and logged-in pages out of the page cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The app's Android package name, or '' to accept any android-app:// referrer.
 */
function ascend_app_package() {
	$package = defined( 'ASCEND_APP_PACKAGE' ) ? (string) ASCEND_APP_PACKAGE : '';
	return (string) apply_filters( 'ascend_app_package', $package );
}

/**
 * Whether a referrer is the app launching the site (android-app://<package>/...).
 */
function ascend_app_referrer_is_app( $referrer ) {
	if ( ! is_string( $referrer ) || 0 !== strpos( $referrer, 'android-app://' ) ) {
		return false;
	}
	$package = ascend_app_package();
	if ( '' === $package ) {
		return true;
	}
	$parts = explode( '/', $referrer );
	return isset( $parts[2] ) && $parts[2] === $package;
}

/**
 * Whether this request comes from the app: the asa_app cookie, or the app's referrer on first launch.
 */
function ascend_app_is_app() {
	if ( isset( $_COOKIE[ ASCEND_APP_COOKIE ] ) && '1' === $_COOKIE[ ASCEND_APP_COOKIE ] ) {
		return true;
	}
	return ascend_app_referrer_is_app( $_SERVER['HTTP_REFERER'] ?? '' );
}

/**
 * First launch reaches PHP with the referrer but no cookie yet: set it here too, so the
 * login form and session length already treat this visit as an app visit.
 */
function ascend_app_set_cookie_from_referrer() {
	if ( isset( $_COOKIE[ ASCEND_APP_COOKIE ] ) || headers_sent() || ! ascend_app_referrer_is_app( $_SERVER['HTTP_REFERER'] ?? '' ) ) {
		return;
	}
	setcookie(
		ASCEND_APP_COOKIE,
		'1',
		array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		)
	);
	$_COOKIE[ ASCEND_APP_COOKIE ] = '1';
}
add_action( 'init', 'ascend_app_set_cookie_from_referrer', 0 );

/**
 * The detection script. Identical for every visitor, so it is safe on cached pages.
 * It renews the cookie on every app page load, so the year counts from the last visit.
 */
function ascend_app_detection_script() {
	$package = wp_json_encode( ascend_app_package() );
	$cookie  = ASCEND_APP_COOKIE;
	return <<<JS
(function (d, w) {
	var pkg = {$package}, ref = d.referrer || "";
	var fromApp = ref.indexOf("android-app://") === 0 && (!pkg || ref.split("/")[2] === pkg);
	var standalone = !!(w.matchMedia && w.matchMedia("(display-mode: standalone)").matches);
	if (fromApp || standalone) {
		d.cookie = "{$cookie}=1; Max-Age=31536000; Path=/; SameSite=Lax" + (location.protocol === "https:" ? "; Secure" : "");
	}
})(document, window);
JS;
}

function ascend_app_print_detection_script() {
	wp_print_inline_script_tag( ascend_app_detection_script(), array( 'id' => 'ascend-app-detect' ) );
}
add_action( 'wp_head', 'ascend_app_print_detection_script', 1 );

/**
 * The app's bottom tab bar. `paths` are the URL paths on which a tab shows as current.
 */
function ascend_app_tabs() {
	$core  = function ( $page, $fallback ) {
		$url = function_exists( 'um_get_core_page' ) ? um_get_core_page( $page ) : '';
		return $url ? $url : home_url( $fallback );
	};
	$path  = fn( $url ) => (string) wp_parse_url( $url, PHP_URL_PATH );
	$games = array_map( fn( $slug ) => $path( home_url( "/{$slug}/" ) ), array( 'guess-a-lotl', 'hex-a-lotl', 'connect-a-lotl', 'cross-a-lotl', 'grow-a-lotl', 'stem-a-lotl-rescue-lab' ) );
	$tabs  = array(
		array( 'label' => 'Dashboard', 'icon' => 'dashboard', 'url' => $core( 'user', '/user/' ) ),
		array( 'label' => 'Time Card', 'icon' => 'timecard', 'url' => home_url( '/time-card-tracker/' ) ),
		array( 'label' => 'Games', 'icon' => 'games', 'url' => home_url( '/guess-a-lotl/' ), 'paths' => $games ),
		array( 'label' => 'Account', 'icon' => 'account', 'url' => $core( 'account', '/account/' ) ),
	);
	$tabs  = array_map( fn( $tab ) => $tab + array( 'paths' => array( $path( $tab['url'] ) ) ), $tabs );
	return (array) apply_filters( 'ascend_app_tabs', $tabs );
}

/**
 * Front-end CSS and JS. Both only change anything inside the app, and are the same for every
 * visitor, so cached pages are fine.
 */
function ascend_app_enqueue_assets() {
	wp_enqueue_style( 'ascend-app', ASCEND_APP_URL . 'assets/app.css', array(), ASCEND_APP_VERSION );
	wp_enqueue_script( 'ascend-app', ASCEND_APP_URL . 'assets/app.js', array(), ASCEND_APP_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_add_inline_script( 'ascend-app', 'window.ascendAppTabs = ' . wp_json_encode( ascend_app_tabs() ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'ascend_app_enqueue_assets' );

/**
 * Never store a cached copy of an app page or a logged-in page. App pages also tell the browser
 * and any proxy not to keep them.
 */
function ascend_app_no_page_cache() {
	$app = ascend_app_is_app();
	if ( ! $app && ! is_user_logged_in() ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( $app && ! headers_sent() ) {
		nocache_headers();
	}
}
add_action( 'template_redirect', 'ascend_app_no_page_cache', 0 );

/**
 * What still needs changing in W3 Total Cache for app and logged-in pages to bypass the page cache.
 *
 * @return string[] Human-readable problems; empty when the settings are right or W3TC is not in use.
 */
function ascend_app_w3tc_problems() {
	if ( ! class_exists( '\W3TC\Dispatcher' ) ) {
		return array();
	}
	$config = \W3TC\Dispatcher::config();
	if ( ! $config->get_boolean( 'pgcache.enabled' ) ) {
		return array();
	}
	$problems = array();
	if ( ! $config->get_boolean( 'pgcache.reject.logged' ) ) {
		$problems[] = __( 'turn on "Don\'t cache pages for logged in users" (Page Cache > General)', 'ascend-app-sessions' );
	}
	$rejected = array_map( 'trim', $config->get_array( 'pgcache.reject.cookie' ) );
	$covered  = false;
	foreach ( $rejected as $name ) {
		if ( '' !== $name && false !== strpos( ASCEND_APP_COOKIE, $name ) ) {
			$covered = true;
		}
	}
	if ( ! $covered ) {
		/* translators: %s: cookie name */
		$problems[] = sprintf( __( 'add %s to "Rejected cookies" (Page Cache > Advanced)', 'ascend-app-sessions' ), ASCEND_APP_COOKIE );
	}
	return $problems;
}

function ascend_app_w3tc_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$problems = ascend_app_w3tc_problems();
	if ( ! $problems ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
		esc_html__( 'Ascend App Sessions:', 'ascend-app-sessions' ),
		esc_html(
			sprintf(
				/* translators: %s: list of settings to change */
				__( 'the app can be served cached, logged-out pages until you %s in Performance > Page Cache, then save and purge the cache.', 'ascend-app-sessions' ),
				implode( __( ' and ', 'ascend-app-sessions' ), $problems )
			)
		)
	);
}
add_action( 'admin_notices', 'ascend_app_w3tc_notice' );
