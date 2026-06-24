<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Baseline migration for OpsDesk 1.0.0.
 * Schema is created by install.php on activation; this is a no-op version marker.
 */
class Migration_Version_100 extends App_module_migration
{
    public function up()
    {
        $CI = &get_instance();

        if (!get_option('opsdesk_module_version')) {
            add_option('opsdesk_module_version', '1.0.0');
        }
    }
}
