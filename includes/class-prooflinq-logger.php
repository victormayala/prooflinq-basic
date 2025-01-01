<?php
class Prooflinq_Logger {
    private $log_directory;
    private static $instance = null;

    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_directory = $upload_dir['basedir'] . '/prooflinq-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($this->log_directory)) {
            wp_mkdir_p($this->log_directory);
            
            // Secure the directory
            file_put_contents($this->log_directory . '/.htaccess', "Order deny,allow\nDeny from all");
            file_put_contents($this->log_directory . '/index.php', "<?php\n// Silence is golden.");
        }
    }

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($level, $message, $context = array()) {
        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        $timestamp = current_time('mysql');
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'];

        $log_entry = sprintf(
            "[%s] [%s] [User:%d] [IP:%s] %s %s\n",
            $timestamp,
            strtoupper($level),
            $user_id,
            $ip,
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        $filename = $this->log_directory . '/prooflinq-' . date('Y-m-d') . '.log';
        error_log($log_entry, 3, $filename);

        // Also log to WordPress error log for critical errors
        if ($level === 'error' || $level === 'critical') {
            error_log($log_entry);
        }
    }

    public function info($message, $context = array()) {
        $this->log('info', $message, $context);
    }

    public function warning($message, $context = array()) {
        $this->log('warning', $message, $context);
    }

    public function error($message, $context = array()) {
        $this->log('error', $message, $context);
    }

    public function critical($message, $context = array()) {
        $this->log('critical', $message, $context);
    }

    public function get_logs($date = null, $level = null, $limit = 100) {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $filename = $this->log_directory . '/prooflinq-' . $date . '.log';
        if (!file_exists($filename)) {
            return array();
        }

        $logs = array_filter(
            array_map('trim', file($filename)),
            function($line) use ($level) {
                return empty($level) || strpos($line, '[' . strtoupper($level) . ']') !== false;
            }
        );

        return array_slice(array_reverse($logs), 0, $limit);
    }

    public function clear_old_logs($days = 30) {
        $files = glob($this->log_directory . '/prooflinq-*.log');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= $days * 24 * 60 * 60) {
                    unlink($file);
                }
            }
        }
    }
} 