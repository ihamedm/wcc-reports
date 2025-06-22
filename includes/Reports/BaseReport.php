<?php
/**
 * Base Report Class
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

use WCCREPORTS\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

abstract class BaseReport {

    /**
     * Get the unique identifier for this report
     *
     * @return string
     */
    abstract public function get_id(): string;

    /**
     * Get the display title for this report
     *
     * @return string
     */
    abstract public function get_title(): string;

    /**
     * Get the description for this report
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Get the SQL query to fetch user IDs for this report
     *
     * @return string
     */
    abstract protected function get_sql_query(): string;

    /**
     * Get the parameters for the SQL query
     *
     * @param string $user_input
     * @return array
     */
    abstract protected function get_parameters(string $user_input = ''): array;

    /**
     * Get the cache key for this report
     *
     * @return string
     */
    public function get_cache_key(): string {
        return 'wccreports_' . $this->get_id();
    }

    /**
     * Get the cache duration in seconds
     *
     * @return int
     */
    public function get_cache_duration(): int {
        return 1800; // 30 minutes default
    }

    /**
     * Check if this report should use WordPress query instead of SQL
     * Override this method in child classes to specify the query approach
     *
     * @return bool
     */
    protected function use_wordpress_query(): bool {
        return false; // Default to SQL approach
    }

    /**
     * Get the parameter placeholder text for the input field
     * Override this method in child classes to provide custom placeholder
     *
     * @return string
     */
    public function get_parameter_placeholder(): string {
        return '';
    }

    /**
     * Get the parameter input field label
     * Override this method in child classes to provide custom label
     *
     * @return string
     */
    public function get_parameter_label(): string {
        return 'پارامترها (اختیاری)';
    }

    /**
     * Parse user input parameters
     * Override this method in child classes to handle custom parameter parsing
     *
     * @param string $user_input
     * @return array
     */
    public function parse_user_parameters(string $user_input): array {
        if (empty(trim($user_input))) {
            return [];
        }

        $params = [];
        $pairs = explode(',', $user_input);
        
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (strpos($pair, '=') !== false) {
                list($key, $value) = explode('=', $pair, 2);
                $params[trim($key)] = trim($value);
            }
        }

        Logger::log(print_r($params, true));
        
        return $params;
    }


    /**
     * @param $refresh_cache
     * @param string $user_input
     * @return array
     */
    public function get_user_ids($refresh_cache = false, string $user_input = ''): array {
        if ($refresh_cache) {
            delete_transient($this->get_cache_key());
        }

        // Create cache key that includes user parameters
        $cache_key = $this->get_cache_key();

        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            return $cached_result;
        }

        $result = $this->execute_query($user_input);
        set_transient($cache_key, $result, $this->get_cache_duration());

        return $result;
    }

    /**
     * Get the count of users for this report
     *
     * @param bool $refresh_cache Whether to refresh the cache
     * @param string $user_input User input parameters
     * @return int
     */
    public function get_count($refresh_cache = false, string $user_input = ''): int {
        $results = $this->get_user_ids($refresh_cache, $user_input);
        return $results ? count($results) : 0;
    }

    /**
     * Execute the count query
     *
     * @param string $user_input
     * @return array
     */
    protected function execute_query(string $user_input = ''): array {
        // Debug logging
        Logger::log("execute_query called with user_input: " . $user_input);
        
        // Check if this report should use WordPress query instead of SQL
        if ($this->use_wordpress_query() && method_exists($this, 'get_wp_query')) {
            Logger::log("Calling get_wp_query method");
            return $this->get_wp_query($user_input);
        }

        // Default SQL execution
        Logger::log("Using SQL execution");
        global $wpdb;

        $sql = $this->get_sql_query();
        $parameters = $this->get_parameters($user_input);
        
        if (!empty($parameters)) {
            $sql = $wpdb->prepare($sql, ...$parameters);
        }

        return $wpdb->get_col($sql);
    }

    /**
     * Get user IDs using WordPress query (override this method for complex reports)
     * This method can be overridden by child classes to use WordPress functions instead of raw SQL
     *
     * @return array
     */
    protected function get_wp_query(): array {
        // Default implementation - can be overridden by child classes
        return [];
    }

    /**
     * Get detailed user information for this report
     *
     * @param string $sort_by
     * @param string $sort_order
     * @return array
     */
    public function get_users_details($sort_by = 'display_name', $sort_order = 'ASC'): array {
        global $wpdb;

        $order_table_name = $wpdb->prefix . 'wc_orders';

        // Validate sort parameters
        $allowed_sort_fields = array('display_name', 'order_count', 'user_registered', 'last_order_date');
        if (!in_array($sort_by, $allowed_sort_fields)) {
            $sort_by = 'display_name';
        }
        
        if (!in_array(strtoupper($sort_order), array('ASC', 'DESC'))) {
            $sort_order = 'ASC';
        }

        // Get user IDs for this report using the specific report logic
        $user_ids = $this->get_user_ids();
        
        if (empty($user_ids)) {
            return array();
        }

        // Get detailed user information with order counts and phone numbers
        $user_ids_placeholder = implode(',', array_fill(0, count($user_ids), '%d'));
        
        // Build ORDER BY clause based on sort parameters
        $order_by_clause = '';
        switch ($sort_by) {
            case 'order_count':
                $order_by_clause = "ORDER BY order_count {$sort_order}, u.display_name ASC";
                break;
            case 'user_registered':
                $order_by_clause = "ORDER BY u.user_registered {$sort_order}, u.display_name ASC";
                break;
            case 'last_order_date':
                $order_by_clause = "ORDER BY last_order_date {$sort_order}, u.display_name ASC";
                break;
            case 'display_name':
            default:
                $order_by_clause = "ORDER BY u.display_name {$sort_order}";
                break;
        }
        
        $sql = $wpdb->prepare(
            "SELECT u.ID, u.user_login, u.user_email, u.display_name, u.user_registered,
                    um.meta_value as phone,
                    COUNT(o.id) as order_count,
                    MAX(o.date_created_gmt) as last_order_date
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'phone'
             LEFT JOIN {$order_table_name} o ON u.ID = o.customer_id
             WHERE u.ID IN ({$user_ids_placeholder})
             GROUP BY u.ID
             {$order_by_clause}",
            ...$user_ids
        );

        $users = $wpdb->get_results($sql);

        return $users ?: array();
    }


    /**
     * Export users to XLS file
     *
     * @return string|false File URL on success, false on failure
     */
    public function export_users(): string|false {
        global $wpdb;

        // Get user IDs for this report using the specific report logic
        $user_ids = $this->get_user_ids();
        
        if (empty($user_ids)) {
            return false;
        }

        // Get user details with phone numbers
        $user_ids_placeholder = implode(',', array_fill(0, count($user_ids), '%d'));
        $sql = $wpdb->prepare(
            "SELECT u.ID, u.display_name, um.meta_value as phone
             FROM {$wpdb->users} u
             LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'phone'
             WHERE u.ID IN ({$user_ids_placeholder})
             ORDER BY u.display_name",
            ...$user_ids
        );

        $users = $wpdb->get_results($sql);

        if (empty($users)) {
            return false;
        }

        // Generate XLS file
        return $this->generate_xls_file($users);
    }

    /**
     * Generate XLS file from user data
     *
     * @param array $users
     * @return string|false File URL on success, false on failure
     */
    protected function generate_xls_file($users): string|false {
        // Create uploads directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/wccreports-exports/';
        
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        // Create index.php to prevent directory listing
        $index_file = $export_dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }

        // Generate filename with unique identifier
        $timestamp = current_time('Y-m-d_H-i-s');
        $unique_id = wp_generate_password(8, false);
        $filename = sanitize_file_name($this->get_id() . '_' . $timestamp . '_' . $unique_id . '.xls');
        $filepath = $export_dir . $filename;

        // Create XLS content
        $xls_content = $this->create_xls_content($users);

        // Write file
        if (file_put_contents($filepath, $xls_content) === false) {
            return false;
        }

        // Return file URL
        return $upload_dir['baseurl'] . '/wccreports-exports/' . $filename;
    }

    /**
     * Create XLS content from user data
     *
     * @param array $users
     * @return string
     */
    protected function create_xls_content($users): string {
        // XLS header
        $content = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
        
        // Report title
        $content .= $this->get_title() . "\n";
        $content .= "Generated: " . current_time('Y-m-d H:i:s') . "\n\n";
        
        // Headers
        $content .= "User ID\tName\tPhone Number\n";
        
        // Data rows
        foreach ($users as $user) {
            $user_id = $user->ID;
            $name = $user->display_name ?: 'N/A';
            $phone = $user->phone ?: 'N/A';
            
            $content .= "{$user_id}\t{$name}\t{$phone}\n";
        }
        
        return $content;
    }

    /**
     * Get the AJAX action name for this report
     *
     * @return string
     */
    public function get_ajax_action(): string {
        return 'wccreports_get_' . $this->get_id();
    }

    /**
     * Get the export AJAX action name for this report
     *
     * @return string
     */
    public function get_export_ajax_action(): string {
        return 'wccreports_export_' . $this->get_id();
    }


    /**
     * Get the details AJAX action name for this report
     *
     * @return string
     */
    public function get_details_ajax_action(): string {
        return 'wccreports_get_' . $this->get_id() . '_details';
    }
} 