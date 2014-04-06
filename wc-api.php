<?php

    require_once 'class-wc-api-client.php';

    $consumer_key = 'ck_9719126d0c03b07f5d966ae166558a85'; // Add your own Consumer Key here
    $consumer_secret = 'cs_e908e17f53834b53b654897d78b7fe65'; // Add your own Consumer Secret here
    $store_url = 'http://woocommerce.com/'; // Add the home URL to the store you want to connect to here

    // Initialize the class
    $wc_api = new WC_API_Client( $consumer_key, $consumer_secret, $store_url );
    
    $action = $_GET['action'];
    if($action == 'GetOrder'){
      $output = $wc_api->get_order($_GET['ID']);
    }
    elseif($action == 'GetProduct')
      $output = $wc_api->get_product($_GET['ID']);

    header("Content-type: application/json");
    die(json_encode($output));
