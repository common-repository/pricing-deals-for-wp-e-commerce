<?php

class VTPRD_Parent_Cart_Validation {
	
	public function __construct(){
    
    //---------------------------- 
    //CATALOG DISPLAY Filters / Actions
    //---------------------------- 
    
    //***************************************************
    //price request processing at catalog product display time
    //***************************************************                                                                           
    //*********************************************************************************************************
    /*
        PRICE FILTER must precede all other info requests, otherwise the info will not be there
        All other info requests (yousave, msg etc) MUST follow sequentially after the price filter request.
        AND the price filter request only is exposed at price display time, so the only way the info is available
        is DIRECTLY AFTER price display time.  Otherwise, as variations don't have identifiers beyond the PARENT \
        product_id in the filter request, multiple variation requests would become hopelessly muddled.
        
        These two filters are called *many* times in wp-e-commerce when a single product is priced.  unfortunately.
        2nd-nth path length is as short as possible.  
    */
    //*********************************************************************************************************
    /*  +++++++++++++++++++++++++++++++++++++++++++++++
     as/of  3.8.11:  readme.txt(194): * Change: wpsc_the_variation_price() output is now filtered through wpsc_do_convert_price filter. 
      which changes some things... 
    */
  
    if( (version_compare(strval('3.8.11'), strval(WPSC_VERSION), '>') == 1) ) {   //'==1' = 2nd value is lower 
      //if this version predates '3.8.11' ... 
      add_filter( 'wpsc_price',             array(&$this, 'vtprd_get_product_catalog_price_new'), 10, 2 );
      add_filter( 'wpsc_do_convert_price',  array(&$this, 'vtprd_get_product_catalog_price_old'), 10, 1 );     
    } else {        
      add_filter( 'wpsc_price',                array(&$this, 'vtprd_get_product_catalog_price_new'), 10, 2 );    
      //!is_admin needed after WPEC 3.8.10 ...
      if (!is_admin()){  //convert_price is now called in wp-admin by wpec! Our "add_filter" can't be run in wp-admin....
        add_filter( 'wpsc_do_convert_price',   array(&$this, 'vtprd_get_product_catalog_price_do_convert'), 10, 3 ); //now uses up to 3 arguments
      } 
    }


    /****************************************************
      Template Tag Actions 
        all of these must be executed in the loop, or after the product post has been obtained...
           Usage:  (only in the loop or after product post has been procured):
       
       WITHIN THE LOOP: (in theme files...)      
              < ?php echo do_action('vtprd_show_list_price_amt_action'); ? >
        For example
          in wp-e-commerce/wpsc-theme/wpsc-single_product.php   
      
       OUTSIDE THE LOOP:       (Where $product_id = the post_ID of the product)
              < ?php echo do_action('vtprd_show_list_price_amt_action', $product_id); ? >
     
       
       ======================================================================================
       IN WPSC, the price action must always occur before any of the Pricing Deal actions 
       ======================================================================================
       If a pricing deal action needs to happen before the theme executes the product price action,
          the following line must be placed BEFORE the FIRST Pricing deal action:
              IN the loop:  wpsc_calculate_price(wpsc_the_product_id());
       
       This ensures that the Pricing deal info is prepared for the Pricing Deal actions
             
    //***************************************************  */                         
 
    // These 3 actions work with WPEC prev 3.8.9 ...
    add_action( 'vtprd_show_product_list_price_action',                    'vtprd_show_product_list_price', 10, 1 ); 
    add_action( 'vtprd_show_product_realtime_discount_full_msgs_action',   'vtprd_show_product_realtime_discount_full_msgs', 10, 1 );
    add_action( 'vtprd_show_product_you_save_action',                      'vtprd_show_product_you_save', 10, 1 ); //number formatted 
   
    /*** + + + + + + + + + + + + + + 
        Full Store Messages SHORTCODES  in  parent-functions.php  (the shortcodes have lots of options...): 
           STORE CATEGORY MESSAGES SHORTCODE    [vtprd_pricing_deal_category_msgs]
           WHOLESTORE MESSAGES SHORTCODE        [vtprd_pricing_deal_store_msgs] 
        Template USE: 
          < ?php echo do_shortcode('vtprd_pricing_deal_store_msgs'); ? >
    *** + + + + + + + + + + + + + + */

    //-END- CATALOG DISPLAY Filters / Actions

    
    
    //---------------------------- 
    //CART AND CHECKOUT Actions
    //----------------------------  
    
    /*  =============+                                    
    *  This action is done here, to facilitate the auto addition/removal of free items inserted into the cart in a BOGO free situation 
    *  the 'init' action does the actual computation                    
       =============+                                    */
        /*
          Priority of 99 to delay add_action execution.  Works normally on the 1st
          time through, and on any page refreshes.  The action kicks in 1st time on the page, and
          we're already on the shopping cart page and change to quantity happens.  The
          priority delays us in the exec sequence until after the quantity change has
          occurred, so we pick up the correct altered state.
          
          wpsc's own quantity change using:
               if ( isset( $_REQUEST['wpsc_update_quantity'] ) && ($_REQUEST['wpsc_update_quantity'] == 'true') ) {
        	add_action( 'init', 'wpsc_update_item_quantity' );
       */
   
    //*************************************************
    // LOGIC for both FRont-end and ADmin
    // Compute Discounts prior to Checkout Display => also ADMIN logid updates
    //   Definitely needed to disallow conflicts with coupons...
    //*************************************************
    add_action( 'init', array(&$this, 'vtprd_maybe_process_discount'),99 ); 
 
   
    //empty cart process - 2nd way to process this controlled in vtprd_maybe_process_discount
    add_action( 'wp_ajax_vtprd_ajax_empty_cart'       , array(&$this, 'vtprd_ajax_empty_cart') );
    add_action( 'wp_ajax_nopriv_vtprd_ajax_empty_cart', array(&$this, 'vtprd_ajax_empty_cart') );         
 
    //*************************************************
    //********  This are only used ==> POST 3.8.8 (3.8.9+) <== to pick up add-to-cart .....
    //+ - + - + - **   add_to_cart_hook  3.8.9+  **  + - + - + - 
    add_action( 'wpsc_add_to_cart', array(&$this, 'vtprd_ajax_add_to_cart_hook'), 999, 2  );    //must be priority 99, tail end charley !!! 
    //*************************************************

    
    //*************************************************
    //********  These are only used  ==> PRE 3.8.9 <==  to pick up add-to-cart .....
    //add to cart process  - 2nd way to process this controlled in vtprd_maybe_process_discount 
    //+ - + - + - **   add_to_cart_hook  less than 3.8.9   **  + - + - + - 
    add_action( 'wp_ajax_vtprd_ajax_process_discount'       , array(&$this, 'vtprd_ajax_process_discount'),999 );
    add_action( 'wp_ajax_nopriv_vtprd_ajax_process_discount', array(&$this, 'vtprd_ajax_process_discount'),999  ); 
    //*************************************************
 
 
    //this gets the nanosecond delay settings value and passes it up to the front-end JS
    add_action( 'wp_ajax_vtprd_ajax_get_nanosecond_delay'       , array(&$this, 'vtprd_ajax_get_nanosecond_delay') );
    add_action( 'wp_ajax_nopriv_vtprd_ajax_get_nanosecond_delay', array(&$this, 'vtprd_ajax_get_nanosecond_delay') );   
    //********  These are only used before 3.8.9 .....
    
                                                                              
   /*  =============+++++++++++++++++++++++++++++++++++++++++++++++++++++++++    */                       
    /*
    CHECKOUT PROCESS:
      - prep the counts at checkout page entry time
      - after each checkout row print, check to see if we're on the last one
          if so, compute and print discounts: both cart and display rules are reapplied to current unit pricing
      - at before_shipping_of_shopping_cart time, add discounts into coupon totals
      - post processing, store records in db    
    */
    
    //*************************************************
    // Print Discounts at Checkout time
    //*************************************************
    add_action( 'wpsc_before_shipping_of_shopping_cart', array(&$this, 'vtprd_maybe_print_checkout_discount'), 10, 1 );
          // Custom Action if the Theme doesn't have "wpsc_before_shipping_of_shopping_cart"
          add_action( 'vtprd_custom_before_shipping_of_shopping_cart', array(&$this, 'vtprd_maybe_print_checkout_discount'), 10, 1 );
          /*
              if YOUR them doesn't use wpsc-theme/wpsc-shopping_cart_page.php "wpsc_before_shipping_of_shopping_cart",
              Look at how that do_action is set up.  Your theme will need an action in a similar place.
              IN this case, USE the following in your theme:
                  do_action('vtprd_custom_before_shipping_of_shopping_cart');  
          */

    //Reapply rules only if an error occurred during processing regarding lifetime rule limits...         
    //the form validation filter executes ONLY at click-to-pay time                                                                      
    add_filter( 'wpsc_checkout_form_validation', array(&$this, 'vtprd_wpsc_checkout_form_validation'), 1);   
  

    //*************************************************
    // Post-Purchase
    //*************************************************
    //  from  wp-e-commerce\wpsc-theme\functions\wpsc-transaction_results_functions.php 
    //    action wpsc_confirm_checkout in wpsc-transaction_results_functions.php  is in a 'for' loop, but we only execute ONCE.
    //      ALSO can be executed on resend of customer email out of wp-admin.  Don't process this request here.        
    add_action('wpsc_confirm_checkout', array( &$this, 'vtprd_post_purchase_save_info' ), 10, 1);  //1st priority


    //add discount reporting to transaction results and customer email...        
    add_filter('wpsc_email_message', array( &$this, 'vtprd_post_purchase_maybe_email' ), 10,6);
    
    //last filter/hook which uses the session variables, also nukes the session vars...
    add_filter('wpsc_get_transaction_html_output', array( &$this, 'vtprd_post_purchase_maybe_purchase_log' ), 10,2);   

    //lifetime tables cleanup on log delete
    add_action('wpsc_purchase_log_before_delete',    array( &$this, 'vtprd_pro_lifetime_log_roll_out' ), 10, 1); 
    add_action('wpsc_sales_log_process_bulk_action', array( &$this, 'vtprd_pro_lifetime_bulk_log_roll_out' ), 10, 1); 
    
    
	} //end constructor



