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
				'restUrl' => esc_url_raw( rest_url() ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			]
		);
	}

	public function render(): void {
		echo '<div id="wootower-dashboard-root"></div>';
	}
}
