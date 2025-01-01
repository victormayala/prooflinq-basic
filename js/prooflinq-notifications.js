(function($) {
    'use strict';

    class ProoflinqNotifications {
        constructor() {
            this.modal = $('#prooflinq-notification-modal');
            this.form = $('#prooflinq-notification-form');
            this.selectedFeedback = [];
            this.setupEventListeners();
        }

        setupEventListeners() {
            // Handle bulk action selection
            $('#feedback-list-form').on('submit', (e) => {
                const action = $('select[name="bulk_action"]').val();
                if (action === 'notify') {
                    e.preventDefault();
                    this.handleNotifyAction();
                }
            });

            // Close modal
            this.modal.find('.prooflinq-modal-close, .cancel-notification').on('click', () => {
                this.closeModal();
            });

            // Handle form submission
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.sendNotification();
            });
        }

        handleNotifyAction() {
            this.selectedFeedback = [];
            $('input[name="feedback[]"]:checked').each((i, el) => {
                this.selectedFeedback.push($(el).val());
            });

            if (this.selectedFeedback.length === 0) {
                alert('Please select at least one feedback item');
                return;
            }

            // Prepare default message
            const count = this.selectedFeedback.length;
            const message = `Selected feedback items: ${count}\n\nPlease review the feedback at: ${window.location.href}`;
            this.form.find('#notification-message').val(message);

            this.openModal();
        }

        openModal() {
            this.modal.fadeIn(200);
        }

        closeModal() {
            this.modal.fadeOut(200);
            this.form[0].reset();
        }

        async sendNotification() {
            const submitButton = this.form.find('.send-notification');
            submitButton.prop('disabled', true).text('Sending...');

            try {
                const response = await fetch(prooflinqAdmin.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'send_bulk_notification',
                        nonce: prooflinqAdmin.nonce,
                        feedback: this.selectedFeedback,
                        recipients: this.form.find('#notification-recipients').val(),
                        subject: this.form.find('#notification-subject').val(),
                        message: this.form.find('#notification-message').val()
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Notification sent successfully!');
                    this.closeModal();
                } else {
                    throw new Error(result.data || 'Failed to send notification');
                }
            } catch (error) {
                console.error('Notification Error:', error);
                alert('Failed to send notification: ' + error.message);
            } finally {
                submitButton.prop('disabled', false).text('Send Notification');
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new ProoflinqNotifications();
    });

})(jQuery); 