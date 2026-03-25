<?php
/**
 * Plugin Name: Woodmart Manual Multilang SEO
 * Plugin URI:  https://example.com/
 * Description: Manual multilingual SEO toolkit for Woodmart-based WooCommerce stores.
 * Version:     1.0.0
 * Requires PHP: 8.1
 * Author:      MCE
 * Text Domain: woodmart-manual-multilang-seo
 *
 * @package MCE\Multilang
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MCE_MULTILANG_SEO_VERSION' ) ) {
	define( 'MCE_MULTILANG_SEO_VERSION', '1.0.0' );
}

if ( ! defined( 'MCE_MULTILANG_SEO_FILE' ) ) {
	define( 'MCE_MULTILANG_SEO_FILE', __FILE__ );
}

if ( ! defined( 'MCE_MULTILANG_SEO_PATH' ) ) {
	define( 'MCE_MULTILANG_SEO_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MCE_MULTILANG_SEO_URL' ) ) {
	define( 'MCE_MULTILANG_SEO_URL', plugin_dir_url( __FILE__ ) );
}

$autoload_file = MCE_MULTILANG_SEO_PATH . 'vendor/autoload.php';

if ( file_exists( $autoload_file ) ) {
	require_once $autoload_file;
}

register_activation_hook(
	MCE_MULTILANG_SEO_FILE,
	array( 'MCE\\Multilang\\DB\\Installer', 'activate' )
);

register_deactivation_hook(
	MCE_MULTILANG_SEO_FILE,
	array( 'MCE\\Multilang\\Core\\Plugin', 'deactivate' )
);

add_action(
	'plugins_loaded',
	static function () {
		MCE\Multilang\Core\Plugin::init();
	}
);
