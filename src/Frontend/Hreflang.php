<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class Hreflang
{
    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'render'], 5);
    }

    public function render(): void
    {
        if (is_admin()) {
            return;
        }

        $objectId = get_queried_object_id();

        if (!$objectId) {
            return;
        }

        $languages = LanguageManager::getSupportedLanguages();

        foreach ($languages as $lang) {
            echo $this->buildTag($objectId, $lang);
        }

        echo $this->buildXDefaultTag($objectId);
    }

    private function buildTag(int $objectId, string $lang): string
    {
        $url = $this->buildUrl($objectId, $lang);

        if ($url === '') {
            return '';
        }

        return sprintf(
            '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
            esc_attr($lang),
            esc_url($url)
        );
    }

    private function buildXDefaultTag(int $objectId): string
    {
        $url = $this->buildUrl($objectId, LanguageManager::getDefaultLanguage());

        if ($url === '') {
            return '';
        }

        return sprintf(
            '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
            esc_url($url)
        );
    }

    private function buildUrl(int $objectId, string $lang): string
    {
        $post = get_post($objectId);

        if (!$post) {
            return '';
        }

        $defaultPermalink = get_permalink($objectId);

        if (!$defaultPermalink) {
            return '';
        }

        if ($lang === LanguageManager::getDefaultLanguage()) {
            return $defaultPermalink;
        }

        $translation = $this->repository->getTranslation($objectId, $post->post_type, $lang);

        if ((int) get_option('page_on_front') === $objectId) {
            return home_url('/' . $lang . '/');
        }

        $slug = '';

        if ($translation && !empty($translation['translated_slug'])) {
            $slug = (string) $translation['translated_slug'];
        } else {
            $slug = $post->post_name;
        }

        if ($post->post_type === 'product') {
            return home_url('/' . $lang . '/product/' . $slug . '/');
        }

        return home_url('/' . $lang . '/' . $slug . '/');
    }
}
