<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.1.2 — Inventory settings: optional stock-check bypass for order creation.
 */
class Migration_Version_112 extends App_module_migration
{
    public function up()
    {
        add_option('opsdesk_bypass_stock_check', '0');
        update_option('opsdesk_module_version', '1.1.2');
    }
}
