<?php

namespace MCE\Multilang\Admin;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class TranslationMetaBox
{
    private const NONCE_ACTION = 'mce_multilang_save_translations';
    private const NONCE_NAME = 'mce_multilang_translations_nonce';

    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post', [$this, 'saveTranslations']);
    }

    private function getSupportedPostTypes(): array
    {
        return [
            'page', 'post', 'product',
            'cms_block', 'html_block', 'woodmart_html_block',
        ];
    }

    public function registerMetaBox(): void
    {
        foreach ($this->getSupportedPostTypes() as $postType) {
            add_meta_box(
                'mce-multilang-translations',
                __('MCE Translations', 'woodmart-manual-multilang-seo'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $languages = array_filter(
            LanguageManager::getSupportedLanguages(),
            static fn (string $language): bool => !LanguageManager::isDefault($language)
        );

        echo '<div class="mce-multilang-wrapper">';
        echo '<p><strong>Manual multilingual fields for this content.</strong></p>';

        echo '<div class="mce-multilang-tabs" style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">';

        $first = true;
        foreach ($languages as $language) {
            $active = $first ? 'style="background:#2271b1;color:#fff;border-color:#2271b1;"' : '';
            echo '<button type="button" class="button mce-lang-tab" data-lang="' . esc_attr($language) . '" ' . $active . '>' . esc_html(strtoupper($language)) . '</button>';
            $first = false;
        }

        echo '</div>';

        $firstPanel = true;

        foreach ($languages as $language) {
            $translation = $this->repository->getTranslation((int) $post->ID, $post->post_type, $language);
            $display = $firstPanel ? 'block' : 'none';

            echo '<div class="mce-lang-panel" data-lang-panel="' . esc_attr($language) . '" style="display:' . esc_attr($display) . '; border:1px solid #ddd; padding:16px; margin-bottom:16px; background:#fff;">';
            echo '<h3 style="margin-top:0;">' . esc_html(strtoupper($language)) . '</h3>';

            $this->renderTextField($language, 'translated_title', 'Title', $translation['translated_title'] ?? '');
            $this->renderTextField($language, 'translated_slug', 'Slug', $translation['translated_slug'] ?? '');
            $this->renderTextareaField($language, 'translated_excerpt', 'Short Description / Excerpt', $translation['translated_excerpt'] ?? '', 4);
            $this->renderTextareaField($language, 'translated_content', 'Main Content', $translation['translated_content'] ?? '', 10);
            $this->renderTextField($language, 'seo_title', 'SEO Title', $translation['seo_title'] ?? '');
            $this->renderTextareaField($language, 'seo_description', 'Meta Description', $translation['seo_description'] ?? '', 3);
            $this->renderTextField($language, 'html_block_ref', 'Woodmart HTML Block ID / Slug', $this->getTranslationMetaValue($translation, 'html_block_ref'));
            $this->renderTextareaField($language, 'custom_html', 'Custom HTML Override', $translation['custom_html'] ?? '', 12);

            echo '</div>';
            $firstPanel = false;
        }

        echo '</div>';

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.mce-lang-tab');
            const panels = document.querySelectorAll('.mce-lang-panel');

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    const lang = this.getAttribute('data-lang');

                    tabs.forEach(function (item) {
                        item.style.background = '';
                        item.style.color = '';
                        item.style.borderColor = '';
                    });

                    panels.forEach(function (panel) {
                        panel.style.display = 'none';
                    });

                    this.style.background = '#2271b1';
                    this.style.color = '#fff';
                    this.style.borderColor = '#2271b1';

                    const activePanel = document.querySelector('.mce-lang-panel[data-lang-panel="' + lang + '"]');

                    if (activePanel) {
                        activePanel.style.display = 'block';
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function saveTranslations(int $postId): void
    {
        if (!$this->canSave($postId)) {
            return;
        }

        $postType = get_post_type($postId);

        if (!in_array($postType, $this->getSupportedPostTypes(), true)) {
            return;
        }

        $rawData = $_POST['mce_multilang'] ?? null;

        // Gutenberg / REST fallback.
        if (!$rawData) {
            $input = file_get_contents('php://input');

            if ($input) {
                $json = json_decode($input, true);

                if (isset($json['meta']['mce_multilang'])) {
                    $rawData = $json['meta']['mce_multilang'];
                }
            }
        }

        if (empty($rawData) || !is_array($rawData)) {
            return;
        }

        $allowedLanguages = array_filter(
            LanguageManager::getSupportedLanguages(),
            static fn (string $language): bool => !LanguageManager::isDefault($language)
        );

        foreach ($rawData as $language => $values) {
            if (!is_string($language) || !in_array($language, $allowedLanguages, true) || !is_array($values)) {
                continue;
            }

            $data = $this->collectLanguageData($values);

            if (!$this->hasAnyValue($data)) {
                continue;
            }

            $existing = $this->repository->getTranslation($postId, $postType, $language);

            $payload = [
                'object_id'          => $postId,
                'object_type'        => $postType,
                'lang_code'          => $language,
                'translated_title'   => $data['translated_title'],
                'translated_slug'    => $data['translated_slug'],
                'translated_excerpt' => $data['translated_excerpt'],
                'translated_content' => $data['translated_content'],
                'seo_title'          => $data['seo_title'],
                'seo_description'    => $data['seo_description'],
                'custom_html'        => $data['custom_html'],
                'status'             => 'active',
            ];

            $translationId = 0;

            if ($existing && !empty($existing['id'])) {
                $this->repository->updateTranslation((int) $existing['id'], $payload);
                $translationId = (int) $existing['id'];
            } else {
                $translationId = $this->repository->saveTranslation($payload);
            }

            if ($translationId > 0) {
                $this->saveTranslationMetaValue($translationId, 'html_block_ref', $data['html_block_ref']);
            }
        }
    }

    private function canSave(int $postId): bool
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (!isset($_POST[self::NONCE_NAME])) {
            return false;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return false;
        }

        if (!current_user_can('edit_post', $postId)) {
            return false;
        }

        return true;
    }

    private function collectLanguageData(array $languageData): array
    {
        return [
            'translated_title'   => $this->getPostedValue($languageData, 'translated_title'),
            'translated_slug'    => $this->getPostedValue($languageData, 'translated_slug'),
            'translated_excerpt' => $this->getPostedValue($languageData, 'translated_excerpt'),
            'translated_content' => $this->getPostedValue($languageData, 'translated_content'),
            'seo_title'          => $this->getPostedValue($languageData, 'seo_title'),
            'seo_description'    => $this->getPostedValue($languageData, 'seo_description'),
            'html_block_ref'     => $this->getPostedValue($languageData, 'html_block_ref'),
            'custom_html'        => $this->getPostedValue($languageData, 'custom_html'),
        ];
    }

    private function getPostedValue(array $languageData, string $field): string
    {
        $value = $languageData[$field] ?? '';

        if (!is_string($value)) {
            return '';
        }

        return wp_unslash($value);
    }

    private function hasAnyValue(array $data): bool
    {
        foreach ($data as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function renderTextField(string $language, string $field, string $label, string $value): void
    {
        echo '<p>';
        echo '<label><strong>' . esc_html($label) . '</strong></label><br />';
        echo '<input type="text" style="width:100%;" name="mce_multilang[' . esc_attr($language) . '][' . esc_attr($field) . ']" value="' . esc_attr($value) . '" />';
        echo '</p>';
    }

    private function renderTextareaField(string $language, string $field, string $label, string $value, int $rows = 6): void
    {
        echo '<p>';
        echo '<label><strong>' . esc_html($label) . '</strong></label><br />';
        echo '<textarea style="width:100%;" rows="' . esc_attr((string) $rows) . '" name="mce_multilang[' . esc_attr($language) . '][' . esc_attr($field) . ']">' . esc_textarea($value) . '</textarea>';
        echo '</p>';
    }

    private function getTranslationMetaValue(?array $translation, string $metaKey): string
    {
        if (!$translation || empty($translation['id'])) {
            return '';
        }

        $value = $this->repository->getTranslationMeta((int) $translation['id'], $metaKey);

        return is_string($value) ? $value : '';
    }

    private function saveTranslationMetaValue(int $translationId, string $metaKey, string $metaValue): void
    {
        $metaValue = trim($metaValue);

        if ($metaValue === '') {
            $this->repository->deleteTranslationMeta($translationId, $metaKey);
            return;
        }

        $this->repository->saveTranslationMeta(
            $translationId,
            $metaKey,
            $metaValue
        );
    }
}
