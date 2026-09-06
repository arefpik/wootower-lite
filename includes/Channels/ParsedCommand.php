<?php
/**
 * Normalized representation of an incoming channel command, independent of the source channel.
 *
 * @package WooTower\Channels
 */

namespace WooTower\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ParsedCommand {

	/** @var string */
	public $chatId;

	/** @var string */
	public $command;

	/** @var array */
	public $args;

	/** @var array */
	public $rawPayload;

	/** @var string|null Identifier of the message the command originated from, if any. */
	public $messageId;

	/**
	 * @param string      $chatId     Identifier of the chat the command came from.
	 * @param string      $command    Command name, without leading slash (e.g. "start").
	 * @param array       $args       Extra arguments parsed from the command.
	 * @param array       $rawPayload Original payload as received from the channel.
	 * @param string|null $messageId  Identifier of the originating message, if any (e.g. the
	 *                                message an inline button was attached to).
	 */
	public function __construct( string $chatId, string $command, array $args = [], array $rawPayload = [], ?string $messageId = null ) {
		$this->chatId     = $chatId;
		$this->command    = $command;
		$this->args       = $args;
		$this->rawPayload = $rawPayload;
		$this->messageId  = $messageId;
	}
}
