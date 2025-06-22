<?php
namespace WCCREPORTS\Core;

class Logger {
    private $logDir;
    private $logFile;

    public function __construct() {
        $this->logDir = WP_CONTENT_DIR . '/sepid-logs';
        $this->logFile = $this->logDir . '/report.log';

        if (!file_exists($this->logDir)) {
            mkdir($this->logDir);
        }
    }

    public function logEvent($message) {
        $current_time = date('Y-m-d H:i:s');
        $log_message = "[$current_time] $message" . PHP_EOL;
        file_put_contents($this->logFile, $log_message, FILE_APPEND);
    }

    public static function log($message) {
        $logger = new self();
        $logger->logEvent($message);
    }

}

?>