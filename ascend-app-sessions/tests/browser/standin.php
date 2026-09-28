<?php
// CLI helper for the Ascend PWA stand-in: php standin.php init|include|static|status
// "static" puts the live manifest in a real manifest.json file (served without WordPress, like a
// static file on the live server); the other modes remove that file. Resets the plugin's status.
$_SERVER['HTTP_HOST'] = 'localhost:' . ( getenv( 'ASA_PORT' ) ?: '8080' );
require __DIR__ . '/wp-load.php';
$mode = $argv[1] ?? 'init';
if ( 'status' === $mode ) { // read-only: what Ascend App Sessions recorded
	echo json_encode( get_option( 'ascend_app_manifest_status' ) ), "\n";
	exit;
}
update_option( 'standin_pwa_mode', $mode );
delete_option( 'ascend_app_manifest_status' );
foreach ( array( __DIR__ . '/manifest.json', __DIR__ . '/manifest.json.before-ascend-app' ) as $f ) {
	if ( is_file( $f ) ) {
		unlink( $f );
	}
}
if ( 'static' === $mode ) {
	file_put_contents( __DIR__ . '/manifest.json', json_encode( standin_pwa_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
}
echo "stand-in: {$mode}\n";
