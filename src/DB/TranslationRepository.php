<?php

namespace MCE\Multilang\DB;

/**
 * Repository for translation entities and metadata.
 */
class TranslationRepository {
	/**
	 * Allowed translation columns for write operations.
	 *
	 * @var string[]
	 */
	private array $allowedColumns = array(
		'object_id',
		'object_type',
		'lang_code',
		'translated_title',
		'translated_slug',
		'translated_excerpt',
		'translated_content',
		'seo_title',
		'seo_description',
		'custom_html',
		'status',
		'updated_at',
		'created_at',
	);

	/**
	 * Get translation by object and language.
	 */
	public function getTranslation( int $objectId, string $objectType, string $langCode ): ?array {
		global $wpdb;

		$object_id   = max( 0, $objectId );
		$object_type = $this->sanitizeObjectType( $objectType );
		$lang_code   = $this->sanitizeLangCode( $langCode );

		if ( 0 === $object_id || '' === $object_type || '' === $lang_code ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->getTableName()} WHERE object_id = %d AND object_type = %s AND lang_code = %s LIMIT 1",
			$object_id,
			$object_type,
			$lang_code
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get translation by primary id.
	 */
	public function getTranslationById( int $id ): ?array {
		global $wpdb;

		$translation_id = max( 0, $id );

		if ( 0 === $translation_id ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->getTableName()} WHERE id = %d LIMIT 1",
			$translation_id
		);

		$row = $wpdb->get_row( $sql, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Insert a translation row and return inserted ID.
	 */
	public function saveTranslation( array $data ): int {
		global $wpdb;

		$insert_data = $this->prepareTranslationData( $data, true );

		if ( empty( $insert_data ) ) {
			return 0;
		}

		$result = $wpdb->insert( $this->getTableName(), $insert_data, $this->getFormats( $insert_data ) );

		if ( false === $result ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update translation by ID.
	 */
	public function updateTranslation( int $id, array $data ): bool {
		global $wpdb;

		$translation_id = max( 0, $id );

		if ( 0 === $translation_id ) {
			return false;
		}

		$update_data = $this->prepareTranslationData( $data, false );

		if ( empty( $update_data ) ) {
			return false;
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->getTableName(),
			$update_data,
			array( 'id' => $translation_id ),
			$this->getFormats( $update_data ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete translation and related meta rows.
	 */
	public function deleteTranslation( int $id ): bool {
		global $wpdb;

		$translation_id = max( 0, $id );

		if ( 0 === $translation_id ) {
			return false;
		}

		$wpdb->delete(
			$this->getMetaTableName(),
			array( 'translation_id' => $translation_id ),
			array( '%d' )
		);

		$result = $wpdb->delete(
			$this->getTableName(),
			array( 'id' => $translation_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a translation meta value.
	 */
	public function getTranslationMeta( int $translationId, string $metaKey ) {
		global $wpdb;

		$translation_id = max( 0, $translationId );
		$meta_key       = sanitize_key( $metaKey );

		if ( 0 === $translation_id || '' === $meta_key ) {
			return null;
		}

		$sql = $wpdb->prepare(
			"SELECT meta_value FROM {$this->getMetaTableName()} WHERE translation_id = %d AND meta_key = %s LIMIT 1",
			$translation_id,
			$meta_key
		);

		$value = $wpdb->get_var( $sql );

		if ( null === $value ) {
			return null;
		}

		return maybe_unserialize( $value );
	}

	/**
	 * Save or update translation meta.
	 */
	public function saveTranslationMeta( int $translationId, string $metaKey, $metaValue ): bool {
		global $wpdb;

		$translation_id = max( 0, $translationId );
		$meta_key       = sanitize_key( $metaKey );

		if ( 0 === $translation_id || '' === $meta_key ) {
			return false;
		}

		$meta_value = maybe_serialize( $metaValue );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->getMetaTableName()} WHERE translation_id = %d AND meta_key = %s LIMIT 1",
				$translation_id,
				$meta_key
			)
		);

		if ( null !== $existing_id ) {
			$result = $wpdb->update(
				$this->getMetaTableName(),
				array( 'meta_value' => $meta_value ),
				array(
					'translation_id' => $translation_id,
					'meta_key'       => $meta_key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);

			return false !== $result;
		}

		$result = $wpdb->insert(
			$this->getMetaTableName(),
			array(
				'translation_id' => $translation_id,
				'meta_key'       => $meta_key,
				'meta_value'     => $meta_value,
			),
			array( '%d', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Delete translation meta by key.
	 */
	public function deleteTranslationMeta( int $translationId, string $metaKey ): bool {
		global $wpdb;

		$translation_id = max( 0, $translationId );
		$meta_key       = sanitize_key( $metaKey );

		if ( 0 === $translation_id || '' === $meta_key ) {
			return false;
		}

		$result = $wpdb->delete(
			$this->getMetaTableName(),
			array(
				'translation_id' => $translation_id,
				'meta_key'       => $meta_key,
			),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get translations table.
	 */
	public function getTableName(): string {
		return Schema::getTranslationsTableName();
	}

	/**
	 * Get translation meta table.
	 */
	public function getMetaTableName(): string {
		return Schema::getTranslationMetaTableName();
	}

	/**
	 * Prepare and sanitize translation data.
	 */
	private function prepareTranslationData( array $data, bool $forInsert ): array {
		$prepared = array();

		foreach ( $this->allowedColumns as $column ) {
			if ( ! array_key_exists( $column, $data ) ) {
				continue;
			}

			switch ( $column ) {
				case 'object_id':
					$object_id = max( 0, (int) $data['object_id'] );
					if ( $object_id > 0 ) {
						$prepared['object_id'] = $object_id;
					}
					break;
				case 'object_type':
					$object_type = $this->sanitizeObjectType( (string) $data['object_type'] );
					if ( '' !== $object_type ) {
						$prepared['object_type'] = $object_type;
					}
					break;
				case 'lang_code':
					$lang_code = $this->sanitizeLangCode( (string) $data['lang_code'] );
					if ( '' !== $lang_code ) {
						$prepared['lang_code'] = $lang_code;
					}
					break;
				case 'translated_slug':
					$prepared[ $column ] = sanitize_title( (string) $data[ $column ] );
					break;
				case 'status':
					$prepared['status'] = sanitize_key( (string) $data['status'] );
					break;
				case 'created_at':
				case 'updated_at':
					$prepared[ $column ] = sanitize_text_field( (string) $data[ $column ] );
					break;
				default:
					$prepared[ $column ] = is_null( $data[ $column ] ) ? null : wp_kses_post( (string) $data[ $column ] );
					break;
			}
		}

		if ( $forInsert ) {
			$now = current_time( 'mysql' );

			if ( ! isset( $prepared['created_at'] ) ) {
				$prepared['created_at'] = $now;
			}

			if ( ! isset( $prepared['updated_at'] ) ) {
				$prepared['updated_at'] = $now;
			}

			if ( ! isset( $prepared['status'] ) ) {
				$prepared['status'] = 'active';
			}

			$required = array( 'object_id', 'object_type', 'lang_code' );
			foreach ( $required as $required_key ) {
				if ( ! isset( $prepared[ $required_key ] ) || '' === (string) $prepared[ $required_key ] ) {
					return array();
				}
			}
		} else {
			unset( $prepared['created_at'] );
		}

		return $prepared;
	}

	/**
	 * Build wpdb format map for columns.
	 *
	 * @param array<string,mixed> $data Data set.
	 *
	 * @return string[]
	 */
	private function getFormats( array $data ): array {
		$formats = array();

		foreach ( array_keys( $data ) as $key ) {
			if ( 'object_id' === $key ) {
				$formats[] = '%d';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}

	/**
	 * Sanitize supported object type.
	 */
	private function sanitizeObjectType( string $objectType ): string {
		$object_type = sanitize_key( $objectType );
		$allowed     = array( 'post', 'product', 'attachment', 'menu_item' );

		return in_array( $object_type, $allowed, true ) ? $object_type : '';
	}

	/**
	 * Sanitize language code.
	 */
	private function sanitizeLangCode( string $langCode ): string {
		$lang_code = sanitize_key( $langCode );

		if ( '' === $lang_code ) {
			return '';
		}

		if ( strlen( $lang_code ) > 10 ) {
			return substr( $lang_code, 0, 10 );
		}

		return $lang_code;
	}
}
