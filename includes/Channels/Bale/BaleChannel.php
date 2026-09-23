<?php
/**
 * Bale (بله) implementation of the messaging channel contract. Bale's bot
 * API mirrors Telegram's, and is reachable from Iranian hosts without a
 * proxy. It requires answerCallbackQuery within seconds of a button tap,
 * which BotApiChannel::acknowledgeCallback() covers.
 *
 * @package WooTower\Channels\Bale
 */

namespace WooTower\Channels\Bale;

use WooTower\Channels\BotApiChannel;
use WooTower\Support\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BaleChannel extends BotApiChannel {

	private const API_BASE_URL = 'https://tapi.bale.ai/bot';

	/** The configured bot, or null while no Bale token is saved. */
	public static function fromConfig(): ?self {
		$token = Config::getBaleBotToken();

		return '' === $token ? null : new self( $token, Config::getBaleWebhookSecret() );
	}

	protected function apiBaseUrl(): string {
		return self::API_BASE_URL;
	}

	protected function channelName(): string {
		return 'Bale';
	}
}
