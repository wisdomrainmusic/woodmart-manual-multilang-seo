<?php

namespace MCE\Multilang\Admin;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class MenuTranslations
{
    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_action('wp_nav_menu_item_custom_fields', [$this, 'renderFields'], 10, 4);
        add_action('wp_update_nav_menu_item', [$this, 'saveFields'], 10, 3);
    }

    public function renderFields(int $itemId, \WP_Post $item, int $depth, array $args): void
    {
        $languages = array_filter(
            LanguageManager::getSupportedLanguages(),
            static fn (string $language): bool => !LanguageManager::isDefault($language)
        );

        echo '<div class="mce-menu-translation-fields" style="margin:12px 0 0 0; padding:12px; border:1px solid #ddd; background:#fafafa;">';
        echo '<p><strong>' . esc_html__('MCE Menu Translations', 'woodmart-manual-multilang-seo') . '</strong></p>';

        foreach ($languages as $language) {
            $translation = $this->repository->getTranslation($itemId, 'nav_menu_item', $language);
            $value = $translation['translated_title'] ?? '';

            echo '<p style="margin-bottom:10px;">';
            echo '<label for="edit-menu-item-mce-translated-title-' . esc_attr((string) $itemId) . '-' . esc_attr($language) . '">';
            echo '<strong>' . esc_html(strtoupper($language)) . ' ' . esc_html__('Label', 'woodmart-manual-multilang-seo') . '</strong>';
            echo '</label><br />';
            echo '<input type="text" class="widefat code edit-menu-item-custom" ';
            echo 'id="edit-menu-item-mce-translated-title-' . esc_attr((string) $itemId) . '-' . esc_attr($language) . '" ';
            echo 'name="menu-item-mce-translated-title[' . esc_attr((string) $itemId) . '][' . esc_attr($language) . ']" ';
            echo 'value="' . esc_attr($value) . '" />';
            echo '</p>';
        }

        wp_nonce_field('mce_menu_translations_action', 'mce_menu_translations_nonce');

        echo '</div>';
    }

    public function saveFields(int $menuId, int $menuItemDbId, array $args): void
    {
        if (!isset($_POST['mce_menu_translations_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['mce_menu_translations_nonce']));

        if (!wp_verify_nonce($nonce, 'mce_menu_translations_action')) {
            return;
        }

        if (!current_user_can('edit_theme_options')) {
            return;
        }

        $postedValues = $_POST['menu-item-mce-translated-title'][$menuItemDbId] ?? null;

        if (!is_array($postedValues)) {
            return;
        }

        $languages = array_filter(
            LanguageManager::getSupportedLanguages(),
            static fn (string $language): bool => !LanguageManager::isDefault($language)
        );

        foreach ($languages as $language) {
            $rawValue = $postedValues[$language] ?? '';

            if (!is_string($rawValue)) {
                continue;
            }

            $translatedTitle = trim(wp_unslash($rawValue));
            $existing = $this->repository->getTranslation($menuItemDbId, 'nav_menu_item', $language);

            if ($translatedTitle === '') {
                if ($existing && !empty($existing['id'])) {
                    $this->repository->deleteTranslation((int) $existing['id']);
                }

                continue;
            }

            $payload = [
                'object_id' => $menuItemDbId,
                'object_type' => 'nav_menu_item',
                'lang_code' => $language,
                'translated_title' => $translatedTitle,
                'translated_slug' => null,
                'translated_excerpt' => null,
                'translated_content' => null,
                'seo_title' => null,
                'seo_description' => null,
                'custom_html' => null,
                'status' => 'active',
            ];

            if ($existing && !empty($existing['id'])) {
                $this->repository->updateTranslation((int) $existing['id'], $payload);
            } else {
                $this->repository->saveTranslation($payload);
            }
        }
    }
}
