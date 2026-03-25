<?php

namespace MCE\Multilang\DB;

class Schema
{
    public static function getTranslationsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'translations';
    }

    public static function getTranslationMetaTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'translation_meta';
    }

    public static function getSql(): string
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $translationsTable = self::getTranslationsTableName();
        $translationMetaTable = self::getTranslationMetaTableName();

        return "
CREATE TABLE {$translationsTable} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    object_id bigint(20) unsigned NOT NULL,
    object_type varchar(50) NOT NULL,
    lang_code varchar(10) NOT NULL,
    translated_title longtext NULL,
    translated_slug varchar(255) NULL,
    translated_excerpt longtext NULL,
    translated_content longtext NULL,
    seo_title text NULL,
    seo_description text NULL,
    custom_html longtext NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY  (id),
    UNIQUE KEY object_lang_unique (object_id, object_type, lang_code),
    KEY object_id (object_id),
    KEY object_type (object_type),
    KEY lang_code (lang_code),
    KEY translated_slug (translated_slug)
) {$charsetCollate};

CREATE TABLE {$translationMetaTable} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    translation_id bigint(20) unsigned NOT NULL,
    meta_key varchar(191) NOT NULL,
    meta_value longtext NULL,
    PRIMARY KEY  (id),
    KEY translation_id (translation_id),
    KEY meta_key (meta_key),
    KEY translation_meta_lookup (translation_id, meta_key)
) {$charsetCollate};
";
    }
}
