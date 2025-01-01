<?php
class Prooflinq_Settings {
    private $options;

    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_styles'));
    }

    public function enqueue_settings_styles() {
        if (isset($_GET['page']) && $_GET['page'] === 'prooflinq-settings') {
            wp_enqueue_style('prooflinq-admin', plugins_url('../css/prooflinq-admin.css', __FILE__));
        }
    }

    public function add_settings_page() {
        add_submenu_page(
            'prooflinq',
            'Prooflinq Settings',
            'Settings',
            'manage_options',
            'prooflinq-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap prooflinq-settings-page">
            <h1>Prooflinq Settings</h1>
            
            <!-- General Settings Section -->
            <form method="post" action="options.php">
                <?php
                settings_fields('prooflinq_options');
                do_settings_sections('prooflinq_settings');
                submit_button();
                ?>
            </form>

            <p>Have questions or just want to say hi?<br>
            Send us an email to <a href="mailto:support@prooflinq.com">support@prooflinq.com</a></p>
        </div>
        <?php
    }

    public function init_settings() {
        register_setting('prooflinq_options', 'prooflinq_notification_emails');

        add_settings_section(
            'prooflinq_notifications',
            'Email Notifications',
            array($this, 'render_notifications_section'),
            'prooflinq_settings'
        );

        add_settings_field(
            'notification_emails',
            'Notification Recipients',
            array($this, 'render_notification_emails_field'),
            'prooflinq_settings',
            'prooflinq_notifications'
        );
    }

    public function render_notifications_section() {
        echo '<p>Configure email addresses that should receive notifications when new feedback is submitted.</p>';
    }

    public function render_notification_emails_field() {
        $emails = get_option('prooflinq_notification_emails', get_option('admin_email'));
        ?>
        <textarea name="prooflinq_notification_emails" rows="3" class="prooflinq-notification-emails" placeholder="Enter email addresses, one per line"><?php echo esc_textarea($emails); ?></textarea>
        <div class="prooflinq-notification-emails description">Enter email addresses that should receive notifications, <br />one per line. Default: site admin email.</div>
        <?php
    }
} 