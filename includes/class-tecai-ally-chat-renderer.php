<?php
/**
 * Frontend chat renderer for TecAI Ally.
 *
 * @package TecAI_Ally
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chat output and asset loading.
 */
class TecAI_Ally_Chat_Renderer {

	/**
	 * Settings handler.
	 *
	 * @var TecAI_Ally_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 *
	 * @param TecAI_Ally_Settings $settings Settings handler.
	 */
	public function __construct( TecAI_Ally_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'gemini_ai_chat', array( $this, 'render_chat_shortcode' ) );
	}

	/**
	 * Register Gutenberg block.
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'tecai-ally-block',
			TECAI_ALLY_PLUGIN_URL . 'assets/js/tecai-ally-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
			TECAI_ALLY_VERSION,
			true
		);

		register_block_type(
			'tecai-ally/chat',
			array(
				'editor_script'   => 'tecai-ally-block',
				'render_callback' => array( $this, 'render_chat_block' ),
			)
		);
	}

	/**
	 * Enqueue frontend scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$options = $this->settings->get_options();

		wp_enqueue_style(
			'tecai-ally-chat',
			TECAI_ALLY_PLUGIN_URL . 'assets/css/tecai-ally-chat.css',
			array(),
			TECAI_ALLY_VERSION
		);

		wp_enqueue_script(
			'tecai-ally-chat',
			TECAI_ALLY_PLUGIN_URL . 'assets/js/tecai-ally-chat.js',
			array(),
			TECAI_ALLY_VERSION,
			true
		);

		// Create nonce for REST API - use wp_rest for better compatibility
		$nonce = wp_create_nonce( 'wp_rest' );
		if ( empty( $nonce ) ) {
			// Fallback to custom nonce
			$nonce = wp_create_nonce( 'tecai_ally_chat' );
		}

		$config = array(
			'restUrl'            => esc_url_raw( rest_url( 'tecai-ally/v1/' ) ),
			'restNonce'          => $nonce,
			'chatNonce'          => wp_create_nonce( 'tecai_ally_chat' ),
			'position'           => $options['position'],
			'accentColor'        => $options['accent_color'],
			'bubbleLabel'        => $options['bubble_label'],
			'canStore'           => ! empty( $options['store_conversations'] ),
			'labels'             => array(
				'header'            => __( 'TecAI Ally', 'tecai-ally' ),
				'intro'             => __( 'Hi! I\'m TecAI Ally, your AI support assistant. Ask me anything about your orders or our products.', 'tecai-ally' ),
				'inputPlaceholder'  => __( 'Type your question...', 'tecai-ally' ),
				'send'              => __( 'Send', 'tecai-ally' ),
				'consentLabel'      => __( 'Allow TecAI Ally to store this chat so a human can review it if needed.', 'tecai-ally' ),
				'emailPlaceholder'  => __( 'Your email (optional, for human follow-up)', 'tecai-ally' ),
				'escButton'         => __( 'Transfer to human', 'tecai-ally' ),
				'deleteButton'      => __( 'Delete my chat history', 'tecai-ally' ),
				'rateLimited'       => __( 'You are sending messages too quickly. Please wait a moment.', 'tecai-ally' ),
				'genericError'      => __( 'Something went wrong. Please try again.', 'tecai-ally' ),
			),
		);

		wp_localize_script( 'tecai-ally-chat', 'TecAIAllyConfig', $config );
	}

	/**
	 * Render chat via shortcode.
	 *
	 * @return string
	 */
	public function render_chat_shortcode() {
		ob_start();
		$this->render_chat_markup();
		return ob_get_clean();
	}

	/**
	 * Render chat via block.
	 *
	 * @return string
	 */
	public function render_chat_block() {
		return $this->render_chat_shortcode();
	}

	/**
	 * Output minimal chat markup for JS bootstrap.
	 *
	 * @return void
	 */
	protected function render_chat_markup() {
		?>
		<div id="tecai-ally-chat-root" data-tecai-ally="1"></div>
		<?php
	}
}
