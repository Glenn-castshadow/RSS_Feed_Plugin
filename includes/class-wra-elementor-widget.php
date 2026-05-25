<?php
/**
 * Elementor widget for Curated RSS Feed.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'curated_rss_feed';
	}

	public function get_title() {
		return __( 'Curated RSS Feed', 'curated-rss-aggregator' );
	}

	public function get_icon() {
		return 'eicon-rss';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'rss', 'feed', 'news', 'aggregator' );
	}

	public function get_style_depends() {
		return array( 'wra-public' );
	}

	protected function register_controls() {
		/* ── Feed Settings ─────────────────────────────────────── */
		$this->start_controls_section(
			'section_feed',
			array( 'label' => __( 'Feed Settings', 'curated-rss-aggregator' ) )
		);

		$this->add_control(
			'feeds',
			array(
				'label'       => __( 'Feed URLs (one per line)', 'curated-rss-aggregator' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 4,
				'description' => __( 'Leave blank to use the plugin default feeds.', 'curated-rss-aggregator' ),
			)
		);

		$this->add_control(
			'items',
			array(
				'label'   => __( 'Items', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 24,
				'default' => 6,
			)
		);

		$this->add_control(
			'per_feed',
			array(
				'label'       => __( 'Max per feed', 'curated-rss-aggregator' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 0,
				'description' => __( '0 = no limit. Prevents one active feed from filling all slots.', 'curated-rss-aggregator' ),
			)
		);

		$this->end_controls_section();

		/* ── Style ─────────────────────────────────────────────── */
		$this->start_controls_section(
			'section_style',
			array( 'label' => __( 'Style', 'curated-rss-aggregator' ) )
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Layout', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'    => __( 'Grid', 'curated-rss-aggregator' ),
					'list'    => __( 'List', 'curated-rss-aggregator' ),
					'compact' => __( 'Compact', 'curated-rss-aggregator' ),
				),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'     => __( 'Columns (0 = auto)', 'curated-rss-aggregator' ),
				'type'      => \Elementor\Controls_Manager::NUMBER,
				'min'       => 0,
				'max'       => 6,
				'default'   => 0,
				'condition' => array( 'layout' => 'grid' ),
			)
		);

		$this->add_control(
			'card_style',
			array(
				'label'   => __( 'Card style', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'default',
				'options' => array(
					'default' => __( 'Default (border)', 'curated-rss-aggregator' ),
					'shadow'  => __( 'Shadow', 'curated-rss-aggregator' ),
					'flat'    => __( 'Flat', 'curated-rss-aggregator' ),
					'outline' => __( 'Outline', 'curated-rss-aggregator' ),
					'none'    => __( 'None', 'curated-rss-aggregator' ),
				),
			)
		);

		$this->add_control(
			'image_ratio',
			array(
				'label'     => __( 'Image ratio', 'curated-rss-aggregator' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '16-9',
				'options'   => array(
					'16-9' => '16 : 9',
					'3-2'  => '3 : 2',
					'4-3'  => '4 : 3',
					'1-1'  => '1 : 1',
				),
				'condition' => array( 'show_image' => 'yes' ),
			)
		);

		$this->end_controls_section();

		/* ── Display ───────────────────────────────────────────── */
		$this->start_controls_section(
			'section_display',
			array( 'label' => __( 'Display', 'curated-rss-aggregator' ) )
		);

		$this->add_control(
			'show_image',
			array(
				'label'   => __( 'Show image', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_date',
			array(
				'label'   => __( 'Show date', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_source',
			array(
				'label'   => __( 'Show source domain', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'show_author',
			array(
				'label'   => __( 'Show author', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'show_excerpt',
			array(
				'label'   => __( 'Show excerpt', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'max_chars',
			array(
				'label'       => __( 'Max excerpt characters', 'curated-rss-aggregator' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => 0,
				'description' => __( '0 = no limit', 'curated-rss-aggregator' ),
				'condition'   => array( 'show_excerpt' => 'yes' ),
			)
		);

		$this->add_control(
			'show_read_more',
			array(
				'label'   => __( 'Show "Read more" link', 'curated-rss-aggregator' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'read_more_text',
			array(
				'label'       => __( 'Read more label', 'curated-rss-aggregator' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'Read more', 'curated-rss-aggregator' ),
				'condition'   => array( 'show_read_more' => 'yes' ),
			)
		);

		$this->end_controls_section();

		/* ── Keyword Filters ────────────────────────────────────── */
		$this->start_controls_section(
			'section_keywords',
			array( 'label' => __( 'Keyword Filters', 'curated-rss-aggregator' ) )
		);

		$this->add_control(
			'include_keywords',
			array(
				'label'       => __( 'Include keywords', 'curated-rss-aggregator' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Comma-separated. Item must match at least one.', 'curated-rss-aggregator' ),
			)
		);

		$this->add_control(
			'exclude_keywords',
			array(
				'label'       => __( 'Exclude keywords', 'curated-rss-aggregator' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'description' => __( 'Comma-separated. Items containing any of these are hidden.', 'curated-rss-aggregator' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		// Elementor SWITCHER returns 'yes' or '' — map '' to 'no' for the shortcode renderer.
		foreach ( array( 'show_image', 'show_date', 'show_source', 'show_author', 'show_excerpt', 'show_read_more' ) as $key ) {
			$s[ $key ] = ! empty( $s[ $key ] ) ? 'yes' : 'no';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo WRA_Plugin::get_shortcode()->render( $s );
	}
}
