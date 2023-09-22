<?php
/**
 * Plugin Name: Share Commerce Payment
 * Plugin URI: https://share-commerce.com
 * Description: Share Commerce Payment 
 * Version: 1.0.1
 * Author: Share Commerce
 * Author URI: https://share-commerce.com
 * WC requires at least: 4.3
 * WC tested up to: 5.8.1
 **/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

# init
add_action( 'plugins_loaded', 'scpay_init', 0 );

function scpay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/scpay.php' );

	add_filter( 'woocommerce_payment_gateways', 'add_scpay_to_woocommerce' );
	function add_scpay_to_woocommerce( $methods ) {
		$methods[] = 'scPay';

		return $methods;
	}
}

# admin
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'scpay_links' );

function scpay_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=scpay' ) . '">' . __( 'Settings', 'scpay' ) . '</a>',
	);

	# Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}

# callback post
add_action( 'init', 'scpay_callback', 15 );

function scpay_callback() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/scpay.php' );

	$scpay = new scpay();
	$scpay->scpay_callback();
}

# redirect 
add_action( 'init', 'scpay_redirect', 15 );

function scpay_redirect() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( 'src/scpay.php' );

	$scpay = new scpay();
	$scpay->scpay_redirect();
}

function scpay_hash_error_msg( $content ) {
	return '<div class="woocommerce-error">The data that we received is invalid. Thank you.</div>' . $content;
}

function scpay_payment_declined_msg( $content ) {
	return '<div class="woocommerce-error">The payment was declined. Please check with your bank. Thank you.</div>' . $content;
}

function scpay_success_msg( $content ) {
	return '<div class="woocommerce-info">The payment was successful. Thank you.</div>' . $content;
}
