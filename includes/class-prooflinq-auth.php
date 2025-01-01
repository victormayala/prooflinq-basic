<?php
class Prooflinq_Auth {
    private $table_name;
    public $cookie_name = 'prooflinq_user_token';
    private $cookie_expiry = MONTH_IN_SECONDS;
    private $logger;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'prooflinq_authorized_users';
        $this->logger = Prooflinq_Logger::get_instance();
        
        add_action('plugins_loaded', array($this, 'init'), 1);
        add_action('init', array($this, 'check_auth_token'), 1);
        add_action('wp_ajax_generate_feedback_link', array($this, 'generate_feedback_link'));
        add_action('wp_ajax_revoke_feedback_access', array($this, 'revoke_feedback_access'));
        add_action('wp_ajax_nopriv_revoke_feedback_access', array($this, 'revoke_feedback_access'));
    }

    public function init() {
        // Handle token validation and cookie setting here
        if (isset($_GET['feedback_token'])) {
            $token = sanitize_text_field($_GET['feedback_token']);
            $this->validate_and_process_token($token);
        }
    }

    public function check_auth_token() {
        if (headers_sent($filename, $linenum)) {
            $this->logger->warning('Headers already sent', array(
                'file' => $filename,
                'line' => $linenum
            ));
            return false;
        }
        

        // Check for token in URL
        if (isset($_GET['feedback_token'])) {

            $token = sanitize_text_field($_GET['feedback_token']);
            $this->logger->info('Checking URL token', array('token' => $token));
            
            if ($this->validate_and_process_token($token)) {
                $this->logger->info('URL token validated successfully');
                return true;
            } else {
                $this->logger->warning('Invalid URL token');
            }
        }
        
        // Check for cookie
        if (isset($_COOKIE[$this->cookie_name])) {
            $this->logger->info('Checking cookie token');
            return $this->validate_user_cookie();
        }

        $this->logger->info('No token found');
        return false;
    }

    private function validate_and_process_token($token) {
        global $wpdb;
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE token = %s 
            AND status = 'active'
            AND (expires_at IS NULL OR expires_at > NOW())",
            $token
        ));

        if ($user) {
            $this->logger->info('Token validated, setting cookie', array('user_id' => $user->id));
            
            // Set cookie with proper path and domain
            $this->set_user_cookie($token);
            
            // Update last access time with current server time
            $wpdb->update(
                $this->table_name,
                array('last_access' => current_time('mysql')),
                array('token' => $token)
            );

            return true;
        }
        
        $this->logger->warning('Token validation failed', array('token' => $token));
        return false;
    }

    private function validate_user_cookie() {
        $token = $_COOKIE[$this->cookie_name];
        global $wpdb;
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE token = %s 
            AND status = 'active'
            AND (expires_at IS NULL OR expires_at > NOW())",
            $token
        ));

        if (!$user) {
            // Clear invalid cookie
            $this->clear_user_cookie();
            return false;
        } else {
            // Update last access time with current server time
            $wpdb->update(
                $this->table_name,
                array('last_access' => current_time('mysql')),
                array('token' => $token)
            );

            return true;
        }
    }

    public function set_user_cookie($token) {
        // Check if headers have already been sent
        if (headers_sent()) {
            $this->logger->warning('Cannot set cookie - headers already sent');
            return false;
        }
        
        $secure = is_ssl();
        $httponly = true;
        
        if (setcookie(
            $this->cookie_name,
            $token,
            [
                'expires' => time() + $this->cookie_expiry,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => 'Lax'
            ]
        )) {
            // Set it in $_COOKIE for immediate use
            $_COOKIE[$this->cookie_name] = $token;
            return true;
        }
        
        $this->logger->error('Failed to set cookie');
        return false;
    }

    public function clear_user_cookie() {
        setcookie(
            $this->cookie_name,
            '',
            time() - 3600,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
        
        unset($_COOKIE[$this->cookie_name]);
        $this->logger->info('Cookie cleared');
    }

    public function generate_feedback_link() {
        // Verify nonce
        if (!check_ajax_referer('prooflinq_generate_link', 'nonce', false)) {
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
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);
        $expires_days = filter_input(INPUT_POST, 'expires_days', FILTER_VALIDATE_INT);
        $send_email = isset($_POST['send_email']) && $_POST['send_email'] === 'true';

        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($email)) {
            wp_send_json_error(array(
                'message' => 'Please fill in all required fields',
                'code' => 'missing_fields'
            ), 400);
        }

        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => 'Please enter a valid email address',
                'code' => 'invalid_email'
            ), 400);
        }
        
        // Generate secure token
        $token = wp_generate_password(32, false);
        
        // Calculate expiration date
        $expires_at = !empty($expires_days) ? 
            date('Y-m-d H:i:s', strtotime('+' . intval($expires_days) . ' days')) : 
            null;

        global $wpdb;
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'name' => $first_name . ' ' . $last_name,
                'email' => $email,
                'token' => $token,
                'created_by' => get_current_user_id(),
                'expires_at' => $expires_at,
                'status' => 'active'
            ),
            array(
                '%s', '%s', '%s', '%d', '%s', '%s'
            )
        );

        if ($result) {
            $feedback_url = add_query_arg('feedback_token', $token, home_url());
            
            if ($send_email) {
                $this->send_access_email($email, $first_name, $feedback_url, $expires_at);
            }

            wp_send_json_success(array(
                'url' => $feedback_url,
                'token' => $token,
                'message' => 'Link generated successfully'
            ));
        }

        wp_send_json_error(array(
            'message' => 'Failed to generate link',
            'code' => 'database_error'
        ), 500);
    }

    private function send_access_email($email, $first_name, $feedback_url, $expires_at) {
        $site_name = get_bloginfo('name');
        $subject = sprintf('[%s] Your Feedback Access Link', $site_name);
        
        $message = sprintf(
            'Hello %s,

You have been granted access to provide feedback on %s.

Click the link below to access the feedback system:
%s

%s

Thank you!',
            $first_name,
            $site_name,
            $feedback_url,
            $expires_at ? 'This link will expire on ' . date('m/d/Y', strtotime($expires_at)) : 'This link has no expiration date'
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $site_name, get_option('admin_email'))
        );
        
        wp_mail($email, $subject, $message, $headers);
    }

    public function revoke_feedback_access() {
        if (!check_ajax_referer('prooflinq_generate_link', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Invalid security token',
                'code' => 'invalid_nonce'
            ), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Unauthorized access',
                'code' => 'insufficient_permissions'
            ), 403);
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        if (empty($token)) {
            wp_send_json_error(array(
                'message' => 'Invalid token',
                'code' => 'invalid_input'
            ), 400);
        }

        global $wpdb;
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'revoked'),
            array('token' => $token),
            array('%s'),
            array('%s')
        );

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Access revoked successfully'
            ));
        }

        wp_send_json_error(array(
            'message' => 'Failed to revoke access',
            'code' => 'update_error'
        ), 500);
    }

    public function is_user_authorized() {
        // First check URL token
        
        if (isset($_GET['feedback_token'])) {
            $token = sanitize_text_field($_GET['feedback_token']);
            
            global $wpdb;
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE token = %s 
                AND status = 'active'
                AND (expires_at IS NULL OR expires_at > NOW())",
                $token
            ));
            
            if ($user) {
                // Set cookie for future requests
                $this->set_user_cookie($token);
                return true;
            }
        }

        // Then check cookie
        if (isset($_COOKIE[$this->cookie_name])) {

           return $this->validate_user_cookie();
           // return true;
        }

        return false;
    }

    public function get_current_user() {
        if (isset($_COOKIE[$this->cookie_name])) {
            $token = $_COOKIE[$this->cookie_name];
            global $wpdb;
            
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE token = %s 
                AND status = 'active'
                AND (expires_at IS NULL OR expires_at > NOW())",
                $token
            ));
        }
        return null;
    }

    public function get_authorized_user() {
        // First check URL token
        if (isset($_GET['feedback_token'])) {
            $token = sanitize_text_field($_GET['feedback_token']);
            $this->logger->info('Getting authorized user from URL token');
            
            global $wpdb;
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                WHERE token = %s 
                AND status = 'active'
                AND (expires_at IS NULL OR expires_at > NOW())",
                $token
            ));
            
            if ($user) {
                $this->set_user_cookie($token);
                return $user;
            }
        }
        
        // Then check cookie
        $token = isset($_COOKIE[$this->cookie_name]) ? $_COOKIE[$this->cookie_name] : null;
        
        if (!$token) {
            $this->logger->info('No token found for authorization');
            return null;
        }

        $this->logger->info('Getting authorized user from cookie token');
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE token = %s 
            AND status = 'active'
            AND (expires_at IS NULL OR expires_at > NOW())",
            $token
        ));

        if (!$user) {
            $this->logger->warning('Invalid cookie token, clearing cookie');
            $this->clear_user_cookie();
        }

        return $user;
    }
} 