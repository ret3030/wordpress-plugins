<?php
/**
 * Plugin Name:       Velrion Images Bulk
 * Description:       Jednorázová optimalizace stávající knihovny médií se zálohou a možností návratu. Po potvrzení a úklidu plugin smažte.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       velrion-images-bulk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Záznam u přílohy: původní metadata, zálohované soubory a ušetřené bajty. */
const VELRION_BULK_META  = '_velrion_bulk';
const VELRION_BULK_BATCH = 5;
const VELRION_BULK_MIMES = array( 'image/jpeg', 'image/png', 'image/webp' );
/** Nastaví se po úklidu: záznamy jsou pryč, fotky už se nesmí optimalizovat podruhé. */
const VELRION_BULK_FINISHED = 'velrion_bulk_finished';

/** Záloha mimo uploads: není veřejně dostupná a nedostane se do záloh médií. */
function velrion_bulk_backup_dir(): string {
	return WP_CONTENT_DIR . '/velrion-images-backup';
}

function velrion_bulk_uploads_dir(): string {
	return wp_get_upload_dir()['basedir'];
}

/** Přesune soubor z uploads do zálohy (se stejnou relativní cestou) a vrátí tu cestu. */
function velrion_bulk_stash( string $path ): ?string {
	$relative = ltrim( substr( $path, strlen( velrion_bulk_uploads_dir() ) ), '/' );
	$target   = velrion_bulk_backup_dir() . '/' . $relative;
	if ( ! wp_mkdir_p( dirname( $target ) ) || ! @rename( $path, $target ) ) {
		return null;
	}
	return $relative;
}

/**
 * Překóduje soubor do dočasného souboru vedle něj: stejný formát, volitelně zmenšení.
 * Vrátí cestu a rozměry, nebo null, když by výsledek nebyl menší.
 *
 * @return array{tmp:string,width:int,height:int}|null
 */
function velrion_bulk_recompress( string $path, int $max ): ?array {
	$type = wp_check_filetype( $path )['type'];
	if ( ! in_array( $type, VELRION_BULK_MIMES, true ) ) {
		return null;
	}
	$editor = wp_get_image_editor( $path );
	if ( is_wp_error( $editor ) ) {
		return null;
	}

	// Překódováním zmizí EXIF i s údajem o natočení, proto natočení nejdřív zapéct do pixelů.
	$editor->maybe_exif_rotate();
	$size    = $editor->get_size();
	$resized = $max && ( $size['width'] > $max || $size['height'] > $max );
	if ( $resized ) {
		$editor->resize( $max, $max, false );
	} elseif ( 'image/jpeg' !== $type ) {
		return null; // PNG a WebP bez zmenšení opakovaná komprese nezmenší.
	}
	$editor->set_quality( (int) get_option( 'velrion_images_quality', 82 ) );

	// Formát musí zůstat stejný i při aktivním převodu zmenšenin na WebP (Velrion Images).
	$keep_format = fn() => array();
	add_filter( 'image_editor_output_format', $keep_format, PHP_INT_MAX );
	$saved = $editor->save( $path . '.velrion-tmp', $type );
	remove_filter( 'image_editor_output_format', $keep_format, PHP_INT_MAX );

	if ( is_wp_error( $saved ) ) {
		return null;
	}
	if ( ! $resized && filesize( $saved['path'] ) >= filesize( $path ) ) {
		@unlink( $saved['path'] );
		return null;
	}
	return array(
		'tmp'    => $saved['path'],
		'width'  => (int) $saved['width'],
		'height' => (int) $saved['height'],
	);
}

/* ------------------------------------------------------------------ */
/* Tři kroky: optimalizace, návrat, úklid                             */
/* ------------------------------------------------------------------ */

