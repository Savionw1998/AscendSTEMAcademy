<?php
/**
 * Ultimate Member login form in the app: always remember the family, and let password managers fill it.
 *
 * Done here for app requests PHP can see (cookie or first-launch referrer). assets/app.js repeats the
 * same changes in the browser, for login pages served from the page cache before the cookie existed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace the "Keep me signed in" checkbox with an always-on hidden field.
 */
function ascend_app_login_form_args( $args ) {
	if ( ascend_app_is_app() && isset( $args['mode'] ) && 'login' === $args['mode'] && ! empty( $args['show_rememberme'] ) ) {
		$args['show_rememberme']       = 0;
		$args['ascend_app_rememberme'] = 1;
	}
	return $args;
}
add_filter( 'um_shortcode_args_filter', 'ascend_app_login_form_args' );

function ascend_app_login_rememberme_field( $args ) {
	if ( ! empty( $args['ascend_app_rememberme'] ) ) {
		echo '<input type="hidden" name="rememberme" value="1" />';
	}
}
add_action( 'um_after_login_fields', 'ascend_app_login_rememberme_field', 999 );

/**
 * autocomplete="username" / "current-password" on the login form's inputs. UM prints
 * autocomplete="off" on the username and nothing on the password.
 */
function ascend_app_login_field_autocomplete( $output, $mode, $value ) {
	if ( 'login' !== $mode || ! ascend_app_is_app() ) {
		return $output;
	}
	$output = preg_replace( '/\sautocomplete="[^"]*"/', '', $output );
	return preg_replace( '/<input\b/', '<input autocomplete="' . esc_attr( $value ) . '"', $output, 1 );
}
foreach ( array( 'user_login', 'username', 'user_email' ) as $ascend_app_key ) {
	add_filter( "um_{$ascend_app_key}_form_edit_field", fn( $output, $mode ) => ascend_app_login_field_autocomplete( $output, $mode, 'username' ), 10, 2 );
}
unset( $ascend_app_key );
add_filter( 'um_user_password_form_edit_field', fn( $output, $mode ) => ascend_app_login_field_autocomplete( $output, $mode, 'current-password' ), 10, 2 );
