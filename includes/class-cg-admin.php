<?php
/**
 * Admin stránka Média → Galerie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CG_Admin {

	const PAGE_SLUG = 'cg-galleries';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_cg_save_galleries', array( $this, 'handle_save' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'upload.php',
			__( 'Galerie', 'custom-gallery' ),
			__( 'Galerie', 'custom-gallery' ),
			cg_capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue( $hook ) {
		if ( 'media_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'cg-admin', CG_URL . 'assets/admin.css', array(), CG_VERSION );
		wp_enqueue_script(
			'cg-admin',
			CG_URL . 'assets/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			CG_VERSION,
			true
		);
		wp_localize_script(
			'cg-admin',
			'cgL10n',
			array(
				'frameTitle'  => __( 'Vybrat obrázky', 'custom-gallery' ),
				'frameButton' => __( 'Přidat do galerie', 'custom-gallery' ),
				'remove'      => __( 'Odebrat obrázek', 'custom-gallery' ),
				'confirm'     => __( 'Smazat tuto galerii? Obrázky zůstanou v knihovně médií.', 'custom-gallery' ),
			)
		);
	}

	/**
	 * Uloží formulář. Volá se přes admin-post.php.
	 */
	public function handle_save() {
		if ( ! current_user_can( cg_capability() ) ) {
			wp_die( esc_html__( 'K této akci nemáte oprávnění.', 'custom-gallery' ), 403 );
		}

		check_admin_referer( 'cg_save_galleries' );

		$raw   = isset( $_POST['cg_galleries'] ) ? (array) wp_unslash( $_POST['cg_galleries'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$clean = array();

		foreach ( $raw as $slug => $data ) {
			$slug = sanitize_key( $slug );

			if ( '' === $slug ) {
				continue;
			}

			$title = isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '';
			$ids   = isset( $data['ids'] ) ? (array) $data['ids'] : array();
			$ids   = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

			// Do DB pustíme jen ID, která opravdu odpovídají příloze.
			$ids = array_values(
				array_filter(
					$ids,
					function ( $id ) {
						return 'attachment' === get_post_type( $id );
					}
				)
			);

			$clean[ $slug ] = array(
				'title' => '' !== $title ? $title : $slug,
				'ids'   => $ids,
			);
		}

		// Smazání galerie.
		$delete = isset( $_POST['cg_delete'] ) ? sanitize_key( wp_unslash( $_POST['cg_delete'] ) ) : '';
		if ( '' !== $delete ) {
			unset( $clean[ $delete ] );
		}

		// Založení nové galerie.
		$new_title = isset( $_POST['cg_new_title'] ) ? sanitize_text_field( wp_unslash( $_POST['cg_new_title'] ) ) : '';
		if ( '' !== $new_title ) {
			$new_slug           = cg_make_slug( $new_title, $clean );
			$clean[ $new_slug ] = array(
				'title' => $new_title,
				'ids'   => array(),
			);
		}

		update_option( CG_OPTION, $clean, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::PAGE_SLUG,
					'cg-status' => 'saved',
				),
				admin_url( 'upload.php' )
			)
		);
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( cg_capability() ) ) {
			return;
		}

		$galleries = cg_get_galleries();
		$saved     = isset( $_GET['cg-status'] ) && 'saved' === $_GET['cg-status']; // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<div class="wrap cg-wrap">
			<h1><?php esc_html_e( 'Galerie', 'custom-gallery' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Obsah galerií spravujte tady. Na stránce je pak zobrazíte widgetem „Redakční galerie“ v Elementoru.', 'custom-gallery' ); ?>
				<br>
				<?php esc_html_e( 'Text pod fotkou se bere z pole Název (jméno kusu) a Popisek (materiál, rozměr) dané přílohy — upravíte je kliknutím na obrázek v knihovně médií.', 'custom-gallery' ); ?>
			</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Změny uloženy.', 'custom-gallery' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cg_save_galleries">
				<?php wp_nonce_field( 'cg_save_galleries' ); ?>
				<?php // Výchozí akce pro Enter, aby se nespustilo tlačítko „Smazat galerii“. ?>
				<button type="submit" class="cg-default-submit" tabindex="-1" aria-hidden="true"></button>

				<?php if ( empty( $galleries ) ) : ?>
					<div class="cg-empty">
						<p><?php esc_html_e( 'Zatím tu není žádná galerie. Založte první níže.', 'custom-gallery' ); ?></p>
					</div>
				<?php endif; ?>

				<?php foreach ( $galleries as $slug => $gallery ) : ?>
					<div class="cg-box postbox">
						<div class="cg-box-head">
							<input
								type="text"
								class="cg-title regular-text"
								name="cg_galleries[<?php echo esc_attr( $slug ); ?>][title]"
								value="<?php echo esc_attr( $gallery['title'] ); ?>">
							<code class="cg-slug"><?php echo esc_html( $slug ); ?></code>
							<button type="submit" name="cg_delete" value="<?php echo esc_attr( $slug ); ?>" class="button-link cg-delete">
								<?php esc_html_e( 'Smazat galerii', 'custom-gallery' ); ?>
							</button>
						</div>

						<div class="cg-box-body">
							<ul class="cg-items" data-slug="<?php echo esc_attr( $slug ); ?>">
								<?php foreach ( $gallery['ids'] as $id ) : ?>
									<?php
									$thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
									if ( ! $thumb ) {
										continue;
									}
									?>
									<li class="cg-item" data-id="<?php echo esc_attr( $id ); ?>">
										<img src="<?php echo esc_url( $thumb ); ?>" alt="">
										<button type="button" class="cg-remove" aria-label="<?php esc_attr_e( 'Odebrat obrázek', 'custom-gallery' ); ?>">&times;</button>
										<input type="hidden" name="cg_galleries[<?php echo esc_attr( $slug ); ?>][ids][]" value="<?php echo esc_attr( $id ); ?>">
									</li>
								<?php endforeach; ?>
							</ul>

							<?php if ( empty( $gallery['ids'] ) ) : ?>
								<p class="description cg-hint"><?php esc_html_e( 'Galerie je zatím prázdná.', 'custom-gallery' ); ?></p>
							<?php endif; ?>

							<p>
								<button type="button" class="button cg-add"><?php esc_html_e( 'Přidat obrázky', 'custom-gallery' ); ?></button>
								<span class="description"><?php esc_html_e( 'Pořadí změníte přetažením.', 'custom-gallery' ); ?></span>
							</p>
						</div>
					</div>
				<?php endforeach; ?>

				<div class="cg-box postbox cg-new">
					<div class="cg-box-body">
						<label for="cg_new_title"><strong><?php esc_html_e( 'Nová galerie', 'custom-gallery' ); ?></strong></label>
						<input type="text" id="cg_new_title" name="cg_new_title" class="regular-text" placeholder="<?php esc_attr_e( 'Např. Fotky z akce', 'custom-gallery' ); ?>">
						<p class="description"><?php esc_html_e( 'Obrázky přidáte hned po uložení.', 'custom-gallery' ); ?></p>
					</div>
				</div>

				<?php submit_button( __( 'Uložit změny', 'custom-gallery' ) ); ?>
			</form>
		</div>
		<?php
	}
}
