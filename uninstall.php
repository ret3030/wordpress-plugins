<?php
/**
 * Smaže uložená data při odinstalaci pluginu.
 * Obrázky v knihovně médií zůstávají nedotčené.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cg_galleries' );
