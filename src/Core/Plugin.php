<?php

namespace MCE\Multilang\Core;

use MCE\Multilang\Admin\MenuTranslations;
use MCE\Multilang\Admin\SettingsPage;
use MCE\Multilang\Admin\TranslationMetaBox;
use MCE\Multilang\DB\Installer;
use MCE\Multilang\Frontend\ContentFilter;
use MCE\Multilang\Frontend\Hreflang;
use MCE\Multilang\Frontend\MenuFilter;
use MCE\Multilang\Integrations\RankMathIntegration;

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

        $menuTranslations = new MenuTranslations();
        $menuTranslations->register();

        $translationMetaBox = new TranslationMetaBox();
        $translationMetaBox->register();

        $router = new Router();
        $router->register();

        $contentFilter = new ContentFilter();
        $contentFilter->register();

        $rankMathIntegration = new RankMathIntegration();
        $rankMathIntegration->register();

        $hreflang = new Hreflang();
        $hreflang->register();

        $menuFilter = new MenuFilter();
        $menuFilter->register();
    }
}
