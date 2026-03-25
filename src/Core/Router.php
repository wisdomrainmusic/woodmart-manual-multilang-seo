<?php

namespace MCE\Multilang\Core;

use MCE\Multilang\DB\TranslationRepository;
use WP_Post;
use WP;

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

    private function findTranslatedObjectId(string $translatedSlug, string $lang, array $allowedTypes = []): ?int
    {
        global $wpdb;

        $repo = new TranslationRepository();
        $table = $repo->getTableName();

        $translatedSlug = sanitize_title($translatedSlug);
        $lang = sanitize_key($lang);

        if ($translatedSlug === '' || $lang === '') {
            return null;
        }

        if (!empty($allowedTypes)) {
            $allowedTypes = array_values(array_filter(array_map('sanitize_key', $allowedTypes)));

            if (empty($allowedTypes)) {
                return null;
            }

            $placeholders = implode(',', array_fill(0, count($allowedTypes), '%s'));
            $params = array_merge([$translatedSlug, $lang], $allowedTypes);

            $sql = $wpdb->prepare(
                "SELECT object_id
                 FROM {$table}
                 WHERE translated_slug = %s
                   AND lang_code = %s
                   AND object_type IN ({$placeholders})
                 ORDER BY id DESC
                 LIMIT 1",
                ...$params
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT object_id
                 FROM {$table}
                 WHERE translated_slug = %s
                   AND lang_code = %s
                 ORDER BY id DESC
                 LIMIT 1",
                $translatedSlug,
                $lang
            );
        }

        $row = $wpdb->get_row($sql);

        if ($row && !empty($row->object_id)) {
            return (int) $row->object_id;
        }

        return null;
    }

    private function findDefaultLanguageObject(string $slug, array $allowedPostTypes): ?WP_Post
    {
        $slug = sanitize_title($slug);
        $allowedPostTypes = array_values(array_filter(array_map('sanitize_key', $allowedPostTypes)));

        if ($slug === '' || empty($allowedPostTypes)) {
            return null;
        }

        foreach ($allowedPostTypes as $postType) {
            if ($postType === 'page') {
                $page = get_page_by_path($slug, OBJECT, 'page');

                if ($page instanceof WP_Post) {
                    return $page;
                }

                continue;
            }

            $posts = get_posts([
                'name'                   => $slug,
                'post_type'              => $postType,
                'post_status'            => ['publish', 'private'],
                'posts_per_page'         => 1,
                'orderby'                => 'ID',
                'order'                  => 'DESC',
                'no_found_rows'          => true,
                'suppress_filters'       => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            if (!empty($posts[0]) && $posts[0] instanceof WP_Post) {
                return $posts[0];
            }
        }

        return null;
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

        // /de => front page
        if (count($segments) === 1 && $segments[0] === $lang) {
            $frontPageId = (int) get_option('page_on_front');

            if ($frontPageId > 0) {
                $frontPage = get_post($frontPageId);

                if ($frontPage) {
                    $wp->query_vars['page_id'] = $frontPageId;
                    $wp->query_vars['page'] = '';
                    $wp->query_vars['pagename'] = $frontPage->post_name;
                    $wp->query_vars['name'] = $frontPage->post_name;
                    $wp->query_vars['post_type'] = 'page';
                    $wp->query_vars['post_name'] = $frontPage->post_name;
                    $wp->query_vars['attachment'] = '';
                    $wp->query_vars['attachment_id'] = '';
                    $wp->query_vars['error'] = '';

                    unset($wp->query_vars['p']);
                    unset($wp->query_vars['category_name']);
                    unset($wp->query_vars['tag']);
                    unset($wp->query_vars['feed']);
                    unset($wp->query_vars['paged']);
                    unset($wp->query_vars['embed']);
                    unset($wp->query_vars[Config::TRANSLATED_PATH_QUERY_VAR]);
                }
            }

            return;
        }

        if (count($segments) < 2) {
            return;
        }

        // /de/product/mein-slug
        if ($segments[0] === $lang && $segments[1] === 'product' && !empty($segments[2])) {
            $translatedSlug = sanitize_title($segments[2]);

            $objectId = $this->findTranslatedObjectId($translatedSlug, $lang, ['product']);

            if ($objectId) {
                $post = get_post($objectId);
            } else {
                $post = $this->findDefaultLanguageObject($translatedSlug, ['product']);
            }

            if ($post && $post->post_type === 'product') {
                $wp->query_vars['post_type'] = 'product';
                $wp->query_vars['name'] = $post->post_name;
                $wp->query_vars['post_name'] = $post->post_name;
                $wp->query_vars['p'] = (int) $post->ID;
                $wp->query_vars['page_id'] = '';
                $wp->query_vars['pagename'] = '';
                $wp->query_vars['attachment'] = '';
                $wp->query_vars['attachment_id'] = '';
                $wp->query_vars['error'] = '';

                unset($wp->query_vars[Config::TRANSLATED_PATH_QUERY_VAR]);
                unset($wp->query_vars['category_name']);
                unset($wp->query_vars['tag']);
                unset($wp->query_vars['feed']);
                unset($wp->query_vars['paged']);
                unset($wp->query_vars['embed']);

                return;
            }

            return;
        }

        // /de/meine-seite
        if ($segments[0] === $lang && !empty($segments[1])) {
            $translatedSlug = sanitize_title($segments[1]);

            $objectId = $this->findTranslatedObjectId($translatedSlug, $lang, ['page', 'post']);

            if ($objectId) {
                $post = get_post($objectId);
            } else {
                $post = $this->findDefaultLanguageObject($translatedSlug, ['page', 'post']);
            }

            if ($post && in_array($post->post_type, ['page', 'post'], true)) {
                if ($post->post_type === 'page') {
                    $wp->query_vars['page_id'] = (int) $post->ID;
                    $wp->query_vars['pagename'] = $post->post_name;
                    unset($wp->query_vars['p']);
                } else {
                    $wp->query_vars['p'] = (int) $post->ID;
                    $wp->query_vars['name'] = $post->post_name;
                }

                $wp->query_vars['post_type'] = $post->post_type;
                $wp->query_vars['name'] = $post->post_name;
                $wp->query_vars['post_name'] = $post->post_name;
                $wp->query_vars['attachment'] = '';
                $wp->query_vars['attachment_id'] = '';
                $wp->query_vars['error'] = '';

                unset($wp->query_vars[Config::TRANSLATED_PATH_QUERY_VAR]);
                unset($wp->query_vars['category_name']);
                unset($wp->query_vars['tag']);
                unset($wp->query_vars['feed']);
                unset($wp->query_vars['paged']);
                unset($wp->query_vars['embed']);
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