  //the form validation filter executes ONLY at click-to-pay time, just to access the global variables!!!!!!!!! 
	public function vtprd_wpsc_checkout_form_validation($states){
         
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }   
    /*  *************************************************
     At this point the global variable contents are gone. 
     session variables are destroyed in parent plugin before post-update processing...
     load the globals with the session variable contents, so that the data will be 
     available in the globals during post-update processing!!!
      
     DATA CHAIN - global to session back to global
     global to session - in vtprd_process_discount
     session to global - in vtprd_wpsc_checkout_form_validation  +
                            vtprd_post_purchase_maybe_purchase_log
     access global     - in vtprd_post_purchase_save_info    
    *************************************************   */
    global $vtprd_rules_set, $vtprd_cart, $vtprd_setup_options, $vtprd_info;
     
    $data_chain      = unserialize($_SESSION['data_chain']); 
    $vtprd_rules_set = $data_chain[0];
    $vtprd_cart      = $data_chain[1];    
    
    // switch from run-through at checkout time 
    if ( (defined('VTPRD_PRO_DIRNAME')) && ($vtprd_setup_options['use_lifetime_max_limits'] == 'yes') ) {    
      if ( ($vtprd_cart->lifetime_limit_applies_to_cart == 'yes') && ( sizeof($vtprd_cart->error_messages) == 0 ) ) {   //error msg > 0 = 2nd time through HERE, customer has blessed the reduction
        //reapply rules to catch lifetime rule logic using email and address info...
        
        $total_discount_1st_runthrough = $vtprd_cart->yousave_cart_total_amt;
        $vtprd_info['checkout_validation_in_process'] = 'yes';
        
        $vtprd_apply_rules = new VTPRD_Apply_Rules;   
   
        //ERROR Message Path
        if ( ( sizeof($vtprd_cart->error_messages) > 0 ) && 
             ($vtprd_cart->yousave_cart_total_amt < $total_discount_1st_runthrough) ) {   //2ND runthrough found additional lifetime limitations, need to alert customer   
            //insert error messages into checkout page
            add_action('wp_head', array(&$this, 'vtprd_display_rule_error_msg_at_checkout') );  //JS to insert error msgs      
            
            /*  turn on the messages processed switch
                otherwise errors are processed and displayed multiple times when the
                wpsc_checkout_form_validation filter finds an error (causes a loop around, 3x error result...) 
            */
            $vtprd_cart->error_messages_processed = 'yes';    
            
            /*  *********************************************************************
              Mark checkout as having ++failed edits++, and can't progress to Payment Gateway. 
              This works only with the filter 'wpsc_checkout_form_validation', which is activated on submit of
              "payment" button. 
            *************************************************************************  */
            $is_valid = false;
            $bad_input_message =  '';
            $states = array( 'is_valid' => $is_valid, 'error_messages' => $bad_input_message );
      
        } 

          /*  *************************************************
           Load this info into session variables, to begin the 
           DATA CHAIN - global to session back to global
           global to session - in vtprd_process_discount
           session to global - in vtprd_wpsc_checkout_form_validation
           access global     - in vtprd_post_purchase_save_info   
          *************************************************   */
          $data_chain = array();
          $data_chain[] = $vtprd_rules_set;
          $data_chain[] = $vtprd_cart;
          $_SESSION['data_chain'] = serialize($data_chain);              
      } else {
      
        //Get the screen data...
        vtprd_get_purchaser_info_from_screen();       
      }
    }
    return $states;   
  } 	
 
  
  /* ************************************************
  **  Price Filter -  Get display info for single product  & return discounted price
  *      (NEVER FORMATTED)
  *      
  *These two filters are called *many* times in wp-e-commerce when a single product is priced.  unfortunately.
        2nd-nth path length is as short as possible.        
  *************************************************** */
	public function vtprd_get_product_catalog_price_old($price, $product_id = null){     //passed data from wpsc_price

 
    global $post, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule;

   // **********************************
   /*   This is a Catalog-Only call
   // **********************************
   *    Every product call is handled, in order to record the all-important
   *      unit-current-price  information.  This info is used for all rule types, to help
   *      determine the 'yousave' information. 
   *      Possible call types are as follows:
   *        (1) Call for Theme message info, before Price call
   *        (2) Price call
   *        (3) Call for Theme message info, after Price call
   *        (4) Call for yousave and other info
   *        
   *    Message call can Precede the Price call
   *    Yousave call CANNOT Precede the Price Call => send back error msg to theme                     
   *                
   */
   
   /*
   //  only applies if one rule set to $rule_execution_type_selected == 'display'.  
   //     Carried in a separate option, set into info in parent-definitions, as this could be called many times ...     
    if ($vtprd_info['ruleset_has_a_display_rule'] == 'no') {
      return $price;
    }
    */
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    }
    if( get_post_field( 'post_parent', $product_id ) ) {
       $product_id = get_post_field( 'post_parent', $product_id );
    }   

    $vtprd_info['current_processing_request'] = 'display'; 
           
    //This is the only time $price is sent to this routine
    vtprd_get_product_session_info($product_id, $price);

    //price is ALWAYS returned with NO formatting, as it is called during processing, not at display time
    if ($vtprd_info['product_session_info']['product_discount_price'] > 0) {
      return $vtprd_info['product_session_info']['product_discount_price'];
    } else {     
      return $price;
    }
         
  } 
   
  /* ************************************************
  **  Price Filter -  Get display info for single product  & return discounted price
  *      (NEVER FORMATTED)
  *      
  *These two filters are called *many* times in wp-e-commerce when a single product is priced.  unfortunately.
        2nd-nth path length is as short as possible.        
  *************************************************** */
	public function vtprd_get_product_catalog_price_new($price, $product_id = null){     //passed data from wpsc_price
    global $post, $vtprd_info;
//mwntest
//echo '001a in catalog_price_new' .'<br>';
//			 wp_die( __('<strong>DIED in vtprd_get_product_catalog_price_new.</strong>', 'vtprd'), __('VT Pricing Deals not compatible - WP', 'vtprd'), array('back_link' => true));
  /* ************************************************
  *
  * Although wpsc_price is activated all over the place,
  * wpsc_do_convert_price takes precedence when the Price is
  * displayed on Screen Display.  
  * 
  * wpsc_price runs ALONE when an ajax call is made:
  *     add_action( 'wp_ajax_update_product_price'       , 'wpsc_update_product_price' );
  *     add_action( 'wp_ajax_nopriv_update_product_price', 'wpsc_update_product_price' );
  *     
  * So in this case, the session variable will 
  *   already have been stored during the Screen Display.
  *   
  * In the grand scheme of Screen Display,  
  *   wpsc_do_convert_price is done 1st, so if there is 
  *   discount info for a single product/variation,
  *   it will already be there by the time wpsc_price is
  *   executed
  *     
  * So when Ajax runs, all the data will be there  
  *                                
  *************************************************** */
    $product_id_passed_into_function = $product_id;

    
    //if we are processing a variation, always get and pass the PARENT ID
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    }
    if( get_post_field( 'post_parent', $product_id ) ) {
       $product_id = get_post_field( 'post_parent', $product_id );
    }  
    
    vtprd_get_product_session_info($product_id, $price);

    //were we passed a Variation ID to start with??
    if (($product_id_passed_into_function != $product_id ) && ($product_id_passed_into_function > ' ') ) {
      vtprd_recompute_discount_price($product_id_passed_into_function, $price);  
    }

