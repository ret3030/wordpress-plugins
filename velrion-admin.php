<?php
/**
 * Plugin Name:       Velrion Admin
 * Description:       Jednoduché menu pro administrátory: jen vybrané položky, bez nástěnky a s přepínačem na celé menu v horní liště.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       velrion-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VELRION_ADMIN_OPTION = 'velrion_admin_menu';
const VELRION_ADMIN_FULL_META = 'velrion_admin_full_menu';
const VELRION_ADMIN_PAGE = 'velrion-admin';

/** Výchozí jednoduché menu pro e-shop; položky, které na webu nejsou, se ignorují. Nástěnka v něm není nikdy. */
const VELRION_ADMIN_DEFAULT_MENU = array(
	'edit.php?post_type=product',
	'woocommerce',
	'edit.php?post_type=page',
	'upload.php',
	'users.php',
	'plugins.php',
	'options-general.php',
	'velrion-snippets',
);

function velrion_admin_simple_mode(): bool {
	return current_user_can( 'manage_options' ) && ! get_user_meta( get_current_user_id(), VELRION_ADMIN_FULL_META, true );
}

function velrion_admin_menu_slugs(): array {
	return (array) get_option( VELRION_ADMIN_OPTION, VELRION_ADMIN_DEFAULT_MENU );
}

/* ------------------------------------------------------------------ */
/* Menu                                                                */
/* ------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		add_options_page( 'Velrion Admin', 'Jednoduché menu', 'manage_options', VELRION_ADMIN_PAGE, 'velrion_admin_render_settings' );
	}
);

add_action(
	'admin_menu',
	function () {
		global $menu;
		// Na stránce nastavení je potřeba celé menu, aby šlo vybírat položky.
		if ( ! velrion_admin_simple_mode() || VELRION_ADMIN_PAGE === ( $_GET['page'] ?? '' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$allowed = velrion_admin_menu_slugs();
		foreach ( (array) $menu as $position => $item ) {
			if ( ! in_array( $item[2] ?? '', $allowed, true ) ) {
				unset( $menu[ $position ] );
			}
		}
	},
	PHP_INT_MAX
);

/** Místo nástěnky rovnou práce: produkty na e-shopu, jinak stránky. */
add_action(
	'load-index.php',
	function () {
		if ( velrion_admin_simple_mode() ) {
			wp_safe_redirect( admin_url( post_type_exists( 'product' ) ? 'edit.php?post_type=product' : 'edit.php?post_type=page' ) );
			exit;
		}
	}
);

/* ------------------------------------------------------------------ */
/* Přepínač v horní liště                                              */
/* ------------------------------------------------------------------ */

add_action(
	'admin_bar_menu',
	function ( $admin_bar ) {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$simple = velrion_admin_simple_mode();
		$admin_bar->add_node(
			array(
				'id'    => 'velrion-admin',
				'title' => $simple ? 'Celé menu' : 'Jednoduché menu',
				'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=velrion_admin_toggle' ), 'velrion_admin_toggle' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'velrion-admin',
				'id'     => 'velrion-admin-settings',
				'title'  => 'Upravit jednoduché menu',
				'href'   => admin_url( 'options-general.php?page=' . VELRION_ADMIN_PAGE ),
			)
		);
	},
	100
);

add_action(
	'admin_post_velrion_admin_toggle',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemáte oprávnění.' );
		}
		check_admin_referer( 'velrion_admin_toggle' );

		$user_id = get_current_user_id();
		if ( get_user_meta( $user_id, VELRION_ADMIN_FULL_META, true ) ) {
			delete_user_meta( $user_id, VELRION_ADMIN_FULL_META );
		} else {
			update_user_meta( $user_id, VELRION_ADMIN_FULL_META, 1 );
		}
		wp_safe_redirect( wp_get_referer() ?: admin_url() );
		exit;
	}
);

/* ------------------------------------------------------------------ */
/* Nastavení                                                           */
/* ------------------------------------------------------------------ */

add_action(
	'admin_post_velrion_admin_save',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nemáte oprávnění.' );
		}
		check_admin_referer( 'velrion_admin_save' );

		$slugs = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['menu'] ?? array() ) ) );
		// Bez nastavení by se k této stránce nešlo vrátit jinak než přes horní lištu.
		$slugs[] = 'options-general.php';
		update_option( VELRION_ADMIN_OPTION, array_values( array_unique( $slugs ) ) );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . VELRION_ADMIN_PAGE . '&saved=1' ) );
		exit;
	}
);

function velrion_admin_render_settings(): void {
	global $menu;
	$selected = velrion_admin_menu_slugs();
	?>
	<div class="wrap">
		<h1>Jednoduché menu</h1>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p>Uloženo.</p></div>
		<?php endif; ?>
		<p>Zaškrtnuté položky uvidí administrátoři v jednoduchém menu. Celé menu se zapíná v horní liště tlačítkem „Celé menu“, každý administrátor zvlášť.</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'velrion_admin_save' ); ?>
			<input type="hidden" name="action" value="velrion_admin_save">
			<fieldset>
				<?php
				foreach ( (array) $menu as $item ) :
					$slug  = $item[2] ?? '';
					$title = trim( wp_strip_all_tags( $item[0] ?? '' ) );
					if ( '' === $title || 'index.php' === $slug || 0 === strpos( $slug, 'separator' ) ) {
						continue;
					}
					?>
					<label style="display:block;margin:6px 0">
						<input type="checkbox" name="menu[]" value="<?php echo esc_attr( $slug ); ?>"<?php checked( in_array( $slug, $selected, true ) ); ?><?php disabled( 'options-general.php', $slug ); ?>>
						<?php echo esc_html( $title ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<?php submit_button( 'Uložit' ); ?>
		</form>
	</div>
	<?php
}
