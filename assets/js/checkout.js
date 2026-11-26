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

	/* Coupon code in the Order Summary (applied through WooCommerce's wc-ajax endpoints) */
	// The field sits inside the summary fragment WooCommerce redraws on every update_checkout,
	// so its state is kept here and put back after each redraw.
	var coupon = { value: '', msg: '', isError: false, fieldError: false, focus: false };
	var couponFlash = null; // message to show once the summary has been redrawn

	// Live regions outside the redrawn fragment, so screen readers hear every message.
	// Created at load (an announcement into a region that did not exist yet is often skipped).
	function liveRegion(isError) {
		var id = isError ? 'nvx-coupon-alert' : 'nvx-coupon-status';
		var $r = $('#' + id);
		if (!$r.length) {
			$r = $('<div/>', { id: id, role: isError ? 'alert' : 'status', 'aria-live': isError ? 'assertive' : 'polite' })
				.css({ position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)', whiteSpace: 'nowrap' })
				.appendTo(document.body);
		}
		return $r;
	}
	if ($('.nvx-coupon').length) { liveRegion(false); liveRegion(true); }

	function announce(text, isError) {
		var $r = liveRegion(isError);
		$r.text('');
		setTimeout(function () { $r.text(text); }, 50);
	}

	function paintCoupon() {
		var $box = $('.nvx-coupon');
		var bad = !!coupon.msg && coupon.isError;
		$box.find('.nvx-coupon__msg').text(coupon.msg)
			.toggleClass('is-error', bad)
			.toggleClass('is-success', !!coupon.msg && !coupon.isError);
		$box.toggleClass('is-invalid', bad && coupon.fieldError);
		if (bad && coupon.fieldError) $box.find('.nvx-coupon__input').attr('aria-invalid', 'true');
		else $box.find('.nvx-coupon__input').removeAttr('aria-invalid');
	}

	// fieldError = the typed code is the problem (red border + aria-invalid), not e.g. a network failure.
	function couponMsg(text, isError, fieldError) {
		coupon.msg = text || '';
		coupon.isError = !!isError;
		coupon.fieldError = !!isError && fieldError !== false;
		paintCoupon();
		if (text) announce(text, isError);
	}

	// wc-ajax returns printed notices as HTML; read only their text in an inert document (no scripts, no handlers).
	function noticeText(html) {
		var doc = new DOMParser().parseFromString(String(html || ''), 'text/html');
		var li = doc.body.querySelector('li');
		var text = (li || doc.body).textContent || '';
		return $.trim(text.replace(/\s+/g, ' '));
	}

	function isErrorNotice(html) {
		return /woocommerce-error|is-error/.test(String(html || ''));
	}

	function couponRequest(endpoint, payload) {
		var p = window.wc_checkout_params;
		var $box = $('.nvx-coupon');
		if (!p || $box.hasClass('is-busy')) return null;
		$box.addClass('is-busy').find('button').prop('disabled', true);
		return $.ajax({
			type: 'POST',
			url: p.wc_ajax_url.toString().replace('%%endpoint%%', endpoint),
			data: payload,
			dataType: 'html'
		}).always(function () {
			$box.removeClass('is-busy').find('button').prop('disabled', false);
		});
	}

	function applyCoupon() {
		var p = window.wc_checkout_params || {};
		var $input = $('#nvx_coupon_code');
		var code = $.trim($input.val() || '');
		if (!code) {
			couponMsg(i18n.couponEmpty || 'Please enter a coupon code.', true);
			$input.trigger('focus');
			return;
		}
		var req = couponRequest('apply_coupon', {
			security: p.apply_coupon_nonce,
			coupon_code: code,
			billing_email: $('#billing_email').val() || ''
		});
		if (!req) return;
		req.done(function (res) {
			if (isErrorNotice(res)) {
				couponMsg(noticeText(res) || i18n.couponFailed, true);
				$input.trigger('focus');
				return;
			}
			couponFlash = noticeText(res) || i18n.couponApplied || 'Coupon code applied successfully.';
			coupon.value = '';
			$(document.body).trigger('applied_coupon_in_checkout', [code]);
			$(document.body).trigger('update_checkout', { update_shipping_method: false });
		}).fail(function () {
			couponMsg(i18n.couponFailed || 'Could not apply the coupon. Please try again.', true);
		});
	}

	$(document).on('click', '.nvx-coupon__btn', function (e) {
		e.preventDefault();
		applyCoupon();
	});
	// Enter in the code field must apply the code, not submit (place) the order.
	$(document).on('keydown', '#nvx_coupon_code', function (e) {
		if (e.key === 'Enter' || e.which === 13) {
			e.preventDefault();
			applyCoupon();
		}
	});
	$(document).on('input', '#nvx_coupon_code', function () {
		coupon.value = $(this).val();
		if (coupon.msg && coupon.isError) couponMsg('');
	});
	$(document).on('focusin', '.nvx-coupon', function () { coupon.focus = true; });
	$(document).on('focusout', '.nvx-coupon', function () {
		var el = this;
		// Removed by a redraw (focus lost to <body>) → keep the flag so focus is put back.
		setTimeout(function () {
			if (document.body.contains(el) && !$.contains(el, document.activeElement)) coupon.focus = false;
		}, 0);
	});

	$(document).on('click', '.nvx-coupon__remove', function (e) {
		e.preventDefault();
		var p = window.wc_checkout_params || {};
		var code = String($(this).attr('data-coupon') || '');
		var req = couponRequest('remove_coupon', { security: p.remove_coupon_nonce, coupon: code });
		if (!req) return;
		var removeFailed = i18n.couponRemoveFailed || 'Could not remove the coupon. Please try again.';
		req.done(function (res) {
			if (isErrorNotice(res)) {
				couponMsg(noticeText(res) || removeFailed, true, false);
				return;
			}
			couponFlash = noticeText(res) || i18n.couponRemoved || 'Coupon has been removed.';
			$(document.body).trigger('removed_coupon_in_checkout', [code]);
			$(document.body).trigger('update_checkout', { update_shipping_method: false });
		}).fail(function () {
			couponMsg(removeFailed, true, false);
		});
	});

	// After any summary redraw: put back the typed code, the message and focus.
	$(document.body).on('updated_checkout', function () {
		var $input = $('#nvx_coupon_code');
		if (!$input.length) return;
		$input.val(coupon.value);
		if (couponFlash) {
			couponMsg(couponFlash, false);
			couponFlash = null;
		} else {
			if (coupon.msg && !coupon.isError) coupon.msg = ''; // a confirmation is shown once, then cleared
			paintCoupon();
		}
		// Focus goes back only when it was in the coupon box (keyboard users) — no surprise phone keyboard after a tap.
		if (coupon.focus && !$.contains($('.nvx-coupon')[0], document.activeElement)) {
			$input.trigger('focus');
		}
	});

	/* Packaging cards → recalc fees via AJAX */
	$(document).on('change', 'input[name="nvx_packaging"]', function () {
		$('.nvx-pack').removeClass('is-selected');
		$(this).closest('.nvx-pack').addClass('is-selected');
		$(document.body).trigger('update_checkout');
	});
});
