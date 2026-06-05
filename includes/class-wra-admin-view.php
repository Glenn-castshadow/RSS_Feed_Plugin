<?php
/**
 * Admin read-side rendering.
 *
 * Pure presentation: builds the settings screen markup and its sub-tables. All
 * state mutation lives in WRA_Admin_Controller; this class only reads through
 * the repository and the fetcher (for preview/health).
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Admin_View {
	/**
	 * Settings repository.
	 *
	 * @var WRA_Settings_Repository
	 */
	private $repo;

	/**
	 * Feed fetcher (preview + health).
	 *
	 * @var WRA_Feed_Fetcher
	 */
	private $fetcher;

	/**
	 * Constructor.
	 *
	 * @param WRA_Settings_Repository $repo    Settings repository.
	 * @param WRA_Feed_Fetcher        $fetcher Feed fetcher.
	 */
	public function __construct( WRA_Settings_Repository $repo, WRA_Feed_Fetcher $fetcher ) {
		$this->repo    = $repo;
		$this->fetcher = $fetcher;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		$settings  = $this->repo->get_settings();
		$jobs      = $this->repo->get_import_jobs();
		$lists     = $this->repo->get_feed_lists();
		$all_logs  = $this->repo->get_all_job_logs();
		$edit_job  = $this->get_edit_job( $jobs );
		$edit_list = $this->get_edit_list( $lists );
		$edit_log  = $edit_job && isset( $all_logs[ $edit_job['id'] ] ) ? (array) $all_logs[ $edit_job['id'] ] : array();
		?>
		<div class="wrap wra-admin">
			<h1><?php esc_html_e( 'Curated RSS Aggregator', 'curated-rss-aggregator' ); ?></h1>
			<?php $this->render_notice(); ?>

			<div class="wra-admin__grid">
				<section class="wra-panel">
					<h2><?php esc_html_e( 'Display Feeds', 'curated-rss-aggregator' ); ?></h2>

					<div class="wra-toolbar">
						<form method="post">
							<?php wp_nonce_field( 'wra_admin_action' ); ?>
							<input type="hidden" name="wra_action" value="clear_feed_cache">
							<button type="submit" class="button"><?php esc_html_e( 'Clear feed cache', 'curated-rss-aggregator' ); ?></button>
							<span class="description wra-desc-inline"><?php esc_html_e( 'Forces all feeds to re-fetch on next load.', 'curated-rss-aggregator' ); ?></span>
						</form>
						<form method="post">
							<?php wp_nonce_field( 'wra_admin_action' ); ?>
							<input type="hidden" name="wra_action" value="check_for_updates">
							<button type="submit" class="button"><?php esc_html_e( 'Check for updates', 'curated-rss-aggregator' ); ?></button>
							<span class="description wra-desc-inline"><?php esc_html_e( 'Clears the cached GitHub release info and forces WordPress to re-check for a plugin update.', 'curated-rss-aggregator' ); ?></span>
						</form>
					</div>

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
						</div>

						<p>
							<label><?php esc_html_e( 'Fallback images', 'curated-rss-aggregator' ); ?></label>
							<span class="description wra-desc-block"><?php esc_html_e( 'Shown when a feed item has no image. Multiple images are chosen randomly.', 'curated-rss-aggregator' ); ?></span>
							<span id="wra-fallback-images-preview" class="wra-fallback-images">
								<?php
								$fb_ids = array_filter( array_map( 'intval', explode( ',', $settings['fallback_image_ids'] ) ) );
								foreach ( $fb_ids as $fb_id ) :
									$thumb = wp_get_attachment_image_url( $fb_id, 'thumbnail' );
									if ( ! $thumb ) continue;
									?>
									<span class="wra-fallback-thumb" data-id="<?php echo esc_attr( $fb_id ); ?>">
										<img src="<?php echo esc_url( $thumb ); ?>" alt="">
										<button type="button" class="wra-remove-thumb" aria-label="<?php esc_attr_e( 'Remove', 'curated-rss-aggregator' ); ?>">&times;</button>
									</span>
								<?php endforeach; ?>
							</span>
							<input type="hidden" id="wra-fallback-image-ids" name="fallback_image_ids" value="<?php echo esc_attr( $settings['fallback_image_ids'] ); ?>">
							<button type="button" id="wra-add-fallback-image" class="button wra-add-images"><?php esc_html_e( 'Add images', 'curated-rss-aggregator' ); ?></button>
						</p>

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

					<details class="wra-opml">
						<summary><?php esc_html_e( 'Import OPML', 'curated-rss-aggregator' ); ?></summary>
						<form method="post" enctype="multipart/form-data" class="wra-opml-form">
							<?php wp_nonce_field( 'wra_admin_action' ); ?>
							<input type="hidden" name="wra_action" value="import_opml">
							<p>
								<label for="wra-opml-file"><?php esc_html_e( 'OPML file', 'curated-rss-aggregator' ); ?></label>
								<input id="wra-opml-file" type="file" name="opml_file" accept=".opml,.xml">
							</p>
							<p>
								<label class="wra-inline-label wra-inline-label--spaced">
									<input type="radio" name="opml_mode" value="merge" checked>
									<?php esc_html_e( 'Merge with existing feeds', 'curated-rss-aggregator' ); ?>
								</label>
								<label class="wra-inline-label">
									<input type="radio" name="opml_mode" value="replace">
									<?php esc_html_e( 'Replace existing feeds', 'curated-rss-aggregator' ); ?>
								</label>
							</p>
							<button type="submit" class="button"><?php esc_html_e( 'Import OPML', 'curated-rss-aggregator' ); ?></button>
						</form>
					</details>

					<details class="wra-settings-io">
						<summary><?php esc_html_e( 'Export / Import Settings', 'curated-rss-aggregator' ); ?></summary>
						<div class="wra-io-row">
							<div>
								<form method="post">
									<?php wp_nonce_field( 'wra_admin_action' ); ?>
									<input type="hidden" name="wra_action" value="export_settings">
									<button type="submit" class="button"><?php esc_html_e( 'Export to JSON', 'curated-rss-aggregator' ); ?></button>
								</form>
								<p class="description"><?php esc_html_e( 'Downloads all settings and import jobs. API key is excluded.', 'curated-rss-aggregator' ); ?></p>
							</div>
							<div>
								<form method="post" enctype="multipart/form-data">
									<?php wp_nonce_field( 'wra_admin_action' ); ?>
									<input type="hidden" name="wra_action" value="import_settings">
									<p class="wra-io-field">
										<label for="wra-settings-file"><?php esc_html_e( 'JSON file', 'curated-rss-aggregator' ); ?></label>
										<input id="wra-settings-file" type="file" name="settings_file" accept=".json">
									</p>
									<button type="submit" class="button"><?php esc_html_e( 'Import from JSON', 'curated-rss-aggregator' ); ?></button>
								</form>
								<p class="description"><?php esc_html_e( 'Merges settings and jobs. Existing API key is kept unless the file contains one.', 'curated-rss-aggregator' ); ?></p>
							</div>
						</div>
					</details>
				</section>

				<section class="wra-panel">
					<h2><?php esc_html_e( 'Shortcode', 'curated-rss-aggregator' ); ?></h2>
					<code>[curated_rss items="6" layout="grid" columns="3" card_style="shadow"]</code>
					<table class="widefat striped wra-subtable">
						<thead><tr><th><?php esc_html_e( 'Attribute', 'curated-rss-aggregator' ); ?></th><th><?php esc_html_e( 'Options / default', 'curated-rss-aggregator' ); ?></th></tr></thead>
						<tbody>
							<tr><td><code>feed_list</code></td><td><?php esc_html_e( 'named list slug (overrides feeds)', 'curated-rss-aggregator' ); ?></td></tr>
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
							<tr><td><code>show_load_more</code></td><td>yes · no <em>(no)</em></td></tr>
							<tr><td><code>affiliate_name</code></td><td>query param name</td></tr>
							<tr><td><code>affiliate_value</code></td><td>query param value</td></tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Feed Health', 'curated-rss-aggregator' ); ?></h3>
					<div class="wra-lazy-panel" data-wra-panel="feed_health">
						<p class="description"><?php esc_html_e( 'Loading…', 'curated-rss-aggregator' ); ?></p>
					</div>

					<h3><?php esc_html_e( 'Preview', 'curated-rss-aggregator' ); ?></h3>
					<div class="wra-lazy-panel" data-wra-panel="preview">
						<p class="description"><?php esc_html_e( 'Loading…', 'curated-rss-aggregator' ); ?></p>
					</div>
				</section>
			</div>

			<section class="wra-panel">
				<h2><?php esc_html_e( 'Feed Lists', 'curated-rss-aggregator' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Named groups of feed URLs. Use a list in your shortcode with the feed_list attribute, e.g.:', 'curated-rss-aggregator' ); ?> <code>[curated_rss feed_list="tech-news"]</code></p>

				<h3><?php echo $edit_list ? esc_html__( 'Edit Feed List', 'curated-rss-aggregator' ) : esc_html__( 'Create Feed List', 'curated-rss-aggregator' ); ?></h3>
				<form method="post" class="wra-fields wra-list-form">
					<?php wp_nonce_field( 'wra_admin_action' ); ?>
					<input type="hidden" name="wra_action" value="save_feed_list">
					<?php if ( $edit_list ) : ?>
						<input type="hidden" name="list_id" value="<?php echo esc_attr( $edit_list['id'] ); ?>">
					<?php endif; ?>

					<?php if ( $edit_list ) : ?>
						<p>
							<label><?php esc_html_e( 'Slug (shortcode key)', 'curated-rss-aggregator' ); ?></label>
							<code><?php echo esc_html( $edit_list['id'] ); ?></code>
						</p>
					<?php else : ?>
						<p>
							<label for="wra-list-slug"><?php esc_html_e( 'Slug', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-list-slug" type="text" name="list_slug" placeholder="tech-news" required pattern="[a-z0-9\-]+" title="<?php esc_attr_e( 'Lowercase letters, numbers, and hyphens only.', 'curated-rss-aggregator' ); ?>">
							<span class="description"><?php esc_html_e( 'Lowercase letters, numbers, and hyphens. Used as the feed_list value in shortcodes.', 'curated-rss-aggregator' ); ?></span>
						</p>
					<?php endif; ?>

					<p>
						<label for="wra-list-name"><?php esc_html_e( 'Display name', 'curated-rss-aggregator' ); ?></label>
						<input id="wra-list-name" type="text" name="list_name" value="<?php echo esc_attr( $edit_list ? $edit_list['name'] : '' ); ?>" required>
					</p>

					<p class="wra-field-wide">
						<label for="wra-list-feeds"><?php esc_html_e( 'Feed URLs', 'curated-rss-aggregator' ); ?></label>
						<textarea id="wra-list-feeds" name="list_feeds" rows="6" placeholder="https://example.com/feed"><?php echo esc_textarea( $edit_list ? $edit_list['feeds'] : '' ); ?></textarea>
						<span class="description"><?php esc_html_e( 'One URL per line.', 'curated-rss-aggregator' ); ?></span>
					</p>

					<?php submit_button( $edit_list ? __( 'Update List', 'curated-rss-aggregator' ) : __( 'Create List', 'curated-rss-aggregator' ) ); ?>
				</form>

				<?php if ( ! empty( $lists ) ) : ?>
				<table class="widefat striped wra-table-spaced">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'curated-rss-aggregator' ); ?></th>
							<th><?php esc_html_e( 'Slug (shortcode key)', 'curated-rss-aggregator' ); ?></th>
							<th><?php esc_html_e( 'Feeds', 'curated-rss-aggregator' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'curated-rss-aggregator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $lists as $list ) : ?>
							<?php $feed_count = count( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $list['feeds'] ) ) ) ); ?>
							<tr>
								<td><?php echo esc_html( $list['name'] ); ?></td>
								<td><code><?php echo esc_html( $list['id'] ); ?></code></td>
								<td><?php echo esc_html( $feed_count ); ?></td>
								<td class="wra-actions">
									<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wra&edit_list=' . rawurlencode( $list['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'curated-rss-aggregator' ); ?></a>
									<form method="post" data-wra-confirm="<?php esc_attr_e( 'Delete this feed list?', 'curated-rss-aggregator' ); ?>">
										<?php wp_nonce_field( 'wra_admin_action' ); ?>
										<input type="hidden" name="wra_action" value="delete_feed_list">
										<input type="hidden" name="list_id" value="<?php echo esc_attr( $list['id'] ); ?>">
										<button class="button button-link-delete" type="submit"><?php esc_html_e( 'Delete', 'curated-rss-aggregator' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</section>

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
							<label for="wra-job-frequency"><?php esc_html_e( 'Run every', 'curated-rss-aggregator' ); ?></label>
							<select id="wra-job-frequency" name="frequency">
								<?php
								$current_freq = $edit_job && isset( $edit_job['frequency'] ) ? (int) $edit_job['frequency'] : 30;
								foreach ( array( 15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', 120 => '2 hours', 360 => '6 hours', 720 => '12 hours', 1440 => '24 hours' ) as $val => $label ) :
									?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_freq, $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
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
						<p>
							<label for="wra-advanced-mode"><?php esc_html_e( 'Advanced filter mode', 'curated-rss-aggregator' ); ?></label>
							<?php $advanced_mode = $edit_job && isset( $edit_job['advanced_filter_mode'] ) ? $edit_job['advanced_filter_mode'] : 'all'; ?>
							<select id="wra-advanced-mode" name="advanced_filter_mode">
								<option value="all" <?php selected( $advanced_mode, 'all' ); ?>><?php esc_html_e( 'All rules must match', 'curated-rss-aggregator' ); ?></option>
								<option value="any" <?php selected( $advanced_mode, 'any' ); ?>><?php esc_html_e( 'Any rule may match', 'curated-rss-aggregator' ); ?></option>
							</select>
						</p>
						<p class="wra-field-wide">
							<label for="wra-advanced-filters"><?php esc_html_e( 'Advanced import filters', 'curated-rss-aggregator' ); ?></label>
							<textarea id="wra-advanced-filters" name="advanced_filters" rows="4" placeholder="title | contains | bourbon&#10;author | not_equals | Sponsored&#10;image | not_empty"><?php echo esc_textarea( $edit_job ? $this->format_advanced_filters( isset( $edit_job['advanced_filters'] ) ? $edit_job['advanced_filters'] : array() ) : '' ); ?></textarea>
							<span class="description"><?php esc_html_e( 'One rule per line: field | operator | value. Fields: title, description, content, author, image, source_feed, date. Operators: contains, not_contains, equals, not_equals, empty, not_empty, regex, date_after, date_before.', 'curated-rss-aggregator' ); ?></span>
						</p>
						<p>
							<label for="wra-job-category"><?php esc_html_e( 'Category', 'curated-rss-aggregator' ); ?></label>
							<select id="wra-job-category" name="category">
								<option value="0"><?php esc_html_e( '— None —', 'curated-rss-aggregator' ); ?></option>
								<?php
								$current_cat = $edit_job ? ( isset( $edit_job['category'] ) ? (int) $edit_job['category'] : 0 ) : 0;
								foreach ( get_categories( array( 'hide_empty' => false ) ) as $cat ) :
									?>
									<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $current_cat, $cat->term_id ); ?>><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description"><?php esc_html_e( 'Applies to the post type only if it supports the category taxonomy.', 'curated-rss-aggregator' ); ?></span>
						</p>
						<p>
							<label for="wra-job-tags"><?php esc_html_e( 'Tags', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-job-tags" type="text" name="tags" value="<?php echo esc_attr( $edit_job ? ( isset( $edit_job['tags'] ) ? $edit_job['tags'] : '' ) : '' ); ?>" placeholder="bourbon, whiskey, review">
							<span class="description"><?php esc_html_e( 'Comma-separated tag names.', 'curated-rss-aggregator' ); ?></span>
						</p>
						<p class="wra-field-wide">
							<label for="wra-category-mappings"><?php esc_html_e( 'Category mappings', 'curated-rss-aggregator' ); ?></label>
							<textarea id="wra-category-mappings" name="category_mappings" rows="3" placeholder="bourbon, whiskey => Reviews&#10;deals, coupon => Deals"><?php echo esc_textarea( $edit_job ? $this->format_category_mappings( isset( $edit_job['category_mappings'] ) ? $edit_job['category_mappings'] : array() ) : '' ); ?></textarea>
							<span class="description"><?php esc_html_e( 'One mapping per line: keywords => category ID, slug, or name. Mapped categories are added alongside the default category.', 'curated-rss-aggregator' ); ?></span>
						</p>
						<p class="wra-field-wide">
							<label for="wra-job-fallback-image"><?php esc_html_e( 'Job fallback image URL', 'curated-rss-aggregator' ); ?></label>
							<input id="wra-job-fallback-image" type="url" name="fallback_image_url" value="<?php echo esc_attr( $edit_job ? ( isset( $edit_job['fallback_image_url'] ) ? $edit_job['fallback_image_url'] : '' ) : '' ); ?>" placeholder="https://example.com/fallback.jpg">
							<span class="description"><?php esc_html_e( 'Used before the global fallback image pool when this import job finds an item with no image.', 'curated-rss-aggregator' ); ?></span>
						</p>
					</div>

					<div class="wra-checks">
						<label><input type="checkbox" name="enabled" value="1" <?php checked( $edit_job ? ! empty( $edit_job['enabled'] ) : true ); ?>> <?php esc_html_e( 'Run on schedule', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="import_full_post" value="1" <?php checked( $edit_job ? ! empty( $edit_job['import_full_post'] ) : false ); ?>> <?php esc_html_e( 'Import full post with images', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="use_full_content" value="1" <?php checked( $edit_job ? ! empty( $edit_job['use_full_content'] ) : false ); ?>> <?php esc_html_e( 'Use full feed content when available', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="full_text_extraction" value="1" <?php checked( $edit_job ? ! empty( $edit_job['full_text_extraction'] ) : false ); ?>> <?php esc_html_e( 'Fetch full text from source URL (overrides feed content, slower)', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="save_featured_image" value="1" <?php checked( $edit_job ? ! empty( $edit_job['save_featured_image'] ) : false ); ?>> <?php esc_html_e( 'Save extracted image as featured image', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="preserve_date" value="1" <?php checked( $edit_job ? ! empty( $edit_job['preserve_date'] ) : false ); ?>> <?php esc_html_e( 'Preserve source publish date', 'curated-rss-aggregator' ); ?></label>
						<label><input type="checkbox" name="enable_canonical" value="1" <?php checked( $edit_job ? ! empty( $edit_job['enable_canonical'] ) : false ); ?>> <?php esc_html_e( 'Use source URL as canonical', 'curated-rss-aggregator' ); ?></label>
					</div>
					<p class="description"><?php esc_html_e( 'Full-post import fetches the source article body, keeps usable inline images, and falls back to full feed content when extraction is unavailable.', 'curated-rss-aggregator' ); ?></p>

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

				<?php if ( $edit_job && ! empty( $edit_log ) ) : ?>
				<details class="wra-log">
					<summary><?php
						/* translators: %d: number of log entries */
						printf( esc_html( _n( 'Run history (%d entry)', 'Run history (%d entries)', count( $edit_log ), 'curated-rss-aggregator' ) ), count( $edit_log ) );
					?></summary>
					<table class="widefat striped wra-subtable">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'curated-rss-aggregator' ); ?></th>
								<th><?php esc_html_e( 'Imported', 'curated-rss-aggregator' ); ?></th>
								<th><?php esc_html_e( 'Skipped', 'curated-rss-aggregator' ); ?></th>
								<th><?php esc_html_e( 'Warnings', 'curated-rss-aggregator' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $edit_log as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['time'] ) ) ); ?></td>
									<td><?php echo esc_html( $entry['imported'] ); ?></td>
									<td><?php echo esc_html( $entry['skipped'] ); ?></td>
									<td>
										<?php
										$warnings = ! empty( $entry['warnings'] ) ? (array) $entry['warnings'] : array();
										if ( empty( $warnings ) ) {
											echo '&mdash;';
										} else {
											echo '<details><summary>' . esc_html( count( $warnings ) ) . '</summary><ul class="wra-warning-list">';
											foreach ( $warnings as $w ) {
												echo '<li>' . esc_html( $w ) . '</li>';
											}
											echo '</ul></details>';
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</details>
				<?php endif; ?>
			</section>

			<section class="wra-panel">
				<h2><?php esc_html_e( 'Import Jobs', 'curated-rss-aggregator' ); ?></h2>
				<?php $this->render_jobs_table( $jobs, $all_logs ); ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Job being edited, from the edit_job query arg.
	 *
	 * @param array $jobs Jobs.
	 * @return array|null
	 */
	private function get_edit_job( $jobs ) {
		$job_id = isset( $_GET['edit_job'] ) ? sanitize_key( wp_unslash( $_GET['edit_job'] ) ) : '';
		return isset( $jobs[ $job_id ] ) ? $jobs[ $job_id ] : null;
	}

	/**
	 * Feed list being edited, from the edit_list query arg.
	 *
	 * @param array $lists Feed lists.
	 * @return array|null
	 */
	private function get_edit_list( $lists ) {
		$list_id = isset( $_GET['edit_list'] ) ? sanitize_key( wp_unslash( $_GET['edit_list'] ) ) : '';
		return isset( $lists[ $list_id ] ) ? $lists[ $list_id ] : null;
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
			array(
				'urls'            => preg_split( '/[\r\n,]+/', $settings['feeds'] ),
				'limit'           => 5,
				'cache_minutes'   => $settings['cache_minutes'],
				'fallback_images' => $this->repo->get_fallback_images(),
			)
		);
	}

	/**
	 * AJAX: render the feed-health table.
	 *
	 * Loaded lazily after the admin page paints so the synchronous per-feed
	 * fetch never blocks the initial page render.
	 */
	public function ajax_feed_health() {
		$this->guard_panel_request();
		$this->render_feed_health( $this->repo->get_settings() );
		wp_die();
	}

	/**
	 * AJAX: render the feed preview list. Lazily loaded (see ajax_feed_health).
	 */
	public function ajax_preview() {
		$this->guard_panel_request();
		$this->render_preview( $this->get_preview_items( $this->repo->get_settings() ) );
		wp_die();
	}

	/**
	 * Reject lazy-panel AJAX requests without the capability or a valid nonce.
	 */
	private function guard_panel_request() {
		if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'wra_admin_panels', 'nonce', false ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Render the feed preview list.
	 *
	 * @param array $preview Preview items.
	 */
	private function render_preview( $preview ) {
		if ( empty( $preview ) ) {
			echo '<p>' . esc_html__( 'Add feed URLs and save settings to preview items.', 'curated-rss-aggregator' ) . '</p>';
			return;
		}
		?>
		<ul class="wra-preview">
			<?php foreach ( $preview as $item ) : ?>
				<li><a href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $item['title'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render the admin status notice from the wra_message query arg.
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
		} elseif ( 'cache_cleared' === $message ) {
			$text = __( 'Feed cache cleared.', 'curated-rss-aggregator' );
		} elseif ( 'opml_imported' === $message ) {
			$added = isset( $_GET['added'] ) ? absint( $_GET['added'] ) : 0;
			$text  = sprintf(
				/* translators: %d: number of feed URLs added */
				_n( 'OPML imported. %d feed URL added.', 'OPML imported. %d feed URLs added.', $added, 'curated-rss-aggregator' ),
				$added
			);
		} elseif ( 'update_check_done' === $message ) {
			$text = __( 'Update cache cleared. WordPress will re-check for plugin updates.', 'curated-rss-aggregator' );
		} elseif ( 'feed_list_saved' === $message ) {
			$text = __( 'Feed list saved.', 'curated-rss-aggregator' );
		} elseif ( 'feed_list_deleted' === $message ) {
			$text = __( 'Feed list deleted.', 'curated-rss-aggregator' );
		} elseif ( 'settings_imported' === $message ) {
			$text = __( 'Settings imported successfully.', 'curated-rss-aggregator' );
		} elseif ( 'settings_import_failed' === $message ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__( 'Settings import failed. Please verify the JSON file and try again.', 'curated-rss-aggregator' ) );
			return;
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	/**
	 * Render the import-jobs summary table.
	 *
	 * @param array $jobs     Jobs.
	 * @param array $all_logs Run logs keyed by job ID.
	 */
	private function render_jobs_table( $jobs, $all_logs ) {
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
					<th><?php esc_html_e( 'Last run', 'curated-rss-aggregator' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'curated-rss-aggregator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $jobs as $job ) : ?>
					<tr>
						<td><?php echo esc_html( $job['name'] ); ?></td>
						<td><?php echo esc_html( wp_trim_words( str_replace( "\n", ', ', $job['feeds'] ), 12 ) ); ?></td>
						<td><?php echo ! empty( $job['enabled'] ) ? esc_html__( 'Scheduled', 'curated-rss-aggregator' ) : esc_html__( 'Paused', 'curated-rss-aggregator' ); ?></td>
						<td>
							<?php
							$job_log = isset( $all_logs[ $job['id'] ] ) ? (array) $all_logs[ $job['id'] ] : array();
							$last    = ! empty( $job_log ) ? $job_log[0] : null;
							if ( $last ) {
								printf(
									'%s<br><small>%s</small>',
									esc_html( human_time_diff( strtotime( $last['time'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'curated-rss-aggregator' ) ),
									/* translators: 1: imported count, 2: skipped count */
									esc_html( sprintf( __( '%1$d in / %2$d sk', 'curated-rss-aggregator' ), $last['imported'], $last['skipped'] ) )
								);
							} else {
								esc_html_e( 'Never', 'curated-rss-aggregator' );
							}
							?>
						</td>
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
	 * Render a per-feed health status table using cached feed data.
	 *
	 * @param array $settings Plugin settings.
	 */
	private function render_feed_health( $settings ) {
		if ( empty( $settings['feeds'] ) ) {
			echo '<p>' . esc_html__( 'Add feed URLs above to see health status.', 'curated-rss-aggregator' ) . '</p>';
			return;
		}

		$urls   = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $settings['feeds'] ) ) );
		$health = $this->fetcher->get_feed_health( $urls, $settings['cache_minutes'] );
		?>
		<table class="widefat striped wra-subtable">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Feed URL', 'curated-rss-aggregator' ); ?></th>
					<th><?php esc_html_e( 'Status', 'curated-rss-aggregator' ); ?></th>
					<th><?php esc_html_e( 'Items', 'curated-rss-aggregator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $health as $url => $info ) : ?>
					<tr>
						<td><code class="wra-feed-url"><?php echo esc_html( $url ); ?></code></td>
						<td>
							<?php if ( $info['ok'] ) : ?>
								<span class="wra-status-ok">&#10003; <?php esc_html_e( 'OK', 'curated-rss-aggregator' ); ?></span>
							<?php else : ?>
								<span class="wra-status-error" title="<?php echo esc_attr( $info['error'] ); ?>">&#10007; <?php esc_html_e( 'Error', 'curated-rss-aggregator' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $info['count'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Format structured filters for textarea editing.
	 *
	 * @param array $filters Filters.
	 * @return string
	 */
	private function format_advanced_filters( $filters ) {
		$lines = array();
		foreach ( (array) $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['operator'] ) ) {
				continue;
			}
			$lines[] = $filter['field'] . ' | ' . $filter['operator'] . ' | ' . ( isset( $filter['value'] ) ? $filter['value'] : '' );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Format category mappings for textarea editing.
	 *
	 * @param array $mappings Mappings.
	 * @return string
	 */
	private function format_category_mappings( $mappings ) {
		$lines = array();
		foreach ( (array) $mappings as $mapping ) {
			if ( empty( $mapping['keywords'] ) || empty( $mapping['category'] ) ) {
				continue;
			}
			$lines[] = $mapping['keywords'] . ' => ' . $mapping['category'];
		}
		return implode( "\n", $lines );
	}
}
