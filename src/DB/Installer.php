<?php

namespace MCE\Multilang\DB;

use MCE\Multilang\Core\Config;

class Installer
{
    public static function run(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = Schema::getSql();

        dbDelta($sql);

        update_option(
            Config::DB_SCHEMA_VERSION_OPTION,
            Config::DB_SCHEMA_VERSION
        );
    }

    public static function maybeUpgrade(): void
    {
        $installedVersion = (string) get_option(Config::DB_SCHEMA_VERSION_OPTION, '');

        if ($installedVersion !== Config::DB_SCHEMA_VERSION) {
            self::run();
        }
    }
}
