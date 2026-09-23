<?php
/**
 * Bale's connection test: reachability of tapi.bale.ai, the token, the
 * webhook, and delivery to the admin chat. See BotApiConnectionTester for
 * the checks themselves.
 *
 * @package WooTower\Channels\Bale
 */

namespace WooTower\Channels\Bale;

use WooTower\Channels\BotApiConnectionTester;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BaleConnectionTester extends BotApiConnectionTester {

	private const API_BASE_URL = 'https://tapi.bale.ai/bot';

	/** Verified against the live API: an unknown token gets 403 "Token not found". */
	private const REJECTED_TOKEN_CODES = [ 401, 403, 404 ];

	protected function apiBaseUrl(): string {
		return self::API_BASE_URL;
	}

	protected function expectedWebhookUrl(): string {
		return BaleWebhookController::getWebhookUrl();
	}

	protected function rejectedTokenCodes(): array {
		return self::REJECTED_TOKEN_CODES;
	}

	/**
	 * Exceeds the usual 40-line limit because it's a flat list of strings;
	 * splitting it would only scatter them.
	 */
	protected function messages(): array {
		return [
			'reach_title'           => __( 'Connection to Bale', 'wootower' ),
			'token_rejected'        => __( 'Your server reached Bale, but Bale rejected the bot token. Copy the token again from BotFather in Bale — it looks like 123456789:AbCdEf...', 'wootower' ),
			/* translators: 1: HTTP status code, 2: Bale's error description */
			'unexpected_response'   => __( 'Bale answered with an unexpected response (HTTP %1$d): %2$s', 'wootower' ),
			/* translators: 1: bot username, 2: round-trip time in milliseconds, 3: "direct connection, no proxy" */
			'connected'             => __( 'Connected to bot @%1$s in %2$d ms (%3$s). The token is valid.', 'wootower' ),
			'webhook_title'         => __( 'Webhook (Bale → your site)', 'wootower' ),
			'webhook_unreadable'    => __( 'Could not read the webhook status from Bale. Run the test again in a moment.', 'wootower' ),
			'webhook_missing'       => __( 'No webhook is registered yet, so button taps in Bale won\'t reach your store. Click "Save Settings" to register it automatically.', 'wootower' ),
			/* translators: %s: the webhook URL Bale currently has on file */
			'webhook_mismatch'      => __( 'The webhook points to a different address (%s). Click "Save Settings" to point it back to this site.', 'wootower' ),
			'webhook_recent_error'  => '%s',
			'webhook_ok'            => __( 'The webhook is registered to this site. (Bale doesn\'t report delivery errors — if buttons don\'t respond, make sure your site is reachable over HTTPS with a valid certificate.)', 'wootower' ),
			'delivery_title'        => __( 'Test message to the admin chat', 'wootower' ),
			'delivery_test_message' => __( '✅ WooTower connection test: your store can send messages to this chat.', 'wootower' ),
			'delivery_ok'           => __( 'A test message was sent — check your Bale.', 'wootower' ),
			/* translators: %s: Bale's error description */
			'delivery_refused'      => __( 'Bale refused to deliver the message (%s). Open your bot in Bale and press Start first; for a group, add the bot to the group and double-check the numeric Chat ID.', 'wootower' ),
		];
	}

	protected function describeNetworkError( string $technicalError ): string {
		/* translators: %s: technical cURL error message */
		return sprintf( __( 'Your server could not reach tapi.bale.ai. Bale is normally reachable from both Iranian and foreign servers without a proxy, so check your host\'s outbound internet access and firewall. Technical details: %s', 'wootower' ), $technicalError );
	}
}
