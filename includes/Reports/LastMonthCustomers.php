<?php
/**
 * New Customers Last Month Report
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LastMonthCustomers extends BaseReport {

    /**
     * Get the unique identifier for this report
     *
     * @return string
     */
    public function get_id(): string {
        return 'last_month_customers';
    }

    /**
     * Get the display title for this report
     *
     * @return string
     */
    public function get_title(): string {
        return 'مشتریان {days} روز اخیر';
    }

    /**
     * Get the description for this report
     *
     * @return string
     */
    public function get_description(): string {
        return 'مشتریانی که در {days} روز گذشته سفارش ثبت کرده اند';
    }

    /**
     * Get the SQL query to fetch user IDs for this report
     *
     * @return string
     */
    protected function get_sql_query(): string {
        global $wpdb;

        $order_table_name = $wpdb->prefix . 'wc_orders';
        $user_table_name = $wpdb->users;

        return "SELECT DISTINCT o.customer_id
                FROM {$order_table_name} o
                WHERE o.date_created_gmt >= %s
                AND o.date_created_gmt <= %s";
    }


    /**
     * Get the parameters for the SQL query
     *
     * @param string $user_input
     * @return array
     */
    protected function get_parameters(string $user_input = ''): array {
        // Calculate the date range for the last month

        $user_params = $this->parse_user_parameters($user_input);
        $days = isset($user_params['days']) ? intval($user_params['days']) : 30;

        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($end_date)));

        return [$start_date, $end_date];
    }

    public function get_parameter_placeholder(): string {
        return 'days=30';
    }

    public function get_parameter_label(): string {
        return 'پارامترها (تعداد روزها)';
    }

} 