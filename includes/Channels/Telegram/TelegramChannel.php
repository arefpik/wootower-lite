<?php
/**
 * Telegram implementation of the messaging channel contract.
 *
 * @package WooTower\Channels\Telegram
 */

namespace WooTower\Channels\Telegram;

use WooTower\Channels\MessagingChannelInterface;
use WooTower\Channels\ParsedCommand;
use WooTower\Support\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramChannel implements MessagingChannelInterface {

	private const API_BASE_URL    = 'https://api.telegram.org/bot';
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
	public function parseIncomingCommand( array $payload ): ParsedCommand {
		if ( isset( $payload['callback_query'] ) ) {
			return $this->parseCallbackQuery( $payload['callback_query'] );
		}

		if ( isset( $payload['message'] ) ) {
			return $this->parseMessage( $payload['message'] );
		}

		throw new \InvalidArgumentException( 'Unsupported Telegram update payload.' );
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
	 * Turns a Telegram "message" update into a normalized command.
	 * The first whitespace-separated token (minus its leading slash) is the
	 * command name; anything after it becomes the argument list.
	 *
	 * @param array $message Telegram message object.
	 */
	private function parseMessage( array $message ): ParsedCommand {
		$chatId = (string) ( $message['chat']['id'] ?? '' );
		$text   = trim( (string) ( $message['text'] ?? '' ) );
		$parts  = array_filter( explode( ' ', $text ), 'strlen' );
		$parts  = array_values( $parts );

		$command = ltrim( (string) array_shift( $parts ), '/' );

		return new ParsedCommand( $chatId, $command, $parts, $message );
	}

	/**
	 * Turns a Telegram "callback_query" update (inline keyboard tap) into a
	 * normalized command. The button's callback_data is expected in the form
	 * "command:arg1:arg2".
	 *
	 * @param array $callbackQuery Telegram callback_query object.
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
	 * Calls a Telegram Bot API method, retrying a limited number of times and
	 * logging (never silently swallowing) any failure.
	 *
	 * @param string $method Telegram Bot API method name.
	 * @param array  $params Request body parameters.
	 */
	private function request( string $method, array $params ): ?array {
		$url = self::API_BASE_URL . $this->botToken . '/' . $method;

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++ ) {
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
				"Telegram API call to {$method} failed (attempt {$attempt}/" . self::MAX_ATTEMPTS . ').',
				[ 'error' => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ) ]
			);
		}

		Logger::error( "Telegram API call to {$method} failed after " . self::MAX_ATTEMPTS . ' attempts.' );

		return null;
	}
}
