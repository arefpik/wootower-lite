<?php
/**
 * Tracks and reads WooTower's own operational counters — how many orders it
 * has notified about, and how many of those an admin has acted on via the
 * bot. Free-tier scope per ai-rules/01-project.md: real sales analytics
 * (revenue, completion rate, best-sellers) is Pro, not this.
 *
 * @package WooTower\Core\Stats
 */

namespace WooTower\Core\Stats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StatsService {

	private const META_NOTIFIED       = '_wootower_notified';
	private const META_STATUS_CHANGED = '_wootower_status_changed_via_bot';

	public function markNotified( int $orderId ): void {
		$this->setOrderMeta( $orderId, self::META_NOTIFIED );
	}

	public function markStatusChangedViaBot( int $orderId ): void {
		$this->setOrderMeta( $orderId, self::META_STATUS_CHANGED );
	}

	/**
	 * Orders WooTower has sent a new-order notification for.
	 */
	public function getNotifiedCount(): int {
		return $this->countOrdersWithMeta( self::META_NOTIFIED );
	}

	/**
	 * Of those, how many an admin has changed the status of via a bot button.
	 */
	public function getStatusChangedCount(): int {
		return $this->countOrdersWithMeta( self::META_STATUS_CHANGED );
	}

	/**
	 * Notified orders nobody has acted on via the bot yet. A status change
	 * can only happen by tapping a button on a notification WooTower already
	 * sent, so "status changed" orders are always a subset of "notified"
	 * ones — the difference alone gives the right count, with no need for
	 * a combined query.
	 */
	public function getPendingCount(): int {
		return max( 0, $this->getNotifiedCount() - $this->getStatusChangedCount() );
	}

	private function setOrderMeta( int $orderId, string $key ): void {
		$order = wc_get_order( $orderId );

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( $key, 'yes' );
		$order->save_meta_data();
	}

	/**
	 * Counts orders carrying a given flag meta. Uses WC_Order_Query's flat
	 * meta_key/meta_value args rather than the meta_query array form —
	 * WooCommerce's legacy post-based order storage doesn't support
	 * meta_query at all (silently ignored, logged as "doing it wrong"),
	 * which would otherwise make every count return the total order count
	 * regardless of the filter.
	 */
	private function countOrdersWithMeta( string $key ): int {
		$result = wc_get_orders(
			[
				'status'     => 'any',
				'limit'      => 1,
				'paginate'   => true,
				'return'     => 'ids',
				'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- WC_Order_Query abstraction, not a direct query.
				'meta_value' => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- WC_Order_Query abstraction, not a direct query.
			]
		);

		return (int) $result->total;
	}
}
