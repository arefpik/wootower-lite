<?php
/**
 * REST endpoint that receives Telegram's webhook updates.
 *
 * @package WooTower\Channels\Telegram
 */

namespace WooTower\Channels\Telegram;

use WooTower\Channels\ParsedCommand;
use WooTower\Core\Notifications\OrderStatusKeyboard;
use WooTower\Core\Orders\OrderRepository;
use WooTower\Core\Orders\OrderService;
use WooTower\Support\Config;
use WooTower\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramWebhookController {

	private const ROUTE_NAMESPACE      = 'wootower/v1';
	private const ROUTE_PATH           = '/telegram-webhook';
	private const SECRET_HEADER        = 'x-telegram-bot-api-secret-token';
	private const ORDER_STATUS_COMMAND = 'order_status';

	/**
	 * Commands that map to a Pro-only feature: Free shows them in the bot's
	 * command list but only ever replies with an upsell message, never the
	 * real feature.
	 */
	private const PRO_LOCKED_COMMANDS = [ 'products' ];

	public function registerRoute(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			self::ROUTE_PATH,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'verifySecretToken' ],
			]
		);
	}

	/**
	 * Confirms the request actually came from Telegram by comparing the
	 * secret token header against the one we registered via setupWebhook().
	 */
	public function verifySecretToken( \WP_REST_Request $request ): bool {
		$expected = Config::getTelegramWebhookSecret();
		$received = (string) $request->get_header( self::SECRET_HEADER );

		return ! empty( $expected ) && hash_equals( $expected, $received );
	}

	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$payload = $request->get_json_params();

		if ( ! is_array( $payload ) ) {
			return new \WP_REST_Response( null, 400 );
		}

		try {
			$channel = new TelegramChannel( Config::getTelegramBotToken(), Config::getTelegramWebhookSecret() );
			$command = $channel->parseIncomingCommand( $payload );

			$this->routeCommand( $command, $channel );
		} catch ( \InvalidArgumentException $e ) {
			// Telegram sends update types we don't act on (e.g. my_chat_member
			// when the bot is added to a group); that's routine, not an error.
			Logger::info( 'Ignored an unsupported Telegram update type.' );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to process an incoming Telegram webhook update.', [ 'exception' => $e->getMessage() ] );
		}

		// Telegram only cares about a 2xx response; errors are logged, not surfaced here.
		return new \WP_REST_Response( null, 200 );
	}

	/**
	 * Routes a parsed command to the matching Core service. Only known
	 * commands are handled; anything else is silently ignored (not an error).
	 */
	private function routeCommand( ParsedCommand $command, TelegramChannel $channel ): void {
		if ( in_array( $command->command, self::PRO_LOCKED_COMMANDS, true ) ) {
			$this->sendProUpsell( $command->chatId, $channel );
			return;
		}

		if ( self::ORDER_STATUS_COMMAND !== $command->command ) {
			return;
		}

		[ $orderId, $status ] = $command->args + [ null, null ];

		if ( null === $orderId || null === $status ) {
			return;
		}

		$status       = sanitize_key( $status );
		$orderService = new OrderService( new OrderRepository() );
		$changed      = $orderService->changeStatus( (int) $orderId, $status );

		if ( ! $changed ) {
			$channel->sendMessage( $command->chatId, __( 'Could not update the order status.', 'wootower' ) );
			return;
		}

		// Edit the original message's keyboard in place rather than sending a
		// separate confirmation: with multiple admins in the same chat/group,
		// a pile of "updated" messages makes it unclear who changed what
		// last, while the keyboard always reflects the order's real state.
		if ( null !== $command->messageId ) {
			$channel->editMessageReplyMarkup(
				$command->chatId,
				$command->messageId,
				OrderStatusKeyboard::build( Config::getStatusButtons(), (int) $orderId, $status )
			);
		}
	}

	/**
	 * Replies with an upsell message instead of running the real (Pro-only)
	 * feature. Free must never actually perform the Pro action.
	 */
	private function sendProUpsell( string $chatId, TelegramChannel $channel ): void {
		$channel->sendMessage(
			$chatId,
			__( 'This feature is part of WooTower Pro. Upgrade to unlock it.', 'wootower' )
		);
	}

	public static function getWebhookUrl(): string {
		return rest_url( self::ROUTE_NAMESPACE . self::ROUTE_PATH );
	}
}
