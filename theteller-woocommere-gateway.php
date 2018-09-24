<?php

/*
Plugin Name: WooCommerce PaySwitch Theteller Payment Gateway
Plugin URI: https://wordpress.org/plugins/woocommerce-theteller-payment-gateway/
Description: PaySwitch Theteller Payment gateway for woocommerce
Version: 1.0
Author: Marc D Christopher AHOURE
Author URI: https://perfectplusventures.com
*/

if (!defined('ABSPATH')) {
    exit;
}
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}

add_action('plugins_loaded', 'woocommerce_theteller_init', 0);

function woocommerce_theteller_init() {
    if (!class_exists('WC_Payment_Gateway'))
        return;

    class WC_Theteller extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'theteller';
            $this->medthod_title = 'Theteller Payment Gateway';
            $this->icon = apply_filters('woocommerce_theteller_icon', plugins_url('assets/images/logo.png', __FILE__));
            $this->has_fields = false;

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchant_name = $this->settings['merchant_name'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->apiuser = $this->settings['apiuser'];
            $this->apikey = $this->settings['apikey'];
            $this->go_live = $this->settings['go_live'];

            if ($this->settings['go_live'] == "yes") {
                $this->api_base_url = 'https://prod.theteller.net/checkout/initiate';
               
            } else {
                 $this->api_base_url = 'https://test.theteller.net/checkout/initiate';
            }

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            if (isset($_REQUEST["theteller-response-notice"])) {
                wc_add_notice($_REQUEST["theteller-response-notice"], "error");
            }

            if (isset($_REQUEST["theteller-error-notice"])) {
                wc_add_notice($_REQUEST["theteller-error-notice"], "error");
            }


            if (isset($_REQUEST["order_id"]) && isset($_REQUEST["transaction_id"])) {
                
               //Check Theteller API Response...
                $this->check_theteller_response();

            }


            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            }
        }

        function init_form_fields() {
            $this->form_fields = array(

                'enabled' => array(
                    'title' => __('Enable/Disable', 'theteller'),
                    'type' => 'checkbox',
                    'label' => __('Enable Theteller Payment Gateway as a payment option on the checkout page.', 'theteller'),
                    'default' => 'no'),

                'go_live' => array(
          'title'       => __( 'Go Live', 'theteller' ),
          'label'       => __( 'Switch to live environment', 'client' ),
          'type'        => 'checkbox',
          'description' => __( 'Ensure that you have all your credentials details set.', 'client' ),
          'default'     => 'no',
          'desc_tip'    => true
        ),
                
                'title' => array(
                    'title' => __('Title:', 'theteller'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'theteller'),
                    'default' => __('Theteller', 'theteller')),

                'description' => array(
                    'title' => __('Description:', 'theteller'),
                    'type' => 'textarea',
                   'description' => __('This controls the description which the user sees during checkout.', 'client'),
                    'default' => __('Pay securely by Credit , Debit card or Mobile Money through PaySwitch Theteller Checkout.', 'client')),

                'merchant_name' => array(
                    'title' => __('Merchant Name or Shop name or Company Name ', 'theteller'),
                    'type' => 'text',
                    'description' => __('This Merchant name will be display to  user during payment .')),

                'merchant_id' => array(
                    'title' => __('Merchant ID', 'theteller'),
                    'type' => 'text',
                    'description' => __('This Merchant ID Given to Merchant by PaySwitch.')),

                'apiuser' => array(
                    'title' => __('API User', 'theteller'),
                    'type' => 'text',
                    'description' => __('API User given to Merchant by PaySwitch', 'theteller')),

                'apikey' => array(
                    'title' => __('API Key', 'theteller'),
                    'type' => 'text',
                    'description' => __('API Key given to Merchant by PaySwitch', 'theteller'))
                        );

        }

        public function admin_options() {
            echo '<h3>' . __('Theteller Payment Gateway', 'theteller') . '</h3>';
            echo '<p>' . __('Theteller is most popular payment gateway for online shopping in Ghana') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
            
        }

        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

       
