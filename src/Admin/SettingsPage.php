<?php

namespace MCE\Multilang\Admin;

use MCE\Multilang\Core\LanguageManager;

/**
 * Admin settings screen.
 */
class SettingsPage {
	private const OPTION_KEY = 'mce_multilang_settings';

	/**
	 * Hook admin menu registration.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addMenuPage' ) );
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
	}

	/**
	 * Add plugin menu page.
	 */
	public function addMenuPage(): void {
		add_menu_page(
			__( 'Manual Multilang SEO', 'woodmart-manual-multilang-seo' ),
			__( 'Multilang SEO', 'woodmart-manual-multilang-seo' ),
			'manage_options',
			'mce-multilang-seo',
			array( $this, 'render' ),
			'dashicons-translation',
			56
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function registerSettings(): void {
		register_setting(
			'mce_multilang_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitizeSettings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitizeSettings( $input ): array {
		$input = is_array( $input ) ? $input : array();

		$clean = array(
			'default_footer_block_id' => isset( $input['default_footer_block_id'] ) ? absint( $input['default_footer_block_id'] ) : 0,
			'footer_block_ids'        => array(),
		);

		$languages = array_filter(
			LanguageManager::getSupportedLanguages(),
			static fn( string $language ): bool => ! LanguageManager::isDefault( $language )
		);

		foreach ( $languages as $language ) {
			$clean['footer_block_ids'][ $language ] = isset( $input['footer_block_ids'][ $language ] ) ? absint( $input['footer_block_ids'][ $language ] ) : 0;
		}

		return $clean;
	}

	/**
	 * Read settings option.
	 *
	 * @return array<string, mixed>
	 */
	private function getSettings(): array {
		$settings = get_option( self::OPTION_KEY, array() );
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Render settings UI.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings             = $this->getSettings();
		$defaultFooterBlockId = isset( $settings['default_footer_block_id'] ) ? (int) $settings['default_footer_block_id'] : 0;
		$footerBlockIds       = isset( $settings['footer_block_ids'] ) && is_array( $settings['footer_block_ids'] ) ? $settings['footer_block_ids'] : array();
		$languages            = array_filter(
			LanguageManager::getSupportedLanguages(),
			static fn( string $language ): bool => ! LanguageManager::isDefault( $language )
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woodmart Manual Multilang SEO', 'woodmart-manual-multilang-seo' ); ?></h1>
			<p><?php esc_html_e( 'Map each language to a dedicated Woodmart HTML Block ID for the footer.', 'woodmart-manual-multilang-seo' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'mce_multilang_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="mce-default-footer-block-id"><?php esc_html_e( 'Default Footer HTML Block ID', 'woodmart-manual-multilang-seo' ); ?></label>
							</th>
							<td>
								<input
									id="mce-default-footer-block-id"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[default_footer_block_id]"
									type="number"
									class="regular-text"
									value="<?php echo esc_attr( (string) $defaultFooterBlockId ); ?>"
									min="0"
									step="1"
								/>
								<p class="description">
									<?php esc_html_e( 'Enter the default English Woodmart HTML Block ID used by the footer.', 'woodmart-manual-multilang-seo' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<hr />
				<h2><?php esc_html_e( 'Per-Language Footer Block IDs', 'woodmart-manual-multilang-seo' ); ?></h2>

				<?php foreach ( $languages as $language ) : ?>
					<?php $value = isset( $footerBlockIds[ $language ] ) ? (int) $footerBlockIds[ $language ] : 0; ?>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="mce-footer-block-id-<?php echo esc_attr( $language ); ?>"><?php echo esc_html( strtoupper( $language ) . ' Footer Block ID' ); ?></label>
								</th>
								<td>
									<input id="mce-footer-block-id-<?php echo esc_attr( $language ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_block_ids][<?php echo esc_attr( $language ); ?>]" type="number" class="regular-text" value="<?php echo esc_attr( (string) $value ); ?>" min="0" step="1" />
								</td>
							</tr>
						</tbody>
					</table>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
