<?php
/**
 * Single point of access for WooTower's wp_options-backed settings.
 *
 * @package WooTower\Support
 */

namespace WooTower\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Config {

	private const OPTION_BOT_TOKEN              = 'wootower_telegram_bot_token';
	private const OPTION_CHAT_ID                = 'wootower_telegram_chat_id';
	private const OPTION_WEBHOOK_SECRET         = 'wootower_telegram_webhook_secret';
	private const OPTION_NOTIFICATION_TEMPLATE  = 'wootower_notification_template';
	private const OPTION_STATUS_BUTTONS         = 'wootower_status_buttons';

	private const WEBHOOK_SECRET_LENGTH = 32;

	private const DEFAULT_NOTIFICATION_TEMPLATE = "New order #{order_number}\nCustomer: {customer}\nTotal: {total}\n{items}";

	public static function getTelegramBotToken(): string {
		return (string) get_option( self::OPTION_BOT_TOKEN, '' );
	}

	public static function setTelegramBotToken( string $token ): void {
		update_option( self::OPTION_BOT_TOKEN, $token );
	}

	public static function getTelegramChatId(): string {
		return (string) get_option( self::OPTION_CHAT_ID, '' );
	}

	public static function setTelegramChatId( string $chatId ): void {
		update_option( self::OPTION_CHAT_ID, $chatId );
	}

	/**
	 * Returns the secret used to verify Telegram's webhook requests, generating
	 * and persisting one on first use so the value stays stable across calls.
	 */
	public static function getTelegramWebhookSecret(): string {
		$secret = get_option( self::OPTION_WEBHOOK_SECRET, '' );

		if ( empty( $secret ) ) {
			$secret = wp_generate_password( self::WEBHOOK_SECRET_LENGTH, false );
			update_option( self::OPTION_WEBHOOK_SECRET, $secret );
		}

		return (string) $secret;
	}

	/**
	 * The message sent for a new order, with {order_number}/{customer}/
	 * {total}/{status}/{items} placeholders. Falls back to a sensible
	 * default so the feature keeps working even before an admin visits
	 * Settings.
	 */
	public static function getNotificationTemplate(): string {
		$template = get_option( self::OPTION_NOTIFICATION_TEMPLATE, '' );

		return '' !== $template ? (string) $template : self::DEFAULT_NOTIFICATION_TEMPLATE;
	}

	public static function setNotificationTemplate( string $template ): void {
		update_option( self::OPTION_NOTIFICATION_TEMPLATE, $template );
	}

	public static function getDefaultNotificationTemplate(): string {
		return self::DEFAULT_NOTIFICATION_TEMPLATE;
	}

	/**
	 * Admin-defined inline buttons shown under a new-order notification,
	 * each mapping a button label to a WooCommerce order status. Stored as
	 * a list of ['label' => string, 'status' => string].
	 */
	public static function getStatusButtons(): array {
		$buttons = get_option( self::OPTION_STATUS_BUTTONS, null );

		return is_array( $buttons ) ? $buttons : self::getDefaultStatusButtons();
	}

	public static function setStatusButtons( array $buttons ): void {
		update_option( self::OPTION_STATUS_BUTTONS, $buttons );
	}

	public static function getDefaultStatusButtons(): array {
		return [
			[
				'label'  => 'Mark as Processing',
				'status' => 'processing',
			],
			[
				'label'  => 'Mark as Completed',
				'status' => 'completed',
			],
		];
	}
}