/*mwntest
if ($product_id == '10') { 
echo 'product_session_info= <pre>'.print_r($vtprd_info['product_session_info'], true).'</pre>' ;  
global $vtprd_cart, $vtprd_rules_set;
echo '$vtprd_cart = <pre>'.print_r($vtprd_cart, true).'</pre>' ;
echo '$vtprd_rules_set = <pre>'.print_r($vtprd_rules_set, true).'</pre>' ;
echo '$vtprd_info = <pre>'.print_r($vtprd_info, true).'</pre>' ;
wp_die( __('<strong>hola</strong>', 'vtprd'), __('VT Pricing Deals not compatible - WP', 'vtprd'), array('back_link' => true));
}
*/ 
    if ($vtprd_info['product_session_info']['product_discount_price'] > 0) {
      return $vtprd_info['product_session_info']['product_discount_price'];
    } else {     
      return $price;
    }
       
  } 


	public function vtprd_get_product_catalog_price_do_convert($price, $product_id = null, $variation = null){   
    global $post, $vtprd_info;
//mwntest
//echo '001a in price_do_convert' .'<br>';
    $product_id_passed_into_function = $product_id;
    
    //if we are processing a variation, always get and pass the PARENT ID
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    }
    if( get_post_field( 'post_parent', $product_id ) ) {
       $product_id = get_post_field( 'post_parent', $product_id );
    }  
    

    vtprd_get_product_session_info($product_id, $price);


    //were we passed a Variation ID to start with??
    if (($product_id_passed_into_function != $product_id ) && ($product_id_passed_into_function > ' ') ) {
//mwntest
//echo '001a above recompute_discount price' .'<br>';      
      vtprd_recompute_discount_price($product_id_passed_into_function, $price);  
    }
   
    if ($vtprd_info['product_session_info']['product_discount_price'] > 0) {
      return $vtprd_info['product_session_info']['product_discount_price'];
    } else {     
      return $price;
    }
      
  }

   
  /* ************************************************
  **  Price Filter -  Get display info for single product at add-to_cart time and put it directly into the cart.
  *     executed out of:  do_action in => wpsc-includes/ajax.functions.php  function wpsc_add_to_cart      
  *************************************************** */

