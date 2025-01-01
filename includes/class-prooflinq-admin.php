<?php
class Prooflinq_Admin {
    private $items_per_page;
    private $nonce_lifetime = 12 * HOUR_IN_SECONDS;
    private $plugin_nonce_key = 'prooflinq_secure_nonce';
    private $premium;

    public function __construct() {
        if (!$this->verify_admin_access()) {
            return;
        }

        $this->items_per_page = 10;
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_update_feedback_status', array($this, 'update_feedback_status'));
        add_action('wp_ajax_get_feedback_details', array($this, 'get_feedback_details'));
        add_action('wp_ajax_generate_access_link', array($this, 'handle_generate_link'));
        add_action('wp_ajax_add_prooflinq_comment', array($this, 'add_comment'));
        add_action('wp_ajax_delete_prooflinq_comment', array($this, 'delete_comment'));
        add_action('wp_ajax_get_prooflinq_comments', array($this, 'get_comments'));
    }

    private function verify_admin_access() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        return true;
    }

    private function verify_nonce($nonce, $action = -1) {
        $nonce_key = $this->plugin_nonce_key;
        if ($action === -1) {
            $action = $nonce_key;
        }
        
        if (!wp_verify_nonce($nonce, $action)) {
            wp_die('Security check failed', 'Security Error', array(
                'response' => 403,
                'back_link' => true,
            ));
        }
        return true;
    }

    private function create_nonce($action = -1) {
        $nonce_key = $this->plugin_nonce_key;
        if ($action === -1) {
            $action = $nonce_key;
        }
        return wp_create_nonce($action);
    }

    private function verify_ajax_nonce($nonce_name = 'nonce') {
        if (!check_ajax_referer($this->plugin_nonce_key, $nonce_name, false)) {
            wp_send_json_error(array(
                'message' => 'Invalid security token',
                'code' => 'invalid_nonce'
            ), 403);
        }
        return true;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Prooflinq',
            'Prooflinq',
            'manage_options',
            'prooflinq',
            array($this, 'render_admin_page'),
            'dashicons-feedback',
            30
        );

        // Add Feedback List as first submenu
        add_submenu_page(
            'prooflinq',
            'Feedback List',
            'Feedback List',
            'manage_options',
            'prooflinq',
            array($this, 'render_admin_page')
        );

        // Add Access Management submenu
        add_submenu_page(
            'prooflinq',
            'Access Management',
            'Access Management',
            'manage_options',
            'prooflinq-access',
            array($this, 'render_access_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, array('toplevel_page_prooflinq', 'prooflinq_page_prooflinq-access'))) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style('dashicons');
        wp_enqueue_style('prooflinq-admin', plugin_dir_url(dirname(__FILE__)) . 'css/prooflinq-admin.css');

        // Enqueue JavaScript files
        wp_enqueue_script('prooflinq-admin', plugin_dir_url(dirname(__FILE__)) . 'js/prooflinq-admin.js', array('jquery'), null, true);
        wp_enqueue_script('prooflinq-access', plugin_dir_url(dirname(__FILE__)) . 'js/prooflinq-access.js', array('jquery'), null, true);

        // Localize scripts with security nonce
        wp_localize_script('prooflinq-admin', 'prooflinqAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('prooflinq_admin_nonce')
        ));

        wp_localize_script('prooflinq-access', 'prooflinqAccess', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('prooflinq_generate_link')
        ));
    }

    public function get_feedback_details() {
        check_ajax_referer('prooflinq_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $feedback_id = isset($_POST['feedback_id']) ? intval($_POST['feedback_id']) : 0;
        if (!$feedback_id) {
            wp_send_json_error('Invalid feedback ID');
        }

        global $wpdb;
        $feedback_table = $wpdb->prefix . 'prooflinq_feedback';
        $comments_table = $wpdb->prefix . 'prooflinq_comments';

        // Get feedback details with a lock
        $feedback = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $feedback_table WHERE id = %d",
            $feedback_id
        ));

        if (!$feedback) {
            wp_send_json_error('Feedback not found');
            return;
        }

        // Get comments with proper join and conditions
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, u.display_name as user_name 
            FROM $comments_table c 
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
            WHERE c.feedback_id = %d 
            ORDER BY c.created_at DESC",
            $feedback_id
        ));

        // Format comments HTML
        $comments_html = '';
        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $comments_html .= sprintf(
                    '<div class="prooflinq-comment">
                        <div class="comment-header">
                            <span class="comment-author">%s</span>
                            <span class="comment-date">%s</span>
                            <button class="delete-comment" data-id="%d">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                        <div class="comment-content">%s</div>
                    </div>',
                    esc_html($comment->user_name),
                    esc_html(date('m/d/Y g:i A', strtotime($comment->created_at))),
                    esc_attr($comment->id),
                    esc_html($comment->comment)
                );
            }
        } else {
            $comments_html = '<p class="no-comments">No notes yet.</p>';
        }

        // Format category name
        $categories = array(
            'bug' => 'Bug Report',
            'feature' => 'Feature Request',
            'improvement' => 'Improvement',
            'general' => 'General Feedback'
        );
        $category_name = $categories[$feedback->category] ?? ucfirst($feedback->category);

        // Format status name
        $statuses = array(
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved'
        );
        $status_name = $statuses[$feedback->status] ?? ucfirst($feedback->status);

        wp_send_json_success(array(
            'id' => $feedback->id,
            'title' => $feedback->title,
            'description' => $feedback->description,
            'date' => date('m/d/Y', strtotime($feedback->created_at)),
            'status' => $status_name,
            'category' => $category_name,
            'submitted_by' => $feedback->submitted_by,
            'page_url' => $feedback->page_url,
            'screenshot_url' => $feedback->screenshot_url,
            'attachment_url' => $feedback->attachment_url,
            'comments' => $comments_html
        ));
    }

    public function render_admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'prooflinq_feedback';

        // Get current page
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Get sorting parameters
        $orderby = isset($_GET['orderby']) ? sanitize_sql_orderby($_GET['orderby']) : 'created_at';
        $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Calculate offset
        $offset = ($current_page - 1) * $this->items_per_page;
        
        // Get total items count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Calculate total pages
        $total_pages = ceil($total_items / $this->items_per_page);
        
        // Get items for current page
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
            $this->items_per_page,
            $offset
        ));

        ?>
        <div class="wrap">
            <h1>Prooflinq Feedback</h1>
            <hr class="wp-header-end">

            <?php do_action('prooflinq_after_header'); ?>
            <?php settings_errors(); ?>

            <div class="tablenav top">
                <!-- Separate form for filters -->
                <form method="get" class="alignleft actions filter-form">
                    <input type="hidden" name="page" value="prooflinq">
                    
                    <input type="date" 
                           name="date_from" 
                           value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>" 
                           placeholder="From Date"
                           class="prooflinq-date-filter">

                    <input type="date" 
                           name="date_to" 
                           value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>" 
                           placeholder="To Date"
                           class="prooflinq-date-filter">

                    <input type="submit" class="button button-filter" value="Filter">
                    <?php if (isset($_GET['date_from']) || isset($_GET['date_to'])): ?>
                        <a href="<?php echo esc_url(remove_query_arg(array('date_from', 'date_to'))); ?>" class="button">Reset Filters</a>
                    <?php endif; ?>
                </form>

                <!-- Separate form for bulk actions -->
                <form method="post" id="feedback-list-form">
                    <?php wp_nonce_field('prooflinq_bulk_action', 'prooflinq_nonce'); ?>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="sortable">Date</th>
                                <th class="sortable">Title</th>
                                <th>Screenshot</th>
                                <th>Page URL</th>
                                <th>Submitted By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr data-id="<?php echo esc_attr($item->id); ?>">
                                <td><?php echo esc_html(date('m/d/Y', strtotime($item->created_at))); ?></td>
                                <td><?php echo esc_html($item->title); ?></td>
                                <td>
                                    <?php if (!empty($item->screenshot_url)): ?>
                                        <a href="#" class="view-screenshot" data-screenshot="<?php echo esc_url($item->screenshot_url); ?>">
                                            <img src="<?php echo esc_url($item->screenshot_url); ?>" alt="Screenshot" style="max-width: 100px; height: auto;">
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><a href="<?php echo esc_url($item->page_url); ?>" target="_blank"><?php echo esc_url($item->page_url); ?></a></td>
                                <td><?php echo esc_html($item->submitted_by); ?></td>
                                <td>
                                    <button class="button view-feedback" data-id="<?php echo esc_attr($item->id); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <span class="items-per-page">
                                Show 
                                <select class="prooflinq-items-per-page">
                                    <?php
                                    $options = array(10, 25, 50);
                                    foreach ($options as $option) {
                                        printf(
                                            '<option value="%d" %s>%d</option>',
                                            $option,
                                            selected($this->items_per_page, $option, false),
                                            $option
                                        );
                                    }
                                    ?>
                                </select>
                                items
                            </span>
                            <span class="displaying-num">
                                <?php 
                                printf(
                                    _n('%s item', '%s items', $total_items, 'prooflinq'),
                                    number_format_i18n($total_items)
                                ); 
                                ?>
                            </span>
                            <span class="pagination-links">
                                <?php
                                $current_url = remove_query_arg(array('paged', 'items_per_page'));
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%', $current_url),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                    'mid_size' => 2,
                                    'type' => 'plain'
                                ));
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Screenshot Modal -->
        <div id="screenshot-modal" class="prooflinq-modal">
            <div class="prooflinq-modal-content screenshot-content">
                <span class="prooflinq-modal-close">&times;</span>
                <img id="modal-screenshot" src="" alt="Screenshot" style="max-width: 100%; height: auto;">
            </div>
        </div>

        <!-- Feedback Details Modal -->
        <div id="prooflinq-modal" class="prooflinq-modal">
            <div class="prooflinq-modal-content">
                <span class="prooflinq-modal-close">&times;</span>
                <div class="prooflinq-modal-header">
                    <h2 id="prooflinq-modal-title"></h2>
                    <span id="prooflinq-modal-date" class="prooflinq-modal-date"></span>
                </div>
                <div class="prooflinq-modal-body">
                    <div class="prooflinq-modal-details">
                        <div class="detail-item">
                            <span class="detail-label">Submitted By:</span>
                            <span id="prooflinq-modal-submitter"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span id="prooflinq-modal-status"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Category:</span>
                            <span id="prooflinq-modal-category"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Page URL:</span>
                            <a id="prooflinq-modal-url" href="" target="_blank"></a>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Description:</span>
                            <p id="prooflinq-modal-description"></p>
                        </div>

                        <div class="prooflinq-comments">
                            <h4>Admin Notes</h4>
                            <div id="prooflinq-comments-list"></div>
                            <div class="prooflinq-comment-form">
                                <textarea id="prooflinq-comment-text" placeholder="Add a note..."></textarea>
                                <button class="button button-primary" id="prooflinq-add-comment">Add Note</button>
                            </div>
                        </div>
                    </div>

                    <div class="prooflinq-modal-content-right">
                        <div class="prooflinq-modal-screenshot">
                            <img id="prooflinq-modal-image" src="" alt="Feedback Screenshot">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Modal -->
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
        <?php
    }

    public function add_comment() {
        // Verify nonce
        if (!check_ajax_referer('prooflinq_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Invalid security token',
                'code' => 'invalid_nonce'
            ), 403);
        }
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'You do not have permission to perform this action',
                'code' => 'insufficient_permissions'
            ), 403);
        }

        // Validate and sanitize inputs
        $feedback_id = filter_input(INPUT_POST, 'feedback_id', FILTER_VALIDATE_INT);
        $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
        
        if (!$feedback_id || !$comment) {
            wp_send_json_error(array(
                'message' => 'Missing required fields',
                'code' => 'missing_fields'
            ), 400);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'prooflinq_comments';
        
        // Verify feedback exists
        $feedback_table = $wpdb->prefix . 'prooflinq_feedback';
        $feedback = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $feedback_table WHERE id = %d",
            $feedback_id
        ));

        if (!$feedback) {
            wp_send_json_error(array(
                'message' => 'Feedback not found',
                'code' => 'not_found'
            ), 404);
        }

        try {
            $result = $wpdb->insert(
                $table_name,
                array(
                    'feedback_id' => $feedback_id,
                    'user_id' => get_current_user_id(),
                    'comment' => $comment
                ),
                array('%d', '%d', '%s')
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            $comment_id = $wpdb->insert_id;
            $user = wp_get_current_user();
            
            wp_send_json_success(array(
                'id' => $comment_id,
                'comment' => $comment,
                'user_name' => $user->display_name,
                'created_at' => current_time('mysql'),
                'message' => 'Comment added successfully'
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to add comment',
                'code' => 'insert_error',
                'error' => $e->getMessage()
            ), 500);
        }
    }

    public function delete_comment() {
        // Verify nonce
        if (!check_ajax_referer('prooflinq_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Invalid security token',
                'code' => 'invalid_nonce'
            ), 403);
        }
        
        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'You do not have permission to perform this action',
                'code' => 'insufficient_permissions'
            ), 403);
        }

        // Validate and sanitize input
        $comment_id = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
        if (!$comment_id) {
            wp_send_json_error(array(
                'message' => 'Invalid comment ID',
                'code' => 'invalid_input'
            ), 400);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'prooflinq_comments';
        
        // Verify comment exists
        $comment = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE id = %d",
            $comment_id
        ));

        if (!$comment) {
            wp_send_json_error(array(
                'message' => 'Comment not found',
                'code' => 'not_found'
            ), 404);
        }

        try {
            $result = $wpdb->delete(
                $table_name,
                array('id' => $comment_id),
                array('%d')
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            // Log the action
            error_log(sprintf(
                'Comment ID %d deleted by user ID %d',
                $comment_id,
                get_current_user_id()
            ));

            wp_send_json_success(array(
                'message' => 'Comment deleted successfully'
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to delete comment',
                'code' => 'delete_error',
                'error' => $e->getMessage()
            ), 500);
        }
    }

    public function get_comments() {
        check_ajax_referer('prooflinq_admin_nonce', 'nonce');
        
        $feedback_id = intval($_GET['feedback_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'prooflinq_comments';
        
        $comments = $wpdb->get_results($wpdb->prepare("
            SELECT c.*, u.display_name as user_name
            FROM $table_name c
            LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE feedback_id = %d
            ORDER BY created_at DESC
        ", $feedback_id));
        
        wp_send_json_success($comments);
    }

    public function render_access_page() {
        ?>
        <div class="wrap">
            <h1>Access Management</h1>
            
            <div class="prooflinq-generate-link">
                <h2>Generate New Access Link</h2>
                <form id="generate-link-form" method="post">
                    <?php wp_nonce_field('prooflinq_generate_link', 'prooflinq_nonce'); ?>
                    
                    <div class="name-fields">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Generate Link</button>
                    </p>
                </form>
            </div>

            <div class="prooflinq-active-links">
                <h2>Active Links</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Access Link</th>
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'prooflinq_authorized_users';
                        $items = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
                        
                        foreach ($items as $item):
                            $status_class = $item->status === 'active' ? 'status-active' : 'status-revoked';
                        ?>
                            <tr>
                                <td><?php echo esc_html($item->name ?: 'Not registered'); ?></td>
                                <td>
                                    <input type="text" readonly value="<?php echo esc_url(add_query_arg('feedback_token', $item->token, home_url())); ?>" class="access-link">
                                    <button class="button copy-link">Copy</button>
                                </td>
                                <td><?php echo date('m/d/Y', strtotime($item->created_at)); ?></td>
                                <td><span class="status-badge status-active">Active</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Rest of the access management page -->
        </div>
        <?php
    }

    public function update_feedback_status() {
        check_ajax_referer('prooflinq_admin_nonce', 'prooflinq_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $feedback_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$feedback_id || !in_array($new_status, array('open', 'in_progress', 'resolved'))) {
            wp_send_json_error('Invalid parameters');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'prooflinq_feedback';

        $result = $wpdb->update(
            $table_name,
            array('status' => $new_status),
            array('id' => $feedback_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success();
        }

        wp_send_json_error('Failed to update status');
    }

    public function render_feedback_list() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'prooflinq_feedback';
        
        // Get current page number
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $this->items_per_page;
        
        // Get total items
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_pages = ceil($total_items / $this->items_per_page);
        
        // Get items for current page
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $this->items_per_page,
            $offset
        ));

        ?>
        <div class="wrap">
            <div class="prooflinq-header">
                <h1>Feedback List</h1>
                <div class="prooflinq-header-actions">
                    <button type="button" class="button export-csv">
                        Export to CSV
                    </button>
                </div>
            </div>
            <?php do_action('prooflinq_after_header'); ?>
            
            <div class="prooflinq-filters">
                <!-- Add filter controls here -->
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Submitted By</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7">No feedback found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="#" class="view-feedback" data-id="<?php echo esc_attr($item->id); ?>">
                                            <?php echo esc_html($item->title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo wp_trim_words($item->description, 10); ?></td>
                                <td><?php echo esc_html($item->category); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($item->status); ?>">
                                        <?php echo ucfirst($item->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($item->submitted_by); ?></td>
                                <td><?php echo date('m/d/Y', strtotime($item->created_at)); ?></td>
                                <td>
                                    <button type="button" 
                                            class="button button-small view-feedback" 
                                            data-id="<?php echo esc_attr($item->id); ?>">
                                        View
                                    </button>
                                    <button type="button" 
                                            class="button button-small delete-feedback" 
                                            data-id="<?php echo esc_attr($item->id); ?>">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_premium_features() {
        ?>
        <div class="wrap">
            <h2>Premium Features</h2>
            <?php if (!$this->premium->has_premium_access()): ?>
                <div class="premium-upgrade-notice">
                    <h3>Upgrade to Premium</h3>
                    <p>Get access to advanced features:</p>
                    <ul>
                        <li>✓ Store up to 1000 feedback items</li>
                        <li>✓ Support for Office documents (.doc, .xls, .ppt)</li>
                        <li>✓ Priority support</li>
                    </ul>
                    <a href="<?php echo esc_url(pro_fs()->get_upgrade_url()); ?>" class="button button-primary">Upgrade Now</a>
                </div>
            <?php else: ?>
                <div class="premium-active-notice">
                    <h3>Premium Features Active</h3>
                    <p>Thank you for being a premium user! You have access to all premium features.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_generate_link() {
        check_ajax_referer('prooflinq_generate_link', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'), 403);
        }

        $first_name = isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '';
        
        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(array('message' => 'First name and last name are required'), 400);
        }

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'prooflinq_authorized_users';
            
            // Generate unique token
            $token = wp_generate_password(32, false);
            
            // Insert new record
            $result = $wpdb->insert(
                $table_name,
                array(
                    'name' => trim($first_name . ' ' . $last_name),
                    'token' => $token,
                    'status' => 'active',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s')
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            $access_link = add_query_arg('feedback_token', $token, home_url());
            
            wp_send_json_success(array(
                'message' => 'Access link generated successfully',
                'link' => $access_link
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Failed to generate link: ' . $e->getMessage()
            ), 500);
        }
    }

    // ... rest of the file remains unchanged ...
} 
