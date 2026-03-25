<?php
/**
 * Uninstall routine for Woodmart Manual Multilang SEO.
 *
 * @package MCE\Multilang
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mce_multilang_seo_version' );
