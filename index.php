<?php
/**
 * Plugin Name: Prooflinq Basic
 * Plugin URI: https://prooflinq.com
 * Description: WordPress design and creative feedback tool for freelancers and agencies.
 * Version: 1.0.0
 * Author: Prooflinq
 * Author URI: https://prooflinq.com
 * Text Domain: prooflinq
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add version constant
define('PROOFLINQ_VERSION', 'basic');

// Add plugin conflict check
function prooflinq_check_premium_version() {
    if (class_exists('Prooflinq_Premium')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Please deactivate the basic version of Prooflinq before activating the premium version.', 'Plugin Conflict', array(
            'back_link' => true
        ));
    }
}
register_activation_hook(__FILE__, 'prooflinq_check_premium_version');

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-prooflinq-activator.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prooflinq-feedback.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prooflinq-admin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prooflinq-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prooflinq-auth.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prooflinq-logger.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-prooflinq-upgrade.php';

// Activation hook
register_activation_hook(__FILE__, array('Prooflinq_Activator', 'activate'));

// Schedule log cleanup
register_activation_hook(__FILE__, 'prooflinq_schedule_log_cleanup');
function prooflinq_schedule_log_cleanup() {
    if (!wp_next_scheduled('prooflinq_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'prooflinq_cleanup_logs');
    }
}

// Cleanup logs handler
add_action('prooflinq_cleanup_logs', 'prooflinq_do_log_cleanup');
function prooflinq_do_log_cleanup() {
    $logger = Prooflinq_Logger::get_instance();
    $logger->clear_old_logs(30); // Keep logs for 30 days
}

// Cleanup on deactivation
register_deactivation_hook(__FILE__, 'prooflinq_cleanup_on_deactivation');
function prooflinq_cleanup_on_deactivation() {
    wp_clear_scheduled_hook('prooflinq_cleanup_logs');
}

class Prooflinq {
    private $admin;
    private $feedback;
    private $settings;
    private $auth;
    private $logger;

    public function __construct() {
        // Initialize logger
        $this->logger = Prooflinq_Logger::get_instance();
        
        // Initialize auth system
        $this->auth = new Prooflinq_Auth();
        
        // Add hooks
        add_action('init', array($this->auth, 'init'), 5);
        add_action('template_redirect', array($this, 'handle_registration_page'), 5);
        add_action('admin_notices', array($this, 'add_premium_banner'));
        
        // Regular hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_form_template'));
        add_action('wp_footer', array($this, 'add_feedback_button'));
        
        // Initialize admin in admin area
        if (is_admin()) {
            $this->admin = new Prooflinq_Admin();
            $this->settings = new Prooflinq_Settings();
            new Prooflinq_Upgrade();
        }
        
        // Initialize feedback
        $this->feedback = new Prooflinq_Feedback();
    }

    public function enqueue_scripts() {
        if ($this->auth->is_user_authorized()) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style('prooflinq', plugins_url('css/prooflinq.css', __FILE__));
            wp_enqueue_script('html2canvas', 'https://html2canvas.hertzen.com/dist/html2canvas.min.js', array(), '1.4.1', true);
            wp_enqueue_script('prooflinq', plugins_url('js/prooflinq.js', __FILE__), array('jquery', 'html2canvas'), rand(1, 1000), true);
            
            wp_localize_script('prooflinq', 'prooflinqData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'restUrl' => esc_url_raw(rest_url()),
                'nonce' => wp_create_nonce('wp_rest')
            ));
        }
    }

    public function add_form_template() {
        if ($this->auth->is_user_authorized()) {
            include plugin_dir_path(__FILE__) . 'templates/feedback-form.php';
        }
    }

    public function handle_registration_page() {
        if (isset($_GET['prooflinq_register']) && isset($_GET['token'])) {
            include plugin_dir_path(__FILE__) . 'templates/registration-form.php';
            exit;
        }
    }

    public function add_feedback_button() {
        if ($this->auth->is_user_authorized()) {
            ?>
            <div class="prooflinq-feedback-button">
                <button type="button">
                    <span class="dashicons dashicons-feedback"></span>
                    Provide Feedback
                </button>
            </div>
            <?php
        }
    }

    public function add_premium_banner() {
        // Only show on our plugin pages
        $screen = get_current_screen();
        if (strpos($screen->id, 'prooflinq') !== false && $screen->id !== 'prooflinq_page_prooflinq-upgrade') {
            ?>
            <div class="prooflinq-promo-banner">
                <span>🚀 Unlock the full potential of Prooflinq! Visit <a href="https://prooflinq.com/" target="_blank">prooflinq.com</a> to learn more about our premium features.</span>
            </div>
            <style>
                .prooflinq-promo-banner {
                    background: #f0f6fc;
                    border-left: 4px solid #2271b1;
                    padding: 12px 16px;
                    margin: 20px 0;
                    display: flex;
                    align-items: center;
                    font-size: 14px;
                }
                
                .prooflinq-promo-banner a {
                    color: #2271b1;
                    text-decoration: none;
                    font-weight: 500;
                }
                
                .prooflinq-promo-banner a:hover {
                    color: #135e96;
                    text-decoration: underline;
                }
            </style>
            <?php
        }
    }
}

// Add premium link to plugin actions
function prooflinq_add_plugin_links($links) {
    $premium_link = '<a href="https://prooflinq.com" target="_blank" style="color: #2271b1; font-weight: bold;">Get Premium</a>';
    array_unshift($links, $premium_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'prooflinq_add_plugin_links');

// Initialize the plugin
function prooflinq_init() {
    global $prooflinq;
    $prooflinq = new Prooflinq();
}
add_action('init', 'prooflinq_init', 10);