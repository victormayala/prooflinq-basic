<?php
require_once plugin_dir_path(__FILE__) . 'class-prooflinq-auth.php';
require_once plugin_dir_path(__FILE__) . 'class-prooflinq-logger.php';

class Prooflinq_Feedback {
    private $table_name;
    private $auth;
    private $logger;
    private $allowed_file_types;
    private $max_items;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'prooflinq_feedback';
        $this->auth = new Prooflinq_Auth();
        $this->logger = Prooflinq_Logger::get_instance();

        // Set default values
        $default_file_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
        $default_max_items = 100;

        $this->allowed_file_types = $default_file_types;
        $this->max_items = $default_max_items;

        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    public function register_rest_routes() {
        register_rest_route('prooflinq/v1', '/feedback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_feedback_submission'),
            'permission_callback' => '__return_true',
            'args' => array(
                'title' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'description' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
                'coordinates' => array(
                    'required' => true,
                    'type' => 'object',
                ),
                'pageUrl' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
                'screenshot' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
    }

    private function handle_screenshot_upload($base64_image) {
        // Validate input
        if (empty($base64_image)) {
            throw new Exception('No screenshot data provided');
        }

        // Extract the actual base64 data
        $matches = array();
        if (!preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,(.+)$/i', $base64_image, $matches)) {
            throw new Exception('Invalid image format');
        }

        $image_type = strtolower($matches[1]); // png, jpeg, jpg, gif
        $image_data = base64_decode($matches[2]);

        if ($image_data === false) {
            throw new Exception('Invalid base64 data');
        }

        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if (strlen($image_data) > $max_size) {
            throw new Exception('Screenshot size exceeds maximum limit of 5MB');
        }

        // Validate image content
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->buffer($image_data);
        $allowed_types = array(
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/gif'
        );

        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Invalid image type. Only PNG, JPEG, and GIF are allowed.');
        }

        // Create secure upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $prooflinq_dir = $upload_dir['basedir'] . '/prooflinq-screenshots';
        
        if (!file_exists($prooflinq_dir)) {
            if (!wp_mkdir_p($prooflinq_dir)) {
                throw new Exception('Failed to create upload directory');
            }
            
            // Create .htaccess to prevent direct access
            $htaccess_content = "Order deny,allow\nDeny from all";
            if (!file_put_contents($prooflinq_dir . '/.htaccess', $htaccess_content)) {
                throw new Exception('Failed to secure upload directory');
            }

            // Create index.php to prevent directory listing
            $index_content = "<?php\n// Silence is golden.";
            if (!file_put_contents($prooflinq_dir . '/index.php', $index_content)) {
                throw new Exception('Failed to secure upload directory');
            }
        }

        // Generate secure filename
        $filename = sprintf(
            '%s-%s.%s',
            wp_hash(uniqid('', true) . random_bytes(16)),
            time(),
            $image_type
        );
        $filepath = $prooflinq_dir . '/' . $filename;

        // Save the file
        if (file_put_contents($filepath, $image_data) === false) {
            throw new Exception('Failed to save screenshot');
        }

        // Set proper file permissions
        chmod($filepath, 0644);

        // Return the URL (not the filesystem path)
        return $upload_dir['baseurl'] . '/prooflinq-screenshots/' . $filename;
    }