/**
 * from cart.class.php => Validate Cart Product Quantity
 * Triggered by 'wpsc_add_item' and 'wpsc_edit_item' actions when products are added to the cart.
 *
 * @since  3.8.10
 * @access private
 *
 * @param int     $product_id                    Cart product ID.
 * @param array   $parameters                    Cart item parameters.
 * @param object  $cart                          Cart object.
 *
 * @uses  wpsc_validate_product_cart_quantity    Filters and restricts the product cart quantity.
 */
  //       add_action( 'wpsc_add_item', array(&$this, 'vtprd_get_product_catalog_price_add_to_cart'), 99, 3 );
 //       add_action( 'wpsc_edit_item', array(&$this, 'vtprd_get_product_catalog_price_add_to_cart'), 99, 3); 
public function vtprd_get_product_catalog_price_add_to_cart( $product_id, $parameters, $cart ) {
     global $vtprd_info;

    $session_found = vtprd_maybe_get_product_session_info($product_id);	
   
    // $session_found MEANS ($vtprd_info['product_session_info']['product_discount_price'] > 0)
    if ($session_found) {  
      foreach ( $cart->cart_items as $key => $cart_item ) {
    		if ( $cart_item->product_id == $product_id ) {   
          if ($vtprd_info['product_session_info']['product_discount_price'] != $cart_item->unit_price) { 
            $cart_item->unit_price   =  $vtprd_info['product_session_info']['product_discount_price'];         
            $cart_item->total_price  =  $cart_item->quantity * $cart_item->unit_price;
          } 
    		}
    	}
    }
}


 
   
  /* ************************************************
  ** Template Tag / Filter -  full_msg_line   => can be accessed by both display and cart rule types    
  *************************************************** */
	public function vtprd_show_product_discount_full_msg_line($product_id=null){
    global $post, $vtprd_info;
       
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    } 
        
    //routine has been called, but no product_id supplied or available
    if (!$product_id) {
      return;
    } 
    
    vtprd_get_product_session_info($product_id);
       
    $output  = '<p class="discount-full-msg" id="fullmsg_' .$product_id. '">' ;
    for($y=0; $y < sizeof($vtprd_info['product_session_info']['product_rule_full_msg_array']); $y++) {
      $output .= $vtprd_info['product_session_info']['product_rule_full_msg_array'][$y] . '<br>' ;
    }      
    $output .= '</p>'; 
        
    echo $output;
    
    return;
  } 


     
  /* ************************************************
  **   Add discount amount to coupons amt, where the discount will be
  *     automatically picked up and calculated as a credit.
  *     If no coupon presented at checkout, print discount totals line 
  *         PRE WPSC 3.8.7, which has a filter instead...  
  *         
  *     Price Reductions (display discounts) are taken realtime at catalog display time
  *     they are NOT reported on in the discount total reporting, as they actually affect the original unit price the customer sees
  *     
  *     When discounts are applied at cart display time, the ORIGINAL unit price is found and the Price Reduction is rolled back into
  *     the unit price, and total of THOSE unit prices is the "Subtotal of item cost before discount" .
  *     Then the CART discounts ONLY are displayed, and are supplied to the coupon total for discounting
  *     
  *     However, the Price Reduction rules are Counted for the cumulative rules switching at Cart Checkout time.  
  *       Price Reductions ALWAYS take place, regardless of cumulative pricing rules (due to WPSC not passing variation-level info at price action time)          
  *         
  *************************************************** */
	public function vtprd_maybe_process_discount(){  //and print discount info...
    global $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $wpsc_coupons;

    if (is_admin()){ 
    
      //LIFETIME logid cleanup...
      //  LogID logic from wpsc-admin/init.php
      if(defined('VTPRD_PRO_DIRNAME')) {
        switch( true ) {
          case ( isset( $_REQUEST['wpsc_admin_action2'] ) && ($_REQUEST['wpsc_admin_action2'] == 'purchlog_bulk_modify') )  :
                 vtprd_maybe_lifetime_log_bulk_modify();
             break; 
          case ( isset( $_REQUEST['wpsc_admin_action'] ) && ($_REQUEST['wpsc_admin_action'] == 'delete_purchlog') ) :
                 vtprd_maybe_lifetime_log_roll_out_cntl();
             break;                                             
        } 
          
        if (version_compare(VTPRD_PRO_VERSION, VTPRD_MINIMUM_PRO_VERSION) < 0) {    //'<0' = 1st value is lower  
          add_action( 'admin_notices',array(&$this, 'vtprd_admin_notice_version_mismatch') );            
        }          
      }

       
      return;    
    }

    $shopping_cart_url = get_option ('shopping_cart_url');
    
    //init the variables
    $vtprd_info['on_checkout_page'];
    $vtprd_info['coupon_num'];
    
    //* * * * * * 
    //  ALL from the checkout page, methinks
    // Pick Up all ajax cart actions - all from wpsc-includes/ajax.functions.php            if ( isset( $_POST['coupon_num'] )
    switch( true ) {

      case ( $shopping_cart_url == $this->vtprd_currPageURL() ) :  
            
            $vtprd_info['on_checkout_page'] = 'yes';  //cleared at end of apply-rule.php
            
            switch( true ) {
                 case ( isset( $_REQUEST['wpsc_ajax_action'] ) && ( 'empty_cart' == $_REQUEST['wpsc_ajax_action']) ) :
                    $this->vtprd_maybe_clear_auto_add_session_vars();
                    return;
                  break; 
                case ( isset( $_REQUEST['coupon_num']) ) :   //this case also picks up the Update_quantity, if there is a coupon num!!
                    $vtprd_info['coupon_num'] = esc_sql( $_POST['coupon_num'] );
                  break;
                case ( isset( $_REQUEST['wpsc_update_quantity'] ) && ($_REQUEST['wpsc_update_quantity'] == 'true') ) :
                     //carry on to updates
                  break;                   
                default:
                    return;   //no coupon, no update, no clear - don't re-run apply-rules ...   
                  break;                                   
            }

            //Then process the discount
           
         break;         
      case ( isset( $_REQUEST['wpsc_ajax_action'] ) && ($_REQUEST['wpsc_ajax_action'] == 'special_widget' || $_REQUEST['wpsc_ajax_action'] == 'donations_widget') ) :
      case ( isset( $_REQUEST['wpsc_ajax_action'] ) && ($_REQUEST['wpsc_ajax_action'] == 'add_to_cart' ) ) :
      case ( isset( $_REQUEST['wpsc_update_quantity'] ) && ($_REQUEST['wpsc_update_quantity'] == 'true') ) :
           //process the discount!
         break; 
//     case ( isset( $_REQUEST['wpsc_ajax_action'] ) && ( 'empty_cart' == $_REQUEST['wpsc_ajax_action'] || isset( $_GET['sessionid'] )  && $_GET['sessionid'] > 0 ) ) :
      case ( isset( $_REQUEST['wpsc_ajax_action'] ) && ( 'empty_cart' == $_REQUEST['wpsc_ajax_action']) ) :
          $this->vtprd_maybe_clear_auto_add_session_vars();
          return;
         break;                      
      default:
 
          return;
         break;                          
    }
    
    

    //if the customer has removed all of the items, just clean up session vars
    if (sizeof($wpsc_cart->cart_items) == 0) {
      $this->vtprd_maybe_clear_auto_add_session_vars();
    } 
 
    $this->vtprd_process_discount();
    
    //if the customer has removed a product which causes the plugin to delete a product
    //  ending up with an empty cart, clean up the session vars...
    if (sizeof($wpsc_cart->cart_items) == 0) {
      $this->vtprd_maybe_clear_auto_add_session_vars();
    }

//echo '$wpsc_cart= <pre>'.print_r($wpsc_cart, true).'</pre>' ;
//echo '$vtprd_cart= <pre>'.print_r($vtprd_cart, true).'</pre>' ; 
//echo '$vtprd_rules_set= <pre>'.print_r($vtprd_rules_set, true).'</pre>' ; 
   
    
    return;
  }   


   public function vtprd_admin_notice_version_mismatch() {
      $message  =  '<strong>' . __('Looks like you\'re running an older version of Pricing Deals Pro.' , 'vtprd') .'<br><br>' . __('Your Pro Version = ' , 'vtprd') .VTPRD_PRO_VERSION.  __(' and the minimum required pro version = ' , 'vtprd') .VTPRD_MINIMUM_PRO_VERSION. '</strong>' ;
      $message .=  '<br><br>' . __('Please delete the old Pricing Deals Pro plugin from your installation, go to http://www.varktech.com/download-pro-plugins/ , download and install the newest Pricing Deals Pro version.'  , 'vtprd');
      $admin_notices = '<div id="message" class="error fade" style="background-color: #FFEBE8 !important;"><p>' . $message . ' </p></div>';
      echo $admin_notices;
      return; 
   
  }   

   //Ajax-only, pre 3.8.9
   public function vtprd_ajax_process_discount() {

    //can only be executed when WPEC version less than 3.8.9
    // after that, the "do_action( 'wpsc_add_to_cart', $product, $cart_item )" in wpsc-includes/ajax.functions.php is used
    if( (version_compare(strval('3.8.9'), strval(WPSC_VERSION), '>') == 1) ) {   //'==1' = 2nd value is lower
      $do_nothing;
    } else {       
      exit;
    }

     $this->vtprd_process_discount();
     
     //Ajax needs exit
     exit;
   }

   // 3.8.9 +
   // from wpsc-includes/ajax.functions.php
   public function vtprd_ajax_add_to_cart_hook($product, $cart_item ) {
  
      //can only be executed when WPEC version >= than 3.8.9
      // before that, the ajax trap above is used
      if( (version_compare(strval('3.8.9'), strval(WPSC_VERSION), '>') == 1) ) {   //'==1' = 2nd value is lower
         return;
      }      
      
      if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
         $this->vtprd_process_discount();
      }    
   

      $this->vtprd_process_discount() ;
 
     
    return;
   }
     
    
	public function vtprd_process_discount(){  //and print discount info...    
    global $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $wpsc_coupons;    

    //calc discounts                
    $vtprd_info['current_processing_request'] = 'cart'; 
    $vtprd_apply_rules = new VTPRD_Apply_Rules;    

    /*  *************************************************
     Load this info into session variables, to begin the 
     DATA CHAIN - global to session back to global
     global to session - in vtprd_process_discount
     session to global - in vtprd_wpsc_checkout_form_validation
     access global     - in vtprd_post_purchase_save_info   
    *************************************************   */
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }
    $data_chain = array();
    $data_chain[] = $vtprd_rules_set;
    $data_chain[] = $vtprd_cart;
    $_SESSION['data_chain'] = serialize($data_chain);             
    
    return;        
} 

  //**************************************************
  //  Maybe print discount, always update the coupon info for post-payment processing
  //**************************************************
	public function vtprd_maybe_print_checkout_discount(){  //and print discount info...
    
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    } 
    
    //set one-time switch for use in function vtprd_post_purchase_save_info
    $_SESSION['do_log_function'] = true;
          
    /*  *************************************************
     At this point the global variable contents are gone. 
     session variables are destroyed in parent plugin before post-update processing...
     load the globals with the session variable contents, so that the data will be 
     available in the globals during post-update processing!!!
      
     DATA CHAIN - global to session back to global
     global to session - in vtprd_process_discount
     session to global - in vtprd_wpsc_checkout_form_validation  +
                            vtprd_post_purchase_maybe_purchase_log
     access global     - in vtprd_post_purchase_save_info    
    *************************************************   */
    global $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $wpsc_coupons;
     
    $data_chain      = unserialize($_SESSION['data_chain']); 
    $vtprd_rules_set = $data_chain[0];
    $vtprd_cart      = $data_chain[1];  
  
    //**************************************************
    //Add discount totals into coupon_totals (a positive #) for payment gateway processing and checkout totals processing
    //  $wpsc_cart->coupons_amount has ALREADY been re-computed in apply-rules.php at add to cart time
    //**************************************************    

