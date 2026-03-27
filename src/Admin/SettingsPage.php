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
			'footer_block_id' => isset( $input['footer_block_id'] ) ? absint( $input['footer_block_id'] ) : 0,
			'footer_html'     => array(),
		);

		$languages = array_filter(
			LanguageManager::getSupportedLanguages(),
			static fn( string $language ): bool => ! LanguageManager::isDefault( $language )
		);

		foreach ( $languages as $language ) {
			$value = $input['footer_html'][ $language ] ?? '';
			$clean['footer_html'][ $language ] = is_string( $value ) ? wp_kses_post( wp_unslash( $value ) ) : '';
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

		$settings      = $this->getSettings();
		$footerBlockId = isset( $settings['footer_block_id'] ) ? (int) $settings['footer_block_id'] : 0;
		$footerHtml    = isset( $settings['footer_html'] ) && is_array( $settings['footer_html'] ) ? $settings['footer_html'] : array();
		$languages     = array_filter(
			LanguageManager::getSupportedLanguages(),
			static fn( string $language ): bool => ! LanguageManager::isDefault( $language )
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woodmart Manual Multilang SEO', 'woodmart-manual-multilang-seo' ); ?></h1>
			<p><?php esc_html_e( 'Use this area for special overrides that are safer than editing Woodmart HTML Blocks directly.', 'woodmart-manual-multilang-seo' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'mce_multilang_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="mce-footer-block-id"><?php esc_html_e( 'Footer HTML Block ID', 'woodmart-manual-multilang-seo' ); ?></label>
							</th>
							<td>
								<input
									id="mce-footer-block-id"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_block_id]"
									type="number"
									class="regular-text"
									value="<?php echo esc_attr( (string) $footerBlockId ); ?>"
									min="0"
									step="1"
								/>
								<p class="description">
									<?php esc_html_e( 'Enter the Woodmart footer HTML Block post ID (cms_block). When this block is rendered on non-default languages, the translated footer HTML below will replace its content.', 'woodmart-manual-multilang-seo' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<hr />
				<h2><?php esc_html_e( 'Footer HTML Overrides', 'woodmart-manual-multilang-seo' ); ?></h2>

				<?php foreach ( $languages as $language ) : ?>
					<?php $value = isset( $footerHtml[ $language ] ) && is_string( $footerHtml[ $language ] ) ? $footerHtml[ $language ] : ''; ?>
					<h3 style="margin-top:24px;"><?php echo esc_html( strtoupper( $language ) ); ?></h3>
					<textarea
						name="<?php echo esc_attr( self::OPTION_KEY ); ?>[footer_html][<?php echo esc_attr( $language ); ?>]"
						rows="12"
						style="width:100%; font-family:monospace;"
					><?php echo esc_textarea( $value ); ?></textarea>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
