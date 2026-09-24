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
	private const OPTION_TELEGRAM_PROXY_ENABLED = 'wootower_telegram_proxy_enabled';
	private const OPTION_TELEGRAM_PROXY_URL     = 'wootower_telegram_proxy_url';
	private const OPTION_BALE_BOT_TOKEN         = 'wootower_bale_bot_token';
	private const OPTION_BALE_CHAT_ID           = 'wootower_bale_chat_id';
	private const OPTION_BALE_WEBHOOK_SECRET    = 'wootower_bale_webhook_secret';

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
		return self::getOrCreateSecret( self::OPTION_WEBHOOK_SECRET );
	}

	public static function getBaleBotToken(): string {
		return (string) get_option( self::OPTION_BALE_BOT_TOKEN, '' );
	}

	public static function setBaleBotToken( string $token ): void {
		update_option( self::OPTION_BALE_BOT_TOKEN, $token );
	}

	/** Bale chat that receives new-order notifications and may change their status. */
	public static function getBaleChatId(): string {
		return (string) get_option( self::OPTION_BALE_CHAT_ID, '' );
	}

	public static function setBaleChatId( string $chatId ): void {
		update_option( self::OPTION_BALE_CHAT_ID, $chatId );
	}

	/**
	 * Secret embedded in Bale's webhook URL. Bale's docs don't define
	 * Telegram's secret-token header, so the URL itself carries the proof
	 * that a request came from the webhook we registered.
	 */
	public static function getBaleWebhookSecret(): string {
		return self::getOrCreateSecret( self::OPTION_BALE_WEBHOOK_SECRET );
	}

	/** Generates and persists the secret on first use, so it stays stable across calls. */
	private static function getOrCreateSecret( string $option ): string {
		$secret = get_option( $option, '' );

		if ( empty( $secret ) ) {
			$secret = wp_generate_password( self::WEBHOOK_SECRET_LENGTH, false );
			update_option( $option, $secret );
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
		$template = (string) get_option( self::OPTION_NOTIFICATION_TEMPLATE, '' );

		// Before the default was translatable, saving the settings page stored the English default
		// verbatim — treat that exact text as "still the default" so it follows the site language too.
		return ( '' === $template || self::DEFAULT_NOTIFICATION_TEMPLATE === $template )
			? self::getDefaultNotificationTemplate()
			: $template;
	}

	public static function setNotificationTemplate( string $template ): void {
		update_option( self::OPTION_NOTIFICATION_TEMPLATE, $template );
	}

	/**
	 * The default message in the site's language. Kept as a literal (not the
	 * constant) so it's a normal translatable string; it must stay identical
	 * to DEFAULT_NOTIFICATION_TEMPLATE.
	 */
	public static function getDefaultNotificationTemplate(): string {
		/* translators: The default new-order message. Keep every {placeholder} exactly as it is. */
		return __( "New order #{order_number}\nCustomer: {customer}\nTotal: {total}\n{items}", 'wootower' );
	}

	/**
	 * Admin-defined inline buttons shown under a new-order notification,
	 * each mapping a button label to a WooCommerce order status. Stored as
	 * a list of ['label' => string, 'status' => string].
	 */
	public static function getStatusButtons(): array {
		$buttons = get_option( self::OPTION_STATUS_BUTTONS, null );

		if ( ! is_array( $buttons ) ) {
			return self::getDefaultStatusButtons();
		}

		// Buttons saved with the old, untranslated default labels are shown in the site's language;
		// labels an admin typed themselves are left exactly as they are.
		$defaultLabels = [
			'Mark as Processing' => __( 'Mark as Processing', 'wootower' ),
			'Mark as Completed'  => __( 'Mark as Completed', 'wootower' ),
		];

		return array_map(
			static function ( $button ) use ( $defaultLabels ) {
				if ( is_array( $button ) && isset( $button['label'] ) && isset( $defaultLabels[ $button['label'] ] ) ) {
					$button['label'] = $defaultLabels[ $button['label'] ];
				}
				return $button;
			},
			$buttons
		);
	}

	public static function setStatusButtons( array $buttons ): void {
		update_option( self::OPTION_STATUS_BUTTONS, $buttons );
	}

	public static function getDefaultStatusButtons(): array {
		return [
			[
				'label'  => __( 'Mark as Processing', 'wootower' ),
				'status' => 'processing',
			],
			[
				'label'  => __( 'Mark as Completed', 'wootower' ),
				'status' => 'completed',
			],
		];
	}

	/**
	 * Opt-in proxy used only for requests to api.telegram.org (see
	 * TelegramProxy) — never applied automatically and never affects any
	 * other outbound request WordPress makes. For hosts where
	 * api.telegram.org is blocked (e.g. inside Iran).
	 */
	public static function isTelegramProxyEnabled(): bool {
		return (bool) get_option( self::OPTION_TELEGRAM_PROXY_ENABLED, false );
	}

	public static function getTelegramProxyUrl(): string {
		return (string) get_option( self::OPTION_TELEGRAM_PROXY_URL, '' );
	}

	public static function setTelegramProxy( bool $enabled, string $url ): void {
		update_option( self::OPTION_TELEGRAM_PROXY_ENABLED, $enabled );
		update_option( self::OPTION_TELEGRAM_PROXY_URL, $url );
	}
}
