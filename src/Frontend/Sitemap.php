<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\Core\LocalizedUrlBuilder;
use MCE\Multilang\DB\TranslationRepository;
use WP_Query;

class Sitemap
{
    private const QUERY_VAR = 'mce_ml_sitemap';

    private TranslationRepository $repository;
    private LocalizedUrlBuilder $urlBuilder;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
        $this->urlBuilder = new LocalizedUrlBuilder($this->repository);
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('template_redirect', [$this, 'renderIfRequested'], 0);
    }

    public static function activate(): void
    {
        $instance = new self();
        $instance->registerRewriteRules();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function registerRewriteRules(): void
    {
        add_rewrite_rule(
            '^mce-multilang-sitemap\.xml$',
            'index.php?' . self::QUERY_VAR . '=index',
            'top'
        );

        add_rewrite_rule(
            '^mce-multilang-sitemap-([a-z]{2})\.xml$',
            'index.php?' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public function registerQueryVars(array $queryVars): array
    {
        $queryVars[] = self::QUERY_VAR;

        return array_values(array_unique($queryVars));
    }

    public function renderIfRequested(): void
    {
        $requested = get_query_var(self::QUERY_VAR);

        if (!is_string($requested) || $requested === '') {
            $requested = $this->detectRequestedSitemapFromUri();
        }

        if (!is_string($requested) || $requested === '') {
            return;
        }

        if (ob_get_length()) {
            ob_clean();
        }

        nocache_headers();
        status_header(200);
        header('Content-Type: application/xml; charset=' . get_bloginfo('charset'));

        if ($requested === 'index') {
            echo $this->renderSitemapIndex();
            exit;
        }

        if (!LanguageManager::isSupportedLanguage($requested)) {
            status_header(404);
            echo $this->renderEmptyUrlset();
            exit;
        }

        echo $this->renderLanguageSitemap($requested);
        exit;
    }

    private function detectRequestedSitemapFromUri(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if (!is_string($requestUri) || $requestUri === '') {
            return '';
        }

        $path = wp_parse_url($requestUri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '';
        }

        if (preg_match('#/mce-multilang-sitemap\.xml$#', $path)) {
            return 'index';
        }

        if (preg_match('#/mce-multilang-sitemap-([a-z]{2})\.xml$#', $path, $matches)) {
            return isset($matches[1]) ? strtolower((string) $matches[1]) : '';
        }

        return '';
    }

    private function renderSitemapIndex(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach (LanguageManager::getSupportedLanguages() as $language) {
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . esc_xml(home_url('/mce-multilang-sitemap-' . $language . '.xml')) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . esc_xml(gmdate('c')) . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    private function renderLanguageSitemap(string $language): string
    {
        $urls = $this->collectUrlsForLanguage($language);

        if (empty($urls)) {
            return $this->renderEmptyUrlset();
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . esc_xml($entry['loc']) . "</loc>\n";

            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . esc_xml($entry['lastmod']) . "</lastmod>\n";
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    private function renderEmptyUrlset(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    private function collectUrlsForLanguage(string $language): array
    {
        $urls = [];
        $frontPageId = (int) get_option('page_on_front');

        if ($frontPageId > 0) {
            $frontUrl = $this->buildUrlForObject($frontPageId, $language);

            if ($frontUrl !== '') {
                $urls[] = [
                    'loc' => $frontUrl,
                    'lastmod' => $this->getLastModifiedIso($frontPageId),
                ];
            }
        }

        $query = new WP_Query([
            'post_type' => ['page', 'post', 'product'],
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
            'post__not_in' => $frontPageId > 0 ? [$frontPageId] : [],
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (!empty($query->posts)) {
            foreach ($query->posts as $objectId) {
                $objectId = (int) $objectId;

                $url = $this->buildUrlForObject($objectId, $language);

                if ($url === '') {
                    continue;
                }

                $urls[] = [
                    'loc' => $url,
                    'lastmod' => $this->getLastModifiedIso($objectId),
                ];
            }
        }

        return $urls;
    }

    private function buildUrlForObject(int $objectId, string $language): string
    {
        return $this->urlBuilder->buildObjectUrl($objectId, $language, true);
    }

    private function getLastModifiedIso(int $objectId): string
    {
        $modified = get_post_modified_time('c', true, $objectId);

        return is_string($modified) ? $modified : gmdate('c');
    }
}
