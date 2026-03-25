<?php

namespace MCE\Multilang\Core;

use MCE\Multilang\DB\TranslationRepository;

class Router
{
    public function register(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_action('parse_request', [$this, 'resolveTranslatedSlug'], 1);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_filter('request', [$this, 'mapLanguageRequest']);
        add_filter('redirect_canonical', [$this, 'maybeDisableCanonicalRedirect'], 10, 2);
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

    public function maybeDisableCanonicalRedirect($redirectUrl, $requestedUrl)
    {
        if (is_admin()) {
            return $redirectUrl;
        }

        $language = get_query_var(Config::LANGUAGE_QUERY_VAR);

        if (is_string($language) && LanguageManager::isSupportedLanguage($language)) {
            return false;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if (is_string($requestUri) && preg_match('#^/(' . Config::getLanguagePattern() . ')(/|$)#', $requestUri)) {
            return false;
        }

        return $redirectUrl;
    }

    public function registerQueryVars(array $queryVars): array
    {
        $queryVars[] = Config::LANGUAGE_QUERY_VAR;
        $queryVars[] = Config::TRANSLATED_PATH_QUERY_VAR;

        return array_values(array_unique($queryVars));
    }

    public function resolveTranslatedSlug(\WP $wp): void
    {
        if (is_admin()) {
            return;
        }

        $lang = $wp->query_vars[Config::LANGUAGE_QUERY_VAR] ?? '';

        if (!$lang || LanguageManager::isDefault($lang)) {
            return;
        }

        $request = trim($wp->request ?? '', '/');

        if (!$request) {
            return;
        }

        $segments = explode('/', $request);

        if (count($segments) < 2) {
            return;
        }

        // /de/product/mein-slug
        if ($segments[0] === $lang && $segments[1] === 'product' && !empty($segments[2])) {
            $translatedSlug = sanitize_title($segments[2]);

            global $wpdb;

            $repo = new TranslationRepository();
            $table = $repo->getTableName();

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT object_id FROM {$table} WHERE translated_slug = %s AND lang_code = %s LIMIT 1",
                    $translatedSlug,
                    $lang
                )
            );

            if ($row && !empty($row->object_id)) {
                $post = get_post((int) $row->object_id);

                if ($post && $post->post_type === 'product') {
                    $wp->query_vars['post_type'] = 'product';
                    $wp->query_vars['name'] = $post->post_name;
                    $wp->query_vars['post_name'] = $post->post_name;
                    $wp->query_vars['page_id'] = '';
                    $wp->query_vars['pagename'] = '';
                    $wp->query_vars['attachment'] = '';
                    $wp->query_vars['attachment_id'] = '';
                    $wp->query_vars['error'] = '';

                    unset($wp->query_vars['p']);
                    unset($wp->query_vars[Config::TRANSLATED_PATH_QUERY_VAR]);
                }
            }

            return;
        }

        // /de/meine-seite
        if ($segments[0] === $lang && !empty($segments[1])) {
            $translatedSlug = sanitize_title($segments[1]);

            global $wpdb;

            $repo = new TranslationRepository();
            $table = $repo->getTableName();

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT object_id FROM {$table} WHERE translated_slug = %s AND lang_code = %s AND object_type IN ('page','post') LIMIT 1",
                    $translatedSlug,
                    $lang
                )
            );

            if ($row && !empty($row->object_id)) {
                $post = get_post((int) $row->object_id);

                if ($post && in_array($post->post_type, ['page', 'post'], true)) {
                    $wp->query_vars['page_id'] = (int) $post->ID;
                    $wp->query_vars['pagename'] = $post->post_name;
                    $wp->query_vars['name'] = $post->post_name;
                    $wp->query_vars['post_type'] = $post->post_type;
                    $wp->query_vars['error'] = '';

                    unset($wp->query_vars[Config::TRANSLATED_PATH_QUERY_VAR]);
                }
            }
        }
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
