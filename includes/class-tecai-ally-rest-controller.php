<?php
/**
 * REST API controller for TecAI Ally.
 *
 * @package TecAI_Ally
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	/**
	 * Minimal stub for WP_REST_Controller to allow CLI tests without WordPress.
	 */
	class WP_REST_Controller {}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * Minimal stub for WP_REST_Server to allow CLI tests without WordPress.
	 */
	class WP_REST_Server {
		const CREATABLE = 'POST';
		const DELETABLE = 'DELETE';
	}
}

/**
 * TecAI Ally REST controller.
 */
class TecAI_Ally_REST_Controller extends WP_REST_Controller {

	/**
	 * Settings handler.
	 *
	 * @var TecAI_Ally_Settings
	 */
	protected $settings;

	/**
	 * WooCommerce helper.
	 *
	 * @var TecAI_Ally_WooCommerce_Helper
	 */
	protected $wc_helper;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'tecai-ally/v1';

	/**
	 * Constructor.
	 *
	 * @param TecAI_Ally_Settings          $settings  Settings handler.
	 * @param TecAI_Ally_WooCommerce_Helper $wc_helper WooCommerce helper.
	 */
	public function __construct( TecAI_Ally_Settings $settings, TecAI_Ally_WooCommerce_Helper $wc_helper ) {
		$this->settings  = $settings;
		$this->wc_helper = $wc_helper;
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/conversation/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_conversation' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/escalate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'escalate_to_human' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Check whether a request is rate limited.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool
	 */
	public function is_rate_limited( WP_REST_Request $request ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$key      = 'tecai_ally_rate_' . md5( $ip );
		$window   = 60; // seconds.
		$max_hits = 20;

		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			$data = array(
				'count'    => 1,
				'start'    => time(),
			);
			set_transient( $key, $data, $window );
			return false;
		}

		if ( time() - (int) $data['start'] > $window ) {
			$data['count'] = 1;
			$data['start'] = time();
			set_transient( $key, $data, $window );
			return false;
		}

		$data['count'] ++;
		set_transient( $key, $data, $window );

		return ( $data['count'] > $max_hits );
	}

