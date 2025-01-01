jQuery(document).ready(function($) {
    // Helper function to format dates
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: '2-digit',
            day: '2-digit',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    }

    // Helper function to show success messages
    function showSuccessMessage(message) {
        // Remove any existing success messages first
        $('.notice-success').remove();
        
        // Create and show new message
        const successMessage = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' + message + '</p></div>');
        
        // Insert message in the appropriate location based on the page
        const prooflinqHeader = $('.prooflinq-header');
        if (prooflinqHeader.length) {
            // On Prooflinq Feedback page
            successMessage.insertAfter(prooflinqHeader);
        } else {
            // On other pages (like Access Management)
            const pageHeader = $('.wrap > h1');
            if (pageHeader.length) {
                successMessage.insertAfter(pageHeader);
            } else {
                $('.wrap').prepend(successMessage);
            }
        }
    }

    // View feedback details
    $(document).on('click', '.view-feedback, .screenshot-link', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // If it's a screenshot link, show the screenshot modal instead
        if ($(this).hasClass('screenshot-link')) {
            const $modal = $('.prooflinq-modal-content.screenshot-content').closest('.prooflinq-modal');
            const screenshotUrl = $(this).find('img').attr('src');
            $modal.find('img').attr('src', screenshotUrl);
            $modal.show();
            return;
        }
        
        const feedbackId = $(this).is('.screenshot-link') ? $(this).closest('tr').find('.view-feedback').data('id') : $(this).data('id');
        
        $.ajax({
            url: prooflinqAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_feedback_details',
                feedback_id: feedbackId,
                nonce: prooflinqAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Build modal content
                    let content = `
                        <div class="prooflinq-modal-body-wrapper">
                            <div class="prooflinq-modal-details">
                                <div class="detail-item">
                                    <span class="detail-label">Submitted By:</span>
                                    <span id="prooflinq-modal-submitter">${data.submitted_by}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Page URL:</span>
                                    <a id="prooflinq-modal-url" href="${data.page_url}" target="_blank">${data.page_url}</a>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Description:</span>
                                    <p id="prooflinq-modal-description">${data.description}</p>
                                </div>
                            </div>

                            <div class="prooflinq-modal-content-right">
                                ${data.screenshot_url ? `
                                    <div class="prooflinq-modal-screenshot">
                                        <img id="prooflinq-modal-image" src="${data.screenshot_url}" alt="Feedback Screenshot">
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                    
                    $('.prooflinq-modal-body').html(content);
                    $('#prooflinq-modal-title').text(data.title);
                    $('#prooflinq-modal-date').text(data.date);
                    
                    // Show modal
                    const $modal = $('#prooflinq-modal');
                    $modal.show();
                    
                    // Remove any existing click handlers
                    $('.prooflinq-modal-close').off('click');
                    
                    // Add new click handler for close button
                    $('.prooflinq-modal-close').on('click', function() {
                        $('#prooflinq-modal').hide();
                    });
                    
                    // Handle click outside modal
                    $(window).on('click', function(e) {
                        if ($(e.target).is($modal)) {
                            $modal.hide();
                        }
                    });
                    
                    // Handle ESC key
                    $(document).on('keyup', function(e) {
                        if (e.key === 'Escape') {
                            $modal.hide();
                        }
                    });
                } else {
                    alert('Failed to load feedback details');
                }
            },
            error: function() {
                alert('Failed to load feedback details. Please try again.');
            }
        });
    });

    // Keep the ESC key handler
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            $('#prooflinq-modal').fadeOut(200);
        }
    });

    // Add comment
    $(document).on('click', '#prooflinq-add-comment', function() {
        const feedbackId = $(this).data('feedback-id');
        const commentText = $('#prooflinq-comment-text').val().trim();
        
        if (!commentText) {
            alert('Please enter a comment');
            return;
        }

        const button = $(this);
        button.prop('disabled', true).text('Adding...');

        $.ajax({
            url: prooflinqAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_prooflinq_comment',
                feedback_id: feedbackId,
                comment: commentText,
                nonce: prooflinqAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const commentHtml = `
                        <div class="prooflinq-comment">
                            <div class="comment-header">
                                <span class="comment-author">${data.user_name}</span>
                                <span class="comment-date">${formatDate(data.created_at)}</span>
                                <button class="delete-comment" data-id="${data.id}">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                            <div class="comment-content">${data.comment}</div>
                        </div>
                    `;
                    
                    if ($('#prooflinq-comments-list').find('p').length && $('#prooflinq-comments-list p').text() === 'No comments yet.') {
                        $('#prooflinq-comments-list').empty();
                    }
                    $('#prooflinq-comments-list').prepend(commentHtml);
                    $('#prooflinq-comment-text').val('');

                    // Show success message
                    const successMessage = $('<div class="notice notice-success" style="margin: 10px 0;"><p>Comment added successfully!</p></div>');
                    $('#prooflinq-comments-list').before(successMessage);
                    setTimeout(() => successMessage.fadeOut(() => successMessage.remove()), 2000);
                } else {
                    alert('Failed to add comment: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to add comment. Please try again. Error: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text('Add Comment');
            }
        });
    });

    // Delete comment
    $(document).on('click', '.delete-comment', function() {
        if (!confirm('Are you sure you want to delete this comment?')) {
            return;
        }

        const button = $(this);
        const commentElement = button.closest('.prooflinq-comment');
        const commentId = button.data('id');

        button.prop('disabled', true);

        $.ajax({
            url: prooflinqAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_prooflinq_comment',
                comment_id: commentId,
                nonce: prooflinqAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    commentElement.fadeOut(200, function() {
                        $(this).remove();
                        if ($('#prooflinq-comments-list').children().length === 0) {
                            $('#prooflinq-comments-list').html('<p>No comments yet.</p>');
                        }
                    });

                    // Show success message
                    const successMessage = $('<div class="notice notice-success" style="margin: 10px 0;"><p>Comment deleted successfully!</p></div>');
                    $('#prooflinq-comments-list').before(successMessage);
                    setTimeout(() => successMessage.fadeOut(() => successMessage.remove()), 2000);
                } else {
                    alert('Failed to delete comment: ' + (response.data.message || 'Unknown error'));
                    button.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to delete comment. Please try again. Error: ' + error);
                button.prop('disabled', false);
            }
        });
    });

    // Screenshot modal handler
    $(document).on('click', '.view-screenshot', function(e) {
        e.preventDefault();
        const screenshotUrl = $(this).data('screenshot');
        const modal = $('#screenshot-modal');
        
        // Set the image source
        $('#modal-screenshot').attr('src', screenshotUrl);
        
        // Show modal
        modal.fadeIn(200);
    });

    // Handle screenshot modal close button
    $(document).on('click', '.screenshot-content .prooflinq-modal-close', function() {
        $(this).closest('.prooflinq-modal').hide();
    });

    // Handle screenshot modal outside click
    $(document).on('click', '.prooflinq-modal', function(e) {
        if ($(e.target).is('.prooflinq-modal')) {
            $(this).hide();
        }
    });
}); 