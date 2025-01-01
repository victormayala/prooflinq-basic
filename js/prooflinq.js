(function($) {
    'use strict';

    class ProoflinqFeedback {
        constructor() {
            this.isActive = false;
            this.form = $('#prooflinq-feedback-form');
            this.clickCoordinates = null;
            
            // Create and append overlay
            this.overlay = $('<div class="prooflinq-overlay"></div>');
            $('body').append(this.overlay);

  var urlParams = new URLSearchParams(window.location.search);
    var feedbackToken = urlParams.get('feedback_token');
    if (feedbackToken) {
        $('a[href]').each(function () {
            var href = $(this).attr('href');
            if (href.startsWith('/') || href.startsWith(window.location.origin)) {
                var separator = href.includes('?') ? '&' : '?';
                $(this).attr('href', href + separator + 'feedback_token=' + feedbackToken);
            }
        });
    }

            
            this.init();
        }

        init() {
            this.setupEventListeners();
        }

        setupEventListeners() {
            // Update button click handler
            $('.prooflinq-feedback-button button').on('click', (e) => {
                e.preventDefault();
                this.toggleFeedbackMode();
            });

            // Page click handler (for feedback placement)
            $('body').on('click', (e) => {
                if (!this.isActive) return;
                if ($(e.target).closest('#prooflinq-feedback-form, #wpadminbar, .prooflinq-feedback-button').length) return;
                e.preventDefault();
                e.stopPropagation();
                this.handlePageClick(e);
            });

            // Form submission
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.submitFeedback();
            });

            // Close button
            this.form.find('.prooflinq-close, .prooflinq-cancel').on('click', () => {
                this.hideForm();
            });
        }

        toggleFeedbackMode() {
            this.isActive = !this.isActive;
            $('body').toggleClass('prooflinq-active', this.isActive);
            
            // Update button text
            const buttonText = this.isActive ? 'Disable Feedback' : 'Enable Feedback';
            $('.prooflinq-feedback-button button').text(buttonText);

            // Hide form and markers when disabling
            if (!this.isActive) {
                this.hideForm();
            }
        }

        handlePageClick(e) {
            this.clickCoordinates = {
                x: e.pageX,
                y: e.pageY
            };

            this.showForm(e.clientX, e.clientY);
            this.addMarker(e.pageX, e.pageY);
        }

        showForm(x, y) {
            // Remove any existing success messages and show the form
            this.form.find('.prooflinq-success').remove();
            this.form.find('form').show();

            // Show overlay and form
            this.overlay.fadeIn(200);
            this.form.css({
                display: 'block',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)'
            });

            // Clear any previous input
            this.form.find('input, textarea').val('');
        }

        hideForm() {
            // Clear all form fields
            this.form.find('input[type="text"], textarea, select').val('');
            
            // Hide the form, overlay and remove the marker
            this.form.hide();
            this.overlay.fadeOut(200);
            $('.prooflinq-marker').remove();
            
            // Disable feedback mode
            this.isActive = false;
            $('body').removeClass('prooflinq-active');
            
            // Update button text
            $('.prooflinq-feedback-button button').text('Enable Feedback');
        }

        addMarker(x, y) {
            $('.prooflinq-marker').remove();
            $('body').append(`<div class="prooflinq-marker" style="left: ${x}px; top: ${y}px;"></div>`);
        }

        async submitFeedback() {
            try {
                // Show loading state
                const submitButton = this.form.find('.prooflinq-submit');
                submitButton.prop('disabled', true).text('Submitting...');

                // Capture screenshot
                const screenshot = await this.captureScreenshot();
                console.log(screenshot);
                
                $('body').append('<img class="img-red" src="'+screenshot+'">');

                // Handle file upload
                const fileInput = this.form.find('#prooflinq-attachment')[0];
                let attachment = null;
                
                if (fileInput && fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    
                    // Check file size (6MB max)
                    if (file.size > 6 * 1024 * 1024) {
                        throw new Error('File size exceeds 6MB limit');
                    }
                    
                    // Convert file to base64
                    attachment = await this.fileToBase64(file);
                }

                const response = await fetch(`${prooflinqData.restUrl}prooflinq/v1/feedback`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': prooflinqData.nonce
                    },
                    body: JSON.stringify({
                        title: this.form.find('#prooflinq-title').val(),
                        description: this.form.find('#prooflinq-description').val(),
                        category: this.form.find('#prooflinq-category').val(),
                        coordinates: this.clickCoordinates,
                        pageUrl: window.location.href,
                        screenshot: screenshot,
                        attachment: attachment || ''
                    })
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Network response was not ok');
                }

                if (result.success) {
                    // Hide form content and show success message
                    this.form.find('form').fadeOut(200, () => {
                        const successMessage = `
                            <div class="prooflinq-success">
                                <div class="success-icon">✓</div>
                                <h3>Thank you for your feedback!</h3>
                                <p>Your feedback has been submitted successfully.</p>
                            </div>
                        `;
                        this.form.append(successMessage);
                        
                        // Auto-hide after 2 seconds
                        setTimeout(() => {
                            this.hideForm();
                        }, 2000);
                    });
                } else {
                    throw new Error(result.message || 'Failed to submit feedback');
                }
            } catch (error) {
                this.form.find('.prooflinq-error').remove();
                this.form.find('form').prepend(`
                    <div class="prooflinq-error">
                        ${error.message}
                    </div>
                `);
            } finally {
                // Reset submit button (only if there was an error)
                if (!this.form.find('.success').length) {
                    this.form.find('.prooflinq-submit').prop('disabled', false).text('Submit Feedback');
                }
            }
        }

        async captureScreenshot() {
    try {
        // Hide the feedback form and overlay temporarily for the screenshot
        this.form.hide();
        this.overlay.hide();

        // Get the viewport dimensions
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        // Get the marker position relative to the document
        const marker = $('.prooflinq-marker');
        const markerPos = marker.offset();

        // Calculate the area around the marker (500px radius), adjusted for scroll
        const captureArea = {
            left: Math.max(0, markerPos.left - 500),
            top: Math.max(0, markerPos.top - 500 - window.scrollY),
            width: Math.min(1000, viewportWidth),
            height: Math.min(1000, viewportHeight)
        };

        // Temporarily move the marker to a fixed position
        const clonedMarker = marker.clone();
        clonedMarker.css('position', 'fixed');
       

        // Capture only the area around the marker
        const canvas = await html2canvas(document.body, {
            logging: false,
            useCORS: true,
            allowTaint: true,
            scrollX: 0,
            scrollY: -window.scrollY,
            x: captureArea.left,
            y: captureArea.top,
            width: captureArea.width,
            height: captureArea.height,
            imageTimeout: 5000, // 5-second timeout for images
            scale: 0.75 // Reduce quality for better performance
        });

        // Remove the temporary marker
        clonedMarker.remove();

        // Show the form and overlay again
        this.overlay.fadeIn(200);
        this.form.show();

        // Convert canvas to base64 image with reduced quality
        return canvas.toDataURL('image/jpeg', 0.8);
    } catch (error) {
        console.error('Screenshot capture failed:', error);

        // Show form and overlay in case of an error
        this.overlay.fadeIn(200);
        this.form.show();
        return null;
    }
}


        async fileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = () => resolve(reader.result);
                reader.onerror = error => reject(error);
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        window.prooflinqFeedback = new ProoflinqFeedback();
    });

})(jQuery); 