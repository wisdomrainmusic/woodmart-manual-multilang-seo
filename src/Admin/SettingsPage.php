<?php

namespace MCE\Multilang\Admin;

/**
 * Admin settings screen skeleton.
 */
class SettingsPage {
	/**
	 * Hook admin menu registration.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addMenuPage' ) );
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
	 * Render settings placeholder.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woodmart Manual Multilang SEO', 'woodmart-manual-multilang-seo' ); ?></h1>
			<p><?php esc_html_e( 'Settings UI will be implemented in a future step.', 'woodmart-manual-multilang-seo' ); ?></p>
		</div>
		<?php
	}
}
