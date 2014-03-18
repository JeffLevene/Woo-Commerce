<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Certus_Connector' ) ) {
    
    class Certus_Connector {

        private $email_address = '';
        private $api_token = '';
        private $version = 1.0;
        private $batch_size = 100;
        public $text_domain;
        private $api_url;
        public $settings_url;

        protected static $instance = null;
        
        /**
        * Call this method to get singleton
        *
        * @return Certus_Connector
        */
        public static function getInstance() {
            if(is_null(self::$instance))
            {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Certus_Connector Constructor.
         *
         * @access public
         * @return void
         */
        private function __construct() {

            $this->text_domain = 'certus_connector';

            $this->api_url = 'http://still-waters-3188.herokuapp.com/deliveries';
            
            if ( is_admin() ) {
                $this->settings_url = admin_url('tools.php?page=certus_connector');
                add_action( 'admin_menu', array(&$this, 'add_admin_menu_page') );
                add_action ( 'wp_ajax_certus_connector_save', array (&$this, 'validate_and_save_settings') );
            }

            $settings = get_option('certus_connector_settings', null);
            if ( $settings != null ) {
                $this->api_token = (!empty($settings['api_token'])) ? $settings['api_token'] : null;
                $this->email_address = (!empty($settings['email_address'])) ? $settings['email_address'] : null; 
                if (is_admin() ) {
                    add_action ( 'wp_ajax_certus_connector_send_batch', array (&$this, 'send_batch') );       
                }
            }

            if (empty($this->api_token) || empty($this->email_address) ) {
                // Show a message to add API information
                add_action ( 'admin_notices', array (&$this, 'admin_notices_api_not_configured_yet') );
            }


        }
        
        public function add_admin_menu_page() {
            add_management_page( __('Certus Connector',  $this->text_domain) , __('Certus Connector',  $this->text_domain), 'manage_options', 'certus_connector', array(&$this, 'display_settings_page') );
        }

        public function admin_notices_api_not_configured_yet() {
           echo '<div id="certus_configure_message" class="updated fade"><p>'.sprintf( __('Please <a href="%s">configure your Certus Email Address and API Token</a> to begin transaction sync.', $this->text_domain ), $this->settings_url ).'</p></div>'; 
        }

        public function display_settings_page() {
            
            // Enque JS file
            wp_enqueue_script( 'certus-connector-js', plugins_url( '../assets/certus-connector.js', __FILE__) , array( 'jquery', 'jquery-ui-progressbar' ), $this->version, true );            
            
            $certus_params = array ('image_url' => plugins_url('../assets/images/', __FILE__) );
            wp_localize_script( 'certus-connector-js', 'certus_params', $certus_params );
            
            // Enque CSS file
            wp_enqueue_style( 'certus-connector-css', plugins_url( '../assets/certus-connector.css', __FILE__) , array( ) );            
            
            // Now show form
            ?>
            <div class="wrap" id="certus_connector_settings_page">
                <h2><?php _e("Certus Connector Settings", $this->text_domain); ?></h2>
                <div id="certus_message" class="updated fade" style="display:none;"><p></p></div>
                <form id="certus_connector_settings_form" action="" method="post">
                    <table cellspacing="15" cellpadding="5">
                        <tr><th><label for="certus_email_address"><?php _e("Your Certus Email Address", $this->text_domain); ?></label></th>
                            <td><input id="certus_email_address" placeholder="test@test.com" type="text" size="50" name="certus_email_address" value="<?php echo $this->email_address; ?>"/></td>
                        </tr>
                        <tr><th><label for="certus_api_token"><?php _e("Your Certus API Token", $this->text_domain); ?></label></th>
                            <td><input id="certus_api_token" placeholder="API Token1, API Token2, ..." type="text" size="50" name="certus_api_token" value="<?php echo $this->api_token; ?>"/></td>
                        </tr>
                        <tr><th>&nbsp;</th>
                            <td><input type="submit" id="certus_connector_submit" class="button-primary" value="<?php _e("Save &amp; Send Past Orders to Certus", $this->text_domain); ?>"><br/><br/>
                                    <div id="certus_connector_progressbar" class="certus_connector_progressbar" style="display:none;"><div id="certus_connector_progress_label" ><?php _e('Saving Settings...', $this->text_domain );?></div></div>
                            </td>
                        </tr>
                        <tr><th>&nbsp;</th>
                            <td></td>
                        </tr>
                    </table>
                </form>
            </div>
            <?php
        }

        public function validate_and_save_settings () {
            $response = array( 'status' => 'ERR', 'message' => __('Invalid Token or Email Address. Please try again.', $this->text_domain) ) ;

            if ( !empty($_POST['certus_api_token']) && !empty($_POST['certus_email_address']) ) {
                $token = trim($_POST['certus_api_token']);
                $email = trim($_POST['certus_email_address']);
                $result = $this->validate_api_info( $token, $email );
                if ( $result === true ) {

                    $token_unique = implode(",",array_map('trim',(array_unique(array_filter(explode(",",trim($token))))))); // to save only unique token keys
                    
                    $this->api_token = $settings['api_token'] = $token_unique;

                    $this->email_address = $settings['email_address'] = $email;
                    // Save settings
                    update_option( 'certus_connector_settings', $settings );
                    
                    // Get total orders
                    $order_count = apply_filters( 'certus_connector_get_order_count', 0 );
                                        
                    // Send response
                    $response = array( 'status' => 'OK', 'message' => '', 'order_count' => $order_count ) ;
                } else if ( is_wp_error( $result ) ) {
                    $response['message'] = $result->get_error_message();

                    $err_data = $result->get_error_data();

                    if (!empty($err_data)) {
                        $response['message'] .= ". '". $err_data ."' token(s) are unauthorized.";
                    }
                    
                }
            }
            die( json_encode( $response ));
        }

        private function validate_api_info( $token, $email ) {
          // Validate with API server
          //test by Simon
          return true;
            $result = wp_remote_head( $this->api_url, 
                        array( 'headers' => array(
                                'Authorization' => 'Basic ' . base64_encode( $email . ':' . $token ),
                                'User-Agent' => 'Certus Connector/'.$this->version
                                )
                            )
                        );

            if (is_wp_error( $result )) {
                return $result;
            }
            else if ( !empty($result['response']['code']) && $result['response']['code'] == 200 ) {
                return true;
            } else {
                if ( !empty($result['response']['code']) && !empty($result['response']['message'])) {
                    $unauthorized_tokens = (!empty($result['headers']['x-certus-invalid-token'])) ? $result['headers']['x-certus-invalid-token'] : '';
                    return new WP_Error( $result['response']['code'], $result['response']['message'], $unauthorized_tokens );
                }
            }
            return false;
        }

        public function post_order ( $order_id ) {

            $params ['order_id'] = $order_id; 
            $params = apply_filters( 'certus_connector_get_orders', $params );
            $post_result = $this->post_orders_to_certus( $params['data'] );
        }

        public function send_batch ( ) {
            $response = array( 'status' => 'OK', 'message' => '', 'results' => array() ) ;  

            $params = (!empty($_POST['params'])) ? json_decode( stripslashes($_POST['params']), true) : array();
            $params['limit'] = $this->batch_size;

            //Getting the data from ecommerce plugins
            $params = apply_filters( 'certus_connector_get_orders', $params );
            
            // Check if all orders are received...
            foreach ( (array) $params as $connector => $orders ) {
                
                // Send one batch to the server
                if (!empty($orders['data']) && is_array($orders['data']) && count($orders['data']) > 0)  {
                    
                    $count += $orders['count'];
                    $start_limit = $this->batch_size + $orders['last_start_limit'];
                    
                    $post_result = $this->post_orders_to_certus( $orders['data'] );
                    
                    $error_response= array();
                    
                    if (is_wp_error( $post_result ) ) {
                        $error_response[ $connector ]['status'] = 'ERR'; 
                        $error_response[ $connector ]['message'] = $post_result->get_error_message();
                        $response = array( 'status' => 'ERR', 'message' => '', 'results' => $error_response ); 
                    } else {
                        $response['results'][ $connector ] = array('status' => 'OK', 'start_limit' => $start_limit );
                    }
                }

                if ( $count < $this->batch_size ) {
                    $all_done = true;
                } else {
                    $all_done = false;
                    break;
                }
            }
            
            if ($all_done === true) {
                $response = array( 'status' => 'ALL_DONE', 'message' => '' ) ;    
            } else {
                $response['sent_count'] = $count;
            }

            die( json_encode( $response ));
        }

        public function post_orders_to_certus ( &$orders ) {
            if (empty($orders)) {
                return true;
            }
            $result = wp_remote_post( $this->api_url, 
                        array('headers' => array(
                                'Content-Type' => 'application/json',
                                //'Authorization' => 'Basic ' . base64_encode( $this->email_address . ':' . $this->api_token ),
                                'User-Agent' => 'Certus Connector/'.$this->version
                                ),
                               'timeout' => 120, 
                               'body' => json_encode($orders)
                            )
                          );
            if (is_wp_error( $result )) {
                return $result;
            }
            else {
                $server_response_default = array('ACK' => 'Failure', 'MESSAGE' => __('Unknown Response', $this->text_domain) );
                $server_response = json_decode( $result['body'], true);
                $server_response = array_merge( $server_response_default, $server_response );
                $server_response_code = $result['response']['code'];

                if ($server_response_code == 200 && $server_response['ACK'] == "Success") {
                    return true;
                }
                return new WP_Error( $server_response_code, $server_response['MESSAGE']);
            }
        }
    }
}
