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
}
