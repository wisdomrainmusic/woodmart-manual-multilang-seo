<?php

namespace MCE\Multilang\Core;

use MCE\Multilang\Admin\SettingsPage;

/**
 * Main plugin bootstrapper.
 */
class Plugin {
	/**
	 * Initialize plugin services.
	 */
	public static function init(): void {
		$plugin = new self();
		$plugin->boot();
	}

	/**
	 * Deactivation hook callback.
	 */
	public static function deactivate(): void {
		// Reserved for future deactivation routines.
	}

	/**
	 * Boot core features.
	 */
	private function boot(): void {
		$settings_page = new SettingsPage();
		$settings_page->register();
	}
}
