<?php
namespace WCCREPORTS\Core;

class Assets {

    private $plugin_version;

    private $plugin_name;

    private $plugin_url;

    public function __construct() {
        $this->plugin_name = WCCREPORTS_TEXT_DOMAIN;
        $this->plugin_version = WCCREPORTS_VERSION;
        $this->plugin_url = WCCREPORTS_PLUGIN_URL;

        if(!is_admin())
            add_action('wp_enqueue_scripts', [$this, 'load_public_assets']);

        if(is_admin())
            add_action('admin_enqueue_scripts', [$this, 'load_admin_assets']);
    }

    /**
     * Load public-facing assets
     */
    public function load_public_assets() {
        
    }

    /**
     * Load admin-facing assets
     */
    public function load_admin_assets($hook) {
        if ($hook !== 'woocommerce_page_customer-reports') {
            return;
        }

        // Enqueue Toastify CSS and JS
        wp_enqueue_style(
            $this->plugin_name . '-toastify',
            $this->plugin_url . '/assets/css/toastify.min.css',
            array(),
            $this->plugin_version
        );

        wp_enqueue_script(
            $this->plugin_name. '-toastify',
            $this->plugin_url . '/assets/js/toastify.js',
            array('jquery'),
            $this->plugin_version,
            false
        );

        // Enqueue main admin scripts and styles
        wp_enqueue_script(
            $this->plugin_name. '-reports-admin',
            $this->plugin_url . '/assets/js/admin-reports.js',
            array('jquery', $this->plugin_name. '-toastify'),
            $this->plugin_version,
            true
        );

        wp_localize_script($this->plugin_name. '-reports-admin', 'wccreports_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wccreports_reports_nonce'),
            'loading_text' => '...',
            'error_text' => 'خطا در بارگذاری گزارش'
        ));

        wp_enqueue_style(
            $this->plugin_name . '-reports-admin',
            $this->plugin_url . '/assets/css/admin-reports.css',
            array(),
            $this->plugin_version
        );
    }
}
