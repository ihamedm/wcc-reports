<?php
/**
 * Report Registry Class
 *
 * @package WCCReports
 */
namespace WCCREPORTS\Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ReportRegistry {

    /**
     * Array of registered reports
     *
     * @var array
     */
    private static $reports = [];

    /**
     * Register a report
     *
     * @param BaseReport $report
     * @return void
     */
    public static function register(BaseReport $report): void {
        self::$reports[$report->get_id()] = $report;
    }

    /**
     * Get all registered reports
     *
     * @return array
     */
    public static function get_all(): array {
        return self::$reports;
    }

    /**
     * Get a specific report by ID
     *
     * @param string $id
     * @return BaseReport|null
     */
    public static function get(string $id): ?BaseReport {
        return self::$reports[$id] ?? null;
    }



    /**
     * Check if a report exists
     *
     * @param string $id
     * @return bool
     */
    public static function exists(string $id): bool {
        return isset(self::$reports[$id]);
    }

    /**
     * Get all report IDs
     *
     * @return array
     */
    public static function get_ids(): array {
        return array_keys(self::$reports);
    }

    /**
     * Get reports as associative array with ID as key and title as value
     *
     * @return array
     */
    public static function get_titles(): array {
        $titles = [];
        foreach (self::$reports as $report) {
            $titles[$report->get_id()] = $report->get_title();
        }
        return $titles;
    }

    /**
     * Clear all registered reports
     *
     * @return void
     */
    public static function clear(): void {
        self::$reports = [];
    }

    /**
     * Get count of registered reports
     *
     * @return int
     */
    public static function count(): int {
        return count(self::$reports);
    }
} 