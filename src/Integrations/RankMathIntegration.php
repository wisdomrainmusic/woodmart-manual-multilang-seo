<?php

namespace MCE\Multilang\Integrations;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class RankMathIntegration
{
    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_filter('rank_math/frontend/title', [$this, 'filterTitle']);
        add_filter('rank_math/frontend/description', [$this, 'filterDescription']);
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
