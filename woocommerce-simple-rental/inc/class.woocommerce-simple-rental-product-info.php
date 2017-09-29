<?php

class Woocommerce_Simple_Rental_Product_Info {
	
	public function __construct() {
		$this->product_id = 0;
		$this->product_name = "";
		$this->product_type = "";
		$this->rental_allowed = false;
		$this->available_stock = 0;
		$this->rental_prices = [];
		$this->security_deposits = [];
		$this->product_variations = [];
	}

	public $product_id;

	public $product_name;

	public $product_type;

	public $rental_allowed;

	public $available_stock;

	public $rental_prices;

	public $security_deposits;

	public $product_variations;
	
}