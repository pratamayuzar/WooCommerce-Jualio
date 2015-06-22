<?php
/*
Plugin Name: WooCommerce Jualio Payment Gateway
Plugin URI: http://www.saklik.com
Description: Jualio Payment gateway for woocommerce (For Now Only IDR Currency)
Version: 1.0
Author: Yuzar
Author URI: http://www.saklik.com
*/
add_action('admin_menu', 'jl_pg_plugin_setup_menu');
 
function jl_pg_plugin_setup_menu(){
    add_menu_page( 'Jualio Payment Tutorial', 'Jualio Payment Tutorial', 'manage_options', 'jl-pg-Tutorial', 'jl_pg_init' );

}

function jl_pg_init(){
    include_once('template/tutorial-pg.php');
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'my_plugin_action_links' );

function my_plugin_action_links( $links ) {
   $links[] = '<a href="'. esc_url( get_admin_url(null, 'admin.php?page=jl-pg-Tutorial') ) .'">Tutorial</a>';
   return $links;
}


add_action('plugins_loaded', 'woocommerce_jualio_pg_init', 0);




function woocommerce_jualio_pg_init(){
	if(!class_exists('WC_Payment_Gateway')) return;

	class WC_Jualio_PG extends WC_Payment_Gateway{
	    public function __construct(){
	      $this -> id = 'jualio';
	      $this -> medthod_title = 'jualio';
	      $this -> has_fields = false;

	      $this -> init_form_fields();
	      $this -> init_settings();

	      $this -> title = $this -> settings['title'];
	      $this -> description = $this -> settings['description'];
	      $this -> master_merchant_id = $this -> settings['master_merchant_id'];
	      $this -> merchant_id = $this -> settings['merchant_id'];
	      $this -> password = $this -> settings['password'];
	      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
	      $this -> status = $this -> settings['status'];
	      $this -> liveurl = 'https://payment.jualio.com/caisse-core/Payment';
	      $this -> devurl = 'http://dev.payment.jualio.com/caisse-core/Payment';

	      $this -> msg['message'] = "";
	      $this -> msg['class'] = "";

	      if ($this->status == 'no'){
	      	$this -> liveurl = $this -> devurl;
	      }
	      
	      //add_action('init', array(&$this, 'check_payu_response2'));
	      if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
	                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
	             } else {
	                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
	            }
	      add_action('woocommerce_receipt_jualio', array(&$this, 'receipt_page'));
	      
	      //add_action('init', array(&$this, 'check_payu_response2'));
	      
	   }
	    function init_form_fields(){

	       $this -> form_fields = array(
	                'enabled' => array(
	                    'title' => __('Enable/Disable', 'jualio'),
	                    'type' => 'checkbox',
	                    'label' => __('Enable Jualio Payment Module.', 'jualio'),
	                    'default' => 'no'),
	                'title' => array(
	                    'title' => __('Title:', 'jualio'),
	                    'type'=> 'text',
	                    'description' => __('This controls the title which the user sees during checkout.', 'jualio'),
	                    'default' => __('Jualio', 'jualio')),
	                'description' => array(
	                    'title' => __('Description:', 'jualio'),
	                    'type' => 'textarea',
	                    'description' => __('This controls the description which the user sees during checkout.', 'jualio'),
	                    'default' => __('Pay securely by Credit or Debit card or internet banking through Jualio Secure Servers.', 'jualio')),
	                'merchant_id' => array(
	                    'title' => __('Merchant ID', 'jualio'),
	                    'type' => 'text',
	                    'description' => __('Get Merchant ID from jualio officer')),
	                'master_merchant_id' => array(
	                    'title' => __('Master Merchant ID', 'jualio'),
	                    'type' => 'text',
	                    'description' => __('Get Master Merchant ID from jualio officer')),
	                'password' => array(
	                    'title' => __('Your Jualio Password', 'jualio'),
	                    'type' => 'text',
	                    'description' => __('Get Your Jualio Password from jualio officer')),
	                'redirect_page_id' => array(
	                    'title' => __('Return Page'),
	                    'type' => 'select',
	                    'options' => $this -> get_pages('Select Page'),
	                    'description' => "URL of success page"
	                ),
	                'status' => array(
	                    'title' => __('Live/Sandbox', 'jualio'),
	                    'type' => 'checkbox',
	                    'label' => __('Live Jualio Payment.', 'jualio'),
	                    'default' => 'no'),
	            );
	    }

		public function admin_options(){
			echo '<h3>'.__('Jualio Payment Gateway', 'jualio').'</h3>';
			echo '<p>'.__('Jualio is most popular payment gateway for online shopping in Indonesia (Only can use currency IDR)').'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this -> generate_settings_html();
			echo '</table>';

		}

	    /**
	     *  There are no payment fields for payu, but we want to show the description if set.
	     **/
	    function payment_fields(){
	        if($this -> description) echo wpautop(wptexturize($this -> description));
	    }
	    /**
	     * Receipt Page
	     **/
	    function receipt_page($order){
	        echo '<p>'.__('Thank you for your order, please click the button below to pay with Jualio.', 'jualio').'</p>';
	        echo $this -> generate_jualio_form($order);
	    }
	    /**
	     * Generate payu button link
	     **/
	    public function generate_jualio_form($order_id){

	       global $woocommerce;
	    	$order = new WC_Order( $order_id );
	        $txnid = $order_id.'_'.date("ymds");

	        $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);

	        $productinfo = "Order $order_id";

	        $order_item = $order->get_items();

			$cart = '';
			foreach ($order_item as $product) {
				
				$data_product = new WC_Product($product['product_id']);
				$url = get_the_guid ( $data_product->get_image_id() );
				if (empty($url)){
					$url = 'http://portal.trees.id/images/lots/00/000/073/636/Legok%20Awi%201.jpg';
				}

				$cart .= $product['name'].','.$data_product->get_price().','.$product['qty'].','.$product['line_subtotal'].','.$url.';;';
				
			}

			$cart = rtrim($cart,";;");

			$nom_gen = 19 - strlen($order_id);

	        $invoice = $order_id.'_'.wp_generate_password($nom_gen, false);

	        

			$MERCHANTID = $this -> merchant_id;
			$MASTERMERCHANTID = $this -> master_merchant_id;
			$jualio_pg = $this -> password;
			
			$PAYMENTTYPE = 1;
			$CATEGORY = 'provide';
			$ItemTotalPrice = $order -> order_total;

			$data = $MASTERMERCHANTID.$invoice.$MERCHANTID.$ItemTotalPrice.$PAYMENTTYPE.$jualio_pg;
			$token = sha1($data);

			date_default_timezone_set('Asia/Jakarta');
			$datetime = date("YmdHi");

			
			if (session_status() == PHP_SESSION_NONE) { session_start(); }

	        $payu_args = array(
	          'INVOICE' => $invoice,
	          'CART' => $cart,
	          'MERCHANTID' => $this -> merchant_id,
	          'MASTERMERCHANTID' => $this -> master_merchant_id,
	          'AMOUNT' => $order -> order_total,
	          'CURRENCY' => 865,
	          'SESSIONID' => session_id(),
	          'TOKEN' => $token,
	          'PAYMENTTYPE' => $PAYMENTTYPE,
	          'DATETIME' => $datetime,
	          'CATEGORY' => $CATEGORY,
	          'NAME' => $order -> billing_first_name,
	          'EMAIL' => $order -> billing_email,
	          'PHONENO' => $order -> billing_phone,
	          //'SELECTEDCHANNEL' => 8,

	        );

	        $payu_args_array = array();
	        foreach($payu_args as $key => $value){
	          $payu_args_array[] = "<input type='text' name='$key' value='$value'/>";
	        }
	        return '<form action="'.$this -> liveurl.'" method="post" id="payu_payment_form">
	            ' . implode('', $payu_args_array) . '
	            <input type="submit" class="button-alt" id="submit_payu_payment_form" value="'.__('Pay via PayU', 'mrova').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'mrova').'</a>
	            <script type="text/javascript">
	          jQuery(function(){
	          jQuery("body").block(
	                  {
	                      message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'mrova').'",
	                          overlayCSS:
	                  {
	                      background: "#fff",
	                          opacity: 0.6
	              },
	              css: {
	                  padding:        20,
	                      textAlign:      "center",
	                      color:          "#555",
	                      border:         "3px solid #aaa",
	                      backgroundColor:"#fff",
	                      cursor:         "wait",
	                      lineHeight:"32px"
	              }
	              });
	              jQuery("#submit_payu_payment_form").click();});</script>
	                      </form>';


	    }
	    /**
	     * Process the payment and return the result
	     **/
	    // get_permalink(get_option('woocommerce_pay_page_id'))
	    function process_payment($order_id){
	        global $woocommerce;
	    	$order = new WC_Order( $order_id );
	        return array('result' => 'success', 'redirect' => add_query_arg('order',
	            $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
	        );
	    }

	    /**
	     * Check for valid payu server callback
	     **/
	    function check_payu_response2(){

	    	//wp_die('die' );
	        global $woocommerce;

	        if(isset($_REQUEST['txnid']) && isset($_REQUEST['mihpayid'])){
	            $order_id_time = $_REQUEST['txnid'];
	            $order_id = explode('_', $_REQUEST['txnid']);
	            $order_id = (int)$order_id[0];
	            if($order_id != ''){
	                try{
	                    $order = new WC_Order( $order_id );
	                    $merchant_id = $_REQUEST['key'];
	                    $amount = $_REQUEST['Amount'];
	                    $hash = $_REQUEST['hash'];

	                    $status = $_REQUEST['status'];
	                    $productinfo = "Order $order_id";
	                    echo $hash;
	                    echo "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}";
	                    $checkhash = hash('sha512', "{$this->salt}|$status|||||||||||{$order->billing_email}|{$order->billing_first_name}|$productinfo|{$order->order_total}|$order_id_time|{$this->merchant_id}");
	                    $transauthorised = false;
	                    if($order -> status !=='completed'){
	                        if($hash == $checkhash)
	                        {

	                          $status = strtolower($status);

	                            if($status=="success"){
	                                $transauthorised = true;
	                                $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
	                                $this -> msg['class'] = 'woocommerce_message';
	                                if($order -> status == 'processing'){

	                                }else{
	                                    $order -> payment_complete();
	                                    $order -> add_order_note('PayU payment successful<br/>Unnique Id from PayU: '.$_REQUEST['mihpayid']);
	                                    $order -> add_order_note($this->msg['message']);
	                                    $woocommerce -> cart -> empty_cart();
	                                }
	                            }else if($status=="pending"){
	                                $this -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
	                                $this -> msg['class'] = 'woocommerce_message woocommerce_message_info';
	                                $order -> add_order_note('PayU payment status is pending<br/>Unnique Id from PayU: '.$_REQUEST['mihpayid']);
	                                $order -> add_order_note($this->msg['message']);
	                                $order -> update_status('on-hold');
	                                $woocommerce -> cart -> empty_cart();
	                            }
	                            else{
	                                $this -> msg['class'] = 'woocommerce_error';
	                                $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
	                                $order -> add_order_note('Transaction Declined: '.$_REQUEST['Error']);
	                                //Here you need to put in the routines for a failed
	                                //transaction such as sending an email to customer
	                                //setting database status etc etc
	                            }
	                        }else{
	                            $this -> msg['class'] = 'error';
	                            $this -> msg['message'] = "Security Error. Illegal access detected";

	                            //Here you need to simply ignore this and dont need
	                            //to perform any operation in this condition
	                        }
	                        if($transauthorised==false){
	                            $order -> update_status('failed');
	                            $order -> add_order_note('Failed');
	                            $order -> add_order_note($this->msg['message']);
	                        }
	                        add_action('the_content', array(&$this, 'showMessage'));
	                    }}catch(Exception $e){
	                        // $errorOccurred = true;
	                        $msg = "Error";
	                    }

	            }



	        }

	    }

	    function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
	     // get all pages
	    function get_pages($title = false, $indent = true) {
	        $wp_pages = get_pages('sort_column=menu_order');
	        $page_list = array();
	        if ($title) $page_list[] = $title;
	        foreach ($wp_pages as $page) {
	            $prefix = '';
	            // show indented child pages?
	            if ($indent) {
	                $has_parent = $page->post_parent;
	                while($has_parent) {
	                    $prefix .=  ' - ';
	                    $next_page = get_page($has_parent);
	                    $has_parent = $next_page->post_parent;
	                }
	            }
	            // add to page list array array
	            $page_list[$page->ID] = $prefix . $page->post_title;
	        }
	        return $page_list;
	    }


	    
	    


	} // end class



	/**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_jualio_gateway($methods) {
        $methods[] = 'WC_Jualio_PG';

        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_jualio_gateway' );


    function callback_handler(){

    	global $woocommerce;

    	$wc_jualio = new WC_Jualio_PG;
    	// echo "<pre>";
    	// print_r($wc_jualio);
    	// echo "</pre>";
    	$jualio_MERCHANTID = $wc_jualio->merchant_id;
    	$jualio_MASTERMERCHANTID = $wc_jualio->master_merchant_id;
    	$jualio_pg = $wc_jualio->password;
    	$redirect_page_id = $wc_jualio->redirect_page_id;

    	if ($_GET['act'] == 'verify'){

			if (!empty($_POST)){
				$cek_post = json_encode($_POST);
				$cek_post = json_decode($cek_post);
				if (isset($cek_post->TOKEN)){
					$token = $cek_post->TOKEN;
					$AMOUNT = $cek_post->AMOUNT;
					$MERCHANTID = $cek_post->MERCHANTID;
					$PAYMENTTYPEID = $cek_post->PAYMENTTYPEID;
					$INVOICE = $cek_post->INVOICE;

					$MASTERMERCHANTID = $jualio_MASTERMERCHANTID;
					$AMOUNT = $AMOUNT;
					$MERCHANTID = $MERCHANTID;
					$jualio_PG = $jualio_pg;
					$PAYMENTTYPEID = $PAYMENTTYPEID;
					$INVOICE = $INVOICE;
					
					$data = $MASTERMERCHANTID.$AMOUNT.$MERCHANTID.$jualio_PG.$PAYMENTTYPEID.$INVOICE;
					$data2 = $MASTERMERCHANTID.'|'.$AMOUNT.'|'.$MERCHANTID.'|'.$jualio_PG.'|'.$PAYMENTTYPEID.'|'.$INVOICE;
					$cek_token = sha1($data);
					$_POST['cek_token'] = $cek_token;
					$_POST['data_token'] = $data;
					$_POST['data2_token'] = $data2;

					if ($token == $cek_token){
						echo "Continue";
						$cek = 'Continue';
					} else {
						echo "Stop";
						$cek = 'Stop3';
					}
					

				} else {
					echo "Stop";
					$cek = 'Stop2';
				}
				
			} else {
				$cek = 'Stop1';
				echo "Stop";
			}

			update_option( 'jualio-test-verify', json_encode($_POST) );
			update_option( 'jualio-test-verify2', $cek );

	    } elseif ($_GET['act'] == 'notify') {

	    	if (!empty($_POST)){
				$cek_post = $_POST;
				if (isset($cek_post['TOKEN'])){

					$token = $cek_post['TOKEN'];
					$CHANNEL = $cek_post['CHANNEL'];
					$UNIQUEID = $cek_post['UNIQUEID'];
					$RESULT = $cek_post['RESULT'];
					$TOTALAMOUNT = $cek_post['TOTALAMOUNT'];
					$APPROVALCODE = $cek_post['APPROVALCODE'];
					$RESPONSECODE = $cek_post['RESPONSECODE'];
					$RESPONSEMSG = $cek_post['RESPONSEMSG'];
					$BANKSOURCE = $cek_post['BANKSOURCE'];
					$QUANTITY = $cek_post['QUANTITY'];
					$AMOUNT = $cek_post['AMOUNT'];
					$UNPAIDFEE = $cek_post['UNPAIDFEE'];
					$FEE = $cek_post['FEE'];
					$INVOICE = $cek_post['INVOICE'];

					$data_customer = $cek_post['CUSTOMERDETAILS'];
					$data_customer = str_replace("\\", "", $data_customer);
					$data_customer2 = json_decode($data_customer);

					$EMAIL = $data_customer2->email;
					$mobileno = $data_customer2->mobileno;
					$name = $data_customer2->name;
					
					$MASTERMERCHANTID = $jualio_MASTERMERCHANTID;
					$AMOUNT = $AMOUNT;
					$jualio_PG = $jualio_pg;
					$INVOICE = $INVOICE;
					
					$data = $INVOICE.$CHANNEL.$UNIQUEID.$jualio_PG.$RESULT.$TOTALAMOUNT;
					$data2 = $INVOICE.'|'.$CHANNEL.'|'.$UNIQUEID.'|'.$jualio_PG.'|'.$RESULT.'|'.$TOTALAMOUNT.'|'.$EMAIL.'|'.$name;
					$cek_token = sha1($data);
					$_POST['cek_token'] = $cek_token;
					$_POST['data_token'] = $data;
					$_POST['data2_token'] = $data2;
					$_POST['session_aff'] = $_SESSION['aff_id'];

		            $order_id = explode('_', $INVOICE);
		            $order_id = (int)$order_id[0];
		            if($order_id != ''){

						if ($token == $cek_token){
							try{
			                    $order = new WC_Order( $order_id );
			                    $status = strtolower($RESULT);

			                    if($order -> status !=='completed'){

			                    	if($status=="success"){
		                                $transauthorised = true;
		                                $wc_jualio -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
		                                $wc_jualio -> msg['class'] = 'woocommerce_message';
		                                if($order -> status == 'processing'){

		                                }else{
		                                    $order -> payment_complete();
		                                    $order -> add_order_note('Jualio payment successful<br/>Unnique Id from Jualio: '.$UNIQUEID);
		                                    $order -> add_order_note($wc_jualio->msg['message']);
		                                    $woocommerce -> cart -> empty_cart();
		                                }
		                            }else if($status=="pending"){
		                                $wc_jualio -> msg['message'] = "Thank you for shopping with us. Right now your payment staus is pending, We will keep you posted regarding the status of your order through e-mail";
		                                $wc_jualio -> msg['class'] = 'woocommerce_message woocommerce_message_info';
		                                $order -> add_order_note('Jualio payment status is pending<br/>Unnique Id from Jualio: '.$UNIQUEID);
		                                $order -> add_order_note($wc_jualio->msg['message']);
		                                $order -> update_status('on-hold');
		                                $woocommerce -> cart -> empty_cart();
		                            }
		                            else{
		                                $wc_jualio -> msg['class'] = 'woocommerce_error';
		                                $wc_jualio -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
		                                $order -> add_order_note('Transaction Declined: '.$RESPONSEMSG);
		                                //Here you need to put in the routines for a failed
		                                //transaction such as sending an email to customer
		                                //setting database status etc etc
		                            }

			                    }
			                } catch(Exception $e){
							    // $errorOccurred = true;
							    $msg = "Error";
							}

							echo "Continue";
							$cek = "Continue";
						} else {
							$wc_jualio -> msg['class'] = 'error';
		                    $wc_jualio -> msg['message'] = "Security Error3. Illegal access detected";
							echo "Stop";
							$cek = "Stop3";
						}
					} else {
						$wc_jualio -> msg['class'] = 'error';
	                    $wc_jualio -> msg['message'] = "Security Error. Undifined order id";
						echo "Stop";
						$cek = "Stop4";
					}
					

				} else {
					$wc_jualio -> msg['class'] = 'error';
	                $wc_jualio -> msg['message'] = "Security Error2. Illegal access detected";
					echo "Stop";
					$cek = "Stop2";
				}
				
			} else {
				$wc_jualio -> msg['class'] = 'error';
	            $wc_jualio -> msg['message'] = "Security Error. Illegal access detected";
				echo "Stop";
				$cek = "Stop";
			}
			update_option( 'jualio-test-notify', json_encode($_POST) );
			update_option( 'jualio-test-notify2', $cek );
			update_option( 'jualio-test-notify-datacst', $data_customer );

	    	
	    } elseif ($_GET['act'] == 'result') {
	    	# code...
	    	$cek = $_POST['PAYSTATUS'];

			update_option( 'jualio-test-result', json_encode($_POST) );
			update_option( 'jualio-test-result-status', $cek );

			if ($cek == 'SUCCESS'){
				$result['type'] = 'success';
				$result['message'] = __('Transaction Success.', 'trees-id-payment');
				$wc_jualio -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
		        $wc_jualio -> msg['class'] = 'woocommerce_message';
			} else {
				$result['type'] = 'danger';
				$result['message'] = __('Transaction failed.', 'trees-id-payment');
				$wc_jualio -> msg['class'] = 'woocommerce_error';
                $wc_jualio -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
       
			}
			ajax_response($result,get_permalink( $redirect_page_id ));
	    }
    	//wp_die( 'aku' );
    	die();
    	//exit();
    	//return 'ada';
    }

    
    add_action( 'woocommerce_api_callback', 'callback_handler' );
    


} // end func init


/**
 * undocumented function
 *
 * @return void
 * @author 
 **/
