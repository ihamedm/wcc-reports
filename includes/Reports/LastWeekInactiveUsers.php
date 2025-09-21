<?php
/**
 * Last Week Inactive Users Report
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LastWeekInactiveUsers extends BaseReport {

    /**
     * Get the unique identifier for this report
     *
     * @return string
     */
    public function get_id(): string {
        return 'last_week_inactive_users';
    }

    /**
     * Get the display title for this report
     *
     * @return string
     */
    public function get_title(): string {
        return 'کاربران غیرفعال {days} روز گذشته';
    }

    /**
     * Get the description for this report
     *
     * @return string
     */
    public function get_description(): string {
        return 'کاربرانی که در دوره زمانی مشخص شده ثبت نام کرده‌اند اما هنوز خریدی انجام نداده‌اند';
    }

    /**
     * Get the SQL query to fetch user IDs for this report
     *
     * @return string
     */
    protected function get_sql_query(): string {
        global $wpdb;

        $users_table_name = $wpdb->users;
        $order_table_name = $wpdb->prefix . 'wc_orders';

        return "SELECT DISTINCT u.ID
                FROM {$users_table_name} u
                LEFT JOIN {$order_table_name} o ON u.ID = o.customer_id 
                    AND o.date_created_gmt >= %s 
                    AND o.date_created_gmt <= %s
                    AND " . self::get_global_status_sql_condition() . "
                WHERE u.user_registered >= %s 
                AND u.user_registered <= %s
                AND o.customer_id IS NULL";
    }

    /**
     * Get the parameters for the SQL query
     *
     * @param string $user_input
     * @return array
     */
    protected function get_parameters(string $user_input = ''): array {
        // Parse user parameters or use defaults
        $user_params = $this->parse_user_parameters($user_input);
        $days = isset($user_params['days']) ? intval($user_params['days']) : 7;
        
        // Calculate the date range based on user input
        $end_date = current_time('mysql');
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days", strtotime($end_date)));

        return [$start_date, $end_date, $start_date, $end_date];
    }

    /**
     * Get the cache duration in seconds
     *
     * @return int
     */
    public function get_cache_duration(): int {
        return 900; // 15 minutes (shorter cache for more frequent data)
    }

    /**
     * Get the parameter placeholder text for the input field
     *
     * @return string
     */
    public function get_parameter_placeholder(): string {
        return 'days=7';
    }

    /**
     * Get the parameter input field label
     *
     * @return string
     */
    public function get_parameter_label(): string {
        return 'پارامترها (تعداد روزها)';
    }
} 