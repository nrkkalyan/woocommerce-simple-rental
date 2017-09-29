<?php

class Woocommerce_Simple_Rental_Rentals {

	private static $rental_product_infos = array();
	private static $currencies = array();

	public static function init() {
		add_action( "woocommerce_after_add_to_cart_button", array( __CLASS__, "check_add_rental_button" ) );
		add_action( "wp", array( __CLASS__, "load_rental_product_info" ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'rental_enqueue_scripts' ) );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'rental_add_to_cart_meta'), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( __CLASS__, 'rental_get_cart_meta_from_session'), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'rental_quantity_add_to_cart_validation'), 10, 4 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'rental_quantity_update_cart_validation'), 10, 4 ); 
		add_filter( 'woocommerce_before_calculate_totals', array( __CLASS__, 'modify_rental_items_in_cart'), 10, 1 );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__,'rental_security_deposit_charge') );
		add_action( 'woocommerce_add_order_item_meta', array( __CLASS__,'rental_add_to_order_meta'), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__,'rental_order_post_process'), 10, 3);
	}

	// Basic loading info
	private static function load_currencies() {
		if (!self::$currencies) {
			self::$currencies[] = get_woocommerce_currency();
			$additional_currencies = get_option( 'wmcs_store_currencies', array() );
			if ( $additional_currencies ) {
				foreach ( $additional_currencies as $currency ) {
					self::$currencies[] = $currency['currency_code'];
				}
			}
		}
	}

	private static function get_rental_product_info( $product_id ) {
		if ( !isset( self::$rental_product_infos[$product_id] ) && $product_id > 0 ) {
			$product = new WC_Product( $product_id );
			$rental_product_info = new Woocommerce_Simple_Rental_Product_Info();
			$rental_product_info->product_id = $product_id;
			$rental_product_info->product_name = $product->get_name();

			$rental_allowed = false;

			if( $product->is_type( 'simple' ) ){
			    $rental_product_info->product_type = "simple";
			    self::get_rental_currency_info( $product_id, $rental_product_info, $rental_allowed );
			} else {
				$variable_product = new WC_Product_Variable( $product_id );
				$variations = $variable_product->get_available_variations();
				if (count($variations) > 0) {
					$rental_product_info->product_type = "variable";
					$product_variations = array();
					foreach ( $variations as $variation ) {
						$product_variation = new Woocommerce_Simple_Rental_Product_Info();
						$product_variation->product_id = $variation['variation_id'];
						self::get_rental_currency_info( $variation['variation_id'], $product_variation, $rental_allowed );
						$product_variations[] = $product_variation;
					}
					$rental_product_info->rental_allowed = $rental_allowed;
					$rental_product_info->product_variations = $product_variations;
				}
			}

			self::$rental_product_infos[$product_id] = $rental_product_info;
		}
	}

	private static function get_rental_currency_info( $product_id, &$rental_product_info, &$rental_allowed = false ) {
		$rental_product_info->rental_allowed = get_post_meta( $product_id, "allow_rental", true );
		if ( ! $rental_allowed ) $rental_allowed = $rental_product_info->rental_allowed;
		$rental_product_info->available_stock = intval( get_post_meta( $product_id, "rental_stock", true ) );
		$security_deposit_id = get_post_meta( $product_id, "security_deposit_id", true );
		foreach ( self::$currencies as $currency ) {
			$rental_product_info->rental_prices[$currency] = get_post_meta( $product_id, "rental_price_" . $currency, true );
			if ( $security_deposit_id ) {
				$rental_product_info->security_deposits[$currency] = get_post_meta( $security_deposit_id, "security_deposit_" . $currency, true );
			}
		}
	}



	public static function load_rental_product_info() {
		if ( is_product() ) {
			global $post;
			$product_id = $post->ID;
			if ( function_exists('icl_object_id') ) {
				$product_id = icl_object_id( $product_id , 'product', true, "en" );
			}
			self::load_currencies();
			self::get_rental_product_info( $product_id );
		}
		if ( is_cart() ) {
			global $woocommerce;
	  		$cart = $woocommerce->cart->get_cart();
	  		foreach ( $cart as $cart_item_key => $cart_item ) {
	  			if ( isset( $cart_item['is_rental'] ) && $cart_item['is_rental'] !== false ) {
	  				$price = $cart_item["data"]->price;
	  				$quantity = $cart_item["quantity"];
	  				$stock = 0;
	  				$product_id = $cart_item["product_id"];
	  				if ( function_exists('icl_object_id') ) {
		  				$product_id = icl_object_id( $cart_item["product_id"] , 'product', true, "en" );
		  			}
	  				self::get_rental_product_info( $product_id );
	  				$rental_info = self::$rental_product_infos[$product_id];
	  				if ( $cart_item["variation_id"] ) {
	  					$variation_id = $cart_item["variation_id"];
	  					if ( function_exists('icl_object_id') ) {
		  					$variation_id = icl_object_id( $cart_item["variation_id"] , 'product_variation', true, "en" );
		  				}
	  					foreach ($rental_info->product_variations as $variation) {
	  						if ($variation->product_id == $variation_id) {
	  							$stock = $variation->available_stock;
	  						}
	  					}
	  				} else {
	  					$stock = $rental_info->available_stock;
	  				}
	  				if ( !$price || $price <= 0 || $stock < $quantity ) {
	  					wc_add_notice( sprintf( __( 'Rental item "%s" has been removed from your cart becaue it\'s no longer available.', 'woocommerce-simple-rental' ), $cart_item["data"]->name ), "error" );
	  					$woocommerce->cart->remove_cart_item($cart_item_key);
	  				}
	  			}
	  		}
		}
	}


	public static function check_add_rental_button() {
		global $product;
		$product_id = $product->get_id();
		if ( function_exists('icl_object_id') ) {
			$product_id = icl_object_id( $product->get_id(), 'product', true, "en" );
		}
		if ( self::product_rental_allowed($product_id) ) {
			?>
			<input type="button" name="add-to-rental" data-value="<?php echo esc_attr( $product->get_id() ); ?>" value="<?= esc_attr__("Add as Rental", 'woocommerce-simple-rental') ?>" class="single_add_to_cart_button add_to_rental button alt">
			<?php
		}
	}

	public static function product_rental_allowed( $product_id ) {
		return isset( self::$rental_product_infos[$product_id] ) && self::$rental_product_infos[$product_id]->rental_allowed;
	}

	public static function rental_enqueue_scripts() {
    	wp_enqueue_script( 'rental_frontend_script', plugin_dir_url( __FILE__ ) . '../assets/js/woocommerce-simple-rental-frontend.js', array('jquery'), '1.0.0.1' );
	}

	public static function rental_add_to_cart_meta( $cart_item_data, $product_id, $variation_id ) {
		if ( isset($_POST['add-as-rental']) ) {
			$cart_item_data['is_rental'] = true;
		}
		return $cart_item_data;
	}

	public static function rental_get_cart_meta_from_session( $cart_item_data, $cart_item_session_data, $cart_item_key ) {
		if ( isset( $cart_item_session_data['is_rental'] ) ) {
	        $cart_item_data['is_rental'] = $cart_item_session_data['is_rental'];
	    }
	    return $cart_item_data;
	}

	public static function validate_rental_quantities( $quantity = 0 ) {
		$allowed = self::maximum_allowed_rental_count();
		$current = self::current_active_rental_count();
		return ( ( $current + $quantity ) <= $allowed );
	}

	public static function current_active_rental_count() {
		$count = 0;
		global $woocommerce, $wpdb;
  		$cart = $woocommerce->cart->get_cart();
  		foreach ( $cart as $cart_item_key => $cart_item ) {
  			if ( isset( $cart_item['is_rental'] ) && $cart_item['is_rental'] !== false ) {
  				$count += $cart_item['quantity'];
  			}
  		}
  		$user_id = get_current_user_id();
  		$query = "SELECT (SUM(rental_quantity) - SUM(return_quantity)) FROM {$wpdb->prefix}rental_order_items WHERE user_id = %d";
		$order_qty = $wpdb->get_var(
			$wpdb->prepare(
				$query, $user_id
			)
		);
		if ( !$order_qty ) $order_qty = 0;
		$count += $order_qty;
		return $count;
	}

	public static function maximum_allowed_rental_count() {
		// TO DO: Make this setting a changable option from backend
		return 2;
	}

	public static function rental_quantity_add_to_cart_validation( $true, $product_id, $quantity, $variation_id ) {
		if ( isset($_POST['add-as-rental']) ) {
			// Check if item is available for rental
			$available = false;
			$stock = 0;
			$prices = array();
			if ( function_exists('icl_object_id') ) {
				$product_id = icl_object_id( $product_id , 'product', true, "en" );
			}
			if ($variation_id && function_exists('icl_object_id')) {
				$variation_id = icl_object_id( $variation_id , 'product_variation', true, "en" );
			}
			self::load_currencies();
			self::get_rental_product_info( $product_id );
			if ( isset( self::$rental_product_infos[$product_id] ) ) {
				$rental_info = self::$rental_product_infos[$product_id];

				$existing = 0;
				global $woocommerce;
  				$cart = $woocommerce->cart->get_cart();
  				foreach ( $cart as $cart_item_key => $cart_item ) {
  					if ( isset( $cart_item['is_rental'] ) && $cart_item['is_rental'] !== false ) {
  						if ( function_exists('icl_object_id') ) {
  							if ( ( $variation_id && $variation_id == icl_object_id( $cart_item['variation_id'], 'product_variation', true, "en" ) ) || ( !$variation_id && icl_object_id( $cart_item['product_id'], 'product', true, "en" ) == $product_id ) ) {
  								$existing = $cart_item["quantity"];
  							}
	  					} else {
	  						if ( ( $variation_id && $variation_id == $cart_item['variation_id'] ) || ( !$variation_id && $product_id == $cart_item['product_id'] ) ) {
	  							$existing = $cart_item["quantity"];
	  						}
	  					}
  					}
		  		}
		  		if (!$existing) $existing = 0;

				if ( $variation_id ) {
					$variation = false;
					foreach ( $rental_info->product_variations as $v ) {
						if ( $v->product_id == $variation_id ) {
							$variation = $v;
							break;
						}
					}
					if ($variation) {
						$available = $variation->rental_allowed;
						$stock = $variation->available_stock;
						$prices = $variation->rental_prices;
					}
				} else {
					$available = $rental_info->rental_allowed;
					$stock = $rental_info->available_stock;
					$prices = $rental_info->rental_prices;
				}
				if ( $available ) {
					$prices_available = false;
					if ( $prices ) {
						foreach ( self::$currencies as $currency ) {
							if ( floatval( $prices[$currency] ) > 0 ) $prices_available = true;
						}
					}
					if ( !$prices_available ) {
						$available = false;
					}
					
					if ( $available ) {
						if ( $quantity + $existing > $stock ) {
							wc_add_notice( __( "Your request exceeds quantity available for this item.", 'woocommerce-simple-rental' ), "error" );
							$true = false;
						} else if ( !self::validate_rental_quantities( $quantity ) ) {
							wc_add_notice( sprintf( __( "Unable to add rental item(s) to your cart. Doing so exceeds the maximum number of allowed rental counts (%d) for your account. Note that your rental count includes the items in your cart as well as your current rental order items not yet returned to us.", 'woocommerce-simple-rental' ), self::maximum_allowed_rental_count() ), "error" );
							$true = false;
						}
					}
				}
			}
			if (!$available) {
				wc_add_notice( __( "This item is not available for rental.", 'woocommerce-simple-rental' ), "error" );
				$true = false;
			}
		}
		return $true;
	}

	public static function rental_quantity_update_cart_validation( $true, $cart_item_key, $values, $quantity ) {
		if ( $values["is_rental"] ) return false;
		return $true;
	}

	public static function modify_rental_items_in_cart( $cart_object ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        	return;

        self::load_currencies();

        $rental_text = __("(Rental)", 'woocommerce-simple-rental');
        foreach ( $cart_object->get_cart() as $cart_item ) {
	        $wc_product = $cart_item['data'];
	        if ( isset( $cart_item['is_rental'] ) && $cart_item['is_rental'] !== false ) {
	        	$product_id = $cart_item["product_id"];
	        	if ( function_exists('icl_object_id') ) {
	        		$product_id = icl_object_id( $cart_item["product_id"] , 'product', true, "en" );
	        	}
	        	self::get_rental_product_info( $product_id );
	        	// Set rental name
		        $original_name = $wc_product->get_name();
		        if (strpos($original_name, $rental_text) === false) {
		        	$new_name = $original_name . " " . $rental_text;
		        	$wc_product->set_name( $new_name );
		        }

		        // Set rental price
		        $currency = current(self::$currencies);
		        $rental_info = self::$rental_product_infos[$product_id];

		        if ( $cart_item["variation_id"] ) {
		        	$variation_id = $cart_item["variation_id"];
		        	if ( function_exists('icl_object_id') ) {
			        	$variation_id = icl_object_id( $cart_item["variation_id"] , 'product_variation', true, "en" );
			        }
		        	// Variable
		        	$variation = false;
					foreach ( $rental_info->product_variations as $v ) {
						if ( $v->product_id == $variation_id ) {
							$variation = $v;
							break;
						}
					}
		        	$wc_product->set_price( $variation->rental_prices[$currency] );
		        } else {
		        	// Simple
		        	$wc_product->set_price( $rental_info->rental_prices[$currency] );
		        }
		    }
	    }
	}

	public static function rental_security_deposit_charge() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;

		global $woocommerce;
		$currency = get_woocommerce_currency();
		$security_deposit = 0;
		foreach ($woocommerce->cart->get_cart() as $cart_item) {
			if ( isset( $cart_item['is_rental'] ) && $cart_item['is_rental'] !== false ) {
				$product_id = $cart_item["product_id"];
				if ( function_exists('icl_object_id') ) {
					$product_id = icl_object_id( $cart_item["product_id"] , 'product', true, "en" );
				}
	        	self::get_rental_product_info( $product_id );
	        	$rental_info = self::$rental_product_infos[$product_id];
	        	if ($cart_item["variation_id"]) {
	        		$variation_id = $cart_item["variation_id"];
	        		if ( function_exists('icl_object_id') ) {
		        		$variation_id = icl_object_id( $cart_item["variation_id"] , 'product_variation', true, "en" );
		        	}
	        		foreach ($rental_info->product_variations as $variation) {
	        			if ($variation_id == $variation->product_id) {
	        				$security_deposit += floatval($variation->security_deposits[$currency]);
	        			}
	        		}
	        	} else {
	        		$security_deposit += floatval($rental_info->security_deposits[$currency]);
	        	}
			}
		}
		if ($security_deposit > 0) {
			$woocommerce->cart->add_fee( __("Rental Security Deposit", 'woocommerce-simple-rental'), $security_deposit, false, '' );
		}
		
	}

	public static function rental_add_to_order_meta( $item_id, $values ) {
		if ( isset( $values['is_rental'] ) && $values['is_rental'] !== false ) {
	        woocommerce_add_order_item_meta( $item_id, 'is_rental', true );           
	    }
	}

	public static function rental_order_post_process( $order_id, $posted_data, $order ) {
	    $items = $order->get_items();
	    $is_rental_order = false;
	    $rental_items = array();
	    foreach ($items as $item) {
			if ( wc_get_order_item_meta($item->get_id(), 'is_rental', true) ) {
				$is_rental_order = true;
				$data = $item->get_data();

				$product_id = 0;
				if ( $data['variation_id'] ) {
					$product_id = $data['variation_id'];
					if ( function_exists('icl_object_id') ) {
						$product_id = icl_object_id( $data['variation_id'] , 'product_variation', true, "en" );
					}
				} else {
					$product_id = $data['product_id'];
					if ( function_exists('icl_object_id') ) {
						$product_id = icl_object_id( $data['product_id'] , 'product', true, "en" );
					}
				}
				wc_update_product_stock($product_id, $data['quantity'], 'increase');
				$rentable_stock = intval( get_post_meta($product_id, 'rental_stock', true) );
				update_post_meta($product_id, 'rental_stock', $rentable_stock - $data['quantity'] );
				$rental_items[] = array(
					"item_id" => $item->get_id(),
					"quantity" => $data['quantity'],
					"product_id" => $data['product_id'],
					"variation_id" => $data['variation_id']
				);
			}
		}
		if ($is_rental_order) {
			$post_array = array(
				"post_title" => sprintf( __("Order #%d", 'woocommerce-simple-rental'), $order_id ),
				"post_status" => "publish",
				"post_type" => "rental-order",
				"comment_status" => "closed"
			);
			$rental_order_id = wp_insert_post( $post_array );
			update_post_meta($rental_order_id, 'order_id', $order_id );
			global $wpdb;
			$datetime = date("Y-m-d H:i:s");
			$user_id = get_current_user_id();
			foreach ($rental_items as $rental_item) {
				$wpdb->insert(
					$wpdb->prefix . "rental_order_items",
					array(
						"order_id" => $order_id, 
						"item_id" => $rental_item["item_id"],
						"product_id" => $rental_item["product_id"],
						"variation_id" => $rental_item["variation_id"], 
						"rental_quantity" => $rental_item["quantity"], 
						"return_quantity" => 0,
						"user_id" => $user_id,
						"date_created" => $datetime,
						"date_modified" => $datetime
					),
					array(
						'%d',
						'%d',
						'%d',
						'%d',
						'%d',
						'%d',
						'%d',
						'%s',
						'%s'
					)
				);
			}
		}
	}

}
