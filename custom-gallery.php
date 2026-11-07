<?php
/**
 * Plugin Name:       Redakční galerie pro Elementor
 * Description:       Elementor widget, jehož obsah spravují redaktoři v Média → Galerie (mimo Elementor editor).
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            —
 * Text Domain:       custom-gallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CG_VERSION', '1.0.1' );
define( 'CG_PATH', plugin_dir_path( __FILE__ ) );
define( 'CG_URL', plugin_dir_url( __FILE__ ) );
define( 'CG_OPTION', 'cg_galleries' );

/**
 * Oprávnění se řeší funkcí cg_capability() v includes/functions.php.
 * Konstanta by se vyhodnotila dřív, než se stihne načíst WooCommerce.
 */

require_once CG_PATH . 'includes/functions.php';
require_once CG_PATH . 'includes/class-cg-admin.php';

new CG_Admin();

/**
 * Registrace widgetu do Elementoru.
 */
add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		require_once CG_PATH . 'includes/class-cg-widget.php';
		$widgets_manager->register( new CG_Widget() );
	}
);

/**
 * Styl pro frontend i pro náhled v Elementor editoru.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'cg-gallery', CG_URL . 'assets/frontend.css', array(), CG_VERSION );
	}
);

/**
 * Upozornění, pokud Elementor není aktivní.
 */
add_action(
	'admin_notices',
	function () {
		if ( did_action( 'elementor/loaded' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Redakční galerie potřebuje aktivní plugin Elementor. Obsah galerií můžete spravovat i teď, ale widget se v editoru nezobrazí.', 'custom-gallery' );
		echo '</p></div>';
	}
);
