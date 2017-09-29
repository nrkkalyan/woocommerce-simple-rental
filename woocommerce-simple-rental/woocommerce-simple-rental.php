<?php
/**
 * @package Woocommerce Simple Rental
 */
/*
Plugin Name: Woocommerce Simple Rental
Description: This plugin simply allows Woocommerce products (simple and variation) for rental. Features multi-currency and multi-lingual support as well as security deposits.
Version: 1.0.1
Author: Daniel (Amplus Marketing & Design)
Author URI: https://amplusmarketing.com
*/

// Make sure we don't expose any info if called directly; also Woocommerce is required.
if ( function_exists( 'add_action' ) && in_array( 
    'woocommerce/woocommerce.php', 
    apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) 
  ) ) {

	foreach ( glob( __DIR__ . "{/inc/*.php}", GLOB_BRACE ) as $file ) {
	    require_once $file;
	}

	register_activation_hook( __FILE__, array( 'Woocommerce_Simple_Rental_Setup', 'plugin_activation' ) );
	register_deactivation_hook( __FILE__, array( 'Woocommerce_Simple_Rental_Setup', 'plugin_deactivation' ) );
	add_action( 'init', array( 'Woocommerce_Simple_Rental_Setup', 'init' ) );
}

