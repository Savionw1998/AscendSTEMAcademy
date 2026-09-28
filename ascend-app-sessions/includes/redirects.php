<?php
/**
 * Return to where you tapped: restricted pages send logged-out visitors to the login page with
 * redirect_to, and the login form sends them back there afterwards.
 *
 * Ultimate Member's login form carries the destination in a hidden redirect_to field
 * (um_browser_url_redirect_to), and um_user_login() follows that field before it ever reaches
 * the role/form "after login" setting or the um_login_redirect_url filter. So the destination is
 * decided here, on that field.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The family dashboard, where a login goes when it was not sent from anywhere.
 */
function ascend_app_dashboard_url() {
	$url = function_exists( 'um_get_core_page' ) ? um_get_core_page( 'user' ) : '';
	return $url ? $url : home_url( '/user/' );
}

function ascend_app_login_url( $return_to ) {
	$login = function_exists( 'um_get_core_page' ) ? um_get_core_page( 'login' ) : '';
	$login = $login ? $login : wp_login_url();
	return add_query_arg( 'redirect_to', rawurlencode( $return_to ), $login );
}

/**
 * A redirect_to value that is safe to send someone to after login: on this site, and not one of
 * the login, logout, registration or password-reset pages. '' when it is not.
 */
function ascend_app_safe_return_url( $url ) {
	$url = trim( wp_unslash( (string) $url ) );
	if ( ! preg_match( '#^(https?://|/(?!/))#i', $url ) ) {
		return ''; // only full URLs and site paths, never paths relative to the login page
	}
	$url = wp_validate_redirect( esc_url_raw( $url ), '' );
	if ( '' === $url ) {
		return '';
	}
	$path = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	if ( function_exists( 'um_get_core_page' ) ) {
		foreach ( array( 'login', 'logout', 'register', 'password-reset' ) as $core ) {
			$page = um_get_core_page( $core );
			if ( $page && untrailingslashit( (string) wp_parse_url( $page, PHP_URL_PATH ) ) === $path ) {
				return '';
			}
		}
	}
	return $url;
}

/**
 * The login form's hidden redirect_to: the page the visitor was sent from, else the form's own
 * "after login" URL, else the dashboard.
 */
function ascend_app_login_return_url( $url ) {
	if ( ! function_exists( 'UM' ) || 'login' !== UM()->fields()->set_mode ) {
		return $url;
	}
	$requested = $_REQUEST['redirect_to'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! is_string( $requested ) ) {
		return ascend_app_dashboard_url();
	}
	if ( '' !== $requested ) {
		$back = ascend_app_safe_return_url( $requested );
		return '' !== $back ? $back : ascend_app_dashboard_url();
	}
	return '' !== (string) $url ? $url : ascend_app_dashboard_url();
}
add_filter( 'um_browser_url_redirect_to__filter', 'ascend_app_login_return_url' );

/**
 * Logged-out visitors to a page Ultimate Member restricts with "Show access restricted message"
 * go to the login page instead, and come back after logging in. Logged-in visitors without the
 * right role still see the message (sending them to login would loop).
 */
function ascend_app_restricted_page_to_login() {
	if ( is_user_logged_in() || ! is_singular() || ! function_exists( 'UM' ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	if ( ! $post_id || ! UM()->access()->is_restricted( $post_id ) ) {
		return;
	}
	$settings = UM()->access()->get_post_privacy_settings( $post_id );
	if ( ! empty( $settings['_um_noaccess_action'] ) ) {
		return; // Set to "Redirect user": Ultimate Member already does that.
	}
	wp_safe_redirect( ascend_app_login_url( UM()->permalinks()->get_current_url() ) );
	exit;
}
add_action( 'template_redirect', 'ascend_app_restricted_page_to_login', 5 );

/**
 * Logged-out visitors to /user/ (the dashboard) go to login and back, instead of to the home page.
 */
function ascend_app_dashboard_to_login() {
	return ascend_app_login_url( ascend_app_dashboard_url() );
}
add_filter( 'um_locate_user_profile_not_loggedin__redirect', 'ascend_app_dashboard_to_login' );

/**
 * The app's start URL is the home page; a family already signed in goes straight to the dashboard.
 */
function ascend_app_home_to_dashboard() {
	if ( ! is_front_page() || ! is_user_logged_in() || ! ascend_app_is_app() || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	wp_safe_redirect( ascend_app_dashboard_url() );
	exit;
}
add_action( 'template_redirect', 'ascend_app_home_to_dashboard', 5 );
