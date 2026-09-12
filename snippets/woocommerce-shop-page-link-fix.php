<?php
/**
 * Fix: "Return to shop" / "Browse store" buttons link to the homepage.
 *
 * WooCommerce builds every back-to-shop link from wc_get_page_permalink( 'shop' ):
 *
 *   wc_get_page_id( 'shop' )        -> absint( get_option( 'woocommerce_shop_page_id' ) ) ?: -1
 *   wc_get_page_permalink( 'shop' ) -> get_permalink( $id ) when $id > 0, else get_home_url()
 *
 * On this site woocommerce_shop_page_id is stored empty, so the lookup returns -1
 * and every button silently falls back to the homepage instead of /shop/.
 *
 * The real fix is to set WooCommerce > Settings > Advanced > Page setup > Shop page
 * to "Ascend STEM Academy Merch" (/shop/). This filter is the code-level equivalent
 * for when that option cannot be written: it defers to the stored setting whenever
 * that setting is valid, and only resolves /shop/ itself when it is missing or broken.
 *
 * Drop into the astra-child theme's functions.php (or a site-specific plugin).
 */

add_filter(
	'woocommerce_get_shop_page_id',
	function ( $page_id ) {
		if ( absint( $page_id ) > 0 && 'publish' === get_post_status( $page_id ) ) {
			return $page_id;
		}

		$shop = get_page_by_path( 'shop' );

		return $shop ? $shop->ID : $page_id;
	}
);
