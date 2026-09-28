<?php
/**
 * 90-day logins for families in the app, renewed while they keep using it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASCEND_APP_SESSION_DAYS', 90 );
define( 'ASCEND_APP_RENEW_BELOW_DAYS', 30 );
define( 'ASCEND_APP_RENEWED_META', 'ascend_app_session_renewed' );

/**
 * Roles that get the long app login. Every family account on the site is um_student.
 */
function ascend_app_long_session_roles() {
	return (array) apply_filters( 'ascend_app_long_session_roles', array( 'um_student' ) );
}

/**
 * Roles that always keep WordPress's default login length, even if they also hold a family role.
 */
function ascend_app_default_session_roles() {
	return (array) apply_filters( 'ascend_app_default_session_roles', array( 'administrator', 'editor', 'author', 'contributor', 'shop_manager', 'um_faculty' ) );
}

function ascend_app_user_gets_long_session( $user_id ) {
	$user = get_userdata( (int) $user_id );
	if ( ! $user ) {
		return false;
	}
	$roles = (array) $user->roles;
	if ( array_intersect( $roles, ascend_app_default_session_roles() ) ) {
		return false;
	}
	return (bool) array_intersect( $roles, ascend_app_long_session_roles() );
}

/**
 * auth_cookie_expiration: 90 days for families signing in from the app.
 */
function ascend_app_auth_cookie_expiration( $length, $user_id, $remember ) {
	if ( ascend_app_is_app() && ascend_app_user_gets_long_session( $user_id ) ) {
		return ASCEND_APP_SESSION_DAYS * DAY_IN_SECONDS;
	}
	return $length;
}
add_filter( 'auth_cookie_expiration', 'ascend_app_auth_cookie_expiration', 20, 3 );

/**
 * Whether this request is a page the family opened, as opposed to AJAX, REST, cron, admin or a form post.
 */
function ascend_app_is_page_view() {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || wp_is_json_request() ) {
		return false;
	}
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return false;
	}
	$dest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? 'document';
	if ( 'document' !== $dest ) {
		return false;
	}
	$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
	return false === strpos( $uri, '/' . rest_get_url_prefix() . '/' ) && false === strpos( $uri, 'rest_route=' );
}

/**
 * Sliding renewal. When an app login has under 30 days left, re-issue it for 90 days, at most once a day.
 *
 * The same session token is kept and its server-side expiry extended, so nonces already printed in
 * open pages stay valid and no extra session is left behind.
 */
function ascend_app_maybe_renew_session() {
	if ( ! ascend_app_is_app() || ! is_user_logged_in() || headers_sent() || ! ascend_app_is_page_view() ) {
		return;
	}
	$cookie = wp_parse_auth_cookie( '', 'logged_in' );
	if ( ! $cookie || empty( $cookie['token'] ) || (int) $cookie['expiration'] - time() >= ASCEND_APP_RENEW_BELOW_DAYS * DAY_IN_SECONDS ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( ! ascend_app_user_gets_long_session( $user_id ) ) {
		return;
	}
	$last = (int) get_user_meta( $user_id, ASCEND_APP_RENEWED_META, true );
	if ( $last && time() - $last < DAY_IN_SECONDS ) {
		return;
	}
	$manager = WP_Session_Tokens::get_instance( $user_id );
	$session = $manager->get( $cookie['token'] );
	if ( ! $session ) {
		return;
	}

	$new_expiration = 0;
	$capture        = function ( $logged_in_cookie, $expire, $expiration ) use ( &$new_expiration ) {
		$new_expiration = (int) $expiration;
	};
	add_action( 'set_logged_in_cookie', $capture, 10, 3 );
	wp_set_auth_cookie( $user_id, true, '', $cookie['token'] );
	remove_action( 'set_logged_in_cookie', $capture, 10 );

	if ( $new_expiration > (int) $session['expiration'] ) {
		$session['expiration'] = $new_expiration;
		$manager->update( $cookie['token'], $session );
	}
	update_user_meta( $user_id, ASCEND_APP_RENEWED_META, time() );
}
add_action( 'init', 'ascend_app_maybe_renew_session', 1 );
