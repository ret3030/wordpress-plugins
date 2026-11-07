<?php
/**
 * Elementor widget zobrazující otázky z menu FAQ.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EPX_FAQ_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'epx_faq';
	}

	public function get_title() {
		return __( 'FAQ', 'epoxis-faq' );
	}

	public function get_icon() {
		return 'eicon-accordion';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'faq', 'otázky', 'dotazy', 'accordion' );
	}

	public function get_style_depends() {
		return array( 'epx-faq' );
	}

	protected function register_controls() {

		/* ================= Obsah ================= */

		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Otázky', 'epoxis-faq' ) )
		);

		$groups = get_terms(
			array(
				'taxonomy'   => 'epx_faq_group',
				'hide_empty' => false,
			)
		);

		$options = array();
		if ( ! is_wp_error( $groups ) ) {
			foreach ( $groups as $group ) {
				$options[ $group->term_id ] = sprintf( '%s (%d)', $group->name, $group->count );
			}
		}

		$this->add_control(
			'groups',
			array(
				'label'       => __( 'Skupiny', 'epoxis-faq' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'options'     => $options,
				'label_block' => true,
				'description' => sprintf(
					/* translators: %s: odkaz na seznam otázek */
					__( 'Prázdné = všechny otázky. Skupiny se zobrazí v pořadí, v jakém je vyberete. Obsah spravujete v %s.', 'epoxis-faq' ),
					'<a href="' . esc_url( admin_url( 'edit.php?post_type=epx_faq' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'menu FAQ', 'epoxis-faq' ) . '</a>'
				),
			)
		);

		$this->add_control(
			'show_group_titles',
			array(
				'label'       => __( 'Nadpisy skupin', 'epoxis-faq' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => __( 'Funguje, jen když jsou vybrané skupiny.', 'epoxis-faq' ),
			)
		);

		$this->add_control(
			'group_title_tag',
			array(
				'label'     => __( 'Úroveň nadpisu', 'epoxis-faq' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'h3',
				'options'   => array(
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
				),
				'condition' => array( 'show_group_titles' => 'yes' ),
			)
		);

		$this->add_control(
			'exclusive',
			array(
				'label'       => __( 'Otevřená jen jedna odpověď', 'epoxis-faq' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'description' => __( 'Otevřením otázky se předchozí zavře.', 'epoxis-faq' ),
			)
		);

		$this->add_control(
			'open_first',
			array(
				'label'   => __( 'První otázka otevřená', 'epoxis-faq' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'schema',
			array(
				'label'       => __( 'Strukturovaná data (FAQPage)', 'epoxis-faq' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => 'yes',
				'separator'   => 'before',
				'description' => __( 'Popis otázek pro Google a AI vyhledávače. Na jedné stránce nechte zapnuté jen u jednoho FAQ widgetu.', 'epoxis-faq' ),
			)
		);

		$this->end_controls_section();

		/* ================= Otázka ================= */

		$this->start_controls_section(
			'section_question_style',
			array(
				'label' => __( 'Otázka', 'epoxis-faq' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'question_color',
			array(
				'label'     => __( 'Barva', 'epoxis-faq' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .epx-faq__q' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'question_typography',
				'selector' => '{{WRAPPER}} .epx-faq__q',
			)
		);

		$this->add_responsive_control(
			'question_padding',
			array(
				'label'      => __( 'Odsazení nahoře a dole', 'epoxis-faq' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'selectors'  => array( '{{WRAPPER}} .epx-faq__q' => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => __( 'Barva šipky', 'epoxis-faq' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .epx-faq' => '--epx-faq-icon: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'line_color',
			array(
				'label'     => __( 'Barva oddělovací linky', 'epoxis-faq' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .epx-faq' => '--epx-faq-line: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();

		/* ================= Odpověď ================= */

		$this->start_controls_section(
			'section_answer_style',
			array(
				'label' => __( 'Odpověď', 'epoxis-faq' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'answer_color',
			array(
				'label'     => __( 'Barva', 'epoxis-faq' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .epx-faq__a' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'answer_typography',
				'selector' => '{{WRAPPER}} .epx-faq__a',
			)
		);

		$this->end_controls_section();

		/* ================= Nadpis skupiny ================= */

		$this->start_controls_section(
			'section_group_style',
			array(
				'label'     => __( 'Nadpis skupiny', 'epoxis-faq' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_group_titles' => 'yes' ),
			)
		);

		$this->add_control(
			'group_color',
			array(
				'label'     => __( 'Barva', 'epoxis-faq' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .epx-faq__group' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'group_typography',
				'selector' => '{{WRAPPER}} .epx-faq__group',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$group_ids = array_values( array_filter( array_map( 'absint', (array) $settings['groups'] ) ) );

		$args = array(
			'post_type'   => 'epx_faq',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		);

		if ( $group_ids ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'epx_faq_group',
					'terms'    => $group_ids,
				),
			);
		}

		$posts = get_posts( $args );

		if ( ! $posts ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<div class="epx-faq-notice">%s</div>',
					esc_html__( 'Žádné otázky k zobrazení. Přidejte je v menu FAQ a stránku obnovte.', 'epoxis-faq' )
				);
			}
			return;
		}

		// Bez vybraných skupin jeden blok bez nadpisu, jinak blok za každou skupinu v pořadí výběru.
		$sections = array();
		if ( $group_ids ) {
			foreach ( $group_ids as $term_id ) {
				$term  = get_term( $term_id, 'epx_faq_group' );
				$items = array_filter(
					$posts,
					function ( $post ) use ( $term_id ) {
						return has_term( $term_id, 'epx_faq_group', $post );
					}
				);
				if ( $term && ! is_wp_error( $term ) && $items ) {
					$sections[] = array(
						'title' => $term->name,
						'items' => $items,
					);
				}
			}
		} else {
			$sections[] = array(
				'title' => '',
				'items' => $posts,
			);
		}

		$tag        = in_array( $settings['group_title_tag'], array( 'h2', 'h3', 'h4' ), true ) ? $settings['group_title_tag'] : 'h3';
		$show_title = 'yes' === $settings['show_group_titles'];
		// Atribut name na <details> zařídí, že je otevřená jen jedna odpověď, bez JavaScriptu.
		$name_attr = 'yes' === $settings['exclusive'] ? ' name="epx-faq-' . esc_attr( $this->get_id() ) . '"' : '';
		$open_next = 'yes' === $settings['open_first'];

		echo '<div class="epx-faq">';

		foreach ( $sections as $section ) {
			if ( $show_title && '' !== $section['title'] ) {
				printf( '<%1$s class="epx-faq__group">%2$s</%1$s>', $tag, esc_html( $section['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- $tag z whitelistu.
			}

			foreach ( $section['items'] as $post ) {
				printf(
					'<details class="epx-faq__item"%1$s%2$s><summary class="epx-faq__q">%3$s</summary><div class="epx-faq__a">%4$s</div></details>',
					$name_attr, // phpcs:ignore WordPress.Security.EscapeOutput
					$open_next ? ' open' : '',
					esc_html( $post->post_title ),
					wpautop( wp_kses_post( $post->post_content ) ) // phpcs:ignore WordPress.Security.EscapeOutput
				);
				$open_next = false;
			}
		}

		echo '</div>';

		if ( 'yes' === $settings['schema'] ) {
			$this->print_schema( $posts );
		}
	}

	/**
	 * Vypíše JSON-LD FAQPage. Bere $posts z dotazu, takže otázka ve dvou
	 * vybraných skupinách je ve schématu jen jednou.
	 *
	 * @param WP_Post[] $posts Otázky.
	 */
	private function print_schema( $posts ) {
		$questions = array();

		foreach ( $posts as $post ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $post->post_title,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $post->post_content ) ) ),
				),
			);
		}

		$schema = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);

		// JSON_HEX_TAG brání tomu, aby text odpovědi ukončil <script>.
		printf(
			'<script type="application/ld+json">%s</script>',
			wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
}
