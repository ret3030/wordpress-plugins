<?php
/**
 * Plugin Name:       Epoxis gravírování
 * Description:       Volitelné gravírování u vybraných produktů: zákazník zadá text a styl písma, cena se připočte k položce.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       epoxis-gravirovani
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPX_ENGR_VERSION', '1.2.0' );
define( 'EPX_ENGR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Nabízené styly. Klíč se ukládá do košíku a určuje CSS třídu náhledu,
 * do objednávky jde název stylu i písma, aby dílna věděla, čím gravírovat.
 *
 * @return array<string, array{label: string, font: string}>
 */
function epx_engr_styles() {
	return array(
		'psaci' => array(
			'label' => __( 'Psací jemný', 'epoxis-gravirovani' ),
			'font'  => 'Halimum',
		),
		'pevny' => array(
			'label' => __( 'Tvrdší', 'epoxis-gravirovani' ),
			'font'  => 'Lora',
		),
	);
}

function epx_engr_price() {
	return (float) get_option( 'epx_engr_price', 290 );
}

function epx_engr_max_length() {
	return max( 1, (int) get_option( 'epx_engr_max_length', 30 ) );
}

/**
 * Volba u produktu: "" podle kategorie, "yes" vždy nabízet, "never" nikdy (výjimka z kategorie).
 * Starší hodnota "no" znamená totéž co "".
 *
 * @return array<string, string>
 */
function epx_engr_product_modes() {
	return array(
		''      => __( 'Podle kategorie', 'epoxis-gravirovani' ),
		'yes'   => __( 'Vždy nabízet', 'epoxis-gravirovani' ),
		'never' => __( 'Nenabízet (výjimka z kategorie)', 'epoxis-gravirovani' ),
	);
}

/**
 * Gravírování se nabízí u lamp z vybraných kategorií, pokud je produkt nevyřadí,
 * a navíc u produktů s volbou „Vždy nabízet“.
 *
 * @param WC_Product|null $product Produkt (u varianty se rozhoduje podle nadřazeného produktu).
 * @return bool
 */
function epx_engr_enabled( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$mode       = get_post_meta( $product_id, '_epx_engraving', true );
	if ( 'yes' === $mode || 'never' === $mode ) {
		return 'yes' === $mode;
	}

	$categories = array_map( 'absint', (array) get_option( 'epx_engr_categories', array() ) );

	return $categories && has_term( $categories, 'product_cat', $product_id );
}

/**
 * Přečte gravírování z odeslaného formuláře produktu.
 *
 * @return array{text: string, style: string}|null Null, když zákazník gravírování nezaškrtl.
 */
function epx_engr_from_request() {
	// phpcs:disable WordPress.Security.NonceVerification -- formulář košíku WooCommerce nonce nepoužívá.
	if ( empty( $_POST['epx_engr'] ) ) {
		return null;
	}

	return array(
		'text'  => isset( $_POST['epx_engr_text'] ) ? sanitize_text_field( wp_unslash( $_POST['epx_engr_text'] ) ) : '',
		'style' => isset( $_POST['epx_engr_style'] ) ? sanitize_key( wp_unslash( $_POST['epx_engr_style'] ) ) : '',
	);
	// phpcs:enable
}

/* ================= Administrace ================= */

add_filter(
	'woocommerce_get_sections_products',
	function ( $sections ) {
		$sections['epx_engraving'] = __( 'Gravírování', 'epoxis-gravirovani' );
		return $sections;
	}
);

add_filter(
	'woocommerce_get_settings_products',
	function ( $settings, $section ) {
		if ( 'epx_engraving' !== $section ) {
			return $settings;
		}

		$categories = array();
		foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $term ) {
			$categories[ $term->term_id ] = $term->name;
		}

		return array(
			array(
				'type'  => 'title',
				'id'    => 'epx_engr_options',
				'title' => __( 'Gravírování', 'epoxis-gravirovani' ),
				'desc'  => __( 'Gravírování se nabízí u všech lamp z vybraných kategorií. U jednotlivé lampy to změníte v Produkty → Upravit produkt → Obecné → Gravírování: „Vždy nabízet“ ji přidá, „Nenabízet“ ji vyřadí.', 'epoxis-gravirovani' ),
			),
			array(
				'type'    => 'multiselect',
				'id'      => 'epx_engr_categories',
				'title'   => __( 'Kategorie s gravírováním', 'epoxis-gravirovani' ),
				'class'   => 'wc-enhanced-select',
				'options' => $categories,
				'default' => array(),
			),
			array(
				'type'              => 'number',
				'id'                => 'epx_engr_price',
				/* translators: %s: symbol měny */
				'title'             => sprintf( __( 'Cena (%s)', 'epoxis-gravirovani' ), get_woocommerce_currency_symbol() ),
				'default'           => '290',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
			),
			array(
				'type'              => 'number',
				'id'                => 'epx_engr_max_length',
				'title'             => __( 'Maximální počet znaků', 'epoxis-gravirovani' ),
				'default'           => '30',
				'custom_attributes' => array(
					'min'  => '1',
					'step' => '1',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'epx_engr_options',
			),
		);
	},
	10,
	2
);

