<?php

namespace MCE\Multilang\DB;

use MCE\Multilang\Core\Config;

/**
 * Handles plugin activation and setup.
 */
class Installer {
	/**
	 * Activation entry point.
	 */
	public static function activate(): void {
		self::setVersionOption();
		self::prepareSchema();
	}

	/**
	 * Persist plugin version in options table.
	 */
	private static function setVersionOption(): void {
		update_option( 'mce_multilang_seo_version', Config::getVersion() );
	}

	/**
	 * Placeholder for future schema setup.
	 */
	private static function prepareSchema(): void {
		// Database schema creation will be implemented in a future step.
	}
}
