<?php

class Woocommerce_Simple_Rental_Setup {

	public static function init() {
		if ( is_admin() ) {
			Woocommerce_Simple_Rental_Admin::init();
		} else {
			Woocommerce_Simple_Rental_Rentals::init();
		}

		self::register_rental_post_types();
	}
	
	private static function register_rental_post_types() {
		$labels = array(
			'name'               => _x( 'Security Deposits', 'post type general name', 'woocommerce-simple-rental' ),
			'singular_name'      => _x( 'Security Deposit', 'post type singular name', 'woocommerce-simple-rental' ),
			'menu_name'          => _x( 'Security Deposit', 'admin menu', 'woocommerce-simple-rental' ),
			'name_admin_bar'     => _x( 'Security Deposit', 'add new on admin bar', 'woocommerce-simple-rental' ),
			'add_new'            => _x( 'Add New', 'security deposit', 'woocommerce-simple-rental' ),
			'add_new_item'       => __( 'Add New Security Deposit', 'woocommerce-simple-rental' ),
			'new_item'           => __( 'New Security Deposit', 'woocommerce-simple-rental' ),
			'edit_item'          => __( 'Edit Security Deposit', 'woocommerce-simple-rental' ),
			'view_item'          => __( 'View Security Deposit', 'woocommerce-simple-rental' ),
			'all_items'          => __( 'All Security Deposits', 'woocommerce-simple-rental' ),
			'search_items'       => __( 'Search Security Deposit', 'woocommerce-simple-rental' ),
			'parent_item_colon'  => __( 'Parent Security Deposit:', 'woocommerce-simple-rental' ),
			'not_found'          => __( 'No Security Deposit found.', 'woocommerce-simple-rental' ),
			'not_found_in_trash' => __( 'No Security Deposit found in Trash.', 'woocommerce-simple-rental' )
		);
		$args = array(
			'labels'             => $labels,
	        'description'        => __( 'Security deposit settings for rental items.', 'woocommerce-simple-rental' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'security-deposit' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 12,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
		);
		register_post_type( 'security-deposit', $args );

		$labels = array(
			'name'               => _x( 'Rental Orders', 'post type general name', 'woocommerce-simple-rental' ),
			'singular_name'      => _x( 'Rental', 'post type singular name', 'woocommerce-simple-rental' ),
			'menu_name'          => _x( 'Rental Orders', 'admin menu', 'woocommerce-simple-rental' ),
			'name_admin_bar'     => _x( 'Rental Orders', 'add new on admin bar', 'woocommerce-simple-rental' ),
			'add_new'            => _x( 'Add New', 'rentalorders', 'woocommerce-simple-rental' ),
			'add_new_item'       => __( 'Add New Rental Order', 'woocommerce-simple-rental' ),
			'new_item'           => __( 'New Rental Order', 'woocommerce-simple-rental' ),
			'edit_item'          => __( 'Edit Rental Order', 'woocommerce-simple-rental' ),
			'view_item'          => __( 'View Rental Order', 'woocommerce-simple-rental' ),
			'all_items'          => __( 'All Rental Orders', 'woocommerce-simple-rental' ),
			'search_items'       => __( 'Search Rental Orders', 'woocommerce-simple-rental' ),
			'parent_item_colon'  => __( 'Parent Rental Orders:', 'woocommerce-simple-rental' ),
			'not_found'          => __( 'No Rental Orders found.', 'woocommerce-simple-rental' ),
			'not_found_in_trash' => __( 'No Rental Orders found in Trash.', 'woocommerce-simple-rental' )
		);
		$args = array(
			'labels'             => $labels,
	        'description'        => __( 'Rental order information.', 'woocommerce-simple-rental' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'rental-orders' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 12,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
		);
		register_post_type( 'rental-order', $args );
	}

	private static function setup_rental_database_tables() {
		global $wpdb;
	     $table_name = $wpdb->prefix . 'rental_order_items';
	     $wpdb_collate = $wpdb->collate;
	     $sql =
	         "CREATE TABLE {$table_name} (
	         ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	         order_id BIGINT UNSIGNED NOT NULL,
	         item_id BIGINT UNSIGNED NOT NULL,
	         product_id BIGINT UNSIGNED NOT NULL,
	         variation_id BIGINT UNSIGNED NULL,
	         rental_quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
	         return_quantity SMALLINT UNSIGNED NOT NULL DEFAULT 0,
	         user_id BIGINT UNSIGNED NULL,
	         date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
	         date_modified DATETIME DEFAULT CURRENT_TIMESTAMP,
	         PRIMARY KEY (ID)
	         )
	         COLLATE {$wpdb_collate}";
	 
	     require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	     dbDelta( $sql );
	}

	public static function plugin_activation() {
		self::register_rental_post_types();
		self::setup_rental_database_tables();
		flush_rewrite_rules();
	}

	public static function plugin_deactivation() {
		flush_rewrite_rules();
	}
}
