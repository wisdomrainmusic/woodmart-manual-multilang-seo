<?php

namespace MCE\Multilang\Core;

class Config
{
    public const VERSION = '1.0.0';
    public const DB_SCHEMA_VERSION = '1.0.0';
    public const DB_SCHEMA_VERSION_OPTION = 'mce_multilang_schema_version';
    public const LANGUAGE_QUERY_VAR = 'mce_lang';
    public const TRANSLATED_PATH_QUERY_VAR = 'mce_translated_path';
    public const LANGUAGE_COOKIE_NAME = 'mce_selected_lang';
    public const LANGUAGE_COOKIE_TTL = 2592000; // 30 days

    public static function getDefaultLanguage(): string
    {
        return 'en';
    }

    public static function getLanguages(): array
    {
        return ['en', 'de', 'it', 'fr', 'es', 'tr'];
    }

    public static function getPluginVersion(): string
    {
        return self::VERSION;
    }

    public static function getLanguageCookieName(): string
    {
        return self::LANGUAGE_COOKIE_NAME;
    }

    public static function getLanguageCookieTtl(): int
    {
        return self::LANGUAGE_COOKIE_TTL;
    }

    public static function getPrefixedLanguages(): array
    {
        return array_values(
            array_filter(
                self::getLanguages(),
                static fn (string $language): bool => $language !== self::getDefaultLanguage()
            )
        );
    }

    public static function getLanguagePattern(): string
    {
        return implode('|', array_map('preg_quote', self::getPrefixedLanguages()));
    }

    public static function getLanguageQueryVar(): string
    {
        return self::LANGUAGE_QUERY_VAR;
    }
}
