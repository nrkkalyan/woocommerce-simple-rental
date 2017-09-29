(function($) { 

    $(document).ready(function() {
    	$("input.add_to_rental").on("click", function(){
    		if (!$(this).hasClass("disabled")) {
    			$(this).closest("form").append("<input type='hidden' class='add-as-rental' name='add-as-rental' value='true'/>");
		    	$("button.single_add_to_cart_button").trigger("click");
		    	$("input.add-as-rental").remove();
    		}
	    });
    });
    
})(jQuery)