	/**
	 * Handle chat messages.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_chat( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		// Handle both JSON body and form data
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		// Get nonce from body params or header
		$nonce = isset( $params['tecai_ally_nonce'] ) ? $params['tecai_ally_nonce'] : '';
		if ( empty( $nonce ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
		}
		if ( empty( $nonce ) ) {
			$nonce = isset( $_REQUEST['tecai_ally_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tecai_ally_nonce'] ) ) : '';
		}

		// Verify nonce - try both wp_rest and custom nonce
		$nonce_check = false;
		if ( ! empty( $nonce ) ) {
			$nonce_check = wp_verify_nonce( $nonce, 'tecai_ally_chat' );
			if ( ! $nonce_check ) {
				// Try wp_rest nonce as fallback
				$nonce_check = wp_verify_nonce( $nonce, 'wp_rest' );
			}
		}
		
		// Check if nonce is at least present and not empty
		if ( empty( $nonce ) ) {
			error_log( 'TecAI Ally: Missing nonce in request. Params: ' . print_r( array_keys( $params ), true ) );
			return new WP_Error( 'tecai_ally_forbidden', __( 'Security check failed. Please refresh the page and try again.', 'tecai-ally' ), array( 'status' => 403 ) );
		}
		
		// If nonce verification fails, check if we're on localhost
		if ( ! $nonce_check ) {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
			$is_localhost = (
				in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) 
				|| strpos( $host, 'localhost' ) !== false
				|| strpos( $host, '127.0.0.1' ) !== false
				|| strpos( $host, '.local' ) !== false
			);
			
			if ( ! $is_localhost ) {
				error_log( 'TecAI Ally: Nonce verification failed. Host: ' . $host . ', Nonce: ' . substr( $nonce, 0, 10 ) . '...' );
				return new WP_Error( 'tecai_ally_forbidden', __( 'Security check failed. Please refresh the page and try again.', 'tecai-ally' ), array( 'status' => 403 ) );
			} else {
				// On localhost, log but allow (for development)
				error_log( 'TecAI Ally: Nonce verification failed on localhost (' . $host . '), allowing for development. Nonce: ' . substr( $nonce, 0, 10 ) . '...' );
			}
		}

		if ( $this->is_rate_limited( $request ) ) {
			return new WP_Error( 'tecai_ally_rate_limited', __( 'You are sending messages too quickly. Please wait a moment and try again.', 'tecai-ally' ), array( 'status' => 429 ) );
		}

		$message         = isset( $params['message'] ) ? sanitize_text_field( wp_unslash( $params['message'] ) ) : '';
		$conversation_id = isset( $params['conversation_id'] ) ? sanitize_text_field( wp_unslash( $params['conversation_id'] ) ) : '';
		$consent         = ! empty( $params['store_consent'] );
		$email           = isset( $params['email'] ) ? sanitize_email( wp_unslash( $params['email'] ) ) : '';

		if ( '' === $message ) {
			return new WP_Error( 'tecai_ally_empty', __( 'Please enter a message.', 'tecai-ally' ), array( 'status' => 400 ) );
		}

		$api_key = $this->settings->get_api_key();

		if ( '' === $api_key ) {
			$response = array(
				'bot_message'      => __( 'Please add your Gemini API key in Settings  WP Gemini AI Support Bot.', 'tecai-ally' ),
				'conversation_id'  => $conversation_id,
				'suggest_human'    => false,
				'needs_api_key'    => true,
			);

			return rest_ensure_response( $response );
		}

		$options = $this->settings->get_options();

		$order_context = '';
		$order_id      = null;

		if ( ! empty( $options['enable_woocommerce'] ) && $this->wc_helper->is_available() ) {
			$order_id = $this->wc_helper->find_order_id_in_message( $message );
			if ( $order_id ) {
				$order_context = $this->wc_helper->get_order_context_summary( $order_id );
			}
		}

		$prompt = $this->build_prompt( $message, $order_context, $email );

		// Log API call for debugging
		error_log( 'TecAI Ally: Calling Gemini API with model: ' . $options['gemini_model'] . ', API key length: ' . strlen( $api_key ) );

		$gemini_result = $this->call_gemini( $api_key, $options['gemini_model'], $prompt );

		if ( is_wp_error( $gemini_result ) ) {
			$error_code = $gemini_result->get_error_code();
			$error_data = $gemini_result->get_error_data();
			$error_message = $gemini_result->get_error_message();

			// Log error for debugging (always log to help diagnose issues)
			error_log( sprintf( 'TecAI Ally Error: Code=%s, Message=%s, Data=%s', $error_code, $error_message, wp_json_encode( $error_data ) ) );

			if ( 'tecai_ally_busy' === $error_code ) {
				$bot_message = __( "I'm busy, try again in a moment.", 'tecai-ally' );
			} elseif ( 'tecai_ally_gemini_http' === $error_code && is_array( $error_data ) ) {
				$http_code = isset( $error_data['http_code'] ) ? (int) $error_data['http_code'] : 0;

				if ( 401 === $http_code || 403 === $http_code ) {
					$bot_message = __( 'Your Gemini API key is invalid or not authorized for this project. Please verify the key in TecAI Ally settings and in Google AI Studio.', 'tecai-ally' );
				} elseif ( 404 === $http_code ) {
					$bot_message = __( 'The configured Gemini model was not found. Please ensure the model name is correct, for example: gemini-2.5-flash.', 'tecai-ally' );
				} elseif ( 429 === $http_code ) {
					$bot_message = __( "I'm receiving too many requests. Please wait a moment and try again.", 'tecai-ally' );
				} elseif ( ! empty( $error_data['error_message'] ) ) {
					$bot_message = $error_data['error_message'];
				} elseif ( ! empty( $error_message ) ) {
					$bot_message = $error_message;
				} else {
					$bot_message = __( 'The AI service returned an error. Please try again or check your server logs for details.', 'tecai-ally' );
				}
			} elseif ( 'tecai_ally_gemini_parse' === $error_code ) {
				if ( ! empty( $error_message ) ) {
					$bot_message = $error_message;
				} else {
					$bot_message = __( 'The AI service returned an unexpected response. Please try again.', 'tecai-ally' );
				}
			} else {
				// Handle other WP_Error types (network errors, etc.)
				if ( ! empty( $error_message ) ) {
					// Show user-friendly message but log the actual error
					if ( strpos( $error_message, 'cURL error' ) !== false || strpos( $error_message, 'SSL' ) !== false ) {
						$bot_message = __( 'Connection error. Please check your server\'s SSL configuration and internet connection.', 'tecai-ally' );
					} else {
						$bot_message = sprintf( __( 'Connection error: %s', 'tecai-ally' ), $error_message );
					}
				} else {
					$bot_message = __( 'Sorry, something went wrong while contacting the AI service. Please check your API key and try again.', 'tecai-ally' );
				}
			}
		} else {
			$bot_message = $gemini_result;
		}

		$suggest_human = false;

		if ( preg_match( '/\[\[ESCALATE:\s*(yes|no)\s*\]\]/i', $bot_message, $m ) ) {
			$suggest_human = ( 'yes' === strtolower( $m[1] ) );
			$bot_message   = trim( str_replace( $m[0], '', $bot_message ) );
		}

		if ( empty( $conversation_id ) ) {
			if ( function_exists( 'wp_generate_uuid4' ) ) {
				$conversation_id = 'tecai_' . wp_generate_uuid4();
			} else {
				$conversation_id = 'tecai_' . md5( microtime() . wp_rand() );
			}
		}

		if ( ! empty( $options['store_conversations'] ) && $consent ) {
			$this->append_to_conversation( $conversation_id, $message, $bot_message, $email );
		}

		$response = array(
			'bot_message'     => $bot_message,
			'conversation_id' => $conversation_id,
			'suggest_human'   => $suggest_human,
			'needs_api_key'   => false,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Delete a stored conversation.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_conversation( WP_REST_Request $request ) {
		$nonce = $request->get_param( 'tecai_ally_nonce' );

		if ( ! wp_verify_nonce( $nonce, 'tecai_ally_chat' ) ) {
			return new WP_Error( 'tecai_ally_forbidden', __( 'Security check failed.', 'tecai-ally' ), array( 'status' => 403 ) );
		}

		$conversation_id = sanitize_text_field( $request->get_param( 'id' ) );

		if ( empty( $conversation_id ) ) {
			return new WP_Error( 'tecai_ally_missing', __( 'Conversation not found.', 'tecai-ally' ), array( 'status' => 404 ) );
		}

		$args  = array(
			'post_type'      => 'tecai_ally_conversation',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_tecai_ally_conversation_id',
			'meta_value'     => $conversation_id,
		);
		$posts = get_posts( $args );

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Handle manual transfer to human.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function escalate_to_human( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		$nonce = isset( $params['tecai_ally_nonce'] ) ? $params['tecai_ally_nonce'] : '';

		if ( ! wp_verify_nonce( $nonce, 'tecai_ally_chat' ) ) {
			return new WP_Error( 'tecai_ally_forbidden', __( 'Security check failed.', 'tecai-ally' ), array( 'status' => 403 ) );
		}

		$conversation_id = isset( $params['conversation_id'] ) ? sanitize_text_field( wp_unslash( $params['conversation_id'] ) ) : '';
		$transcript      = isset( $params['transcript'] ) ? wp_kses_post( wp_unslash( $params['transcript'] ) ) : '';
		$email           = isset( $params['email'] ) ? sanitize_email( wp_unslash( $params['email'] ) ) : '';
		$consent         = ! empty( $params['store_consent'] );

		if ( '' === $transcript ) {
			return new WP_Error( 'tecai_ally_empty', __( 'Transcript is empty.', 'tecai-ally' ), array( 'status' => 400 ) );
		}

		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf( __( 'TecAI Ally  Conversation escalated (%s)', 'tecai-ally' ), $conversation_id );

		$body_lines   = array();
		$body_lines[] = __( 'A conversation has been escalated to a human agent.', 'tecai-ally' );
		$body_lines[] = '';
		if ( ! empty( $email ) ) {
			$body_lines[] = sprintf( __( 'Customer email: %s', 'tecai-ally' ), $email );
			$body_lines[] = '';
		}
		$body_lines[] = __( 'Transcript:', 'tecai-ally' );
		$body_lines[] = '------------------------------';
		$body_lines[] = wp_strip_all_tags( $transcript );

		wp_mail( $admin_email, $subject, implode( "\n", $body_lines ) );

		$options = $this->settings->get_options();

		if ( ! empty( $options['store_conversations'] ) && $consent ) {
			$this->append_escalated_conversation_post( $conversation_id, $transcript, $email );
		}

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Build a system prompt for Gemini.
	 *
	 * @param string $message       User message.
	 * @param string $order_context Order context, if any.
	 * @param string $email         Optional email.
	 *
	 * @return string
	 */
	protected function build_prompt( $message, $order_context, $email ) {
		$site_name = get_bloginfo( 'name' );

		$intro = "You are TecAI Ally, an AI customer support assistant for the WooCommerce store '{$site_name}'.";
		$intro .= " You answer customer questions about orders, refunds, products, shipping, delivery, account issues, and general customer support.";

		$intents = 'Recognise intents: order status/tracking, refund/return, product questions, shipping/delivery, account/login issues, small talk, and when to escalate to a human.';

		$rules  = 'Use the ORDER CONTEXT provided when answering order related questions. If you are not sure or the user is frustrated, politely suggest speaking with a human and include "[[ESCALATE: yes]]" at the end of your reply.';
		$rules .= ' Otherwise include "[[ESCALATE: no]]" at the end of your reply. Keep answers concise and friendly.';

		$context_lines = array( $intro, $intents, $rules );

		if ( ! empty( $order_context ) ) {
			$context_lines[] = 'ORDER CONTEXT:';
			$context_lines[] = $order_context;
		}

		if ( ! empty( $email ) ) {
			$context_lines[] = 'CUSTOMER EMAIL (if needed for account lookup): ' . $email;
		}

		$context_lines[] = 'CUSTOMER QUESTION:';
		$context_lines[] = $message;

		return implode( "\n\n", $context_lines );
	}

