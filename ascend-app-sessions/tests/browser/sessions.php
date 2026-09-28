<?php
// CLI helper: print a user's session tokens (expiration) and the renewal meta as JSON.
$_SERVER['HTTP_HOST'] = 'localhost:8080';
require __DIR__ . '/wp-load.php';
$u = get_user_by( 'login', $argv[1] );
if ( isset( $argv[2] ) && 'reset' === $argv[2] ) {
	WP_Session_Tokens::get_instance( $u->ID )->destroy_all();
	delete_user_meta( $u->ID, 'ascend_app_session_renewed' );
}
$s = get_user_meta( $u->ID, 'session_tokens', true ) ?: array();
echo json_encode( array( 'sessions' => array_values( array_map( fn( $x ) => $x['expiration'], $s ) ), 'renewed' => get_user_meta( $u->ID, 'ascend_app_session_renewed', true ) ) );
