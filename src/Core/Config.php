<?php

namespace MCE\Multilang\Core;

/**
 * Static plugin configuration values.
 */
class Config {
	/**
	 * Get plugin version.
	 */
	public static function getVersion(): string {
		return MCE_MULTILANG_SEO_VERSION;
	}

	/**
	 * Get plugin database schema version.
	 */
	public static function getSchemaVersion(): string {
		return '1.0.0';
	}

	/**
	 * Get schema version option key.
	 */
	public static function getSchemaVersionOptionKey(): string {
		return 'mce_multilang_seo_schema_version';
	}

	/**
	 * Get the default language code.
	 */
	public static function getDefaultLanguage(): string {
		return 'en';
	}

	/**
	 * Get supported language registry.
	 *
	 * @return string[]
	 */
	public static function getSupportedLanguages(): array {
		return array( 'en', 'de', 'it', 'fr', 'es', 'tr' );
	}
}
