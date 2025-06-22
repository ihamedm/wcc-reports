<?php
namespace WCCREPORTS\Core;

class Uninstall{

    public function run_uninstall()
    {
        self::remove_plugin_options();

        $uninstaller = new self();
    }

    private static function remove_plugin_options()
    {

    }

    public function clear_scheduled(){

    }

}