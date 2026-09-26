<?php
/**
 * Exercises the plugin's access rules without WordPress, using small stand-ins
 * for the WordPress functions and the Ascend Axolotl Games key functions.
 *
 *   php ascend-stem-a-lotl-rescue-lab/tests/plugin-test.php
 */

define( 'ABSPATH', __DIR__ );
$GLOBALS['__meta'] = array(); $GLOBALS['__opt'] = array(); $GLOBALS['__today'] = '2026-09-26'; $GLOBALS['__keys'] = array(); $GLOBALS['__pass'] = array(); $GLOBALS['__filters'] = array();

class WP_Error { private $c; private $m; function __construct( $c, $m ) { $this->c = $c; $this->m = $m; } function get_error_code() { return $this->c; } function get_error_message() { return $this->m; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_user_meta( $u, $k, $s ) { return $GLOBALS['__meta'][ $u ][ $k ] ?? ''; }
function update_user_meta( $u, $k, $v ) { $GLOBALS['__meta'][ $u ][ $k ] = $v; return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__opt'] ) ? $GLOBALS['__opt'][ $k ] : $d; }
function update_option( $k, $v ) { foreach ( $GLOBALS['__filters'][ 'pre_update_option_' . $k ] ?? array() as $f ) { $v = $f( $v ); } $GLOBALS['__opt'][ $k ] = $v; return true; }
function add_option( $k, $v ) { if ( ! array_key_exists( $k, $GLOBALS['__opt'] ) ) { $GLOBALS['__opt'][ $k ] = $v; } }
function add_filter( $h, $f ) { $GLOBALS['__filters'][ $h ][] = $f; }
function apply_filters( $h, $v ) { foreach ( $GLOBALS['__filters'][ $h ] ?? array() as $f ) { $v = $f( $v ); } return $v; }
function add_action() {} function add_shortcode() {} function register_rest_route() {}
function current_time( $f ) { return $GLOBALS['__today']; }
function wp_cache_delete() {}
function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $s ) ); }
function esc_url_raw( $s ) { return $s; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function do_action() {}
class FakeDb { public $locks = array(); function prepare( $q, $a ) { return array( $q, $a ); } function get_var( $p ) { if ( isset( $this->locks[ $p[1] ] ) ) { return '0'; } $this->locks[ $p[1] ] = true; return '1'; } function query( $p ) { unset( $this->locks[ $p[1] ] ); } }
$GLOBALS['wpdb'] = new FakeDb();

// stand-ins for the Ascend Axolotl Games key API
function ascend_games_skips( $u ) { return $GLOBALS['__keys'][ $u ] ?? 0; }
function ascend_games_consume_skip( $u ) { if ( ( $GLOBALS['__keys'][ $u ] ?? 0 ) < 1 ) { return false; } $GLOBALS['__keys'][ $u ]--; return true; }
function ascend_games_has_pass( $u ) { return ! empty( $GLOBALS['__pass'][ $u ] ); }

require __DIR__ . '/../ascend-stem-a-lotl-rescue-lab.php';

$fails = 0; $n = 0;
function check( $name, $cond ) { global $fails, $n; $n++; echo ( $cond ? 'PASS ' : 'FAIL ' ) . $name . "\n"; if ( ! $cond ) { $fails++; } }

// defaults
check( 'defaults: 3 free, 1 key, daily on', ascend_rl_config() === ascend_rl_config_defaults() );
check( 'free puzzles open for anyone', ascend_rl_is_open( 1, 3 ) && ! ascend_rl_is_open( 1, 4 ) );

// daily: once per day, lowest locked, no rollover
check( 'first visit opens puzzle 4', ascend_rl_claim_daily( 1 ) === 4 );
check( 'second visit the same day opens nothing', ascend_rl_claim_daily( 1 ) === 0 );
$GLOBALS['__today'] = '2026-09-27';
check( 'next day opens puzzle 5', ascend_rl_claim_daily( 1 ) === 5 );
$GLOBALS['__today'] = '2026-10-05';
check( 'after 8 missed days only one opens (no rollover)', ascend_rl_claim_daily( 1 ) === 6 && ascend_rl_claim_daily( 1 ) === 0 );
check( 'daily opens are recorded as daily', ascend_rl_owned( 1 ) === array( 4 => 'daily', 5 => 'daily', 6 => 'daily' ) );

// keys: any locked puzzle, idempotent, never below zero
$GLOBALS['__keys'][1] = 2;
check( 'unlocking puzzle 20 spends one key', ascend_rl_unlock_level( 1, 20 ) === array( 'spent' => 1 ) && ascend_games_skips( 1 ) === 1 );
check( 'unlocking puzzle 20 again spends nothing', ascend_rl_unlock_level( 1, 20 ) === array( 'spent' => 0 ) && ascend_games_skips( 1 ) === 1 );
check( 'free puzzle never costs a key', ascend_rl_unlock_level( 1, 2 ) === array( 'spent' => 0 ) && ascend_games_skips( 1 ) === 1 );
ascend_rl_unlock_level( 1, 21 );
$r = ascend_rl_unlock_level( 1, 22 );
check( 'no keys left gives no_keys and spends nothing', is_wp_error( $r ) && $r->get_error_code() === 'no_keys' && ascend_games_skips( 1 ) === 0 );
$GLOBALS['__today'] = '2026-10-06';
check( 'daily skips puzzles already opened with keys', ascend_rl_claim_daily( 1 ) === 7 );
check( 'bad puzzle id is refused', is_wp_error( ascend_rl_unlock_level( 1, 41 ) ) );

// concurrent unlock: a held lock refuses the second request instead of double spending
$GLOBALS['__keys'][2] = 1; $GLOBALS['wpdb']->locks['ascend_rl_2'] = true;
$r = ascend_rl_unlock_level( 2, 10 );
check( 'a second concurrent request is refused while the first holds the lock', is_wp_error( $r ) && $r->get_error_code() === 'busy' && ascend_games_skips( 2 ) === 1 );
unset( $GLOBALS['wpdb']->locks['ascend_rl_2'] );

// pass holders: everything open, daily not needed
$GLOBALS['__pass'][3] = true;
check( 'pass opens every puzzle', ascend_rl_is_open( 3, 40 ) && ascend_rl_claim_daily( 3 ) === 0 );

// skins
$GLOBALS['__keys'][4] = 1;
check( 'buying the galaxy pellet spends one key', ascend_rl_buy_skin( 4, 'galaxy' ) === array( 'spent' => 1 ) && ascend_rl_owned_skins( 4 ) === array( 'galaxy' ) );
check( 'buying it again spends nothing', ascend_rl_buy_skin( 4, 'galaxy' ) === array( 'spent' => 0 ) );
check( 'earned skins are not for sale', is_wp_error( ascend_rl_buy_skin( 4, 'rainbow' ) ) );

// v1 ownership (plain list of ids) is still honoured
update_user_meta( 5, ASCEND_RL_OWNED_META, array( 4, 9 ) );
check( 'v1 owned list migrates', ascend_rl_owned( 5 ) === array( 4 => 'key', 9 => 'key' ) );

// config over MCP: JSON strings accepted, junk clamped
update_option( 'ascend_rl_config', '{"freeLevels": 5, "keyCost": 0, "dailyUnlock": "false", "skinPrices": {"galaxy": 3, "rainbow": 9}}' );
$c = ascend_rl_config();
check( 'config from JSON string is cleaned', $c['freeLevels'] === 5 && $c['keyCost'] === 1 && $c['dailyUnlock'] === false && $c['skinPrices'] === array( 'galaxy' => 3, 'pearl' => 1, 'disco' => 1 ) );
$GLOBALS['__today'] = '2026-10-07';
check( 'daily off means no daily puzzle', ascend_rl_claim_daily( 1 ) === 0 );
check( 'free count follows config', ascend_rl_is_open( 9, 5 ) && ! ascend_rl_is_open( 9, 6 ) );
update_option( 'ascend_rl_level_text', array( '12' => array( 'name' => ' <b>Gusty</b> ', 'bogus' => 'x' ), '99' => array( 'name' => 'nope' ) ) );
check( 'level text overrides are cleaned', get_option( 'ascend_rl_level_text' ) === array( '12' => array( 'name' => 'Gusty' ) ) );
check( 'options are exposed to Royal MCP', count( array_intersect( ascend_rl_options(), apply_filters( 'royal_mcp_writable_options', array() ) ) ) === 4 );

// progress merge keeps earned stars
$a = array( 'levels' => array( 1 => array( 'done' => true, 'badges' => array( 'rescue' => true ) ) ), 'runs' => 3 );
$b = array( 'levels' => array( 1 => array( 'done' => true, 'badges' => array( 'efficiency' => true ) ), 2 => array( 'done' => true ) ), 'runs' => 5, 'owned' => array( 9 ) );
$m = ascend_rl_merge_progress( $a, $b );
check( 'merge keeps stars from both sides', $m['levels']['1']['badges'] === array( 'rescue' => true, 'efficiency' => true ) && $m['levels']['2']['done'] && ! isset( $m['owned'] ) );

echo ( $n - $fails ) . "/$n passed\n";
exit( $fails ? 1 : 0 );
