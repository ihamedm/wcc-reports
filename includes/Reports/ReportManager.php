<?php
/**
 * Report Manager Class
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ReportManager {

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_ajax_actions();
        $this->register_cleanup_hook();
    }

    /**
     * Register all AJAX actions
     */
    private function register_ajax_actions(): void {
        // Register AJAX actions for each report
        foreach (ReportRegistry::get_all() as $report) {
            add_action('wp_ajax_' . $report->get_ajax_action(), array($this, 'handle_get_report_count'));
            add_action('wp_ajax_' . $report->get_export_ajax_action(), array($this, 'handle_export_report'));
            add_action('wp_ajax_' . $report->get_details_ajax_action(), array($this, 'handle_get_report_details'));
        }

    }

    /**
     * Register cleanup hook
     */
    private function register_cleanup_hook(): void {
        // Clean up old export files daily
        if (!wp_next_scheduled('wccreports_cleanup_exports')) {
            wp_schedule_event(time(), 'daily', 'wccreports_cleanup_exports');
        }
        add_action('wccreports_cleanup_exports', array($this, 'cleanup_old_exports'));
    }

    /**
     * Handle getting report count
     */
    public function handle_get_report_count(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wccreports_reports_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $report_id = sanitize_text_field($_POST['report_id']);
        $refresh_cache = isset($_POST['refresh_cache']) && $_POST['refresh_cache'] === 'true';
        $user_parameters = sanitize_text_field($_POST['user_parameters'] ?? '');

        $report = ReportRegistry::get($report_id);
        if (!$report) {
            wp_send_json_error(array('message' => __('Report Not found', WCCREPORTS_TEXT_DOMAIN)));
        }

        $count = $report->get_count($refresh_cache, $user_parameters);
        
        wp_send_json_success(array(
            'count' => $count,
            'message' => __('Reports Generated successfully', WCCREPORTS_TEXT_DOMAIN),
        ));
    }

    /**
     * Handle exporting report
     */
    public function handle_export_report(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wccreports_reports_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $report_id = sanitize_text_field($_POST['report_id']);
        
        $report = ReportRegistry::get($report_id);
        if (!$report) {
            wp_send_json_error(array('message' => __('Report Not found', WCCREPORTS_TEXT_DOMAIN)));
        }

        $filename = $report->export_users();
        
        if ($filename) {
            wp_send_json_success(array(
                'file_url' => $filename,
                'message' => __('Export successfully created.', WCCREPORTS_TEXT_DOMAIN)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Data not found', WCCREPORTS_TEXT_DOMAIN)
            ));
        }
    }

    /**
     * Handle getting report details
     */
    public function handle_get_report_details(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wccreports_reports_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $report_id = sanitize_text_field($_POST['report_id']);
        $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'display_name');
        $sort_order = sanitize_text_field($_POST['sort_order'] ?? 'ASC');

        $report = ReportRegistry::get($report_id);
        if (!$report) {
            wp_send_json_error(array('message' =>__('Report Not found', WCCREPORTS_TEXT_DOMAIN)));
        }

        $users = $report->get_users_details($sort_by, $sort_order);
        
        wp_send_json_success(array(
            'users' => $users,
            'count' => count($users),
            'message' => __('Details received successfully', WCCREPORTS_TEXT_DOMAIN)
        ));
    }

    /**
     * Clean up old export files
     */
    public function cleanup_old_exports(): void {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/wccreports-exports/';
        
        if (is_dir($export_dir)) {
            // Remove problematic .htaccess file if it exists
            $htaccess_file = $export_dir . '.htaccess';
            if (file_exists($htaccess_file)) {
                unlink($htaccess_file);
            }
            
            $files = glob($export_dir . '*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $file_time = filectime($file);
                        $current_time = time();
                        $age = $current_time - $file_time;
                        if ($age > 86400) { // 86400 seconds = 24 hours
                            unlink($file);
                        }
                    }
                }
            }
        }
    }



    /**
     * Get all reports for display
     *
     * @return array
     */
    public function get_all_reports(): array {
        return ReportRegistry::get_all();
    }

    /**
     * Get a specific report
     *
     * @param string $id
     * @return BaseReport|null
     */
    public function get_report(string $id): ?BaseReport {
        return ReportRegistry::get($id);
    }
} 