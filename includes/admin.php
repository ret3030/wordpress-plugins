<?php
/** Administrace odznaků: typ příspěvku, formulář, ukládání, sloupce v seznamu a založení hotových ikon. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_post_type(
			'epx-badge',
			array(
				'labels'       => array(
					'name'          => 'Odznaky',
					'singular_name' => 'Odznak',
					'menu_name'     => 'Odznaky',
					'add_new'       => 'Přidat odznak',
					'add_new_item'  => 'Přidat odznak',
					'edit_item'     => 'Upravit odznak',
					'search_items'  => 'Hledat odznaky',
					'not_found'     => 'Žádné odznaky',
				),
				'public'       => false,
				'show_ui'      => true,
				'supports'     => array( 'title' ),
				'menu_icon'    => 'dashicons-awards',
				'menu_position' => 56,
			)
		);
	}
);

/**
 * Hotové ikony se založí jako koncepty, tedy vypnuté. Stačí u nich vybrat kategorie nebo produkty
 * a zveřejnit. Přidá se jen to, co chybí, takže nové ikony v další verzi se doplní bez duplicit.
 */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'epxb_seeded' ) === EPXB_VERSION ) {
			return;
		}
		$existing = get_posts(
			array(
				'post_type'   => 'epx-badge',
				'post_status' => 'any',
				'numberposts' => -1,
				'meta_key'    => '_epxb_preset',
				'fields'      => 'ids',
			)
		);
		$have     = array_map( function ( $id ) { return get_post_meta( $id, '_epxb_preset', true ); }, $existing );

		foreach ( epxb_presets() as $key => $preset ) {
			if ( in_array( $key, $have, true ) ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'   => 'epx-badge',
					'post_status' => 'draft',
					'post_title'  => $preset['label'],
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_epxb_preset', $key );
				update_post_meta(
					$id,
					'_badge_settings',
					epxb_clean_settings(
						array_merge(
							epxb_defaults(),
							array(
								'type'     => 'icon',
								'icon'     => $key,
								'text'     => $preset['label'],
								'bg_color' => $preset['color'],
								'position' => $preset['position'],
							)
						)
					)
				);
			}
		}
		update_option( 'epxb_seeded', EPXB_VERSION );
	}
);

add_action(
	'add_meta_boxes_epx-badge',
	function () {
		add_meta_box( 'epxb_settings', 'Vzhled odznaku', 'epxb_box_settings', 'epx-badge', 'normal', 'high' );
		add_meta_box( 'epxb_scope', 'Kde se odznak zobrazí', 'epxb_box_scope', 'epx-badge', 'normal', 'default' );
		add_meta_box( 'epxb_preview', 'Náhled', 'epxb_box_preview', 'epx-badge', 'side', 'high' );
	}
);

