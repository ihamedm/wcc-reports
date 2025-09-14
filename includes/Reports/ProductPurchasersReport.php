<?php
/**
 * Product Purchasers Report
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ProductPurchasersReport extends BaseReport {

    /**
     * Get the unique identifier for this report
     *
     * @return string
     */
    public function get_id(): string {
        return 'product_purchasers';
    }

    /**
     * Get the display title for this report
     *
     * @return string
     */
    public function get_title(): string {
        return 'مشتریانی که محصول {product_id} را در {days} روز گذشته خرید کرده‌اند';
    }

    /**
     * Get the description for this report
     *
     * @return string
     */
    public function get_description(): string {
        return 'مشتریانی که محصول مشخصی را در X روز گذشته خرید کرده‌اند';
    }

    /**
     * Get the SQL query to fetch user IDs for this report
     *
     * @return string
     */
    protected function get_sql_query(): string {
        global $wpdb;

        $order_table_name = $wpdb->prefix . 'wc_orders';
        $order_items_table = $wpdb->prefix . 'wc_order_product_lookup';

        return "SELECT DISTINCT o.customer_id
                FROM {$order_table_name} o
                INNER JOIN {$order_items_table} oi ON o.id = oi.order_id
                WHERE oi.product_id = %d
                AND o.date_created_gmt >= %s
                AND o.date_created_gmt <= %s
                AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')";
    }

    /**
     * Get the parameters for the SQL query
     *
     * @param string $user_input
     * @return array
     */
    protected function get_parameters(string $user_input = ''): array {
        $user_params = $this->parse_user_parameters($user_input);
        
        // Get product ID and days from user input
        $product_id = isset($user_params['product_id']) ? intval($user_params['product_id']) : 0;
        $days = isset($user_params['days']) ? intval($user_params['days']) : 30;

        // Validate product ID
        if ($product_id <= 0) {
            return [0, '1970-01-01 00:00:00', '1970-01-01 00:00:00']; // Return empty result for invalid product ID
        }

        // Calculate date range
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($end_date)));

        return [$product_id, $start_date, $end_date];
    }

    /**
     * Get the parameter placeholder text for the input field
     *
     * @return string
     */
    public function get_parameter_placeholder(): string {
        return 'product_id=123,days=30';
    }

    /**
     * Get the parameter input field label
     *
     * @return string
     */
    public function get_parameter_label(): string {
        return 'پارامترها (شناسه محصول، روزها)';
    }

}
