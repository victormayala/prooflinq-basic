    <div class="feedback-details">
        <h3><?php echo esc_html($feedback->title); ?></h3>
        
        <div class="feedback-meta">
            <span class="date"><?php echo esc_html(date('M j, Y g:i a', strtotime($feedback->created_at))); ?></span>
            <span class="submitter"><?php echo esc_html($feedback->submitted_by); ?></span>
        </div>

        <div class="feedback-content">
            <p class="description"><?php echo nl2br(esc_html($feedback->description)); ?></p>
            
            <?php if (!empty($feedback->page_url)): ?>
            <p class="page-url">
                <strong>Page URL:</strong>
                <a href="<?php echo esc_url($feedback->page_url); ?>" target="_blank"><?php echo esc_html($feedback->page_url); ?></a>
            </p>
            <?php endif; ?>

            <?php if (!empty($feedback->screenshot_url)): ?>
            <div class="screenshot">
                <strong>Screenshot:</strong>
                <a href="<?php echo esc_url($feedback->screenshot_url); ?>" target="_blank" class="view-screenshot" data-screenshot="<?php echo esc_url($feedback->screenshot_url); ?>">
                    View Screenshot
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div> 