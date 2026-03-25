<?php

namespace MCE\Multilang\Core;

class Config
{
    public const VERSION = '1.0.0';
    public const DB_SCHEMA_VERSION = '1.0.0';
    public const DB_SCHEMA_VERSION_OPTION = 'mce_multilang_schema_version';

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
}
