<?php
/**
 * Shared implementation of the messaging channel contract for providers
 * that speak the Telegram Bot API dialect — Telegram itself, and Bale,
 * whose bot API mirrors Telegram's method names and payload shapes under a
 * different host. Subclasses only supply the endpoint and their name.
 *
 * @package WooTower\Channels
 */

namespace WooTower\Channels;

use WooTower\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BotApiChannel implements MessagingChannelInterface {

	private const REQUEST_TIMEOUT = 15;
	private const MAX_ATTEMPTS    = 2;

	/** @var string */
	private $botToken;

	/** @var string */
	private $webhookSecret;

	public function __construct( string $botToken, string $webhookSecret = '' ) {
		$this->botToken      = $botToken;
		$this->webhookSecret = $webhookSecret;
	}

	/** Method endpoint prefix, e.g. "https://api.telegram.org/bot" (the token follows). */
	abstract protected function apiBaseUrl(): string;

	/** Provider name for log messages. */
	abstract protected function channelName(): string;

	/**
	 * {@inheritDoc}
	 */
	public function sendMessage( string $chatId, string $text, array $keyboard = [] ): void {
		$params = [
			'chat_id' => $chatId,
			'text'    => $text,
		];

		if ( ! empty( $keyboard ) ) {
			$params['reply_markup'] = wp_json_encode( [ 'inline_keyboard' => $keyboard ] );
		}

		$this->request( 'sendMessage', $params );
	}

	/**
	 * {@inheritDoc}
	 */
	public function editMessageReplyMarkup( string $chatId, string $messageId, array $keyboard ): void {
		$this->request(
			'editMessageReplyMarkup',
			[
				'chat_id'      => $chatId,
				'message_id'   => $messageId,
				'reply_markup' => wp_json_encode( [ 'inline_keyboard' => $keyboard ] ),
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function acknowledgeCallback( array $payload ): void {
		$callbackId = (string) ( $payload['callback_query']['id'] ?? '' );

		if ( '' === $callbackId ) {
			return;
		}

		// One attempt only: a late acknowledgement is rejected as "query is
		// too old" anyway, so a retry would only add delay before the reply.
		$this->request( 'answerCallbackQuery', [ 'callback_query_id' => $callbackId ], 1 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function parseIncomingCommand( array $payload ): ParsedCommand {
		if ( isset( $payload['callback_query'] ) ) {
			return $this->parseCallbackQuery( $payload['callback_query'] );
		}

		if ( isset( $payload['message'] ) ) {
			return $this->parseMessage( $payload['message'] );
		}

		throw new \InvalidArgumentException( "Unsupported {$this->channelName()} update payload." );
	}

	/**
	 * {@inheritDoc}
	 */
	public function setupWebhook( string $webhookUrl ): bool {
		$params = [ 'url' => $webhookUrl ];

		if ( ! empty( $this->webhookSecret ) ) {
			$params['secret_token'] = $this->webhookSecret;
		}

		$response = $this->request( 'setWebhook', $params );

		return null !== $response && ! empty( $response['ok'] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function registerCommands( array $commands ): void {
		$this->request( 'setMyCommands', [ 'commands' => wp_json_encode( $commands ) ] );
	}

	/**
	 * Turns a "message" update into a normalized command. The first
	 * whitespace-separated token (minus its leading slash) is the command
	 * name; anything after it becomes the argument list.
	 *
	 * @param array $message Bot API message object.
	 */
	private function parseMessage( array $message ): ParsedCommand {
		$chatId = (string) ( $message['chat']['id'] ?? '' );
		$text   = trim( (string) ( $message['text'] ?? '' ) );
		$parts  = array_values( array_filter( explode( ' ', $text ), 'strlen' ) );

		$command = ltrim( (string) array_shift( $parts ), '/' );

		return new ParsedCommand( $chatId, $command, $parts, $message );
	}

	/**
	 * Turns a "callback_query" update (inline keyboard tap) into a
	 * normalized command. The button's callback_data is expected in the
	 * form "command:arg1:arg2".
	 *
	 * @param array $callbackQuery Bot API callback_query object.
	 */
	private function parseCallbackQuery( array $callbackQuery ): ParsedCommand {
		$chatId    = (string) ( $callbackQuery['message']['chat']['id'] ?? '' );
		$data      = trim( (string) ( $callbackQuery['data'] ?? '' ) );
		$parts     = explode( ':', $data );
		$messageId = isset( $callbackQuery['message']['message_id'] ) ? (string) $callbackQuery['message']['message_id'] : null;

		$command = (string) array_shift( $parts );

		return new ParsedCommand( $chatId, $command, $parts, $callbackQuery, $messageId );
	}

	/**
	 * Calls a Bot API method, retrying a limited number of times and
	 * logging (never silently swallowing) any failure.
	 *
	 * @param string $method      Bot API method name.
	 * @param array  $params      Request body parameters.
	 * @param int    $maxAttempts How many times to try before giving up.
	 */
	private function request( string $method, array $params, int $maxAttempts = self::MAX_ATTEMPTS ): ?array {
		$url = $this->apiBaseUrl() . $this->botToken . '/' . $method;

		for ( $attempt = 1; $attempt <= $maxAttempts; $attempt++ ) {
			$response = wp_remote_post(
				$url,
				[
					'timeout' => self::REQUEST_TIMEOUT,
					'body'    => $params,
				]
			);

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				return is_array( $body ) ? $body : null;
			}

			Logger::warning(
				"{$this->channelName()} API call to {$method} failed (attempt {$attempt}/{$maxAttempts}).",
				[ 'error' => $this->redactToken( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ) ) ]
			);
		}

		Logger::error( "{$this->channelName()} API call to {$method} failed after {$maxAttempts} attempts." );

		return null;
	}

	/**
	 * The request URL embeds the bot token, and some WP_Http transport
	 * errors include the failed URL in their message — strips it out before
	 * anything reaches the log.
	 */
	private function redactToken( string $text ): string {
		return '' !== $this->botToken ? str_replace( $this->botToken, '[redacted]', $text ) : $text;
	}
}
