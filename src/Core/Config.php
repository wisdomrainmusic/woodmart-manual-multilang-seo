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
