<?php
namespace WCCREPORTS\Core;

class Install{

    public function run_install()
    {
        add_action('plugins_loaded', array(__CLASS__, 'update_plugin_version'));
    }


    public static function update_plugin_version()
    {
        $current_version = get_option(WCCREPORTS_VERSION__OPT_KEY);
        if ($current_version !== WCCREPORTS_VERSION) {
            update_option(WCCREPORTS_VERSION__OPT_KEY, WCCREPORTS_VERSION);
        }
    }

}