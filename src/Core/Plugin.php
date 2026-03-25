<?php

namespace MCE\Multilang\Core;

use MCE\Multilang\Admin\SettingsPage;
use MCE\Multilang\DB\Installer;
use MCE\Multilang\Frontend\ContentFilter;

class Plugin
{
    public static function activate(): void
    {
        Installer::run();
        Router::activate();
    }

    public static function init(): void
    {
        self::boot();
    }

    public static function deactivate(): void
    {
        Router::deactivate();
    }

    private static function boot(): void
    {
        add_action('admin_init', [Installer::class, 'maybeUpgrade']);

        $settingsPage = new SettingsPage();
        $settingsPage->register();

        $router = new Router();
        $router->register();

        $contentFilter = new ContentFilter();
        $contentFilter->register();
    }
}
