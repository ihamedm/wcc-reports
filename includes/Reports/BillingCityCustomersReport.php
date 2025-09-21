<?php
/**
 * Billing City Customers Report
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

use WCCREPORTS\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class BillingCityCustomersReport extends BaseReport {

    /**
     * Get the unique identifier for this report
     *
     * @return string
     */
    public function get_id(): string {
        return 'billing_city_customers';
    }

    /**
     * Get the display title for this report
     *
     * @return string
     */
    public function get_title(): string {
        return 'مشتریان شهر {billing_city}';
    }

    /**
     * Get the description for this report
     *
     * @return string
     */
    public function get_description(): string {
        return 'مشتریانی که شهر صورتحساب آن‌ها {billing_city} است';
    }

    /**
     * Get the SQL query to fetch user IDs for this report
     *
     * @return string
     */
    protected function get_sql_query(): string {
        global $wpdb;

        $order_table_name = $wpdb->prefix . 'wc_orders';
        $address_table = $wpdb->prefix . 'wc_order_addresses';
        
        Logger::log("BillingCityCustomersReport - Using wp_wc_order_addresses table with LIKE search");
        
        return "SELECT DISTINCT o.customer_id
                FROM {$order_table_name} o
                INNER JOIN {$address_table} a ON o.id = a.order_id
                WHERE a.address_type = 'billing'
                AND a.city LIKE %s
                AND o.date_created_gmt >= %s
                AND o.date_created_gmt <= %s
                AND " . self::get_global_status_sql_condition();
    }

    /**
     * Get the parameters for the SQL query
     *
     * @param string $user_input
     * @return array
     */
    protected function get_parameters(string $user_input = ''): array {
        Logger::log("BillingCityCustomersReport - get_parameters called with user_input: " . $user_input);
        
        $user_params = $this->parse_user_parameters($user_input);
        Logger::log("BillingCityCustomersReport - parsed user_params: " . print_r($user_params, true));
        
        // Get billing city from user input
        $billing_city = isset($user_params['billing_city']) ? sanitize_text_field($user_params['billing_city']) : '';
        Logger::log("BillingCityCustomersReport - extracted billing_city: " . $billing_city);

        // Validate billing city
        if (empty($billing_city)) {
            Logger::log("BillingCityCustomersReport - invalid billing_city, returning ['']");
            return ['']; // Return empty result for invalid billing city
        }

        // Get days from user input or use default
        $days = isset($user_params['days']) ? intval($user_params['days']) : 90;
        Logger::log("BillingCityCustomersReport - extracted days: " . $days);

        // Calculate date range
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($end_date)));
        Logger::log("BillingCityCustomersReport - date range: {$start_date} to {$end_date}");

        // Use LIKE search for city name (handles spelling variations)
        $city_to_search = '%' . $billing_city . '%';
        Logger::log("BillingCityCustomersReport - City to search with LIKE: '{$city_to_search}' (original: '{$billing_city}')");

        // Debug: Check what cities exist in the database
        $this->debug_cities_in_database($billing_city);

        Logger::log("BillingCityCustomersReport - returning parameters: [" . $city_to_search . ", " . $start_date . ", " . $end_date . "]");
        return [$city_to_search, $start_date, $end_date];
    }

    /**
     * Debug method to check what cities exist in the database
     *
     * @param string $search_city
     */
    private function debug_cities_in_database($search_city): void {
        global $wpdb;
        
        // Check wp_wc_order_addresses table
        $address_table = $wpdb->prefix . 'wc_order_addresses';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$address_table}'");
        Logger::log("BillingCityCustomersReport - wp_wc_order_addresses table exists: " . ($table_exists ? 'YES' : 'NO'));
        
        if ($table_exists) {
            $total_addresses = $wpdb->get_var("SELECT COUNT(*) FROM {$address_table}");
            Logger::log("BillingCityCustomersReport - Total addresses in wp_wc_order_addresses: {$total_addresses}");
            
            // Check billing addresses specifically
            $billing_addresses = $wpdb->get_var("SELECT COUNT(*) FROM {$address_table} WHERE address_type = 'billing'");
            Logger::log("BillingCityCustomersReport - Billing addresses: {$billing_addresses}");
            
            // Get sample cities from billing addresses
            $cities_sql = "SELECT DISTINCT city, COUNT(*) as count 
                          FROM {$address_table} 
                          WHERE address_type = 'billing'
                          AND city IS NOT NULL AND city != ''
                          GROUP BY city 
                          ORDER BY count DESC 
                          LIMIT 20";
            $cities = $wpdb->get_results($cities_sql);
            Logger::log("BillingCityCustomersReport - Sample cities in wp_wc_order_addresses: " . print_r($cities, true));
            
            // Check for exact match
            $exact_match_sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$address_table} WHERE address_type = 'billing' AND city = %s",
                $search_city
            );
            $exact_count = $wpdb->get_var($exact_match_sql);
            Logger::log("BillingCityCustomersReport - Exact match for '{$search_city}': {$exact_count} billing addresses");
            
            // Check for LIKE match
            $like_match_sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$address_table} WHERE address_type = 'billing' AND city LIKE %s",
                '%' . $wpdb->esc_like($search_city) . '%'
            );
            $like_count = $wpdb->get_var($like_match_sql);
            Logger::log("BillingCityCustomersReport - LIKE match for '{$search_city}': {$like_count} billing addresses");
            
            // Get partial matches for debugging
            $partial_matches_sql = $wpdb->prepare(
                "SELECT DISTINCT city, COUNT(*) as count 
                 FROM {$address_table} 
                 WHERE address_type = 'billing'
                 AND city LIKE %s 
                 GROUP BY city 
                 ORDER BY count DESC 
                 LIMIT 10",
                '%' . $wpdb->esc_like($search_city) . '%'
            );
            $partial_matches = $wpdb->get_results($partial_matches_sql);
            Logger::log("BillingCityCustomersReport - Partial matches for '{$search_city}': " . print_r($partial_matches, true));
        }
    }


    /**
     * Get the parameter placeholder text for the input field
     *
     * @return string
     */
    public function get_parameter_placeholder(): string {
        return 'days=90,billing_city=اهواز';
    }

    /**
     * Get the parameter input field label
     *
     * @return string
     */
    public function get_parameter_label(): string {
        return 'پارامترها (تعداد روزها، نام شهر)';
    }
}
