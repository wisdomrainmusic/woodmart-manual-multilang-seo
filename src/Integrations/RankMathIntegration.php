<?php

namespace MCE\Multilang\Integrations;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\Core\LocalizedUrlBuilder;
use MCE\Multilang\DB\TranslationRepository;

class RankMathIntegration
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
        add_filter('rank_math/frontend/title', [$this, 'filterTitle']);
        add_filter('rank_math/frontend/description', [$this, 'filterDescription']);
        add_filter('rank_math/frontend/canonical', [$this, 'filterCanonicalUrl']);

        // 🔥 SEO FIXES
        add_filter('rank_math/opengraph/url', [$this, 'filterOpenGraphUrl']);
        add_filter('rank_math/opengraph/facebook/locale', [$this, 'filterOpenGraphLocale']);
        add_filter('language_attributes', [$this, 'filterLanguageAttributes']);
        add_filter('get_canonical_url', [$this, 'filterCanonicalUrl']);
    }

    public function filterTitle(string $title): string
    {
        if (is_admin()) {
            return $title;
        }

        $translation = $this->getCurrentTranslation();

        if (!$translation) {
            return $title;
        }

        return !empty($translation['seo_title'])
            ? (string) $translation['seo_title']
            : $title;
    }

    public function filterDescription(string $description): string
    {
        if (is_admin()) {
            return $description;
        }

        $translation = $this->getCurrentTranslation();

        if (!$translation) {
            return $description;
        }

        return !empty($translation['seo_description'])
            ? (string) $translation['seo_description']
            : $description;
    }

    // 🔥 OG URL FIX
    public function filterOpenGraphUrl(string $url): string
    {
        if (is_admin()) {
            return $url;
        }

        $currentUrl = $this->getCurrentLocalizedUrl();

        return $currentUrl !== '' ? $currentUrl : $url;
    }

    public function filterCanonicalUrl(string $url): string
    {
        if (is_admin()) {
            return $url;
        }

        $localizedUrl = $this->getCurrentLocalizedUrl();

        return $localizedUrl !== '' ? $localizedUrl : $url;
    }

    // 🔥 OG LOCALE FIX (Facebook format: de_DE)
    public function filterOpenGraphLocale(string $locale): string
    {
        if (is_admin()) {
            return $locale;
        }

        return $this->getCurrentOpenGraphLocale();
    }

    // 🔥 HTML LANG FIX (HTML standard: de-DE)
    public function filterLanguageAttributes(string $output): string
    {
        if (is_admin()) {
            return $output;
        }

        $locale = $this->getCurrentHtmlLocale();

        if ($locale === '') {
            return $output;
        }

        if (strpos($output, 'lang=') !== false) {
            return (string) preg_replace(
                '/lang=("|\')[^"\']*("|\')/i',
                'lang="' . esc_attr($locale) . '"',
                $output,
                1
            );
        }

        return trim($output . ' lang="' . esc_attr($locale) . '"');
    }

    private function getCurrentHtmlLocale(): string
    {
        $lang = LanguageManager::getCurrentLanguage();

        $map = [
            'en' => 'en-US',
            'de' => 'de-DE',
            'it' => 'it-IT',
            'fr' => 'fr-FR',
            'es' => 'es-ES',
            'tr' => 'tr-TR',
        ];

        return $map[$lang] ?? 'en-US';
    }

    private function getCurrentOpenGraphLocale(): string
    {
        $lang = LanguageManager::getCurrentLanguage();

        $map = [
            'en' => 'en_US',
            'de' => 'de_DE',
            'it' => 'it_IT',
            'fr' => 'fr_FR',
            'es' => 'es_ES',
            'tr' => 'tr_TR',
        ];

        return $map[$lang] ?? 'en_US';
    }

    private function getCurrentLocalizedUrl(): string
    {
        $objectId = $this->resolveObjectId();

        if ($objectId <= 0) {
            return '';
        }

        $language = LanguageManager::getCurrentLanguage();

        return $this->urlBuilder->buildObjectUrl($objectId, $language, false);
    }

    private function getCurrentTranslation(): ?array
    {
        $language = LanguageManager::getCurrentLanguage();

        if (LanguageManager::isDefault($language)) {
            return null;
        }

        $objectId = $this->resolveObjectId();

        if ($objectId <= 0) {
            return null;
        }

        $postType = get_post_type($objectId);

        if (!$postType || !in_array($postType, ['page', 'post', 'product'], true)) {
            return null;
        }

        return $this->repository->getTranslation($objectId, $postType, $language);
    }

    private function resolveObjectId(): int
    {
        $queriedObjectId = get_queried_object_id();

        if (is_numeric($queriedObjectId) && (int) $queriedObjectId > 0) {
            return (int) $queriedObjectId;
        }

        $loopPostId = get_the_ID();

        if (is_numeric($loopPostId) && (int) $loopPostId > 0) {
            return (int) $loopPostId;
        }

        global $post;

        if (is_object($post) && !empty($post->ID)) {
            return (int) $post->ID;
        }

        return 0;
    }
}
