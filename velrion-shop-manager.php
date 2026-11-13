<?php
/**
 * Plugin Name:       Velrion Shop Manager
 * Description:       Zjednodušená administrace pro roli Správce obchodu: jen produkty, odznaky, média, objednávky a překlad. Nahrazuje Adminimize a User Role Editor.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Text Domain:       velrion-shop-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VELRION_SM_ROLE = 'shop_manager';

/**
 * Oprávnění, která role dostala navíc přes User Role Editor. Umožňovala měnit
 * nastavení webu, instalovat šablony a upravovat jejich PHP soubory.
 */
const VELRION_SM_REMOVE_CAPS = array(
	'manage_options',
	'edit_files',
	'edit_themes',
	'install_themes',
	'update_themes',
	'unfiltered_upload',
	'edit_dashboard',
	'create_posts',
	'bricks_upload_svg',
	'iawp_read_only_access',
);

/** Překlad webu v TranslatePress (doplněk Translator Accounts). */
const VELRION_SM_ADD_CAPS = array( 'translate_strings' );

/**
 * Povolené položky menu v pořadí, v jakém se zobrazí: hlavní položka => povolené podpoložky (null = všechny).
 * Položka „Překlad webu“ od TranslatePress se doplní za běhu na konec, má v názvu adresu webu.
 */
const VELRION_SM_MENU = array(
	'edit.php?post_type=product' => null,
	'woocommerce'                => array( 'wc-orders', 'edit.php?post_type=shop_order' ),
	'upload.php'                 => null,
	'edit.php?post_type=epx_faq' => null,
	'edit.php?post_type=epx-badge' => null,
	'woocommerce-marketing'      => array( 'edit.php?post_type=shop_coupon' ),
	'profile.php'                => null,
);

/** Položky horní lišty, které zůstanou (root-default a top-secondary jsou skupiny levé a pravé části). */
const VELRION_SM_ADMIN_BAR = array( 'root-default', 'top-secondary', 'menu-toggle', 'site-name', 'trp_edit_translation', 'my-account' );

register_activation_hook(
	__FILE__,
	function () {
		$role = get_role( VELRION_SM_ROLE );
		if ( ! $role ) {
			return;
		}
		foreach ( VELRION_SM_REMOVE_CAPS as $cap ) {
			$role->remove_cap( $cap );
		}
		foreach ( VELRION_SM_ADD_CAPS as $cap ) {
			$role->add_cap( $cap );
		}
	}
);

/** Zjednodušení platí jen pro správce obchodu, administrátor vidí vše. */
function velrion_sm_is_restricted( $user = null ): bool {
	$user = $user ?: wp_get_current_user();
	return $user instanceof WP_User
		&& in_array( VELRION_SM_ROLE, $user->roles, true )
		&& ! in_array( 'administrator', $user->roles, true );
}

function velrion_sm_home_url(): string {
	return admin_url( 'edit.php?post_type=product' );
}

/* ------------------------------------------------------------------ */
/* Přihlášení a nástěnka                                               */
/* ------------------------------------------------------------------ */

add_filter(
	'login_redirect',
	function ( $redirect_to, $requested, $user ) {
		if ( ! velrion_sm_is_restricted( $user ) ) {
			return $redirect_to;
		}
		// Konkrétní požadovanou stránku (např. odkaz z e-mailu s objednávkou) respektujeme.
		$generic = array( '', admin_url(), admin_url( 'index.php' ) );
		return in_array( $requested, $generic, true ) ? velrion_sm_home_url() : $redirect_to;
	},
	10,
	3
);

add_action(
	'load-index.php',
	function () {
		if ( velrion_sm_is_restricted() ) {
			wp_safe_redirect( velrion_sm_home_url() );
			exit;
		}
	}
);

/* ------------------------------------------------------------------ */
/* Menu a horní lišta                                                  */
/* ------------------------------------------------------------------ */

add_action(
	'admin_menu',
	function () {
		global $menu, $submenu;
		if ( ! velrion_sm_is_restricted() ) {
			return;
		}

		$allowed = VELRION_SM_MENU;
		$allowed[ add_query_arg( 'trp-edit-translation', 'true', trailingslashit( home_url() ) ) ] = null;

		foreach ( (array) $menu as $position => $item ) {
			$slug = $item[2] ?? '';
			if ( ! array_key_exists( $slug, $allowed ) ) {
				unset( $menu[ $position ] );
				continue;
			}
			if ( null === $allowed[ $slug ] || empty( $submenu[ $slug ] ) ) {
				continue;
			}
			foreach ( $submenu[ $slug ] as $sub_position => $sub_item ) {
				if ( ! in_array( $sub_item[2], $allowed[ $slug ], true ) ) {
					unset( $submenu[ $slug ][ $sub_position ] );
				}
			}
		}
	},
	PHP_INT_MAX
);

add_filter(
	'custom_menu_order',
	function ( $custom ) {
		return velrion_sm_is_restricted() ? true : $custom;
	}
);

// Až po WooCommerce, který v tomto filtru přesouvá Produkty za svou položku.
add_filter(
	'menu_order',
	function ( $order ) {
		if ( ! velrion_sm_is_restricted() ) {
			return $order;
		}
		$rank = array_flip( array_keys( VELRION_SM_MENU ) );
		usort(
			$order,
			function ( $a, $b ) use ( $rank ) {
				return ( $rank[ $a ] ?? PHP_INT_MAX ) <=> ( $rank[ $b ] ?? PHP_INT_MAX );
			}
		);
		return $order;
	},
	PHP_INT_MAX
);

/** Hláška o nové verzi WordPressu: aktualizuje administrátor, správce obchodu s ní nic neudělá. */
add_action(
	'admin_init',
	function () {
		if ( velrion_sm_is_restricted() ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
		}
	}
);

add_action(
	'admin_bar_menu',
	function ( $admin_bar ) {
		if ( ! velrion_sm_is_restricted() ) {
			return;
		}
		$top_level = array( '', 'root-default', 'top-secondary' );
		foreach ( $admin_bar->get_nodes() as $node ) {
			if ( in_array( (string) $node->parent, $top_level, true ) && ! in_array( $node->id, VELRION_SM_ADMIN_BAR, true ) ) {
				$admin_bar->remove_node( $node->id );
			}
		}
	},
	PHP_INT_MAX
);
