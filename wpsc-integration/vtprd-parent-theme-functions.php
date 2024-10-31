<?php
/*

*/
  //====================================
  //SHORTCODE: pricing_deal_store_msgs
  //====================================
  
  //shortcode documentation here - wholestore
  //WHOLESTORE MESSAGES SHORTCODE     'vtprd_pricing_deal_store_msgs'
  /* ================================================================================= 
  => rule_id_list is OPTIONAL - Show msgs only for these rules => if not supplied, all msgs will be produced
  
   A list can be a single code [ example: rule_id_list => '123' }, or a group of codes [ example: rule_id_list => '123,456,789' }  with no spaces in the list
  A switch can be sent to just display the whole store messages
  
  As a shortcode:
    [pricing_deal_whole_store_msgs  rule_id_list="10,15,30"]
  
  As a template code with a passed variable containing the list:
    $rule_id_list="10,15,30"; //or it is a generated list 
    echo do_shortcode('[pricing_deal_store_msgs rule_id_list=' .$rule_id_list. ']');
        OR
    echo do_shortcode('[pricing_deal_store_msgs  rule_id_list="10,15,30"]');
    echo do_shortcode('[pricing_deal_store_msgs  wholestore_msgs_only="yes"  rule_id_list="10,15,30" ]');     

  ====================================
  PARAMETER DEFAULTS and VALID VALUES
  ==================================== 
        msg_type => 'cart',            //'cart' (default) / 'catalog' / 'all' ==> "cart" msgs = cart rules type, "catalog" msgs = realtime catalog rules type        
                                       // AND (implicit)
        wholestore_msgs_only => 'no',  //'yes' / 'no' (default) 
                                       // AND [implicit]
                                       //   (  
                                       // OR  [implicit]
        role_name_list => '',          //'Administrator,Customer,Not logged in (just visiting),Member'  
                                       // OR  [implicit]
        rule_id_list => '',            //'123,456,789'     
                                       // OR  [implicit]
        product_id_list => ''          //'123,456,789'    (ONLY WORKS in the LOOP, or if the Post is available )
                                       //   )       
  ================================================================================= */
  //
  add_shortcode('pricing_deal_store_msgs','vtprd_pricing_deal_store_msgs');   
  function vtprd_pricing_deal_store_msgs($atts) {
    global $vtprd_rules_set, $post, $vtprd_setup_options;
    extract(shortcode_atts (
      array (
        msg_type => 'cart',            //'cart' (default) / 'catalog' / 'all' ==> "cart" msgs = cart rules type, "catalog" msgs = realtime catalog rules type        
                                       // AND (implicit)
        wholestore_msgs_only => 'no',  //'yes' / 'no' (default) 
                                       // AND [implicit]
                                       //   (  
                                       // OR  [implicit]
        role_name_list => '',          //'Administrator,Customer,Not logged in (just visiting),Member'  
                                       // OR  [implicit]
        rule_id_list => '',            //'123,456,789'     
                                       // OR  [implicit]
        product_id_list => ''          //'123,456,789'    (ONLY WORKS in the LOOP, or if the Post is available )
                                       //   )                                  
      ), $atts));  //override default value with supplied parameters...
    
    vtprd_set_selected_timezone();


    $output = '<div class="vtprd-store-deal-msg-area">';
    $msg_counter = 0;
    
    $vtprd_rules_set = get_option( 'vtprd_rules_set' );
    
    $sizeof_rules_set = sizeof($vtprd_rules_set);
    for($i=0; $i < $sizeof_rules_set; $i++) { 

      //BEGIN skip tests      
      if ( $vtprd_rules_set[$i]->rule_status != 'publish' ) {
        continue;
      }      

      $rule_is_date_valid = vtprd_rule_date_validity_test($i);
      if (!$rule_is_date_valid) {
         continue;
      }  
      //IP is immediately available, check against Lifetime limits
      if ( (defined('VTPRD_PRO_DIRNAME')) && ($vtprd_setup_options['use_lifetime_max_limits'] == 'yes') )  {  
        $rule_has_reached_lifetime_limit = vtprd_rule_lifetime_validity_test($i,'shortcode');
        if ($rule_has_reached_lifetime_limit) {
           continue;
        }
      }

      $exit_stage_left = 'no';
      switch( $msg_type ) {
        case 'cart':
          if ($vtprd_rules_set[$i]->rule_execution_type == 'display') {
            $exit_stage_left = 'yes';
          }
          break;
        case 'catalog':                                                                                   
          if ($vtprd_rules_set[$i]->rule_execution_type == 'cart') {
            $exit_stage_left = 'yes';
          }  
          break;
        case 'all':
          break; 
      }     
      if ($exit_stage_left == 'yes') {
         continue;
      }      
    
      if ($wholestore_msgs_only == 'yes') {
        if ( ($vtprd_rules_set[$i]->inPop != 'wholeStore') && ($vtprd_rules_set[$i]->actionPop != 'wholeStore' ) ) {
          continue;
        }
      } 
      
      if ($role_name_list > ' ') {
        $userRole = vtprd_get_current_user_role();
        $userRole_name = translate_user_role( $userRole );
        $role_name_list_array = explode(",", $role_name_list);   //remove comma separator, make list an array
        if (!in_array($userRole_name, $role_name_list_array)) {
          continue;
        }
      }
      //END skip tests
      
      //INclusion test begin  -  all are implicit 'or' functions     
      
      //if no lists are present, then the skip tests are all there is.  Print the msg and exit.
      if (($rule_id_list <= ' ' ) && ($product_id_list <= ' ') && ($product_id_list <= '')) { 
        $msg_counter++;
        $output .= vtprd_store_deal_msg($i);  //Print
        continue;      
      }
      
      if ($rule_id_list > ' ') {
        $rule_id_list_array = explode(",", $rule_id_list);   //remove comma separator, make list an array
        if (in_array($vtprd_rules_set[$i]->post_id, $rule_id_list_array)) {
          $msg_counter++;
          $output .= vtprd_store_deal_msg($i);  //Print
          continue;
        }
      } 
            
          //**********************************
          // ONLY works in the loop
          //**********************************
      if ($product_id_list > ' ')  {    
        $product_id_list_array = explode(",", $product_id_list);   //remove comma separator, make list an array
        // $post->ID = $product_id in this instance
        if (in_array($post->ID, $product_id_list_array)) {
          $msg_counter++;
          $output .= vtprd_store_deal_msg($i);  //Print
          continue;
        }
      }      
      //PRINT test end     
       
    } //end 'for' loop
    
    if ($msg_counter == 0) {
      return;
    }

    //close owning div 
    $output .= '</div>';
  //  vtprd_enqueue_front_end_css();
        
    return $output;  
  }
  
  function vtprd_store_deal_msg($i) {
    global $vtprd_rules_set;
    $output  = '<span class="vtprd-store-deal-msg" id="vtprd-store-deal-msg-' . $vtprd_rules_set[$i]->post_id . '">';
    $output .= stripslashes($vtprd_rules_set[$i]->discount_product_full_msg);
    $output .= '</span>';
    $output .= '<span class="vtprd-line-skip-with-display-block"></span>';
    return $output;
  }
 //====================================
 //SHORTCODE: pricing_deal_category_msgs
 //==================================== 
 
 //shortcode documentation here - category
 //STORE CATEGORY MESSAGES SHORTCODE    vtprd_pricing_deal_category_msgs
  /* ================================================================================= 
  => either prodcat_id_list or rulecat_id_list or rule_id_list is REQUIRED
  => if both lists supplied, the shortcode will find rule msgs in EITHER prodcat_id_list OR rulecat_id_list OR rule_id_list.
  
        A list can be a single code [ example: rule_id_list => '123' }, or a group of codes [ example: rule_id_list => '123,456,789' }  with no spaces in the list 
        
        REQUIRED => Data MUST be sent in ONE of the list parameters, or nothing is returned.
        
  As a shortcode:
    [pricing_deal_category_msgs  prodcat_id_list="10,15,30"  rulecat_id_list="12,17,32"]
  
  As a template code with a passed variable containing the list:
    to show only the current category messages, for example:
    GET CURRENT CATEGORY 
    
    if (is_category()) {
      $prodcat_id_list = get_query_var('cat');
      echo do_shortcode('[pricing_deal_category_msgs  prodcat_id_list=' .$prodcat_id_list. ']');
    }
        OR 
    USING A HARDCODED CAT LIST   
    echo do_shortcode('[pricing_deal_category_msgs  prodcat_id_list="10,15,30" ]');

  ====================================
  PARAMETER DEFAULTS and VALID VALUES
  ====================================
          msg_type => 'cart',     //'cart' (default) / 'catalog' / 'all' ==> "cart" msgs = cart rules type, "catalog" msgs = realtime catalog rules type 
                                // AND [implicit]                                               
                                //   ( 
        prodcat_id_list => '',  //'123,456,789'      only active if in this list
                                // OR  [implicit]
        rulecat_id_list => ''   //'123,456,789'      only active if in this list
                                //   )                      
  ================================================================================= */
  add_shortcode('pricing_deal_category_msgs','vtprd_pricing_deal_category_msgs');   
  function vtprd_pricing_deal_category_msgs($atts) {
    global $vtprd_rules_set, $vtprd_setup_options;
    extract(shortcode_atts (
      array (
        msg_type => 'cart',     //'cart' (default) / 'catalog' / 'all' ==> "cart" msgs = cart rules type, "catalog" msgs = realtime catalog rules type 
                                // AND [implicit]                                               
                                //   ( 
        prodcat_id_list => '',  //'123,456,789'      only active if in this list
                                // OR  [implicit]
        rulecat_id_list => ''   //'123,456,789'      only active if in this list
                                //   ) 
      ), $atts));               
    
    vtprd_set_selected_timezone();
    
    if ( ($prodcat_id_list <= ' ') && ($rulecat_id_list <= ' ') && ($rule_id_list <= ' ') ) {   //MUST supply one or the other
       return;
    }
    
    $vtprd_rules_set = get_option( 'vtprd_rules_set' );

    $output = '<div class="vtprd-category-deal-msg-area">';
    $msg_counter = 0;
    
    $sizeof_rules_set = sizeof($vtprd_rules_set);
    for($i=0; $i < $sizeof_rules_set; $i++) { 

      if ( $vtprd_rules_set[$i]->rule_status != 'publish' ) {
        continue;
      }      
      
      $rule_is_date_valid = vtprd_rule_date_validity_test($i);
      if (!$rule_is_date_valid) {
         continue;
      }  
      if ( (defined('VTPRD_PRO_DIRNAME')) && ($vtprd_setup_options['use_lifetime_max_limits'] == 'yes') )  {
      //IP is immediately available, check against Lifetime limits
        $rule_has_reached_lifetime_limit = vtprd_rule_lifetime_validity_test($i,'shortcode');
        if ($rule_has_reached_lifetime_limit) {
           continue;
        }
      }

      $exit_stage_left = 'no';
      switch( $msg_type ) {
        case 'cart':
          if ($vtprd_rules_set[$i]->rule_execution_type == 'display') {
            $exit_stage_left = 'yes';
          }
          break;
        case 'catalog':                                                                                   
          if ($vtprd_rules_set[$i]->rule_execution_type == 'cart') {
            $exit_stage_left = 'yes';
          }  
          break;
        case 'all':
          break; 
      }     
      if ($exit_stage_left == 'yes') {
         continue;
      }      

      //the rest are implied 'or' relationships


      if ($prodcat_id_list > ' ') {
        $prodcat_id_list_array = explode(",", $prodcat_id_list);   //remove comma separator, make list an array
        if ( ( array_intersect($vtprd_rules_set[$i]->prodcat_in_checked,  $prodcat_id_list_array ) ) ||
             ( array_intersect($vtprd_rules_set[$i]->prodcat_out_checked, $prodcat_id_list_array ) ) ) {  
           $msg_counter++;
           $output .= vtprd_category_deal_msg($i);
            continue; //only output the msg once 
        }
      } 

      if ($rulecat_id_list > ' ') {
        $rulecat_id_list_array = explode(",", $rulecat_id_list);   //remove comma separator, make list an array
        if ( ( array_intersect($vtprd_rules_set[$i]->rulecat_in_checked,  $rulecat_id_list_array ) ) ||
             ( array_intersect($vtprd_rules_set[$i]->rulecat_out_checked, $rulecat_id_list_array ) ) ) {  
           $msg_counter++;
           $output .= vtprd_category_deal_msg($i);
            continue; //only output the msg once 
        }
      }
      
    }
    
    if ($msg_counter == 0) {
      return;
    }

    //close owning div 
    $output .= '</div>';  
 //   vtprd_enqueue_front_end_css();
    
    return $output;  
  }
  
  function vtprd_category_deal_msg($i) {
    global $vtprd_rules_set;
    $output  = '<span class="vtprd-category-deal-msg" id="vtprd-category-deal-msg-' . $vtprd_rules_set[$i]->post_id . '">';
    $output .= stripslashes($vtprd_rules_set[$i]->discount_product_full_msg);
    $output .= '</span>';
    $output .= '<span class="vtprd-line-skip-with-display-block"></span>';
    return $output;
  }
