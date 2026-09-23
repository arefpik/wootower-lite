<?php
/**
 * Optional, admin-configured HTTP proxy for reaching api.telegram.org —
 * for servers that can't reach it directly (e.g. hosts in Iran, where
 * Telegram is blocked). Off by default and scoped only to Telegram API
 * requests: it never sets WordPress's global WP_PROXY_HOST/PORT constants,
 * which would route every outbound request (core cron, plugin updates,
 * WooCommerce's own calls) through the proxy — this only touches the one
 * cURL handle for a request whose URL is api.telegram.org.
 *
 * @package WooTower\Support
 */

namespace WooTower\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TelegramProxy {

	private const TELEGRAM_HOST = 'api.telegram.org';

	/**
	 * socks5h = SOCKS5 with DNS resolved by the proxy instead of this
	 * server — matters on hosts whose local resolver returns a poisoned
	 * address for api.telegram.org, where plain socks5 still fails.
	 */
	private const ALLOWED_SCHEMES = [ 'http', 'https', 'socks5', 'socks5h' ];

	/**
	 * When non-null, replaces the saved setting for the duration of
	 * runWith(): '' forces a direct connection, anything else is used as
	 * the proxy URL. Lets the connection tester try unsaved form values.
	 *
	 * @var string|null
	 */
	private static $overrideUrl = null;

	public static function register(): void {
		add_action( 'http_api_curl', [ self::class, 'maybeSetProxy' ], 10, 3 );
	}

	/**
	 * An absolute URL with a host and one of the schemes cURL's
	 * CURLOPT_PROXY understands — that's all a proxy value needs to be.
	 */
	public static function isValidProxyUrl( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$parsed = wp_parse_url( $url );

		return isset( $parsed['scheme'], $parsed['host'] ) && in_array( strtolower( $parsed['scheme'] ), self::ALLOWED_SCHEMES, true );
	}

	/**
	 * Runs $callback with the given proxy in effect instead of the saved
	 * one ('' = no proxy), restoring the saved behaviour afterwards even if
	 * the callback throws.
	 *
	 * @param string   $proxyUrl Proxy URL to use, or '' for a direct connection.
	 * @param callable $callback Work to run while the override is active.
	 * @return mixed Whatever $callback returns.
	 */
	public static function runWith( string $proxyUrl, callable $callback ) {
		self::$overrideUrl = $proxyUrl;

		try {
			return $callback();
		} finally {
			self::$overrideUrl = null;
		}
	}

	/**
	 * @param resource|\CurlHandle $handle cURL handle for the in-flight request.
	 * @param array                $parsedArgs wp_remote_*() args (unused, required by the hook signature).
	 * @param string               $url The request URL.
	 */
	public static function maybeSetProxy( $handle, array $parsedArgs, string $url ): void {
		if ( self::TELEGRAM_HOST !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return;
		}

		$proxyUrl = self::resolveProxyUrl();

		if ( '' === $proxyUrl ) {
			return;
		}

		curl_setopt( $handle, CURLOPT_PROXY, $proxyUrl ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- scoped, opt-in proxying of Telegram API requests only, via WP's own http_api_curl hook.
	}

	private static function resolveProxyUrl(): string {
		if ( null !== self::$overrideUrl ) {
			return self::$overrideUrl;
		}

		return Config::isTelegramProxyEnabled() ? Config::getTelegramProxyUrl() : '';
	}
}
