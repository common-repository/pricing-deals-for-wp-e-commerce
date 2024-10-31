      
                       jQuery.noConflict();

                        jQuery(document).ready(function($) {                                                        


              //**************************************************************************************************
              //   WPSC Ajax Functions     FROM   wpsc-core/js/wp-e-commerce.js
              //            Initiate discount processing for these functions...
              //**************************************************************************************************                            

                      //execute 1st time through...
                      var nanosecond_delay;
                      ajax_get_nanosecond_delay();
                        
                      //nanosecond delay supplied through settings screen.
                      function ajax_get_nanosecond_delay() {                                                                                  
                            jQuery.ajax({
                               type : "post",
                               dataType : "json",
                               url : get_nanosecond_delay.ajaxurl,  
                               data :  {action: "vtprd_ajax_get_nanosecond_delay", getDelay: 'getDelay' } ,
                              success: function(response) {
                                  nanosecond_delay = response;
                               //   alert('Got this from the server: ' + response); 
                               
                               /*       ***************************************************************
                                  use console.log() as a handy js function too
                                        using Chrome or FF with Firebug you can see the console messages in the inspection pane
                                       ****************************************************************/  
                                                                     
                                } ,
                              error: function(response) {
                                }
                            }) ;                          
                        
                        };
                        
                        //Empty Cart
                        //jQuery( 'body' ).on( 'click', 'a.emptycart', function(){
                        $("a.emptycart").click(function() {                                                         
                            jQuery.ajax({
                               type : "post",
                               dataType : "html",
                               url : empty_cart_discount.ajaxurl,  
                               data :  {action: "vtprd_ajax_empty_cart", emptyCart: 'emptyCart' } ,
                               success: function(response) {                                        
                                  // alert('EMPTY CART SUCCESS');
                                } ,
                               error: function(response) {                                        
                                }
                            }) ;  
    
                         }); 
                         
                        
                        
                      //FROM http://stackoverflow.com/questions/11308404/trying-to-add-delay-to-jquery-ajax-request  
                      /******************************************************
                       *MUST DELAY ADD-TO-CART FUNCTION UNTIL PARENT PLUGIN DONE, as the ajax_add_to_cart is done Asynchronously...
                       *  otherwise the cart's empty at begin of processing!! 
                       *  - a value of '3000' = 3seconds...                                                                   
                       ******************************************************/                        
                      $(".wpsc_buy_button").click(function() {
                          window.timer=setTimeout(function(){ // setting the delay 
                                  ajax_add_to_cart(); //runs the ajax request
                              }, nanosecond_delay);  //delay value passed from via ajax from settings screen...
                         //window.timer cleared below...
                      });
                      
                        
                        
                      function ajax_add_to_cart() {                           
                        //$(".wpsc_buy_button").click(function() {
                        //jQuery( 'body' ).on( 'click', '.wpsc_buy_button', function(){                                                        
                            jQuery.ajax({
                               type : "post",
                               dataType : "html",
                               url : add_to_cart_discount.ajaxurl,  
                               data :  {action: "vtprd_ajax_process_discount", addToCart: 'addToCart' } ,
                              success: function(response) {
                                  //alert('ADD TO CART SUCCESS');
                                  /*
                                    SEE ECHOES IN THE ALERT BOX:   
                                      success: function(response) {                                                                               
                                        alert('Got this from the server: ' + response);
                                      } ,
                                  */
                                  //alert('Got this from the server: ' + response);
                                  //clear out window.timer set above
                                  clearTimeout(window.timer);                                        
                                } ,
                              error: function(response) {
                                //clear out window.timer set above
                                clearTimeout(window.timer);                                        
                                }
                            }) ;                          
                        
                        };
                                                          
                        //**************     
                        //  end Ajax
                        //**************                      
                     
                      	                        						

                    }); //end ready function 
                        