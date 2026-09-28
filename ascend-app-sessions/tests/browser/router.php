<?php
// Router for `php -S`: serve real files, send everything else to WordPress.
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = __DIR__ . $path;
if ( $path !== '/' && is_file( $file ) && ! str_ends_with( $file, '.php' ) ) {
	return false;
}
if ( $path !== '/' && is_file( $file ) && str_ends_with( $file, '.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = $path;
	$_SERVER['SCRIPT_FILENAME'] = $file;
	chdir( dirname( $file ) );
	require $file;
	return true;
}
if ( is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) && $path !== '/' ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
	require rtrim( $file, '/' ) . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
