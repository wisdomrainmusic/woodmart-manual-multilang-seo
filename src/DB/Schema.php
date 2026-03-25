<?php

namespace MCE\Multilang\DB;

/**
 * Defines and installs custom database tables.
 */
class Schema {
	/**
	 * Create or update plugin tables.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$translations    = self::getTranslationsTableName();
		$meta            = self::getTranslationMetaTableName();

		$translations_sql = "CREATE TABLE {$translations} (
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
			KEY lang_code (lang_code)
		) {$charset_collate};";

		$meta_sql = "CREATE TABLE {$meta} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			translation_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) NOT NULL,
			meta_value longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY translation_meta_unique (translation_id, meta_key),
			KEY translation_id (translation_id),
			KEY meta_key (meta_key)
		) {$charset_collate};";

		dbDelta( $translations_sql );
		dbDelta( $meta_sql );
	}

	/**
	 * Get translations table name.
	 */
	public static function getTranslationsTableName(): string {
		global $wpdb;

		return $wpdb->prefix . 'mce_translations';
	}

	/**
	 * Get translation meta table name.
	 */
	public static function getTranslationMetaTableName(): string {
		global $wpdb;

		return $wpdb->prefix . 'mce_translation_meta';
	}
}
