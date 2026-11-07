<?php
/**
 * Plugin Name:       Velrion DPD Pickup
 * Description:       Doprava DPD Pickup: zákazník si v pokladně vybere výdejní místo nebo box DPD (včetně AlzaBoxů) na mapě DPD.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       velrion-dpd-pickup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VELRION_DPD_METHOD = 'velrion_dpd_pickup';
const VELRION_DPD_VERSION = '1.1.0';
const VELRION_DPD_WIDGET = 'https://api.dpd.cz/widget/latest/index.html';
/** Jazyky, které widget DPD umí; ostatní dostanou angličtinu. */
const VELRION_DPD_LANGS = array( 'bg', 'cs', 'de', 'en', 'es', 'el', 'et', 'fr', 'hr', 'hu', 'it', 'lt', 'lv', 'nl', 'pl', 'pt', 'ro', 'sk', 'sl', 'sv' );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/* ------------------------------------------------------------------ */
/* Metoda dopravy                                                      */
/* ------------------------------------------------------------------ */

add_action(
	'woocommerce_shipping_init',
	function () {
		class Velrion_DPD_Pickup_Method extends WC_Shipping_Method {
			public function __construct( $instance_id = 0 ) {
				$this->id                 = VELRION_DPD_METHOD;
				$this->instance_id        = absint( $instance_id );
				$this->method_title       = 'DPD Pickup';
				$this->method_description = 'Doručení do výdejního místa nebo boxu DPD, včetně AlzaBoxů. Zákazník vybírá místo na mapě DPD.';
				$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );
				$this->instance_form_fields = array(
					'title' => array(
						'title'   => 'Název',
						'type'    => 'text',
						'default' => 'DPD Pickup (AlzaBox, box nebo výdejní místo DPD)',
					),
					'cost'      => array(
						'title'   => 'Cena',
						'type'    => 'price',
						'default' => '79',
					),
					'hide_zbox' => array(
						'title'   => 'Z-BOXy',
						'type'    => 'checkbox',
						'label'   => 'Skrýt Z-BOXy v mapě (nabízí je už doprava Zásilkovnou)',
						'default' => 'yes',
					),
				);
				$this->title = $this->get_option( 'title' );

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function calculate_shipping( $package = array() ) {
				$this->add_rate(
					array(
						'label'   => $this->title,
						'cost'    => (float) $this->get_option( 'cost' ),
						'package' => $package,
					)
				);
			}
		}
	}
);

add_filter(
	'woocommerce_shipping_methods',
	function ( $methods ) {
		$methods[ VELRION_DPD_METHOD ] = 'Velrion_DPD_Pickup_Method';
		return $methods;
	}
);

/** Je mezi zvolenými způsoby dopravy DPD Pickup? Hodnoty mají tvar "velrion_dpd_pickup:12". */
function velrion_dpd_is_chosen( array $shipping_methods ): bool {
	foreach ( $shipping_methods as $method ) {
		if ( 0 === strpos( (string) $method, VELRION_DPD_METHOD . ':' ) ) {
			return true;
		}
	}
	return false;
}

/* ------------------------------------------------------------------ */
/* Pokladna                                                            */
/* ------------------------------------------------------------------ */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$url  = plugin_dir_url( __FILE__ );
		$lang = substr( get_locale(), 0, 2 );

		wp_enqueue_style( 'velrion-dpd-pickup', $url . 'assets/checkout.css', array(), VELRION_DPD_VERSION );
		wp_enqueue_script( 'velrion-dpd-pickup', $url . 'assets/checkout.js', array( 'jquery' ), VELRION_DPD_VERSION, true );
		wp_localize_script(
			'velrion-dpd-pickup',
			'velrionDpdPickup',
			array(
				'widgetUrl' => VELRION_DPD_WIDGET,
				'lang'      => in_array( $lang, VELRION_DPD_LANGS, true ) ? $lang : 'en',
				'choose'    => 'Vybrat výdejní místo',
				'change'    => 'Změnit výdejní místo',
				'none'      => 'Výdejní místo zatím není vybrané.',
			)
		);
	}
);

/**
 * Tlačítko pod sazbou DPD Pickup. Tabulka dopravy se při každé změně v pokladně
 * překresluje přes AJAX, proto vybrané místo drží skrytá pole mimo ni.
 */
add_action(
	'woocommerce_after_shipping_rate',
	function ( $rate, $index ) {
		if ( ! is_checkout() || VELRION_DPD_METHOD !== $rate->get_method_id() ) {
			return;
		}
		$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods' ) : array();
		if ( ( $chosen[ $index ] ?? '' ) !== $rate->get_id() ) {
			return;
		}
		// Hodnota pro nezdokumentovaný, ale funkční parametr widgetu excludeLockerProvider.
		$method  = WC_Shipping_Zones::get_shipping_method( $rate->get_instance_id() );
		$exclude = $method && 'yes' === $method->get_option( 'hide_zbox', 'yes' ) ? 'ZBox' : '';
		printf(
			'<div class="velrion-dpd-pickup"><span class="velrion-dpd-selected"></span><button type="button" class="button velrion-dpd-choose" data-exclude="%s"></button></div>',
			esc_attr( $exclude )
		);
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_after_customer_details',
	function () {
		echo '<input type="hidden" id="velrion_dpd_point_id" name="velrion_dpd_point_id" value="">';
		echo '<input type="hidden" id="velrion_dpd_point_label" name="velrion_dpd_point_label" value="">';
	}
);

add_action(
	'woocommerce_after_checkout_validation',
	function ( $data, $errors ) {
		// Nonce pokladny ověřuje WooCommerce před tímto hookem.
		if ( velrion_dpd_is_chosen( (array) ( $data['shipping_method'] ?? array() ) ) && empty( $_POST['velrion_dpd_point_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$errors->add( 'shipping', 'Vyberte prosím výdejní místo DPD.' );
		}
	},
	10,
	2
);

add_action(
	'woocommerce_checkout_create_order',
	function ( $order, $data ) {
		if ( ! velrion_dpd_is_chosen( (array) ( $data['shipping_method'] ?? array() ) ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$order->update_meta_data( '_velrion_dpd_point_id', sanitize_text_field( wp_unslash( $_POST['velrion_dpd_point_id'] ?? '' ) ) );
		$order->update_meta_data( '_velrion_dpd_point', sanitize_text_field( wp_unslash( $_POST['velrion_dpd_point_label'] ?? '' ) ) );
		// phpcs:enable
	},
	10,
	2
);

/* ------------------------------------------------------------------ */
/* Zobrazení v objednávce                                              */
/* ------------------------------------------------------------------ */

/** Výdejní místo pod řádkem Doprava: v e-mailech, na děkovací stránce i v účtu zákazníka. */
add_filter(
	'woocommerce_get_order_item_totals',
	function ( $rows, $order ) {
		$point = $order->get_meta( '_velrion_dpd_point' );
		if ( $point && isset( $rows['shipping'] ) ) {
			$rows['shipping']['value'] .= '<br><small>Výdejní místo: ' . esc_html( $point ) . '</small>';
		}
		return $rows;
	},
	10,
	2
);

add_action(
	'woocommerce_admin_order_data_after_shipping_address',
	function ( $order ) {
		$point = $order->get_meta( '_velrion_dpd_point' );
		if ( $point ) {
			printf(
				'<p><strong>DPD Pickup:</strong><br>%s<br>ID místa: <code>%s</code></p>',
				esc_html( $point ),
				esc_html( $order->get_meta( '_velrion_dpd_point_id' ) )
			);
		}
	}
);
