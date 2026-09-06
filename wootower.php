<?php
/**
 * Plugin Name:       WooTower
 * Plugin URI:        https://wootower.example
 * Description:       Manage your WooCommerce store from Telegram and a built-in wp-admin dashboard.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            WooTower
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wootower
 * Domain Path:       /languages
 *
 * @package WooTower
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WOOTOWER_VERSION', '0.1.0' );
define( 'WOOTOWER_PLUGIN_FILE', __FILE__ );
define( 'WOOTOWER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOOTOWER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * PSR-4 style autoloader for the WooTower\ namespace, mapped to includes/.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'WooTower\\';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$relative_path  = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
		$file           = WOOTOWER_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative_path;

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Boots the plugin once WooCommerce is confirmed active.
 */
function wootower_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wootower_missing_woocommerce_notice' );
		return;
	}

	add_action(
		'rest_api_init',
		function () {
			( new \WooTower\Channels\Telegram\TelegramWebhookController() )->registerRoute();
			( new \WooTower\Admin\Rest\PingController() )->registerRoute();
			( new \WooTower\Admin\Rest\StatsController( new \WooTower\Core\Stats\StatsService() ) )->registerRoute();
		}
	);

	add_action(
		'wootower_new_order_notified',
		function ( $order_id ) {
			( new \WooTower\Core\Stats\StatsService() )->markNotified( (int) $order_id );
		}
	);

	add_action(
		'wootower_order_status_changed',
		function ( $order_id ) {
			( new \WooTower\Core\Stats\StatsService() )->markStatusChangedViaBot( (int) $order_id );
		}
	);

	if ( is_admin() ) {
		$dashboard_page = new \WooTower\Admin\Dashboard\DashboardPage();
		add_action( 'admin_menu', [ $dashboard_page, 'registerMenu' ] );
		add_action( 'admin_enqueue_scripts', [ $dashboard_page, 'enqueueAssets' ] );

		$settings_page = new \WooTower\Admin\SettingsPage();
		add_action( 'admin_menu', [ $settings_page, 'registerMenu' ] );
		add_action( 'admin_init', [ $settings_page, 'handleSave' ] );
	}

	// Classic (shortcode) checkout passes an order id; the Blocks/Store API
	// checkout — the default since WooCommerce 8+ — passes the WC_Order
	// object instead, via a different hook. Both must be covered, or stores
	// using the modern checkout never get a notification.
	add_action( 'woocommerce_checkout_order_processed', 'wootower_handle_new_order' );
	add_action( 'woocommerce_store_api_checkout_order_processed', 'wootower_handle_new_order' );
}
add_action( 'plugins_loaded', 'wootower_init' );

/**
 * @param int|\WC_Order $order_id_or_order
 */
function wootower_handle_new_order( $order_id_or_order ) {
	$order_id = $order_id_or_order instanceof \WC_Order ? $order_id_or_order->get_id() : (int) $order_id_or_order;

	$dispatcher = wootower_build_notification_dispatcher();

	if ( ! $dispatcher ) {
		return;
	}

	$listener = new \WooTower\Core\Notifications\NewOrderListener(
		new \WooTower\Core\Orders\OrderService( new \WooTower\Core\Orders\OrderRepository() ),
		$dispatcher
	);

	$listener->handle( $order_id );
}

/**
 * Builds a NotificationDispatcher wired to the configured Telegram bot, or
 * null when the bot token / admin chat id haven't been set up yet.
 */
function wootower_build_notification_dispatcher(): ?\WooTower\Core\Notifications\NotificationDispatcher {
	$bot_token = \WooTower\Support\Config::getTelegramBotToken();
	$chat_id   = \WooTower\Support\Config::getTelegramChatId();

	if ( empty( $bot_token ) || empty( $chat_id ) ) {
		return null;
	}

	$channel = new \WooTower\Channels\Telegram\TelegramChannel( $bot_token, \WooTower\Support\Config::getTelegramWebhookSecret() );

	return new \WooTower\Core\Notifications\NotificationDispatcher(
		$channel,
		$chat_id,
		\WooTower\Support\Config::getNotificationTemplate(),
		\WooTower\Support\Config::getStatusButtons()
	);
}

/**
 * Shows an admin notice when WooCommerce is not active.
 */
function wootower_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'WooTower requires WooCommerce to be installed and active.', 'wootower' ) .
		'</p></div>';
}
