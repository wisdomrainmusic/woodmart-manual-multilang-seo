<?php

namespace MCE\Multilang\Frontend;

use MCE\Multilang\Core\LanguageManager;

class MenuFilter
{
    public function register(): void
    {
        add_filter('wp_nav_menu_objects', [$this, 'translateMenu'], 10, 2);
    }

    public function translateMenu(array $items): array
    {
        if (is_admin()) {
            return $items;
        }

        $lang = LanguageManager::getCurrentLanguage();

        if ($lang === 'en') {
            return $items;
        }

        foreach ($items as $item) {
            $translated = $this->getTranslation($item->ID, $lang);

            if ($translated) {
                $item->title = $translated;
            }
        }

        return $items;
    }

    private function getTranslation(int $objectId, string $lang): ?string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'translations';

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT title FROM $table WHERE object_id = %d AND lang = %s AND type = 'menu' LIMIT 1",
                $objectId,
                $lang
            )
        );

        return $result ?: null;
    }
}
