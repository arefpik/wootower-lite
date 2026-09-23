<?php
/**
 * REST endpoint that receives Bale's webhook updates. The secret lives in
 * the URL path (/bale-webhook/<secret>) rather than a header, since Bale's
 * docs don't define Telegram's secret-token header.
 *
 * @package WooTower\Channels\Bale
 */

namespace WooTower\Channels\Bale;

use WooTower\Channels\BotWebhookController;
use WooTower\Channels\MessagingChannelInterface;
use WooTower\Support\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BaleWebhookController extends BotWebhookController {

	private const ROUTE_PATH = '/bale-webhook';

	protected function routePattern(): string {
		return self::ROUTE_PATH . '/(?P<secret>[A-Za-z0-9]+)';
	}

	/**
	 * Also refuses every request while no Bale bot is configured, so the
	 * route is inert on stores that only use Telegram.
	 */
	public function verifyRequest( \WP_REST_Request $request ): bool {
		if ( '' === Config::getBaleBotToken() ) {
			return false;
		}

		$received = (string) $request->get_param( 'secret' );

		return hash_equals( Config::getBaleWebhookSecret(), $received );
	}

	protected function buildChannel(): MessagingChannelInterface {
		return new BaleChannel( Config::getBaleBotToken(), Config::getBaleWebhookSecret() );
	}

	protected function allowedChatId(): string {
		return Config::getBaleChatId();
	}

	public static function getWebhookUrl(): string {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH . '/' . Config::getBaleWebhookSecret() );
	}
}
