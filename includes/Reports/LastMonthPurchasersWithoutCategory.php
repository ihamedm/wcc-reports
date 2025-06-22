<?php
/**
 * Last Month Purchasers Without Specific Category Report
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

use WCCREPORTS\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LastMonthPurchasersWithoutCategory extends BaseReport {

    /**
     * Get the unique identifier for this report
     *
     * @return string
     */
    public function get_id(): string {
        return 'last_month_purchasers_without_category';
    }

    /**
     * Get the display title for this report
     *
     * @return string
     */
    public function get_title(): string {
        return 'خریدارانی که از دسته ایی خاص خرید نکرده اند';
    }

    /**
     * Get the description for this report
     *
     * @return string
     */
    public function get_description(): string {
        return 'کاربرانی که در {days} روز گذشته خرید داشته‌اند اما از دسته‌بندی محصولات {category_id} خرید نکرده‌اند';
    }

    /**
     * Get the SQL query to fetch user IDs for this report
     *
     * @return string
     */
    protected function get_sql_query(): string {
        return "";
    }

    /**
     * Get the parameters for the SQL query
     *
     * @param string $user_input
     * @return array
     */
    protected function get_parameters(string $user_input = ''): array {
       return [];
    }


    public function get_parameter_placeholder(): string {
        return 'days=30, category_id=1696';
    }

    public function get_parameter_label(): string {
        return 'پارامترها (روزها و دسته‌بندی)';
    }

    /**
     * Check if this report should use WordPress query instead of SQL
     *
     * @return bool
     */
    protected function use_wordpress_query(): bool {
        return true;
    }

    /**
     * Get user IDs using WordPress query instead of raw SQL
     * This approach is more reliable for complex category-based queries
     *
     * @param string $user_input
     * @return array
     */
    protected function get_wp_query(string $user_input = ''): array {
        global $wpdb;
        
        // Parse user parameters or use defaults
        $user_params = $this->parse_user_parameters($user_input);
        $days = isset($user_params['days']) ? intval($user_params['days']) : 10;
        $category_id = isset($user_params['category_id']) ? intval($user_params['category_id']) : 1696;

        // Calculate date range based on user input
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($end_date)));
        
        // Get all users who have made orders in the specified period
        $order_table_name = $wpdb->prefix . 'wc_orders';
        $users_with_orders = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT customer_id 
            FROM {$order_table_name} 
            WHERE customer_id IS NOT NULL
            AND date_created_gmt >= %s
            AND date_created_gmt <= %s
        ", $start_date, $end_date));
        
        if (empty($users_with_orders)) {
            return [];
        }
        
        // Get users who bought from the specific category in the specified period
        $users_with_category_orders = [];
        
        foreach ($users_with_orders as $user_id) {
            // Get user's orders from specified period
            $user_orders = wc_get_orders([
                'customer_id' => $user_id,
                'date_created' => $start_date . '...' . $end_date,
                'limit' => -1,
                'return' => 'ids'
            ]);
            
            if (empty($user_orders)) {
                continue;
            }
            
            // Check if any order contains products from the specific category
            foreach ($user_orders as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) {
                    continue;
                }
                
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    if ($product_id) {
                        // Check if product belongs to the excluded category
                        if (has_term($category_id, 'product_cat', $product_id)) {
                            $users_with_category_orders[] = $user_id;
                            break 2; // Break out of both loops for this user
                        }
                    }
                }
            }
        }
        
        // Return users who have orders in specified period but didn't buy from the specific category
        return array_diff($users_with_orders, $users_with_category_orders);
    }
} 