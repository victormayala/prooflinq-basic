(function($) {
    'use strict';

    $(document).ready(function() {
        $('#prooflinq-register-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitButton = form.find('button[type="submit"]');
            
            submitButton.prop('disabled', true).text('Processing...');

            $.ajax({
                url: prooflinqData.ajaxurl,
                method: 'POST',
                data: {
                    action: 'prooflinq_register_user',
                    nonce: prooflinqData.nonce,
                    token: form.find('input[name="token"]').val(),
                    name: form.find('#prooflinq-name').val(),
                    email: form.find('#prooflinq-email').val()
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert(response.data || 'Registration failed');
                        submitButton.prop('disabled', false).text('Continue');
                    }
                },
                error: function() {
                    alert('Registration failed. Please try again.');
                    submitButton.prop('disabled', false).text('Continue');
                }
            });
        });
    });

})(jQuery); 