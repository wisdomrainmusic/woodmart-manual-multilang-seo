<?php

namespace MCE\Multilang\Core;

/**
 * Language registry helper.
 */
class LanguageManager {
	/**
	 * Get default language.
	 */
	public function getDefaultLanguage(): string {
		return Config::getDefaultLanguage();
	}

	/**
	 * Get supported language codes.
	 *
	 * @return string[]
	 */
	public function getSupportedLanguages(): array {
		return Config::getSupportedLanguages();
	}

	/**
	 * Check whether language is supported.
	 */
	public function isSupportedLanguage( string $lang ): bool {
		return in_array( strtolower( $lang ), $this->getSupportedLanguages(), true );
	}
}
