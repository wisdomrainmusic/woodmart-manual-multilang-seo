<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\Core\LocalizedUrlBuilder;
use MCE\Multilang\DB\TranslationRepository;

class Hreflang
{
    private TranslationRepository $repository;
    private LocalizedUrlBuilder $urlBuilder;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
        $this->urlBuilder = new LocalizedUrlBuilder($this->repository);
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

        if (!$objectId && is_front_page()) {
            $objectId = (int) get_option('page_on_front');
        }

        if (!$objectId && is_home()) {
            $objectId = (int) get_option('page_for_posts');
        }

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
        $post = get_post($objectId);

        if (!$post) {
            return '';
        }

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
        return $this->urlBuilder->buildObjectUrl($objectId, $lang, true);
    }
}
