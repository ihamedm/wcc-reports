<?php
/**
 * Coupon Users Report
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

use WCCREPORTS\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class CouponUsersReport extends BaseReport {

    /**
     * Get the unique identifier for this report
     *
     * @return string
     */
    public function get_id(): string {
        return 'coupon_users';
    }

    /**
     * Get the display title for this report
     *
     * @return string
     */
    public function get_title(): string {
        return 'مشتریانی که از کد تخفیف {coupon_code} استفاده کرده‌اند';
    }

    /**
     * Get the description for this report
     *
     * @return string
     */
    public function get_description(): string {
        return 'مشتریانی که از کد تخفیف مشخصی استفاده کرده‌اند';
    }

    /**
     * Get the SQL query to fetch user IDs for this report
     *
     * @return string
     */
    protected function get_sql_query(): string {
        global $wpdb;

        $order_table_name = $wpdb->prefix . 'wc_orders';
        $order_items_table = $wpdb->prefix . 'woocommerce_order_items';

        return "SELECT DISTINCT o.customer_id
                FROM {$order_table_name} o
                INNER JOIN {$order_items_table} oi ON o.id = oi.order_id
                WHERE oi.order_item_type = 'coupon'
                AND oi.order_item_name = %s
                AND o.date_created_gmt >= %s
                AND o.date_created_gmt <= %s
                AND " . self::get_global_status_sql_condition();
    }

    /**
     * Get placeholders for status IN clause
     *
     * @return string
     */
    private function get_status_placeholders(): string {
        // This will be replaced with actual placeholders in execute_query
        return '%s';
    }

    /**
     * Get the parameters for the SQL query
     *
     * @param string $user_input
     * @return array
     */
    protected function get_parameters(string $user_input = ''): array {
        Logger::log("CouponUsersReport - get_parameters called with user_input: " . $user_input);
        
        $user_params = $this->parse_user_parameters($user_input);
        Logger::log("CouponUsersReport - parsed user_params: " . print_r($user_params, true));
        
        // Get coupon code from user input
        $coupon_code = isset($user_params['coupon_code']) ? sanitize_text_field($user_params['coupon_code']) : '';
        Logger::log("CouponUsersReport - extracted coupon_code: " . $coupon_code);

        // Validate coupon code
        if (empty($coupon_code)) {
            Logger::log("CouponUsersReport - invalid coupon_code, returning ['']");
            return ['']; // Return empty result for invalid coupon code
        }

        // Get days from user input or use default
        $days = isset($user_params['days']) ? intval($user_params['days']) : 1000;
        Logger::log("CouponUsersReport - extracted days: " . $days);

        // Calculate date range
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($end_date)));
        Logger::log("CouponUsersReport - date range: {$start_date} to {$end_date}");

        Logger::log("CouponUsersReport - returning parameters: [" . $coupon_code . ", " . $start_date . ", " . $end_date . "]");
        return [$coupon_code, $start_date, $end_date];
    }

    /**
     * Get the parameter placeholder text for the input field
     *
     * @return string
     */
    public function get_parameter_placeholder(): string {
        return 'days=1000,coupon_code=tleg';
    }

    /**
     * Get the parameter input field label
     *
     * @return string
     */
    public function get_parameter_label(): string {
        return 'پارامترها (تعداد روزها، کد تخفیف)';
    }

}
