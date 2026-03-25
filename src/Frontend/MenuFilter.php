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
            if (!is_object($item) || empty($item->object_id) || empty($item->type)) {
                continue;
            }

            if ($item->type !== 'post_type') {
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
}
