<?php
/**
 * Coordinates turning a "new order" WooCommerce event into a dispatched notification.
 *
 * @package WooTower\Core\Notifications
 */

namespace WooTower\Core\Notifications;

use WooTower\Core\Orders\OrderService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NewOrderListener {

	private OrderService $orderService;

	private NotificationDispatcher $dispatcher;

	public function __construct( OrderService $orderService, NotificationDispatcher $dispatcher ) {
		$this->orderService = $orderService;
		$this->dispatcher   = $dispatcher;
	}

	public function handle( int $orderId ): void {
		$summary = $this->orderService->getOrderSummary( $orderId );

		if ( null === $summary ) {
			return;
		}

		$this->dispatcher->dispatchNewOrder( $summary );
	}
}
