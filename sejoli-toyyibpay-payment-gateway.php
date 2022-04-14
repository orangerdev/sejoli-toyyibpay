<?php
/**
 *
 * @link              https://ridwan-arifandi.com
 * @since             1.0.0
 * @package           Sejoli
 *
 * @wordpress-plugin
 * Plugin Name:       Sejoli - TOYYIBPAY Payment Gateway
 * Plugin URI:        https://sejoli.co.id
 * Description:       Integrate Sejoli Premium WordPress Membership Plugin with TOYYIBPAY Payment Gateway.
 * Version:           1.0.0
 * Requires PHP: 	  7.4.1
 * Author:            Sejoli
 * Author URI:        https://sejoli.co.id
 * Text Domain:       sejoli-tpyyibpay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {

	die;

}

// Register payment gateway
add_filter('sejoli/payment/available-libraries', function( array $libraries ) {

    require_once ( plugin_dir_path( __FILE__ ) . '/class-toyyibpay-payment-gateway.php' );

    $libraries['toyyibpay'] = new \SejoliToyyibpay();

    return $libraries;

});

add_action( 'plugins_loaded', 'sejoli_toyyibpay_plugin_init' ); 
function sejoli_toyyibpay_plugin_init() {

    load_plugin_textdomain( 'sejoli-toyyibpay', false, dirname(plugin_basename(__FILE__)).'/languages/' );

}
