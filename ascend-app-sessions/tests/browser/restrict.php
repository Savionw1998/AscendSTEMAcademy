<?php
// CLI helper: switch Ultimate Member's "Restrict access to this post?" on or off for a page.
//   php restrict.php 6097 on|off
// Live (2026-09-28) has it OFF for the Time Card (6097), with the roles still filled in.
$_SERVER['HTTP_HOST'] = 'localhost:' . ( getenv( 'ASA_PORT' ) ?: '8080' );
require __DIR__ . '/wp-load.php';
$id = (int) $argv[1];
$r  = get_post_meta( $id, 'um_content_restriction', true );
$r['_um_custom_access_settings'] = 'on' === $argv[2];
update_post_meta( $id, 'um_content_restriction', $r );
echo "{$id}: " . ( $r['_um_custom_access_settings'] ? 'restricted' : 'not restricted' ) . "\n";
