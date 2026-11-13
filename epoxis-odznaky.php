<?php
/**
 * Plugin Name:       Epoxis odznaky
 * Description:       Odznaky na produktech WooCommerce: text nebo hotové ikony (ekologický výrobek, vyměnitelná žárovka, gravírování) podle kategorií nebo konkrétních produktů.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       epoxis-odznaky
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPXB_VERSION', '2.0.0' );
define( 'EPXB_URL', plugin_dir_url( __FILE__ ) );

require __DIR__ . '/includes/badges.php';
require __DIR__ . '/includes/frontend.php';

if ( is_admin() ) {
	require __DIR__ . '/includes/admin.php';
}
