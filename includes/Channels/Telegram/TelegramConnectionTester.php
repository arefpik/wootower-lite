<?php
/**
 * Diagnoses the Telegram connection end to end, for the "Test Connection"
 * button on the settings screen: can this server reach api.telegram.org
 * (directly or through the given proxy), is the token valid, is the
 * webhook registered and receiving updates, and can the bot message the
 * admin chat.
 *
 * Talks to the Bot API directly instead of through TelegramChannel on
 * purpose: TelegramChannel retries and only logs failures, while a
 * diagnosis needs the exact error of a single attempt to explain it.
 *
 * @package WooTower\Channels\Telegram
 */

namespace WooTower\Channels\Telegram;

use WooTower\Support\TelegramProxy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramConnectionTester {

	private const API_BASE_URL = 'https://api.telegram.org/bot';

	/**
	 * Kept short so the whole test (up to three calls) stays well under
	 * the 30s PHP time limit most shared hosts enforce.
	 */
	private const REACH_TIMEOUT    = 10;
	private const FOLLOWUP_TIMEOUT = 8;

	/** A webhook delivery error older than this is treated as already resolved. */
	private const RECENT_ERROR_WINDOW = DAY_IN_SECONDS;

	public const STATUS_PASS = 'pass';
	public const STATUS_WARN = 'warn';
	public const STATUS_FAIL = 'fail';

	/** @var string */
	private $botToken;

	/** @var string */
	private $proxyUrl;

	/**
	 * @param string $botToken Bot token to test.
	 * @param string $proxyUrl Proxy to test through, or '' for a direct connection.
	 */
	public function __construct( string $botToken, string $proxyUrl ) {
		$this->botToken = $botToken;
		$this->proxyUrl = $proxyUrl;
	}

	/**
	 * Runs every check that makes sense given the earlier results — there's
	 * no point checking the webhook if Telegram can't be reached at all.
	 *
	 * @param string $chatId Admin chat to send a test message to, or '' to skip that check.
	 * @return array{ok: bool, checks: array<int, array{status: string, title: string, detail: string}>}
	 */
	public function run( string $chatId ): array {
		$reach  = $this->checkReachability();
		$checks = [ $reach ];

		if ( self::STATUS_PASS === $reach['status'] ) {
			$checks[] = $this->checkWebhook();

			if ( '' !== $chatId ) {
				$checks[] = $this->checkMessageDelivery( $chatId );
			}
		}

		$failed = array_filter(
			$checks,
			static function ( array $check ): bool {
				return self::STATUS_FAIL === $check['status'];
			}
		);

		return [
			'ok'     => empty( $failed ),
			'checks' => $checks,
		];
	}

	private function checkReachability(): array {
		$startedAt = microtime( true );
		$response  = $this->call( 'getMe', [], self::REACH_TIMEOUT );
		$elapsedMs = (int) round( ( microtime( true ) - $startedAt ) * 1000 );
		$title     = __( 'Connection to Telegram', 'wootower' );

		if ( is_wp_error( $response ) ) {
			return $this->result( self::STATUS_FAIL, $title, $this->describeNetworkError( $response ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code || 404 === $code ) {
			return $this->result(
				self::STATUS_FAIL,
				$title,
				__( 'Your server reached Telegram, but Telegram rejected the bot token. Copy the token again from @BotFather — it looks like 123456789:ABC-DEF...', 'wootower' )
			);
		}

		$body = $this->decode( $response );

		if ( 200 !== $code || empty( $body['ok'] ) ) {
			return $this->result(
				self::STATUS_FAIL,
				$title,
				/* translators: 1: HTTP status code, 2: Telegram's error description */
				sprintf( __( 'Telegram answered with an unexpected response (HTTP %1$d): %2$s', 'wootower' ), $code, $this->describeApiError( $body ) )
			);
		}

		$via = '' === $this->proxyUrl
			? __( 'direct connection, no proxy', 'wootower' )
			: __( 'through the proxy', 'wootower' );

		return $this->result(
			self::STATUS_PASS,
			$title,
			/* translators: 1: bot username, 2: round-trip time in milliseconds, 3: "direct connection" or "through the proxy" */
			sprintf( __( 'Connected to bot @%1$s in %2$d ms (%3$s). The token is valid.', 'wootower' ), $body['result']['username'] ?? '', $elapsedMs, $via )
		);
	}

	/**
	 * Outgoing calls working doesn't mean incoming ones do: Telegram must
	 * also be able to POST updates to this site, which fails when the site
	 * isn't publicly reachable over HTTPS or the host drops foreign traffic.
	 */
	private function checkWebhook(): array {
		$title    = __( 'Webhook (Telegram → your site)', 'wootower' );
		$response = $this->call( 'getWebhookInfo', [], self::FOLLOWUP_TIMEOUT );
		$body     = is_wp_error( $response ) ? null : $this->decode( $response );

		if ( empty( $body['ok'] ) ) {
			return $this->result( self::STATUS_WARN, $title, __( 'Could not read the webhook status from Telegram. Run the test again in a moment.', 'wootower' ) );
		}

		$info     = $body['result'];
		$expected = TelegramWebhookController::getWebhookUrl();

		if ( empty( $info['url'] ) ) {
			return $this->result( self::STATUS_WARN, $title, __( 'No webhook is registered yet, so button taps in Telegram won\'t reach your store. Click "Save Settings" to register it automatically.', 'wootower' ) );
		}

		if ( $info['url'] !== $expected ) {
			return $this->result(
				self::STATUS_WARN,
				$title,
				/* translators: %s: the webhook URL Telegram currently has on file */
				sprintf( __( 'The webhook points to a different address (%s). Click "Save Settings" to point it back to this site.', 'wootower' ), $info['url'] )
			);
		}

		$lastErrorAt = (int) ( $info['last_error_date'] ?? 0 );

		if ( $lastErrorAt > 0 && ( time() - $lastErrorAt ) < self::RECENT_ERROR_WINDOW && ! empty( $info['last_error_message'] ) ) {
			return $this->result(
				self::STATUS_WARN,
				$title,
				/* translators: %s: Telegram's own delivery error message */
				sprintf( __( 'Telegram recently failed to deliver updates to your site: "%s". Your site must be reachable from the internet over HTTPS with a valid certificate. Some Iranian hosts block incoming traffic from abroad — if so, ask your host to allow Telegram\'s IP ranges 149.154.160.0/20 and 91.108.4.0/22.', 'wootower' ), $info['last_error_message'] )
			);
		}

		return $this->result( self::STATUS_PASS, $title, __( 'The webhook is registered to this site and Telegram reports no recent delivery errors.', 'wootower' ) );
	}

	private function checkMessageDelivery( string $chatId ): array {
		$title    = __( 'Test message to the admin chat', 'wootower' );
		$response = $this->call(
			'sendMessage',
			[
				'chat_id' => $chatId,
				'text'    => __( '✅ WooTower connection test: your store can send messages to this chat.', 'wootower' ),
			],
			self::FOLLOWUP_TIMEOUT
		);

		if ( is_wp_error( $response ) ) {
			return $this->result( self::STATUS_FAIL, $title, $this->describeNetworkError( $response ) );
		}

		$body = $this->decode( $response );

		if ( ! empty( $body['ok'] ) ) {
			return $this->result( self::STATUS_PASS, $title, __( 'A test message was sent — check your Telegram.', 'wootower' ) );
		}

		return $this->result(
			self::STATUS_FAIL,
			$title,
			/* translators: %s: Telegram's error description */
			sprintf( __( 'Telegram refused to deliver the message (%s). Open your bot in Telegram and press Start first; for a group, add the bot to the group and double-check the numeric Chat ID.', 'wootower' ), $this->describeApiError( $body ) )
		);
	}

	/**
	 * The raw cURL error is kept (token redacted) because it's what a host's
	 * support team will ask for, but it's preceded by the likely cause in
	 * plain words.
	 */
	private function describeNetworkError( \WP_Error $error ): string {
		$technical = $this->redactToken( $error->get_error_message() );

		if ( '' === $this->proxyUrl ) {
			/* translators: %s: technical cURL error message */
			return sprintf( __( 'Your server could not reach api.telegram.org. This is expected on hosts inside Iran, where Telegram is filtered — turn on the Telegram proxy above, enter a proxy address, and run the test again. Technical details: %s', 'wootower' ), $technical );
		}

		/* translators: %s: technical cURL error message */
		return sprintf( __( 'The connection through the proxy failed. Check the proxy address, port and credentials, and make sure the proxy server is online and can itself reach Telegram. If your host filters DNS, use socks5h:// instead of socks5://. Technical details: %s', 'wootower' ), $technical );
	}

	private function describeApiError( ?array $body ): string {
		return isset( $body['description'] ) ? (string) $body['description'] : __( 'no description', 'wootower' );
	}

	/**
	 * @return array|\WP_Error
	 */
	private function call( string $method, array $params, int $timeout ) {
		$url = self::API_BASE_URL . $this->botToken . '/' . $method;

		return TelegramProxy::runWith(
			$this->proxyUrl,
			static function () use ( $url, $params, $timeout ) {
				return wp_remote_post(
					$url,
					[
						'timeout' => $timeout,
						'body'    => $params,
					]
				);
			}
		);
	}

	private function decode( array $response ): ?array {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : null;
	}

	private function result( string $status, string $title, string $detail ): array {
		return [
			'status' => $status,
			'title'  => $title,
			'detail' => $detail,
		];
	}

	/** Some transport errors echo the request URL, which embeds the token. */
	private function redactToken( string $text ): string {
		return '' !== $this->botToken ? str_replace( $this->botToken, '[redacted]', $text ) : $text;
	}
}
