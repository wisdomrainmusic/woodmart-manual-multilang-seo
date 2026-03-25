<?php

namespace MCE\Multilang\Core;

use MCE\Multilang\Admin\SettingsPage;
use MCE\Multilang\DB\Installer;

class Plugin
{
    public static function init(): void
    {
        self::boot();
    }

    public static function deactivate(): void
    {
        // Reserved for future cleanup tasks.
    }

    private static function boot(): void
    {
        add_action('admin_init', [Installer::class, 'maybeUpgrade']);

        $settingsPage = new SettingsPage();
        $settingsPage->register();
    }
}