function process_test()
{
	
	$order_id = 2;
	if (isset($_GET['order'])){
		$order_id = $_GET['order'];
	}
	$order = new WC_Order( $order_id );
	//echo "string2 : ".$nom_gen = 19 - strlen($order_id);

	$order_item = $order->get_items();

	//echo "Test : ".$order-> prices_include_tax;
	$cart = '';
	foreach ($order_item as $product) {
		echo "Test : ".$product['name']." | ".$product['line_subtotal']." | ".$product['qty']." <br>";
		$data_product = new WC_Product($product['product_id']);
		$url = get_the_guid ( $data_product->get_image_id() );
		if (empty($url)){
			$url = 'http://portal.trees.id/images/lots/00/000/073/636/Legok%20Awi%201.jpg';
		}

		$cart .= $product['name'].','.$data_product->get_price().','.$product['qty'].','.$product['line_subtotal'].','.$url.';;';
		
		//echo "|ini:".$data_product->get_image_id();

	}

	$cart = rtrim($cart,";;");
	echo "Cart : $cart";
	//$products->get_image_id()
	echo "<pre>";
	print_r($order_item);
	echo "</pre>";
	die();
}
add_action('wp_ajax_test', 'process_test');
add_action('wp_ajax_nopriv_test', 'process_test');

?>