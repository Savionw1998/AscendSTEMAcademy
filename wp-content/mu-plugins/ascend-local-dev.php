<?php
/**
 * Plugin Name: Ascend Local Development Helpers
 * Description: Local-only safety rails: routes outbound mail to Mailpit and badges the admin bar. Does nothing outside a local environment.
 * Version:     1.0.0
 * Author:      Ascend STEM Academy
 *
 * @package ascend-local
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this request is running on a local development install.
 *
 * Every hook in this file is gated on this, so the file is inert if it is
 * ever deployed to staging or production by mistake.
 *
 * @return bool
 */
function ascend_is_local_env() {
	if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
		return true;
	}

	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	return in_array( $host, array( 'localhost', '127.0.0.1' ), true );
}

if ( ! ascend_is_local_env() ) {
	return;
}

/**
 * Send all outbound mail to Mailpit instead of the internet.
 *
 * Without this, a plugin firing enrollment or contact-form email during local
 * testing could reach a real family. Start Mailpit with `make mail`.
 *
 * @param PHPMailer $phpmailer The mailer instance, passed by reference.
 */
function ascend_local_route_mail_to_mailpit( $phpmailer ) {
	$phpmailer->isSMTP();
	$phpmailer->Host       = 'mailpit';
	$phpmailer->Port       = 1025;
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPAutoTLS = false;
}
add_action( 'phpmailer_init', 'ascend_local_route_mail_to_mailpit' );

/**
 * Make it obvious at a glance that this is not the live site.
 */
function ascend_local_admin_bar_badge( $wp_admin_bar ) {
	$wp_admin_bar->add_node(
		array(
			'id'    => 'ascend-local-badge',
			'title' => 'LOCAL',
			'meta'  => array( 'title' => 'This is a local development environment' ),
		)
	);
}
add_action( 'admin_bar_menu', 'ascend_local_admin_bar_badge', 5 );

/**
 * Style the badge so it reads as a warning.
 */
function ascend_local_admin_bar_styles() {
	echo '<style>#wpadminbar #wp-admin-bar-ascend-local-badge > .ab-item{background:#b32d2e;color:#fff;font-weight:700;}</style>';
}
add_action( 'wp_head', 'ascend_local_admin_bar_styles' );
add_action( 'admin_head', 'ascend_local_admin_bar_styles' );