//echo '$wpsc_cart= <pre>'.print_r($wpsc_cart, true).'</pre>' ;
//echo '$vtprd_cart= <pre>'.print_r($vtprd_cart, true).'</pre>' ; 
//echo '$vtprd_rules_set= <pre>'.print_r($vtprd_rules_set, true).'</pre>' ; 
         
    
    if ($vtprd_cart->yousave_cart_total_amt > 0) {
    //   vtprd_enqueue_front_end_css();   
        vtprd_print_checkout_discount();
    } 

    /*  *************************************************
     Load this info into session variables, to begin the 
     DATA CHAIN - global to session back to global
     global to session - in vtprd_process_discount
     session to global - in vtprd_wpsc_checkout_form_validation
     access global     - in vtprd_post_purchase_save_info   
    *************************************************   */
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }
    $data_chain = array();
    $data_chain[] = $vtprd_rules_set;
    $data_chain[] = $vtprd_cart;
    $_SESSION['data_chain'] = serialize($data_chain);  
            
    return;        
} 

  /* ************************************************
  **   After purchase is completed, store lifetime purchase and discount log info
  *
  * This function is executed multiple times, only complete on 1st time through    
  *************************************************** */ 
  public function vtprd_post_purchase_save_info($log_id) {   //$log_id comes in as an argument from wpsc call...
    
    //while the global data is available here, it does not stay 'current' between iterations, and we loos the 'already_done' switch, so we need the data chain.
    
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }   
    
    //only do this once - set in function vtprd_maybe_print_checkout_discount    
    if (!$_SESSION['do_log_function']) {
        return;
    }
    $_SESSION['do_log_function'] = false;
    
    /*  *************************************************
     At this point the global variable contents are gone. 
     session variables are destroyed in parent plugin before post-update processing...
     load the globals with the session variable contents, so that the data will be 
     available in the globals during post-update processing!!!
      
     DATA CHAIN - global to session back to global
     global to session - in vtprd_process_discount
     session to global - in vtprd_wpsc_checkout_form_validation  +
                            vtprd_post_purchase_maybe_purchase_log
     access global     - in vtprd_post_purchase_save_info    
    *************************************************   */
    global $wpsc_cart, $vtprd_setup_options, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $wpsc_coupons;
     
    $data_chain      = unserialize($_SESSION['data_chain']); 
    $vtprd_rules_set = $data_chain[0];
    $vtprd_cart      = $data_chain[1];  


    //if this was initiated during a re-send of the customer email out of WP-Admin, EXIT stage left!!
    //    (this switch set at cart load time...)
    //  do_log_function above should take care of this already...
