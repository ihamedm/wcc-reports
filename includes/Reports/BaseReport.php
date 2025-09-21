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
     * Get global order status filter
     *
     * @return array
     */
    public static function get_global_order_statuses(): array {
        $default_statuses = ['wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pws-ready-to-ship'];
        $saved_statuses = get_option('wccreports_global_order_statuses', $default_statuses);
        
        // Ensure we always have an array
        if (!is_array($saved_statuses)) {
            return $default_statuses;
        }
        
        return $saved_statuses;
    }

    /**
     * Get all available WooCommerce order statuses
     *
     * @return array
     */
    public static function get_available_order_statuses(): array {
        if (!function_exists('wc_get_order_statuses')) {
            return [];
        }
        
        $statuses = wc_get_order_statuses();
        $formatted_statuses = [];
        
        foreach ($statuses as $key => $label) {
            $formatted_statuses[$key] = $label;
        }
        
        return $formatted_statuses;
    }

    /**
     * Get global order status filter SQL condition
     *
     * @return string
     */
    public static function get_global_status_sql_condition(): string {
        $statuses = self::get_global_order_statuses();
        
        if (empty($statuses)) {
            return "1=1"; // No filter if no statuses
        }
        
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
        return "o.status IN ({$placeholders})";
    }

    /**
     * Get global order status filter parameters
     *
     * @return array
     */
    public static function get_global_status_parameters(): array {
        return self::get_global_order_statuses();
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
     * Get the user IDs for this report
     *
     * @param bool $refresh_cache Whether to refresh the cache
     * @param string $user_input User input parameters
     * @return array
     */
    public function get_user_ids($refresh_cache = false, string $user_input = ''): array {
        // Create cache key that includes user parameters
        $cache_key = $this->get_cache_key() . '_' . md5($user_input);
        
        Logger::log("BaseReport - Cache key: " . $cache_key);
        Logger::log("BaseReport - User input: " . $user_input);
        
        if ($refresh_cache) {
            delete_transient($cache_key);
            Logger::log("BaseReport - Cache cleared for key: " . $cache_key);
        }

        $cached_result = get_transient($cache_key);

        if ($cached_result !== false) {
            Logger::log("BaseReport - Using cached result for key: " . $cache_key . " (count: " . count($cached_result) . ")");
            return $cached_result;
        }

        Logger::log("BaseReport - No cache found, executing query for key: " . $cache_key);
        $result = $this->execute_query($user_input);
        set_transient($cache_key, $result, $this->get_cache_duration());
        
        // Also cache the user input for this report
        $params_cache_key = $this->get_cache_key() . '_params';
        set_transient($params_cache_key, $user_input, $this->get_cache_duration());
        
        Logger::log("BaseReport - Cached result for key: " . $cache_key . " (count: " . count($result) . ")");
        Logger::log("BaseReport - Cached params for key: " . $params_cache_key . " (params: " . $user_input . ")");

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
     * Get cached user parameters for this report
     *
     * @return string
     */
    public function get_cached_user_params(): string {
        $params_cache_key = $this->get_cache_key() . '_params';
        $cached_params = get_transient($params_cache_key);
        Logger::log("BaseReport - Getting cached params for key: " . $params_cache_key . " (params: " . $cached_params . ")");
        return $cached_params ?: '';
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
        
        // Add global status filter parameters
        $global_status_params = self::get_global_status_parameters();
        $all_parameters = array_merge($parameters, $global_status_params);
        
        Logger::log("BaseReport - Raw SQL: " . $sql);
        Logger::log("BaseReport - Parameters: " . print_r($parameters, true));
        Logger::log("BaseReport - Global status parameters: " . print_r($global_status_params, true));
        Logger::log("BaseReport - All parameters: " . print_r($all_parameters, true));
        
        if (!empty($all_parameters)) {
            $sql = $wpdb->prepare($sql, ...$all_parameters);
        }
        
        Logger::log("BaseReport - Prepared SQL: " . $sql);
        
        $results = $wpdb->get_col($sql);
        Logger::log("BaseReport - Query results count: " . count($results));
        Logger::log("BaseReport - Query results: " . print_r($results, true));

        return $results;
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
    public function get_users_details($sort_by = 'display_name', $sort_order = 'ASC', string $user_input = ''): array {
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
        // If no user_input provided, try to get from cache
        if (empty($user_input)) {
            $user_input = $this->get_cached_user_params();
        }
        Logger::log("BaseReport - get_users_details using user_input: " . $user_input);
        $user_ids = $this->get_user_ids(false, $user_input);
        Logger::log("BaseReport - get_users_details found user_ids: " . count($user_ids));
        
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
    public function export_users(string $user_input = ''): string|false {
        global $wpdb;

        // Get user IDs for this report using the specific report logic
        // If no user_input provided, try to get from cache
        if (empty($user_input)) {
            $user_input = $this->get_cached_user_params();
        }
        Logger::log("BaseReport - export_users using user_input: " . $user_input);
        $user_ids = $this->get_user_ids(false, $user_input);
        Logger::log("BaseReport - export_users found user_ids: " . count($user_ids));
        
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
        return $this->generate_xls_file($users, $user_input);
    }

    /**
     * Generate XLS file from user data
     *
     * @param array $users
     * @param string $user_input
     * @return string|false File URL on success, false on failure
     */
    protected function generate_xls_file($users, string $user_input = ''): string|false {
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

        // Generate filename with parameters and unique identifier
        $timestamp = current_time('Y-m-d_H-i-s');
        $unique_id = wp_generate_password(8, false);
        
        // Create parameter string for filename
        $param_string = '';
        if (!empty($user_input)) {
            // Parse parameters and create a clean string
            $params = $this->parse_user_parameters($user_input);
            $param_parts = [];
            foreach ($params as $key => $value) {
                $param_parts[] = $key . '=' . $value;
            }
            $param_string = '_' . sanitize_file_name(implode('_', $param_parts));
        }
        
        $filename = sanitize_file_name($this->get_id() . $param_string . '_' . $timestamp . '_' . $unique_id . '.xls');
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