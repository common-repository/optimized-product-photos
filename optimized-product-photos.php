<?php
/*
Plugin Name: Retail ready photos by Pixc
Plugin URI:  https://pixc.com/partners-and-integrations
Description: Pixc removes the background and optimizes your photos, ensuring your products look their best so you can increase your online sales.
Version:     1.2
Author:      Pixc inc.
Author URI:  https://pixc.com/about
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: optimized-product-photos
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    require dirname(__FILE__) . '/include/PIXC_Plugin.php';

    register_activation_hook( __FILE__, array( 'PIXC_Plugin', 'activate' ));
    register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

    PIXC_Plugin::init();
}
