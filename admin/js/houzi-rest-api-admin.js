jQuery(document).ready(function ($) {
    "use strict";

	var ajaxurl = houzi_admin_vars.ajaxurl;
    var paid_text = houzi_admin_vars.paid_status;
    var install_now = houzi_admin_vars.install_now;
    var installing = houzi_admin_vars.installing;
    var installed = houzi_admin_vars.installed;
    var activate_now = houzi_admin_vars.activate_now;
    var activating = houzi_admin_vars.activating;
    var activated = houzi_admin_vars.activated;
    var active = houzi_admin_vars.active;
    var failed = houzi_admin_vars.failed;
	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	/*--------------------------------------------------------------
    * Elevenized
    * ------------------------------------------------------------*/
	$('#houzi-eleven-button').on('click', function(e) {
		e.preventDefault();
		
		var $button = $(this);
		var button_text = $button.text();
		var form_messages = $('#form-messages');
		var item_eleven_text = $('#item_eleven_field').val();
		var nonce = $('#eleven_nonce_field').val();

		// document.getElementById("admin-houzi-form").reset();
		// form_messages.html('<span class="success">'+"success"+'</span>');
		// setTimeout(function () {
		// 	form_messages.html('');
		// }, 2500);
		// return;

		if( !item_eleven_text ) {
			form_messages.html('<span class="error">Enter valid purchase code..</span>');

		} else {

			form_messages.html('');

			jQuery.ajax({
				type: 'POST',
				dataType: 'json',
				url: ajaxurl,
				data: {
					'action'             : 'houzi_lets_eleven',
					'item_eleven_text' : item_eleven_text,
					'nonce'              : nonce
				},
				beforeSend: function( ) {
					$button.addClass('updating-message');
				},
				complete: function(){
					$button.removeClass('updating-message');
				},
				success: function (response) {
					if (response.success) {
						document.getElementById("admin-houzi-form").reset();
						form_messages.html('<span class="success">'+response.msg+'</span>');
						location.reload();
					} else {
						form_messages.html('<span class="error">'+response.msg+'</span>');
					}
					

					setTimeout(function () {
						form_messages.html('');
					}, 2500);

				},
				error: function (errorThrown) {}
			});

		}

	});


	$('#houzi-twelve-button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var button_text = $button.text();
        var form_messages = $('#form-messages');
        var nonce = $('#eleven_nonce_field').val();

        form_messages.html('');

        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: ajaxurl,
            data: {
                'action'             : 'houzi_lets_twelve',
                'nonce'              : nonce
            },
            beforeSend: function( ) {
                $button.addClass('updating-message');
            },
            complete: function(){
                $button.removeClass('updating-message');
            },
            success: function (response) {

                if (response.success) {
                    document.getElementById("admin-houzi-form").reset();
                    form_messages.html('<span class="success">'+response.msg+'</span>');
                    location.reload();
                } else {
                    form_messages.html('<span class="error">'+response.msg+'</span>');
                }

                setTimeout(function () {
                    form_messages.html('');
                }, 2500);

            },
            error: function (errorThrown) {}
        });

    });
});
