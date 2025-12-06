<?php
/**
 * Settings handler for TecAI Ally.
 *
 * @package TecAI_Ally
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TecAI Ally settings management.
 */
class TecAI_Ally_Settings {

	const OPTION_API_KEY  = 'tecai_ally_gemini_api_key';
	const OPTION_SETTINGS = 'tecai_ally_options';

	/**
	 * Get plugin options merged with defaults.
	 *
	 * @return array
	 */
	public function get_options() {
		$defaults = array(
			'gemini_model'        => 'gemini-2.5-flash',
			'position'            => 'right',
			'accent_color'        => '#1d4ed8',
			'bubble_label'        => __( 'Chat with us', 'tecai-ally' ),
			'store_conversations' => false,
			'enable_woocommerce'  => true,
		);

		$stored = get_option( self::OPTION_SETTINGS, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( $defaults, $stored );
	}

	/**
	 * Get Gemini API key from the database.
	 *
	 * @return string
	 */
	public function get_api_key() {
		$key = get_option( self::OPTION_API_KEY, '' );

		if ( ! is_string( $key ) ) {
			return '';
		}

		return trim( $key );
	}

	/**
	 * Sanitize a raw API key string.
	 *
	 * @param string $raw Raw key input.
	 *
	 * @return string
	 */
	public function sanitize_api_key( $raw ) {
		$raw = (string) $raw;
		$raw = trim( $raw );

		// Only allow safe key characters.
		$raw = preg_replace( '/[^a-zA-Z0-9_\-\.\:]/', '', $raw );

		if ( ! is_string( $raw ) ) {
			return '';
		}

		return $raw;
	}

	/**
	 * Register settings page in the admin menu.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_menu_page(
			__( 'TecAI Ally', 'tecai-ally' ),
			__( 'TecAI Ally', 'tecai-ally' ),
			'manage_options',
			'tecai-ally',
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			56
		);
	}

	/**
	 * Handle settings form submission.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( ! isset( $_POST['tecai_ally_settings_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['tecai_ally_settings_nonce'] ) || ! check_admin_referer( 'tecai_ally_save_settings', 'tecai_ally_settings_nonce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$api_key = '';

		if ( isset( $_POST['tecai_ally_api_key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$api_key = $this->sanitize_api_key( wp_unslash( $_POST['tecai_ally_api_key'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		update_option( self::OPTION_API_KEY, $api_key );

		$options = $this->get_options();

		if ( isset( $_POST['tecai_ally_gemini_model'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$options['gemini_model'] = sanitize_text_field( wp_unslash( $_POST['tecai_ally_gemini_model'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( isset( $_POST['tecai_ally_position'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$position = 'left' === $_POST['tecai_ally_position'] ? 'left' : 'right'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$options['position'] = $position;
		}

		if ( isset( $_POST['tecai_ally_accent_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$color = sanitize_hex_color( wp_unslash( $_POST['tecai_ally_accent_color'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! empty( $color ) ) {
				$options['accent_color'] = $color;
			}
		}

		if ( isset( $_POST['tecai_ally_bubble_label'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$options['bubble_label'] = sanitize_text_field( wp_unslash( $_POST['tecai_ally_bubble_label'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$options['store_conversations'] = ! empty( $_POST['tecai_ally_store_conversations'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$options['enable_woocommerce']  = ! empty( $_POST['tecai_ally_enable_woocommerce'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		update_option( self::OPTION_SETTINGS, $options );

		add_settings_error(
			'tecai-ally',
			'tecai_ally_saved',
			__( 'Settings saved.', 'tecai-ally' ),
			'updated'
		);
	}

	/**
	 * Render the admin settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->get_options();
		$api_key = $this->get_api_key();

		settings_errors( 'tecai-ally' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'TecAI Ally  AI Support Chatbot', 'tecai-ally' ); ?></h1>
			<p><?php esc_html_e( 'Connect Google Gemini free tier and configure your floating WooCommerce support chatbot.', 'tecai-ally' ); ?></p>

			<div style="display:flex;gap:20px;align-items:flex-start;max-width:1100px;">
				<div style="flex:2;min-width:0;">
					<form method="post">
						<?php wp_nonce_field( 'tecai_ally_save_settings', 'tecai_ally_settings_nonce' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="tecai_ally_api_key"><?php esc_html_e( 'Google Gemini API key', 'tecai-ally' ); ?></label>
								</th>
								<td>
									<input name="tecai_ally_api_key" type="password" id="tecai_ally_api_key" class="regular-text" value="<?php echo esc_attr( $api_key ); ?>" autocomplete="off" />
									<p class="description">
										<?php esc_html_e( 'Your key is stored in your WordPress database via update_option(). This plugin never exposes it to the browser or any third party.', 'tecai-ally' ); ?>
									</p>
									<p class="description">
										<?php esc_html_e( 'If no key is set, the chatbot will respond with a friendly reminder: "Please add your Gemini API key in Settings  WP Gemini AI Support Bot".', 'tecai-ally' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="tecai_ally_gemini_model"><?php esc_html_e( 'Gemini model', 'tecai-ally' ); ?></label>
								</th>
								<td>
									<input name="tecai_ally_gemini_model" type="text" id="tecai_ally_gemini_model" class="regular-text" value="<?php echo esc_attr( $options['gemini_model'] ); ?>" />
									<p class="description">
										<?php esc_html_e( 'Default: gemini-2.5-flash (recommended free-tier model). You may use other compatible models if they are available on your account.', 'tecai-ally' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Chat bubble position', 'tecai-ally' ); ?></th>
								<td>
									<fieldset>
										<label>
											<input type="radio" name="tecai_ally_position" value="right" <?php checked( 'right', $options['position'] ); ?> />
											<?php esc_html_e( 'Bottom right', 'tecai-ally' ); ?>
										</label><br />
										<label>
											<input type="radio" name="tecai_ally_position" value="left" <?php checked( 'left', $options['position'] ); ?> />
											<?php esc_html_e( 'Bottom left', 'tecai-ally' ); ?>
										</label>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="tecai_ally_accent_color"><?php esc_html_e( 'Accent color', 'tecai-ally' ); ?></label>
								</th>
								<td>
									<input name="tecai_ally_accent_color" type="text" id="tecai_ally_accent_color" class="regular-text" value="<?php echo esc_attr( $options['accent_color'] ); ?>" />
									<p class="description"><?php esc_html_e( 'Primary color for the floating bubble and chat actions (hex).', 'tecai-ally' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="tecai_ally_bubble_label"><?php esc_html_e( 'Bubble label', 'tecai-ally' ); ?></label>
								</th>
								<td>
									<input name="tecai_ally_bubble_label" type="text" id="tecai_ally_bubble_label" class="regular-text" value="<?php echo esc_attr( $options['bubble_label'] ); ?>" />
									<p class="description"><?php esc_html_e( 'Short label shown next to the chat bubble.', 'tecai-ally' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Privacy & storage', 'tecai-ally' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="tecai_ally_store_conversations" value="1" <?php checked( true, ! empty( $options['store_conversations'] ) ); ?> />
										<?php esc_html_e( 'Allow TecAI Ally to store full chat transcripts (with visitor consent).', 'tecai-ally' ); ?>
									</label>
									<p class="description">
										<?php esc_html_e( 'When disabled, the chatbot will still function, but no full transcripts are persisted automatically.', 'tecai-ally' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'WooCommerce integration', 'tecai-ally' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="tecai_ally_enable_woocommerce" value="1" <?php checked( true, ! empty( $options['enable_woocommerce'] ) ); ?> />
										<?php esc_html_e( 'Enable order lookup, status, and shipping questions using WooCommerce data.', 'tecai-ally' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit" name="tecai_ally_settings_submit" class="button-primary">
								<?php esc_html_e( 'Save changes', 'tecai-ally' ); ?>
							</button>
						</p>
					</form>
				</div>

				<div style="flex:1;min-width:260px;background:#ffffff;border:1px solid #e5e7eb;padding:16px;border-radius:8px;">
					<h2 style="margin-top:0;">
						<?php esc_html_e( 'Go Pro (coming soon)', 'tecai-ally' ); ?>
					</h2>
					<p>
						<?php esc_html_e( 'TecAI Ally Pro will unlock voice support, long-term memory, advanced analytics, and multi-model fallbacks (OpenAI, Groq) while keeping your site lightweight.', 'tecai-ally' ); ?>
					</p>
					<p>
						<a href="https://tecdevs.net/" target="_blank" class="button button-secondary">
							<?php esc_html_e( 'Learn about TecAI Ally Pro', 'tecai-ally' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}
}
