<?php
/** Data odznaků: hotové ikony, čištění nastavení, sestavení odznaků z databáze a výběr pro produkt. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hotové ikony: klíč => popisek, barva, výchozí roh, SVG tvary.
 * Ikony jsou čárové (24x24) a kreslí se barvou textu odznaku.
 */
function epxb_presets(): array {
	return array(
		'eco'       => array(
			'label'    => 'Ekologický výrobek',
			'color'    => '#4a8f3f',
			'position' => 'top-right',
			'svg'      => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>',
		),
		'bulb'      => array(
			'label'    => 'Vyměnitelná žárovka',
			'color'    => '#d9951a',
			'position' => 'top-right',
			'svg'      => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
		),
		'engraving' => array(
			'label'    => 'Vlastní gravírování',
			'color'    => '#8a5a2b',
			'position' => 'top-right',
			'svg'      => '<path d="M12 20h9"/><path d="M16.4 3.6a2.1 2.1 0 0 1 3 3L7.4 18.6a2 2 0 0 1-.9.5l-2.9.8a.5.5 0 0 1-.6-.6l.8-2.9a2 2 0 0 1 .5-.9z"/>',
		),
		'handmade'  => array(
			'label'    => 'Ruční výroba',
			'color'    => '#b5533c',
			'position' => 'top-left',
			'svg'      => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.8 0-3 .5-4.5 2-1.5-1.5-2.7-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/>',
		),
		'warranty'  => array(
			'label'    => 'Záruka',
			'color'    => '#2f6f9f',
			'position' => 'top-left',
			'svg'      => '<path d="M20 13c0 5-3.5 7.5-7.7 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.5 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
		),
		'shipping'  => array(
			'label'    => 'Expedice do 72 hodin',
			'color'    => '#3d7a5f',
			'position' => 'top-left',
			'svg'      => '<path d="M11 21.7a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7z"/><path d="M12 22V12"/><path d="m3.3 7 7.7 4.7a2 2 0 0 0 2 0L20.7 7"/><path d="m7.5 4.3 9 5.2"/>',
		),
		'gift'      => array(
			'label'    => 'Super dárek',
			'color'    => '#d04a4a',
			'position' => 'top-left',
			'svg'      => '<path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
		),
		'bestseller' => array(
			'label'    => 'Nejprodávanější',
			'color'    => '#dd9933',
			'position' => 'top-left',
			'svg'      => '<path d="M11.5 2.3a.5.5 0 0 1 1 0l2.3 4.7a2.1 2.1 0 0 0 1.6 1.2l5.2.7a.5.5 0 0 1 .3.9l-3.7 3.6a2.1 2.1 0 0 0-.6 1.9l.9 5.1a.5.5 0 0 1-.8.6l-4.6-2.4a2.1 2.1 0 0 0-2 0L6.4 21a.5.5 0 0 1-.8-.6l.9-5.1a2.1 2.1 0 0 0-.6-1.9L2.2 9.8a.5.5 0 0 1 .3-.9l5.2-.7a2.1 2.1 0 0 0 1.6-1.2z"/>',
		),
	);
}

function epxb_icon_svg( string $key ): string {
	$presets = epxb_presets();
	if ( ! isset( $presets[ $key ] ) ) {
		return '';
	}
	return '<svg class="epxb-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $presets[ $key ]['svg'] . '</svg>';
}

function epxb_defaults(): array {
	return array(
		'type'           => 'icon',
		'icon'           => 'eco',
		'text'           => '',
		'bg_color'       => '#4a8f3f',
		'text_color'     => '#ffffff',
		'position'       => 'top-right',
		'size'           => 'medium',
		'shape'          => 'circle',
		'show_on_loop'   => 'yes',
		'show_on_single' => 'yes',
	);
}

