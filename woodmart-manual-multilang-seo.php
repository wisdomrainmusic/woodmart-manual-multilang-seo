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
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$namespace = 'MCE\\Multilang\\';

			if ( 0 !== strpos( $class, $namespace ) ) {
				return;
			}

			$relative = substr( $class, strlen( $namespace ) );
			$parts    = explode( '\\', $relative );
			$root     = array_shift( $parts );
			$roots    = array(
				'Core'     => 'Core',
				'Admin'    => 'Admin',
				'DB'       => 'DB',
				'Frontend' => 'Frontend',
				'Integrations' => 'Integrations',
			);

			if ( empty( $root ) || ! isset( $roots[ $root ] ) ) {
				return;
			}

			$path = MCE_MULTILANG_SEO_PATH . 'src/' . $roots[ $root ] . '/';

			if ( ! empty( $parts ) ) {
				$path .= implode( '/', $parts ) . '.php';
			} else {
				$path .= $root . '.php';
			}

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	);
}

class_exists( 'MCE\\Multilang\\DB\\Installer' );
class_exists( 'MCE\\Multilang\\Core\\Plugin' );

register_activation_hook(
	MCE_MULTILANG_SEO_FILE,
	array( 'MCE\\Multilang\\Core\\Plugin', 'activate' )
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
