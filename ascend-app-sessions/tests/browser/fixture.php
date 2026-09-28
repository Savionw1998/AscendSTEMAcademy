<?php
/**
 * Local fixture: recreates the parts of ascendstemacademy.com the app work touches, using the
 * live page IDs and the live settings read through the site's MCP connection on 2026-09-28.
 * Run by setup-site.sh (`php fixture.php install`, then `php fixture.php`); safe to re-run.
 */
$_SERVER['HTTP_HOST'] = 'localhost:' . ( getenv( 'ASA_PORT' ) ?: '8080' );
$installing           = 'install' === ( $argv[1] ?? '' );
if ( $installing ) {
	define( 'WP_INSTALLING', true );
}
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

if ( $installing ) {
	if ( ! is_blog_installed() ) {
		wp_install( 'Ascend Local', 'admin', 'admin@example.test', false, '', 'admin-pass-123' );
	}
	echo "installed\n";
	exit;
}
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'timezone_string', 'America/New_York' );

foreach ( array( 'ultimate-member/ultimate-member.php', 'ascend-app-sessions/ascend-app-sessions.php', 'ascend-pwa/ascend-pwa.php' ) as $plugin ) {
	$r = activate_plugin( $plugin );
	if ( is_wp_error( $r ) ) {
		echo $plugin . ': ' . $r->get_error_message() . "\n";
	}
}

// Astra stand-in, with the live CSS that pushes content below the floating header.
switch_theme( 'astra' );
wp_update_custom_css_post( file_get_contents( __DIR__ . '/live-header-offsets.css' ), array( 'stylesheet' => 'astra' ) );

// Roles as on the live site.
add_role( 'um_student', 'Student', array( 'read' => true ) );
add_role( 'um_faculty', 'Faculty', array( 'read' => true ) );
update_option( 'um_roles', array( 'student', 'faculty' ) );
// Ultimate Member role settings that affect redirects, as live (um_role_*_meta).
foreach ( array( 'student' => array( 'Student', 1 ), 'faculty' => array( 'Faculty', 4 ) ) as $slug => $role ) {
	update_option( "um_role_{$slug}_meta", array(
		'_um_is_custom' => '1', 'name' => $role[0], '_um_priority' => $role[1], '_um_can_access_wpadmin' => false,
		'_um_can_not_see_adminbar' => true, '_um_can_edit_profile' => true, '_um_can_delete_profile' => true,
		'_um_can_view_all' => 'faculty' === $slug, '_um_default_homepage' => true, '_um_redirect_homepage' => '',
		'_um_status' => 'approved', '_um_after_login' => 'redirect_profile', '_um_login_redirect_url' => '',
		'_um_after_logout' => 'redirect_home', '_um_logout_redirect_url' => '', 'wp_capabilities' => array( 'read' => true ),
	) );
}

function fx_page( $id, $slug, $title, $content ) {
	if ( get_post( $id ) ) {
		wp_update_post( array( 'ID' => $id, 'post_content' => $content, 'post_name' => $slug, 'post_title' => $title ) );
		return $id;
	}
	return wp_insert_post( array( 'import_id' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content ) );
}

// Login form 4414 with the live meta.
if ( ! get_post( 4414 ) ) {
	wp_insert_post( array( 'import_id' => 4414, 'post_type' => 'um_form', 'post_status' => 'publish', 'post_title' => 'Default Login' ) );
}
$login_fields = array(
	'user_login'    => array( 'title' => 'Username', 'metakey' => 'user_login', 'type' => 'text', 'label' => 'Username', 'required' => 1, 'public' => 1, 'editable' => false, 'validate' => 'unique_username', 'min_chars' => 3, 'max_chars' => 24, 'position' => 1, 'in_row' => '_um_row_1', 'in_sub_row' => '0', 'in_column' => 1, 'in_group' => '' ),
	'user_password' => array( 'title' => 'Password', 'metakey' => 'user_password', 'type' => 'password', 'label' => 'Password', 'required' => 1, 'public' => 1, 'editable' => 1, 'min_chars' => 8, 'max_chars' => 30, 'force_good_pass' => 1, 'force_confirm_pass' => 1, 'label_confirm_pass' => 'Confirm Password', 'position' => 2, 'in_row' => '_um_row_1', 'in_sub_row' => '0', 'in_column' => 1, 'in_group' => '' ),
	'_um_row_1'     => array( 'type' => 'row', 'id' => '_um_row_1', 'sub_rows' => 1, 'cols' => 1, 'origin' => '_um_row_1' ),
);
$meta = array(
	'_um_mode' => 'login', '_um_custom_fields' => $login_fields, 'um_form_version' => '2.8.9',
	'_um_login_use_custom_settings' => '1', '_um_login_template' => 'login', '_um_login_max_width' => '450px',
	'_um_login_icons' => 'label', '_um_login_primary_btn_word' => 'Login', '_um_login_secondary_btn' => '1',
	'_um_login_secondary_btn_word' => 'Register', '_um_login_forgot_pass_link' => '1', '_um_login_show_rememberme' => '1',
	'_um_login_after_login' => 'redirect_profile', '_um_login_redirect_url' => '', '_um_login_g_recaptcha_status' => '0',
);
foreach ( $meta as $k => $v ) { update_post_meta( 4414, $k, $v ); }

