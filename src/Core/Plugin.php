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

    private static function boot(): void
    {
        add_action('admin_init', [Installer::class, 'maybeUpgrade']);

        new SettingsPage();
    }
}
