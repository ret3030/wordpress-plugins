<?php
/**
 * Plugin Name:       Epoxis FAQ pro Elementor
 * Description:       Časté dotazy spravované v administraci (menu FAQ) a vkládané na stránky Elementor widgetem „FAQ“.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       epoxis-faq
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EPX_FAQ_VERSION', '1.0.1' );
define( 'EPX_FAQ_PATH', plugin_dir_path( __FILE__ ) );
define( 'EPX_FAQ_URL', plugin_dir_url( __FILE__ ) );

/**
 * Otázka = příspěvek typu epx_faq (název je otázka, obsah je odpověď),
 * skupina = taxonomie epx_faq_group. Obojí je jen pro administraci,
 * na webu se nic nezobrazuje mimo widget.
 */
add_action(
	'init',
	function () {
		register_post_type(
			'epx_faq',
			array(
				'labels'        => array(
					'name'               => __( 'FAQ', 'epoxis-faq' ),
					'singular_name'      => __( 'Otázka', 'epoxis-faq' ),
					'menu_name'          => __( 'FAQ', 'epoxis-faq' ),
					'all_items'          => __( 'Všechny otázky', 'epoxis-faq' ),
					'add_new'            => __( 'Přidat otázku', 'epoxis-faq' ),
					'add_new_item'       => __( 'Nová otázka', 'epoxis-faq' ),
					'edit_item'          => __( 'Upravit otázku', 'epoxis-faq' ),
					'search_items'       => __( 'Hledat otázky', 'epoxis-faq' ),
					'not_found'          => __( 'Zatím tu nejsou žádné otázky.', 'epoxis-faq' ),
					'not_found_in_trash' => __( 'V koši nejsou žádné otázky.', 'epoxis-faq' ),
				),
				'public'        => false,
				'show_ui'       => true,
				'menu_position' => 21,
				'menu_icon'     => 'dashicons-editor-help',
				// page-attributes přidá pole „Pořadí“.
				'supports'      => array( 'title', 'editor', 'page-attributes', 'revisions' ),
				// Bez REST běží klasický editor, pro krátké odpovědi je přehlednější než bloky.
				'show_in_rest'  => false,
				'rewrite'       => false,
				'query_var'     => false,
			)
		);

		register_taxonomy(
			'epx_faq_group',
			'epx_faq',
			array(
				'labels'            => array(
					'name'          => __( 'Skupiny', 'epoxis-faq' ),
					'singular_name' => __( 'Skupina', 'epoxis-faq' ),
					'menu_name'     => __( 'Skupiny', 'epoxis-faq' ),
					'all_items'     => __( 'Všechny skupiny', 'epoxis-faq' ),
					'add_new_item'  => __( 'Přidat skupinu', 'epoxis-faq' ),
					'edit_item'     => __( 'Upravit skupinu', 'epoxis-faq' ),
					'search_items'  => __( 'Hledat skupiny', 'epoxis-faq' ),
					'not_found'     => __( 'Žádné skupiny.', 'epoxis-faq' ),
				),
				'public'            => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				// Hierarchická = zaškrtávací políčka v editoru místo psaní štítků.
				'hierarchical'      => true,
				'show_in_rest'      => false,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}
);

add_filter(
	'enter_title_here',
	function ( $text, $post ) {
		return 'epx_faq' === $post->post_type ? __( 'Otázka, např. Jak dlouho trvá výroba?', 'epoxis-faq' ) : $text;
	},
	10,
	2
);

/**
 * V seznamu otázek řadit stejně jako na webu.
 */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'epx_faq' !== $query->get( 'post_type' ) || $query->get( 'orderby' ) ) {
			return;
		}
		$query->set(
			'orderby',
			array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			)
		);
	}
);

add_filter(
	'manage_epx_faq_posts_columns',
	function ( $columns ) {
		$columns['menu_order'] = __( 'Pořadí', 'epoxis-faq' );
		return $columns;
	}
);

add_action(
	'manage_epx_faq_posts_custom_column',
	function ( $column, $post_id ) {
		if ( 'menu_order' === $column ) {
			echo (int) get_post_field( 'menu_order', $post_id );
		}
	},
	10,
	2
);

add_action(
	'wp_enqueue_scripts',
	function () {
		// Jen registrace, načte se přes get_style_depends() na stránkách s widgetem.
		wp_register_style( 'epx-faq', EPX_FAQ_URL . 'assets/frontend.css', array(), EPX_FAQ_VERSION );
	}
);

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		require_once EPX_FAQ_PATH . 'includes/class-epx-faq-widget.php';
		$widgets_manager->register( new EPX_FAQ_Widget() );
	}
);
