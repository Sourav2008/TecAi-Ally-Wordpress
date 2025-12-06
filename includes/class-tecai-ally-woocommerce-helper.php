<?php
/**
 * WooCommerce helper for TecAI Ally.
 *
 * @package TecAI_Ally
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce integration utilities.
 */
class TecAI_Ally_WooCommerce_Helper {

	/**
	 * Determine whether WooCommerce integration is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return function_exists( 'wc_get_order' );
	}

	/**
	 * Try to extract an order ID from a free-form message.
	 *
	 * @param string $message Visitor message.
	 *
	 * @return int|null
	 */
	public function find_order_id_in_message( $message ) {
		$message = strtolower( (string) $message );

		if ( preg_match( '/(?:order|#)\s*(\d{3,})/', $message, $matches ) ) {
			return (int) $matches[1];
		}

		return null;
	}

	/**
	 * Build a concise, text-only summary of a WooCommerce order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string
	 */
	public function get_order_context_summary( $order_id ) {
		if ( ! $this->is_available() ) {
			return '';
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return '';
		}

		$lines   = array();
		$lines[] = 'Order ID: ' . $order->get_id();
		$lines[] = 'Order status: ' . $order->get_status();
		$lines[] = 'Order total: ' . wp_strip_all_tags( wp_kses_post( $order->get_formatted_order_total() ) );

		$shipping_address = $order->get_formatted_shipping_address();
		if ( empty( $shipping_address ) ) {
			$shipping_address = $order->get_formatted_billing_address();
		}

		if ( ! empty( $shipping_address ) ) {
			$lines[] = 'Shipping address: ' . wp_strip_all_tags( $shipping_address );
		}

		$items = $order->get_items();
		if ( ! empty( $items ) ) {
			$item_summaries = array();
			foreach ( $items as $item ) {
				$item_summaries[] = $item->get_name() . ' x ' . $item->get_quantity();
			}
			$lines[] = 'Items: ' . implode( '; ', $item_summaries );
		}

		$tracking = $order->get_meta( '_tracking_number', true );
		if ( ! empty( $tracking ) ) {
			$lines[] = 'Tracking: ' . $tracking;
		}

		return implode( "\n", $lines );
	}
}
