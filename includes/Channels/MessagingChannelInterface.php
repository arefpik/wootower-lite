<?php
/**
 * Contract that every messaging channel (Telegram, WhatsApp, Discord, ...) must implement.
 *
 * @package WooTower\Channels
 */

namespace WooTower\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface MessagingChannelInterface {

	/**
	 * Sends a text message to a chat, optionally with an inline keyboard.
	 *
	 * @param string $chatId   Destination chat identifier.
	 * @param string $text     Message body.
	 * @param array  $keyboard Optional inline keyboard definition.
	 */
	public function sendMessage( string $chatId, string $text, array $keyboard = [] ): void;

	/**
	 * Replaces the inline keyboard on an existing message, without touching
	 * its text. Used to reflect a state change (e.g. an order's new status)
	 * directly on the original message instead of sending a follow-up one —
	 * important when multiple admins share the same chat/group, since a
	 * pile of separate confirmation messages makes it hard to tell who
	 * changed what last.
	 *
	 * @param string $chatId    Chat the message belongs to.
	 * @param string $messageId Identifier of the message to edit.
	 * @param array  $keyboard  New inline keyboard definition.
	 */
	public function editMessageReplyMarkup( string $chatId, string $messageId, array $keyboard ): void;

	/**
	 * Parses a raw incoming webhook payload into a normalized command.
	 *
	 * @param array $payload Raw payload received from the channel's webhook.
	 */
	public function parseIncomingCommand( array $payload ): ParsedCommand;

	/**
	 * Registers the given webhook URL with the channel provider.
	 *
	 * @param string $webhookUrl Publicly reachable webhook endpoint.
	 */
	public function setupWebhook( string $webhookUrl ): bool;

	/**
	 * Registers the list of bot commands with the channel provider.
	 *
	 * @param array $commands Command definitions to register.
	 */
	public function registerCommands( array $commands ): void;
}
