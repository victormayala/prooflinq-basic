<div id="prooflinq-notification-modal" class="prooflinq-modal" style="display: none;">
    <div class="prooflinq-modal-content">
        <span class="prooflinq-modal-close">&times;</span>
        <div class="prooflinq-modal-header">
            <h2>Send Notification</h2>
        </div>
        <div class="prooflinq-modal-body">
            <form id="prooflinq-notification-form">
                <div class="prooflinq-form-group">
                    <label for="notification-recipients">Recipients</label>
                    <textarea id="notification-recipients" rows="2" class="large-text" placeholder="Enter email addresses, separated by commas"></textarea>
                    <p class="description">Leave empty to use default notification recipients</p>
                </div>
                <div class="prooflinq-form-group">
                    <label for="notification-subject">Subject</label>
                    <input type="text" id="notification-subject" class="large-text" value="Feedback Update">
                </div>
                <div class="prooflinq-form-group">
                    <label for="notification-message">Message</label>
                    <textarea id="notification-message" rows="6" class="large-text"></textarea>
                </div>
                <div class="prooflinq-form-footer">
                    <button type="button" class="button cancel-notification">Cancel</button>
                    <button type="submit" class="button button-primary send-notification">Send Notification</button>
                </div>
            </form>
        </div>
    </div>
</div> 