//====================================
 //SHORTCODE: pricing_deal_advanced_msgs
 //==================================== 
 
 //shortcode documentation here - advanced
 //ADVANCED MESSAGES SHORTCODE    vtprd_pricing_deal_advanced_msgs
  /* ================================================================================= 
   
        A list can be a single code [ example: rule_id_list => '123' }, or a group of codes [ example: rule_id_list => '123,456,789' }  with no spaces in the list 
        
        NB - please be careful to follow the comma use exactly as described!!!  
        
  As a shortcode:
    [pricing_deal_advanced_msgs  
        grp1_msg_type => 'cart'
        grp1_and_or_wholestore_msgs_only => 'and'
        grp1_wholestore_msgs_only => 'no'
          and_or_grp1_to_grp2 => 'and'
        grp2_rule_id_list => ''
        grp2_and_or_role_name_list => 'and'
        grp2_role_name_list => ''
          and_or_grp2_to_grp3 => 'and'
        grp3_prodcat_id_list => ''
        grp3_and_or_rulecat_id_list => 'or'
        grp3_rulecat_id_list => ''   
    ]
  
  As a template code with passed variablea
    echo do_shortcode('[pricing_deal_advanced_msgs  
        grp1_msg_type => 'cart'
        grp1_and_or_wholestore_msgs_only => 'and'
        grp1_wholestore_msgs_only => 'no'
          and_or_grp1_to_grp2 => 'and'
        grp2_rule_id_list => ''
        grp2_and_or_role_name_list => 'and'
        grp2_role_name_list => ''
          and_or_grp2_to_grp3 => 'and'
        grp3_prodcat_id_list => ''
        grp3_and_or_rulecat_id_list => 'or'
        grp3_rulecat_id_list => '' 
    ]');
  
  ====================================
  PARAMETER DEFAULTS and VALID VALUES
  ====================================
                                                    //   (  grp 1
        grp1_msg_type => 'cart',                   //'cart' (default) / 'catalog' / 'all' ==> "cart" msgs = cart rules type, "catalog" msgs = realtime catalog rules type  
        grp1_and_or_wholestore_msgs_only => 'and', //'and'(default) / 'or' 
        grp1_wholestore_msgs_only => 'no',         //'yes' / 'no' (default)   only active if rule active for whole store
                                                   //   )
        and_or_grp1_to_grp2 => 'and',              //'and'(default) / 'or' 
                                                   //   (  grp 2
        grp2_rule_id_list => '',                   //'123,456,789'          only active if in this list
        grp2_and_or_role_name_list => 'and',       //'and'(default) / 'or' 
        grp2_role_name_list => '',                 //'Administrator,Customer,Not logged in (just visiting),Member'         Only active if in this list 
                                                   //   )
        and_or_grp2_to_grp3 => 'and',              //'and'(default) / 'or' 
                                                   //   (  grp 3
        grp3_prodcat_id_list => '',                //'123,456,789'      only active if in this list
        grp3_and_or_rulecat_id_list => 'or',       //'and' / 'or'(default) 
        grp3_rulecat_id_list => ''                 //'123,456,789'      only active if in this list
                                                   //   )   
  ================================================================================= */
  add_shortcode('pricing_deal_advanced_msgs','vtprd_pricing_deal_advanced_msgs');   
  function vtprd_pricing_deal_advanced_msgs($atts) {
    global $vtprd_rules_set, $vtprd_setup_options;
    extract(shortcode_atts (
      array (
                                                   //   (  grp 1
        grp1_msg_type => 'cart',                   //'cart' (default) / 'catalog' / 'all' ==> "cart" msgs = cart rules type, "catalog" msgs = realtime catalog rules type  
        grp1_and_or_wholestore_msgs_only => 'and', //'and'(default) / 'or' 
        grp1_wholestore_msgs_only => 'no',         //'yes' / 'no' (default)   only active if rule active for whole store
                                                   //   )
        and_or_grp1_to_grp2 => 'and',              //'and'(default) / 'or' 
                                                   //   (  grp 2
        grp2_rule_id_list => '',                   //'123,456,789'          only active if in this list
        grp2_and_or_role_name_list => 'and',       //'and'(default) / 'or' 
        grp2_role_name_list => '',                 //'Administrator,Customer,Not logged in (just visiting),Member'         Only active if in this list 
                                                   //   )
        and_or_grp2_to_grp3 => 'and',              //'and'(default) / 'or' 
                                                   //   (  grp 3
        grp3_prodcat_id_list => '',                //'123,456,789'      only active if in this list
        grp3_and_or_rulecat_id_list => 'or',       //'and' / 'or'(default) 
        grp3_rulecat_id_list => ''                 //'123,456,789'      only active if in this list
                                                   //   )
      ), $atts));  //override default value with supplied parameters...
    
    vtprd_set_selected_timezone();

    $vtprd_rules_set = get_option( 'vtprd_rules_set' );

    $output = '<div class="vtprd-advanced-deal-msg-area">';
    $msg_counter = 0;
//echo 'incoming attributes= ' .$atts. '<br>'; //mwnt 
    $sizeof_rules_set = sizeof($vtprd_rules_set);
    for($i=0; $i < $sizeof_rules_set; $i++) { 
      
      if ( $vtprd_rules_set[$i]->rule_status != 'publish' ) {
        continue;
      }      
            
      $rule_is_date_valid = vtprd_rule_date_validity_test($i);
      if (!$rule_is_date_valid) {
         continue;
      }  
      if ( (defined('VTPRD_PRO_DIRNAME')) && ($vtprd_setup_options['use_lifetime_max_limits'] == 'yes') )  {
      //IP is immediately available, check against Lifetime limits
        $rule_has_reached_lifetime_limit = vtprd_rule_lifetime_validity_test($i,'shortcode');
        if ($rule_has_reached_lifetime_limit) {
           continue;
        }
      }
      
      $status =       array (
        'grp1_msg_type' => '',                  
        'grp1_wholestore_msgs_only' => '',           
        'grp2_rule_id_list' => '',                        
        'grp2_role_name_list' => '',                       
        'grp3_prodcat_id_list' => '',                
        'grp3_rulecat_id_list' => '',
        'grp1' => '',
        'grp2' => '',
        'grp3' => '',
        'total' => ''                 
      );
      
      //SET Status success/failed for each parameter
      switch( $grp1_msg_type ) {
        case 'cart':      
          if ($vtprd_rules_set[$i]->rule_execution_type == 'display') {
            $status['grp1_msg_type'] = 'failed';      
          } else {
            $status['grp1_msg_type'] = 'success';      
          }
          break;
        case 'catalog':                                                                                          
          if ($vtprd_rules_set[$i]->rule_execution_type == 'cart') {
            $status['grp1_msg_type'] = 'failed';          
          } else {
            $status['grp1_msg_type'] = 'success';         
          } 
          break;
        case 'all':
          $status['grp1_msg_type'] = 'success';
          break;
        default:
          $status['grp1_msg_type'] = 'failed';
          break; 
      }     

      if ($grp1_wholestore_msgs_only == 'yes') {
        if ( ($vtprd_rules_set[$i]->inPop == 'wholeStore') || ($vtprd_rules_set[$i]->actionPop == 'wholeStore' ) ) {
          $status['grp1_wholestore_msgs_only'] = 'success';
        } else {
          $status['grp1_wholestore_msgs_only'] = 'failed';
        }
      } else {
        $status['grp1_wholestore_msgs_only'] = 'success';
      }
            
      if ($grp2_role_name_list > ' ') {
        $userRole = vtprd_get_current_user_role();
        $userRole_name = translate_user_role( $userRole );
        $grp2_role_name_list_array = explode(",", $grp2_role_name_list);   //remove comma separator, make list an array
        if (in_array($userRole_name, $grp2_role_name_list_array)) {
          $status['grp2_role_name_list'] = 'success';
        } else {
          $status['grp2_role_name_list'] = 'failed';
        }
      } else {
        $status['grp2_role_name_list'] = 'success';
      }

      if ($grp2_rule_id_list > ' ') {
        $grp2_rule_id_list_array = explode(",", $grp2_rule_id_list);   //remove comma separator, make list an array
        if (in_array($vtprd_rules_set[$i]->post_id, $grp2_rule_id_list_array)) {
          $status['grp2_rule_id_list'] = 'success';
        } else {
          $status['grp2_rule_id_list'] = 'failed';
        }
      } else {
        $status['grp2_rule_id_list'] = 'success';
      }

      if ($grp3_prodcat_id_list > ' ') {
        $grp3_prodcat_id_list_array = explode(",", $grp3_prodcat_id_list);   //remove comma separator, make list an array
        if ( ( array_intersect($vtprd_rules_set[$i]->prodcat_in_checked,  $grp3_prodcat_id_list_array ) ) ||
             ( array_intersect($vtprd_rules_set[$i]->prodcat_out_checked, $grp3_prodcat_id_list_array ) ) ) {  
           $status['grp3_prodcat_id_list'] = 'success'; 
        } else {
           $status['grp3_prodcat_id_list'] = 'failed'; 
        }
      } else {
        $status['grp3_prodcat_id_list'] = 'success';
      }

      if ($grp3_rulecat_id_list > ' ') {
        $grp3_rulecat_id_list_array = explode(",", $grp3_rulecat_id_list);   //remove comma separator, make list an array
        if ( ( array_intersect($vtprd_rules_set[$i]->rulecat_in_checked,  $grp3_rulecat_id_list_array ) ) ||
             ( array_intersect($vtprd_rules_set[$i]->rulecat_out_checked, $grp3_rulecat_id_list_array ) ) ) {  
           $status['grp3_rulecat_id_list'] = 'success'; 
        } else {
           $status['grp3_rulecat_id_list'] = 'failed'; 
        }
      } else {
        $status['grp3_rulecat_id_list'] = 'success';
      }
      
      //Evaluate status settings

      //evaluate grp1
      switch( $grp1_and_or_wholestore_msgs_only ) {
        case 'and':        
            if (($status['grp1_msg_type'] == 'success') &&
                ($status['grp1_wholestore_msgs_only'] == 'success')) {
              $status['grp1'] = 'success';
            } else {
              $status['grp1'] = 'failed';
            }            
          break;
        case 'or':
            if (($status['grp1_msg_type'] == 'success') ||
                ($status['grp1_wholestore_msgs_only'] == 'success')) {
              $status['grp1'] = 'success';  
            } else {
              $status['grp1'] = 'failed';
            }          
          break;
        default:
            $status['grp1'] = 'failed';         
          break;
      } 
      
      //evaluate grp2
      switch( $grp2_and_or_role_name_list ) {
        case 'and': 
            if (($status['grp2_rule_id_list'] == 'success') &&
                ($status['grp2_role_name_list'] == 'success')) {
              $status['grp2'] = 'success';  
            } else {
              $status['grp2'] = 'failed';
            }            
          break;
        case 'or':
            if (($status['grp2_rule_id_list'] == 'success') ||
                ($status['grp2_role_name_list'] == 'success')) {
              $status['grp2'] = 'success';  
            } else {
              $status['grp2'] = 'failed';
            }          
          break;
        default:
            $status['grp2'] = 'failed';         
          break;
      } 

      //evaluate grp3
      switch( $grp3_and_or_rulecat_id_list ) {
        case 'and': 
            if (($status['grp3_prodcat_id_list'] == 'success') &&
                ($status['grp3_rulecat_id_list'] == 'success')) {
              $status['grp3'] = 'success';  
            } else {
              $status['grp3'] = 'failed';
            }            
          break;
        case 'or':
            if (($status['grp3_prodcat_id_list'] == 'success') ||
                ($status['grp3_rulecat_id_list'] == 'success')) {
              $status['grp3'] = 'success';  
            } else {
              $status['grp3'] = 'failed';
            }          
          break;
        default:
            $status['grp3'] = 'failed';         
          break;          
      } 

      //evaluate all 3 groups together
      switch( true ) {
        case ( ($and_or_grp1_to_grp2 == 'and') &&
               ($and_or_grp2_to_grp3 == 'and') ) : 
            if ( ($status['grp1'] == 'success') &&
                 ($status['grp2'] == 'success') &&
                 ($status['grp3'] == 'success') ) {
              $status['total'] = 'success';  
            } else {
              $status['total'] = 'failed';
            }            
          break;
        case ( ($and_or_grp1_to_grp2 == 'and') &&
               ($and_or_grp2_to_grp3 == 'or') ) : 
            if ( (($status['grp1'] == 'success')  &&
                  ($status['grp2'] == 'success')) ||
                  ($status['grp3'] == 'success') ) {
              $status['total'] = 'success';  
            } else {
              $status['total'] = 'failed';
            }            
          break;
        case ( ($and_or_grp1_to_grp2 == 'or') &&
               ($and_or_grp2_to_grp3 == 'and') ) : 
            if ( (($status['grp1'] == 'success')  ||
                  ($status['grp2'] == 'success')) &&
                  ($status['grp3'] == 'success') ) {
              $status['total'] = 'success';  
            } else {
              $status['total'] = 'failed';
            }            
          break;
        case ( ($and_or_grp1_to_grp2 == 'or') &&
               ($and_or_grp2_to_grp3 == 'or') ) : 
            if ( ($status['grp1'] == 'success') ||
                 ($status['grp2'] == 'success') ||
                 ($status['grp3'] == 'success') ) {
              $status['total'] = 'success';  
            } else {
              $status['total'] = 'failed';
            }            
          break;                    
      } 

      if ($status['total'] == 'success') {
        $msg_counter++;
        $output .= '<span class="vtprd-advanced-deal-msg" id="vtprd-advanced-deal-msg-' . $vtprd_rules_set[$i]->post_id . '">';
        $output .= stripslashes($vtprd_rules_set[$i]->discount_product_full_msg);
        $output .= '</span>';
        $output .= '<span class="vtprd-line-skip-with-display-block"></span>';      
      }
      
    } //end 'for' loop
    
    
    if ($msg_counter == 0) {
      return;
    }

    //close owning div 
    $output .= '</div>';
  //  vtprd_enqueue_front_end_css();
    
    return $output;  
  }
  
  /**
  ***************************** 
  *** FOR WPEC VERSION 3.9+ ***
  *****************************  
  COPIED FROM WPSC-INCLUDES/PRODUCT-TEMPLATE.PHP  WPEC VERSION 3.8.10  
 * WPSC The Product Price Display
 *
 * @param  $args  (array)   Array of args.
 * @return        (string)  HTML formatted prices
 *
 * @uses   apply_filters()                      Calls 'wpsc_the_product_price_display_old_price_class' passing class and product ID
 * @uses   apply_filters()                      Calls 'wpsc_the_product_price_display_old_price_amount_class' passing class and product ID
 * @uses   apply_filters()                      Calls 'wpsc_the_product_price_display_price_class' passing class and product ID
 * @uses   apply_filters()                      Calls 'wpsc_the_product_price_display_price_amount_class' passing class and product ID
 * @uses   apply_filters()                      Calls 'wpsc_the_product_price_display_you_save_class' passing class and product ID
 * @uses   apply_filters()                      Calls 'wpsc_the_product_price_display_you_save_amount_class' passing class and product ID
 * @uses   wpsc_product_normal_price()          Get the normal price
 * @uses   wpsc_the_product_price()             Get the current price
 * @uses   wpsc_you_save()                      Get pricing saving
 * @uses   wpsc_product_on_special()            Is product on sale?
 * @uses   wpsc_product_has_variations()        Checks if product has variations
 * @uses   wpsc_product_variation_price_from()  Gets the lowest variation price
 * @uses   wpsc_currency_display()              Display price as currency
 */
