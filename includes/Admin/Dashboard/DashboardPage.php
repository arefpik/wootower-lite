<?php
/**
 * wp-admin page that hosts and enqueues the dashboard-app React bundle.
 *
 * @package WooTower\Admin\Dashboard
 */

namespace WooTower\Admin\Dashboard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DashboardPage {

	public const MENU_SLUG = 'wootower-dashboard';

	private const CAPABILITY    = 'manage_woocommerce';
	private const SCRIPT_HANDLE = 'wootower-dashboard';
	private const UPGRADE_URL   = 'https://wootower.pro/buy';

	public function registerMenu(): void {
		add_menu_page(
			__( 'WooTower', 'wootower' ),
			__( 'WooTower', 'wootower' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render' ],
			'dashicons-store',
			56
		);
	}

	/**
	 * Enqueues the built React bundle, but only on WooTower's own admin
	 * screens, and only once it has actually been built.
	 *
	 * @param string $hookSuffix Current admin page hook, passed by WordPress.
	 */
	public function enqueueAssets( string $hookSuffix ): void {
		if ( false === strpos( $hookSuffix, self::MENU_SLUG ) ) {
			return;
		}

		$build_dir = WOOTOWER_PLUGIN_DIR . 'dashboard-app/build/';
		$build_url = WOOTOWER_PLUGIN_URL . 'dashboard-app/build/';

		$script_path = $build_dir . 'wootower-dashboard.js';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$build_url . 'wootower-dashboard.js',
			[],
			(string) filemtime( $script_path ),
			true
		);

		$style_path = $build_dir . 'wootower-dashboard.css';

		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				self::SCRIPT_HANDLE,
				$build_url . 'wootower-dashboard.css',
				[],
				(string) filemtime( $style_path )
			);
		}

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'wootowerDashboardConfig',
			[
				'restUrl'    => esc_url_raw( rest_url() ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'upgradeUrl' => self::UPGRADE_URL,
				'isRtl'      => is_rtl(),
				'i18n'       => $this->strings(),
			]
		);
	}

	/**
	 * The React bundle has no gettext of its own, so every string it shows is
	 * translated here and handed over through wootowerDashboardConfig — that
	 * keeps the dashboard in the same .po file as the rest of the plugin.
	 * Keys must match the ones used in dashboard-app/src/utils/i18n.js.
	 *
	 * @return array<string, string>
	 */
	private function strings(): array {
		return [
			'title'           => __( 'WooTower Dashboard', 'wootower' ),
			'connecting'      => __( 'Connecting to WooTower...', 'wootower' ),
			'connected'       => __( 'Connected to the backend.', 'wootower' ),
			'connectionError' => __( 'Could not reach the WooTower backend.', 'wootower' ),
			'overview'        => __( 'Overview', 'wootower' ),
			'notifiedOrders'  => __( 'New order notifications sent', 'wootower' ),
			'statusChanges'   => __( 'Status changes made via bot', 'wootower' ),
			'awaitingAction'  => __( 'Awaiting action', 'wootower' ),
			'totalRevenue'    => __( 'Total revenue', 'wootower' ),
			'completionRate'  => __( 'Order completion rate', 'wootower' ),
			'bestSeller'      => __( 'Best-selling product', 'wootower' ),
			'moreWithPro'     => __( 'More with Pro', 'wootower' ),
			'products'        => __( 'Products', 'wootower' ),
			'orderManagement' => __( 'Full Order Management', 'wootower' ),
			'customers'       => __( 'Customers', 'wootower' ),
			'analytics'       => __( 'Analytics', 'wootower' ),
			'proFeatureTitle' => __( 'This is a Pro feature', 'wootower' ),
			'proFeatureBody'  => __( 'Upgrade to WooTower Pro to unlock this feature, along with full product, order and customer management.', 'wootower' ),
			'close'           => __( 'Close', 'wootower' ),
			'upgrade'         => __( 'Upgrade to Pro', 'wootower' ),
		];
	}

	public function render(): void {
		echo '<div id="wootower-dashboard-root"></div>';
	}
}
