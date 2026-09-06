<?php
/**
 * Formats and sends order notifications through the active messaging channel.
 *
 * Depends only on MessagingChannelInterface, never on a concrete channel,
 * so swapping Telegram for another channel later requires no change here.
 *
 * @package WooTower\Core\Notifications
 */

namespace WooTower\Core\Notifications;

use WooTower\Channels\MessagingChannelInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NotificationDispatcher {

	private MessagingChannelInterface $channel;

	private string $chatId;

	private string $messageTemplate;

	/** @var array Admin-defined list of ['label' => string, 'status' => string]. */
	private array $statusButtons;

	/**
	 * @param string $messageTemplate Message text with {order_number}/{customer}/
	 *                                {total}/{status}/{items} placeholders.
	 * @param array  $statusButtons   List of ['label' => string, 'status' => string]
	 *                                shown as inline buttons under the message.
	 */
	public function __construct( MessagingChannelInterface $channel, string $chatId, string $messageTemplate, array $statusButtons ) {
		$this->channel         = $channel;
		$this->chatId          = $chatId;
		$this->messageTemplate = $messageTemplate;
		$this->statusButtons   = $statusButtons;
	}

	/**
	 * Sends a new-order notification with inline status-change buttons.
	 *
	 * @param array $order Order summary, as returned by OrderService::getOrderSummary().
	 */
	public function dispatchNewOrder( array $order ): void {
		if ( empty( $this->chatId ) ) {
			return;
		}

		$this->channel->sendMessage(
			$this->chatId,
			$this->renderTemplate( $order ),
			OrderStatusKeyboard::build( $this->statusButtons, $order['id'], $order['status'] )
		);

		/**
		 * Fires after WooTower has sent a new-order notification.
		 *
		 * @param int $orderId The order ID.
		 */
		do_action( 'wootower_new_order_notified', $order['id'] );
	}

	/**
	 * Substitutes order placeholders into the admin-configured template.
	 * Uses strtr() (simultaneous replacement) instead of chained str_replace()
	 * calls so a value that happens to contain "{...}" text can never be
	 * mistaken for another placeholder.
	 */
	private function renderTemplate( array $order ): string {
		$tokens = [
			'{order_number}' => (string) $order['number'],
			'{customer}'     => (string) $order['customer'],
			'{phone}'        => (string) $order['phone'],
			'{email}'        => (string) $order['email'],
			'{total}'        => (string) $order['total'],
			'{status}'       => (string) $order['status'],
			'{items}'        => $this->formatItemsList( $order['items'] ),
		];

		return strtr( $this->messageTemplate, $tokens );
	}

	/**
	 * Each item's own custom fields (e.g. a game top-up product's account
	 * id, or any other plugin-added checkout field) are listed right under
	 * it, indented — there's no fixed set of these to expose as separate
	 * placeholders, since a store can add new custom fields to any product
	 * at any time.
	 */
	private function formatItemsList( array $items ): string {
		$lines = [];

		foreach ( $items as $item ) {
			$lines[] = sprintf( '- %s x%d — %s', $item['name'], $item['quantity'], $item['total'] );

			foreach ( $item['meta'] as $meta ) {
				$lines[] = sprintf( '  %s: %s', $meta['label'], $meta['value'] );
			}
		}

		return implode( "\n", $lines );
	}
}
