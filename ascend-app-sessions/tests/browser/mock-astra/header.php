<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="hfeed site">
<header class="site-header header-main-layout-1 ast-primary-menu-enabled ast-builder-menu-toggle-icon ast-mobile-header-inline" id="masthead">
	<div id="ast-desktop-header" data-toggle-type="dropdown">
		<div class="ast-main-header-wrap main-header-bar-wrap"><div class="ast-primary-header-bar ast-primary-header main-header-bar site-header-focus-item">
			<div class="site-primary-header-wrap ast-builder-grid-row-container site-header-focus-item ast-container"><div class="ast-builder-grid-row ast-builder-grid-row-has-sides ast-builder-grid-row-no-center">
				<div class="site-header-primary-section-left site-header-section ast-flex site-header-section-left"><div class="ast-builder-layout-element ast-flex site-header-focus-item" data-section="title_tagline"><div class="site-branding ast-site-identity"><span class="site-logo-img"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="custom-logo-link" rel="home"><img class="custom-logo" width="200" height="70" alt="Ascend STEM Academy" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%2270%22%3E%3Crect width=%22200%22 height=%2270%22 fill=%22%23009CDE%22/%3E%3C/svg%3E"></a></span></div></div></div>
				<div class="site-header-primary-section-right site-header-section ast-flex ast-grid-right-section">
					<div class="ast-builder-menu-1 ast-builder-menu ast-flex ast-builder-menu-1-focus-item ast-builder-layout-element site-header-focus-item" data-section="section-hb-menu-1"><div class="ast-main-header-bar-alignment"><div class="main-header-bar-navigation"><nav class="site-navigation ast-flex-grow-1 navigation-accessibility site-header-focus-item" id="primary-site-navigation-desktop" aria-label="Primary Site Navigation"><div class="main-navigation ast-inline-flex"><?php wp_nav_menu( array( 'theme_location' => 'primary', 'menu_id' => 'ast-hf-menu-1', 'menu_class' => 'main-header-menu ast-menu-shadow ast-nav-menu ast-flex submenu-with-border stack-on-mobile', 'container' => false ) ); ?></div></nav></div></div></div>
				</div>
			</div></div>
		</div></div>
	</div>
	<div id="ast-mobile-header" class="ast-mobile-header-wrap" data-type="dropdown">
		<div class="ast-main-header-wrap main-header-bar-wrap"><div class="ast-primary-header-bar ast-primary-header main-header-bar site-primary-header-wrap site-header-focus-item" data-section="section-primary-header-builder"><div class="ast-builder-grid-row ast-builder-grid-row-has-sides ast-builder-grid-row-no-center ast-container">
			<div class="site-header-primary-section-left site-header-section ast-flex site-header-section-left"><div class="ast-builder-layout-element ast-flex site-header-focus-item" data-section="title_tagline"><div class="site-branding ast-site-identity"><span class="site-logo-img"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="custom-logo-link" rel="home"><img class="custom-logo" width="200" height="70" alt="Ascend STEM Academy" src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%2270%22%3E%3Crect width=%22200%22 height=%2270%22 fill=%22%23009CDE%22/%3E%3C/svg%3E"></a></span></div></div></div>
			<div class="site-header-primary-section-right site-header-section ast-flex ast-grid-right-section"><div class="ast-builder-layout-element ast-flex site-header-focus-item" data-section="section-header-mobile-trigger"><div class="ast-button-wrap"><button type="button" class="menu-toggle main-header-menu-toggle ast-mobile-menu-trigger-minimal" aria-expanded="false">Menu</button></div></div></div>
		</div></div></div>
		<div class="ast-mobile-header-content content-align-flex-start"><?php wp_nav_menu( array( 'theme_location' => 'mobile_menu', 'menu_id' => 'ast-hf-mobile-menu', 'menu_class' => 'main-header-menu ast-nav-menu', 'container' => false ) ); ?></div>
	</div>
</header>
<div id="content" class="site-content"><div class="ast-container">
