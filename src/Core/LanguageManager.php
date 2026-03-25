<?php

namespace MCE\Multilang\Core;

class LanguageManager
{
    public static function getDefaultLanguage(): string
    {
        return Config::getDefaultLanguage();
    }

    public static function getSupportedLanguages(): array
    {
        return Config::getLanguages();
    }

    public static function isDefault(string $lang): bool
    {
        return $lang === self::getDefaultLanguage();
    }

    public static function isSupportedLanguage(string $lang): bool
    {
        return in_array($lang, self::getSupportedLanguages(), true);
    }

    public static function getCurrentLanguage(): string
    {
        $queryVar = Config::getLanguageQueryVar();
        $language = get_query_var($queryVar);

        if (is_string($language) && self::isSupportedLanguage($language)) {
            return $language;
        }

        return self::getDefaultLanguage();
    }

    public static function getPrefixedLanguages(): array
    {
        return Config::getPrefixedLanguages();
    }

    public static function getLanguagePrefix(string $lang): string
    {
        if (!self::isSupportedLanguage($lang) || self::isDefault($lang)) {
            return '';
        }

        return '/' . $lang;
    }

    public static function stripLanguagePrefix(string $path): string
    {
        $path = ltrim($path, '/');
        $pattern = '#^(' . Config::getLanguagePattern() . ')(/|$)#';

        return (string) preg_replace($pattern, '', $path, 1);
    }
}
