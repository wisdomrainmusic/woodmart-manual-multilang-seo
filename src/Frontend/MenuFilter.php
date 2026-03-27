<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class MenuFilter
{
    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_filter('wp_nav_menu_objects', [$this, 'translateMenuItems'], 10, 2);
        add_filter('wp_nav_menu_objects', [$this, 'translateMenuUrls'], 20, 2);
    }

    public function translateMenuItems(array $items, $args): array
    {
        if (is_admin()) {
            return $items;
        }

        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return $items;
        }

        foreach ($items as $item) {
            if (!is_object($item) || empty($item->ID)) {
                continue;
            }

            $translation = $this->repository->getTranslation((int) $item->ID, 'nav_menu_item', $language);

            if (!$translation) {
                continue;
            }

            if (!empty($translation['translated_title'])) {
                $item->title = (string) $translation['translated_title'];
            }
        }

        return $items;
    }

    public function translateMenuUrls(array $items, $args): array
    {
        if (is_admin()) {
            return $items;
        }

        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return $items;
        }

        foreach ($items as $item) {
            if (!is_object($item) || empty($item->type)) {
                continue;
            }

            if ($item->type === 'custom' || $item->type === 'post_type_archive') {
                if (!empty($item->url) && is_string($item->url)) {
                    $item->url = $this->localizeInternalUrl($item->url, $language);
                }
                continue;
            }

            if ($item->type !== 'post_type' || empty($item->object_id)) {
                continue;
            }

            $objectId = (int) $item->object_id;
            $postType = get_post_type($objectId);

            if (!$postType || !in_array($postType, ['page', 'post', 'product'], true)) {
                continue;
            }

            $translation = $this->repository->getTranslation($objectId, $postType, $language);

            if ((int) get_option('page_on_front') === $objectId) {
                $item->url = home_url('/' . $language . '/');
                continue;
            }

            $slug = '';

            if ($translation && !empty($translation['translated_slug'])) {
                $slug = (string) $translation['translated_slug'];
            } else {
                $post = get_post($objectId);

                if (!$post) {
                    continue;
                }

                $slug = $post->post_name;
            }

            if ($postType === 'product') {
                $item->url = home_url('/' . $language . '/product/' . $slug . '/');
                continue;
            }

            $item->url = home_url('/' . $language . '/' . $slug . '/');
        }

        return $items;
    }

    private function localizeInternalUrl(string $url, string $language): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        $lowerUrl = strtolower($url);

        if (
            str_starts_with($lowerUrl, '#') ||
            str_starts_with($lowerUrl, 'mailto:') ||
            str_starts_with($lowerUrl, 'tel:') ||
            str_starts_with($lowerUrl, 'javascript:')
        ) {
            return $url;
        }

        $parts = wp_parse_url($url);

        if (!is_array($parts)) {
            return $url;
        }

        $homeParts = wp_parse_url(home_url('/'));

        if (!is_array($homeParts)) {
            return $url;
        }

        $homeHost = strtolower((string) ($homeParts['host'] ?? ''));
        $urlHost  = strtolower((string) ($parts['host'] ?? ''));

        if ($urlHost !== '' && $homeHost !== '' && $urlHost !== $homeHost) {
            return $url;
        }

        $path = (string) ($parts['path'] ?? '');

        if ($path === '' && !str_starts_with($url, '/')) {
            return $url;
        }

        $homePath = trim((string) ($homeParts['path'] ?? ''), '/');
        $path     = '/' . ltrim($path, '/');
        $relativePath = ltrim($path, '/');

        if ($homePath !== '') {
            if ($relativePath === $homePath) {
                $relativePath = '';
            } elseif (str_starts_with($relativePath, $homePath . '/')) {
                $relativePath = substr($relativePath, strlen($homePath) + 1);
            }
        }

        $relativePath = LanguageManager::stripLanguagePrefix($relativePath);
        $relativePath = trim($relativePath, '/');

        if ($relativePath === '') {
            $localizedUrl = home_url('/' . $language . '/');
        } else {
            $localizedUrl = home_url('/' . user_trailingslashit($language . '/' . $relativePath));
        }

        if (isset($parts['query']) && $parts['query'] !== '') {
            $localizedUrl .= '?' . $parts['query'];
        }

        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $localizedUrl .= '#' . $parts['fragment'];
        }

        return $localizedUrl;
    }
}
