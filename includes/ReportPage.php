<?php
/**
 * Report Class
 *
 * @package WCCReports
 */

namespace WCCREPORTS;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ReportPage {
    private $report_manager;

    public function __construct() {
        // Initialize the report system
        $this->initialize_reports();
        
        $this->report_manager = new Reports\ReportManager();
        add_action('admin_menu', array($this, 'add_reports_page'));
    }

    /**
     * Initialize all reports
     */
    private function initialize_reports(): void {
        // Register all reports
        Reports\ReportRegistry::register(new Reports\LastMonthCustomers());
        Reports\ReportRegistry::register(new Reports\NewCustomersLastMonth());
        Reports\ReportRegistry::register(new Reports\LastWeekInactiveUsers());
        Reports\ReportRegistry::register(new Reports\LastMonthPurchasersWithoutCategory());
    }


    /**
     * Add reports page to WordPress admin menu
     */
    public function add_reports_page() {
        add_submenu_page(
            'woocommerce',
            __('Customer Reports', WCCREPORTS_TEXT_DOMAIN), // Page title
            __('Customer Reports', WCCREPORTS_TEXT_DOMAIN), // Menu title
            'manage_options', // Capability required
            'customer-reports', // Menu slug
            array($this, 'display_reports_page'), // Function to display the page
            'dashicons-chart-bar', // Icon
            30 // Position
        );
    }

