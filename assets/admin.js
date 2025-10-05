/**
 * Admin JavaScript for Mail Extractor
 *
 * @package MailExtractor
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Show message
	 */
	function showMessage(message, type) {
		const $msg = $('#mail-extractor-message');
		$msg.removeClass('error loading').text(message);
		
		if (type === 'error') {
			$msg.addClass('error');
		} else if (type === 'loading') {
			$msg.addClass('loading');
		}
		
		$msg.show();
		
		if (type !== 'loading') {
			setTimeout(function() {
				$msg.fadeOut();
			}, 5000);
		}
	}

	/**
	 * Test connection
	 */
	$('#test-connection').on('click', function(e) {
		e.preventDefault();
		
		const $btn = $(this);
		const originalText = $btn.text();
		
		// Disable button and show loading
		$btn.prop('disabled', true).addClass('loading');
		showMessage('Testing connection...', 'loading');
		
		// Get form data
		const data = {
			action: 'mail_extractor_test_connection',
			nonce: mailExtractorData.nonce,
			server: $('#pop3_server').val(),
			port: $('#pop3_port').val(),
			username: $('#username').val(),
			password: $('#password').val(),
			app_password: $('#app_password').val(),
			use_ssl: $('#use_ssl').is(':checked') ? '1' : '0'
		};
		
		// Send AJAX request
		$.post(mailExtractorData.ajaxurl, data, function(response) {
			if (response.success) {
				showMessage(response.data, 'success');
			} else {
				showMessage(response.data, 'error');
			}
		}).fail(function() {
			showMessage('Connection test failed. Please check your settings.', 'error');
		}).always(function() {
			$btn.prop('disabled', false).removeClass('loading');
		});
	});

	/**
	 * Import now
	 */
	$('#import-now').on('click', function(e) {
		e.preventDefault();
		
		const $btn = $(this);
		const originalText = $btn.text();
		
		// Disable button and show loading
		$btn.prop('disabled', true).addClass('loading');
		showMessage('Importing emails...', 'loading');
		
		// Get form data
		const data = {
			action: 'mail_extractor_import_now',
			nonce: mailExtractorData.nonce
		};
		
		// Send AJAX request
		$.post(mailExtractorData.ajaxurl, data, function(response) {
			if (response.success) {
				showMessage(response.data, 'success');
			} else {
				showMessage(response.data, 'error');
			}
		}).fail(function() {
			showMessage('Import failed. Please check your settings and logs.', 'error');
		}).always(function() {
			$btn.prop('disabled', false).removeClass('loading');
		});
	});

})(jQuery);