function vtprd_the_product_price_display( $args = array() ) {
   global $vtprd_info,  $vtprd_setup_options;
  if ( empty( $args['id'] ) )
		$id = get_the_ID();
	else
		$id = (int) $args['id'];

       
 //-+--+-+-+-+-+-+-+-+-+-+-++--+-+-+-+-+-+-+-+-+-+-++--+-+-+-+-+-+-+-+-+-+-+
 //  if $id is a variation and has a parent, sent the PARENT!!!

  //gets all of the info we need and puts it into 'product_session_info'
  vtprd_get_product_session_info($id);
  
   
 //-+--+-+-+-+-+-+-+-+-+-+-++--+-+-+-+-+-+-+-+-+-+-++--+-+-+-+-+-+-+-+-+-+-+
 //  if $id is a variation, refigure product_yousave_total_amt!!


  //refigure yousave amts for WPEC 
  if (VTPRD_PARENT_PLUGIN_NAME == 'WP E-Commerce') { 
    vtprd_WPEC_recompute_theme_amts();
  }   
 
  
  //if we have no yousave amt, do the default routine and exit
  if ($vtprd_info['product_session_info']['product_yousave_total_amt'] == 0) {
     wpsc_the_product_price_display($args);
     return;
  }

	$defaults = array(
		'id' => $id,
		'old_price_text'   => __( 'Old Price: %s', 'wpsc' ),
		'price_text'       => __( 'Price: %s', 'wpsc' ),
		/* translators     : %1$s is the saved amount text, %2$s is the saved percentage text, %% is the percentage sign */
		'you_save_text'    => __( 'You save: %s', 'wpsc' ),
		'old_price_class'  => 'pricedisplay wpsc-product-old-price ' . $id,
		'old_price_before' => '<p %s>',
		'old_price_after'  => '</p>',
		'old_price_amount_id'     => 'old_product_price_' . $id,
		'old_price_amount_class' => 'oldprice',
		'old_price_amount_before' => '<span class="%1$s" id="%2$s">',
		'old_price_amount_after' => '</span>',
		'price_amount_id'     => 'product_price_' . $id,
		'price_class'  => 'pricedisplay wpsc-product-price ' . $id,
		'price_before' => '<p %s>',
		'price_after' => '</p>',
		'price_amount_class' => 'currentprice pricedisplay ' . $id,
		'price_amount_before' => '<span class="%1$s" id="%2$s">',
		'price_amount_after' => '</span>',
		'you_save_class' => 'pricedisplay wpsc-product-you-save product_' . $id,
		'you_save_before' => '<p %s>',
		'you_save_after' => '</p>',
		'you_save_amount_id'     => 'yousave_' . $id,
		'you_save_amount_class' => 'yousave',
		'you_save_amount_before' => '<span class="%1$s" id="%2$s">',
		'you_save_amount_after'  => '</span>',
		'output_price'     => true,
		'output_old_price' => true,
		'output_you_save'  => true,
	);

	$r = wp_parse_args( $args, $defaults );
	extract( $r );


  $amt = $vtprd_info['product_session_info']['product_list_price'];
  $amt = vtprd_format_money_element($amt);
  $old_price  =  $amt;

  $amt = $vtprd_info['product_session_info']['product_yousave_total_amt'];
  $amt = vtprd_format_money_element($amt);  
  $you_save            = $amt . '! (' . $vtprd_info['product_session_info']['product_yousave_total_pct'] . '%)';
  
  $you_save_percentage = $vtprd_info['product_session_info']['product_yousave_total_pct'];

	// if the product has no variations, these amounts are straight forward...
//	$old_price           = wpsc_product_normal_price( $id );
	$current_price       = wpsc_the_product_price( false, false, $id );
//	$you_save            = wpsc_you_save( 'type=amount' ) . '! (' . wpsc_you_save() . '%)';
//	$you_save_percentage = wpsc_you_save();

//	$show_old_price = $show_you_save = wpsc_product_on_special( $id );
  
  /*
	// but if the product has variations and at least one of the variations is on special, we have
	// a few edge cases...
	if ( wpsc_product_has_variations( $id ) && wpsc_product_on_special( $id ) ) {
		// generally it doesn't make sense to display "you save" amount unless the user has selected
		// a specific variation
		$show_you_save = false;

		$old_price_number = wpsc_product_variation_price_from( $id, array( 'only_normal_price' => true ) );
		$current_price_number = wpsc_product_variation_price_from( $id );

		// if coincidentally, one of the variations are not on special, but its price is equal to
		// or lower than the lowest variation sale price, old price should be hidden, and current
		// price should reflect the "normal" price, not the sales price, to avoid confusion
		if ( $old_price_number == $current_price_number ) {
			$show_old_price = false;
			$current_price = wpsc_product_normal_price( $id );
		}
	}
  */
	// replace placeholders in arguments with correct values
	$old_price_class = apply_filters( 'wpsc_the_product_price_display_old_price_class', $old_price_class, $id );
	$old_price_amount_class = apply_filters( 'wpsc_the_product_price_display_old_price_amount_class', $old_price_amount_class, $id );
	$attributes = 'class="' . esc_attr( $old_price_class ) . '"';
//	if ( ! $show_old_price )
//		$attributes .= ' style="display:none;"';
	$old_price_before = sprintf( $old_price_before, $attributes );
	$old_price_amount_before = sprintf( $old_price_amount_before, esc_attr( $old_price_amount_class ), esc_attr( $old_price_amount_id ) );

	$price_class = 'class="' . esc_attr( apply_filters( 'wpsc_the_product_price_display_price_class', esc_attr( $price_class ), $id )  ) . '"';
	$price_amount_class = apply_filters( 'wpsc_the_product_price_display_price_amount_class', esc_attr( $price_amount_class ), $id );
	$price_before = sprintf( $price_before, $price_class );
	$price_amount_before = sprintf( $price_amount_before, esc_attr( $price_amount_class ), esc_attr( $price_amount_id ) );

	$you_save_class = apply_filters( 'wpsc_the_product_price_display_you_save_class', $you_save_class, $id );
	$you_save_amount_class = apply_filters( 'wpsc_the_product_price_display_you_save_amount_class', $you_save_amount_class, $id );
	$attributes = 'class="' . esc_attr( $you_save_class ) . '"';
//	if ( ! $show_you_save )
//		$attributes .= ' style="display:none;"';
	$you_save_before = sprintf( $you_save_before, $attributes );
	$you_save_amount_before = sprintf( $you_save_amount_before, esc_attr( $you_save_amount_class ), esc_attr( $you_save_amount_id ) );
//	$you_save = wpsc_currency_display ( $you_save );

	$old_price     = $old_price_amount_before . $old_price . $old_price_amount_after;
	$current_price = $price_amount_before . $current_price . $price_amount_after;
	$you_save      = $you_save_amount_before . $you_save . $you_save_amount_after;

	$old_price_text = sprintf( $old_price_text, $old_price );
	$price_text     = sprintf( $price_text, $current_price );
	$you_save_text  = sprintf( $you_save_text, $you_save );

 // if ( $vtprd_setup_options['show_old_price'] == 'yes' ) {
	if (($output_old_price) && ($old_price_text > ' ')) {
		echo $old_price_before . $old_price_text . $old_price_after . "\n";
  }
	if ( $output_price )
		echo $price_before . $price_text . $price_after . "\n";

 // if ( $vtprd_setup_options['show_you_save'] == 'yes' ) {
	if ($output_you_save) {
  	if ($you_save_text > ' ') {
      echo $you_save_before . $you_save_text . $you_save_after . "\n";
    } else  
    if ($vtprd_info['product_session_info']['show_yousave_one_some_msg'] > ' ') {
      echo $vtprd_info['product_session_info']['show_yousave_one_some_msg'] . "\n";
    }
  }  

  return;   
}