function velrion_bulk_optimize( int $id ): void {
	$file   = get_attached_file( $id );
	$meta   = wp_get_attachment_metadata( $id );
	$record = array(
		'meta'  => $meta,
		'files' => array(),
		'saved' => 0,
	);
	if ( ! $file || ! is_file( $file ) || ! is_array( $meta ) ) {
		update_post_meta( $id, VELRION_BULK_META, $record );
		return;
	}
	$dir   = dirname( $file );
	$paths = array( $file );

	$stash = function ( string $path ) use ( &$record ) {
		$size     = filesize( $path );
		$relative = velrion_bulk_stash( $path );
		if ( $relative ) {
			$record['files'][] = $relative;
			return $size;
		}
		return 0;
	};
	$replace = function ( string $path, array $result ) use ( &$record ) {
		$before   = filesize( $path );
		$relative = velrion_bulk_stash( $path );
		if ( ! $relative ) {
			@unlink( $result['tmp'] );
			return false;
		}
		if ( ! @rename( $result['tmp'], $path ) ) {
			@rename( velrion_bulk_backup_dir() . '/' . $relative, $path ); // originál hned zpět
			@unlink( $result['tmp'] );
			return false;
		}
		$record['files'][] = $relative;
		$record['saved']  += $before - filesize( $path );
		return true;
	};

	// 1. Originál v plné velikosti vedle „-scaled“, web ho nezobrazuje.
	if ( ! empty( $meta['original_image'] ) ) {
		$original = $dir . '/' . $meta['original_image'];
		if ( $original !== $file && is_file( $original ) ) {
			$paths[]          = $original;
			$record['saved'] += $stash( $original );
		}
		unset( $meta['original_image'] );
	}

	// 2. Hlavní soubor: zmenšit na limit a překódovat, název i formát zůstávají.
	$result = velrion_bulk_recompress( $file, (int) get_option( 'velrion_images_max', 2400 ) );
	if ( $result && $replace( $file, $result ) ) {
		$meta['width']    = $result['width'];
		$meta['height']   = $result['height'];
		$meta['filesize'] = filesize( $file );
	}

	// 3. Zmenšeniny: jen překódovat.
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $name => $size ) {
		$path    = $dir . '/' . $size['file'];
		$paths[] = $path;
		$result  = is_file( $path ) ? velrion_bulk_recompress( $path, 0 ) : null;
		if ( $result && $replace( $path, $result ) ) {
			$meta['sizes'][ $name ]['filesize'] = filesize( $path );
		}
	}

	// 4. Kopie „foto.jpg.webp“ po dřívějších optimalizačních pluginech.
	foreach ( $paths as $path ) {
		if ( is_file( $path . '.webp' ) ) {
			$record['saved'] += $stash( $path . '.webp' );
		}
	}

	wp_update_attachment_metadata( $id, $meta );
	update_post_meta( $id, VELRION_BULK_META, $record );
}

/** Vrátí zálohované soubory na původní místo a obnoví původní metadata. */
function velrion_bulk_restore( int $id ): void {
	$record  = get_post_meta( $id, VELRION_BULK_META, true );
	$uploads = velrion_bulk_uploads_dir();
	foreach ( (array) ( $record['files'] ?? array() ) as $relative ) {
		$backup = velrion_bulk_backup_dir() . '/' . $relative;
		if ( is_file( $backup ) ) {
			@rename( $backup, $uploads . '/' . $relative ); // přepíše optimalizovanou verzi
		}
	}
	if ( is_array( $record['meta'] ?? null ) ) {
		wp_update_attachment_metadata( $id, $record['meta'] );
	}
	delete_post_meta( $id, VELRION_BULK_META );
}

/** Potvrzení: smaže zálohu přílohy i záznam, optimalizace zůstává. */
function velrion_bulk_cleanup( int $id ): void {
	$record = get_post_meta( $id, VELRION_BULK_META, true );
	foreach ( (array) ( $record['files'] ?? array() ) as $relative ) {
		@unlink( velrion_bulk_backup_dir() . '/' . $relative );
	}
	delete_post_meta( $id, VELRION_BULK_META );
}

/** Smaže prázdnou strukturu složek zálohy (soubory už smazal úklid). */
function velrion_bulk_remove_backup_dir(): void {
	$dir = velrion_bulk_backup_dir();
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) {
		$item->isDir() ? @rmdir( $item->getPathname() ) : @unlink( $item->getPathname() );
	}
	@rmdir( $dir );
}

/* ------------------------------------------------------------------ */
/* Dávky přes AJAX                                                     */
/* ------------------------------------------------------------------ */

/** @param bool $processed true = přílohy se záznamem (pro návrat a úklid), false = čekající na optimalizaci. */
function velrion_bulk_query( bool $processed, int $limit ): WP_Query {
	return new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => VELRION_BULK_MIMES,
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'no_found_rows'  => false,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => VELRION_BULK_META,
					'compare' => $processed ? 'EXISTS' : 'NOT EXISTS',
				),
			),
		)
	);
}

add_action(
	'wp_ajax_velrion_bulk',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Nemáte oprávnění.', 403 );
		}
		check_ajax_referer( 'velrion_bulk' );
		@set_time_limit( 120 );

		$step = sanitize_key( $_POST['step'] ?? '' );
		if ( 'optimize' === $step && get_option( VELRION_BULK_FINISHED ) ) {
			wp_send_json_error( 'Optimalizace už proběhla a byla potvrzena.' );
		}
		$run  = array(
			'optimize' => 'velrion_bulk_optimize',
			'restore'  => 'velrion_bulk_restore',
			'cleanup'  => 'velrion_bulk_cleanup',
		)[ $step ] ?? null;
		if ( ! $run ) {
			wp_send_json_error( 'Neznámý krok.' );
		}

		foreach ( velrion_bulk_query( 'optimize' !== $step, VELRION_BULK_BATCH )->posts as $id ) {
			$run( (int) $id );
		}

		$remaining = velrion_bulk_query( 'optimize' !== $step, 1 )->found_posts;
		if ( 0 === $remaining && 'optimize' !== $step ) {
			velrion_bulk_remove_backup_dir();
			if ( 'cleanup' === $step ) {
				update_option( VELRION_BULK_FINISHED, time(), false );
			}
		}
		wp_send_json_success( array( 'remaining' => $remaining ) );
	}
);