//    if ($vtprd_cart->wpsc_purchase_in_progress != 'yes') {
//      return;
//    }
  
    //*****************
    //Save LIfetime data
    //*****************
    if ( (defined('VTPRD_PRO_DIRNAME')) && ($vtprd_setup_options['use_lifetime_max_limits'] == 'yes') )  { 
      vtprd_save_lifetime_purchase_info($log_id);
    }

    //Save Discount Purchase Log info
    //************************************************
    //*   Purchase log is essential to customer email reporting
    //*      so it MUST be saved at all times.
    //************************************************
    vtprd_save_discount_purchase_log($log_id);     
//wp_die( __('<strong>die again.</strong>', 'vtprd'), __('VT Pricing Deals not compatible - WP', 'vtprd'), array('back_link' => true));     
    return;
  } // end  function vtprd_store_max_purchaser_info()     


   
  /* ************************************************
  **   After purchase is completed, create the plaintext or html discount report
  *  
  *pre 3.8.9, executed out of wpsc-theme/functions/wpsc_transaction_results_functions.php
  *   $message = apply_filters('wpsc_email_message', $message, $report_id, $product_list, $total_tax, $total_shipping_email, $total_price_email);
  *     
  *post 3.8.9 
  * The 'wpsc_email_message' Filter is now wpsc-includes/purchase-log-notifications.class.php
  *   Executed at line 314:
  *       return apply_filters( 'wpsc_email_message', parent::process_plaintext_args(), $this->plaintext_args['purchase_id'], $this->plaintext_product_list, $this->plaintext_args['total_tax'], $this->plaintext_args['total_shipping'], $this->plaintext_args['total_price'] );
  *   And at line 319:
  *       return apply_filters( 'wpsc_email_message', parent::process_html_args(), $this->html_args['purchase_id'], $this->html_product_list, $this->html_args['total_tax'], $this->html_args['total_shipping'], $this->html_args['total_price'] );
  *  
  *************************************************** */ 
  public function vtprd_post_purchase_maybe_email($message, $report_id, $product_list, $total_tax, $total_shipping_email, $total_price_email) {   
    global $wpdb, $vtprd_rules_set, $vtprd_cart, $vtprd_setup_options; 
    //This function can be called during cart processing, but after 3.8.9, also out of dashboard.
    //   so we need to get the info from the database created post-purchase.
    
    // First, get the log_id from the $report_id variable, which may alread by formatted
    $int = filter_var($report_id, FILTER_SANITIZE_NUMBER_INT);
    
    //just in case there are signs, we'll get rid of those:
    $log_Id = str_replace(array('+','-'), '', $int);
   
    //if there's a discount history, let's find it...
    $vtprd_purchase_log = $wpdb->get_row( "SELECT * FROM `" . VTPRD_PURCHASE_LOG . "` WHERE `cart_parent_purchase_log_id`='" . $log_Id . "' LIMIT 1", ARRAY_A );      	
    	    
    //if purchase log, use that info instead of current 
    if ($vtprd_purchase_log) { 
      $vtprd_cart      = unserialize($vtprd_purchase_log['cart_object']);    
      $vtprd_rules_set = unserialize($vtprd_purchase_log['ruleset_object']);
    }                                                                                                                          

    //NO discount found, no msg changes
    if (!($vtprd_cart->yousave_cart_total_amt > 0)) {
      return $message;    
    } 
  

    //if $message contains HTML, produce discount msgs in html as well
    if($message != strip_tags($message)) {
       $msgType = 'html';
    } else {
       $msgType = 'plainText';
    }

    //get the Discount detail report...
    $discount_reporting = vtprd_email_cart_reporting($msgType); 
        
    //just concatenate in the discount DETAIL info into $message and return
    
    //split the message up into pieces.  We're going to insert all the Discount Reporting
    //  just before "Total Shipping:"
    $totShip_literal = __( 'Total Shipping:', 'wpsc' ); 
    $message_pieces  = explode($totShip_literal, $message); //this removes the delimiter string...
    
    //overwrite $message old message parts, new info as well...
    $message  =  $message_pieces[0]; //1st piece before the delimiter "Total Shipping:"
    $message .=  $discount_reporting;
    
    //skip a line    
    if ($msgType == 'html') {
      $message .= '<br>';
    } else {
      $message .= "\r\n";
    }
    
    //put the delimeter string BACK
    $message .=  $totShip_literal; 
    $message .=  $message_pieces[1]; //2nd piece after the delimiter "Total Shipping:"

    return $message;
  }    

