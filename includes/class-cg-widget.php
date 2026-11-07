<?php
/**
 * Elementor widget zobrazující galerii spravovanou v Média → Galerie.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CG_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'cg_gallery';
	}

	public function get_title() {
		return __( 'Redakční galerie', 'custom-gallery' );
	}

	public function get_icon() {
		return 'eicon-gallery-masonry';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'galerie', 'gallery', 'lampy', 'reference', 'obrázky' );
	}

	protected function register_controls() {

		/* ================= Obsah ================= */

		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Galerie', 'custom-gallery' ) )
		);

		$options = array();
		foreach ( cg_get_galleries() as $slug => $gallery ) {
			$options[ $slug ] = sprintf( '%s (%d)', $gallery['title'], count( $gallery['ids'] ) );
		}

		if ( empty( $options ) ) {
			$options = array( '' => __( '— zatím žádná galerie —', 'custom-gallery' ) );
		}

		$this->add_control(
			'gallery_key',
			array(
				'label'       => __( 'Která galerie', 'custom-gallery' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => (string) array_key_first( $options ),
				'description' => sprintf(
					/* translators: %s: odkaz na admin stránku */
					__( 'Obsah spravujete v %s.', 'custom-gallery' ),
					'<a href="' . esc_url( admin_url( 'upload.php?page=cg-galleries' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Média → Galerie', 'custom-gallery' ) . '</a>'
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Image_Size::get_type(),
			array(
				'name'    => 'thumbnail',
				'default' => 'large',
			)
		);

		$this->add_control(
			'open_lightbox',
			array(
				'label'   => __( 'Otevírat v lightboxu', 'custom-gallery' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'       => __( 'Zobrazit nejvýše', 'custom-gallery' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 0,
				'description' => __( '0 = bez omezení.', 'custom-gallery' ),
			)
		);

		$this->end_controls_section();

		/* ================= Popisky ================= */

		$this->start_controls_section(
			'section_caption',
			array( 'label' => __( 'Popisky', 'custom-gallery' ) )
		);

		$this->add_control(
			'caption_source',
			array(
				'label'       => __( 'Co u kusu zobrazit', 'custom-gallery' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'both',
				'options'     => array(
					''        => __( 'Nic', 'custom-gallery' ),
					'title'   => __( 'Jen název', 'custom-gallery' ),
					'caption' => __( 'Jen specifikaci', 'custom-gallery' ),
					'both'    => __( 'Název i specifikaci', 'custom-gallery' ),
				),
				'description' => __( 'Text se bere z pole Název a Popisek dané přílohy v knihovně médií.', 'custom-gallery' ),
			)
		);

		$this->add_control(
			'caption_position',
			array(
				'label'     => __( 'Umístění', 'custom-gallery' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'below',
				'options'   => array(
					'below'   => __( 'Pod fotkou', 'custom-gallery' ),
					'overlay' => __( 'Přes fotku, až po najetí', 'custom-gallery' ),
				),
				'condition' => array( 'caption_source!' => '' ),
			)
		);

		$this->end_controls_section();

		/* ================= Rozvržení ================= */

		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Rozvržení', 'custom-gallery' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'       => __( 'Typ', 'custom-gallery' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'masonry',
				'options'     => array(
					'masonry' => __( 'Zděné — každý kus si nechá svou proporci', 'custom-gallery' ),
					'grid'    => __( 'Mřížka — jednotný ořez', 'custom-gallery' ),
				),
				'description' => __( 'Pro kusovou výrobu je vhodnější zděné; ořez na čtverec komolí vysoké i podsadité kusy.', 'custom-gallery' ),
			)
		);

		$columns = array();
		foreach ( range( 1, 6 ) as $n ) {
			$columns[ (string) $n ] = (string) $n;
		}

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Sloupce', 'custom-gallery' ),
				'type'           => \Elementor\Controls_Manager::SELECT,
				'options'        => $columns,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'selectors'      => array(
					'{{WRAPPER}} .cg-gallery--grid'    => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
					'{{WRAPPER}} .cg-gallery--masonry' => 'column-count: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Mezera', 'custom-gallery' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'default'    => array(
					'unit' => 'px',
					'size' => 14,
				),
				'selectors'  => array(
					'{{WRAPPER}} .cg-gallery--grid'            => 'gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .cg-gallery--masonry'         => 'column-gap: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .cg-gallery--masonry .cg-piece' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'ratio',
			array(
				'label'     => __( 'Poměr stran', 'custom-gallery' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '1/1',
				'options'   => array(
					''     => __( 'Původní', 'custom-gallery' ),
					'1/1'  => '1:1',
					'4/3'  => '4:3',
					'3/4'  => '3:4  (závěsné)',
					'2/3'  => '2:3  (vysoké)',
					'16/9' => '16:9',
				),
				'condition' => array( 'layout' => 'grid' ),
				'selectors' => array(
					'{{WRAPPER}} .cg-gallery--grid .cg-frame'     => 'aspect-ratio: {{VALUE}};',
					'{{WRAPPER}} .cg-gallery--grid .cg-frame img' => 'height: 100%; object-fit: cover;',
				),
			)
		);

		$this->add_control(
			'radius',
			array(
				'label'      => __( 'Zaoblení rohů', 'custom-gallery' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .cg-piece' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		/* ================= Světlo ================= */

		$this->start_controls_section(
			'section_light',
			array(
				'label' => __( 'Světlo', 'custom-gallery' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'lit',
			array(
				'label'        => __( 'Rozsvítit při najetí', 'custom-gallery' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'description'  => __( 'Fotky jsou v klidu mírně ztlumené, po najetí se kus prosvětlí a prosákne teplá záře.', 'custom-gallery' ),
				'label_on'     => __( 'Zap', 'custom-gallery' ),
				'label_off'    => __( 'Vyp', 'custom-gallery' ),
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'glow_color',
			array(
				'label'     => __( 'Barva světla', 'custom-gallery' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => 'rgba(255, 176, 87, 0.34)',
				'condition' => array( 'lit' => 'yes' ),
				'selectors' => array(
					'{{WRAPPER}} .cg-gallery' => '--cg-glow: {{VALUE}}; --cg-glow-line: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'ground_color',
			array(
				'label'     => __( 'Podklad dlaždice', 'custom-gallery' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#101215',
				'selectors' => array(
					'{{WRAPPER}} .cg-gallery' => '--cg-ground: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		/* ================= Typografie štítku ================= */

		$this->start_controls_section(
			'section_plate',
			array(
				'label'     => __( 'Štítek', 'custom-gallery' ),
				'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => array( 'caption_source!' => '' ),
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'     => __( 'Barva názvu', 'custom-gallery' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .cg-gallery' => '--cg-name: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'name_typography',
				'selector' => '{{WRAPPER}} .cg-name',
			)
		);

		$this->add_control(
			'spec_color',
			array(
				'label'     => __( 'Barva specifikace', 'custom-gallery' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array(
					'{{WRAPPER}} .cg-gallery' => '--cg-spec: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'spec_typography',
				'selector' => '{{WRAPPER}} .cg-spec',
			)
		);

		$this->add_control(
			'plate_align',
			array(
				'label'     => __( 'Zarovnání', 'custom-gallery' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'separator' => 'before',
				'options'   => array(
					'left'   => array(
						'title' => __( 'Vlevo', 'custom-gallery' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center' => array(
						'title' => __( 'Na střed', 'custom-gallery' ),
						'icon'  => 'eicon-text-align-center',
					),
					'right'  => array(
						'title' => __( 'Vpravo', 'custom-gallery' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'left',
				'selectors' => array(
					'{{WRAPPER}} .cg-plate' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Sestaví štítek jednoho kusu.
	 *
	 * Text bere z nativních polí přílohy, aby nevznikalo druhé úložiště
	 * popisků vedle knihovny médií.
	 *
	 * @param int    $id       ID přílohy.
	 * @param string $source   Co zobrazit.
	 * @return string HTML nebo prázdný řetězec.
	 */
	private function get_plate( $id, $source ) {
		if ( '' === $source ) {
			return '';
		}

		$name = ( 'caption' !== $source ) ? trim( (string) get_the_title( $id ) ) : '';
		$spec = ( 'title' !== $source ) ? trim( (string) wp_get_attachment_caption( $id ) ) : '';

		if ( '' === $name && '' === $spec ) {
			return '';
		}

		$html = '';

		if ( '' !== $name ) {
			$html .= '<span class="cg-name">' . esc_html( $name ) . '</span>';
		}

		if ( '' !== $spec ) {
			$html .= '<span class="cg-spec">' . esc_html( $spec ) . '</span>';
		}

		return '<figcaption class="cg-plate">' . $html . '</figcaption>';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$ids      = cg_get_gallery_ids( isset( $settings['gallery_key'] ) ? $settings['gallery_key'] : '' );

		$limit = isset( $settings['limit'] ) ? absint( $settings['limit'] ) : 0;
		if ( $limit > 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		if ( empty( $ids ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<div class="cg-gallery-notice">%s</div>',
					esc_html__( 'Galerie je prázdná. Přidejte fotky v Média → Galerie a stránku obnovte.', 'custom-gallery' )
				);
			}
			return;
		}

		$source   = isset( $settings['caption_source'] ) ? $settings['caption_source'] : '';
		$position = isset( $settings['caption_position'] ) ? $settings['caption_position'] : 'below';
		$layout   = ( isset( $settings['layout'] ) && 'grid' === $settings['layout'] ) ? 'grid' : 'masonry';

		$classes = array( 'cg-gallery', 'cg-gallery--' . $layout );

		if ( isset( $settings['lit'] ) && 'yes' === $settings['lit'] ) {
			$classes[] = 'cg-gallery--lit';
		}

		if ( '' !== $source && 'overlay' === $position ) {
			$classes[] = 'cg-gallery--overlay';
		}

		$lightbox  = isset( $settings['open_lightbox'] ) && 'yes' === $settings['open_lightbox'];
		$slideshow = $this->get_id();

		printf( '<div class="%s">', esc_attr( implode( ' ', $classes ) ) );

		foreach ( $ids as $id ) {
			$image_html = \Elementor\Group_Control_Image_Size::get_attachment_image_html(
				array_merge( $settings, array( 'image' => array( 'id' => $id ) ) ),
				'thumbnail',
				'image'
			);

			if ( ! $image_html ) {
				continue;
			}

			$plate = $this->get_plate( $id, $source );

			echo '<figure class="cg-piece">';

			if ( $lightbox ) {
				printf(
					'<a class="cg-frame" href="%1$s" data-elementor-open-lightbox="yes" data-elementor-lightbox-slideshow="%2$s" data-elementor-lightbox-title="%3$s" data-elementor-lightbox-description="%4$s">%5$s</a>',
					esc_url( wp_get_attachment_image_url( $id, 'full' ) ),
					esc_attr( $slideshow ),
					esc_attr( get_the_title( $id ) ),
					esc_attr( (string) wp_get_attachment_caption( $id ) ),
					$image_html // phpcs:ignore WordPress.Security.EscapeOutput -- výstup wp_get_attachment_image().
				);
			} else {
				printf(
					'<span class="cg-frame">%s</span>',
					$image_html // phpcs:ignore WordPress.Security.EscapeOutput
				);
			}

			echo $plate; // phpcs:ignore WordPress.Security.EscapeOutput -- sestaveno v get_plate() s esc_html().

			echo '</figure>';
		}

		echo '</div>';
	}
}
