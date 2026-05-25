<?php
/**
 * Admin UI.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Admin {
	/**
	 * Feed fetcher.
	 *
	 * @var WRA_Feed_Fetcher
	 */
	private $fetcher;

	/**
	 * Importer.
	 *
	 * @var WRA_Importer
	 */
	private $importer;

	/**
	 * Constructor.
	 *
	 * @param WRA_Feed_Fetcher $fetcher Feed fetcher.
	 * @param WRA_Importer     $importer Importer.
	 */
	public function __construct( WRA_Feed_Fetcher $fetcher, WRA_Importer $importer ) {
		$this->fetcher  = $fetcher;
		$this->importer = $importer;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_posts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'RSS Aggregator', 'curated-rss-aggregator' ),
			__( 'RSS Aggregator', 'curated-rss-aggregator' ),
			'manage_options',
			'wra',
			array( $this, 'render_page' ),
			'dashicons-rss',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_wra' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wra-admin', WRA_PLUGIN_URL . 'assets/css/admin.css', array(), WRA_VERSION );
		wp_enqueue_script( 'wra-admin', WRA_PLUGIN_URL . 'assets/js/admin.js', array(), WRA_VERSION, true );
	}

	/**
	 * Handle admin form posts.
	 */
	public function handle_posts() {
		if ( empty( $_POST['wra_action'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'wra_admin_action' );

		$action = sanitize_key( wp_unslash( $_POST['wra_action'] ) );

		if ( 'save_settings' === $action ) {
			update_option( WRA_Plugin::SETTINGS_OPTION, $this->sanitize_settings( $_POST ) );
			$this->redirect_with_message( 'settings_saved' );
		}

		if ( 'save_job' === $action ) {
			$jobs   = WRA_Plugin::get_import_jobs();
			$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';

			if ( empty( $job_id ) ) {
				$job_id = uniqid( 'job_' );
			}

			$jobs[ $job_id ] = $this->sanitize_job( $_POST, $job_id );
			update_option( WRA_Plugin::IMPORTS_OPTION, $jobs );
			$this->redirect_with_message( 'job_saved' );
		}

		if ( 'delete_job' === $action ) {
			$jobs   = WRA_Plugin::get_import_jobs();
			$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
			unset( $jobs[ $job_id ] );
			update_option( WRA_Plugin::IMPORTS_OPTION, $jobs );
			$this->redirect_with_message( 'job_deleted' );
		}

		if ( 'run_job' === $action ) {
			$jobs   = WRA_Plugin::get_import_jobs();
			$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
			$result = isset( $jobs[ $job_id ] ) ? $this->importer->run_job( $jobs[ $job_id ] ) : array( 'imported' => 0, 'skipped' => 0 );
			$this->redirect_with_message( 'job_ran', $result );
		}
	}

	/**
	 * Render admin page.
	 */
	public function render_page() {
		$settings = WRA_Plugin::get_settings();
		$jobs     = WRA_Plugin::get_import_jobs();
		$edit_job = $this->get_edit_job( $jobs );
		$preview  = $this->get_preview_items( $settings );
		?>
		<div class="wrap wra-admin">
			<h1><?php esc_html_e( 'Curated RSS Aggregator', 'curated-rss-aggregator' ); ?></h1>
			<?php $this->render_notice(); ?>

			<div class="wra-admin__grid">
				<section class="wra-panel">
					<h2><?php esc_html_e( 'Display Feeds', 'curated-rss-aggregator' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'wra_admin_action' ); ?>
						<input type="hidden" name="wra_action" value="save_settings">

						<label for="wra-feeds"><?php esc_html_e( 'Default feed URLs', 'curated-rss-aggregator' ); ?></label>
						<textarea id="wra-feeds" name="feeds" rows="7" placeholder="https://example.com/feed"><?php echo esc_textarea( $settings['feeds'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Add one feed URL per line. Shortcodes can override this list.', 'curated-rss-aggregator' ); ?></p>

						<div class="wra-fields">
							<p>
								<label for="wra-cache"><?php esc_html_e( 'Cache minutes', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-cache" type="number" min="5" name="cache_minutes" value="<?php echo esc_attr( $settings['cache_minutes'] ); ?>">
							</p>
							<p>
								<label for="wra-fallback"><?php esc_html_e( 'Fallback image URL', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-fallback" type="url" name="fallback_image" value="<?php echo esc_url( $settings['fallback_image'] ); ?>">
							</p>
						</div>

						<h3><?php esc_html_e( 'Referral Parameters', 'curated-rss-aggregator' ); ?></h3>
						<div class="wra-fields">
							<p>
								<label for="wra-affiliate-name"><?php esc_html_e( 'Query name', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-affiliate-name" type="text" name="affiliate_name" value="<?php echo esc_attr( $settings['affiliate_name'] ); ?>" placeholder="ref">
							</p>
							<p>
								<label for="wra-affiliate-value"><?php esc_html_e( 'Query value', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-affiliate-value" type="text" name="affiliate_value" value="<?php echo esc_attr( $settings['affiliate_value'] ); ?>" placeholder="partner-id">
							</p>
						</div>

						<h3><?php esc_html_e( 'Amazon Associates', 'curated-rss-aggregator' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Adds your Associates tag to Amazon product links in feed displays and imported post content.', 'curated-rss-aggregator' ); ?></p>
						<div class="wra-fields">
							<p>
								<label for="wra-amazon-tag"><?php esc_html_e( 'Associates tag', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-amazon-tag" type="text" name="amazon_tag" value="<?php echo esc_attr( $settings['amazon_tag'] ); ?>" placeholder="yourstore-20">
							</p>
						</div>

						<h3><?php esc_html_e( 'AI Rewrite / Summarize', 'curated-rss-aggregator' ); ?></h3>
						<p class="description"><?php esc_html_e( 'Configure an AI provider here; choose a mode per import job below. Leave provider blank to disable AI processing globally.', 'curated-rss-aggregator' ); ?></p>
						<div class="wra-fields">
							<p>
								<label for="wra-ai-provider"><?php esc_html_e( 'Provider', 'curated-rss-aggregator' ); ?></label>
								<select id="wra-ai-provider" name="ai_provider">
									<option value=""><?php esc_html_e( '— Disabled —', 'curated-rss-aggregator' ); ?></option>
									<option value="openai" <?php selected( $settings['ai_provider'], 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'curated-rss-aggregator' ); ?></option>
									<option value="openrouter" <?php selected( $settings['ai_provider'], 'openrouter' ); ?>><?php esc_html_e( 'OpenRouter', 'curated-rss-aggregator' ); ?></option>
								</select>
							</p>
							<p>
								<label for="wra-ai-key"><?php esc_html_e( 'API Key', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-ai-key" type="password" name="ai_api_key" value="" autocomplete="new-password"<?php if ( ! empty( $settings['ai_api_key'] ) ) : ?> placeholder="<?php esc_attr_e( '(saved — leave blank to keep)', 'curated-rss-aggregator' ); ?>"<?php endif; ?>>
							</p>
							<p>
								<label for="wra-ai-model"><?php esc_html_e( 'Model', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-ai-model" type="text" name="ai_model" value="<?php echo esc_attr( $settings['ai_model'] ); ?>" placeholder="gpt-4o-mini">
							</p>
						</div>

						<?php submit_button( __( 'Save Settings', 'curated-rss-aggregator' ) ); ?>
					</form>
				</section>

				<section class="wra-panel">
					<h2><?php esc_html_e( 'Shortcode', 'curated-rss-aggregator' ); ?></h2>
					<code>[curated_rss items="6" layout="grid" columns="3" card_style="shadow"]</code>
					<table class="widefat striped" style="margin-top:8px;font-size:0.875rem;">
						<thead><tr><th><?php esc_html_e( 'Attribute', 'curated-rss-aggregator' ); ?></th><th><?php esc_html_e( 'Options / default', 'curated-rss-aggregator' ); ?></th></tr></thead>
						<tbody>
							<tr><td><code>layout</code></td><td>grid · list · compact <em>(grid)</em></td></tr>
							<tr><td><code>columns</code></td><td>1–6, 0 = auto <em>(0)</em></td></tr>
							<tr><td><code>card_style</code></td><td>default · shadow · flat · outline · none <em>(default)</em></td></tr>
							<tr><td><code>image_ratio</code></td><td>16-9 · 4-3 · 3-2 · 1-1 <em>(16-9)</em></td></tr>
							<tr><td><code>items</code></td><td>integer <em>(6)</em></td></tr>
							<tr><td><code>per_feed</code></td><td>integer, 0 = no limit <em>(0)</em></td></tr>
							<tr><td><code>show_image</code></td><td>yes · no <em>(yes)</em></td></tr>
							<tr><td><code>show_date</code></td><td>yes · no <em>(yes)</em></td></tr>
							<tr><td><code>show_source</code></td><td>yes · no <em>(no)</em></td></tr>
							<tr><td><code>show_author</code></td><td>yes · no <em>(no)</em></td></tr>
							<tr><td><code>show_excerpt</code></td><td>yes · no <em>(yes)</em></td></tr>
							<tr><td><code>max_chars</code></td><td>integer, 0 = no limit <em>(0)</em></td></tr>
							<tr><td><code>show_read_more</code></td><td>yes · no <em>(no)</em></td></tr>
							<tr><td><code>read_more_text</code></td><td>any string <em>(Read more)</em></td></tr>
							<tr><td><code>include_keywords</code></td><td>comma-separated</td></tr>
							<tr><td><code>exclude_keywords</code></td><td>comma-separated</td></tr>
							<tr><td><code>affiliate_name</code></td><td>query param name</td></tr>
							<tr><td><code>affiliate_value</code></td><td>query param value</td></tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Preview', 'curated-rss-aggregator' ); ?></h3>
					<?php if ( empty( $preview ) ) : ?>
						<p><?php esc_html_e( 'Add feed URLs and save settings to preview items.', 'curated-rss-aggregator' ); ?></p>
					<?php else : ?>
						<ul class="wra-preview">
							<?php foreach ( $preview as $item ) : ?>
								<li><a href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['title'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>

			<section class="wra-panel">
				<h2><?php echo $edit_job ? esc_html__( 'Edit Import Job', 'curated-rss-aggregator' ) : esc_html__( 'Create Import Job', 'curated-rss-aggregator' ); ?></h2>
				<form method="post" class="wra-job-form">
					<?php wp_nonce_field( 'wra_admin_action' ); ?>
					<input type="hidden" name="wra_action" value="save_job">
					<input type="hidden" name="job_id" value="<?php echo esc_attr( $edit_job ? $edit_job['id'] : '' ); ?>">

					<div class="wra-fields">
						<p>
							<label for="wra-job-name"><?php esc_html_e( 'Job name', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-job-name" type="text" name="name" value="<?php echo esc_attr( $edit_job ? $edit_job['name'] : '' ); ?>" required>
						</p>
						<p>
							<label for="wra-job-limit"><?php esc_html_e( 'Items per run', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-job-limit" type="number" min="1" max="50" name="limit" value="<?php echo esc_attr( $edit_job ? $edit_job['limit'] : 10 ); ?>">
						</p>
						<p>
							<label for="wra-job-status"><?php esc_html_e( 'Post status', 'curated-rss-aggregator' ); ?></label>
							<select id="wra-job-status" name="post_status">
								<?php foreach ( array( 'draft', 'publish', 'pending', 'private' ) as $status ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $edit_job ? $edit_job['post_status'] : 'draft', $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p>
							<label for="wra-job-type"><?php esc_html_e( 'Post type', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-job-type" type="text" name="post_type" value="<?php echo esc_attr( $edit_job ? $edit_job['post_type'] : 'post' ); ?>">
						</p>
					</div>

					<label for="wra-job-feeds"><?php esc_html_e( 'Feed URLs', 'curated-rss-aggregator' ); ?></label>
					<textarea id="wra-job-feeds" name="feeds" rows="5" required><?php echo esc_textarea( $edit_job ? $edit_job['feeds'] : $settings['feeds'] ); ?></textarea>

					<div class="wra-fields">
						<p>
							<label for="wra-include"><?php esc_html_e( 'Include keywords', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-include" type="text" name="include_keywords" value="<?php echo esc_attr( $edit_job ? $edit_job['include_keywords'] : '' ); ?>">
						</p>
						<p>
							<label for="wra-exclude"><?php esc_html_e( 'Exclude keywords', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-exclude" type="text" name="exclude_keywords" value="<?php echo esc_attr( $edit_job ? $edit_job['exclude_keywords'] : '' ); ?>">
						</p>
						<p>
							<label for="wra-after"><?php esc_html_e( 'Date after', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-after" type="date" name="date_after" value="<?php echo esc_attr( $edit_job ? $edit_job['date_after'] : '' ); ?>">
						</p>
						<p>
							<label for="wra-before"><?php esc_html_e( 'Date before', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-before" type="date" name="date_before" value="<?php echo esc_attr( $edit_job ? $edit_job['date_before'] : '' ); ?>">
						</p>
					</div>

					<div class="wra-checks">
						<label><input type="checkbox" name="enabled" value="1" <?php checked( $edit_job ? $edit_job['enabled'] : true ); ?>> <?php esc_html_e( 'Run on schedule', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="use_full_content" value="1" <?php checked( $edit_job ? $edit_job['use_full_content'] : false ); ?>> <?php esc_html_e( 'Use full feed content when available', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="full_text_extraction" value="1" <?php checked( $edit_job ? ! empty( $edit_job['full_text_extraction'] ) : false ); ?>> <?php esc_html_e( 'Fetch full text from source URL (overrides feed content, slower)', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="save_featured_image" value="1" <?php checked( $edit_job ? $edit_job['save_featured_image'] : false ); ?>> <?php esc_html_e( 'Save extracted image as featured image', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="preserve_date" value="1" <?php checked( $edit_job ? $edit_job['preserve_date'] : false ); ?>> <?php esc_html_e( 'Preserve source publish date', 'curated-rss-aggregator' ); ?></label>
					</div>

					<div class="wra-fields">
						<p>
							<label for="wra-ai-mode"><?php esc_html_e( 'AI processing', 'curated-rss-aggregator' ); ?></label>
							<select id="wra-ai-mode" name="ai_mode">
								<?php $current_ai_mode = $edit_job ? ( isset( $edit_job['ai_mode'] ) ? $edit_job['ai_mode'] : 'none' ) : 'none'; ?>
								<option value="none" <?php selected( $current_ai_mode, 'none' ); ?>><?php esc_html_e( 'None', 'curated-rss-aggregator' ); ?></option>
								<option value="rewrite" <?php selected( $current_ai_mode, 'rewrite' ); ?>><?php esc_html_e( 'Rewrite', 'curated-rss-aggregator' ); ?></option>
								<option value="summarize" <?php selected( $current_ai_mode, 'summarize' ); ?>><?php esc_html_e( 'Summarize', 'curated-rss-aggregator' ); ?></option>
							</select>
						</p>
						<p>
							<label for="wra-ai-prompt"><?php esc_html_e( 'Custom AI instructions', 'curated-rss-aggregator' ); ?></label>
							<textarea id="wra-ai-prompt" name="ai_prompt" rows="2" placeholder="<?php esc_attr_e( 'Optional. E.g. Write for a tech-savvy audience.', 'curated-rss-aggregator' ); ?>"><?php echo esc_textarea( $edit_job ? ( isset( $edit_job['ai_prompt'] ) ? $edit_job['ai_prompt'] : '' ) : '' ); ?></textarea>
						</p>
					</div>

					<?php submit_button( $edit_job ? __( 'Update Job', 'curated-rss-aggregator' ) : __( 'Create Job', 'curated-rss-aggregator' ) ); ?>
				</form>
			</section>

			<section class="wra-panel">
				<h2><?php esc_html_e( 'Import Jobs', 'curated-rss-aggregator' ); ?></h2>
				<?php $this->render_jobs_table( $jobs ); ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Sanitize settings form.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	private function sanitize_settings( $data ) {
		$existing = WRA_Plugin::get_settings();
		$ai_key   = isset( $data['ai_api_key'] ) ? trim( wp_unslash( $data['ai_api_key'] ) ) : '';

		return array(
			'feeds'           => isset( $data['feeds'] ) ? $this->sanitize_multiline_urls( wp_unslash( $data['feeds'] ) ) : '',
			'cache_minutes'   => isset( $data['cache_minutes'] ) ? max( 5, absint( $data['cache_minutes'] ) ) : 60,
			'fallback_image'  => isset( $data['fallback_image'] ) ? esc_url_raw( wp_unslash( $data['fallback_image'] ) ) : '',
			'affiliate_name'  => isset( $data['affiliate_name'] ) ? sanitize_key( wp_unslash( $data['affiliate_name'] ) ) : '',
			'affiliate_value' => isset( $data['affiliate_value'] ) ? sanitize_text_field( wp_unslash( $data['affiliate_value'] ) ) : '',
			'amazon_tag'      => isset( $data['amazon_tag'] ) ? sanitize_text_field( wp_unslash( $data['amazon_tag'] ) ) : '',
			'ai_provider'     => isset( $data['ai_provider'] ) ? sanitize_key( wp_unslash( $data['ai_provider'] ) ) : '',
			'ai_api_key'      => '' !== $ai_key ? sanitize_text_field( $ai_key ) : $existing['ai_api_key'],
			'ai_model'        => isset( $data['ai_model'] ) ? sanitize_text_field( wp_unslash( $data['ai_model'] ) ) : '',
		);
	}

	/**
	 * Sanitize job form.
	 *
	 * @param array  $data Raw data.
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
			'post_status'          => isset( $data['post_status'] ) ? sanitize_key( wp_unslash( $data['post_status'] ) ) : 'draft',
			'post_type'            => isset( $data['post_type'] ) ? sanitize_key( wp_unslash( $data['post_type'] ) ) : 'post',
			'include_keywords'     => isset( $data['include_keywords'] ) ? sanitize_text_field( wp_unslash( $data['include_keywords'] ) ) : '',
			'exclude_keywords'     => isset( $data['exclude_keywords'] ) ? sanitize_text_field( wp_unslash( $data['exclude_keywords'] ) ) : '',
			'date_after'           => isset( $data['date_after'] ) ? sanitize_text_field( wp_unslash( $data['date_after'] ) ) : '',
			'date_before'          => isset( $data['date_before'] ) ? sanitize_text_field( wp_unslash( $data['date_before'] ) ) : '',
			'enabled'              => ! empty( $data['enabled'] ),
			'use_full_content'     => ! empty( $data['use_full_content'] ),
			'full_text_extraction' => ! empty( $data['full_text_extraction'] ),
			'save_featured_image'  => ! empty( $data['save_featured_image'] ),
			'preserve_date'        => ! empty( $data['preserve_date'] ),
			'ai_mode'              => in_array( $ai_mode, $valid_ai_modes, true ) ? $ai_mode : 'none',
			'ai_prompt'            => isset( $data['ai_prompt'] ) ? sanitize_textarea_field( wp_unslash( $data['ai_prompt'] ) ) : '',
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

	/**
	 * Get job being edited.
	 *
	 * @param array $jobs Jobs.
	 * @return array|null
	 */
	private function get_edit_job( $jobs ) {
		$job_id = isset( $_GET['edit_job'] ) ? sanitize_key( wp_unslash( $_GET['edit_job'] ) ) : '';
		return isset( $jobs[ $job_id ] ) ? $jobs[ $job_id ] : null;
	}

	/**
	 * Preview configured feeds.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	private function get_preview_items( $settings ) {
		if ( empty( $settings['feeds'] ) ) {
			return array();
		}

		return $this->fetcher->get_items(
			preg_split( '/[\r\n,]+/', $settings['feeds'] ),
			array(
				'limit'          => 5,
				'cache_minutes'  => $settings['cache_minutes'],
				'fallback_image' => $settings['fallback_image'],
			)
		);
	}

	/**
	 * Render status notice.
	 */
	private function render_notice() {
		if ( empty( $_GET['wra_message'] ) ) {
			return;
		}

		$message = sanitize_key( wp_unslash( $_GET['wra_message'] ) );
		$text    = __( 'Settings saved.', 'curated-rss-aggregator' );

		if ( 'job_saved' === $message ) {
			$text = __( 'Import job saved.', 'curated-rss-aggregator' );
		} elseif ( 'job_deleted' === $message ) {
			$text = __( 'Import job deleted.', 'curated-rss-aggregator' );
		} elseif ( 'job_ran' === $message ) {
			$imported = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
			$skipped  = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
			$text     = sprintf(
				/* translators: 1: imported count, 2: skipped count */
				__( 'Import complete. Imported %1$d item(s), skipped %2$d.', 'curated-rss-aggregator' ),
				$imported,
				$skipped
			);
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	/**
	 * Render jobs table.
	 *
	 * @param array $jobs Jobs.
	 */
	private function render_jobs_table( $jobs ) {
		if ( empty( $jobs ) ) {
			echo '<p>' . esc_html__( 'No import jobs yet.', 'curated-rss-aggregator' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'curated-rss-aggregator' ); ?></th>
					<th><?php esc_html_e( 'Feeds', 'curated-rss-aggregator' ); ?></th>
					<th><?php esc_html_e( 'Status', 'curated-rss-aggregator' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'curated-rss-aggregator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $jobs as $job ) : ?>
					<tr>
						<td><?php echo esc_html( $job['name'] ); ?></td>
						<td><?php echo esc_html( wp_trim_words( str_replace( "\n", ', ', $job['feeds'] ), 12 ) ); ?></td>
						<td><?php echo ! empty( $job['enabled'] ) ? esc_html__( 'Scheduled', 'curated-rss-aggregator' ) : esc_html__( 'Paused', 'curated-rss-aggregator' ); ?></td>
						<td class="wra-actions">
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wra&edit_job=' . rawurlencode( $job['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'curated-rss-aggregator' ); ?></a>
							<form method="post">
								<?php wp_nonce_field( 'wra_admin_action' ); ?>
								<input type="hidden" name="wra_action" value="run_job">
								<input type="hidden" name="job_id" value="<?php echo esc_attr( $job['id'] ); ?>">
								<button class="button" type="submit"><?php esc_html_e( 'Run Now', 'curated-rss-aggregator' ); ?></button>
							</form>
							<form method="post" data-wra-confirm="<?php esc_attr_e( 'Delete this import job?', 'curated-rss-aggregator' ); ?>">
								<?php wp_nonce_field( 'wra_admin_action' ); ?>
								<input type="hidden" name="wra_action" value="delete_job">
								<input type="hidden" name="job_id" value="<?php echo esc_attr( $job['id'] ); ?>">
								<button class="button button-link-delete" type="submit"><?php esc_html_e( 'Delete', 'curated-rss-aggregator' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Redirect after POST.
	 *
	 * @param string $message Message key.
	 * @param array  $args Extra args.
	 */
	private function redirect_with_message( $message, $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => 'wra', 'wra_message' => $message ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
