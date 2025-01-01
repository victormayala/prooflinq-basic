<div class="prooflinq-registration-form">
    <div class="prooflinq-registration-header">
        <h2>Welcome to <?php echo esc_html(get_bloginfo('name')); ?></h2>
        <p>Please provide your information to access the feedback system.</p>
    </div>
    <form id="prooflinq-register-form">
        <input type="hidden" name="token" value="<?php echo esc_attr($_GET['token']); ?>">
        
        <div class="prooflinq-form-group">
            <label for="prooflinq-name">Your Name</label>
            <input type="text" id="prooflinq-name" name="name" required>
        </div>

        <div class="prooflinq-form-group">
            <label for="prooflinq-email">Your Email</label>
            <input type="email" id="prooflinq-email" name="email" required>
        </div>

        <div class="prooflinq-form-footer">
            <button type="submit" class="button button-primary">Continue</button>
        </div>
    </form>
</div> 