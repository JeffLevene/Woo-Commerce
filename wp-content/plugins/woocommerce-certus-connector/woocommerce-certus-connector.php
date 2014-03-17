<?php
/*
 * Plugin Name: WooCommerce Certus Connector
 * Description: Track WooCommerce transactions data with Certus. Communicate the ordor with the Workflow server.
 * Version: 1.0
 * License: GPL 3.0
*/

add_action( 'plugins_loaded', 'woocommerce_certus_connector_pre_init' );

function woocommerce_certus_connector_pre_init () {

	// Simple check for WooCommerce being active...
	if ( class_exists('WooCommerce') ) {

		// Init admin menu for settings etc if we are in admin
		if ( is_admin() ) {
			woocommerce_certus_connector_init();
		} 

		// If configuration not done, can't track anything...
		if ( null != get_option('certus_connector_settings', null) ) {
			// On these events, send order data to Certus
			if ( is_admin() && ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) ) { 
                add_action( 'post_updated', 'woocommerce_certus_connector_order_updated');
            } else {
                add_action( 'woocommerce_order_status_changed', 'woocommerce_certus_connector_post_order' );
            }
		}
	}
}

function woocommerce_certus_connector_init() {
	
	include_once 'classes/class.certus-connector.php';
	$GLOBALS['certus_connector'] = Certus_Connector::getInstance();

    include_once 'classes/class.certus-connector-woocommerce.php';
    if ( !isset( $GLOBALS['woocommerce_certus_connector'] ) ) {
    	$GLOBALS['woocommerce_certus_connector'] = new WooCommerce_Certus_Connector();
	}
}

function woocommerce_certus_connector_order_updated( $post_id ) {

	$order_status_old = get_the_terms( $post_id,'shop_order_status');
	$order_status_new = $_POST['order_status'];

	if ( get_post_type( $post_id ) === 'shop_order' && $order_status_old[0]->slug == $order_status_new ) {
		woocommerce_certus_connector_post_order($post_id);
	}
}

function woocommerce_certus_connector_post_order( $order_id ) {
	woocommerce_certus_connector_init();
	if (method_exists($GLOBALS['certus_connector'], 'post_order') ) {
		$GLOBALS['certus_connector']->post_order( $order_id );	
	}
}

