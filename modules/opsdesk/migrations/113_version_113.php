<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * OpsDesk 1.1.3 — Allow multiple carton photos per order.
 *
 * Widens carton_photo so a JSON list of filenames can be stored.
 */
class Migration_Version_113 extends App_module_migration
{
    public function up()
    {
        $CI     = &get_instance();
        $prefix = db_prefix();
        $orders = $prefix . 'opsdesk_orders';

        if ($CI->db->table_exists($orders) && $CI->db->field_exists('carton_photo', $orders)) {
            $CI->db->query("ALTER TABLE `{$orders}` MODIFY COLUMN `carton_photo` TEXT DEFAULT NULL");
        }

        update_option('opsdesk_module_version', '1.1.3');
    }
}
