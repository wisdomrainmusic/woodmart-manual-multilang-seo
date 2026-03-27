<?php

namespace MCE\Multilang\Core;

use MCE\Multilang\DB\TranslationRepository;
use WP_Post;

class LocalizedUrlBuilder
{
    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function buildObjectUrl(int $objectId, string $language, bool $requireTranslationRecord = true): string
    {
        if ($objectId <= 0 || !LanguageManager::isSupportedLanguage($language)) {
            return '';
        }

        $post = get_post($objectId);

        if (!$post instanceof WP_Post || !in_array($post->post_type, ['page', 'post', 'product'], true)) {
            return '';
        }

        if ((int) get_option('page_on_front') === $objectId) {
            return LanguageManager::isDefault($language)
                ? home_url('/')
                : home_url('/' . $language . '/');
        }

        if (LanguageManager::isDefault($language)) {
            $permalink = get_permalink($objectId);
            return is_string($permalink) ? $permalink : '';
        }

        $translation = $this->repository->getTranslation($objectId, $post->post_type, $language);

        if ($requireTranslationRecord && !$translation) {
            return '';
        }

        $slug = '';

        if (is_array($translation) && !empty($translation['translated_slug'])) {
            $slug = (string) $translation['translated_slug'];
        } else {
            $slug = (string) $post->post_name;
        }

        if ($slug === '') {
            return '';
        }

        if ($post->post_type === 'product') {
            return home_url('/' . $language . '/product/' . $slug . '/');
        }

        return home_url('/' . $language . '/' . $slug . '/');
    }
}