    private function handle_file_upload($base64_file) {
        if (empty($base64_file)) {
            return '';
        }

        // Include WordPress file handling functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Extract the file data
        $matches = array();
        if (!preg_match('/^data:([^;]+);base64,(.+)$/', $base64_file, $matches)) {
            throw new Exception('Invalid file format');
        }

        $mime_type = $matches[1];
        $file_data = base64_decode($matches[2]);

        if ($file_data === false) {
            throw new Exception('Invalid base64 data');
        }

        // Validate file size (6MB max)
        $max_size = 6 * 1024 * 1024;
        if (strlen($file_data) > $max_size) {
            throw new Exception('File size exceeds maximum limit of 6MB');
        }

        // Validate file type
        $allowed_types = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/gif'
        );

        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Invalid file type. Only PDF, DOC, DOCX, TXT, JPG, PNG, and GIF are allowed.');
        }

        // Get file extension from mime type
        $extensions = array(
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        );

        $extension = $extensions[$mime_type];

        // Generate secure filename
        $filename = sprintf(
            'prooflinq-attachment-%s.%s',
            wp_hash(uniqid('', true) . random_bytes(16)),
            $extension
        );

        // Get WordPress upload directory
        $upload_dir = wp_upload_dir();
        
        // Create temporary file
        $tmp_path = get_temp_dir() . $filename;
        if (file_put_contents($tmp_path, $file_data) === false) {
            throw new Exception('Failed to create temporary file');
        }

        // Prepare file array for media library
        $file_array = array(
            'name' => $filename,
            'type' => $mime_type,
            'tmp_name' => $tmp_path,
            'error' => 0,
            'size' => strlen($file_data)
        );

        // Insert file into media library
        $attachment_id = media_handle_sideload($file_array, 0);

        // Clean up temporary file
        @unlink($tmp_path);

        if (is_wp_error($attachment_id)) {
            throw new Exception('Failed to upload file: ' . $attachment_id->get_error_message());
        }

        // Get attachment URL
        $attachment_url = wp_get_attachment_url($attachment_id);
        
        if (!$attachment_url) {
            throw new Exception('Failed to get attachment URL');
        }

        return $attachment_url;
    }

    private function check_rate_limit($user_id) {
        $transient_key = 'prooflinq_rate_limit_' . md5($user_id);
        $submission_count = get_transient($transient_key);
        
        if ($submission_count === false) {
            // First submission in the time window
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
            return true;
        }
        
        if ($submission_count >= 10) { // Max 10 submissions per hour
            throw new Exception('Rate limit exceeded. Please try again later.');
        }
        
        // Increment submission count
        set_transient($transient_key, $submission_count + 1, HOUR_IN_SECONDS);
        return true;
    }

    public function handle_feedback_submission($request) {
        try {
            $this->logger->info('Starting feedback submission process');

            // Verify user is authorized
            $auth_user = $this->auth->get_authorized_user();
            if (!$auth_user) {
                $this->logger->warning('Authorization failed: No authorized user found');
                throw new Exception('Unauthorized access');
            }

            // Check rate limit
            $this->check_rate_limit($auth_user->id);

            $params = $request->get_params();
            $this->logger->info('Received feedback parameters', $params);
            
            // Set default category
            $params['category'] = 'general';
            
            // Validate feedback data
            try {
                $this->validate_feedback_data($params);
            } catch (Exception $e) {
                $this->logger->warning('Feedback validation failed: ' . $e->getMessage());
                throw $e;
            }

            // Handle screenshot if provided
            $screenshot_url = '';
            if (!empty($params['screenshot'])) {
                try {
                    $this->logger->info('Processing screenshot upload');
                    $screenshot_url = $this->handle_screenshot_upload($params['screenshot']);
                } catch (Exception $e) {
                    $this->logger->warning('Screenshot upload failed: ' . $e->getMessage());
                    // Continue without screenshot
                }
            }

            // Handle file attachment if provided
            $attachment_url = '';
            if (!empty($params['attachment'])) {
                try {
                    $this->logger->info('Processing file attachment');
                    $attachment_url = $this->handle_file_upload($params['attachment']);
                } catch (Exception $e) {
                    $this->logger->warning('File upload failed: ' . $e->getMessage());
                    throw $e; // File upload failure is critical
                }
            }

            // Get full name from authorized user
            $submitter_name = trim($auth_user->name);
            
            // Sanitize and prepare data
            $data = array(
                'title' => sanitize_text_field($params['title']),
                'description' => sanitize_textarea_field($params['description']),
                'coordinates' => sanitize_text_field($params['coordinates']),
                'page_url' => esc_url_raw($params['pageUrl']),
                'screenshot_url' => $screenshot_url,
                'attachment_url' => $attachment_url,
                'category' => isset($params['category']) ? sanitize_text_field($params['category']) : 'general',
                'status' => 'open',
                'submitted_by' => $submitter_name
            );

            $this->logger->info('Prepared data for insertion', $data);
            
            // Insert feedback
            global $wpdb;
            $wpdb->show_errors();
            
            $result = $wpdb->insert(
                $this->table_name,
                $data,
                array(
                    '%s', // title
                    '%s', // description
                    '%s', // coordinates
                    '%s', // page_url
                    '%s', // screenshot_url
                    '%s', // attachment_url
                    '%s', // category
                    '%s', // status
                    '%s'  // submitted_by
                )
            );

            if ($result === false) {
                $this->logger->error('Database error: ' . $wpdb->last_error);
                throw new Exception('Failed to save feedback: ' . $wpdb->last_error);
            }

            $feedback_id = $wpdb->insert_id;

            $this->logger->info(sprintf(
                'New feedback submitted successfully (ID: %d) by %s',
                $feedback_id,
                $submitter_name
            ));

            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Thank you for your feedback!',
                'data' => array(
                    'feedback_id' => $feedback_id
                )
            ), 200);

        } catch (Exception $e) {
            $this->logger->error('Feedback submission error: ' . $e->getMessage(), array(
                'stack_trace' => $e->getTraceAsString()
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to submit feedback: ' . $e->getMessage(),
                'code' => $e->getCode() ?: 500
            ), 500);
        }
    }

    private function send_verification_email($email, $token, $feedback_data) {
        $site_name = get_bloginfo('name');
        $verify_url = add_query_arg(array(
            'action' => 'verify_feedback',
            'token' => $token
        ), site_url());

        $subject = sprintf('[%s] Verify your feedback submission', $site_name);
        
        $message = sprintf(
            'Thank you for submitting feedback on %s!

To verify your feedback, please click the link below:
%s

Feedback Details:
Title: %s
Category: %s
Page URL: %s

This link will expire in 24 hours.

If you did not submit this feedback, please ignore this email.',
            $site_name,
            $verify_url,
            $feedback_data['title'],
            ucfirst($feedback_data['category']),
            $feedback_data['page_url']
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $site_name, get_option('admin_email'))
        );
        
        wp_mail($email, $subject, $message, $headers);
    }

    // Add verification handler
    public function verify_feedback() {
        if (empty($_GET['action']) || $_GET['action'] !== 'verify_feedback' || empty($_GET['token'])) {
            return;
        }

        global $wpdb;
        $token = sanitize_text_field($_GET['token']);
        $tokens_table = $wpdb->prefix . 'prooflinq_verification_tokens';
        
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $tokens_table WHERE token = %s AND verified_at IS NULL",
            $token
        ));

        if (!$token_data) {
            wp_die('Invalid or expired verification link.');
        }

        // Update token as verified
        $wpdb->update(
            $tokens_table,
            array('verified_at' => current_time('mysql')),
            array('id' => $token_data->id)
        );

        // Update feedback status
        $wpdb->update(
            $this->table_name,
            array('status' => 'open'),
            array('id' => $token_data->feedback_id)
        );

        // Send notification to admin
        $this->send_notification_email(
            $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $token_data->feedback_id
            )),
            $token_data->feedback_id
        );

        // Show success message
        wp_die('Thank you! Your feedback has been verified and submitted successfully.', 'Feedback Verified', array(
            'response' => 200,
            'back_link' => true,
        ));
    }

    private function send_notification_email($feedback_data, $feedback_id) {
        $recipients = Prooflinq_Settings::get_notification_emails();
        if (empty($recipients)) {
            return;
        }

        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        $subject = sprintf('[%s] New Feedback Received: %s', $site_name, $feedback_data['title']);
        
        $message = sprintf(
            'New feedback has been submitted on %s

Title: %s
Category: %s
Status: %s
Page URL: %s

Description:
%s

View feedback: %s',
            $site_name,
            $feedback_data['title'],
            ucfirst($feedback_data['category']),
            ucfirst($feedback_data['status']),
            $feedback_data['page_url'],
            $feedback_data['description'],
            admin_url('admin.php?page=prooflinq&feedback_id=' . $feedback_id)
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $site_name, $admin_email)
        );
        
        foreach ($recipients as $email) {
            wp_mail($email, $subject, $message, $headers);
        }
    }

    private function validate_feedback_data($params) {
        $required_fields = array('title', 'description', 'coordinates', 'pageUrl');
        foreach ($required_fields as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate and sanitize title
        if (strlen($params['title']) > 255) {
            throw new Exception('Title is too long (maximum 255 characters)');
        }

        // Validate URL
        if (!filter_var($params['pageUrl'], FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid page URL');
        }

        // Validate coordinates
        if (!isset($params['coordinates']['x']) || !isset($params['coordinates']['y']) ||
            !is_numeric($params['coordinates']['x']) || !is_numeric($params['coordinates']['y'])) {
            throw new Exception('Invalid coordinates data');
        }

        return true;
    }

    /**
     * Check if file type is allowed
     */
    private function is_file_type_allowed($file_type) {
        return in_array(strtolower($file_type), $this->allowed_file_types);
    }

    /**
     * Check if feedback limit is reached
     */
    private function is_feedback_limit_reached() {
        $feedback_count = $this->get_feedback_count();
        return $feedback_count >= $this->max_items;
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

        // Get feedback details
        $feedback = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $feedback_table WHERE id = %d",
            $feedback_id
        ));

        if (!$feedback) {
            wp_send_json_error('Feedback not found');
            return;
        }

        wp_send_json_success(array(
            'id' => $feedback->id,
            'title' => $feedback->title,
            'description' => $feedback->description,
            'date' => date('m/d/Y', strtotime($feedback->created_at)),
            'submitted_by' => $feedback->submitted_by,
            'page_url' => $feedback->page_url,
            'screenshot_url' => $feedback->screenshot_url,
        ));
    }

    public function render_admin_page() {
        global $wpdb;
        
        // Get feedback items
        $feedback_items = $wpdb->get_results(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC"
        );
        
        // Include template
        include plugin_dir_path(dirname(__FILE__)) . 'templates/admin-dashboard.php';
    }
} 