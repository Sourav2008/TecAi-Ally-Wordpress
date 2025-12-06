<?php
/**
 * Plugin Name: TecAI Ally - AI Support Chatbot (Google Gemini)
 * Plugin URI:  https://tecdevs.net/
 * Description: TecAI Ally is a floating AI customer support chatbot for WooCommerce, powered by the free Google Gemini API tier.
 * Version:     1.0.0
 * Author:      TecDevs (Sourav C)
 * Author URI:  https://tecdevs.net/
 * Text Domain: tecai-ally
 * Domain Path: /languages
 * * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tecai-ally
 */
 *
 * @package TecAI_Ally
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TECAI_ALLY_VERSION' ) ) {
	define( 'TECAI_ALLY_VERSION', '1.0.0' );
}

if ( ! defined( 'TECAI_ALLY_PLUGIN_FILE' ) ) {
	define( 'TECAI_ALLY_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'TECAI_ALLY_PLUGIN_DIR' ) ) {
	define( 'TECAI_ALLY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'TECAI_ALLY_PLUGIN_URL' ) ) {
	define( 'TECAI_ALLY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once TECAI_ALLY_PLUGIN_DIR . 'includes/class-tecai-ally-plugin.php';

register_activation_hook( __FILE__, array( 'TecAI_Ally_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TecAI_Ally_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'TecAI_Ally_Plugin', 'init' ) );
