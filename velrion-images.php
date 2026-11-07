<?php
/**
 * Plugin Name:       Velrion Images
 * Description:       Optimalizace fotek při nahrání (zmenšení, převod formátu, bez originálu v plné velikosti, zmenšeniny ve WebP) a hromadná optimalizace stávající knihovny.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       velrion-images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VELRION_IMAGES_DEFAULTS = array(
	'velrion_images_max'     => 2400,
	'velrion_images_quality' => 82,
	// JPEG kvůli katalogu Meta a Google Merchant, které WebP spolehlivě nepřijímají.
	'velrion_images_format'  => 'image/jpeg',
);

/** Formáty, které plugin zpracovává; GIF (animace) a SVG nechává být. */
const VELRION_IMAGES_INPUT = array( 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/avif' );

function velrion_images_option( string $name ) {
	return get_option( $name, VELRION_IMAGES_DEFAULTS[ $name ] );
}

/* ------------------------------------------------------------------ */
/* Plná velikost: zmenšit, převést, originál zahodit                   */
/* ------------------------------------------------------------------ */

add_filter( 'wp_handle_upload', 'velrion_images_optimize_upload' );

/**
 * Běží hned po uložení nahraného souboru, ještě než WordPress vytvoří zmenšeniny,
 * takže ty už vznikají z optimalizované verze.
 *
 * @param array{file:string,url:string,type:string} $upload
 */
function velrion_images_optimize_upload( array $upload ): array {
	if ( ! in_array( $upload['type'], VELRION_IMAGES_INPUT, true ) ) {
		return $upload;
	}

	$editor = wp_get_image_editor( $upload['file'] );
	if ( is_wp_error( $editor ) ) {
		return $upload; // Server formát neumí (např. HEIC bez Imagicku), soubor zůstane, jak je.
	}

	$editor->maybe_exif_rotate();

	$max     = (int) velrion_images_option( 'velrion_images_max' );
	$size    = $editor->get_size();
	$resized = $size['width'] > $max || $size['height'] > $max;
	if ( $resized ) {
		$editor->resize( $max, $max, false );
	}
	$editor->set_quality( (int) velrion_images_option( 'velrion_images_quality' ) );

	// PNG bývá grafika s průhledností, kterou JPEG neumí.
	$mime = 'image/png' === $upload['type'] ? 'image/webp' : velrion_images_option( 'velrion_images_format' );
	$dir  = dirname( $upload['file'] );
	$tmp  = $dir . '/velrion-tmp-' . wp_generate_password( 8, false ) . '.' . ( 'image/webp' === $mime ? 'webp' : 'jpg' );

	$GLOBALS['velrion_images_original'] = true; // Pro plnou velikost platí $mime, ne převod na WebP níže.
	$saved = $editor->save( $tmp, $mime );
	$GLOBALS['velrion_images_original'] = false;

	if ( is_wp_error( $saved ) ) {
		return $upload;
	}

	// Už optimalizovanou fotku stejného formátu nechceme opakovanou kompresí zvětšit ani zhoršit.
	if ( ! $resized && $mime === $upload['type'] && filesize( $tmp ) >= filesize( $upload['file'] ) ) {
		@unlink( $tmp );
		return $upload;
	}

	$name   = pathinfo( $upload['file'], PATHINFO_FILENAME ) . '.' . pathinfo( $tmp, PATHINFO_EXTENSION );
	$target = $dir . '/' . ( $dir . '/' . $name === $upload['file'] ? $name : wp_unique_filename( $dir, $name ) );

	// Přejmenování na stejnou cestu originál atomicky přepíše; při selhání zůstane originál beze změny.
	if ( ! @rename( $tmp, $target ) ) {
		@unlink( $tmp );
		return $upload;
	}
	if ( $target !== $upload['file'] ) {
		@unlink( $upload['file'] );
	}

	return array(
		'file' => $target,
		'url'  => trailingslashit( dirname( $upload['url'] ) ) . basename( $target ),
		'type' => $mime,
	);
}

// Originál už je zmenšený, WordPress nemá vedle něj ukládat další kopii „-scaled“.
add_filter( 'big_image_size_threshold', '__return_false' );

/* ------------------------------------------------------------------ */
/* Zmenšeniny pro web ve WebP                                          */
/* ------------------------------------------------------------------ */

add_filter(
	'image_editor_output_format',
	function ( $formats ) {
		if ( ! empty( $GLOBALS['velrion_images_original'] ) ) {
			return $formats;
		}
		$formats['image/jpeg'] = 'image/webp';
		$formats['image/png']  = 'image/webp';
		return $formats;
	}
);

/* ------------------------------------------------------------------ */
/* Hromadná optimalizace stávající knihovny                            */
/* ------------------------------------------------------------------ */

const VELRION_IMAGES_DONE_META = '_velrion_images_saved';
const VELRION_IMAGES_BATCH     = 5;

/**
 * Překóduje soubor na místě: stejný název, stejný formát, takže všechny odkazy na webu platí dál.
 * Nahradí ho jen tehdy, když je výsledek menší nebo se fotka zmenšovala.
 *
 * @return array{width:int,height:int,saved:int}|null Null, když se soubor neměnil.
 */
function velrion_images_recompress( string $path, int $max ): ?array {
	$type = wp_check_filetype( $path )['type'];
	if ( ! in_array( $type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
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
	$editor->set_quality( (int) velrion_images_option( 'velrion_images_quality' ) );

	$tmp = $path . '.velrion-tmp';
	$GLOBALS['velrion_images_original'] = true; // Ponechat formát, ne převádět na WebP.
	$saved = $editor->save( $tmp, $type );
	$GLOBALS['velrion_images_original'] = false;
	if ( is_wp_error( $saved ) ) {
		return null;
	}

	$before = filesize( $path );
	$after  = filesize( $saved['path'] );
	if ( ( ! $resized && $after >= $before ) || ! @rename( $saved['path'], $path ) ) {
		@unlink( $saved['path'] );
		return null;
	}

	return array(
		'width'  => (int) $saved['width'],
		'height' => (int) $saved['height'],
		'saved'  => $before - $after,
	);
}

/** Zpracuje jednu přílohu a vrátí ušetřené bajty. */
function velrion_images_optimize_attachment( int $id ): int {
	$file = get_attached_file( $id );
	$meta = wp_get_attachment_metadata( $id );
	if ( ! $file || ! is_file( $file ) || ! is_array( $meta ) ) {
		update_post_meta( $id, VELRION_IMAGES_DONE_META, 0 );
		return 0;
	}
	$dir   = dirname( $file );
	$saved = 0;
	$paths = array( $file );

	// 1. Originál v plné velikosti, který WordPress drží vedle „-scaled“ a web ho nezobrazuje.
	if ( ! empty( $meta['original_image'] ) ) {
		$original = $dir . '/' . $meta['original_image'];
		if ( $original !== $file && is_file( $original ) ) {
			$saved += filesize( $original );
			$paths[] = $original; // kvůli osiřelým kopiím .webp níže
			@unlink( $original );
		}
		unset( $meta['original_image'] );
	}

	// 2. Hlavní soubor: zmenšit na limit a překódovat.
	$result = velrion_images_recompress( $file, (int) velrion_images_option( 'velrion_images_max' ) );
	if ( $result ) {
		$saved           += $result['saved'];
		$meta['width']    = $result['width'];
		$meta['height']   = $result['height'];
		$meta['filesize'] = filesize( $file );
	}

	// 3. Zmenšeniny: jen překódovat, rozměry i názvy zůstávají.
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $name => $size ) {
		$path    = $dir . '/' . $size['file'];
		$paths[] = $path;
		$result  = is_file( $path ) ? velrion_images_recompress( $path, 0 ) : null;
		if ( $result ) {
			$saved                            += $result['saved'];
			$meta['sizes'][ $name ]['filesize'] = filesize( $path );
		}
	}

	// 4. Kopie „foto.jpg.webp“ po dřívějších optimalizačních pluginech, web je nepoužívá.
	foreach ( $paths as $path ) {
		if ( is_file( $path . '.webp' ) ) {
			$saved += filesize( $path . '.webp' );
			@unlink( $path . '.webp' );
		}
	}

	wp_update_attachment_metadata( $id, $meta );
	update_post_meta( $id, VELRION_IMAGES_DONE_META, $saved );
	return $saved;
}

function velrion_images_pending_ids( int $limit ): array {
	return get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => VELRION_IMAGES_DONE_META,
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);
}

function velrion_images_count( bool $pending ): int {
	$query = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => $pending ? array( array( 'key' => VELRION_IMAGES_DONE_META, 'compare' => 'NOT EXISTS' ) ) : array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return (int) $query->found_posts;
}

add_action(
	'wp_ajax_velrion_images_bulk',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Nemáte oprávnění.', 403 );
		}
		check_ajax_referer( 'velrion_images_bulk' );
		@set_time_limit( 120 );

		$saved = 0;
		foreach ( velrion_images_pending_ids( VELRION_IMAGES_BATCH ) as $id ) {
			$saved += velrion_images_optimize_attachment( (int) $id );
		}
		wp_send_json_success(
			array(
				'saved'   => $saved,
				'pending' => velrion_images_count( true ),
			)
		);
	}
);