//Sending Request to Theteller API...
function send_request_to_theteller_api($order_id) {
            
global $woocommerce;

//Getting settings...
$merchantname = $this->merchant_name;
$merchantid = $this->merchant_id; 
$api_base_url = $this->api_base_url;       
$apiuser = $this->apiuser; 
$apikey = $this->apikey; 
$order = new WC_Order($order_id);
$amount = $order->total;
$customer_email = $order->billing_email;


$redirect_url = $woocommerce->cart->get_checkout_url().'?order_id='.$order_id.'&theteller_response';

         //Convert amount to minor float..
         $minor='';
          if(is_float((float)$amount) || is_double((double)$amount)) {
        $number = $amount * 100;
        
        $zeros = 12 - strlen($number);
        $padding = '';
        for($i=0; $i<$zeros; $i++) {
            $padding .= '0';
        }
        $minor = $padding.$number;
    }
    if(strlen($amount)==12) {
        $minor = $amount;

    }


//Generating 12 unique random transaction id...
$transaction_id='';
$allowed_characters = array(1,2,3,4,5,6,7,8,9,0); 
for($i = 1;$i <= 12; $i++){ 
    $transaction_id .= $allowed_characters[rand(0, count($allowed_characters) - 1)]; 
  WC()->session->set('theteller_wc_transaction_id', $transaction_id);
} 


//Hashing order details...
$key_options = $merchantid.$transaction_id.$amount.$customer_email;
$theteller_wc_hash_key = hash('sha512', $key_options);
WC()->session->set('theteller_wc_hash_key', $theteller_wc_hash_key);
//die($theteller_wc_hash_key);
          

//Payload to send to API...
$postdata = array(
    'body' => json_encode(array(
    "merchant_id"  => $merchantid,
    'transaction_id'  => $transaction_id,
    'desc'  => "Payment  to ".$merchantname."",
    'amount'  => $minor,
    'email' =>$customer_email,
    'redirect_url'  => $redirect_url,
)),
    'timeout' => '60',
    'redirection' => '5',
    'httpversion' => '1.0',
    'blocking' => true,
    'sslverify' => true,
    'headers' => array( 
    'Content-Type' => 'application/json',
    'cache-control' => 'no-cache',
    'Expect' => '',
    'Authorization' => 'Basic '.base64_encode($apiuser.':'.$apikey).'' 
  ), 
    
);


//Making Request...
$response = wp_remote_post($api_base_url, $postdata);

//Decoding response...
$response_data = json_decode($response['body'], true);


//Checking if error
if ( is_wp_error($response) ) {
        $error_message = $response->get_error_message();
        echo $error_message;
    }
  
//Getting Response...
     if (!isset($response_data['code'])) {
    $response_data['code'] = null;
        }

        else
        {
            $code = $response_data['code'];
        }

    if (!isset($response_data['status'])) {
    $response_data['status'] = null;
        }

        else
        {
            $status = $response_data['status'];
        }


 if (!isset($response_data['reason'])) {
    $response_data['reason'] = null;
        }

        else
        {
            $reason = $response_data['reason'];
        }

         if (!isset($response_data['token'])) {
    $response_data['token'] = null;
        }

        else
        {
            $token = $response_data['token'];
        }

         if (!isset($response_data['description'])) {
    $response_data['description'] = null;
        }

        else
        {
            $description = $response_data['description'];
        }

        if (!isset($response_data['checkout_url'])) {
    $response_data['checkout_url'] = null;
        }

        else
        {
            $checkout_url = $response_data['checkout_url'];
        }

  
  if($status == "success" && $code == "200" && $token !="")
    { 

      //Redirect to checkout page...
        return $checkout_url;
        exit;

    }

    else
    {
        return $redirect_url . "&theteller-response-notice=" .$description;
    }

 


  }//end of send_request_to_theteller_api()...


        //Processing payment...
        function process_payment($order_id) {
            WC()->session->set('theteller_wc_oder_id', $order_id);
            $order = new WC_Order($order_id);
           
            return array(
                'result' => 'success',
                'redirect' => $this->send_request_to_theteller_api($order_id)
            );
        }

        //show message either error or success...
        function showMessage($content) {
            return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
        }

        
        function get_pages($title = false, $indent = true) {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title)
                $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_page($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }


        //Getting Theteller Api response...
        function check_theteller_response() {
            global $woocommerce;
            $theteller = isset($_REQUEST["theteller_response"]) ? $_REQUEST["theteller_response"] : "";
            $order_id = isset($_REQUEST["order_id"]) ? $_REQUEST["order_id"] : "";
            $code = isset($_REQUEST["code"]) ? $_REQUEST["code"] : "";
            $status = isset($_REQUEST["status"]) ? $_REQUEST["status"] : "";
            $transaction_id = isset($_REQUEST["transaction_id"]) ? $_REQUEST["transaction_id"] : "";
            $reason = isset($_REQUEST["reason"]) ? $_REQUEST["reason"] : "";

            if($theteller != "")
            {
                 die("<h2 style=color:red>Not a valid request !</h2>");
            }


            $wc_order_id = WC()->session->get('theteller_wc_oder_id');
            $order = new WC_Order($wc_order_id);
             if($order->status == 'pending' || $order->status == 'processing'){

            
            if ($order_id !='' && $code !=''  && $transaction_id !='') {

                 
               
                $wc_transaction_id = WC()->session->get('theteller_wc_transaction_id');
                $theteller_wc_hash_key = WC()->session->get('theteller_wc_hash_key');

                if(empty($theteller_wc_hash_key))
                {
                    die("<h2 style=color:red>Ooups ! something went wrong </h2>");
                }
                
                
                 if($order_id != $wc_order_id)
            {

                
                 $message = "Code 0001 : Data has been tampered . 
                            Order ID is ".$wc_order_id."";
                            $message_type = "error"; 
                            $order->add_order_note($message);
                            $redirect_url = $order->get_cancel_order_url();
                            wp_redirect($redirect_url);
                            exit;
               
            }

             if($transaction_id != $wc_transaction_id)
            {   

                 
                 $message = "Code 0002 : Data has been tampered . 
                            Order ID is ".$wc_order_id."";
                            $message_type = "error";
                            $order->add_order_note($message);
                            $redirect_url = $order->get_cancel_order_url();
                            wp_redirect($redirect_url);
                            exit;
                
            }

                if ($wc_order_id != '' && $wc_transaction_id !='') {

                    try {
                                             
                        if($code =="000")
                        {   
                            
                             $message = "Thank you for shopping with us. 
                                Your transaction was succssful, payment has been received. 
                                You order is currently being processed. 
                                Your Order ID is ".$wc_order_id."";
                                $message_type = "success";

                                $order->payment_complete();
                                $order->update_status('completed');
                                $order->add_order_note('Theteller status code : '.$code.'<br/>Transaction ID  ' . $wc_transaction_id.'<br /> Reason: '.$reason.'');
                               
                                $woocommerce->cart->empty_cart();
                                $redirect_url = $this->get_return_url($order);
                                $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                                 WC()->session->__unset('theteller_wc_hash_key');
                        WC()->session->__unset('theteller_wc_order_id');
                        WC()->session->__unset('theteller_wc_transaction_id');
                        wp_redirect($redirect_url);
                        exit;
                        }

                         if($code =="900")
                        {   
                             
                            $order->payment_complete();
                                $order->update_status('failed');
                                $order->add_order_note('Theteller status code : '.$code.'<br/>Transaction ID  ' . $wc_transaction_id.'<br /> Reason: Transaction declined');
                               
                              
                                 $redirect_url = $woocommerce->cart->get_checkout_url();
                                $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                                 WC()->session->__unset('theteller_wc_hash_key');
                        WC()->session->__unset('theteller_wc_order_id');
                        WC()->session->__unset('theteller_wc_transaction_id');
                        wp_redirect($redirect_url);
                        exit;
                                

                        }

                        if($code =="100")
                        {   
                           
                            $message = "Thank you for shopping with us. However, 
                                    the transaction has been declined.";
                                $message_type = "error";
                               
                                
                                 $order->payment_complete();
                                $order->update_status('failed');
                                $order->add_order_note('Theteller status code : '.$code.'<br/>Transaction ID  ' . $wc_transaction_id.'<br /> Reason: Transaction declined');
                               
                              
                                 $redirect_url = $woocommerce->cart->get_checkout_url();
                                $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                                 WC()->session->__unset('theteller_wc_hash_key');
                        WC()->session->__unset('theteller_wc_order_id');
                        WC()->session->__unset('theteller_wc_transaction_id');
                        wp_redirect($redirect_url);
                        exit;

                        }

                        else {
                                   
        $message = "Thank you for shopping with us. However, the transaction failed.";
        $message_type = "error";
                                   
                                   $order->payment_complete();
                                $order->update_status('failed');
                                $order->add_order_note('Theteller status code : '.$code.'<br/>Transaction ID  ' . $wc_transaction_id.'<br /> Reason: '.$reason.'');
                               
                                $woocommerce->cart->empty_cart();
                                $redirect_url = $this->get_return_url($order);
                                $customer = trim($order->billing_last_name . " " . $order->billing_first_name);
                                 WC()->session->__unset('theteller_wc_hash_key');
                        WC()->session->__unset('theteller_wc_order_id');
                        WC()->session->__unset('theteller_wc_transaction_id');
                        wp_redirect($redirect_url);
                        exit;

                                }

                      
                        $notification_message = array(
                            'message' => $message,
                            'message_type' => $message_type
                        );
                        if (version_compare(WOOCOMMERCE_VERSION, "2.2") >= 0) {
                            add_post_meta($wc_order_id, '_theteller_hash', $theteller_wc_hash_key, true);
                        }
                        update_post_meta($wc_order_id, '_theteller_wc_message', $notification_message);

                       
                    } catch (Exception $e) {
                        $order->add_order_note('Error: ' . $e->getMessage());
                        $redirect_url = $order->get_cancel_order_url();
                        wp_redirect($redirect_url);
                        exit;
                    }
              
                }
            }

               
            }
            
        }
        

      

        static function woocommerce_add_theteller_gateway($methods) {
            $methods[] = 'WC_Theteller';
            return $methods;
        }

        static function woocommerce_add_theteller_settings_link($links) {
            $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_theteller">Settings</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

    }

    $plugin = plugin_basename(__FILE__);

    
    add_filter("plugin_action_links_$plugin", array('WC_Theteller', 'woocommerce_add_theteller_settings_link'));
    add_filter('woocommerce_payment_gateways', array('WC_Theteller', 'woocommerce_add_theteller_gateway'));
}
