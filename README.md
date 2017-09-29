# Woocommerce Simple Rental
Works with Woocommerce 3.0+. This plugin simply allows Woocommerce products (simple and variable) for rental. Features multi-currency and multi-lingual support as well as security deposits. Multi-currency supports plugin Woocommerce Multicurrency Store.

To start, simply enable the plugin alongside Woocommerce.

# Setting Up Products for Rental
Add or edit your Woocommerce products like you normally would. Rental information can be set under the Product Date meta box.

For simple products, go to the Inventory tab. You should be able to see a checkbox labeled "Enable Rental". After enabling, you can select the security deposit associated with the product as well as the price of each currency. Rentable stock quantity must be larger than 0 for item to be rentable.

For variable products, go to Variations tab. Choose the variations you would like to enable rental, and you should be able to see the "Enable Rental" checkbox below. Proceed as you would for simple products.

# Security Deposits
You can define security deposits by navigating to "Security Deposits" on the left hand side of Wordpress dashboard. Note that you can set the amount for each currency you defined by Woocommerce Multicurrency Store. After saving the security deposits, you will be able to select them when editing products.\

# Purchases
If a product is allowed for rental, then in the product page there will be a button "Add as Rental" beside the "Add to Cart" button. Items added as rental will be charged rental prices you set instead of the regular item price, and security deposits will be added as fees when cart total is calculated. When a rental order falls through, the "rentable stock quantity" will be subtracted by the purchase quantity while the regular managed item stock quantity will remain unchanged.

# Manage Rental Orders
Orders with rental items will appear as "Rental Orders" in the dashboard. Navigate to "Rental Orders" and see the rented items and returned count. Once all items are returned for the order, you may navigate to the woocommerce order and perform your optional refund of security deposits.

# Additional TO DO:
(1) The maximum number of allowed rental items per account is controlled by the method maximum_allowed_rental_count() in the class Woocommerce_Simple_Rental_Rentals. This value is hard-set as 2, meaning any user can only have a maximum of 2 items in their active rental orders and shopping cart combined. To make this more flexible, I am considering making a backend setting page to change this amount.
(2) This plugin defines a namespace "woocommerce-simple-rental" for translation. It may be a good idea to generate a .pot file for proper translation.

Thanks for reviewing or using my code! I do not have any plan to maintain this repository unless there is a request for it. If you have any questions, you may contact me through my email danielchen4312@gmail.com.
