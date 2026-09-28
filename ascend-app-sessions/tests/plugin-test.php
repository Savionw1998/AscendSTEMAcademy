<?php
/**
 * Exercises the plugin's rules without WordPress, using small stand-ins for the
 * WordPress functions and for W3 Total Cache's config object.
 *
 *   php ascend-app-sessions/tests/plugin-test.php
 *
 * The browser behaviour (cookies, login form, renewal) is covered by tests/browser/.
 */

namespace W3TC {
	class FakeConfig {
		public $values = array();
		public function get_boolean( $key ) { return (bool) ( $this->values[ $key ] ?? false ); }
		public function get_array( $key ) { return (array) ( $this->values[ $key ] ?? array() ); }
	}
	class Dispatcher {
		public static $config;
		public static function config() { return self::$config; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'YEAR_IN_SECONDS', 365 * 86400 );
	$GLOBALS['__filters'] = array();
	$GLOBALS['__users']   = array();

	function add_filter( $h, $f, $p = 10, $a = 1 ) { $GLOBALS['__filters'][ $h ][] = $f; }
	function add_action( $h, $f, $p = 10, $a = 1 ) {}
	function apply_filters( $h, $v, ...$args ) { foreach ( $GLOBALS['__filters'][ $h ] ?? array() as $f ) { $v = $f( $v, ...$args ); } return $v; }
	function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/ascend-app-sessions/'; }
	function get_userdata( $id ) { return isset( $GLOBALS['__users'][ $id ] ) ? (object) array( 'roles' => $GLOBALS['__users'][ $id ] ) : false; }
	function __( $s ) { return $s; }
	function wp_json_encode( $v ) { return json_encode( $v ); }

	require __DIR__ . '/../ascend-app-sessions.php';

	$fails = 0;
	$n     = 0;
	function check( $name, $cond ) {
		global $fails, $n;
		$n++;
		echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n";
		if ( ! $cond ) {
			$fails++;
		}
	}

	// Referrer detection.
	check( 'any android-app referrer counts when no package is set', ascend_app_referrer_is_app( 'android-app://com.example.app/' ) );
	check( 'web referrers do not count', ! ascend_app_referrer_is_app( 'https://www.google.com/' ) && ! ascend_app_referrer_is_app( '' ) );
	add_filter( 'ascend_app_package', fn() => 'com.ascendstemacademy.twa' );
	check( 'with a package set, only that package counts', ascend_app_referrer_is_app( 'android-app://com.ascendstemacademy.twa/' ) && ! ascend_app_referrer_is_app( 'android-app://com.google.android.gm/' ) );
	check( 'a package is matched exactly, not by prefix', ! ascend_app_referrer_is_app( 'android-app://com.ascendstemacademy.twa.evil/' ) );
	check( 'the in-page script carries the package', false !== strpos( ascend_app_detection_script(), '"com.ascendstemacademy.twa"' ) );

	// Who gets the 90-day app login.
	$GLOBALS['__users'] = array(
		1 => array( 'administrator', 'bbp_keymaster' ),
		2 => array( 'um_student', 'bbp_participant' ),
		3 => array( 'um_faculty' ),
		4 => array( 'editor' ),
		5 => array( 'um_student', 'editor' ),
		6 => array( 'subscriber' ),
	);
	check( 'families (um_student) get the long login', ascend_app_user_gets_long_session( 2 ) );
	check( 'admins, faculty and editors do not', ! ascend_app_user_gets_long_session( 1 ) && ! ascend_app_user_gets_long_session( 3 ) && ! ascend_app_user_gets_long_session( 4 ) );
	check( 'a staff role wins over a family role on the same account', ! ascend_app_user_gets_long_session( 5 ) );
	check( 'other roles and unknown users do not', ! ascend_app_user_gets_long_session( 6 ) && ! ascend_app_user_gets_long_session( 99 ) );

	$_COOKIE = array();
	check( 'outside the app the length is untouched', ascend_app_auth_cookie_expiration( 1209600, 2, true ) === 1209600 );
	$_COOKIE['asa_app'] = '1';
	check( 'in the app a family gets 90 days', ascend_app_auth_cookie_expiration( 1209600, 2, true ) === 90 * DAY_IN_SECONDS );
	check( 'in the app an admin keeps the default', ascend_app_auth_cookie_expiration( 1209600, 1, true ) === 1209600 );
	$_COOKIE['asa_app'] = 'yes';
	check( 'only asa_app=1 counts as the app', ascend_app_auth_cookie_expiration( 1209600, 2, true ) === 1209600 );

	// W3 Total Cache self-check.
	$c = new \W3TC\FakeConfig();
	\W3TC\Dispatcher::$config = $c;
	$c->values = array( 'pgcache.enabled' => false );
	check( 'W3TC page cache off: nothing to fix', array() === ascend_app_w3tc_problems() );
	$c->values = array( 'pgcache.enabled' => true, 'pgcache.reject.logged' => true, 'pgcache.reject.cookie' => array( 'wptouch_switch_toggle' ) );
	$p = ascend_app_w3tc_problems();
	check( 'W3TC defaults: asks for asa_app in Rejected cookies', 1 === count( $p ) && false !== strpos( $p[0], 'asa_app' ) );
	$c->values['pgcache.reject.logged'] = false;
	check( 'W3TC caching logged-in pages: asks to turn that off too', 2 === count( ascend_app_w3tc_problems() ) );
	$c->values = array( 'pgcache.enabled' => true, 'pgcache.reject.logged' => true, 'pgcache.reject.cookie' => array( 'wptouch_switch_toggle', ' asa_app ' ) );
	check( 'W3TC configured: nothing to fix', array() === ascend_app_w3tc_problems() );

	echo "\n" . ( $n - $fails ) . "/$n passed\n";
	exit( $fails ? 1 : 0 );
}
