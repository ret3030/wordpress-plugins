<?php
/**
 * Sdílené funkce pro čtení dat galerií.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vrátí capability potřebnou pro správu galerií.
 *
 * Výchozí `manage_woocommerce` mají role Správce obchodu (shop_manager)
 * a Administrátor. Redaktor ji nemá.
 *
 * Když WooCommerce neběží, tuto capability nemá nikdo — ani administrátor —
 * takže se v tom případě vracíme k `edit_pages`, aby stránka nezmizela.
 *
 * Vyhodnocuje se až za běhu (ne při načtení pluginu), protože WooCommerce
 * se načítá později než tenhle plugin.
 *
 * @return string
 */
function cg_capability() {
	$cap = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'edit_pages';

	/**
	 * Umožňuje přenastavit, kdo smí spravovat obsah galerií.
	 *
	 * @param string $cap Capability.
	 */
	return apply_filters( 'cg_capability', $cap );
}

/**
 * Vrátí všechny galerie.
 *
 * Struktura: [ 'slug' => [ 'title' => string, 'ids' => int[] ], ... ]
 *
 * @return array
 */
function cg_get_galleries() {
	$galleries = get_option( CG_OPTION, array() );

	if ( ! is_array( $galleries ) ) {
		return array();
	}

	foreach ( $galleries as $slug => $gallery ) {
		$galleries[ $slug ] = array(
			'title' => isset( $gallery['title'] ) ? (string) $gallery['title'] : $slug,
			'ids'   => isset( $gallery['ids'] ) ? array_map( 'absint', (array) $gallery['ids'] ) : array(),
		);
	}

	return $galleries;
}

/**
 * Vrátí ID obrázků jedné galerie. Odfiltruje mezitím smazané přílohy.
 *
 * @param string $slug Identifikátor galerie.
 * @return int[]
 */
function cg_get_gallery_ids( $slug ) {
	$galleries = cg_get_galleries();
	$slug      = sanitize_key( $slug );

	if ( ! isset( $galleries[ $slug ] ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$galleries[ $slug ]['ids'],
			function ( $id ) {
				return 'attachment' === get_post_type( $id );
			}
		)
	);
}

/**
 * Vytvoří unikátní slug z názvu galerie.
 *
 * @param string $title    Název zadaný redaktorem.
 * @param array  $existing Už existující slugy.
 * @return string
 */
function cg_make_slug( $title, $existing = array() ) {
	$base = sanitize_key( sanitize_title( $title ) );

	if ( '' === $base ) {
		$base = 'galerie';
	}

	$slug = $base;
	$i    = 2;

	while ( isset( $existing[ $slug ] ) ) {
		$slug = $base . '-' . $i;
		++$i;
	}

	return $slug;
}