add_action(
	'admin_menu',
	function () {
		add_media_page( 'Optimalizovat knihovnu', 'Optimalizovat knihovnu', 'manage_options', 'velrion-images-bulk', 'velrion_images_render_bulk' );
	}
);

function velrion_images_render_bulk(): void {
	$total   = velrion_images_count( false );
	$pending = velrion_images_count( true );
	?>
	<div class="wrap">
		<h1>Optimalizovat knihovnu</h1>
		<p>Projde stávající fotky (JPEG, PNG, WebP) a u každé:</p>
		<ol>
			<li>smaže originál v plné velikosti, který WordPress drží vedle verze „-scaled“ a web ho nezobrazuje,</li>
			<li>hlavní soubor zmenší na nejdelší stranu <?php echo (int) velrion_images_option( 'velrion_images_max' ); ?> px a překóduje v kvalitě <?php echo (int) velrion_images_option( 'velrion_images_quality' ); ?>,</li>
			<li>překóduje zmenšeniny,</li>
			<li>smaže kopie „foto.jpg.webp“ po dřívějších optimalizačních pluginech.</li>
		</ol>
		<p><strong>Názvy, adresy i formáty souborů zůstávají stejné</strong>, odkazy na webu, v Elementoru i v katalogu Meta platí dál. Soubor se nahradí jen tehdy, když je nový menší. <strong>Změna je nevratná: nejdřív zálohujte média</strong> (Velrion Backup → Zálohovat jen média).</p>
		<p>Fotek: <strong><?php echo (int) $total; ?></strong>, zbývá zpracovat: <strong id="velrion-pending"><?php echo (int) $pending; ?></strong>, ušetřeno v tomto běhu: <strong id="velrion-saved">0 MB</strong></p>
		<p>
			<button type="button" class="button button-primary" id="velrion-start"<?php disabled( 0, $pending ); ?>>Spustit optimalizaci</button>
			<span id="velrion-status" class="description"></span>
		</p>
		<p class="description">Běží po <?php echo (int) VELRION_IMAGES_BATCH; ?> fotkách, stránku nechte otevřenou. Po zavření jde kdykoli pokračovat, hotové fotky se přeskočí.</p>
	</div>
	<script>
	( function () {
		const button = document.getElementById( 'velrion-start' );
		const status = document.getElementById( 'velrion-status' );
		let saved = 0;

		async function step() {
			const body = new URLSearchParams( { action: 'velrion_images_bulk', _ajax_nonce: <?php echo wp_json_encode( wp_create_nonce( 'velrion_images_bulk' ) ); ?> } );
			const response = await fetch( ajaxurl, { method: 'POST', body } );
			const json = await response.json().catch( () => null );
			if ( ! json || ! json.success ) {
				status.textContent = 'Chyba, zkuste pokračovat znovu: ' + ( json && json.data ? json.data : response.status );
				button.disabled = false;
				return;
			}
			saved += json.data.saved;
			document.getElementById( 'velrion-pending' ).textContent = json.data.pending;
			document.getElementById( 'velrion-saved' ).textContent = ( saved / 1048576 ).toFixed( 0 ) + ' MB';
			if ( json.data.pending > 0 ) {
				step();
			} else {
				status.textContent = 'Hotovo.';
			}
		}

		button.addEventListener( 'click', function () {
			button.disabled = true;
			status.textContent = 'Probíhá…';
			step();
		} );
	} )();
	</script>
	<?php
}