add_action(
	'woocommerce_product_options_general_product_data',
	function () {
		echo '<div class="options_group">';
		woocommerce_wp_select(
			array(
				'id'          => '_epx_engraving',
				'label'       => __( 'Gravírování', 'epoxis-gravirovani' ),
				'options'     => epx_engr_product_modes(),
				'desc_tip'    => true,
				'description' => sprintf(
					/* translators: %s: cena gravírování */
					__( 'Gravírování textu za %s. „Podle kategorie“ ho nabídne u lamp z kategorií vybraných v nastavení gravírování, „Nenabízet“ lampu z nich vyřadí.', 'epoxis-gravirovani' ),
					wp_strip_all_tags( wc_price( epx_engr_price() ) )
				),
			)
		);
		echo '</div>';
	}
);

add_action(
	'woocommerce_admin_process_product_object',
	function ( $product ) {
		// Nonce ověřuje WooCommerce před tímto hookem.
		$mode = sanitize_key( wp_unslash( $_POST['_epx_engraving'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$product->update_meta_data( '_epx_engraving', array_key_exists( $mode, epx_engr_product_modes() ) ? $mode : '' );
	}
);

/* ================= Produktová stránka ================= */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_product() || ! epx_engr_enabled( wc_get_product( get_queried_object_id() ) ) ) {
			return;
		}
		wp_enqueue_style( 'epx-engr', EPX_ENGR_URL . 'assets/engraving.css', array(), EPX_ENGR_VERSION );
		wp_enqueue_script( 'epx-engr', EPX_ENGR_URL . 'assets/engraving.js', array(), EPX_ENGR_VERSION, true );
	}
);

