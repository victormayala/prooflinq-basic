<?php
class Prooflinq_Upgrade {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_upgrade_page'));
    }

    public function add_upgrade_page() {
        add_submenu_page(
            'prooflinq',
            'Upgrade Prooflinq',
            '<span style="color: #6bbc5b;">Upgrade</span>',
            'manage_options',
            'prooflinq-upgrade',
            array($this, 'render_upgrade_page')
        );
    }

    public function render_upgrade_page() {
        ?>
        <div class="wrap">
            <h1>Upgrade to Prooflinq Premium</h1>
            
            <div class="prooflinq-upgrade-content">
                <div class="prooflinq-upgrade-header">
                    <h2>Enhance Your Feedback Management with Premium Features</h2>
                    <p>Unlock powerful features to streamline your feedback workflow</p>
                </div>

                <div class="prooflinq-features-grid">
                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <h3>Feedback Analytics & Stats</h3>
                        <p>Get detailed insights into your feedback data</p>
                    </div>
                    
                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-search"></span>
                        <h3>Advanced Search</h3>
                        <p>Quickly find specific feedback items</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-filter"></span>
                        <h3>Bulk Actions & Filters</h3>
                        <p>Efficiently manage multiple feedback items</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-category"></span>
                        <h3>Categorization</h3>
                        <p>Organize feedback by custom categories</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-flag"></span>
                        <h3>Status Management</h3>
                        <p>Track feedback progress with custom statuses</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-admin-comments"></span>
                        <h3>Admin Notes</h3>
                        <p>Add private notes to feedback items</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-paperclip"></span>
                        <h3>File Attachments</h3>
                        <p>Allow file uploads with feedback</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-trash"></span>
                        <h3>Ticket Deletion</h3>
                        <p>Permanently remove unwanted feedback</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-clock"></span>
                        <h3>Link Expiration</h3>
                        <p>Set access link expiration periods</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-dismiss"></span>
                        <h3>Link Revocation</h3>
                        <p>Instantly revoke access when needed</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-download"></span>
                        <h3>Export Data</h3>
                        <p>Export feedback data to CSV format</p>
                    </div>

                    <div class="prooflinq-feature">
                        <span class="dashicons dashicons-email-alt"></span>
                        <h3>Fast Email Support</h3>
                        <p>Get priority email support from our team</p>
                    </div>
                </div>

                <div class="prooflinq-upgrade-cta">
                    <h3>Starting at just $9.99/month</h3>
                    <p>All these features and more available in our premium plans</p>
                    <a href="https://prooflinq.com/pricing.html" class="button button-primary button-hero" target="_blank">
                        View Pricing & Upgrade
                    </a>
                </div>

                <div class="prooflinq-contact-info">
                    <p>Have questions or just want to say hi?<br>
                    Send us an email to <a href="mailto:support@prooflinq.com">support@prooflinq.com</a></p>
                </div>
            </div>
        </div>
        <?php
        $this->add_upgrade_styles();
    }

    private function add_upgrade_styles() {
        ?>
        <style>
            .prooflinq-upgrade-content {
                max-width: 1200px;
                margin: 20px 0;
            }
            
            .prooflinq-upgrade-header {
                text-align: center;
                margin-bottom: 40px;
            }
            
            .prooflinq-features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 25px;
                margin: 30px 0;
            }
            
            .prooflinq-feature {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                text-align: center;
            }
            
            .prooflinq-feature .dashicons {
                font-size: 30px;
                width: 30px;
                height: 30px;
                color: #2271b1;
            }
            
            .prooflinq-feature h3 {
                margin: 15px 0 10px;
                color: #1d2327;
            }
            
            .prooflinq-feature p {
                margin: 0;
                color: #50575e;
            }
            
            .prooflinq-upgrade-cta {
                text-align: center;
                margin-top: 40px;
                padding: 40px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .prooflinq-upgrade-cta h3 {
                color: #1d2327;
                font-size: 24px;
                margin: 0 0 10px;
            }
            
            .prooflinq-upgrade-cta .button-hero {
                margin-top: 20px;
            }
            
            .prooflinq-contact-info {
                text-align: center;
                margin-top: 30px;
                color: #50575e;
            }
            
            .prooflinq-contact-info a {
                color: #2271b1;
                text-decoration: none;
            }
            
            .prooflinq-contact-info a:hover {
                color: #135e96;
                text-decoration: underline;
            }

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