<?php
/** Výpis odznaků ve výpisu produktů a na detailu produktu. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'epxb-badges', EPXB_URL . 'assets/css/badges.css', array(), EPXB_VERSION );
	}
);

function epxb_print_badges( string $where ) {
	global $product;
	if ( $product instanceof WC_Product ) {
		echo epxb_corners_html( epxb_badges_for_product( $product, $where ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML je escapované při sestavení.
	}
}

add_action( 'woocommerce_before_shop_loop_item_title', function () { epxb_print_badges( 'loop' ); }, 9 );
add_action( 'woocommerce_before_single_product_summary', function () { epxb_print_badges( 'single' ); }, 9 );
