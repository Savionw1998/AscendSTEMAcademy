<?php
// CLI helper: set Ultimate Member's restriction on a page.
//   php restrict.php 6097 off|message|redirect
//   off       public
//   message   logged-in roles only; others get UM's "Restricted content" page (this plugin sends
//             logged-out visitors to the login page instead)
//   redirect  logged-in roles only; UM itself sends others to the login page. The live Time Card
//             (6097) has been set like this since 2026-09-28.
$_SERVER['HTTP_HOST'] = 'localhost:' . ( getenv( 'ASA_PORT' ) ?: '8080' );
require __DIR__ . '/wp-load.php';
$id   = (int) $argv[1];
$mode = $argv[2] ?? 'redirect';
$r    = get_post_meta( $id, 'um_content_restriction', true );
$r['_um_custom_access_settings'] = 'off' !== $mode;
$r['_um_noaccess_action']        = 'redirect' === $mode ? 1 : 0;
$r['_um_access_redirect']        = 0; // 0 = the login page
update_post_meta( $id, 'um_content_restriction', $r );
echo "{$id}: {$mode}\n";
