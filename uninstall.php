<?php
/**
 * Úklid při smazání pluginu: záznamy u příloh, příznak dokončení a případná záloha.
 * Smazání pluginu bez „Vrátit vše“ znamená ponechat optimalizované fotky.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_post_meta_by_key( '_velrion_bulk' );
delete_option( 'velrion_bulk_finished' );

$dir = WP_CONTENT_DIR . '/velrion-images-backup';
if ( is_dir( $dir ) ) {
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) {
		$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
	}
	@rmdir( $dir );
}
