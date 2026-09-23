<?php
/**
 * Ascend site features (Code Snippets: "Run snippet everywhere").
 *
 * Replaces the runtime parts of the Ascend Site Updates plugin so that plugin can be deleted:
 *   - [ascend_latest_guides] shortcode (blog page and guide lists)
 *   - sibling discount: 10% off each additional enrollment in one order
 *   - /blog and /register redirects
 *   - the office phone number in the time card's no-JavaScript message
 *   - the /shop/ page shows its own page content instead of WooCommerce's default product list
 *   - logged-out visitors who open the enrollment form are sent to log in first, then brought back
 *
 * While the Ascend Site Updates plugin is still active this snippet does nothing, so the two never
 * run twice (no double discount).
 */

if ( function_exists( 'asu_changes' ) ) {
	return;
}

$asa_enrollment_products = array( 4461, 4463, 4464, 4466 );
$asa_shop_page_id        = 3477;
$asa_enroll_form_page_id = 4426;

/* Redirects for two broken paths linked from live pages. */
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
	$map  = array(
		'blog'     => '/posts/',
		'register' => '/registration/',
	);
	if ( isset( $map[ $path ] ) ) {
		wp_safe_redirect( home_url( $map[ $path ] ), 301 );
		exit;
	}
} );

/* Enrollment form: logged-out visitors (for example from the app's long-press shortcut) go to log in, then come back. */
add_action( 'template_redirect', function () use ( $asa_enroll_form_page_id ) {
	if ( is_user_logged_in() || ! is_page( $asa_enroll_form_page_id ) ) {
		return;
	}
	$login = get_page_by_path( 'login' );
	$back  = get_permalink( $asa_enroll_form_page_id );
	$url   = $login ? add_query_arg( 'redirect_to', rawurlencode( $back ), get_permalink( $login ) ) : wp_login_url( $back );
	wp_safe_redirect( $url );
	exit;
}, 5 );

/* Sibling discount: 10% off every enrollment after the first in one order. The highest-priced one pays full price. */
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) use ( $asa_enrollment_products ) {
	if ( ( is_admin() && ! defined( 'DOING_AJAX' ) ) || ! $cart ) {
		return;
	}
	$prices = array();
	foreach ( $cart->get_cart() as $item ) {
		$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
		if ( ! in_array( $pid, $asa_enrollment_products, true ) || empty( $item['data'] ) ) {
			continue;
		}
		$price = (float) $item['data']->get_price();
		for ( $i = 0; $i < (int) $item['quantity']; $i++ ) {
			$prices[] = $price;
		}
	}
	if ( count( $prices ) < 2 ) {
		return;
	}
	rsort( $prices, SORT_NUMERIC );
	array_shift( $prices );
	$discount = round( array_sum( $prices ) * 0.10, 2 );
	if ( $discount > 0 ) {
		$cart->add_fee( 'Sibling discount (10% off each additional child)', -$discount, false );
	}
} );

/*
 * [ascend_latest_guides count="3"]              newest posts as cards
 * [ascend_latest_guides count="24" filters="1"] blog grid with topic filter links
 */
add_shortcode( 'ascend_latest_guides', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 3, 'filters' => 0 ), $atts, 'ascend_latest_guides' );
	$cat  = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
	$args = array(
		'numberposts'      => max( 1, min( 50, (int) $atts['count'] ) ),
		'post_status'      => 'publish',
		'suppress_filters' => false,
	);
	if ( $atts['filters'] && $cat ) {
		$args['category_name'] = $cat;
	}
	$posts = get_posts( $args );
	$out   = '<div class="asa-why asa-latest">';
	if ( $atts['filters'] ) {
		$terms = get_categories( array( 'hide_empty' => true, 'exclude' => array( (int) get_option( 'default_category' ) ) ) );
		if ( $terms ) {
			$base = remove_query_arg( 'topic' );
			$out .= '<nav class="asa-topics" aria-label="Filter by topic"><a href="' . esc_url( $base ) . '"' . ( $cat ? '' : ' aria-current="page"' ) . '>All</a>';
			foreach ( $terms as $t ) {
				$out .= '<a href="' . esc_url( add_query_arg( 'topic', $t->slug, $base ) ) . '"' . ( $cat === $t->slug ? ' aria-current="page"' : '' ) . '>' . esc_html( $t->name ) . '</a>';
			}
			$out .= '</nav>';
		}
	}
	if ( ! $posts ) {
		return $out . '<p>No guides in this topic yet.</p></div>';
	}
	$out .= '<div class="asa-guides">';
	foreach ( $posts as $p ) {
		$cats = get_the_category( $p->ID );
		$tag  = $cats && (int) $cats[0]->term_id !== (int) get_option( 'default_category' ) ? $cats[0]->name : 'Guide';
		$out .= sprintf(
			'<a class="asa-guide" href="%s"><span class="asa-tag">%s</span><strong>%s</strong><span class="asa-more">Read the guide &rarr;</span></a>',
			esc_url( get_permalink( $p ) ),
			esc_html( $tag ),
			esc_html( get_the_title( $p ) )
		);
	}
	return $out . '</div></div>';
} );

/* The time card's no-JavaScript message mentions calling the office but gives no number. */
add_filter( 'do_shortcode_tag', function ( $output, $tag ) {
	if ( 'ascend_time_card' === $tag && is_string( $output ) && false === strpos( $output, '385-7653' ) ) {
		$output = str_replace(
			'Call the office and we will take your hours over the phone.',
			'Call the office at (386) 385-7653 and we will take your hours over the phone.',
			$output
		);
	}
	return $output;
}, 10, 2 );

/*
 * /shop/: WooCommerce replaces the Shop page with its default product list, so whatever is on the
 * page itself never shows. Show the page's own content (intro and Merch / Student services /
 * Game passes sections) on the plain /shop/ URL. Searches, sorting, filters and page 2+ keep
 * WooCommerce's normal list.
 */
add_action( 'template_redirect', function () use ( $asa_shop_page_id ) {
	if ( ! function_exists( 'is_shop' ) || ! is_shop() || is_search() || is_paged() || ! empty( $_GET ) ) {
		return;
	}
	$page = get_post( $asa_shop_page_id );
	if ( ! $page || 'publish' !== $page->post_status || '' === trim( $page->post_content ) ) {
		return;
	}
	add_filter( 'body_class', function ( $classes ) {
		$classes[] = 'asa-shop-landing';
		return $classes;
	} );
	get_header();
	echo '<div id="primary" class="content-area primary"><main id="main" class="site-main"><article class="page type-page">';
	echo '<div class="entry-content clear">' . do_shortcode( do_blocks( $page->post_content ) ) . '</div>';
	echo '</article></main></div>';
	get_footer();
	exit;
}, 20 );
