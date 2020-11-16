var checkbancard = function() {
	jQuery(function($){
		var data = $("span[data-order]").data("order");
		console.log(data);
		if (data) {
			var method = $("span[data-method]").data("method");
			if (method == "zimple") {
				Bancard.Zimple.createForm('zimple-checkout', data);
				return false;
			}
			else if (method == "bancard") {
				Bancard.Checkout.createForm('bancard-checkout', data);
				return false;
			}
		}
	});
};
jQuery(function($){
	var checkout_form = $('body');
	checkout_form.on( 'checkout_error', checkbancard );
	checkout_form.on( 'payment_method_selected', checkbancard );
$( document.body ).on( 'init_checkout',function() {console.log('init_checkout iniciated')});
$( document.body ).on( 'payment_method_selected',function() {console.log('payment_method_selected iniciated')});
$( document.body ).on( 'update_checkout',function() {console.log('update_checkout iniciated')});
$( document.body ).on( 'updated_checkout',function() {console.log('updated_checkout iniciated')});
$( document.body ).on( 'checkout_error',function() {console.log('checkout_error iniciated')});
});