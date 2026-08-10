<?php
/**
 * Plugin Name: WooCommerce Offline Credit Card Payment
 * Plugin URI: http://plugins.wrpsolution.com
 * Description: Woocomemrce payment gateway for accepting offline credit card payments
 * Version: 1.6.1
 * Author: Naresh Goyani
 * Author URI: http://plugins.wrpsolution.com
 * Requires at least: 4.0
 *
 * Text Domain: woo_offline_payment
 * Domain Path: /languages/
 */
 
if ( ! defined( 'ABSPATH' ) ) exit; 

// Global Contant
define('WOO_OFFLINE_PATH', plugin_dir_path( __FILE__ ));
define('WOO_OFFLINE_URL', plugins_url('/', __FILE__));

load_textdomain('woo_offline_payment', WOO_OFFLINE_PATH . 'languages/' . get_locale() . '.mo');

$global_options = json_encode(array(
	'name'=>__('WC Offline Credit Card Payment','woo_offline_payment'),
	'slug'=>'woo_offline_payment',
	'domain'=>'woo_offline_payment',
	'version'=>'1.0.0',
	'wc_version'=>'2.6.8',
	'wp_version'=>'4.6'
));

define('WOO_OFFLINE_OPTIONS',$global_options);

if ( ! class_exists( 'wc_variations_layouts' ) ) :
	
	/* Check Woocommerce Active OR not */
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ):
		require_once( WOO_OFFLINE_PATH . 'encryption-manager.php' );
		class WooOffline_Credit_Card_Payment
		{
			
			public $version = '1.0';	
			protected static $_instance = null;
			
			function __construct()
			{
				add_action( 'plugins_loaded', array($this, 'init_gateway') );
				add_filter( 'woocommerce_payment_gateways', array($this, 'register_gateway') );
				add_action( 'add_meta_boxes', array( $this, 'wc_offline_credit_card_metabox' ) );
				add_action( 'wp_ajax_wc_offline_decrypt_card_data', array( $this, 'wc_offline_decrypt_card_data' ) );
				add_action( 'wp_ajax_wc_offline_delete_credit_card', array( $this, 'wc_offline_delete_credit_card' ) );

				// Add decryption password field to user profile
				add_action( 'show_user_profile', array($this, 'user_decrypt_pwd_field') );
				add_action( 'personal_options_update', array($this, 'save_user_decrypt_pwd_field') );
				
				/* Setting Link */
				add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this,'offline_credit_card_setting_link') );
			}

			//Ensures only one instance of our plugin is loaded or can be loaded.	 
			public static function instance() 
			{
				if ( is_null( self::$_instance ) ) 
				{
					self::$_instance = new self();
				}
				return self::$_instance;
			}

			// Cloning is forbidden.
			public function __clone() 
			{
				_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woo_offline_payment' ), '1.0' );
			}

			// Unserializing instances of this class is forbidden.
			public function __wakeup() 
			{
				_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woo_offline_payment' ), '1.0' );
			}

			// hook gateway class to WC
			public function init_gateway()
			{
				if( class_exists( 'WooOffline_Payment_Gateway_Init' ) ) return;

				// Load gateway class
				require_once( WOO_OFFLINE_PATH . 'class-offline-credit-card-payment.php' );
			}

			// Make WC aware of our payment gateway
			public function register_gateway( $methods ) 
			{
				$methods[] = 'WooOffline_Payment_Gateway_Init'; 
				return $methods;
			}

			public function wc_offline_credit_card_metabox()
			{
				global $post;
				if($post->post_type=="shop_order"):  
					$order = new WC_Order( $post->ID );
					// add our metabox only if order paid using Offline Credit Card Method
					if( 'woo_offline_credit_card_payment_method' == get_post_meta($order->ID,'_payment_method',true)) :
						add_meta_box( 
							'woo_offline_payment-credit-card-details', 
							__('WC Offline Credit Card Details', 'woo_offline_payment'), 
							array($this, 'get_offline_credit_cardx_detial'), 
							'shop_order'
						);
					endif;
				endif;
			}
			
			public function offline_credit_card_setting_link ( $links ) {
			 $settng_link = array(
				 '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=woo_offline_credit_card_payment_method' ) . '">'.__('Setting','wcvw').'</a>',
				 '<a href="' . admin_url( 'profile.php' ) . '">'.__('Password Setting','wcvw').'</a>',
				 );
				return array_merge($links, $settng_link );
			}

			public function get_offline_credit_cardx_detial( $post )
			{
				$order = new WC_Order( $post );
				
				if( $order ) :
				
				   $woocommerce_offline_setting = get_option('woocommerce_woo_offline_credit_card_payment_method_settings','no');
				   $card_holder_name = 'Card Holder Name';
				   $card_number = 'Card Number';
				   $expiry_date  = 'Expiry (MM/YY)';
				   $cvv_no = 'Card Code';
				   $card_type = 'Card Type';
				   if($woocommerce_offline_setting!="no"):
					   if($woocommerce_offline_setting['ch_text']!=""):
							$card_holder_name = $woocommerce_offline_setting['ch_text'];
					   endif;
					   
					   if($woocommerce_offline_setting['ca_nobmber']!=""):
							$card_number = $woocommerce_offline_setting['ca_nobmber'];
					   endif;
					   
					    if($woocommerce_offline_setting['c_type']!=""):
							$card_number = $woocommerce_offline_setting['c_type'];
					   endif;
					   
					   if($woocommerce_offline_setting['ce_date']!=""):
							$expiry_date  = $woocommerce_offline_setting['ce_date'];
					   endif;
					   
					   if($woocommerce_offline_setting['cc_label']!=""):
							$cvv_no = $woocommerce_offline_setting['cc_label'];
					   endif;
				   endif;
	   
			?>
			<div id="wc-offline-card-details">
				<span class="wc_valid_errors"></span>
				<div class="wc_description_credit_card_form">			
					<input type="password" name="description_password" id="description_password" class="text-input" placeholder="<?php echo _e('Decryption Password','woo_offline_payment') ?>" autocomplete="off">			
					<input type="button" name="decrypt_encrypted_data" id="decrypt_encrypted_data" class="button button-primary" value="<?php echo _e('Decrypt Credit Card Details','woo_offline_payment') ?>" data-order-id="<?php echo $order->ID; ?>">			
					<input type="button" onclick="" name="wc_offline_delete_credit_card" id="wc_offline_delete_credit_card" class="button button-primary" value="<?php echo _e('Delete credit card','woo_offline_payment') ?>" data-order-id="<?php echo $order->ID; ?>">			
				</div>
				<table class="widefat striped description_password_table">
					<tr>
						<td><strong><?php _e($card_holder_name,'woo_offline_payment'); ?></strong></td>
						<td>:</td>
						<td class="wc_card_name"><?php echo get_post_meta($order->ID,'_card_holder',true)!="" ? get_post_meta($order->ID,'_card_holder',true) : ''; ?></td>				
					</tr>
					<tr>
						<td><strong><?php _e($card_number,'woo_offline_payment'); ?></strong></td>
						<td>:</td>
						<td class="wc_card_no"><?php echo get_post_meta($order->ID,'_card_no_plain',true)? get_post_meta($order->ID,'_card_no_plain',true) : ''; ?></td>
					</tr>
					<tr>
						<td><strong><?php _e($card_type,'woo_offline_payment'); ?></strong></td>
						<td>:</td>
						<td class="wc_card_type"><?php echo get_post_meta($order->ID,'_card_type',true) ? get_post_meta($order->ID,'_card_type',true) : ''; ?></td>
					</tr>
					<tr>
						<td><strong><?php _e($expiry_date,'woo_offline_payment'); ?></strong></td>
						<td>:</td>
						<td class="wc_card_exp"><?php echo '**/**'; ?></td>
					</tr>
					<tr>
						<td><strong><?php _e($cvv_no,'woo_offline_payment'); ?></strong></td>
						<td>:</td>
						<td class="wc_card_cvv"><?php echo '***'; ?></td>
					</tr>	
							
				</table>
			</div>
			<style type="text/css">
				#woo_offline_payment-credit-card-details{ box-shadow:0px 0px 5px 5px #ddd; }
				#wc-offline-card-details { margin-bottom: 1em;  }
				#wc-offline-card-details .errors { color: red; }
				#wc-offline-card-details .wc_description_credit_card_form { margin-bottom: 1em; }
				#woo_offline_payment-credit-card-details h2{ background:#f9f9f9 }
				#wc-offline-card-details .wc_description_credit_card_form .text-input { padding: 6px 10px; width: 30%; }
				#wc-offline-card-details .wc_description_credit_card_form .button {  height: auto !important;padding: 2px 15px; }
				.description_password_table tr td:nth-child(1){ width:30%; }
				.description_password_table tr td:nth-child(2){ width:20%; }
				.description_password_table tr td{ color:#444 !important; }
				.wc_valid_errors{ color:red; }
			</style>
			
			<script type="text/javascript">
				(function($){

					$(document).ready(function(){
						
						// Decrypt data
						$('#decrypt_encrypted_data').click(function(){
							$('.wc_valid_errors').html('');
							var req = {
								"action" 	: "wc_offline_decrypt_card_data",
								"order_id" 	: $(this).data('order-id'),
								"pwd" 		: $('#description_password').val(),
								"nonce"		: "<?php echo wp_create_nonce( 'decrypt_cc' ); ?>"
							};

							$('#wc-offline-card-details').block({
							overlayCSS: { backgroundColor:'#fff',opacity:0.5,cursor:'wait'},
							css: {color:'#999',border:'none',backgroundColor:'transparent',fontSize: '22px', width: '35%;'},
							message: ''
							});

							$.post( ajaxurl, req, function(res){

								$('#wc-offline-card-details').unblock();

								if( res.success )
								{
									$('.wc_card_no').text( res.data.wc_card_no );
									$('.wc_card_exp').text( res.data.wc_card_exp );
									$('.wc_card_cvv').text( res.data.wc_card_cvc );
								}
								else
								{
									$('.wc_valid_errors').html( res.data.msg );
								}
							}, 'json');

						});
						
						
						/* Delete Credit Card */
						$('#wc_offline_delete_credit_card').click(function(){
							if( confirm('Are you sure you want to delete credit card ?')){
								$('.wc_valid_errors').html('');
								var req = {
									"action" 	: "wc_offline_delete_credit_card",
									"order_id" 	: $(this).data('order-id'),
								};

								$('#wc-offline-card-details').block({
								overlayCSS: { backgroundColor:'#fff',opacity:0.5,cursor:'wait'},
								css: {color:'#999',border:'none',backgroundColor:'transparent',fontSize: '22px', width: '35%;'},
								message: ''
								});

								$.post( ajaxurl, req, function(res){

									$('#wc-offline-card-details').unblock();

									if( res.success )
									{
										$('.wc_valid_errors').html( res.data.msg );
									}
									else
									{
										$('.wc_valid_errors').html( res.data.msg );
									}
								}, 'json');
							}
						});
						
					});

				})(jQuery);
			</script>
			
			
			<?php
				endif;
			}
			
			public function wc_offline_delete_credit_card(){
				if(isset($_POST['order_id']) && $_POST['order_id']!=""){
					$order_id = wc_clean($_POST['order_id']);
					update_post_meta( $order_id, '_card_holder',  '-----');
					update_post_meta( $order_id, '_card_number',  '-----');
					update_post_meta( $order_id, '_card_type',  '-----');
					update_post_meta( $order_id, '_card_expiry',  '-----');
					update_post_meta( $order_id, '_card_cvc',  '-----');
					update_post_meta( $order_id, '_card_no_plain',  '-----' );
					wp_send_json_error( array('msg' => __('<span style="color:green">Credit card deleted !</span>','woo_offline_payment' ) ));
				}
			}
			
			public function wc_offline_decrypt_card_data()
			{ 
				if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'decrypt_cc' ) ) 
				{
					$order_id 	= wc_clean($_POST['order_id']);
					$order 		= new WC_Order( $order_id );
					$pwd 		= isset($_POST['pwd']) ? $_POST['pwd'] : '';
					
					$e = SecureEncryption::instance();
					
					if( ! $pwd )
					{
						wp_send_json_error( array('msg' => __('Password is required.','woo_offline_payment' ) ));
					}

					if( ! $order )
					{
						wp_send_json_error( array('msg' => __('Invalid order.','woo_offline_payment') ) );
					}

					// Check user password against
					$user_id = get_current_user_id();

					if( 0 == $user_id || ( ! current_user_can( 'administrator' ) && !current_user_can( 'shop_manager' ) ) )
					{
						wp_send_json_error( array('msg' => __('You are not allowed to decrypt data.','woo_offline_payment') ) );
					}

					$user_decrypt_pwd = $e->wc_offline_decrypt( get_option( '_decrypt_pwd') );
					
					if( '' == $user_decrypt_pwd )
					{
						wp_send_json_error( array('msg' => __('You have not set decryption password. To decrypt data you must set password first from your profile.','woo_offline_payment') ) );
					}

					if( $pwd != $user_decrypt_pwd )
					{
						wp_send_json_error( array('msg' => __('Incorrect password.' ,'woo_offline_payment')) );
					}			

					// Include Encryption class
					
					$cc = array();
					$cc['wc_card_no']		= $e->wc_offline_decrypt( $order->card_number );
					$cc['wc_card_exp']		= $e->wc_offline_decrypt( $order->card_expiry );
					$cc['wc_card_cvc']		= $e->wc_offline_decrypt( $order->card_cvc );
					if($cc['wc_card_no']==false){ $cc['wc_card_no'] = '-----'; }
					if($cc['wc_card_exp']==false){ $cc['wc_card_exp'] = '-----'; }
					if($cc['wc_card_cvc']==false){ $cc['wc_card_cvc'] = '-----'; }
					if( !empty($cc) )
					{
						wp_send_json_success( $cc );
					}
					else
					{
						wp_send_json_error( array('msg' => __('Error occured while decrypting.','woo_offline_payment')) );
					}

				} // end if

				wp_send_json_error( array('msg' => __('Invalid Request.','woo_offline_payment')) );
			}

			public function user_decrypt_pwd_field( $user )
			{
				if ( !is_super_admin() ){  return; }
				$d_pwd = $user->_decrypt_pwd ? $user->_decrypt_pwd : '';
				$e = SecureEncryption::instance();
			?>
			<div style="background:#fff; border-radius:5px; padding:5px 15px; box-shadow:0px 0px 8px 3px #ddd;">
				<h3 style="border-bottom: 1px solid #eee; margin-bottom: 0; padding-bottom: 15px;">Decryption Credit Card Details Authantication On Each Order</h3>
				<table class="form-table" style="margin-top:0;">
					<tr>
						<th><label for="twitter"><?php _e('Password','woo_offline_payment') ?></label></th>
						<td>
							<input type="password" name="_decrypt_pwd" data-encrpted="<?php echo $d_pwd; ?>" data-descrypted="<?php echo $e->wc_offline_decrypt( $d_pwd ); ?>"  id="_decrypt_pwd" class="regular-text" value="<?php echo $e->wc_offline_decrypt( $d_pwd );; ?>"><a href="javascript:void(0)" class="button button-secondary show-hide">Show</a><br>
							<span class="description"><?php _e('This password will be used decrypting credit card data on order.','woo_offline_payment'); ?></span>
						</td>
					</tr>
				</table>
			</div>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					jQuery('.show-hide').click(function() {
						var $this = $(this);
						var input = $('#_decrypt_pwd');
						if( $this.hasClass('shown') )
						{
							//input.attr('value',input.attr('data-encrpted'));
							input.attr( 'type', 'password' );
							$this.text( 'Show' );
							$this.removeClass( 'shown' );
						}
						else
						{
							//input.attr('value',input.attr('data-descrypted'));
							input.attr( 'type', 'text' );
							$this.text( 'Hide' );
							$this.addClass( 'shown' );
						}
					});
				});
			</script>
			<?php
			}

			public function save_user_decrypt_pwd_field( $user_id )
			{
				if ( !current_user_can( 'edit_user', $user_id ) )
				return false;
				
				$e = SecureEncryption::instance();
				
				update_usermeta( $user_id, '_decrypt_pwd', $e->wc_offline_encrypt($_POST['_decrypt_pwd']) );
				update_option( '_decrypt_pwd', $e->wc_offline_encrypt($_POST['_decrypt_pwd']) );
			}


		}

		// Main plugin instance
		function woo_offline_payment()
		{
			return WooOffline_Credit_Card_Payment::instance();
		}

		woo_offline_payment();
	else:
		/* Add Admin Notice If woocomnmece not activated */
		function wc_offline_payment_gateway_admin_notice() { 
			$wcvl_options = json_decode(WOO_OFFLINE_OPTIONS); 
			$class = 'notice notice-error';
			$message = __( '<b>'.$wcvl_options->name.'</b> requires Woocommerce to be installed & activated!', $wcvl_options->domain );

			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
		}
		add_action( 'admin_notices', 'wc_offline_payment_gateway_admin_notice' );
	endif;
endif;
