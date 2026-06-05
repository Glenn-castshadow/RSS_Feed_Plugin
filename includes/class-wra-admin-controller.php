<?php
/**
 * Admin POST routing and write-side logic.
 *
 * Owns everything that mutates plugin state: settings/job/feed-list saves,
 * OPML import, JSON export/import, cache clearing, and update checks. Rendering
 * lives in WRA_Admin_View; coordination/hooks live in WRA_Admin.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Admin_Controller {
	/**
	 * Settings repository.
	 *
	 * @var WRA_Settings_Repository
	 */
	private $repo;

	/**
	 * Importer.
	 *
	 * @var WRA_Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @param WRA_Settings_Repository $repo     Settings repository.
	 * @param WRA_Importer            $importer Importer.
	 */
	public function __construct( WRA_Settings_Repository $repo, WRA_Importer $importer ) {
		$this->repo     = $repo;
		$this->importer = $importer;
	}

	/**
	 * Route an admin form submission to its handler.
	 *
	 * Hooked on admin_init.
	 */
	public function handle() {
		if ( empty( $_POST['wra_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'wra_admin_action' );

		$action = sanitize_key( wp_unslash( $_POST['wra_action'] ) );

		$handlers = array(
			'save_settings'     => 'save_settings',
			'save_job'          => 'save_job',
			'delete_job'        => 'delete_job',
			'run_job'           => 'run_job',
			'clear_feed_cache'  => 'clear_feed_cache',
			'check_for_updates' => 'check_for_updates',
			'import_opml'       => 'import_opml',
			'export_settings'   => 'export_settings',
			'import_settings'   => 'import_settings',
			'save_feed_list'    => 'save_feed_list',
			'delete_feed_list'  => 'delete_feed_list',
		);

		if ( isset( $handlers[ $action ] ) ) {
			$this->{ $handlers[ $action ] }();
		}
	}

	// ---------------------------------------------------------------------
	// Action handlers
	// ---------------------------------------------------------------------

	/** Save the global settings form. */
	private function save_settings() {
		$this->repo->save_settings( $this->sanitize_settings( $_POST ) );
		$this->redirect_with_message( 'settings_saved' );
	}

	/** Create or update an import job. */
	private function save_job() {
		$jobs   = $this->repo->get_import_jobs();
		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';

		if ( empty( $job_id ) ) {
			$job_id = uniqid( 'job_' );
		}

		$jobs[ $job_id ] = $this->sanitize_job( $_POST, $job_id );
		$this->repo->save_import_jobs( $jobs );
		$this->redirect_with_message( 'job_saved' );
	}

	/** Delete an import job and its run log. */
	private function delete_job() {
		$jobs   = $this->repo->get_import_jobs();
		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		unset( $jobs[ $job_id ] );
		$this->repo->save_import_jobs( $jobs );
		$this->repo->delete_job_log( $job_id );
		$this->redirect_with_message( 'job_deleted' );
	}

	/** Run an import job immediately. */
	private function run_job() {
		$jobs   = $this->repo->get_import_jobs();
		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		$result = isset( $jobs[ $job_id ] ) ? $this->importer->run_job( $jobs[ $job_id ] ) : array( 'imported' => 0, 'skipped' => 0 );
		$this->redirect_with_message( 'job_ran', $result );
	}

	/** Flush cached feeds. */
	private function clear_feed_cache() {
		$this->flush_feed_cache();
		$this->redirect_with_message( 'cache_cleared' );
	}

	/** Force a re-check for plugin updates. */
	private function check_for_updates() {
		delete_transient( WRA_GitHub_Updater::TRANSIENT );
		delete_site_transient( 'update_plugins' );
		$this->redirect_with_message( 'update_check_done' );
	}

	/** Import feed URLs from an uploaded OPML file. */
	private function import_opml() {
		$added = $this->handle_opml_import();
		$this->redirect_with_message( 'opml_imported', array( 'added' => $added ) );
	}

	/** Stream settings/jobs to the browser as JSON (exits). */
	private function export_settings() {
		$settings               = $this->repo->get_settings();
		$settings['ai_api_key'] = ''; // Never export credentials.

		$export = array(
			'plugin'   => 'curated-rss-aggregator',
			'version'  => WRA_VERSION,
			'exported' => gmdate( 'c' ),
			'settings' => $settings,
			'jobs'     => $this->repo->get_import_jobs(),
		);

		$filename = 'wra-settings-' . gmdate( 'Y-m-d' ) . '.json';
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded plugin data.
		echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** Import settings/jobs from an uploaded JSON file. */
	private function import_settings() {
		$ok = $this->import_settings_file();
		$this->redirect_with_message( $ok ? 'settings_imported' : 'settings_import_failed' );
	}

	/** Create or update a named feed list. */
	private function save_feed_list() {
		$lists   = $this->repo->get_feed_lists();
		$list_id = isset( $_POST['list_id'] ) ? sanitize_key( wp_unslash( $_POST['list_id'] ) ) : '';

		if ( empty( $list_id ) ) {
			$slug    = isset( $_POST['list_slug'] ) ? sanitize_title( wp_unslash( $_POST['list_slug'] ) ) : '';
			$list_id = $slug;
		}

		if ( ! empty( $list_id ) ) {
			$lists[ $list_id ] = $this->sanitize_feed_list( $_POST, $list_id );
			$this->repo->save_feed_lists( $lists );
		}
		$this->redirect_with_message( 'feed_list_saved' );
	}

	/** Delete a named feed list. */
	private function delete_feed_list() {
		$lists   = $this->repo->get_feed_lists();
		$list_id = isset( $_POST['list_id'] ) ? sanitize_key( wp_unslash( $_POST['list_id'] ) ) : '';
		unset( $lists[ $list_id ] );
		$this->repo->save_feed_lists( $lists );
		$this->redirect_with_message( 'feed_list_deleted' );
	}

	// ---------------------------------------------------------------------
	// Sanitizers
	// ---------------------------------------------------------------------

	/**
	 * Sanitize the settings form.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	private function sanitize_settings( $data ) {
		$existing = $this->repo->get_settings();
		$ai_key   = isset( $data['ai_api_key'] ) ? trim( wp_unslash( $data['ai_api_key'] ) ) : '';

		$raw_ids       = isset( $data['fallback_image_ids'] ) ? sanitize_text_field( wp_unslash( $data['fallback_image_ids'] ) ) : '';
		$sanitized_ids = implode( ',', array_filter( array_map( 'absint', explode( ',', $raw_ids ) ) ) );

		return array(
			'feeds'              => isset( $data['feeds'] ) ? $this->sanitize_multiline_urls( wp_unslash( $data['feeds'] ) ) : '',
			'cache_minutes'      => isset( $data['cache_minutes'] ) ? max( 5, absint( $data['cache_minutes'] ) ) : 60,
			'fallback_image'     => '',
			'fallback_image_ids' => $sanitized_ids,
			'affiliate_name'     => isset( $data['affiliate_name'] ) ? sanitize_key( wp_unslash( $data['affiliate_name'] ) ) : '',
			'affiliate_value' => isset( $data['affiliate_value'] ) ? sanitize_text_field( wp_unslash( $data['affiliate_value'] ) ) : '',
			'amazon_tag'      => isset( $data['amazon_tag'] ) ? sanitize_text_field( wp_unslash( $data['amazon_tag'] ) ) : '',
			'ai_provider'     => isset( $data['ai_provider'] ) ? sanitize_key( wp_unslash( $data['ai_provider'] ) ) : '',
			'ai_api_key'      => '' !== $ai_key ? sanitize_text_field( $ai_key ) : $existing['ai_api_key'],
			'ai_model'        => isset( $data['ai_model'] ) ? sanitize_text_field( wp_unslash( $data['ai_model'] ) ) : '',
		);
	}

	/**
	 * Sanitize the job form.
	 *
	 * @param array  $data   Raw data.
	 * @param string $job_id Job ID.
	 * @return array
	 */
	private function sanitize_job( $data, $job_id ) {
		$valid_ai_modes = array( 'none', 'rewrite', 'summarize' );
		$ai_mode        = isset( $data['ai_mode'] ) ? sanitize_key( wp_unslash( $data['ai_mode'] ) ) : 'none';

		return array(
			'id'                   => $job_id,
			'name'                 => isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : __( 'Untitled import', 'curated-rss-aggregator' ),
			'feeds'                => isset( $data['feeds'] ) ? $this->sanitize_multiline_urls( wp_unslash( $data['feeds'] ) ) : '',
			'limit'                => isset( $data['limit'] ) ? max( 1, min( 50, absint( $data['limit'] ) ) ) : 10,
			'frequency'            => isset( $data['frequency'] ) ? max( 15, absint( $data['frequency'] ) ) : 30,
			'post_status'          => isset( $data['post_status'] ) ? sanitize_key( wp_unslash( $data['post_status'] ) ) : 'draft',
			'post_type'            => isset( $data['post_type'] ) ? sanitize_key( wp_unslash( $data['post_type'] ) ) : 'post',
			'category'             => isset( $data['category'] ) ? absint( $data['category'] ) : 0,
			'tags'                 => isset( $data['tags'] ) ? sanitize_text_field( wp_unslash( $data['tags'] ) ) : '',
			'include_keywords'     => isset( $data['include_keywords'] ) ? sanitize_text_field( wp_unslash( $data['include_keywords'] ) ) : '',
			'exclude_keywords'     => isset( $data['exclude_keywords'] ) ? sanitize_text_field( wp_unslash( $data['exclude_keywords'] ) ) : '',
			'advanced_filter_mode' => isset( $data['advanced_filter_mode'] ) && 'any' === sanitize_key( wp_unslash( $data['advanced_filter_mode'] ) ) ? 'any' : 'all',
			'advanced_filters'     => isset( $data['advanced_filters'] ) ? $this->sanitize_advanced_filters( wp_unslash( $data['advanced_filters'] ) ) : array(),
			'category_mappings'    => isset( $data['category_mappings'] ) ? $this->sanitize_category_mappings( wp_unslash( $data['category_mappings'] ) ) : array(),
			'fallback_image_url'   => isset( $data['fallback_image_url'] ) ? esc_url_raw( wp_unslash( $data['fallback_image_url'] ) ) : '',
			'date_after'           => isset( $data['date_after'] ) ? sanitize_text_field( wp_unslash( $data['date_after'] ) ) : '',
			'date_before'          => isset( $data['date_before'] ) ? sanitize_text_field( wp_unslash( $data['date_before'] ) ) : '',
			'enabled'              => ! empty( $data['enabled'] ),
			'import_full_post'     => ! empty( $data['import_full_post'] ),
			'use_full_content'     => ! empty( $data['use_full_content'] ),
			'full_text_extraction' => ! empty( $data['full_text_extraction'] ),
			'save_featured_image'  => ! empty( $data['save_featured_image'] ),
			'preserve_date'        => ! empty( $data['preserve_date'] ),
			'enable_canonical'     => ! empty( $data['enable_canonical'] ),
			'ai_mode'              => in_array( $ai_mode, $valid_ai_modes, true ) ? $ai_mode : 'none',
			'ai_prompt'            => isset( $data['ai_prompt'] ) ? sanitize_textarea_field( wp_unslash( $data['ai_prompt'] ) ) : '',
		);
	}

	/**
	 * Parse structured filter rules from textarea input.
	 *
	 * @param string|array $value Raw textarea value or imported array.
	 * @return array
	 */
	private function sanitize_advanced_filters( $value ) {
		if ( is_array( $value ) ) {
			$rows = $value;
		} else {
			$rows = preg_split( '/[\r\n]+/', (string) $value );
		}

		$allowed_fields    = array( 'title', 'description', 'content', 'author', 'image', 'source_feed', 'date' );
		$allowed_operators = array( 'contains', 'not_contains', 'equals', 'not_equals', 'empty', 'not_empty', 'regex', 'date_after', 'date_before' );
		$filters           = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$field    = isset( $row['field'] ) ? sanitize_key( $row['field'] ) : '';
				$operator = isset( $row['operator'] ) ? sanitize_key( $row['operator'] ) : '';
				$raw      = isset( $row['value'] ) ? $row['value'] : '';
			} else {
				$parts = array_map( 'trim', explode( '|', (string) $row, 3 ) );
				if ( count( $parts ) < 2 ) {
					continue;
				}
				$field    = sanitize_key( $parts[0] );
				$operator = sanitize_key( $parts[1] );
				$raw      = isset( $parts[2] ) ? $parts[2] : '';
			}

			if ( ! in_array( $field, $allowed_fields, true ) || ! in_array( $operator, $allowed_operators, true ) ) {
				continue;
			}

			$filters[] = array(
				'field'    => $field,
				'operator' => $operator,
				'value'    => sanitize_text_field( $raw ),
			);
		}

		return $filters;
	}

	/**
	 * Parse keyword-to-category mappings from textarea input.
	 *
	 * @param string|array $value Raw textarea value or imported array.
	 * @return array
	 */
	private function sanitize_category_mappings( $value ) {
		if ( is_array( $value ) ) {
			$rows = $value;
		} else {
			$rows = preg_split( '/[\r\n]+/', (string) $value );
		}

		$mappings = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$keywords = isset( $row['keywords'] ) ? $row['keywords'] : '';
				$category = isset( $row['category'] ) ? $row['category'] : '';
			} else {
				$parts = array_map( 'trim', explode( '=>', (string) $row, 2 ) );
				if ( count( $parts ) < 2 ) {
					continue;
				}
				$keywords = $parts[0];
				$category = $parts[1];
			}

			if ( '' === trim( (string) $keywords ) || '' === trim( (string) $category ) ) {
				continue;
			}

			$mappings[] = array(
				'keywords' => sanitize_text_field( $keywords ),
				'category' => sanitize_text_field( $category ),
			);
		}

		return $mappings;
	}

	/**
	 * Sanitize feed list form data.
	 *
	 * @param array  $data    Raw POST data.
	 * @param string $list_id List slug/ID.
	 * @return array
	 */
	private function sanitize_feed_list( $data, $list_id ) {
		return array(
			'id'    => $list_id,
			'name'  => isset( $data['list_name'] ) ? sanitize_text_field( wp_unslash( $data['list_name'] ) ) : $list_id,
			'feeds' => isset( $data['list_feeds'] ) ? $this->sanitize_multiline_urls( wp_unslash( $data['list_feeds'] ) ) : '',
		);
	}

	/**
	 * Sanitize line-separated URLs.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_multiline_urls( $value ) {
		$urls = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $value ) ) );
		$urls = array_filter( array_map( 'esc_url_raw', $urls ) );

		return implode( "\n", $urls );
	}

	// ---------------------------------------------------------------------
	// OPML / import-export / cache
	// ---------------------------------------------------------------------

	/**
	 * Delete all SimplePie feed transients from the options table.
	 */
	private function flush_feed_cache() {
		global $wpdb;
		$like_value   = $wpdb->esc_like( '_transient_feed_' ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_feed_' ) . '%';
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like_value,
				$like_timeout
			)
		);
	}

	/**
	 * Parse an uploaded OPML file and merge/replace the global feed list.
	 *
	 * @return int Number of new feed URLs added.
	 */
	private function handle_opml_import() {
		if ( empty( $_FILES['opml_file']['tmp_name'] ) ) {
			return 0;
		}

		$upload_error = isset( $_FILES['opml_file']['error'] ) ? (int) $_FILES['opml_file']['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			return 0;
		}

		$tmp = isset( $_FILES['opml_file']['tmp_name'] ) ? wp_unslash( $_FILES['opml_file']['tmp_name'] ) : '';
		if ( ! is_uploaded_file( $tmp ) ) {
			return 0;
		}

		$original_name = isset( $_FILES['opml_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['opml_file']['name'] ) ) : '';
		$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'opml', 'xml' ), true ) ) {
			return 0;
		}

		$urls = $this->parse_opml_urls( $tmp );
		if ( empty( $urls ) ) {
			return 0;
		}

		$mode     = isset( $_POST['opml_mode'] ) && 'replace' === $_POST['opml_mode'] ? 'replace' : 'merge';
		$settings = $this->repo->get_settings();

		$existing = 'replace' === $mode
			? array()
			: array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $settings['feeds'] ) ) );

		$merged = array_values( array_unique( array_merge( $existing, $urls ) ) );
		$added  = count( $merged ) - count( $existing );

		$settings['feeds'] = implode( "\n", $merged );
		$this->repo->save_settings( $settings );

		return max( 0, $added );
	}

	/**
	 * Load and parse an OPML file, returning an array of feed URLs.
	 *
	 * @param string $file_path Absolute path to the temporary uploaded file.
	 * @return string[]
	 */
	private function parse_opml_urls( $file_path ) {
		libxml_use_internal_errors( true );
		// LIBXML_NONET blocks any network access (external entity) during parse.
		$xml = simplexml_load_file( $file_path, 'SimpleXMLElement', LIBXML_NONET );
		libxml_clear_errors();

		if ( false === $xml || ! isset( $xml->body ) ) {
			return array();
		}

		$urls = array();
		$this->extract_opml_urls( $xml->body, $urls );
		return $urls;
	}

	/**
	 * Recursively collect xmlUrl values from OPML outline elements.
	 *
	 * @param \SimpleXMLElement $node Current node.
	 * @param string[]          $urls Accumulator passed by reference.
	 */
	private function extract_opml_urls( $node, &$urls ) {
		foreach ( $node->outline as $outline ) {
			$xml_url = (string) $outline['xmlUrl'];
			if ( ! empty( $xml_url ) ) {
				$clean = esc_url_raw( $xml_url );
				if ( $clean ) {
					$urls[] = $clean;
				}
			}
			if ( $outline->outline ) {
				$this->extract_opml_urls( $outline, $urls );
			}
		}
	}

	/**
	 * Import settings and jobs from an uploaded JSON file.
	 *
	 * @return bool True on success, false on any validation or parse failure.
	 */
	private function import_settings_file() {
		if ( empty( $_FILES['settings_file']['tmp_name'] ) ) {
			return false;
		}

		$upload_error = isset( $_FILES['settings_file']['error'] ) ? (int) $_FILES['settings_file']['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			return false;
		}

		$tmp = isset( $_FILES['settings_file']['tmp_name'] ) ? wp_unslash( $_FILES['settings_file']['tmp_name'] ) : '';
		if ( ! is_uploaded_file( $tmp ) ) {
			return false;
		}

		$original_name = isset( $_FILES['settings_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['settings_file']['name'] ) ) : '';
		if ( 'json' !== strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a local tmp file.
		$raw = file_get_contents( $tmp );
		if ( false === $raw ) {
			return false;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return false;
		}

		// Merge settings, keeping the existing API key if the export omitted it.
		$this->repo->save_settings( $this->sanitize_settings( $data['settings'] ) );

		// Merge jobs (added or overwritten by ID; run logs are stored separately
		// and left untouched by the import).
		if ( ! empty( $data['jobs'] ) && is_array( $data['jobs'] ) ) {
			$existing_jobs = $this->repo->get_import_jobs();
			foreach ( $data['jobs'] as $raw_id => $raw_job ) {
				$job_id = sanitize_key( (string) $raw_id );
				if ( empty( $job_id ) || ! is_array( $raw_job ) ) {
					continue;
				}
				$raw_job['id']            = $job_id;
				$existing_jobs[ $job_id ] = $this->sanitize_job( $raw_job, $job_id );
			}
			$this->repo->save_import_jobs( $existing_jobs );
		}

		return true;
	}

	/**
	 * Redirect after a POST handler completes.
	 *
	 * @param string $message Message key.
	 * @param array  $args    Extra query args.
	 */
	private function redirect_with_message( $message, $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => 'wra', 'wra_message' => $message ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