/** Převede libovolná data na platné nastavení. Hodnoty končí v CSS a HTML, takže se nikdy nepoužijí bez kontroly. */
function epxb_clean_settings( $raw ): array {
	$raw      = is_array( $raw ) ? $raw : array();
	$defaults = epxb_defaults();
	$choice   = function ( string $key, array $allowed ) use ( $raw, $defaults ) {
		$value = $raw[ $key ] ?? $defaults[ $key ];
		return in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
	};

	return array(
		'type'           => $choice( 'type', array( 'icon', 'text' ) ),
		'icon'           => $choice( 'icon', array_keys( epxb_presets() ) ),
		'text'           => mb_substr( sanitize_text_field( $raw['text'] ?? '' ), 0, 60 ),
		'bg_color'       => sanitize_hex_color( $raw['bg_color'] ?? '' ) ?: $defaults['bg_color'],
		'text_color'     => sanitize_hex_color( $raw['text_color'] ?? '' ) ?: $defaults['text_color'],
		'position'       => $choice( 'position', array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' ) ),
		'size'           => $choice( 'size', array( 'small', 'medium', 'large' ) ),
		'shape'          => $choice( 'shape', array( 'rectangle', 'rounded', 'circle' ) ),
		'show_on_loop'   => ( $raw['show_on_loop'] ?? '' ) === 'yes' ? 'yes' : 'no',
		'show_on_single' => ( $raw['show_on_single'] ?? '' ) === 'yes' ? 'yes' : 'no',
	);
}

/** HTML jednoho odznaku. Vstup je vyčištěný, hodnoty se i tak escapují. */
function epxb_badge_html( array $badge ): string {
	$s       = $badge['settings'];
	$classes = sprintf( 'epxb-badge epxb-badge-%d epxb-%s epxb-%s epxb-type-%s', $badge['id'], $s['size'], $s['shape'], $s['type'] );
	$style   = sprintf( '--epxb-bg:%s;--epxb-fg:%s', $s['bg_color'], $s['text_color'] );

	if ( 'icon' === $s['type'] ) {
		// Popisek se při najetí myší, zaměření nebo dotyku vysune vedle ikony (tabindex kvůli dotyku a klávesnici).
		$label = '' === $s['text'] ? '' : '<span class="epxb-label">' . esc_html( $s['text'] ) . '</span>';
		return sprintf(
			'<span class="%s" style="%s" tabindex="0" role="img" aria-label="%s">%s%s</span>',
			esc_attr( $classes ),
			esc_attr( $style ),
			esc_attr( $s['text'] ),
			epxb_icon_svg( $s['icon'] ),
			$label
		);
	}
	return sprintf( '<span class="%s" style="%s">%s</span>', esc_attr( $classes ), esc_attr( $style ), esc_html( $s['text'] ) );
}

/**
 * Všechny zveřejněné odznaky s připravenými množinami produktů a kategorií a hotovým HTML.
 * Výsledek je v transientu, takže výpis produktů nedělá žádné dotazy na odznaky.
 */
function epxb_get_badges(): array {
	$cached = get_transient( 'epxb_badges' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$badges = array();
	$posts  = get_posts(
		array(
			'post_type'   => 'epx-badge',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => 'ID',
			'order'       => 'ASC',
		)
	);
	foreach ( $posts as $post ) {
		$products   = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_assigned_products', true ) ) );
		$categories = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_assigned_categories', true ) ) );
		// Odznak u nadřazené kategorie platí i pro podkategorie.
		foreach ( $categories as $term_id ) {
			$children = get_term_children( $term_id, 'product_cat' );
			if ( is_array( $children ) ) {
				$categories = array_merge( $categories, $children );
			}
		}

		$badge         = array(
			'id'         => $post->ID,
			'settings'   => epxb_clean_settings( get_post_meta( $post->ID, '_badge_settings', true ) ),
			'products'   => array_flip( $products ),
			'categories' => array_flip( $categories ),
		);
		$badge['html'] = epxb_badge_html( $badge );
		$badges[]      = $badge;
	}

	set_transient( 'epxb_badges', $badges, DAY_IN_SECONDS );
	return $badges;
}

function epxb_flush_cache() {
	delete_transient( 'epxb_badges' );
}
add_action( 'transition_post_status', 'epxb_flush_cache' );
add_action( 'deleted_post', 'epxb_flush_cache' );
add_action( 'created_product_cat', 'epxb_flush_cache' );
add_action( 'edited_product_cat', 'epxb_flush_cache' );
add_action( 'delete_product_cat', 'epxb_flush_cache' );

/** Odznaky pro produkt. $where je 'loop' nebo 'single'. U varianty se bere nadřazený produkt. */
function epxb_badges_for_product( WC_Product $product, string $where ): array {
	$id         = $product->get_parent_id() ?: $product->get_id();
	$categories = array_flip( wc_get_product_term_ids( $id, 'product_cat' ) );

	$found = array();
	foreach ( epxb_get_badges() as $badge ) {
		if ( 'yes' !== $badge['settings'][ 'show_on_' . $where ] ) {
			continue;
		}
		if ( isset( $badge['products'][ $id ] ) || array_intersect_key( $badge['categories'], $categories ) ) {
			$found[] = $badge;
		}
	}
	return $found;
}

/** Odznaky seskupené podle rohu, aby se ve stejném rohu řadily pod sebe a nepřekrývaly. */
function epxb_corners_html( array $badges ): string {
	$corners = array();
	foreach ( $badges as $badge ) {
		$corners[ $badge['settings']['position'] ][] = $badge['html'];
	}

	$html = '';
	foreach ( $corners as $position => $items ) {
		$html .= '<div class="epxb-corner epxb-' . esc_attr( $position ) . '">' . implode( '', $items ) . '</div>';
	}
	return $html;
}
