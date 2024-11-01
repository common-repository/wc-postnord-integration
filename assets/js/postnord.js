if (!window.Global) {
	var Global = {};
}

jQuery(function ($) {

	Global.getAgents = function (methodId) {

		var postnordForm = $('.postnord.form-row[data-method-id="' + methodId + '"]');

		var zipCode = $('[name="ship_to_different_address"]').is(':checked') && $('#shipping_postcode').val() !== '' ?
			$('#shipping_postcode').val() :
			$('#billing_postcode').val();

		var countryCode = $('[name="ship_to_different_address"]').is(':checked') && $('#shipping_country').val() !== '' ?
			$('#shipping_country').val() :
			$('#billing_country').val();

		$.post(wooPostnordIntegrationPhpVar.ajaxurl, {
			action: 'get_points',
			country_code: countryCode,
			postcode: zipCode,
		}).done(function (response) {
			if (response['error']) {
				postnordForm.find('.select-agent').html(response['error']);
			} else {
				var pickup_points = JSON.parse(response['data']);
				prettyMethodId = methodId.replace(/:/g, "_");
				var agentSelect = '<select name="select_' + prettyMethodId + '" id="select_' + prettyMethodId + '">';
				for (var i = 0; i < pickup_points.length; i++) {
					agentSelect += '<option value=\'' + pickup_points[i].servicePointId + '\'">';
					agentSelect += pickup_points[i].name + ', ' + pickup_points[i].visitingAddress.streetName + ' ' + pickup_points[i].visitingAddress.streetNumber + ', ' + pickup_points[i].visitingAddress.postalCode + ' ' + pickup_points[i].visitingAddress.city;
					agentSelect += '</option>';
				}
				agentSelect += '</select>';
				postnordForm.find('.select-agent').html(agentSelect);
			}
		});

	}

	// TODO: not working properly. postnord forms for not selected shipping option should be hidden
	// Possible solution: Hide all as default then unhide? Will cause problems with wc-enhanced-checkout
	/*
	$( document.body ).on( 'update_checkout', function() {
		var selectedMethodId = $('input[name="shipping_method[0]"]:checked').val();
		$('.postnord.form-row').not('[data-method-id="'+selectedMethodId+'"]').hide();
	} );
	*/

});
