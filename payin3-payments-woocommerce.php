<?php
/**
 * Plugin Name: BharatX Pay-in-3
 * Plugin URI: https://github.com/ashirrwad
 * Author: Ashirwad Mishra
 * Author URI: https://github.com/ashirrwad
 * Description: BharatX pay-in-3 integration.
 * Version: 0.1.0
 * License: GPL2
 * License URL: http://www.gnu.org/licenses/gpl-2.0.txt
 * text-domain: payin3-payments-woo
 * 
 * Class WC_Gateway_Payin3 file.
 *
 * @package WooCommerce\Payin3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

//dont load if woocommerce is not present
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

add_action( 'plugins_loaded', 'payin3_payment_init', 11 );
add_filter( 'woocommerce_payment_gateways', 'add_to_woo_payin3_payment_gateway');

function payin3_payment_init() {
    if( class_exists( 'WC_Payment_Gateway' ) ) {
		require_once plugin_dir_path( __FILE__ ) . '/includes/class-wc-payment-gateway-payin3.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/payin3-order-statuses.php';
		require_once plugin_dir_path( __FILE__ ) . '/includes/payin3-checkout-description-fields.php';
	}
}

function add_to_woo_payin3_payment_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Payin3';
    return $gateways;
}

