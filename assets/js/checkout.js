/* Neuvix Checkout */
jQuery(function ($) {
	var data = window.nvxCheckout || {};
	var map = data.locations || {};

	function refreshSelect($sel) {
		if ($sel.hasClass('select2-hidden-accessible')) {
			$sel.trigger('change.select2');
		}
	}

	/* District → Area */
	function fillAreas(keepValue) {
		var $district = $('#nvx_district');
		var $area = $('#nvx_area');
		if (!$district.length || !$area.length) return;

		var district = $district.val() || '';
		var current = keepValue ? ($area.val() || '') : '';
		var areas = map[district] || [];
		var html = '<option value="">' + (data.areaPlaceholder || 'Select Area') + '</option>';

		$.each(areas, function (i, a) {
			var esc = $('<div/>').text(a).html();
			html += '<option value="' + esc + '"' + (a === current ? ' selected' : '') + '>' + esc + '</option>';
		});
		$area.html(html);
		refreshSelect($area);
	}

	$(document).on('change', '#nvx_district', function () {
		fillAreas(false);
		// .update_totals_on_change on the wrapper makes WooCommerce refresh the summary + delivery charge.
	});
	fillAreas(true);

	/* Phone + email validation with inline errors (server re-validates on submit) */
	var i18n = data.i18n || {};

	function cleanPhone(v) {
		v = String(v || '').replace(/[০-৯]/g, function (d) { return '০১২৩৪৫৬৭৮৯'.indexOf(d); });
		return v.replace(/[\s\-\.\(\)]/g, '');
	}

	function isValidPhone(v) {
		v = cleanPhone(v);
		if ((data.country || 'BD') === 'BD') {
			v = v.replace(/^\+/, '').replace(/^880/, '');
			if (/^1\d{9}$/.test(v)) v = '0' + v;
			return /^01[3-9]\d{8}$/.test(v);
		}
		return /^\+?\d{7,15}$/.test(v);
	}

	function isValidEmail(v) {
		return /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)*\.[a-z]{2,}$/i.test(String(v || '').trim());
	}

	var rules = {
		billing_phone: { test: isValidPhone, msg: i18n.phone || 'Please enter a valid mobile number.' },
		billing_email: { test: isValidEmail, msg: i18n.email || 'Please enter a valid email address.' }
	};

	function setError($input, msg) {
		var $row = $input.closest('.form-row');
		var id = $input.attr('id') + '-nvx-error';
		$row.find('.nvx-field-error').remove();
		if (msg) {
			$row.find('.checkout-inline-error-message').remove(); // avoid a duplicate WooCommerce message
			$row.removeClass('woocommerce-validated').addClass('woocommerce-invalid nvx-invalid');
			$input.attr({ 'aria-invalid': 'true', 'aria-describedby': id });
			$('<span class="nvx-field-error" role="alert"/>').attr('id', id).text(msg).appendTo($row);
		} else {
			$row.removeClass('nvx-invalid');
			$input.removeAttr('aria-describedby');
		}
	}

	/* Returns true when valid. Empty values are left to WooCommerce's "required" check. */
	function checkField($input) {
		var rule = rules[$input.attr('id')];
		if (!rule) return true;
		var v = $.trim($input.val());
		var ok = v === '' || rule.test(v);
		setError($input, ok ? '' : rule.msg);
		return ok;
	}

	var selector = '#billing_phone, #billing_email';

	$(document).on('blur change', selector, function () {
		checkField($(this));
	});
	// While an error is visible, clear it as soon as the value becomes valid.
	$(document).on('input', selector, function () {
		var $i = $(this);
		if ($i.closest('.form-row').hasClass('nvx-invalid')) checkField($i);
	});

	// Block submit when a field is invalid.
	$('form.checkout').on('checkout_place_order', function () {
		var $bad = $();
		$(selector).each(function () {
			if (!checkField($(this))) $bad = $bad.add(this);
		});
		if ($bad.length) {
			$('html, body').animate({ scrollTop: $bad.first().closest('.form-row').offset().top - 120 }, 300);
			$bad.first().trigger('focus');
			return false;
		}
		return true;
	});

	/* Packaging cards → recalc fees via AJAX */
	$(document).on('change', 'input[name="nvx_packaging"]', function () {
		$('.nvx-pack').removeClass('is-selected');
		$(this).closest('.nvx-pack').addClass('is-selected');
		$(document.body).trigger('update_checkout');
	});
});
