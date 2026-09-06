<?php
/**
 * Minimal REST route the dashboard-app uses to confirm it can reach the backend.
 *
 * @package WooTower\Admin\Rest
 */

namespace WooTower\Admin\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PingController {

	private const ROUTE_NAMESPACE = 'wootower/v1';
	private const ROUTE_PATH      = '/ping';
	private const CAPABILITY      = 'manage_woocommerce';

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
				'ok'      => true,
				'version' => WOOTOWER_VERSION,
			],
			200
		);
	}
}