/* ************************************************
  **   Template Tag / Filter  -  Get display info for single product   & return list price amt
  *************************************************** */  
  function vtprd_show_product_list_price($product_id=null) {
    global $post, $vtprd_info, $vtprd_setup_options;    
      
    //can only be executed when WPEC version less than 3.8.9
    if( !(version_compare(strval('3.8.9'), strval(WPSC_VERSION), '>') == 1) ) {   //'==1' = 2nd value is lower
       return;
    } 
    
    
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    }
    if (!$product_id) {
      return;
    }    
    $amt = vtprd_get_product_list_price_amt($product_id);

    //CUSTOM function created by CUSTOMER
    if (function_exists('custom_show_product_list_price_amt')) {
      custom_show_product_list_price_amt($product_id, $amt);
      return;
    }

    if ($amt) {
      ?>
				<p class="pricedisplay <?php echo wpsc_the_product_id(); ?>"><?php _e('Old Price', 'wpsc'); ?>: <span class="oldprice" id="old_product_price_<?php echo wpsc_the_product_id(); ?>"><?php echo $amt; ?></span></p>
      <?php
    } else {
      //original code from wpsc-single_product.php
      ?>
      
      <?php if(wpsc_product_on_special()) : ?>
				<p class="pricedisplay <?php echo wpsc_the_product_id(); ?>"><?php _e('Old Price', 'wpsc'); ?>: <span class="oldprice" id="old_product_price_<?php echo wpsc_the_product_id(); ?>"><?php echo wpsc_product_normal_price(); ?></span></p>
			<?php endif; ?>
      
      <?php
    }        
    return;
  }   

    function vtprd_get_product_list_price_amt($product_id=null) {
    global $post, $vtprd_info, $vtprd_setup_options;
        
   //  only applies if one rule set to $rule_execution_type_selected == 'display'.  Carried in an option, set into info...     
    if ($vtprd_info['ruleset_has_a_display_rule'] == 'no') {
      return;
    }
    
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    }   
    
    //routine has been called, but no product_id supplied or available
    if (!$product_id) {
      return;
    }
   
    vtprd_get_product_session_info($product_id);
    
    //if the product does not participate in any rule which allows use at display time, only messages are available - send back nothing
    if ( !$vtprd_info['product_session_info']['product_in_rule_allowing_display']  == 'yes') {
       return;
    }
        
    //refigure yousave amts for WPEC 
    if (VTPRD_PARENT_PLUGIN_NAME == 'WP E-Commerce') { 
      vtprd_WPEC_recompute_theme_amts();
    }   

    
    //list price
    $amt = $vtprd_info['product_session_info']['product_list_price'];
    $amt = vtprd_format_money_element($amt);        
    return $amt;

  }   

  

  /* ************************************************
  ** Template Tag / Filter -  Get display info for single product   & return you save line - amt and pct
  *************************************************** */
	function vtprd_show_product_you_save($product_id=null){
    global $post, $vtprd_setup_options, $vtprd_info;
    
    //can only be executed when WPEC version less than 3.8.9
    if( !(version_compare(strval('3.8.9'), strval(WPSC_VERSION), '>') == 1) ) {   //'==1' = 2nd value is lower
       return;
    }
         
    $pct = vtprd_get_single_product_you_save_pct($product_id); 
    $amt = $vtprd_info['product_session_info']['product_yousave_total_amt'];
    $amt = vtprd_format_money_element($amt);
    
    //CUSTOM function created by CUSTOMER
    if (function_exists('custom_show_single_product_you_save')) {
      custom_show_single_product_you_save($product_id, $pct, $amt);
      return;
    }    

    if ($pct) {
      ?>
				<p class="pricedisplay product_<?php echo wpsc_the_product_id(); ?>"><?php _e('You save', 'wpsc'); ?>: <span class="yousave" id="yousave_<?php echo wpsc_the_product_id(); ?>"><?php echo $amt; ?>! (<?php echo $pct; ?>%)</span></p>
			<?php
    } else {
      //original code from wpsc-single_product.php
      ?>
      
        <?php if(wpsc_product_on_special()) : ?>
					<p class="pricedisplay product_<?php echo wpsc_the_product_id(); ?>"><?php _e('You save', 'wpsc'); ?>: <span class="yousave" id="yousave_<?php echo wpsc_the_product_id(); ?>"><?php echo wpsc_currency_display(wpsc_you_save('type=amount'), array('html' => false)); ?>! (<?php echo wpsc_you_save(); ?>%)</span></p>
				<?php endif; ?>
      
      <?php
     }
    return;
  } 
	
  function vtprd_get_single_product_you_save_pct($product_id=null){
    global $post, $vtprd_setup_options, $vtprd_info;
    
   //  only applies if one rule set to $rule_execution_type_selected == 'display'.  Carried in an option, set into info...     
    if ($vtprd_info['ruleset_has_a_display_rule'] == 'no') {
      return;
    }
    
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    }
            
    //routine has been called, but no product_id supplied or available
    if (!$product_id) {
      return;
    }
        
    vtprd_get_product_session_info($product_id);
    
    //if the product does not participate in any rule which allows use at display time, only messages are available - send back nothing
    if ( !$vtprd_info['product_session_info']['product_in_rule_allowing_display']  == 'yes') {
       return;
    }

    //refigure yousave amts for WPEC 
    if (VTPRD_PARENT_PLUGIN_NAME == 'WP E-Commerce') { 
      vtprd_WPEC_recompute_theme_amts();
    }   

    
    if ( $vtprd_info['product_session_info']['product_yousave_total_pct']  > 0) {
       return $vtprd_info['product_session_info']['product_yousave_total_pct'];
    }
     
    return;
  } 

  
  /* ************************************************
  ** Template Tag / Filter -  full_msg_list  => can be accessed by both display and cart rule types
  *************************************************** */
	function vtprd_show_product_realtime_discount_full_msgs($product_id=null){
    global $post, $vtprd_info;
    
    //can only be executed when WPEC version less than 3.8.9
    if( !(version_compare(strval('3.8.9'), strval(WPSC_VERSION), '>') == 1) ) {   //'==1' = 2nd value is lower
       return;
    } 
    
    if ($post->ID > ' ' ) {
      $product_id = $post->ID;
    } 
        
    //routine has been called, but no product_id supplied or available
    if (!$product_id) {
      return;
    }
   
    vtprd_get_product_session_info($product_id);
 
    //CUSTOM function created by CUSTOMER
    if (function_exists('custom_show_product_realtime_discount_full_msgs')) {
      custom_show_product_realtime_discount_full_msgs($product_id, $vtprd_info['product_session_info']['product_rule_full_msg_array']);
      return;
    } 

    $sizeof_msg_array = sizeof($vtprd_info['product_session_info']['product_rule_full_msg_array']);
    for($y=0; $y < $sizeof_msg_array; $y++) {
      ?>
				<p class="pricedisplay <?php echo wpsc_the_product_id(); ?> vtprd-single-product-msgs"><?php echo stripslashes($vtprd_info['product_session_info']['product_rule_full_msg_array'][$y]); ?> </p>
      <?php
    } 
         
    return;
  } 


  /* ************************************************
  **   
  *************************************************** */
