<?php
/**
 * GitHub-based update checker.
 *
 * Hooks into WordPress's plugin update transient so the Plugins list shows an
 * "Update Available" notice whenever a newer GitHub release exists, and lets
 * the standard one-click upgrader install it.
 *
 * Requires the GitHub repo to have at least one published release with a
 * semantic version tag (e.g. v1.0.1 or 1.0.1).
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_GitHub_Updater {

	const GITHUB_REPO = 'Glenn-castshadow/RSS_Feed_Plugin';

	/** Transient key for the cached release payload. */
	const TRANSIENT = 'wra_github_release';

	/** How long to cache the GitHub API response (12 hours). */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/** @var string Absolute path to the main plugin file. */
	private $plugin_file;

	/** @var string WordPress plugin basename (dir/file.php). */
	private $plugin_basename;

	/** @var string Plugin directory slug (the part before the slash). */
	private $plugin_slug;

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->plugin_slug     = dirname( $this->plugin_basename );
	}

	/**
	 * Register all hooks. Call once during plugin init (admin-only context is fine).
	 */
	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
	}

	// -------------------------------------------------------------------------
	// WordPress hooks
	// -------------------------------------------------------------------------

	/**
	 * Inject update info into the WP update transient when a newer release exists.
	 *
	 * @param object $transient The update_plugins transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		if ( version_compare( WRA_VERSION, $release['version'], '<' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'id'          => 'github.com/' . self::GITHUB_REPO,
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $release['version'],
				'url'         => 'https://github.com/' . self::GITHUB_REPO,
				'package'     => $release['zipball_url'],
				'icons'       => array(),
				'banners'     => array(),
				'requires'    => '5.8',
				'requires_php' => '7.4',
				'tested'      => '',
			);
		} else {
			// Ensure we don't show a stale "update available" notice.
			unset( $transient->response[ $this->plugin_basename ] );
		}

		return $transient;
	}

	/**
	 * Supply plugin details for the "View version details" modal.
	 *
	 * @param false|object|WP_Error $result Current result.
	 * @param string                $action API action.
	 * @param object                $args   Request args.
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'Curated RSS Aggregator',
			'slug'          => $this->plugin_slug,
			'version'       => $release['version'],
			'author'        => 'Glenn',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'download_link' => $release['zipball_url'],
			'requires'      => '5.8',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published_at'],
			'sections'      => array(
				'description' => 'Display RSS feeds anywhere and optionally import filtered feed items as WordPress posts.',
				'changelog'   => $release['changelog'],
			),
		);
	}

	/**
	 * Rename the extracted GitHub archive folder to match the installed plugin
	 * directory so WordPress replaces the correct files.
	 *
	 * GitHub names extracted folders like "Owner-Repo-{sha}/" which would create
	 * a new directory instead of overwriting the existing plugin.
	 *
	 * @param string      $source        Extracted source path.
	 * @param string      $remote_source Temp extraction directory.
	 * @param WP_Upgrader $upgrader      Upgrader instance.
	 * @param array       $hook_extra    Extra context passed by the upgrader.
	 * @return string Corrected source path.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if (
			empty( $hook_extra['plugin'] ) ||
			$hook_extra['plugin'] !== $this->plugin_basename
		) {
			return $source;
		}

		$target = trailingslashit( $remote_source ) . $this->plugin_slug . '/';

		if ( $source !== $target && $wp_filesystem->move( $source, $target ) ) {
			return $target;
		}

		return $source;
	}

	/**
	 * Clear the cached release after a successful plugin upgrade so the next
	 * admin page load reflects the newly-installed version.
	 *
	 * @param WP_Upgrader $upgrader   Upgrader instance.
	 * @param array       $hook_extra Extra data including action and plugin basename.
	 */
	public function purge_cache( $upgrader, $hook_extra ) {
		if (
			isset( $hook_extra['action'], $hook_extra['plugin'] ) &&
			'update' === $hook_extra['action'] &&
			$hook_extra['plugin'] === $this->plugin_basename
		) {
			delete_transient( self::TRANSIENT );
		}
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Fetch (or return cached) latest release info from the GitHub API.
	 *
	 * @return array|false {
	 *     @type string version      Cleaned version string (without leading "v").
	 *     @type string zipball_url  Download URL for the release source archive.
	 *     @type string changelog    HTML-formatted release notes.
	 *     @type string published_at ISO 8601 publish date.
	 * }
	 */
	private function get_latest_release() {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['tag_name'] ) || empty( $data['zipball_url'] ) ) {
			return false;
		}

		$release = array(
			'version'      => ltrim( $data['tag_name'], 'v' ),
			'zipball_url'  => $data['zipball_url'],
			'changelog'    => nl2br( esc_html( isset( $data['body'] ) ? $data['body'] : '' ) ),
			'published_at' => isset( $data['published_at'] ) ? $data['published_at'] : '',
		);

		set_transient( self::TRANSIENT, $release, self::CACHE_TTL );

		return $release;
	}
}