/* ------------------------------------------------------------------ */
/* Stránka Média → Optimalizovat knihovnu                              */
/* ------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		add_media_page( 'Optimalizovat knihovnu', 'Optimalizovat knihovnu', 'manage_options', 'velrion-images-bulk', 'velrion_bulk_render' );
	}
);

function velrion_bulk_render(): void {
	global $wpdb;
	$finished  = get_option( VELRION_BULK_FINISHED );
	$pending   = $finished ? 0 : velrion_bulk_query( false, 1 )->found_posts;
	$processed = velrion_bulk_query( true, 1 )->found_posts;
	$saved     = 0;
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", VELRION_BULK_META ) ) as $value ) {
		$saved += (int) ( maybe_unserialize( $value )['saved'] ?? 0 );
	}
	?>
	<div class="wrap">
		<h1>Optimalizovat knihovnu</h1>
		<?php if ( $finished ) : ?>
			<div class="notice notice-success"><p>Optimalizace byla potvrzena <?php echo esc_html( wp_date( 'j. n. Y H:i', (int) $finished ) ); ?> a záloha smazána. Plugin teď deaktivujte a smažte, při smazání odstraní i tuto poslední poznámku.</p></div>
		<?php endif; ?>
		<p>Jednorázová optimalizace stávajících fotek (JPEG, PNG, WebP). U každé fotky smaže nepoužívaný originál vedle verze „-scaled“, hlavní soubor zmenší na <?php echo (int) get_option( 'velrion_images_max', 2400 ); ?> px a překóduje, překóduje zmenšeniny a smaže kopie „foto.jpg.webp“ po starých optimalizačních pluginech. <strong>Názvy, adresy a formáty souborů zůstávají.</strong></p>
		<p><strong>Nic se nemaže natrvalo:</strong> každý změněný nebo odebraný soubor se přesune do zálohy <code><?php echo esc_html( velrion_bulk_backup_dir() ); ?></code> a původní záznam fotky se uloží. Na disku je proto do úklidu potřeba místo zhruba jako dnes zabírají média.</p>

		<table class="widefat" style="max-width:520px">
			<tr><td>Čeká na optimalizaci</td><td><strong id="vb-pending"><?php echo (int) $pending; ?></strong></td></tr>
			<tr><td>Optimalizováno (se zálohou)</td><td><strong id="vb-processed"><?php echo (int) $processed; ?></strong></td></tr>
			<tr><td>Ušetřeno</td><td><strong><?php echo esc_html( size_format( $saved ) ); ?></strong></td></tr>
		</table>

		<h2>1. Optimalizace</h2>
		<p><button type="button" class="button button-primary" data-step="optimize"<?php disabled( 0, $pending ); ?>>Spustit / pokračovat</button></p>

		<h2>2. Kontrola webu</h2>
		<p>Projděte úvodní stránku, galerii, produkty a košík. Když něco nesedí, vraťte vše zpět:</p>
		<p><button type="button" class="button" data-step="restore" data-confirm="Vrátit všechny fotky a záznamy do stavu před optimalizací?"<?php disabled( 0, $processed ); ?>>Vrátit vše</button></p>

		<h2>3. Potvrzení</h2>
		<p>Když je vše v pořádku, smažte zálohu a záznamy pluginu. <strong>Potom už návrat nejde.</strong> Nakonec plugin deaktivujte a smažte.</p>
		<p><button type="button" class="button button-link-delete" data-step="cleanup" data-confirm="Smazat zálohu? Návrat pak už nebude možný."<?php disabled( 0, $processed ); ?>>Potvrdit a uklidit</button></p>

		<p id="vb-status" class="description">Běží po <?php echo (int) VELRION_BULK_BATCH; ?> fotkách, stránku nechte otevřenou. Přerušený krok jde spustit znovu.</p>
	</div>
	<script>
	( function () {
		const status = document.getElementById( 'vb-status' );
		const buttons = document.querySelectorAll( '[data-step]' );

		async function run( step ) {
			const body = new URLSearchParams( { action: 'velrion_bulk', step, _ajax_nonce: <?php echo wp_json_encode( wp_create_nonce( 'velrion_bulk' ) ); ?> } );
			const response = await fetch( ajaxurl, { method: 'POST', body } );
			const json = await response.json().catch( () => null );
			if ( ! json || ! json.success ) {
				status.textContent = 'Chyba (' + ( json && json.data ? json.data : response.status ) + '), spusťte krok znovu.';
				buttons.forEach( ( b ) => ( b.disabled = false ) );
				return;
			}
			status.textContent = 'Zbývá: ' + json.data.remaining;
			if ( json.data.remaining > 0 ) {
				return run( step );
			}
			status.textContent = 'Hotovo, načítám stav…';
			location.reload();
		}

		buttons.forEach( ( button ) => button.addEventListener( 'click', function () {
			if ( button.dataset.confirm && ! confirm( button.dataset.confirm ) ) {
				return;
			}
			buttons.forEach( ( b ) => ( b.disabled = true ) );
			status.textContent = 'Probíhá…';
			run( button.dataset.step );
		} ) );
	} )();
	</script>
	<?php
}
