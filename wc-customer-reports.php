<?php
/*
Plugin Name: Woocommerce Customer Reports Agent
Plugin URI: https://github.com/ihamedm/wcc-reports
Description: Advanced customer analytics and reporting plugin for WooCommerce. Track customer behavior, generate detailed reports, and gain insights into your customer base with performance-optimized analytics.
Version: 0.1.2
Author: Hamed Movasaqpoor
Author URI: https://github.com/ihamedm
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wccreports
Domain Path: /languages
*/

namespace WCCREPORTS;

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}


class WCC_Reports {

    public static $plugin_url;
    public static $plugin_path;
    public static $plugin_version;

    public static $plugin_text_domain;

    protected static $_instance = null;

    public static function instance()
    {
        null === self::$_instance and self::$_instance = new self;
        return self::$_instance;
    }

    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->hooks();
        $this->instances();
        $this->load_textdomain();
    }


    private function define_constants(){
        /*
         * Get Plugin Data
         */
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(__FILE__);

        self::$plugin_version = $plugin_data['Version'];
        self::$plugin_text_domain = $plugin_data['TextDomain'];

        self::$plugin_url = plugins_url('', __FILE__);

        self::$plugin_path = plugin_dir_path(__FILE__);


        /**
         * Define needed constants to use in plugin
         */
        define('WCCREPORTS_TEXT_DOMAIN', self::$plugin_text_domain);
        define('WCCREPORTS_VERSION', self::$plugin_version);
        define('WCCREPORTS_PLUGIN_PATH', self::$plugin_path);
        define('WCCREPORTS_PLUGIN_URL', self::$plugin_url);
        define('WCCREPORTS_DB_VERSION', '0.1');
        define('WCCREPORTS_CRON_VERSION', '0.1');

        define('WCCREPORTS_VERSION__OPT_KEY', '_wccreports_version');
        define('WCCREPORTS_CRON_VERSION__OPT_KEY', '_wccreports_cron_version');
        define('WCCREPORTS_DB_VERSION__OPT_KEY', '_wccreports_db_version');

    }

    private function hooks(){
        add_action('init', function() {
            $installer = new Core\Install();
            register_activation_hook(__FILE__, [$installer, 'run_install']);

            $uninstaller = new Core\Uninstall();
            register_deactivation_hook(__FILE__, [$uninstaller, 'run_uninstall']);
            
            // Add deactivation hook for cleanup
            register_deactivation_hook(__FILE__, array($this, 'plugin_deactivation'));
        });
    }

    private function includes(){
        include dirname(__FILE__) . '/vendor/autoload.php';
    }

    private function instances()
    {
        new Core\Assets();
        new ReportPage();
    }

    /**
     * Plugin deactivation cleanup
     */
    public function plugin_deactivation() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wccreports_cleanup_exports');
        
        // Clean up export files
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/wccreports-exports/';
        
        if (is_dir($export_dir)) {
            $files = glob($export_dir . '*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            // Remove the directory if it's empty
            if (is_dir($export_dir) && count(glob($export_dir . '*')) === 0) {
                rmdir($export_dir);
            }
        }
    }

    /**
     * Load plugin text domain
     */
    private function load_textdomain() {
        load_plugin_textdomain(self::$plugin_text_domain, false, self::$plugin_path . 'languages/');
    }
}


WCC_Reports::instance();
