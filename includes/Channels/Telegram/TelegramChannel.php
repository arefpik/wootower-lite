<?php
/**
 * Telegram implementation of the messaging channel contract. All requests
 * go to api.telegram.org, which TelegramProxy can route through a proxy on
 * hosts where Telegram is blocked.
 *
 * @package WooTower\Channels\Telegram
 */

namespace WooTower\Channels\Telegram;

use WooTower\Channels\BotApiChannel;
use WooTower\Support\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramChannel extends BotApiChannel {

	private const API_BASE_URL = 'https://api.telegram.org/bot';

	/** The configured bot, or null while no Telegram token is saved. */
	public static function fromConfig(): ?self {
		$token = Config::getTelegramBotToken();

		return '' === $token ? null : new self( $token, Config::getTelegramWebhookSecret() );
	}

	protected function apiBaseUrl(): string {
		return self::API_BASE_URL;
	}

	protected function channelName(): string {
		return 'Telegram';
	}
}
