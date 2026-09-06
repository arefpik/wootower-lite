<?php
/**
 * Minimal logging helper so external/IO failures are never silently swallowed.
 *
 * @package WooTower\Support
 */

namespace WooTower\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Logger {

	public static function info( string $message, array $context = [] ): void {
		self::write( 'INFO', $message, $context );
	}

	public static function warning( string $message, array $context = [] ): void {
		self::write( 'WARNING', $message, $context );
	}

	public static function error( string $message, array $context = [] ): void {
		self::write( 'ERROR', $message, $context );
	}

	private static function write( string $level, string $message, array $context ): void {
		$line = sprintf( '[WooTower][%s] %s', $level, $message );

		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional plugin-level logging.
		error_log( $line );
	}
}