/* ************************************************
  **   After purchase is completed, => create the html transaction results report <=
  *       ONLY at transaction time...
  *********************************************** */     
 public function vtprd_post_purchase_maybe_purchase_log($message, $notification) {   
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }   
    /*  *************************************************
     At this point the global variable contents are gone. 
     session variables are destroyed in parent plugin before post-update processing...
     load the globals with the session variable contents, so that the data will be 
     available in the globals during post-update processing!!!
      
     DATA CHAIN - global to session back to global
     global to session - in vtprd_process_discount
     session to global - in vtprd_wpsc_checkout_form_validation  +
                            vtprd_post_purchase_maybe_purchase_log
     access global     - in vtprd_post_purchase_save_info    
    *************************************************   */
    global $vtprd_rules_set, $vtprd_cart, $vtprd_setup_options, $vtprd_info;
    
    if(!isset($_SESSION['data_chain'])){
      return $message;    
    }
     
    $data_chain      = unserialize($_SESSION['data_chain']); 
    $vtprd_rules_set = $data_chain[0];
    $vtprd_cart      = $data_chain[1];  
    
    
    //NO discount found, no msg changes
    if (!($vtprd_cart->yousave_cart_total_amt > 0)) {
      $this->vtprd_nuke_session_variables();
      return $message;    
    } 
    
    //check if the discount reporting has already been applied, by looking for the header
    //  as this function may be called Twice
    $needle = '<th>' . __('Discount Quantity', 'vtprd') .'</th>';
    if (strpos($message, $needle)) {   //if $needle already in the #message
      $this->vtprd_nuke_session_variables();
      return $message;
    }
    
  
    $msgType = 'html';

    //get the Discount detail report...
    $discount_reporting = vtprd_email_cart_reporting($msgType); 
    
    //just concatenate in the discount DETAIL info into $message and return
    
    //split the message up into pieces.  We're going to insert all the Discount Reporting
    //  just before "Total Shipping:"
    $totShip_literal = __( 'Total Shipping:', 'wpsc' ); 
    $message_pieces  = explode($totShip_literal, $message); //this removes the delimiter string...
    
    //overwrite $message old message parts, new info as well...
    $message  =  $message_pieces[0]; //1st piece before the delimiter "Total Shipping:"
    $message .=  $discount_reporting;
    
    //skip a line    
    if ($msgType == 'html') {
      $message .= '<br>';
    } else {
      $message .= "\r\n";
    }
    
    //put the delimeter string BACK
    $message .=  $totShip_literal; 
    $message .=  $message_pieces[1]; //2nd piece after the delimiter "Total Shipping:"

    $this->vtprd_nuke_session_variables();
    return $message;
  } 
 
   
  /* ************************************************
  **   Post-transaction cleanup - Nuke the session variables 
  *************************************************** */ 
 public  function vtprd_nuke_session_variables() {
    
    if (isset($_SESSION['data_chain']))  {
      $contents = $_SESSION['data_chain'];
      unset( $_SESSION['data_chain'], $contents );
    }
    
    if (isset($_SESSION['previous_free_product_array']))  {    
      $contents = $_SESSION['previous_free_product_array'];
      unset( $_SESSION['previous_free_product_array'], $contents );
    }

    if (isset($_SESSION['current_free_product_array']))  {         
      $contents = $_SESSION['current_free_product_array'];
      unset( $_SESSION['current_free_product_array'], $contents ); 
    }
    
    return;   
 }
   
   
  /* ************************************************
  **   Application - get current page url
  *       
  *       The code checking for 'www.' is included since
  *       some server configurations do not respond with the
  *       actual info, as to whether 'www.' is part of the 
  *       URL.  The additional code balances out the currURL,
  *       relative to the Parent Plugin's recorded URLs           
  *************************************************** */ 
 public  function vtprd_currPageURL() {
     global $vtprd_info;
     $currPageURL = $this->vtprd_get_currPageURL();
     $www = 'www.';
     
     $curr_has_www = 'no';
     if (strpos($currPageURL, $www )) {
         $curr_has_www = 'yes';
     }
     
     //use checkout URL as an example of all setup URLs
     $checkout_has_www = 'no';
     $shopping_cart_url = get_option ('shopping_cart_url');
     if (strpos($shopping_cart_url, $www )) {
         $checkout_has_www = 'yes';
     }     
         
     switch( true ) {
        case ( ($curr_has_www == 'yes') && ($checkout_has_www == 'yes') ):
        case ( ($curr_has_www == 'no')  && ($checkout_has_www == 'no') ): 
            //all good, no action necessary
          break;
        case ( ($curr_has_www == 'no') && ($checkout_has_www == 'yes') ):
            //reconstruct the URL with 'www.' included.
            $currPageURL = $this->vtprd_get_currPageURL($www); 
          break;
        case ( ($curr_has_www == 'yes') && ($checkout_has_www == 'no') ): 
            //all of the woo URLs have no 'www.', and curr has it, so remove the string 
            $currPageURL = str_replace($www, "", $currPageURL);
          break;
     } 
 
     return $currPageURL;
  } 
 public  function vtprd_get_currPageURL($www = null) {
     global $vtprd_info;
     $pageURL = 'http';
     //if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
     if ( isset( $_SERVER["HTTPS"] ) && strtolower( $_SERVER["HTTPS"] ) == "on" ) { $pageURL .= "s";}
     $pageURL .= "://";
     $pageURL .= $www;   //mostly null, only active rarely, 2nd time through - see above
     
     //NEVER create the URL with the port name!!!!!!!!!!!!!!
     $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
     /* 
     if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
     } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
     }
     */
     return $pageURL;
  }  
   
   
  
  /* ************************************************
  **   Application - On Error Display Message on E-Commerce Checkout Screen  
  *************************************************** */ 
  public function vtprd_display_rule_error_msg_at_checkout(){
    global $vtprd_info, $vtprd_cart, $vtprd_setup_options;
     
    //error messages are inserted just above the checkout products, and above the checkout form
     ?>     
        <script type="text/javascript">
        jQuery(document).ready(function($) {
    <?php 
    //loop through all of the error messages 
    //          $vtprd_info['line_cnt'] is used when table formattted msgs come through.  Otherwise produces an inactive css id. 
    for($i=0; $i < sizeof($vtprd_cart->error_messages); $i++) { 
      ?>
       <?php  if ( $vtprd_setup_options['show_error_before_checkout_products_selector'] > ' ' )  {  ?> 
          $('<div class="vtprd-error"><p> <?php echo $vtprd_cart->error_messages[$i] ?> </p></div>').insertBefore('<?php echo $vtprd_setup_options['show_error_before_checkout_products_selector'] ?>') ;
       <?php  }  ?>
       <?php  if ( $vtprd_setup_options['show_error_before_checkout_address_selector'] > ' ' )  {  ?>  
          $('<div class="vtprd-error"><p> <?php echo $vtprd_cart->error_messages[$i] ?> </p></div>').insertBefore('<?php echo $vtprd_setup_options['show_error_before_checkout_address_selector'] ?>') ;
       <?php  }  ?>
      <?php 
    }  //end 'for' loop      
    ?>   
            });   
          </script>
     <?php    


     /* ***********************************
        CUSTOM ERROR MSG CSS AT CHECKOUT
        *********************************** */
     if ($vtprd_setup_options[custom_error_msg_css_at_checkout] > ' ' )  {
        echo '<style type="text/css">';
        echo $vtprd_setup_options[custom_error_msg_css_at_checkout];
        echo '</style>';
     }
     
     /*
      Turn off the messages processed switch.  As this function is only executed out
      of wp_head, the switch is only cleared when the next screenful is sent.
     */
     $vtprd_cart->error_messages_processed = 'no';
       
 } 

   //Ajax-only
   public function vtprd_ajax_empty_cart() {

     $this->vtprd_maybe_clear_auto_add_session_vars();
     
     //Ajax needs exit
     exit;
   }

 //Clean Up Session Variables which would otherwise persist during Discount Processing       
  public function vtprd_maybe_clear_auto_add_session_vars() {
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    } 
    if (isset($_SESSION['previous_auto_add_array']))  {
        $contents = $_SESSION['previous_auto_add_array'];
        unset( $_SESSION['previous_auto_add_array'], $contents );    
    }
    if (isset($_SESSION['current_auto_add_array']))  {
        $contents = $_SESSION['current_auto_add_array'];
        unset( $_SESSION['current_auto_add_array'], $contents );    
    }
    if (isset($_SESSION['data_chain']))  {
        $contents = $_SESSION['data_chain'];
        unset( $_SESSION['data_chain'], $contents );    
    }    

    return;    
  }

   //Ajax-only
   public function vtprd_ajax_get_nanosecond_delay() {
     global $vtprd_setup_options;
    
     $response = $vtprd_setup_options['nanosecond_delay_for_add_to_cart_processing']; 
     echo json_encode($response); 
    
     //Ajax needs exit
     exit;
   }

 /*
    also:  in wpsc-includes/purchase-log-class.php  (from 3.9)
		do_action( 'wpsc_sales_log_process_bulk_action', $current_action );
  */
	public function vtprd_pro_lifetime_log_roll_out($log_id ){  
    if ( (is_admin()) && (defined('VTPRD_PRO_DIRNAME')) ) {     
       vtprd_maybe_lifetime_roll_log_totals_out($log_id);
    }
    return;   
  }

 /*
    also:  in wpsc-includes/purchase-log-class.php  (from 3.9)
 		do_action( 'wpsc_purchase_log_before_delete', $log_id ); 
  */
	public function vtprd_pro_lifetime_bulk_log_roll_out($current_action){  
    if ( (is_admin()) && (defined('VTPRD_PRO_DIRNAME')) ) {     
       vtprd_maybe_lifetime_bulk_roll_log_totals_out($current_action);
    }
    return;   
  }
   
    
   
} //end class
$vtprd_parent_cart_validation = new VTPRD_Parent_Cart_Validation;