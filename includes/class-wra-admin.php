<?php
/**
 * Admin coordinator.
 *
 * Owns the menu, asset enqueue, and WordPress hook wiring, then delegates POST
 * handling to WRA_Admin_Controller and rendering to WRA_Admin_View.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Admin {
	/**
	 * Write-side controller.
	 *
	 * @var WRA_Admin_Controller
	 */
	private $controller;

	/**
	 * Read-side view.
	 *
	 * @var WRA_Admin_View
	 */
	private $view;

	/**
	 * Constructor.
	 *
	 * @param WRA_Feed_Fetcher        $fetcher  Feed fetcher.
	 * @param WRA_Importer            $importer Importer.
	 * @param WRA_Settings_Repository $repo     Settings repository.
	 */
	public function __construct( WRA_Feed_Fetcher $fetcher, WRA_Importer $importer, WRA_Settings_Repository $repo ) {
		$this->controller = new WRA_Admin_Controller( $repo, $importer );
		$this->view       = new WRA_Admin_View( $repo, $fetcher );

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this->controller, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the top-level admin menu.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'RSS Aggregator', 'curated-rss-aggregator' ),
			__( 'RSS Aggregator', 'curated-rss-aggregator' ),
			'manage_options',
			'wra',
			array( $this->view, 'render_page' ),
			'dashicons-rss',
			58
		);
	}

	/**
	 * Enqueue admin assets on the plugin page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wra' !== $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'wra-admin', WRA_PLUGIN_URL . 'assets/css/admin.css', array(), WRA_VERSION );
		wp_enqueue_script( 'wra-admin', WRA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), WRA_VERSION, true );
		wp_localize_script(
			'wra-admin',
			'wra_admin',
			array(
				'media_title'  => __( 'Select Fallback Images', 'curated-rss-aggregator' ),
				'media_button' => __( 'Use these images', 'curated-rss-aggregator' ),
			)
		);
	}
}
