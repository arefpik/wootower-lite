<?php
/**
 * REST endpoint that receives Telegram's webhook updates.
 *
 * @package WooTower\Channels\Telegram
 */

namespace WooTower\Channels\Telegram;

use WooTower\Channels\BotWebhookController;
use WooTower\Channels\MessagingChannelInterface;
use WooTower\Support\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramWebhookController extends BotWebhookController {

	private const ROUTE_PATH    = '/telegram-webhook';
	private const SECRET_HEADER = 'x-telegram-bot-api-secret-token';

	protected function routePattern(): string {
		return self::ROUTE_PATH;
	}

	/**
	 * Confirms the request actually came from Telegram by comparing the
	 * secret token header against the one we registered via setupWebhook().
	 */
	public function verifyRequest( \WP_REST_Request $request ): bool {
		$expected = Config::getTelegramWebhookSecret();
		$received = (string) $request->get_header( self::SECRET_HEADER );

		return ! empty( $expected ) && hash_equals( $expected, $received );
	}

	protected function buildChannel(): MessagingChannelInterface {
		return new TelegramChannel( Config::getTelegramBotToken(), Config::getTelegramWebhookSecret() );
	}

	protected function allowedChatId(): string {
		return Config::getTelegramChatId();
	}

	public static function getWebhookUrl(): string {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH );
	}
}
