<?php
/**
 * Builds the inline keyboard of order-status buttons, shared by the initial
 * notification and the in-place edit made after a status change.
 *
 * @package WooTower\Core\Notifications
 */

namespace WooTower\Core\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderStatusKeyboard {

	/**
	 * @param array       $statusButtons Admin-defined list of ['label' => string, 'status' => string].
	 * @param int         $orderId       Order the buttons act on.
	 * @param string|null $currentStatus When given, the button matching this status is marked
	 *                                   with a checkmark, so the keyboard always reflects the
	 *                                   order's real current state — regardless of which admin
	 *                                   last changed it, in a store with multiple admins sharing
	 *                                   the same chat.
	 */
	public static function build( array $statusButtons, int $orderId, ?string $currentStatus = null ): array {
		$keyboard = [];

		foreach ( $statusButtons as $button ) {
			$label = $button['status'] === $currentStatus ? '✅ ' . $button['label'] : $button['label'];

			$keyboard[] = [
				[
					'text'          => $label,
					'callback_data' => "order_status:{$orderId}:{$button['status']}",
				],
			];
		}

		return $keyboard;
	}
}
