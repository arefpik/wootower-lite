<?php
/**
 * What the bot does with an incoming update, independent of which
 * messenger it arrived on: change an order's status from the buttons under
 * a new-order notification, and reply to Pro-only commands with an upsell.
 * Each channel's webhook controller authenticates the request and hands
 * the payload here, so Telegram and Bale behave identically.
 *
 * @package WooTower\Channels
 */

namespace WooTower\Channels;

use WooTower\Core\Notifications\OrderStatusKeyboard;
use WooTower\Core\Orders\OrderRepository;
use WooTower\Core\Orders\OrderService;
use WooTower\Support\Config;
use WooTower\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BotUpdateHandler {

	private const START_COMMAND        = 'start';
	private const ORDER_STATUS_COMMAND = 'order_status';

	/**
	 * Commands that map to a Pro-only feature: Free shows them in the bot's
	 * command list but only ever replies with an upsell message, never the
	 * real feature.
	 */
	private const PRO_LOCKED_COMMANDS = [ 'products' ];

	/**
	 * Processes one already-authenticated update. Never throws: the
	 * provider only needs a 2xx response, so errors are logged instead.
	 *
	 * @param array                     $payload       Raw update payload.
	 * @param MessagingChannelInterface $channel       Channel the update arrived on.
	 * @param string                    $allowedChatId The chat notifications go to on this channel.
	 */
	public function process( array $payload, MessagingChannelInterface $channel, string $allowedChatId ): void {
		try {
			$channel->acknowledgeCallback( $payload );
			$command = $channel->parseIncomingCommand( $payload );

			if ( self::START_COMMAND === $command->command ) {
				$this->sendChatId( $command->chatId, $channel );
				return;
			}

			// Only the notification chat may act: the webhook secret proves a
			// request came from the messenger, not who sent it — without this,
			// anyone who found the bot could type "/order_status 12 cancelled".
			if ( '' === $allowedChatId || $command->chatId !== $allowedChatId ) {
				return;
			}

			$this->routeCommand( $command, $channel );
		} catch ( \InvalidArgumentException $e ) {
			// Messengers send update types we don't act on (e.g. my_chat_member
			// when the bot is added to a group); that's routine, not an error.
			Logger::info( 'Ignored an unsupported bot update type.' );
		} catch ( \Throwable $e ) {
			Logger::error( 'Failed to process an incoming bot update.', [ 'exception' => $e->getMessage() ] );
		}
	}

	/**
	 * /start replies with the chat's own ID, which is what goes into the
	 * Chat ID setting — Bale has no equivalent of Telegram's @userinfobot.
	 */
	private function sendChatId( string $chatId, MessagingChannelInterface $channel ): void {
		$channel->sendMessage(
			$chatId,
			sprintf(
				/* translators: %s: the chat's numeric ID */
				__( "👋 This is your WooTower bot.\nYour chat ID: %s\nPaste it into WooTower > Settings to receive new-order notifications here.", 'wootower' ),
				$chatId
			)
		);
	}

	/**
	 * Routes a parsed command to the matching Core service. Only known
	 * commands are handled; anything else is silently ignored (not an error).
	 */
	private function routeCommand( ParsedCommand $command, MessagingChannelInterface $channel ): void {
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
	private function sendProUpsell( string $chatId, MessagingChannelInterface $channel ): void {
		$channel->sendMessage(
			$chatId,
			__( 'This feature is part of WooTower Pro. Upgrade to unlock it.', 'wootower' )
		);
	}
}
