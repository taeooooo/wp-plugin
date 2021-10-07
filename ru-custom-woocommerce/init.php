<?php
/*
Plugin Name: Ru Customize For Woocommerce
Plugin URI:  https://run-up.co.kr
Description: Korea Checkout Customizing / My Account Customizing
Version:     1.0.0
Author:      RUNUP
Author URI:  http://www.run-up.co.kr
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );

if ( ! defined( 'RU_CUSTOM_WC_PLUGIN_FILE' ) ) {
        define( 'RU_CUSTOM_WC_PLUGIN_FILE', __FILE__ );
}

// Include Package Class . 
if ( ! class_exists( 'ru_sale_for_product_count', false ) ) {
        include_once dirname( RU_CUSTOM_WC_PLUGIN_FILE ) . '/includes/class-sale-for-product-count.php';
}

// Include Package Class . 
if ( ! class_exists( 'ru_product_custom_label', false ) ) {
        include_once dirname( RU_CUSTOM_WC_PLUGIN_FILE ) . '/includes/class-custom-label.php';
}

// Include Main Class.
if ( ! class_exists( 'ru_customize_woocommerce', false ) ) {
        include_once dirname( RU_CUSTOM_WC_PLUGIN_FILE ) . '/includes/class-ru-custom-woocommerce.php';
}


$ru_customize_woocommerce = ru_customize_woocommerce::instance();

register_activation_hook( __FILE__, array($ru_customize_woocommerce, 'install') );
register_deactivation_hook( __FILE__, array($ru_customize_woocommerce, 'uninstall') );