	/**
	 * Call the Gemini API and return the model reply text.
	 *
	 * @param string $api_key API key.
	 * @param string $model   Model name.
	 * @param string $prompt  Prompt text.
	 *
	 * @return string|WP_Error
	 */
	protected function call_gemini( $api_key, $model, $prompt ) {
		$model = $model ? $model : 'gemini-2.5-flash';

		// Use v1beta endpoint (standard for Gemini API)
		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		$body = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array(
							'text' => $prompt,
						),
					),
				),
			),
		);

		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 30,
			'body'    => wp_json_encode( $body ),
			'sslverify' => true,
		);
		
		// For localhost, disable SSL verification if needed
		if ( defined( 'WP_LOCAL_DEV' ) && WP_LOCAL_DEV ) {
			$args['sslverify'] = false;
		}

		$max_retries = 2;
		$attempt     = 0;

		do {
			$attempt++;
			$response = wp_remote_post( $url, $args );

			if ( is_wp_error( $response ) ) {
				$error_msg = $response->get_error_message();
				error_log( 'TecAI Ally: wp_remote_post error: ' . $error_msg );
				if ( $attempt > $max_retries ) {
					return $response;
				}
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );

			if ( 429 === $code ) {
				if ( $attempt > $max_retries ) {
					return new WP_Error( 'tecai_ally_busy', __( "I'm busy, try again in a moment.", 'tecai-ally' ) );
				}

				sleep( 1 );
				continue;
			}

			if ( $code >= 500 && $attempt <= $max_retries ) {
				sleep( 1 );
				continue;
			}

			break;
		} while ( $attempt <= $max_retries );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$error_message = __( 'Gemini API error.', 'tecai-ally' );
			$error_data    = array( 'http_code' => $code );
			
			// Try to extract more details from the error response
			$error_body = json_decode( $body, true );
			if ( is_array( $error_body ) && isset( $error_body['error']['message'] ) ) {
				$error_message = $error_body['error']['message'];
				$error_data['error_message'] = $error_message;
			} elseif ( ! empty( $body ) ) {
				// If we have a body but no structured error, include it for debugging
				$error_data['raw_response'] = substr( $body, 0, 500 );
			}
			
			return new WP_Error( 'tecai_ally_gemini_http', $error_message, $error_data );
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'tecai_ally_gemini_parse', __( 'Invalid JSON response from Gemini API.', 'tecai-ally' ), array( 'http_code' => $code ) );
		}

		if ( empty( $data['candidates'] ) || ! is_array( $data['candidates'] ) ) {
			$error_message = __( 'No response candidates from Gemini API.', 'tecai-ally' );
			if ( isset( $data['error'] ) && isset( $data['error']['message'] ) ) {
				$error_message = $data['error']['message'];
			}
			return new WP_Error( 'tecai_ally_gemini_parse', $error_message, array( 'http_code' => $code ) );
		}

		if ( empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$error_message = __( 'Empty response from Gemini API.', 'tecai-ally' );
			if ( isset( $data['candidates'][0]['finishReason'] ) ) {
				$finish_reason = $data['candidates'][0]['finishReason'];
				if ( 'SAFETY' === $finish_reason ) {
					$error_message = __( 'The response was blocked due to safety filters.', 'tecai-ally' );
				} elseif ( 'RECITATION' === $finish_reason ) {
					$error_message = __( 'The response was blocked due to recitation concerns.', 'tecai-ally' );
				}
			}
			return new WP_Error( 'tecai_ally_gemini_parse', $error_message, array( 'http_code' => $code ) );
		}

		return (string) $data['candidates'][0]['content']['parts'][0]['text'];
	}

	/**
	 * Append a message pair to a stored conversation.
	 *
	 * @param string $conversation_id Conversation identifier.
	 * @param string $user_message    User message.
	 * @param string $bot_message     Bot reply.
	 * @param string $email           Optional email.
	 *
	 * @return void
	 */
	protected function append_to_conversation( $conversation_id, $user_message, $bot_message, $email ) {
		$post_id = $this->get_conversation_post_id( $conversation_id );

		$entry = array(
			'time'    => current_time( 'mysql' ),
			'user'    => $user_message,
			'bot'     => $bot_message,
			'email'   => $email,
		);

		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'tecai_ally_conversation',
					'post_status' => 'publish',
					'post_title'  => sprintf( __( 'Conversation %s', 'tecai-ally' ), $conversation_id ),
					'post_content'=> wp_json_encode( array( $entry ) ),
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_tecai_ally_conversation_id', $conversation_id );
			}

			return;
		}

		$content = get_post_field( 'post_content', $post_id );
		$history = json_decode( $content, true );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$history[] = $entry;

		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_json_encode( $history ),
			)
		);
	}

	/**
	 * Store an escalated conversation transcript.
	 *
	 * @param string $conversation_id Conversation identifier.
	 * @param string $transcript      Transcript content.
	 * @param string $email           Optional email.
	 *
	 * @return void
	 */
	protected function append_escalated_conversation_post( $conversation_id, $transcript, $email ) {
		$post_id = $this->get_conversation_post_id( $conversation_id );

		if ( ! $post_id ) {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'tecai_ally_conversation',
					'post_status' => 'publish',
					'post_title'  => sprintf( __( 'Conversation %s', 'tecai-ally' ), $conversation_id ),
					'post_content'=> wp_kses_post( $transcript ),
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, '_tecai_ally_conversation_id', $conversation_id );
				update_post_meta( $post_id, '_tecai_ally_escalated', 1 );
				if ( ! empty( $email ) ) {
					update_post_meta( $post_id, '_tecai_ally_email', $email );
				}
			}

			return;
		}

		update_post_meta( $post_id, '_tecai_ally_escalated', 1 );
		if ( ! empty( $email ) ) {
			update_post_meta( $post_id, '_tecai_ally_email', $email );
		}
	}

	/**
	 * Get the post ID for a stored conversation.
	 *
	 * @param string $conversation_id Conversation identifier.
	 *
	 * @return int|false
	 */
	protected function get_conversation_post_id( $conversation_id ) {
		$args = array(
			'post_type'      => 'tecai_ally_conversation',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_tecai_ally_conversation_id',
			'meta_value'     => $conversation_id,
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return false;
		}

		return (int) $posts[0];
	}
}