// Profile form used by /user/ (UM creates default forms on activation).
$profile_form = get_posts( array( 'post_type' => 'um_form', 'meta_key' => '_um_mode', 'meta_value' => 'profile', 'numberposts' => 1, 'fields' => 'ids' ) );
$register_form = get_posts( array( 'post_type' => 'um_form', 'meta_key' => '_um_mode', 'meta_value' => 'register', 'numberposts' => 1, 'fields' => 'ids' ) );

$pages = array(
	'home'     => fx_page( 2732, 'home', 'Elevate Your Child\'s Education', '<div class="asa-why"><section class="asa-hero"><div class="asa-wrap"><h1>HOME PAGE</h1></div></section></div>' ),
	'user'     => fx_page( 4302, 'user', 'User', '[ultimatemember form_id="' . ( $profile_form[0] ?? 0 ) . '"]' ),
	'login'    => fx_page( 4303, 'login', 'Ascend STEM Academy Login Page', '[ultimatemember form_id="4414"]' ),
	'register' => fx_page( 4304, 'registration', 'Register', '[ultimatemember form_id="' . ( $register_form[0] ?? 0 ) . '"]' ),
	'logout'   => fx_page( 4306, 'logout', 'Logout', '' ),
	'account'  => fx_page( 4307, 'account', 'User Account Page', "<h3>Student Account</h3>\n[ultimatemember_account]" ),
	'password-reset' => fx_page( 4308, 'password-reset', 'Password Reset', '[ultimatemember_password]' ),
);
fx_page( 6097, 'time-card-tracker', 'Time Card', '<div class="elementor elementor-6097"><div class="elementor-element elementor-element-241cfa88 e-con"><p id="tc-content">TIME CARD CONTENT</p></div></div>' );
fx_page( 5952, 'guess-a-lotl', 'Guess-a-lotl', '<p id="game-content">GUESS-A-LOTL GAME</p>' );
fx_page( 5982, 'hex-a-lotl', 'Hex-a-lotl', '<p>HEX GAME</p>' );
fx_page( 2785, 'contact-us', 'Contact', '<div class="asa-why"><section class="asa-hero"><div class="asa-wrap"><h1>CONTACT</h1></div></section></div>' );

// Time Card restriction exactly as live (since 2026-09-28: logged-in roles only, UM redirects others to login).
update_post_meta( 6097, 'um_content_restriction', array(
	'_um_custom_access_settings' => true, '_um_accessible' => 2,
	'_um_access_roles' => array( 'administrator' => '1', 'editor' => '1', 'author' => '1', 'um_student' => '1', 'um_faculty' => '1' ),
	'_um_access_hide_from_queries' => false, '_um_noaccess_action' => 1, '_um_restrict_by_custom_message' => 0,
	'_um_restrict_custom_message' => '', '_um_access_redirect' => 0, '_um_access_redirect_url' => '',
) );

// UM core pages and content-restriction feature.
$um = get_option( 'um_options', array() );
foreach ( $pages as $core => $pid ) {
	if ( 'home' === $core ) { continue; }
	$um[ 'core_' . $core ] = $pid;
	update_post_meta( $pid, '_um_core', $core );
}
$um['restricted_access_post_metabox'] = array( 'post' => 1, 'page' => 1 );
$um['permalink_base'] = 'user_login';
update_option( 'um_options', $um );

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', 2732 );

// Users: one family (um_student), one faculty, one editor, plus the admin from install.
function fx_user( $login, $role ) {
	$u = get_user_by( 'login', $login );
	$id = $u ? $u->ID : wp_insert_user( array( 'user_login' => $login, 'user_pass' => 'Test-pass-12345', 'user_email' => $login . '@example.test', 'role' => $role, 'display_name' => $login ) );
	( new WP_User( $id ) )->set_role( $role );
	update_user_meta( $id, 'account_status', 'approved' );
	return $id;
}
fx_user( 'family1', 'um_student' );
fx_user( 'teacher1', 'um_faculty' );
fx_user( 'editor1', 'editor' );
fx_user( 'subscriber1', 'subscriber' ); // logged in, but not a role the Time Card allows
update_user_meta( 1, 'account_status', 'approved' );

// Menus: public "Header" menu in the primary and mobile locations, as live.
$menu = wp_get_nav_menu_object( 'Header' );
$menu_id = $menu ? $menu->term_id : wp_create_nav_menu( 'Header' );
if ( ! $menu ) {
	foreach ( array( 'Home' => '/', 'About Us' => '/about-us/', 'Enrollment' => '/enrollment/', 'Shop' => '/shop/', 'FAQ' => '/faq/' ) as $t => $u ) {
		wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $t, 'menu-item-url' => home_url( $u ), 'menu-item-status' => 'publish' ) );
	}
}
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu_id, 'mobile_menu' => $menu_id ) );

flush_rewrite_rules( false );
echo "fixture ok\n";
