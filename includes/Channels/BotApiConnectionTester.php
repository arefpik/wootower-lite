<?php
/**
 * Diagnoses a bot connection end to end, for the "Test Connection" buttons
 * on the settings screen: can this server reach the messenger's API, is
 * the token valid, is the webhook registered and receiving updates, and can
 * the bot message the admin chat. Subclasses supply the endpoint and the
 * messenger-specific wording and advice.
 *
 * Talks to the Bot API directly instead of through BotApiChannel on
 * purpose: the channel retries and only logs failures, while a diagnosis
 * needs the exact error of a single attempt to explain it.
 *
 * @package WooTower\Channels
 */

namespace WooTower\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BotApiConnectionTester {

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
	protected $botToken;

	/** @param string $botToken Bot token to test. */
	public function __construct( string $botToken ) {
		$this->botToken = $botToken;
	}

	/** Method endpoint prefix, e.g. "https://api.telegram.org/bot" (the token follows). */
	abstract protected function apiBaseUrl(): string;

	/** The webhook address this site registers with the messenger. */
	abstract protected function expectedWebhookUrl(): string;

	/** HTTP status codes the messenger answers a wrong token with. */
	abstract protected function rejectedTokenCodes(): array;

	/**
	 * The user-facing wording for every outcome, keyed by outcome — see
	 * TelegramConnectionTester::messages() for the full set of keys.
	 *
	 * @return array<string, string>
	 */
	abstract protected function messages(): array;

	/** A likely cause, in plain words, for a failed connection, plus the technical error. */
	abstract protected function describeNetworkError( string $technicalError ): string;

	/**
	 * Performs one HTTP call. Overridden where the connection needs extra
	 * setup (Telegram's optional proxy).
	 *
	 * @param callable $call Makes the request and returns its result.
	 * @return array|\WP_Error
	 */
	protected function send( callable $call ) {
		return $call();
	}

	/**
	 * Runs every check that makes sense given the earlier results — there's
	 * no point checking the webhook if the messenger can't be reached at all.
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
		$text      = $this->messages();
		$startedAt = microtime( true );
		$response  = $this->call( 'getMe', [], self::REACH_TIMEOUT );
		$elapsedMs = (int) round( ( microtime( true ) - $startedAt ) * 1000 );
		$title     = $text['reach_title'];

		if ( is_wp_error( $response ) ) {
			return $this->result( self::STATUS_FAIL, $title, $this->describeNetworkError( $this->redactToken( $response->get_error_message() ) ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( in_array( $code, $this->rejectedTokenCodes(), true ) ) {
			return $this->result( self::STATUS_FAIL, $title, $text['token_rejected'] );
		}

		$body = $this->decode( $response );

		if ( 200 !== $code || empty( $body['ok'] ) ) {
			return $this->result( self::STATUS_FAIL, $title, sprintf( $text['unexpected_response'], $code, $this->describeApiError( $body ) ) );
		}

		return $this->result(
			self::STATUS_PASS,
			$title,
			sprintf( $text['connected'], $body['result']['username'] ?? '', $elapsedMs, $this->connectionRoute() )
		);
	}

	/** How the request travelled, for the success message; see TelegramConnectionTester. */
	protected function connectionRoute(): string {
		return __( 'direct connection, no proxy', 'wootower' );
	}

	/**
	 * Outgoing calls working doesn't mean incoming ones do: the messenger
	 * must also be able to POST updates to this site, which fails when the
	 * site isn't publicly reachable over HTTPS or the host drops foreign traffic.
	 */
	private function checkWebhook(): array {
		$text     = $this->messages();
		$title    = $text['webhook_title'];
		$response = $this->call( 'getWebhookInfo', [], self::FOLLOWUP_TIMEOUT );
		$body     = is_wp_error( $response ) ? null : $this->decode( $response );

		if ( empty( $body['ok'] ) ) {
			return $this->result( self::STATUS_WARN, $title, $text['webhook_unreadable'] );
		}

		$info = $body['result'];

		if ( empty( $info['url'] ) ) {
			return $this->result( self::STATUS_WARN, $title, $text['webhook_missing'] );
		}

		if ( $info['url'] !== $this->expectedWebhookUrl() ) {
			return $this->result( self::STATUS_WARN, $title, sprintf( $text['webhook_mismatch'], $this->redactWebhookUrl( (string) $info['url'] ) ) );
		}

		// Only Telegram reports delivery errors; Bale's getWebhookInfo has no such fields.
		$lastErrorAt = (int) ( $info['last_error_date'] ?? 0 );

		if ( $lastErrorAt > 0 && ( time() - $lastErrorAt ) < self::RECENT_ERROR_WINDOW && ! empty( $info['last_error_message'] ) ) {
			return $this->result( self::STATUS_WARN, $title, sprintf( $text['webhook_recent_error'], $info['last_error_message'] ) );
		}

		return $this->result( self::STATUS_PASS, $title, $text['webhook_ok'] );
	}

	/**
	 * Bale's webhook URL embeds its secret; a mismatching URL could be an
	 * old one of ours, so only its host and path start are ever shown.
	 */
	private function redactWebhookUrl( string $url ): string {
		return preg_replace( '#(/bale-webhook/)[A-Za-z0-9]+#', '$1…', $url );
	}

	private function checkMessageDelivery( string $chatId ): array {
		$text     = $this->messages();
		$title    = $text['delivery_title'];
		$response = $this->call(
			'sendMessage',
			[
				'chat_id' => $chatId,
				'text'    => $text['delivery_test_message'],
			],
			self::FOLLOWUP_TIMEOUT
		);

		if ( is_wp_error( $response ) ) {
			return $this->result( self::STATUS_FAIL, $title, $this->describeNetworkError( $this->redactToken( $response->get_error_message() ) ) );
		}

		$body = $this->decode( $response );

		if ( ! empty( $body['ok'] ) ) {
			return $this->result( self::STATUS_PASS, $title, $text['delivery_ok'] );
		}

		return $this->result( self::STATUS_FAIL, $title, sprintf( $text['delivery_refused'], $this->describeApiError( $body ) ) );
	}

	private function describeApiError( ?array $body ): string {
		return isset( $body['description'] ) ? (string) $body['description'] : __( 'no description', 'wootower' );
	}

	/**
	 * @return array|\WP_Error
	 */
	private function call( string $method, array $params, int $timeout ) {
		$url = $this->apiBaseUrl() . $this->botToken . '/' . $method;

		return $this->send(
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
