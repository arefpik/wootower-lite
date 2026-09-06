<?php
/**
 * Business logic for reading order summaries and changing order status.
 *
 * @package WooTower\Core\Orders
 */

namespace WooTower\Core\Orders;

use WooTower\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderService {

	private OrderRepository $repository;

	public function __construct( OrderRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Returns a plain-array summary of an order, suitable for formatting a
	 * notification message, or null if the order doesn't exist.
	 */
	public function getOrderSummary( int $orderId ): ?array {
		$order = $this->repository->find( $orderId );

		if ( ! $order ) {
			return null;
		}

		return [
			'id'       => $order->get_id(),
			'number'   => $order->get_order_number(),
			'total'    => $this->formatPlainTextTotal( $order ),
			'customer' => trim( $order->get_formatted_billing_full_name() ),
			'phone'    => $order->get_billing_phone(),
			'email'    => $order->get_billing_email(),
			'status'   => $order->get_status(),
			'items'    => $this->formatItems( $order ),
		];
	}

	/**
	 * Changes an order's status after validating it against WooCommerce's
	 * own registered statuses, so an unknown/forged status is never applied.
	 */
	public function changeStatus( int $orderId, string $newStatus ): bool {
		if ( ! $this->isValidStatus( $newStatus ) ) {
			Logger::warning( "Rejected order status change to unknown status \"{$newStatus}\".", [ 'order_id' => $orderId ] );
			return false;
		}

		$order = $this->repository->find( $orderId );

		if ( ! $order ) {
			Logger::warning( 'Attempted to change status of a non-existent order.', [ 'order_id' => $orderId ] );
			return false;
		}

		$this->repository->updateStatus( $order, $newStatus );

		/**
		 * Fires after an order's status has been changed through WooTower.
		 *
		 * @param int    $orderId   The order ID.
		 * @param string $newStatus The new order status, without the "wc-" prefix.
		 */
		do_action( 'wootower_order_status_changed', $orderId, $newStatus );

		return true;
	}

	private function isValidStatus( string $status ): bool {
		return array_key_exists( 'wc-' . $status, wc_get_order_statuses() );
	}

	/**
	 * get_formatted_order_total() returns HTML meant for wp-admin, which
	 * would leak raw markup into a plain-text Telegram message; this strips
	 * and decodes it down to plain text (e.g. "$200.00").
	 */
	private function formatPlainTextTotal( \WC_Order $order ): string {
		return $this->stripHtmlToPlainText( $order->get_formatted_order_total() );
	}

	private function formatItems( \WC_Order $order ): array {
		$items = [];

		foreach ( $order->get_items() as $item ) {
			$items[] = [
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $this->stripHtmlToPlainText( wc_price( $item->get_total(), [ 'currency' => $order->get_currency() ] ) ),
				'meta'     => $this->formatItemMeta( $item ),
			];
		}

		return $items;
	}

	/**
	 * Order items can carry arbitrary custom field data — variation
	 * attributes, "Player ID" / account fields from a game top-up product,
	 * anything a product-addons plugin attaches — with no fixed set of
	 * keys known in advance. get_formatted_meta_data() is WooCommerce's own
	 * generic, forward-compatible way to read whatever was captured at
	 * checkout for this specific item, whatever product type added it.
	 */
	private function formatItemMeta( \WC_Order_Item $item ): array {
		$meta = [];

		foreach ( $item->get_formatted_meta_data() as $metaItem ) {
			$meta[] = [
				'label' => $this->stripHtmlToPlainText( $metaItem->display_key ),
				'value' => $this->stripHtmlToPlainText( $metaItem->display_value ),
			];
		}

		return $meta;
	}

	/**
	 * WooCommerce price/meta helpers return HTML meant for wp-admin or the
	 * storefront; this strips and decodes it down to plain text.
	 */
	private function stripHtmlToPlainText( string $html ): string {
		return html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES );
	}
}
