<?php
/**
 * REST endpoint shape shared by every messenger's webhook: authenticate the
 * request the channel's own way, then hand the payload to BotUpdateHandler.
 *
 * @package WooTower\Channels
 */

namespace WooTower\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BotWebhookController {

	protected const ROUTE_NAMESPACE = 'wootower/v1';

	/** REST route (regex allowed) relative to ROUTE_NAMESPACE. */
	abstract protected function routePattern(): string;

	/** Whether the request really came from the webhook WooTower registered. */
	abstract public function verifyRequest( \WP_REST_Request $request ): bool;

	/** The configured channel to reply through. */
	abstract protected function buildChannel(): MessagingChannelInterface;

	/** The one chat on this channel allowed to act (the notification chat). */
	abstract protected function allowedChatId(): string;

	public function registerRoute(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			$this->routePattern(),
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'verifyRequest' ],
			]
		);
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return new \WP_REST_Response( null, 400 );
		}

		( new BotUpdateHandler() )->process( $payload, $this->buildChannel(), $this->allowedChatId() );

		// The provider only cares about a 2xx response; errors are logged, not surfaced here.
		return new \WP_REST_Response( null, 200 );
	}
}