function epxb_box_settings( WP_Post $post ) {
	wp_nonce_field( 'epxb_save', 'epxb_nonce' );

	$stored = get_post_meta( $post->ID, '_badge_settings', true );
	$s      = $stored ? epxb_clean_settings( $stored ) : epxb_defaults();

	$positions = array(
		'top-left'     => 'Vlevo nahoře',
		'top-right'    => 'Vpravo nahoře',
		'bottom-left'  => 'Vlevo dole',
		'bottom-right' => 'Vpravo dole',
	);
	$sizes     = array( 'small' => 'Malý', 'medium' => 'Střední', 'large' => 'Velký' );
	$shapes    = array( 'circle' => 'Kruh', 'rounded' => 'Zaoblený', 'rectangle' => 'Obdélník' );
	?>
	<table class="form-table epxb-form" role="presentation">
		<tr>
			<th>Typ</th>
			<td>
				<label><input type="radio" name="badge_settings[type]" value="icon" <?php checked( $s['type'], 'icon' ); ?>> Ikona</label>
				<label><input type="radio" name="badge_settings[type]" value="text" <?php checked( $s['type'], 'text' ); ?>> Text</label>
			</td>
		</tr>
		<tr class="epxb-only-icon">
			<th>Ikona</th>
			<td class="epxb-picker">
				<?php foreach ( epxb_presets() as $key => $preset ) : ?>
					<label class="epxb-pick">
						<input type="radio" name="badge_settings[icon]" value="<?php echo esc_attr( $key ); ?>" data-label="<?php echo esc_attr( $preset['label'] ); ?>" data-color="<?php echo esc_attr( $preset['color'] ); ?>" <?php checked( $s['icon'], $key ); ?>>
						<?php echo epxb_icon_svg( $key ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span><?php echo esc_html( $preset['label'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th>
				<span class="epxb-only-icon">Popisek při najetí myší</span>
				<span class="epxb-only-text">Text odznaku</span>
			</th>
			<td><input type="text" name="badge_settings[text]" value="<?php echo esc_attr( $s['text'] ); ?>" class="regular-text" maxlength="60"></td>
		</tr>
		<tr>
			<th>Barva pozadí</th>
			<td><input type="text" name="badge_settings[bg_color]" value="<?php echo esc_attr( $s['bg_color'] ); ?>" class="epxb-color"></td>
		</tr>
		<tr>
			<th>Barva textu a ikony</th>
			<td><input type="text" name="badge_settings[text_color]" value="<?php echo esc_attr( $s['text_color'] ); ?>" class="epxb-color"></td>
		</tr>
		<tr>
			<th>Umístění</th>
			<td>
				<select name="badge_settings[position]">
					<?php foreach ( $positions as $value => $name ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['position'], $value ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">Více odznaků ve stejném rohu se řadí pod sebe.</p>
			</td>
		</tr>
		<tr>
			<th>Velikost a tvar</th>
			<td>
				<select name="badge_settings[size]">
					<?php foreach ( $sizes as $value => $name ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['size'], $value ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="badge_settings[shape]">
					<?php foreach ( $shapes as $value => $name ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['shape'], $value ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th>Zobrazit</th>
			<td>
				<label><input type="checkbox" name="badge_settings[show_on_loop]" value="yes" <?php checked( $s['show_on_loop'], 'yes' ); ?>> Ve výpisu produktů</label><br>
				<label><input type="checkbox" name="badge_settings[show_on_single]" value="yes" <?php checked( $s['show_on_single'], 'yes' ); ?>> Na detailu produktu</label>
			</td>
		</tr>
	</table>
	<?php
}

function epxb_box_scope( WP_Post $post ) {
	$selected_cats = array_map( 'absint', (array) get_post_meta( $post->ID, '_assigned_categories', true ) );
	$selected_prod = array_filter( array_map( 'absint', (array) get_post_meta( $post->ID, '_assigned_products', true ) ) );
	$categories    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
	?>
	<p class="description">Odznak se ukáže u produktů z vybraných kategorií (včetně podkategorií) a u vybraných produktů. Odznak se zobrazuje, jen když je zveřejněný.</p>
	<p>
		<label for="epxb_categories"><strong>Kategorie</strong></label><br>
		<select id="epxb_categories" name="assigned_categories[]" class="wc-enhanced-select" multiple style="width:100%" data-placeholder="Vyberte kategorie">
			<?php foreach ( is_array( $categories ) ? $categories : array() as $term ) : ?>
				<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $selected_cats, true ) ); ?>>
					<?php echo esc_html( str_repeat( "\u{00a0}\u{00a0}\u{00a0}", count( get_ancestors( $term->term_id, 'product_cat' ) ) ) . $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="epxb_products"><strong>Konkrétní produkty</strong></label><br>
		<select id="epxb_products" name="assigned_products[]" class="wc-product-search" multiple style="width:100%" data-placeholder="Hledat produkt" data-action="woocommerce_json_search_products">
			<?php
			foreach ( $selected_prod as $product_id ) {
				$product = wc_get_product( $product_id );
				if ( $product ) {
					echo '<option value="' . esc_attr( $product_id ) . '" selected>' . esc_html( $product->get_formatted_name() ) . '</option>';
				}
			}
			?>
		</select>
	</p>
	<?php
}

function epxb_box_preview() {
	echo '<div class="epxb-stage"><div class="epxb-corner"></div></div>';
}

add_action(
	'save_post_epx-badge',
	function ( $post_id ) {
		if ( ! isset( $_POST['epxb_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['epxb_nonce'] ), 'epxb_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_badge_settings', wp_slash( epxb_clean_settings( wp_unslash( $_POST['badge_settings'] ?? array() ) ) ) );
		update_post_meta( $post_id, '_assigned_products', array_filter( array_map( 'absint', (array) ( $_POST['assigned_products'] ?? array() ) ) ) );
		update_post_meta( $post_id, '_assigned_categories', array_filter( array_map( 'absint', (array) ( $_POST['assigned_categories'] ?? array() ) ) ) );
		// Přechod stavu proběhl před uložením metadat, proto druhé vyprázdnění.
		epxb_flush_cache();
	}
);

add_filter(
	'manage_epx-badge_posts_columns',
	function ( array $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['epxb_preview'] = 'Náhled';
				$new['epxb_scope']   = 'Zobrazuje se u';
			}
		}
		return $new;
	}
);

add_action(
	'manage_epx-badge_posts_custom_column',
	function ( string $column, int $post_id ) {
		if ( 'epxb_preview' === $column ) {
			$badge = array( 'id' => $post_id, 'settings' => epxb_clean_settings( get_post_meta( $post_id, '_badge_settings', true ) ) );
			echo '<div class="epxb-corner epxb-static">' . epxb_badge_html( $badge ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		if ( 'epxb_scope' !== $column ) {
			return;
		}

		$names = array();
		foreach ( array_filter( array_map( 'absint', (array) get_post_meta( $post_id, '_assigned_categories', true ) ) ) as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		$products = count( array_filter( (array) get_post_meta( $post_id, '_assigned_products', true ) ) );
		if ( $products ) {
			$names[] = sprintf( 'produkty: %d', $products );
		}
		echo $names ? esc_html( implode( ', ', $names ) ) : 'nikde (vyberte kategorie nebo produkty)';
	},
	10,
	2
);

add_action(
	'admin_enqueue_scripts',
	function ( string $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || 'epx-badge' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'epxb-badges', EPXB_URL . 'assets/css/badges.css', array(), EPXB_VERSION );
		wp_enqueue_style( 'epxb-admin', EPXB_URL . 'assets/css/admin.css', array( 'epxb-badges' ), EPXB_VERSION );

		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'epxb-admin', EPXB_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), EPXB_VERSION, true );
	}
);
