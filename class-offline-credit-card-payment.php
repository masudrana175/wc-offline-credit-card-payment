<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

class WooOffline_Payment_Gateway_Init extends WC_Payment_Gateway
{
	public function __construct() 
	{
		$this->id = "woo_offline_credit_card_payment_method";
		$this->method_title = __( "WC Offline Credit Card Payment", 'woo_offline_payment' );
		$this->method_description = __( "Pay via Offline Credit Card.", 'woo_offline_payment' ); 
		$this->title = __( "WC Offline Credit Card Payment", 'woo_offline_payment' );
		$this->icon = null;
		$this->has_fields = true;
		$this->wc_offline_generate_form_fields();
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) 
		{
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		//add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) 
		{			
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ),9999 );
		}

		// Add credit card holder name field, since it's not present by default
		add_action( 'woocommerce_credit_card_form_start', array($this, 'card_holder_name_field') );
	}
	
	/* Save payment method settings */
	public function process_admin_options(){
		$e = SecureEncryption::instance();
		$this->init_settings();

        $post_data = $this->get_post_data();
        //print_r($_REQUEST); die;

        foreach ( $this->get_form_fields() as $key => $field ) {
            if ( 'title' !== $this->get_field_type( $field ) ) {
                try {
                    if($key=='en_pass'){
						$this->settings[ $key ] = $e->wc_offline_encrypt($this->get_field_value( $key, $field, $post_data ));
					}else{
						$this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
					}
                } catch ( Exception $e ) {
                    $this->add_error( $e->getMessage() );
                }
            }
        }
        update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
		
	}

	public function card_holder_name_field($cc_form)
	{
		echo '<p class="form-row">
            <label for="' . esc_attr( $this->id ) . '-card-holder-name">' . __( 'Card Holder Name', 'woo_offline_payment' ) . ' <span class="required">*</span></label>
            <input id="' . esc_attr( $this->id ) . '-card-holder-name" class="input-text" type="text" autocomplete="off" placeholder="' . esc_attr__( 'Card Holder Name', 'woo_offline_payment' ) . '" name="' . esc_attr( $this->id ) . '-card-holder-name" style="height:40px;padding:8px;" />
        </p>';
	}

	// Payment Form Fields
	public function payment_fields() 
	{
        if ( $description = $this->get_description() ) 
        {
            echo wpautop( wptexturize( __($description,'woo_offline_payment') ) );
        }

        // Show Woocommerce's default credit card form
        $cc_form 			= new WC_Payment_Gateway_CC;
	    $cc_form->id 		= $this->id;
	    $cc_form->supports 	= $this->supports;
	   /* $cc_form->form();*/
	   wp_enqueue_script( 'wc-credit-card-form' );
	    
	   $woocommerce_offline_setting = get_option('woocommerce_woo_offline_credit_card_payment_method_settings','no');
	   $card_holder_name = __('Card Holder Name','woo_offline_payment');
	   $card_number = __('Card Number','woo_offline_payment');
	   $card_type = __('Card Type','woo_offline_payment');
	   $expiry_date  = __('Expiry (MM/YY)','woo_offline_payment');
	   $cvv_no = __('Card Code','woo_offline_payment');
	   if($woocommerce_offline_setting!="no"):
		   if($woocommerce_offline_setting['ch_text']!=""):
				$card_holder_name = $woocommerce_offline_setting['ch_text'];
		   endif;
		   
		   if($woocommerce_offline_setting['ca_nobmber']!=""):
				$card_number = $woocommerce_offline_setting['ca_nobmber'];
		   endif;
		   
		   if($woocommerce_offline_setting['c_type']!=""):
				$card_type = $woocommerce_offline_setting['c_type'];
		   endif;
		   
		   if($woocommerce_offline_setting['ce_date']!=""):
				$expiry_date  = $woocommerce_offline_setting['ce_date'];
		   endif;
		   
		   if($woocommerce_offline_setting['cc_label']!=""):
				$cvv_no = $woocommerce_offline_setting['cc_label'];
		   endif;
	   endif;
	   ?>
			<fieldset id="wc-woo_offline_credit_card_payment_method-cc-form" class="wc-credit-card-form wc-payment-form">
				<p class="form-row woocommerce-validated">
					<label for="woo_offline_credit_card_payment_method-card-holder-name"><?php  _e($card_holder_name,'woo_offline_payment'); ?><span class="required">*</span></label>
					<input id="woo_offline_credit_card_payment_method-card-holder-name" class="input-text" autocomplete="off" placeholder="<?php _e('Card Holder Name','woo_offline_payment'); ?>" name="woo_offline_credit_card_payment_method-card-holder-name" style="height:40px;padding:8px;" type="text">
					<span class="wc_offline_pg_error"></span>
				</p>
				<p class="form-row form-row-first">
					<label for="woo_offline_credit_card_payment_method-card-number"><?php  _e($card_number,'woo_offline_payment'); ?><span class="required">*</span></label>
					<input id="woo_offline_credit_card_payment_method-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" placeholder="•••• •••• •••• ••••" name="woo_offline_credit_card_payment_method-card-number" type="tel">
					<span class="wc_offline_pg_error"></span>
				</p>
				<p class="form-row form-row-last">
					<label for="woo_offline_credit_card_payment_method-card-type"><?php  _e($card_type,'woo_offline_payment'); ?><span class="required">*</span></label>
					<select style="width:100%;" name="woo_offline_credit_card_payment_method-card-type" id="woo_offline_credit_card_payment_method-card-type" class="woo_offline_credit_card_payment_method-card-type">
						<?php 
							$card_types_array= array(
								"American Express"=>__('American Express','woo_offline_payment'),
								//"Diners Club Carte Blanche"=>__('Diners Club Carte Blanche','woo_offline_payment'),
								//"Diners Club"=>__('Diners Club','woo_offline_payment'),
								//"Discover"=>__('Discover','woo_offline_payment'),
								//"Diners Club Enroute"=>__('Diners Club Enroute','woo_offline_payment'),
								//"JCB"=>__('JCB','woo_offline_payment'),
								//"Maestro"=>__('Maestro','woo_offline_payment'),
								"MasterCard"=>__('MasterCard','woo_offline_payment'),
								//"Solo"=>__('Solo','woo_offline_payment'),
								//"Switch"=>__('Switch','woo_offline_payment'),
								"VISA"=>__('VISA','woo_offline_payment'),
							   //"VISA Electron"=>__('VISA Electron','woo_offline_payment'),
								//"LaserCard"=>__('LaserCard','woo_offline_payment'),
							);
						 ?>
						<option value=""><?php _e('Select Card Type','woo_offline_payment'); ?></option>
						<?php 
							foreach($card_types_array as $key=>$single_Card):
								?><option value="<?php echo $key; ?>"><?php _e($single_Card); ?></option><?php
							endforeach;
						 ?>
					</select>
					<div class="wc_offline_cart_images"></div>
					<span class="wc_offline_pg_error"></span>
				</p>
				<p class="form-row form-row-first">
					<label for="woo_offline_credit_card_payment_method-card-expiry"><?php  _e($expiry_date,'woo_offline_payment'); ?><span class="required">*</span></label>
					<input id="woo_offline_credit_card_payment_method-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" placeholder="<?php _e('MM / YY','woo_offline_payment'); ?>" name="woo_offline_credit_card_payment_method-card-expiry" type="tel">
					<span class="wc_offline_pg_error"></span>
				</p>
				<p class="form-row form-row-last">
					<label for="woo_offline_credit_card_payment_method-card-cvc"><?php  _e($cvv_no,'woo_offline_payment'); ?><span class="required">*</span></label>
					<input id="woo_offline_credit_card_payment_method-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" maxlength="4" placeholder="<?php _e('CVC','woo_offline_payment'); ?>" name="woo_offline_credit_card_payment_method-card-cvc" style="width:100px" type="tel">
					<span class="wc_offline_pg_error"></span>
				</p>
				<div class="clear"></div>
			</fieldset>
			
			<style>
				.payment_box.payment_method_woo_offline_credit_card_payment_method > p {  margin-bottom: 0; }
				#wc-woo_offline_credit_card_payment_method-cc-form {  border-top: 1px solid #f1f1f1 !important;  padding-top: 5px !important;}
				#wc-woo_offline_credit_card_payment_method-cc-form > p{ padding-left:0px; }
				#wc-woo_offline_credit_card_payment_method-cc-form > p.form-row-first{ clear:both; overflow:hidden; }
				li .payment_method_woo_offline_credit_card_payment_method{ box-shadow: 0 0 8px 2px #f9f9f9; }
				#wc-woo_offline_credit_card_payment_method-cc-form input {  background: #fff none repeat scroll 0 0 !important;  border-color: #ccc !important;  border-radius: 5px !important;  color: #000 !important;  font-size: 15px !important;  font-weight: bold !important;}
				.payment_box.payment_method_woo_offline_credit_card_payment_method p { color: #000; }
				.wc_offline_pg_error{ color:red; font-style:italic; display:block; }
				.wc_offline_pg_error .required{ display:none; }
				.wc_payment_method.payment_method_woo_offline_credit_card_payment_method .wc_offline_cart_images {	position: absolute;	right: 5px;	bottom: 5px;}
			</style>
			<script>
				jQuery(function($){
					<?php if($woocommerce_offline_setting['rt_valid']=='yes'): ?>
						var error_flag = false;
						$(document).on('click','#place_order',function(){
							var payment_method_type = $(document).find('#payment input[name="payment_method"]:checked').val();
							if(payment_method_type == 'woo_offline_credit_card_payment_method'){
								var return_type=true;
								$(document).find('#wc-woo_offline_credit_card_payment_method-cc-form input,#wc-woo_offline_credit_card_payment_method-cc-form select').each(function(){
									var label_val = $(this).closest('p').find('label').html();
									if($(this).val()==""){
										return_type = false;
										$(this).closest('p').find('.wc_offline_pg_error').html(label_val+' is required.')
									}else if($(this).attr('name')=='woo_offline_credit_card_payment_method-card-holder-name' && isUpperCase($(this).val())==false){
										return_type = false;
										$(this).closest('p').find('.wc_offline_pg_error').html(label_val+' must be uppercase.')
									}else{
										$(this).closest('p').find('.wc_offline_pg_error').html('')
									}
								});
								return return_type;
							}
						});
						
						$(document).on('change','#wc-woo_offline_credit_card_payment_method-cc-form select',function(){
							wc_offline_cc_form_validate($(this));
						});
						
						$(document).on('keyup','#wc-woo_offline_credit_card_payment_method-cc-form input',function(){
							wc_offline_cc_form_validate($(this));
						});
						
						function wc_offline_cc_form_validate($this_obj){
							var label_val = $this_obj.closest('p').find('label').html();
							if($this_obj.val()==""){
								return_type = false;
								$this_obj.closest('p').find('.wc_offline_pg_error').html(label_val+' is required.')
							}else if($this_obj.attr('name')=='woo_offline_credit_card_payment_method-card-holder-name' && isUpperCase($this_obj.val())==false){
								return_type = false;
								$this_obj.closest('p').find('.wc_offline_pg_error').html(label_val+' must be uppercase.')
							}else{ 
								if($this_obj.attr('name')=='woo_offline_credit_card_payment_method-card-number'){
									var return_valid_card_name = GetCardType($this_obj.val());
									if(return_valid_card_name!=""){
										$(document).find('#woo_offline_credit_card_payment_method-card-type option[value="'+return_valid_card_name+'"]').attr('selected','selected');
										var card_src = return_valid_card_name.replace(' ','-');
										var plugin_path = '<?php echo plugin_dir_url( __FILE__ ); ?>';
										card_src = card_src.toLowerCase();
										$('.wc_offline_cart_images').html('<img src="'+ (plugin_path+'cards/'+card_src) +'.png">');
									}
								}
								$this_obj.closest('p').find('.wc_offline_pg_error').html('')
							}
						}					
						
						$(document).on('change','#woo_offline_credit_card_payment_method-card-type',function(){
							var return_valid_card_name = $(this).val();
							var card_src = return_valid_card_name.replace(' ','-');
							var plugin_path = '<?php echo plugin_dir_url( __FILE__ ); ?>';
							card_src = card_src.toLowerCase();
							if(return_valid_card_name!=""){
								$('.wc_offline_cart_images').html('<img src="'+ (plugin_path+'cards/'+card_src) +'.png">');
							}else{
								$('.wc_offline_cart_images').html('');
							}
						});
						
						function isUpperCase(str) {
							//return str === str.toUpperCase();
							return true;
						}
						
						function GetCardType(number)
						{ 
							var re = new RegExp("^3[47]");
							if (number.match(re) != null){ 	return "American Express"; }						
							re = new RegExp("^30[0-5]");
							if (number.match(re) != null){	return "Diners Club Carte Blanche"; }
							re = new RegExp("^36");
							if (number.match(re) != null){	return "Diners Club"; }
							re = new RegExp("^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)");
							if (number.match(re) != null){	return "Discover"; }
							re = new RegExp("^35(2[89]|[3-8][0-9])");
							if (number.match(re) != null){	return "JCB"; }
							re = new RegExp("^5[1-5]");
							if (number.match(re) != null){ 	return "MasterCard"; }
							re = new RegExp("^4");
							if (number.match(re) != null){ return "VISA"; }
							re = new RegExp("^(4026|417500|4508|4844|491(3|7))");
							if (number.match(re) != null){	return "VISA Electron"; }
							return "";
						}
					<?php endif; ?>
				});
			</script>
	    <?php
    }

	// Build the administration fields for this specific Gateway
	public function wc_offline_generate_form_fields() 
	{		
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'woo_offline_payment' ),
				'label'		=> __( 'Enable this payment gateway', 'woo_offline_payment' ),
				'type'		=> 'checkbox',
				'desc_tip'	=> __( 'Enable this payment gateway.', 'woo_offline_payment' ),
				'default'	=> 'no',
			),
			'rt_valid' => array(
				'title'		=> __( 'Enable/Disable Real Time jQuery Validation', 'woo_offline_payment' ),
				'label'		=> __( 'Enable/Disable Real Time jQuery Validation', 'woo_offline_payment' ),
				'type'		=> 'checkbox',
				'desc_tip'	=> __( 'Enable/Disable Real Time jQuery Validation.', 'woo_offline_payment' ),
				'default'	=> 'yes',
			),
			'title' => array(
				'title'		=> __( 'Title', 'woo_offline_payment' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'woo_offline_payment' ),
				'default'	=> __( 'Offline Credit Card', 'woo_offline_payment' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'woo_offline_payment' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'woo_offline_payment' ),
				'default'	=> __( 'Pay via Offline Credit Card.', 'woo_offline_payment' ),
				'css'		=> 'max-width:350px;'
			),
			'def_status' => array(
				'title'		=> __( 'Default order status', 'woo_offline_payment' ),
				'type'		=> 'select',
				'desc_tip'	=> __( 'This order staus is by default for placing order.', 'woo_offline_payment' ),
				'default'	=> __( 'on-hold', 'woo_offline_payment' ),
				'css'		=> 'max-width:350px;',
				'options'       => array(
					'on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
					'pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
					'processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
					'completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
					'cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
					'refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
					'failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
				)
			),
			/*'en_pass' => array(
				'title'		=> __( 'Password for decrypt credit card', 'woo_offline_payment' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This option is used to descrpt credit card details on order.', 'woo_offline_payment' ),
				'default'	=> __( 'Admin@123', 'woo_offline_payment' ),
			),*/
			'ch_text' => array(
				'title'		=> __( 'Card Holder Name', 'woo_offline_payment' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This text is display on card holder name label on checkout page.', 'woo_offline_payment' ),
				'default'	=> __( 'Card Holder Name', 'woo_offline_payment' ),
			),
			'ca_nobmber' => array(
				'title'		=> __( 'Card Account Number', 'woo_offline_payment' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This text is display on account nomber label on checkout page.', 'woo_offline_payment' ),
				'default'	=> __( 'Card Account Number', 'woo_offline_payment' ),
			),
			'c_type' => array(
				'title'		=> __( 'Card Type', 'woo_offline_payment' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This text is display on account nomber label on checkout page.', 'woo_offline_payment' ),
				'default'	=> __( 'Card Type', 'woo_offline_payment' ),
			),
			'ce_date' => array(
				'title'		=> __( 'Card Holder Text', 'woo_offline_payment' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This text is display on expiry date label on checkout page.', 'woo_offline_payment' ),
				'default'	=> __( 'Card Expiry Date', 'woo_offline_payment' ),
			),
			'cc_label' => array(
				'title'		=> __( 'Card CVC Number', 'woo_offline_payment' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This text is display on card cvc label on checkout page.', 'woo_offline_payment' ),
				'default'	=> __( 'Card CVC Number', 'woo_offline_payment' ),
			),
		);		
	}

	// Check if we are forcing SSL on checkout pages
	public function do_ssl_check() 
	{
		if( $this->enabled == "yes" ) 
		{
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) 
			{
				echo '<div class="error"><p>' . sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href='%s'>forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) .'</p></div>';
			}
		}		
	}

	// Validating Payment Fields
	public function validate_fields()
	{
		$prefix = esc_attr( $this->id );
		$error = false;

		if( empty($_POST[ $prefix . '-card-holder-name']) )
		{
			wc_add_notice( __('<strong>Card Holder Name</strong> is required.', 'woo_offline_payment'), 'error' );
			$error = true;
		}
		if( empty($_POST[ $prefix . '-card-number']) )
		{
			wc_add_notice( __('<strong>Card Number</strong> is required.', 'woo_offline_payment') , 'error' );
			$error = true;		
		}elseif(strlen($_POST[ $prefix . '-card-number']) <= 10 || strlen($_POST[ $prefix . '-card-number']) > 20){
			wc_add_notice( __('<strong>Card Number</strong> length is not valid.', 'woo_offline_payment') , 'error' );
			$error = true;
		}elseif($this->checkCreditCard($_POST[ $prefix . '-card-number'],$_POST[ $prefix . '-card-type'])==false){
			wc_add_notice( __('<strong>Card Number</strong> is wrong, Please enter valid credit card number.', 'woo_offline_payment') , 'error' );
			$error = true;
	    }
		if( empty($_POST[ $prefix . '-card-expiry']) )
		{
			wc_add_notice( __('<strong>Card Expiry</strong> is required.', 'woo_offline_payment') , 'error' );
			$error = true;
		}
		if( empty($_POST[ $prefix . '-card-type']) )
		{
			wc_add_notice( __('<strong>Card Type</strong> is required.', 'woo_offline_payment') , 'error' );
			$error = true;
		}
		if( empty($_POST[ $prefix . '-card-cvc']) )
		{
			wc_add_notice( __('<strong>Card CVV</strong> is required.', 'woo_offline_payment') , 'error' );
			$error = true;
		}
		return $error;
	}
	
	
	public function process_payment( $order_id )
	{
		$order = new WC_Order( $order_id );

		// Mark as pending as payment is not done yet
		//$order->update_status( 'on-hold', __( 'Order placed using offline credit card.', 'woo_offline_payment' ) );
		
		$woocommerce_offline_setting = get_option('woocommerce_woo_offline_credit_card_payment_method_settings','no');
		
		$default_status = 'on-hold';
		if($woocommerce_offline_setting!="no" && isset($woocommerce_offline_setting['def_status'])){
			$default_status = $woocommerce_offline_setting['def_status'];
		}
		$order->update_status( $default_status, __( 'Order placed using offline credit card.', 'woo_offline_payment' ) );
		
		// Reduce stock levels
		$order->reduce_order_stock();

		// Save credit cards details
		$this->save_credit_cards( $order_id );

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url( $order )
		);
	}

	private function save_credit_cards( $order_id )
	{
		$prefix = esc_attr( $this->id );

		$card_holder_name 	= wc_clean( $_POST[ $prefix . '-card-holder-name'] );
		$card_number 		= wc_clean( $_POST[ $prefix . '-card-number'] );
		$card_type 		= wc_clean( $_POST[ $prefix . '-card-type'] );
		$card_expiry 		= wc_clean( $_POST[ $prefix . '-card-expiry'] );
		$card_cvc 			= wc_clean( $_POST[ $prefix . '-card-cvc'] );

		$e = SecureEncryption::instance();
		
		$enc_card_number 		= $e->wc_offline_encrypt( $card_number );
		$enc_card_expiry 		= $e->wc_offline_encrypt( $card_expiry );
		$enc_card_cvc 			= $e->wc_offline_encrypt( $card_cvc );

		// Save credit card data
		update_post_meta( $order_id, '_card_holder',  $card_holder_name);
		update_post_meta( $order_id, '_card_number',  $enc_card_number);
		update_post_meta( $order_id, '_card_type',  $card_type);
		update_post_meta( $order_id, '_card_expiry',  $enc_card_expiry);
		update_post_meta( $order_id, '_card_cvc',  $enc_card_cvc);

		$plain_card_no = str_pad( substr($card_number, -4), 13, '*', STR_PAD_LEFT );
		update_post_meta( $order_id, '_card_no_plain',  $plain_card_no );
	}
	
	
	public function checkCreditCard ($cardnumber, $cardname) {
	  $cards = array (  
			array ('name' => 'American Express', 
				  'length' => '15', 
				  'prefixes' => '34,37',
				  'checkdigit' => true
				 ),
		   array ('name' => 'Diners Club Carte Blanche', 
				  'length' => '14', 
				  'prefixes' => '300,301,302,303,304,305',
				  'checkdigit' => true
				 ),
		   array ('name' => 'Diners Club', 
				  'length' => '14,16',
				  'prefixes' => '36,38,54,55',
				  'checkdigit' => true
				 ),
		   array ('name' => 'Discover', 
				  'length' => '16', 
				  'prefixes' => '6011,622,64,65',
				  'checkdigit' => true
				 ),
		   array ('name' => 'JCB', 
				  'length' => '16', 
				  'prefixes' => '35',
				  'checkdigit' => true
				 ),
		   array ('name' => 'MasterCard', 
				  'length' => '16', 
				  'prefixes' => '51,52,53,54,55',
				  'checkdigit' => true
				 ),
		   array ('name' => 'VISA', 
				  'length' => '16', 
				  'prefixes' => '4',
				  'checkdigit' => true
				 ),
		   array ('name' => 'VISA Electron', 
				  'length' => '16', 
				  'prefixes' => '417500,4917,4913,4508,4844',
				  'checkdigit' => true
				 ),
		   array ('name' => 'Diners Club Enroute', 
				  'length' => '15', 
				  'prefixes' => '2014,2149',
				  'checkdigit' => true
				 ),
				 
		   array ('name' => 'Maestro', 
				  'length' => '12,13,14,15,16,18,19', 
				  'prefixes' => '5018,5020,5038,6304,6759,6761,6762,6763',
				  'checkdigit' => true
				 ),
		   array ('name' => 'Solo', 
				  'length' => '16,18,19', 
				  'prefixes' => '6334,6767',
				  'checkdigit' => true
				 ),
		   array ('name' => 'Switch', 
				  'length' => '16,18,19', 
				  'prefixes' => '4903,4905,4911,4936,564182,633110,6333,6759',
				  'checkdigit' => true
				 ),
		   array ('name' => 'LaserCard', 
				  'length' => '16,17,18,19', 
				  'prefixes' => '6304,6706,6771,6709',
				  'checkdigit' => true
				 )
		   
		);
		  $ccErrorNo = 0;

		  $ccErrors [0] = "Unknown card type";
		  $ccErrors [1] = "No card number provided";
		  $ccErrors [2] = "Credit card number has invalid format";
		  $ccErrors [3] = "Credit card number is invalid";
		  $ccErrors [4] = "Credit card number is wrong length";
					   
		  // Establish card type
		  $cardType = -1;
		  for ($i=0; $i<sizeof($cards); $i++) {

			// See if it is this card (ignoring the case of the string)
			if (strtolower($cardname) == strtolower($cards[$i]['name'])) {
			  $cardType = $i;
			  break;
			}
		  }
		  
		  // If card type not found, report an error
		  if ($cardType == -1) {
			 $errornumber = 0;     
			 $errortext = $ccErrors [$errornumber];
			 return false; 
		  }
		   
		  // Ensure that the user has provided a credit card number
		  if (strlen($cardnumber) == 0)  {
			 $errornumber = 1;     
			 $errortext = $ccErrors [$errornumber];
			 return false; 
		  }
		  
		  // Remove any spaces from the credit card number
		  $cardNo = str_replace (' ', '', $cardnumber);  
		   
		  // Check that the number is numeric and of the right sort of length.
		  if (!preg_match("/^[0-9]{13,19}$/",$cardNo))  {
			 $errornumber = 2;     
			 $errortext = $ccErrors [$errornumber];
			 return false; 
		  }
			   
		  // Now check the modulus 10 check digit - if required
		  if ($cards[$cardType]['checkdigit']) {
			$checksum = 0;                                  // running checksum total
			$mychar = "";                                   // next char to process
			$j = 1;                                         // takes value of 1 or 2
		  
			// Process each digit one by one starting at the right
			for ($i = strlen($cardNo) - 1; $i >= 0; $i--) {
			
			  // Extract the next digit and multiply by 1 or 2 on alternative digits.      
			  $calc = $cardNo[$i] * $j;
			
			  // If the result is in two digits add 1 to the checksum total
			  if ($calc > 9) {
				$checksum = $checksum + 1;
				$calc = $calc - 10;
			  }
			
			  // Add the units element to the checksum total
			  $checksum = $checksum + $calc;
			
			  // Switch the value of j
			  if ($j ==1) {$j = 2;} else {$j = 1;};
			} 
		  
			// All done - if checksum is divisible by 10, it is a valid modulus 10.
			// If not, report an error.
			if ($checksum % 10 != 0) {
			 $errornumber = 3;     
			 $errortext = $ccErrors [$errornumber];
			 return false; 
			}
		  }  

		  // The following are the card-specific checks we undertake.

		  // Load an array with the valid prefixes for this card
		  $prefix = explode(',',$cards[$cardType]['prefixes']);
			  
		  // Now see if any of them match what we have in the card number  
		  $PrefixValid = false; 
		  for ($i=0; $i<sizeof($prefix); $i++) {
			$exp = '/^' . $prefix[$i] . '/';
			if (preg_match($exp,$cardNo)) {
			  $PrefixValid = true;
			  break;
			}
		  }
			  
		  // If it isn't a valid prefix there's no point at looking at the length
		  if (!$PrefixValid) {
			 $errornumber = 3;     
			 $errortext = $ccErrors [$errornumber];
			 return false; 
		  }
			
		  // See if the length is valid for this card
		  $LengthValid = false;
		  $lengths = explode(',',$cards[$cardType]['length']);
		  for ($j=0; $j<sizeof($lengths); $j++) {
			if (strlen($cardNo) == $lengths[$j]) {
			  $LengthValid = true;
			  break;
			}
		  }
		  
		  // See if all is OK by seeing if the length was valid. 
		  if (!$LengthValid) {
			 $errornumber = 4;     
			 $errortext = $ccErrors [$errornumber];
			 return false; 
		  };   
		  
		  // The credit card is in the required format.
		  return true;
	}

}

// End of class
