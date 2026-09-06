<?php
/**
 * REST route the dashboard-app uses to read WooTower's operational counters.
 *
 * @package WooTower\Admin\Rest
 */

namespace WooTower\Admin\Rest;

use WooTower\Core\Stats\StatsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StatsController {

	private const ROUTE_NAMESPACE = 'wootower/v1';
	private const ROUTE_PATH      = '/stats';
	private const CAPABILITY      = 'manage_woocommerce';

	private StatsService $stats;

	public function __construct( StatsService $stats ) {
		$this->stats = $stats;
	}

	public function registerRoute(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_PATH,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'checkPermission' ],
			]
		);
	}

	public function checkPermission(): bool {
		return current_user_can( self::CAPABILITY );
	}

	public function handle(): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'notified_orders'       => $this->stats->getNotifiedCount(),
				'status_changed_orders' => $this->stats->getStatusChangedCount(),
				'pending_orders'        => $this->stats->getPendingCount(),
			],
			200
		);
	}
}
