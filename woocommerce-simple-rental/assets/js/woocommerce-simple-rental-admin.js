(function($) { 
	$(document).ready( function() {
		$(document).on("change", "[id^=allow_rental]", function(){
	        if ($(this).is(":checked")) {
	            $(this).closest(".rental_toggle").next(".rental_price_fields").show();
	        } else {
	            $(this).closest(".rental_toggle").next(".rental_price_fields").hide();
	        }
	    }).on("click", ".update-rental-order-button", function() {
	    	$("#publish").trigger("click");
	    });
	} );
})(jQuery)