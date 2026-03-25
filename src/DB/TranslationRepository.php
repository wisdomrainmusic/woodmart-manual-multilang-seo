<?php

namespace MCE\Multilang\DB;

class TranslationRepository
{
    private const CACHE_GROUP = 'mce_multilang_translations';
    private const CACHE_MISS = '__mce_multilang_translation_miss__';
    private const CACHE_TTL = HOUR_IN_SECONDS;

    public function getTableName(): string
    {
        return Schema::getTranslationsTableName();
    }

    public function getMetaTableName(): string
    {
        return Schema::getTranslationMetaTableName();
    }

    private function getTranslationCacheKey(int $objectId, string $objectType, string $langCode): string
    {
        return sprintf(
            'translation:%d:%s:%s',
            $objectId,
            sanitize_key($objectType),
            sanitize_key($langCode)
        );
    }

    private function deleteTranslationCache(int $objectId, string $objectType, string $langCode): void
    {
        wp_cache_delete(
            $this->getTranslationCacheKey($objectId, $objectType, $langCode),
            self::CACHE_GROUP
        );
    }

    private function primeTranslationCache(int $objectId, string $objectType, string $langCode, ?array $translation): void
    {
        wp_cache_set(
            $this->getTranslationCacheKey($objectId, $objectType, $langCode),
            $translation ?? self::CACHE_MISS,
            self::CACHE_GROUP,
            self::CACHE_TTL
        );
    }

    public function getTranslation(int $objectId, string $objectType, string $langCode): ?array
    {
        global $wpdb;

        $objectType = sanitize_key($objectType);
        $langCode = sanitize_key($langCode);

        $cacheKey = $this->getTranslationCacheKey($objectId, $objectType, $langCode);
        $cached = wp_cache_get($cacheKey, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached === self::CACHE_MISS ? null : $cached;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->getTableName()} WHERE object_id = %d AND object_type = %s AND lang_code = %s LIMIT 1",
            $objectId,
            $objectType,
            $langCode
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        $translation = is_array($row) ? $row : null;
        $this->primeTranslationCache($objectId, $objectType, $langCode, $translation);

        return $translation;
    }

    public function getTranslationById(int $id): ?array
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->getTableName()} WHERE id = %d LIMIT 1",
            $id
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function saveTranslation(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');

        $insertData = [
            'object_id'           => isset($data['object_id']) ? (int) $data['object_id'] : 0,
            'object_type'         => isset($data['object_type']) ? sanitize_key((string) $data['object_type']) : '',
            'lang_code'           => isset($data['lang_code']) ? sanitize_key((string) $data['lang_code']) : '',
            'translated_title'    => $data['translated_title'] ?? null,
            'translated_slug'     => isset($data['translated_slug']) ? sanitize_title((string) $data['translated_slug']) : null,
            'translated_excerpt'  => $data['translated_excerpt'] ?? null,
            'translated_content'  => $data['translated_content'] ?? null,
            'seo_title'           => $data['seo_title'] ?? null,
            'seo_description'     => $data['seo_description'] ?? null,
            'custom_html'         => $data['custom_html'] ?? null,
            'status'              => isset($data['status']) ? sanitize_key((string) $data['status']) : 'active',
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        $formats = [
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        ];

        $result = $wpdb->insert($this->getTableName(), $insertData, $formats);

        if ($result === false) {
            return 0;
        }

        $this->deleteTranslationCache(
            (int) $insertData['object_id'],
            (string) $insertData['object_type'],
            (string) $insertData['lang_code']
        );

        return (int) $wpdb->insert_id;
    }

    public function updateTranslation(int $id, array $data): bool
    {
        global $wpdb;
        $existing = $this->getTranslationById($id);

        $allowed = [
            'translated_title',
            'translated_slug',
            'translated_excerpt',
            'translated_content',
            'seo_title',
            'seo_description',
            'custom_html',
            'status',
        ];

        $updateData = [];
        $formats = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($field === 'translated_slug' && $value !== null) {
                $value = sanitize_title((string) $value);
            }

            if ($field === 'status' && $value !== null) {
                $value = sanitize_key((string) $value);
            }

            $updateData[$field] = $value;
            $formats[] = '%s';
        }

        $updateData['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        if (empty($updateData)) {
            return false;
        }

        $result = $wpdb->update(
            $this->getTableName(),
            $updateData,
            ['id' => $id],
            $formats,
            ['%d']
        );

        if ($result !== false && is_array($existing)) {
            $this->deleteTranslationCache(
                (int) $existing['object_id'],
                (string) $existing['object_type'],
                (string) $existing['lang_code']
            );
        }

        return $result !== false;
    }

    public function deleteTranslation(int $id): bool
    {
        global $wpdb;
        $existing = $this->getTranslationById($id);

        $wpdb->delete(
            $this->getMetaTableName(),
            ['translation_id' => $id],
            ['%d']
        );

        $result = $wpdb->delete(
            $this->getTableName(),
            ['id' => $id],
            ['%d']
        );

        if ($result !== false && is_array($existing)) {
            $this->deleteTranslationCache(
                (int) $existing['object_id'],
                (string) $existing['object_type'],
                (string) $existing['lang_code']
            );
        }

        return $result !== false;
    }

    public function getTranslationMeta(int $translationId, string $metaKey): mixed
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT meta_value FROM {$this->getMetaTableName()} WHERE translation_id = %d AND meta_key = %s LIMIT 1",
            $translationId,
            sanitize_key($metaKey)
        );

        $value = $wpdb->get_var($sql);

        return $value;
    }

    public function saveTranslationMeta(int $translationId, string $metaKey, mixed $metaValue): bool
    {
        global $wpdb;

        $metaKey = sanitize_key($metaKey);
        $metaValue = maybe_serialize($metaValue);

        $existing = $this->getTranslationMeta($translationId, $metaKey);

        if ($existing !== null) {
            $result = $wpdb->update(
                $this->getMetaTableName(),
                ['meta_value' => $metaValue],
                [
                    'translation_id' => $translationId,
                    'meta_key'       => $metaKey,
                ],
                ['%s'],
                ['%d', '%s']
            );

            return $result !== false;
        }

        $result = $wpdb->insert(
            $this->getMetaTableName(),
            [
                'translation_id' => $translationId,
                'meta_key'       => $metaKey,
                'meta_value'     => $metaValue,
            ],
            ['%d', '%s', '%s']
        );

        return $result !== false;
    }

    public function deleteTranslationMeta(int $translationId, string $metaKey): bool
    {
        global $wpdb;

        $result = $wpdb->delete(
            $this->getMetaTableName(),
            [
                'translation_id' => $translationId,
                'meta_key'       => sanitize_key($metaKey),
            ],
            ['%d', '%s']
        );

        return $result !== false;
    }
}
