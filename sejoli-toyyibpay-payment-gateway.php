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
 * Version:           1.0.1
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

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'SEJOLI_TOYYIBPAY_VERSION',    '1.0.1' );
define( 'SEJOLI_TOYYIBPAY_DIR',        plugin_dir_path( __FILE__ ) );
define( 'SEJOLI_TOYYIBPAY_URL',        plugin_dir_url( __FILE__ ) );

add_action('muplugins_loaded', 'sejoli_toyyibpay_check_sejoli');

function sejoli_toyyibpay_check_sejoli() {

    if(!defined('SEJOLISA_VERSION')) :

        add_action('admin_notices', 'sejoli_toyyibpay_no_sejoli_functions');

        function sejoli_toyyibpay_no_sejoli_functions() {
            ?><div class='notice notice-error'>
            <p><?php _e('Anda belum menginstall atau mengaktifkan SEJOLI terlebih dahulu.', 'sejoli-toyyibpay'); ?></p>
            </div><?php
        }

        return;
    endif;

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
