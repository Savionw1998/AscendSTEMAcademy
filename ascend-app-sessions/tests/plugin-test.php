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
	define( 'ABSPATH', sys_get_temp_dir() . '/ascend-app-test-' . getmypid() . '/' ); // the web root, for manifest.json
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'HOUR_IN_SECONDS', 3600 );
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
	function home_url( $p = '' ) { return 'https://ascendstemacademy.com' . $p; }
	function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
	$GLOBALS['__options'] = array();
	$GLOBALS['__ajax']    = false;
	$GLOBALS['__flushed'] = array();
	function get_option( $k, $d = false ) { return $GLOBALS['__options'][ $k ] ?? $d; }
	function update_option( $k, $v, $autoload = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
	function wp_doing_ajax() { return $GLOBALS['__ajax']; }
	function wp_is_writable( $f ) { return is_writable( $f ) && ! ( $GLOBALS['__read_only'] ?? false ); }
	function current_user_can( $c ) { return true; }
	function esc_html__( $s ) { return $s; }
	function w3tc_flush_posts() { $GLOBALS['__flushed'][] = 'posts'; }
	function w3tc_flush_url( $u ) { $GLOBALS['__flushed'][] = $u; }

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

	// Referrer detection. The default package is the published app's applicationId.
	check( 'the app\'s own referrer counts', ascend_app_referrer_is_app( 'android-app://com.ascendstemacademy.twa/' ) );
	check( 'other apps\' referrers do not (e.g. a link opened from Gmail)', ! ascend_app_referrer_is_app( 'android-app://com.google.android.gm/' ) );
	check( 'a package is matched exactly, not by prefix', ! ascend_app_referrer_is_app( 'android-app://com.ascendstemacademy.twa.evil/' ) );
	check( 'web referrers do not count', ! ascend_app_referrer_is_app( 'https://www.google.com/' ) && ! ascend_app_referrer_is_app( '' ) );
	check( 'the in-page script carries the package', false !== strpos( ascend_app_detection_script(), '"com.ascendstemacademy.twa"' ) );
	add_filter( 'ascend_app_package', fn() => '' );
	check( 'with the package set to \'\', any android-app referrer counts', ascend_app_referrer_is_app( 'android-app://com.example.app/' ) );

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

	// Web manifest: icons and shortcuts handed to the Ascend PWA plugin.
	$m = apply_filters( 'ascend_app_manifest', array( 'name' => 'Ascend STEM Academy', 'start_url' => '/', 'shortcuts' => array( 'old' ) ) );
	check( 'manifest filter keeps the PWA plugin\'s other fields', 'Ascend STEM Academy' === $m['name'] && '/' === $m['start_url'] );
	check( 'shortcuts replaced: Time Card, Students, Parents, Games', array( 'Time Card', 'Students', 'Parents', 'Games' ) === array_column( $m['shortcuts'], 'name' ) );
	check( 'shortcut URLs', array( 'https://ascendstemacademy.com/time-card-tracker/', 'https://ascendstemacademy.com/user/', 'https://ascendstemacademy.com/account/', 'https://ascendstemacademy.com/guess-a-lotl/' ) === array_column( $m['shortcuts'], 'url' ) );
	$ok = true;
	foreach ( $m['shortcuts'] as $sc ) {
		foreach ( $sc['icons'] as $icon ) {
			$file = __DIR__ . '/../assets/icons/' . basename( strtok( $icon['src'], '?' ) );
			$dim  = is_file( $file ) ? getimagesize( $file ) : false;
			$ok   = $ok && $dim && 96 === $dim[0] && 96 === $dim[1] && 'image/png' === $dim['mime'] && '96x96' === $icon['sizes'];
		}
		$ok = $ok && array( 'any', 'maskable' ) === array_column( $sc['icons'], 'purpose' );
	}
	check( 'every shortcut has a 96x96 PNG, round and maskable, and the files exist', $ok );
	$ok = count( $m['icons'] ) === 3;
	foreach ( $m['icons'] as $icon ) {
		$dim = getimagesize( __DIR__ . '/../assets/icons/' . basename( strtok( $icon['src'], '?' ) ) );
		$ok  = $ok && $icon['sizes'] === $dim[0] . 'x' . $dim[1] && 'image/png' === $dim['mime'];
	}
	check( 'app icons: 192, 512 and maskable 512, sizes match the files', $ok && array( 'any', 'any', 'maskable' ) === array_column( $m['icons'], 'purpose' ) );

	// The live /manifest.json response, as the Ascend PWA plugin sends it, with ours merged in.
	$ours = ascend_app_manifest( array() );
	$live = json_encode( array( 'name' => 'Ascend STEM Academy', 'short_name' => 'Ascend STEM', 'start_url' => '/', 'theme_color' => '#009CDE', 'icons' => array( array( 'src' => '/old.png' ) ) ) );
	$out  = json_decode( ascend_app_merge_manifest_json( $live, $ours ), true );
	check( 'manifest response: icons and shortcuts replaced', $out['icons'] === $ours['icons'] && $out['shortcuts'] === $ours['shortcuts'] );
	check( 'manifest response: the PWA plugin\'s own fields kept', 'Ascend STEM' === $out['short_name'] && '#009CDE' === $out['theme_color'] && '/' === $out['start_url'] );
	check( 'manifest response: URLs stay unescaped', false === strpos( ascend_app_merge_manifest_json( $live, $ours ), '\\/' ) );
	$html = '<!doctype html><title>Page not found</title>';
	check( 'a response that is not a manifest is left alone', $html === ascend_app_merge_manifest_json( $html, $ours ) && '{"a":1}' === ascend_app_merge_manifest_json( '{"a":1}', $ours ) );

	// /manifest.json served by WordPress: buffered from the moment this plugin loads, merged on the way out.
	$_SERVER['REQUEST_METHOD']     = 'GET';
	$_SERVER['REQUEST_URI']        = '/manifest.json?v=3';
	$_SERVER['HTTP_IF_NONE_MATCH'] = '"old"';
	ob_start();
	$level = ob_get_level();
	ascend_app_rewrite_manifest_response();
	$buffered = ob_get_level() > $level;
	echo $live;
	if ( $buffered ) {
		ob_end_flush();
	}
	$out = json_decode( ob_get_clean(), true );
	check( 'GET /manifest.json: buffered and rewritten on the way out', $buffered && $out['shortcuts'] === $ours['shortcuts'] && 'Ascend STEM' === $out['short_name'] );
	check( 'GET /manifest.json: never page-cached, never a 304 for the old version', defined( 'DONOTCACHEPAGE' ) && ! isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) );
	check( 'GET /manifest.json: recorded as served by WordPress', ascend_app_manifest_status()['served_by_wordpress'] > 0 );
	foreach ( array( array( 'GET', '/manifest.json.bak' ), array( 'GET', '/' ), array( 'POST', '/manifest.json' ) ) as $req ) {
		list( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ) = $req;
		$level = ob_get_level();
		ascend_app_rewrite_manifest_response();
		$ok = ob_get_level() === $level;
		if ( ! $ok ) {
			ob_end_clean();
		}
		check( "{$req[0]} {$req[1]}: left alone", $ok );
	}

	// /manifest.json as a real file in the web root: updated from admin pages.
	mkdir( ABSPATH );
	$file = ABSPATH . 'manifest.json';
	ascend_app_update_static_manifest();
	check( 'no manifest.json file: nothing to do', 'none' === ascend_app_manifest_status()['static_file'] );
	file_put_contents( $file, '{"hello":"world"}' );
	ascend_app_update_static_manifest();
	check( 'some other JSON file: left alone', 'not a web manifest' === ascend_app_manifest_status()['static_file'] && '{"hello":"world"}' === file_get_contents( $file ) );
	file_put_contents( $file, $live );
	$GLOBALS['__read_only'] = true;
	ascend_app_update_static_manifest();
	ob_start();
	ascend_app_manifest_notice();
	$notice = ob_get_clean();
	check( 'a read-only file: left alone, recorded, and the admin is told', 'not writable' === ascend_app_manifest_status()['static_file'] && $live === file_get_contents( $file ) && false !== strpos( $notice, 'cannot be changed' ) );
	$GLOBALS['__read_only'] = false;
	$GLOBALS['__ajax']      = true;
	ascend_app_update_static_manifest();
	check( 'not during AJAX requests', $live === file_get_contents( $file ) );
	$GLOBALS['__ajax'] = false;
	ascend_app_update_static_manifest();
	$out = json_decode( file_get_contents( $file ), true );
	check( 'a writable file: icons and shortcuts updated, other fields kept', $out['shortcuts'] === $ours['shortcuts'] && $out['icons'] === $ours['icons'] && 'Ascend STEM' === $out['short_name'] && '#009CDE' === $out['theme_color'] );
	check( 'the original is kept next to it, and the update is recorded', $live === file_get_contents( $file . '.before-ascend-app' ) && 'updated' === ascend_app_manifest_status()['static_file'] );
	ob_start();
	ascend_app_manifest_notice();
	check( 'no admin notice once updated', '' === ob_get_clean() );
	$mtime = filemtime( $file );
	$raw   = file_get_contents( $file );
	ascend_app_update_static_manifest();
	check( 'already up to date: not written again', 'up to date' === ascend_app_manifest_status()['static_file'] && $raw === file_get_contents( $file ) && filemtime( $file ) === $mtime );
	file_put_contents( $file, $live ); // the PWA plugin writing its own version again
	ascend_app_update_static_manifest();
	check( 'overwritten again later: updated again, the first original kept', json_decode( file_get_contents( $file ), true )['shortcuts'] === $ours['shortcuts'] && $live === file_get_contents( $file . '.before-ascend-app' ) );
	array_map( 'unlink', glob( ABSPATH . '*' ) );
	rmdir( ABSPATH );

	// Stored copies in W3 Total Cache: emptied once per plugin version.
	$GLOBALS['__ajax'] = true;
	ascend_app_flush_stored_copies();
	check( 'cache not emptied during AJAX requests', array() === $GLOBALS['__flushed'] );
	$GLOBALS['__ajax'] = false;
	ascend_app_flush_stored_copies();
	check( 'first admin page after an update: page cache and the manifest URL emptied', array( 'posts', 'https://ascendstemacademy.com/manifest.json' ) === $GLOBALS['__flushed'] );
	ascend_app_flush_stored_copies();
	check( 'only once per version', 2 === count( $GLOBALS['__flushed'] ) );
	$GLOBALS['__options']['ascend_app_flushed_version'] = '1.0.0';
	ascend_app_flush_stored_copies();
	check( 'again after the next update', 4 === count( $GLOBALS['__flushed'] ) );

	echo "\n" . ( $n - $fails ) . "/$n passed\n";
	exit( $fails ? 1 : 0 );
}
