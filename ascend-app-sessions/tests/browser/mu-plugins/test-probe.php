<?php
// Local test probe only: report whether the page would be stored by a page cache.
add_action( 'template_redirect', function () {
	header( 'X-Test-DoNotCachePage: ' . ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ? 'yes' : 'no' ) );
}, 99 );
