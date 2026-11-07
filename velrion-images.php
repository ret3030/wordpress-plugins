<?php
/**
 * Plugin Name:       Velrion Images
 * Description:       Optimalizace fotek při nahrání: zmenšení, převod formátu a zahození originálu v plné velikosti. Zmenšeniny pro web ve WebP.
 * Version:           1.0.0
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
