<?php
class Prooflinq_Activator {
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'prooflinq_feedback';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            coordinates varchar(100) NOT NULL,
            page_url varchar(255) NOT NULL,
            screenshot_url varchar(255) DEFAULT '',
            attachment_url varchar(255) DEFAULT '',
            status varchar(20) DEFAULT 'open',
            category varchar(50) DEFAULT 'general',
            submitted_by varchar(255) DEFAULT 'Anonymous',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Create comments table
        $comments_table = $wpdb->prefix . 'prooflinq_comments';
        $sql_comments = "CREATE TABLE IF NOT EXISTS $comments_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feedback_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            comment text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY feedback_id (feedback_id)
        ) $charset_collate;";

        dbDelta($sql_comments);

        // Create authorized users table
        $auth_table = $wpdb->prefix . 'prooflinq_authorized_users';
        $sql_auth = "CREATE TABLE IF NOT EXISTS $auth_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            token varchar(64) NOT NULL,
            created_by bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            last_access datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            PRIMARY KEY  (id),
            UNIQUE KEY token (token)
        ) $charset_collate;";

        dbDelta($sql_auth);
    }
}