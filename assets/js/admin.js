jQuery(function ($) {

	$('.wcpn_order_actions').on('click', function (e) {
		e.stopImmediatePropagation();
		var target = $(e.target);
		if (target.is('button')) {

			var select = target.prev();
			console.log(select);

			var children = select.children();
			console.log(children);

			var selected = select.children("option:selected").val();
			console.log(selected);

			if (selected) {
				$.post(ajaxurl, { action: 'postnord_set_service', nonce: postnord_admin.nonce, order_id: e.target.id, service: selected }, function (response) {
					if (response.status == 'success') {
						window.location.reload()
					} else if (response.status == 'error') {
						alert(response.description)
					}
				})
			}

		}
	});

	$('.postnord.sync').on('click', function (e) {
		e.preventDefault();
		var button = $(this);
		button.prop('disabled', true);
		button.html('...');

		var orderId = button.data('order-id');
		var data = {
			'action': 'postnord_sync',
			'order_id': orderId,
		};

		jQuery.post(ajaxurl, data, function (response) {
			if (response >= 200 && response < 300) {
				button.prop('disabled', false);
				button.html('Synced!');
			} else {
				button.prop('disabled', false);
				button.html(response);
			}
		});
	});

	$('.postnord.print').on('click', function (e) {
		e.preventDefault();
		var button = $(this);
		button.prop('disabled', true);
		button.html('...');

		var orderId = button.data('order-id');
		var data = {
			'action': 'postnord_print',
			'order_id': orderId,
		};

		jQuery.post(ajaxurl, data, function (response) {
			console.log(response);
			button.prop('disabled', false);
			if (response) {
				printJS({printable: response.data, type: 'pdf', base64: true})
				button.html('Printed');
			}
		});
	}
	);

	function waitForConfirmation() {

		jQuery.post(ajaxurl, { 'action': 'postnord_check_activation', 'nonce': postnord_admin.nonce }, function (response) {
			var message = response.message
			document.getElementById('postnord-status').innerHTML = message

			if (response.status == 'success') {
				var modal = document.getElementById('postnord-modal-id')
				if (modal) { modal.style.display = 'none' }
				window.location.reload()
				return;
			} else if (response.status == 'failure') {
				alert(response.message)
				return;
			} else {
				setTimeout(function () { waitForConfirmation() }, 1000)
			}
		})
	}

	$('.postnord-close').on('click', function (e) {
		var modal = document.getElementById('postnord-modal-id')
		if (modal) { modal.style.display = 'none' }
	})

	$('.postnord-connect').on('click', function (e) {
		e.preventDefault()
		var customer_number = $('#postnord_customer_number')
		var user_email = $('#postnord_user_email')
		var modal = document.getElementById('postnord-modal-id');
		if (modal) { modal.style.display = 'block' }
		$.post(ajaxurl, { action: 'postnord_connection', nonce: postnord_admin.nonce, customer_number: customer_number.val(), user_email: user_email.val(), id: e.target.id }, function (response) {
			if (response.status == 'success') {
				waitForConfirmation()
			} else if (response.status == 'error') {
				alert(response.description)
			}
		})
	})

	$('.postnord-disconnect').on('click', function (e) {
		e.preventDefault()
		$.post(ajaxurl, { action: 'postnord_connection', nonce: postnord_admin.nonce, id: e.target.id }, function (response) {
			if (response.status == 'success') {
				window.location.reload()
			} else if (response.status == 'error') {
				alert(response.description)
			}
		})
	})


});
