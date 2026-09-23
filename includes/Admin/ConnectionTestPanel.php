<?php
/**
 * "Test Connection" rows on the settings screen (one per messenger) and the
 * admin-ajax handler behind them. Tests the values currently typed into the
 * form (token, chat id, proxy) — not only the saved ones — so an admin can
 * find a working setup before saving anything.
 *
 * @package WooTower\Admin
 */

namespace WooTower\Admin;

use WooTower\Channels\Bale\BaleConnectionTester;
use WooTower\Channels\BotApiConnectionTester;
use WooTower\Channels\Telegram\TelegramConnectionTester;
use WooTower\Support\Config;
use WooTower\Support\TelegramProxy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConnectionTestPanel {

	private const AJAX_ACTION  = 'wootower_test_telegram_connection';
	private const NONCE_ACTION = 'wootower_test_telegram_connection';
	private const CAPABILITY   = 'manage_woocommerce';

	private const CHANNEL_TELEGRAM = 'telegram';
	private const CHANNEL_BALE     = 'bale';

	/**
	 * Form field ids each messenger's test reads its values from.
	 */
	private const FIELDS = [
		self::CHANNEL_TELEGRAM => [
			'token' => 'wootower_bot_token',
			'chat'  => 'wootower_chat_id',
		],
		self::CHANNEL_BALE     => [
			'token' => 'wootower_bale_bot_token',
			'chat'  => 'wootower_bale_chat_id',
		],
	];

	public static function register(): void {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'handleAjax' ] );
	}

	public static function handleAjax(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to change WooTower settings.', 'wootower' ) ], 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$channel  = self::CHANNEL_BALE === self::postedText( 'channel' ) ? self::CHANNEL_BALE : self::CHANNEL_TELEGRAM;
		$botToken = self::postedText( 'bot_token' );
		$chatId   = self::postedText( 'chat_id' );

		if ( '' === $botToken ) {
			$botToken = self::CHANNEL_BALE === $channel ? Config::getBaleBotToken() : Config::getTelegramBotToken();
		}

		if ( '' === $botToken ) {
			wp_send_json_error( [ 'message' => __( 'Enter your bot token first, then run the test.', 'wootower' ) ] );
		}

		if ( 1 !== preg_match( '/^-?\d+$/', $chatId ) ) {
			$chatId = '';
		}

		wp_send_json_success( self::buildTester( $channel, $botToken )->run( $chatId ) );
	}

	private static function buildTester( string $channel, string $botToken ): BotApiConnectionTester {
		if ( self::CHANNEL_BALE === $channel ) {
			return new BaleConnectionTester( $botToken );
		}

		$proxyEnabled = '1' === self::postedText( 'proxy_enabled' );
		$proxyUrl     = $proxyEnabled ? self::postedText( 'proxy_url' ) : '';

		if ( $proxyEnabled && ! TelegramProxy::isValidProxyUrl( $proxyUrl ) ) {
			wp_send_json_error( [ 'message' => __( 'Enter a valid proxy URL (e.g. http://host:port, https://host:port, socks5://host:port, or socks5h://host:port) before enabling the Telegram proxy.', 'wootower' ) ] );
		}

		return new TelegramConnectionTester( $botToken, $proxyUrl );
	}

	private static function postedText( string $field ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_ajax_referer() in handleAjax() before any field is read.
		return isset( $_POST[ $field ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : '';
	}

	/**
	 * @param string $channel "telegram" or "bale".
	 */
	public static function renderRow( string $channel ): void {
		$isBale = self::CHANNEL_BALE === $channel;
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Connection Test', 'wootower' ); ?></th>
			<td>
				<button
					type="button"
					class="button button-secondary wootower-test-connection"
					data-channel="<?php echo esc_attr( $channel ); ?>"
					data-token-field="<?php echo esc_attr( self::FIELDS[ $channel ]['token'] ); ?>"
					data-chat-field="<?php echo esc_attr( self::FIELDS[ $channel ]['chat'] ); ?>"
				>
					<?php esc_html_e( 'Test Connection', 'wootower' ); ?>
				</button>
				<p class="description">
					<?php
					if ( $isBale ) {
						esc_html_e( 'Checks, using the values currently in this form (no need to save first): whether your server can reach Bale, whether the bot token is valid, whether the webhook points to your site, and whether the bot can message your Chat ID.', 'wootower' );
					} else {
						esc_html_e( 'Checks, using the values currently in this form (no need to save first): whether your server can reach Telegram directly or through the proxy, whether the bot token is valid, whether Telegram can deliver updates to your site, and whether the bot can message your Chat ID.', 'wootower' );
					}
					?>
				</p>
				<div class="wootower-test-connection-result" style="margin-top: 12px;" aria-live="polite"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * One script for every test button on the page. Exceeds the usual
	 * 40-line limit because it is a plain JS template; splitting it would
	 * hurt readability without reducing complexity.
	 */
	public static function renderScript(): void {
		$strings = [
			'testing' => __( 'Testing the connection… this can take up to 30 seconds.', 'wootower' ),
			'failed'  => __( 'The test request itself failed. Reload the page and try again.', 'wootower' ),
			'allOk'   => __( 'Everything works. Your store and the messenger can talk to each other.', 'wootower' ),
			'someBad' => __( 'Some checks failed — see the details below.', 'wootower' ),
		];
		?>
		<script>
		( function () {
			var strings = <?php echo wp_json_encode( $strings ); ?>;
			var icons   = { pass: '✅', warn: '⚠️', fail: '❌' };
			var colors  = { pass: '#00a32a', warn: '#dba617', fail: '#d63638' };

			function valueOf( id ) {
				var field = document.getElementById( id );
				return field ? field.value : '';
			}

			function line( text, bold ) {
				var node = document.createElement( bold ? 'strong' : 'p' );
				node.textContent = text;
				if ( bold ) {
					node.style.display = 'block';
					node.style.marginBottom = '8px';
				}
				return node;
			}

			function renderChecks( output, result ) {
				output.innerHTML = '';
				output.appendChild( line( result.ok ? strings.allOk : strings.someBad, true ) );

				result.checks.forEach( function ( check ) {
					var box = document.createElement( 'div' );
					box.style.cssText = 'border-inline-start: 4px solid ' + colors[ check.status ] + '; background: #fff; padding: 8px 12px; margin-bottom: 8px; max-width: 720px;';
					box.appendChild( line( icons[ check.status ] + ' ' + check.title, true ) );
					var detail = line( check.detail, false );
					detail.style.margin = '0';
					box.appendChild( detail );
					output.appendChild( box );
				} );
			}

			function showError( output, message ) {
				output.innerHTML = '';
				output.appendChild( line( '❌ ' + message, false ) );
			}

			document.querySelectorAll( '.wootower-test-connection' ).forEach( function ( button ) {
				var output = button.parentNode.querySelector( '.wootower-test-connection-result' );

				button.addEventListener( 'click', function () {
					var proxyToggle = document.getElementById( 'wootower_telegram_proxy_enabled' );
					var data = new FormData();
					data.append( 'action', <?php echo wp_json_encode( self::AJAX_ACTION ); ?> );
					data.append( 'nonce', <?php echo wp_json_encode( wp_create_nonce( self::NONCE_ACTION ) ); ?> );
					data.append( 'channel', button.dataset.channel );
					data.append( 'bot_token', valueOf( button.dataset.tokenField ) );
					data.append( 'chat_id', valueOf( button.dataset.chatField ) );
					data.append( 'proxy_enabled', proxyToggle && proxyToggle.checked ? '1' : '0' );
					data.append( 'proxy_url', valueOf( 'wootower_telegram_proxy_url' ) );

					button.disabled = true;
					output.innerHTML = '';
					output.appendChild( line( strings.testing, false ) );

					fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } )
						.then( function ( response ) { return response.json(); } )
						.then( function ( json ) {
							if ( json && json.success ) {
								renderChecks( output, json.data );
								return;
							}
							showError( output, json && json.data && json.data.message ? json.data.message : strings.failed );
						} )
						.catch( function () {
							showError( output, strings.failed );
						} )
						.finally( function () {
							button.disabled = false;
						} );
				} );
			} );
		} )();
		</script>
		<?php
	}
}