    /**
     * Display the reports page content
     */
    public function display_reports_page() {
        // Check if we're viewing details
        if (isset($_GET['view']) && $_GET['view'] === 'details' && isset($_GET['type'])) {
            $this->display_user_details_page($_GET['type']);
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Customer Reports', WCCREPORTS_TEXT_DOMAIN);?></h1>
            
            <div class="wcc-reports-container">
                <?php
                $reports = Reports\ReportRegistry::get_all();
                foreach ($reports as $report) {
                    $this->display_report_card($report);
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display a single report card
     *
     * @param Reports\BaseReport $report
     */
    private function display_report_card($report): void {
        $report_id = $report->get_id();
        $report_title = $report->get_title();
        $report_description = $report->get_description();
        $ajax_action = $report->get_ajax_action();
        $export_action = $report->get_export_ajax_action();
        $parameter_label = $report->get_parameter_label();
        $parameter_placeholder = $report->get_parameter_placeholder();
        
        ?>
        <div class="wcc-report-card">
            <div class="report-header">
                <h2><?php echo esc_html($report_title); ?></h2>
                <p><?php echo esc_html($report_description); ?></p>
            </div>
            
            <div class="report-content">
                <?php if (!empty($parameter_placeholder)): ?>
                <div class="report-parameters">
                    <label for="params-<?php echo esc_attr($report_id); ?>"><?php echo esc_html($parameter_label); ?>:</label>
                    <input type="text" 
                           id="params-<?php echo esc_attr($report_id); ?>"
                           class="report-parameter-input ltr" 
                           placeholder="<?php echo esc_attr($parameter_placeholder); ?>"
                           value="<?php echo esc_attr($parameter_placeholder); ?>"
                           data-report="<?php echo esc_attr($report_id); ?>">
                </div>
                <?php endif; ?>
                
                <div class="report-value" id="<?php echo esc_attr($report_id); ?>">
                    <span class="placeholder"><?php _e('Click on load report to show results.', WCCREPORTS_TEXT_DOMAIN); ?></span>
                </div>
                
                <div class="report-actions">
                    <button type="button" class="button button-primary generate-report-btn" 
                            data-report="<?php echo esc_attr($report_id); ?>" 
                            data-action="<?php echo esc_attr($ajax_action); ?>">
                        <span class="button-text"><?php _e('Generate Report', WCCREPORTS_TEXT_DOMAIN); ?></span>
                        <span class="spinner" style="display: none;"></span>
                    </button>

                    <button type="button" class="button button-secondary refresh-cache-btn"
                            data-report="<?php echo esc_attr($report_id); ?>">
                        <?php _e('Update cache', WCCREPORTS_TEXT_DOMAIN); ?>
                    </button>

                    <button type="button" class="button button-secondary export-users-btn" 
                            data-report-id="<?php echo esc_attr($report_id); ?>"
                            data-report-name="<?php echo esc_attr($report_title); ?>">
                        <?php _e('XLS', WCCREPORTS_TEXT_DOMAIN); ?>
                    </button>

                    <a href="<?php echo admin_url('admin.php?page=customer-reports&view=details&type=' . esc_attr($report_id)); ?>"
                       class="button button-secondary" target="_blank">
                        <?php _e('List', WCCREPORTS_TEXT_DOMAIN); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display user details page
     *
     * @param string $report_type
     */
    public function display_user_details_page($report_type) {
        $report = Reports\ReportRegistry::get($report_type);
        if (!$report) {
            wp_die(__('Report not found', WCCREPORTS_TEXT_DOMAIN));
        }

        $sort_by = sanitize_text_field($_GET['sort_by'] ?? 'display_name');
        $sort_order = sanitize_text_field($_GET['sort_order'] ?? 'ASC');
        
        // Get current sort parameters for links
        $current_sort = array(
            'display_name' => $sort_by === 'display_name' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC',
            'order_count' => $sort_by === 'order_count' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC',
            'user_registered' => $sort_by === 'user_registered' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC',
            'last_order_date' => $sort_by === 'last_order_date' ? ($sort_order === 'ASC' ? 'DESC' : 'ASC') : 'ASC'
        );

        ?>
        <div class="wrap">
            <h1>
                <a href="<?php echo admin_url('admin.php?page=customer-reports'); ?>" class="page-title-action">
                    ← <?php _e('Return to reports', WCCREPORTS_TEXT_DOMAIN); ?>
                </a>
                <?php echo esc_html($report->get_title()); ?> - <?php _e('Users Details', WCCREPORTS_TEXT_DOMAIN); ?>
            </h1>
            
            <div class="wcc-reports-user-details-container">
                <div class="wcc-reports-user-details-table-wrapper">
                    <table class="wp-list-table widefat fixed striped wcc-reports-user-details-table">
                        <thead>
                            <tr>
                                <th style="width:32px">
                                    #
                                </th>
                                <th>
                                    <a href="<?php echo admin_url('admin.php?page=customer-reports&view=details&type=' . esc_attr($report_type) . '&sort_by=display_name&sort_order=' . $current_sort['display_name']); ?>">
                                        <?php _e('Name', WCCREPORTS_TEXT_DOMAIN); ?>
                                        <?php if ($sort_by === 'display_name'): ?>
                                            <span class="sort-indicator"><?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th><?php _e('Email', WCCREPORTS_TEXT_DOMAIN); ?></th>
                                <th><?php _e('Phone', WCCREPORTS_TEXT_DOMAIN); ?></th>
                                <th>
                                    <a href="<?php echo admin_url('admin.php?page=customer-reports&view=details&type=' . esc_attr($report_type) . '&sort_by=order_count&sort_order=' . $current_sort['order_count']); ?>">
                                        <?php _e('Orders', WCCREPORTS_TEXT_DOMAIN); ?>
                                        <?php if ($sort_by === 'order_count'): ?>
                                            <span class="sort-indicator"><?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo admin_url('admin.php?page=customer-reports&view=details&type=' . esc_attr($report_type) . '&sort_by=user_registered&sort_order=' . $current_sort['user_registered']); ?>">
                                        <?php _e('Register on', WCCREPORTS_TEXT_DOMAIN); ?>
                                        <?php if ($sort_by === 'user_registered'): ?>
                                            <span class="sort-indicator"><?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?php echo admin_url('admin.php?page=customer-reports&view=details&type=' . esc_attr($report_type) . '&sort_by=last_order_date&sort_order=' . $current_sort['last_order_date']); ?>">
                                        <?php _e('Last order', WCCREPORTS_TEXT_DOMAIN); ?>
                                        <?php if ($sort_by === 'last_order_date'): ?>
                                            <span class="sort-indicator"><?php echo $sort_order === 'ASC' ? '↑' : '↓'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $users = $report->get_users_details($sort_by, $sort_order);
                            if (!empty($users)) {
                                foreach ($users as $i => $user) {
                                    $this->display_user_row($user, $i);
                                }
                            } else {
                                ?>
                                <tr>
                                    <td colspan="6"><?php _e('Not found any records for this report.', WCCREPORTS_TEXT_DOMAIN); ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Display a single user row
     *
     * @param object $user
     */
    private function display_user_row($user, $index): void {
        $order_count = $user->order_count ?? 0;
        $registration_date = $user->user_registered ? wp_date('j F Y', strtotime($user->user_registered)) : __('N/A', WCCREPORTS_TEXT_DOMAIN);
        $last_order_date = $user->last_order_date ? wp_date('j F Y', strtotime($user->last_order_date)) : __('N/A', WCCREPORTS_TEXT_DOMAIN);
        
        ?>
        <tr>
            <td><?php echo $index+1 ?></td>
            <td><?php printf('<a href="%s" target="_blank">%s</a>',
                    admin_url('user-edit.php?user_id=' . $user->ID),
                    esc_html($user->display_name ?: $user->user_login)) ; ?></td>
            <td><?php echo esc_html($user->user_email); ?></td>
            <td><?php echo esc_html($user->phone ?: __('N/A', WCCREPORTS_TEXT_DOMAIN)); ?></td>
            <td>
                <?php if ($order_count > 0): ?>
                    <a href="<?php echo admin_url('edit.php?post_type=shop_order&_customer_user=' . $user->ID); ?>" 
                       target="_blank" class="order-count-link">
                        <span class="order-count"><?php echo esc_html($order_count); ?></span>
                    </a>
                <?php else: ?>
                    <?php echo esc_html($order_count); ?>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($registration_date); ?></td>
            <td><?php echo esc_html($last_order_date); ?></td>
        </tr>
        <?php
    }
} 