jQuery(function ($) {
	function toggleCaptchaFields() {
		var provider = $('#lsl-captcha-provider').val();
		$('.lsl-field-recaptcha').toggle(provider === 'recaptcha');
		$('.lsl-field-turnstile').toggle(provider === 'turnstile');
	}

	if ($('#lsl-captcha-provider').length) {
		toggleCaptchaFields();
		$('#lsl-captcha-provider').on('change', toggleCaptchaFields);
	}
});
