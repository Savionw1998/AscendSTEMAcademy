<?php
/**
 * Site features that run on the front end: redirects, sibling discount,
 * the [ascend_latest_guides] shortcode and the stylesheet for new sections.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Enrollment (tuition) product IDs that the sibling discount applies to. */
const ASU_ENROLLMENT_PRODUCTS = array( 4461, 4463, 4464, 4466 );
const ASU_SIBLING_RATE        = 0.10;

function asu_sibling_enabled() {
	return (bool) get_option( 'asu_sibling_enabled', 1 );
}

function asu_redirects_enabled() {
	return (bool) get_option( 'asu_redirects_enabled', 1 );
}

/* Redirects for two broken paths linked from live pages. */
add_action( 'template_redirect', function () {
	if ( ! asu_redirects_enabled() || ! is_404() ) {
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

/**
 * Sibling discount: 10% off every enrollment after the first in one order.
 * The highest-priced enrollment pays full price.
 */
function asu_sibling_discount_for_prices( array $unit_prices ) {
	if ( count( $unit_prices ) < 2 ) {
		return 0.0;
	}
	rsort( $unit_prices, SORT_NUMERIC );
	array_shift( $unit_prices );
	return round( array_sum( $unit_prices ) * ASU_SIBLING_RATE, 2 );
}

add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {
	if ( ! asu_sibling_enabled() || ( is_admin() && ! defined( 'DOING_AJAX' ) ) || ! $cart ) {
		return;
	}
	$prices = array();
	foreach ( $cart->get_cart() as $item ) {
		$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
		if ( ! in_array( $pid, ASU_ENROLLMENT_PRODUCTS, true ) || empty( $item['data'] ) ) {
			continue;
		}
		$price = (float) $item['data']->get_price();
		for ( $i = 0; $i < (int) $item['quantity']; $i++ ) {
			$prices[] = $price;
		}
	}
	$discount = asu_sibling_discount_for_prices( $prices );
	if ( $discount > 0 ) {
		$cart->add_fee( __( 'Sibling discount (10% off each additional child)', 'ascend-site-updates' ), -$discount, false );
	}
} );

/*
 * [ascend_latest_guides count="3"]            newest posts as cards
 * [ascend_latest_guides count="24" filters="1"] blog grid with category filter links
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
	if ( 'ascend_time_card' === $tag && is_string( $output ) ) {
		$output = str_replace(
			'Call the office and we will take your hours over the phone.',
			'Call the office at (386) 385-7653 and we will take your hours over the phone.',
			$output
		);
	}
	return $output;
}, 10, 2 );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'asu-site', ASU_URL . 'assets/site.css', array(), ASU_VERSION );
} );