/*  Now loaded into wp-head directly in pricing-deals.php
  function vtprd_enqueue_front_end_css() {
    global $vtprd_setup_options;
    if ( $vtprd_setup_options['use_plugin_front_end_css'] == 'yes' ){
      wp_register_style( 'vtprd-front-end-style', VTPRD_URL.'/core/css/vtprd-front-end.css' );  
      wp_enqueue_style('vtprd-front-end-style');
    }
  }
*/

     
  /* ************************************************
  **   WPSC needs Recompute Discount Info for theme display  

    //refigure yousave amts for WPEC 
    if (VTPRD_PARENT_PLUGIN_NAME == 'WP E-Commerce') { 
      vtprd_WPEC_recompute_theme_amts();
    }    
  *************************************************** */
  function vtprd_WPEC_recompute_theme_amts(){
      global $vtprd_info;  

      if ( ($vtprd_info['product_session_info']['product_special_price'] > 0) &&
          ($vtprd_info['product_session_info']['product_special_price'] < $vtprd_info['product_session_info']['product_list_price']) ) {
         $orig_price = $vtprd_info['product_session_info']['product_special_price']; 
      } else {
         $orig_price = $vtprd_info['product_session_info']['product_list_price']; 
      }

      $vtprd_info['product_session_info']['product_yousave_total_amt'] = ( $orig_price - $vtprd_info['product_session_info']['product_discount_price'] );
      
      //compute yousave_pct
      $computed_pct =  $vtprd_info['product_session_info']['product_discount_price'] /  $orig_price ;
      $computed_pct_2decimals = bcdiv($vtprd_info['product_session_info']['product_discount_price'] , $orig_price , 2); 
      $remainder = $computed_pct - $computed_pct_2decimals;
      if ($remainder > 0.005) {
        $yousave_pct = ($computed_pct_2decimals + .01) * 100;
      } else {
        $yousave_pct = $computed_pct_2decimals * 100;
      }
      
      $vtprd_info['product_session_info']['product_yousave_total_pct'] = $yousave_pct;
       
     return;
  }