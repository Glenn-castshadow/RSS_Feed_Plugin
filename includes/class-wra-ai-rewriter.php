<?php
/**
 * AI content rewriter/summarizer.
 *
 * Supports OpenAI and OpenRouter (identical request format, different endpoint).
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_AI_Rewriter {
	const OPENAI_ENDPOINT     = 'https://api.openai.com/v1/chat/completions';
	const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	/**
	 * Default model per provider when the user hasn't specified one.
	 *
	 * @var array
	 */
	private static $default_models = array(
		'openai'     => 'gpt-4o-mini',
		'openrouter' => 'openai/gpt-4o-mini',
	);

	/**
	 * Plugin settings (provider, api_key, model).
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings array containing ai_provider, ai_api_key, ai_model.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Rewrite or summarize content via the configured AI provider.
	 *
	 * Returns the original content unchanged on API failure so imports are never blocked.
	 *
	 * @param string      $content HTML content to process.
	 * @param string      $title   Post title sent as context.
	 * @param string      $mode    'rewrite' or 'summarize'.
	 * @param string      $prompt  Optional additional instructions appended to the system prompt.
	 * @param string|null $error   Optional. Set to an error message string on failure.
	 * @return string Processed content (wpautop'd plain text) or original HTML on failure.
	 */
	public function process( $content, $title, $mode, $prompt = '', &$error = null ) {
		$provider = isset( $this->settings['ai_provider'] ) ? $this->settings['ai_provider'] : '';
		$api_key  = isset( $this->settings['ai_api_key'] ) ? trim( $this->settings['ai_api_key'] ) : '';
		$model    = isset( $this->settings['ai_model'] ) && ! empty( $this->settings['ai_model'] )
			? $this->settings['ai_model']
			: ( isset( self::$default_models[ $provider ] ) ? self::$default_models[ $provider ] : '' );

		if ( empty( $provider ) || empty( $api_key ) || empty( $model ) ) {
			return $content;
		}

		$endpoint = 'openrouter' === $provider ? self::OPENROUTER_ENDPOINT : self::OPENAI_ENDPOINT;
		$headers  = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		);

		if ( 'openrouter' === $provider ) {
			$headers['HTTP-Referer'] = home_url();
			$headers['X-Title']      = get_bloginfo( 'name' );
		}

		$body = wp_json_encode(
			array(
				'model'      => $model,
				'messages'   => array(
					array(
						'role'    => 'system',
						'content' => $this->build_system_prompt( $mode, $prompt ),
					),
					array(
						'role'    => 'user',
						'content' => sprintf(
							"Title: %s\n\nContent:\n%s",
							$title,
							wp_strip_all_tags( $content )
						),
					),
				),
				'max_tokens' => 2000,
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			return $content;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$body  = json_decode( wp_remote_retrieve_body( $response ), true );
			$error = isset( $body['error']['message'] )
				? $body['error']['message']
				: sprintf( 'HTTP %d', (int) wp_remote_retrieve_response_code( $response ) );
			return $content;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = isset( $data['choices'][0]['message']['content'] ) ? trim( $data['choices'][0]['message']['content'] ) : '';

		return ! empty( $text ) ? wpautop( esc_html( $text ) ) : $content;
	}

	/**
	 * Build the system prompt for the given mode, optionally appending custom instructions.
	 *
	 * @param string $mode   'rewrite' or 'summarize'.
	 * @param string $prompt Extra instructions from the job config.
	 * @return string
	 */
	private function build_system_prompt( $mode, $prompt ) {
		if ( 'summarize' === $mode ) {
			$base = 'You are a concise editorial assistant. Summarize the provided article in 2–3 clear paragraphs. Return only the summary text, no headings or preamble.';
		} else {
			$base = 'You are an editorial assistant. Rewrite the provided article in an engaging, clear style while preserving all key facts. Return only the rewritten text, no preamble.';
		}

		if ( ! empty( $prompt ) ) {
			$base .= ' Additional instructions: ' . sanitize_textarea_field( $prompt );
		}

		return $base;
	}
}
