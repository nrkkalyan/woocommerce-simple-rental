<?php

class Woocommerce_Simple_Rental_Admin {

	private static $currencies = array();
	private static $available_security_deposits = array();

	public static function init() {
		// Prepare page when loaded
		add_action( 'admin_head', array( __CLASS__, 'prepare_page' ) );
		// Display Custom Fields at Admin Metabox
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'variable_rental_setting_fields' ), 10, 3 );
		add_action( 'woocommerce_product_options_inventory_product_data', array( __CLASS__, 'product_rental_setting_fields' ) );
		// JS to Add Custom Fields at Admin Metabox
		add_action( 'woocommerce_product_after_variable_attributes_js', array( __CLASS__, 'variable_rental_setting_fields_js' ) );
		// Save simple fields
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_simple_rental_setting_fields' ), 10, 2 );
		// Save variation fields
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variable_rental_setting_fields' ), 10, 2 );
		// Add scripts
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'rental_admin_enqueue_scripts' ) );
		// Add sections for custom types
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_sections_for_rental_types' ) );
		// Process saving custom post data
		add_action( 'save_post', array( __CLASS__, 'save_rental_types' ), 10, 3 );

		add_filter( 'manage_rental-order_posts_columns', array( __CLASS__, 'rental_order_modify_columns' ) );
		add_action( 'manage_rental-order_posts_custom_column', array( __CLASS__, 'rental_order_custom_columns' ), 10, 2 );
	}

	public static function prepare_page() {
		global $post;
		if ( $post ) {
			$type = get_post_type($post);
			if ( 'product' == $type || 'rental-order' == $type || 'security-deposit' == $type ) {
				// Instantiate currencies
				self::$currencies[] = get_woocommerce_currency();
				$additional_currencies = get_option( 'wmcs_store_currencies', array() );
				if ( $additional_currencies ) {
					foreach ( $additional_currencies as $currency ) {
						self::$currencies[] = $currency['currency_code'];
					}
				}

				// Instantiate security deposits
				self::$available_security_deposits = self::get_available_security_deposits();
				
				// Hide sections for custom types
				?>
				<style>
					#postdivrich, #postexcerpt, #postimagediv, #commentstatusdiv, #authordiv {
						display: none!important;
					}
				</style>
				<?php
			}
		}
	}

	public static function rental_admin_enqueue_scripts($hook) {
		if ( 'post.php' != $hook ) {
	        return;
	    }
    	wp_enqueue_script( 'rental_admin_script', plugin_dir_url( __FILE__ ) . '../assets/js/woocommerce-simple-rental-admin.js', array('jquery'), '1.0.0.1' );
	}

	public static function product_rental_setting_fields() {
		global $post;
		$rental_allowed = get_post_meta($post->ID, 'allow_rental', true);
		$rentable_stock = get_post_meta($post->ID, 'rental_stock', true);
		$security_deposit_id = get_post_meta($post->ID, 'security_deposit_id', true);
		?>
		<div class="options_group show_if_simple">
			<div class="form-row form-row-full options rental_toggle" style="clear:both;">
				<?php
				woocommerce_wp_checkbox( 
				array( 
					'id'            => 'allow_rental', 
					'label'         => " " . __('Enable Rental', 'woocommerce-simple-rental' ), 
					'value'         => $rental_allowed
					)
				);
				?>
			</div>
			<div class="rental_price_fields rental_price_field" <?= $rental_allowed ? '' : 'style="display:none;"' ?>>
				<?php
					if ( self::$available_security_deposits ) {
				?>
				<div class="form-row security-deposit">
				<?php
					$security_deposits = array(
						0 => __( '-- Select --', 'woocommerce-simple-rental' )
					);
					foreach ( self::$available_security_deposits as $security_deposit ) {
						$security_deposits[$security_deposit->ID] = $security_deposit->title;
					}
					woocommerce_wp_select( 
						array( 
							'id'      => 'security_deposit_id', 
							'label'   => __( 'Security Deposit', 'woocommerce-simple-rental' ), 
							'options' => $security_deposits,
							'value' => $security_deposit_id
						)
					);
				?>
				</div>
				<?php
					}
				?>
				<div class="form-row form-row-first rental-stock">
					<?php
					woocommerce_wp_text_input( 
					array( 
						'id'            => 'rental_stock', 
						'label'         => " " . __('Rentable stock quantity', 'woocommerce-simple-rental' ), 
						'value'         => $rentable_stock
						)
					);
					?>
				</div>
				<?php foreach (self::$currencies as $currency) { ?>
				<div style="clear: both;"></div>
				<div class="form-row form-row-first">
						<?php
						woocommerce_wp_text_input( 
							array( 
								'id'          => 'rental_price_' . $currency, 
								'label'       => sprintf( __( 'Rental Price (%s)', 'woocommerce-simple-rental' ), $currency ), 
								'desc_tip'    => 'true',
								'description' => sprintf( __( 'Enter rental price for this item in %s.', 'woocommerce-simple-rental' ), $currency ),
								'value'       => get_post_meta($post->ID, 'rental_price_' . $currency, true),
								'custom_attributes' => array(
												'step' 	=> 'any',
												'min'	=> '0'
											) 
							)
						);
						?>
				</div>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Create new fields for variations
	 *
	*/
	public static function variable_rental_setting_fields( $loop, $variation_data, $variation ) {
		$rental_allowed = get_post_meta($variation->ID, 'allow_rental', true);
		$rentable_stock = get_post_meta($variation->ID, 'rental_stock', true);
		$security_deposit_id = get_post_meta($variation->ID, 'security_deposit_id', true);

		// Load variables since admin_head is not loaded
		if ( !self::$currencies ) {
			self::$currencies[] = get_woocommerce_currency();
			$additional_currencies = get_option( 'wmcs_store_currencies', array() );

			if ( $additional_currencies ) {
				foreach ( $additional_currencies as $currency ) {
					self::$currencies[] = $currency['currency_code'];
				}
			}
		}
		if ( !self::$available_security_deposits ) {
			self::$available_security_deposits = self::get_available_security_deposits();
		}
	?>
		<div class="form-row form-row-full options rental_toggle" style="clear:both;">
				<?php
				woocommerce_wp_checkbox( 
				array( 
					'id'            => 'allow_rental['.$loop.']', 
					'label'         => " " . __('Enable Rental', 'woocommerce-simple-rental' ), 
					'value'         => $rental_allowed
					)
				);
				?>
		</div>
		
		<div class="rental_price_fields rental_price_fields_<?php echo $loop; ?>" <?= $rental_allowed ? '' : 'style="display:none;"' ?>>
			<?php
			if ( self::$available_security_deposits ) {
			?>
			<div class="form-row form-row-first rental-stock">
			<?php
				$security_deposits = array(
					0 => __( '-- Select --', 'woocommerce-simple-rental' )
				);
				foreach ( self::$available_security_deposits as $security_deposit ) {
					$security_deposits[$security_deposit->ID] = $security_deposit->title;
				}
				woocommerce_wp_select( 
					array( 
						'id'      => 'security_deposit_id['.$loop.']', 
						'label'   => __( 'Security Deposit', 'woocommerce-simple-rental' ), 
						'options' => $security_deposits,
						'value' => $security_deposit_id
					)
				);
			?>
			</div>
			<?php
				}
			?>
			<div class="form-row form-row-first rental-stock">
				<?php
				woocommerce_wp_text_input( 
				array( 
					'id'            => 'rental_stock['.$loop.']', 
					'label'         => " " . __('Rentable stock quantity', 'woocommerce-simple-rental' ), 
					'value'         => $rentable_stock
					)
				);
				?>
			</div>
			<?php foreach (self::$currencies as $currency) { ?>
			<div style="clear: both;"></div>
			<div class="form-row form-row-first">
					<?php
					woocommerce_wp_text_input( 
						array( 
							'id'          => 'rental_price_' . $currency . '['.$loop.']', 
							'label'       => sprintf( __( 'Rental Price (%s)', 'woocommerce-simple-rental' ), $currency ), 
							'desc_tip'    => 'true',
							'description' => sprintf( __( 'Enter rental price for this item in %s.', 'woocommerce-simple-rental' ), $currency ),
							'value'       => get_post_meta($variation->ID, 'rental_price_' . $currency, true),
							'custom_attributes' => array(
											'step' 	=> 'any',
											'min'	=> '0'
										) 
						)
					);
					?>
			</div>
			<?php } ?>
		</div>

	<?php
	}
	/**
	 * Create new fields for new variations
	 *
	*/
	public static function variable_rental_setting_fields_js() {
	?>
		<div class="form-row form-row-full options rental_toggle" style="clear:both;">
			<?php
			woocommerce_wp_checkbox( 
			array( 
				'id'            => 'allow_rental[ + loop + ]', 
				'label'         => " " . __('Enable Rental', 'woocommerce-simple-rental' ),
				'value'         => '', 
				)
			);
			?>
		</div>
		<div style="display: none;" class="rental_price_fields rental_price_fields_[ + loop + ]">
			<div class="form-row form-row-first rental-stock">
				<?php
				woocommerce_wp_text_input( 
				array( 
					'id'            => 'rental_stock[ + loop + ]', 
					'label'         => " " . __('Rentable stock quantity', 'woocommerce-simple-rental' ), 
					'value'         => ''
					)
				);
				?>
			</div>
			<?php foreach (self::$currencies as $currency) { ?>
			<div style="clear: both;"></div>
			<div class="form-row form-row-first">
				<?php
				woocommerce_wp_text_input( 
					array( 
						'id'                => 'rental_price_' . $currency . '[ + loop + ]', 
						'label'             => sprintf( __( 'Rental Price (%s)', 'woocommerce-simple-rental' ), $currency ),
						'desc_tip'          => 'true',
						'description'       => sprintf( __( 'Enter rental price for this item in %s.', 'woocommerce-simple-rental' ), $currency ),
						'value'             => '',
						'custom_attributes' => array(
										'step' 	=> 'any',
										'min'	=> '0'
									) 
					)
				);
				?>
			</div>
			<?php } ?>
		</div>
	<?php
	}

	/**
	 * Save new fields for simple products
	 *
	*/
	public static function save_simple_rental_setting_fields( $post_id ) {
		$allow_rental = isset( $_POST['allow_rental'] ) && boolval($_POST['allow_rental']) ? 'yes' : 'no';
		update_post_meta( $post_id, 'allow_rental', $allow_rental );
		$rental_stock = isset( $_POST['rental_stock'] ) && intval($_POST['rental_stock']) > 0 ? intval($_POST['rental_stock']) : 0;
		update_post_meta( $post_id, 'rental_stock', $rental_stock );
		$security_deposit_id = isset( $_POST['security_deposit_id'] ) && intval($_POST['security_deposit_id']) > 0 ? intval($_POST['security_deposit_id']) : 0;
		update_post_meta( $post_id, 'security_deposit_id', $security_deposit_id );
		self::$currencies[] = get_woocommerce_currency();
		$additional_currencies = get_option( 'wmcs_store_currencies', array() );
		if ( $additional_currencies ) {
			foreach ( $additional_currencies as $currency ) {
				self::$currencies[] = $currency['currency_code'];
			}
		}
		foreach (self::$currencies as $currency) {
			if (isset($_POST['rental_price_' . $currency])) {
				$rental_price = floatval($_POST['rental_price_' . $currency]); 
				update_post_meta( $post_id, 'rental_price_' . $currency, esc_attr( $rental_price ) );
			}
		}
	}

	/**
	 * Save new fields for variations
	 *
	*/
	public static function save_variable_rental_setting_fields( $variation_id, $i ) {
		// Load variables since admin_head is not loaded
		if ( !self::$currencies ) {
			self::$currencies[] = get_woocommerce_currency();
			$additional_currencies = get_option( 'wmcs_store_currencies', array() );

			if ( $additional_currencies ) {
				foreach ( $additional_currencies as $currency ) {
					self::$currencies[] = $currency['currency_code'];
				}
			}
		}
		if ( !self::$available_security_deposits ) {
			self::$available_security_deposits = self::get_available_security_deposits();
		}
		
		$allow_rental = isset( $_POST['allow_rental'][$i] ) && boolval($_POST['allow_rental'][$i]) ? 'yes' : 'no';
		update_post_meta( $variation_id, 'allow_rental', $allow_rental );
		$rental_stock = isset( $_POST['rental_stock'][$i] ) && intval($_POST['rental_stock'][$i]) > 0 ? intval($_POST['rental_stock'][$i]) : 0;
		update_post_meta( $variation_id, 'rental_stock', $rental_stock );
		$security_deposit_id = isset( $_POST['security_deposit_id'][$i] ) && intval($_POST['security_deposit_id'][$i]) > 0 ? intval($_POST['security_deposit_id'][$i]) : 0;
		update_post_meta( $variation_id, 'security_deposit_id', $security_deposit_id );
		foreach (self::$currencies as $currency) {
			if (isset($_POST['rental_price_' . $currency][$i])) {
				$rental_price = floatval($_POST['rental_price_' . $currency][$i]); 
				update_post_meta( $variation_id, 'rental_price_' . $currency, esc_attr( $rental_price ) );
			}
		}
	}

	public static function add_sections_for_rental_types() {
		add_meta_box( 
            'secuity_deposit_box',
            __( 'Security Deposit Settings', 'woocommerce-simple-rental' ),
            array( __CLASS__, 'render_section_for_security_deposit' ),
            'security-deposit',
            'normal',
            'high'
        );
        add_meta_box( 
            'rental_item_box',
            __( 'Rental Items', 'woocommerce-simple-rental' ),
            array( __CLASS__, 'render_section_for_rental_order' ),
            'rental-order',
            'normal',
            'high'
        );
	}

	public static function render_section_for_security_deposit() {
		global $post;
		?>
        <div class='securitydepositdiv'>
        	<?php 
        		if ( $post && isset( $post->ID ) ) {
        			foreach ( self::$currencies as $currency ) {
        				$value = floatval( get_post_meta($post->ID, 'security_deposit_' . $currency, true) );
        				if ( !$value ) {
        					$value = 0;
        				}
        				?>
        				<div>
							<label><?= sprintf( esc_html__( "%s Security Deposit Amount", "woocommerce-simple-rental" ), $currency ) ?></label><br />
							<input class="security-deposit-field" name="security_deposit_<?= $currency ?>" value="<?= $value ?>" />
							<br /><br />
						</div>
        				<?php
        			}
        			$checked = "";
        			$default_id = get_option( "default_security_deposit" );
        			if ( $default_id == $post->ID ) {
        				$checked = "checked";
        			}
        			?>
        			<br />
					<div>
						<label><input type="checkbox" name="default_security_deposit" <?= $checked ?> /> <?= esc_html__( "Set as default security deposit", "woocommerce-simple-rental" ) ?></label>
					</div>
        			<?php
        		}
    		?>
        </div>
        <?php
	}

	public static function render_section_for_rental_order() {
		global $post, $wpdb;
		$post_id = $post->ID;
		$order_id = get_post_meta( $post->ID, "order_id", true );
		$order = wc_get_order( $order_id );
		$order_data = $order->get_data();
		$order_item = $order->get_items();
		$query = "SELECT * FROM {$wpdb->prefix}rental_order_items WHERE order_id = %d";
		$results = $wpdb->get_results(
			$wpdb->prepare(
				$query,
				$order_id
			), ARRAY_A
		);
		$loaded_attributes = array();
		?>
        <div class='rentalorderdiv'>
        	<style>
        		.rental-item-table{
        			width: 100%;
        		}
        		.rental-item-table th{
        			border-bottom: 1px solid #bbb;
        			padding: 7px 0;
        		}
        		.rental-item-table td{
					border-bottom: 1px solid #bbb;
					padding: 5px 0;
        		}
        		.item-label {
					text-align: left;
					max-width: 125px;
        		}
        		.qty-field {
        			text-align: center;
        		}
        		.item-image img {
        			width: 100%;
        			max-width: 125px;
        		}
        	</style>
        	<table class="rental-item-table">
        		<thead>
        			<tr>
        				<th class="item-label"><?= __("Item", 'woocommerce-simple-rental') ?></th>
        				<th></th>
        				<th><?= __("Qty", 'woocommerce-simple-rental') ?></th>
        				<th><?= __("Returned Qty", 'woocommerce-simple-rental') ?></th>
        				<th><?= __("Updated Time", 'woocommerce-simple-rental') ?></th>
        			</tr>
        		</thead>
        		<tbody>
        	<?php
        		foreach ( $results as $rental_item ) {
        			if ( $rental_item["variation_id"] ) {
        				$product = new WC_Product_Variation( $rental_item["variation_id"] );
        			} else {
        				$product = new WC_Product_Simple( $rental_item["product_id"] );
        			}
        			$edit_link = get_edit_post_link( $rental_item["product_id"] );
        	?>
        		<tr>
        			<td class="item-image"><a href="<?= $edit_link ?>"><?= $product->get_image() ?></a></td>
        			<td>
        				<a href="<?= $edit_link ?>"><?= $product->get_title(); ?>	</a><br />
        				<?php
        					$sku = $product->get_sku();
        					if ($sku) {
        						echo "<strong>" . __("SKU:", 'woocommerce-simple-rental') . "</strong> " . $sku . "<br />";
        					}
        					$type = $product->get_type();
        					if ( $type == "variation" ) {
        						$attributes = $product->get_attributes();
        						foreach( $attributes as $key => $attribute ){
        							if ( !isset( $loaded_attributes[$key] ) ) {
        								$loaded_attributes[$key] = get_terms( $key );
        							}
        							foreach( $loaded_attributes[$key] as $loaded_attribute ) {
        								if ( $loaded_attribute->slug == $attribute ) {
        									echo "<strong>" . get_taxonomy($loaded_attribute->taxonomy)->labels->name . ":</strong> " . $loaded_attribute->name . "<br />";
        								}
        							}
        						}
        					}
    					?>
    				</td>
					<td class="qty-field"><?= $rental_item["rental_quantity"] ?></td>
					<td class="qty-field"><input type="number" min="0" max="<?= $rental_item["rental_quantity"] ?>" name="return_quantity[<?= $rental_item["ID"] ?>]" value="<?= $rental_item["return_quantity"] ?>"></td>
					<td class="qty-field"><?= $rental_item["date_modified"] ?></td>
        		</tr>
        	<?php
        		}
        	?>
        		</tbody>
        	</table>
        	<p>
        		<input class="button button-primary update-rental-order-button" type="button" value="<?= __("Update", 'woocommerce-simple-rental') ?>" />
	        	<a href="<?= get_edit_post_link( $order_id ) ?>" class="button">Go to Woocommerce Order</a>
	        </p>
        </div>
        <?php
	}

	public static function save_rental_types( $post_id, $post, $update ) {
		$type = get_post_type($post_id);
		if ( $type != "rental-order" && $type != "security-deposit" ) 
			return;

		if ( current_user_can( "manage_options" ) ) {
			if ( $type == "security-deposit" ) {
				if ( !self::$currencies ) {
					self::$currencies[] = get_woocommerce_currency();
					$additional_currencies = get_option( 'wmcs_store_currencies', array() );

					if ( $additional_currencies ) {
						foreach ( $additional_currencies as $currency ) {
							self::$currencies[] = $currency['currency_code'];
						}
					}
				}
				foreach ( self::$currencies as $currency ) {
					if ( isset( $_REQUEST['security_deposit_' . $currency] ) ) {
						 update_post_meta( $post_id, 'security_deposit_' . $currency, $_REQUEST['security_deposit_' . $currency] );
					}
				}
				if ( isset( $_REQUEST['default_security_deposit'] ) && boolval( $_REQUEST['default_security_deposit'] ) ) {
					update_option( "default_security_deposit", $post_id );
				}
			} else if ( $type == "rental-order" ) {
				if ( isset( $_REQUEST['return_quantity'] ) && is_array( $_REQUEST['return_quantity'] ) && count( $_REQUEST['return_quantity'] ) > 0 ) {
					global $wpdb;
					foreach( $_REQUEST['return_quantity'] as $key => $value ) {
						$query = "SELECT * FROM {$wpdb->prefix}rental_order_items WHERE ID = %d LIMIT 1";
						$row = $wpdb->get_row(
							$wpdb->prepare(
								$query, $key
							), ARRAY_A
						);
						if ($row) {
							$quantity = intval( $value );
							if ( $quantity <= $row["rental_quantity"] && $quantity != $row["return_quantity"] ) {
								$difference = $quantity - $row["return_quantity"];
								$wpdb->update( 
									$wpdb->prefix . "rental_order_items", 
									array( 
										'return_quantity' => $quantity,
										'date_modified' => date("Y-m-d H:i:s")
									), 
									array( 'ID' => $key ), 
									array( 
										'%d',
										'%s'
									), 
									array( '%d' ) 
								);
								$product_id = $row["product_id"];
								if ( $row["variation_id"] ) {
									$product_id = $row["variation_id"];
								}
								$rentable_stock = intval( get_post_meta($product_id, 'rental_stock', true) );
								update_post_meta($product_id, 'rental_stock', $rentable_stock + $difference );
							}
						}
					}
				}
			}
		}
	}

	public static function rental_order_modify_columns( $columns ) {
		// $columns = array();
		$date_column = $columns['date'];
		unset($columns['author']);
		unset($columns['comments']);
		unset($columns['ebor_post_thumb']);
		unset($columns['date']);
		$new_columns = array(
			'user' => __( 'User', 'woocommerce-simple-rental' ),
			'address' => __( 'Address', 'woocommerce-simple-rental' ),
			'rental_count' => __( 'Rented', 'woocommerce-simple-rental' ),
			'return_count' => __( 'Returned', 'woocommerce-simple-rental' ),
		 );
		$columns = array_merge( $columns, $new_columns );
		$columns['date'] = $date_column;
		return $columns;
	}

	public static function rental_order_custom_columns( $column, $post_id ) {
		global $wpdb;
		$order_id = get_post_meta( $post_id, "order_id", true );
		
		switch ( $column ) {
			case "user":
				$user_id = get_post_meta( $order_id, '_customer_user', true );
				$user = get_userdata( $user_id );
				if ( $user )
					echo $user->user_login . " (" . $user->user_email . ")";
			break;
			case "address":
				$order = wc_get_order( $order_id );
				$order_data = $order->get_data();
				if (preg_match("/\p{Han}+/u", $str)) {
					echo $order_data['shipping']['country'] . $order_data['shipping']['state'] . $order_data['shipping']['city'] . $order_data['shipping']['address_1'];
					if ($order_data['shipping']['address_2']) {
						echo $order_data['shipping']['address_2'];
					}
				} else {
					if ($order_data['shipping']['address_2']) {
						echo $order_data['shipping']['address_2'] . " ";
					}
					echo $order_data['shipping']['address_1'] . " " . $order_data['shipping']['city'] . " " . $order_data['shipping']['state'] . ", " . $order_data['shipping']['country'];
				}
			break;
			case "rental_count":
				$rental_count = $wpdb->get_var( $wpdb->prepare( 
					"
						SELECT sum(rental_quantity) 
						FROM {$wpdb->prefix}rental_order_items
						WHERE order_id = %d
					", 
					$order_id
				) );
				if ( !$rental_count ) $rental_count = 0;
				echo $rental_count;
			break;
			case "return_count":
				$return_count = $wpdb->get_var( $wpdb->prepare( 
					"
						SELECT sum(return_quantity) 
						FROM {$wpdb->prefix}rental_order_items
						WHERE order_id = %d
					", 
					$order_id
				) );
				if ( !$return_count ) $return_count = 0;
				echo $return_count;
			break;
		}
	}

	private static function get_available_security_deposits() {
		$results = array();
		global $wpdb;
		try {
			$results = $wpdb->get_results(
				"SELECT ID, post_title as title FROM {$wpdb->prefix}posts WHERE post_type = 'security-deposit' AND post_status = 'publish'"
			);
		} catch ( Exception $e ) {
			$results = array();
		}
		return $results;
	}


}

