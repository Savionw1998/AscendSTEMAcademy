<?php
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	register_nav_menus( array( 'primary' => 'Primary', 'mobile_menu' => 'Mobile' ) );
} );
// Astra adds these body classes; the transparent header is off on WooCommerce pages live.
add_filter( 'body_class', function ( $c ) {
	$c[] = 'ast-theme-transparent-header';
	$c[] = is_page() ? 'ast-page-builder-template' : 'ast-separate-container';
	return $c;
} );
add_action( 'wp_enqueue_scripts', function () {
	// Core of Astra's header + transparent header CSS (astra 4.13 frontend + transparent-header dynamic CSS).
	wp_register_style( 'astra-theme-css', false );
	wp_enqueue_style( 'astra-theme-css' );
	wp_add_inline_style( 'astra-theme-css', '
body{margin:0;background:#EDFBE2;font-family:system-ui,sans-serif;color:#1D4010}
.ast-container{max-width:1240px;margin:0 auto;padding:0 20px}
#masthead{z-index:99}
.ast-builder-grid-row{display:flex;align-items:center;justify-content:space-between;min-height:90px}
.main-header-bar{background:#fff;border-bottom:1px solid #eaeaea}
.site-branding img{max-height:70px;display:block}
.main-header-menu{display:flex;gap:18px;list-style:none;margin:0;padding:0}
.main-header-menu a{color:#1D4010;font-weight:700;text-decoration:none}
.ast-header-button-1 a{background:#009CDE;color:#fff;border-radius:999px;padding:10px 18px;text-decoration:none}
#ast-mobile-header{display:none}
.ast-mobile-header-content{display:none}
.menu-toggle{background:#009CDE;color:#fff;border:0;border-radius:4px;padding:10px 12px}
@media (max-width:921px){#ast-desktop-header{display:none}#ast-mobile-header{display:block}.ast-builder-grid-row{min-height:70px}}
/* transparent header (Astra: Customizer > Transparent Header, enabled on desktop and mobile) */
.ast-theme-transparent-header #masthead{position:absolute;left:0;right:0}
.ast-theme-transparent-header .main-header-bar,.ast-theme-transparent-header .ast-primary-header-bar{background:none;border-bottom-width:0}
' );
} );
