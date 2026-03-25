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
	 * Create or update schema and persist schema version.
	 */
	private static function prepareSchema(): void {
		Schema::install();
		update_option( Config::getSchemaVersionOptionKey(), Config::getSchemaVersion() );
	}
}