/* ------------------------------------------------------------------ */
/* Nastavení → Média                                                   */
/* ------------------------------------------------------------------ */

add_action(
	'admin_init',
	function () {
		register_setting(
			'media',
			'velrion_images_max',
			array(
				'type'              => 'integer',
				'sanitize_callback' => fn( $v ) => max( 800, min( 6000, (int) $v ) ),
			)
		);
		register_setting(
			'media',
			'velrion_images_quality',
			array(
				'type'              => 'integer',
				'sanitize_callback' => fn( $v ) => max( 40, min( 100, (int) $v ) ),
			)
		);
		register_setting(
			'media',
			'velrion_images_format',
			array(
				'type'              => 'string',
				'sanitize_callback' => fn( $v ) => 'image/webp' === $v ? 'image/webp' : 'image/jpeg',
			)
		);

		add_settings_section(
			'velrion_images',
			'Optimalizace fotek (Velrion Images)',
			function () {
				echo '<p>Platí pro nově nahrané fotky. Originál v plné velikosti se neukládá, převod je nevratný. Zmenšeniny, které web zobrazuje, jsou vždy ve WebP, PNG se převádí na WebP.</p>';
			},
			'media'
		);
		add_settings_field(
			'velrion_images_max',
			'Nejdelší strana',
			function () {
				printf( '<input name="velrion_images_max" type="number" min="800" max="6000" step="100" class="small-text" value="%d"> px', (int) velrion_images_option( 'velrion_images_max' ) );
			},
			'media',
			'velrion_images'
		);
		add_settings_field(
			'velrion_images_quality',
			'Kvalita',
			function () {
				printf( '<input name="velrion_images_quality" type="number" min="40" max="100" class="small-text" value="%d"> %%', (int) velrion_images_option( 'velrion_images_quality' ) );
			},
			'media',
			'velrion_images'
		);
		add_settings_field(
			'velrion_images_format',
			'Formát plné velikosti',
			function () {
				$format = velrion_images_option( 'velrion_images_format' );
				echo '<select name="velrion_images_format">';
				printf( '<option value="image/jpeg"%s>JPEG (doporučeno pro e-shop: katalog Meta a Google)</option>', selected( $format, 'image/jpeg', false ) );
				printf( '<option value="image/webp"%s>WebP (menší soubory)</option>', selected( $format, 'image/webp', false ) );
				echo '</select>';
			},
			'media',
			'velrion_images'
		);
	}
);
