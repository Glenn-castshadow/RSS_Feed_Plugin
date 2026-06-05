<?php
/**
 * Single owner of the plugin's persisted state.
 *
 * Wraps every get_option()/update_option() call behind instance methods so the
 * rest of the plugin can depend on an injected object instead of static global
 * accessors. Also the single source of truth for the settings schema defaults
 * (previously duplicated between activation and read paths).
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Settings_Repository {
	const SETTINGS_OPTION   = 'wra_settings';
	const IMPORTS_OPTION    = 'wra_import_jobs';
	const FEED_LISTS_OPTION = 'wra_feed_lists';
	const JOB_LOGS_OPTION   = 'wra_job_logs';

	/** How many run-log entries to retain per job. */
	const LOG_CAP = 20;

	/**
	 * Settings schema with default values. The single source of truth.
	 *
	 * @var array
	 */
	const DEFAULTS = array(
		'feeds'              => '',
		'cache_minutes'      => 60,
		'fallback_image'     => '',
		'fallback_image_ids' => '',
		'affiliate_name'     => '',
		'affiliate_value'    => '',
		'amazon_tag'         => '',
		'ai_provider'        => '',
		'ai_api_key'         => '',
		'ai_model'           => '',
	);

	/**
	 * Settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		return wp_parse_args( get_option( self::SETTINGS_OPTION, array() ), self::DEFAULTS );
	}

	/**
	 * Persist the settings array.
	 *
	 * @param array $settings Sanitized settings.
	 */
	public function save_settings( array $settings ) {
		update_option( self::SETTINGS_OPTION, $settings );
	}

	/**
	 * Resolve fallback image attachment IDs to full-size URLs.
	 *
	 * Falls back to the legacy single-URL setting when no IDs are saved.
	 *
	 * @return string[]
	 */
	public function get_fallback_images() {
		$settings = $this->get_settings();
		$ids      = array_filter( array_map( 'intval', explode( ',', $settings['fallback_image_ids'] ) ) );
		$urls     = array();

		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) {
				$urls[] = $url;
			}
		}

		// Backward compat: honour the old single-URL setting if nothing else is set.
		if ( empty( $urls ) && ! empty( $settings['fallback_image'] ) ) {
			$urls[] = $settings['fallback_image'];
		}

		return $urls;
	}

	/**
	 * Import jobs keyed by job ID.
	 *
	 * @return array
	 */
	public function get_import_jobs() {
		$jobs = get_option( self::IMPORTS_OPTION, array() );
		return is_array( $jobs ) ? $jobs : array();
	}

	/**
	 * Persist the import jobs array.
	 *
	 * @param array $jobs Jobs keyed by ID.
	 */
	public function save_import_jobs( array $jobs ) {
		update_option( self::IMPORTS_OPTION, $jobs );
	}

	/**
	 * Named feed lists keyed by slug.
	 *
	 * @return array
	 */
	public function get_feed_lists() {
		$lists = get_option( self::FEED_LISTS_OPTION, array() );
		return is_array( $lists ) ? $lists : array();
	}

	/**
	 * Persist the feed lists array.
	 *
	 * @param array $lists Lists keyed by slug.
	 */
	public function save_feed_lists( array $lists ) {
		update_option( self::FEED_LISTS_OPTION, $lists );
	}

	// ---------------------------------------------------------------------
	// Run logs — stored apart from job config so the cron importer never has
	// to read-modify-write the whole jobs blob just to append a log line.
	// ---------------------------------------------------------------------

	/**
	 * All run logs keyed by job ID.
	 *
	 * @return array
	 */
	public function get_all_job_logs() {
		$logs = get_option( self::JOB_LOGS_OPTION, array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Run log entries for a single job, newest first.
	 *
	 * @param string $job_id Job ID.
	 * @return array
	 */
	public function get_job_log( $job_id ) {
		$logs = $this->get_all_job_logs();
		return isset( $logs[ $job_id ] ) && is_array( $logs[ $job_id ] ) ? $logs[ $job_id ] : array();
	}

	/**
	 * Prepend a run-log entry for a job, capped at self::LOG_CAP.
	 *
	 * Re-reads the logs option immediately before writing to shrink the
	 * concurrent-write window between cron and a manual "Run now".
	 *
	 * @param string $job_id Job ID.
	 * @param array  $entry  Log entry (time, imported, skipped, warnings).
	 */
	public function append_job_log( $job_id, array $entry ) {
		if ( '' === (string) $job_id ) {
			return;
		}

		wp_cache_delete( self::JOB_LOGS_OPTION, 'options' );
		$logs            = $this->get_all_job_logs();
		$existing        = isset( $logs[ $job_id ] ) ? (array) $logs[ $job_id ] : array();
		$logs[ $job_id ] = $this->build_log_stack( $existing, $entry, self::LOG_CAP );
		update_option( self::JOB_LOGS_OPTION, $logs );
	}

	/**
	 * Drop the stored log for a job (called when the job is deleted).
	 *
	 * @param string $job_id Job ID.
	 */
	public function delete_job_log( $job_id ) {
		$logs = $this->get_all_job_logs();
		if ( isset( $logs[ $job_id ] ) ) {
			unset( $logs[ $job_id ] );
			update_option( self::JOB_LOGS_OPTION, $logs );
		}
	}

	/**
	 * Pure log-stack builder: prepend newest, cap at $cap. No I/O.
	 *
	 * @param array $existing Existing entries (newest first).
	 * @param array $entry    New entry to prepend.
	 * @param int   $cap      Maximum entries to keep.
	 * @return array
	 */
	public function build_log_stack( array $existing, array $entry, $cap ) {
		array_unshift( $existing, $entry );
		return array_slice( $existing, 0, max( 1, (int) $cap ) );
	}

	/**
	 * One-time migration: lift run logs that older versions stored inline on
	 * each job into the dedicated logs option, then strip them from the jobs.
	 *
	 * Idempotent — safe to call on every load.
	 */
	public function migrate_inline_logs() {
		$jobs = $this->get_import_jobs();
		if ( empty( $jobs ) ) {
			return;
		}

		$logs    = $this->get_all_job_logs();
		$changed = false;

		foreach ( $jobs as $job_id => $job ) {
			if ( isset( $job['log'] ) ) {
				if ( ! isset( $logs[ $job_id ] ) && ! empty( $job['log'] ) ) {
					$logs[ $job_id ] = (array) $job['log'];
				}
				unset( $jobs[ $job_id ]['log'] );
				$changed = true;
			}
		}

		if ( $changed ) {
			$this->save_import_jobs( $jobs );
			update_option( self::JOB_LOGS_OPTION, $logs );
		}
	}
}
