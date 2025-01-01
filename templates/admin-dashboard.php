<div class="wrap">
    <h1>Prooflinq Feedback Dashboard</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Date</th>
                <th>Title</th>
                <th>Description</th>
                <th>Screenshot</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($feedback_items as $item): ?>
            <tr>
                <td><?php echo esc_html($item->created_at); ?></td>
                <td><?php echo esc_html($item->title); ?></td>
                <td><?php echo esc_html($item->description); ?></td>
                <td>
                    <a href="#" class="screenshot-link">
                        <img src="<?php echo esc_url($item->screenshot_url); ?>" width="100">
                    </a>
                </td>
                <td>
                    <button class="button view-feedback" data-id="<?php echo esc_attr($item->id); ?>">View Details</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Feedback Details Modal -->
    <div id="prooflinq-modal" class="prooflinq-modal" style="display: none;">
        <div class="prooflinq-modal-content">
            <span class="prooflinq-modal-close">&times;</span>
            <div class="prooflinq-modal-header">
                <h2>Feedback Details</h2>
            </div>
            <div class="prooflinq-modal-body">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
    </div>
</div> 