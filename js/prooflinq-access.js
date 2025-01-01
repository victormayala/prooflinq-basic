jQuery(document).ready(function($) {
    // Generate new link
    $('#generate-link-form').on('submit', function(e) {
        e.preventDefault();
        
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true).text('Generating...');

        var formData = {
            action: 'generate_access_link',
            nonce: prooflinqAccess.nonce,
            first_name: $('#first_name').val(),
            last_name: $('#last_name').val()
        };

        $.ajax({
            url: prooflinqAccess.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to generate link');
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            },
            complete: function() {
                submitButton.prop('disabled', false).text('Generate Link');
            }
        });
    });

    // Copy link functionality
    $(document).on('click', '.copy-link', function() {
        const input = $(this).prev('.access-link');
        input.select();
        document.execCommand('copy');
        
        const button = $(this);
        const originalText = button.text();
        button.text('Copied!');
        setTimeout(() => {
            button.text(originalText);
        }, 2000);
    });
}); 