add_action(
	'woocommerce_before_add_to_cart_button',
	function () {
		global $product;

		if ( ! epx_engr_enabled( $product ) ) {
			return;
		}

		// Po neúspěšném přidání do košíku zachovat, co zákazník vyplnil; po úspěšném začít načisto.
		$styles  = epx_engr_styles();
		$max     = epx_engr_max_length();
		$posted  = did_action( 'woocommerce_add_to_cart' ) ? null : epx_engr_from_request();
		$text    = $posted ? $posted['text'] : '';
		$current = $posted && isset( $styles[ $posted['style'] ] ) ? $posted['style'] : array_key_first( $styles );
		$sample  = __( 'Vaše věnování', 'epoxis-gravirovani' );
		?>
		<div class="epx-engr">
			<label class="epx-engr__toggle">
				<input type="checkbox" name="epx_engr" value="1" <?php checked( null !== $posted ); ?>>
				<span><?php esc_html_e( 'Přidat gravírování', 'epoxis-gravirovani' ); ?></span>
				<span class="epx-engr__price">+<?php echo wp_kses_post( wc_price( epx_engr_price() ) ); ?></span>
			</label>

			<div class="epx-engr__fields">
				<label class="epx-engr__label" for="epx-engr-text"><?php esc_html_e( 'Text gravírování', 'epoxis-gravirovani' ); ?></label>
				<input
					type="text"
					id="epx-engr-text"
					class="epx-engr__input"
					name="epx_engr_text"
					maxlength="<?php echo esc_attr( $max ); ?>"
					value="<?php echo esc_attr( $text ); ?>"
					placeholder="<?php esc_attr_e( 'Např. Pro Aničku, 24. 12. 2026', 'epoxis-gravirovani' ); ?>"
					autocomplete="off">
				<p class="epx-engr__count"><span data-epx-count><?php echo esc_html( mb_strlen( $text ) ); ?></span> / <?php echo esc_html( $max ); ?></p>

				<fieldset class="epx-engr__styles">
					<legend class="epx-engr__label"><?php esc_html_e( 'Styl písma', 'epoxis-gravirovani' ); ?></legend>
					<?php foreach ( $styles as $key => $style ) : ?>
						<label class="epx-engr__style">
							<input type="radio" name="epx_engr_style" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current, $key ); ?>>
							<span class="epx-engr__sample epx-engr__sample--<?php echo esc_attr( $key ); ?>" data-epx-sample data-placeholder="<?php echo esc_attr( $sample ); ?>"><?php echo esc_html( '' !== $text ? $text : $sample ); ?></span>
							<span class="epx-engr__style-name"><?php echo esc_html( $style['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</div>
		</div>
		<?php
	}
);

/* ================= Košík ================= */

add_filter(
	'woocommerce_add_to_cart_validation',
	function ( $passed, $product_id ) {
		$engraving = epx_engr_from_request();

		if ( ! $engraving || ! epx_engr_enabled( wc_get_product( $product_id ) ) ) {
			return $passed;
		}

		if ( '' === $engraving['text'] ) {
			wc_add_notice( __( 'Vyplňte text gravírování, nebo gravírování zrušte.', 'epoxis-gravirovani' ), 'error' );
			return false;
		}

		if ( mb_strlen( $engraving['text'] ) > epx_engr_max_length() ) {
			/* translators: %d: maximální počet znaků */
			wc_add_notice( sprintf( __( 'Text gravírování může mít nejvýše %d znaků.', 'epoxis-gravirovani' ), epx_engr_max_length() ), 'error' );
			return false;
		}

		if ( ! isset( epx_engr_styles()[ $engraving['style'] ] ) ) {
			wc_add_notice( __( 'Vyberte styl gravírování.', 'epoxis-gravirovani' ), 'error' );
			return false;
		}

		return $passed;
	},
	10,
	2
);

add_filter(
	'woocommerce_add_cart_item_data',
	function ( $data, $product_id ) {
		$engraving = epx_engr_from_request();

		// Jiný text = jiná data = samostatný řádek v košíku.
		if ( $engraving && epx_engr_enabled( wc_get_product( $product_id ) ) ) {
			$data['epx_engraving'] = $engraving;
		}

		return $data;
	},
	10,
	2
);

/**
 * Přičte cenu gravírování k produktu v košíku.
 *
 * Běží při vložení do košíku a při každém načtení košíku ze session, tedy
 * jednou na nový objekt produktu. woocommerce_before_calculate_totals se
 * volá opakovaně a cenu by přičetl víckrát.
 *
 * @param array $item Položka košíku.
 * @return array
 */
function epx_engr_apply_price( $item ) {
	if ( isset( $item['epx_engraving'] ) ) {
		$item['data']->set_price( (float) $item['data']->get_price( 'edit' ) + epx_engr_price() );
	}
	return $item;
}
add_filter( 'woocommerce_add_cart_item', 'epx_engr_apply_price' );
add_filter( 'woocommerce_get_cart_item_from_session', 'epx_engr_apply_price' );

add_filter(
	'woocommerce_get_item_data',
	function ( $rows, $item ) {
		if ( empty( $item['epx_engraving'] ) ) {
			return $rows;
		}

		$styles = epx_engr_styles();
		$style  = $item['epx_engraving']['style'];

		$rows[] = array(
			'key'   => __( 'Gravírování', 'epoxis-gravirovani' ),
			'value' => esc_html( $item['epx_engraving']['text'] ),
		);
		$rows[] = array(
			'key'   => __( 'Styl gravírování', 'epoxis-gravirovani' ),
			'value' => esc_html( isset( $styles[ $style ] ) ? $styles[ $style ]['label'] : $style ),
		);

		return $rows;
	},
	10,
	2
);

/* ================= Objednávka ================= */

add_action(
	'woocommerce_checkout_create_order_line_item',
	function ( $item, $cart_item_key, $values ) {
		if ( empty( $values['epx_engraving'] ) ) {
			return;
		}

		$styles = epx_engr_styles();
		$style  = $values['epx_engraving']['style'];
		$label  = isset( $styles[ $style ] ) ? sprintf( '%s (%s)', $styles[ $style ]['label'], $styles[ $style ]['font'] ) : $style;

		// Čitelné klíče: zobrazí se v detailu objednávky, e-mailech i na faktuře.
		$item->add_meta_data( __( 'Gravírování', 'epoxis-gravirovani' ), $values['epx_engraving']['text'] );
		$item->add_meta_data( __( 'Styl gravírování', 'epoxis-gravirovani' ), $label );
	},
	10,
	3
);
