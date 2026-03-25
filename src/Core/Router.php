<?php

namespace MCE\Multilang\Core;

class Router
{
    public function register(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('request', [$this, 'mapLanguageRequest']);
    }

    public static function activate(): void
    {
        $router = new self();
        $router->registerRewriteRules();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function registerQueryVars(array $queryVars): array
    {
        $queryVars[] = Config::LANGUAGE_QUERY_VAR;
        $queryVars[] = Config::TRANSLATED_PATH_QUERY_VAR;

        return array_values(array_unique($queryVars));
    }

    public function registerRewriteRules(): void
    {
        $languagePattern = Config::getLanguagePattern();

        if ($languagePattern === '') {
            return;
        }

        add_rewrite_rule(
            '^(' . $languagePattern . ')/?$',
            'index.php?' . Config::LANGUAGE_QUERY_VAR . '=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^(' . $languagePattern . ')/(.*)/?$',
            'index.php?' . Config::LANGUAGE_QUERY_VAR . '=$matches[1]&' . Config::TRANSLATED_PATH_QUERY_VAR . '=$matches[2]',
            'top'
        );
    }

    public function mapLanguageRequest(array $queryVars): array
    {
        if (is_admin()) {
            return $queryVars;
        }

        $language = $queryVars[Config::LANGUAGE_QUERY_VAR] ?? '';
        $path = $queryVars[Config::TRANSLATED_PATH_QUERY_VAR] ?? '';

        if (!is_string($language) || !LanguageManager::isSupportedLanguage($language)) {
            return $queryVars;
        }

        if (!is_string($path) || $path === '') {
            return $queryVars;
        }

        $path = trim($path, '/');

        if ($path === '') {
            return $queryVars;
        }

        if (str_starts_with($path, 'product/')) {
            $productSlug = trim((string) substr($path, strlen('product/')), '/');

            if ($productSlug !== '') {
                $queryVars['post_type'] = 'product';
                $queryVars['name'] = sanitize_title($productSlug);
            }

            unset($queryVars[Config::TRANSLATED_PATH_QUERY_VAR]);

            return $queryVars;
        }

        $queryVars['pagename'] = sanitize_text_field($path);
        $queryVars['name'] = sanitize_title(basename($path));

        unset($queryVars[Config::TRANSLATED_PATH_QUERY_VAR]);

        return $queryVars;
    }
}
