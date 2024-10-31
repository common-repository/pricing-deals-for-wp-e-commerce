<?php

	/*******************************************************  
 	     The session variable for this product will already have been
 	     stored during the catalog display of the product price 
          (similar pricing done in vtprd-auto-add.php...)       
  ******************************************************** */
	function vtprd_load_vtprd_cart_for_processing(){
      
      global $post, $wpdb, $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_setup_options, $vtprd_info; 

      $vtprd_cart = new VTPRD_Cart;  

      foreach($wpsc_cart->cart_items as $key => $cart_item) {
        $vtprd_cart_item                = new VTPRD_Cart_Item;
        //load up the wpsc values into $vtprd_cart_item
        $vtprd_cart_item->product_id    = $cart_item->product_id;
        
       
        //get post for current product id, call it var post
        //   if post_parent > 0, this is a variation product!
        $var_post = get_post($cart_item->product_id);
        if ($var_post->post_parent > 0) {
          // cats associated with parent, not variation...
          $cart_item_id_for_cats = $var_post->post_parent; 
        } else {
          //default to current cart item product id  if product not a variation
          $cart_item_id_for_cats = $cart_item->product_id;
        }
       
       //  from wpsc-includes/cart-class.php 
      //  if ( isset( $special_price ) && $special_price > 0 && $special_price < $price )   then $price =   $special_price
         
        /* there's a WPEC variation title bug in the checkout cart.  Verify if product is 
        * a variation id, then if so, verify that the title has an 
        * open paren (standard variation title naming).  If not, go
        * directly to the variation post and get the title.                                      
        */
        if ($var_post->post_parent > 0) {  
           if ( !strstr($cart_item->product_name, '(') ) {              
              $cart_item->product_name = $var_post->post_title ;
           }                      
        }  
               
        $vtprd_cart_item->product_name  = $cart_item->product_name;
        //when storing qty, first remove previously added free qty, if there...
        $vtprd_cart_item->quantity      = $cart_item->quantity;
        
        
        //this gets all of the pricing info...
//        vtprd_get_wpsc_item_info();
        
        //FROM wp-e-commerce/wpsc-includes/cart.class.php   refresh_item()
   	    $product_id  = $vtprd_cart_item->product_id;
       	$product = get_post( $product_id );
       	$product_meta = get_metadata( 'post', $product_id );
       	$price = get_post_meta( $product_id, '_wpsc_price', true ); 
                
        $special_price = get_post_meta( $product_id, '_wpsc_special_price', true ); 
        
        if ($special_price == 0) {
           if ($var_post->post_parent > 0) {                                               
              $special_price = get_post_meta( $var_post->post_parent, '_wpsc_special_price', true );     
           }
        }

       	$product_meta = get_post_meta( $product_id, '_wpsc_product_metadata', true );
   
        //if variation, get the variation list price
        if ($var_post->post_parent > 0) {  
          $vtprd_cart_item->db_unit_price_list = get_post_meta( $var_post->post_parent, '_wpsc_price', true );                                            
        } else {
          $vtprd_cart_item->db_unit_price_list    = $price;           
        }        
     
        $session_found = vtprd_maybe_get_product_session_info($product_id);
       
        //By this time, there may  be a 'display' session variable for this product, if a discount was displayed in the catalog
        //  so 2nd - nth iteration picks up the original unit price, not any discount shown currently
        //  The discount is applied in apply-rules for disambiguation, then rolled out before it gets processed (again)
        if ($session_found) {
            $vtprd_cart_item->unit_price     =  $vtprd_info['product_session_info']['product_unit_price'];
            $vtprd_cart_item->db_unit_price  =  $vtprd_info['product_session_info']['product_unit_price']; 
        } else {  
            if ( isset( $special_price ) && $special_price > 0 && $special_price < $price )  {
           		$price = $special_price;              
            }
            $priceandstock_id = 0;
           	//$this->weight = isset( $product_meta[0]['weight'] ) ? $product_meta[0]["weight"] : 0;
           	// if we are using table rate price
           	if ( isset( $product_meta[0]['table_rate_price'] ) ) {
           		$levels = $product_meta[0]['table_rate_price'];
           		if ( ! empty( $levels['quantity'] ) ) {
           			foreach((array)$levels['quantity'] as $key => $qty) {
           				if ($vtprd_cart_item->quantity >= $qty) {
                  //if ($this->quantity >= $qty) {
           					$unit_price = $levels['table_price'][$key];
           					if ($unit_price != '')
           						$price = $unit_price;                    
           				}
           			}
           		}
           	}
            //**************
            $vtprd_cart_item->unit_price            = $price;
            $vtprd_cart_item->db_unit_price         = $price;
            //**************            
        }

        $vtprd_cart_item->db_unit_price_special = $special_price; 
        
        //  from wpsc-includes/cart-class.php 
        //  if ( isset( $special_price ) && $special_price > 0 && $special_price < $price )          
        if ( ($vtprd_cart_item->db_unit_price_special > 0) && 
             ($vtprd_cart_item->db_unit_price_special < $vtprd_cart_item->db_unit_price_list ) )  {
            $vtprd_cart_item->product_is_on_special = 'yes';             
            $vtprd_cart_item->unit_price  =  $vtprd_cart_item->db_unit_price_special;
        }            

        //******************************************
        //REASSERT DISPLAY RULE DISCOUNT unit price *if* cart rule - if it was already arrived at....
        //  if it's a cart run, we're at add-to-cart time, so any display discounts would have already happened...
        //******************************************
        if ( ($vtprd_info['current_processing_request'] == 'cart') &&
             ($session_found) && 
             ($cart_item->unit_price < $vtprd_cart_item->unit_price) ) {
            $vtprd_cart_item->unit_price  =  $cart_item->unit_price;
        }
        
        
        //always recalculate based on the db price, to redo any discounts previously applied
        $vtprd_cart_item->total_price   = $vtprd_cart_item->quantity * $vtprd_cart_item->unit_price;      
        //end ***************************************************
      

        /*  *********************************
        ***  JUST the cat *ids* please...
        ************************************ */
        $vtprd_cart_item->prod_cat_list = wp_get_object_terms( $cart_item_id_for_cats, $vtprd_info['parent_plugin_taxonomy'], $args = array('fields' => 'ids') );
        $vtprd_cart_item->rule_cat_list = wp_get_object_terms( $cart_item_id_for_cats, $vtprd_info['rulecat_taxonomy'], $args = array('fields' => 'ids') );
        //*************************************                    
        
        //initialize the arrays
        $vtprd_cart_item->prod_rule_include_only_list = array();  
        $vtprd_cart_item->prod_rule_exclusion_list = array();
        
        /*  *********************************
        ***  fill in include/exclude arrays if selected on the PRODUCT Screen (parent plugin)
        ************************************ */
        $vtprd_includeOrExclude_meta  = get_post_meta($product_id, $vtprd_info['product_meta_key_includeOrExclude'], true);
        if ( $vtprd_includeOrExclude_meta ) {
          switch( $vtprd_includeOrExclude_meta['includeOrExclude_option'] ) {
            case 'includeAll':  
              break;
            case 'includeList':                  
                $vtprd_cart_item->prod_rule_include_only_list = $vtprd_includeOrExclude_meta['includeOrExclude_checked_list'];                                            
              break;
            case 'excludeList':  
                $vtprd_cart_item->prod_rule_exclusion_list = $vtprd_includeOrExclude_meta['includeOrExclude_checked_list'];                                               
              break;
            case 'excludeAll':  
                $vtprd_cart_item->prod_rule_exclusion_list[0] = 'all';  //set the exclusion list to exclude all
              break;
          }
        }
       
        //add cart_item to cart array
        $vtprd_cart->cart_items[]       = $vtprd_cart_item; 
        
        /*
          this switch is accessed in wpsc-integration/parent-cart-validation.php  function vtprd_post_purchase_save_info
              if it's not set to 'yes', that means that the post-purchase function was initiated
              during a re-send of the customer email out of WP-Admin, and that function should terminate....
        */
        $vtprd_cart->wpsc_purchase_in_progress = 'yes';
        
    }  //end foreach
     
     
    if ( (defined('VTPRD_PRO_DIRNAME')) && ($vtprd_setup_options['use_lifetime_max_limits'] == 'yes') )  {
      vtprd_get_purchaser_info_from_screen();   
    }
    $vtprd_cart->purchaser_ip_address = $vtprd_info['purchaser_ip_address']; 

  }



   //************************************* 
   // tabulate $vtprd_info['cart_rows_at_checkout']
   //************************************* 
	function vtprd_compute_wpsc_coupon_discount(){
      global $wpsc_cart, $vtprd_info, $wpsc_coupons, $vtprd_rules_set; 

      if ($vtprd_info['on_checkout_page'] != 'yes') {
        $vtprd_rules_set[0]->coupons_amount_without_rule_discounts = 0;        //?????
        return;
      } 

     if ($vtprd_info['coupon_num'] == '') {
       $vtprd_rules_set[0]->coupons_amount_without_rule_discounts = 0;
       return ;
     }
     
     //free shipping coupon returns a 0 coupons_amount
     
     $coupon =  $vtprd_info['coupon_num'];
     if ( $wpsc_coupons->validate_coupon() ) {
       $wpsc_coupons = new wpsc_coupons( $coupon );
       $wpsc_cart->coupons_amount = $wpsc_coupons->calculate_discount(); 
       $vtprd_rules_set[0]->coupons_amount_without_rule_discounts = $wpsc_cart->coupons_amount;
     }
     
     return;     
  } 
 
   //************************************* 
   // tabulate $vtprd_info['cart_rows_at_checkout']
   //************************************* 
	function vtprd_count_wpsc_cart_contents(){
      global $wpsc_cart, $vtprd_info; 

      $vtprd_info['cart_rows_at_checkout_count'] = 0;
      foreach($wpsc_cart->cart_items as $key => $cart_item) {
        $vtprd_info['cart_rows_at_checkout_count']++;   //increment count by 1
      } 
      return;     
  } 
 
 
	function vtprd_load_vtprd_cart_for_single_product_price($product_id, $price){
      global $post, $wpdb, $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info; 

      $vtprd_cart = new VTPRD_Cart;  
      $vtprd_cart_item                = new VTPRD_Cart_Item;      
      $post = get_post($product_id);
   
      //load up the wpsc values into $vtprd_cart_item  => done this way since wpsc does not send the variation down with the price call!
      $vtprd_cart_item->product_id            = $product_id;
      $vtprd_cart_item->product_name          = $post->post_name;
      $vtprd_cart_item->quantity              = 1;
      $vtprd_cart_item->unit_price            = $price;
      $vtprd_cart_item->db_unit_price         = $price;
      $vtprd_cart_item->db_unit_price_list    = $price;
      $vtprd_cart_item->db_unit_price_special = $price;    
      $vtprd_cart_item->total_price           = $price;
            
      /*  *********************************
      ***  JUST the cat *ids* please...
      ************************************ */
      $vtprd_cart_item->prod_cat_list = wp_get_object_terms( $product_id, $vtprd_info['parent_plugin_taxonomy'], $args = array('fields' => 'ids') );
      $vtprd_cart_item->rule_cat_list = wp_get_object_terms( $product_id, $vtprd_info['rulecat_taxonomy'], $args = array('fields' => 'ids') );
        //*************************************                    

        
      //add cart_item to cart array
      $vtprd_cart->cart_items[]       = $vtprd_cart_item;  
      
                
  }


    //*******************************************************************************
    //FROM wp-e-commerce/wpsc-includes/cart.class.php   refresh_item()
    //*******************************************************************************
    function vtprd_get_wpsc_item_info() {
   	global $wpdb, $wpsc_shipping_modules, $wpsc_cart, $vtprd_cart, $vtprd_cart_item;

   }
	
	function vtprd_move_vtprd_cart_to_session($product_id){
      global $post, $wpdb, $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_setup_options, $vtprd_rules_set;  

      $short_msg_array = array();
      $full_msg_array = array();
      $msg_already_done = 'no';
    
      //auditTrail keyed to rule_id, so foreach is necessary
      foreach ($vtprd_cart->cart_items[0]->cartAuditTrail as $key => $row) {       
        
        //parent product vargroup on sale, individual product variation may not be on sale.
        // send an addtional sale msg for the varProd parent group...
        if ($vtprd_setup_options['show_yousave_one_some_msg'] == 'yes' ) {
          $show_yousave_one_some_msg;
          if (!$show_yousave_one_some_msg) {
            $rulesetKey = $row['ruleset_occurrence'];
            switch( $vtprd_rules_set[$rulesetKey]->inPop_varProdID_parentLit) {  
              case 'one':
                 $show_yousave_one_some_msg = __('One of these are on Sale', 'vtprd');
                break;
              case 'some':
                 $show_yousave_one_some_msg = __('Some of these are on Sale', 'vtprd');
                break;         
              case 'all':  //all are on sale, handled as normal.
                break; 
              default:  //handled as normal.
                break;       
            }
          }
        }
         
        if ($row['rule_short_msg'] > ' ' ) {       
          $short_msg_array [] = $row['rule_short_msg'];
          $full_msg_array  [] = $row['rule_full_msg'];
        }

      }

      /*
       if  $vtprd_cart->cart_level_status == 'rejected' no discounts found
       how to handle yousave display, etc.... If no yousave, return 'false'
      */
      if ( $vtprd_cart->cart_level_status == 'rejected' ) {
        $vtprd_cart->cart_items[0]->discount_price = 0;
        $vtprd_cart->cart_items[0]->yousave_total_amt = 0;
        $vtprd_cart->cart_items[0]->yousave_total_pct = 0;
      } 
      
      //needed for wp-e-commerce!!!!!!!!!!!
      //  if = 'yes', display of 'yousave' becomes 'save FROM' and doesn't change!!!!!!!
      $product_variations_sw = vtprd_test_for_variations($product_id);
    
      $vtprd_info['product_session_info']  =     array (
            'product_list_price'           => $vtprd_cart->cart_items[0]->db_unit_price_list,
            'product_unit_price'           => $vtprd_cart->cart_items[0]->db_unit_price,
            'product_special_price'        => $vtprd_cart->cart_items[0]->db_unit_price_special,
            'product_discount_price'       => $vtprd_cart->cart_items[0]->discount_price,
            'product_is_on_special'        => $vtprd_cart->cart_items[0]->product_is_on_special,
            'product_yousave_total_amt'    => $vtprd_cart->cart_items[0]->yousave_total_amt,     
            'product_yousave_total_pct'    => $vtprd_cart->cart_items[0]->yousave_total_pct,     
            'product_rule_short_msg_array' => $short_msg_array,        
            'product_rule_full_msg_array'  => $full_msg_array,
            'product_has_variations'       => $product_variations_sw,
            'session_timestamp_in_seconds' => time(),
            'user_role'                    => vtprd_get_current_user_role(),            
            'product_in_rule_allowing_display'  => $vtprd_cart->cart_items[0]->product_in_rule_allowing_display, //if not= 'yes', only msgs are returned 
            'show_yousave_one_some_msg'    => $show_yousave_one_some_msg, 
            //for later ajaxVariations pricing
            'this_is_a_parent_product_with_variations' => $vtprd_cart->cart_items[0]->this_is_a_parent_product_with_variations,            
            'pricing_by_rule_array'        => $vtprd_cart->cart_items[0]->pricing_by_rule_array                   
          ) ;      
      if(!isset($_SESSION)){
        session_start();
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
      } 
      //store session id 'vtprd_product_session_info_[$product_id]'
      $_SESSION['vtprd_product_session_info_'.$product_id] = $vtprd_info['product_session_info'];
      
  }

    function vtprd_fill_variations_checklist($tax_class, $checked_list = NULL, $pop_in_out_sw, $product_ID, $product_variation_IDs) { 
        global $post, $vtprd_setup_options, $vtprd_info;

        $checkbox_cnt = 0;
        foreach ($product_variation_IDs as $product_variation_ID) {     //($product_variation_IDs as $product_variation_ID => $info)
            $checkbox_cnt++;
            $post = get_post($product_variation_ID);
            $output  = '<li id='.$product_variation_ID.'>' ;
            $output  .= '<label class="selectit var-list-'.$pop_in_out_sw.'-checkbox">' ;
            $output  .= '<input id="'.$product_variation_ID.'_'.$tax_class.' " ';
            $output  .= 'type="checkbox" name="tax-input-' .  $tax_class . '[]" ';
            $output  .= 'value="'.$product_variation_ID.'" ';
            $check_found = 'no';
            if ($checked_list) {
                if (in_array($product_variation_ID, $checked_list)) {   //if variation is in previously checked_list   
                   $output  .= 'checked="checked"';
                   $check_found = 'yes';
                }                
            }
            $output  .= '>'; //end input statement
            $output  .= '&nbsp;' . $post->post_title;
            $output  .= '</label>';            
            $output  .= '</li>';
            echo  $output ;
        }
        if ($tax_class == 'var-in') {
           $vtprd_info['inpop_variation_checkbox_total'] = $checkbox_cnt;
        } 
         
        return;   
    }
    

  /* ************************************************
  **   Get all variations for product
  *************************************************** */
  function vtprd_get_variations_list($product_ID) {
        
    //do variations exist?
    $product_has_variations = vtprd_test_for_variations ($product_ID); 
    
    if ($product_has_variations == "yes") {    
      //get all variation IDs (title will be obtained in checkbox logic)
      /*Loop through product variations saved previously and create array of the variations *only* 
      * tt.`parent` > '0' ==> parent = 0 indicates a variation set name rather than a variation set member    
      * the inner select gets the 'child' variation posts (status = 'inherit'), then the outer select passes by the variation set name post  
      * 
      *the inner select will eventually be slow, but won't be accessed that often, so is currently acceptable.  The alternative is massively complex
      * (use db_id to go to term_rel and get the variation set name term_tax_id, get all of the term_tax_ids of the varition set and variations, get all of the obj_id's they own and compare to posts.id...)                    	
       */
      global $wpdb;
    	$varsql = "SELECT tr.`object_id` 
          FROM `".$wpdb->term_relationships."` AS tr 
    			LEFT JOIN `".$wpdb->term_taxonomy."` AS tt
          ON  tr.`term_taxonomy_id` = 	tt.`term_taxonomy_id`	
    			WHERE  tr.`object_id` in 
               ( SELECT posts.`id` 
            			FROM `".$wpdb->posts."` AS posts			
            			WHERE posts.`post_status` = 'inherit' AND posts.`post_parent`= '" . $product_ID . "'
                )
           AND  tt.`parent` > '0'      
            ";                    
    	$product_variations_list = $wpdb->get_col($varsql);  // yields an array of child post ids (variations, where the $$, sku etc are held).
    } else  {
      $product_variations_list;
    }
    
    return ($product_variations_list);
  } 


  /* ************************************************
  **   Get single variation data to support discount_auto_add_free_product
  *************************************************** */
  function vtprd_get_var_out_product_variations_parameter($product_ID) {

    global $wpdb;
        
  	$varsql = "SELECT tt.`parent` , tr.`term_taxonomy_id` 
        FROM `".$wpdb->term_relationships."` AS tr 
  			LEFT JOIN `".$wpdb->term_taxonomy."` AS tt
        ON  tr.`term_taxonomy_id` = 	tt.`term_taxonomy_id`	
  			WHERE  tr.`object_id` = '" . $product_ID . "' 
          AND  tt.`parent` > '0'      
          LIMIT 1";
                    
  	$results = $wpdb->get_row($varsql, ARRAY_A);  // yields an array of child post ids (variations, where the $$, sku etc are held).
    
    /*
    build the variations_paramater, where
        array key  = variation set id
        array data = variation occurrence id
    This mimics the structure the ajax add-to-cart delivers from the screen,
    allowing the use of the regular add-to-cart structures.
    */
    
    $key = $results['parent'];
    $data = $results['term_taxonomy_id'];
    
    $product_variations_array = array();
    $product_variations_array[$key] = $data;
    
    return ($product_variations_array);
  } 
  
  
  function vtprd_test_for_variations($prod_ID) { 
     $vartest_response = 'no';
     if ( wpsc_product_has_variations( $prod_ID ) )  {
        $vartest_response = 'yes';
     }
      return ($vartest_response);   
  }  
  
    
   function vtprd_format_money_element($money) { 
    
     /*  ****************************    
        wpsc_currency_display function    in wp-e-commerce/wpsc-includes/processing.functions.php
            $args( array(
          		'display_currency_symbol' => true,
          		'display_decimal_point'   => true,
          		'display_currency_code'   => false,
          		'display_as_html'         => true,
          		'isocode'                 => false,
          	)
        So only need to override what's necessary, turn off the displa_as_html    
     ****************************  */
     $formatted = wpsc_currency_display( $money , array('display_as_html' => false) );
     
     return $formatted;
   }
   
   //****************************
   // Gets Currency Symbol from PARENT plugin   - only used in backend UI during rules update
   //****************************   
  function vtprd_get_currency_symbol() {
    global $wpdb;
    $currency_data = $wpdb->get_row( "SELECT `symbol`,`symbol_html`,`code` FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `id`='" . esc_attr( get_option( 'currency_type' ) ) . "' LIMIT 1", ARRAY_A );      	
    $currency_sign = ! empty( $currency_data['symbol'] ) ? $currency_data['symbol_html'] : $currency_data['code'];
    
    return $currency_sign;
  } 

  function vtprd_get_current_user_role() {
    global $current_user; 
    
    if ( !$current_user )  {
      $current_user = wp_get_current_user();
    }
    
    $user_roles = $current_user->roles;
    $user_role = array_shift($user_roles);
    if  ($user_role <= ' ') {
      $user_role = 'notLoggedIn';
    }      
    return $user_role;
  } 
  
  //***************************************************	
  // Cart widget processing, executed out of cart_widget.php
  //***************************************************	
	function vtprd_cart_widget_discount_details(){
         
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }   

    global $post, $wpdb, $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_setup_options; 
     
    $data_chain      = unserialize($_SESSION['data_chain']); 
    $vtprd_rules_set = $data_chain[0];
    $vtprd_cart      = $data_chain[1];     
    
            
    $vtprd_info['current_processing_request'] = 'cart'; 

 //   $vtprd_apply_rules = new VTPRD_Apply_Rules;
 //new line...
    $vtprd_cart->cart_discount_subtotal = $vtprd_cart->yousave_cart_total_amt;
    
    if ($vtprd_cart->yousave_cart_total_amt > 0) {        
      
    //  vtprd_enqueue_front_end_css();      
      
      //*****************************
      //PRINT DISCOUNT ROWS + total line
      //*****************************

      $execType = 'cartWidget';
      
      if ($vtprd_setup_options['show_cartWidget_purchases_subtotal'] == 'beforeDiscounts') {
        vtprd_print_cart_purchases_subtotal($execType);
      }
      
      vtprd_print_cart_widget_title();
      vtprd_print_cart_discount_rows($execType);

      if ($vtprd_setup_options['show_cartWidget_purchases_subtotal'] == 'withDiscounts') {
        vtprd_print_cart_purchases_subtotal($execType);
      } 
      vtprd_print_cart_discount_total($execType);        
    }  
 
  }

    
   //****************************
   // copied from wpsc-includes/cart.class.php  function wpsc_cart_total_widget
   //****************************
   function vtprd_cart_total_widget( $shipping = true, $tax = true, $coupons = true ) {
                     //added global
   global $wpsc_cart, $vtprd_cart, $vtprd_info, $vtprd_rules_set;

   $total = $subtotal = $wpsc_cart->calculate_subtotal();

   if ( $shipping ) {
      $total += $wpsc_cart->calculate_total_shipping();
   }
   if ( $tax && wpsc_tax_isincluded() == false ) {
      $total += $wpsc_cart->calculate_total_tax();
   }
   if ( $coupons ) {
      $total -= $vtprd_rules_set[0]->coupons_amount_without_rule_discounts;
   }
   
   //only change to the routine:
   $total -= $vtprd_cart->yousave_cart_total_amt;

   if ( get_option( 'add_plustax' ) == 1 ) {
      return wpsc_currency_display( $subtotal );
   } else {
      return wpsc_currency_display( $total );
   }

 }
 
 
  /* ************************************************
  **   print discount amount by product, and print total AND MOVE DISCOUNT INTO TOTAL...             
  *************************************************** */

	function vtprd_print_checkout_discount() {
    global $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;
   //when executing from here, the table rows created by the print routines need a <table>
    //  when executed from the cart_widget, the TR lines appear in the midst of an existing <table>
    
    $execType = 'checkout';
    
    if ($vtprd_setup_options['show_checkout_purchases_subtotal'] == 'beforeDiscounts') {
      vtprd_print_cart_purchases_subtotal($execType);
    }
    
    $output;

    $output .=  '<table class="vtprd-discount-table"> ';
    
    
    if ($vtprd_setup_options['show_checkout_discount_titles_above_details'] == 'yes') {    
      $output .= '<tr id="vtprd-discount-title-checkout" >';
            /* COLSPAN no longer used here, has no affect
      $output .= '<td colspan="' .$vtprd_setup_options['checkout_html_colspan_value']. '" id="vtprd-discount-title-above-checkout">';
      */
      $output .= '<td id="vtprd-discount-title-above-checkout">';
      $output .= '<div class="vtprd-discount-prodLine-checkout" >';
      
      $output .= '<span class="vtprd-discount-prodCol-checkout">' .  __('Product', 'vtprd') . '</span>';
      
      $output .= '<span class="vtprd-discount-unitCol-checkout">' .  __('Discount Qty', 'vtprd') . '</span>';
  
      $output .= '<span class="vtprd-discount-amtCol-checkout">' .  __('Discount Amount', 'vtprd') . '</span>';
      
      $output .= '</div'; //end prodline
      $output .= '</td>';
      $output .= '</tr>';
         
     }
     echo  $output;
    
    $vtprd_cart->cart_discount_subtotal = $vtprd_cart->yousave_cart_total_amt;
    
    if ($vtprd_rules_set[0]->coupons_amount_without_rule_discounts > 0) {
       $vtprd_cart->cart_discount_subtotal += $vtprd_rules_set[0]->coupons_amount_without_rule_discounts;
       //print a separate discount line if price discounts taken, PRN
       vtprd_print_coupon_discount_row($execType);
    }
                                                 
    //print discount detail rows 
    vtprd_print_cart_discount_rows($execType);
 
    if ($vtprd_setup_options['show_checkout_purchases_subtotal'] == 'withDiscounts') {
      vtprd_print_cart_purchases_subtotal($execType);
    } 
 
    if ($vtprd_rules_set[0]->coupons_amount_without_rule_discounts > 0) {
       //print totals using the coupon amount  
       if ($vtprd_setup_options['show_checkout_credit_total_when_coupon_active'] == 'yes')  {          
          vtprd_print_cart_discount_total($execType); 
       }    
    } else {
      //if there's no coupon being presented, no coupon totals will be printed, so discount total line is needed     
      vtprd_print_cart_discount_total($execType);   
    }
    
    //coupons_amount FROM the user entering in a coupon is already in subtotal from above, prn
    $wpsc_cart->coupons_amount = $vtprd_cart->cart_discount_subtotal;
    
    if ($vtprd_setup_options['checkout_new_subtotal_line'] == 'yes') {
      vtprd_print_new_cart_checkout_subtotal_line($execType);
    }    

    echo   '</table>  <!-- vtprd discounts table close -->  '; 
        
 } 
 
  /* ************************************************
  **   print discount amount by product, and print total              
  *************************************************** */
	function vtprd_print_cart_discount_rows($execType) {
    global $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;
       
      $printRowsCheck = 'show_' .$execType. '_discount_detail_lines';
      if ($vtprd_setup_options[$printRowsCheck] == 'no') {
        return;
      }
  
      $sizeof_cart_items = sizeof($vtprd_cart->cart_items);
      for($k=0; $k < $sizeof_cart_items; $k++) {  
       	if ( $vtprd_cart->cart_items[$k]->yousave_total_amt > 0) {            
            if ((($execType == 'checkout')   && ($vtprd_setup_options['show_checkout_discount_details_grouped_by_what']   == 'rule')) ||
                (($execType == 'cartWidget') && ($vtprd_setup_options['show_cartWidget_discount_details_grouped_by_what'] == 'rule'))) {
              //these rows are indexed by ruleID, so a foreach is needed...
              foreach($vtprd_cart->cart_items[$k]->yousave_by_rule_info as $key => $yousave_by_rule) {
                $i = $yousave_by_rule['ruleset_occurrence'];
                //display info is tabulated for cumulative rule processing, but the Price Reduction has already taken place!!
                if ($vtprd_rules_set[$i]->rule_execution_type == 'cart') {
                  $output  = '<tr class="vtprd-discount-title-row" >';                  
                  $output .= '<td  class="vtprd-ruleNameCol-' .$execType. ' vtprd-border-cntl vtprd-deal-msg" >' . stripslashes($yousave_by_rule['rule_short_msg']) . '</td>';
                  $output .= '</tr>';
                  echo  $output;
                  
                  //if a max was reached and msg supplied, print here 
                  if ($yousave_by_rule['rule_max_amt_msg'] > ' ') {    
                    $output  = '<tr class="vtprd-discount-title-row" >';                  
                    $output .= '<td  class="vtprd-ruleNameCol-' .$execType. ' vtprd-border-cntl vtprd-deal-msg" >' . stripslashes($yousave_by_rule['rule_max_amt_msg']) . '</td>';
                    $output .= '</tr>';
                  echo  $output;                  
                  }
                  
                  $amt = $yousave_by_rule['yousave_amt']; 
                  $units = $yousave_by_rule['discount_applies_to_qty'];                  
                  vtprd_print_discount_detail_line($amt, $units, $execType, $k);
                }
              }
            } else {   //show discounts by product
                  $amt = $vtprd_cart->cart_items[$k]->yousave_total_amt; 
                  $units = $vtprd_cart->cart_items[$k]->yousave_total_qty;                  
                  vtprd_print_discount_detail_line($amt, $units, $execType, $k);
           }
        }
      }

    return;
    
  }

  function vtprd_print_cart_widget_title() {     
    global $vtprd_setup_options;
    if ($vtprd_setup_options['show_cartWidget_discount_titles_above_details'] == 'yes') {    
      $output;  
      $output .= '<tr id="vtprd-discount-title-cartWidget" >';
      $output .= '<td colspan="' .$vtprd_setup_options['cartWidget_html_colspan_value']. '" id="vtprd-discount-title-cartWidget-line">';
      $output .= '<div class="vtprd-discount-prodLine-cartWidget" >';
      
      $output .= '<span class="vtprd-discount-prodCol-cartWidget">&nbsp;</span>';
      
      $output .= '<span class="vtprd-discount-unitCol-cartWidget">&nbsp;</span>';
  
      $output .= '<span class="vtprd-discount-amtCol-cartWidget">' .  __('Discount', 'vtprd') . '</span>';
      
      $output .= '</div>'; //end prodline
      $output .= '</td>';
      $output .= '</tr>';

      echo  $output;
    }
    return;   
  }
 
     
	function vtprd_print_discount_detail_line($amt, $units, $execType, $k) {  
    global $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;
    $output;
    $output .= '<tr class="vtprd-discount-total-for-product-rule-row-' .$execType. '  bottomLine-' .$execType. '" >';
    $output .= '<td colspan="' .$vtprd_setup_options['' .$execType. '_html_colspan_value']. '">';
    $output .= '<div class="vtprd-discount-prodLine-' .$execType. '" >';
    
    $output .= '<span class="vtprd-discount-prodCol-' .$execType. '" id="vtprd-discount-product-id-' . $vtprd_cart->cart_items[$k]->product_id . '">';
    $output .= $vtprd_cart->cart_items[$k]->product_name;
    $output .= '</span>';
    
    $output .= '<span class="vtprd-discount-unitCol-' .$execType. '">' . $units . '</span>';
    
    $amt = vtprd_format_money_element($amt); 
    $output .= '<span class="vtprd-discount-amtCol-' .$execType. '">' . $vtprd_setup_options['' .$execType. '_credit_detail_label'] . ' ' .$amt . '</span>';
    
    $output .= '</div>'; //end prodline
    $output .= '</td>';
    $output .= '</tr>';
    echo  $output;  
 }
  
  //coupon discount only shows at Checkout 
	function vtprd_print_coupon_discount_row($execType) {
    global $wpsc_cart, $vtprd_setup_options, $vtprd_rules_set;

    $output;
    $output .= '<tr class="vtprd-discount-total-for-product-rule-row-' .$execType. '  bottomLine-' .$execType. '  vtprd-coupon_discount-' .$execType. '" >';
    $output .= '<td colspan="' .$vtprd_setup_options['' .$execType. '_html_colspan_value']. '">';
    $output .= '<div class="vtprd-discount-prodLine-' .$execType. '" >';
    
    $output .= '<span class="vtprd-discount-prodCol-' .$execType. ' vtprd-coupon_discount-literal-' .$execType. '">';
    $output .= __('Coupon Discount: ', 'vtprd'); 
    $output .= '</span>';
    
    $output .= '<span class="vtprd-discount-unitCol-' .$execType. '">&nbsp;</span>';
    
    $labelType = $execType . '_credit_detail_label';
    
    $amt = vtprd_format_money_element($vtprd_rules_set[0]->coupons_amount_without_rule_discounts);  //show original coupon amt as credit
    $output .= '<span class="vtprd-discount-amtCol-' .$execType. '  vtprd-coupon_discount-amt-' .$execType. '">' . $vtprd_setup_options['' .$execType. '_credit_detail_label'] . ' ' .$amt . '</span>';
    
    $output .= '</div>'; //end prodline
    $output .= '</td>';
    $output .= '</tr>';
    echo  $output; 
       
    return;
    
  }
   
   
	//***************************************
  // Subtotal - Cart Purchases:
  //***************************************
  function vtprd_print_cart_purchases_subtotal($execType) {
    global $vtprd_cart, $wpsc_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;   
      $subTotalCheck = 'show_' .$execType. '_purchases_subtotal';
      if ($vtprd_setup_options[$subTotalCheck] == 'none') {     
        return;
      }
    //FOR WPSC, we the subtotal we've generated may be inaccurate, due to price action not supplying variation info...
    //    so we skip it.  Code left here for use by other parent plugins...
  //  if (VTPRD_PARENT_PLUGIN_NAME == 'WP E-Commerce') {
  //    $skip_this;
   // } else {

      $output;
      if ($vtprd_setup_options[$subTotalCheck] == 'beforeDiscounts') {
          $output .= '<tr class="vtprd-discount-total-' .$execType. '" >';
          $output .= '<td colspan="' .$vtprd_setup_options['' .$execType. '_html_colspan_value'].'" class="vtprd-discount-total-' .$execType. '-line">';
          $output .= '<div class="vtprd-discount-prodLine-' .$execType. '" >';
          
          $output .= '<span class="vtprd-discount-totCol-' .$execType. '">';
          $output .= $vtprd_setup_options['' .$execType. '_credit_subtotal_title'];
          $output .= '</span>';
      
 //       due to a WPEC problem,  $vtprd_cart->cart_original_total_amt  may be inaccurate - use wpec's own subtotaling....
 //         $subTotal = $vtprd_cart->cart_original_total_amt;    //show as a credit 
          $subTotal = $wpsc_cart->calculate_subtotal();
          $amt = vtprd_format_money_element($subTotal);
 
 
          $labelType = $execType . '_credit_detail_label';  
          $output .= '<span class="vtprd-discount-totAmtCol-' .$execType. '"> &nbsp;&nbsp;' .$amt . '</span>';
          
          $output .= '</div>'; //end prodline
          $output .= '</td>';
          $output .= '</tr>'; 
      } else {
          $output .= '<tr class="vtprd-discount-total-' .$execType. '" >';
          $output .= '<td colspan="' .$vtprd_setup_options['' .$execType. '_html_colspan_value'].'" class="vtprd-discount-total-' .$execType. '-line">';
          $output .= '<div class="vtprd-discount-prodLine-' .$execType. '" >';
          
          $output .= '<span class="vtprd-discount-totCol-' .$execType. '">';
          $output .= $vtprd_setup_options['' .$execType. '_credit_subtotal_title'];
          $output .= '</span>';
      
      
 //         $subTotal = $vtprd_cart->cart_original_total_amt;    //show as a credit
          $subTotal = $wpsc_cart->calculate_subtotal();
          $amt = vtprd_format_money_element($subTotal);
          
          
          $labelType = $execType . '_credit_detail_label';  
          $output .= '<span class="vtprd-discount-totAmtCol-' .$execType. '"> &nbsp;&nbsp;' .$amt . '</span>';
          
          $output .= '</div>'; //end prodline
          $output .= '</td>';
          $output .= '</tr>'; 
      }
      echo  $output;
   
    return;
    
  }

  //***************************************
  // Subtotal with Discount:  (print)
  //***************************************
	function vtprd_print_new_cart_checkout_subtotal_line($execType) {
    global $vtprd_cart, $wpsc_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;   

      $output;
 
      $output .= '<tr class="vtprd-discount-total-' .$execType. ' vtprd-new-subtotal-line" >';
      $output .= '<td colspan="' .$vtprd_setup_options['' .$execType. '_html_colspan_value'].'" class="vtprd-discount-total-' .$execType. '-line">';
      $output .= '<div class="vtprd-discount-prodLine-' .$execType. '" >';
      
      $output .= '<span class="vtprd-discount-totCol-' .$execType. '">';
      $output .= $vtprd_setup_options['' .$execType. '_new_subtotal_label'];
      $output .= '</span>';
  
      //$subTotal = $vtprd_cart->cart_original_total_amt - $vtprd_cart->yousave_cart_total_amt;    //show as a credit
      $subTotal  = $wpsc_cart->calculate_subtotal();
  
   //no longer used...   $subTotal -= $vtprd_cart->yousave_cart_total_amt;
      $subTotal  -= $vtprd_cart->cart_discount_subtotal;
   
      $amt = vtprd_format_money_element($subTotal);
      $labelType = $execType . '_credit_detail_label';  
      $output .= '<span class="vtprd-discount-totAmtCol-' .$execType. ' vtprd-new-subtotal-amt"> &nbsp;&nbsp;' .$amt . '</span>';
      
      $output .= '</div>'; //end prodline
      $output .= '</td>';
      $output .= '</tr>'; 

      echo  $output;
   
    return;  
  }
    
     
	function vtprd_print_cart_discount_total($execType) {
    global $vtprd_cart, $wpsc_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;
    
    $printRowsCheck = 'show_' .$execType. '_discount_total_line';
    
    if ($vtprd_setup_options[$printRowsCheck] == 'no') {
      return;
    }
    $output;
    $output .= '<tr class="vtprd-discount-total-' .$execType. ' vtprd-discount-line" >';    
    $output .= '<td colspan="' .$vtprd_setup_options['' .$execType. '_html_colspan_value']. '" class="vtprd-discount-total-' .$execType. '-line ">';
    $output .= '<div class="vtprd-discount-prodLine-' .$execType. '" >';
    
    $output .= '<span class="vtprd-discount-totCol-' .$execType. '">';
    $output .= $vtprd_setup_options['' .$execType. '_credit_total_title'];
    $output .= '</span>';


    $amt = vtprd_format_money_element($vtprd_cart->cart_discount_subtotal); 

    
    $output .= '<span class="vtprd-discount-totAmtCol-' .$execType. ' vtprd-discount-amt">' . $vtprd_setup_options['' .$execType. '_credit_detail_label'] . ' ' .$amt . '</span>';
     
    $output .= '</div>'; //end prodline
    $output .= '</td>';
    $output .= '</tr>';
    echo  $output;
       
    return;
    
  }
   
    
     /*
    \n = CR (Carriage Return) // Used as a new line character in Unix
    \r = LF (Line Feed) // Used as a new line character in Mac OS
    \n\r = CR + LF // Used as a new line character in Windows
    (char)13 = \n = CR // Same as \n
    http://en.wikipedia.org/wiki/Newline
    */
  /* ************************************************
  **   Assemble all of the cart discount row info FOR email/transaction results messaging  
  *        $msgType = 'html' or 'plainText'            
  *************************************************** */
	function vtprd_email_cart_reporting($msgType) {
    global $vtprd_cart, $vtprd_cart_item, $vtprd_rules_set, $vtprd_info, $vtprd_setup_options;
    $output;
    
    if ($msgType == 'html') {
      $output .= '<br><br>';
      $output .= '<table class="vtprd-discount-transaction-results">';
        $output .= '<thead>';
          $output .= '<tr>';
            $output .= '<th>' . __('Name', 'vtprd') .'</th>';
            $output .= '<th>' . __('Discount Quantity', 'vtprd') .'</th>';
            $output .= '<th>' . __('Discount Amount', 'vtprd') .'</th>';
          $output .= '</tr>';
        $output .= '</thead>';
      $output .= '<tbody>';
    } else {
      //first a couple of page ejects
      $output .= "\r\n \r\n";
      $output .= __( 'Discounts ', 'wpsc' );
      $output .= "\r\n";
    }
 
    $vtprd_cart->cart_discount_subtotal = $vtprd_cart->yousave_cart_total_amt;
    
    
    if ($vtprd_cart->wpsc_orig_coupon_amount > 0) {
       //print a separate discount line if price discounts taken, PRN
       $output .= vtprd_email_cart_coupon_discount_row($msgType);
       $vtprd_cart->cart_discount_subtotal += $vtprd_cart->wpsc_orig_coupon_amount; 
    }

    //get the discount details    
    $output .= vtprd_email_cart_discount_rows($msgType);

    if ($vtprd_setup_options['show_checkout_purchases_subtotal'] != 'none') {
      $output .= vtprd_email_cart_purchases_subtotal($msgType);
    } 
  
    $output .= vtprd_email_cart_discount_total($msgType);
    

    if ($vtprd_setup_options['checkout_new_subtotal_line'] == 'yes') {
      $output .= vtprd_email_new_cart_checkout_subtotal_line($msgType);
    }    

    if ($msgType == 'html') {
      $output .= '</tbody>';
      $output .= '</table>';
    }

    return $output;
    
  }
  
  //coupon discount only shows at Checkout 
	function vtprd_email_cart_coupon_discount_row($msgType) {
    global $vtprd_cart, $vtprd_rules_set, $vtprd_setup_options;

    $output;
    $amt = vtprd_format_money_element($vtprd_cart->wpsc_orig_coupon_amount);  //show original coupon amt as credit
    
    if ($msgType == 'html')  {
      $output .= '<tr>';
        $output .= '<td colspan="2">' . __('Coupon Discount', 'vtprd') .'</td>';
        $output .= '<td>' . $vtprd_setup_options['checkout_credit_detail_label'] . ' ' .$amt .'</td>';
      $output .= '</tr>';    
    } else {
      $output .= __('Coupon Discount: ', 'vtprd'); 
      
      $output .= $amt;
      $output .= "\r\n \r\n";
    }

    return $output; 
    
  }      
    
  /* ************************************************
  **   Assemble all of the cart discount row info              
  *************************************************** */
	function vtprd_email_cart_discount_rows($msgType) {
    global $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;
       
      $output;

      $sizeof_cart_items = sizeof($vtprd_cart->cart_items);
      for($k=0; $k < $sizeof_cart_items; $k++) {  
       	if ( $vtprd_cart->cart_items[$k]->yousave_total_amt > 0) {            
            if ($vtprd_setup_options['show_checkout_discount_details_grouped_by_what']   == 'rule') {
              //these rows are indexed by ruleID, so a foreach is needed...
              foreach($vtprd_cart->cart_items[$k]->yousave_by_rule_info as $key => $yousave_by_rule) {
              
                //display info is tabulated for cumulative rule processing, but the Price Reduction has already taken place!!
                if ($yousave_by_rule['rule_execution_type'] == 'cart') {
                  if ($msgType == 'html')  {
                    $output .= '<tr>';
                      $output .= '<td colspan="3">' . stripslashes($yousave_by_rule['rule_short_msg'])  .'</td>';
                    $output .= '</tr>';
                  } else {
                    $output .=  stripslashes($yousave_by_rule['rule_short_msg']) . "\r\n"; 
                  }                                 
                  $amt   = $yousave_by_rule['yousave_amt']; 
                  $units = $yousave_by_rule['discount_applies_to_qty'];                  
                  $output .= vtprd_email_discount_detail_line($amt, $units, $msgType, $k); 
                }                 
              }
            } else {   //show discounts by product
                  $amt = $vtprd_cart->cart_items[$k]->yousave_total_amt; 
                  $units = $vtprd_cart->cart_items[$k]->yousave_total_qty;                  
                  $output .= vtprd_email_discount_detail_line($amt, $units, $msgType, $k);
           }
        }
      }

    return $output;
    
  }
     
	function vtprd_email_discount_detail_line($amt, $units, $msgType, $k) {  
    global $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;
      $output;
    $amt = vtprd_format_money_element($amt); //mwn
    if ($msgType == 'html')  {
      $output .= '<tr>';
        $output .= '<td>' . $vtprd_cart->cart_items[$k]->product_name .'</td>';
        $output .= '<td>' . $units .'</td>';
        $output .= '<td>' . $vtprd_setup_options['checkout_credit_detail_label'] . ' ' .$amt .'</td>';
      $output .= '</tr>';   
    } else {
      $output .= __( 'Product: ', 'wpsc' ); 
      $output .= $vtprd_cart->cart_items[$k]->product_name;
      $output .= __( ' Discount Units: ', 'wpsc' );
      $output .= $units ;
      $output .= __( ' Discount Amount: ', 'wpsc' ); 
      $output .= $amt;
      $output .= "\r\n";
    }
    
    return  $output;  
 }
   
	function vtprd_email_cart_purchases_subtotal($msgType) {
    global $vtprd_cart, $wpsc_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;   

    $output;
    //$subTotal = $vtprd_cart->cart_original_total_amt;    //show as a credit
    $subTotal = $wpsc_cart->calculate_subtotal(); 
    $amt = vtprd_format_money_element($subTotal);        
          
    if ($msgType == 'html')  {
      $output .= '<tr>';
        $output .= '<td colspan="2">' . $vtprd_setup_options['checkout_credit_subtotal_title'] .'</td>';
        $output .= '<td>' . $amt .'</td>';
      $output .= '</tr>';   
    } else {
      $output .= $vtprd_setup_options['checkout_credit_subtotal_title'];
      $output .= '  ';
      $output .= $amt;
      $output .= "\r\n";        
    }
    return $output;  
  }
 
     
	function vtprd_email_cart_discount_total($msgType) {
    global $vtprd_cart, $vtprd_rules_set, $vtprd_setup_options;

      $output;
  
      
      $amt = vtprd_format_money_element($vtprd_cart->yousave_cart_total_amt);      
          
    if ($msgType == 'html')  {
      $output .= '<tr>';
        $output .= '<td colspan="2">' . $vtprd_setup_options['checkout_credit_total_title'] .'</td>';
        $output .= '<td>' . $vtprd_setup_options['checkout_credit_detail_label'] . ' ' .$amt .'</td>';
      $output .= '</tr>';   
    } else {      
      $output .= $vtprd_setup_options['checkout_credit_total_title'];          //Discount Total
      $output .= $amt ;
      $output .= "\r\n";        
    }
    
    return $output;  
    
  }
   
	
  //***************************************
  // Subtotal with Discount:  (email)
  //***************************************
  function vtprd_email_new_cart_checkout_subtotal_line($msgType) {
    global $vtprd_cart, $wpsc_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule, $vtprd_setup_options;   

      $output;
   
      // for wpec $vtprd_cart->cart_original_total_amt is not accurate - use wpec's own routine
      //$subTotal = $vtprd_cart->cart_original_total_amt - $vtprd_cart->yousave_cart_total_amt;    //show as a credit
      
      $subTotal  = $wpsc_cart->calculate_subtotal();
      
      //*****************************
      //No longer used - $subTotal -= $vtprd_cart->yousave_cart_total_amt;
      //*****************************
      $subTotal -= $vtprd_cart->cart_discount_subtotal;   //may or may not contain the coupon amount, depending on passed value calling function
      
      $amt = vtprd_format_money_element($subTotal);

    if ($msgType == 'html')  {
      $output .= '<tr>';
        $output .= '<td colspan="2">' . $vtprd_setup_options['checkout_new_subtotal_label'] .'</td>';
        $output .= '<td>' . $amt .'</td>';
      $output .= '</tr>';
    } else {
      $output .= $vtprd_setup_options['checkout_new_subtotal_label'];
      $output .= '  '; 
      $output .= $amt;
      $output .= "\r\n";        
    }
    
    return $output; 
  }  
  
    
  /* ************************************************
  **   get current page url
  *************************************************** */ 
   function vtprd_currPageURL() {
     $pageURL = 'http';
     if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
        $pageURL .= "://";
     if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
     } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
     }
     return $pageURL;
  } 
  
  
  function vtprd_numberOfDecimals($value) {
      if ((int)$value == $value) {
          return 0;
      }
      else if (! is_numeric($value)) {
          // throw new Exception('numberOfDecimals: ' . $value . ' is not a number!');
          return false;
      }
  
      return strlen($value) - strrpos($value, '.') - 1;  
  }

   function vtprd_print_rule_full_msg($i) { 
    global $vtprd_rules_set;
    $output  = '<span  class="vtprd-full-messages" id="vtprd-category-deal-msg' . $vtprd_rules_set[$i]->post_id . '">';
    $output .= stripslashes($vtprd_rules_set[$i]->discount_product_full_msg);
    $output .= '</span>'; 
    return $output;    
   }  


  // ****************  
  // Date Validity Rule Test
  // ****************             
   function vtprd_rule_date_validity_test($i) {  
       global $vtprd_rules_set;

       switch( $vtprd_rules_set[$i]->rule_on_off_sw_select ) {
          case 'on':  //continue, use scheduling dates
            break;
          case 'off': //rule is always off!!!
              return false;
            break;
          case 'onForever': //rule is always on!!
              return true;
            break;
        }

       $today = date("Y-m-d");
       
       for($t=0; $t < sizeof($vtprd_rules_set[$i]->periodicByDateRange); $t++) {
          if ( ($today >= $vtprd_rules_set[$i]->periodicByDateRange[$t]['rangeBeginDate']) &&
               ($today <= $vtprd_rules_set[$i]->periodicByDateRange[$t]['rangeEndDate']) ) {
             return true;  
          }
       } 
        
       return false; //marks test as valid
   }   


  /* ************************************************
  *    PRODUCT META INCLUDE/EXCLUDE RULE ID LISTS
  *       Meta box added to PRODUCT in rules-ui.php 
  *             updated in pricing-deals.php    
  * ************************************************               
  **   Products can be individually added to two lists:
  *       Include only list - **includes** the product in a rule population 
  *         *only" if:
  *           (1) The product already participates in the rule
  *           (2) The product is in the include only rule list 
*       Exclude list - excludes the product in a rule population 
  *         *only" if:
  *           (1) The product already participates in the rule
  *           (2) The product is in the exclude rule list        
  *************************************************** */  

  //depending on the switch setting, this will be either include or exclude - but from the function's
  //  point of view, it doesn't matter...
  function vtprd_fill_include_exclude_lists($checked_list = NULL) { 
      global $wpdb, $post, $vtprd_setup_options;

      $varsql = "SELECT posts.`id`
            			FROM `".$wpdb->posts."` AS posts			
            			WHERE posts.`post_status` = 'publish' AND posts.`post_type`= 'vtprd-rule'";                    
    	$rule_id_list = $wpdb->get_col($varsql);

      //Include or Exclude list
      foreach ($rule_id_list as $rule_id) {     //($rule_ids as $rule_id => $info)
          $post = get_post($rule_id);
          $output  = '<li id="inOrEx-li-' .$rule_id. '">' ;
          $output  .= '<label class="selectit inOrEx-list-checkbox-label">' ;
          $output  .= '<input id="inOrEx-input-' .$rule_id. '" class="inOrEx-list-checkbox-class" ';
          $output  .= 'type="checkbox" name="includeOrExclude-checked_list[]" ';
          $output  .= 'value="'.$rule_id.'" ';
          $check_found = 'no';
          if ($checked_list) {
              if (in_array($rule_id, $checked_list)) {   //if variation is in previously checked_list   
                 $output  .= 'checked="checked"';
                 $check_found = 'yes';
              }                
          }
          $output  .= '>'; //end input statement
          $output  .= '&nbsp;' . $post->post_title;
          $output  .= '</label>';            
          $output  .= '</li>';
          echo  $output ;
       }
       
      return;   
  }
   
  function vtprd_set_selected_timezone() {
   global $vtprd_setup_options;
    //set server timezone to Store local for date processing
    switch( $vtprd_setup_options['use_this_timeZone'] ) {
      case 'none':
      case 'keep':
        break;
      default:
          $useThisTimeZone = $vtprd_setup_options['use_this_timeZone'];
          date_default_timezone_set($useThisTimeZone);
        break;
    }
  }

	//this routine only gets previouosly-stored session info
  function vtprd_maybe_get_product_session_info($product_id) {
    global $vtprd_info;    
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }  
    // ********************************************************
    //this routine is also called during cart processing.             
    //  if so, get the session info if there, MOVE it to VTPRD_INFO and exit
    // ********************************************************
    if(isset($_SESSION['vtprd_product_session_info_'.$product_id])) {      
      $vtprd_info['product_session_info'] = $_SESSION['vtprd_product_session_info_'.$product_id];
    }  else {     
      return false;
    }
    //Only a product with an active 'display' rule will return a price > 0...
    if ($vtprd_info['product_session_info']['product_unit_price'] > 0) {   
      return true;
    } else {    
      return false;
    }

           
  }  
          
   /* ************************************************
  **  get display session info and MOVE to $vtprd_info['product_session_info']
  *  First time go to the DB.
  *  2nd thru nth go to session variable...
  *    If the ID is a Variation (only comes realtime from AJAX), the recompute is run to refigure price.
  *    
  * //$cart_processing_sw: 'yes' => only get the session info
  *                        'no'  => only get the session info
  *             
  *************************************************** */
	function vtprd_get_product_session_info($product_id, $price=null){   //PRICE only comes from  parent-cart-validation function vtprd_show_product_catalog_price
    global $post, $vtprd_info;
//echo ' cv001a product_id= ' . $product_id. ' price= ' .$price. '<br>' ; //mwnt    
    //store product-specific session info
    if(!isset($_SESSION)){
      session_start();
      header("Cache-Control: no-cache");
      header("Pragma: no-cache");
    }  
    

    //if already in the session variable... => this routine can be called multiple times in displaying a single catalog price.  check first if already done.
    if(isset($_SESSION['vtprd_product_session_info_'.$product_id])) {
       $vtprd_info['product_session_info'] = $_SESSION['vtprd_product_session_info_'.$product_id];   
      //will be a problem in Ajax...
      $current_time_in_seconds = time();
      $user_role = vtprd_get_current_user_role(); 
      if ( ( ($current_time_in_seconds - $vtprd_info['product_session_info']['session_timestamp_in_seconds']) > '3600' ) ||     //session data older than 60 minutes
           (  $user_role != $vtprd_info['product_session_info']['user_role']) ) {    //current user role not the same as prev
        vtprd_apply_rules_to_single_product($product_id, $price);
        //reset stored role to current
        $vtprd_info['product_session_info']['user_role'] = $user_role;
      }         
    } else { 
       //First time obtaining the info, also moves the data to $vtprd_info       
      vtprd_apply_rules_to_single_product($product_id, $price);
      // vtprd_apply_rules_to_vargroup_or_single($product_id, $price);        
    } 

    
    //If the correct discount already computed, then nothing further needed...
    if ($vtprd_info['product_session_info']['product_unit_price'] == $price) {
      return;
    }

    // *****************
    //if this is the 2nd thru nth call, $price value passed in may be different (if product has a product sale price), reapply percent in all cases...
    // *****************
    if ($price > 0) {
      vtprd_recompute_discount_price($product_id, $price);
    }        
//echo ' price after refigure= ' . $vtprd_info['product_session_info']['product_discount_price']. '<br>';

    return;
  } 
 


  /* ************************************************
  **   Apply Rules to single product + store as session info
  *************************************************** */
	function vtprd_apply_rules_to_single_product($product_id, $price=null){     //passed data from wpsc_price
 
    global $post, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set, $vtprd_rule;

    vtprd_set_selected_timezone();
    vtprd_load_vtprd_cart_for_single_product_price($product_id, $price);

    $vtprd_info['current_processing_request'] = 'display';
    $vtprd_apply_rules = new VTPRD_Apply_Rules; 

    //also moves the data to $vtprd_info
    vtprd_move_vtprd_cart_to_session($product_id);
    //return formatted price; if discounted, store price, orig price and you_save in session id
    //  if no discount, formatted DB price returned, no session variable stored
      
    //price result stored in $vtprd_info['product_session_info'] 
    return; 
      
  } 
  
  /* ************************************************
  **   Post-purchase discount logging
  *************************************************** */	
	function vtprd_save_discount_purchase_log($cart_parent_purchase_log_id) {   
      global $post, $wpdb, $wpsc_cart, $vtprd_cart, $vtprd_cart_item, $vtprd_info, $vtprd_rules_set;  

      if ($vtprd_cart->yousave_cart_total_amt == 0) {
        return;
      }
      //Create PURCHASE LOG row - 1 per cart
      $purchaser_ip_address = $vtprd_info['purchaser_ip_address']; 
      $next_id; //supply null value for use with autoincrement table key
 
      $ruleset_object = serialize($vtprd_rules_set); 
      $cart_object    = serialize($vtprd_cart);
      
      $wpdb->query("INSERT INTO `".VTPRD_PURCHASE_LOG."` (`id`,`cart_parent_purchase_log_id`,`purchaser_name`,`purchaser_ip_address`,`purchase_date`,`cart_total_discount_currency`,`ruleset_object`,`cart_object`) 
        VALUES ('{$next_id}','{$cart_parent_purchase_log_id}','{$vtprd_cart->billto_name}','{$purchaser_ip_address}','{$date}','{$vtprd_cart->yousave_cart_total_amt}','{$ruleset_object}','{$cart_object}' );");

      $purchase_log_row_id = $wpdb->get_var("SELECT LAST_INSERT_ID() AS `id` FROM `".VTPRD_PURCHASE_LOG."` LIMIT 1");

      foreach($vtprd_cart->cart_items as $key => $cart_item) {  
        if ($cart_item->yousave_total_amt > 0 ) { 
          //Create PURCHASE LOG PRODUCT row - 1 per product
          $wpdb->query("INSERT INTO `".VTPRD_PURCHASE_LOG_PRODUCT."` (`id`,`purchase_log_row_id`,`product_id`,`product_title`,`cart_parent_purchase_log_id`,
                `product_orig_unit_price`,`product_total_discount_units`,`product_total_discount_currency`,`product_total_discount_percent`) 
            VALUES ('{$next_id}','{$purchase_log_row_id}','{$cart_item->product_id}','{$cart_item->product_name}','{$cart_parent_purchase_log_id}',
                '{$cart_item->db_unit_price}','{$cart_item->yousave_total_qty}','{$cart_item->yousave_total_amt}','{$cart_item->yousave_total_pct}' );");
      
          $purchase_log_product_row_id = $wpdb->get_var("SELECT LAST_INSERT_ID() AS `id` FROM `".VTPRD_PURCHASE_LOG_PRODUCT."` LIMIT 1"); 
          foreach($cart_item->yousave_by_rule_info as $key => $yousave_by_rule) {
            $ruleset_occurrence = $yousave_by_rule['ruleset_occurrence'] ;
            $rule_id = $vtprd_rules_set[$ruleset_occurrence]->post_id;
            $discount_applies_to_qty = $yousave_by_rule['discount_applies_to_qty'];
            $yousave_amt = $yousave_by_rule['yousave_amt'];
            $yousave_pct = $yousave_by_rule['yousave_pct'];        
            //Create PURCHASE LOG PRODUCT RULE row  -  1 per product/rule combo
            $wpdb->query("INSERT INTO `".VTPRD_PURCHASE_LOG_PRODUCT_RULE."` (`id`,`purchase_log_product_row_id`,`product_id`,`rule_id`,`cart_parent_purchase_log_id`,
                  `product_rule_discount_units`,`product_rule_discount_dollars`,`product_rule_discount_percent`) 
              VALUES ('{$next_id}','{$purchase_log_product_row_id}','{$cart_item->product_id}','{$rule_id}','{$cart_parent_purchase_log_id}',
                  '{$discount_applies_to_qty}','{$yousave_amt}','{$yousave_pct}' );");              
          }    
        }
      }
      
           
  }
    
     
  /* ************************************************
  **   Recompute Discount for VARIATION Display rule AJAX  
  *************************************************** */
  function vtprd_recompute_discount_price($variation_id, $price){
      global $vtprd_info;  
      
      $yousave_amt = 0;
      $sizeof_pricing_array = sizeof($vtprd_info['product_session_info']['pricing_by_rule_array']);
      for($y=0; $y < $sizeof_pricing_array; $y++) {
        
        $apply_this = 'yes';
        
        $pricing_rule_applies_to_variations_array = $vtprd_info['product_session_info']['pricing_by_rule_array'][$y]['pricing_rule_applies_to_variations_array'];
        
        if (sizeof($pricing_rule_applies_to_variations_array) > 0) {
           if (in_array($variation_id, $pricing_rule_applies_to_variations_array )) {
             $apply_this = 'yes';
           } else {
             $apply_this = 'no';  //this rule is variation-specific, and the passed id is not!! in the group - skip
           }
        }
        
        if ($apply_this == 'yes') {
          if ($vtprd_info['product_session_info']['pricing_by_rule_array'][$y]['pricing_rule_currency_discount'] > 0) {
            $yousave_amt +=  $vtprd_info['product_session_info']['pricing_by_rule_array'][$y]['pricing_rule_currency_discount'];
          } else {
            $PercentValue =  $vtprd_info['product_session_info']['pricing_by_rule_array'][$y]['pricing_rule_percent_discount'];
            $yousave_amt +=  vtprd_compute_percent_discount($PercentValue, $price);
          }
        }
        
      }  //end for loop
      
      $vtprd_info['product_session_info']['product_discount_price'] = $price - $yousave_amt;
      //                                  ************************
       
     return;
  }
  
   
  /* ************************************************
  **   Compute percent discount for VARIATION realtime
  *************************************************** */
  function vtprd_compute_percent_discount($PercentValue, $price){
    //from apply-rules.php   function vtprd_compute_each_discount
      $percent_off = $PercentValue / 100;          
      
      $discount_2decimals = bcmul($price , $percent_off , 2);
    
      //compute rounding
      $temp_discount = $price * $percent_off;
      $rounding = $temp_discount - $discount_2decimals;
      if ($rounding > 0.005) {
        $discount = $discount_2decimals + .01;
      }  else {
        $discount = $discount_2decimals;
      }
           
     return $discount;
  }
    
  
  function vtprd_checkDateTime($date) {
    if (date('Y-m-d', strtotime($date)) == $date) {
        return true;
    } else {
        return false;
    }
  }

  /* ************************************************
  **   Change the default title in the rule custom post type
  *************************************************** */
  function vtprd_change_default_title( $title ){
     $screen = get_current_screen();
     if  ( 'vtprd-rule' == $screen->post_type ) {
          $title = 'Enter Rule Title';
     }
     return $title;
  }
  add_filter( 'enter_title_here', 'vtprd_change_default_title' ); 

  /* ************************************************
  **  Disable draggable metabox in the rule custom post type
  *************************************************** */
  function vtprd_disable_drag_metabox() {
     $screen = get_current_screen();
     if  ( 'vtprd-rule' == $screen->post_type ) { 
       wp_deregister_script('postbox');
     }
  }
  add_action( 'admin_init', 'vtprd_disable_drag_metabox' ); 
  
  /* ************************************************
  **  Display DB queries, time spent and memory consumption  IF  debugging_mode_on
  *************************************************** */
  function vtprd_performance( $visible = false ) {
    if ( $vtprd_setup_options['debugging_mode_on'] == 'yes' ){ 
      $stat = sprintf(  '%d queries in %.3f seconds, using %.2fMB memory',
          get_num_queries(),
          timer_stop( 0, 3 ),
          memory_get_peak_usage() / 1024 / 1024
          );
      echo  $visible ? $stat : "<!-- {$stat} -->" ;
    }
}
 add_action( 'wp_footer', 'vtprd_performance', 20 );
