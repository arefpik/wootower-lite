<?php
/**
 * Telegram's connection test: reachability of api.telegram.org (directly
 * or through the given proxy), the token, the webhook, and delivery to the
 * admin chat. See BotApiConnectionTester for the checks themselves.
 *
 * @package WooTower\Channels\Telegram
 */

namespace WooTower\Channels\Telegram;

use WooTower\Channels\BotApiConnectionTester;
use WooTower\Support\TelegramProxy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramConnectionTester extends BotApiConnectionTester {

	private const API_BASE_URL = 'https://api.telegram.org/bot';

	/** Telegram answers an unknown token with 401, a malformed one with 404. */
	private const REJECTED_TOKEN_CODES = [ 401, 404 ];

	/** @var string */
	private $proxyUrl;

	/**
	 * @param string $botToken Bot token to test.
	 * @param string $proxyUrl Proxy to test through, or '' for a direct connection.
	 */
	public function __construct( string $botToken, string $proxyUrl ) {
		parent::__construct( $botToken );
		$this->proxyUrl = $proxyUrl;
	}

	protected function apiBaseUrl(): string {
		return self::API_BASE_URL;
	}

	protected function expectedWebhookUrl(): string {
		return TelegramWebhookController::getWebhookUrl();
	}

	protected function rejectedTokenCodes(): array {
		return self::REJECTED_TOKEN_CODES;
	}

	protected function send( callable $call ) {
		return TelegramProxy::runWith( $this->proxyUrl, $call );
	}

	protected function connectionRoute(): string {
		return '' === $this->proxyUrl
			? __( 'direct connection, no proxy', 'wootower' )
			: __( 'through the proxy', 'wootower' );
	}

	/**
	 * Exceeds the usual 40-line limit because it's a flat list of strings;
	 * splitting it would only scatter them.
	 */
	protected function messages(): array {
		return [
			'reach_title'           => __( 'Connection to Telegram', 'wootower' ),
			'token_rejected'        => __( 'Your server reached Telegram, but Telegram rejected the bot token. Copy the token again from @BotFather — it looks like 123456789:ABC-DEF...', 'wootower' ),
			/* translators: 1: HTTP status code, 2: Telegram's error description */
			'unexpected_response'   => __( 'Telegram answered with an unexpected response (HTTP %1$d): %2$s', 'wootower' ),
			/* translators: 1: bot username, 2: round-trip time in milliseconds, 3: "direct connection" or "through the proxy" */
			'connected'             => __( 'Connected to bot @%1$s in %2$d ms (%3$s). The token is valid.', 'wootower' ),
			'webhook_title'         => __( 'Webhook (Telegram → your site)', 'wootower' ),
			'webhook_unreadable'    => __( 'Could not read the webhook status from Telegram. Run the test again in a moment.', 'wootower' ),
			'webhook_missing'       => __( 'No webhook is registered yet, so button taps in Telegram won\'t reach your store. Click "Save Settings" to register it automatically.', 'wootower' ),
			/* translators: %s: the webhook URL Telegram currently has on file */
			'webhook_mismatch'      => __( 'The webhook points to a different address (%s). Click "Save Settings" to point it back to this site.', 'wootower' ),
			/* translators: %s: Telegram's own delivery error message */
			'webhook_recent_error'  => __( 'Telegram recently failed to deliver updates to your site: "%s". Your site must be reachable from the internet over HTTPS with a valid certificate. Some Iranian hosts block incoming traffic from abroad — if so, ask your host to allow Telegram\'s IP ranges 149.154.160.0/20 and 91.108.4.0/22.', 'wootower' ),
			'webhook_ok'            => __( 'The webhook is registered to this site and Telegram reports no recent delivery errors.', 'wootower' ),
			'delivery_title'        => __( 'Test message to the admin chat', 'wootower' ),
			'delivery_test_message' => __( '✅ WooTower connection test: your store can send messages to this chat.', 'wootower' ),
			'delivery_ok'           => __( 'A test message was sent — check your Telegram.', 'wootower' ),
			/* translators: %s: Telegram's error description */
			'delivery_refused'      => __( 'Telegram refused to deliver the message (%s). Open your bot in Telegram and press Start first; for a group, add the bot to the group and double-check the numeric Chat ID.', 'wootower' ),
		];
	}

	/**
	 * The raw cURL error is kept (token redacted) because it's what a host's
	 * support team will ask for, but it's preceded by the likely cause in
	 * plain words.
	 */
	protected function describeNetworkError( string $technicalError ): string {
		if ( '' === $this->proxyUrl ) {
			/* translators: %s: technical cURL error message */
			return sprintf( __( 'Your server could not reach api.telegram.org. This is expected on hosts inside Iran, where Telegram is filtered — turn on the Telegram proxy above, enter a proxy address, and run the test again. Technical details: %s', 'wootower' ), $technicalError );
		}

		/* translators: %s: technical cURL error message */
		return sprintf( __( 'The connection through the proxy failed. Check the proxy address, port and credentials, and make sure the proxy server is online and can itself reach Telegram. If your host filters DNS, use socks5h:// instead of socks5://. Technical details: %s', 'wootower' ), $technicalError );
	}
}
