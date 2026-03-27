<?php

namespace MCE\Multilang\Admin;

use MCE\Multilang\Core\LanguageManager;
use MCE\Multilang\DB\TranslationRepository;

class TranslationMetaBox
{
    private const NONCE_ACTION = 'mce_multilang_save_translations';
    private const NONCE_NAME   = 'mce_multilang_translations_nonce';
    private const REST_META_KEY = '_mce_multilang_payload';

    private TranslationRepository $repository;

    public function __construct(?TranslationRepository $repository = null)
    {
        $this->repository = $repository ?? new TranslationRepository();
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerRestMeta']);
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post', [$this, 'saveTranslations']);

        foreach ($this->getSupportedPostTypes() as $postType) {
            add_action(
                'rest_after_insert_' . $postType,
                [$this, 'saveTranslationsFromRest'],
                10,
                3
            );
        }
    }

    private function getSupportedPostTypes(): array
    {
        return [
            'page', 'post', 'product',
            'cms_block', 'html_block', 'woodmart_html_block',
        ];
    }

    public function registerRestMeta(): void
    {
        foreach ($this->getSupportedPostTypes() as $postType) {
            register_post_meta($postType, self::REST_META_KEY, [
                'single'       => true,
                'type'         => 'string',
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'string',
                    ],
                ],
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
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

        $payloadForJs = [];

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
            $payloadForJs[$language] = [
                'translated_title'   => $translation['translated_title'] ?? '',
                'translated_slug'    => $translation['translated_slug'] ?? '',
                'translated_excerpt' => $translation['translated_excerpt'] ?? '',
                'translated_content' => $translation['translated_content'] ?? '',
                'seo_title'          => $translation['seo_title'] ?? '',
                'seo_description'    => $translation['seo_description'] ?? '',
                'html_block_ref'     => $translation['html_block_ref'] ?? '',
                'custom_html'        => $translation['custom_html'] ?? '',
            ];

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

        echo '<input type="hidden" 
            id="mce-multilang-payload" 
            name="' . esc_attr(self::REST_META_KEY) . '" 
            value="' . esc_attr(wp_json_encode($payloadForJs)) . '" 
        />';
        echo '</div>';

        ?>
        <script>
        wp.domReady(function () {
            const wrapper = document.querySelector('.mce-multilang-wrapper');
            const tabs = document.querySelectorAll('.mce-lang-tab');
            const panels = document.querySelectorAll('.mce-lang-panel');

            if (!wrapper) {
                return;
            }

            function collectPayload() {
                const payload = {};
                const fields = wrapper.querySelectorAll('input[name^="mce_multilang["], textarea[name^="mce_multilang["]');

                fields.forEach(function (field) {
                    const match = field.name.match(/^mce_multilang\[([^\]]+)\]\[([^\]]+)\]$/);

                    if (!match) {
                        return;
                    }

                    const lang = match[1];
                    const key = match[2];

                    if (!payload[lang]) {
                        payload[lang] = {};
                    }

                    payload[lang][key] = field.value || '';
                });

                return payload;
            }

            function syncPayload() {
                const payload = collectPayload();
                const json = JSON.stringify(payload);

                if (
                    window.wp &&
                    wp.data &&
                    wp.data.dispatch
                ) {
                    const editor = wp.data.select('core/editor');
                    const currentMeta = editor.getEditedPostAttribute('meta') || {};

                    wp.data.dispatch('core/editor').editPost({
                        meta: {
                            ...currentMeta,
                            '_mce_multilang_payload': json
                        }
                    });
                }
            }

            wrapper.addEventListener('input', syncPayload);
            wrapper.addEventListener('change', syncPayload);

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

            syncPayload();
        });
        </script>
        <?php
    }

    public function saveTranslationsFromRest($post, $request, bool $creating): void
    {
        if (!$post instanceof \WP_Post) {
            return;
        }

        if (!in_array($post->post_type, $this->getSupportedPostTypes(), true)) {
            return;
        }

        if (!current_user_can('edit_post', (int) $post->ID)) {
            return;
        }

        if (!$request instanceof \WP_REST_Request) {
            return;
        }

        $requestData = $request->get_json_params();

        if (!is_array($requestData) || empty($requestData)) {
            $requestData = $request->get_params();
        }

        $this->persistTranslations((int) $post->ID, (string) $post->post_type, $requestData);
    }

    public function saveTranslations(int $postId): void
    {
        $requestData = $this->getRequestData();

        if (!$this->canSave($postId, $requestData)) {
            return;
        }

        $postType = get_post_type($postId);

        if (!in_array($postType, $this->getSupportedPostTypes(), true)) {
            return;
        }

        // Gutenberg / REST save işlemi ayrıca rest_after_insert_* ile ele alınıyor.
        // Burada sadece klasik / metabox submit hattını çalıştırıyoruz.
        if ((defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request()) {
            return;
        }

        $this->persistTranslations($postId, $postType, $requestData);
    }

    private function persistTranslations(int $postId, string $postType, array $requestData): void
    {
        $rawData = $this->extractRawData($requestData);
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

            $existing = $this->repository->getTranslation($postId, $postType, $language);

            if (!$this->hasAnyValue($data)) {
                $this->deleteExistingTranslationIfNeeded($existing);
                continue;
            }

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

    private function extractRawData(array $requestData): ?array
    {
        if (isset($requestData['mce_multilang']) && is_array($requestData['mce_multilang'])) {
            return $requestData['mce_multilang'];
        }

        if (
            isset($requestData['meta']) &&
            is_array($requestData['meta']) &&
            isset($requestData['meta'][self::REST_META_KEY]) &&
            is_string($requestData['meta'][self::REST_META_KEY])
        ) {
            $decoded = json_decode(wp_unslash($requestData['meta'][self::REST_META_KEY]), true);
            return is_array($decoded) ? $decoded : null;
        }

        if (
            isset($requestData[self::REST_META_KEY]) &&
            is_string($requestData[self::REST_META_KEY])
        ) {
            $decoded = json_decode(wp_unslash($requestData[self::REST_META_KEY]), true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    private function deleteExistingTranslationIfNeeded(?array $existing): void
    {
        if (!$existing || empty($existing['id'])) {
            return;
        }

        $translationId = (int) $existing['id'];

        $this->repository->deleteTranslationMeta($translationId, 'html_block_ref');
        $this->repository->deleteTranslation($translationId);
    }

    private function canSave(int $postId, array $requestData = []): bool
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (!current_user_can('edit_post', $postId)) {
            return false;
        }

        $nonce = '';

        if (isset($_POST[self::NONCE_NAME]) && is_string($_POST[self::NONCE_NAME])) {
            $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));
        } elseif (isset($requestData[self::NONCE_NAME]) && is_string($requestData[self::NONCE_NAME])) {
            $nonce = sanitize_text_field(wp_unslash($requestData[self::NONCE_NAME]));
        }

        // Classic / metabox submit.
        if ($nonce !== '') {
            return wp_verify_nonce($nonce, self::NONCE_ACTION);
        }

        // Gutenberg / REST fallback for logged-in editors.
        if ((defined('REST_REQUEST') && REST_REQUEST) || wp_is_json_request()) {
            return true;
        }

        return false;
    }

    private function getRequestData(): array
    {
        if (!empty($_POST) && is_array($_POST)) {
            return $_POST;
        }

        $input = file_get_contents('php://input');

        if (!is_string($input) || trim($input) === '') {
            return [];
        }

        $json = json_decode($input, true);

        if (is_array($json)) {
            if (isset($json['mce_multilang']) && is_array($json['mce_multilang'])) {
                return $json;
            }

            if (isset($json['meta']) && is_array($json['meta'])) {
                return $json;
            }
        }

        $parsed = [];
        parse_str($input, $parsed);

        if (is_array($parsed) && !empty($parsed)) {
            return $parsed;
        }

        return [];
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
