<?php

namespace MCE\Multilang\Core;

class LanguageManager
{
    public static function register(): void
    {
        add_action('init', [self::class, 'persistSelectedLanguage'], 1);
    }

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

        $requestLanguage = self::detectLanguageFromRequest();

        if ($requestLanguage !== null) {
            return $requestLanguage;
        }

        $cookieLanguage = self::getLanguageFromCookie();

        if ($cookieLanguage !== null) {
            return $cookieLanguage;
        }

        return self::getDefaultLanguage();
    }

    public static function persistSelectedLanguage(): void
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        $language = self::detectLanguageFromRequest();

        if ($language === null) {
            return;
        }

        self::setLanguageCookie($language);
    }

    public static function getPrefixedLanguages(): array
    {
        return Config::getPrefixedLanguages();
    }

    public static function getLanguageCookieName(): string
    {
        return Config::getLanguageCookieName();
    }

    public static function getLanguageCookieTtl(): int
    {
        return Config::getLanguageCookieTtl();
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

    private static function detectLanguageFromRequest(): ?string
    {
        $queryVar = Config::getLanguageQueryVar();
        $requestedLanguage = $_GET[$queryVar] ?? '';

        if (is_string($requestedLanguage)) {
            $requestedLanguage = sanitize_key(wp_unslash($requestedLanguage));

            if (self::isSupportedLanguage($requestedLanguage)) {
                return $requestedLanguage;
            }
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if (!is_string($requestUri) || $requestUri === '') {
            return null;
        }

        $path = (string) wp_parse_url($requestUri, PHP_URL_PATH);
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $language = sanitize_key((string) ($segments[0] ?? ''));

        if (self::isSupportedLanguage($language) && !self::isDefault($language)) {
            return $language;
        }

        return null;
    }

    private static function getLanguageFromCookie(): ?string
    {
        $cookieName = Config::getLanguageCookieName();
        $cookieValue = $_COOKIE[$cookieName] ?? '';

        if (!is_string($cookieValue) || $cookieValue === '') {
            return null;
        }

        $language = sanitize_key(wp_unslash($cookieValue));

        return self::isSupportedLanguage($language) ? $language : null;
    }

    private static function setLanguageCookie(string $language): void
    {
        if (!self::isSupportedLanguage($language)) {
            return;
        }

        $_COOKIE[Config::getLanguageCookieName()] = $language;

        if (headers_sent()) {
            return;
        }

        setcookie(Config::getLanguageCookieName(), $language, time() + Config::getLanguageCookieTtl(), COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), false);
    }
}
