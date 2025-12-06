<?php
/**
 * Core plugin bootstrap for TecAI Ally.
 *
 * @package TecAI_Ally
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main TecAI Ally plugin class.
 */
class TecAI_Ally_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var TecAI_Ally_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Settings handler.
	 *
	 * @var TecAI_Ally_Settings
	 */
	protected $settings;

	/**
	 * REST controller.
	 *
	 * @var TecAI_Ally_REST_Controller
	 */
	protected $rest_controller;

	/**
	 * WooCommerce helper.
	 *
	 * @var TecAI_Ally_WooCommerce_Helper
	 */
	protected $wc_helper;

	/**
	 * Chat renderer.
	 *
	 * @var TecAI_Ally_Chat_Renderer
	 */
	protected $chat_renderer;

	/**
	 * Initialize the plugin singleton.
	 *
	 * @return TecAI_Ally_Plugin
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->setup_objects();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 *
	 * @return void
	 */
	protected function includes() {
		require_once TECAI_ALLY_PLUGIN_DIR . 'includes/class-tecai-ally-settings.php';
		require_once TECAI_ALLY_PLUGIN_DIR . 'includes/class-tecai-ally-woocommerce-helper.php';
		require_once TECAI_ALLY_PLUGIN_DIR . 'includes/class-tecai-ally-rest-controller.php';
		require_once TECAI_ALLY_PLUGIN_DIR . 'includes/class-tecai-ally-chat-renderer.php';
	}

	/**
	 * Instantiate core objects.
	 *
	 * @return void
	 */
	protected function setup_objects() {
		$this->settings      = new TecAI_Ally_Settings();
		$this->wc_helper     = new TecAI_Ally_WooCommerce_Helper();
		$this->rest_controller = new TecAI_Ally_REST_Controller( $this->settings, $this->wc_helper );
		$this->chat_renderer   = new TecAI_Ally_Chat_Renderer( $this->settings );
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	protected function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_conversation_cpt' ) );

		add_action( 'init', array( $this->chat_renderer, 'register_shortcodes' ) );
		add_action( 'init', array( $this->chat_renderer, 'register_block' ) );

		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );

		add_action( 'admin_menu', array( $this->settings, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this->settings, 'handle_form_submission' ) );

		add_action( 'wp_enqueue_scripts', array( $this->chat_renderer, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		// Flush rewrites in case CPT is used.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'tecai-ally', false, dirname( plugin_basename( TECAI_ALLY_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Register custom post type for conversations.
	 *
	 * @return void
	 */
	public function register_conversation_cpt() {
		$labels = array(
			'name'                  => _x( 'AI Conversations', 'post type general name', 'tecai-ally' ),
			'singular_name'         => _x( 'AI Conversation', 'post type singular name', 'tecai-ally' ),
			'menu_name'             => _x( 'AI Conversations', 'admin menu', 'tecai-ally' ),
			'name_admin_bar'        => _x( 'AI Conversation', 'add new on admin bar', 'tecai-ally' ),
			'add_new'               => __( 'Add New', 'tecai-ally' ),
			'add_new_item'          => __( 'Add New Conversation', 'tecai-ally' ),
			'new_item'              => __( 'New Conversation', 'tecai-ally' ),
			'edit_item'             => __( 'Edit Conversation', 'tecai-ally' ),
			'view_item'             => __( 'View Conversation', 'tecai-ally' ),
			'all_items'             => __( 'All Conversations', 'tecai-ally' ),
			'not_found'             => __( 'No conversations found.', 'tecai-ally' ),
			'not_found_in_trash'    => __( 'No conversations found in Trash.', 'tecai-ally' ),
		);

		$capabilities = array(
			'edit_post'          => 'manage_options',
			'read_post'          => 'manage_options',
			'delete_post'        => 'manage_options',
			'edit_posts'         => 'manage_options',
			'edit_others_posts'  => 'manage_options',
			'publish_posts'      => 'manage_options',
			'read_private_posts' => 'manage_options',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => false,
			'supports'           => array( 'title', 'editor' ),
			'capability_type'    => 'post',
			'capabilities'       => $capabilities,
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'exclude_from_search'=> true,
		);

		register_post_type( 'tecai_ally_conversation', $args );
	}
}
